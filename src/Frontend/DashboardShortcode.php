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
        ?>
        <article class="card app-card app-metric">
            <p class="app-metric__label"><?php esc_html_e('Current Streak', 'habit-tracker'); ?></p>
            <h2 class="app-metric__value">
                <?php
                printf(
                    esc_html(_n('%d day', '%d days', (int) $metrics['streak_days'], 'habit-tracker')),
                    (int) $metrics['streak_days']
                );
                ?>
            </h2>
            <p class="app-metric__meta"><?php esc_html_e('Consecutive days with at least one completed check.', 'habit-tracker'); ?></p>
        </article>

        <article class="card app-card app-metric">
            <p class="app-metric__label"><?php esc_html_e('Monthly Consistency', 'habit-tracker'); ?></p>
            <h2 class="app-metric__value"><?php echo esc_html((string) (int) $metrics['monthly_consistency_percent']); ?>%</h2>
            <p class="app-metric__meta"><?php esc_html_e('Completed checks against all possible checks in the last 30 days.', 'habit-tracker'); ?></p>
        </article>

        <article class="card app-card app-metric">
            <p class="app-metric__label"><?php esc_html_e('Active Habits', 'habit-tracker'); ?></p>
            <h2 class="app-metric__value"><?php echo esc_html((string) (int) $metrics['active_habits']); ?></h2>
            <p class="app-metric__meta"><?php esc_html_e('Habits currently active in your dashboard stack.', 'habit-tracker'); ?></p>
        </article>
        <?php
    }

    private function renderPanels(array $context): void
    {
        $categories = [
            self::CATEGORY_MIND => __('Mind', 'habit-tracker'),
            self::CATEGORY_BODY => __('Body', 'habit-tracker'),
            self::CATEGORY_PRODUCTIVITY => __('Productivity', 'habit-tracker'),
            self::CATEGORY_LIFE => __('Life', 'habit-tracker'),
        ];

        ?>
        <div class="habit-tracker-dashboard-panels app-grid">
            <?php foreach ($categories as $category_key => $category_label) : ?>
                <?php $category_habits = $context['habits_by_category'][$category_key] ?? []; ?>
                <article class="card app-card habit-tracker-dashboard-panel habit-tracker-dashboard-panel--<?php echo esc_attr($category_key); ?>">
                    <div class="habit-tracker-dashboard-panel__header">
                        <p class="app-card__eyebrow"><?php echo esc_html($category_label); ?></p>
                        <h3><?php echo esc_html(sprintf(__('%s Check', 'habit-tracker'), $category_label)); ?></h3>
                    </div>

                    <?php if ($category_habits === []) : ?>
                        <p class="habit-tracker-empty-state"><?php esc_html_e('No active habits in this category.', 'habit-tracker'); ?></p>
                    <?php else : ?>
                        <ul class="habit-tracker-dashboard-list">
                            <?php foreach ($category_habits as $habit) : ?>
                                <?php
                                $user_habit_id = isset($habit->id) ? (int) $habit->id : 0;
                                $history_map = $context['history_map'][$user_habit_id] ?? [];
                                $is_checked_today = isset($context['today_map'][$user_habit_id]);
                                ?>
                                <li class="habit-tracker-dashboard-item">
                                    <form class="habit-tracker-checkin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="<?php echo esc_attr(self::TOGGLE_CHECKIN_ACTION); ?>">
                                        <input type="hidden" name="user_habit_id" value="<?php echo esc_attr((string) $user_habit_id); ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($context['redirect_url']); ?>">
                                        <?php wp_nonce_field(self::TOGGLE_CHECKIN_ACTION); ?>

                                        <div class="habit-tracker-dashboard-item__main">
                                            <button
                                                type="submit"
                                                class="habit-tracker-check-tile<?php echo $is_checked_today ? ' is-checked' : ''; ?>"
                                                aria-label="<?php echo esc_attr(sprintf(__('Toggle today check for %s', 'habit-tracker'), (string) $habit->name)); ?>"
                                                title="<?php esc_attr_e('Toggle today check', 'habit-tracker'); ?>"
                                            >
                                                <?php echo $is_checked_today ? '&#10003;' : ''; ?>
                                            </button>

                                            <span class="habit-tracker-dashboard-item__name"><?php echo esc_html((string) $habit->name); ?></span>
                                        </div>

                                        <div class="habit-tracker-dashboard-item__history" aria-hidden="true">
                                            <?php foreach ($context['history_dates'] as $history_date) : ?>
                                                <?php
                                                $is_filled = isset($history_map[$history_date]);
                                                $is_today_cell = $history_date === $context['today'];
                                                ?>
                                                <span
                                                    class="habit-tracker-history-cell<?php echo $is_filled ? ' is-filled' : ''; ?><?php echo $is_today_cell ? ' is-today' : ''; ?>"
                                                    title="<?php echo esc_attr($history_date); ?>"
                                                ></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
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
        $active_habits = $this->user_habits->findActiveByUser($user_id);

        return [
            'today' => $today,
            'history_dates' => $history_dates,
            'redirect_url' => $this->getCurrentUrl(),
            'today_map' => $this->checkins->getCompletedMapForDate($user_id, $today),
            'history_map' => $this->checkins->getCompletedMapForRange($user_id, $start_date, $today),
            'habits_by_category' => $this->groupHabitsByCategory($active_habits),
            'metrics' => $this->buildMetrics($user_id, count($active_habits), $start_date, $today),
        ];
    }

    private function buildMetrics(int $user_id, int $active_habits_count, string $start_date, string $today): array
    {
        $completed_month = $this->checkins->countCompletedBetween($user_id, $start_date, $today);
        $monthly_slots = $active_habits_count * self::HISTORY_DAYS;
        $monthly_percent = $monthly_slots > 0 ? (int) round(($completed_month / $monthly_slots) * 100) : 0;
        $monthly_percent = max(0, min(100, $monthly_percent));

        return [
            'streak_days' => $this->calculateStreak($user_id, $today),
            'monthly_consistency_percent' => $monthly_percent,
            'active_habits' => $active_habits_count,
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
