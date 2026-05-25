<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="habit-tracker-habits">
    <?php echo isset($notice_html) ? (string) $notice_html : ''; ?>

    <div class="app-grid">
        <?php echo isset($stack_html) ? (string) $stack_html : ''; ?>
        <?php echo isset($shared_html) ? (string) $shared_html : ''; ?>
    </div>
</div>
