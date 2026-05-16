<?php
require_once( '../../../wp-load.php' );

global $wpdb;

echo "--- DATA SCANNER ---\n";

$tables = [
    'fcom_posts',
    'fcom_media_archive',
    'fcom_spaces'
];

foreach ( $tables as $t ) {
    $full_table = $wpdb->prefix . $t;
    echo "Table: $full_table\n";
    $rows = $wpdb->get_results( "SELECT * FROM $full_table LIMIT 3", ARRAY_A );
    if ( empty( $rows ) ) {
        echo "   (Empty)\n";
    } else {
        foreach ( $rows as $row ) {
            echo "   ID: " . ($row['id'] ?? 'N/A') . " | Title/Key: " . ($row['title'] ?? $row['media_key'] ?? 'N/A') . " | Status/Type: " . ($row['status'] ?? $row['media_type'] ?? 'N/A') . "\n";
        }
    }
}
