<?php

if (!defined("ABSPATH")) {
    exit;
}

class Habit_Tracker_Admin
{
    public function __construct()
    {
        add_action("admin_menu", [$this, "add_plugin_menu"]);
        add_action("admin_init", [$this, "handle_actions"]);
        add_action("admin_notices", [$this, "show_admin_notices"]);
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
    public function handle_actions()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['submit_habit'])) {
            $this->handle_add_habit();
        }
        if (isset($_POST['update_habit'])) {
            $this->handle_update_habit();
        }
        if (isset($_GET['delete'])) {
            $this->handle_delete_habit();
        }
    }
    public function handle_add_habit()
    {
        if (
            !isset($_POST['habit_nonce']) ||
            !wp_verify_nonce($_POST['habit_nonce'], 'save_habit')
        ) {
            return;
        }
        global $wpdb;

        if (empty($_POST['habit_name'])) {
            return;
        }

        $wpdb->insert(
            $wpdb->prefix . 'habits',
            [
                'user_id' => get_current_user_id(),
                'name' => sanitize_text_field($_POST['habit_name']),
                'category' => sanitize_text_field($_POST['habit_category']),
            ],
            ['%d', '%s', '%s'],
        );
        wp_redirect(admin_url('admin.php?page=habit-tracker&message=added'));
        exit;
    }
    public function handle_update_habit()
    {
        if (
            !isset($_POST['habit_nonce']) ||
            !wp_verify_nonce($_POST['habit_nonce'], 'save_habit')
        ) {
            return;
        }

        global $wpdb;

        if (empty($_POST['habit_id']) || empty($_POST['habit_name'])) {
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'habits',
            [
                'name' => sanitize_text_field($_POST['habit_name']),
                'category' => sanitize_text_field($_POST['habit_category']),
            ],
            ['habit_id' => intval($_POST['habit_id'])],
            ['%s', '%s'],
            ['%d'],
        );
        wp_redirect(admin_url('admin.php?page=habit-tracker&message=updated'));
        exit;
    }
    public function handle_delete_habit()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!isset($_GET['delete']) || !isset($_GET['_wpnonce'])) {
            return;
        }

        $habit_id = intval($_GET['delete']);

        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_habit_' . $habit_id)) {
            return;
        }

        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'habits',
            ['habit_id' => $habit_id],
            ['%d']
        );
        wp_redirect(admin_url('admin.php?page=habit-tracker&message=deleted'));
        exit;
    }
    public function handle_edit_habit()
    {
        if (!isset($_GET["edit"])) {
            return null;
        }
        global $wpdb;

        $habit_id = intval($_GET["edit"]);

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}habits WHERE habit_id = %d",
                $habit_id
            )
        );
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
    public function show_admin_notices()
    {
        if (!isset($_GET["message"])) {
            return;
        }
        $message = sanitize_text_field($_GET["message"]);
        $messages = [
            "added" => ['Habit added successfully.', 'success'],
            "updated" => ['Habit updated successfully.', 'success'],
            "deleted" => ['Habit deleted successfully.', 'success'],
            "error" => ['Something went wrong.', 'error'],
        ];
        if (!isset($messages[$message])) {
            return;
        }
        [$text, $type] = $messages[$message];
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($text); ?></p>
        </div>
        <?php
    }
    public function render_admin_page()
    {
        $habits = $this->get_user_habits();
        $habit_to_edit = $this->handle_edit_habit();
        $editing = $habit_to_edit !== null;
        ?>
        <div class='wrap'>
            <h1>Habit Tracker</h1>

            <form method="post">
                <?php wp_nonce_field('save_habit', 'habit_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="habit_name">Habit name</label>
                        </th>
                        <td>
                            <input type="text" name="habit_name" value="<?php echo esc_attr($habit_to_edit->name ?? ''); ?>"
                                id="habit_name" class="regular-text" required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="habit_category">Category</label>
                        </th>
                        <td>
                            <input type="text" name="habit_category"
                                value="<?php echo esc_attr($habit_to_edit->category ?? ''); ?>" id="habit_category"
                                class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php if ($editing): ?>
                                <input type="hidden" name="habit_id" value="<?php echo esc_attr($habit_to_edit->habit_id); ?>">
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="<?php echo $editing ? 'update_habit' : 'submit_habit'; ?>"
                    class="button button-primary">
                    <?php echo $editing ? 'Update Habit' : 'Add Habit'; ?>
                </button>
            </form>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Habit</th>
                        <th>Category</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($habits)): ?>
                        <?php foreach ($habits as $habit): ?>
                            <tr>
                                <td><?php echo esc_html($habit->name); ?></td>
                                <td><?php echo esc_html($habit->category); ?></td>
                                <td><?php echo esc_html($habit->created_at); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=habit-tracker&edit=' . $habit->habit_id); ?>"
                                        class="button">
                                        Edit
                                    </a>
                                    <?php
                                    $delete_url = wp_nonce_url(
                                        admin_url('admin.php?page=habit-tracker&delete=' . $habit->habit_id),
                                        'delete_habit_' . $habit->habit_id
                                    ); ?>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="button button-link-delete">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">No habits added yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
        <?php
    }

}