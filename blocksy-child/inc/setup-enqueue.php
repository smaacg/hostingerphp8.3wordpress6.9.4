<?php
/**
 * 樣式 / 腳本載入：jQuery / FA / CSS / JS / 條件式載入 / 自訂模板 CSS
 *
 * @package weixiaoacg
 * @subpackage Enqueue
 * @version 2.4.0 (2026-05-13)
 *
 * v2.1.0 變更 — Batch C #14：
 *   - 新增 page-year-review.php 範本的條件式 CSS/JS 載入
 *   - 新增 smacgYearReview localize（ajax / nonce / user_id / year）
 *
 * v2.2.0 變更 — 頭像上傳優化：
 *   - 新增 Cropper.js CDN（CSS + JS）條件式載入
 *   - 僅在 page-member.php 範本（或 UM user page）載入
 *   - LiteSpeed Cache 排除規則
 *
 * v2.3.0 變更 — Batch 1A 公開個人頁：
 *   - 新增公開個人頁 CSS/JS 條件式載入（僅在 smacg_is_public_profile_page() 為 true 時）
 * v2.4.0 變更 — Batch 1B-2 追蹤系統：
 *   - 全站載入 follow.js / follow.css
 *   - 注入 smacgFollow（ajax / nonce / loggedIn / loginUrl）
 *   - 在 functions.php 載入 inc/follow-ajax.php

 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   jQuery 3.6.4
   ============================================================ */
add_action( 'wp_enqueue_scripts', function() {
    wp_deregister_script('jquery');
    wp_deregister_script('jquery-core');
    wp_deregister_script('jquery-migrate');
    wp_register_script('jquery-core','https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js',[],'3.6.4',false);
    wp_register_script('jquery',false,['jquery-core'],null,false);
    wp_enqueue_script('jquery');
}, 1 );

/* ============================================================
   Font Awesome
   ============================================================ */
add_action( 'wp_enqueue_scripts', function() {
    if ( wp_style_is( 'weixiaoacg-fa6', 'registered' ) ) return;
    wp_enqueue_style('weixiaoacg-fa6','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',[],'6.5.0');
}, 5 );

/* ============================================================
   主要樣式載入
   ============================================================ */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style('blocksy-parent', get_template_directory_uri().'/style.css', ['weixiaoacg-fa6'], wp_get_theme('blocksy')->get('Version'));
    wp_enqueue_style('weixiaoacg-child-style', get_stylesheet_uri(), ['blocksy-parent'], weixiaoacg_VERSION);
    wp_enqueue_style('weixiaoacg-fonts','https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700;800&display=swap',[],null);

    foreach ([
        'weixiaoacg-glass' => ['glass.css', ['weixiaoacg-child-style']],
        'weixiaoacg-style' => ['style.css', ['weixiaoacg-glass']],
    ] as $h => [$f,$dep]) {
        $p = weixiaoacg_THEME_DIR.'/assets/css/'.$f;
        if (file_exists($p)) wp_enqueue_style($h, weixiaoacg_THEME_URL.'/assets/css/'.$f, $dep, filemtime($p));
    }

    $is_um_user = function_exists('um_is_core_page') && (um_is_core_page('user') || get_query_var('um_user'));
    $cond = [];
    if ( is_page('news')   || is_page_template('page-news.php') )    $cond['weixiaoacg-news']       = 'news.css';
    if ( is_page('season') || is_page_template('page-season.php') )  $cond['weixiaoacg-season']     = 'season.css';
    if ( is_page_template('page-ranking.php') )                       $cond['weixiaoacg-ranking']    = 'ranking.css';
    if ( is_page_template('page-anime-list.php') )                    $cond['weixiaoacg-anime-list'] = 'anime-list.css';
    if ( is_page_template('page-music.php') )                         $cond['weixiaoacg-music']      = 'music.css';
    if ( is_page_template('page-cosplay.php') )                       $cond['weixiaoacg-cosplay']    = 'cosplay.css';
    if ( is_search() )                                                $cond['weixiaoacg-search']     = 'search.css';
    if ( is_404() )                                                   $cond['weixiaoacg-404']        = '404.css';
    if ( is_page(['about','contact','disclaimer','sources','privacy','terms','join']) || is_page_template('page-join.php') )
                                                                      $cond['weixiaoacg-static']     = 'static.css';
    if ( is_page_template('page-member.php') || $is_um_user )         $cond['weixiaoacg-member']     = 'member.css';

    $is_account = is_page(1527) || is_page('account') ||
                  (function_exists('um_is_core_page') && um_is_core_page('account')) ||
                  (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/account') !== false);
    if ( $is_account ) $cond['weixiaoacg-account'] = 'account.css';

    foreach ($cond as $h => $f) {
        $p = weixiaoacg_THEME_DIR.'/assets/css/'.$f;
        if (file_exists($p)) wp_enqueue_style($h, weixiaoacg_THEME_URL.'/assets/css/'.$f, ['weixiaoacg-style'], filemtime($p));
    }

    if ( is_singular('anime') ) {
        if ( ! wp_style_is( 'weixiaoacg-anime', 'registered' ) ) {
            $anime_css = WP_PLUGIN_DIR.'/anime-sync-pro/public/assets/css/anime-single.css';
            if (file_exists($anime_css))
                wp_enqueue_style('weixiaoacg-anime', plugins_url('anime-sync-pro/public/assets/css/anime-single.css'), ['weixiaoacg-style','weixiaoacg-fa6'], filemtime($anime_css));
        }
        $tb = weixiaoacg_THEME_DIR.'/assets/css/track-bar.css';
        if (file_exists($tb)) wp_enqueue_style('smacg-track-bar', weixiaoacg_THEME_URL.'/assets/css/track-bar.css', ['weixiaoacg-anime'], filemtime($tb));
        $as = weixiaoacg_THEME_DIR.'/assets/css/anime-status.css';
        if (file_exists($as)) wp_enqueue_style('smacg-anime-status', weixiaoacg_THEME_URL.'/assets/css/anime-status.css', ['smacg-track-bar'], filemtime($as));
    }

    $p = weixiaoacg_THEME_DIR.'/assets/css/admin-sync.css';
    if (file_exists($p) && filesize($p) > 0)
        wp_enqueue_style('weixiaoacg-admin-sync', weixiaoacg_THEME_URL.'/assets/css/admin-sync.css', ['weixiaoacg-style'], filemtime($p));
}, 10 );

add_action( 'admin_enqueue_scripts', function() {
    $p = weixiaoacg_THEME_DIR.'/assets/css/admin-sync.css';
    if (file_exists($p) && filesize($p) > 0)
        wp_enqueue_style('weixiaoacg-admin', weixiaoacg_THEME_URL.'/assets/css/admin-sync.css', [], filemtime($p));
} );

/* LiteSpeed 排除 Font Awesome */
add_filter('litespeed_optm_css_minify', fn($c)=>$c, 10);
add_filter('litespeed_optimize_css_excludes', function($excludes) {
    $excludes[] = 'cdnjs.cloudflare.com/ajax/libs/font-awesome';
    $excludes[] = 'fontawesome';
    return $excludes;
});

/* ============================================================
   JS 載入
   ============================================================ */
add_action( 'wp_enqueue_scripts', function() {
    foreach ([
        'weixiaoacg-api'           => ['api.js',           []],
        'weixiaoacg-utils'         => ['utils.js',         ['weixiaoacg-api']],
        'weixiaoacg-page-template' => ['page-template.js', ['weixiaoacg-utils']],
        'weixiaoacg-nav'           => ['nav.js',           ['weixiaoacg-utils']],
    ] as $h => [$f,$dep]) {
        $p = weixiaoacg_THEME_DIR.'/assets/js/'.$f;
        if (file_exists($p)) wp_enqueue_script($h, weixiaoacg_THEME_URL.'/assets/js/'.$f, $dep, filemtime($p), true);
    }

    if (is_front_page()) {
        $p = weixiaoacg_THEME_DIR.'/assets/js/main.js';
        if (file_exists($p)) wp_enqueue_script('weixiaoacg-main', weixiaoacg_THEME_URL.'/assets/js/main.js', ['weixiaoacg-api'], filemtime($p), true);
    }

    if (is_singular('anime')) {
        foreach ([
            'weixiaoacg-anime-js' => 'anime.js',
            'smacg-anime-status'  => 'anime-status.js',
            'smacg-anime-rating'  => 'anime-rating.js',
        ] as $h=>$f) {
            $p = weixiaoacg_THEME_DIR.'/assets/js/'.$f;
            if (file_exists($p)) wp_enqueue_script($h, weixiaoacg_THEME_URL.'/assets/js/'.$f, ['weixiaoacg-api'], filemtime($p), true);
        }
        wp_localize_script('smacg-anime-status','SmacgConfig',[
            'apiUrl'    => esc_url_raw(rest_url('weixiaoacg/v1/')),
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'ajaxNonce' => wp_create_nonce('smacg_nonce'),
            'loggedIn'  => is_user_logged_in(),
            'loginUrl'  => function_exists('um_get_core_page') ? um_get_core_page('login') : wp_login_url(get_permalink()),
            'postId'    => get_the_ID(),
            'permalink' => get_permalink(),
            'title'     => get_the_title(),
        ]);
    }

    if (is_page_template('page-ranking.php')) {
        $p = weixiaoacg_THEME_DIR.'/assets/js/ranking.js';
        if (file_exists($p)) wp_enqueue_script('weixiaoacg-ranking', weixiaoacg_THEME_URL.'/assets/js/ranking.js', ['weixiaoacg-utils','weixiaoacg-api'], filemtime($p), true);
    }

    wp_localize_script('weixiaoacg-nav','weixiaoacg_ajax',[
        'ajax_url'     => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('weixiaoacg_nonce'),
        'user_id'      => get_current_user_id(),
        'is_logged_in' => is_user_logged_in(),
        'user_level'   => weixiaoacg_get_user_level_int(),
        'site_url'     => home_url(),
        'login_url'    => wp_login_url(get_permalink()),
        'register_url' => wp_registration_url(),
        'rest_url'     => rest_url('weixiaoacg/v1/'),
        'rest_nonce'   => wp_create_nonce('wp_rest'),
    ]);
}, 10 );

/* ============================================================
   自訂模板專用 CSS（後台外的條件式 CSS）
   ============================================================ */
add_action( 'wp_enqueue_scripts', function () {
    $base_url = get_stylesheet_directory_uri() . '/assets/css/';
    $base_dir = get_stylesheet_directory()     . '/assets/css/';
    $ver = fn($f) => file_exists($base_dir.$f) ? filemtime($base_dir.$f) : '1.0';

    if ( is_category() || is_tax( 'channel' ) )
        wp_enqueue_style( 'smacg-news', $base_url . 'news.css', [], $ver('news.css') );

    if ( is_singular( 'post' ) ) {
        wp_enqueue_style( 'smacg-news',   $base_url . 'news.css',   [],            $ver('news.css') );
        wp_enqueue_style( 'smacg-single', $base_url . 'single.css', ['smacg-news'], $ver('single.css') );
    }

    if ( is_page_template( 'page-columns.php' ) ) {
        wp_enqueue_style( 'smacg-news',    $base_url . 'news.css',    [],              $ver('news.css') );
        wp_enqueue_style( 'smacg-columns', $base_url . 'columns.css', ['smacg-news'], $ver('columns.css') );
    }
}, 20 );

/* ============================================================
   Year Review 頁面（Batch C #14 - 2026-05-13）
   ============================================================ */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_page_template( 'page-year-review.php' ) ) {
        return;
    }

    $base_dir = weixiaoacg_THEME_DIR;
    $base_url = weixiaoacg_THEME_URL;

    // CSS
    $css_path = $base_dir . '/assets/css/year-review.css';
    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'smacg-year-review',
            $base_url . '/assets/css/year-review.css',
            [ 'weixiaoacg-fa6' ],
            filemtime( $css_path )
        );
    }

    // JS
    $js_path = $base_dir . '/assets/js/year-review.js';
    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'smacg-year-review',
            $base_url . '/assets/js/year-review.js',
            [],
            filemtime( $js_path ),
            true
        );

        $year = isset( $_GET['year'] ) ? max( 2020, min( (int) date( 'Y' ), (int) $_GET['year'] ) ) : (int) date( 'Y' );
        wp_localize_script( 'smacg-year-review', 'smacgYearReview', [
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'smacg_year_review' ),
            'userId'  => get_current_user_id(),
            'year'    => $year,
            'siteUrl' => home_url(),
        ] );
    }
}, 15 );

/* ============================================================
   Cropper.js（v2.2.0 - 2026-05-13）
   ============================================================ */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'smacg_is_member_page' ) || ! smacg_is_member_page() ) {
        return;
    }

    wp_enqueue_style(
        'cropperjs',
        'https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css',
        [],
        '1.6.1'
    );

    wp_enqueue_script(
        'cropperjs',
        'https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js',
        [],
        '1.6.1',
        true
    );
}, 25 );

add_filter( 'litespeed_optimize_js_excludes', function ( $excludes ) {
    $excludes[] = 'cdn.jsdelivr.net/npm/cropperjs';
    $excludes[] = 'cropper.min.js';
    return $excludes;
} );
add_filter( 'litespeed_optimize_css_excludes', function ( $excludes ) {
    $excludes[] = 'cdn.jsdelivr.net/npm/cropperjs';
    $excludes[] = 'cropper.min.css';
    return $excludes;
} );

/* ============================================================
   Public Profile 公開個人頁（v2.3.0 - 2026-05-13 新增）
   ------------------------------------------------------------
   - 僅在 smacg_is_public_profile_page() 為 true 時載入
   - 不需 jQuery（純 vanilla JS）
   - 同時注入 toast 內聯樣式（避免另開 CSS 檔）
   ============================================================ */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'smacg_is_public_profile_page' ) || ! smacg_is_public_profile_page() ) {
        return;
    }

    $base_dir = weixiaoacg_THEME_DIR;
    $base_url = weixiaoacg_THEME_URL;

    // CSS
    $css_path = $base_dir . '/assets/css/public-profile.css';
    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'smacg-public-profile',
            $base_url . '/assets/css/public-profile.css',
            [ 'weixiaoacg-fa6' ],
            filemtime( $css_path )
        );

        // Toast 樣式（直接 inline，省去額外請求）
        wp_add_inline_style( 'smacg-public-profile', '
            .pp-toast {
                position: fixed;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%) translateY(20px);
                padding: 10px 20px;
                font-size: 14px;
                font-weight: 600;
                color: #fff;
                border-radius: 999px;
                box-shadow: 0 8px 24px rgba(0,0,0,.4);
                opacity: 0;
                pointer-events: none;
                transition: opacity .2s, transform .2s;
                z-index: 99999;
            }
            .pp-toast--show {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
            .pp-toast--ok  { background: linear-gradient(135deg, #34d399, #10b981); }
            .pp-toast--err { background: linear-gradient(135deg, #f87171, #ef4444); }
        ' );
    }

    // JS
    $js_path = $base_dir . '/assets/js/public-profile.js';
    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'smacg-public-profile',
            $base_url . '/assets/js/public-profile.js',
            [],
            filemtime( $js_path ),
            true
        );
    }
}, 20 );
/* ============================================================
   Follow System 追蹤按鈕（v2.4.0 - 2026-05-13）
   ------------------------------------------------------------
   - 全站載入（任何頁面都可能出現 .smacg-follow-btn）
   - 體積小，且只在頁面有按鈕時實際發 AJAX
   ============================================================ */
add_action( 'wp_enqueue_scripts', function () {
    $base_dir = weixiaoacg_THEME_DIR;
    $base_url = weixiaoacg_THEME_URL;

    // CSS
    $css_path = $base_dir . '/assets/css/follow.css';
    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'smacg-follow',
            $base_url . '/assets/css/follow.css',
            [ 'weixiaoacg-fa6' ],
            filemtime( $css_path )
        );
    }

    // JS
    $js_path = $base_dir . '/assets/js/follow.js';
    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'smacg-follow',
            $base_url . '/assets/js/follow.js',
            [],
            filemtime( $js_path ),
            true
        );
        wp_localize_script( 'smacg-follow', 'smacgFollow', [
            'ajax'     => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'smacg_follow_nonce' ),
            'loggedIn' => is_user_logged_in(),
            'loginUrl' => function_exists( 'um_get_core_page' )
                ? um_get_core_page( 'login' )
                : wp_login_url(),
        ] );
    }
}, 22 );
