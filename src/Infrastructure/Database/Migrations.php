<?php

namespace HabitTracker\Infrastructure\Database;

if (! defined('ABSPATH')) {
    exit;
}

final class Migrations
{
    private const DB_VERSION = '1.2.0';
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

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

}
