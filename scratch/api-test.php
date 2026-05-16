<?php
/**
 * REST API Status Check
 * Path: wp-content/plugins/fcm-orbits/scratch/api-test.php
 */

define( 'WP_USE_THEMES', false );
require_once( '../../../../wp-load.php' );

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied.' );
}

$api_url = rest_url( 'fcm-reels/v1/track' );

echo "<h2>REST API Test</h2>";
echo "Testing endpoint: <code>$api_url</code><br><br>";

$test_data = [
    'video_id'      => 1,
    'event_type'    => 'test_ping',
    'watch_seconds' => 0,
    'session_id'    => 'test_session_' . time(),
    'device'        => 'server_test'
];

$response = wp_remote_post( $api_url, [
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'body' => wp_json_encode( $test_data ),
    'timeout' => 15
] );

if ( is_wp_error( $response ) ) {
    echo "<p style='color: red;'>❌ Request Failed: " . $response->get_error_message() . "</p>";
} else {
    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    
    echo "<p>Response Code: <strong>$code</strong></p>";
    echo "Response Body: <pre>" . esc_html($body) . "</pre>";
    
    if ( $code === 200 ) {
        echo "<p style='color: green;'>✅ REST API is reachable and accepting POST requests!</p>";
    } else {
        echo "<p style='color: red;'>❌ API returned an error. Check if 'fcm-reels/v1' namespace is registered.</p>";
    }
}

echo "<hr>";
echo "<h3>Server Environment</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "WP Version: " . get_bloginfo('version') . "<br>";
echo "REST Base: " . get_rest_url() . "<br>";
