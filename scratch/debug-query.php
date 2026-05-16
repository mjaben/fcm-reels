<?php
require_once( '../../../wp-load.php' );

global $wpdb;
$archive_tbl = $wpdb->prefix . 'fcom_media_archive';
$posts_tbl   = $wpdb->prefix . 'fcom_posts';

echo "--- FCM Reels Diagnostic ---\n";
echo "Table Prefix: " . $wpdb->prefix . "\n";

$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$archive_tbl} WHERE is_active = 1" );
echo "Active Media Count: " . ($count ?? 0) . "\n";

$video_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$archive_tbl} WHERE is_active = 1 AND (media_type = 'fluent_player' OR media_type LIKE 'video/%')" );
echo "Video-specific Count: " . ($video_count ?? 0) . "\n";

$published_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$archive_tbl} ma INNER JOIN {$posts_tbl} p ON p.id = ma.feed_id WHERE p.status = 'published'" );
echo "Published Post Match Count: " . ($published_count ?? 0) . "\n";
