<?php

namespace HabitTracker\Frontend;

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
    private const CATEGORY_MIND = 'mind';
    private const CATEGORY_BODY = 'body';
    private const CATEGORY_PRODUCTIVITY = 'productivity';
    private const CATEGORY_LIFE = 'life';
    private const CATEGORY_CUSTOM = 'custom';

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

        $habit_id = isset($_POST['habit_id']) ? absint(wp_unslash($_POST['habit_id'])) : 0;

        if ($habit_id <= 0) {
            $this->redirectWithNotice('shared-invalid');
        }

        $shared_habit = $this->habits->findById($habit_id);

        if (
            $shared_habit === null ||
            ! isset($shared_habit->is_active) ||
            (int) $shared_habit->is_active !== 1
        ) {
            $this->redirectWithNotice('shared-invalid');
        }

        $result = $this->user_habits->addSharedHabitForUser(get_current_user_id(), $shared_habit);

        if ($result === 'created') {
            $this->redirectWithNotice('shared-added');
        }

        if ($result === 'exists' || $result === 'reactivated') {
            $this->redirectWithNotice('shared-already-added');
        }

        $this->redirectWithNotice('shared-add-failed');
    }

    public function handleAddCustomHabit(): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer(self::ADD_CUSTOM_ACTION);

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));

        if ($name === '') {
            $this->redirectWithNotice('custom-name-required');
        }

        $created_id = $this->user_habits->createCustomHabitForUser(
            get_current_user_id(),
            [
                'name' => $name,
                'category' => $category,
                'description' => $description,
            ]
        );

        if ($created_id <= 0) {
            $this->redirectWithNotice('custom-add-failed');
        }

        $this->redirectWithNotice('custom-added');
    }

    public function handleRemoveUserHabit(): void
    {
        if (! is_user_logged_in()) {
            $this->handleUnauthorized();
        }

        check_admin_referer(self::REMOVE_USER_HABIT_ACTION);

        $user_habit_id = isset($_POST['user_habit_id']) ? absint(wp_unslash($_POST['user_habit_id'])) : 0;

        if ($user_habit_id <= 0) {
            $this->redirectWithNotice('stack-remove-invalid');
        }

        $result = $this->user_habits->archiveActiveByIdForUser(get_current_user_id(), $user_habit_id);

        if ($result === 'archived') {
            $this->redirectWithNotice('stack-removed');
        }

        if ($result === 'not-found') {
            $this->redirectWithNotice('stack-remove-invalid');
        }

        $this->redirectWithNotice('stack-remove-failed');
    }

    public function handleUnauthorized(): void
    {
        $redirect = $this->resolveRedirectUrl();

        wp_safe_redirect(wp_login_url($redirect));
        exit;
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
            '<section class="habit-tracker-block habit-tracker-logged-out"><p class="app-card__eyebrow">%s</p><h3>%s</h3><p>%s</p><a class="btn btn-primary" href="%s">%s</a></section>',
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
        <section class="habit-tracker-block habit-tracker-block--stack">
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
                                        &times;
                                    </button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php
    }

    private function renderSharedSection(array $shared_habits, array $active_shared_habit_ids, string $redirect_url): void
    {
        $grouped_habits = $this->groupHabitsByPresetCategory($shared_habits);
        $categories = [
            self::CATEGORY_MIND => __('Mind', 'habit-tracker'),
            self::CATEGORY_BODY => __('Body', 'habit-tracker'),
            self::CATEGORY_PRODUCTIVITY => __('Productivity', 'habit-tracker'),
            self::CATEGORY_LIFE => __('Life', 'habit-tracker'),
        ];

        ?>
        <section class="habit-tracker-block habit-tracker-block--shared">
            <div class="habit-tracker-block__header">
                <p class="app-card__eyebrow"><?php esc_html_e('Habits', 'habit-tracker'); ?></p>
                <h3><?php esc_html_e('Pick Habits For Your Lab', 'habit-tracker'); ?></h3>
            </div>
            <p class="habit-tracker-block__intro"><?php esc_html_e('Choose a category to open the habits list for that area.', 'habit-tracker'); ?></p>

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
                                    <?php $is_added = isset($active_shared_habit_ids[(int) $shared_habit->id]); ?>
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
                                                <form class="habit-tracker-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ADD_SHARED_ACTION); ?>">
                                                    <input type="hidden" name="habit_id" value="<?php echo esc_attr((string) $shared_habit->id); ?>">
                                                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_url); ?>">
                                                    <?php wp_nonce_field(self::ADD_SHARED_ACTION); ?>
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
                            <input id="habit-tracker-custom-category" type="text" name="category" maxlength="100">
                        </div>

                        <div class="habit-tracker-field">
                            <label for="habit-tracker-custom-description"><?php esc_html_e('Description', 'habit-tracker'); ?></label>
                            <textarea id="habit-tracker-custom-description" name="description" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?php esc_html_e('Add Custom Habit', 'habit-tracker'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </section>
        <?php
    }

    private function renderCustomSection(string $redirect_url): void
    {
        ?>
        <section class="habit-tracker-block habit-tracker-block--custom">
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
                    <input id="habit-tracker-custom-category" type="text" name="category" maxlength="100">
                </div>

                <div class="habit-tracker-field">
                    <label for="habit-tracker-custom-description"><?php esc_html_e('Description', 'habit-tracker'); ?></label>
                    <textarea id="habit-tracker-custom-description" name="description" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?php esc_html_e('Add Custom Habit', 'habit-tracker'); ?>
                </button>
            </form>
        </section>
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

        $messages = [
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

        if (! isset($messages[$notice])) {
            return;
        }
        ?>
        <div class="<?php echo esc_attr($messages[$notice]['class']); ?>">
            <p><?php echo esc_html($messages[$notice]['text']); ?></p>
        </div>
        <?php
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

        if (! is_string($raw_redirect) || $raw_redirect === '') {
            $raw_redirect = wp_get_referer();
        }

        if (! is_string($raw_redirect) || $raw_redirect === '') {
            $raw_redirect = $this->getCurrentUrl();
        }

        return wp_validate_redirect($raw_redirect, home_url('/'));
    }

    private function getCurrentUrl(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

        if (! is_string($request_uri) || $request_uri === '') {
            return home_url('/');
        }

        return home_url($request_uri);
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

        if (
            $category_key === self::CATEGORY_MIND ||
            $category_key === self::CATEGORY_BODY ||
            $category_key === self::CATEGORY_PRODUCTIVITY ||
            $category_key === self::CATEGORY_LIFE
        ) {
            return $category_key;
        }

        if ((string) ($dashboard_habit->source_type ?? '') === 'custom') {
            return self::CATEGORY_CUSTOM;
        }

        return self::CATEGORY_LIFE;
    }

    private function groupHabitsByPresetCategory(array $shared_habits): array
    {
        $grouped = [
            self::CATEGORY_MIND => [],
            self::CATEGORY_BODY => [],
            self::CATEGORY_PRODUCTIVITY => [],
            self::CATEGORY_LIFE => [],
        ];

        foreach ($shared_habits as $shared_habit) {
            $category_key = sanitize_key((string) $shared_habit->category);

            if (! isset($grouped[$category_key])) {
                $category_key = self::CATEGORY_LIFE;
            }

            $grouped[$category_key][] = $shared_habit;
        }

        return $grouped;
    }
}
