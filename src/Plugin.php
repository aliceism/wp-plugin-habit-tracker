<?php

namespace HabitTracker;

use HabitTracker\Admin\HabitAdminPage;
use HabitTracker\Frontend\HabitsShortcode;
use HabitTracker\Infrastructure\Database\Migrations;
use HabitTracker\Infrastructure\Persistence\WpdbHabitRepository;
use HabitTracker\Infrastructure\Persistence\WpdbUserHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

    private ?WpdbHabitRepository $habit_repository = null;

    private ?WpdbUserHabitRepository $user_habit_repository = null;

    private ?HabitsShortcode $habits_shortcode = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        Migrations::maybeMigrate();
        $this->bootFrontend();

        if (is_admin()) {
            $this->bootAdmin();
        }
    }

    public function habits(): WpdbHabitRepository
    {
        if (! $this->habit_repository instanceof WpdbHabitRepository) {
            $this->habit_repository = new WpdbHabitRepository();
        }

        return $this->habit_repository;
    }

    public function userHabits(): WpdbUserHabitRepository
    {
        if (! $this->user_habit_repository instanceof WpdbUserHabitRepository) {
            $this->user_habit_repository = new WpdbUserHabitRepository();
        }

        return $this->user_habit_repository;
    }

    private function bootAdmin(): void
    {
        $admin_page = new HabitAdminPage($this->habits());

        $admin_page->register();
    }

    private function bootFrontend(): void
    {
        if ($this->habits_shortcode instanceof HabitsShortcode) {
            return;
        }

        $this->habits_shortcode = new HabitsShortcode($this->habits(), $this->userHabits());
        $this->habits_shortcode->register();
    }
}
