<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="habit-tracker-dashboard">
    <?php echo isset($notice_html) ? (string) $notice_html : ''; ?>
    <?php echo isset($metrics_html) ? (string) $metrics_html : ''; ?>
    <?php echo isset($panels_html) ? (string) $panels_html : ''; ?>
</div>
