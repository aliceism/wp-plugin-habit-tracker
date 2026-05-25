<?php
if (! defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$month_rows = isset($month_rows) && is_array($month_rows) ? $month_rows : [];
$month_dates = isset($context['month_dates']) && is_array($context['month_dates']) ? $context['month_dates'] : [];
$month_date_count = count($month_dates);
$checkins_header_label = esc_html__('Check-ins', 'habit-tracker');
$today = isset($context['today']) ? (string) $context['today'] : '';
$redirect = isset($context['redirect_url']) ? (string) $context['redirect_url'] : home_url('/');
$admin_post_url = isset($admin_post_url) && is_string($admin_post_url) && $admin_post_url !== ''
    ? $admin_post_url
    : admin_url('admin-post.php');
$toggle_action = isset($toggle_action) && is_string($toggle_action) && $toggle_action !== ''
    ? $toggle_action
    : 'habit_tracker_toggle_checkin';
$side_html = isset($side_html) ? (string) $side_html : '';
?>
<div class="habit-tracker-dashboard-panels habit-tracker-month-layout">
    <article class="card app-card habit-tracker-dashboard-panel habit-tracker-month-table-panel">
        <div class="habit-tracker-dashboard-panel__header">
            <p class="app-card__eyebrow"><?php esc_html_e('Check-ins', 'habit-tracker'); ?></p>
            <h3><?php esc_html_e('Monthly Habit Grid', 'habit-tracker'); ?></h3>
        </div>

        <?php if ($month_rows === []) : ?>
            <p class="habit-tracker-empty-state"><?php esc_html_e('No active habits in your dashboard stack yet.', 'habit-tracker'); ?></p>
        <?php else : ?>
            <div class="habit-tracker-month-table-wrap">
                <table class="habit-tracker-month-table">
                    <thead>
                        <tr class="habit-tracker-month-table__weeks">
                            <th class="habit-tracker-month-table__habit-col"><?php esc_html_e('Habits', 'habit-tracker'); ?></th>
                            <th colspan="<?php echo esc_attr((string) $month_date_count); ?>">
                                <?php echo esc_html($checkins_header_label); ?>
                            </th>
                            <th class="habit-tracker-month-table__progress-col"><?php esc_html_e('Progress', 'habit-tracker'); ?></th>
                        </tr>
                        <tr class="habit-tracker-month-table__days">
                            <th aria-hidden="true"></th>
                            <?php foreach ($month_dates as $month_date) : ?>
                                <th title="<?php echo esc_attr((string) $month_date); ?>">
                                    <span class="habit-tracker-month-dayhead">
                                        <span class="habit-tracker-month-dayname"><?php echo esc_html(wp_date('D', strtotime((string) $month_date))); ?></span>
                                        <strong class="habit-tracker-month-daynum"><?php echo esc_html(wp_date('j', strtotime((string) $month_date))); ?></strong>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                            <th aria-hidden="true"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($month_rows as $row) : ?>
                            <?php
                            $row_id = isset($row['id']) ? (int) $row['id'] : 0;
                            $row_name = (string) ($row['name'] ?? '');
                            $row_category = sanitize_key((string) ($row['category'] ?? 'life'));
                            $row_progress_percent = (int) ($row['progress_percent'] ?? 0);
                            $row_completed = (int) ($row['completed'] ?? 0);
                            $row_target_total = (int) ($row['target_total'] ?? 0);
                            $row_day_map = isset($row['day_map']) && is_array($row['day_map']) ? $row['day_map'] : [];
                            ?>
                            <tr
                                class="habit-tracker-month-row habit-tracker-month-row--<?php echo esc_attr($row_category); ?>"
                                data-user-habit-id="<?php echo esc_attr((string) $row_id); ?>"
                            >
                                <th scope="row" class="habit-tracker-month-habit"><?php echo esc_html($row_name); ?></th>

                                <?php foreach ($month_dates as $month_date) : ?>
                                    <?php
                                    $month_date = (string) $month_date;
                                    $is_filled = isset($row_day_map[$month_date]);
                                    $is_today = $month_date === $today;
                                    $is_future = $today !== '' && $month_date > $today;
                                    ?>
                                    <td class="habit-tracker-month-day<?php echo $is_today ? ' is-today' : ''; ?>">
                                        <?php if ($is_today) : ?>
                                            <form class="habit-tracker-month-toggle-form" method="post" action="<?php echo esc_url($admin_post_url); ?>">
                                                <input type="hidden" name="action" value="<?php echo esc_attr($toggle_action); ?>">
                                                <input type="hidden" name="user_habit_id" value="<?php echo esc_attr((string) $row_id); ?>">
                                                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                                                <?php wp_nonce_field($toggle_action); ?>
                                                <button
                                                    type="submit"
                                                    class="habit-tracker-month-cell habit-tracker-month-cell--action<?php echo $is_filled ? ' is-filled' : ''; ?> is-today"
                                                    aria-label="<?php echo esc_attr(sprintf(__('Toggle today check for %s', 'habit-tracker'), $row_name)); ?>"
                                                    title="<?php esc_attr_e('Toggle today check', 'habit-tracker'); ?>"
                                                >
                                                    <?php echo $is_filled ? '&#10003;' : ''; ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span class="habit-tracker-month-cell<?php echo $is_filled ? ' is-filled' : ''; ?><?php echo $is_future ? ' is-future' : ''; ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <td class="habit-tracker-month-progress">
                                    <span class="habit-tracker-progress-bar">
                                        <span style="width: <?php echo esc_attr((string) $row_progress_percent); ?>%;"></span>
                                    </span>
                                    <span class="habit-tracker-month-progress-text">
                                        <?php
                                        printf(
                                            esc_html__('%1$d/%2$d · %3$d%%', 'habit-tracker'),
                                            $row_completed,
                                            $row_target_total,
                                            $row_progress_percent
                                        );
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <div class="habit-tracker-month-side">
        <?php echo $side_html; ?>
    </div>
</div>
