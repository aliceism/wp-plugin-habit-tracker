<?php

class Habit_Tracker_Activator
{
    public static function activate()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $habits_table = $wpdb->prefix . "habits";
        $logs_table = $wpdb->prefix . "habit_logs";

        require_once(ABSPATH . "wp-admin/includes/upgrade.php");

        $sql_habits = "CREATE TABLE $habits_table (
            habit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id  BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (habit_id)
        ) $charset_collate;";

        $sql_logs = "CREATE TABLE $logs_table (
            log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            habit_id BIGINT UNSIGNED NOT NULL,
            log_date DATE NOT NULL,
            status TINYINT(1) DEFAULT 0,
            PRIMARY KEY (log_id)
        ) $charset_collate;";

        dbDelta($sql_habits);
        dbDelta($sql_logs);
    }
}