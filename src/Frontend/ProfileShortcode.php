<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class ProfileShortcode
{
    private const SHORTCODE_ALL = 'habit_tracker_profile';
    private const UPDATE_PROFILE_ACTION = 'habitlab_update_profile';
    private const UPDATE_PASSWORD_ACTION = 'habitlab_update_password';
    private const NOTICE_QUERY_KEY = 'hlp_notice';
    private const ERROR_QUERY_KEY = 'hlp_error';

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_ALL, [$this, 'render']);

        add_action('admin_post_' . self::UPDATE_PROFILE_ACTION, [$this, 'handleProfileUpdate']);
        add_action('admin_post_' . self::UPDATE_PASSWORD_ACTION, [$this, 'handleProfilePasswordUpdate']);
        add_action('admin_post_nopriv_' . self::UPDATE_PROFILE_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::UPDATE_PASSWORD_ACTION, [$this, 'handleUnauthorized']);
    }

    public function render(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        FrontendAssets::enqueue();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->buildLoggedInContext();
        $feedback = $this->resolveFeedback();

        return $this->renderTemplate(
            'profile',
            [
                'feedback' => $feedback,
                'first_name' => $context['first_name'],
                'last_name' => $context['last_name'],
                'display_name' => $context['display_name'],
                'email' => $context['email'],
                'registered_label' => $context['registered_label'],
                'role_label' => $context['role_label'],
                'profile_url' => $this->getProfileUrl(),
                'admin_post_url' => admin_url('admin-post.php'),
                'update_profile_action' => self::UPDATE_PROFILE_ACTION,
                'update_password_action' => self::UPDATE_PASSWORD_ACTION,
            ]
        );
    }

    private function buildLoggedInContext(): array
    {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        $first_name = (string) get_user_meta($user_id, 'first_name', true);
        $last_name = (string) get_user_meta($user_id, 'last_name', true);
        $display_name = $user instanceof \WP_User ? (string) $user->display_name : '';
        $email = $user instanceof \WP_User ? (string) $user->user_email : '';
        $registered = $user instanceof \WP_User ? (string) $user->user_registered : '';
        $registered_label = '';
        $registered_timestamp = strtotime($registered);

        if ($registered !== '' && $registered_timestamp !== false) {
            $registered_label = wp_date(get_option('date_format'), $registered_timestamp);
        }

        $role_label = __('Member', 'habit-tracker');
        $role_key = $user instanceof \WP_User && is_array($user->roles) && isset($user->roles[0])
            ? (string) $user->roles[0]
            : '';
        $roles = wp_roles();

        if ($role_key !== '' && isset($roles->role_names[$role_key])) {
            $role_label = translate_user_role($roles->role_names[$role_key]);
        }

        return [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name,
            'email' => $email,
            'registered_label' => $registered_label,
            'role_label' => $role_label,
        ];
    }

    private function resolveFeedback(): ?array
    {
        $notice = sanitize_key((string) wp_unslash($_GET[self::NOTICE_QUERY_KEY] ?? ''));
        $error = sanitize_key((string) wp_unslash($_GET[self::ERROR_QUERY_KEY] ?? ''));
        $feedback_key = $notice !== '' ? $notice : $error;

        if ($feedback_key === '') {
            return null;
        }

        $messages = [
            'profile-updated' => ['type' => 'success', 'text' => __('Profile updated successfully.', 'habit-tracker')],
            'password-updated' => ['type' => 'success', 'text' => __('Password updated successfully.', 'habit-tracker')],
            'email-invalid' => ['type' => 'error', 'text' => __('Please enter a valid email address.', 'habit-tracker')],
            'email-exists' => ['type' => 'error', 'text' => __('This email is already used by another account.', 'habit-tracker')],
            'profile-save-failed' => ['type' => 'error', 'text' => __('Could not save profile changes. Please try again.', 'habit-tracker')],
            'password-required' => ['type' => 'error', 'text' => __('All password fields are required.', 'habit-tracker')],
            'password-mismatch' => ['type' => 'error', 'text' => __('New password and confirmation do not match.', 'habit-tracker')],
            'password-short' => ['type' => 'error', 'text' => __('New password must be at least 8 characters.', 'habit-tracker')],
            'password-current-invalid' => ['type' => 'error', 'text' => __('Current password is incorrect.', 'habit-tracker')],
            'password-update-failed' => ['type' => 'error', 'text' => __('Could not update password. Please try again.', 'habit-tracker')],
        ];

        if (! isset($messages[$feedback_key])) {
            return null;
        }

        return $messages[$feedback_key];
    }

    private function renderLoggedOut(): string
    {
        return $this->renderTemplate(
            'profile-logged-out',
            [
                'login_url' => $this->resolveLoginUrl(),
                'kicker' => __('Profile Access', 'habit-tracker'),
                'title' => __('Log In To Open Your Profile', 'habit-tracker'),
                'description' => __('You need an account to manage profile and security settings.', 'habit-tracker'),
                'button_text' => __('Login', 'habit-tracker'),
            ]
        );
    }

    private function resolveLoginUrl(): string
    {
        $login_url = wp_login_url($this->getProfileUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                return $theme_login_url;
            }
        }

        return $login_url;
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

        $profile_page = get_page_by_path('profile');

        if ($profile_page instanceof \WP_Post) {
            $profile_permalink = get_permalink($profile_page);

            if (is_string($profile_permalink) && $profile_permalink !== '') {
                return $profile_permalink;
            }
        }

        $profile_template_pages = get_pages([
            'post_status' => 'publish',
            'number' => 1,
            'meta_key' => '_wp_page_template',
            'meta_value' => 'page-profile.php',
        ]);

        if (
            is_array($profile_template_pages) &&
            isset($profile_template_pages[0]) &&
            $profile_template_pages[0] instanceof \WP_Post
        ) {
            $template_permalink = get_permalink($profile_template_pages[0]);

            if (is_string($template_permalink) && $template_permalink !== '') {
                return $template_permalink;
            }
        }

        return home_url('/profile');
    }

    private function renderTemplate(string $template, array $context = []): string
    {
        return ThemeTemplate::render($template, $context);
    }
}
