/**
 * FCM Reels — reels.js
 * TikTok-style vertical snap-scroll video feed.
 * Fetches videos from the fcm-reels/v1/feed REST API endpoint.
 */
(function () {
    'use strict';

    /* ── Config from wp_localize_script ── */
    const CFG = window.FCMReels || {};
    const API = CFG.apiBase || '/wp-json/fcm-reels/v1';
    const NONCE = CFG.nonce || '';
    const PER_PAGE = CFG.perPage || 8;
    const IS_LOGGED_IN = CFG.isLoggedIn || false;
    const USER_ID = CFG.userId || 0;
    const LOGIN_URL = CFG.loginUrl || '/wp-login.php';

    /* ── DOM References ── */
    const feed = document.getElementById('reels-feed');
    const slidesWrap = document.getElementById('reels-slides');
    const loader = document.getElementById('reels-loader');
    const emptyState = document.getElementById('reels-empty');
    const sentinel = document.getElementById('reels-sentinel');
    const muteBtn = document.getElementById('reels-mute-btn');
    const muteIconOn = muteBtn ? muteBtn.querySelector('.mute-icon--muted') : null;
    const muteIconOff = muteBtn ? muteBtn.querySelector('.mute-icon--unmuted') : null;
    const filterBar = document.getElementById('reels-filter-bar');
    const shareModal = document.getElementById('reels-share-modal');
    const shareOverlay = document.getElementById('reels-share-overlay');
    const shareClose = document.getElementById('reels-share-close');
    const shareInput = document.getElementById('reels-share-input');
    const copyBtn = document.getElementById('reels-copy-btn');
    const slideTpl = document.getElementById('reel-slide-tpl');

    /* ── State ── */
    let discoverySeed = Math.floor(Math.random() * 999999);
    let seenVideos = [];
    const seenKey = 'fcm_reels_seen_' + USER_ID;
    try {
        seenVideos = JSON.parse(localStorage.getItem(seenKey) || '[]');
    } catch (e) { }

    let nextCursor = '';
    let isLoading = false;
    let hasMore = true;
    let isMuted = true;
    let currentSpace = '';
    let activeSlide = null;
    let progressTimer = null;

    /* ─────────────────────────────────────────────
       INIT
    ───────────────────────────────────────────── */
    function init() {
        if (!slidesWrap || !slideTpl) return;

        loadSpaces();
        loadNextPage();
        setupMuteButton();
        setupShareModal();
        setupSentinelObserver();
        setupFilterBar();

        // Autoplay Watchdog: Check active video every 2 seconds
        setInterval(() => {
            if (activeSlide) {
                const v = activeSlide.querySelector('.reel-video');
                if (v && v.paused && !activeSlide.classList.contains('is-paused')) {
                    console.log('Watchdog: Active video stalled. Attempting recovery...');
                    activeSlide.classList.add('is-loading');
                    v.play().catch(() => { });
                }
            }
        }, 2000);
    }

    /* ─────────────────────────────────────────────
       FETCH VIDEOS
    ───────────────────────────────────────────── */

    /**
     * Fetch the next page of videos from the REST API.
     */
    async function loadNextPage() {
        if (isLoading) return;

        // If we hit the end, reset and loop back for infinite scrolling
        if (!hasMore) {
            console.log('FCM Reels: End reached. Shuffling and resetting...');
            nextCursor = '';
            hasMore = true;
            discoverySeed = Math.floor(Math.random() * 999999);
        }

        isLoading = true;
        
        // 🤫 STEALTH LOADING: 
        // Only show the big loader on the VERY FIRST load.
        // For background batches, we load silently.
        const isFirstLoad = (slidesWrap.querySelectorAll('.reel-slide').length === 0);
        if (isFirstLoad) {
            showLoader(true);
        }

        let url = `${API}/feed?per_page=${PER_PAGE}`;

        if (nextCursor) {
            url += `&cursor=${encodeURIComponent(nextCursor)}`;
        } else {
            url += `&seed=${discoverySeed}`;
        }

        if (currentSpace) {
            url += `&space=${currentSpace}`;
        }

        try {
            console.log('Fetching videos from:', url);

            const resp = await fetch(url, {
                headers: { 'X-WP-Nonce': NONCE },
                credentials: 'same-origin',
            });

            if (!resp.ok) throw new Error('API error: ' + resp.status);

            const data = await resp.json();
            console.log('Received videos data:', data);

            if (data.videos && data.videos.length > 0) {
                renderSlides(data.videos);
                nextCursor = data.next_cursor;
                hasMore = !!nextCursor || (data.videos.length >= PER_PAGE);

                // Auto-Refill: If we have very few videos, proactively load more 
                // to ensure the swiper always has "room" to swipe up.
                const totalSlides = slidesWrap.querySelectorAll('.reel-slide').length;
                if (totalSlides < 5 && hasMore && !isLoading) {
                    // Small delay to prevent recursion issues
                    setTimeout(() => loadNextPage(), 100);
                }
            } else if (!nextCursor) {
                showEmpty(true);
            }

            // Hide loader ONLY AFTER content is handled to prevent the "dark glitch"
            showLoader(false);
        } catch (err) {
            console.error('FCM Reels fetch error:', err);
            showLoader(false);
        } finally {
            isLoading = false;
        }
    }

    /* ─────────────────────────────────────────────
       RENDER SLIDES
    ───────────────────────────────────────────── */

    /**
     * Render an array of video objects into reel slides.
     *
     * @param {Array} videos
     */
    function renderSlides(videos) {
        const fragment = document.createDocumentFragment();

        // 🛡️ CLIENT-SIDE DE-DUPLICATION:
        // Ensure the first video of the new batch isn't a duplicate of the last existing slide
        const lastSlide = slidesWrap.querySelector('.reel-slide:last-child');
        if (lastSlide && videos.length > 0) {
            const lastId = parseInt(lastSlide.dataset.id);
            if (parseInt(videos[0].id) === lastId) {
                console.log('FCM Reels: Filtering out duplicate video ID ' + lastId + ' at batch junction.');
                videos.shift();
            }
        }

        videos.forEach((video) => {
            const slide = createSlide(video);
            fragment.appendChild(slide);
        });

        // Append to the container
        slidesWrap.appendChild(fragment);
        console.log('Successfully appended ' + videos.length + ' slides');

        // 🧹 DOM GARBAGE COLLECTION:
        // If we have too many slides in the DOM, remove the oldest ones to save memory.
        const allSlides = slidesWrap.querySelectorAll('.reel-slide');
        if (allSlides.length > 40) {
            console.log('FCM Reels: Performing DOM cleanup (Removing oldest 10 slides).');
            for (let i = 0; i < 10; i++) {
                const s = allSlides[i];
                const v = s.querySelector('.reel-video');
                if (v) {
                    v.pause();
                    v.src = '';
                    v.load();
                }
                s.remove();
            }
        }

        // Observe new slides for autoplay
        observeSlides();

        // If first load, play the first slide
        if (allSlides.length > 0 && !activeSlide) {
            activateSlide(allSlides[0]);
        }
    }

    /**
     * Build a single reel slide DOM node from the template.
     *
     * @param {Object} video
     * @returns {HTMLElement}
     */
    function createSlide(video) {
        const node = slideTpl.content.cloneNode(true);
        const slide = node.querySelector('.reel-slide');

        slide.dataset.id = video.id;
        slide.dataset.postUrl = buildPostUrl(video);
        slide.dataset.liked = video.user_liked ? '1' : '0';
        slide.dataset.likesCount = video.likes_count;

        /* Video element */
        const videoEl = slide.querySelector('.reel-video');

        const videoUrl = video.video_url;
        const isHLS = videoUrl.includes('.m3u8') || videoUrl.includes('cloudflarestream.com');

        // Mobile Hardening: Attributes must be set before SRC
        videoEl.muted = true;
        videoEl.defaultMuted = true;
        videoEl.setAttribute('muted', '');
        videoEl.setAttribute('playsinline', '');
        videoEl.setAttribute('webkit-playsinline', '');
        videoEl.setAttribute('autoplay', '');
        videoEl.preload = 'auto';

        // If it's Cloudflare Stream / HLS and the browser doesn't support it natively
        // we could use HLS.js, but iOS/Safari and most modern Chrome/Android support it now.
        videoEl.src = videoUrl;

        if (video.thumbnail_url) {
            videoEl.poster = video.thumbnail_url;
        }

        videoEl.load();

        // 4. Wait Until Ready to Play
        videoEl.addEventListener('playing', () => {
            if (activeSlide === slide) {
                // Mark as seen IMMEDIATELY so it doesn't show up on reload
                markVideoAsSeen(video.id);
            }
        }, { once: true });

        /* Tap overlay — play/pause */
        const tapOverlay = slide.querySelector('.reel-tap-overlay');
        tapOverlay.addEventListener('click', () => togglePlayPause(slide));

        /* Author */
        const avatarLink = slide.querySelector('.reel-author__link');
        const avatar = slide.querySelector('.reel-author__avatar');
        const nameLink = slide.querySelector('.reel-author__name');
        const spaceEl = slide.querySelector('.reel-author__space');

        if (video.author.profile_url) {
            avatarLink.href = video.author.profile_url;
            nameLink.href = video.author.profile_url;
        }

        avatar.src = video.author.avatar || '';
        avatar.alt = video.author.name || '';
        nameLink.textContent = video.author.name || 'Member';

        if (video.space && video.space.title) {
            spaceEl.textContent = '# ' + video.space.title;
            spaceEl.style.display = '';
        } else {
            spaceEl.style.display = 'none';
        }

        /* Description */
        const descEl = slide.querySelector('.reel-description');
        if (video.description || video.title) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = video.description || video.title;
            descEl.textContent = tempDiv.textContent;
        } else {
            descEl.textContent = '';
        }

        /* Like button */
        const likeBtn = slide.querySelector('.reel-like-btn');
        const likeCount = slide.querySelector('.reel-like-btn .reel-action-btn__count');
        likeCount.textContent = formatCount(video.likes_count);
        if (video.user_liked) likeBtn.classList.add('is-liked');

        likeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            handleLike(slide, video.id);
        });

        /* Comment link */
        const commentBtn = slide.querySelector('.reel-comment-btn');
        const commentCount = slide.querySelector('.reel-comment-btn .reel-action-btn__count');
        commentBtn.href = slide.dataset.postUrl || '#';
        commentCount.textContent = formatCount(video.comments_count);

        /* Share button */
        const shareBtn = slide.querySelector('.reel-share-btn');
        shareBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openShareModal(slide.dataset.postUrl || window.location.href, video.title || '', video.thumbnail_url || '');
        });

        /* Progress bar */
        videoEl.addEventListener('timeupdate', () => updateProgress(slide, videoEl));
        videoEl.addEventListener('ended', () => {
            const fill = slide.querySelector('.reel-progress-fill');
            if (fill) fill.style.width = '100%';
        });

        /* Buffering / Loading states */
        videoEl.addEventListener('waiting', () => slide.classList.add('is-loading'));
        videoEl.addEventListener('stalled', () => slide.classList.add('is-loading'));
        videoEl.addEventListener('playing', () => slide.classList.remove('is-loading'));
        videoEl.addEventListener('pause', () => {
            if (activeSlide === slide && !slide.classList.contains('is-paused')) {
                slide.classList.add('is-loading');
            }
        });
        videoEl.addEventListener('canplay', () => {
            slide.classList.remove('is-loading');
            if (activeSlide === slide) {
                activateSlide(slide);
            }
        });

        return slide;
    }

    /* ─────────────────────────────────────────────
       INTERSECTION OBSERVER — Autoplay
    ───────────────────────────────────────────── */
    let slideObserver = null;

    function observeSlides() {
        if (!('IntersectionObserver' in window)) return;

        if (slideObserver) {
            // Observe only newly added, unobserved slides
            const slides = slidesWrap.querySelectorAll('.reel-slide:not([data-observed])');
            slides.forEach((s) => {
                s.dataset.observed = '1';
                slideObserver.observe(s);
            });
            return;
        }

        slideObserver = new IntersectionObserver(
            (entries) => {
                // Find the entry with the highest visibility
                let mostVisible = null;
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        if (!mostVisible || entry.intersectionRatio > mostVisible.intersectionRatio) {
                            mostVisible = entry;
                        }
                    }
                });

                if (mostVisible && mostVisible.intersectionRatio >= 0.4) {
                    activateSlide(mostVisible.target);
                }

                // Deactivate any entry that is barely visible
                entries.forEach(entry => {
                    if (!entry.isIntersecting || entry.intersectionRatio < 0.2) {
                        if (entry.target !== activeSlide) {
                            deactivateSlide(entry.target);
                        }
                    }
                });
            },
            { threshold: [0, 0.2, 0.4, 0.6, 0.8, 1.0] }
        );

        const slides = slidesWrap.querySelectorAll('.reel-slide:not([data-observed])');
        slides.forEach((s) => {
            s.dataset.observed = '1';
            slideObserver.observe(s);
        });
    }

    /**
     * Play video in active slide, pause all others.
     *
     * @param {HTMLElement} slide
     */
    async function activateSlide(slide) {
        if (activeSlide && activeSlide !== slide) {
            deactivateSlide(activeSlide);
        }

        activeSlide = slide;
        const videoEl = slide.querySelector('.reel-video');
        if (!videoEl) return;

        videoEl.muted = isMuted;
        videoEl.setAttribute('playsinline', '');

        if (!slide.classList.contains('is-paused')) {
            const playPromise = videoEl.play();
            if (playPromise !== undefined) {
                playPromise.catch((error) => {
                    console.warn('Playback deferred.', error);
                });
            }
        }

        slide.classList.remove('is-paused');

        // Track as "Seen"
        markVideoAsSeen(parseInt(slide.dataset.id));

        // 🛰️ PROACTIVE BATCHING:
        // If the user is nearing the end of the current slides, load the next batch NOW.
        const allSlides = Array.from(slidesWrap.querySelectorAll('.reel-slide'));
        const currentIndex = allSlides.indexOf(slide);
        
        if (currentIndex >= allSlides.length - 2 && hasMore && !isLoading) {
            console.log('FCM Reels: Nearing end of batch (Slide ' + (currentIndex + 1) + '/' + allSlides.length + '). Fetching more...');
            loadNextPage();
        }
    }

    /**
     * Pause video and reset progress on slide leaving viewport.
     *
     * @param {HTMLElement} slide
     */
    function deactivateSlide(slide) {
        const videoEl = slide.querySelector('.reel-video');
        if (videoEl) {
            videoEl.pause();
            videoEl.currentTime = 0;
        }
        slide.classList.remove('is-paused');

        const fill = slide.querySelector('.reel-progress-fill');
        if (fill) fill.style.width = '0%';
    }

    /* ─────────────────────────────────────────────
       PLAY / PAUSE TOGGLE
    ───────────────────────────────────────────── */

    function togglePlayPause(slide) {
        const videoEl = slide.querySelector('.reel-video');
        if (!videoEl) return;

        if (videoEl.paused) {
            videoEl.play();
            slide.classList.remove('is-paused');
        } else {
            videoEl.pause();
            slide.classList.add('is-paused');
        }
    }

    /* ─────────────────────────────────────────────
       PROGRESS BAR
    ───────────────────────────────────────────── */

    function updateProgress(slide, videoEl) {
        if (!videoEl.duration) return;
        const pct = (videoEl.currentTime / videoEl.duration) * 100;
        const fill = slide.querySelector('.reel-progress-fill');
        if (fill) fill.style.width = pct + '%';
    }

    /* ─────────────────────────────────────────────
       MUTE TOGGLE
    ───────────────────────────────────────────── */

    function setupMuteButton() {
        if (!muteBtn) return;
        muteBtn.addEventListener('click', () => {
            isMuted = !isMuted;
            syncMuteUI();

            // Apply to current active video
            if (activeSlide) {
                const v = activeSlide.querySelector('.reel-video');
                if (v) v.muted = isMuted;
            }
        });
    }

    function setupGlobalInteraction() {
        const handleFirstInteraction = () => {
            isMuted = false;
            syncMuteUI();

            if (activeSlide) {
                activeSlide.classList.remove('is-paused-blocked');
                const v = activeSlide.querySelector('.reel-video');
                if (v) {
                    v.muted = false;
                    v.play().catch(() => { });
                }
            }

            document.removeEventListener('click', handleFirstInteraction);
            document.removeEventListener('touchstart', handleFirstInteraction);
        };

        document.addEventListener('click', handleFirstInteraction);
        document.addEventListener('touchstart', handleFirstInteraction);
    }

    function syncMuteUI() {
        if (!muteIconOn || !muteIconOff || !muteBtn) return;
        muteIconOn.style.display = isMuted ? 'flex' : 'none';
        muteIconOff.style.display = isMuted ? 'none' : 'flex';
        muteBtn.setAttribute('aria-label', isMuted ? 'Unmute' : 'Mute');
    }

    /* ─────────────────────────────────────────────
       LIKE / UNLIKE
    ───────────────────────────────────────────── */

    async function handleLike(slide, videoId) {
        if (!IS_LOGGED_IN) {
            if (confirm(CFG.labels.login_like || 'Log in to like this video.')) {
                window.location.href = LOGIN_URL;
            }
            return;
        }

        const likeBtn = slide.querySelector('.reel-like-btn');
        const countEl = slide.querySelector('.reel-like-btn .reel-action-btn__count');
        const isLiked = likeBtn.classList.contains('is-liked');

        // Optimistic UI update
        const prevCount = parseInt(slide.dataset.likesCount || '0', 10);
        const newCount = isLiked ? Math.max(0, prevCount - 1) : prevCount + 1;

        likeBtn.classList.toggle('is-liked');
        countEl.textContent = formatCount(newCount);
        slide.dataset.likesCount = newCount;

        try {
            const resp = await fetch(API + '/like/' + videoId, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': NONCE,
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!resp.ok) throw new Error('Like failed');

            const data = await resp.json();
            // Sync with server truth
            countEl.textContent = formatCount(data.likes_count);
            slide.dataset.likesCount = data.likes_count;
            if (data.liked) {
                likeBtn.classList.add('is-liked');
            } else {
                likeBtn.classList.remove('is-liked');
            }
        } catch (err) {
            // Revert on error
            likeBtn.classList.toggle('is-liked');
            countEl.textContent = formatCount(prevCount);
            slide.dataset.likesCount = prevCount;
        }
    }

    /* ─────────────────────────────────────────────
       SHARE MODAL
    ───────────────────────────────────────────── */

    function setupShareModal() {
        if (!shareModal) return;

        if (shareOverlay) shareOverlay.addEventListener('click', closeShareModal);
        if (shareClose) shareClose.addEventListener('click', closeShareModal);

        if (copyBtn && shareInput) {
            copyBtn.addEventListener('click', () => {
                shareInput.select();
                try {
                    navigator.clipboard.writeText(shareInput.value);
                    copyBtn.textContent = '✓ Copied!';
                    setTimeout(() => { copyBtn.textContent = 'Copy'; }, 2000);
                } catch {
                    document.execCommand('copy');
                }
            });
        }
    }

    function openShareModal(url, title, thumbnail) {
        if (!shareModal) return;
        if (shareInput) shareInput.value = url;

        // Thumbnail preview
        const previewWrap = document.getElementById('reels-share-preview');
        if (previewWrap) {
            if (thumbnail) {
                previewWrap.innerHTML = `<img src="${thumbnail}" alt="Preview" class="reels-share-preview-img">`;
                previewWrap.style.display = 'block';
            } else {
                previewWrap.style.display = 'none';
            }
        }

        const wa = document.getElementById('share-whatsapp');
        const tw = document.getElementById('share-twitter');
        const fb = document.getElementById('share-facebook');

        if (wa) wa.href = 'https://wa.me/?text=' + encodeURIComponent(title + ' ' + url);
        if (tw) tw.href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(title) + '&url=' + encodeURIComponent(url);
        if (fb) fb.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);

        shareModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeShareModal() {
        if (!shareModal) return;
        shareModal.style.display = 'none';
        document.body.style.overflow = '';
    }

    /* ─────────────────────────────────────────────
       FILTER BAR — Spaces
    ───────────────────────────────────────────── */

    function setupFilterBar() {
        if (!filterBar) return;
        filterBar.addEventListener('click', (e) => {
            const chip = e.target.closest('.reels-filter-chip');
            if (!chip) return;

            filterBar.querySelectorAll('.reels-filter-chip').forEach((c) => c.classList.remove('active'));
            chip.classList.add('active');

            currentSpace = chip.dataset.space || '';
            resetFeed();
        });
    }

    /**
     * Load spaces that have video content into the filter bar.
     */
    async function loadSpaces() {
        if (!filterBar) return;
        try {
            const resp = await fetch(API + '/spaces', { credentials: 'same-origin', headers: { 'X-WP-Nonce': NONCE } });
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.spaces || !data.spaces.length) return;

            const fragment = document.createDocumentFragment();
            data.spaces.forEach((space) => {
                const chip = document.createElement('button');
                chip.className = 'reels-filter-chip';
                chip.dataset.space = space.slug;
                chip.textContent = space.title;
                chip.setAttribute('type', 'button');
                fragment.appendChild(chip);
            });
            filterBar.appendChild(fragment);
        } catch { }
    }

    /* ─────────────────────────────────────────────
       INFINITE SCROLL — Sentinel Observer
    ───────────────────────────────────────────── */

    function setupSentinelObserver() {
        if (!sentinel || !('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting) {
                    loadNextPage();
                }
            },
            { rootMargin: '800px' } // Trigger much earlier (800px before reaching the end)
        );

        observer.observe(sentinel);
    }

    /* ─────────────────────────────────────────────
       RESET FEED (on filter change)
    ───────────────────────────────────────────── */

    function resetFeed() {
        currentPage = 1;
        hasMore = true;

        // Stop and clear existing slides
        slidesWrap.querySelectorAll('.reel-slide').forEach((s) => {
            const v = s.querySelector('.reel-video');
            if (v) { v.pause(); v.src = ''; }
            s.remove();
        });

        activeSlide = null;
        if (slideObserver) { slideObserver.disconnect(); slideObserver = null; }

        showEmpty(false);
        loadNextPage();
    }

    /* ─────────────────────────────────────────────
       HELPERS
    ───────────────────────────────────────────── */

    function showLoader(show) {
        if (loader) loader.style.display = show ? 'flex' : 'none';
    }

    function showEmpty(show) {
        if (emptyState) emptyState.style.display = show ? 'flex' : 'none';
    }

    /**
     * Build the post permalink from video data.
     *
     * @param {Object} video
     * @returns {string}
     */
    function buildPostUrl(video) {
        const base = CFG.portalUrl || (window.location.origin + '/');
        if (video.space && video.space.slug) {
            return base + 'space/' + video.space.slug + '/post/' + video.slug;
        }
        return base + 'post/' + video.slug;
    }

    /**
     * Format a large count into human-readable form (1.2K, 3.4M).
     *
     * @param {number} num
     * @returns {string}
     */
    function formatCount(num) {
        num = parseInt(num, 10) || 0;
        if (num >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        return String(num);
    }

    /* ─────────────────────────────────────────────
       KEYBOARD SHORTCUTS
    ───────────────────────────────────────────── */
    document.addEventListener('keydown', (e) => {
        if (e.key === 'm' || e.key === 'M') {
            if (muteBtn) muteBtn.click();
        }
        if (e.key === ' ') {
            e.preventDefault();
            if (activeSlide) togglePlayPause(activeSlide);
        }
    });

    /* ─────────────────────────────────────────────
       GLOBAL INTERACTION
    ───────────────────────────────────────────── */
    function setupGlobalInteraction() {
        const handleFirstInteraction = () => {
            isMuted = false;
            syncMuteUI();

            if (activeSlide) {
                activeSlide.classList.remove('is-paused-blocked');
                const v = activeSlide.querySelector('.reel-video');
                if (v) {
                    v.muted = false;
                    v.play().catch(() => { });
                }
            }

            document.removeEventListener('click', handleFirstInteraction);
            document.removeEventListener('touchstart', handleFirstInteraction);
        };

        document.addEventListener('click', handleFirstInteraction);
        document.addEventListener('touchstart', handleFirstInteraction);
    }

    /* ─────────────────────────────────────────────
       BOOT
    ───────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            init();
            setupGlobalInteraction();
        });
    } else {
        init();
        setupGlobalInteraction();
    }

    /**
     * Mark a video ID as seen in the local state and storage.
     * @param {number} videoId 
     */
    function markVideoAsSeen(videoId) {
        if (!videoId || seenVideos.includes(videoId)) return;

        seenVideos.push(videoId);

        // Keep the last 200 seen videos to prevent storage bloat
        if (seenVideos.length > 200) {
            seenVideos.shift();
        }

        localStorage.setItem(seenKey, JSON.stringify(seenVideos));
        console.log('FCM Reels: Marked video ' + videoId + ' as seen.');
    }
})();
