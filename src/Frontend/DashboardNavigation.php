<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class DashboardNavigation
{
    public function resolveRedirectUrl(): string
    {
        $raw_redirect = wp_unslash($_POST['redirect_to'] ?? '');

        if (! is_string($raw_redirect) || $raw_redirect === '' || $this->isAdminPostUrl($raw_redirect)) {
            $raw_redirect = wp_unslash($_POST['_wp_http_referer'] ?? '');
        }

        if (! is_string($raw_redirect) || $raw_redirect === '' || $this->isAdminPostUrl($raw_redirect)) {
            $raw_redirect = wp_get_referer();
        }

        if (! is_string($raw_redirect) || $raw_redirect === '' || $this->isAdminPostUrl($raw_redirect)) {
            $raw_redirect = $this->getCurrentUrl();
        }

        return wp_validate_redirect($raw_redirect, $this->getDashboardFallbackUrl());
    }

    public function getCurrentUrl(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

        if (! is_string($request_uri) || $request_uri === '') {
            return $this->getDashboardFallbackUrl();
        }

        $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $request_query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);

        if ($request_path === '') {
            return $this->getDashboardFallbackUrl();
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

    public function appendNotice(string $url, string $notice_query_key, string $notice): string
    {
        $with_removed_notice = remove_query_arg($notice_query_key, $url);

        return add_query_arg($notice_query_key, $notice, $with_removed_notice);
    }

    public function getDashboardFallbackUrl(): string
    {
        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_dashboard_url = habitlab_get_page_url_by_slug('dashboard');

            if (is_string($theme_dashboard_url) && $theme_dashboard_url !== '') {
                return $theme_dashboard_url;
            }
        }

        return home_url('/');
    }

    private function isAdminPostUrl(string $url): bool
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);

        if ($path === '') {
            return false;
        }

        return str_ends_with($path, '/wp-admin/admin-post.php');
    }
}
