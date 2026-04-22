<?php

namespace HabitTracker\Infrastructure\Persistence;

use HabitTracker\Domain\Rules\HabitRules;
use HabitTracker\Infrastructure\Database\Schema;

if (! defined('ABSPATH')) {
    exit;
}

final class WpdbUserHabitRepository
{
    private const MAX_NAME_LENGTH = 191;
    private const MAX_DESCRIPTION_LENGTH = 5000;

    private \wpdb $wpdb;

    private string $user_habits_table;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        if (! $database instanceof \wpdb) {
            $database = $wpdb;
        }

        $this->wpdb = $database;
        $this->user_habits_table = Schema::getTableName(Schema::TABLE_USER_HABITS, $this->wpdb);
    }

    public function findActiveByUser(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->user_habits_table} WHERE user_id = %d AND is_active = 1 ORDER BY position ASC, created_at ASC, id ASC",
            $user_id
        );

        $results = $this->wpdb->get_results($sql);

        return is_array($results) ? $results : [];
    }

    public function getActiveSharedHabitIdsByUser(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $sql = $this->wpdb->prepare(
            "SELECT habit_id FROM {$this->user_habits_table} WHERE user_id = %d AND is_active = 1 AND habit_id IS NOT NULL",
            $user_id
        );

        $rows = $this->wpdb->get_col($sql);

        if (! is_array($rows)) {
            return [];
        }

        return array_map('intval', $rows);
    }

    public function createCustomHabitForUser(int $user_id, array $data): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        [$frequency_type, $target_count, $target_days_mask] = $this->resolveCustomFrequencyConfig($data);
        $category = $this->normalizeCategoryKey((string) ($data['category'] ?? ''));
        $name = $this->truncateTextField((string) ($data['name'] ?? ''), self::MAX_NAME_LENGTH);
        $description = $this->truncateTextField((string) ($data['description'] ?? ''), self::MAX_DESCRIPTION_LENGTH);

        if ($name === '') {
            return 0;
        }

        $timestamp = current_time('mysql');
        $inserted = $this->wpdb->insert(
            $this->user_habits_table,
            [
                'user_id'            => $user_id,
                'habit_id'           => null,
                'source_type'        => 'custom',
                'name'               => $name,
                'category'           => $category,
                'description'        => $description,
                'frequency_type'     => $frequency_type,
                'target_count'       => $target_count,
                'target_days_mask'   => $target_days_mask,
                'start_date'         => wp_date('Y-m-d'),
                'position'           => $this->nextPosition($user_id),
                'is_active'          => 1,
                'created_by_user_id' => $user_id,
                'created_at'         => $timestamp,
                'updated_at'         => $timestamp,
                'archived_at'        => null,
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function addSharedHabitForUser(int $user_id, object $habit, array $frequency_override = []): string
    {
        if ($user_id <= 0) {
            return 'error';
        }

        $habit_id = isset($habit->id) ? (int) $habit->id : 0;

        if ($habit_id <= 0) {
            return 'error';
        }

        [$frequency_type, $target_count, $target_days_mask] = $this->resolveFrequencyConfig($habit, $frequency_override);
        $category = $this->normalizeCategoryKey((string) ($habit->category ?? ''));
        $habit_name = $this->truncateTextField((string) ($habit->name ?? ''), self::MAX_NAME_LENGTH);
        $habit_description = $this->truncateTextField((string) ($habit->description ?? ''), self::MAX_DESCRIPTION_LENGTH);

        if ($habit_name === '') {
            return 'error';
        }

        $existing = $this->findByUserAndHabitId($user_id, $habit_id);

        if ($existing !== null) {
            if ((int) $existing->is_active === 1) {
                return 'exists';
            }

            $updated = $this->wpdb->update(
                $this->user_habits_table,
                [
                    'source_type'      => 'habit',
                    'name'             => $habit_name,
                    'category'         => $category,
                    'description'      => $habit_description,
                    'frequency_type'   => $frequency_type,
                    'target_count'     => $target_count,
                    'target_days_mask' => $target_days_mask,
                    'is_active'        => 1,
                    'archived_at'      => null,
                    'updated_at'       => current_time('mysql'),
                ],
                ['id' => (int) $existing->id],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s'],
                ['%d']
            );

            return $updated === false ? 'error' : 'reactivated';
        }

        $timestamp = current_time('mysql');
        $inserted = $this->wpdb->insert(
            $this->user_habits_table,
            [
                'user_id'            => $user_id,
                'habit_id'           => $habit_id,
                'source_type'        => 'habit',
                'name'               => $habit_name,
                'category'           => $category,
                'description'        => $habit_description,
                'frequency_type'     => $frequency_type,
                'target_count'       => $target_count,
                'target_days_mask'   => $target_days_mask,
                'start_date'         => wp_date('Y-m-d'),
                'position'           => $this->nextPosition($user_id),
                'is_active'          => 1,
                'created_by_user_id' => $user_id,
                'created_at'         => $timestamp,
                'updated_at'         => $timestamp,
                'archived_at'        => null,
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        return $inserted === false ? 'error' : 'created';
    }

    private function resolveCustomFrequencyConfig(array $data): array
    {
        $target_per_week = isset($data['target_per_week'])
            ? HabitRules::normalizeTargetPerWeek((int) $data['target_per_week'])
            : HabitRules::DEFAULT_TARGET_PER_WEEK;

        if ($target_per_week > 0) {
            return [
                HabitRules::FREQUENCY_WEEKLY,
                $target_per_week,
                HabitRules::DEFAULT_TARGET_DAYS_MASK,
            ];
        }

        return [
            HabitRules::FREQUENCY_DAILY,
            HabitRules::DEFAULT_TARGET_COUNT,
            HabitRules::DEFAULT_TARGET_DAYS_MASK,
        ];
    }

    private function resolveFrequencyConfig(object $habit, array $frequency_override): array
    {
        $frequency_type = $this->normalizeFrequencyType((string) ($habit->default_frequency_type ?? HabitRules::FREQUENCY_DAILY));
        $target_count = $this->normalizeTargetCount((int) ($habit->default_target_count ?? HabitRules::DEFAULT_TARGET_COUNT));
        $target_days_mask = $this->normalizeTargetDaysMask((int) ($habit->default_target_days_mask ?? HabitRules::DEFAULT_TARGET_DAYS_MASK));
        $target_per_week = isset($frequency_override['target_per_week'])
            ? HabitRules::normalizeTargetPerWeek((int) $frequency_override['target_per_week'])
            : 0;

        if ($target_per_week > 0) {
            $frequency_type = HabitRules::FREQUENCY_WEEKLY;
            $target_count = $target_per_week;
            $target_days_mask = HabitRules::DEFAULT_TARGET_DAYS_MASK;
        }

        return [$frequency_type, $target_count, $target_days_mask];
    }

    public function archiveActiveByIdForUser(int $user_id, int $user_habit_id): string
    {
        if ($user_id <= 0 || $user_habit_id <= 0) {
            return 'not-found';
        }

        $updated = $this->wpdb->update(
            $this->user_habits_table,
            [
                'is_active'   => 0,
                'archived_at' => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ],
            [
                'id' => $user_habit_id,
                'user_id' => $user_id,
                'is_active' => 1,
            ],
            ['%d', '%s', '%s'],
            ['%d', '%d', '%d']
        );

        if ($updated === false) {
            return 'error';
        }

        if ($updated === 0) {
            return 'not-found';
        }

        return 'archived';
    }

    private function findByUserAndHabitId(int $user_id, int $habit_id): ?object
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->user_habits_table} WHERE user_id = %d AND habit_id = %d LIMIT 1",
            $user_id,
            $habit_id
        );

        $row = $this->wpdb->get_row($sql);

        return is_object($row) ? $row : null;
    }

    private function nextPosition(int $user_id): int
    {
        $sql = $this->wpdb->prepare(
            "SELECT MAX(position) FROM {$this->user_habits_table} WHERE user_id = %d",
            $user_id
        );

        $max_position = (int) $this->wpdb->get_var($sql);

        return $max_position + 1;
    }

    private function normalizeFrequencyType(string $frequency_type): string
    {
        return HabitRules::normalizeFrequencyType($frequency_type);
    }

    private function normalizeTargetCount(int $target_count): int
    {
        return HabitRules::normalizeTargetCount($target_count);
    }

    private function normalizeTargetDaysMask(int $target_days_mask): int
    {
        return HabitRules::normalizeTargetDaysMask($target_days_mask);
    }

    private function normalizeCategoryKey(string $category): string
    {
        return HabitRules::normalizeCategoryKey($category);
    }

    private function truncateTextField(string $value, int $max_length): string
    {
        if ($value === '' || $max_length <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $max_length, 'UTF-8');
        }

        return substr($value, 0, $max_length);
    }
}
