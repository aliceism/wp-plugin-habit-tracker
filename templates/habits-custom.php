<?php
if (! defined('ABSPATH')) {
    exit;
}

$categories = isset($categories) && is_array($categories) ? $categories : [];
$target_options = isset($target_options) && is_array($target_options) ? $target_options : [];
$redirect = isset($redirect_url) && is_string($redirect_url) && $redirect_url !== ''
    ? $redirect_url
    : home_url('/');
$admin_post_url = isset($admin_post_url) && is_string($admin_post_url) && $admin_post_url !== ''
    ? $admin_post_url
    : admin_url('admin-post.php');
$add_custom_action = isset($add_custom_action) && is_string($add_custom_action) && $add_custom_action !== ''
    ? $add_custom_action
    : 'habit_tracker_add_custom_habit';
$custom_default_category = isset($custom_default_category)
    ? sanitize_key((string) $custom_default_category)
    : 'mind';
$custom_default_target_per_week = isset($custom_default_target_per_week)
    ? (int) $custom_default_target_per_week
    : 7;
?>
<article class="card app-card habit-tracker-block habit-tracker-block--custom">
    <div class="habit-tracker-block__header">
        <p class="app-card__eyebrow"><?php esc_html_e('Custom Habit', 'habit-tracker'); ?></p>
        <h3><?php esc_html_e('Create One Just For You', 'habit-tracker'); ?></h3>
    </div>
    <p class="habit-tracker-block__intro"><?php esc_html_e('Custom habits are private to your account and appear directly in your dashboard.', 'habit-tracker'); ?></p>

    <form class="habit-tracker-form" method="post" action="<?php echo esc_url($admin_post_url); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr($add_custom_action); ?>">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
        <?php wp_nonce_field($add_custom_action); ?>

        <div class="habit-tracker-field">
            <label for="habit-tracker-custom-name"><?php esc_html_e('Name', 'habit-tracker'); ?></label>
            <input id="habit-tracker-custom-name" type="text" name="name" required maxlength="191">
        </div>

        <div class="habit-tracker-field">
            <label for="habit-tracker-custom-category"><?php esc_html_e('Category', 'habit-tracker'); ?></label>
            <select
                id="habit-tracker-custom-category"
                name="category"
                class="habit-tracker-field-select"
                required
            >
                <?php foreach ($categories as $category_key => $category_label) : ?>
                    <?php $category_key = sanitize_key((string) $category_key); ?>
                    <option value="<?php echo esc_attr($category_key); ?>" <?php selected($category_key, $custom_default_category); ?>>
                        <?php echo esc_html((string) $category_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="habit-tracker-field">
            <label for="habit-tracker-custom-description"><?php esc_html_e('Description', 'habit-tracker'); ?></label>
            <textarea id="habit-tracker-custom-description" name="description" rows="3"></textarea>
        </div>

        <div class="habit-tracker-field">
            <label for="habit-tracker-custom-target-per-week"><?php esc_html_e('Weekly Goal', 'habit-tracker'); ?></label>
            <select
                id="habit-tracker-custom-target-per-week"
                name="target_per_week"
                class="habit-tracker-field-select"
            >
                <?php foreach ($target_options as $target_option) : ?>
                    <?php
                    $target_value = isset($target_option['value']) ? (int) $target_option['value'] : 7;
                    $target_label = (string) ($target_option['label'] ?? '');
                    ?>
                    <option value="<?php echo esc_attr((string) $target_value); ?>" <?php selected($target_value, $custom_default_target_per_week); ?>>
                        <?php echo esc_html($target_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">
            <?php esc_html_e('Add Custom Habit', 'habit-tracker'); ?>
        </button>
    </form>
</article>
