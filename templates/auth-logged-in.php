<?php
if (! defined('ABSPATH')) {
    exit;
}

$message = isset($message) ? (string) $message : __('You are already logged in. Continue to your dashboard.', 'habit-tracker');
$dashboard_url = isset($dashboard_url) ? (string) $dashboard_url : home_url('/');
$button_text = isset($button_text) ? (string) $button_text : __('Open Dashboard', 'habit-tracker');
?>
<div class="auth-state auth-state--logged-in">
    <p><?php echo esc_html($message); ?></p>
    <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url); ?>">
        <?php echo esc_html($button_text); ?>
    </a>
</div>
