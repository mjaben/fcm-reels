<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * FCM_Reels_Query
 *
 * Fetches videos from FluentCommunity's media archive table and joins them
 * with their parent feed posts and author profiles.
 *
 * Data flow:
 *   wp_fcom_media_archive (primary source for video URLs)
 *     → JOIN wp_fcom_posts (parent post data: likes, comments, space)
 *     → JOIN wp_fcom_xprofile (author data)
 */
class FCM_Reels_Query {

    /**
     * Fetch a paginated list of reel videos.
     *
     * @param int    $page       Current page (1-indexed).
     * @param int    $per_page   Items per page (capped at 20).
     * @param string $space_slug Filter by FCM space slug (empty = all).
     * @param string $order_by   'latest' | 'popular'.
     * @return array { videos: array, has_more: bool, total: int, page: int }
     */
    public function get_videos( $page = 1, $per_page = 10, $space_slug = '', $order_by = 'discovery', $seed = 0, $exclude_ids = '' ) {
        global $wpdb;

        $page      = max( 1, absint( $page ) );
        $per_page  = min( 20, max( 1, absint( $per_page ) ) );
        $offset    = ( $page - 1 ) * $per_page;
        $seed      = absint( $seed ) ?: mt_rand( 1, 999999 );

        $posts_tbl   = $wpdb->prefix . 'fcom_posts';
        $spaces_tbl  = $wpdb->prefix . 'fcom_spaces';
        $xp_tbl      = $wpdb->prefix . 'fcom_xprofile';
        $archive_tbl = $wpdb->prefix . 'fcom_media_archive';

        // Exclusion logic: prevent seen videos from appearing
        $exclude_where = '';
        if ( ! empty( $exclude_ids ) ) {
            $ids = array_map( 'absint', explode( ',', $exclude_ids ) );
            if ( ! empty( $ids ) ) {
                $exclude_where = "AND p.id NOT IN (" . implode( ',', $ids ) . ")";
            }
        }

        /**
         * Hotness Discovery Algorithm:
         * ( (Likes * 2) + (Comments * 5) + 1 ) / (DaysOld + 2)^1.5
         */
        $hotness_sql = "( (p.reactions_count * 2) + (p.comments_count * 5) + 1 ) / POW( DATEDIFF( NOW(), ma.created_at ) + 2, 1.5 )";

        // ORDER BY clause.
        // [STOCHASTIC WATERFALL]
        // [A] Popularity (Dampened): Rewards viral content without making it unbeatable.
        // [B] Freshness Boost: Gives brand new videos (last 24h) a head start.
        // [C] Shuffle Force: Ensures a different experience on every reload/loop.
        $order_sql = "
            (LOG10(p.reactions_count + p.comments_count + 1) * 2.0)
            + (CASE WHEN p.created_at > NOW() - INTERVAL 1 DAY THEN 1.5 ELSE 0 END)
            + (RAND({$seed}) * 2.5)
            DESC
        ";

        // Optional space filter.
        $space_join  = '';
        $space_where = '';
        if ( ! empty( $space_slug ) ) {
            $space_slug  = sanitize_text_field( $space_slug );
            $space_join  = "LEFT JOIN {$spaces_tbl} sp_filter ON sp_filter.id = p.space_id";
            $space_where = $wpdb->prepare( 'AND sp_filter.slug = %s', $space_slug );
        }

        // Query the media archive for video types.
        $sql = "
            SELECT
                ma.id         AS archive_id,
                ma.media_url  AS video_url,
                ma.settings   AS media_settings,
                ma.created_at AS archive_created_at,
                p.id          AS post_id,
                p.slug        AS post_slug,
                p.title       AS post_title,
                p.message     AS post_message,
                p.meta        AS post_meta,
                p.reactions_count,
                p.comments_count,
                p.user_id,
                xp.display_name,
                xp.avatar,
                xp.username,
                sp2.title     AS space_title,
                sp2.slug      AS space_slug
            FROM {$archive_tbl} ma
            INNER JOIN {$posts_tbl} p
                ON p.id = ma.feed_id
            LEFT JOIN {$xp_tbl} xp
                ON xp.user_id = p.user_id
            LEFT JOIN {$spaces_tbl} sp2
                ON sp2.id = p.space_id
            {$space_join}
            WHERE ma.is_active = 1
              AND (ma.media_type = 'fluent_player' OR ma.media_type LIKE 'video/%')
              AND p.status = 'published'
              {$space_where}
              {$exclude_where}
            ORDER BY {$order_sql}
            LIMIT %d OFFSET %d
        ";

        $rows = $wpdb->get_results(
            $wpdb->prepare( $sql, $per_page + 1, $offset )
        );

        // Universal Gap-Filling: If we didn't get enough "New" videos to fill the page,
        // fill the remaining slots with "Seen" videos (starting from the beginning).
        if ( count( $rows ) < $per_page && ! empty( $exclude_ids ) ) {
            $count_needed = ( $per_page + 1 ) - count( $rows );
            $sql_fallback = str_replace( $exclude_where, '', $sql );
            
            // Add a sub-exclusion to avoid duplicates within this same page
            $already_got = array_column( $rows, 'post_id' );
            if ( ! empty( $already_got ) ) {
                $sql_fallback = str_replace( "WHERE ma.is_active = 1", "WHERE ma.is_active = 1 AND p.id NOT IN (" . implode( ',', array_map( 'absint', $already_got ) ) . ")", $sql_fallback );
            }

            // Reset offset to 0 for the "Fill" part to ensure it always finds content
            $fill_rows = $wpdb->get_results(
                $wpdb->prepare( $sql_fallback, $count_needed, 0 )
            );
            $rows = array_merge( $rows, $fill_rows );
        }

        // FORCE INFINITE: As long as the site has videos, we tell the app there's ALWAYS more.
        $has_more = ( ! empty( $rows ) || $page === 1 );
        $rows     = array_slice( $rows, 0, $per_page );

        if ( empty( $rows ) ) {
            return [
                'videos'   => [],
                'has_more' => false,
                'total'    => 0,
                'page'     => $page,
            ];
        }

        // ── Build video objects ────────────────────────────────────────────
        $current_user_id = get_current_user_id();
        $videos          = [];

        foreach ( $rows as $row ) {
            $videos[] = $this->format_video( $row, $current_user_id );
        }

        // Total count (separate lightweight query).
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$archive_tbl} ma
                 INNER JOIN {$posts_tbl} p ON p.id = ma.feed_id
                 {$space_join}
                 WHERE ma.is_active = 1
                   AND (ma.media_type = 'fluent_player' OR ma.media_type LIKE 'video/%')
                   AND p.status = 'published'
                   {$space_where}",
                []
            )
        );

        return [
            'videos'   => $videos,
            'has_more' => $has_more,
            'total'    => $total,
            'page'     => $page,
        ];
    }

    /**
     * Format a raw DB row into a clean video object.
     *
     * @param object $row             Raw row from SQL query.
     * @param int    $current_user_id Logged-in user ID (0 if guest).
     * @return array
     */
    private function format_video( $row, $current_user_id ) {
        global $wpdb;
        // Author avatar with WP fallback.
        $avatar = ! empty( $row->avatar )
            ? $row->avatar
            : get_avatar_url( $row->user_id, [ 'size' => 128 ] );

        // Extract thumbnail from media settings        // 1. Check FluentPlayer/Archive settings
        $thumbnail_url = '';
        $media_settings = maybe_unserialize( $row->media_settings );
        if ( isset( $media_settings['thumbnail'] ) ) {
            $thumbnail_url = $media_settings['thumbnail'];
        }

        // 2. Automate: Check if WordPress generated a thumbnail for the attachment itself
        if ( empty( $thumbnail_url ) ) {
            $attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1", $row->video_url ) );
            if ( $attachment_id ) {
                $wp_thumb = wp_get_attachment_image_src( $attachment_id, 'large' );
                if ( $wp_thumb ) {
                    $thumbnail_url = $wp_thumb[0];
                }
            }
        }

        // 3. Prioritize manual Featured Image (if set, it wins)
        if ( has_post_thumbnail( $row->post_id ) ) {
            $thumbnail_url = get_the_post_thumbnail_url( $row->post_id, 'large' );
        }

        if ( empty( $thumbnail_url ) && ! empty( $row->post_meta ) ) {
            $post_meta = maybe_unserialize( $row->post_meta );
            if ( is_array( $post_meta ) && isset( $post_meta['media_preview']['thumbnail'] ) ) {
                $thumbnail_url = $post_meta['media_preview']['thumbnail'];
            } elseif ( is_array( $post_meta ) && isset( $post_meta['media_preview']['posterSrc'] ) ) {
                $thumbnail_url = $post_meta['media_preview']['posterSrc'];
            }
        }

        // Check if current user has liked this feed post.
        $user_liked = $this->has_user_liked( $row->post_id, $current_user_id );

        // Extract description (strip block markup if any).
        $description = $row->post_message ? $this->strip_blocks( $row->post_message ) : '';

        return [
            'id'             => (int) $row->post_id,
            'archive_id'     => (int) $row->archive_id,
            'slug'           => $row->post_slug,
            'title'          => wp_strip_all_tags( $row->post_title ?: 'Video' ),
            'description'    => wp_strip_all_tags( $description ),
            'video_url'      => esc_url_raw( $row->video_url ),
            'thumbnail_url'  => $thumbnail_url ? esc_url_raw( $thumbnail_url ) : '',
            'duration'       => isset( $media_settings['duration'] ) ? (float) $media_settings['duration'] : 0,
            'author'         => [
                'id'          => (int) $row->user_id,
                'name'        => $row->display_name ?: 'Community Member',
                'username'    => $row->username ?: '',
                'avatar'      => esc_url_raw( $avatar ),
                'profile_url' => $this->get_profile_url( $row->username ),
            ],
            'space'          => [
                'title' => $row->space_title ?: '',
                'slug'  => $row->space_slug  ?: '',
            ],
            'likes_count'    => (int) $row->reactions_count,
            'comments_count' => (int) $row->comments_count,
            'user_liked'     => $user_liked,
            'created_at'     => $row->archive_created_at,
        ];
    }

    /**
     * Check whether the given user has liked a feed post.
     *
     * @param int $feed_id         FCM post ID.
     * @param int $current_user_id WP user ID (0 = guest).
     * @return bool
     */
    private function has_user_liked( $feed_id, $current_user_id ) {
        if ( ! $current_user_id ) {
            return false;
        }

        global $wpdb;
        $reactions_tbl = $wpdb->prefix . 'fcom_reactions';

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$reactions_tbl}
                 WHERE object_id = %d AND object_type = 'feed'
                   AND user_id = %d AND type = 'like' LIMIT 1",
                $feed_id,
                $current_user_id
            )
        );
    }

    /**
     * Strip Gutenberg block comments from a message string to get plain text.
     *
     * @param string $message Raw post message.
     * @return string
     */
    private function strip_blocks( $message ) {
        // Remove block comment tags: <!-- wp:... --> and <!-- /wp:... -->
        $stripped = preg_replace( '/<!--\s*\/?wp:[^>]+-->/s', '', $message );
        // Remove remaining HTML tags.
        return trim( wp_strip_all_tags( $stripped ) );
    }

    /**
     * Build an FCM profile URL for a given username.
     *
     * @param string $username FCM username.
     * @return string
     */
    private function get_profile_url( $username ) {
        if ( ! $username ) {
            return '';
        }
        
        // Use our smart discovery logic
        $manual = get_option( 'fcm_reels_portal_url_manual' );
        if ( $manual ) {
            $base = trim( $manual, '/' );
        } else {
            $base = defined( 'FLUENT_COMMUNITY_PORTAL_SLUG' ) ? FLUENT_COMMUNITY_PORTAL_SLUG : 'community';
        }

        return home_url( trailingslashit( $base ) . 'u/' . rawurlencode( $username ) );
    }

    /**
     * Get FCM spaces that have at least one video in the archive.
     *
     * @return array
     */
    public function get_spaces_with_videos() {
        global $wpdb;

        $posts_tbl   = $wpdb->prefix . 'fcom_posts';
        $spaces_tbl  = $wpdb->prefix . 'fcom_spaces';
        $archive_tbl = $wpdb->prefix . 'fcom_media_archive';

        $sql = "
            SELECT DISTINCT sp.id, sp.title, sp.slug
            FROM {$spaces_tbl} sp
            INNER JOIN {$posts_tbl} p
                ON p.space_id = sp.id
            INNER JOIN {$archive_tbl} ma
                ON ma.feed_id = p.id
            WHERE p.status = 'published'
              AND ma.is_active = 1
              AND (ma.media_type = 'fluent_player' OR ma.media_type LIKE 'video/%')
            ORDER BY sp.title ASC
        ";

        return $wpdb->get_results( $sql );
    }
}
