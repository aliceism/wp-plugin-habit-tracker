<?php
if (! defined('ABSPATH')) {
    exit;
}

$category_stats = isset($category_stats) && is_array($category_stats) ? $category_stats : [];
?>
<div class="app-section app-metrics-grid habit-tracker-progress-metrics">
    <?php foreach ($category_stats as $stat) : ?>
        <?php
        $active_habits = (int) ($stat['active_habits'] ?? 0);
        $month_percent = (int) ($stat['month_percent'] ?? 0);
        $month_completed = (int) ($stat['completed_month'] ?? 0);
        $month_target = (int) ($stat['target_month'] ?? 0);
        $streak_days = (int) ($stat['streak_days'] ?? 0);
        $key = sanitize_key((string) ($stat['key'] ?? HabitTracker\Domain\Rules\HabitRules::CATEGORY_LIFE));
        $label = (string) ($stat['label'] ?? '');
        ?>
        <article class="card app-card app-metric habit-tracker-progress-metric habit-tracker-progress-metric--<?php echo esc_attr($key); ?>">
            <p class="app-metric__label"><?php echo esc_html($label); ?></p>
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
    <?php endforeach; ?>
</div>
