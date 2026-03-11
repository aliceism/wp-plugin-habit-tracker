<?php

namespace HabitTracker\Infrastructure\Persistence;

use HabitTracker\Infrastructure\Database\Schema;

if (! defined('ABSPATH')) {
    exit;
}

final class WpdbCheckinRepository
{
    private const STATUS_COMPLETED = 'completed';

    private \wpdb $wpdb;

    private string $checkins_table;

    private string $user_habits_table;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        if (! $database instanceof \wpdb) {
            $database = $wpdb;
        }

        $this->wpdb = $database;
        $this->checkins_table = Schema::getTableName(Schema::TABLE_CHECKINS, $this->wpdb);
        $this->user_habits_table = Schema::getTableName(Schema::TABLE_USER_HABITS, $this->wpdb);
    }

    public function toggleCompletedForToday(int $user_id, int $user_habit_id): string
    {
        if ($user_id <= 0 || $user_habit_id <= 0) {
            return 'invalid';
        }

        if (! $this->isActiveUserHabit($user_id, $user_habit_id)) {
            return 'not-found';
        }

        $today = wp_date('Y-m-d');
        $sql = $this->wpdb->prepare(
            "SELECT id, status FROM {$this->checkins_table} WHERE user_id = %d AND user_habit_id = %d AND checkin_date = %s LIMIT 1",
            $user_id,
            $user_habit_id,
            $today
        );

        $existing = $this->wpdb->get_row($sql);

        if (is_object($existing)) {
            if ((string) $existing->status === self::STATUS_COMPLETED) {
                $deleted = $this->wpdb->delete(
                    $this->checkins_table,
                    ['id' => (int) $existing->id],
                    ['%d']
                );

                return $deleted === false ? 'error' : 'unchecked';
            }

            $updated = $this->wpdb->update(
                $this->checkins_table,
                [
                    'status' => self::STATUS_COMPLETED,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => (int) $existing->id],
                ['%s', '%s'],
                ['%d']
            );

            return $updated === false ? 'error' : 'checked';
        }

        $timestamp = current_time('mysql');
        $inserted = $this->wpdb->insert(
            $this->checkins_table,
            [
                'user_habit_id' => $user_habit_id,
                'user_id' => $user_id,
                'checkin_date' => $today,
                'status' => self::STATUS_COMPLETED,
                'note' => '',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        return $inserted === false ? 'error' : 'checked';
    }

    public function getCompletedMapForDate(int $user_id, string $date): array
    {
        if ($user_id <= 0 || $date === '') {
            return [];
        }

        $sql = $this->wpdb->prepare(
            "SELECT user_habit_id
            FROM {$this->checkins_table}
            WHERE user_id = %d AND checkin_date = %s AND status = %s",
            $user_id,
            $date,
            self::STATUS_COMPLETED
        );

        $rows = $this->wpdb->get_col($sql);

        if (! is_array($rows)) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row] = true;
        }

        return $map;
    }

    public function getCompletedMapForRange(int $user_id, string $start_date, string $end_date): array
    {
        if ($user_id <= 0 || $start_date === '' || $end_date === '') {
            return [];
        }

        $sql = $this->wpdb->prepare(
            "SELECT user_habit_id, checkin_date
            FROM {$this->checkins_table}
            WHERE user_id = %d
                AND checkin_date BETWEEN %s AND %s
                AND status = %s",
            $user_id,
            $start_date,
            $end_date,
            self::STATUS_COMPLETED
        );

        $rows = $this->wpdb->get_results($sql);

        if (! is_array($rows)) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $habit_id = isset($row->user_habit_id) ? (int) $row->user_habit_id : 0;
            $date = isset($row->checkin_date) ? (string) $row->checkin_date : '';

            if ($habit_id <= 0 || $date === '') {
                continue;
            }

            if (! isset($map[$habit_id])) {
                $map[$habit_id] = [];
            }

            $map[$habit_id][$date] = true;
        }

        return $map;
    }

    public function countCompletedBetween(int $user_id, string $start_date, string $end_date): int
    {
        if ($user_id <= 0 || $start_date === '' || $end_date === '') {
            return 0;
        }

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$this->checkins_table}
            WHERE user_id = %d
                AND checkin_date BETWEEN %s AND %s
                AND status = %s",
            $user_id,
            $start_date,
            $end_date,
            self::STATUS_COMPLETED
        );

        return (int) $this->wpdb->get_var($sql);
    }

    public function getDistinctCompletedDatesUntil(int $user_id, string $end_date, int $limit = 180): array
    {
        if ($user_id <= 0 || $end_date === '' || $limit <= 0) {
            return [];
        }

        $sql = $this->wpdb->prepare(
            "SELECT DISTINCT checkin_date
            FROM {$this->checkins_table}
            WHERE user_id = %d
                AND checkin_date <= %s
                AND status = %s
            ORDER BY checkin_date DESC
            LIMIT %d",
            $user_id,
            $end_date,
            self::STATUS_COMPLETED,
            $limit
        );

        $rows = $this->wpdb->get_col($sql);

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $rows)));
    }

    private function isActiveUserHabit(int $user_id, int $user_habit_id): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$this->user_habits_table}
            WHERE id = %d AND user_id = %d AND is_active = 1",
            $user_habit_id,
            $user_id
        );

        return (int) $this->wpdb->get_var($sql) > 0;
    }
}
