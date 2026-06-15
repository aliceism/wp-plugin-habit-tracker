<?php
if (! defined('ABSPATH')) {
    exit;
}

echo isset($notice_html) ? (string) $notice_html : '';
