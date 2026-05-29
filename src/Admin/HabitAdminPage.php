<?php

namespace HabitTracker\Admin;

use HabitTracker\Domain\Rules\HabitRules;
use HabitTracker\Infrastructure\Persistence\WpdbHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class HabitAdminPage
{
    private const PAGE_SLUG = 'habit-tracker';
    private const SAVE_ACTION = 'habit_tracker_save_habit';
    private const TOGGLE_ACTION = 'habit_tracker_toggle_habit';
    private const DELETE_ACTION = 'habit_tracker_delete_habit';
    private const IMPORT_NAME_MAX_LENGTH = 191;
    private const IMPORT_DESCRIPTION_MAX_LENGTH = 5000;

    private WpdbHabitRepository $habits;

    public function __construct(WpdbHabitRepository $habits)
    {
        $this->habits = $habits;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'handleSave']);
        add_action('admin_post_' . self::TOGGLE_ACTION, [$this, 'handleToggle']);
        add_action('admin_post_' . self::DELETE_ACTION, [$this, 'handleDelete']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Habit Tracker', 'habit-tracker'),
            __('Habit Tracker', 'habit-tracker'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'dashicons-yes-alt',
            26
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage habits.', 'habit-tracker'));
        }

        $editing_habit = $this->getEditingHabit();
        $list_filters = $this->getListFiltersFromRequest();
        $total_habit_count = $this->habits->countAllForAdmin();
        $filtered_habit_rows = $this->habits->findForAdminList($list_filters);
        $list_filter_query_args = $this->buildListFilterQueryArgs($list_filters);
        $assignment_counts = $this->habits->getAssignmentCounts();
        $form_values = $this->getFormValues($editing_habit);
        $is_editing = $editing_habit !== null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Habit Tracker', 'habit-tracker'); ?></h1>
            <?php $this->renderNotice(); ?>

            <div class="card" style="max-width: 880px; padding: 20px; margin-top: 20px;">
                <h2><?php echo esc_html($is_editing ? __('Edit Shared Habit', 'habit-tracker') : __('Add Shared Habit', 'habit-tracker')); ?></h2>
                <p>
                    <?php esc_html_e('Shared habits are managed by admins and can later be added by users to their own dashboards.', 'habit-tracker'); ?>
                </p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                    <?php if ($is_editing) : ?>
                        <input type="hidden" name="habit_id" value="<?php echo esc_attr((string) $editing_habit->id); ?>">
                    <?php endif; ?>
                    <?php wp_nonce_field(self::SAVE_ACTION); ?>

                    <table class="form-table" role="presentation">
                        <tbody>
                        <tr>
                            <th scope="row">
                                <label for="habit-name"><?php esc_html_e('Name', 'habit-tracker'); ?></label>
                            </th>
                            <td>
                                <input
                                    id="habit-name"
                                    name="name"
                                    type="text"
                                    class="regular-text"
                                    maxlength="191"
                                    required
                                    value="<?php echo esc_attr($form_values['name']); ?>"
                                >
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="habit-category"><?php esc_html_e('Category', 'habit-tracker'); ?></label>
                            </th>
                            <td>
                                <select
                                    id="habit-category"
                                    name="category"
                                    class="regular-text"
                                    required
                                >
                                    <?php foreach ($this->categoryOptions() as $category_key => $category_label) : ?>
                                        <option value="<?php echo esc_attr($category_key); ?>" <?php selected($form_values['category'], $category_key); ?>>
                                            <?php echo esc_html($category_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="habit-description"><?php esc_html_e('Description', 'habit-tracker'); ?></label>
                            </th>
                            <td>
                                <textarea
                                    id="habit-description"
                                    name="description"
                                    class="large-text"
                                    rows="4"
                                ><?php echo esc_textarea($form_values['description']); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="habit-sort-order"><?php esc_html_e('Sort Order', 'habit-tracker'); ?></label>
                            </th>
                            <td>
                                <input
                                    id="habit-sort-order"
                                    name="sort_order"
                                    type="number"
                                    class="small-text"
                                    min="0"
                                    step="1"
                                    value="<?php echo esc_attr($form_values['sort_order']); ?>"
                                >
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Defaults', 'habit-tracker'); ?></th>
                            <td>
                                <p class="description">
                                    <?php esc_html_e('MVP shared habits are created as daily habits with one completion expected per day.', 'habit-tracker'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Status', 'habit-tracker'); ?></th>
                            <td>
                                <label for="habit-is-active">
                                    <input
                                        id="habit-is-active"
                                        name="is_active"
                                        type="checkbox"
                                        value="1"
                                        <?php checked($form_values['is_active'], '1'); ?>
                                    >
                                    <?php esc_html_e('Active', 'habit-tracker'); ?>
                                </label>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <?php submit_button($is_editing ? __('Update Habit', 'habit-tracker') : __('Add Habit', 'habit-tracker')); ?>

                    <?php if ($is_editing) : ?>
                        <a class="button button-secondary" href="<?php echo esc_url($this->getPageUrl()); ?>">
                            <?php esc_html_e('Cancel Editing', 'habit-tracker'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <h2 style="margin-top: 32px;"><?php esc_html_e('Shared Habits', 'habit-tracker'); ?></h2>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 12px 0 16px; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px;">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">

                <div>
                    <label for="habit-admin-search"><strong><?php esc_html_e('Search', 'habit-tracker'); ?></strong></label><br>
                    <input
                        id="habit-admin-search"
                        type="search"
                        name="search"
                        value="<?php echo esc_attr($list_filters['search']); ?>"
                        placeholder="<?php esc_attr_e('Name, description...', 'habit-tracker'); ?>"
                        class="regular-text"
                    >
                </div>

                <div>
                    <label for="habit-admin-filter-category"><strong><?php esc_html_e('Category', 'habit-tracker'); ?></strong></label><br>
                    <select id="habit-admin-filter-category" name="filter_category">
                        <option value="all" <?php selected($list_filters['category'], 'all'); ?>>
                            <?php esc_html_e('All categories', 'habit-tracker'); ?>
                        </option>
                        <?php foreach ($this->categoryOptions() as $category_key => $category_label) : ?>
                            <option value="<?php echo esc_attr($category_key); ?>" <?php selected($list_filters['category'], $category_key); ?>>
                                <?php echo esc_html($category_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="habit-admin-filter-status"><strong><?php esc_html_e('Status', 'habit-tracker'); ?></strong></label><br>
                    <select id="habit-admin-filter-status" name="filter_status">
                        <option value="all" <?php selected($list_filters['status'], 'all'); ?>>
                            <?php esc_html_e('All statuses', 'habit-tracker'); ?>
                        </option>
                        <option value="active" <?php selected($list_filters['status'], 'active'); ?>>
                            <?php esc_html_e('Active', 'habit-tracker'); ?>
                        </option>
                        <option value="inactive" <?php selected($list_filters['status'], 'inactive'); ?>>
                            <?php esc_html_e('Inactive', 'habit-tracker'); ?>
                        </option>
                    </select>
                </div>

                <div>
                    <label for="habit-admin-sort"><strong><?php esc_html_e('Sort By', 'habit-tracker'); ?></strong></label><br>
                    <select id="habit-admin-sort" name="sort">
                        <?php foreach ($this->sortOptions() as $sort_key => $sort_label) : ?>
                            <option value="<?php echo esc_attr($sort_key); ?>" <?php selected($list_filters['sort'], $sort_key); ?>>
                                <?php echo esc_html($sort_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php submit_button(__('Apply', 'habit-tracker'), 'secondary', '', false); ?>

                <a class="button" href="<?php echo esc_url($this->getPageUrl()); ?>">
                    <?php esc_html_e('Reset', 'habit-tracker'); ?>
                </a>
            </form>

            <p class="description" style="margin-top: 0;">
                <?php
                printf(
                    esc_html__('Showing %1$d of %2$d habits.', 'habit-tracker'),
                    count($filtered_habit_rows),
                    $total_habit_count
                );
                ?>
            </p>

            <?php if ($total_habit_count <= 0) : ?>
                <p><?php esc_html_e('No shared habits have been created yet.', 'habit-tracker'); ?></p>
            <?php elseif ($filtered_habit_rows === []) : ?>
                <p>
                    <?php esc_html_e('No habits match the selected filters.', 'habit-tracker'); ?>
                    <a href="<?php echo esc_url($this->getPageUrl()); ?>">
                        <?php esc_html_e('Clear filters', 'habit-tracker'); ?>
                    </a>
                </p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'habit-tracker'); ?></th>
                        <th><?php esc_html_e('Category', 'habit-tracker'); ?></th>
                        <th><?php esc_html_e('Status', 'habit-tracker'); ?></th>
                        <th><?php esc_html_e('Used By', 'habit-tracker'); ?></th>
                        <th><?php esc_html_e('Sort', 'habit-tracker'); ?></th>
                        <th><?php esc_html_e('Updated', 'habit-tracker'); ?></th>
                        <th><?php esc_html_e('Actions', 'habit-tracker'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filtered_habit_rows as $habit_row) : ?>
                        <?php $assignment_count = $assignment_counts[(int) $habit_row->id] ?? 0; ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($habit_row->name); ?></strong>
                                <?php if ($habit_row->description !== '') : ?>
                                    <p><?php echo esc_html(wp_trim_words((string) $habit_row->description, 18)); ?></p>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($this->resolveCategoryLabel((string) $habit_row->category)); ?></td>
                            <td><?php echo esc_html(((int) $habit_row->is_active === 1) ? __('Active', 'habit-tracker') : __('Inactive', 'habit-tracker')); ?></td>
                            <td><?php echo esc_html((string) $assignment_count); ?></td>
                            <td><?php echo esc_html((string) $habit_row->sort_order); ?></td>
                            <td><?php echo esc_html(mysql2date('Y-m-d H:i', (string) $habit_row->updated_at, false)); ?></td>
                            <td>
                                <a
                                    class="button button-secondary"
                                    href="<?php echo esc_url($this->getPageUrl(array_merge(['action' => 'edit', 'habit_id' => (int) $habit_row->id], $list_filter_query_args))); ?>"
                                >
                                    <?php esc_html_e('Edit', 'habit-tracker'); ?>
                                </a>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin: 0 6px 0 0;">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::TOGGLE_ACTION); ?>">
                                    <input type="hidden" name="habit_id" value="<?php echo esc_attr((string) $habit_row->id); ?>">
                                    <input type="hidden" name="is_active" value="<?php echo esc_attr(((int) $habit_row->is_active === 1) ? '0' : '1'); ?>">
                                    <?php $this->renderListFilterHiddenFields($list_filters); ?>
                                    <?php wp_nonce_field(self::TOGGLE_ACTION); ?>
                                    <button type="submit" class="button">
                                        <?php echo esc_html(((int) $habit_row->is_active === 1) ? __('Deactivate', 'habit-tracker') : __('Activate', 'habit-tracker')); ?>
                                    </button>
                                </form>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin: 0;">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                    <input type="hidden" name="habit_id" value="<?php echo esc_attr((string) $habit_row->id); ?>">
                                    <?php $this->renderListFilterHiddenFields($list_filters); ?>
                                    <?php wp_nonce_field(self::DELETE_ACTION); ?>
                                    <button
                                        type="submit"
                                        class="button button-link-delete"
                                        onclick="return confirm('<?php echo esc_js(__('Delete this shared habit permanently?', 'habit-tracker')); ?>');"
                                    >
                                        <?php esc_html_e('Delete', 'habit-tracker'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handleSave(): void
    {
        $this->assertManageOptions();
        check_admin_referer(self::SAVE_ACTION);

        $habit_id = isset($_POST['habit_id']) ? absint(wp_unslash($_POST['habit_id'])) : 0;
        $existing = $habit_id > 0 ? $this->habits->findById($habit_id) : null;

        if ($habit_id > 0 && $existing === null) {
            $this->redirect('habit-not-found');
        }

        $name = trim($this->truncateImportText(
            sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            self::IMPORT_NAME_MAX_LENGTH
        ));
        $category = $this->normalizeCategoryKey(sanitize_key((string) wp_unslash($_POST['category'] ?? '')));
        $description = $this->truncateImportText(
            sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            self::IMPORT_DESCRIPTION_MAX_LENGTH
        );
        $sort_order = max(0, (int) wp_unslash($_POST['sort_order'] ?? 0));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $target = $habit_id > 0 ? ['action' => 'edit', 'habit_id' => $habit_id] : [];
            $this->redirect('habit-name-required', $target);
        }

        $data = [
            'name'                     => $name,
            'category'                 => $category,
            'description'              => $description,
            'default_frequency_type'   => HabitRules::FREQUENCY_DAILY,
            'default_target_count'     => HabitRules::DEFAULT_TARGET_COUNT,
            'is_active'                => $is_active,
            'sort_order'               => $sort_order,
        ];

        if ($habit_id > 0) {
            $saved = $this->habits->update($habit_id, $data);
            $this->redirect(
                $saved ? 'habit-updated' : 'habit-save-failed',
                ['action' => 'edit', 'habit_id' => $habit_id]
            );
        }

        $created_habit_id = $this->habits->create($data, get_current_user_id());

        if ($created_habit_id <= 0) {
            $this->redirect('habit-save-failed');
        }

        $this->redirect('habit-created');
    }

    public function handleToggle(): void
    {
        $this->assertManageOptions();
        check_admin_referer(self::TOGGLE_ACTION);

        $habit_id = isset($_POST['habit_id']) ? absint(wp_unslash($_POST['habit_id'])) : 0;
        $is_active = isset($_POST['is_active']) && (int) wp_unslash($_POST['is_active']) === 1;

        if ($habit_id <= 0 || $this->habits->findById($habit_id) === null) {
            $this->redirect('habit-not-found');
        }

        $updated = $this->habits->setActive($habit_id, $is_active);

        $this->redirect(
            $updated ? 'habit-status-updated' : 'habit-save-failed',
            $this->getListFilterArgsFromPost()
        );
    }

    public function handleDelete(): void
    {
        $this->assertManageOptions();
        check_admin_referer(self::DELETE_ACTION);

        $habit_id = isset($_POST['habit_id']) ? absint(wp_unslash($_POST['habit_id'])) : 0;

        if ($habit_id <= 0 || $this->habits->findById($habit_id) === null) {
            $this->redirect('habit-not-found');
        }

        if ($this->habits->countUserAssignments($habit_id) > 0) {
            $this->redirect('habit-delete-blocked');
        }

        $deleted = $this->habits->delete($habit_id);

        $this->redirect(
            $deleted ? 'habit-deleted' : 'habit-delete-failed',
            $this->getListFilterArgsFromPost()
        );
    }

    private function assertManageOptions(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage habits.', 'habit-tracker'));
        }
    }

    private function getEditingHabit(): ?object
    {
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $habit_id = isset($_GET['habit_id']) ? absint(wp_unslash($_GET['habit_id'])) : 0;

        if ($action !== 'edit' || $habit_id <= 0) {
            return null;
        }

        return $this->habits->findById($habit_id);
    }

    private function getFormValues(?object $habit): array
    {
        if ($habit === null) {
            return [
                'name' => '',
                'category' => HabitRules::CATEGORY_MIND,
                'description' => '',
                'sort_order' => '0',
                'is_active' => '1',
            ];
        }

        return [
            'name' => (string) $habit->name,
            'category' => $this->normalizeCategoryKey((string) $habit->category),
            'description' => (string) $habit->description,
            'sort_order' => (string) $habit->sort_order,
            'is_active' => ((int) $habit->is_active === 1) ? '1' : '0',
        ];
    }

    private function renderNotice(): void
    {
        $notice_code = isset($_GET['notice']) ? sanitize_key(wp_unslash($_GET['notice'])) : '';

        if ($notice_code === '') {
            return;
        }

        $notices = [
            'habit-created' => [
                'class' => 'notice notice-success',
                'text'  => __('Shared habit created.', 'habit-tracker'),
            ],
            'habit-updated' => [
                'class' => 'notice notice-success',
                'text'  => __('Shared habit updated.', 'habit-tracker'),
            ],
            'habit-status-updated' => [
                'class' => 'notice notice-success',
                'text'  => __('Shared habit status updated.', 'habit-tracker'),
            ],
            'habit-deleted' => [
                'class' => 'notice notice-success',
                'text'  => __('Shared habit deleted.', 'habit-tracker'),
            ],
            'habit-name-required' => [
                'class' => 'notice notice-error',
                'text'  => __('Name is required.', 'habit-tracker'),
            ],
            'habit-not-found' => [
                'class' => 'notice notice-error',
                'text'  => __('Habit not found.', 'habit-tracker'),
            ],
            'habit-save-failed' => [
                'class' => 'notice notice-error',
                'text'  => __('The habit could not be saved.', 'habit-tracker'),
            ],
            'habit-delete-failed' => [
                'class' => 'notice notice-error',
                'text'  => __('The habit could not be deleted.', 'habit-tracker'),
            ],
            'habit-delete-blocked' => [
                'class' => 'notice notice-warning',
                'text'  => __('This habit is already assigned to users and cannot be deleted.', 'habit-tracker'),
            ],
        ];

        if (! isset($notices[$notice_code])) {
            return;
        }
        ?>
        <div class="<?php echo esc_attr($notices[$notice_code]['class']); ?>">
            <p><?php echo esc_html($notices[$notice_code]['text']); ?></p>
        </div>
        <?php
    }

    private function getPageUrl(array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => self::PAGE_SLUG], $args),
            admin_url('admin.php')
        );
    }

    private function redirect(string $notice, array $args = []): void
    {
        $url = $this->getPageUrl(array_merge($args, ['notice' => $notice]));

        wp_safe_redirect($url);
        exit;
    }

    private function categoryOptions(): array
    {
        return HabitRules::categoryLabels(false);
    }

    private function normalizeCategoryKey(string $category): string
    {
        return HabitRules::normalizeCategoryKey($category);
    }

    private function resolveCategoryLabel(string $category): string
    {
        $options = $this->categoryOptions();
        $normalized = $this->normalizeCategoryKey($category);

        return (string) ($options[$normalized] ?? $options[HabitRules::CATEGORY_LIFE]);
    }

    private function truncateImportText(string $value, int $max_length): string
    {
        if ($max_length <= 0 || $value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $max_length, 'UTF-8');
        }

        return substr($value, 0, $max_length);
    }
    private function getListFiltersFromRequest(): array
    {
        return $this->normalizeListFilters([
            'search' => isset($_GET['search']) ? (string) wp_unslash($_GET['search']) : '',
            'category' => isset($_GET['filter_category']) ? (string) wp_unslash($_GET['filter_category']) : 'all',
            'status' => isset($_GET['filter_status']) ? (string) wp_unslash($_GET['filter_status']) : 'all',
            'sort' => isset($_GET['sort']) ? (string) wp_unslash($_GET['sort']) : 'default',
        ]);
    }

    private function getListFilterArgsFromPost(): array
    {
        $filters = $this->normalizeListFilters([
            'search' => isset($_POST['search']) ? (string) wp_unslash($_POST['search']) : '',
            'category' => isset($_POST['filter_category']) ? (string) wp_unslash($_POST['filter_category']) : 'all',
            'status' => isset($_POST['filter_status']) ? (string) wp_unslash($_POST['filter_status']) : 'all',
            'sort' => isset($_POST['sort']) ? (string) wp_unslash($_POST['sort']) : 'default',
        ]);

        return $this->buildListFilterQueryArgs($filters);
    }

    private function normalizeListFilters(array $filters): array
    {
        $search = sanitize_text_field((string) ($filters['search'] ?? ''));
        $search = trim($search);

        $category = sanitize_key((string) ($filters['category'] ?? 'all'));

        if ($category !== 'all') {
            $category = $this->normalizeCategoryKey($category);
        }

        $status = sanitize_key((string) ($filters['status'] ?? 'all'));

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        $sort = sanitize_key((string) ($filters['sort'] ?? 'default'));

        if (! isset($this->sortOptions()[$sort])) {
            $sort = 'default';
        }

        return [
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'sort' => $sort,
        ];
    }

    private function buildListFilterQueryArgs(array $filters): array
    {
        $args = [];
        $search = trim((string) ($filters['search'] ?? ''));
        $category = (string) ($filters['category'] ?? 'all');
        $status = (string) ($filters['status'] ?? 'all');
        $sort = (string) ($filters['sort'] ?? 'default');

        if ($search !== '') {
            $args['search'] = $search;
        }

        if ($category !== 'all') {
            $args['filter_category'] = $category;
        }

        if ($status !== 'all') {
            $args['filter_status'] = $status;
        }

        if ($sort !== 'default') {
            $args['sort'] = $sort;
        }

        return $args;
    }

    private function renderListFilterHiddenFields(array $filters): void
    {
        $args = $this->buildListFilterQueryArgs($filters);

        foreach ($args as $key => $value) {
            printf(
                '<input type="hidden" name="%1$s" value="%2$s">',
                esc_attr((string) $key),
                esc_attr((string) $value)
            );
        }
    }

    private function sortOptions(): array
    {
        return [
            'default' => __('Default Order', 'habit-tracker'),
            'name_asc' => __('Name (A-Z)', 'habit-tracker'),
            'name_desc' => __('Name (Z-A)', 'habit-tracker'),
            'updated_desc' => __('Recently Updated', 'habit-tracker'),
            'updated_asc' => __('Oldest Updated', 'habit-tracker'),
        ];
    }
}
