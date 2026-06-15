<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class CurrentUrlResolver
{
    public static function resolve(string $fallback_url): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

        if (! is_string($request_uri) || $request_uri === '') {
            return $fallback_url;
        }

        $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $request_query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);

        if ($request_path === '') {
            return $fallback_url;
        }

        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        $home_path = '/' . trim($home_path, '/');
        $normalized_path = $request_path;

        if ($home_path !== '/' && $home_path !== '') {
            if ($normalized_path === $home_path) {
                $normalized_path = '/';
            } elseif (str_starts_with($normalized_path, $home_path . '/')) {
                $normalized_path = substr($normalized_path, strlen($home_path));
            }
        }

        if ($normalized_path === '' || $normalized_path[0] !== '/') {
            $normalized_path = '/' . ltrim($normalized_path, '/');
        }

        $current_url = home_url($normalized_path);

        if ($request_query !== '') {
            $current_url .= '?' . $request_query;
        }

        return $current_url;
    }
}
