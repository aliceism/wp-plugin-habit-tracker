<?php

namespace HabitTracker\Domain\Analytics;

use HabitTracker\Domain\Rules\HabitRules;
use HabitTracker\Infrastructure\Persistence\WpdbCheckinRepository;
use HabitTracker\Infrastructure\Persistence\WpdbUserHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class ProgressAnalytics
{
    private WpdbUserHabitRepository $user_habits;

    private WpdbCheckinRepository $checkins;

    public function __construct(WpdbUserHabitRepository $user_habits, WpdbCheckinRepository $checkins)
    {
        $this->user_habits = $user_habits;
        $this->checkins = $checkins;
    }

    public function buildContext(int $user_id): array
    {
        $today = wp_date('Y-m-d');
        $today_ts = strtotime($today);

        if (! is_int($today_ts)) {
            $today_ts = time();
            $today = wp_date('Y-m-d', $today_ts);
        }

        $month_start = wp_date('Y-m-01', $today_ts);
        $week_start_ts = strtotime('monday this week', $today_ts);

        if (! is_int($week_start_ts)) {
            $week_start_ts = $today_ts;
        }

        $week_start = wp_date('Y-m-d', $week_start_ts);
        $history_start = wp_date('Y-m-d', strtotime('-' . (HabitRules::HISTORY_DAYS_PROGRESS - 1) . ' days', $today_ts));
        $week_dates = $this->buildDateRange($week_start, $today);
        $month_dates = $this->buildDateRange($month_start, $today);
        $active_habits = $this->user_habits->findActiveByUser($user_id);
        $active_habit_ids = [];

        foreach ($active_habits as $habit) {
            $habit_id = isset($habit->id) ? (int) $habit->id : 0;

            if ($habit_id <= 0) {
                continue;
            }

            $active_habit_ids[$habit_id] = true;
        }

        $today_map = array_intersect_key($this->checkins->getCompletedMapForDate($user_id, $today), $active_habit_ids);
        $week_map = array_intersect_key($this->checkins->getCompletedMapForRange($user_id, $week_start, $today), $active_habit_ids);
        $month_map = array_intersect_key($this->checkins->getCompletedMapForRange($user_id, $month_start, $today), $active_habit_ids);
        $history_map = array_intersect_key($this->checkins->getCompletedMapForRange($user_id, $history_start, $today), $active_habit_ids);
        $habits_by_category = $this->groupHabitsByCategory($active_habits);
        $category_stats = $this->buildCategoryStats(
            $habits_by_category,
            $today_map,
            $week_map,
            $month_map,
            $history_map,
            $week_start,
            $month_start,
            $today
        );
        $habit_rows = $this->buildHabitPerformanceRows(
            $active_habits,
            $week_map,
            $month_map,
            $week_start,
            $month_start,
            $today
        );

        return [
            'category_stats' => $category_stats,
            'week_chart' => $this->buildActivityChartData($active_habits, $history_map, $week_dates),
            'month_chart' => $this->buildActivityChartData($active_habits, $history_map, $month_dates),
            'habit_rows' => $habit_rows,
            'has_active_habits' => $this->hasActiveHabits($category_stats),
            'summary' => $this->buildSummary($category_stats, $habit_rows),
        ];
    }

    private function buildCategoryStats(
        array $habits_by_category,
        array $today_map,
        array $week_map,
        array $month_map,
        array $history_map,
        string $week_start,
        string $month_start,
        string $today
    ): array {
        $stats = [];
        $labels = $this->categoryLabels();
        $days_elapsed = max(1, (int) wp_date('j', strtotime($today)));

        foreach ($labels as $category_key => $label) {
            $category_habits = $habits_by_category[$category_key] ?? [];
            $completed_week = 0;
            $target_week = 0;
            $completed_month = 0;
            $target_month = 0;
            $checked_today = 0;
            $today_target = 0;
            $month_active_days = [];
            $history_day_set = [];

            foreach ($category_habits as $habit) {
                $habit_id = isset($habit->id) ? (int) $habit->id : 0;

                if ($habit_id <= 0) {
                    continue;
                }

                $habit_start = $this->resolveHabitStartDate($habit, $month_start);
                $frequency_type = $this->normalizeFrequencyType((string) ($habit->frequency_type ?? HabitRules::FREQUENCY_DAILY));
                $target_count = $this->normalizeTargetCount((int) ($habit->target_count ?? HabitRules::DEFAULT_TARGET_COUNT));
                $week_tracking_start = $habit_start > $week_start ? $habit_start : $week_start;
                $month_tracking_start = $habit_start > $month_start ? $habit_start : $month_start;
                $habit_week_map = isset($week_map[$habit_id]) && is_array($week_map[$habit_id]) ? $week_map[$habit_id] : [];
                $habit_month_map = isset($month_map[$habit_id]) && is_array($month_map[$habit_id]) ? $month_map[$habit_id] : [];
                $habit_history_map = isset($history_map[$habit_id]) && is_array($history_map[$habit_id]) ? $history_map[$habit_id] : [];

                if ($habit_start <= $today) {
                    $today_target++;
                }

                if (isset($today_map[$habit_id])) {
                    $checked_today++;
                }

                if ($week_tracking_start <= $today) {
                    $target_week += $this->calculateTargetForPeriod(
                        $frequency_type,
                        $target_count,
                        $week_tracking_start,
                        $today
                    );
                }

                if ($month_tracking_start <= $today) {
                    $target_month += $this->calculateTargetForPeriod(
                        $frequency_type,
                        $target_count,
                        $month_tracking_start,
                        $today
                    );
                }

                $filtered_week = $this->filterDateMapForRange($habit_week_map, $week_tracking_start, $today);
                $filtered_month = $this->filterDateMapForRange($habit_month_map, $month_tracking_start, $today);
                $filtered_history = $this->filterDateMapForRange($habit_history_map, $habit_start, $today);
                $completed_week += count($filtered_week);
                $completed_month += count($filtered_month);

                foreach ($filtered_month as $date => $flag) {
                    unset($flag);
                    $month_active_days[(string) $date] = true;
                }

                foreach ($filtered_history as $date => $flag) {
                    unset($flag);
                    $history_day_set[(string) $date] = true;
                }
            }

            $week_percent = $target_week > 0 ? (int) round(($completed_week / $target_week) * 100) : 0;
            $month_percent = $target_month > 0 ? (int) round(($completed_month / $target_month) * 100) : 0;
            $today_percent = $today_target > 0 ? (int) round(($checked_today / $today_target) * 100) : 0;
            $consistency_percent = $days_elapsed > 0
                ? (int) round((count($month_active_days) / $days_elapsed) * 100)
                : 0;

            $stats[] = [
                'key' => $category_key,
                'label' => $label,
                'active_habits' => count($category_habits),
                'completed_week' => $completed_week,
                'target_week' => $target_week,
                'week_percent' => $this->clampPercent($week_percent),
                'completed_month' => $completed_month,
                'target_month' => $target_month,
                'month_percent' => $this->clampPercent($month_percent),
                'checked_today' => $checked_today,
                'today_target' => $today_target,
                'today_percent' => $this->clampPercent($today_percent),
                'streak_days' => $this->calculateDateSetStreak($history_day_set, $today, HabitRules::HISTORY_DAYS_PROGRESS),
                'consistency_percent' => $this->clampPercent($consistency_percent),
            ];
        }

        return $stats;
    }

    private function buildSummary(array $category_stats, array $habit_rows): array
    {
        $total_completed_month = 0;
        $total_target_month = 0;
        $active_rows = [];

        foreach ($category_stats as $row) {
            $total_completed_month += (int) ($row['completed_month'] ?? 0);
            $total_target_month += (int) ($row['target_month'] ?? 0);

            if ((int) ($row['active_habits'] ?? 0) > 0) {
                $active_rows[] = $row;
            }
        }

        $total_percent = $total_target_month > 0
            ? $this->clampPercent((int) round(($total_completed_month / $total_target_month) * 100))
            : 0;

        return [
            'total_completed_month' => $total_completed_month,
            'total_target_month' => $total_target_month,
            'total_percent' => $total_percent,
            'best_category' => $this->pickBestCategory($active_rows),
            'focus_category' => $this->pickFocusCategory($active_rows),
            'weekly_leader_category' => $this->pickWeeklyLeaderCategory($active_rows),
            'best_habit' => $this->pickBestHabit($habit_rows),
            'focus_habit' => $this->pickFocusHabit($habit_rows),
            'weekly_leader_habit' => $this->pickWeeklyLeaderHabit($habit_rows),
        ];
    }

    private function buildActivityChartData(array $active_habits, array $history_map, array $dates): array
    {
        if ($dates === []) {
            return [
                'dates' => [],
                'rows' => [],
                'points' => [],
                'max_completed' => 1,
            ];
        }

        $rows = [];
        $points = [];
        $max_completed = 0;
        $fallback_start = (string) $dates[0];

        foreach ($dates as $date) {
            $date_key = (string) $date;
            $active = 0;
            $completed = 0;

            foreach ($active_habits as $habit) {
                $habit_id = isset($habit->id) ? (int) $habit->id : 0;

                if ($habit_id <= 0) {
                    continue;
                }

                $habit_start = $this->resolveHabitStartDate($habit, $fallback_start);

                if ($habit_start > $date_key) {
                    continue;
                }

                $active++;

                if (
                    isset($history_map[$habit_id]) &&
                    is_array($history_map[$habit_id]) &&
                    isset($history_map[$habit_id][$date_key])
                ) {
                    $completed++;
                }
            }

            $percent = $active > 0 ? $this->clampPercent((int) round(($completed / $active) * 100)) : 0;
            $points[] = $percent;
            $max_completed = max($max_completed, $completed);
            $rows[] = [
                'date' => $date_key,
                'day_label' => wp_date('D', strtotime($date_key)),
                'completed' => $completed,
                'active' => $active,
                'percent' => $percent,
            ];
        }

        return [
            'dates' => $dates,
            'rows' => $rows,
            'points' => $points,
            'max_completed' => max(1, $max_completed),
        ];
    }

    private function buildHabitPerformanceRows(
        array $active_habits,
        array $week_map,
        array $month_map,
        string $week_start,
        string $month_start,
        string $today
    ): array {
        $rows = [];

        foreach ($active_habits as $habit) {
            $habit_id = isset($habit->id) ? (int) $habit->id : 0;

            if ($habit_id <= 0) {
                continue;
            }

            $habit_start = $this->resolveHabitStartDate($habit, $month_start);
            $week_tracking_start = $habit_start > $week_start ? $habit_start : $week_start;
            $month_tracking_start = $habit_start > $month_start ? $habit_start : $month_start;
            $frequency_type = $this->normalizeFrequencyType((string) ($habit->frequency_type ?? HabitRules::FREQUENCY_DAILY));
            $target_count = $this->normalizeTargetCount((int) ($habit->target_count ?? HabitRules::DEFAULT_TARGET_COUNT));
            $habit_week_map = isset($week_map[$habit_id]) && is_array($week_map[$habit_id]) ? $week_map[$habit_id] : [];
            $habit_month_map = isset($month_map[$habit_id]) && is_array($month_map[$habit_id]) ? $month_map[$habit_id] : [];
            $filtered_week = $this->filterDateMapForRange($habit_week_map, $week_tracking_start, $today);
            $filtered_month = $this->filterDateMapForRange($habit_month_map, $month_tracking_start, $today);
            $completed_week = count($filtered_week);
            $completed_month = count($filtered_month);
            $target_week = $week_tracking_start <= $today
                ? $this->calculateTargetForPeriod(
                    $frequency_type,
                    $target_count,
                    $week_tracking_start,
                    $today
                )
                : 0;
            $target_month = $month_tracking_start <= $today
                ? $this->calculateTargetForPeriod(
                    $frequency_type,
                    $target_count,
                    $month_tracking_start,
                    $today
                )
                : 0;
            $week_percent = $target_week > 0 ? $this->clampPercent((int) round(($completed_week / $target_week) * 100)) : 0;
            $month_percent = $target_month > 0 ? $this->clampPercent((int) round(($completed_month / $target_month) * 100)) : 0;

            $rows[] = [
                'id' => $habit_id,
                'name' => (string) ($habit->name ?? ''),
                'category' => $this->normalizeCategoryKey((string) ($habit->category ?? '')),
                'completed_week' => $completed_week,
                'target_week' => $target_week,
                'week_percent' => $week_percent,
                'completed_month' => $completed_month,
                'target_month' => $target_month,
                'month_percent' => $month_percent,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            if ((int) $left['month_percent'] === (int) $right['month_percent']) {
                if ((int) $left['week_percent'] === (int) $right['week_percent']) {
                    if ((int) $left['completed_month'] === (int) $right['completed_month']) {
                        return strcmp((string) $left['name'], (string) $right['name']);
                    }

                    return (int) $right['completed_month'] <=> (int) $left['completed_month'];
                }

                return (int) $right['week_percent'] <=> (int) $left['week_percent'];
            }

            return (int) $right['month_percent'] <=> (int) $left['month_percent'];
        });

        return $rows;
    }

    private function pickBestCategory(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        usort($rows, static function (array $left, array $right): int {
            if ((int) $left['month_percent'] === (int) $right['month_percent']) {
                return (int) $right['completed_month'] <=> (int) $left['completed_month'];
            }

            return (int) $right['month_percent'] <=> (int) $left['month_percent'];
        });

        return $rows[0] ?? null;
    }

    private function pickFocusCategory(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        usort($rows, static function (array $left, array $right): int {
            if ((int) $left['month_percent'] === (int) $right['month_percent']) {
                return (int) $left['completed_month'] <=> (int) $right['completed_month'];
            }

            return (int) $left['month_percent'] <=> (int) $right['month_percent'];
        });

        return $rows[0] ?? null;
    }

    private function pickWeeklyLeaderCategory(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $sorted = $rows;

        usort($sorted, static function (array $left, array $right): int {
            if ((int) $left['week_percent'] === (int) $right['week_percent']) {
                if ((int) $left['month_percent'] === (int) $right['month_percent']) {
                    return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
                }

                return (int) $right['month_percent'] <=> (int) $left['month_percent'];
            }

            return (int) $right['week_percent'] <=> (int) $left['week_percent'];
        });

        return $sorted[0] ?? null;
    }

    private function pickBestHabit(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $sorted = $rows;

        usort($sorted, static function (array $left, array $right): int {
            if ((int) $left['month_percent'] === (int) $right['month_percent']) {
                if ((int) $left['week_percent'] === (int) $right['week_percent']) {
                    return strcmp((string) $left['name'], (string) $right['name']);
                }

                return (int) $right['week_percent'] <=> (int) $left['week_percent'];
            }

            return (int) $right['month_percent'] <=> (int) $left['month_percent'];
        });

        return $sorted[0] ?? null;
    }

    private function pickFocusHabit(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $sorted = $rows;

        usort($sorted, static function (array $left, array $right): int {
            if ((int) $left['month_percent'] === (int) $right['month_percent']) {
                if ((int) $left['week_percent'] === (int) $right['week_percent']) {
                    return strcmp((string) $left['name'], (string) $right['name']);
                }

                return (int) $left['week_percent'] <=> (int) $right['week_percent'];
            }

            return (int) $left['month_percent'] <=> (int) $right['month_percent'];
        });

        return $sorted[0] ?? null;
    }

    private function pickWeeklyLeaderHabit(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $sorted = $rows;

        usort($sorted, static function (array $left, array $right): int {
            if ((int) $left['week_percent'] === (int) $right['week_percent']) {
                if ((int) $left['month_percent'] === (int) $right['month_percent']) {
                    return strcmp((string) $left['name'], (string) $right['name']);
                }

                return (int) $right['month_percent'] <=> (int) $left['month_percent'];
            }

            return (int) $right['week_percent'] <=> (int) $left['week_percent'];
        });

        return $sorted[0] ?? null;
    }

    private function hasActiveHabits(array $category_stats): bool
    {
        foreach ($category_stats as $row) {
            if ((int) ($row['active_habits'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
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

    private function categoryLabels(): array
    {
        return HabitRules::categoryLabels(false);
    }

    private function normalizeCategoryKey(string $category_key): string
    {
        return HabitRules::normalizeCategoryKey($category_key);
    }

    private function calculateDateSetStreak(array $date_set, string $today, int $max_days): int
    {
        return HabitMath::calculateDateSetStreak($date_set, $today, $max_days);
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

    private function buildDateRange(string $start_date, string $end_date): array
    {
        return HabitMath::buildDateRange($start_date, $end_date);
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

    private function clampPercent(int $value): int
    {
        return max(0, min(100, $value));
    }
}
