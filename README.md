# HabitLab Habit Tracker

HabitLab Habit Tracker is a Bachelor Thesis project implemented as a custom WordPress plugin. It turns WordPress into a habit management platform where administrators can curate a shared habit library, and registered users can build personal habit dashboards, record daily progress, maintain streaks, and review analytics.

The project focuses on product-oriented problem solving: helping users select meaningful habits, track consistency over time, and understand progress through clear dashboard and reporting views.

## Table of Contents

- [Features](#features)
- [Screenshots](#screenshots)
- [Architecture](#architecture)
- [Database Design](#database-design)
- [User Workflow](#user-workflow)
- [Shortcodes](#shortcodes)
- [Technologies Used](#technologies-used)
- [Testing](#testing)
- [Key Learning Outcomes](#key-learning-outcomes)
- [Repository Structure](#repository-structure)
- [Future Improvements](#future-improvements)

## Features

- Admin-managed shared habit library with create, edit, activate/deactivate, delete, search, filter, sort, and assignment counts.
- User habit stack built from shared habits and custom user-created habits.
- Habit categories for mind, body, productivity, life, and custom habits.
- Configurable targets using daily tracking or weekly completion goals from 1 to 7 days per week.
- Daily check-in workflow for active habits, with form fallback and AJAX updates.
- Dashboard metrics for current month progress, momentum streaks, today's completion, active days, top habits, and active streaks.
- Progress reporting by category, week, month, and individual habit performance.
- User registration, login form integration, profile editing, and password update flows.
- Theme-aware template rendering with plugin templates as defaults and support for theme overrides.
- Data validation, sanitization, nonces, prepared database queries, and authenticated mutation handlers.
- Database migrations on activation and version changes.

## Screenshots

| Area | Placeholder |
| --- | --- |
| Dashboard | `docs/screenshots/dashboard.png` |
| Habit Library | `docs/screenshots/habits.png` |
| Progress Analytics | `docs/screenshots/progress.png` |
| Admin Management | `docs/screenshots/admin.png` |

## Architecture

The plugin is organized around clear application layers instead of placing all logic in WordPress hooks.

- **Bootstrap**: `habit-tracker.php` defines plugin metadata, constants, compatibility helpers, autoloading, activation hooks, and plugin bootstrapping.
- **Application coordinator**: `src/Plugin.php` is a singleton responsible for wiring repositories, admin pages, frontend shortcodes, and migrations.
- **Domain logic**: `src/Domain/` contains habit rules and analytics calculations. This includes category and frequency normalization, target calculations, streak logic, dashboard metrics, and progress summaries.
- **Persistence layer**: `src/Infrastructure/Persistence/` wraps direct `wpdb` usage behind repository classes for shared habits, user habits, and check-ins.
- **Database layer**: `src/Infrastructure/Database/` defines table schemas and migration execution through WordPress `dbDelta`.
- **Admin UI**: `src/Admin/HabitAdminPage.php` implements the WordPress admin interface for managing shared habits.
- **Frontend UI**: `src/Frontend/` contains shortcode controllers, authentication/profile flows, dashboard actions, navigation helpers, asset loading, and template resolution.
- **Presentation templates**: `templates/` contains PHP templates for dashboard, habits, progress, authentication, profile, and logged-out states.
- **Assets**: `assets/css/frontend.css` and `assets/js/frontend.js` provide the frontend interface, modal behavior, filtering/sorting controls, AJAX interactions, and app-style navigation enhancements.

## Database Design

The plugin creates three custom WordPress database tables using the active WordPress table prefix.

### `wp_habit_tracker_habits`

Stores shared habits curated by administrators.

Important fields include:

- `name`, `category`, and `description`
- default frequency fields: `default_frequency_type`, `default_target_count`
- `is_active` status
- author and timestamp metadata

This table represents the reusable habit catalog that users can add to their own dashboard.

### `wp_habit_tracker_user_habits`

Stores each user's active or archived habit stack.

Important fields include:

- `user_id`
- optional `habit_id` reference for shared habits
- `source_type` to distinguish shared habits from custom habits
- copied habit name/category/description for user-level display
- user-specific `frequency_type`, `target_count`, `start_date`, `position`, and archive state

This table separates global habit definitions from each user's personal tracking configuration.

### `wp_habit_tracker_checkins`

Stores daily completion records.

Important fields include:

- `user_habit_id`
- `user_id`
- `checkin_date`
- `status`
- optional `value` and `note`
- timestamp metadata

A unique key on `user_habit_id` and `checkin_date` prevents duplicate check-ins for the same habit on the same day.

## User Workflow

### Administrator

1. Opens the WordPress admin menu item **Habit Tracker**.
2. Creates shared habits with a name, category, description, and active status.
3. Searches, filters, sorts, edits, activates/deactivates, or deletes shared habits.
4. Reviews how many users have assigned each shared habit.

### Registered User

1. Registers or logs in through the plugin's frontend authentication shortcodes.
2. Adds shared habits from the admin-curated library.
3. Creates custom habits for personal goals.
4. Sets a weekly target from 1 to 7 completions.
5. Removes habits from the active stack when they are no longer needed.
6. Checks habits off each day from the dashboard.
7. Reviews monthly completion, streaks, category trends, and habit performance.
8. Updates profile details and password through the profile shortcode.

## Shortcodes

The plugin exposes modular shortcodes so pages can render full views or individual sections.

| Shortcode | Purpose |
| --- | --- |
| `[habit_tracker_habits]` | Full habit management view |
| `[habit_tracker_habits_notice]` | Habit action feedback |
| `[habit_tracker_habits_stack]` | User's active habit stack |
| `[habit_tracker_habits_shared]` | Shared habit library |
| `[habit_tracker_habits_custom]` | Custom habit form |
| `[habit_tracker_dashboard]` | Full tracking dashboard |
| `[habit_tracker_dashboard_notice]` | Dashboard action feedback |
| `[habit_tracker_dashboard_metrics]` | Dashboard metric cards |
| `[habit_tracker_dashboard_panels]` | Dashboard tracking and side panels |
| `[habit_tracker_progress]` | Full progress reporting view |
| `[habit_tracker_progress_metrics]` | Category progress metrics |
| `[habit_tracker_progress_breakdown]` | Progress charts and habit breakdown |
| `[habit_tracker_login_form]` | Login form |
| `[habit_tracker_register_form]` | Registration form |
| `[habit_tracker_profile]` | Profile and password management |

## Technologies Used

- WordPress Plugin API
- PHP 7.4+
- MySQL / MariaDB through WordPress `wpdb`
- WordPress shortcodes, admin pages, hooks, nonces, authentication, and user APIs
- Custom database migrations with `dbDelta`
- Vanilla JavaScript for modals, AJAX updates, filtering, sorting, and navigation behavior
- CSS for responsive frontend presentation
- Plain PHP test runner for isolated domain and helper tests

## Testing

The repository includes a lightweight PHP test harness in `tests/`.

Current test coverage focuses on:

- `HabitMath` streak, date range, and target calculations
- `CurrentUrlResolver` URL normalization behavior

Run the tests from the plugin root:

```bash
php tests/run.php
```

Latest local result:

```text
All tests passed: 14
```

## Key Learning Outcomes

- Designing a non-trivial WordPress plugin with separation between domain logic, persistence, controllers, templates, and assets.
- Modeling habit tracking as a product workflow rather than only a CRUD interface.
- Building custom database tables and migration logic in a WordPress environment.
- Applying secure WordPress development practices such as nonces, capability checks, escaping, sanitization, and prepared SQL queries.
- Implementing analytics features such as streaks, category summaries, active-day consistency, weekly targets, and progress percentages.
- Supporting progressive enhancement by combining form submissions with AJAX updates.
- Creating testable PHP domain logic outside the full WordPress runtime.

## Repository Structure

```text
habit-tracker/
|-- assets/
|   |-- css/frontend.css
|   `-- js/frontend.js
|-- src/
|   |-- Admin/
|   |-- Domain/
|   |   |-- Analytics/
|   |   `-- Rules/
|   |-- Frontend/
|   |-- Infrastructure/
|   |   |-- Database/
|   |   `-- Persistence/
|   |-- Support/
|   `-- Plugin.php
|-- templates/
|-- tests/
|   |-- Unit/
|   |-- bootstrap.php
|   `-- run.php
|-- habit-tracker.php
|-- uninstall.php
`-- README.md
```

## Future Improvements

- Add PHPUnit configuration and broader automated tests for repositories and shortcode handlers.
- Add REST API endpoints for a richer JavaScript frontend.
