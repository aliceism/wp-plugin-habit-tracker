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
    private const IMPORT_ACTION = 'habit_tracker_import_habits';
    private const TOGGLE_ACTION = 'habit_tracker_toggle_habit';
    private const DELETE_ACTION = 'habit_tracker_delete_habit';

    private WpdbHabitRepository $habits;

    public function __construct(WpdbHabitRepository $habits)
    {
        $this->habits = $habits;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'handleSave']);
        add_action('admin_post_' . self::IMPORT_ACTION, [$this, 'handleImport']);
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
        $habit_rows = $this->habits->findAllForAdmin();
        $list_filters = $this->getListFiltersFromRequest();
        $filtered_habit_rows = $this->filterHabitRows($habit_rows, $list_filters);
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
                                <label for="habit-slug"><?php esc_html_e('Slug', 'habit-tracker'); ?></label>
                            </th>
                            <td>
                                <input
                                    id="habit-slug"
                                    name="slug"
                                    type="text"
                                    class="regular-text"
                                    maxlength="191"
                                    value="<?php echo esc_attr($form_values['slug']); ?>"
                                >
                                <p class="description">
                                    <?php esc_html_e('Leave blank to generate a unique slug from the name.', 'habit-tracker'); ?>
                                </p>
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

            <div class="card" style="max-width: 880px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e('Import Shared Habits (JSON)', 'habit-tracker'); ?></h2>
                <p><?php esc_html_e('Upload a JSON file to create habits in bulk. Supported fields per item: name, category, description, slug, sort_order.', 'habit-tracker'); ?></p>
                <p class="description"><code>[{"name":"Read 10 pages","category":"mind","description":"Daily reading"}]</code></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::IMPORT_ACTION); ?>">
                    <?php wp_nonce_field(self::IMPORT_ACTION); ?>

                    <p>
                        <input
                            type="file"
                            name="habits_json_file"
                            accept=".json,application/json"
                            required
                        >
                    </p>

                    <?php submit_button(__('Import Habits', 'habit-tracker')); ?>
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
                        placeholder="<?php esc_attr_e('Name, slug, description...', 'habit-tracker'); ?>"
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
                    count($habit_rows)
                );
                ?>
            </p>

            <?php if ($habit_rows === []) : ?>
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
                        <th><?php esc_html_e('Slug', 'habit-tracker'); ?></th>
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
                            <td><code><?php echo esc_html($habit_row->slug); ?></code></td>
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

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $slug_input = sanitize_text_field(wp_unslash($_POST['slug'] ?? ''));
        $category = $this->normalizeCategoryKey(sanitize_key((string) wp_unslash($_POST['category'] ?? '')));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $sort_order = max(0, (int) wp_unslash($_POST['sort_order'] ?? 0));
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $target = $habit_id > 0 ? ['action' => 'edit', 'habit_id' => $habit_id] : [];
            $this->redirect('habit-name-required', $target);
        }

        if ($slug_input === '' && $existing !== null) {
            $slug_input = (string) $existing->slug;
        }

        $data = [
            'name'                     => $name,
            'slug'                     => $this->habits->generateUniqueSlug($slug_input !== '' ? $slug_input : $name, $habit_id > 0 ? $habit_id : null),
            'category'                 => $category,
            'description'              => $description,
            'default_frequency_type'   => HabitRules::FREQUENCY_DAILY,
            'default_target_count'     => HabitRules::DEFAULT_TARGET_COUNT,
            'default_target_days_mask' => HabitRules::DEFAULT_TARGET_DAYS_MASK,
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

    public function handleImport(): void
    {
        $this->assertManageOptions();
        check_admin_referer(self::IMPORT_ACTION);

        $upload = $_FILES['habits_json_file'] ?? null;

        if (! is_array($upload)) {
            $this->redirect('habit-import-file-required');
        }

        $upload_error = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

        if ($upload_error !== UPLOAD_ERR_OK) {
            $this->redirect('habit-import-upload-failed');
        }

        $tmp_name = isset($upload['tmp_name']) ? (string) $upload['tmp_name'] : '';
        $file_name = isset($upload['name']) ? sanitize_file_name((string) $upload['name']) : '';

        if ($tmp_name === '' || ! is_uploaded_file($tmp_name)) {
            $this->redirect('habit-import-upload-failed');
        }

        $file_type = wp_check_filetype_and_ext($tmp_name, $file_name);
        $extension = isset($file_type['ext']) ? sanitize_key((string) $file_type['ext']) : '';

        if ($extension !== 'json' && ! str_ends_with(strtolower($file_name), '.json')) {
            $this->redirect('habit-import-invalid-file');
        }

        $raw_content = file_get_contents($tmp_name);

        if (! is_string($raw_content) || trim($raw_content) === '') {
            $this->redirect('habit-import-empty-file');
        }

        $decoded = json_decode($raw_content, true);

        if (! is_array($decoded)) {
            $this->redirect('habit-import-invalid-json');
        }

        $items = $this->normalizeImportItems($decoded);

        if ($items === []) {
            $this->redirect('habit-import-empty-file');
        }

        $created = 0;
        $skipped = 0;
        $next_sort_order = $this->getNextSortOrderBase();

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $skipped++;
                continue;
            }

            $name = sanitize_text_field((string) ($item['name'] ?? ''));

            if ($name === '') {
                $skipped++;
                continue;
            }

            $category = $this->normalizeCategoryKey(sanitize_key((string) ($item['category'] ?? '')));
            $description = sanitize_textarea_field((string) ($item['description'] ?? ''));
            $slug_input = isset($item['slug'])
                ? sanitize_text_field((string) $item['slug'])
                : $name;
            $sort_order = isset($item['sort_order'])
                ? max(0, (int) $item['sort_order'])
                : ($next_sort_order + (int) $index);

            $data = [
                'name'                     => $name,
                'slug'                     => $this->habits->generateUniqueSlug($slug_input),
                'category'                 => $category,
                'description'              => $description,
                'default_frequency_type'   => HabitRules::FREQUENCY_DAILY,
                'default_target_count'     => HabitRules::DEFAULT_TARGET_COUNT,
                'default_target_days_mask' => HabitRules::DEFAULT_TARGET_DAYS_MASK,
                'is_active'                => 1,
                'sort_order'               => $sort_order,
            ];

            $created_habit_id = $this->habits->create($data, get_current_user_id());

            if ($created_habit_id <= 0) {
                $skipped++;
                continue;
            }

            $created++;
        }

        if ($created <= 0) {
            $this->redirect(
                'habit-import-none-created',
                ['imported' => 0, 'skipped' => $skipped]
            );
        }

        $this->redirect(
            'habit-import-completed',
            ['imported' => $created, 'skipped' => $skipped]
        );
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
                'slug' => '',
                'category' => HabitRules::CATEGORY_MIND,
                'description' => '',
                'sort_order' => '0',
                'is_active' => '1',
            ];
        }

        return [
            'name' => (string) $habit->name,
            'slug' => (string) $habit->slug,
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

        if ($notice_code === 'habit-import-completed') {
            $imported = isset($_GET['imported']) ? absint(wp_unslash($_GET['imported'])) : 0;
            $skipped = isset($_GET['skipped']) ? absint(wp_unslash($_GET['skipped'])) : 0;
            ?>
            <div class="notice notice-success">
                <p>
                    <?php
                    printf(
                        esc_html__('Import completed. Added: %1$d. Skipped: %2$d.', 'habit-tracker'),
                        $imported,
                        $skipped
                    );
                    ?>
                </p>
            </div>
            <?php
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
            'habit-import-file-required' => [
                'class' => 'notice notice-error',
                'text'  => __('Please select a JSON file to import.', 'habit-tracker'),
            ],
            'habit-import-invalid-file' => [
                'class' => 'notice notice-error',
                'text'  => __('Invalid file type. Upload a .json file.', 'habit-tracker'),
            ],
            'habit-import-empty-file' => [
                'class' => 'notice notice-error',
                'text'  => __('The import file is empty or contains no habits.', 'habit-tracker'),
            ],
            'habit-import-invalid-json' => [
                'class' => 'notice notice-error',
                'text'  => __('Invalid JSON format. Please verify the file structure.', 'habit-tracker'),
            ],
            'habit-import-none-created' => [
                'class' => 'notice notice-warning',
                'text'  => __('Import finished but no habits were created.', 'habit-tracker'),
            ],
            'habit-import-upload-failed' => [
                'class' => 'notice notice-error',
                'text'  => __('Could not read uploaded file. Please try again.', 'habit-tracker'),
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

    private function normalizeImportItems(array $decoded): array
    {
        if (isset($decoded['habits']) && is_array($decoded['habits'])) {
            return $decoded['habits'];
        }

        if ($this->isListArray($decoded)) {
            return $decoded;
        }

        if (isset($decoded['name'])) {
            return [$decoded];
        }

        return [];
    }

    private function isListArray(array $items): bool
    {
        $expected_key = 0;

        foreach (array_keys($items) as $key) {
            if ($key !== $expected_key) {
                return false;
            }

            $expected_key++;
        }

        return true;
    }

    private function getNextSortOrderBase(): int
    {
        $habits = $this->habits->findAllForAdmin();
        $max_sort_order = 0;

        foreach ($habits as $habit) {
            $max_sort_order = max($max_sort_order, (int) ($habit->sort_order ?? 0));
        }

        return $max_sort_order + 1;
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

    private function filterHabitRows(array $habit_rows, array $filters): array
    {
        $filtered = [];
        $search = (string) ($filters['search'] ?? '');
        $selected_category = (string) ($filters['category'] ?? 'all');
        $selected_status = (string) ($filters['status'] ?? 'all');
        $sort = (string) ($filters['sort'] ?? 'default');

        foreach ($habit_rows as $habit_row) {
            if (! is_object($habit_row)) {
                continue;
            }

            $row_category = $this->normalizeCategoryKey((string) ($habit_row->category ?? ''));
            $row_status = ((int) ($habit_row->is_active ?? 0) === 1) ? 'active' : 'inactive';

            if ($selected_category !== 'all' && $row_category !== $selected_category) {
                continue;
            }

            if ($selected_status !== 'all' && $row_status !== $selected_status) {
                continue;
            }

            if (! $this->habitMatchesSearch($habit_row, $search)) {
                continue;
            }

            $filtered[] = $habit_row;
        }

        return $this->sortFilteredHabitRows($filtered, $sort);
    }

    private function habitMatchesSearch(object $habit_row, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystacks = [
            (string) ($habit_row->name ?? ''),
            (string) ($habit_row->slug ?? ''),
            (string) ($habit_row->description ?? ''),
            $this->resolveCategoryLabel((string) ($habit_row->category ?? '')),
        ];

        foreach ($haystacks as $haystack) {
            if ($this->containsCaseInsensitive($haystack, $search)) {
                return true;
            }
        }

        return false;
    }

    private function containsCaseInsensitive(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
        }

        return stripos($haystack, $needle) !== false;
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

    private function sortFilteredHabitRows(array $rows, string $sort): array
    {
        if ($rows === [] || $sort === '' || $sort === 'default') {
            return $rows;
        }

        usort($rows, function (object $left, object $right) use ($sort): int {
            $left_name = (string) ($left->name ?? '');
            $right_name = (string) ($right->name ?? '');
            $left_sort_order = (int) ($left->sort_order ?? 0);
            $right_sort_order = (int) ($right->sort_order ?? 0);
            $left_updated = $this->parseUpdatedTimestamp((string) ($left->updated_at ?? ''));
            $right_updated = $this->parseUpdatedTimestamp((string) ($right->updated_at ?? ''));

            if ($sort === 'name_asc') {
                $comparison = strnatcasecmp($left_name, $right_name);
                return $comparison !== 0 ? $comparison : ($left_sort_order <=> $right_sort_order);
            }

            if ($sort === 'name_desc') {
                $comparison = strnatcasecmp($right_name, $left_name);
                return $comparison !== 0 ? $comparison : ($left_sort_order <=> $right_sort_order);
            }

            if ($sort === 'updated_desc') {
                $comparison = $right_updated <=> $left_updated;
                return $comparison !== 0 ? $comparison : strnatcasecmp($left_name, $right_name);
            }

            if ($sort === 'updated_asc') {
                $comparison = $left_updated <=> $right_updated;
                return $comparison !== 0 ? $comparison : strnatcasecmp($left_name, $right_name);
            }

            return 0;
        });

        return $rows;
    }

    private function parseUpdatedTimestamp(string $date_time): int
    {
        $timestamp = strtotime($date_time);

        if (! is_int($timestamp)) {
            return 0;
        }

        return $timestamp;
    }
}
