<?php
if (! defined('ABSPATH')) {
    exit;
}

$top_habits = isset($top_habits) && is_array($top_habits) ? $top_habits : [];
$active_streaks = isset($active_streaks) && is_array($active_streaks) ? $active_streaks : [];
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
                <?php
                $category = sanitize_key((string) ($top_habit['category'] ?? 'life'));
                $name = (string) ($top_habit['name'] ?? '');
                $completed = (int) ($top_habit['completed'] ?? 0);
                $target_total = (int) ($top_habit['target_total'] ?? 0);
                $progress_percent = (int) ($top_habit['progress_percent'] ?? 0);
                ?>
                <li class="habit-tracker-ranking-item habit-tracker-ranking-item--<?php echo esc_attr($category); ?>">
                    <span class="habit-tracker-ranking-item__name"><?php echo esc_html($name); ?></span>
                    <span
                        class="habit-tracker-ranking-meter"
                        aria-label="<?php echo esc_attr(sprintf(esc_html__('%1$d of %2$d target completions', 'habit-tracker'), $completed, $target_total)); ?>"
                    >
                        <span class="habit-tracker-ranking-meter__fill<?php echo $progress_percent > 0 ? ' has-value' : ''; ?>" style="width: <?php echo esc_attr((string) $progress_percent); ?>%;">
                            <span class="habit-tracker-ranking-meter__count"><?php echo esc_html((string) $progress_percent); ?>%</span>
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
                $category = sanitize_key((string) ($streak_row['category'] ?? 'life'));
                $name = (string) ($streak_row['name'] ?? '');
                $streak = (int) ($streak_row['streak'] ?? 0);
                $streak_percent = (int) ($streak_row['streak_percent'] ?? 0);
                $streak_unit = (string) ($streak_row['streak_unit'] ?? 'day');
                $streak_aria = $streak_unit === 'week'
                    ? sprintf(_n('%d week streak', '%d weeks streak', $streak, 'habit-tracker'), $streak)
                    : sprintf(_n('%d day streak', '%d days streak', $streak, 'habit-tracker'), $streak);
                ?>
                <li class="habit-tracker-streak-item habit-tracker-streak-item--<?php echo esc_attr($category); ?>">
                    <span class="habit-tracker-streak-item__name"><?php echo esc_html($name); ?></span>
                    <span class="habit-tracker-streak-meter" aria-label="<?php echo esc_attr($streak_aria); ?>">
                        <span class="habit-tracker-streak-meter__fill" style="width: <?php echo esc_attr((string) $streak_percent); ?>%;">
                            <span class="habit-tracker-streak-meter__count"><?php echo esc_html((string) $streak); ?></span>
                        </span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>
