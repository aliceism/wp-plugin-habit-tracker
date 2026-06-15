<?php

namespace HabitTracker\Frontend;

use HabitTracker\Infrastructure\Persistence\WpdbCheckinRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class DashboardCheckinActionHandler
{
    private WpdbCheckinRepository $checkins;

    private DashboardNavigation $navigation;

    private DashboardNoticeService $notices;

    private string $toggle_action;

    private string $notice_query_key;

    public function __construct(
        WpdbCheckinRepository $checkins,
        DashboardNavigation $navigation,
        DashboardNoticeService $notices,
        string $toggle_action,
        string $notice_query_key
    )
    {
        $this->checkins = $checkins;
        $this->navigation = $navigation;
        $this->notices = $notices;
        $this->toggle_action = $toggle_action;
        $this->notice_query_key = $notice_query_key;
    }

    public function handleToggleCheckin(): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer($this->toggle_action);

        $user_habit_id = isset($_POST['user_habit_id']) ? absint(wp_unslash($_POST['user_habit_id'])) : 0;

        if ($user_habit_id <= 0) {
            $this->redirectWithNotice('checkin-invalid');
        }

        $result = $this->checkins->toggleCompletedForToday(get_current_user_id(), $user_habit_id);

        if ($result === 'checked') {
            $this->redirectWithNotice('checkin-checked');
        }

        if ($result === 'unchecked') {
            $this->redirectWithNotice('checkin-unchecked');
        }

        if ($result === 'invalid' || $result === 'not-found') {
            $this->redirectWithNotice('checkin-invalid');
        }

        $this->redirectWithNotice('checkin-failed');
    }

    public function handleToggleCheckinAjax(callable $refresh_payload): void
    {
        if (! is_user_logged_in()) {
            wp_send_json_error(
                [
                    'notice' => $this->notices->getPayload('checkin-invalid'),
                    'login_url' => $this->resolveLoginUrl(),
                ],
                401
            );
        }

        $is_nonce_valid = check_ajax_referer($this->toggle_action, '_wpnonce', false);

        if (! $is_nonce_valid) {
            wp_send_json_error(
                [
                    'notice' => $this->notices->getPayload('checkin-failed'),
                ],
                403
            );
        }

        $user_habit_id = isset($_POST['user_habit_id']) ? absint(wp_unslash($_POST['user_habit_id'])) : 0;

        if ($user_habit_id <= 0) {
            wp_send_json_error(
                [
                    'notice' => $this->notices->getPayload('checkin-invalid'),
                ],
                400
            );
        }

        $result = $this->checkins->toggleCompletedForToday(get_current_user_id(), $user_habit_id);

        if ($result !== 'checked' && $result !== 'unchecked') {
            $notice = ($result === 'invalid' || $result === 'not-found')
                ? 'checkin-invalid'
                : 'checkin-failed';

            wp_send_json_error(
                [
                    'notice' => $this->notices->getPayload($notice),
                ],
                400
            );
        }

        $refresh_data = $refresh_payload($user_habit_id, $result);

        if (! is_array($refresh_data)) {
            $refresh_data = [];
        }

        wp_send_json_success(
            [
                'checked' => $result === 'checked',
                'row' => isset($refresh_data['row']) && is_array($refresh_data['row']) ? $refresh_data['row'] : null,
                'metrics_html' => isset($refresh_data['metrics_html']) ? (string) $refresh_data['metrics_html'] : '',
                'side_html' => isset($refresh_data['side_html']) ? (string) $refresh_data['side_html'] : '',
                'notice' => $this->notices->getPayload(
                    $result === 'checked' ? 'checkin-checked' : 'checkin-unchecked'
                ),
            ]
        );
    }

    public function handleUnauthorized(): void
    {
        $redirect = $this->navigation->resolveRedirectUrl();

        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    private function redirectWithNotice(string $notice): void
    {
        $redirect_url = $this->navigation->resolveRedirectUrl();
        $redirect_url = $this->navigation->appendNotice($redirect_url, $this->notice_query_key, $notice);

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function resolveLoginUrl(): string
    {
        $login_url = wp_login_url($this->navigation->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                $login_url = $theme_login_url;
            }
        }

        return $login_url;
    }
}
