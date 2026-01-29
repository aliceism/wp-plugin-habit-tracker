<?php

if (!defined("ABSPATH")) {
    exit;
}

class Habit_Tracker_Admin
{
    public function __construct()
    {
        add_action("admin_menu", [$this, "add_plugin_menu"]);
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
    private function handle_add_habit()
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

    }
    private function handle_update_habit()
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

    }
    private function handle_delete_habit()
    {
        if (!isset($_GET['delete'])) {
            return;
        }

        global $wpdb;
        $habit_id = intval($_GET['delete']);

        $wpdb->delete(
            $wpdb->prefix . 'habits',
            ['habit_id' => $habit_id],
            ['%d']
        );

    }
    private function handle_edit_habit()
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

    public function render_admin_page()
    {
        $this->handle_delete_habit();
        $habit_to_edit = $this->handle_edit_habit();
        $editing = $habit_to_edit !== null;
        if (isset($_POST['submit_habit'])) {
            $this->handle_add_habit();
        }
        if (isset($_POST['update_habit'])) {
            $this->handle_update_habit();
        }

        global $wpdb;
        $table_name = $wpdb->prefix . "habits";
        $habits = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
                get_current_user_id()
            )
        );

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
                                    <a href="<?php echo admin_url('admin.php?page=habit-tracker&delete=' . $habit->habit_id); ?>"
                                        class="button">
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