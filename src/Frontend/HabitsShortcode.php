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
        add_action('admin_post_nopriv_' . self::ADD_SHARED_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::ADD_CUSTOM_ACTION, [$this, 'handleUnauthorized']);
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
            <?php $this->renderCustomSection($context['redirect_url']); ?>
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
        <section class="habit-tracker-block">
            <p class="app-card__eyebrow"><?php esc_html_e('Dashboard Stack', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Your Active Habits', 'habit-tracker'); ?></h3>
            <?php if ($user_dashboard_habits === []) : ?>
                <p><?php esc_html_e('No habits in your dashboard yet. Add one from the shared list or create a custom habit.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <ul class="habit-tracker-habits__stack app-list">
                    <?php foreach ($user_dashboard_habits as $dashboard_habit) : ?>
                        <li>
                            <span><?php echo esc_html((string) $dashboard_habit->name); ?></span>
                            <span class="habit-tracker-pill">
                                <?php
                                echo esc_html(
                                    (string) $dashboard_habit->source_type === 'custom'
                                        ? __('Custom', 'habit-tracker')
                                        : __('Shared', 'habit-tracker')
                                );
                                ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php
    }

    private function renderSharedSection(array $shared_habits, array $active_shared_habit_ids, string $redirect_url): void
    {
        ?>
        <section class="habit-tracker-block">
            <p class="app-card__eyebrow"><?php esc_html_e('Shared Habits', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Pick Habits For Your Dashboard', 'habit-tracker'); ?></h3>
            <?php if ($shared_habits === []) : ?>
                <p><?php esc_html_e('No shared habits are available yet. Ask an admin to add some habits first.', 'habit-tracker'); ?></p>
            <?php else : ?>
                <div class="habit-tracker-shared-grid">
                    <?php foreach ($shared_habits as $shared_habit) : ?>
                        <?php $is_added = isset($active_shared_habit_ids[(int) $shared_habit->id]); ?>
                        <article class="habit-tracker-shared-item">
                            <h4><?php echo esc_html((string) $shared_habit->name); ?></h4>
                            <p class="habit-tracker-shared-category">
                                <?php
                                echo esc_html(
                                    (string) $shared_habit->category !== ''
                                        ? (string) $shared_habit->category
                                        : __('Uncategorized', 'habit-tracker')
                                );
                                ?>
                            </p>

                            <?php if ((string) $shared_habit->description !== '') : ?>
                                <p><?php echo esc_html((string) $shared_habit->description); ?></p>
                            <?php endif; ?>

                            <div class="habit-tracker-shared-actions">
                                <?php if ($is_added) : ?>
                                    <span class="habit-tracker-pill habit-tracker-pill--active">
                                        <?php esc_html_e('Already Added', 'habit-tracker'); ?>
                                    </span>
                                <?php else : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ADD_SHARED_ACTION); ?>">
                                        <input type="hidden" name="habit_id" value="<?php echo esc_attr((string) $shared_habit->id); ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_url); ?>">
                                        <?php wp_nonce_field(self::ADD_SHARED_ACTION); ?>
                                        <button type="submit" class="btn btn-ghost">
                                            <?php esc_html_e('Add to Dashboard', 'habit-tracker'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private function renderCustomSection(string $redirect_url): void
    {
        ?>
        <section class="habit-tracker-block">
            <p class="app-card__eyebrow"><?php esc_html_e('Custom Habit', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Create One Just For You', 'habit-tracker'); ?></h3>
            <p><?php esc_html_e('Custom habits are private to your account and appear directly in your dashboard.', 'habit-tracker'); ?></p>

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
        wp_enqueue_style(
            'habit-tracker-frontend',
            HABIT_TRACKER_URL . 'assets/css/frontend.css',
            [],
            HABIT_TRACKER_VERSION
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
}
