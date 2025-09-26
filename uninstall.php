<?php
/**
 * Psych Club Form Builder - Uninstall
 */

if (!defined('WP_UNINSTALL_PLUGIN') || !defined('PCFB_PATH')) {
    exit;
}

// حذف گزینه‌ها
$options = [
    'pcfb_version',
    'pcfb_enable_anti_spam',
    'pcfb_submission_limit',
    'pcfb_time_frame',
    'pcfb_enable_email_notifications',
    'pcfb_admin_email'
];

foreach ($options as $option) {
    delete_option($option);
}

// حذف جداول دیتابیس (اختیاری)
global $wpdb;

$tables = [
    $wpdb->prefix . 'pcfb_forms',
    $wpdb->prefix . 'pcfb_submissions'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// پاکسازی transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient_pcfb%'");