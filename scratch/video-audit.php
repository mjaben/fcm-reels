<?php
require_once('../../../../wp-load.php');

if (!is_user_logged_in() && !current_user_can('manage_options')) {
    die('Unauthorized');
}

global $wpdb;
$archive_tbl = $wpdb->prefix . 'fcom_media_archive';
$posts_tbl   = $wpdb->prefix . 'fcom_posts';

echo "<h1>FCM Orbits Live Auditor</h1>";

// 1. Total count in archive
$total_media = $wpdb->get_var("SELECT COUNT(*) FROM $archive_tbl");
echo "<p>Total entries in fcom_media_archive: <strong>$total_media</strong></p>";

// 2. Breakdown by type
$types = $wpdb->get_results("SELECT media_type, COUNT(*) as count FROM $archive_tbl GROUP BY media_type");
echo "<h2>Media Types found:</h2><ul>";
foreach ($types as $t) {
    echo "<li>{$t->media_type}: {$t->count}</li>";
}
echo "</ul>";

// 3. Check for published video posts
$published_videos = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM $posts_tbl p
    INNER JOIN $archive_tbl ma ON ma.feed_id = p.id
    WHERE p.status = 'published' AND ma.is_active = 1
");
echo "<h2>Matching Videos:</h2>";
echo "<p>Videos that pass our 'Published & Active' filters: <strong>$published_videos</strong></p>";

if ($published_videos == 0) {
    echo "<p style='color:red;'><strong>⚠️ This is why it's empty! Our filters found 0 valid videos.</strong></p>";
} else {
    echo "<p style='color:green;'><strong>✅ Data found! If it's still empty, it might be a cache issue.</strong></p>";
}
