<?php

namespace HabitTracker\Frontend;

use HabitTracker\Domain\Analytics\ProgressAnalytics;
use HabitTracker\Domain\Rules\HabitRules;
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

        ob_start();
        ?>
        <div class="habit-tracker-progress">
            <div class="app-section app-metrics-grid habit-tracker-progress-metrics">
                <?php $this->renderMetrics($context['category_stats']); ?>
            </div>

            <?php
            $this->renderCharts(
                $context['week_chart'],
                $context['month_chart'],
                $context['habit_rows'],
                $context['has_active_habits']
            );
            ?>

            <div class="habit-tracker-progress-sections">
                <?php $this->renderCategoryBreakdown($context['category_stats'], $context['has_active_habits']); ?>
                <?php $this->renderInsights($context['summary'], $context['has_active_habits']); ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function renderMetricsShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOutInline();
        }

        $context = $this->buildContext();

        ob_start();
        ?>
        <div class="app-section app-metrics-grid habit-tracker-progress-metrics">
            <?php $this->renderMetrics($context['category_stats']); ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function renderBreakdownShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOutInline();
        }

        $context = $this->buildContext();

        ob_start();
        ?>
        <div class="habit-tracker-progress">
            <?php
            $this->renderCharts(
                $context['week_chart'],
                $context['month_chart'],
                $context['habit_rows'],
                $context['has_active_habits']
            );
            ?>

            <div class="habit-tracker-progress-sections">
                <?php $this->renderCategoryBreakdown($context['category_stats'], $context['has_active_habits']); ?>
                <?php $this->renderInsights($context['summary'], $context['has_active_habits']); ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
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
            esc_html__('Progress Access', 'habit-tracker'),
            esc_html__('Log In To Review Progress', 'habit-tracker'),
            esc_html__('You need an account to review category trends, streaks, and completion metrics.', 'habit-tracker'),
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
            esc_html__('Log in to review your progress analytics.', 'habit-tracker'),
            esc_url($login_url),
            esc_html__('Login', 'habit-tracker')
        );
    }

    private function renderMetrics(array $category_stats): void
    {
        foreach ($category_stats as $stat) :
            $active_habits = (int) $stat['active_habits'];
            $month_percent = (int) $stat['month_percent'];
            $month_completed = (int) $stat['completed_month'];
            $month_target = (int) $stat['target_month'];
            $streak_days = (int) $stat['streak_days'];
            ?>
            <article class="card app-card app-metric habit-tracker-progress-metric habit-tracker-progress-metric--<?php echo esc_attr((string) $stat['key']); ?>">
                <p class="app-metric__label"><?php echo esc_html((string) $stat['label']); ?></p>
                <?php if ($active_habits <= 0) : ?>
                    <h2 class="app-metric__value"><?php esc_html_e('No Habits', 'habit-tracker'); ?></h2>
                    <p class="app-metric__meta"><?php esc_html_e('Add habits in this category to unlock analytics.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <h2 class="app-metric__value"><?php echo esc_html((string) $month_percent); ?>%</h2>
                    <p class="app-metric__meta">
                        <?php
                        printf(
                            esc_html__('%1$d/%2$d monthly · %3$d day streak', 'habit-tracker'),
                            $month_completed,
                            $month_target,
                            $streak_days
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </article>
        <?php
        endforeach;
    }

    private function renderCharts(
        array $week_chart,
        array $month_chart,
        array $habit_rows,
        bool $has_active_habits
    ): void {
        ?>
        <div class="habit-tracker-progress-charts">
            <?php $this->renderWeeklySnapshotChart($week_chart, $has_active_habits); ?>
            <?php $this->renderMonthlyTrendChart($month_chart, $has_active_habits); ?>
            <?php $this->renderHabitPerformanceChart($habit_rows, $has_active_habits); ?>
        </div>
        <?php
    }

    private function renderWeeklySnapshotChart(array $week_chart, bool $has_active_habits): void
    {
        $rows = isset($week_chart['rows']) && is_array($week_chart['rows']) ? $week_chart['rows'] : [];
        $max_completed = max(1, (int) ($week_chart['max_completed'] ?? 1));
        ?>
        <article class="card app-card habit-tracker-progress-card habit-tracker-progress-card--chart">
            <p class="app-card__eyebrow"><?php esc_html_e('Weekly Chart', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Completions Per Day', 'habit-tracker'); ?></h3>

            <?php if (! $has_active_habits || $rows === []) : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('Add active habits to see weekly chart.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <div class="habit-tracker-progress-week-chart" role="img" aria-label="<?php esc_attr_e('Weekly completion chart', 'habit-tracker'); ?>">
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $completed = (int) ($row['completed'] ?? 0);
                        $height = $completed > 0 ? round(($completed / $max_completed) * 100, 2) : 0;
                        ?>
                        <div class="habit-tracker-progress-week-col" style="--ht-week-bar-height: <?php echo esc_attr((string) $height); ?>%;">
                            <span class="habit-tracker-progress-week-col__track">
                                <span class="habit-tracker-progress-week-col__bar"></span>
                                <span class="habit-tracker-progress-week-col__value"><?php echo esc_html((string) $completed); ?></span>
                            </span>
                            <span class="habit-tracker-progress-week-col__label"><?php echo esc_html((string) ($row['day_label'] ?? '')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderMonthlyTrendChart(array $month_chart, bool $has_active_habits): void
    {
        $dates = isset($month_chart['dates']) && is_array($month_chart['dates']) ? $month_chart['dates'] : [];
        $points = isset($month_chart['points']) && is_array($month_chart['points']) ? $month_chart['points'] : [];
        $rows = isset($month_chart['rows']) && is_array($month_chart['rows']) ? $month_chart['rows'] : [];
        $svg_points = $this->buildSvgPolylinePoints($points);
        $date_count = count($dates);
        $start_label = $date_count > 0 ? wp_date('M j', strtotime((string) $dates[0])) : '';
        $end_label = $date_count > 0 ? wp_date('M j', strtotime((string) $dates[$date_count - 1])) : '';
        $total_completed = 0;
        $total_active = 0;

        foreach ($rows as $row) {
            $total_completed += (int) ($row['completed'] ?? 0);
            $total_active += (int) ($row['active'] ?? 0);
        }

        ?>
        <article class="card app-card habit-tracker-progress-card habit-tracker-progress-card--chart">
            <p class="app-card__eyebrow"><?php esc_html_e('Monthly Chart', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Daily Activity Rate', 'habit-tracker'); ?></h3>

            <?php if (! $has_active_habits || $date_count === 0 || $svg_points === '') : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('Add active habits to see monthly trend.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <div class="habit-tracker-progress-trend-chart" role="img" aria-label="<?php esc_attr_e('Monthly activity trend chart', 'habit-tracker'); ?>">
                    <svg viewBox="0 0 100 42" preserveAspectRatio="none" aria-hidden="true">
                        <line x1="0" y1="6" x2="100" y2="6"></line>
                        <line x1="0" y1="18" x2="100" y2="18"></line>
                        <line x1="0" y1="30" x2="100" y2="30"></line>
                        <polyline class="habit-tracker-progress-trend-path habit-tracker-progress-trend-path--month" points="<?php echo esc_attr($svg_points); ?>"></polyline>
                    </svg>
                </div>

                <div class="habit-tracker-progress-trend-meta">
                    <span><?php echo esc_html($start_label); ?></span>
                    <span>
                        <?php
                        printf(
                            esc_html__('%1$d checks / %2$d slots', 'habit-tracker'),
                            $total_completed,
                            $total_active
                        );
                        ?>
                    </span>
                    <span><?php echo esc_html($end_label); ?></span>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderHabitPerformanceChart(array $habit_rows, bool $has_active_habits): void
    {
        ?>
        <article class="card app-card habit-tracker-progress-card habit-tracker-progress-card--chart habit-tracker-progress-card--full">
            <p class="app-card__eyebrow"><?php esc_html_e('Habit Chart', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Weekly + Monthly Progress Per Habit', 'habit-tracker'); ?></h3>

            <?php if (! $has_active_habits || $habit_rows === []) : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('Add habits to see individual habit chart rows.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <ul class="habit-tracker-progress-habit-chart">
                    <?php foreach ($habit_rows as $row) : ?>
                        <?php $category_key = sanitize_key((string) ($row['category'] ?? HabitRules::CATEGORY_LIFE)); ?>
                        <li class="habit-tracker-progress-habit-row habit-tracker-progress-habit-row--<?php echo esc_attr($category_key); ?>">
                            <div class="habit-tracker-progress-habit-row__head">
                                <span class="habit-tracker-progress-habit-row__name"><?php echo esc_html((string) ($row['name'] ?? '')); ?></span>
                                <span class="habit-tracker-progress-habit-row__meta">
                                    <?php
                                    printf(
                                        esc_html__('W %1$d/%2$d · M %3$d/%4$d', 'habit-tracker'),
                                        (int) ($row['completed_week'] ?? 0),
                                        (int) ($row['target_week'] ?? 0),
                                        (int) ($row['completed_month'] ?? 0),
                                        (int) ($row['target_month'] ?? 0)
                                    );
                                    ?>
                                </span>
                            </div>

                            <div class="habit-tracker-progress-habit-row__line">
                                <span><?php esc_html_e('Week', 'habit-tracker'); ?></span>
                                <span class="habit-tracker-progress-bar">
                                    <span style="width: <?php echo esc_attr((string) (int) ($row['week_percent'] ?? 0)); ?>%;"></span>
                                </span>
                                <strong><?php echo esc_html((string) (int) ($row['week_percent'] ?? 0)); ?>%</strong>
                            </div>

                            <div class="habit-tracker-progress-habit-row__line">
                                <span><?php esc_html_e('Month', 'habit-tracker'); ?></span>
                                <span class="habit-tracker-progress-bar">
                                    <span style="width: <?php echo esc_attr((string) (int) ($row['month_percent'] ?? 0)); ?>%;"></span>
                                </span>
                                <strong><?php echo esc_html((string) (int) ($row['month_percent'] ?? 0)); ?>%</strong>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderCategoryBreakdown(array $category_stats, bool $has_active_habits): void
    {
        ?>
        <article class="card app-card habit-tracker-progress-card habit-tracker-progress-card--breakdown">
            <p class="app-card__eyebrow"><?php esc_html_e('Category Breakdown', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Weekly + Monthly Signals', 'habit-tracker'); ?></h3>

            <?php if (! $has_active_habits) : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('No active habits in your dashboard stack yet.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <ul class="habit-tracker-progress-list">
                    <?php foreach ($category_stats as $stat) : ?>
                        <?php if ((int) $stat['active_habits'] <= 0) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <li class="habit-tracker-progress-item habit-tracker-progress-item--<?php echo esc_attr((string) $stat['key']); ?>">
                            <div class="habit-tracker-progress-item__head">
                                <span class="habit-tracker-progress-item__name"><?php echo esc_html((string) $stat['label']); ?></span>
                                <span class="habit-tracker-progress-item__meta">
                                    <?php
                                    printf(
                                        esc_html(_n('%d habit', '%d habits', (int) $stat['active_habits'], 'habit-tracker')),
                                        (int) $stat['active_habits']
                                    );
                                    ?>
                                </span>
                            </div>

                            <div class="habit-tracker-progress-item__line">
                                <span><?php esc_html_e('Week', 'habit-tracker'); ?></span>
                                <span class="habit-tracker-progress-bar">
                                    <span style="width: <?php echo esc_attr((string) (int) $stat['week_percent']); ?>%;"></span>
                                </span>
                                <strong>
                                    <?php
                                    printf(
                                        esc_html__('%1$d/%2$d', 'habit-tracker'),
                                        (int) $stat['completed_week'],
                                        (int) $stat['target_week']
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div class="habit-tracker-progress-item__line">
                                <span><?php esc_html_e('Month', 'habit-tracker'); ?></span>
                                <span class="habit-tracker-progress-bar">
                                    <span style="width: <?php echo esc_attr((string) (int) $stat['month_percent']); ?>%;"></span>
                                </span>
                                <strong>
                                    <?php
                                    printf(
                                        esc_html__('%1$d/%2$d', 'habit-tracker'),
                                        (int) $stat['completed_month'],
                                        (int) $stat['target_month']
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div class="habit-tracker-progress-item__foot">
                                <span>
                                    <?php
                                    printf(
                                        esc_html__('Today: %1$d/%2$d', 'habit-tracker'),
                                        (int) $stat['checked_today'],
                                        (int) $stat['today_target']
                                    );
                                    ?>
                                </span>
                                <span>
                                    <?php
                                    printf(
                                        esc_html__('Consistency: %d%%', 'habit-tracker'),
                                        (int) $stat['consistency_percent']
                                    );
                                    ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderInsights(array $summary, bool $has_active_habits): void
    {
        ?>
        <div class="app-grid habit-tracker-progress-insights-grid">
            <article class="card app-card app-card--accent habit-tracker-progress-card habit-tracker-progress-card--insights">
                <p class="app-card__eyebrow"><?php esc_html_e('Insights', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Category-driven review for this month.', 'habit-tracker'); ?></h3>

                <?php if (! $has_active_habits) : ?>
                    <p><?php esc_html_e('Build your stack first, then this page will surface category signals and trend comparisons automatically.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <ul class="app-list habit-tracker-progress-insights habit-tracker-progress-insights--category">
                        <li>
                            <?php
                            printf(
                                esc_html__('Monthly completion: %1$d/%2$d (%3$d%%)', 'habit-tracker'),
                                (int) $summary['total_completed_month'],
                                (int) $summary['total_target_month'],
                                (int) $summary['total_percent']
                            );
                            ?>
                        </li>
                        <li>
                            <?php
                            if (is_array($summary['best_category'])) {
                                printf(
                                    esc_html__('Best category: %1$s (%2$d%%)', 'habit-tracker'),
                                    esc_html((string) $summary['best_category']['label']),
                                    (int) $summary['best_category']['month_percent']
                                );
                            } else {
                                esc_html_e('Best category: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                        <li>
                            <?php
                            if (is_array($summary['focus_category'])) {
                                printf(
                                    esc_html__('Focus category: %1$s (%2$d%%)', 'habit-tracker'),
                                    esc_html((string) $summary['focus_category']['label']),
                                    (int) $summary['focus_category']['month_percent']
                                );
                            } else {
                                esc_html_e('Focus category: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                        <li>
                            <?php
                            if (is_array($summary['longest_streak_category'])) {
                                printf(
                                    esc_html__('Longest category streak: %1$s (%2$d days)', 'habit-tracker'),
                                    esc_html((string) $summary['longest_streak_category']['label']),
                                    (int) $summary['longest_streak_category']['streak_days']
                                );
                            } else {
                                esc_html_e('Longest category streak: no active streak yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                    </ul>
                <?php endif; ?>
            </article>

            <article class="card app-card app-card--accent habit-tracker-progress-card habit-tracker-progress-card--insights">
                <p class="app-card__eyebrow"><?php esc_html_e('Insights', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Habit-driven review for this month.', 'habit-tracker'); ?></h3>

                <?php if (! $has_active_habits) : ?>
                    <p><?php esc_html_e('Add habits to unlock individual habit insights and priority signals.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <ul class="app-list habit-tracker-progress-insights habit-tracker-progress-insights--habit">
                        <li>
                            <?php
                            if (is_array($summary['best_habit'])) {
                                printf(
                                    esc_html__('Top habit: %1$s (M %2$d%% · W %3$d%%)', 'habit-tracker'),
                                    esc_html((string) $summary['best_habit']['name']),
                                    (int) $summary['best_habit']['month_percent'],
                                    (int) $summary['best_habit']['week_percent']
                                );
                            } else {
                                esc_html_e('Top habit: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                        <li>
                            <?php
                            if (is_array($summary['focus_habit'])) {
                                printf(
                                    esc_html__('Focus habit: %1$s (M %2$d%% · W %3$d%%)', 'habit-tracker'),
                                    esc_html((string) $summary['focus_habit']['name']),
                                    (int) $summary['focus_habit']['month_percent'],
                                    (int) $summary['focus_habit']['week_percent']
                                );
                            } else {
                                esc_html_e('Focus habit: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                        <li>
                            <?php
                            if (is_array($summary['weekly_leader_habit'])) {
                                printf(
                                    esc_html__('Weekly leader: %1$s (W %2$d%%)', 'habit-tracker'),
                                    esc_html((string) $summary['weekly_leader_habit']['name']),
                                    (int) $summary['weekly_leader_habit']['week_percent']
                                );
                            } else {
                                esc_html_e('Weekly leader: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                    </ul>
                <?php endif; ?>
            </article>
        </div>
        <?php
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

    private function getCurrentUrl(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

        if (! is_string($request_uri) || $request_uri === '') {
            return home_url('/');
        }

        return home_url($request_uri);
    }
}
