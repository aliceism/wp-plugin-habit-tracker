<?php
if (! defined('ABSPATH')) {
    exit;
}

$items = isset($items) && is_array($items) ? $items : [];
$redirect = isset($redirect_url) && is_string($redirect_url) && $redirect_url !== ''
    ? $redirect_url
    : home_url('/');
$admin_post_url = isset($admin_post_url) && is_string($admin_post_url) && $admin_post_url !== ''
    ? $admin_post_url
    : admin_url('admin-post.php');
$remove_action = isset($remove_action) && is_string($remove_action) && $remove_action !== ''
    ? $remove_action
    : 'habit_tracker_remove_user_habit';
$update_action = isset($update_action) && is_string($update_action) && $update_action !== ''
    ? $update_action
    : 'habit_tracker_update_custom_habit';
$update_shared_frequency_action = isset($update_shared_frequency_action)
    && is_string($update_shared_frequency_action)
    && $update_shared_frequency_action !== ''
    ? $update_shared_frequency_action
    : 'habit_tracker_update_shared_habit_frequency';
$categories = isset($categories) && is_array($categories) ? $categories : [];
$target_options = isset($target_options) && is_array($target_options) ? $target_options : [];
?>
<article class="card app-card habit-tracker-block habit-tracker-block--stack">
    <div class="habit-tracker-block__header">
        <p class="app-card__eyebrow"><?php esc_html_e('Dashboard Stack', 'habit-tracker'); ?></p>
        <h3><?php esc_html_e('Your Active Habits', 'habit-tracker'); ?></h3>
    </div>

    <?php if ($items === []) : ?>
        <p class="habit-tracker-empty-state"><?php esc_html_e('No habits in your dashboard yet. Add one from the shared list or create a custom habit.', 'habit-tracker'); ?></p>
    <?php else : ?>
        <div class="habit-tracker-stack-panel">
            <ul class="habit-tracker-habits__stack">
                <?php foreach ($items as $item_index => $item) : ?>
                    <?php
                    $stack_item_id = isset($item['id']) ? (int) $item['id'] : 0;
                    $category_class = sanitize_key((string) ($item['category_class'] ?? 'life'));
                    $habit_name = (string) ($item['name'] ?? '');
                    $habit_description = trim((string) ($item['description'] ?? ''));
                    $habit_target_label = (string) ($item['target_label'] ?? __('Everyday', 'habit-tracker'));
                    $stack_modal_id = $stack_item_id > 0
                        ? 'habit-tracker-stack-modal-' . $stack_item_id
                        : 'habit-tracker-stack-modal-' . ($item_index + 1);
                    ?>
                    <li class="habit-tracker-stack-item habit-tracker-stack-item--<?php echo esc_attr($category_class); ?>">
                        <button
                            type="button"
                            class="habit-tracker-stack-item__open"
                            data-ht-open-modal="<?php echo esc_attr($stack_modal_id); ?>"
                            aria-label="<?php echo esc_attr(sprintf(__('Open details for %s', 'habit-tracker'), $habit_name)); ?>"
                        >
                            <span class="habit-tracker-stack-item__name"><?php echo esc_html($habit_name); ?></span>
                        </button>

                        <?php if ($stack_item_id > 0) : ?>
                            <div class="habit-tracker-stack-item__controls">
                                <form
                                    class="habit-tracker-inline-form"
                                    method="post"
                                    action="<?php echo esc_url($admin_post_url); ?>"
                                    data-ht-confirm="<?php esc_attr_e('Remove this habit from your dashboard stack?', 'habit-tracker'); ?>"
                                >
                                    <input type="hidden" name="action" value="<?php echo esc_attr($remove_action); ?>">
                                    <input type="hidden" name="user_habit_id" value="<?php echo esc_attr((string) $stack_item_id); ?>">
                                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                                    <?php wp_nonce_field($remove_action); ?>
                                    <button
                                        type="submit"
                                        class="habit-tracker-stack-item__remove"
                                        aria-label="<?php esc_attr_e('Remove from dashboard stack', 'habit-tracker'); ?>"
                                        title="<?php esc_attr_e('Remove from dashboard stack', 'habit-tracker'); ?>"
                                    >
                                        <span class="habit-tracker-stack-item__remove-glyph" aria-hidden="true"></span>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php foreach ($items as $item_index => $item) : ?>
            <?php
            $stack_item_id = isset($item['id']) ? (int) $item['id'] : 0;
            $category_class = sanitize_key((string) ($item['category_class'] ?? 'life'));
            $category_key = sanitize_key((string) ($item['category_key'] ?? $category_class));
            $is_custom = ! empty($item['is_custom']);
            $habit_name = (string) ($item['name'] ?? '');
            $habit_description = trim((string) ($item['description'] ?? ''));
            $habit_target_label = (string) ($item['target_label'] ?? __('Everyday', 'habit-tracker'));
            $habit_target_per_week = isset($item['target_per_week']) ? (int) $item['target_per_week'] : 7;
            $stack_modal_id = $stack_item_id > 0
                ? 'habit-tracker-stack-modal-' . $stack_item_id
                : 'habit-tracker-stack-modal-' . ($item_index + 1);
            $stack_modal_title_id = $stack_modal_id . '-title';
            ?>
            <div class="habit-tracker-modal" data-ht-modal="<?php echo esc_attr($stack_modal_id); ?>" hidden>
                <div class="habit-tracker-modal__backdrop" data-ht-close-modal></div>
                <div
                    class="habit-tracker-modal__panel habit-tracker-modal__panel--<?php echo esc_attr($category_class); ?>"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="<?php echo esc_attr($stack_modal_title_id); ?>"
                >
                    <div class="habit-tracker-modal__header">
                        <p class="app-card__eyebrow"><?php esc_html_e('Habit Details', 'habit-tracker'); ?></p>
                        <div class="habit-tracker-stack-modal__title-row">
                            <h4 id="<?php echo esc_attr($stack_modal_title_id); ?>"><?php echo esc_html($habit_name); ?></h4>
                            <div class="habit-tracker-stack-modal__meta">
                                <span class="habit-tracker-stack-modal__meta-label"><?php esc_html_e('Weekly Goal', 'habit-tracker'); ?></span>
                                <strong><?php echo esc_html($habit_target_label); ?></strong>
                            </div>
                        </div>
                        <button type="button" class="habit-tracker-modal__close" data-ht-close-modal aria-label="<?php esc_attr_e('Close', 'habit-tracker'); ?>">
                            &times;
                        </button>
                    </div>

                    <div class="habit-tracker-stack-modal__description">
                        <p>
                            <?php
                            echo esc_html(
                                $habit_description !== ''
                                    ? $habit_description
                                    : __('No description added for this habit yet.', 'habit-tracker')
                            );
                            ?>
                        </p>
                    </div>

                    <?php if ($is_custom && $stack_item_id > 0) : ?>
                        <form
                            class="habit-tracker-form habit-tracker-stack-modal__form"
                            method="post"
                            action="<?php echo esc_url($admin_post_url); ?>"
                        >
                            <input type="hidden" name="action" value="<?php echo esc_attr($update_action); ?>">
                            <input type="hidden" name="user_habit_id" value="<?php echo esc_attr((string) $stack_item_id); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                            <?php wp_nonce_field($update_action); ?>

                            <div class="habit-tracker-stack-modal__form-grid">
                                <div class="habit-tracker-field">
                                    <label for="<?php echo esc_attr($stack_modal_id . '-name'); ?>"><?php esc_html_e('Name', 'habit-tracker'); ?></label>
                                    <input
                                        id="<?php echo esc_attr($stack_modal_id . '-name'); ?>"
                                        type="text"
                                        name="name"
                                        maxlength="191"
                                        value="<?php echo esc_attr($habit_name); ?>"
                                        required
                                    >
                                </div>

                                <div class="habit-tracker-field">
                                    <label for="<?php echo esc_attr($stack_modal_id . '-category'); ?>"><?php esc_html_e('Category', 'habit-tracker'); ?></label>
                                    <select
                                        id="<?php echo esc_attr($stack_modal_id . '-category'); ?>"
                                        name="category"
                                        class="habit-tracker-field-select"
                                        required
                                    >
                                        <?php foreach ($categories as $value => $label) : ?>
                                            <?php $normalized_value = sanitize_key((string) $value); ?>
                                            <option value="<?php echo esc_attr($normalized_value); ?>" <?php selected($normalized_value, $category_key); ?>>
                                                <?php echo esc_html((string) $label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="habit-tracker-field">
                                <label for="<?php echo esc_attr($stack_modal_id . '-description'); ?>"><?php esc_html_e('Description', 'habit-tracker'); ?></label>
                                <textarea
                                    id="<?php echo esc_attr($stack_modal_id . '-description'); ?>"
                                    name="description"
                                    rows="3"
                                ><?php echo esc_textarea($habit_description); ?></textarea>
                            </div>

                            <div class="habit-tracker-stack-modal__form-actions">
                                <div class="habit-tracker-field">
                                    <label for="<?php echo esc_attr($stack_modal_id . '-target'); ?>"><?php esc_html_e('Weekly Goal', 'habit-tracker'); ?></label>
                                    <select
                                        id="<?php echo esc_attr($stack_modal_id . '-target'); ?>"
                                        name="target_per_week"
                                        class="habit-tracker-field-select"
                                    >
                                        <?php foreach ($target_options as $target_option) : ?>
                                            <?php
                                            $target_value = isset($target_option['value']) ? (int) $target_option['value'] : 7;
                                            $target_label = isset($target_option['label']) ? (string) $target_option['label'] : '';
                                            ?>
                                            <option value="<?php echo esc_attr((string) $target_value); ?>" <?php selected($target_value, $habit_target_per_week); ?>>
                                                <?php echo esc_html($target_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary habit-tracker-stack-modal__save">
                                    <?php esc_html_e('Save Changes', 'habit-tracker'); ?>
                                </button>
                            </div>
                        </form>
                    <?php elseif ($stack_item_id > 0) : ?>
                        <form
                            class="habit-tracker-form habit-tracker-stack-modal__form habit-tracker-stack-modal__form--shared"
                            method="post"
                            action="<?php echo esc_url($admin_post_url); ?>"
                        >
                            <input type="hidden" name="action" value="<?php echo esc_attr($update_shared_frequency_action); ?>">
                            <input type="hidden" name="user_habit_id" value="<?php echo esc_attr((string) $stack_item_id); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                            <?php wp_nonce_field($update_shared_frequency_action); ?>

                            <div class="habit-tracker-stack-modal__form-actions">
                                <div class="habit-tracker-field">
                                    <label for="<?php echo esc_attr($stack_modal_id . '-target'); ?>"><?php esc_html_e('Weekly Goal', 'habit-tracker'); ?></label>
                                    <select
                                        id="<?php echo esc_attr($stack_modal_id . '-target'); ?>"
                                        name="target_per_week"
                                        class="habit-tracker-field-select"
                                    >
                                        <?php foreach ($target_options as $target_option) : ?>
                                            <?php
                                            $target_value = isset($target_option['value']) ? (int) $target_option['value'] : 7;
                                            $target_label = isset($target_option['label']) ? (string) $target_option['label'] : '';
                                            ?>
                                            <option value="<?php echo esc_attr((string) $target_value); ?>" <?php selected($target_value, $habit_target_per_week); ?>>
                                                <?php echo esc_html($target_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary habit-tracker-stack-modal__save">
                                    <?php esc_html_e('Save Changes', 'habit-tracker'); ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</article>
