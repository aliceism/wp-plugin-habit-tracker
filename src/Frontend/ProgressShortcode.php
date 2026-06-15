<?php

namespace HabitTracker\Frontend;

use HabitTracker\Domain\Analytics\ProgressAnalytics;
use HabitTracker\Infrastructure\Persistence\WpdbCheckinRepository;
use HabitTracker\Infrastructure\Persistence\WpdbUserHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class ProgressShortcode
{
    private const SHORTCODE_ALL = 'habit_tracker_progress';
    private const SHORTCODE_METRICS = 'habit_tracker_progress_metrics';
    private const SHORTCODE_BREAKDOWN = 'habit_tracker_progress_breakdown';

    private ProgressAnalytics $analytics;

    public function __construct(
        WpdbUserHabitRepository $user_habits,
        WpdbCheckinRepository $checkins,
        ?ProgressAnalytics $analytics = null
    )
    {
        $this->analytics = $analytics instanceof ProgressAnalytics
            ? $analytics
            : new ProgressAnalytics($user_habits, $checkins);
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_ALL, [$this, 'render']);
        add_shortcode(self::SHORTCODE_METRICS, [$this, 'renderMetricsShortcode']);
        add_shortcode(self::SHORTCODE_BREAKDOWN, [$this, 'renderBreakdownShortcode']);
    }

    public function render(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->buildContext();
        $metrics_html = $this->renderMetricsSectionHtml($context['category_stats']);
        $breakdown_html = $this->renderBreakdownSectionHtml(
            $context['week_chart'],
            $context['month_chart'],
            $context['habit_rows'],
            $context['summary'],
            $context['category_stats'],
            $context['has_active_habits'],
            false
        );

        return $this->renderTemplate(
            'progress',
            [
                'metrics_html' => $metrics_html,
                'breakdown_html' => $breakdown_html,
                'context' => $context,
            ]
        );
    }

    public function renderMetricsShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->buildContext();
        return $this->renderMetricsSectionHtml($context['category_stats']);
    }

    public function renderBreakdownShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->buildContext();
        $breakdown_html = $this->renderBreakdownSectionHtml(
            $context['week_chart'],
            $context['month_chart'],
            $context['habit_rows'],
            $context['summary'],
            $context['category_stats'],
            $context['has_active_habits'],
            true
        );
        return $breakdown_html;
    }

    private function renderLoggedOut(): string
    {
        return $this->renderTemplate(
            'progress-logged-out',
            [
                'login_url' => $this->resolveLoginUrl(),
                'kicker' => __('Progress Access', 'habit-tracker'),
                'title' => __('Log In To Review Progress', 'habit-tracker'),
                'description' => __('You need an account to review category trends, streaks, and completion metrics.', 'habit-tracker'),
                'button_text' => __('Login', 'habit-tracker'),
            ]
        );
    }

    private function buildContext(): array
    {
        return $this->analytics->buildContext(get_current_user_id());
    }

    private function clampPercent(int $value): int
    {
        return max(0, min(100, $value));
    }

    private function buildSvgPolylinePoints(array $points): string
    {
        $count = count($points);

        if ($count === 0) {
            return '';
        }

        $coordinates = [];

        foreach ($points as $index => $point_value) {
            $value = $this->clampPercent((int) $point_value);
            $x = $count === 1 ? 50 : ((100 / ($count - 1)) * $index);
            $y = 36 - (($value / 100) * 30);
            $coordinates[] = sprintf('%.2f,%.2f', $x, $y);
        }

        return implode(' ', $coordinates);
    }

    private function renderMetricsSectionHtml(array $category_stats): string
    {
        return $this->renderTemplate(
            'progress-metrics',
            [
                'category_stats' => $category_stats,
            ]
        );
    }

    private function renderBreakdownSectionHtml(
        array $week_chart,
        array $month_chart,
        array $habit_rows,
        array $summary,
        array $category_stats,
        bool $has_active_habits,
        bool $include_root_wrapper
    ): string {
        $month_points = isset($month_chart['points']) && is_array($month_chart['points'])
            ? $month_chart['points']
            : [];
        return $this->renderTemplate(
            'progress-breakdown',
            [
                'week_chart' => $week_chart,
                'month_chart' => $month_chart,
                'habit_rows' => $habit_rows,
                'summary' => $summary,
                'category_stats' => $category_stats,
                'has_active_habits' => $has_active_habits,
                'include_root_wrapper' => $include_root_wrapper,
                'month_svg_points' => $this->buildSvgPolylinePoints($month_points),
            ]
        );
    }

    private function enqueueAssets(): void
    {
        FrontendAssets::enqueue();
    }

    private function getCurrentUrl(): string
    {
        return CurrentUrlResolver::resolve($this->getProgressFallbackUrl());
    }

    private function resolveLoginUrl(): string
    {
        $login_url = wp_login_url($this->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                return $theme_login_url;
            }
        }

        return $login_url;
    }

    private function getProgressFallbackUrl(): string
    {
        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_progress_url = habitlab_get_page_url_by_slug('progress');

            if (is_string($theme_progress_url) && $theme_progress_url !== '') {
                return $theme_progress_url;
            }
        }

        return home_url('/');
    }

    private function renderTemplate(string $template, array $context = []): string
    {
        return ThemeTemplate::render($template, $context);
    }
}
