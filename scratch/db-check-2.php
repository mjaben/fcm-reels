<?php
require_once('../../../../wp-load.php');
global $wpdb;

$archive_tbl = $wpdb->prefix . 'fcom_media_archive';
$posts_tbl   = $wpdb->prefix . 'fcom_posts';

$sql = "
    SELECT count(*)
    FROM {$posts_tbl} p
    INNER JOIN {$archive_tbl} ma ON ma.feed_id = p.id
    WHERE ma.is_active = 1
      AND (ma.media_type = 'fluent_player' OR ma.media_type LIKE 'video/%' OR ma.media_type = 'video')
      AND p.status = 'published'
";

$count = $wpdb->get_var($sql);

echo "Active, Published Videos matching filter: " . $count;
