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
        ?>
        <div class='wrap'>
            <h1>Habit Tracker</h1>

            <form action="post">
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
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Add habit">
                </p>
            </form>
        </div>
        <?php
    }
}