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

        ob_start();
        ?>
        <div class="habit-tracker-dashboard">
            <?php $this->renderNotice(); ?>

            <div class="app-section app-metrics-grid habit-tracker-dashboard-metrics">
                <?php $this->renderMetricsCards($context['metrics']); ?>
            </div>

            <?php $this->renderPanels($context); ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function renderNoticeShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        ob_start();
        $this->renderNotice();

        return (string) ob_get_clean();
    }

    public function renderMetricsShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOutInline();
        }

        $context = $this->getContext();

        ob_start();
        ?>
        <div class="app-section app-metrics-grid habit-tracker-dashboard-metrics">
            <?php $this->renderMetricsCards($context['metrics']); ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function renderPanelsShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOutInline();
        }

        $context = $this->getContext();

        ob_start();
        $this->renderPanels($context);

        return (string) ob_get_clean();
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
        $login_url = wp_login_url($this->navigation->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                $login_url = $theme_login_url;
            }
        }

        return sprintf(
            '<section class="habit-tracker-block habit-tracker-logged-out"><p class="app-card__eyebrow">%s</p><h3>%s</h3><p>%s</p><a class="btn btn-primary" href="%s">%s</a></section>',
            esc_html__('Dashboard Access', 'habit-tracker'),
            esc_html__('Log In To Track Your Habits', 'habit-tracker'),
            esc_html__('You need an account to check habits and view your daily dashboard.', 'habit-tracker'),
            esc_url($login_url),
            esc_html__('Login', 'habit-tracker')
        );
    }

    private function renderLoggedOutInline(): string
    {
        $login_url = wp_login_url($this->navigation->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                $login_url = $theme_login_url;
            }
        }

        return sprintf(
            '<p>%s <a href="%s">%s</a></p>',
            esc_html__('Log in to access your habit dashboard.', 'habit-tracker'),
            esc_url($login_url),
            esc_html__('Login', 'habit-tracker')
        );
    }

    private function renderMetricsCards(array $metrics): void
    {
        $current_month_label = wp_date('F Y');
        $current_day = (int) wp_date('j');
        $days_in_month = max(1, (int) wp_date('t'));
        $month_progress_percent = (int) round(($current_day / $days_in_month) * 100);
        $month_progress_percent = max(0, min(100, $month_progress_percent));

        $cards = [
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

        ?>
        <?php foreach ($cards as $card) : ?>
            <article class="card app-card app-metric habit-tracker-metric-card habit-tracker-metric-card--<?php echo esc_attr($card['class']); ?>">
                <div class="habit-tracker-metric-orb" style="--ht-value: <?php echo esc_attr((string) $card['value']); ?>;">
                    <span><?php echo esc_html((string) (int) $card['value']); ?>%</span>
                </div>
                <div class="habit-tracker-metric-content">
                    <?php if ((string) ($card['label'] ?? '') !== '') : ?>
                        <p class="app-metric__label"><?php echo esc_html((string) $card['label']); ?></p>
                    <?php endif; ?>
                    <h2 class="app-metric__value"><?php echo esc_html((string) $card['stat']); ?></h2>
                    <p class="app-metric__meta"><?php echo esc_html((string) $card['meta']); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
        <?php
    }

    private function renderPanels(array $context): void
    {
        $month_rows = $this->buildMonthRowsFromContext($context);
        $top_habits = $this->buildTopHabits($month_rows);
        $active_streaks = $this->buildActiveStreaks($month_rows);

        ?>
        <div class="habit-tracker-dashboard-panels habit-tracker-month-layout">
            <article class="card app-card habit-tracker-dashboard-panel habit-tracker-month-table-panel">
                <div class="habit-tracker-dashboard-panel__header">
                    <p class="app-card__eyebrow"><?php esc_html_e('Check-ins', 'habit-tracker'); ?></p>
                    <h3><?php esc_html_e('Monthly Habit Grid', 'habit-tracker'); ?></h3>
                </div>

                <?php if ($month_rows === []) : ?>
                    <p class="habit-tracker-empty-state"><?php esc_html_e('No active habits in your dashboard stack yet.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <div class="habit-tracker-month-table-wrap">
                        <table class="habit-tracker-month-table">
                            <thead>
                                <tr class="habit-tracker-month-table__weeks">
                                    <th class="habit-tracker-month-table__habit-col"><?php esc_html_e('Habits', 'habit-tracker'); ?></th>
                                    <?php foreach ($context['month_weeks'] as $week) : ?>
                                        <th colspan="<?php echo esc_attr((string) (int) $week['count']); ?>">
                                            <?php echo esc_html($week['label']); ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="habit-tracker-month-table__progress-col"><?php esc_html_e('Progress', 'habit-tracker'); ?></th>
                                </tr>
                                <tr class="habit-tracker-month-table__days">
                                    <th aria-hidden="true"></th>
                                    <?php foreach ($context['month_dates'] as $month_date) : ?>
                                        <th title="<?php echo esc_attr($month_date); ?>">
                                            <span class="habit-tracker-month-dayhead">
                                                <span class="habit-tracker-month-dayname"><?php echo esc_html(wp_date('D', strtotime($month_date))); ?></span>
                                                <strong class="habit-tracker-month-daynum"><?php echo esc_html(wp_date('j', strtotime($month_date))); ?></strong>
                                            </span>
                                        </th>
                                    <?php endforeach; ?>
                                    <th aria-hidden="true"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($month_rows as $row) : ?>
                                    <tr
                                        class="habit-tracker-month-row habit-tracker-month-row--<?php echo esc_attr($row['category']); ?>"
                                        data-user-habit-id="<?php echo esc_attr((string) (int) $row['id']); ?>"
                                    >
                                        <th scope="row" class="habit-tracker-month-habit"><?php echo esc_html($row['name']); ?></th>

                                        <?php foreach ($context['month_dates'] as $month_date) : ?>
                                            <?php
                                            $is_filled = isset($row['day_map'][$month_date]);
                                            $is_today = $month_date === $context['today'];
                                            $is_future = $month_date > $context['today'];
                                            ?>
                                            <td class="habit-tracker-month-day<?php echo $is_today ? ' is-today' : ''; ?>">
                                                <?php if ($is_today) : ?>
                                                    <form class="habit-tracker-month-toggle-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                        <input type="hidden" name="action" value="<?php echo esc_attr(self::TOGGLE_CHECKIN_ACTION); ?>">
                                                        <input type="hidden" name="user_habit_id" value="<?php echo esc_attr((string) $row['id']); ?>">
                                                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($context['redirect_url']); ?>">
                                                        <?php wp_nonce_field(self::TOGGLE_CHECKIN_ACTION); ?>
                                                        <button
                                                            type="submit"
                                                            class="habit-tracker-month-cell habit-tracker-month-cell--action<?php echo $is_filled ? ' is-filled' : ''; ?> is-today"
                                                            aria-label="<?php echo esc_attr(sprintf(__('Toggle today check for %s', 'habit-tracker'), $row['name'])); ?>"
                                                            title="<?php esc_attr_e('Toggle today check', 'habit-tracker'); ?>"
                                                        >
                                                            <?php echo $is_filled ? '&#10003;' : ''; ?>
                                                        </button>
                                                    </form>
                                                <?php else : ?>
                                                    <span class="habit-tracker-month-cell<?php echo $is_filled ? ' is-filled' : ''; ?><?php echo $is_future ? ' is-future' : ''; ?>"></span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>

                                        <td class="habit-tracker-month-progress">
                                            <span class="habit-tracker-progress-bar">
                                                <span style="width: <?php echo esc_attr((string) (int) $row['progress_percent']); ?>%;"></span>
                                            </span>
                                            <span class="habit-tracker-month-progress-text">
                                                <?php echo esc_html($this->formatProgressText($row)); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <div class="habit-tracker-month-side">
                <?php $this->renderSidePanelsContent($top_habits, $active_streaks); ?>
            </div>
        </div>
        <?php
    }

    private function renderSidePanelsContent(array $top_habits, array $active_streaks): void
    {
        ?>
        <article class="card app-card habit-tracker-dashboard-side habit-tracker-month-side-card">
            <div class="habit-tracker-dashboard-panel__header">
                <p class="app-card__eyebrow"><?php esc_html_e('Top Habits', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('This Month', 'habit-tracker'); ?></h3>
            </div>

            <?php if ($top_habits === []) : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('No check-ins yet.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <ul class="habit-tracker-ranking-list">
                    <?php foreach ($top_habits as $top_habit) : ?>
                        <li class="habit-tracker-ranking-item habit-tracker-ranking-item--<?php echo esc_attr($top_habit['category']); ?>">
                            <span class="habit-tracker-ranking-item__name"><?php echo esc_html($top_habit['name']); ?></span>
                            <span
                                class="habit-tracker-ranking-meter"
                                aria-label="<?php echo esc_attr(sprintf(esc_html__('%1$d of %2$d target completions', 'habit-tracker'), (int) $top_habit['completed'], (int) $top_habit['target_total'])); ?>"
                            >
                                <span class="habit-tracker-ranking-meter__fill<?php echo ((int) $top_habit['progress_percent'] > 0) ? ' has-value' : ''; ?>" style="width: <?php echo esc_attr((string) (int) $top_habit['progress_percent']); ?>%;">
                                    <span class="habit-tracker-ranking-meter__count"><?php echo esc_html((string) (int) $top_habit['progress_percent']); ?>%</span>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="card app-card habit-tracker-dashboard-side habit-tracker-month-side-card habit-tracker-month-side-card--streaks">
            <div class="habit-tracker-dashboard-panel__header">
                <p class="app-card__eyebrow"><?php esc_html_e('Active Streaks', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Current Run', 'habit-tracker'); ?></h3>
            </div>

            <?php if ($active_streaks === []) : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('No active streaks right now.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <ul class="habit-tracker-streak-list">
                    <?php foreach ($active_streaks as $streak_row) : ?>
                        <?php
                        $streak_unit = (string) ($streak_row['streak_unit'] ?? 'day');
                        $streak_aria = $streak_unit === 'week'
                            ? sprintf(_n('%d week streak', '%d weeks streak', (int) $streak_row['streak'], 'habit-tracker'), (int) $streak_row['streak'])
                            : sprintf(_n('%d day streak', '%d days streak', (int) $streak_row['streak'], 'habit-tracker'), (int) $streak_row['streak']);
                        ?>
                        <li class="habit-tracker-streak-item habit-tracker-streak-item--<?php echo esc_attr($streak_row['category']); ?>">
                            <span class="habit-tracker-streak-item__name"><?php echo esc_html($streak_row['name']); ?></span>
                            <span
                                class="habit-tracker-streak-meter"
                                aria-label="<?php echo esc_attr($streak_aria); ?>"
                            >
                                <span class="habit-tracker-streak-meter__fill" style="width: <?php echo esc_attr((string) (int) $streak_row['streak_percent']); ?>%;">
                                    <span class="habit-tracker-streak-meter__count"><?php echo esc_html((string) (int) $streak_row['streak']); ?></span>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderMetricsCardsHtml(array $metrics): string
    {
        ob_start();
        $this->renderMetricsCards($metrics);

        return (string) ob_get_clean();
    }

    private function renderSidePanelsHtml(array $top_habits, array $active_streaks): string
    {
        ob_start();
        $this->renderSidePanelsContent($top_habits, $active_streaks);

        return (string) ob_get_clean();
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

    private function renderNotice(): void
    {
        $notice = isset($_GET[self::NOTICE_QUERY_KEY])
            ? sanitize_key(wp_unslash($_GET[self::NOTICE_QUERY_KEY]))
            : '';

        if ($notice === '') {
            return;
        }

        $messages = $this->notices->getMessages();

        if (! isset($messages[$notice])) {
            return;
        }
        ?>
        <div class="<?php echo esc_attr($messages[$notice]['class']); ?>">
            <p><?php echo esc_html($messages[$notice]['text']); ?></p>
        </div>
        <?php
    }

    private function enqueueAssets(): void
    {
        $style_path = HABIT_TRACKER_PATH . 'assets/css/frontend.css';
        $script_path = HABIT_TRACKER_PATH . 'assets/js/frontend.js';

        $style_version = is_readable($style_path)
            ? (string) filemtime($style_path)
            : HABIT_TRACKER_VERSION;

        $script_version = is_readable($script_path)
            ? (string) filemtime($script_path)
            : HABIT_TRACKER_VERSION;

        wp_enqueue_style(
            'habit-tracker-frontend',
            HABIT_TRACKER_URL . 'assets/css/frontend.css',
            [],
            $style_version
        );

        wp_enqueue_script(
            'habit-tracker-frontend',
            HABIT_TRACKER_URL . 'assets/js/frontend.js',
            [],
            $script_version,
            true
        );

        wp_localize_script(
            'habit-tracker-frontend',
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
}
