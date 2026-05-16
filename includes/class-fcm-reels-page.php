<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FCM_Reels_Page
 *
 * Handles registering the reels page template, enqueueing assets,
 * and injecting the required JS config into the page.
 */
class FCM_Reels_Page {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register() {
        add_filter( 'page_template', [ $this, 'load_reels_template' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_shortcode( 'fcm_reels', [ $this, 'render_shortcode' ] );
    }

    /**
     * Use our custom template for the designated reels page.
     *
     * @param string $template Current template path.
     * @return string
     */
    public function load_reels_template( $template ) {
        $page_id = (int) get_option( 'fcm_reels_page_id', 0 );
        if ( $page_id && is_page( $page_id ) ) {
            $custom = FCM_REELS_DIR . 'templates/reels-page.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }
        return $template;
    }

    /**
     * Enqueue CSS and JS on the reels page or when the shortcode is present.
     *
     * @return void
     */
    public function enqueue_assets() {
        $page_id = (int) get_option( 'fcm_reels_page_id', 0 );

        if ( ! $page_id || ! is_page( $page_id ) ) {
            return;
        }

        wp_enqueue_style(
            'fcm-reels-style',
            FCM_REELS_URL . 'assets/css/reels.css',
            [],
            FCM_REELS_VERSION
        );

        wp_enqueue_script(
            'fcm-reels-script',
            FCM_REELS_URL . 'assets/js/reels.js',
            [],
            FCM_REELS_VERSION,
            true
        );

        // Find FCM Portal page for linking.
        $portal_url = $this->get_portal_url();

        // Pass config to JS.
        wp_localize_script( 'fcm-reels-script', 'FCMReels', [
            'apiBase'      => esc_url_raw( rest_url( 'fcm-reels/v1' ) ),
            'portalUrl'    => esc_url( trailingslashit( $portal_url ) ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'isLoggedIn'   => is_user_logged_in(),
            'userId'       => get_current_user_id(),
            'loginUrl'     => esc_url( wp_login_url( get_permalink() ) ),
            'requireLogin' => get_option( 'fcm_reels_require_login', 'no' ),
            'perPage'      => 10,
            'labels'       => [
                'like'       => __( 'Like', 'fcm-reels' ),
                'liked'      => __( 'Liked', 'fcm-reels' ),
                'comment'    => __( 'Comment', 'fcm-reels' ),
                'share'      => __( 'Share', 'fcm-reels' ),
                'follow'     => __( 'Follow', 'fcm-reels' ),
                'mute'       => __( 'Mute', 'fcm-reels' ),
                'unmute'     => __( 'Unmute', 'fcm-reels' ),
                'loading'    => __( 'Loading reels...', 'fcm-reels' ),
                'no_videos'  => __( 'No videos found.', 'fcm-reels' ),
                'login_like' => __( 'Log in to like this video.', 'fcm-reels' ),
            ],
        ] );
    }

    /**
     * Try to find the FluentCommunity Portal URL.
     *
     * @return string
     */
    private function get_portal_url() {
        // 0. Try Manual Override First
        $manual = get_option( 'fcm_reels_portal_url_manual' );
        if ( $manual ) {
            return home_url( '/' . trim( $manual, '/' ) . '/' );
        }

        // 1. Try FCM setting
        $portal_id = get_option( 'fcom_portal_page_id' );
        if ( $portal_id ) {
            $url = get_permalink( $portal_id );
            if ( $url ) return $url;
        }

        // 2. Try Database search for the portal block
        global $wpdb;
        $portal_slug = $wpdb->get_var( "SELECT post_name FROM {$wpdb->posts} WHERE post_content LIKE '%fluent_community%' AND post_status = 'publish' AND post_type = 'page' LIMIT 1" );
        if ( $portal_slug ) {
            return home_url( '/' . $portal_slug . '/' );
        }

        // 3. Common fallback slugs
        foreach ( ['social', 'home', 'community'] as $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page ) return get_permalink( $page->ID );
        }

        return home_url( '/social/' ); // Final fallback for this site
    }

    /**
     * Render the reels feed via shortcode [fcm_reels].
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_shortcode( $atts ) {
        // Enqueue assets for shortcode usage.
        wp_enqueue_style(
            'fcm-reels-style',
            FCM_REELS_URL . 'assets/css/reels.css',
            [],
            FCM_REELS_VERSION
        );
        wp_enqueue_script(
            'fcm-reels-script',
            FCM_REELS_URL . 'assets/js/reels.js',
            [],
            FCM_REELS_VERSION,
            true
        );

        // Find FCM Portal page for linking.
        $portal_url = $this->get_portal_url();

        wp_localize_script( 'fcm-reels-script', 'FCMReels', [
            'apiBase'      => esc_url_raw( rest_url( 'fcm-reels/v1' ) ),
            'portalUrl'    => esc_url( trailingslashit( $portal_url ) ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'isLoggedIn'   => is_user_logged_in(),
            'userId'       => get_current_user_id(),
            'loginUrl'     => esc_url( wp_login_url( get_permalink() ) ),
            'requireLogin' => get_option( 'fcm_reels_require_login', 'no' ),
            'perPage'      => 10,
            'labels'       => [
                'like'       => __( 'Like', 'fcm-reels' ),
                'liked'      => __( 'Liked', 'fcm-reels' ),
                'comment'    => __( 'Comment', 'fcm-reels' ),
                'share'      => __( 'Share', 'fcm-reels' ),
                'follow'     => __( 'Follow', 'fcm-reels' ),
                'mute'       => __( 'Mute', 'fcm-reels' ),
                'unmute'     => __( 'Unmute', 'fcm-reels' ),
                'loading'    => __( 'Loading reels...', 'fcm-reels' ),
                'no_videos'  => __( 'No videos found.', 'fcm-reels' ),
                'login_like' => __( 'Log in to like this video.', 'fcm-reels' ),
            ],
        ] );

        ob_start();
        include FCM_REELS_DIR . 'templates/reels-page.php';
        return ob_get_clean();
    }
}
