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
    private const REMOVE_USER_HABIT_ACTION = 'habit_tracker_remove_user_habit';
    private const NOTICE_QUERY_KEY = 'ht_notice';

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
        add_action('admin_post_' . self::REMOVE_USER_HABIT_ACTION, [$this, 'handleRemoveUserHabit']);
        add_action('admin_post_nopriv_' . self::ADD_SHARED_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::ADD_CUSTOM_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::REMOVE_USER_HABIT_ACTION, [$this, 'handleUnauthorized']);
        add_action('wp_ajax_' . self::ADD_SHARED_ACTION, [$this, 'handleAddSharedHabitAjax']);
        add_action('wp_ajax_' . self::ADD_CUSTOM_ACTION, [$this, 'handleAddCustomHabitAjax']);
        add_action('wp_ajax_' . self::REMOVE_USER_HABIT_ACTION, [$this, 'handleRemoveUserHabitAjax']);
        add_action('wp_ajax_nopriv_' . self::ADD_SHARED_ACTION, [$this, 'handleUnauthorizedAjax']);
        add_action('wp_ajax_nopriv_' . self::ADD_CUSTOM_ACTION, [$this, 'handleUnauthorizedAjax']);
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

        ob_start();
        ?>
        <div class="habit-tracker-habits">
            <?php $this->renderNotice(); ?>

            <?php $this->renderStackSection($context['user_dashboard_habits']); ?>
            <?php $this->renderSharedSection($context['shared_habits'], $context['active_shared_habit_ids'], $context['redirect_url']); ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function renderNoticeShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        ob_start();
        $this->renderNotice();

        return (string) ob_get_clean();
    }

    public function renderStackShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOutInline();
        }

        $context = $this->getLoggedInContext();

        ob_start();
        $this->renderStackSection($context['user_dashboard_habits']);

        return (string) ob_get_clean();
    }

    public function renderSharedShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOutInline();
        }

        $context = $this->getLoggedInContext();

        ob_start();
        $this->renderSharedSection($context['shared_habits'], $context['active_shared_habit_ids'], $context['redirect_url']);

        return (string) ob_get_clean();
    }

    public function renderCustomShortcode(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);
        $this->enqueueAssets();

        if (! is_user_logged_in()) {
            return $this->renderLoggedOutInline();
        }

        $context = $this->getLoggedInContext();

        ob_start();
        $this->renderCustomSection($context['redirect_url']);

        return (string) ob_get_clean();
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

    private function handleMutationAjax(string $action): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorizedAjax();
        }

        $is_nonce_valid = check_ajax_referer($action, '_wpnonce', false);

        if (! $is_nonce_valid) {
            $notice = $this->resolveFailedNoticeForAction($action);
            $this->sendMutationAjaxSuccessPayload($notice, false);
        }

        $notice = $this->resolveNoticeForAction($action);
        $success_notices = [
            'shared-added' => true,
            'shared-already-added' => true,
            'custom-added' => true,
            'stack-removed' => true,
        ];

        $this->sendMutationAjaxSuccessPayload(
            $notice,
            isset($success_notices[$notice])
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

    private function processAddCustomHabitFromRequest(): string
    {
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $category = $this->normalizeCategoryKey(sanitize_key((string) wp_unslash($_POST['category'] ?? '')));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
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
        $login_url = wp_login_url($this->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                $login_url = $theme_login_url;
            }
        }

        wp_send_json_error(
            [
                'notice' => $this->getNoticePayload('shared-invalid'),
                'login_url' => $login_url,
            ],
            401
        );
    }

    private function renderLoggedOut(): string
    {
        $login_url = wp_login_url($this->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                $login_url = $theme_login_url;
            }
        }

        return sprintf(
            '<article class="card app-card habit-tracker-block habit-tracker-logged-out"><p class="app-card__eyebrow">%s</p><h3>%s</h3><p>%s</p><a class="btn btn-primary" href="%s">%s</a></article>',
            esc_html__('Habits Access', 'habit-tracker'),
            esc_html__('Log In To Build Your Stack', 'habit-tracker'),
            esc_html__('You need an account to add shared habits and create custom habits for your dashboard.', 'habit-tracker'),
            esc_url($login_url),
            esc_html__('Login', 'habit-tracker')
        );
    }

    private function renderLoggedOutInline(): string
    {
        $login_url = wp_login_url($this->getCurrentUrl());

        if (function_exists('habitlab_get_page_url_by_slug')) {
            $theme_login_url = habitlab_get_page_url_by_slug('login');

            if (is_string($theme_login_url) && $theme_login_url !== '') {
                $login_url = $theme_login_url;
            }
        }

        return sprintf(
            '<p>%s <a href="%s">%s</a></p>',
            esc_html__('Log in to manage habits for your dashboard.', 'habit-tracker'),
            esc_url($login_url),
            esc_html__('Login', 'habit-tracker')
        );
    }

    private function renderStackSection(array $user_dashboard_habits): void
    {
        ?>
        <article class="card app-card habit-tracker-block habit-tracker-block--stack">
            <div class="habit-tracker-block__header">
                <p class="app-card__eyebrow"><?php esc_html_e('Dashboard Stack', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Your Active Habits', 'habit-tracker'); ?></h3>
            </div>
            <?php if ($user_dashboard_habits === []) : ?>
                <p class="habit-tracker-empty-state"><?php esc_html_e('No habits in your dashboard yet. Add one from the shared list or create a custom habit.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <ul class="habit-tracker-habits__stack">
                    <?php foreach ($user_dashboard_habits as $dashboard_habit) : ?>
                        <?php
                        $stack_category_class = $this->resolveStackCategoryClass($dashboard_habit);
                        $stack_item_id = isset($dashboard_habit->id) ? (int) $dashboard_habit->id : 0;
                        ?>
                        <li class="habit-tracker-stack-item habit-tracker-stack-item--<?php echo esc_attr($stack_category_class); ?>">
                            <span class="habit-tracker-stack-item__name"><?php echo esc_html((string) $dashboard_habit->name); ?></span>
                            <?php if ($stack_item_id > 0) : ?>
                                <div class="habit-tracker-stack-item__controls">
                                    <form class="habit-tracker-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="<?php echo esc_attr(self::REMOVE_USER_HABIT_ACTION); ?>">
                                        <input type="hidden" name="user_habit_id" value="<?php echo esc_attr((string) $stack_item_id); ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($this->getCurrentUrl()); ?>">
                                        <?php wp_nonce_field(self::REMOVE_USER_HABIT_ACTION); ?>
                                        <button
                                            type="submit"
                                            class="habit-tracker-stack-item__remove"
                                            aria-label="<?php esc_attr_e('Remove from dashboard stack', 'habit-tracker'); ?>"
                                            title="<?php esc_attr_e('Remove from dashboard stack', 'habit-tracker'); ?>"
                                        >
                                            <span class="habit-tracker-stack-item__remove-glyph" aria-hidden="true"></span>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderSharedSection(array $shared_habits, array $active_shared_habit_ids, string $redirect_url): void
    {
        $grouped_habits = $this->groupHabitsByPresetCategory($shared_habits);
        $categories = $this->categoryOptions();
        ?>
        <article class="card app-card habit-tracker-block habit-tracker-block--shared">
            <div class="habit-tracker-block__header">
                <p class="app-card__eyebrow"><?php esc_html_e('Habits', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Pick Habits For Your Lab', 'habit-tracker'); ?></h3>
            </div>

            <div class="habit-tracker-category-grid">
                <?php foreach ($categories as $category_key => $category_label) : ?>
                    <?php $modal_id = 'habit-tracker-modal-' . $category_key; ?>
                    <button
                        type="button"
                        class="habit-tracker-category-card habit-tracker-category-card--<?php echo esc_attr($category_key); ?>"
                        data-ht-open-modal="<?php echo esc_attr($modal_id); ?>"
                    >
                        <span class="habit-tracker-category-card__label"><?php echo esc_html($category_label); ?></span>
                        <span class="habit-tracker-category-card__meta">
                            <?php
                            printf(
                                esc_html(_n('%d habit', '%d habits', count($grouped_habits[$category_key]), 'habit-tracker')),
                                count($grouped_habits[$category_key])
                            );
                            ?>
                        </span>
                        <span class="habit-tracker-category-card__cta"><?php esc_html_e('Open list', 'habit-tracker'); ?></span>
                    </button>
                <?php endforeach; ?>

                <?php $custom_modal_id = 'habit-tracker-modal-custom'; ?>
                <button type="button" class="habit-tracker-category-card habit-tracker-category-card--custom" data-ht-open-modal="<?php echo esc_attr($custom_modal_id); ?>">
                    <span class="habit-tracker-category-card__label"><?php esc_html_e('Custom Habit', 'habit-tracker'); ?></span>
                    <span class="habit-tracker-category-card__cta"><?php esc_html_e('Create now', 'habit-tracker'); ?></span>
                </button>
            </div>

            <?php foreach ($categories as $category_key => $category_label) : ?>
                <?php
                $modal_id = 'habit-tracker-modal-' . $category_key;
                $modal_title_id = $modal_id . '-title';
                ?>
                <div class="habit-tracker-modal" data-ht-modal="<?php echo esc_attr($modal_id); ?>" hidden>
                    <div class="habit-tracker-modal__backdrop" data-ht-close-modal></div>
                    <div
                        class="habit-tracker-modal__panel habit-tracker-modal__panel--<?php echo esc_attr($category_key); ?>"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="<?php echo esc_attr($modal_title_id); ?>"
                    >
                        <div class="habit-tracker-modal__header">
                            <p class="app-card__eyebrow"><?php echo esc_html($category_label); ?></p>
                            <h4 id="<?php echo esc_attr($modal_title_id); ?>"><?php echo esc_html(sprintf(__('%s Habits', 'habit-tracker'), $category_label)); ?></h4>
                            <button type="button" class="habit-tracker-modal__close" data-ht-close-modal aria-label="<?php esc_attr_e('Close', 'habit-tracker'); ?>">
                                &times;
                            </button>
                        </div>

                        <?php if ($grouped_habits[$category_key] === []) : ?>
                            <p><?php esc_html_e('No habits in this category yet.', 'habit-tracker'); ?></p>
                        <?php else : ?>
                            <ul class="habit-tracker-modal-list">
                                <?php foreach ($grouped_habits[$category_key] as $shared_habit) : ?>
                                    <?php
                                    $shared_habit_id = (int) $shared_habit->id;
                                    $is_added = isset($active_shared_habit_ids[$shared_habit_id]);
                                    $target_select_id = 'habit-tracker-weekly-target-' . $shared_habit_id;
                                    $default_target_per_week = $this->resolveDefaultTargetPerWeek($shared_habit);
                                    ?>
                                    <li class="habit-tracker-modal-list__item">
                                        <div class="habit-tracker-modal-list__content">
                                            <h4><?php echo esc_html((string) $shared_habit->name); ?></h4>
                                            <?php if ((string) $shared_habit->description !== '') : ?>
                                                <p><?php echo esc_html((string) $shared_habit->description); ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="habit-tracker-modal-list__action">
                                            <?php if ($is_added) : ?>
                                                <span class="habit-tracker-pill habit-tracker-pill--active">
                                                    <?php esc_html_e('Already Added', 'habit-tracker'); ?>
                                                </span>
                                            <?php else : ?>
                                                <form class="habit-tracker-inline-form habit-tracker-inline-form--add" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ADD_SHARED_ACTION); ?>">
                                                    <input type="hidden" name="habit_id" value="<?php echo esc_attr((string) $shared_habit->id); ?>">
                                                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_url); ?>">
                                                    <?php wp_nonce_field(self::ADD_SHARED_ACTION); ?>
                                                    <label class="screen-reader-text" for="<?php echo esc_attr($target_select_id); ?>">
                                                        <?php esc_html_e('Times per week', 'habit-tracker'); ?>
                                                    </label>
                                                    <select
                                                        id="<?php echo esc_attr($target_select_id); ?>"
                                                        name="target_per_week"
                                                        class="habit-tracker-add-frequency"
                                                        aria-label="<?php esc_attr_e('Times per week', 'habit-tracker'); ?>"
                                                    >
                                                        <?php foreach ($this->targetPerWeekOptions() as $target_option) : ?>
                                                            <option value="<?php echo esc_attr((string) $target_option); ?>" <?php selected($target_option, $default_target_per_week); ?>>
                                                                <?php echo esc_html($this->formatTargetPerWeekLabel($target_option)); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button
                                                        type="submit"
                                                        class="btn btn-ghost habit-tracker-add-btn"
                                                        aria-label="<?php esc_attr_e('Add to Dashboard', 'habit-tracker'); ?>"
                                                        title="<?php esc_attr_e('Add to Dashboard', 'habit-tracker'); ?>"
                                                    >
                                                        +
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php $custom_modal_title_id = 'habit-tracker-modal-custom-title'; ?>
            <div class="habit-tracker-modal" data-ht-modal="<?php echo esc_attr($custom_modal_id); ?>" hidden>
                <div class="habit-tracker-modal__backdrop" data-ht-close-modal></div>
                <div
                    class="habit-tracker-modal__panel habit-tracker-modal__panel--custom"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="<?php echo esc_attr($custom_modal_title_id); ?>"
                >
                    <div class="habit-tracker-modal__header">
                        <p class="app-card__eyebrow"><?php esc_html_e('Custom Habit', 'habit-tracker'); ?></p>
                        <h4 id="<?php echo esc_attr($custom_modal_title_id); ?>"><?php esc_html_e('Create One Just For You', 'habit-tracker'); ?></h4>
                        <button type="button" class="habit-tracker-modal__close" data-ht-close-modal aria-label="<?php esc_attr_e('Close', 'habit-tracker'); ?>">
                            &times;
                        </button>
                    </div>

                    <p class="habit-tracker-block__intro"><?php esc_html_e('Custom habits are private to your account and appear directly in your dashboard.', 'habit-tracker'); ?></p>

                    <form class="habit-tracker-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ADD_CUSTOM_ACTION); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_url); ?>">
                        <?php wp_nonce_field(self::ADD_CUSTOM_ACTION); ?>

                        <div class="habit-tracker-field">
                            <label for="habit-tracker-custom-name"><?php esc_html_e('Name', 'habit-tracker'); ?></label>
                            <input id="habit-tracker-custom-name" type="text" name="name" required maxlength="191">
                        </div>

                        <div class="habit-tracker-field">
                            <label for="habit-tracker-custom-category"><?php esc_html_e('Category', 'habit-tracker'); ?></label>
                            <select
                                id="habit-tracker-custom-category"
                                name="category"
                                class="habit-tracker-field-select"
                                required
                            >
                                    <?php foreach ($this->categoryOptions() as $category_key => $category_label) : ?>
                                    <option value="<?php echo esc_attr($category_key); ?>" <?php selected($category_key, HabitRules::CATEGORY_MIND); ?>>
                                        <?php echo esc_html($category_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="habit-tracker-field">
                            <label for="habit-tracker-custom-description"><?php esc_html_e('Description', 'habit-tracker'); ?></label>
                            <textarea id="habit-tracker-custom-description" name="description" rows="3"></textarea>
                        </div>

                        <div class="habit-tracker-field">
                            <label for="habit-tracker-custom-target-per-week-modal"><?php esc_html_e('Weekly Goal', 'habit-tracker'); ?></label>
                            <select
                                id="habit-tracker-custom-target-per-week-modal"
                                name="target_per_week"
                                class="habit-tracker-field-select"
                            >
                                <?php foreach ($this->targetPerWeekOptions() as $target_option) : ?>
                                    <option value="<?php echo esc_attr((string) $target_option); ?>" <?php selected($target_option, 7); ?>>
                                        <?php echo esc_html($this->formatTargetPerWeekLabel($target_option)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?php esc_html_e('Add Custom Habit', 'habit-tracker'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </article>
        <?php
    }

    private function renderCustomSection(string $redirect_url): void
    {
        ?>
        <article class="card app-card habit-tracker-block habit-tracker-block--custom">
            <div class="habit-tracker-block__header">
                <p class="app-card__eyebrow"><?php esc_html_e('Custom Habit', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Create One Just For You', 'habit-tracker'); ?></h3>
            </div>
            <p class="habit-tracker-block__intro"><?php esc_html_e('Custom habits are private to your account and appear directly in your dashboard.', 'habit-tracker'); ?></p>

            <form class="habit-tracker-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ADD_CUSTOM_ACTION); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_url); ?>">
                <?php wp_nonce_field(self::ADD_CUSTOM_ACTION); ?>

                <div class="habit-tracker-field">
                    <label for="habit-tracker-custom-name"><?php esc_html_e('Name', 'habit-tracker'); ?></label>
                    <input id="habit-tracker-custom-name" type="text" name="name" required maxlength="191">
                </div>

                <div class="habit-tracker-field">
                    <label for="habit-tracker-custom-category"><?php esc_html_e('Category', 'habit-tracker'); ?></label>
                    <select
                        id="habit-tracker-custom-category"
                        name="category"
                        class="habit-tracker-field-select"
                        required
                    >
                        <?php foreach ($this->categoryOptions() as $category_key => $category_label) : ?>
                            <option value="<?php echo esc_attr($category_key); ?>" <?php selected($category_key, HabitRules::CATEGORY_MIND); ?>>
                                <?php echo esc_html($category_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="habit-tracker-field">
                    <label for="habit-tracker-custom-description"><?php esc_html_e('Description', 'habit-tracker'); ?></label>
                    <textarea id="habit-tracker-custom-description" name="description" rows="3"></textarea>
                </div>

                <div class="habit-tracker-field">
                    <label for="habit-tracker-custom-target-per-week"><?php esc_html_e('Weekly Goal', 'habit-tracker'); ?></label>
                    <select
                        id="habit-tracker-custom-target-per-week"
                        name="target_per_week"
                        class="habit-tracker-field-select"
                    >
                        <?php foreach ($this->targetPerWeekOptions() as $target_option) : ?>
                            <option value="<?php echo esc_attr((string) $target_option); ?>" <?php selected($target_option, 7); ?>>
                                <?php echo esc_html($this->formatTargetPerWeekLabel($target_option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php esc_html_e('Add Custom Habit', 'habit-tracker'); ?>
                </button>
            </form>
        </article>
        <?php
    }

    private function renderNotice(): void
    {
        $notice = isset($_GET[self::NOTICE_QUERY_KEY])
            ? sanitize_key(wp_unslash($_GET[self::NOTICE_QUERY_KEY]))
            : '';

        if ($notice === '') {
            return;
        }

        $this->renderNoticeByCode($notice);
    }

    private function renderNoticeByCode(string $notice): void
    {
        $messages = $this->getNoticeMessages();

        if (! isset($messages[$notice])) {
            return;
        }

        ?>
        <div class="<?php echo esc_attr($messages[$notice]['class']); ?>">
            <p><?php echo esc_html($messages[$notice]['text']); ?></p>
        </div>
        <?php
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
            'custom-added' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--success',
                'text' => __('Custom habit added to your dashboard.', 'habit-tracker'),
            ],
            'custom-name-required' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Custom habit name is required.', 'habit-tracker'),
            ],
            'custom-add-failed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Could not create custom habit.', 'habit-tracker'),
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
        ob_start();
        $this->renderNoticeByCode($notice);

        return (string) ob_get_clean();
    }

    private function renderStackSectionHtml(array $user_dashboard_habits): string
    {
        ob_start();
        $this->renderStackSection($user_dashboard_habits);

        return (string) ob_get_clean();
    }

    private function renderSharedSectionHtml(array $shared_habits, array $active_shared_habit_ids, string $redirect_url): string
    {
        ob_start();
        $this->renderSharedSection($shared_habits, $active_shared_habit_ids, $redirect_url);

        return (string) ob_get_clean();
    }

    private function resolveNoticeForAction(string $action): string
    {
        if ($action === self::ADD_SHARED_ACTION) {
            return $this->processAddSharedHabitFromRequest();
        }

        if ($action === self::ADD_CUSTOM_ACTION) {
            return $this->processAddCustomHabitFromRequest();
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

        if ($action === self::REMOVE_USER_HABIT_ACTION) {
            return 'stack-remove-failed';
        }

        return 'shared-add-failed';
    }

    private function sendMutationAjaxSuccessPayload(string $notice, bool $ok): void
    {
        $context = $this->getLoggedInContext();
        $payload = [
            'ok' => $ok,
            'notice' => $this->getNoticePayload($notice),
            'notice_html' => $this->renderNoticeHtmlByCode($notice),
            'stack_html' => $this->renderStackSectionHtml($context['user_dashboard_habits']),
            'shared_html' => $this->renderSharedSectionHtml(
                $context['shared_habits'],
                $context['active_shared_habit_ids'],
                $context['redirect_url']
            ),
            'close_modals' => (
                $notice === 'shared-added' ||
                $notice === 'shared-already-added' ||
                $notice === 'custom-added'
            ),
        ];

        wp_send_json_success($payload);
    }

    private function enqueueAssets(): void
    {
        $style_path = HABIT_TRACKER_PATH . 'assets/css/frontend.css';
        $script_path = HABIT_TRACKER_PATH . 'assets/js/frontend.js';

        $style_version = is_readable($style_path)
            ? (string) filemtime($style_path)
            : HABIT_TRACKER_VERSION;

        $script_version = is_readable($script_path)
            ? (string) filemtime($script_path)
            : HABIT_TRACKER_VERSION;

        wp_enqueue_style(
            'habit-tracker-frontend',
            HABIT_TRACKER_URL . 'assets/css/frontend.css',
            [],
            $style_version
        );

        wp_enqueue_script(
            'habit-tracker-frontend',
            HABIT_TRACKER_URL . 'assets/js/frontend.js',
            [],
            $script_version,
            true
        );

        wp_localize_script(
            'habit-tracker-frontend',
            'habitTrackerHabits',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'addSharedAction' => self::ADD_SHARED_ACTION,
                'addCustomAction' => self::ADD_CUSTOM_ACTION,
                'removeAction' => self::REMOVE_USER_HABIT_ACTION,
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
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

        if (! is_string($request_uri) || $request_uri === '') {
            return $this->getHabitsFallbackUrl();
        }

        $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $request_query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);

        if ($request_path === '') {
            return $this->getHabitsFallbackUrl();
        }

        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        $home_path = '/' . trim($home_path, '/');
        $normalized_path = $request_path;

        if ($home_path !== '/' && $home_path !== '') {
            if ($normalized_path === $home_path) {
                $normalized_path = '/';
            } elseif (str_starts_with($normalized_path, $home_path . '/')) {
                $normalized_path = substr($normalized_path, strlen($home_path));
            }
        }

        if ($normalized_path === '' || $normalized_path[0] !== '/') {
            $normalized_path = '/' . ltrim($normalized_path, '/');
        }

        $current_url = home_url($normalized_path);

        if ($request_query !== '') {
            $current_url .= '?' . $request_query;
        }

        return $current_url;
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

        return $grouped;
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
}
