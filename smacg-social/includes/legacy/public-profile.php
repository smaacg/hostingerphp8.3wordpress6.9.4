<?php
/**
 * Public Profile System - /u/{username}/
 *
 * @package weixiaoacg
 * @subpackage PublicProfile
 * @version 1.2.0 (2026-05-16)
 *
 * v1.2.0 變更（2026-05-16）：
 *   - 新增 /u/{username}/(followers|following)/ rewrite 規則（含分頁）
 *   - 新增 query var smacg_pp_section（值：'followers' | 'following'）
 *   - smacg_pp_dispatch() 偵測 followers/following 子頁時，
 *     不載入 page-public-profile.php，改由 followers-page.php 接管渲染。
 *   - smacg_is_public_profile_page() 自動對 followers/following 頁回傳 true
 *     （因為仍會設定 $GLOBALS['smacg_pp_user_obj']），
 *     使主題 setup-enqueue.php 的 public-profile.css/js 自動載入。
 *   - 【Bug 修復】Section 6 新用戶預設隱私改寫扁平裸 key
 *     （public_profile / public_watchlist / show_email / show_continue_watching），
 *     與 member-ajax.php 的 smacg_update_privacy 寫入位置對齊。
 *     原本寫入 'smacg_privacy' 陣列 meta 的方式與 settings 寫入位置不一致，
 *     導致 smacg_can_view_profile_section() 讀不到註冊預設值。
 *     舊用戶資料由 privacy.php 的 smacg_get_user_privacy() 自動相容讀取與遷移。
 *
 * v1.1.0 變更：
 *   - Bug #10 修正：移除模糊比對 fallback，避免 /u/taro/ 撈到 taro2
 *   - Bug #11 修正：rewrite flush flag 改用 SMACG_SOCIAL_VERSION，升版自動 flush
 *   - Bug #12 修正：移除自前 canonical，改 hook 進 Yoast / Rank Math
 *
 * 提供公開個人頁功能：
 * - URL: /u/{username}/
 * - URL: /u/{username}/(followers|following)/         (v1.2.0)
 * - URL: /u/{username}/(followers|following)/page/{n}/ (v1.2.0)
 * - 訪客可看（依隱私設定過濾內容）
 * - 完整 SEO meta（OpenGraph / Twitter Card）
 * - 條件式 noindex（私人帳號 / 不存在使用者）
 *
 * Hook 流程：
 *   init → 註冊 rewrite rule + query var
 *   parse_request → 解析 username → user_id
 *   template_redirect → 主頁載入 page-public-profile.php；
 *                       followers/following 由 followers-page.php 接管。
 *
 * 提供給其他檔案的 helper：
 *   smacg_get_public_profile_url( $user_id_or_login )
 *   smacg_get_public_profile_user()
 *   smacg_is_public_profile_page()
 *   smacg_can_view_profile_section( $user_id, $section )
 *   smacg_pp_is_owner()
 *   smacg_pp_has_seo_plugin()
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

    // 主頁：/u/{username}/
    add_rewrite_rule(
        '^' . $slug . '/([^/]+)/?$',
        'index.php?smacg_pp_user=$matches[1]',
        'top'
    );

    // 主頁分頁：/u/{username}/page/{n}/
    add_rewrite_rule(
        '^' . $slug . '/([^/]+)/page/([0-9]+)/?$',
        'index.php?smacg_pp_user=$matches[1]&smacg_pp_paged=$matches[2]',
        'top'
    );

    // Tabs：/u/{username}/(watchlist|ratings|badges|activity)/
    add_rewrite_rule(
        '^' . $slug . '/([^/]+)/(watchlist|ratings|badges|activity)/?$',
        'index.php?smacg_pp_user=$matches[1]&smacg_pp_tab=$matches[2]',
        'top'
    );

    // v1.2.0：Followers / Following 列表頁
    add_rewrite_rule(
        '^' . $slug . '/([^/]+)/(followers|following)/?$',
        'index.php?smacg_pp_user=$matches[1]&smacg_pp_section=$matches[2]',
        'top'
    );

    // v1.2.0：Followers / Following 列表分頁
    add_rewrite_rule(
        '^' . $slug . '/([^/]+)/(followers|following)/page/([0-9]+)/?$',
        'index.php?smacg_pp_user=$matches[1]&smacg_pp_section=$matches[2]&smacg_pp_paged=$matches[3]',
        'top'
    );
}

add_filter( 'query_vars', 'smacg_pp_query_vars' );
function smacg_pp_query_vars( $vars ) {
    $vars[] = 'smacg_pp_user';
    $vars[] = 'smacg_pp_paged';
    $vars[] = 'smacg_pp_tab';
    $vars[] = 'smacg_pp_section';   // v1.2.0：'followers' | 'following'
    return $vars;
}

/* ============================================================
   2. 首次啟用 / 版本變更時自動 flush rewrite
   ------------------------------------------------------------
   v1.1.0：用 SMACG_SOCIAL_VERSION 比對，升版會自動 flush
   ============================================================ */
add_action( 'init', 'smacg_pp_maybe_flush_rewrite', 99 );
function smacg_pp_maybe_flush_rewrite() {
    $current = defined( 'SMACG_SOCIAL_VERSION' ) ? SMACG_SOCIAL_VERSION : '0.0.0';
    if ( get_option( 'smacg_pp_rewrite_flushed' ) !== $current ) {
        flush_rewrite_rules( false );
        update_option( 'smacg_pp_rewrite_flushed', $current );
    }
}

// 切換主題時清掉 flag，下次再 flush
add_action( 'switch_theme', function () {
    delete_option( 'smacg_pp_rewrite_flushed' );
} );

/* ============================================================
   3. 解析 URL → 找 user → 載入模板
   ------------------------------------------------------------
   v1.1.0：移除模糊比對 fallback（避免 /u/taro/ 跑去顯示 taro2）
   v1.2.0：偵測 smacg_pp_section（followers/following）時，
           只解析 user 並設定全域變數，模板載入交給 followers-page.php
   ============================================================ */
add_action( 'template_redirect', 'smacg_pp_dispatch' );
function smacg_pp_dispatch() {
    $username = get_query_var( 'smacg_pp_user' );
    if ( empty( $username ) ) return;

    // URL 解碼（支援中文 username）
    $username = urldecode( $username );

    // 1) 完全匹配 user_login
    $user = get_user_by( 'login', $username );

    // 2) 完全匹配 user_nicename (slug)
    if ( ! $user ) {
        $user = get_user_by( 'slug', $username );
    }

    // 找不到 → 直接 404，不再做模糊比對（避免誤指）
    if ( ! $user ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        return;
    }

    // 把 user 物件存進全域，供模板用
    // 不論主頁、tab、followers/following 子頁，都需要此全域變數，
    // 這也讓 smacg_is_public_profile_page() 對所有子頁自動回傳 true，
    // 主題 setup-enqueue.php 內 public-profile.css/js 因此自動沿用。
    $GLOBALS['smacg_pp_user_obj'] = $user;

    // v1.2.0：followers/following 子頁不由本檔載入模板，
    //         交給 followers-page.php 的 template_redirect 處理（priority 11）。
    //         本檔仍負責設定 user 全域變數與 SEO meta hook。
    $section = get_query_var( 'smacg_pp_section' );
    if ( $section === 'followers' || $section === 'following' ) {
        // 仍掛 SEO meta，但標題與描述會由 followers-page.php 內的 filter 覆蓋
        add_action( 'wp_head', 'smacg_pp_render_meta_tags', 1 );
        add_filter( 'wpseo_canonical',                       'smacg_pp_filter_seo_canonical', 10, 1 );
        add_filter( 'rank_math/frontend/canonical',          'smacg_pp_filter_seo_canonical', 10, 1 );
        add_filter( 'aioseo_canonical_url',                  'smacg_pp_filter_seo_canonical', 10, 1 );
        add_filter( 'wpseo_opengraph_title',                 'smacg_pp_filter_seo_title', 10, 1 );
        add_filter( 'rank_math/opengraph/facebook/og_title', 'smacg_pp_filter_seo_title', 10, 1 );
        return; // ← 不 include 主模板
    }

    // 主頁與 tab 子頁：載入既有模板
    $template = locate_template( 'page-public-profile.php' );
    if ( $template ) {
        // SEO：發送 meta tags
        add_action( 'wp_head', 'smacg_pp_render_meta_tags', 1 );

        // v1.1.0：覆寫 Yoast / Rank Math 的 canonical
        add_filter( 'wpseo_canonical',                 'smacg_pp_filter_seo_canonical', 10, 1 );
        add_filter( 'rank_math/frontend/canonical',    'smacg_pp_filter_seo_canonical', 10, 1 );
        add_filter( 'aioseo_canonical_url',            'smacg_pp_filter_seo_canonical', 10, 1 );

        // 同時讓它們的 OG tags 用我們的資料
        add_filter( 'wpseo_opengraph_title',           'smacg_pp_filter_seo_title', 10, 1 );
        add_filter( 'rank_math/opengraph/facebook/og_title', 'smacg_pp_filter_seo_title', 10, 1 );

        include $template;
        exit;
    }

    // 沒模板 → 404
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
}

/* ============================================================
   4. SEO Meta Tags
   ------------------------------------------------------------
   v1.1.0：
     - 偵測到 Yoast / Rank Math / AIOSEO 啟用時，不自前 echo canonical
       （改由上面的 filter 提供 URL）
     - OG / Twitter tags 仍照發（這些 SEO 外掛通常會跳過 user 頁面）
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

    // v1.1.0：只有在沒裝 SEO 外掛時才自前發 canonical
    if ( ! smacg_pp_has_seo_plugin() ) {
        printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );
    }
    echo "<!-- /SMACG Public Profile Meta -->\n\n";
}

/**
 * v1.1.0 helper：偵測是否有主流 SEO 外掛
 */
function smacg_pp_has_seo_plugin() {
    return defined( 'WPSEO_VERSION' )                 // Yoast
        || defined( 'RANK_MATH_VERSION' )              // Rank Math
        || defined( 'AIOSEO_VERSION' )                 // All in One SEO
        || class_exists( 'WPSEO_Frontend' )
        || class_exists( 'RankMath' );
}

/**
 * v1.1.0 filter：覆寫 SEO 外掛的 canonical URL
 */
function smacg_pp_filter_seo_canonical( $canonical ) {
    if ( ! smacg_is_public_profile_page() ) return $canonical;
    $user = smacg_get_public_profile_user();
    if ( ! $user ) return $canonical;
    return smacg_get_public_profile_url( $user );
}

/**
 * v1.1.0 filter：覆寫 SEO 外掛的 OG title
 */
function smacg_pp_filter_seo_title( $title ) {
    if ( ! smacg_is_public_profile_page() ) return $title;
    $user = smacg_get_public_profile_user();
    if ( ! $user ) return $title;
    $display = $user->display_name ?: $user->user_login;
    return sprintf( '%s 的個人頁 - %s', $display, get_bloginfo( 'name' ) );
}

/* ============================================================
   5. 提供給模板與其他檔案的 helper 函式
   ============================================================ */

/**
 * 取得任一使用者的公開頁 URL
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
    $name = $user->user_nicename ?: $user->user_login;

    return home_url( '/' . $slug . '/' . rawurlencode( $name ) . '/' );
}

/**
 * 在公開頁內取得當前正在顯示的 user 物件
 */
function smacg_get_public_profile_user() {
    return $GLOBALS['smacg_pp_user_obj'] ?? null;
}

/**
 * 判斷當前頁面是否為公開個人頁
 *
 * v1.2.0：因為 smacg_pp_dispatch() 對 followers/following 子頁也會設定
 *         $GLOBALS['smacg_pp_user_obj']，本函式對所有子頁自動回傳 true，
 *         使主題 setup-enqueue.php 的 public-profile.css/js 沿用至 followers/following 頁。
 */
function smacg_is_public_profile_page() {
    return ! empty( $GLOBALS['smacg_pp_user_obj'] );
}

/**
 * 判斷觀看者能否看到某使用者的某區塊
 */
function smacg_can_view_profile_section( $owner_id, $section ) {
    $owner_id = (int) $owner_id;
    if ( ! $owner_id ) return false;

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
 */
function smacg_pp_is_owner() {
    $user = smacg_get_public_profile_user();
    if ( ! $user ) return false;
    return get_current_user_id() === (int) $user->ID;
}

/* ============================================================
   6. 新使用者預設隱私
   ------------------------------------------------------------
   v1.2.0 修復：改寫扁平裸 key，與 member-ajax.php 的 smacg_update_privacy
   寫入位置對齊。
   原本寫入 'smacg_privacy' 陣列 meta 的方式與 settings 寫入位置不同，
   導致 smacg_can_view_profile_section() 讀不到註冊預設值。
   舊用戶資料由 privacy.php 的 smacg_get_user_privacy() 自動相容讀取與遷移。
   ============================================================ */
add_action( 'user_register', 'smacg_pp_set_default_privacy', 20 );
function smacg_pp_set_default_privacy( $user_id ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) return;

    // v1.2.0：優先呼叫資料層提供的預設值（privacy.php），確保單一資料源。
    $defaults = function_exists( 'smacg_get_user_privacy_defaults' )
        ? smacg_get_user_privacy_defaults()
        : [
            'public_profile'         => '1',
            'public_watchlist'       => '1',
            'show_email'             => '0',
            'show_continue_watching' => '1',
        ];

    // 僅在欄位不存在時寫入，避免覆寫其他來源預先寫入的偏好。
    foreach ( $defaults as $key => $val ) {
        $existing = get_user_meta( $user_id, $key, true );
        if ( $existing === '' || $existing === false || $existing === null ) {
            update_user_meta( $user_id, $key, $val );
        }
    }
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

/* ============================================================
   8. Hero 區等級徽章注入（Batch 2A-4）
   ============================================================ */
add_action( 'wp_footer', function () {
    if ( ! smacg_is_public_profile_page() ) return;

    // v1.2.0：followers/following 子頁的 hero 由 followers-page.php 自行渲染，
    //         不需要此 JS 注入；只在主頁與 tab 子頁執行。
    $section = get_query_var( 'smacg_pp_section' );
    if ( $section === 'followers' || $section === 'following' ) return;

    $user = smacg_get_public_profile_user();
    if ( ! $user ) return;

    $badge = function_exists( 'smacg_render_level_badge' )
        ? smacg_render_level_badge( $user->ID, 'lg', [ 'show_job' => true ] )
        : '';
    if ( ! $badge ) return;
    ?>
    <script>
    (function(){
        if (document.querySelector('.smacg-pp-hero .smacg-lvbadge')) return;
        var anchor = document.querySelector('.smacg-pp-hero h1, .smacg-pp-hero .pp-name, .pp-hero h1');
        if (!anchor) return;
        var wrap = document.createElement('div');
        wrap.className = 'smacg-pp-hero__lvbadge';
        wrap.style.marginTop = '8px';
        wrap.innerHTML = <?php echo wp_json_encode( $badge ); ?>;
        anchor.parentNode.insertBefore(wrap, anchor.nextSibling);
    })();
    </script>
    <?php
}, 99 );
