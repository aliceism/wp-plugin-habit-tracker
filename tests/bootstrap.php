<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (! defined('HABIT_TRACKER_PATH')) {
    define('HABIT_TRACKER_PATH', dirname(__DIR__) . '/');
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);

        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}

require_once HABIT_TRACKER_PATH . 'src/Support/Autoloader.php';

\HabitTracker\Support\Autoloader::register();

