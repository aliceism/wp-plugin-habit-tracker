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
        ob_start();
        ?>
        <tr data-id="<?php echo esc_attr($habit->habit_id); ?>">
            <td>
                <?php echo esc_html($habit->name); ?>
            </td>
            <td>
                <?php echo esc_html($habit->category); ?>
            </td>
            <td>
                <?php echo esc_html($habit->created_at); ?>
            </td>
            <td>
                <a href="<?php echo admin_url('admin.php?page=habit-tracker&edit=' . $habit->habit_id); ?>" class="button">
                    Edit
                </a>

                <a href="<?php echo admin_url('admin.php?page=habit-tracker&delete=' . $habit->habit_id .
                    '&_wpnonce=' . wp_create_nonce('delete_habit_' . $habit->habit_id))
                ; ?>" class="button habit-delete" data-id="<?php echo esc_attr($habit->habit_id); ?>"
                    data-name="<?php echo esc_attr($habit->name); ?>">
                    Delete
                </a>
            </td>
        </tr>
        <?php
        $row_html = ob_get_clean();
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

        $deleted = $wpdb->delete($wpdb->prefix . 'habits', ['habit_id' => $habit_id], ['%d']);

        if (!$deleted) {
            wp_send_json_error(['message' => 'Failed to delete habit']);
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
            ['habit_id' => $habit_id],
            ['%s', '%s'],
            ['%d']
        );
        if ($updated === false) {
            wp_send_json_error(['message' => 'DB update failed']);
        }
        wp_send_json_success(['message' => 'Habit updated']);
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
    public function render_admin_page()
    {
        $habits = $this->get_user_habits();

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

            <table class="widefat fixed striped" id="habits-table">
                <thead>
                    <tr>
                        <th>Habit</th>
                        <th>Category</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="habits-table-body">
                    <?php if (!empty($habits)): ?>
                        <?php foreach ($habits as $habit): ?>
                            <tr data-id="<?php echo esc_html($habit->habit_id); ?>" data-name="<?php echo esc_html($habit->name); ?>"
                                data-category="<?php echo esc_html($habit->category); ?>">
                                <td class="habit-name"><?php echo esc_html($habit->name); ?></td>
                                <td class="habit_category"><?php echo esc_html($habit->category); ?></td>
                                <td><?php echo esc_html($habit->created_at); ?></td>

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

                                    <a href="<?php echo admin_url('admin.php?page=habit-tracker&delete=' . $habit->habit_id .
                                        '&_wpnonce=' . wp_create_nonce('delete_habit_' . $habit->habit_id))
                                    ; ?>" class="button habit-delete"
                                        data-id="<?php echo esc_attr($habit->habit_id); ?>"
                                        data-name="<?php echo esc_attr($habit->name); ?>">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan=" 3">No habits added yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
        <?php
    }

}