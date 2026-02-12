<?php

if (!defined("ABSPATH")) {
    exit;
}

class Habit_Tracker_Admin
{
    public function __construct()
    {
        add_action("admin_menu", [$this, "add_plugin_menu"]);
        add_action("admin_enqueue_scripts", [$this, "enqueue_admin_assets"]);
        add_action("wp_ajax_add_habit", [$this, "ajax_add_habit"]);
        add_action("wp_ajax_delete_habit", [$this, "ajax_delete_habit"]);
        add_action("wp_ajax_update_habit", [$this, "ajax_update_habit"]);
        add_action("wp_ajax_filter_habits", [$this, "ajax_filter_habits"]);

    }
    public function add_plugin_menu()
    {
        add_menu_page(
            'Habit Tracker',
            'Habit Tracker',
            'manage_options',
            'habit-tracker',
            [$this, 'render_admin_page'],
            'dashicons_heart',
            25
        );
    }
    public function enqueue_admin_assets()
    {
        wp_enqueue_script(
            'habit-admin',
            plugin_dir_url(__FILE__) . 'js/habit-admin.js',
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('habit-admin', 'habitTracker', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('habit_ajax_nonce'),
        ]);
    }
    public function ajax_add_habit()
    {
        check_ajax_referer('habit_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permission']);
        }
        $name = sanitize_text_field($_POST['habit_name'] ?? '');
        $category = sanitize_text_field($_POST['habit_category'] ?? '');

        if (empty($name)) {
            wp_send_json_error(['message' => 'Habit name is required']);
        }
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'habits',
            [
                'user_id' => get_current_user_id(),
                'name' => $name,
                'category' => $category,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s'],
        );

        $habit_id = $wpdb->insert_id;

        $habit = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}habits WHERE habit_id = %d",
                $habit_id
            )
        );

        $row_html = $this->render_habit_row($habit);

        wp_send_json_success([
            'message' => 'Habit added successfully',
            'row' => $row_html
        ]);
    }
    public function ajax_delete_habit()
    {
        check_ajax_referer('habit_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to delete this habit.']);
        }

        $habit_id = intval($_POST['habit_id']) ?? 0;

        if (!$habit_id) {
            wp_send_json_error(['message' => 'Invalid habit ID']);
        }

        global $wpdb;

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'habits',
            [
                'habit_id' => $habit_id,
                'user_id' => get_current_user_id(),
            ],
            ['%d', '%d']
        );

        if (!$deleted) {
            wp_send_json_error(['message' => 'Failed to delete habit']);
        }
        if ($deleted === 0) {
            wp_send_json_error(['message' => 'Habit not found or not permission']);
        }

        wp_send_json_success(['message' => 'Habit deleted successfully']);

    }
    public function ajax_update_habit()
    {
        check_ajax_referer('habit_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permission']);
        }

        global $wpdb;

        $habit_id = intval($_POST['habit_id']);
        $name = sanitize_text_field($_POST['habit_name']);
        $category = sanitize_text_field($_POST['habit_category']);

        if (!$habit_id || empty(($name))) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'habits',
            [
                'name' => $name,
                'category' => $category
            ],
            [
                'habit_id' => $habit_id,
                'user_id' => get_current_user_id()
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );
        if (!$updated) {
            wp_send_json_error(['message' => 'DB update failed']);
        }
        if ($updated === 0) {
            wp_send_json_error(['message' => 'Habit not found or no permission']);
        }

        wp_send_json_success(['message' => 'Habit updated']);
    }
    public function ajax_filter_habits()
    {
        check_ajax_referer('habit_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => "No permission"]);
        }

        global $wpdb;

        $search = isset($_POST["search"]) ? sanitize_text_field($_POST["search"]) : "";
        $category = isset($_POST["category"]) ? sanitize_text_field($_POST["category"]) : "all";

        $sort = isset($_POST["sort"]) ? sanitize_text_field($_POST["sort"]) : "newest";

        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 10;

        $offset = ($page - 1) * $per_page;

        $table = $wpdb->prefix . "habits";

        $order_by = "created_at DESC";

        switch ($sort) {
            case "oldest":
                $order_by = "created_at ASC";
                break;
            case "name_asc":
                $order_by = "name ASC";
                break;
            case "name_desc":
                $order_by = "name DESC";
                break;
            case "newest":
                break;
        }

        $where = [];
        $params = [];

        $where[] = 'user_id = %d';
        $params[] = get_current_user_id();

        if ($search !== '') {
            $where[] = 'name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($category !== 'all') {
            $where[] = 'category = %s';
            $params[] = $category;
        }

        $where_sql = '';

        if (!empty($where)) {
            $where_sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $count_sql = "SELECT COUNT(*) FROM {$table}{$where_sql}";
        $total = (int) $wpdb->get_var(
            $wpdb->prepare($count_sql, $params)
        );

        if ($total === 0) {
            wp_send_json_success([
                'rows' => '',
                'page' => 1,
                'total_pages' => 1,
                'total' => 0
            ]);
        }

        $total_pages = (int) ceil($total / $per_page);

        if ($page > $total_pages) {
            $page = $total_pages;
            $offset = ($page - 1) * $per_page;
        }

        $data_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";

        $params_with_limit = array_merge($params, [$per_page, $offset]);

        $habits = $wpdb->get_results($wpdb->prepare($data_sql, $params_with_limit));

        //Render rows
        $html = '';
        foreach ($habits as $habit) {
            $html .= $this->render_habit_row($habit);
        }

        wp_send_json_success([
            'rows' => $html,
            'page' => $page,
            'total_pages' => $total_pages,
            'total' => $total,
        ]);

    }
    public function get_user_habits()
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}habits WHERE user_id = %d ORDER BY created_at DESC",
                get_current_user_id()
            )
        );
    }
    private function render_habit_row($habit)
    {
        ob_start();
        ?>
        <tr data-id="<?php echo esc_html($habit->habit_id); ?>" data-name="<?php echo esc_html($habit->name); ?>"
            data-category="<?php echo esc_html($habit->category); ?>">
            <td class="habit-name">
                <?php echo esc_html($habit->name); ?>
            </td>
            <td class="habit-category">
                <?php echo esc_html($habit->category); ?>
            </td>
            <td>
                <?php echo esc_html($habit->created_at); ?>
            </td>

            <td class="actions">
                <a href="#" class="button habit-edit">
                    Edit
                </a>
                <a href="#" class="button button-primary habit-save">
                    Save
                </a>
                <a href="#" class="button habit-cancel">
                    Cancel
                </a>

                <a href="#" class="button habit-delete" data-id="<?php echo esc_attr($habit->habit_id); ?>"
                    data-name="<?php echo esc_attr($habit->name); ?>">
                    Delete
                </a>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
    public function render_admin_page()
    {
        ?>
        <div class='wrap'>
            <h1>Habit Tracker</h1>
            <form method="post" id="habit-form">
                <?php wp_nonce_field('save_habit', 'habit_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="habit_name">Habit name</label>
                        </th>
                        <td>
                            <input type="text" name="habit_name" id="habit_name" class="regular-text" required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="habit_category">Category</label>
                        </th>
                        <td>
                            <input type="text" name="habit_category" id="habit_category" class="regular-text">
                        </td>
                    </tr>
                </table>
                <button type="submit" class="button button-primary">
                    Add Habit
                </button>
            </form>

            <hr>

            <div class="habit-filters" style="margin: 20px 0; display: flex; gap: 10px; align-items: center;">

                <input type="text" id="habit-search" placeholder="Search habits..." class="regular-text">

                <select id="habit-category-filter">
                    <option value="all">All categories</option>
                </select>

                <select id="habit-sort">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="name_asc">Name A-Z</option>
                    <option value="name_desc">Name Z-A</option>
                </select>

                <button type="button" class="button" id="habit-filter-apply">
                    Apply
                </button>
            </div>

            <table class="widefat fixed striped" id="habits-table">
                <thead>
                    <tr>
                        <th>Habit</th>
                        <th>Category</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="4">Loading...</td>
                    </tr>
                </tbody>
            </table>

            <div id="habit-pagination" style="margin-top: 15px; display: flex; gap: 12px; align-items: center;">
                <button type="button" class="button" id="habit-prev-page" disabled>
                    <- Prev</button>
                        <span id="habit-page-info">
                            Page 1 of 1</span>
                        <button type="button" class="button" id="habit-next-page" disabled>
                            Next -></button>
            </div>
        </div>
        <?php
    }

}