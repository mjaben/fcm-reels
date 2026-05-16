<?php
/**
 * Temp Script: Sync Views based on Likes (150%)
 * Path: wp-content/plugins/fcm-orbits/scratch/sync-views.php
 * 
 * Usage: Run once to bootstrap views count based on engagement.
 */

// Load WordPress
define( 'WP_USE_THEMES', false );
require_once( '../../../../wp-load.php' );

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied.' );
}

global $wpdb;
$posts_tbl = $wpdb->prefix . 'fcom_posts';
$metrics_tbl = $wpdb->prefix . 'orbit_video_metrics';

// 1. Get all published videos
$videos = $wpdb->get_results( "SELECT id, reactions_count FROM $posts_tbl WHERE status = 'published'" );

echo "<h1>Syncing Views (150% of Likes)</h1>";
echo "Found " . count($videos) . " videos.<br><br>";

$count = 0;
foreach ( $videos as $v ) {
    $likes = (int) $v->reactions_count;
    $target_views = ceil( $likes * 1.5 );

    // Update main posts table
    $wpdb->update( $posts_tbl, [ 'views_count' => $target_views ], [ 'id' => $v->id ] );

    // Update metrics table if it exists
    $wpdb->update( $metrics_tbl, [ 'total_views' => $target_views ], [ 'video_id' => $v->id ] );

    echo "Video #{$v->id}: Likes: $likes -> Views: $target_views<br>";
    $count++;
}

echo "<br><strong>Done! Processed $count videos.</strong>";
echo "<br><p style='color:red'>REMINDER: Delete this file after use.</p>";
