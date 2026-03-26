<?php

namespace HabitTracker\Domain\Analytics;

use HabitTracker\Domain\Rules\HabitRules;

if (! defined('ABSPATH')) {
    exit;
}

final class HabitMath
{
    public const FREQUENCY_DAILY = HabitRules::FREQUENCY_DAILY;
    public const FREQUENCY_WEEKLY = HabitRules::FREQUENCY_WEEKLY;

    public static function calculateDateSetStreak(array $date_set, string $today, int $max_days): int
    {
        if ($date_set === [] || $max_days <= 0) {
            return 0;
        }

        $today_date = self::parseDate($today);

        if (! $today_date instanceof \DateTimeImmutable) {
            return 0;
        }

        $today_key = $today_date->format('Y-m-d');
        $streak = 0;
        $start_offset = isset($date_set[$today_key]) ? 0 : 1;

        for ($offset = $start_offset; $offset < ($max_days + $start_offset); $offset++) {
            $date = $today_date->modify(sprintf('-%d day', $offset));

            if (! $date instanceof \DateTimeImmutable) {
                break;
            }

            $date_key = $date->format('Y-m-d');

            if (! isset($date_set[$date_key])) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    public static function calculateDailyHabitStreak(
        array $habit_history,
        string $today,
        int $target_days_mask,
        int $history_days
    ): int {
        if ($habit_history === [] || $history_days <= 0) {
            return 0;
        }

        $today_date = self::parseDate($today);

        if (! $today_date instanceof \DateTimeImmutable) {
            return 0;
        }

        $target_days_mask = HabitRules::normalizeTargetDaysMask($target_days_mask);

        $date_set = self::normalizeHistoryToDateSet($habit_history);

        if ($date_set === []) {
            return 0;
        }

        $today_key = $today_date->format('Y-m-d');
        $is_today_enabled = self::isDateEnabledByMask($today_key, $target_days_mask);
        $start_offset = ($is_today_enabled && ! isset($date_set[$today_key])) ? 1 : 0;
        $streak = 0;

        for ($offset = $start_offset; $offset < ($history_days + $start_offset); $offset++) {
            $date = $today_date->modify(sprintf('-%d day', $offset));

            if (! $date instanceof \DateTimeImmutable) {
                break;
            }

            $date_key = $date->format('Y-m-d');

            if (! self::isDateEnabledByMask($date_key, $target_days_mask)) {
                continue;
            }

            if (! isset($date_set[$date_key])) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    public static function calculateWeeklyHabitStreak(
        array $habit_history,
        string $today,
        int $target_count,
        int $target_days_mask,
        int $history_days
    ): int {
        if ($habit_history === [] || $history_days <= 0) {
            return 0;
        }

        $today_date = self::parseDate($today);

        if (! $today_date instanceof \DateTimeImmutable) {
            return 0;
        }

        $target_count = HabitRules::normalizeTargetCount($target_count);
        $target_days_mask = HabitRules::normalizeTargetDaysMask($target_days_mask);
        $weekly_counts = self::buildWeeklyCompletionCounts($habit_history, $target_days_mask);

        if ($weekly_counts === []) {
            return 0;
        }

        $streak = 0;
        $max_weeks = max(1, (int) ceil($history_days / 7));
        $current_week_key = $today_date->format('o-W');
        $current_week_completed = (int) ($weekly_counts[$current_week_key] ?? 0) >= $target_count;
        $start_offset = $current_week_completed ? 0 : 1;

        for ($offset = $start_offset; $offset < ($max_weeks + $start_offset); $offset++) {
            $week_date = $today_date->modify(sprintf('-%d week', $offset));

            if (! $week_date instanceof \DateTimeImmutable) {
                break;
            }

            $week_key = $week_date->format('o-W');
            $completed = (int) ($weekly_counts[$week_key] ?? 0);

            if ($completed < $target_count) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    public static function calculateTargetForPeriod(
        string $frequency_type,
        int $target_count,
        int $target_days_mask,
        string $start_date,
        string $end_date
    ): int {
        $target_count = HabitRules::normalizeTargetCount($target_count);
        $target_days_mask = HabitRules::normalizeTargetDaysMask($target_days_mask);
        $period_dates = self::buildDateRange($start_date, $end_date);

        if ($period_dates === []) {
            return 0;
        }

        if (self::normalizeFrequencyType($frequency_type) === self::FREQUENCY_WEEKLY) {
            $weeks = [];

            foreach ($period_dates as $period_date) {
                if (! self::isDateEnabledByMask($period_date, $target_days_mask)) {
                    continue;
                }

                $date = self::parseDate($period_date);

                if (! $date instanceof \DateTimeImmutable) {
                    continue;
                }

                $weeks[$date->format('o-W')] = true;
            }

            return count($weeks) * $target_count;
        }

        $eligible_days = 0;

        foreach ($period_dates as $period_date) {
            if (self::isDateEnabledByMask($period_date, $target_days_mask)) {
                $eligible_days++;
            }
        }

        return $eligible_days * $target_count;
    }

    public static function buildDateRange(string $start_date, string $end_date): array
    {
        $start = self::parseDate($start_date);
        $end = self::parseDate($end_date);

        if (! $start instanceof \DateTimeImmutable || ! $end instanceof \DateTimeImmutable || $end < $start) {
            return [];
        }

        $dates = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $dates[] = $cursor->format('Y-m-d');
            $next = $cursor->modify('+1 day');

            if (! $next instanceof \DateTimeImmutable || $next <= $cursor) {
                break;
            }

            $cursor = $next;
        }

        return $dates;
    }

    public static function isDateEnabledByMask(string $date, int $target_days_mask): bool
    {
        $target_days_mask = HabitRules::normalizeTargetDaysMask($target_days_mask);

        if (
            $target_days_mask <= HabitRules::TARGET_DAYS_MASK_MIN ||
            $target_days_mask >= HabitRules::TARGET_DAYS_MASK_MAX
        ) {
            return true;
        }

        $date_object = self::parseDate($date);

        if (! $date_object instanceof \DateTimeImmutable) {
            return true;
        }

        $weekday = (int) $date_object->format('w');
        $weekday_bit = 1 << $weekday;

        return ($target_days_mask & $weekday_bit) !== 0;
    }

    private static function buildWeeklyCompletionCounts(array $habit_history, int $target_days_mask): array
    {
        $counts = [];

        foreach ($habit_history as $date => $is_checked) {
            unset($is_checked);
            $date_key = self::normalizeDateKey((string) $date);

            if ($date_key === null || ! self::isDateEnabledByMask($date_key, $target_days_mask)) {
                continue;
            }

            $date_object = self::parseDate($date_key);

            if (! $date_object instanceof \DateTimeImmutable) {
                continue;
            }

            $week_key = $date_object->format('o-W');

            if (! isset($counts[$week_key])) {
                $counts[$week_key] = 0;
            }

            $counts[$week_key]++;
        }

        return $counts;
    }

    private static function normalizeHistoryToDateSet(array $history): array
    {
        $date_set = [];

        foreach ($history as $date => $is_checked) {
            unset($is_checked);
            $date_key = self::normalizeDateKey((string) $date);

            if ($date_key === null) {
                continue;
            }

            $date_set[$date_key] = true;
        }

        return $date_set;
    }

    private static function normalizeFrequencyType(string $frequency_type): string
    {
        if (function_exists('sanitize_key')) {
            return HabitRules::normalizeFrequencyType($frequency_type);
        }

        $normalized = preg_replace('/[^a-z0-9_\-]/', '', strtolower($frequency_type));

        return (string) $normalized === self::FREQUENCY_WEEKLY
            ? self::FREQUENCY_WEEKLY
            : self::FREQUENCY_DAILY;
    }

    private static function normalizeDateKey(string $date): ?string
    {
        $parsed = self::parseDate($date);

        if (! $parsed instanceof \DateTimeImmutable) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    private static function parseDate(string $date): ?\DateTimeImmutable
    {
        if ($date === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));

        if (! $parsed instanceof \DateTimeImmutable) {
            return null;
        }

        return $parsed->format('Y-m-d') === $date ? $parsed : null;
    }
}
