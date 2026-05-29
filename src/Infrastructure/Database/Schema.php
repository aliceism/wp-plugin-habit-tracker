<?php

namespace HabitTracker\Infrastructure\Database;

use HabitTracker\Domain\Rules\HabitRules;

if (! defined('ABSPATH')) {
    exit;
}

final class Schema
{
    public const TABLE_HABITS = 'habits';
    public const TABLE_USER_HABITS = 'user_habits';
    public const TABLE_CHECKINS = 'checkins';

    public static function getTableNames(\wpdb $wpdb): array
    {
        $base_name = $wpdb->prefix . 'habit_tracker_';

        return [
            self::TABLE_HABITS      => $base_name . 'habits',
            self::TABLE_USER_HABITS => $base_name . 'user_habits',
            self::TABLE_CHECKINS    => $base_name . 'checkins',
        ];
    }

    public static function getTableName(string $table, ?\wpdb $database = null): string
    {
        global $wpdb;

        if (! $database instanceof \wpdb) {
            $database = $wpdb;
        }

        if (! $database instanceof \wpdb) {
            return '';
        }

        $tables = self::getTableNames($database);

        return $tables[$table] ?? '';
    }

    public static function getCreateStatements(\wpdb $wpdb): array
    {
        $tables = self::getTableNames($wpdb);
        $charset_collate = $wpdb->get_charset_collate();
        $default_frequency_type = HabitRules::FREQUENCY_DAILY;
        $default_target_count = HabitRules::DEFAULT_TARGET_COUNT;

        return [
            self::TABLE_HABITS => "
                CREATE TABLE {$tables[self::TABLE_HABITS]} (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    name varchar(191) NOT NULL,
                    category varchar(100) NOT NULL DEFAULT '',
                    description text NULL,
                    default_frequency_type varchar(20) NOT NULL DEFAULT '{$default_frequency_type}',
                    default_target_count smallint(5) unsigned NOT NULL DEFAULT {$default_target_count},
                    is_active tinyint(1) NOT NULL DEFAULT 1,
                    sort_order int(10) unsigned NOT NULL DEFAULT 0,
                    created_by_user_id bigint(20) unsigned NOT NULL,
                    created_at datetime NOT NULL,
                    updated_at datetime NOT NULL,
                    PRIMARY KEY  (id),
                    UNIQUE KEY name_unique (name),
                    KEY is_active_sort (is_active, sort_order),
                    KEY category (category)
                ) {$charset_collate};
            ",
            self::TABLE_USER_HABITS => "
                CREATE TABLE {$tables[self::TABLE_USER_HABITS]} (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    user_id bigint(20) unsigned NOT NULL,
                    habit_id bigint(20) unsigned NULL,
                    source_type varchar(20) NOT NULL,
                    name varchar(191) NOT NULL,
                    category varchar(100) NOT NULL DEFAULT '',
                    description text NULL,
                    frequency_type varchar(20) NOT NULL DEFAULT '{$default_frequency_type}',
                    target_count smallint(5) unsigned NOT NULL DEFAULT {$default_target_count},
                    start_date date NOT NULL,
                    position int(10) unsigned NOT NULL DEFAULT 0,
                    is_active tinyint(1) NOT NULL DEFAULT 1,
                    created_by_user_id bigint(20) unsigned NOT NULL,
                    created_at datetime NOT NULL,
                    updated_at datetime NOT NULL,
                    archived_at datetime NULL,
                    PRIMARY KEY  (id),
                    UNIQUE KEY user_habit_unique (user_id, habit_id),
                    KEY user_active_position (user_id, is_active, position, id),
                    KEY user_category (user_id, category),
                    KEY habit_id (habit_id),
                    KEY source_type (source_type)
                ) {$charset_collate};
            ",
            self::TABLE_CHECKINS => "
                CREATE TABLE {$tables[self::TABLE_CHECKINS]} (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    user_habit_id bigint(20) unsigned NOT NULL,
                    user_id bigint(20) unsigned NOT NULL,
                    checkin_date date NOT NULL,
                    status varchar(20) NOT NULL DEFAULT 'completed',
                    value decimal(10,2) NULL,
                    note varchar(255) NOT NULL DEFAULT '',
                    created_at datetime NOT NULL,
                    updated_at datetime NOT NULL,
                    PRIMARY KEY  (id),
                    UNIQUE KEY user_habit_day (user_habit_id, checkin_date),
                    KEY user_date (user_id, checkin_date),
                    KEY user_habit_status_date (user_habit_id, status, checkin_date)
                ) {$charset_collate};
            ",
        ];
    }
}
