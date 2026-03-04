<?php

namespace HabitTracker\Support;

if (! defined('ABSPATH')) {
    exit;
}

final class Autoloader
{
    private const PREFIX = 'HabitTracker\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        if (strpos($class, self::PREFIX) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen(self::PREFIX));
        $relative_path = str_replace('\\', '/', $relative_class) . '.php';
        $file_path = HABIT_TRACKER_PATH . 'src/' . $relative_path;

        if (is_readable($file_path)) {
            require_once $file_path;
        }
    }
}
