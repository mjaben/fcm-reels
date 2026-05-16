<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FCM_Reels_Admin
 *
 * Adds a settings page under Settings → FCM Reels to configure
 * the reels page, login requirement, and other preferences.
 */
class FCM_Reels_Admin {

    /**
     * Register admin hooks.
     *
     * @return void
     */
    public function register() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        
        // Media Library Columns
        add_filter( 'manage_media_columns', [ $this, 'add_media_columns' ] );
        add_action( 'manage_media_custom_column', [ $this, 'display_media_column_content' ], 10, 2 );
    }

    /**
     * Add the settings page to the WordPress admin menu.
     *
     * @return void
     */
    /**
     * Add the settings page to the WordPress admin menu.
     *
     * @return void
     */
    public function add_menu() {
        add_menu_page(
            'FCM Reels',
            'FCM Reels',
            'manage_options',
            'fcm-reels',
            [ $this, 'render_settings_page' ],
            'dashicons-video-alt3',
            30
        );
    }

    /**
     * Register plugin settings with the WordPress Settings API.
     *
     * @return void
     */
    public function register_settings() {
        register_setting( 'fcm_reels_options', 'fcm_reels_page_id' );
        register_setting( 'fcm_reels_options', 'fcm_reels_require_login' );
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Save settings if posted
        if ( isset( $_POST['fcm_reels_save_settings'] ) ) {
            check_admin_referer( 'fcm_reels_settings' );
            
            update_option( 'fcm_reels_page_id', (int) $_POST['reels_page_id'] );
            update_option( 'fcm_reels_portal_url_manual', sanitize_text_field( $_POST['portal_url_manual'] ) );
            update_option( 'fcm_reels_require_login', sanitize_text_field( $_POST['require_login'] ) );
            
            // Cloudflare Stream Settings
            update_option( 'fcm_reels_cf_account_id', sanitize_text_field( $_POST['cf_account_id'] ) );
            update_option( 'fcm_reels_cf_subdomain', sanitize_text_field( $_POST['cf_subdomain'] ) );
            update_option( 'fcm_reels_cf_api_token', sanitize_text_field( $_POST['cf_api_token'] ) );
            update_option( 'fcm_reels_cf_sync_enabled', isset( $_POST['cf_sync_enabled'] ) ? 'yes' : 'no' );

            echo '<div class="updated"><p>Settings saved successfully!</p></div>';
        }

        $page_id         = (int) get_option( 'fcm_reels_page_id' );
        $require_login   = get_option( 'fcm_reels_require_login', 'no' );
        $cf_account_id   = get_option( 'fcm_reels_cf_account_id' );
        $cf_api_token    = get_option( 'fcm_reels_cf_api_token' );
        $cf_sync_enabled = get_option( 'fcm_reels_cf_sync_enabled', 'no' );

        $pages = get_pages();
        ?>
        <div class="wrap">
            <h1>FCM Reels Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'fcm_reels_settings' ); ?>
                
                <div class="card">
                    <h2>General Configuration</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Reels Page</th>
                            <td>
                                <select name="reels_page_id" class="regular-text">
                                    <option value="0">-- Select Page --</option>
                                    <?php foreach ( $pages as $page ) : ?>
                                        <option value="<?php echo $page->ID; ?>" <?php selected( $page_id, $page->ID ); ?>>
                                            <?php echo esc_html( $page->post_title ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select the page that contains the <code>[fcm_reels]</code> shortcode.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Community Portal URL</th>
                            <td>
                                <input type="text" name="portal_url_manual" value="<?php echo esc_attr( get_option( 'fcm_reels_portal_url_manual' ) ); ?>" class="regular-text" placeholder="e.g. /social or /home">
                                <p class="description">The URL of your FluentCommunity portal (e.g. <code>/social</code> on live, <code>/home</code> on local).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Privacy Control</th>
                            <td>
                                <select name="require_login" class="regular-text">
                                    <option value="no" <?php selected( $require_login, 'no' ); ?>>Public (Anyone can view)</option>
                                    <option value="yes" <?php selected( $require_login, 'yes' ); ?>>Private (Logged-in members only)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card">
                    <h2>Cloudflare Stream Integration</h2>
                    <p>Enable adaptive bitrate streaming and global CDN delivery for mobile performance.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Account ID</th>
                            <td>
                                <input type="text" name="cf_account_id" value="<?php echo esc_attr( $cf_account_id ); ?>" class="regular-text" placeholder="e.g. f33zs165nr7gyyc4...">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Customer Subdomain</th>
                            <td>
                                <input type="text" name="cf_subdomain" value="<?php echo esc_attr( get_option( 'fcm_reels_cf_subdomain' ) ); ?>" class="regular-text" placeholder="e.g. customer-f33zs165nr7gyyc4">
                                <p class="description">Your unique Cloudflare Stream subdomain.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API Token</th>
                            <td>
                                <input type="password" name="cf_api_token" value="<?php echo esc_attr( $cf_api_token ); ?>" class="regular-text">
                                <p class="description">Requires <strong>Account: Stream: Edit</strong> permissions.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Auto-Sync</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cf_sync_enabled" value="1" <?php checked( $cf_sync_enabled, 'yes' ); ?>>
                                    Automatically process new video uploads via Cloudflare Stream.
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" name="fcm_reels_save_settings" class="button button-primary button-large" value="Save All Changes">
                </p>
            </form>

            <hr>
            <h3>Developer Reference</h3>
            <table class="widefat striped" style="max-width: 800px;">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>/fcm-reels/v1/feed</code></td>
                        <td>Main video feed (supports pagination).</td>
                    </tr>
                    <tr>
                        <td><code>/fcm-reels/v1/spaces</code></td>
                        <td>List of communities with video content.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-top: 20px;
                max-width: 800px;
            }
            .card h2 { margin-top: 0; }
        </style>
        <?php
    }

    /**
     * Add Cloudflare ID column to Media Library.
     */
    public function add_media_columns( $columns ) {
        $columns['fcm_reels_cf'] = 'Cloudflare Stream';
        return $columns;
    }

    /**
     * Display Cloudflare ID in Media Library.
     */
    public function display_media_column_content( $column_name, $post_id ) {
        if ( 'fcm_reels_cf' === $column_name ) {
            $cf_id = get_post_meta( $post_id, '_fcm_reels_cf_uid', true );
            if ( $cf_id ) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <code>' . esc_html( $cf_id ) . '</code>';
            } else {
                echo '<span class="description">Pending / Local Only</span>';
            }
        }
    }
}
