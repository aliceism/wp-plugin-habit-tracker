<?php

namespace HabitTracker;

use HabitTracker\Admin\HabitAdminPage;
use HabitTracker\Frontend\AuthShortcode;
use HabitTracker\Frontend\DashboardShortcode;
use HabitTracker\Frontend\HabitsShortcode;
use HabitTracker\Frontend\ProfileShortcode;
use HabitTracker\Frontend\ProgressShortcode;
use HabitTracker\Infrastructure\Database\Migrations;
use HabitTracker\Infrastructure\Persistence\WpdbCheckinRepository;
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

    private ?WpdbCheckinRepository $checkin_repository = null;

    private ?HabitsShortcode $habits_shortcode = null;

    private ?DashboardShortcode $dashboard_shortcode = null;

    private ?ProgressShortcode $progress_shortcode = null;

    private ?AuthShortcode $auth_shortcode = null;

    private ?ProfileShortcode $profile_shortcode = null;

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

    public function checkins(): WpdbCheckinRepository
    {
        if (! $this->checkin_repository instanceof WpdbCheckinRepository) {
            $this->checkin_repository = new WpdbCheckinRepository();
        }

        return $this->checkin_repository;
    }

    private function bootAdmin(): void
    {
        $admin_page = new HabitAdminPage($this->habits());

        $admin_page->register();
    }

    private function bootFrontend(): void
    {
        if ($this->habits_shortcode instanceof HabitsShortcode) {
            if ($this->dashboard_shortcode instanceof DashboardShortcode) {
                if ($this->progress_shortcode instanceof ProgressShortcode) {
                    if ($this->auth_shortcode instanceof AuthShortcode) {
                        if ($this->profile_shortcode instanceof ProfileShortcode) {
                            return;
                        }
                    }
                }
            }
        }

        if (! $this->habits_shortcode instanceof HabitsShortcode) {
            $this->habits_shortcode = new HabitsShortcode($this->habits(), $this->userHabits());
            $this->habits_shortcode->register();
        }

        if (! $this->dashboard_shortcode instanceof DashboardShortcode) {
            $this->dashboard_shortcode = new DashboardShortcode($this->userHabits(), $this->checkins());
            $this->dashboard_shortcode->register();
        }

        if (! $this->progress_shortcode instanceof ProgressShortcode) {
            $this->progress_shortcode = new ProgressShortcode($this->userHabits(), $this->checkins());
            $this->progress_shortcode->register();
        }

        if (! $this->auth_shortcode instanceof AuthShortcode) {
            $this->auth_shortcode = new AuthShortcode();
            $this->auth_shortcode->register();
        }

        if (! $this->profile_shortcode instanceof ProfileShortcode) {
            $this->profile_shortcode = new ProfileShortcode();
            $this->profile_shortcode->register();
        }
    }
}
