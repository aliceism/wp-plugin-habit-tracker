<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Preserve user data for now; only clean up plugin metadata.
delete_option('habit_tracker_db_version');
delete_site_option('habit_tracker_db_version');
