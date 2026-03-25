<?php

namespace HabitTracker\Infrastructure\Persistence;

use HabitTracker\Infrastructure\Database\Schema;

if (! defined('ABSPATH')) {
    exit;
}

final class WpdbHabitRepository
{
    private const CATEGORY_MIND = 'mind';
    private const CATEGORY_BODY = 'body';
    private const CATEGORY_PRODUCTIVITY = 'productivity';
    private const CATEGORY_LIFE = 'life';

    private \wpdb $wpdb;

    private string $habits_table;

    private string $user_habits_table;

    public function __construct(?\wpdb $database = null)
    {
        global $wpdb;

        if (! $database instanceof \wpdb) {
            $database = $wpdb;
        }

        $this->wpdb = $database;
        $this->habits_table = Schema::getTableName(Schema::TABLE_HABITS, $this->wpdb);
        $this->user_habits_table = Schema::getTableName(Schema::TABLE_USER_HABITS, $this->wpdb);
    }

    public function findAllForAdmin(): array
    {
        $sql = "SELECT * FROM {$this->habits_table} ORDER BY is_active DESC, sort_order ASC, name ASC";

        $results = $this->wpdb->get_results($sql);

        return is_array($results) ? $results : [];
    }

    public function findActiveForFrontend(): array
    {
        $sql = "SELECT * FROM {$this->habits_table} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
        $results = $this->wpdb->get_results($sql);

        return is_array($results) ? $results : [];
    }

    public function findById(int $habit_id): ?object
    {
        if ($habit_id <= 0) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->habits_table} WHERE id = %d LIMIT 1",
            $habit_id
        );

        $habit = $this->wpdb->get_row($sql);

        return is_object($habit) ? $habit : null;
    }

    public function create(array $data, int $created_by_user_id): int
    {
        $timestamp = current_time('mysql');
        $category = $this->normalizeCategoryKey((string) ($data['category'] ?? ''));

        $inserted = $this->wpdb->insert(
            $this->habits_table,
            [
                'name'                     => $data['name'],
                'slug'                     => $data['slug'],
                'category'                 => $category,
                'description'              => $data['description'],
                'default_frequency_type'   => $data['default_frequency_type'],
                'default_target_count'     => $data['default_target_count'],
                'default_target_days_mask' => $data['default_target_days_mask'],
                'is_active'                => $data['is_active'],
                'sort_order'               => $data['sort_order'],
                'created_by_user_id'       => $created_by_user_id,
                'created_at'               => $timestamp,
                'updated_at'               => $timestamp,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function update(int $habit_id, array $data): bool
    {
        if ($habit_id <= 0) {
            return false;
        }

        $category = $this->normalizeCategoryKey((string) ($data['category'] ?? ''));

        $updated = $this->wpdb->update(
            $this->habits_table,
            [
                'name'                     => $data['name'],
                'slug'                     => $data['slug'],
                'category'                 => $category,
                'description'              => $data['description'],
                'default_frequency_type'   => $data['default_frequency_type'],
                'default_target_count'     => $data['default_target_count'],
                'default_target_days_mask' => $data['default_target_days_mask'],
                'is_active'                => $data['is_active'],
                'sort_order'               => $data['sort_order'],
                'updated_at'               => current_time('mysql'),
            ],
            ['id' => $habit_id],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
            ],
            ['%d']
        );

        return $updated !== false;
    }

    public function setActive(int $habit_id, bool $is_active): bool
    {
        if ($habit_id <= 0) {
            return false;
        }

        $updated = $this->wpdb->update(
            $this->habits_table,
            [
                'is_active'  => $is_active ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $habit_id],
            ['%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    public function delete(int $habit_id): bool
    {
        if ($habit_id <= 0) {
            return false;
        }

        $deleted = $this->wpdb->delete(
            $this->habits_table,
            ['id' => $habit_id],
            ['%d']
        );

        return $deleted !== false;
    }

    public function countUserAssignments(int $habit_id): int
    {
        if ($habit_id <= 0) {
            return 0;
        }

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->user_habits_table} WHERE habit_id = %d",
            $habit_id
        );

        return (int) $this->wpdb->get_var($sql);
    }

    public function getAssignmentCounts(): array
    {
        $sql = "SELECT habit_id, COUNT(*) AS total FROM {$this->user_habits_table} WHERE habit_id IS NOT NULL GROUP BY habit_id";
        $rows = $this->wpdb->get_results($sql);

        if (! is_array($rows)) {
            return [];
        }

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->habit_id] = (int) $row->total;
        }

        return $counts;
    }

    public function generateUniqueSlug(string $requested_slug, ?int $exclude_habit_id = null): string
    {
        $base_slug = sanitize_title($requested_slug);

        if ($base_slug === '') {
            $base_slug = 'habit';
        }

        $candidate = $base_slug;
        $suffix = 2;

        while ($this->slugExists($candidate, $exclude_habit_id)) {
            $candidate = $base_slug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists(string $slug, ?int $exclude_habit_id = null): bool
    {
        if ($exclude_habit_id !== null && $exclude_habit_id > 0) {
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->habits_table} WHERE slug = %s AND id != %d",
                $slug,
                $exclude_habit_id
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->habits_table} WHERE slug = %s",
                $slug
            );
        }

        return (int) $this->wpdb->get_var($sql) > 0;
    }

    private function normalizeCategoryKey(string $category): string
    {
        $normalized = sanitize_key($category);
        $allowed = [
            self::CATEGORY_MIND => true,
            self::CATEGORY_BODY => true,
            self::CATEGORY_PRODUCTIVITY => true,
            self::CATEGORY_LIFE => true,
        ];

        if (isset($allowed[$normalized])) {
            return $normalized;
        }

        return self::CATEGORY_LIFE;
    }
}
