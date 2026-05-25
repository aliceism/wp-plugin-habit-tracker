<?php
if (! defined('ABSPATH')) {
    exit;
}

$notice_html = isset($notice_html) ? (string) $notice_html : '';
$form_action = isset($form_action) ? (string) $form_action : admin_url('admin-post.php');
$redirect = isset($redirect) ? (string) $redirect : home_url('/');
$register_action = isset($register_action) ? (string) $register_action : 'habit_tracker_register_user';
?>
<?php echo $notice_html; ?>
<form class="habit-tracker-register-form" action="<?php echo esc_url($form_action); ?>" method="post">
    <input type="hidden" name="action" value="<?php echo esc_attr($register_action); ?>">
    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
    <?php wp_nonce_field($register_action); ?>
    <p>
        <label for="habit-tracker-register-email"><?php esc_html_e('Email', 'habit-tracker'); ?></label>
        <input id="habit-tracker-register-email" name="user_email" type="email" required autocomplete="email">
    </p>
    <p>
        <label for="habit-tracker-register-password"><?php esc_html_e('Password', 'habit-tracker'); ?></label>
        <input id="habit-tracker-register-password" name="user_pass" type="password" required autocomplete="new-password">
    </p>
    <p>
        <label for="habit-tracker-register-password-confirm"><?php esc_html_e('Confirm password', 'habit-tracker'); ?></label>
        <input id="habit-tracker-register-password-confirm" name="user_pass_confirm" type="password" required autocomplete="new-password">
    </p>
    <p>
        <button type="submit" class="btn btn-primary"><?php esc_html_e('Create account', 'habit-tracker'); ?></button>
    </p>
</form>
