<?php
require_once('../../../../wp-load.php');

if (!is_user_logged_in() && !defined('WP_DEBUG')) {
    die('Unauthorized');
}

global $wpdb;
$table = $wpdb->prefix . 'fcom_posts';

// Check if column exists first
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'views_count'");

if (empty($column_exists)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN views_count INT DEFAULT 0 AFTER reactions_count");
    echo "<h1>Database Upgraded! ✅</h1>";
    echo "<p>Added 'views_count' column to $table.</p>";
} else {
    echo "<h1>Already Upgraded! ✅</h1>";
    echo "<p>'views_count' column already exists in $table.</p>";
}
