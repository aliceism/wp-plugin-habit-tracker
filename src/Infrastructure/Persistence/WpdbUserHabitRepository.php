<?php

namespace HabitTracker\Infrastructure\Persistence;

use HabitTracker\Infrastructure\Database\Schema;

if (! defined('ABSPATH')) {
    exit;
}

final class WpdbUserHabitRepository
{
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

        $timestamp = current_time('mysql');
        $inserted = $this->wpdb->insert(
            $this->user_habits_table,
            [
                'user_id'            => $user_id,
                'habit_id'           => null,
                'source_type'        => 'custom',
                'name'               => $data['name'],
                'category'           => $data['category'],
                'description'        => $data['description'],
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

        $existing = $this->findByUserAndHabitId($user_id, $habit_id);

        if ($existing !== null) {
            if ((int) $existing->is_active === 1) {
                return 'exists';
            }

            $updated = $this->wpdb->update(
                $this->user_habits_table,
                [
                    'source_type'      => 'habit',
                    'name'             => (string) $habit->name,
                    'category'         => (string) $habit->category,
                    'description'      => (string) $habit->description,
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
                'name'               => (string) $habit->name,
                'category'           => (string) $habit->category,
                'description'        => (string) $habit->description,
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
        $target_per_week = isset($data['target_per_week']) ? (int) $data['target_per_week'] : 7;

        if ($target_per_week >= 1 && $target_per_week <= 7) {
            return ['weekly', $target_per_week, 127];
        }

        return ['daily', 1, 127];
    }

    private function resolveFrequencyConfig(object $habit, array $frequency_override): array
    {
        $frequency_type = $this->normalizeFrequencyType((string) ($habit->default_frequency_type ?? 'daily'));
        $target_count = $this->normalizeTargetCount((int) ($habit->default_target_count ?? 1));
        $target_days_mask = $this->normalizeTargetDaysMask((int) ($habit->default_target_days_mask ?? 127));
        $target_per_week = isset($frequency_override['target_per_week']) ? (int) $frequency_override['target_per_week'] : 0;

        if ($target_per_week >= 1 && $target_per_week <= 7) {
            $frequency_type = 'weekly';
            $target_count = $target_per_week;
            $target_days_mask = 127;
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
        return sanitize_key($frequency_type) === 'weekly' ? 'weekly' : 'daily';
    }

    private function normalizeTargetCount(int $target_count): int
    {
        return max(1, min(7, $target_count));
    }

    private function normalizeTargetDaysMask(int $target_days_mask): int
    {
        return max(0, min(127, $target_days_mask));
    }
}
