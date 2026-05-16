<?php
// Load WordPress
$path = $_SERVER['DOCUMENT_ROOT'];
include_once $path . '/wp-load.php';

global $wpdb;
$table = $wpdb->prefix . 'options';

echo "--- CACHE PURGE ---\n";

// Delete both the transient and its timeout entry
$wpdb->query( "DELETE FROM $table WHERE option_name LIKE '_transient_fcm_reels_pool_%'" );
$wpdb->query( "DELETE FROM $table WHERE option_name LIKE '_transient_timeout_fcm_reels_pool_%'" );

echo "Discovery Pool Cache Cleared! ✅\n";
