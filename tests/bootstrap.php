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

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1)
    {
        return parse_url($url, $component);
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        $base = 'https://example.test/wordpress';

        if ($path === '') {
            return $base;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return rtrim($base, '/') . $path;
    }
}

require_once HABIT_TRACKER_PATH . 'src/Support/Autoloader.php';

\HabitTracker\Support\Autoloader::register();
