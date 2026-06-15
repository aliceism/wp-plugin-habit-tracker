<?php
if (! defined('ABSPATH')) {
    exit;
}

$kicker = isset($kicker) ? (string) $kicker : __('Habits Access', 'habit-tracker');
$title = isset($title) ? (string) $title : __('Log In To Build Your Stack', 'habit-tracker');
$description = isset($description) ? (string) $description : __('You need an account to add shared habits and create custom habits for your dashboard.', 'habit-tracker');
$button_text = isset($button_text) ? (string) $button_text : __('Login', 'habit-tracker');

include HABIT_TRACKER_PATH . 'templates/partials/logged-out-card.php';
