<?php

namespace HabitTracker\Infrastructure\Database;

if (! defined('ABSPATH')) {
    exit;
}

final class Migrations
{
    private const DB_VERSION = '1.1.0';
    private const DB_VERSION_OPTION = 'habit_tracker_db_version';

    public static function activate(): void
    {
        self::migrate();
    }

    public static function maybeMigrate(): void
    {
        $installed_version = get_option(self::DB_VERSION_OPTION, '');

        if (! is_string($installed_version) || version_compare($installed_version, self::DB_VERSION, '<')) {
            self::migrate();
        }
    }

    private static function migrate(): void
    {
        global $wpdb;

        if (! $wpdb instanceof \wpdb) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (Schema::getCreateStatements($wpdb) as $statement) {
            dbDelta($statement);
        }

        self::removeLegacyMaskColumns($wpdb);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    private static function removeLegacyMaskColumns(\wpdb $wpdb): void
    {
        $tables = Schema::getTableNames($wpdb);
        $habits_table = $tables[Schema::TABLE_HABITS] ?? '';
        $user_habits_table = $tables[Schema::TABLE_USER_HABITS] ?? '';

        self::dropColumnIfExists($wpdb, $habits_table, 'default_target_days_mask');
        self::dropColumnIfExists($wpdb, $user_habits_table, 'target_days_mask');
    }

    private static function dropColumnIfExists(\wpdb $wpdb, string $table_name, string $column_name): void
    {
        if ($table_name === '' || $column_name === '') {
            return;
        }

        if (! self::columnExists($wpdb, $table_name, $column_name)) {
            return;
        }

        $table_sql = self::quoteIdentifier($table_name);
        $column_sql = self::quoteIdentifier($column_name);

        $wpdb->query("ALTER TABLE {$table_sql} DROP COLUMN {$column_sql}");
    }

    private static function columnExists(\wpdb $wpdb, string $table_name, string $column_name): bool
    {
        $sql = $wpdb->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $table_name,
            $column_name
        );
        $count = (int) $wpdb->get_var($sql);

        return $count > 0;
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
