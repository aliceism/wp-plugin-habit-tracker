<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class FrontendAssetPolicy
{
    public static function shouldEnqueuePluginStyles(): bool
    {
        $default = true;

        if (function_exists('habitlab_should_enqueue_habit_tracker_assets')) {
            // If the theme confirms it handles tracker assets for this request,
            // plugin CSS can stay disabled. Otherwise keep plugin CSS as fallback.
            $default = ! ((bool) habitlab_should_enqueue_habit_tracker_assets());
        }

        return (bool) apply_filters('habit_tracker_enqueue_plugin_styles', $default);
    }
}
