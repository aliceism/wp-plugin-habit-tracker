<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class ProfileAccountActions
{
    private const UPDATE_PROFILE_ACTION = 'habitlab_update_profile';
    private const UPDATE_PASSWORD_ACTION = 'habitlab_update_password';
    private const NOTICE_QUERY_KEY = 'hlp_notice';
    private const ERROR_QUERY_KEY = 'hlp_error';

    public function register(): void
    {
        // Remove theme-owned handlers so the plugin becomes the single backend owner.
        add_action('init', [$this, 'removeLegacyThemeCallbacks'], 1);

        add_action('admin_post_' . self::UPDATE_PROFILE_ACTION, [$this, 'handleProfileUpdate']);
        add_action('admin_post_' . self::UPDATE_PASSWORD_ACTION, [$this, 'handleProfilePasswordUpdate']);
        add_action('admin_post_nopriv_' . self::UPDATE_PROFILE_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::UPDATE_PASSWORD_ACTION, [$this, 'handleUnauthorized']);
    }

    public function removeLegacyThemeCallbacks(): void
    {
        remove_action('admin_post_' . self::UPDATE_PROFILE_ACTION, 'habitlab_handle_profile_update');
        remove_action('admin_post_' . self::UPDATE_PASSWORD_ACTION, 'habitlab_handle_profile_password_update');
        remove_action('admin_post_nopriv_' . self::UPDATE_PROFILE_ACTION, 'habitlab_handle_profile_unauthorized');
        remove_action('admin_post_nopriv_' . self::UPDATE_PASSWORD_ACTION, 'habitlab_handle_profile_unauthorized');
    }

    public function handleProfileUpdate(): void
    {
        $user_id = $this->requireAuth();

        check_admin_referer('habitlab_profile_update');

        $display_name = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
        $first_name = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last_name = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $user_email = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));

        if ($user_email === '' || ! is_email($user_email)) {
            $this->redirectWithState('error', 'email-invalid');
        }

        $email_owner = email_exists($user_email);

        if (is_int($email_owner) && $email_owner > 0 && $email_owner !== $user_id) {
            $this->redirectWithState('error', 'email-exists');
        }

        $updated = wp_update_user([
            'ID' => $user_id,
            'user_email' => $user_email,
            'display_name' => $display_name !== '' ? $display_name : $user_email,
        ]);

        if (is_wp_error($updated)) {
            $this->redirectWithState('error', 'profile-save-failed');
        }

        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);

        $this->redirectWithState('notice', 'profile-updated');
    }

    public function handleProfilePasswordUpdate(): void
    {
        $user_id = $this->requireAuth();

        check_admin_referer('habitlab_profile_password_update');

        $current_password = (string) wp_unslash($_POST['current_password'] ?? '');
        $new_password = (string) wp_unslash($_POST['new_password'] ?? '');
        $new_password_confirm = (string) wp_unslash($_POST['new_password_confirm'] ?? '');

        if ($current_password === '' || $new_password === '' || $new_password_confirm === '') {
            $this->redirectWithState('error', 'password-required');
        }

        if ($new_password !== $new_password_confirm) {
            $this->redirectWithState('error', 'password-mismatch');
        }

        if (strlen($new_password) < 8) {
            $this->redirectWithState('error', 'password-short');
        }

        $user = get_user_by('id', $user_id);

        if (! ($user instanceof \WP_User)) {
            $this->redirectWithState('error', 'password-update-failed');
        }

        if (! wp_check_password($current_password, $user->user_pass, $user_id)) {
            $this->redirectWithState('error', 'password-current-invalid');
        }

        wp_set_password($new_password, $user_id);

        $fresh_user = get_user_by('id', $user_id);

        if ($fresh_user instanceof \WP_User) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            do_action('wp_login', $fresh_user->user_login, $fresh_user);
        }

        $this->redirectWithState('notice', 'password-updated');
    }

    public function handleUnauthorized(): void
    {
        $redirect = $this->resolveRedirectTarget();
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    private function requireAuth(): int
    {
        if (is_user_logged_in()) {
            return get_current_user_id();
        }

        $redirect = $this->resolveRedirectTarget();
        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    private function resolveRedirectTarget(): string
    {
        $redirect = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? ''));

        if (is_string($redirect) && $redirect !== '') {
            return $redirect;
        }

        return $this->getProfileUrl();
    }

    private function redirectWithState(string $type, string $state, ?string $target = null): void
    {
        $target_url = $target;

        if (! is_string($target_url) || $target_url === '') {
            $target_url = $this->resolveRedirectTarget();
        }

        $query_key = $type === 'error' ? self::ERROR_QUERY_KEY : self::NOTICE_QUERY_KEY;
        $redirect_url = add_query_arg($query_key, sanitize_key($state), $target_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function getProfileUrl(): string
    {
        if (function_exists('habitlab_get_profile_url')) {
            $profile_url = habitlab_get_profile_url();

            if (is_string($profile_url) && $profile_url !== '') {
                return $profile_url;
            }
        }

        return home_url('/profile');
    }
}
