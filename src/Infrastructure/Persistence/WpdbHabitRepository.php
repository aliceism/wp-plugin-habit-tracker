<?php

namespace HabitTracker\Infrastructure\Persistence;

use HabitTracker\Domain\Rules\HabitRules;
use HabitTracker\Infrastructure\Database\Schema;

if (! defined('ABSPATH')) {
    exit;
}

final class WpdbHabitRepository
{
    private const MAX_NAME_LENGTH = 191;
    private const MAX_SLUG_LENGTH = 191;
    private const MAX_DESCRIPTION_LENGTH = 5000;

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
        $sql = "SELECT * FROM {$this->habits_table} ORDER BY is_active DESC, id ASC";

        $results = $this->wpdb->get_results($sql);

        return is_array($results) ? $results : [];
    }

    public function findForAdminList(array $filters = []): array
    {
        $args = [];
        $where_sql = $this->buildAdminWhereClause($filters, $args);
        $order_by_sql = $this->resolveAdminOrderBy((string) ($filters['sort'] ?? 'default'));
        $sql = "SELECT * FROM {$this->habits_table}{$where_sql} ORDER BY {$order_by_sql}";

        if ($args !== []) {
            $sql = $this->wpdb->prepare($sql, $args);
        }

        $results = $this->wpdb->get_results($sql);

        return is_array($results) ? $results : [];
    }

    public function countAllForAdmin(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->habits_table}";

        return (int) $this->wpdb->get_var($sql);
    }

    public function getMaxSortOrderForAdmin(): int
    {
        $sql = "SELECT MAX(sort_order) FROM {$this->habits_table}";
        $max_sort_order = (int) $this->wpdb->get_var($sql);

        return max(0, $max_sort_order);
    }

    public function findActiveForFrontend(): array
    {
        $sql = "SELECT * FROM {$this->habits_table} WHERE is_active = 1 ORDER BY created_at ASC, id ASC";
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
        $name = $this->truncateTextField((string) ($data['name'] ?? ''), self::MAX_NAME_LENGTH);
        $slug = $this->truncateTextField((string) ($data['slug'] ?? ''), self::MAX_SLUG_LENGTH);
        $description = $this->truncateTextField((string) ($data['description'] ?? ''), self::MAX_DESCRIPTION_LENGTH);

        if ($name === '' || $slug === '') {
            return 0;
        }

        $inserted = $this->wpdb->insert(
            $this->habits_table,
            [
                'name'                     => $name,
                'slug'                     => $slug,
                'category'                 => $category,
                'description'              => $description,
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
        $name = $this->truncateTextField((string) ($data['name'] ?? ''), self::MAX_NAME_LENGTH);
        $slug = $this->truncateTextField((string) ($data['slug'] ?? ''), self::MAX_SLUG_LENGTH);
        $description = $this->truncateTextField((string) ($data['description'] ?? ''), self::MAX_DESCRIPTION_LENGTH);

        if ($name === '' || $slug === '') {
            return false;
        }

        $updated = $this->wpdb->update(
            $this->habits_table,
            [
                'name'                     => $name,
                'slug'                     => $slug,
                'category'                 => $category,
                'description'              => $description,
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

        $base_slug = $this->truncateTextField($base_slug, self::MAX_SLUG_LENGTH);

        if ($base_slug === '') {
            $base_slug = 'habit';
        }

        $candidate = $base_slug;
        $suffix = 2;

        while ($this->slugExists($candidate, $exclude_habit_id)) {
            $suffix_text = '-' . $suffix;
            $available_length = self::MAX_SLUG_LENGTH - strlen($suffix_text);

            if ($available_length <= 0) {
                $candidate = substr($suffix_text, -self::MAX_SLUG_LENGTH);
            } else {
                $candidate = substr($base_slug, 0, $available_length) . $suffix_text;
            }

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
        return HabitRules::normalizeCategoryKey($category);
    }

    private function buildAdminWhereClause(array $filters, array &$args): string
    {
        $conditions = [];
        $search = trim((string) ($filters['search'] ?? ''));
        $category = sanitize_key((string) ($filters['category'] ?? 'all'));
        $status = sanitize_key((string) ($filters['status'] ?? 'all'));

        if ($search !== '') {
            $pattern = '%' . $this->wpdb->esc_like($search) . '%';
            $conditions[] = '(name LIKE %s OR slug LIKE %s OR description LIKE %s OR category LIKE %s)';
            $args[] = $pattern;
            $args[] = $pattern;
            $args[] = $pattern;
            $args[] = $pattern;
        }

        if ($category !== '' && $category !== 'all') {
            $conditions[] = 'category = %s';
            $args[] = $this->normalizeCategoryKey($category);
        }

        if ($status === 'active') {
            $conditions[] = 'is_active = %d';
            $args[] = 1;
        } elseif ($status === 'inactive') {
            $conditions[] = 'is_active = %d';
            $args[] = 0;
        }

        if ($conditions === []) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', $conditions);
    }

    private function resolveAdminOrderBy(string $sort): string
    {
        if ($sort === 'name_asc') {
            return 'name ASC, id ASC';
        }

        if ($sort === 'name_desc') {
            return 'name DESC, id ASC';
        }

        if ($sort === 'updated_desc') {
            return 'updated_at DESC, name ASC, id ASC';
        }

        if ($sort === 'updated_asc') {
            return 'updated_at ASC, name ASC, id ASC';
        }

        return 'is_active DESC, id ASC';
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
