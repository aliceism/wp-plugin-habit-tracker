<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="habit-tracker-progress">
    <?php echo isset($metrics_html) ? (string) $metrics_html : ''; ?>
    <?php echo isset($breakdown_html) ? (string) $breakdown_html : ''; ?>
</div>
