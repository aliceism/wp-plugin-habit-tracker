<?php
if (! defined('ABSPATH')) {
    exit;
}

$cards = isset($cards) && is_array($cards) ? $cards : [];
?>
<div class="app-section app-metrics-grid habit-tracker-dashboard-metrics">
    <?php foreach ($cards as $card) : ?>
        <?php
        $card_class = sanitize_key((string) ($card['class'] ?? ''));
        $card_value = (int) ($card['value'] ?? 0);
        $card_label = (string) ($card['label'] ?? '');
        $card_stat = (string) ($card['stat'] ?? '');
        $card_meta = (string) ($card['meta'] ?? '');
        ?>
        <article class="card app-card app-metric habit-tracker-metric-card habit-tracker-metric-card--<?php echo esc_attr($card_class); ?>">
            <div class="habit-tracker-metric-orb" style="--ht-value: <?php echo esc_attr((string) $card_value); ?>;">
                <span><?php echo esc_html((string) $card_value); ?>%</span>
            </div>
            <div class="habit-tracker-metric-content">
                <?php if ($card_label !== '') : ?>
                    <p class="app-metric__label"><?php echo esc_html($card_label); ?></p>
                <?php endif; ?>
                <h2 class="app-metric__value"><?php echo esc_html($card_stat); ?></h2>
                <p class="app-metric__meta"><?php echo esc_html($card_meta); ?></p>
            </div>
        </article>
    <?php endforeach; ?>
</div>
