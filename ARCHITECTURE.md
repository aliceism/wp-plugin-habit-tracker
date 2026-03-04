# Habit Tracker Plugin Architecture

## Role of the Plugin

The `habit-tracker` plugin owns:

- data storage
- habit business rules
- auth shortcodes
- frontend interactions for Habits, Dashboard, and Progress
- admin management for the shared habit library

The `habitlab` theme owns:

- page routing by slug
- app shell and branded layout
- navigation and page templates

## Core Product Flows

### Admin Flow

1. Admin creates and manages shared habit templates.
2. Shared templates become visible on the `Habits` page.

### User Flow

1. User browses shared habit templates on `Habits`.
2. User adds a template habit to their dashboard.
3. User can also create a custom habit that belongs only to them.
4. `Dashboard` shows only the current user's active dashboard habits.
5. User checks habits daily.
6. `Progress` calculates streaks and weekly/monthly summaries from check-in data.

## Database Strategy

The plugin should use three custom tables:

- `{$wpdb->prefix}habit_tracker_templates`
- `{$wpdb->prefix}habit_tracker_user_habits`
- `{$wpdb->prefix}habit_tracker_checkins`

No summary tables are needed for MVP. Weekly and monthly progress should be derived from `checkins`.

WordPress plugins should not rely on MySQL foreign keys for correctness. Ownership and integrity should be enforced in PHP services and repositories.

## Table 1: Habit Templates

Shared habits managed by admins and offered as reusable options on the `Habits` page.

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
name VARCHAR(191) NOT NULL
slug VARCHAR(191) NOT NULL
category VARCHAR(100) NOT NULL DEFAULT ''
description TEXT NULL
default_frequency_type VARCHAR(20) NOT NULL DEFAULT 'daily'
default_target_count SMALLINT UNSIGNED NOT NULL DEFAULT 1
default_target_days_mask TINYINT UNSIGNED NOT NULL DEFAULT 127
is_active TINYINT(1) NOT NULL DEFAULT 1
sort_order INT UNSIGNED NOT NULL DEFAULT 0
created_by_user_id BIGINT UNSIGNED NOT NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
PRIMARY KEY (id)
UNIQUE KEY slug (slug)
KEY is_active_sort (is_active, sort_order, name)
KEY category (category)
```

### Notes

- `slug` gives each template a stable identity.
- `default_target_days_mask` is a 7-bit mask for weekdays.
- For MVP, all templates should be created as `daily` habits with `default_target_count = 1`.
- `sort_order` allows intentional ordering on the `Habits` page.

## Table 2: User Habits

Concrete habits shown on a user's dashboard. A row can come from an admin template or be fully custom.

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
user_id BIGINT UNSIGNED NOT NULL
template_id BIGINT UNSIGNED NULL
source_type VARCHAR(20) NOT NULL
name VARCHAR(191) NOT NULL
category VARCHAR(100) NOT NULL DEFAULT ''
description TEXT NULL
frequency_type VARCHAR(20) NOT NULL DEFAULT 'daily'
target_count SMALLINT UNSIGNED NOT NULL DEFAULT 1
target_days_mask TINYINT UNSIGNED NOT NULL DEFAULT 127
start_date DATE NOT NULL
position INT UNSIGNED NOT NULL DEFAULT 0
is_active TINYINT(1) NOT NULL DEFAULT 1
created_by_user_id BIGINT UNSIGNED NOT NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
archived_at DATETIME NULL
PRIMARY KEY (id)
UNIQUE KEY user_template_unique (user_id, template_id)
KEY user_active_position (user_id, is_active, position, id)
KEY user_category (user_id, category)
KEY template_id (template_id)
KEY source_type (source_type)
```

### Notes

- `source_type` values:
  - `template`
  - `custom`
- `template_id` is nullable because custom habits have no template origin.
- `name`, `category`, and `description` are copied onto the user habit so the dashboard remains stable even if a template changes later.
- `user_template_unique` prevents a user from adding the same shared template more than once.
- If a user re-adds an archived template habit, the service should reactivate the existing row instead of inserting a duplicate.
- `position` is reserved for dashboard ordering.

## Table 3: Check-ins

Daily tracking records for each user habit. Progress, streaks, and charts are calculated from this table.

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
user_habit_id BIGINT UNSIGNED NOT NULL
user_id BIGINT UNSIGNED NOT NULL
checkin_date DATE NOT NULL
status VARCHAR(20) NOT NULL DEFAULT 'completed'
value DECIMAL(10,2) NULL
note VARCHAR(255) NOT NULL DEFAULT ''
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
PRIMARY KEY (id)
UNIQUE KEY user_habit_day (user_habit_id, checkin_date)
KEY user_date (user_id, checkin_date)
KEY user_habit_status_date (user_habit_id, status, checkin_date)
```

### Notes

- `user_id` is stored redundantly for faster user-scoped reporting queries.
- `status` values for MVP:
  - `completed`
  - `skipped`
- Missing data is not stored as `missed`; it is inferred when a habit was expected but no completed check-in exists.
- `value` is reserved for future quantitative habits such as minutes, liters, or steps.

## Day Mask Convention

`target_days_mask` uses a 7-bit weekday mask:

- Monday = `1`
- Tuesday = `2`
- Wednesday = `4`
- Thursday = `8`
- Friday = `16`
- Saturday = `32`
- Sunday = `64`

Examples:

- Every day: `127`
- Monday/Wednesday/Friday: `21`
- Weekdays only: `31`

For MVP, all created habits should default to `127`.

## Access Rules

- Admin manages only `habit_tracker_templates`.
- User can create custom rows only in `habit_tracker_user_habits` where `user_id = current user`.
- User can add shared templates to their dashboard, which creates or reactivates a row in `habit_tracker_user_habits`.
- User can check or uncheck only their own `user_habits`.
- Progress queries must always be filtered by the current user.

## Read Models by Page

### Habits Page

Should display:

- active shared templates not yet added by the current user
- current user's active custom habits
- current user's active template-based habits, marked as already added

### Dashboard Page

Should display:

- current user's active `user_habits`
- today's completion state per habit
- quick actions for check/uncheck

### Progress Page

Should calculate from `habit_tracker_checkins`:

- current streak
- longest streak
- current week completion
- current month completion
- completion rate by habit
- weekly and monthly trend data

## Analytics Rules

### Current Streak

For MVP, streak is daily:

1. Start from today in the active site timezone.
2. Walk backward by day.
3. Count consecutive expected days with `status = completed`.
4. Stop on the first expected day without a completed check-in.

### Weekly Progress

For the current week:

- `planned` = number of expected habit-days across all active user habits
- `completed` = number of those expected habit-days with a completed check-in
- `weekly_progress_percent = completed / planned`

### Monthly Progress

For the current month:

- `planned` = number of expected habit-days across all active user habits
- `completed` = number of those expected habit-days with a completed check-in
- `monthly_progress_percent = completed / planned`

## MVP Simplification

The first implementation should keep the behavior narrow:

- all habits are daily
- `target_count = 1`
- `target_days_mask = 127`
- no reminders
- no chart library dependency yet
- no drag-and-drop ordering yet

This is enough to support:

- admin-created shared habits
- user-created custom habits
- add-to-dashboard flow
- daily check-ins
- dashboard cards
- weekly and monthly progress
- streak calculations

## Implementation Order

1. Create activation and migration code for the three tables.
2. Build repositories for templates, user habits, and check-ins.
3. Add admin CRUD for shared templates.
4. Add frontend `Habits` shortcode with:
   - template listing
   - add-to-dashboard action
   - custom habit creation
5. Add frontend `Dashboard` shortcode with daily check-in actions.
6. Add frontend `Progress` shortcode with streak and weekly/monthly summaries.
