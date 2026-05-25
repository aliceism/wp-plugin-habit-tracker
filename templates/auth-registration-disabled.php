<?php
if (! defined('ABSPATH')) {
    exit;
}

$notice_html = isset($notice_html) ? (string) $notice_html : '';
$message = isset($message) ? (string) $message : __('Registration is currently disabled. Use login if you already have an account.', 'habit-tracker');
$login_url = isset($login_url) ? (string) $login_url : wp_login_url(home_url('/'));
$button_text = isset($button_text) ? (string) $button_text : __('Go to Login', 'habit-tracker');
?>
<?php echo $notice_html; ?>
<div class="auth-state">
    <p><?php echo esc_html($message); ?></p>
    <a class="btn btn-primary" href="<?php echo esc_url($login_url); ?>">
        <?php echo esc_html($button_text); ?>
    </a>
</div>
