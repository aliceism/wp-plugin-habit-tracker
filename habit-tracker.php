<?php
/**
 * Plugin Name: Habit Tracker
 * Description: Плъгин за проследяване на здравословни навици.
 * Version: 1.0
 * Author: Alice Ismail
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-activator.php';

register_activation_hook(__FILE__, array('Habit_Tracker_Activator', 'activate'));

require_once plugin_dir_path(__FILE__) . 'includes/class-habit-tracker-admin.php';

new Habit_Tracker_Admin();