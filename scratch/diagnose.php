<?php
// Load WordPress
$path = $_SERVER['DOCUMENT_ROOT'];
include_once $path . '/wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
    // Optional: add a password check or remove this for a quick test
}

global $wpdb;
$archive_tbl = $wpdb->prefix . 'fcom_media_archive';
$posts_tbl   = $wpdb->prefix . 'fcom_posts';

echo "<h2>FCM Reels — Deep Database Scan</h2>";

// 1. Check Media Archive Table
$media_count = $wpdb->get_var( "SELECT COUNT(*) FROM $archive_tbl" );
echo "<strong>Total Media in Archive:</strong> $media_count <br>";

if ( $media_count > 0 ) {
    $samples = $wpdb->get_results( "SELECT * FROM $archive_tbl LIMIT 10", ARRAY_A );
    echo "<h3>Last 10 Media Entries:</h3><table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Feed ID</th><th>Type</th><th>Is Active</th><th>URL</th></tr>";
    foreach ( $samples as $s ) {
        echo "<tr>
            <td>{$s['id']}</td>
            <td>{$s['feed_id']}</td>
            <td>{$s['media_type']}</td>
            <td>{$s['is_active']}</td>
            <td>" . substr($s['media_url'], 0, 50) . "...</td>
        </tr>";
    }
    echo "</table>";
}

// 2. Check Posts Table
$post_count = $wpdb->get_var( "SELECT COUNT(*) FROM $posts_tbl" );
echo "<br><strong>Total Posts in FluentCommunity:</strong> $post_count <br>";

if ( $post_count > 0 ) {
    $p_samples = $wpdb->get_results( "SELECT id, title, status, type FROM $posts_tbl LIMIT 10", ARRAY_A );
    echo "<h3>Last 10 Post Entries:</h3><table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Type</th></tr>";
    foreach ( $p_samples as $p ) {
        echo "<tr>
            <td>{$p['id']}</td>
            <td>{$p['title']}</td>
            <td>{$p['status']}</td>
            <td>{$p['type']}</td>
        </tr>";
    }
    echo "</table>";
}

// 3. Test the JOIN
$join_count = $wpdb->get_var( "SELECT COUNT(*) FROM $archive_tbl ma INNER JOIN $posts_tbl p ON p.id = ma.feed_id" );
echo "<br><strong>Successful Joins (Media matched to Post):</strong> $join_count <br>";
