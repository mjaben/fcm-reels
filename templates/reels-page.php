<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php wp_title( '|', true, 'right' ); ?><?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'fcm-reels-body' ); ?>>

<div id="fcm-reels-app">

    <!-- Top Navigation Bar -->
    <header class="reels-header">
        <div class="reels-header__logo">
            <a href="<?php echo esc_url( home_url() ); ?>">
                <?php
                $logo_id = get_theme_mod( 'custom_logo' );
                if ( $logo_id ) {
                    echo wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'site-logo' ] );
                } else {
                    echo '<span class="site-name">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
                }
                ?>
            </a>
        </div>

        <div class="reels-header__title">
            <img class="reels-icon-img" src="<?php echo esc_url( FCM_REELS_URL . 'assets/img/video-icon.png' ); ?>" alt="" width="20" height="20">
            <?php esc_html_e( 'Orbits', 'fcm-reels' ); ?> <span class="reels-beta-badge">Beta</span>
        </div>

        <div class="reels-header__actions">
            <?php if ( is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( home_url() ); ?>" class="reels-btn reels-btn--ghost">
                    <?php esc_html_e( 'Community', 'fcm-reels' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="reels-btn reels-btn--primary">
                    <?php esc_html_e( 'Log In', 'fcm-reels' ); ?>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Filter / Category Bar -->
    <div class="reels-filter-bar" id="reels-filter-bar">
        <button class="reels-filter-chip active" data-space="" id="filter-all">
            <?php esc_html_e( 'All', 'fcm-reels' ); ?>
        </button>
        <!-- Spaces loaded dynamically by JS -->
    </div>

    <!-- Main Reel Feed Container -->
    <main class="reels-feed" id="reels-feed" aria-label="<?php esc_attr_e( 'Orbits Feed', 'fcm-reels' ); ?>">

        <!-- Loading Skeleton -->
        <div class="reels-loader" id="reels-loader" aria-live="polite">
            <div class="reels-loader__spinner"></div>
            <p><?php esc_html_e( 'Loading orbits...', 'fcm-reels' ); ?></p>
        </div>

        <!-- Empty State -->
        <div class="reels-empty" id="reels-empty" style="display:none;">
            <div class="reels-empty__icon">&#127909;</div>
            <h3><?php esc_html_e( 'No orbits yet', 'fcm-reels' ); ?></h3>
            <p><?php esc_html_e( 'Be the first to share an orbit with the community!', 'fcm-reels' ); ?></p>
            <a href="<?php echo esc_url( home_url() ); ?>" class="reels-btn reels-btn--primary">
                <?php esc_html_e( 'Go to Community', 'fcm-reels' ); ?>
            </a>
        </div>

        <!-- Reel slides will be injected here by reels.js -->
        <div class="reels-slides" id="reels-slides">
            <!-- Load More Sentinel (for Intersection Observer infinite scroll) -->
            <div class="reels-sentinel" id="reels-sentinel" aria-hidden="true"></div>
        </div>

    </main>

    <!-- Mute Button (persistent, top-right of video area) -->
    <button class="reels-mute-btn" id="reels-mute-btn" aria-label="<?php esc_attr_e( 'Toggle mute', 'fcm-reels' ); ?>">
        <span class="mute-icon mute-icon--muted">&#128263;</span>
        <span class="mute-icon mute-icon--unmuted" style="display:none;">&#128266;</span>
    </button>

    <!-- Share Modal -->
    <div class="reels-modal reels-share-modal" id="reels-share-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Share Video', 'fcm-reels' ); ?>" style="display:none;">
        <div class="reels-modal__overlay" id="reels-share-overlay"></div>
        <div class="reels-modal__content">
            <button class="reels-modal__close" id="reels-share-close" aria-label="<?php esc_attr_e( 'Close', 'fcm-reels' ); ?>">&#10005;</button>
            <div id="reels-share-preview" class="reels-share-preview"></div>
            <h3><?php esc_html_e( 'Share this Orbit', 'fcm-reels' ); ?></h3>
            <div class="reels-share-url">
                <input type="text" id="reels-share-input" readonly aria-label="<?php esc_attr_e( 'Share URL', 'fcm-reels' ); ?>">
                <button id="reels-copy-btn" class="reels-btn reels-btn--primary">
                    <?php esc_html_e( 'Copy', 'fcm-reels' ); ?>
                </button>
            </div>
            <div class="reels-share-platforms">
                <a class="reels-share-platform" id="share-whatsapp" target="_blank" rel="noopener">
                    <img src="<?php echo FCM_REELS_URL . 'assets/img/share-icon-whatsapp.png'; ?>" alt="WhatsApp" width="24" height="24"> WhatsApp
                </a>
                <a class="reels-share-platform" id="share-telegram" target="_blank" rel="noopener">
                    <img src="<?php echo FCM_REELS_URL . 'assets/img/share-icon-telegram.png'; ?>" alt="Telegram" width="24" height="24"> Telegram
                </a>
                <a class="reels-share-platform" id="share-twitter" target="_blank" rel="noopener">
                    <img src="<?php echo FCM_REELS_URL . 'assets/img/share-icon-x-twitter.png'; ?>" alt="X" width="24" height="24"> X / Twitter
                </a>
            </div>
        </div>
    </div>

</div><!-- #fcm-reels-app -->

<!-- Reel Slide Template (hidden, cloned by JS) -->
<template id="reel-slide-tpl">
    <article class="reel-slide" data-id="" role="region">
        <!-- Video -->
        <div class="reel-video-wrap">
            <video
                class="reel-video"
                muted
                autoplay
                playsinline
                webkit-playsinline
                preload="auto"
            ></video>
            <!-- Tap-to-pause overlay -->
            <div class="reel-tap-overlay" aria-hidden="true"></div>
            <!-- Pause indicator -->
            <div class="reel-pause-indicator" aria-hidden="true">
    <img src="<?php echo FCM_REELS_URL . 'assets/img/pause-icon.gif'; ?>" alt="Paused">
</div>
            <!-- Progress Bar -->
            <div class="reel-progress-bar" aria-hidden="true">
                <div class="reel-progress-fill"></div>
            </div>
            <!-- Video Loading Spinner -->
            <div class="reel-video-loading" aria-hidden="true">
                <div class="reels-loader__spinner"></div>
            </div>
            <div class="reel-video-loading" aria-hidden="true">
                <div class="reels-loader__spinner"></div>
            </div>
        </div>

        <!-- Left info overlay -->
        <div class="reel-overlay">
            <div class="reel-author">
                <a class="reel-author__link" href="#">
                    <img class="reel-author__avatar" src="" alt="" width="44" height="44" loading="lazy">
                </a>
                <div class="reel-author__meta">
                    <a class="reel-author__name" href="#"></a>
                    <span class="reel-author__space"></span>
                </div>
            </div>
            <p class="reel-description"></p>
        </div>

        <!-- Right action sidebar -->
        <div class="reel-actions">
            <!-- Views -->
            <div class="reel-action-btn reel-views-btn" aria-label="Views">
                <span class="reel-action-btn__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <span class="reel-action-btn__count"></span>
            </div>
            <!-- Like -->
            <button class="reel-action-btn reel-like-btn" aria-label="Like">
                <span class="reel-action-btn__icon">
                    <img src="<?php echo FCM_REELS_URL . 'assets/img/reaction-love.png'; ?>" alt="Like" class="reel-custom-icon">
                </span>
                <span class="reel-action-btn__count"></span>
            </button>
            <!-- Comment -->
            <a class="reel-action-btn reel-comment-btn" href="#" aria-label="Comment">
                <span class="reel-action-btn__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/></svg>
                </span>
                <span class="reel-action-btn__count"></span>
            </a>
            <!-- Share -->
            <button class="reel-action-btn reel-share-btn" aria-label="Share">
                <span class="reel-action-btn__icon">
                    <img src="<?php echo FCM_REELS_URL . 'assets/img/share-icon.png'; ?>" alt="Share" class="reel-custom-icon">
                </span>
                <span class="reel-action-btn__label">Share</span>
            </button>
        </div>
    </article>
</template>

<?php wp_footer(); ?>
</body>
</html>
