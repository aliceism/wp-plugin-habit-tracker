<?php
if (! defined('ABSPATH')) {
    exit;
}

$categories = isset($categories) && is_array($categories) ? $categories : [];
$grouped_items = isset($grouped_items) && is_array($grouped_items) ? $grouped_items : [];
$category_counts = isset($category_counts) && is_array($category_counts) ? $category_counts : [];
$target_options = isset($target_options) && is_array($target_options) ? $target_options : [];
$redirect = isset($redirect_url) && is_string($redirect_url) && $redirect_url !== ''
    ? $redirect_url
    : home_url('/');
$admin_post_url = isset($admin_post_url) && is_string($admin_post_url) && $admin_post_url !== ''
    ? $admin_post_url
    : admin_url('admin-post.php');
$add_shared_action = isset($add_shared_action) && is_string($add_shared_action) && $add_shared_action !== ''
    ? $add_shared_action
    : 'habit_tracker_add_shared_habit';
$add_custom_action = isset($add_custom_action) && is_string($add_custom_action) && $add_custom_action !== ''
    ? $add_custom_action
    : 'habit_tracker_add_custom_habit';
$custom_default_category = isset($custom_default_category)
    ? sanitize_key((string) $custom_default_category)
    : 'mind';
$custom_default_target_per_week = isset($custom_default_target_per_week)
    ? (int) $custom_default_target_per_week
    : 7;
$custom_modal_id = 'habit-tracker-modal-custom';
?>
<article class="card app-card habit-tracker-block habit-tracker-block--shared">
    <div class="habit-tracker-block__header">
        <p class="app-card__eyebrow"><?php esc_html_e('Habits', 'habit-tracker'); ?></p>
        <h3><?php esc_html_e('Pick Habits For Your Lab', 'habit-tracker'); ?></h3>
    </div>

    <div class="habit-tracker-category-grid">
        <?php foreach ($categories as $category_key => $category_label) : ?>
            <?php
            $category_key = sanitize_key((string) $category_key);
            $modal_id = 'habit-tracker-modal-' . $category_key;
            $count = isset($category_counts[$category_key]) ? (int) $category_counts[$category_key] : 0;
            ?>
            <button
                type="button"
                class="habit-tracker-category-card habit-tracker-category-card--<?php echo esc_attr($category_key); ?>"
                data-ht-open-modal="<?php echo esc_attr($modal_id); ?>"
            >
                <span class="habit-tracker-category-card__label"><?php echo esc_html((string) $category_label); ?></span>
                <span class="habit-tracker-category-card__meta">
                    <?php
                    printf(
                        esc_html(_n('%d habit', '%d habits', $count, 'habit-tracker')),
                        $count
                    );
                    ?>
                </span>
                <span class="habit-tracker-category-card__cta"><?php esc_html_e('Open list', 'habit-tracker'); ?></span>
            </button>
        <?php endforeach; ?>

        <button type="button" class="habit-tracker-category-card habit-tracker-category-card--custom" data-ht-open-modal="<?php echo esc_attr($custom_modal_id); ?>">
            <span class="habit-tracker-category-card__label"><?php esc_html_e('Custom Habit', 'habit-tracker'); ?></span>
            <span class="habit-tracker-category-card__cta"><?php esc_html_e('Create now', 'habit-tracker'); ?></span>
        </button>
    </div>

    <?php foreach ($categories as $category_key => $category_label) : ?>
        <?php
        $category_key = sanitize_key((string) $category_key);
        $modal_id = 'habit-tracker-modal-' . $category_key;
        $modal_title_id = $modal_id . '-title';
        $items = isset($grouped_items[$category_key]) && is_array($grouped_items[$category_key])
            ? $grouped_items[$category_key]
            : [];
        ?>
        <div class="habit-tracker-modal" data-ht-modal="<?php echo esc_attr($modal_id); ?>" hidden>
            <div class="habit-tracker-modal__backdrop" data-ht-close-modal></div>
            <div
                class="habit-tracker-modal__panel habit-tracker-modal__panel--<?php echo esc_attr($category_key); ?>"
                role="dialog"
                aria-modal="true"
                aria-labelledby="<?php echo esc_attr($modal_title_id); ?>"
            >
                <div class="habit-tracker-modal__header">
                    <p class="app-card__eyebrow"><?php echo esc_html((string) $category_label); ?></p>
                    <h4 id="<?php echo esc_attr($modal_title_id); ?>"><?php echo esc_html(sprintf(__('%s Habits', 'habit-tracker'), (string) $category_label)); ?></h4>
                    <button type="button" class="habit-tracker-modal__close" data-ht-close-modal aria-label="<?php esc_attr_e('Close', 'habit-tracker'); ?>">
                        &times;
                    </button>
                </div>

                <?php if ($items === []) : ?>
                    <p><?php esc_html_e('No habits in this category yet.', 'habit-tracker'); ?></p>
                <?php else : ?>
                    <ul class="habit-tracker-modal-list">
                        <?php foreach ($items as $item) : ?>
                            <?php
                            $shared_habit_id = isset($item['id']) ? (int) $item['id'] : 0;
                            if ($shared_habit_id <= 0) {
                                continue;
                            }

                            $is_added = ! empty($item['is_added']);
                            $habit_name = (string) ($item['name'] ?? '');
                            $habit_description = (string) ($item['description'] ?? '');
                            $target_select_id = 'habit-tracker-weekly-target-' . $shared_habit_id;
                            $default_target_per_week = isset($item['default_target_per_week'])
                                ? (int) $item['default_target_per_week']
                                : 7;
                            ?>
                            <li class="habit-tracker-modal-list__item">
                                <div class="habit-tracker-modal-list__content">
                                    <h4><?php echo esc_html($habit_name); ?></h4>
                                    <?php if ($habit_description !== '') : ?>
                                        <p><?php echo esc_html($habit_description); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="habit-tracker-modal-list__action">
                                    <?php if ($is_added) : ?>
                                        <span class="habit-tracker-pill habit-tracker-pill--active">
                                            <?php esc_html_e('Already Added', 'habit-tracker'); ?>
                                        </span>
                                    <?php else : ?>
                                        <form class="habit-tracker-inline-form habit-tracker-inline-form--add" method="post" action="<?php echo esc_url($admin_post_url); ?>">
                                            <input type="hidden" name="action" value="<?php echo esc_attr($add_shared_action); ?>">
                                            <input type="hidden" name="habit_id" value="<?php echo esc_attr((string) $shared_habit_id); ?>">
                                            <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                                            <?php wp_nonce_field($add_shared_action); ?>
                                            <label class="screen-reader-text" for="<?php echo esc_attr($target_select_id); ?>">
                                                <?php esc_html_e('Times per week', 'habit-tracker'); ?>
                                            </label>
                                            <select
                                                id="<?php echo esc_attr($target_select_id); ?>"
                                                name="target_per_week"
                                                class="habit-tracker-add-frequency"
                                                aria-label="<?php esc_attr_e('Times per week', 'habit-tracker'); ?>"
                                            >
                                                <?php foreach ($target_options as $target_option) : ?>
                                                    <?php
                                                    $target_value = isset($target_option['value']) ? (int) $target_option['value'] : 7;
                                                    $target_label = (string) ($target_option['label'] ?? '');
                                                    ?>
                                                    <option value="<?php echo esc_attr((string) $target_value); ?>" <?php selected($target_value, $default_target_per_week); ?>>
                                                        <?php echo esc_html($target_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button
                                                type="submit"
                                                class="btn btn-ghost habit-tracker-add-btn"
                                                aria-label="<?php esc_attr_e('Add to Dashboard', 'habit-tracker'); ?>"
                                                title="<?php esc_attr_e('Add to Dashboard', 'habit-tracker'); ?>"
                                            >
                                                +
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php $custom_modal_title_id = 'habit-tracker-modal-custom-title'; ?>
    <div class="habit-tracker-modal" data-ht-modal="<?php echo esc_attr($custom_modal_id); ?>" hidden>
        <div class="habit-tracker-modal__backdrop" data-ht-close-modal></div>
        <div
            class="habit-tracker-modal__panel habit-tracker-modal__panel--custom"
            role="dialog"
            aria-modal="true"
            aria-labelledby="<?php echo esc_attr($custom_modal_title_id); ?>"
        >
            <div class="habit-tracker-modal__header">
                <p class="app-card__eyebrow"><?php esc_html_e('Custom Habit', 'habit-tracker'); ?></p>
                <h4 id="<?php echo esc_attr($custom_modal_title_id); ?>"><?php esc_html_e('Create One Just For You', 'habit-tracker'); ?></h4>
                <button type="button" class="habit-tracker-modal__close" data-ht-close-modal aria-label="<?php esc_attr_e('Close', 'habit-tracker'); ?>">
                    &times;
                </button>
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
                    <label for="habit-tracker-custom-target-per-week-modal"><?php esc_html_e('Weekly Goal', 'habit-tracker'); ?></label>
                    <select
                        id="habit-tracker-custom-target-per-week-modal"
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
        </div>
    </div>
</article>
