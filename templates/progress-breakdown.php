<?php
if (! defined('ABSPATH')) {
    exit;
}

$week_chart = isset($week_chart) && is_array($week_chart) ? $week_chart : [];
$month_chart = isset($month_chart) && is_array($month_chart) ? $month_chart : [];
$habit_rows = isset($habit_rows) && is_array($habit_rows) ? $habit_rows : [];
$summary = isset($summary) && is_array($summary) ? $summary : [];
$category_stats = isset($category_stats) && is_array($category_stats) ? $category_stats : [];
$has_active_habits = ! empty($has_active_habits);
$include_root_wrapper = ! empty($include_root_wrapper);
$month_svg_points = isset($month_svg_points) ? (string) $month_svg_points : '';
$category_labels = HabitTracker\Domain\Rules\HabitRules::categoryLabels(true);
$core_category_keys = HabitTracker\Domain\Rules\HabitRules::categoryKeys(false);
$core_category_labels = HabitTracker\Domain\Rules\HabitRules::categoryLabels(false);
$category_stats_map = [];

foreach ($core_category_keys as $core_category_key) {
    $category_stats_map[$core_category_key] = [
        'key' => $core_category_key,
        'label' => (string) ($core_category_labels[$core_category_key] ?? ucfirst($core_category_key)),
        'active_habits' => 0,
        'completed_week' => 0,
        'target_week' => 0,
        'week_percent' => 0,
        'completed_month' => 0,
        'target_month' => 0,
        'month_percent' => 0,
        'checked_today' => 0,
        'today_target' => 0,
        'today_percent' => 0,
        'streak_days' => 0,
        'consistency_percent' => 0,
    ];
}

foreach ($category_stats as $category_stat) {
    $category_key = sanitize_key((string) ($category_stat['key'] ?? ''));

    if (! in_array($category_key, $core_category_keys, true)) {
        continue;
    }

    $category_stats_map[$category_key] = array_merge(
        $category_stats_map[$category_key],
        [
            'label' => (string) ($category_stat['label'] ?? $category_stats_map[$category_key]['label']),
            'active_habits' => (int) ($category_stat['active_habits'] ?? 0),
            'completed_week' => (int) ($category_stat['completed_week'] ?? 0),
            'target_week' => (int) ($category_stat['target_week'] ?? 0),
            'week_percent' => (int) ($category_stat['week_percent'] ?? 0),
            'completed_month' => (int) ($category_stat['completed_month'] ?? 0),
            'target_month' => (int) ($category_stat['target_month'] ?? 0),
            'month_percent' => (int) ($category_stat['month_percent'] ?? 0),
            'checked_today' => (int) ($category_stat['checked_today'] ?? 0),
            'today_target' => (int) ($category_stat['today_target'] ?? 0),
            'today_percent' => (int) ($category_stat['today_percent'] ?? 0),
            'streak_days' => (int) ($category_stat['streak_days'] ?? 0),
            'consistency_percent' => (int) ($category_stat['consistency_percent'] ?? 0),
        ]
    );
}

$week_rows = isset($week_chart['rows']) && is_array($week_chart['rows']) ? $week_chart['rows'] : [];
$week_max_completed = max(1, (int) ($week_chart['max_completed'] ?? 1));
$month_dates = isset($month_chart['dates']) && is_array($month_chart['dates']) ? $month_chart['dates'] : [];
$month_rows = isset($month_chart['rows']) && is_array($month_chart['rows']) ? $month_chart['rows'] : [];
$month_date_count = count($month_dates);
$month_start_label = $month_date_count > 0 ? wp_date('M j', strtotime((string) $month_dates[0])) : '';
$month_end_label = $month_date_count > 0 ? wp_date('M j', strtotime((string) $month_dates[$month_date_count - 1])) : '';
$month_total_completed = 0;
$month_total_active = 0;

foreach ($month_rows as $month_row) {
    $month_total_completed += (int) ($month_row['completed'] ?? 0);
    $month_total_active += (int) ($month_row['active'] ?? 0);
}
?>
<?php if ($include_root_wrapper) : ?>
<div class="habit-tracker-progress">
<?php endif; ?>

    <div class="habit-tracker-progress-charts">
        <article class="card app-card habit-tracker-progress-card habit-tracker-progress-card--chart">
            <p class="app-card__eyebrow"><?php esc_html_e('Weekly Chart', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Completions Per Day', 'habit-tracker'); ?></h3>

            <?php if (! $has_active_habits || $week_rows === []) : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('Add active habits to see weekly chart.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <div class="habit-tracker-progress-week-chart" role="img" aria-label="<?php esc_attr_e('Weekly completion chart', 'habit-tracker'); ?>">
                    <?php foreach ($week_rows as $row) : ?>
                        <?php
                        $completed = (int) ($row['completed'] ?? 0);
                        $height = $completed > 0 ? round(($completed / $week_max_completed) * 100, 2) : 0;
                        $day_label = (string) ($row['day_label'] ?? '');
                        $bar_state_class = $completed > 0 ? 'is-filled' : 'is-empty';
                        ?>
                        <div class="habit-tracker-progress-week-col" style="--ht-week-bar-height: <?php echo esc_attr((string) $height); ?>%;">
                            <span class="habit-tracker-progress-week-col__track">
                                <span class="habit-tracker-progress-week-col__bar <?php echo esc_attr($bar_state_class); ?>">
                                    <span class="habit-tracker-progress-week-col__value"><?php echo esc_html((string) $completed); ?></span>
                                </span>
                            </span>
                            <span class="habit-tracker-progress-week-col__label"><?php echo esc_html($day_label); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="card app-card habit-tracker-progress-card habit-tracker-progress-card--chart">
            <p class="app-card__eyebrow"><?php esc_html_e('Monthly Chart', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Daily Activity Rate', 'habit-tracker'); ?></h3>

            <?php if (! $has_active_habits || $month_date_count === 0 || $month_svg_points === '') : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('Add active habits to see monthly trend.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <div class="habit-tracker-progress-trend-chart" role="img" aria-label="<?php esc_attr_e('Monthly activity trend chart', 'habit-tracker'); ?>">
                    <svg viewBox="0 0 100 42" preserveAspectRatio="none" aria-hidden="true">
                        <line x1="0" y1="6" x2="100" y2="6"></line>
                        <line x1="0" y1="18" x2="100" y2="18"></line>
                        <line x1="0" y1="30" x2="100" y2="30"></line>
                        <polyline class="habit-tracker-progress-trend-path habit-tracker-progress-trend-path--month" points="<?php echo esc_attr($month_svg_points); ?>"></polyline>
                    </svg>
                </div>

                <div class="habit-tracker-progress-trend-meta">
                    <span><?php echo esc_html($month_start_label); ?></span>
                    <span>
                        <?php
                        printf(
                            esc_html__('%1$d checks / %2$d slots', 'habit-tracker'),
                            $month_total_completed,
                            $month_total_active
                        );
                        ?>
                    </span>
                    <span><?php echo esc_html($month_end_label); ?></span>
                </div>
            <?php endif; ?>
        </article>

        <article class="card app-card habit-tracker-progress-card habit-tracker-progress-card--chart habit-tracker-progress-card--full">
            <p class="app-card__eyebrow"><?php esc_html_e('Habit Chart', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Weekly + Monthly Progress Per Habit', 'habit-tracker'); ?></h3>

            <?php if (! $has_active_habits || $habit_rows === []) : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('Add habits to see individual habit chart rows.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <div class="habit-tracker-progress-habit-chart-head" aria-hidden="true">
                    <span><?php esc_html_e('Habit', 'habit-tracker'); ?></span>
                    <span><?php esc_html_e('Weekly', 'habit-tracker'); ?></span>
                    <span><?php esc_html_e('Monthly', 'habit-tracker'); ?></span>
                </div>

                <ul class="habit-tracker-progress-habit-chart">
                    <?php foreach ($habit_rows as $row) : ?>
                        <?php
                        $category_key = sanitize_key((string) ($row['category'] ?? HabitTracker\Domain\Rules\HabitRules::CATEGORY_LIFE));
                        $category_label = (string) ($category_labels[$category_key] ?? ucfirst($category_key));
                        $name = (string) ($row['name'] ?? '');
                        $completed_week = (int) ($row['completed_week'] ?? 0);
                        $target_week = (int) ($row['target_week'] ?? 0);
                        $completed_month = (int) ($row['completed_month'] ?? 0);
                        $target_month = (int) ($row['target_month'] ?? 0);
                        $week_percent = (int) ($row['week_percent'] ?? 0);
                        $month_percent = (int) ($row['month_percent'] ?? 0);
                        ?>
                        <li class="habit-tracker-progress-habit-row habit-tracker-progress-habit-row--<?php echo esc_attr($category_key); ?>">
                            <div class="habit-tracker-progress-habit-row__head">
                                <span class="habit-tracker-progress-habit-row__name"><?php echo esc_html($name); ?></span>
                                <span class="habit-tracker-progress-habit-row__meta"><?php echo esc_html($category_label); ?></span>
                            </div>

                            <div class="habit-tracker-progress-habit-row__line habit-tracker-progress-habit-row__line--week">
                                <span class="habit-tracker-progress-habit-row__line-label"><?php esc_html_e('Week', 'habit-tracker'); ?></span>
                                <span class="habit-tracker-progress-bar">
                                    <span style="width: <?php echo esc_attr((string) $week_percent); ?>%;"></span>
                                </span>
                                <strong>
                                    <?php
                                    printf(
                                        esc_html__('%1$d/%2$d · %3$d%%', 'habit-tracker'),
                                        $completed_week,
                                        $target_week,
                                        $week_percent
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div class="habit-tracker-progress-habit-row__line habit-tracker-progress-habit-row__line--month">
                                <span class="habit-tracker-progress-habit-row__line-label"><?php esc_html_e('Month', 'habit-tracker'); ?></span>
                                <span class="habit-tracker-progress-bar">
                                    <span style="width: <?php echo esc_attr((string) $month_percent); ?>%;"></span>
                                </span>
                                <strong>
                                    <?php
                                    printf(
                                        esc_html__('%1$d/%2$d · %3$d%%', 'habit-tracker'),
                                        $completed_month,
                                        $target_month,
                                        $month_percent
                                    );
                                    ?>
                                </strong>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </div>

    <div class="habit-tracker-progress-sections">
        <article class="card app-card habit-tracker-progress-card habit-tracker-progress-card--breakdown">
            <p class="app-card__eyebrow"><?php esc_html_e('Category Breakdown', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Weekly + Monthly Signals', 'habit-tracker'); ?></h3>

            <p class="habit-tracker-progress-breakdown-intro"><?php esc_html_e('Tap a category to open a modal with full weekly + monthly details.', 'habit-tracker'); ?></p>

            <ul class="habit-tracker-progress-category-grid">
                <?php foreach ($core_category_keys as $category_key) : ?>
                    <?php
                    $category_stat = $category_stats_map[$category_key] ?? [];
                    $category_label = (string) ($category_stat['label'] ?? ($core_category_labels[$category_key] ?? ucfirst($category_key)));
                    $active_habits = (int) ($category_stat['active_habits'] ?? 0);
                    $week_percent = (int) ($category_stat['week_percent'] ?? 0);
                    $month_percent = (int) ($category_stat['month_percent'] ?? 0);
                    $modal_id = 'habit-tracker-progress-category-modal-' . $category_key;
                    ?>
                    <li class="habit-tracker-progress-category-grid__item">
                        <button
                            type="button"
                            class="habit-tracker-progress-category-trigger habit-tracker-progress-category-trigger--<?php echo esc_attr($category_key); ?>"
                            data-ht-open-modal="<?php echo esc_attr($modal_id); ?>"
                            aria-haspopup="dialog"
                        >
                            <span class="habit-tracker-progress-category-trigger__head">
                                <span class="habit-tracker-progress-category-trigger__name"><?php echo esc_html($category_label); ?></span>
                                <span class="habit-tracker-progress-category-trigger__meta">
                                    <?php
                                    printf(
                                        esc_html(_n('%d habit', '%d habits', $active_habits, 'habit-tracker')),
                                        $active_habits
                                    );
                                    ?>
                                </span>
                            </span>

                            <span class="habit-tracker-progress-category-trigger__signals">
                                <span class="habit-tracker-progress-category-trigger__signal">
                                    <small><?php esc_html_e('Week', 'habit-tracker'); ?></small>
                                    <strong><?php echo esc_html((string) $week_percent); ?>%</strong>
                                </span>
                                <span class="habit-tracker-progress-category-trigger__signal">
                                    <small><?php esc_html_e('Month', 'habit-tracker'); ?></small>
                                    <strong><?php echo esc_html((string) $month_percent); ?>%</strong>
                                </span>
                            </span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php foreach ($core_category_keys as $category_key) : ?>
                <?php
                $category_stat = $category_stats_map[$category_key] ?? [];
                $category_label = (string) ($category_stat['label'] ?? ($core_category_labels[$category_key] ?? ucfirst($category_key)));
                $active_habits = (int) ($category_stat['active_habits'] ?? 0);
                $completed_week = (int) ($category_stat['completed_week'] ?? 0);
                $target_week = (int) ($category_stat['target_week'] ?? 0);
                $week_percent = (int) ($category_stat['week_percent'] ?? 0);
                $completed_month = (int) ($category_stat['completed_month'] ?? 0);
                $target_month = (int) ($category_stat['target_month'] ?? 0);
                $month_percent = (int) ($category_stat['month_percent'] ?? 0);
                $checked_today = (int) ($category_stat['checked_today'] ?? 0);
                $today_target = (int) ($category_stat['today_target'] ?? 0);
                $today_percent = (int) ($category_stat['today_percent'] ?? 0);
                $consistency_percent = (int) ($category_stat['consistency_percent'] ?? 0);
                $streak_days = (int) ($category_stat['streak_days'] ?? 0);
                $modal_id = 'habit-tracker-progress-category-modal-' . $category_key;
                $modal_title_id = $modal_id . '-title';
                ?>
                <div class="habit-tracker-modal" data-ht-modal="<?php echo esc_attr($modal_id); ?>" hidden>
                    <div class="habit-tracker-modal__backdrop" data-ht-close-modal></div>
                    <div
                        class="habit-tracker-modal__panel habit-tracker-modal__panel--<?php echo esc_attr($category_key); ?> habit-tracker-progress-category-modal__panel"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="<?php echo esc_attr($modal_title_id); ?>"
                    >
                        <div class="habit-tracker-modal__header">
                            <h4 id="<?php echo esc_attr($modal_title_id); ?>"><?php echo esc_html(sprintf(__('%s Signals', 'habit-tracker'), $category_label)); ?></h4>
                            <button type="button" class="habit-tracker-modal__close" data-ht-close-modal aria-label="<?php esc_attr_e('Close', 'habit-tracker'); ?>">
                                &times;
                            </button>
                        </div>

                        <?php if ($active_habits <= 0) : ?>
                            <p class="habit-tracker-progress-category-modal__empty">
                                <?php
                                printf(
                                    esc_html__('No active habits in %s yet. Add habits to this category to unlock weekly and monthly analytics.', 'habit-tracker'),
                                    esc_html($category_label)
                                );
                                ?>
                            </p>
                        <?php else : ?>
                            <ul class="habit-tracker-modal-list habit-tracker-progress-category-modal__list">
                                <li class="habit-tracker-modal-list__item">
                                    <div class="habit-tracker-modal-list__content">
                                        <h4><?php esc_html_e('Weekly Progress', 'habit-tracker'); ?></h4>
                                        <p>
                                            <?php
                                            printf(
                                                esc_html__('%1$d/%2$d completions in the current week.', 'habit-tracker'),
                                                $completed_week,
                                                $target_week
                                            );
                                            ?>
                                        </p>
                                    </div>
                                    <div class="habit-tracker-modal-list__action">
                                        <strong class="habit-tracker-progress-category-modal__value"><?php echo esc_html((string) $week_percent); ?>%</strong>
                                    </div>
                                </li>

                                <li class="habit-tracker-modal-list__item">
                                    <div class="habit-tracker-modal-list__content">
                                        <h4><?php esc_html_e('Monthly Progress', 'habit-tracker'); ?></h4>
                                        <p>
                                            <?php
                                            printf(
                                                esc_html__('%1$d/%2$d completions this month.', 'habit-tracker'),
                                                $completed_month,
                                                $target_month
                                            );
                                            ?>
                                        </p>
                                    </div>
                                    <div class="habit-tracker-modal-list__action">
                                        <strong class="habit-tracker-progress-category-modal__value"><?php echo esc_html((string) $month_percent); ?>%</strong>
                                    </div>
                                </li>

                                <li class="habit-tracker-modal-list__item">
                                    <div class="habit-tracker-modal-list__content">
                                        <h4><?php esc_html_e('Today', 'habit-tracker'); ?></h4>
                                        <p>
                                            <?php
                                            printf(
                                                esc_html__('%1$d/%2$d completed today.', 'habit-tracker'),
                                                $checked_today,
                                                $today_target
                                            );
                                            ?>
                                        </p>
                                    </div>
                                    <div class="habit-tracker-modal-list__action">
                                        <strong class="habit-tracker-progress-category-modal__value"><?php echo esc_html((string) $today_percent); ?>%</strong>
                                    </div>
                                </li>

                                <li class="habit-tracker-modal-list__item">
                                    <div class="habit-tracker-modal-list__content">
                                        <h4><?php esc_html_e('Consistency', 'habit-tracker'); ?></h4>
                                        <p><?php esc_html_e('Category consistency across active days in the month.', 'habit-tracker'); ?></p>
                                    </div>
                                    <div class="habit-tracker-modal-list__action">
                                        <strong class="habit-tracker-progress-category-modal__value"><?php echo esc_html((string) $consistency_percent); ?>%</strong>
                                    </div>
                                </li>

                                <li class="habit-tracker-modal-list__item">
                                    <div class="habit-tracker-modal-list__content">
                                        <h4><?php esc_html_e('Active Streak', 'habit-tracker'); ?></h4>
                                        <p><?php esc_html_e('Current streak for this category.', 'habit-tracker'); ?></p>
                                    </div>
                                    <div class="habit-tracker-modal-list__action">
                                        <strong class="habit-tracker-progress-category-modal__value"><?php echo esc_html((string) $streak_days); ?>d</strong>
                                    </div>
                                </li>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </article>

        <div class="app-grid habit-tracker-progress-insights-grid">
            <article class="card app-card app-card--accent habit-tracker-progress-card habit-tracker-progress-card--insights habit-tracker-progress-card--insights-category">
                <p class="app-card__eyebrow"><?php esc_html_e('Insights', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Category-driven review for this month.', 'habit-tracker'); ?></h3>

                <?php if (! $has_active_habits) : ?>
                    <p class="habit-tracker-progress-insights__empty"><?php esc_html_e('Build your stack first, then this page will surface category signals and trend comparisons automatically.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <ul class="app-list habit-tracker-progress-insights habit-tracker-progress-insights--category">
                        <li class="habit-tracker-progress-insight-item">
                            <?php
                            if (is_array($summary['best_category'] ?? null)) {
                                printf(
                                    esc_html__('Best category: %1$s (M %2$d%% · W %3$d%%)', 'habit-tracker'),
                                    esc_html((string) ($summary['best_category']['label'] ?? '')),
                                    (int) ($summary['best_category']['month_percent'] ?? 0),
                                    (int) ($summary['best_category']['week_percent'] ?? 0)
                                );
                            } else {
                                esc_html_e('Best category: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                        <li class="habit-tracker-progress-insight-item">
                            <?php
                            if (is_array($summary['focus_category'] ?? null)) {
                                printf(
                                    esc_html__('Focus category: %1$s (M %2$d%% · W %3$d%%)', 'habit-tracker'),
                                    esc_html((string) ($summary['focus_category']['label'] ?? '')),
                                    (int) ($summary['focus_category']['month_percent'] ?? 0),
                                    (int) ($summary['focus_category']['week_percent'] ?? 0)
                                );
                            } else {
                                esc_html_e('Focus category: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                        <li class="habit-tracker-progress-insight-item">
                            <?php
                            if (is_array($summary['weekly_leader_category'] ?? null)) {
                                printf(
                                    esc_html__('Weekly leader: %1$s (W %2$d%%)', 'habit-tracker'),
                                    esc_html((string) ($summary['weekly_leader_category']['label'] ?? '')),
                                    (int) ($summary['weekly_leader_category']['week_percent'] ?? 0)
                                );
                            } else {
                                esc_html_e('Weekly leader: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                    </ul>
                <?php endif; ?>
            </article>

            <article class="card app-card app-card--accent habit-tracker-progress-card habit-tracker-progress-card--insights habit-tracker-progress-card--insights-habit">
                <p class="app-card__eyebrow"><?php esc_html_e('Insights', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Habit-driven review for this month.', 'habit-tracker'); ?></h3>

                <?php if (! $has_active_habits) : ?>
                    <p class="habit-tracker-progress-insights__empty"><?php esc_html_e('Add habits to unlock individual habit insights and priority signals.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <ul class="app-list habit-tracker-progress-insights habit-tracker-progress-insights--habit">
                        <li class="habit-tracker-progress-insight-item">
                            <?php
                            if (is_array($summary['best_habit'] ?? null)) {
                                printf(
                                    esc_html__('Top habit: %1$s (M %2$d%% · W %3$d%%)', 'habit-tracker'),
                                    esc_html((string) ($summary['best_habit']['name'] ?? '')),
                                    (int) ($summary['best_habit']['month_percent'] ?? 0),
                                    (int) ($summary['best_habit']['week_percent'] ?? 0)
                                );
                            } else {
                                esc_html_e('Top habit: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                        <li class="habit-tracker-progress-insight-item">
                            <?php
                            if (is_array($summary['focus_habit'] ?? null)) {
                                printf(
                                    esc_html__('Focus habit: %1$s (M %2$d%% · W %3$d%%)', 'habit-tracker'),
                                    esc_html((string) ($summary['focus_habit']['name'] ?? '')),
                                    (int) ($summary['focus_habit']['month_percent'] ?? 0),
                                    (int) ($summary['focus_habit']['week_percent'] ?? 0)
                                );
                            } else {
                                esc_html_e('Focus habit: not enough data yet.', 'habit-tracker');
                            }
                            ?>
                        </li>
                        <li class="habit-tracker-progress-insight-item">
                            <?php
                            if (is_array($summary['weekly_leader_habit'] ?? null)) {
                                printf(
                                    esc_html__('Weekly leader: %1$s (W %2$d%%)', 'habit-tracker'),
                                    esc_html((string) ($summary['weekly_leader_habit']['name'] ?? '')),
                                    (int) ($summary['weekly_leader_habit']['week_percent'] ?? 0)
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
    </div>

<?php if ($include_root_wrapper) : ?>
</div>
<?php endif; ?>
