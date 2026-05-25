<?php

namespace HabitTracker\Frontend;

use HabitTracker\Domain\Analytics\DashboardAnalytics;
use HabitTracker\Domain\Rules\HabitRules;
use HabitTracker\Infrastructure\Persistence\WpdbCheckinRepository;
use HabitTracker\Infrastructure\Persistence\WpdbUserHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class DashboardShortcode
{
    private const SHORTCODE_ALL = 'habit_tracker_dashboard';
    private const SHORTCODE_NOTICE = 'habit_tracker_dashboard_notice';
    private const SHORTCODE_METRICS = 'habit_tracker_dashboard_metrics';
    private const SHORTCODE_PANELS = 'habit_tracker_dashboard_panels';
    private const TOGGLE_CHECKIN_ACTION = 'habit_tracker_toggle_checkin';
    private const NOTICE_QUERY_KEY = 'htd_notice';

    private DashboardAnalytics $analytics;

    private DashboardNavigation $navigation;

    private DashboardNoticeService $notices;

    private DashboardCheckinActionHandler $actions;

    public function __construct(
        WpdbUserHabitRepository $user_habits,
        WpdbCheckinRepository $checkins,
        ?DashboardAnalytics $analytics = null,
        ?DashboardNavigation $navigation = null,
        ?DashboardNoticeService $notices = null,
        ?DashboardCheckinActionHandler $actions = null
    )
    {
        $this->analytics = $analytics instanceof DashboardAnalytics
            ? $analytics
            : new DashboardAnalytics($user_habits, $checkins);
        $this->navigation = $navigation instanceof DashboardNavigation
            ? $navigation
            : new DashboardNavigation();
        $this->notices = $notices instanceof DashboardNoticeService
            ? $notices
            : new DashboardNoticeService();
        $this->actions = $actions instanceof DashboardCheckinActionHandler
            ? $actions
            : new DashboardCheckinActionHandler(
                $checkins,
                $this->navigation,
                $this->notices,
                self::TOGGLE_CHECKIN_ACTION,
                self::NOTICE_QUERY_KEY
            );
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_ALL, [$this, 'render']);
        add_shortcode(self::SHORTCODE_NOTICE, [$this, 'renderNoticeShortcode']);
        add_shortcode(self::SHORTCODE_METRICS, [$this, 'renderMetricsShortcode']);
        add_shortcode(self::SHORTCODE_PANELS, [$this, 'renderPanelsShortcode']);

        add_action('admin_post_' . self::TOGGLE_CHECKIN_ACTION, [$this, 'handleToggleCheckin']);
        add_action('admin_post_nopriv_' . self::TOGGLE_CHECKIN_ACTION, [$this, 'handleUnauthorized']);
        add_action('wp_ajax_' . self::TOGGLE_CHECKIN_ACTION, [$this, 'handleToggleCheckinAjax']);
        add_action('wp_ajax_nopriv_' . self::TOGGLE_CHECKIN_ACTION, [$this, 'handleToggleCheckinAjax']);
    }

    public function render(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->getContext();
        $notice_html = $this->renderNoticeHtml();
        $metrics_html = $this->renderMetricsSectionHtml($context['metrics']);
        $panels_html = $this->renderPanelsHtml($context);

        return $this->renderTemplate(
            'dashboard',
            [
                'notice_html' => $notice_html,
                'metrics_html' => $metrics_html,
                'panels_html' => $panels_html,
                'context' => $context,
            ]
        );
    }

    public function renderNoticeShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();
        $notice_html = $this->renderNoticeHtml();
        $context = is_user_logged_in() ? $this->getContext() : [];

        return $this->renderTemplate(
            'dashboard-notice',
            [
                'notice_html' => $notice_html,
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

        $context = $this->getContext();
        return $this->renderMetricsSectionHtml($context['metrics']);
    }

    public function renderPanelsShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->getContext();
        return $this->renderPanelsHtml($context);
    }

    public function handleToggleCheckin(): void
    {
        $this->actions->handleToggleCheckin();
    }

    public function handleToggleCheckinAjax(): void
    {
        $this->actions->handleToggleCheckinAjax(function (int $user_habit_id, string $result): array {
            unset($result);
            $context = $this->getContext();
            $month_rows = $this->buildMonthRowsFromContext($context);
            $top_habits = $this->buildTopHabits($month_rows);
            $active_streaks = $this->buildActiveStreaks($month_rows);

            return [
                'row' => $this->buildMonthRowPayload($month_rows, $user_habit_id),
                'metrics_html' => $this->renderMetricsCardsHtml($context['metrics']),
                'side_html' => $this->renderSidePanelsHtml($top_habits, $active_streaks),
            ];
        });
    }

    public function handleUnauthorized(): void
    {
        $this->actions->handleUnauthorized();
    }

    private function renderLoggedOut(): string
    {
        return $this->renderTemplate(
            'dashboard-logged-out',
            [
                'login_url' => $this->resolveLoginUrl(),
                'kicker' => __('Dashboard Access', 'habit-tracker'),
                'title' => __('Log In To Track Your Habits', 'habit-tracker'),
                'description' => __('You need an account to check habits and view your daily dashboard.', 'habit-tracker'),
                'button_text' => __('Login', 'habit-tracker'),
            ]
        );
    }

    private function renderMetricsCardsHtml(array $metrics): string
    {
        $cards = $this->buildMetricCards($metrics);
        return $this->renderTemplate(
            'dashboard-metrics-cards',
            [
                'cards' => $cards,
                'metrics' => $metrics,
            ]
        );
    }

    private function renderMetricsSectionHtml(array $metrics): string
    {
        $cards = $this->buildMetricCards($metrics);
        return $this->renderTemplate(
            'dashboard-metrics',
            [
                'cards' => $cards,
                'metrics' => $metrics,
            ]
        );
    }

    private function renderSidePanelsHtml(array $top_habits, array $active_streaks): string
    {
        return $this->renderTemplate(
            'dashboard-side',
            [
                'top_habits' => $top_habits,
                'active_streaks' => $active_streaks,
            ]
        );
    }

    private function renderPanelsHtml(array $context): string
    {
        $month_rows = $this->buildMonthRowsFromContext($context);
        $top_habits = $this->buildTopHabits($month_rows);
        $active_streaks = $this->buildActiveStreaks($month_rows);
        $side_html = $this->renderSidePanelsHtml($top_habits, $active_streaks);
        return $this->renderTemplate(
            'dashboard-panels',
            [
                'context' => $context,
                'month_rows' => $month_rows,
                'top_habits' => $top_habits,
                'active_streaks' => $active_streaks,
                'side_html' => $side_html,
                'admin_post_url' => admin_url('admin-post.php'),
                'toggle_action' => self::TOGGLE_CHECKIN_ACTION,
            ]
        );
    }

    private function buildMetricCards(array $metrics): array
    {
        $current_month_label = wp_date('F Y');
        $current_day = (int) wp_date('j');
        $days_in_month = max(1, (int) wp_date('t'));
        $month_progress_percent = (int) round(($current_day / $days_in_month) * 100);
        $month_progress_percent = max(0, min(100, $month_progress_percent));

        return [
            [
                'class' => 'month',
                'label' => '',
                'value' => $month_progress_percent,
                'stat' => $current_month_label,
                'meta' => sprintf(
                    esc_html__('Day %1$d of %2$d', 'habit-tracker'),
                    $current_day,
                    $days_in_month
                ),
            ],
            [
                'class' => 'momentum',
                'label' => __('Momentum', 'habit-tracker'),
                'value' => (int) $metrics['streak_percent'],
                'stat' => sprintf(
                    esc_html(_n('%d day', '%d days', (int) $metrics['streak_days'], 'habit-tracker')),
                    (int) $metrics['streak_days']
                ),
                'meta' => __('Current streak length.', 'habit-tracker'),
            ],
            [
                'class' => 'today',
                'label' => __('Today', 'habit-tracker'),
                'value' => (int) $metrics['today_completion_percent'],
                'stat' => sprintf(
                    esc_html__('%1$d / %2$d habits', 'habit-tracker'),
                    (int) $metrics['checked_today'],
                    (int) $metrics['active_habits']
                ),
                'meta' => __('Checked habits for today.', 'habit-tracker'),
            ],
            [
                'class' => 'coverage',
                'label' => __('Active Days', 'habit-tracker'),
                'value' => (int) $metrics['active_days_percent'],
                'stat' => sprintf(
                    esc_html__('%1$d / %2$d days', 'habit-tracker'),
                    (int) $metrics['active_days'],
                    HabitRules::HISTORY_DAYS_DASHBOARD
                ),
                'meta' => __('Days with at least one completed check.', 'habit-tracker'),
            ],
        ];
    }

    private function buildMonthRowsFromContext(array $context): array
    {
        return $this->analytics->buildMonthRowsFromContext($context);
    }

    private function buildMonthRowPayload(array $month_rows, int $user_habit_id): ?array
    {
        foreach ($month_rows as $row) {
            if ((int) ($row['id'] ?? 0) !== $user_habit_id) {
                continue;
            }

            return [
                'id' => (int) $row['id'],
                'completed' => (int) $row['completed'],
                'target_total' => (int) $row['target_total'],
                'progress_percent' => (int) $row['progress_percent'],
                'progress_text' => $this->formatProgressText($row),
            ];
        }

        return null;
    }

    private function formatProgressText(array $row): string
    {
        return sprintf(
            __(
                '%1$d/%2$d · %3$d%%',
                'habit-tracker'
            ),
            (int) ($row['completed'] ?? 0),
            (int) ($row['target_total'] ?? 0),
            (int) ($row['progress_percent'] ?? 0)
        );
    }

    private function renderNoticeHtml(): string
    {
        $notice = isset($_GET[self::NOTICE_QUERY_KEY])
            ? sanitize_key(wp_unslash($_GET[self::NOTICE_QUERY_KEY]))
            : '';

        if ($notice === '') {
            return '';
        }

        $messages = $this->notices->getMessages();
        if (! isset($messages[$notice])) {
            return '';
        }

        return sprintf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr((string) $messages[$notice]['class']),
            esc_html((string) $messages[$notice]['text'])
        );
    }

    private function enqueueAssets(): void
    {
        FrontendAssets::enqueue(
            'habitTrackerDashboard',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'toggleAction' => self::TOGGLE_CHECKIN_ACTION,
            ]
        );
    }

    private function getContext(): array
    {
        return $this->analytics->getContext(get_current_user_id(), $this->navigation->getCurrentUrl());
    }

    private function buildTopHabits(array $rows): array
    {
        return $this->analytics->buildTopHabits($rows);
    }

    private function buildActiveStreaks(array $rows): array
    {
        return $this->analytics->buildActiveStreaks($rows);
    }

    private function resolveLoginUrl(): string
    {
        $login_url = wp_login_url($this->navigation->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                return $theme_login_url;
            }
        }

        return $login_url;
    }

    private function renderTemplate(string $template, array $context = []): string
    {
        return ThemeTemplate::render($template, $context);
    }
}
