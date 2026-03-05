<?php

namespace HabitTracker\Frontend;

use HabitTracker\Infrastructure\Persistence\WpdbHabitRepository;
use HabitTracker\Infrastructure\Persistence\WpdbUserHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class HabitsShortcode
{
    private const SHORTCODE = 'habit_tracker_habits';
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
        add_shortcode(self::SHORTCODE, [$this, 'render']);

        add_action('admin_post_' . self::ADD_SHARED_ACTION, [$this, 'handleAddSharedHabit']);
        add_action('admin_post_' . self::ADD_CUSTOM_ACTION, [$this, 'handleAddCustomHabit']);
        add_action('admin_post_nopriv_' . self::ADD_SHARED_ACTION, [$this, 'handleUnauthorized']);
        add_action('admin_post_nopriv_' . self::ADD_CUSTOM_ACTION, [$this, 'handleUnauthorized']);
    }

    public function render(array $atts = [], ?string $content = null, string $shortcode_tag = ''): string
    {
        unset($atts, $content, $shortcode_tag);

        if (! is_user_logged_in()) {
            return $this->renderLoggedOut();
        }

        $user_id = get_current_user_id();
        $shared_habits = $this->habits->findActiveForFrontend();
        $user_dashboard_habits = $this->user_habits->findActiveByUser($user_id);
        $active_shared_habit_ids = array_flip($this->user_habits->getActiveSharedHabitIdsByUser($user_id));
        $redirect_url = $this->getCurrentUrl();

        ob_start();
        ?>
        <div class="habit-tracker-habits">
            <?php $this->renderNotice(); ?>

            <section class="habit-tracker-habits__section">
                <h3><?php esc_html_e('Your Dashboard Stack', 'habit-tracker'); ?></h3>
                <?php if ($user_dashboard_habits === []) : ?>
                    <p><?php esc_html_e('No habits in your dashboard yet. Add one from the shared list or create a custom habit.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <ul class="habit-tracker-habits__stack">
                        <?php foreach ($user_dashboard_habits as $dashboard_habit) : ?>
                            <li>
                                <strong><?php echo esc_html((string) $dashboard_habit->name); ?></strong>
                                <span>
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

            <section class="habit-tracker-habits__section">
                <h3><?php esc_html_e('Shared Habits', 'habit-tracker'); ?></h3>
                <?php if ($shared_habits === []) : ?>
                    <p><?php esc_html_e('No shared habits are available yet. Ask an admin to add some habits first.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                        <tr>
                            <th><?php esc_html_e('Habit', 'habit-tracker'); ?></th>
                            <th><?php esc_html_e('Category', 'habit-tracker'); ?></th>
                            <th><?php esc_html_e('Action', 'habit-tracker'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($shared_habits as $shared_habit) : ?>
                            <?php $is_added = isset($active_shared_habit_ids[(int) $shared_habit->id]); ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html((string) $shared_habit->name); ?></strong>
                                    <?php if ((string) $shared_habit->description !== '') : ?>
                                        <p><?php echo esc_html((string) $shared_habit->description); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) $shared_habit->category !== ''
                                            ? (string) $shared_habit->category
                                            : __('Uncategorized', 'habit-tracker')
                                    );
                                    ?>
                                </td>
                                <td>
                                    <?php if ($is_added) : ?>
                                        <button type="button" class="button" disabled>
                                            <?php esc_html_e('Added', 'habit-tracker'); ?>
                                        </button>
                                    <?php else : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="<?php echo esc_attr(self::ADD_SHARED_ACTION); ?>">
                                            <input type="hidden" name="habit_id" value="<?php echo esc_attr((string) $shared_habit->id); ?>">
                                            <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_url); ?>">
                                            <?php wp_nonce_field(self::ADD_SHARED_ACTION); ?>
                                            <button type="submit" class="button button-primary">
                                                <?php esc_html_e('Add to Dashboard', 'habit-tracker'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="habit-tracker-habits__section">
                <h3><?php esc_html_e('Create Custom Habit', 'habit-tracker'); ?></h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ADD_CUSTOM_ACTION); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_url); ?>">
                    <?php wp_nonce_field(self::ADD_CUSTOM_ACTION); ?>

                    <p>
                        <label for="habit-tracker-custom-name"><?php esc_html_e('Name', 'habit-tracker'); ?></label><br>
                        <input id="habit-tracker-custom-name" type="text" name="name" required maxlength="191" class="regular-text">
                    </p>
                    <p>
                        <label for="habit-tracker-custom-category"><?php esc_html_e('Category', 'habit-tracker'); ?></label><br>
                        <input id="habit-tracker-custom-category" type="text" name="category" maxlength="100" class="regular-text">
                    </p>
                    <p>
                        <label for="habit-tracker-custom-description"><?php esc_html_e('Description', 'habit-tracker'); ?></label><br>
                        <textarea id="habit-tracker-custom-description" name="description" rows="3" class="large-text"></textarea>
                    </p>
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Add Custom Habit', 'habit-tracker'); ?>
                        </button>
                    </p>
                </form>
            </section>
        </div>
        <?php

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
            '<p>%s <a href="%s">%s</a></p>',
            esc_html__('Log in to add habits to your dashboard.', 'habit-tracker'),
            esc_url($login_url),
            esc_html__('Login', 'habit-tracker')
        );
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
                'class' => 'notice notice-success',
                'text' => __('Shared habit added to your dashboard.', 'habit-tracker'),
            ],
            'shared-already-added' => [
                'class' => 'notice notice-info',
                'text' => __('This shared habit is already in your dashboard.', 'habit-tracker'),
            ],
            'shared-invalid' => [
                'class' => 'notice notice-error',
                'text' => __('Selected shared habit is not available.', 'habit-tracker'),
            ],
            'shared-add-failed' => [
                'class' => 'notice notice-error',
                'text' => __('Could not add the shared habit.', 'habit-tracker'),
            ],
            'custom-added' => [
                'class' => 'notice notice-success',
                'text' => __('Custom habit added to your dashboard.', 'habit-tracker'),
            ],
            'custom-name-required' => [
                'class' => 'notice notice-error',
                'text' => __('Custom habit name is required.', 'habit-tracker'),
            ],
            'custom-add-failed' => [
                'class' => 'notice notice-error',
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
}
