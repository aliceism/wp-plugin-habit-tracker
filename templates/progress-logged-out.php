<?php
if (! defined('ABSPATH')) {
    exit;
}

$kicker = isset($kicker) ? (string) $kicker : __('Progress Access', 'habit-tracker');
$title = isset($title) ? (string) $title : __('Log In To Review Progress', 'habit-tracker');
$description = isset($description) ? (string) $description : __('You need an account to review category trends, streaks, and completion metrics.', 'habit-tracker');
$button_text = isset($button_text) ? (string) $button_text : __('Login', 'habit-tracker');

include HABIT_TRACKER_PATH . 'templates/partials/logged-out-card.php';
