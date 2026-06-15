<?php
if (! defined('ABSPATH')) {
    exit;
}

$feedback = isset($feedback) && is_array($feedback) ? $feedback : null;
$first_name = isset($first_name) ? (string) $first_name : '';
$last_name = isset($last_name) ? (string) $last_name : '';
$display_name = isset($display_name) ? (string) $display_name : '';
$email = isset($email) ? (string) $email : '';
$registered_label = isset($registered_label) ? (string) $registered_label : '';
$role_label = isset($role_label) ? (string) $role_label : __('Member', 'habit-tracker');
$profile_url = isset($profile_url) && is_string($profile_url) && $profile_url !== ''
    ? $profile_url
    : home_url('/profile');
$admin_post_url = isset($admin_post_url) && is_string($admin_post_url) && $admin_post_url !== ''
    ? $admin_post_url
    : admin_url('admin-post.php');
$update_profile_action = isset($update_profile_action) && is_string($update_profile_action) && $update_profile_action !== ''
    ? $update_profile_action
    : 'habitlab_update_profile';
$update_password_action = isset($update_password_action) && is_string($update_password_action) && $update_password_action !== ''
    ? $update_password_action
    : 'habitlab_update_password';
?>
<?php if ($feedback !== null) : ?>
    <p class="habitlab-profile-alert habitlab-profile-alert--<?php echo esc_attr((string) ($feedback['type'] ?? 'success')); ?>">
        <?php echo esc_html((string) ($feedback['text'] ?? '')); ?>
    </p>
<?php endif; ?>

<div class="habitlab-profile-hybrid" aria-label="<?php esc_attr_e('Profile sections', 'habit-tracker'); ?>">
    <article class="card habit-tracker-block habitlab-profile-card habitlab-profile-card--overview habitlab-profile-hybrid__overview">
        <div class="habitlab-profile-card__head">
            <p class="app-card__eyebrow"><?php esc_html_e('Overview', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Account Snapshot', 'habit-tracker'); ?></h3>
        </div>
        <dl class="habitlab-profile-summary">
            <div class="habitlab-profile-summary__row">
                <dt><?php esc_html_e('Email', 'habit-tracker'); ?></dt>
                <dd><?php echo esc_html($email); ?></dd>
            </div>
            <div class="habitlab-profile-summary__row">
                <dt><?php esc_html_e('Role', 'habit-tracker'); ?></dt>
                <dd><?php echo esc_html($role_label); ?></dd>
            </div>
            <div class="habitlab-profile-summary__row">
                <dt><?php esc_html_e('Member since', 'habit-tracker'); ?></dt>
                <dd><?php echo esc_html($registered_label); ?></dd>
            </div>
        </dl>
    </article>

    <div class="habitlab-profile-hybrid__actions">
        <div class="habitlab-profile-action-group">
            <p class="habitlab-profile-action-hint"><?php esc_html_e('Update your account details', 'habit-tracker'); ?></p>
            <button
                type="button"
                class="card habit-tracker-block habitlab-profile-action-btn habitlab-profile-action-btn--details"
                data-ht-open-modal="habitlab-profile-modal-details"
                aria-haspopup="dialog"
            >
                <h3><?php esc_html_e('Profile', 'habit-tracker'); ?></h3>
            </button>
        </div>

        <div class="habitlab-profile-action-group">
            <p class="habitlab-profile-action-hint"><?php esc_html_e('Manage password and account access', 'habit-tracker'); ?></p>
            <button
                type="button"
                class="card habit-tracker-block habitlab-profile-action-btn habitlab-profile-action-btn--password"
                data-ht-open-modal="habitlab-profile-modal-password"
                aria-haspopup="dialog"
            >
                <h3><?php esc_html_e('Security', 'habit-tracker'); ?></h3>
            </button>
        </div>
    </div>
</div>

<div
    id="habitlab-profile-modal-details"
    class="system-modal habitlab-profile-modal"
    data-ht-modal="habitlab-profile-modal-details"
    role="dialog"
    aria-modal="true"
    aria-labelledby="habitlab-profile-modal-details-title"
    hidden
>
    <div class="system-modal__backdrop" data-ht-close-modal></div>
    <div class="system-modal__dialog" role="document" tabindex="-1">
        <button class="system-modal__close" type="button" data-ht-close-modal>
            <span aria-hidden="true">&times;</span>
            <span class="screen-reader-text"><?php esc_html_e('Close', 'habit-tracker'); ?></span>
        </button>

        <p class="system-modal__eyebrow"><?php esc_html_e('Profile', 'habit-tracker'); ?></p>
        <h3 id="habitlab-profile-modal-details-title">
            <?php esc_html_e('Update Account Details', 'habit-tracker'); ?>
        </h3>

        <div class="habitlab-profile-modal__body">
            <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="habitlab-profile-form">
                <input type="hidden" name="action" value="<?php echo esc_attr($update_profile_action); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($profile_url); ?>">
                <?php wp_nonce_field('habitlab_profile_update'); ?>

                <p>
                    <label for="habitlab-profile-display-name"><?php esc_html_e('Display name', 'habit-tracker'); ?></label>
                    <input
                        id="habitlab-profile-display-name"
                        type="text"
                        name="display_name"
                        value="<?php echo esc_attr($display_name); ?>"
                        autocomplete="name"
                    >
                </p>
                <p>
                    <label for="habitlab-profile-first-name"><?php esc_html_e('First name', 'habit-tracker'); ?></label>
                    <input
                        id="habitlab-profile-first-name"
                        type="text"
                        name="first_name"
                        value="<?php echo esc_attr($first_name); ?>"
                        autocomplete="given-name"
                    >
                </p>
                <p>
                    <label for="habitlab-profile-last-name"><?php esc_html_e('Last name', 'habit-tracker'); ?></label>
                    <input
                        id="habitlab-profile-last-name"
                        type="text"
                        name="last_name"
                        value="<?php echo esc_attr($last_name); ?>"
                        autocomplete="family-name"
                    >
                </p>
                <p>
                    <label for="habitlab-profile-email"><?php esc_html_e('Email', 'habit-tracker'); ?></label>
                    <input
                        id="habitlab-profile-email"
                        type="email"
                        name="user_email"
                        value="<?php echo esc_attr($email); ?>"
                        autocomplete="email"
                        required
                    >
                </p>
                <p>
                    <button type="submit" class="btn btn-primary"><?php esc_html_e('Save Profile', 'habit-tracker'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>

<div
    id="habitlab-profile-modal-password"
    class="system-modal habitlab-profile-modal"
    data-ht-modal="habitlab-profile-modal-password"
    role="dialog"
    aria-modal="true"
    aria-labelledby="habitlab-profile-modal-password-title"
    hidden
>
    <div class="system-modal__backdrop" data-ht-close-modal></div>
    <div class="system-modal__dialog" role="document" tabindex="-1">
        <button class="system-modal__close" type="button" data-ht-close-modal>
            <span aria-hidden="true">&times;</span>
            <span class="screen-reader-text"><?php esc_html_e('Close', 'habit-tracker'); ?></span>
        </button>

        <p class="system-modal__eyebrow"><?php esc_html_e('Security', 'habit-tracker'); ?></p>
        <h3 id="habitlab-profile-modal-password-title"><?php esc_html_e('Change Password', 'habit-tracker'); ?></h3>

        <div class="habitlab-profile-modal__body">
            <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="habitlab-profile-form habitlab-profile-form--password">
                <input type="hidden" name="action" value="<?php echo esc_attr($update_password_action); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($profile_url); ?>">
                <?php wp_nonce_field('habitlab_profile_password_update'); ?>

                <p>
                    <label for="habitlab-profile-current-password"><?php esc_html_e('Current password', 'habit-tracker'); ?></label>
                    <input
                        id="habitlab-profile-current-password"
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >
                </p>
                <p>
                    <label for="habitlab-profile-new-password"><?php esc_html_e('New password', 'habit-tracker'); ?></label>
                    <input
                        id="habitlab-profile-new-password"
                        type="password"
                        name="new_password"
                        autocomplete="new-password"
                        required
                    >
                </p>
                <p>
                    <label for="habitlab-profile-new-password-confirm"><?php esc_html_e('Confirm new password', 'habit-tracker'); ?></label>
                    <input
                        id="habitlab-profile-new-password-confirm"
                        type="password"
                        name="new_password_confirm"
                        autocomplete="new-password"
                        required
                    >
                </p>
                <p>
                    <button type="submit" class="btn btn-primary"><?php esc_html_e('Update Password', 'habit-tracker'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>
