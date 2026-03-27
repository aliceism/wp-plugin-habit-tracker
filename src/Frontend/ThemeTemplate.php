<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class ThemeTemplate
{
    public static function render(string $template, array $context = []): string
    {
        $path = self::locate($template);

        if ($path === '') {
            return '';
        }

        ob_start();

        if ($context !== []) {
            extract($context, EXTR_SKIP);
        }

        include $path;

        return (string) ob_get_clean();
    }

    public static function exists(string $template): bool
    {
        return self::locate($template) !== '';
    }

    private static function locate(string $template): string
    {
        $normalized = trim($template, '/');

        if ($normalized === '') {
            return '';
        }

        if (! str_ends_with($normalized, '.php')) {
            $normalized .= '.php';
        }

        $candidates = [
            'habit-tracker/' . $normalized,
            $normalized,
        ];

        $candidates = apply_filters('habit_tracker_theme_template_candidates', $candidates, $normalized);
        $located = (string) locate_template($candidates, false, false);

        return (string) apply_filters('habit_tracker_theme_template_path', $located, $normalized, $candidates);
    }
}

