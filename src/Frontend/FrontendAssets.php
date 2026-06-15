<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class FrontendAssets
{
    /** @var array<string,bool> */
    private static array $localized_objects = [];

    public static function enqueue(?string $localize_object = null, array $localize_data = []): void
    {
        $script_path = HABIT_TRACKER_PATH . 'assets/js/frontend.js';
        $script_version = is_readable($script_path)
            ? (string) filemtime($script_path)
            : HABIT_TRACKER_VERSION;

        if (FrontendAssetPolicy::shouldEnqueuePluginStyles()) {
            $style_path = HABIT_TRACKER_PATH . 'assets/css/frontend.css';
            $style_version = is_readable($style_path)
                ? (string) filemtime($style_path)
                : HABIT_TRACKER_VERSION;

            wp_enqueue_style(
                'habit-tracker-frontend',
                HABIT_TRACKER_URL . 'assets/css/frontend.css',
                [],
                $style_version
            );
        }

        wp_enqueue_script(
            'habit-tracker-frontend',
            HABIT_TRACKER_URL . 'assets/js/frontend.js',
            [],
            $script_version,
            true
        );

        if (
            $localize_object !== null &&
            $localize_object !== '' &&
            $localize_data !== [] &&
            ! isset(self::$localized_objects[$localize_object])
        ) {
            wp_localize_script(
                'habit-tracker-frontend',
                $localize_object,
                $localize_data
            );

            self::$localized_objects[$localize_object] = true;
        }
    }
}
