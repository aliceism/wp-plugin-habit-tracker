<?php

namespace HabitTracker\Domain\Analytics;

use HabitTracker\Domain\Rules\HabitRules;
use HabitTracker\Infrastructure\Persistence\WpdbCheckinRepository;
use HabitTracker\Infrastructure\Persistence\WpdbUserHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class DashboardAnalytics
{
    private WpdbUserHabitRepository $user_habits;

    private WpdbCheckinRepository $checkins;

    public function __construct(WpdbUserHabitRepository $user_habits, WpdbCheckinRepository $checkins)
    {
        $this->user_habits = $user_habits;
        $this->checkins = $checkins;
    }

    public function getContext(int $user_id, string $redirect_url): array
    {
        $today = wp_date('Y-m-d');
        $history_dates = $this->buildHistoryDates($today, HabitRules::HISTORY_DAYS_DASHBOARD);
        $start_date = $history_dates[0] ?? $today;
        $month_dates = $this->buildCurrentMonthDates($today);
        $month_start = $month_dates[0] ?? $today;
        $month_end = $month_dates !== [] ? $month_dates[count($month_dates) - 1] : $today;
        $active_habits = $this->user_habits->findActiveByUser($user_id);
        $active_habit_ids = [];

        foreach ($active_habits as $active_habit) {
            $active_habit_id = isset($active_habit->id) ? (int) $active_habit->id : 0;

            if ($active_habit_id <= 0) {
                continue;
            }

            $active_habit_ids[$active_habit_id] = true;
        }

        $today_map = $this->checkins->getCompletedMapForDate($user_id, $today);
        $history_map = $this->checkins->getCompletedMapForRange($user_id, $start_date, $today);
        $month_map = $this->checkins->getCompletedMapForRange($user_id, $month_start, $month_end);
        $today_map = array_intersect_key($today_map, $active_habit_ids);
        $history_map = array_intersect_key($history_map, $active_habit_ids);
        $month_map = array_intersect_key($month_map, $active_habit_ids);

        return [
            'today' => $today,
            'history_dates' => $history_dates,
            'month_dates' => $month_dates,
            'month_weeks' => $this->buildWeekGroups($month_dates),
            'month_label' => wp_date('F Y', strtotime($today)),
            'days_elapsed' => (int) wp_date('j', strtotime($today)),
            'redirect_url' => $redirect_url,
            'today_map' => $today_map,
            'history_map' => $history_map,
            'month_map' => $month_map,
            'habits_by_category' => $this->groupHabitsByCategory($active_habits),
            'metrics' => $this->buildMetrics(
                $user_id,
                count($active_habits),
                $today,
                count($today_map),
                $history_map
            ),
        ];
    }

    public function buildMonthRowsFromContext(array $context): array
    {
        $ordered_habits = $this->flattenHabitsByCategory($context['habits_by_category'] ?? []);

        return $this->buildMonthRows(
            $ordered_habits,
            $context['month_dates'] ?? [],
            $context['month_map'] ?? [],
            $context['history_map'] ?? [],
            (string) ($context['today'] ?? wp_date('Y-m-d'))
        );
    }

    public function buildTopHabits(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            if ($left['progress_percent'] === $right['progress_percent']) {
                if ($left['completed'] === $right['completed']) {
                    if ($left['streak'] === $right['streak']) {
                        return strcmp((string) $left['name'], (string) $right['name']);
                    }

                    return (int) $right['streak'] <=> (int) $left['streak'];
                }

                return (int) $right['completed'] <=> (int) $left['completed'];
            }

            return (int) $right['progress_percent'] <=> (int) $left['progress_percent'];
        });

        return $rows;
    }

    public function buildActiveStreaks(array $rows): array
    {
        $active = array_values(array_filter($rows, static function (array $row): bool {
            return (int) ($row['streak'] ?? 0) > 0;
        }));

        usort($active, static function (array $left, array $right): int {
            if ($left['streak'] === $right['streak']) {
                if ($left['progress_percent'] === $right['progress_percent']) {
                    return strcmp((string) $left['name'], (string) $right['name']);
                }

                return (int) $right['progress_percent'] <=> (int) $left['progress_percent'];
            }

            return (int) $right['streak'] <=> (int) $left['streak'];
        });

        if ($active === []) {
            return [];
        }

        $max_streak = max(array_map(static function (array $row): int {
            return max(1, (int) ($row['streak'] ?? 0));
        }, $active));

        foreach ($active as &$row) {
            $row['streak_percent'] = (int) round((((int) ($row['streak'] ?? 0)) / $max_streak) * 100);
        }
        unset($row);

        return $active;
    }

    private function buildMetrics(
        int $user_id,
        int $active_habits_count,
        string $today,
        int $checked_today,
        array $history_map
    ): array {
        $completed_month = $this->countCompletedFromHistoryMap($history_map);
        $monthly_slots = $active_habits_count * HabitRules::HISTORY_DAYS_DASHBOARD;
        $monthly_percent = $monthly_slots > 0 ? (int) round(($completed_month / $monthly_slots) * 100) : 0;
        $monthly_percent = max(0, min(100, $monthly_percent));
        $today_percent = $active_habits_count > 0 ? (int) round(($checked_today / $active_habits_count) * 100) : 0;
        $today_percent = max(0, min(100, $today_percent));
        $active_days = $this->countActiveDaysFromHistoryMap($history_map);
        $active_days_percent = HabitRules::HISTORY_DAYS_DASHBOARD > 0
            ? (int) round(($active_days / HabitRules::HISTORY_DAYS_DASHBOARD) * 100)
            : 0;
        $active_days_percent = max(0, min(100, $active_days_percent));
        $streak_days = $this->calculateStreak($user_id, $today);
        $streak_percent = HabitRules::HISTORY_DAYS_DASHBOARD > 0
            ? (int) round(($streak_days / HabitRules::HISTORY_DAYS_DASHBOARD) * 100)
            : 0;
        $streak_percent = max(0, min(100, $streak_percent));
        $remaining_checks = max(0, $monthly_slots - $completed_month);

        return [
            'streak_days' => $streak_days,
            'streak_percent' => $streak_percent,
            'monthly_consistency_percent' => $monthly_percent,
            'active_habits' => $active_habits_count,
            'checked_today' => $checked_today,
            'today_completion_percent' => $today_percent,
            'completed_checks' => $completed_month,
            'total_slots' => $monthly_slots,
            'remaining_checks' => $remaining_checks,
            'active_days' => $active_days,
            'active_days_percent' => $active_days_percent,
        ];
    }

    private function calculateStreak(int $user_id, string $today): int
    {
        $max_days = 365;
        $dates = $this->checkins->getDistinctCompletedDatesUntil($user_id, $today, $max_days);

        if ($dates === []) {
            return 0;
        }

        return HabitMath::calculateDateSetStreak(array_fill_keys($dates, true), $today, $max_days);
    }

    private function groupHabitsByCategory(array $active_habits): array
    {
        $grouped = array_fill_keys(HabitRules::categoryKeys(false), []);

        foreach ($active_habits as $habit) {
            $category_key = HabitRules::normalizeCategoryKey((string) ($habit->category ?? ''));

            $grouped[$category_key][] = $habit;
        }

        return $grouped;
    }

    private function flattenHabitsByCategory(array $habits_by_category): array
    {
        $ordered = [];
        $category_order = HabitRules::categoryKeys(false);

        foreach ($category_order as $category_key) {
            $category_habits = $habits_by_category[$category_key] ?? [];

            foreach ($category_habits as $habit) {
                $ordered[] = [
                    'habit' => $habit,
                    'category' => $category_key,
                ];
            }
        }

        return $ordered;
    }

    private function buildMonthRows(
        array $ordered_habits,
        array $month_dates,
        array $month_map,
        array $history_map,
        string $today
    ): array {
        $rows = [];
        $month_start = $month_dates[0] ?? $today;
        $month_end = $month_dates !== [] ? $month_dates[count($month_dates) - 1] : $today;

        foreach ($ordered_habits as $entry) {
            $habit = $entry['habit'];
            $category_key = (string) $entry['category'];
            $habit_id = isset($habit->id) ? (int) $habit->id : 0;
            $habit_month_map = isset($month_map[$habit_id]) && is_array($month_map[$habit_id])
                ? $month_map[$habit_id]
                : [];
            $habit_history = isset($history_map[$habit_id]) && is_array($history_map[$habit_id])
                ? $history_map[$habit_id]
                : [];
            $habit_start_date = $this->resolveHabitStartDate($habit, $month_start);
            $tracking_start = $habit_start_date > $month_start ? $habit_start_date : $month_start;
            $frequency_type = $this->normalizeFrequencyType((string) ($habit->frequency_type ?? HabitRules::FREQUENCY_DAILY));
            $target_count = $this->normalizeTargetCount((int) ($habit->target_count ?? HabitRules::DEFAULT_TARGET_COUNT));
            $filtered_month_map = $this->filterDateMapForRange($habit_month_map, $tracking_start, $month_end);
            $filtered_history_map = $this->filterDateMapForRange($habit_history, $habit_start_date, $today);
            $completed = 0;

            foreach ($filtered_month_map as $date => $is_checked) {
                unset($is_checked);

                if ((string) $date <= $today) {
                    $completed++;
                }
            }

            $target_total = $this->calculateTargetForPeriod(
                $frequency_type,
                $target_count,
                $tracking_start,
                $month_end
            );
            $progress_percent = $target_total > 0 ? (int) round(($completed / $target_total) * 100) : 0;
            $progress_percent = max(0, min(100, $progress_percent));

            $rows[] = [
                'id' => $habit_id,
                'name' => (string) ($habit->name ?? ''),
                'category' => $category_key,
                'frequency_type' => $frequency_type,
                'target_count' => $target_count,
                'target_total' => $target_total,
                'day_map' => $filtered_month_map,
                'completed' => $completed,
                'progress_percent' => $progress_percent,
                'streak' => $this->calculateHabitStreakFromHistory(
                    $filtered_history_map,
                    $today,
                    $frequency_type,
                    $target_count
                ),
                'streak_unit' => $frequency_type === HabitRules::FREQUENCY_WEEKLY ? 'week' : 'day',
            ];
        }

        return $rows;
    }

    private function buildCurrentMonthDates(string $today): array
    {
        $today_ts = strtotime($today);

        if (! is_int($today_ts)) {
            return [];
        }

        $month_start = wp_date('Y-m-01', $today_ts);
        $month_end = wp_date('Y-m-t', $today_ts);

        return $this->buildDateRange($month_start, $month_end);
    }

    private function buildWeekGroups(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $groups = [];
        $week_number = 1;

        foreach (array_chunk($dates, 7) as $chunk) {
            $groups[] = [
                'label' => sprintf(esc_html__('Week %d', 'habit-tracker'), $week_number),
                'count' => count($chunk),
            ];

            $week_number++;
        }

        return $groups;
    }

    private function buildDateRange(string $start_date, string $end_date): array
    {
        return HabitMath::buildDateRange($start_date, $end_date);
    }

    private function calculateHabitStreakFromHistory(
        array $habit_history,
        string $today,
        string $frequency_type,
        int $target_count
    ): int {
        if ($habit_history === []) {
            return 0;
        }

        if ($frequency_type === HabitRules::FREQUENCY_WEEKLY) {
            return $this->calculateWeeklyHabitStreakFromHistory(
                $habit_history,
                $today,
                $target_count
            );
        }

        return $this->calculateDailyHabitStreakFromHistory($habit_history, $today);
    }

    private function calculateDailyHabitStreakFromHistory(
        array $habit_history,
        string $today
    ): int {
        return HabitMath::calculateDailyHabitStreak(
            $habit_history,
            $today,
            HabitRules::HISTORY_DAYS_DASHBOARD
        );
    }

    private function calculateWeeklyHabitStreakFromHistory(
        array $habit_history,
        string $today,
        int $target_count
    ): int {
        return HabitMath::calculateWeeklyHabitStreak(
            $habit_history,
            $today,
            $target_count,
            HabitRules::HISTORY_DAYS_DASHBOARD
        );
    }

    private function calculateTargetForPeriod(
        string $frequency_type,
        int $target_count,
        string $start_date,
        string $end_date
    ): int {
        return HabitMath::calculateTargetForPeriod(
            $frequency_type,
            $target_count,
            $start_date,
            $end_date
        );
    }

    private function resolveHabitStartDate(object $habit, string $fallback): string
    {
        $start_date = isset($habit->start_date) ? (string) $habit->start_date : '';
        $start_ts = strtotime($start_date);

        if ($start_date === '' || ! is_int($start_ts)) {
            return $fallback;
        }

        return wp_date('Y-m-d', $start_ts);
    }

    private function filterDateMapForRange(array $date_map, string $start_date, string $end_date): array
    {
        if ($date_map === []) {
            return [];
        }

        $filtered = [];

        foreach ($date_map as $date => $is_checked) {
            unset($is_checked);
            $date_key = (string) $date;

            if ($date_key < $start_date || $date_key > $end_date) {
                continue;
            }

            $filtered[$date_key] = true;
        }

        return $filtered;
    }

    private function normalizeFrequencyType(string $frequency_type): string
    {
        return HabitRules::normalizeFrequencyType($frequency_type);
    }

    private function normalizeTargetCount(int $target_count): int
    {
        return HabitRules::normalizeTargetCount($target_count);
    }

    private function countCompletedFromHistoryMap(array $history_map): int
    {
        $count = 0;

        foreach ($history_map as $habit_history) {
            if (! is_array($habit_history)) {
                continue;
            }

            $count += count($habit_history);
        }

        return $count;
    }

    private function countActiveDaysFromHistoryMap(array $history_map): int
    {
        $dates = [];

        foreach ($history_map as $habit_history) {
            if (! is_array($habit_history)) {
                continue;
            }

            foreach ($habit_history as $date => $flag) {
                unset($flag);
                $dates[(string) $date] = true;
            }
        }

        return count($dates);
    }

    private function buildHistoryDates(string $today, int $days): array
    {
        $dates = [];
        $today_ts = strtotime($today);

        if (! is_int($today_ts) || $days <= 0) {
            return $dates;
        }

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $dates[] = wp_date('Y-m-d', strtotime('-' . $offset . ' days', $today_ts));
        }

        return $dates;
    }
}
