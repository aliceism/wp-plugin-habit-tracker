<?php

namespace HabitTracker;

use HabitTracker\Admin\HabitAdminPage;
use HabitTracker\Infrastructure\Database\Migrations;
use HabitTracker\Infrastructure\Persistence\WpdbHabitRepository;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

    private ?WpdbHabitRepository $habit_repository = null;

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

    private function bootAdmin(): void
    {
        $admin_page = new HabitAdminPage($this->habits());

        $admin_page->register();
    }
}
