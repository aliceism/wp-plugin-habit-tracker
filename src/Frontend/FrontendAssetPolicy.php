<?php

namespace HabitTracker\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

final class FrontendAssetPolicy
{
    public static function shouldEnqueuePluginStyles(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (
            wp_style_is('habit-tracker-frontend', 'enqueued') ||
            wp_style_is('habit-tracker-frontend', 'registered')
        ) {
            return false;
        }

        $default = true;

        if (function_exists('habitlab_should_enqueue_habit_tracker_assets')) {
            $default = !((bool) habitlab_should_enqueue_habit_tracker_assets());
        }

        return (bool) apply_filters('habit_tracker_enqueue_plugin_styles', $default);
    }
}
