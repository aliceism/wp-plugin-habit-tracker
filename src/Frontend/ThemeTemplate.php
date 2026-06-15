<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class ThemeTemplate
{
    public static function render(string $template, array $template_data = []): string
    {
        $path = self::locate($template);

        if ($path === '') {
            return '';
        }

        ob_start();

        // Unified contract for templates:
        // - $data contains the full context array.
        // - top-level keys are extracted for backward compatibility.
        $data = $template_data;

        if ($template_data !== []) {
            extract($template_data, EXTR_SKIP);
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

        $theme_template = self::locateThemeTemplate($normalized);

        if ($theme_template !== '') {
            return $theme_template;
        }

        return self::locatePluginTemplate($normalized);
    }

    private static function locateThemeTemplate(string $normalized): string
    {
        $candidates = [
            'habit-tracker/' . $normalized,
            $normalized,
        ];

        $candidates = apply_filters('habit_tracker_theme_template_candidates', $candidates, $normalized);
        $located = (string) locate_template($candidates, false, false);

        return (string) apply_filters('habit_tracker_theme_template_path', $located, $normalized, $candidates);
    }

    private static function locatePluginTemplate(string $normalized): string
    {
        $default_path = trailingslashit(HABIT_TRACKER_PATH) . 'templates/' . $normalized;

        $path = (string) apply_filters(
            'habit_tracker_plugin_template_path',
            $default_path,
            $normalized
        );

        return is_readable($path) ? $path : '';
    }
}
