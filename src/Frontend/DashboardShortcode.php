<?php

namespace HabitTracker\Frontend;

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
    private const CATEGORY_MIND = 'mind';
    private const CATEGORY_BODY = 'body';
    private const CATEGORY_PRODUCTIVITY = 'productivity';
    private const CATEGORY_LIFE = 'life';
    private const HISTORY_DAYS = 30;

    private WpdbUserHabitRepository $user_habits;

    private WpdbCheckinRepository $checkins;

    public function __construct(WpdbUserHabitRepository $user_habits, WpdbCheckinRepository $checkins)
    {
        $this->user_habits = $user_habits;
        $this->checkins = $checkins;
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_ALL, [$this, 'render']);
        add_shortcode(self::SHORTCODE_NOTICE, [$this, 'renderNoticeShortcode']);
        add_shortcode(self::SHORTCODE_METRICS, [$this, 'renderMetricsShortcode']);
        add_shortcode(self::SHORTCODE_PANELS, [$this, 'renderPanelsShortcode']);

        add_action('admin_post_' . self::TOGGLE_CHECKIN_ACTION, [$this, 'handleToggleCheckin']);
        add_action('admin_post_nopriv_' . self::TOGGLE_CHECKIN_ACTION, [$this, 'handleUnauthorized']);
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
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer(self::TOGGLE_CHECKIN_ACTION);

        $user_habit_id = isset($_POST['user_habit_id']) ? absint(wp_unslash($_POST['user_habit_id'])) : 0;

        if ($user_habit_id <= 0) {
            $this->redirectWithNotice('checkin-invalid');
        }

        $result = $this->checkins->toggleCompletedForToday(get_current_user_id(), $user_habit_id);

        if ($result === 'checked') {
            $this->redirectWithNotice('checkin-checked');
        }

        if ($result === 'unchecked') {
            $this->redirectWithNotice('checkin-unchecked');
        }

        if ($result === 'invalid' || $result === 'not-found') {
            $this->redirectWithNotice('checkin-invalid');
        }

        $this->redirectWithNotice('checkin-failed');
    }

    public function handleUnauthorized(): void
    {
        $redirect = $this->resolveRedirectUrl();

        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    private function renderLoggedOut(): string
    {
        $login_url = wp_login_url($this->getCurrentUrl());

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
        $login_url = wp_login_url($this->getCurrentUrl());

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
                    self::HISTORY_DAYS
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
        $ordered_habits = $this->flattenHabitsByCategory($context['habits_by_category']);
        $month_rows = $this->buildMonthRows(
            $ordered_habits,
            $context['month_map'],
            $context['history_map'],
            (int) $context['days_elapsed'],
            $context['today']
        );
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
                                    <tr class="habit-tracker-month-row habit-tracker-month-row--<?php echo esc_attr($row['category']); ?>">
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
                                                <?php
                                                printf(
                                                    esc_html__('%1$d/%2$d · %3$d%%', 'habit-tracker'),
                                                    (int) $row['completed'],
                                                    (int) $context['days_elapsed'],
                                                    (int) $row['progress_percent']
                                                );
                                                ?>
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
                                        aria-label="<?php echo esc_attr(sprintf(esc_html__('%1$d of %2$d days completed', 'habit-tracker'), (int) $top_habit['completed'], (int) $context['days_elapsed'])); ?>"
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
                                <li class="habit-tracker-streak-item habit-tracker-streak-item--<?php echo esc_attr($streak_row['category']); ?>">
                                    <span class="habit-tracker-streak-item__name"><?php echo esc_html($streak_row['name']); ?></span>
                                    <span
                                        class="habit-tracker-streak-meter"
                                        aria-label="<?php echo esc_attr(sprintf(_n('%d day streak', '%d days streak', (int) $streak_row['streak'], 'habit-tracker'), (int) $streak_row['streak'])); ?>"
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
            </div>
        </div>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET[self::NOTICE_QUERY_KEY])
            ? sanitize_key(wp_unslash($_GET[self::NOTICE_QUERY_KEY]))
            : '';

        if ($notice === '') {
            return;
        }

        $messages = [
            'checkin-checked' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--success',
                'text' => __('Habit checked for today.', 'habit-tracker'),
            ],
            'checkin-unchecked' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--info',
                'text' => __('Habit unchecked for today.', 'habit-tracker'),
            ],
            'checkin-invalid' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--info',
                'text' => __('This habit is not available for check-in.', 'habit-tracker'),
            ],
            'checkin-failed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Could not update check status.', 'habit-tracker'),
            ],
        ];

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
    }

    private function redirectWithNotice(string $notice): void
    {
        $redirect_url = $this->resolveRedirectUrl();
        $redirect_url = remove_query_arg(self::NOTICE_QUERY_KEY, $redirect_url);
        $redirect_url = add_query_arg(self::NOTICE_QUERY_KEY, $notice, $redirect_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function resolveRedirectUrl(): string
    {
        $raw_redirect = wp_unslash($_POST['redirect_to'] ?? '');

        if (! is_string($raw_redirect) || $raw_redirect === '') {
            $raw_redirect = wp_get_referer();
        }

        if (! is_string($raw_redirect) || $raw_redirect === '') {
            $raw_redirect = $this->getCurrentUrl();
        }

        return wp_validate_redirect($raw_redirect, home_url('/'));
    }

    private function getCurrentUrl(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

        if (! is_string($request_uri) || $request_uri === '') {
            return home_url('/');
        }

        return home_url($request_uri);
    }

    private function getContext(): array
    {
        $user_id = get_current_user_id();
        $today = wp_date('Y-m-d');
        $history_dates = $this->buildHistoryDates($today, self::HISTORY_DAYS);
        $start_date = $history_dates[0] ?? $today;
        $month_dates = $this->buildCurrentMonthDates($today);
        $month_start = $month_dates[0] ?? $today;
        $month_end = $month_dates !== [] ? $month_dates[count($month_dates) - 1] : $today;
        $active_habits = $this->user_habits->findActiveByUser($user_id);
        $active_habit_ids = [];

        foreach ($active_habits as $active_habit) {
            $active_habit_id = isset($active_habit->id) ? (int) $active_habit->id : 0;

            if ($active_habit_id <= 0) {
                continue;
            }

            $active_habit_ids[$active_habit_id] = true;
        }

        $today_map = $this->checkins->getCompletedMapForDate($user_id, $today);
        $history_map = $this->checkins->getCompletedMapForRange($user_id, $start_date, $today);
        $month_map = $this->checkins->getCompletedMapForRange($user_id, $month_start, $month_end);
        $today_map = array_intersect_key($today_map, $active_habit_ids);
        $history_map = array_intersect_key($history_map, $active_habit_ids);
        $month_map = array_intersect_key($month_map, $active_habit_ids);

        return [
            'today' => $today,
            'history_dates' => $history_dates,
            'month_dates' => $month_dates,
            'month_weeks' => $this->buildWeekGroups($month_dates),
            'month_label' => wp_date('F Y', strtotime($today)),
            'days_elapsed' => (int) wp_date('j', strtotime($today)),
            'redirect_url' => $this->getCurrentUrl(),
            'today_map' => $today_map,
            'history_map' => $history_map,
            'month_map' => $month_map,
            'habits_by_category' => $this->groupHabitsByCategory($active_habits),
            'metrics' => $this->buildMetrics(
                $user_id,
                count($active_habits),
                $today,
                count($today_map),
                $history_map
            ),
        ];
    }

    private function buildMetrics(
        int $user_id,
        int $active_habits_count,
        string $today,
        int $checked_today,
        array $history_map
    ): array
    {
        $completed_month = $this->countCompletedFromHistoryMap($history_map);
        $monthly_slots = $active_habits_count * self::HISTORY_DAYS;
        $monthly_percent = $monthly_slots > 0 ? (int) round(($completed_month / $monthly_slots) * 100) : 0;
        $monthly_percent = max(0, min(100, $monthly_percent));
        $today_percent = $active_habits_count > 0 ? (int) round(($checked_today / $active_habits_count) * 100) : 0;
        $today_percent = max(0, min(100, $today_percent));
        $active_days = $this->countActiveDaysFromHistoryMap($history_map);
        $active_days_percent = self::HISTORY_DAYS > 0
            ? (int) round(($active_days / self::HISTORY_DAYS) * 100)
            : 0;
        $active_days_percent = max(0, min(100, $active_days_percent));
        $streak_days = $this->calculateStreak($user_id, $today);
        $streak_percent = self::HISTORY_DAYS > 0
            ? (int) round(($streak_days / self::HISTORY_DAYS) * 100)
            : 0;
        $streak_percent = max(0, min(100, $streak_percent));
        $remaining_checks = max(0, $monthly_slots - $completed_month);

        return [
            'streak_days' => $streak_days,
            'streak_percent' => $streak_percent,
            'monthly_consistency_percent' => $monthly_percent,
            'active_habits' => $active_habits_count,
            'checked_today' => $checked_today,
            'today_completion_percent' => $today_percent,
            'completed_checks' => $completed_month,
            'total_slots' => $monthly_slots,
            'remaining_checks' => $remaining_checks,
            'active_days' => $active_days,
            'active_days_percent' => $active_days_percent,
        ];
    }

    private function calculateStreak(int $user_id, string $today): int
    {
        $max_days = 365;
        $dates = $this->checkins->getDistinctCompletedDatesUntil($user_id, $today, $max_days);

        if ($dates === []) {
            return 0;
        }

        $date_set = array_fill_keys($dates, true);
        $streak = 0;
        $today_ts = strtotime($today);

        if (! is_int($today_ts)) {
            return 0;
        }

        for ($offset = 0; $offset < $max_days; $offset++) {
            $date = wp_date('Y-m-d', strtotime('-' . $offset . ' days', $today_ts));

            if (! isset($date_set[$date])) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    private function groupHabitsByCategory(array $active_habits): array
    {
        $grouped = [
            self::CATEGORY_MIND => [],
            self::CATEGORY_BODY => [],
            self::CATEGORY_PRODUCTIVITY => [],
            self::CATEGORY_LIFE => [],
        ];

        foreach ($active_habits as $habit) {
            $category_key = sanitize_key((string) ($habit->category ?? ''));

            if (
                $category_key !== self::CATEGORY_MIND &&
                $category_key !== self::CATEGORY_BODY &&
                $category_key !== self::CATEGORY_PRODUCTIVITY &&
                $category_key !== self::CATEGORY_LIFE
            ) {
                $category_key = self::CATEGORY_LIFE;
            }

            $grouped[$category_key][] = $habit;
        }

        return $grouped;
    }

    private function flattenHabitsByCategory(array $habits_by_category): array
    {
        $ordered = [];
        $category_order = [
            self::CATEGORY_MIND,
            self::CATEGORY_BODY,
            self::CATEGORY_PRODUCTIVITY,
            self::CATEGORY_LIFE,
        ];

        foreach ($category_order as $category_key) {
            $category_habits = $habits_by_category[$category_key] ?? [];

            foreach ($category_habits as $habit) {
                $ordered[] = [
                    'habit' => $habit,
                    'category' => $category_key,
                ];
            }
        }

        return $ordered;
    }

    private function buildMonthRows(
        array $ordered_habits,
        array $month_map,
        array $streak_map,
        int $days_elapsed,
        string $today
    ): array {
        $rows = [];
        $days_elapsed = max(1, $days_elapsed);

        foreach ($ordered_habits as $entry) {
            $habit = $entry['habit'];
            $category_key = (string) $entry['category'];
            $habit_id = isset($habit->id) ? (int) $habit->id : 0;
            $habit_month_map = $month_map[$habit_id] ?? [];
            $completed = 0;

            if (is_array($habit_month_map)) {
                foreach ($habit_month_map as $date => $is_checked) {
                    unset($is_checked);

                    if ((string) $date <= $today) {
                        $completed++;
                    }
                }
            } else {
                $habit_month_map = [];
            }

            $progress_percent = (int) round(($completed / $days_elapsed) * 100);
            $progress_percent = max(0, min(100, $progress_percent));

            $rows[] = [
                'id' => $habit_id,
                'name' => (string) ($habit->name ?? ''),
                'category' => $category_key,
                'day_map' => $habit_month_map,
                'completed' => $completed,
                'progress_percent' => $progress_percent,
                'streak' => $this->calculateHabitStreakFromHistory($streak_map[$habit_id] ?? [], $today),
            ];
        }

        return $rows;
    }

    private function buildTopHabits(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            if ($left['completed'] === $right['completed']) {
                if ($left['streak'] === $right['streak']) {
                    return strcmp((string) $left['name'], (string) $right['name']);
                }

                return (int) $right['streak'] <=> (int) $left['streak'];
            }

            return (int) $right['completed'] <=> (int) $left['completed'];
        });

        return $rows;
    }

    private function buildActiveStreaks(array $rows): array
    {
        $active = array_values(array_filter($rows, static function (array $row): bool {
            return (int) ($row['streak'] ?? 0) > 0;
        }));

        usort($active, static function (array $left, array $right): int {
            if ($left['streak'] === $right['streak']) {
                return (int) $right['completed'] <=> (int) $left['completed'];
            }

            return (int) $right['streak'] <=> (int) $left['streak'];
        });

        if ($active === []) {
            return [];
        }

        $max_streak = max(array_map(static function (array $row): int {
            return max(1, (int) ($row['streak'] ?? 0));
        }, $active));

        foreach ($active as &$row) {
            $row['streak_percent'] = (int) round((((int) ($row['streak'] ?? 0)) / $max_streak) * 100);
        }
        unset($row);

        return $active;
    }

    private function buildCurrentMonthDates(string $today): array
    {
        $today_ts = strtotime($today);

        if (! is_int($today_ts)) {
            return [];
        }

        $month_start = wp_date('Y-m-01', $today_ts);
        $month_end = wp_date('Y-m-t', $today_ts);

        return $this->buildDateRange($month_start, $month_end);
    }

    private function buildWeekGroups(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $groups = [];
        $week_number = 1;

        foreach (array_chunk($dates, 7) as $chunk) {
            $groups[] = [
                'label' => sprintf(esc_html__('Week %d', 'habit-tracker'), $week_number),
                'count' => count($chunk),
            ];

            $week_number++;
        }

        return $groups;
    }

    private function buildDateRange(string $start_date, string $end_date): array
    {
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);

        if (! is_int($start_ts) || ! is_int($end_ts) || $end_ts < $start_ts) {
            return [];
        }

        $dates = [];
        $cursor = $start_ts;

        while ($cursor <= $end_ts) {
            $dates[] = wp_date('Y-m-d', $cursor);
            $next = strtotime('+1 day', $cursor);

            if (! is_int($next) || $next <= $cursor) {
                break;
            }

            $cursor = $next;
        }

        return $dates;
    }

    private function calculateHabitStreakFromHistory(array $habit_history, string $today): int
    {
        if ($habit_history === []) {
            return 0;
        }

        $today_ts = strtotime($today);

        if (! is_int($today_ts)) {
            return 0;
        }

        $date_set = array_fill_keys(array_keys($habit_history), true);
        $streak = 0;

        for ($offset = 0; $offset < self::HISTORY_DAYS; $offset++) {
            $date = wp_date('Y-m-d', strtotime('-' . $offset . ' days', $today_ts));

            if (! isset($date_set[$date])) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    private function countCompletedFromHistoryMap(array $history_map): int
    {
        $count = 0;

        foreach ($history_map as $habit_history) {
            if (! is_array($habit_history)) {
                continue;
            }

            $count += count($habit_history);
        }

        return $count;
    }

    private function countActiveDaysFromHistoryMap(array $history_map): int
    {
        $dates = [];

        foreach ($history_map as $habit_history) {
            if (! is_array($habit_history)) {
                continue;
            }

            foreach ($habit_history as $date => $flag) {
                unset($flag);
                $dates[(string) $date] = true;
            }
        }

        return count($dates);
    }

    private function buildHistoryDates(string $today, int $days): array
    {
        $dates = [];
        $today_ts = strtotime($today);

        if (! is_int($today_ts) || $days <= 0) {
            return $dates;
        }

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $dates[] = wp_date('Y-m-d', strtotime('-' . $offset . ' days', $today_ts));
        }

        return $dates;
    }
}
