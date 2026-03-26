<?php

namespace HabitTracker\Domain\Rules;

if (! defined('ABSPATH')) {
    exit;
}

final class HabitRules
{
    public const CATEGORY_MIND = 'mind';
    public const CATEGORY_BODY = 'body';
    public const CATEGORY_PRODUCTIVITY = 'productivity';
    public const CATEGORY_LIFE = 'life';
    public const CATEGORY_CUSTOM = 'custom';

    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';

    public const TARGET_COUNT_MIN = 1;
    public const TARGET_COUNT_MAX = 7;
    public const TARGET_DAYS_MASK_MIN = 0;
    public const TARGET_DAYS_MASK_MAX = 127;

    public const DEFAULT_TARGET_COUNT = 1;
    public const DEFAULT_TARGET_DAYS_MASK = 127;
    public const DEFAULT_TARGET_PER_WEEK = 7;

    public const HISTORY_DAYS_DASHBOARD = 30;
    public const HISTORY_DAYS_PROGRESS = 120;

    public static function categoryKeys(bool $include_custom = false): array
    {
        $keys = [
            self::CATEGORY_MIND,
            self::CATEGORY_BODY,
            self::CATEGORY_PRODUCTIVITY,
            self::CATEGORY_LIFE,
        ];

        if ($include_custom) {
            $keys[] = self::CATEGORY_CUSTOM;
        }

        return $keys;
    }

    public static function categoryLabels(bool $include_custom = false): array
    {
        $labels = [
            self::CATEGORY_MIND => self::translate('Mind'),
            self::CATEGORY_BODY => self::translate('Body'),
            self::CATEGORY_PRODUCTIVITY => self::translate('Productivity'),
            self::CATEGORY_LIFE => self::translate('Life'),
        ];

        if ($include_custom) {
            $labels[self::CATEGORY_CUSTOM] = self::translate('Custom');
        }

        return $labels;
    }

    public static function isCoreCategoryKey(string $category_key): bool
    {
        $normalized = sanitize_key($category_key);

        return in_array($normalized, self::categoryKeys(false), true);
    }

    public static function normalizeCategoryKey(string $category_key, bool $allow_custom = false): string
    {
        $normalized = sanitize_key($category_key);
        $allowed = self::categoryKeys($allow_custom);

        if (in_array($normalized, $allowed, true)) {
            return $normalized;
        }

        return self::CATEGORY_LIFE;
    }

    public static function normalizeFrequencyType(string $frequency_type): string
    {
        return sanitize_key($frequency_type) === self::FREQUENCY_WEEKLY
            ? self::FREQUENCY_WEEKLY
            : self::FREQUENCY_DAILY;
    }

    public static function normalizeTargetCount(int $target_count): int
    {
        return max(self::TARGET_COUNT_MIN, min(self::TARGET_COUNT_MAX, $target_count));
    }

    public static function normalizeTargetDaysMask(int $target_days_mask): int
    {
        return max(self::TARGET_DAYS_MASK_MIN, min(self::TARGET_DAYS_MASK_MAX, $target_days_mask));
    }

    public static function normalizeTargetPerWeek(int $target_per_week): int
    {
        if ($target_per_week < self::TARGET_COUNT_MIN || $target_per_week > self::TARGET_COUNT_MAX) {
            return 0;
        }

        return $target_per_week;
    }

    public static function targetPerWeekOptions(): array
    {
        return [
            self::DEFAULT_TARGET_PER_WEEK,
            1,
            2,
            3,
            4,
            5,
            6,
        ];
    }

    public static function resolveDefaultTargetPerWeek(string $frequency_type, int $target_count): int
    {
        $normalized_frequency = self::normalizeFrequencyType($frequency_type);
        $normalized_target_count = self::normalizeTargetCount($target_count);

        if ($normalized_frequency === self::FREQUENCY_WEEKLY) {
            return $normalized_target_count;
        }

        return self::DEFAULT_TARGET_PER_WEEK;
    }

    private static function translate(string $text): string
    {
        if (function_exists('__')) {
            return __($text, 'habit-tracker');
        }

        return $text;
    }
}
