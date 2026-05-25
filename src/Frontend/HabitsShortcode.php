<?php

namespace HabitTracker\Frontend;

use HabitTracker\Domain\Rules\HabitRules;
use HabitTracker\Infrastructure\Persistence\WpdbHabitRepository;
use HabitTracker\Infrastructure\Persistence\WpdbUserHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class HabitsShortcode
{
    private const SHORTCODE_ALL = 'habit_tracker_habits';
    private const SHORTCODE_NOTICE = 'habit_tracker_habits_notice';
    private const SHORTCODE_STACK = 'habit_tracker_habits_stack';
    private const SHORTCODE_SHARED = 'habit_tracker_habits_shared';
    private const SHORTCODE_CUSTOM = 'habit_tracker_habits_custom';
    private const ADD_SHARED_ACTION = 'habit_tracker_add_shared_habit';
    private const ADD_CUSTOM_ACTION = 'habit_tracker_add_custom_habit';
    private const UPDATE_CUSTOM_ACTION = 'habit_tracker_update_custom_habit';
    private const UPDATE_SHARED_FREQUENCY_ACTION = 'habit_tracker_update_shared_habit_frequency';
    private const REMOVE_USER_HABIT_ACTION = 'habit_tracker_remove_user_habit';
    private const NOTICE_QUERY_KEY = 'ht_notice';
    private const CUSTOM_NAME_MAX_LENGTH = 191;
    private const CUSTOM_DESCRIPTION_MAX_LENGTH = 5000;

    private WpdbHabitRepository $habits;

    private WpdbUserHabitRepository $user_habits;

    public function __construct(WpdbHabitRepository $habits, WpdbUserHabitRepository $user_habits)
    {
        $this->habits = $habits;
        $this->user_habits = $user_habits;
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_ALL, [$this, 'render']);
        add_shortcode(self::SHORTCODE_NOTICE, [$this, 'renderNoticeShortcode']);
        add_shortcode(self::SHORTCODE_STACK, [$this, 'renderStackShortcode']);
        add_shortcode(self::SHORTCODE_SHARED, [$this, 'renderSharedShortcode']);
        add_shortcode(self::SHORTCODE_CUSTOM, [$this, 'renderCustomShortcode']);

        add_action('admin_post_' . self::ADD_SHARED_ACTION, [$this, 'handleAddSharedHabit']);
        add_action('admin_post_' . self::ADD_CUSTOM_ACTION, [$this, 'handleAddCustomHabit']);
        add_action('admin_post_' . self::UPDATE_CUSTOM_ACTION, [$this, 'handleUpdateCustomHabit']);
        add_action('admin_post_' . self::UPDATE_SHARED_FREQUENCY_ACTION, [$this, 'handleUpdateSharedHabitFrequency']);
        add_action('admin_post_' . self::REMOVE_USER_HABIT_ACTION, [$this, 'handleRemoveUserHabit']);
        add_action('admin_post_nopriv_' . self::ADD_SHARED_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::ADD_CUSTOM_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::UPDATE_CUSTOM_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::UPDATE_SHARED_FREQUENCY_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::REMOVE_USER_HABIT_ACTION, [$this, 'handleUnauthorized']);
        add_action('wp_ajax_' . self::ADD_SHARED_ACTION, [$this, 'handleAddSharedHabitAjax']);
        add_action('wp_ajax_' . self::ADD_CUSTOM_ACTION, [$this, 'handleAddCustomHabitAjax']);
        add_action('wp_ajax_' . self::UPDATE_CUSTOM_ACTION, [$this, 'handleUpdateCustomHabitAjax']);
        add_action('wp_ajax_' . self::UPDATE_SHARED_FREQUENCY_ACTION, [$this, 'handleUpdateSharedHabitFrequencyAjax']);
        add_action('wp_ajax_' . self::REMOVE_USER_HABIT_ACTION, [$this, 'handleRemoveUserHabitAjax']);
        add_action('wp_ajax_nopriv_' . self::ADD_SHARED_ACTION, [$this, 'handleUnauthorizedAjax']);
        add_action('wp_ajax_nopriv_' . self::ADD_CUSTOM_ACTION, [$this, 'handleUnauthorizedAjax']);
        add_action('wp_ajax_nopriv_' . self::UPDATE_CUSTOM_ACTION, [$this, 'handleUnauthorizedAjax']);
        add_action('wp_ajax_nopriv_' . self::UPDATE_SHARED_FREQUENCY_ACTION, [$this, 'handleUnauthorizedAjax']);
        add_action('wp_ajax_nopriv_' . self::REMOVE_USER_HABIT_ACTION, [$this, 'handleUnauthorizedAjax']);
    }

    public function render(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->getLoggedInContext();
        $notice_key = $this->getCurrentNoticeKey();
        $notice_html = $notice_key !== '' ? $this->renderNoticeHtmlByCode($notice_key) : '';
        $stack_html = $this->renderStackSectionHtml(
            $context['user_dashboard_habits'],
            $context['redirect_url']
        );
        $shared_html = $this->renderSharedSectionHtml(
            $context['shared_habits'],
            $context['active_shared_habit_ids'],
            $context['redirect_url']
        );
        $custom_html = $this->renderCustomSectionHtml($context['redirect_url']);

        return $this->renderTemplate(
            'habits',
            [
                'notice_key' => $notice_key,
                'notice_html' => $notice_html,
                'stack_html' => $stack_html,
                'shared_html' => $shared_html,
                'custom_html' => $custom_html,
                'context' => $context,
            ]
        );
    }

    public function renderNoticeShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();
        $notice_key = $this->getCurrentNoticeKey();
        $notice_html = $notice_key !== '' ? $this->renderNoticeHtmlByCode($notice_key) : '';

        return $this->renderTemplate(
            'habits-notice',
            [
                'notice_key' => $notice_key,
                'notice_html' => $notice_html,
            ]
        );
    }

    public function renderStackShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->getLoggedInContext();
        return $this->renderStackSectionHtml(
            $context['user_dashboard_habits'],
            $context['redirect_url']
        );
    }

    public function renderSharedShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->getLoggedInContext();
        $shared_html = $this->renderSharedSectionHtml(
            $context['shared_habits'],
            $context['active_shared_habit_ids'],
            $context['redirect_url']
        );
        return $shared_html;
    }

    public function renderCustomShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $context = $this->getLoggedInContext();
        return $this->renderCustomSectionHtml($context['redirect_url']);
    }

    public function handleAddSharedHabit(): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer(self::ADD_SHARED_ACTION);
        $notice = $this->processAddSharedHabitFromRequest();
        $this->redirectWithNotice($notice);
    }

    public function handleAddSharedHabitAjax(): void
    {
        $this->handleMutationAjax(self::ADD_SHARED_ACTION);
    }

    public function handleAddCustomHabitAjax(): void
    {
        $this->handleMutationAjax(self::ADD_CUSTOM_ACTION);
    }

    public function handleRemoveUserHabitAjax(): void
    {
        $this->handleMutationAjax(self::REMOVE_USER_HABIT_ACTION);
    }

    public function handleUpdateCustomHabitAjax(): void
    {
        $this->handleMutationAjax(self::UPDATE_CUSTOM_ACTION);
    }

    public function handleUpdateSharedHabitFrequencyAjax(): void
    {
        $this->handleMutationAjax(self::UPDATE_SHARED_FREQUENCY_ACTION);
    }

    private function handleMutationAjax(string $action): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorizedAjax();
        }

        $is_nonce_valid = check_ajax_referer($action, '_wpnonce', false);

        if (! $is_nonce_valid) {
            $notice = $this->resolveFailedNoticeForAction($action);
            $this->sendMutationAjaxErrorPayload($notice, 403);
        }

        $notice = $this->resolveNoticeForAction($action);
        $success_notices = [
            'shared-added' => true,
            'shared-already-added' => true,
            'custom-added' => true,
            'custom-updated' => true,
            'shared-frequency-updated' => true,
            'stack-removed' => true,
        ];
        $close_modal_notices = [
            'shared-added' => true,
            'shared-already-added' => true,
            'custom-added' => true,
            'custom-updated' => true,
            'shared-frequency-updated' => true,
        ];
        $is_success = isset($success_notices[$notice]);
        $should_close_modals = $is_success && isset($close_modal_notices[$notice]);

        $this->sendMutationAjaxSuccessPayload(
            $notice,
            $is_success,
            $should_close_modals
        );
    }

    private function processAddSharedHabitFromRequest(): string
    {
        $habit_id = isset($_POST['habit_id']) ? absint(wp_unslash($_POST['habit_id'])) : 0;
        $target_per_week = isset($_POST['target_per_week']) ? (int) wp_unslash($_POST['target_per_week']) : 0;

        if ($target_per_week < 1 || $target_per_week > 7) {
            $target_per_week = 0;
        }

        if ($habit_id <= 0) {
            return 'shared-invalid';
        }

        $shared_habit = $this->habits->findById($habit_id);

        if (
            $shared_habit === null ||
            ! isset($shared_habit->is_active) ||
            (int) $shared_habit->is_active !== 1
        ) {
            return 'shared-invalid';
        }

        $result = $this->user_habits->addSharedHabitForUser(
            get_current_user_id(),
            $shared_habit,
            [
                'target_per_week' => $target_per_week,
            ]
        );

        if ($result === 'created') {
            return 'shared-added';
        }

        if ($result === 'exists' || $result === 'reactivated') {
            return 'shared-already-added';
        }

        return 'shared-add-failed';
    }

    public function handleAddCustomHabit(): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer(self::ADD_CUSTOM_ACTION);
        $notice = $this->processAddCustomHabitFromRequest();
        $this->redirectWithNotice($notice);
    }

    public function handleUpdateCustomHabit(): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer(self::UPDATE_CUSTOM_ACTION);
        $notice = $this->processUpdateCustomHabitFromRequest();
        $this->redirectWithNotice($notice);
    }

    public function handleUpdateSharedHabitFrequency(): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer(self::UPDATE_SHARED_FREQUENCY_ACTION);
        $notice = $this->processUpdateSharedHabitFrequencyFromRequest();
        $this->redirectWithNotice($notice);
    }

    private function processAddCustomHabitFromRequest(): string
    {
        $name = $this->truncateTextField(
            sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            self::CUSTOM_NAME_MAX_LENGTH
        );
        $category = $this->normalizeCategoryKey(sanitize_key((string) wp_unslash($_POST['category'] ?? '')));
        $description = $this->truncateTextField(
            sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            self::CUSTOM_DESCRIPTION_MAX_LENGTH
        );
        $target_per_week = isset($_POST['target_per_week']) ? (int) wp_unslash($_POST['target_per_week']) : 7;

        if ($target_per_week < 1 || $target_per_week > 7) {
            $target_per_week = 7;
        }

        if ($name === '') {
            return 'custom-name-required';
        }

        $created_id = $this->user_habits->createCustomHabitForUser(
            get_current_user_id(),
            [
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'target_per_week' => $target_per_week,
            ]
        );

        if ($created_id <= 0) {
            return 'custom-add-failed';
        }

        return 'custom-added';
    }

    private function processUpdateCustomHabitFromRequest(): string
    {
        $user_habit_id = isset($_POST['user_habit_id']) ? absint(wp_unslash($_POST['user_habit_id'])) : 0;
        $name = $this->truncateTextField(
            sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            self::CUSTOM_NAME_MAX_LENGTH
        );
        $category = $this->normalizeCategoryKey(sanitize_key((string) wp_unslash($_POST['category'] ?? '')));
        $description = $this->truncateTextField(
            sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            self::CUSTOM_DESCRIPTION_MAX_LENGTH
        );
        $target_per_week = isset($_POST['target_per_week']) ? (int) wp_unslash($_POST['target_per_week']) : 7;

        if ($target_per_week < 1 || $target_per_week > 7) {
            $target_per_week = 7;
        }

        if ($user_habit_id <= 0) {
            return 'custom-update-invalid';
        }

        if ($name === '') {
            return 'custom-update-name-required';
        }

        $result = $this->user_habits->updateActiveCustomHabitByIdForUser(
            get_current_user_id(),
            $user_habit_id,
            [
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'target_per_week' => $target_per_week,
            ]
        );

        if ($result === 'updated') {
            return 'custom-updated';
        }

        if ($result === 'name-required') {
            return 'custom-update-name-required';
        }

        if ($result === 'not-found') {
            return 'custom-update-invalid';
        }

        return 'custom-update-failed';
    }

    private function processUpdateSharedHabitFrequencyFromRequest(): string
    {
        $user_habit_id = isset($_POST['user_habit_id']) ? absint(wp_unslash($_POST['user_habit_id'])) : 0;
        $target_per_week = isset($_POST['target_per_week']) ? (int) wp_unslash($_POST['target_per_week']) : 7;

        if ($target_per_week < 1 || $target_per_week > 7) {
            return 'shared-frequency-invalid';
        }

        if ($user_habit_id <= 0) {
            return 'shared-frequency-invalid';
        }

        $result = $this->user_habits->updateActiveSharedHabitTargetPerWeekByIdForUser(
            get_current_user_id(),
            $user_habit_id,
            $target_per_week
        );

        if ($result === 'updated') {
            return 'shared-frequency-updated';
        }

        if ($result === 'not-found') {
            return 'shared-frequency-invalid';
        }

        return 'shared-frequency-failed';
    }

    public function handleRemoveUserHabit(): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer(self::REMOVE_USER_HABIT_ACTION);
        $notice = $this->processRemoveUserHabitFromRequest();
        $this->redirectWithNotice($notice);
    }

    private function processRemoveUserHabitFromRequest(): string
    {
        $user_habit_id = isset($_POST['user_habit_id']) ? absint(wp_unslash($_POST['user_habit_id'])) : 0;

        if ($user_habit_id <= 0) {
            return 'stack-remove-invalid';
        }

        $result = $this->user_habits->archiveActiveByIdForUser(get_current_user_id(), $user_habit_id);

        if ($result === 'archived') {
            return 'stack-removed';
        }

        if ($result === 'not-found') {
            return 'stack-remove-invalid';
        }

        return 'stack-remove-failed';
    }

    public function handleUnauthorized(): void
    {
        $redirect = $this->resolveRedirectUrl();

        wp_safe_redirect(wp_login_url($redirect));
        exit;
    }

    public function handleUnauthorizedAjax(): void
    {
        wp_send_json_error(
            [
                'notice' => $this->getNoticePayload('shared-invalid'),
                'login_url' => $this->resolveLoginUrl(),
            ],
            401
        );
    }

    private function renderLoggedOut(): string
    {
        return $this->renderTemplate(
            'habits-logged-out',
            [
                'login_url' => $this->resolveLoginUrl(),
                'kicker' => __('Habits Access', 'habit-tracker'),
                'title' => __('Log In To Build Your Stack', 'habit-tracker'),
                'description' => __('You need an account to add shared habits and create custom habits for your dashboard.', 'habit-tracker'),
                'button_text' => __('Login', 'habit-tracker'),
            ]
        );
    }

    private function getCurrentNoticeKey(): string
    {
        return isset($_GET[self::NOTICE_QUERY_KEY])
            ? sanitize_key(wp_unslash($_GET[self::NOTICE_QUERY_KEY]))
            : '';
    }

    private function getNoticeMessages(): array
    {
        return [
            'shared-added' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--success',
                'text' => __('Shared habit added to your dashboard.', 'habit-tracker'),
            ],
            'shared-already-added' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--info',
                'text' => __('This shared habit is already in your dashboard.', 'habit-tracker'),
            ],
            'shared-invalid' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Selected shared habit is not available.', 'habit-tracker'),
            ],
            'shared-add-failed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Could not add the shared habit.', 'habit-tracker'),
            ],
            'shared-frequency-updated' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--success',
                'text' => __('Weekly goal updated for this shared habit.', 'habit-tracker'),
            ],
            'shared-frequency-invalid' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--info',
                'text' => __('This shared habit is no longer available in your dashboard stack.', 'habit-tracker'),
            ],
            'shared-frequency-failed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Could not update the weekly goal for this habit.', 'habit-tracker'),
            ],
            'custom-added' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--success',
                'text' => __('Custom habit added to your dashboard.', 'habit-tracker'),
            ],
            'custom-updated' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--success',
                'text' => __('Custom habit updated.', 'habit-tracker'),
            ],
            'custom-name-required' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Custom habit name is required.', 'habit-tracker'),
            ],
            'custom-update-name-required' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Custom habit name is required.', 'habit-tracker'),
            ],
            'custom-update-invalid' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--info',
                'text' => __('This custom habit is no longer available in your dashboard stack.', 'habit-tracker'),
            ],
            'custom-add-failed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Could not create custom habit.', 'habit-tracker'),
            ],
            'custom-update-failed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Could not update custom habit.', 'habit-tracker'),
            ],
            'stack-removed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--success',
                'text' => __('Habit removed from your dashboard stack.', 'habit-tracker'),
            ],
            'stack-remove-invalid' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--info',
                'text' => __('This habit is no longer active in your dashboard stack.', 'habit-tracker'),
            ],
            'stack-remove-failed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Could not remove the habit from your dashboard stack.', 'habit-tracker'),
            ],
        ];
    }

    private function getNoticePayload(string $notice): array
    {
        $messages = $this->getNoticeMessages();
        $fallback = $messages['shared-add-failed'];
        $resolved = $messages[$notice] ?? $fallback;

        return [
            'key' => $notice,
            'class' => (string) ($resolved['class'] ?? $fallback['class']),
            'text' => (string) ($resolved['text'] ?? $fallback['text']),
        ];
    }

    private function renderNoticeHtmlByCode(string $notice): string
    {
        $payload = $this->getNoticePayload($notice);

        return sprintf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr((string) ($payload['class'] ?? 'habit-tracker-notice habit-tracker-notice--info')),
            esc_html((string) ($payload['text'] ?? ''))
        );
    }

    private function renderStackSectionHtml(array $user_dashboard_habits, ?string $redirect_url = null): string
    {
        $context = $this->buildStackTemplateContext($user_dashboard_habits, $redirect_url);
        return $this->renderTemplate('habits-stack', $context);
    }

    private function renderSharedSectionHtml(array $shared_habits, array $active_shared_habit_ids, string $redirect_url): string
    {
        $context = $this->buildSharedTemplateContext($shared_habits, $active_shared_habit_ids, $redirect_url);
        return $this->renderTemplate('habits-shared', $context);
    }

    private function renderCustomSectionHtml(string $redirect_url): string
    {
        $context = $this->buildCustomTemplateContext($redirect_url);
        return $this->renderTemplate('habits-custom', $context);
    }

    private function resolveNoticeForAction(string $action): string
    {
        if ($action === self::ADD_SHARED_ACTION) {
            return $this->processAddSharedHabitFromRequest();
        }

        if ($action === self::ADD_CUSTOM_ACTION) {
            return $this->processAddCustomHabitFromRequest();
        }

        if ($action === self::UPDATE_SHARED_FREQUENCY_ACTION) {
            return $this->processUpdateSharedHabitFrequencyFromRequest();
        }

        if ($action === self::UPDATE_CUSTOM_ACTION) {
            return $this->processUpdateCustomHabitFromRequest();
        }

        if ($action === self::REMOVE_USER_HABIT_ACTION) {
            return $this->processRemoveUserHabitFromRequest();
        }

        return $this->resolveFailedNoticeForAction($action);
    }

    private function resolveFailedNoticeForAction(string $action): string
    {
        if ($action === self::ADD_SHARED_ACTION) {
            return 'shared-add-failed';
        }

        if ($action === self::ADD_CUSTOM_ACTION) {
            return 'custom-add-failed';
        }

        if ($action === self::UPDATE_SHARED_FREQUENCY_ACTION) {
            return 'shared-frequency-failed';
        }

        if ($action === self::UPDATE_CUSTOM_ACTION) {
            return 'custom-update-failed';
        }

        if ($action === self::REMOVE_USER_HABIT_ACTION) {
            return 'stack-remove-failed';
        }

        return 'shared-add-failed';
    }

    private function sendMutationAjaxSuccessPayload(string $notice, bool $ok, bool $close_modals = false): void
    {
        $this->sendMutationAjaxPayload($notice, $ok, true, 200, $close_modals);
    }

    private function sendMutationAjaxErrorPayload(string $notice, int $status): void
    {
        $this->sendMutationAjaxPayload($notice, false, false, $status, false);
    }

    private function sendMutationAjaxPayload(
        string $notice,
        bool $ok,
        bool $success,
        int $status,
        bool $close_modals = false
    ): void
    {
        $context = $this->getLoggedInContext();
        $notice_html = $this->renderNoticeHtmlByCode($notice);
        $stack_html = $this->renderStackSectionHtml(
            $context['user_dashboard_habits'],
            $context['redirect_url']
        );
        $shared_html = $this->renderSharedSectionHtml(
            $context['shared_habits'],
            $context['active_shared_habit_ids'],
            $context['redirect_url']
        );

        $payload = [
            'ok' => $ok,
            'notice' => $this->getNoticePayload($notice),
            'notice_html' => $notice_html,
            'stack_html' => $stack_html,
            'shared_html' => $shared_html,
            'close_modals' => $close_modals,
        ];

        if ($success) {
            wp_send_json_success($payload, $status);
        }

        wp_send_json_error($payload, $status);
    }

    private function enqueueAssets(): void
    {
        FrontendAssets::enqueue(
            'habitTrackerHabits',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'addSharedAction' => self::ADD_SHARED_ACTION,
                'addCustomAction' => self::ADD_CUSTOM_ACTION,
                'updateAction' => self::UPDATE_CUSTOM_ACTION,
                'updateSharedFrequencyAction' => self::UPDATE_SHARED_FREQUENCY_ACTION,
                'removeAction' => self::REMOVE_USER_HABIT_ACTION,
                'stackControls' => [
                    'searchPlaceholder' => __('Search habits...', 'habit-tracker'),
                    'filterLabel' => __('Filter', 'habit-tracker'),
                    'filterAll' => __('All categories', 'habit-tracker'),
                    'sortLabel' => __('Sort', 'habit-tracker'),
                    'sortDefault' => __('Default order', 'habit-tracker'),
                    'sortNameAsc' => __('Name (A-Z)', 'habit-tracker'),
                    'sortNameDesc' => __('Name (Z-A)', 'habit-tracker'),
                    'sortCategory' => __('Category', 'habit-tracker'),
                    'noResults' => __('No habits match your filters.', 'habit-tracker'),
                    'categories' => array_merge(
                        $this->categoryOptions(),
                        [
                            HabitRules::CATEGORY_CUSTOM => __('Custom', 'habit-tracker'),
                        ]
                    ),
                ],
            ]
        );
    }

    private function redirectWithNotice(string $notice): void
    {
        $redirect_url = $this->resolveRedirectUrl();
        $redirect_url = remove_query_arg(self::NOTICE_QUERY_KEY, $redirect_url);
        $redirect_url = add_query_arg(self::NOTICE_QUERY_KEY, $notice, $redirect_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function resolveRedirectUrl(): string
    {
        $raw_redirect = wp_unslash($_POST['redirect_to'] ?? '');

        if (! is_string($raw_redirect) || $raw_redirect === '' || $this->isAdminPostUrl($raw_redirect)) {
            $raw_redirect = wp_unslash($_POST['_wp_http_referer'] ?? '');
        }

        if (! is_string($raw_redirect) || $raw_redirect === '' || $this->isAdminPostUrl($raw_redirect)) {
            $raw_redirect = wp_get_referer();
        }

        if (! is_string($raw_redirect) || $raw_redirect === '' || $this->isAdminPostUrl($raw_redirect)) {
            $raw_redirect = $this->getCurrentUrl();
        }

        return wp_validate_redirect($raw_redirect, $this->getHabitsFallbackUrl());
    }

    private function getCurrentUrl(): string
    {
        return CurrentUrlResolver::resolve($this->getHabitsFallbackUrl());
    }

    private function isAdminPostUrl(string $url): bool
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);

        if ($path === '') {
            return false;
        }

        return str_ends_with($path, '/wp-admin/admin-post.php');
    }

    private function getHabitsFallbackUrl(): string
    {
        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_habits_url = habitlab_get_page_url_by_slug('habits');

            if (is_string($theme_habits_url) && $theme_habits_url !== '') {
                return $theme_habits_url;
            }
        }

        return home_url('/');
    }

    private function getLoggedInContext(): array
    {
        $user_id = get_current_user_id();

        return [
            'shared_habits' => $this->habits->findActiveForFrontend(),
            'user_dashboard_habits' => $this->user_habits->findActiveByUser($user_id),
            'active_shared_habit_ids' => array_flip($this->user_habits->getActiveSharedHabitIdsByUser($user_id)),
            'redirect_url' => $this->getCurrentUrl(),
        ];
    }

    private function resolveStackCategoryClass(object $dashboard_habit): string
    {
        $category_key = sanitize_key((string) ($dashboard_habit->category ?? ''));

        if (HabitRules::isCoreCategoryKey($category_key)) {
            return $category_key;
        }

        if ((string) ($dashboard_habit->source_type ?? '') === 'custom') {
            return HabitRules::CATEGORY_CUSTOM;
        }

        return HabitRules::CATEGORY_LIFE;
    }

    private function categoryOptions(): array
    {
        return HabitRules::categoryLabels(false);
    }

    private function normalizeCategoryKey(string $category): string
    {
        return HabitRules::normalizeCategoryKey($category);
    }

    private function groupHabitsByPresetCategory(array $shared_habits): array
    {
        $grouped = array_fill_keys(HabitRules::categoryKeys(false), []);

        foreach ($shared_habits as $shared_habit) {
            $category_key = sanitize_key((string) $shared_habit->category);

            if (! isset($grouped[$category_key])) {
                $category_key = HabitRules::CATEGORY_LIFE;
            }

            $grouped[$category_key][] = $shared_habit;
        }

        foreach ($grouped as &$category_habits) {
            usort($category_habits, [$this, 'compareHabitsByName']);
        }
        unset($category_habits);

        return $grouped;
    }

    private function compareHabitsByName($left, $right): int
    {
        $left_name = trim((string) (is_object($left) ? ($left->name ?? '') : ''));
        $right_name = trim((string) (is_object($right) ? ($right->name ?? '') : ''));

        return strnatcasecmp($left_name, $right_name);
    }

    private function resolveDefaultTargetPerWeek(object $shared_habit): int
    {
        $frequency_type = HabitRules::normalizeFrequencyType(
            (string) ($shared_habit->default_frequency_type ?? HabitRules::FREQUENCY_DAILY)
        );
        $target_count = HabitRules::normalizeTargetCount(
            (int) ($shared_habit->default_target_count ?? HabitRules::DEFAULT_TARGET_COUNT)
        );

        return HabitRules::resolveDefaultTargetPerWeek($frequency_type, $target_count);
    }

    private function formatTargetPerWeekLabel(int $target_option): string
    {
        if ($target_option >= 7) {
            return __('Everyday', 'habit-tracker');
        }

        return sprintf(
            _n('%d day per week', '%d days per week', $target_option, 'habit-tracker'),
            $target_option
        );
    }

    private function targetPerWeekOptions(): array
    {
        return HabitRules::targetPerWeekOptions();
    }

    private function buildStackTemplateContext(array $user_dashboard_habits, ?string $redirect_url = null): array
    {
        $resolved_redirect = is_string($redirect_url) && $redirect_url !== ''
            ? $redirect_url
            : $this->getCurrentUrl();
        $items = [];

        foreach ($user_dashboard_habits as $dashboard_habit) {
            $stack_item_id = isset($dashboard_habit->id) ? (int) $dashboard_habit->id : 0;
            $category_key = $this->resolveStackCategoryClass($dashboard_habit);
            $is_custom = (string) ($dashboard_habit->source_type ?? '') === 'custom';
            $form_category_key = sanitize_key((string) ($dashboard_habit->category ?? ''));

            if (! HabitRules::isCoreCategoryKey($form_category_key)) {
                $form_category_key = HabitRules::CATEGORY_LIFE;
            }
            $frequency_type = HabitRules::normalizeFrequencyType(
                (string) ($dashboard_habit->frequency_type ?? HabitRules::FREQUENCY_DAILY)
            );
            $target_count = HabitRules::normalizeTargetCount(
                (int) ($dashboard_habit->target_count ?? HabitRules::DEFAULT_TARGET_COUNT)
            );
            $target_per_week = HabitRules::resolveDefaultTargetPerWeek($frequency_type, $target_count);

            $items[] = [
                'id' => $stack_item_id,
                'name' => (string) ($dashboard_habit->name ?? ''),
                'description' => trim((string) ($dashboard_habit->description ?? '')),
                'target_per_week' => $target_per_week,
                'target_label' => $this->formatTargetPerWeekLabel($target_per_week),
                'category_class' => $category_key,
                'category_key' => $form_category_key,
                'is_custom' => $is_custom,
            ];
        }

        $target_options = [];

        foreach ($this->targetPerWeekOptions() as $target_option) {
            $target_options[] = [
                'value' => (int) $target_option,
                'label' => $this->formatTargetPerWeekLabel((int) $target_option),
            ];
        }

        return [
            'items' => $items,
            'categories' => $this->categoryOptions(),
            'target_options' => $target_options,
            'redirect_url' => $resolved_redirect,
            'update_action' => self::UPDATE_CUSTOM_ACTION,
            'update_shared_frequency_action' => self::UPDATE_SHARED_FREQUENCY_ACTION,
            'remove_action' => self::REMOVE_USER_HABIT_ACTION,
            'admin_post_url' => admin_url('admin-post.php'),
        ];
    }

    private function buildSharedTemplateContext(
        array $shared_habits,
        array $active_shared_habit_ids,
        string $redirect_url
    ): array {
        $categories = $this->categoryOptions();
        $grouped_habits = $this->groupHabitsByPresetCategory($shared_habits);
        $grouped_items = [];
        $category_counts = [];

        foreach ($categories as $category_key => $category_label) {
            unset($category_label);
            $grouped_items[$category_key] = [];
            $category_habits = $grouped_habits[$category_key] ?? [];

            foreach ($category_habits as $shared_habit) {
                $shared_habit_id = isset($shared_habit->id) ? (int) $shared_habit->id : 0;

                if ($shared_habit_id <= 0) {
                    continue;
                }

                $grouped_items[$category_key][] = [
                    'id' => $shared_habit_id,
                    'name' => (string) ($shared_habit->name ?? ''),
                    'description' => (string) ($shared_habit->description ?? ''),
                    'is_added' => isset($active_shared_habit_ids[$shared_habit_id]),
                    'default_target_per_week' => $this->resolveDefaultTargetPerWeek($shared_habit),
                ];
            }

            $category_counts[$category_key] = count($grouped_items[$category_key]);
        }

        $target_options = [];

        foreach ($this->targetPerWeekOptions() as $target_option) {
            $target_options[] = [
                'value' => (int) $target_option,
                'label' => $this->formatTargetPerWeekLabel((int) $target_option),
            ];
        }

        return [
            'categories' => $categories,
            'grouped_items' => $grouped_items,
            'category_counts' => $category_counts,
            'target_options' => $target_options,
            'redirect_url' => $redirect_url,
            'admin_post_url' => admin_url('admin-post.php'),
            'add_shared_action' => self::ADD_SHARED_ACTION,
            'add_custom_action' => self::ADD_CUSTOM_ACTION,
            'custom_default_category' => HabitRules::CATEGORY_MIND,
            'custom_default_target_per_week' => 7,
        ];
    }

    private function buildCustomTemplateContext(string $redirect_url): array
    {
        $target_options = [];

        foreach ($this->targetPerWeekOptions() as $target_option) {
            $target_options[] = [
                'value' => (int) $target_option,
                'label' => $this->formatTargetPerWeekLabel((int) $target_option),
            ];
        }

        return [
            'categories' => $this->categoryOptions(),
            'target_options' => $target_options,
            'redirect_url' => $redirect_url,
            'admin_post_url' => admin_url('admin-post.php'),
            'add_custom_action' => self::ADD_CUSTOM_ACTION,
            'custom_default_category' => HabitRules::CATEGORY_MIND,
            'custom_default_target_per_week' => 7,
        ];
    }

    private function resolveLoginUrl(): string
    {
        $login_url = wp_login_url($this->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                return $theme_login_url;
            }
        }

        return $login_url;
    }

    private function renderTemplate(string $template, array $context = []): string
    {
        return ThemeTemplate::render($template, $context);
    }

    private function truncateTextField(string $value, int $max_length): string
    {
        if ($value === '' || $max_length <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $max_length, 'UTF-8');
        }

        return substr($value, 0, $max_length);
    }
}
