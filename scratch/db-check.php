<?php
/**
 * DB Check Script for Orbit Analytics
 * Path: wp-content/plugins/fcm-orbits/scratch/db-check.php
 */

define( 'WP_USE_THEMES', false );
require_once( '../../../../wp-load.php' );

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied.' );
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'orbit_video_events',
    $wpdb->prefix . 'orbit_video_metrics',
    $wpdb->prefix . 'orbit_feed_sessions'
];

echo "<h2>Orbit Database Check</h2>";

foreach ( $tables as $table ) {
    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    
    if ( $exists ) {
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        echo "<p style='color: green;'>✅ Table <strong>$table</strong> exists. (Rows: $count)</p>";
        
        // Show columns for metrics table to check for the new ones
        if ( strpos($table, 'metrics') !== false ) {
            $columns = $wpdb->get_results( "DESCRIBE $table" );
            echo "<ul>";
            foreach($columns as $col) {
                echo "<li>{$col->Field} ({$col->Type})</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: red;'>❌ Table <strong>$table</strong> is MISSING!</p>";
    }
}

echo "<hr>";
echo "<h3>Recent Events</h3>";
$recent = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}orbit_video_events ORDER BY id DESC LIMIT 5" );
if ( $recent ) {
    echo "<pre>" . print_r($recent, true) . "</pre>";
} else {
    echo "No events found in raw logs.";
}

echo "<br><p><strong>Note:</strong> If tables are missing, go to Orbit Settings -> Analytics and click 'Update Schema'.</p>";
