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
        return CurrentUrlResolver::resolve($this->getDashboardFallbackUrl());
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
