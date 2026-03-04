<?php
/**
 * Plugin Name: Habit Tracker
 * Description: Habit library, dashboard selection, and progress tracking for HabitLab.
 * Version: 0.1.0
 * Author: Alice Ismail
 * Text Domain: habit-tracker
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('HABIT_TRACKER_FILE')) {
    define('HABIT_TRACKER_FILE', __FILE__);
}

if (! defined('HABIT_TRACKER_PATH')) {
    define('HABIT_TRACKER_PATH', plugin_dir_path(__FILE__));
}

if (! defined('HABIT_TRACKER_URL')) {
    define('HABIT_TRACKER_URL', plugin_dir_url(__FILE__));
}

if (! defined('HABIT_TRACKER_VERSION')) {
    define('HABIT_TRACKER_VERSION', '0.1.0');
}

require_once HABIT_TRACKER_PATH . 'src/Support/Autoloader.php';

\HabitTracker\Support\Autoloader::register();

register_activation_hook(
    HABIT_TRACKER_FILE,
    ['HabitTracker\\Infrastructure\\Database\\Migrations', 'activate']
);

if (! function_exists('habit_tracker')) {
    function habit_tracker(): \HabitTracker\Plugin
    {
        return \HabitTracker\Plugin::instance();
    }
}

add_action(
    'plugins_loaded',
    static function (): void {
        habit_tracker()->boot();
    }
);
