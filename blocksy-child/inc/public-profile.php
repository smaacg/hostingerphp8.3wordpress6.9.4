<?php
/**
 * Public Profile System - /u/{username}/
 *
 * @package weixiaoacg
 * @subpackage PublicProfile
 * @version 1.0.0 (2026-05-13)
 *
 * 提供公開個人頁功能：
 * - URL: /u/{username}/
 * - 訪客可看（依隱私設定過濾內容）
 * - 完整 SEO meta（OpenGraph / Twitter Card）
 * - 條件式 noindex（私人帳號 / 不存在使用者）
 *
 * Hook 流程：
 *   init → 註冊 rewrite rule + query var
 *   parse_request → 解析 username → user_id
 *   template_redirect → 載入 page-public-profile.php
 *
 * 提供給其他檔案的 helper：
 *   smacg_get_public_profile_url( $user_id_or_login )
 *   smacg_get_public_profile_user()           // 在公開頁內取得當前 user 物件
 *   smacg_is_public_profile_page()            // 判斷是否為公開頁
 *   smacg_can_view_profile_section( $user_id, $section )
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   設定常數
   ============================================================ */
if ( ! defined( 'SMACG_PUBLIC_PROFILE_SLUG' ) ) {
    define( 'SMACG_PUBLIC_PROFILE_SLUG', 'u' );  // URL 前綴：/u/{username}/
}

/* ============================================================
   1. 註冊 rewrite rule + query var
   ============================================================ */
add_action( 'init', 'smacg_pp_register_rewrite' );
function smacg_pp_register_rewrite() {
    $slug = SMACG_PUBLIC_PROFILE_SLUG;

    // /u/{username}/        → 公開頁主頁
    // /u/{username}/page/2/ → 預留分頁
    add_rewrite_rule(
        '^' . $slug . '/([^/]+)/?$',
        'index.php?smacg_pp_user=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^' . $slug . '/([^/]+)/page/([0-9]+)/?$',
        'index.php?smacg_pp_user=$matches[1]&smacg_pp_paged=$matches[2]',
        'top'
    );
    // 預留 tab 子路徑：/u/{username}/watchlist/
    add_rewrite_rule(
        '^' . $slug . '/([^/]+)/(watchlist|ratings|badges|activity)/?$',
        'index.php?smacg_pp_user=$matches[1]&smacg_pp_tab=$matches[2]',
        'top'
    );
}

add_filter( 'query_vars', 'smacg_pp_query_vars' );
function smacg_pp_query_vars( $vars ) {
    $vars[] = 'smacg_pp_user';
    $vars[] = 'smacg_pp_paged';
    $vars[] = 'smacg_pp_tab';
    return $vars;
}

/* ============================================================
   2. 首次啟用自動 flush rewrite
   ============================================================ */
add_action( 'init', 'smacg_pp_maybe_flush_rewrite', 99 );
function smacg_pp_maybe_flush_rewrite() {
    if ( get_option( 'smacg_pp_rewrite_flushed' ) !== '1.0.0' ) {
        flush_rewrite_rules( false );
        update_option( 'smacg_pp_rewrite_flushed', '1.0.0' );
    }
}

// 切換主題時清掉 flag，下次再 flush
add_action( 'switch_theme', function () {
    delete_option( 'smacg_pp_rewrite_flushed' );
} );

/* ============================================================
   3. 解析 URL → 找 user → 載入模板
   ============================================================ */
add_action( 'template_redirect', 'smacg_pp_dispatch' );
function smacg_pp_dispatch() {
    $username = get_query_var( 'smacg_pp_user' );
    if ( empty( $username ) ) return;

    // URL 解碼（支援中文 username）
    $username = urldecode( $username );

    // 找使用者（依序試 user_login / user_nicename / display_name）
    $user = get_user_by( 'login', $username );
    if ( ! $user ) {
        $user = get_user_by( 'slug', $username );
    }
    if ( ! $user ) {
        // 最後試 user_nicename 模糊
        $u = get_users( [
            'search'         => $username,
            'search_columns' => [ 'user_nicename', 'user_login' ],
            'number'         => 1,
            'fields'         => [ 'ID' ],
        ] );
        $user = ! empty( $u ) ? get_userdata( $u[0]->ID ) : null;
    }

    // 找不到使用者 → 404
    if ( ! $user ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        // 讓 404 模板處理
        return;
    }

    // 把 user 物件存進全域，供模板用
    $GLOBALS['smacg_pp_user_obj'] = $user;

    // 載入模板
    $template = locate_template( 'page-public-profile.php' );
    if ( $template ) {
        // SEO：發送 meta tags
        add_action( 'wp_head', 'smacg_pp_render_meta_tags', 1 );

        include $template;
        exit;
    }

    // 沒模板 → 404
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
}

/* ============================================================
   4. SEO Meta Tags（OpenGraph / Twitter Card / noindex）
   ============================================================ */
function smacg_pp_render_meta_tags() {
    $user = smacg_get_public_profile_user();
    if ( ! $user ) return;

    $privacy = function_exists( 'smacg_get_user_privacy' )
        ? smacg_get_user_privacy( $user->ID )
        : [];

    $display = $user->display_name ?: $user->user_login;
    $bio     = get_user_meta( $user->ID, 'description', true );
    $url     = smacg_get_public_profile_url( $user );

    // 頭像
    $avatar  = '';
    $aid     = (int) get_user_meta( $user->ID, 'smacg_avatar_id', true );
    if ( $aid && wp_attachment_is_image( $aid ) ) {
        $img = wp_get_attachment_image_src( $aid, 'full' );
        if ( $img ) $avatar = $img[0];
    }
    if ( ! $avatar ) {
        $avatar = get_avatar_url( $user->ID, [ 'size' => 400 ] );
    }

    // 隱私帳號 → noindex
    $is_private = empty( $privacy['public_profile'] );
    if ( $is_private ) {
        echo '<meta name="robots" content="noindex,nofollow">' . "\n";
    }

    $title = sprintf( '%s 的個人頁 - %s', $display, get_bloginfo( 'name' ) );
    $desc  = $bio ?: sprintf( '%s 在 %s 的追番清單與評分', $display, get_bloginfo( 'name' ) );

    echo "\n<!-- SMACG Public Profile Meta -->\n";
    printf( "<meta property=\"og:type\" content=\"profile\">\n" );
    printf( "<meta property=\"og:title\" content=\"%s\">\n", esc_attr( $title ) );
    printf( "<meta property=\"og:description\" content=\"%s\">\n", esc_attr( wp_strip_all_tags( $desc ) ) );
    printf( "<meta property=\"og:url\" content=\"%s\">\n", esc_url( $url ) );
    printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( $avatar ) );
    printf( "<meta property=\"profile:username\" content=\"%s\">\n", esc_attr( $user->user_login ) );
    printf( "<meta name=\"twitter:card\" content=\"summary\">\n" );
    printf( "<meta name=\"twitter:title\" content=\"%s\">\n", esc_attr( $title ) );
    printf( "<meta name=\"twitter:description\" content=\"%s\">\n", esc_attr( wp_strip_all_tags( $desc ) ) );
    printf( "<meta name=\"twitter:image\" content=\"%s\">\n", esc_url( $avatar ) );
    printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );
    echo "<!-- /SMACG Public Profile Meta -->\n\n";
}

/* ============================================================
   5. 提供給模板與其他檔案的 helper 函式
   ============================================================ */

/**
 * 取得任一使用者的公開頁 URL
 *
 * @param int|string|WP_User $user_id_or_login user ID / login / WP_User
 * @return string URL（永遠回 http(s)://...，找不到就回首頁）
 */
function smacg_get_public_profile_url( $user_id_or_login ) {
    $user = null;
    if ( $user_id_or_login instanceof WP_User ) {
        $user = $user_id_or_login;
    } elseif ( is_numeric( $user_id_or_login ) ) {
        $user = get_userdata( (int) $user_id_or_login );
    } elseif ( is_string( $user_id_or_login ) ) {
        $user = get_user_by( 'login', $user_id_or_login );
    }
    if ( ! $user ) return home_url( '/' );

    $slug = SMACG_PUBLIC_PROFILE_SLUG;
    // 優先用 user_nicename（permalink-safe），fallback user_login
    $name = $user->user_nicename ?: $user->user_login;

    return home_url( '/' . $slug . '/' . rawurlencode( $name ) . '/' );
}

/**
 * 在公開頁內取得當前正在顯示的 user 物件
 *
 * @return WP_User|null
 */
function smacg_get_public_profile_user() {
    return $GLOBALS['smacg_pp_user_obj'] ?? null;
}

/**
 * 判斷當前頁面是否為公開個人頁
 *
 * @return bool
 */
function smacg_is_public_profile_page() {
    return ! empty( $GLOBALS['smacg_pp_user_obj'] );
}

/**
 * 判斷觀看者能否看到某使用者的某區塊
 *
 * @param int    $owner_id  個人頁所屬使用者
 * @param string $section   區塊名：profile / watchlist / ratings / activity / email
 * @return bool
 */
function smacg_can_view_profile_section( $owner_id, $section ) {
    $owner_id = (int) $owner_id;
    if ( ! $owner_id ) return false;

    // 自己看自己一律可以
    if ( get_current_user_id() === $owner_id ) {
        return true;
    }

    $privacy = function_exists( 'smacg_get_user_privacy' )
        ? smacg_get_user_privacy( $owner_id )
        : [];

    switch ( $section ) {
        case 'profile':
            return ! empty( $privacy['public_profile'] );
        case 'watchlist':
            return ! empty( $privacy['public_profile'] )
                && ! empty( $privacy['public_watchlist'] );
        case 'ratings':
            return ! empty( $privacy['public_profile'] );
        case 'activity':
            return ! empty( $privacy['public_profile'] );
        case 'email':
            return ! empty( $privacy['show_email'] );
        case 'continue_watching':
            return ! empty( $privacy['public_profile'] )
                && ! empty( $privacy['show_continue_watching'] );
        default:
            return false;
    }
}

/**
 * 判斷觀看者是否為頁面擁有者本人
 *
 * @return bool
 */
function smacg_pp_is_owner() {
    $user = smacg_get_public_profile_user();
    if ( ! $user ) return false;
    return get_current_user_id() === (int) $user->ID;
}

/* ============================================================
   6. 新使用者預設隱私：public_profile = 1（開）
   ------------------------------------------------------------
   依使用者決定：新註冊預設「開」個人頁
   ============================================================ */
add_action( 'user_register', 'smacg_pp_set_default_privacy', 20 );
function smacg_pp_set_default_privacy( $user_id ) {
    $existing = get_user_meta( $user_id, 'smacg_privacy', true );
    if ( ! empty( $existing ) ) return;  // 已有設定就不覆蓋

    update_user_meta( $user_id, 'smacg_privacy', [
        'public_profile'         => 1,  // 公開個人頁（開）
        'public_watchlist'       => 1,  // 公開清單（開）
        'show_email'             => 0,  // 顯示 email（關，預設保護隱私）
        'show_continue_watching' => 1,  // 繼續觀看（開）
    ] );
}

/* ============================================================
   7. 後台連結（admin bar 加「公開頁」捷徑）
   ============================================================ */
add_action( 'admin_bar_menu', 'smacg_pp_admin_bar_link', 100 );
function smacg_pp_admin_bar_link( $wp_admin_bar ) {
    if ( ! is_user_logged_in() ) return;
    if ( ! current_user_can( 'read' ) ) return;

    $wp_admin_bar->add_node( [
        'id'    => 'smacg-public-profile',
        'title' => '👁 查看公開頁',
        'href'  => smacg_get_public_profile_url( get_current_user_id() ),
        'meta'  => [ 'title' => '查看你的公開個人頁' ],
    ] );
}
