<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class AuthShortcode
{
    private const SHORTCODE_LOGIN = 'habit_tracker_login_form';
    private const SHORTCODE_REGISTER = 'habit_tracker_register_form';
    private const REGISTER_ACTION = 'habit_tracker_register_user';
    private const NOTICE_QUERY_KEY = 'hta_notice';
    private const ERROR_QUERY_KEY = 'hta_error';

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_LOGIN, [$this, 'renderLogin']);
        add_shortcode(self::SHORTCODE_REGISTER, [$this, 'renderRegister']);

        add_action('admin_post_' . self::REGISTER_ACTION, [$this, 'handleRegister']);
        add_action('admin_post_nopriv_' . self::REGISTER_ACTION, [$this, 'handleRegister']);
    }

    public function renderLogin(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);

        if (is_user_logged_in()) {
            return $this->renderLoggedInState();
        }

        return $this->renderTemplate(
            'auth-login',
            [
                'notice_html' => $this->renderNotice(),
                'login_form_html' => wp_login_form([
                    'echo'           => false,
                    'redirect'       => $this->getDashboardUrl(),
                    'remember'       => true,
                    'label_username' => __('Email or username', 'habit-tracker'),
                    'label_password' => __('Password', 'habit-tracker'),
                    'label_remember' => __('Remember me', 'habit-tracker'),
                    'label_log_in'   => __('Login', 'habit-tracker'),
                    'id_form'        => 'habit-tracker-login-form',
                    'id_username'    => 'habit-tracker-login-username',
                    'id_password'    => 'habit-tracker-login-password',
                    'id_remember'    => 'habit-tracker-login-remember',
                    'id_submit'      => 'habit-tracker-login-submit',
                ]),
            ]
        );
    }

    public function renderRegister(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);

        if (is_user_logged_in()) {
            return $this->renderLoggedInState();
        }

        if (! $this->isUserRegistrationEnabled()) {
            return $this->renderTemplate(
                'auth-registration-disabled',
                [
                    'notice_html' => $this->renderNotice(),
                    'message' => __('Registration is currently disabled. Use login if you already have an account.', 'habit-tracker'),
                    'login_url' => $this->getLoginUrl(),
                    'button_text' => __('Go to Login', 'habit-tracker'),
                ]
            );
        }

        return $this->renderTemplate(
            'auth-register',
            [
                'notice_html' => $this->renderNotice(),
                'form_action' => admin_url('admin-post.php'),
                'redirect' => $this->getDashboardUrl(),
                'register_action' => self::REGISTER_ACTION,
            ]
        );
    }

    public function handleRegister(): void
    {
        if (is_user_logged_in()) {
            wp_safe_redirect($this->getDashboardUrl());
            exit;
        }

        if (! $this->isUserRegistrationEnabled()) {
            $this->redirectRegisterError('registration-disabled');
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));

        if (! wp_verify_nonce($nonce, self::REGISTER_ACTION)) {
            $this->redirectRegisterError('invalid-request');
        }

        $user_email = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));
        $user_pass = (string) wp_unslash($_POST['user_pass'] ?? '');
        $user_pass_confirm = (string) wp_unslash($_POST['user_pass_confirm'] ?? '');
        $redirect = esc_url_raw(wp_unslash($_POST['redirect_to'] ?? ''));

        if ($user_email === '' || ! is_email($user_email)) {
            $this->redirectRegisterError('email-invalid');
        }

        if ($user_pass === '') {
            $this->redirectRegisterError('password-required');
        }

        if (strlen($user_pass) < 8) {
            $this->redirectRegisterError('password-short');
        }

        if ($user_pass !== $user_pass_confirm) {
            $this->redirectRegisterError('password-mismatch');
        }

        if (email_exists($user_email)) {
            $this->redirectRegisterError('email-exists');
        }

        $user_login = $this->generateUniqueUsernameFromEmail($user_email);

        $new_user_id = wp_insert_user([
            'user_login'   => $user_login,
            'user_email'   => $user_email,
            'user_pass'    => $user_pass,
            'role'         => get_option('default_role', 'subscriber'),
            'display_name' => $user_login,
        ]);

        if (is_wp_error($new_user_id) || (int) $new_user_id <= 0) {
            $this->redirectRegisterError('create-failed');
        }

        $new_user = get_user_by('id', (int) $new_user_id);

        if ($new_user instanceof \WP_User) {
            wp_set_current_user((int) $new_user->ID);
            wp_set_auth_cookie((int) $new_user->ID, true);
            do_action('wp_login', $new_user->user_login, $new_user);
        }

        if (! is_string($redirect) || $redirect === '') {
            $redirect = $this->getDashboardUrl();
        }

        wp_safe_redirect($redirect);
        exit;
    }

    private function renderLoggedInState(): string
    {
        return $this->renderTemplate(
            'auth-logged-in',
            [
                'message' => __('You are already logged in. Continue to your dashboard.', 'habit-tracker'),
                'dashboard_url' => $this->getDashboardUrl(),
                'button_text' => __('Open Dashboard', 'habit-tracker'),
            ]
        );
    }

    private function renderNotice(): string
    {
        $notice = sanitize_key((string) wp_unslash($_GET[self::NOTICE_QUERY_KEY] ?? ''));
        $error = sanitize_key((string) wp_unslash($_GET[self::ERROR_QUERY_KEY] ?? ''));

        if ($notice === 'registered') {
            return sprintf(
                '<p class="form-success">%s</p>',
                esc_html__('Account created successfully. You can log in now.', 'habit-tracker')
            );
        }

        if ($error === '') {
            return '';
        }

        $messages = [
            'invalid-request' => __('Invalid request. Please submit the form again.', 'habit-tracker'),
            'registration-disabled' => __('Registration is currently disabled.', 'habit-tracker'),
            'email-invalid' => __('A valid email is required.', 'habit-tracker'),
            'password-required' => __('Password is required.', 'habit-tracker'),
            'password-short' => __('Password must be at least 8 characters.', 'habit-tracker'),
            'password-mismatch' => __('Passwords do not match.', 'habit-tracker'),
            'email-exists' => __('This email is already in use.', 'habit-tracker'),
            'create-failed' => __('Could not create account. Please try again.', 'habit-tracker'),
        ];

        if (! isset($messages[$error])) {
            return '';
        }

        return sprintf('<p class="form-error">%s</p>', esc_html($messages[$error]));
    }

    private function redirectRegisterError(string $error): void
    {
        $join_url = $this->getJoinUrl();
        $target = add_query_arg(
            [self::ERROR_QUERY_KEY => sanitize_key($error)],
            $join_url
        );

        wp_safe_redirect($target);
        exit;
    }

    private function isUserRegistrationEnabled(): bool
    {
        return (bool) get_option('users_can_register');
    }

    private function generateUniqueUsernameFromEmail(string $user_email): string
    {
        $email_name_part = strstr($user_email, '@', true);
        $base = is_string($email_name_part) ? sanitize_user($email_name_part, false) : '';

        if ($base === '') {
            $base = 'user';
        }

        if (! username_exists($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix <= 9999; $suffix++) {
            $candidate = $base . $suffix;

            if (! username_exists($candidate)) {
                return $candidate;
            }
        }

        return $base . wp_rand(10000, 99999);
    }

    private function getDashboardUrl(): string
    {
        if (function_exists('habitlab_get_dashboard_url')) {
            $dashboard_url = habitlab_get_dashboard_url();

            if (is_string($dashboard_url) && $dashboard_url !== '') {
                return $dashboard_url;
            }
        }

        return home_url('/');
    }

    private function getLoginUrl(): string
    {
        if (function_exists('habitlab_get_page_url_by_slug')) {
            $login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($login_url) && $login_url !== '') {
                return $login_url;
            }
        }

        return wp_login_url($this->getDashboardUrl());
    }

    private function getJoinUrl(): string
    {
        if (function_exists('habitlab_get_page_url_by_slug')) {
            $join_url = habitlab_get_page_url_by_slug('join');

            if (is_string($join_url) && $join_url !== '') {
                return $join_url;
            }
        }

        return wp_registration_url();
    }

    private function renderTemplate(string $template, array $context = []): string
    {
        return ThemeTemplate::render($template, $context);
    }
}
