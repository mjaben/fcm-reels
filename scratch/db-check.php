<?php
require_once('../../../../wp-load.php');

// Simple auth check for dev environment
if (!is_user_logged_in() && !defined('WP_DEBUG')) {
    die('Unauthorized');
}

global $wpdb;
$posts_table = $wpdb->prefix . 'fcom_posts';
$media_table = $wpdb->prefix . 'fcom_media_archive';

function check_table($table) {
    global $wpdb;
    $columns = $wpdb->get_results("DESCRIBE $table");
    echo "<h2>Table: $table</h2>";
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
    echo "<tr style='background:#eee;'><th>Field</th><th>Type</th></tr>";
    foreach ($columns as $col) {
        $color = (strpos($col->Field, 'view') !== false) ? 'yellow' : 'white';
        echo "<tr style='background:$color;'><td>{$col->Field}</td><td>{$col->Type}</td></tr>";
    }
    echo "</table>";
}

check_table($posts_table);
check_table($media_table);
