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
            <p>Welcome to your habit tracking dashboard.</p>
        </div>
        <?php
    }
}