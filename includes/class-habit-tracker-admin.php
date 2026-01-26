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
    public function render_admin_page()
    {
        if (isset($_POST['submit_habit'])) {
            $this->handle_add_habit();
        }

        ?>
        <div class='wrap'>
            <h1>Habit Tracker</h1>

            <form method="post">
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
                <button type="submit" name="submit_habit" class="button button-primary">Add Habit</button>
            </form>
        </div>
        <?php
    }
    private function handle_add_habit()
    {
        global $wpdb;

        if (empty($_POST['habit_name'])) {
            return;
        }

        $table_name = $wpdb->prefix . 'habits';

        $wpdb->insert(
            $table_name,
            [
                "user_id" => get_current_user_id(),
                "name" => sanitize_text_field($_POST["habit_name"]),
                "category" => sanitize_text_field($_POST["habit_category"]),
            ],
            [
                "%d",
                "%s",
                "%s",
            ]
        );
    }
}