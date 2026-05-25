<?php
if (! defined('ABSPATH')) {
    exit;
}

$login_url = isset($login_url) && is_string($login_url) && $login_url !== ''
    ? $login_url
    : wp_login_url(home_url('/'));
$kicker = isset($kicker) ? (string) $kicker : '';
$title = isset($title) ? (string) $title : '';
$description = isset($description) ? (string) $description : '';
$button_text = isset($button_text) ? (string) $button_text : __('Login', 'habit-tracker');
?>
<article class="card app-card habit-tracker-block habit-tracker-logged-out ht-card ht-empty-state">
    <?php if ($kicker !== '') : ?>
        <p class="app-card__eyebrow"><?php echo esc_html($kicker); ?></p>
    <?php endif; ?>
    <?php if ($title !== '') : ?>
        <h3><?php echo esc_html($title); ?></h3>
    <?php endif; ?>
    <?php if ($description !== '') : ?>
        <p><?php echo esc_html($description); ?></p>
    <?php endif; ?>
    <a class="btn btn-primary ht-button" href="<?php echo esc_url($login_url); ?>">
        <?php echo esc_html($button_text); ?>
    </a>
</article>
