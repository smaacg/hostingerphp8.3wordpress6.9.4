<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package weixiaoacg
 * @version 1.5.0
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數
   ============================================================ */
define( 'weixiaoacg_VERSION',    '1.5.0' );
define( 'weixiaoacg_THEME_URL',  get_stylesheet_directory_uri() );
define( 'weixiaoacg_THEME_DIR',  get_stylesheet_directory() );
define( 'weixiaoacg_PLUGIN_URL', plugins_url( 'weixiaoacg-core' ) );

define( 'SMACG_POINT_FAVORITE',  5  );
define( 'SMACG_POINT_WANT',      1  );
define( 'SMACG_POINT_WATCHING',  3  );
define( 'SMACG_POINT_COMPLETED', 8  );
define( 'SMACG_POINT_FULLCLEAR', 10 );
define( 'SMACG_POINT_EPISODE',   1  );
define( 'SMACG_POINT_READ',      2  );
define( 'SMACG_POINT_COMMENT',   3  );
define( 'SMACG_POINT_LOGIN',     1  );

/* ============================================================
   主題設定
   ============================================================ */
add_action( 'after_setup_theme', function() {
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'html5', ['search-form','comment-form','comment-list','gallery','caption','style','script'] );
    add_theme_support( 'custom-logo', ['height'=>40,'width'=>180,'flex-height'=>true,'flex-width'=>true] );
    add_theme_support( 'menus' );

    foreach ([
        'weixiaoacg-cover'  => [300, 420, true],
        'weixiaoacg-banner' => [1280,400, true],
        'weixiaoacg-thumb'  => [160, 224, true],
        'anime-thumb'   => [300, 420, true],
        'news-thumb'    => [800, 450, true],
        'season-thumb'  => [180, 260, true],
    ] as $name => [$w,$h,$c]) add_image_size($name,$w,$h,$c);

    load_child_theme_textdomain( 'weixiaoacg', weixiaoacg_THEME_DIR . '/languages' );
} );

/* ============================================================
   選單
   ============================================================ */
add_action( 'after_setup_theme', function() {
    register_nav_menus([
        'primary-menu' => '主導覽選單', 'footer-menu'  => '頁腳選單',
        'more-menu'    => '更多下拉選單','primary'     => '主選單',
        'secondary'    => '次要選單',   'footer-col-1' => '頁腳欄位 1',
        'footer-col-2' => '頁腳欄位 2', 'footer-col-3' => '頁腳欄位 3',
        'footer-col-4' => '頁腳欄位 4',
    ]);
} );

add_action( 'admin_init', function() {
    $locs = get_nav_menu_locations();
    foreach ( array_keys( get_registered_nav_menus() ) as $loc )
        if ( empty($locs[$loc]) ) $locs[$loc] = 0;
    set_theme_mod( 'nav_menu_locations', $locs );
} );

add_filter( 'wp_nav_menu_objects', function($items,$args) {
    $in = is_user_logged_in();
    foreach ( $items as $k => $item ) {
        $c = (array)$item->classes;
        if ( in_array('logged-in-only',$c) && !$in ) unset($items[$k]);
        if ( in_array('logged-out-only',$c) && $in  ) unset($items[$k]);
    }
    return $items;
}, 10, 2 );

/* 防止 LiteSpeed / CDN 快取 UM 用戶個人頁 + 自訂會員頁 */
add_action('template_redirect', function() {
    $is_um_page = function_exists('um_is_core_page') && um_is_core_page('user');
    $is_member  = is_page_template('page-member.php');
    
    if ($is_um_page || $is_member) {
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('Surrogate-Control: no-store');
    }
}, 1);


/* ============================================================
   自訂 Nav Walker
   ============================================================ */
class weixiaoacg_Nav_Walker extends Walker_Nav_Menu {
    public function start_el(&$out,$item,$depth=0,$args=null,$id=0) {
        $c    = (array)$item->classes;
        $curr = in_array('current-menu-item',$c)||in_array('current-page-ancestor',$c)||in_array('current-menu-ancestor',$c);
        $drop = in_array('menu-item-has-children',$c);
        $icon = trim($item->description??'');
        $tgt  = $item->target ? ' target="'.esc_attr($item->target).'"' : '';
        if ($depth===0) {
            $out .= '<div class="nav-item'.($drop?' has-dropdown':'').'">';
            $out .= '<a href="'.esc_url($item->url).'" class="nav-link'.($curr?' active':'').'"'.$tgt.($drop?' aria-haspopup="true" aria-expanded="false"':'').'>';
            if ($icon) $out .= '<i class="'.esc_attr($icon).'" aria-hidden="true"></i> ';
            $out .= esc_html($item->title);
            if ($drop) $out .= ' <span class="nav-arrow-wrap" aria-hidden="true"><i class="fa-solid fa-chevron-down nav-arrow"></i></span>';
            $out .= '</a>';
        } else {
            $out .= '<a href="'.esc_url($item->url).'" class="nav-dropdown-item'.($curr?' active':'').'"'.$tgt.'>';
            if ($icon) $out .= '<i class="'.esc_attr($icon).'" aria-hidden="true"></i> ';
            $out .= esc_html($item->title).'</a>';
        }
    }
    public function end_el(&$out,$item,$depth=0,$args=null) { if($depth===0) $out.='</div>'; }
    public function start_lvl(&$out,$depth=0,$args=null) { $out.='<div class="nav-dropdown">'; }
    public function end_lvl(&$out,$depth=0,$args=null)   { $out.='</div>'; }
}

/* ============================================================
   強制使用子主題 header.php
   ============================================================ */
add_filter( 'blocksy:header:is-enabled', '__return_false' );
add_filter( 'blocksy_hero_enabled',      '__return_false' );
add_action( 'get_header', function() {
    $t = locate_template('header.php');
    if ($t) load_template($t);
}, 1 );

/* ============================================================
   jQuery → 3.6.4
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
   ★ 改:Font Awesome — 提到最前面，優先級 5（早於所有樣式）
   ============================================================ */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'weixiaoacg-fa6',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
        [],
        '6.5.0'
    );
}, 5 );

/* ============================================================
   ★ 改:樣式載入 — 整理結構，並把 anime 頁面 CSS 整合
   ============================================================ */
add_action( 'wp_enqueue_scripts', function() {

    /* ── 基礎樣式 ── */
    wp_enqueue_style(
        'blocksy-parent',
        get_template_directory_uri().'/style.css',
        ['weixiaoacg-fa6'],
        wp_get_theme('blocksy')->get('Version')
    );
    wp_enqueue_style(
        'weixiaoacg-fonts',
        'https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700;800&display=swap',
        [],
        null
    );

    /* ── 共用樣式 ── */
    foreach ([
        'weixiaoacg-glass' => ['glass.css', ['blocksy-parent']],
        'weixiaoacg-style' => ['style.css',  ['weixiaoacg-glass']],
    ] as $h => [$f,$dep]) {
        $p = weixiaoacg_THEME_DIR.'/assets/css/'.$f;
        if (file_exists($p)) wp_enqueue_style($h, weixiaoacg_THEME_URL.'/assets/css/'.$f, $dep, filemtime($p));
    }

    /* ── 條件式頁面樣式 ── */
    $is_um_user = function_exists('um_is_core_page') && (um_is_core_page('user') || get_query_var('um_user'));
    $cond = [];
    if ( is_page('news')   || is_page_template('page-news.php') )       $cond['weixiaoacg-news']       = 'news.css';
    if ( is_page('season') || is_page_template('page-season.php') )     $cond['weixiaoacg-season']     = 'season.css';
    if ( is_page_template('page-ranking.php') )                          $cond['weixiaoacg-ranking']    = 'ranking.css';
    if ( is_page_template('page-anime-list.php') )                       $cond['weixiaoacg-anime-list'] = 'anime-list.css';
    if ( is_page_template('page-music.php') )                            $cond['weixiaoacg-music']      = 'music.css';
    if ( is_page_template('page-cosplay.php') )                          $cond['weixiaoacg-cosplay']    = 'cosplay.css';
    if ( is_search() )                                                   $cond['weixiaoacg-search']     = 'search.css';
    if ( is_404() )                                                      $cond['weixiaoacg-404']        = '404.css';
    if ( is_page(['about','contact','disclaimer','sources','privacy','terms','join']) || is_page_template('page-join.php') )
                                                                         $cond['weixiaoacg-static']     = 'static.css';
    if ( is_page_template('page-member.php') || $is_um_user )           $cond['weixiaoacg-member']     = 'member.css';

    $is_account_page = is_page(1527) || is_page('account') ||
                       (function_exists('um_is_core_page') && um_is_core_page('account')) ||
                       (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/account') !== false);
    if ( $is_account_page ) $cond['weixiaoacg-account'] = 'account.css';

    foreach ($cond as $h => $f) {
        $p = weixiaoacg_THEME_DIR.'/assets/css/'.$f;
        if (file_exists($p)) wp_enqueue_style($h, weixiaoacg_THEME_URL.'/assets/css/'.$f, ['weixiaoacg-style'], filemtime($p));
    }

    /* ── ★ 改:Anime Single 頁面樣式（整合載入順序） ── */
    if ( is_singular('anime') ) {

        // 1) Plugin 主樣式 anime-single.css
        $anime_css = WP_PLUGIN_DIR.'/anime-sync-pro/public/assets/css/anime-single.css';
        if (file_exists($anime_css)) {
            wp_enqueue_style(
                'weixiaoacg-anime',
                plugins_url('anime-sync-pro/public/assets/css/anime-single.css'),
                ['weixiaoacg-style','weixiaoacg-fa6'],   // ★ 確保 FA 先載
                filemtime($anime_css)
            );
        }

        // 2) ★ 新增:追蹤列獨立樣式 track-bar.css
        $tb_css = weixiaoacg_THEME_DIR.'/assets/css/track-bar.css';
        if (file_exists($tb_css)) {
            wp_enqueue_style(
                'smacg-track-bar',
                weixiaoacg_THEME_URL.'/assets/css/track-bar.css',
                ['weixiaoacg-anime'],   // ★ 必須在 anime-single.css 之後載入以覆寫
                filemtime($tb_css)
            );
        }

        // 3) anime-status.css（如果有）
        $p = weixiaoacg_THEME_DIR.'/assets/css/anime-status.css';
        if (file_exists($p)) {
            wp_enqueue_style(
                'smacg-anime-status',
                weixiaoacg_THEME_URL.'/assets/css/anime-status.css',
                ['smacg-track-bar'],
                filemtime($p)
            );
        }
    }

    /* ── 後台同步樣式 ── */
    $p = weixiaoacg_THEME_DIR.'/assets/css/admin-sync.css';
    if (file_exists($p)) wp_enqueue_style('weixiaoacg-admin-sync', weixiaoacg_THEME_URL.'/assets/css/admin-sync.css', ['weixiaoacg-style'], filemtime($p));

}, 10 );

add_action( 'admin_enqueue_scripts', function() {
    $p = weixiaoacg_THEME_DIR.'/assets/css/admin-sync.css';
    if (file_exists($p)) wp_enqueue_style('weixiaoacg-admin', weixiaoacg_THEME_URL.'/assets/css/admin-sync.css', [], filemtime($p));
} );

/* ============================================================
   ★ 改:LiteSpeed / 快取外掛排除 Font Awesome（避免被合併破壞）
   ============================================================ */
add_filter('litespeed_optm_css_minify', function($content) {
    return $content;
}, 10);

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
        'weixiaoacg-nav'           => ['nav.js',            ['weixiaoacg-utils']],
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
        'weixiaoacg-anime-js'    => 'anime.js',
        'smacg-anime-status' => 'anime-status.js',
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
   輔助函式
   ============================================================ */
function weixiaoacg_get_user_level_int(): int {
    if (!is_user_logged_in()) return 0;
    $uid = get_current_user_id();
    $lv  = (int)get_user_meta($uid,'weixiaoacg_user_level',true);
    if (!$lv && function_exists('um_user')) {
        $role = um_user('role');
        $lv = match($role) { 'weixiaoacg_vip'=>3,'weixiaoacg_pro'=>2,default=>1 };
    }
    return $lv ?: 1;
}

function weixiaoacg_get_user_points(int $uid=0): int {
    return (int)get_user_meta($uid?:get_current_user_id(),'weixiaoacg_points',true);
}

if (!function_exists('weixiaoacg_get_news_thumb')) {
    function weixiaoacg_get_news_thumb(int $post_id,string $size='news-thumb'): string {
        if ($url=get_the_post_thumbnail_url($post_id,$size)) return $url;
        $c = get_post($post_id)->post_content??'';
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/',$c,$m)) return $m[1];
        return function_exists('get_field') ? (get_field('weixiaoacg_cover_url',$post_id)?:'') : '';
    }
}

if (!function_exists('weixiaoacg_get_anilist'))  { function weixiaoacg_get_anilist($id)     { return null; } }
if (!function_exists('weixiaoacg_get_bangumi'))  { function weixiaoacg_get_bangumi($id,$t=2){ return null; } }

add_filter('excerpt_length', fn()=>60);
add_filter('excerpt_more',   fn()=>'…');

add_filter('get_search_form', function() {
    ob_start(); ?>
    <form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
        <div class="search-box glass-light">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="search" id="search-input" name="s"
                   placeholder="<?php esc_attr_e('搜尋動畫、角色、聲優、新聞…','weixiaoacg'); ?>"
                   value="<?php echo esc_attr(get_search_query()); ?>" autocomplete="off"/>
            <button type="submit" class="btn-icon btn-ghost" aria-label="<?php esc_attr_e('搜尋','weixiaoacg'); ?>">
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </form>
    <?php return ob_get_clean();
});

/* ============================================================
   Widgets
   ============================================================ */
add_action('widgets_init', function() {
    register_sidebar(['name'=>'頁腳 Widget 區 1','id'=>'footer-1',
        'before_widget'=>'<div class="footer-widget">','after_widget'=>'</div>',
        'before_title'=>'<h5 class="footer-widget-title">','after_title'=>'</h5>']);
    register_sidebar(['name'=>'側欄 Widget 區','id'=>'sidebar-1',
        'before_widget'=>'<div class="sidebar-card glass-mid">','after_widget'=>'</div>',
        'before_title'=>'<div class="rank-sidebar-title">','after_title'=>'</div>']);
});

/* ============================================================
   Ultimate Member 整合
   ============================================================ */

/* ① 強制載入 UM JS */
add_action('wp_enqueue_scripts', function() {
    if (!function_exists('um_is_core_page')) return;
    $is_um = um_is_core_page('user') || get_query_var('um_user') || um_is_core_page('account');
    $is_member_tpl = is_page_template('page-member.php');
    if ($is_um || $is_member_tpl) {
        wp_enqueue_script('um_scripts');
        wp_enqueue_script('um_profile');
        wp_enqueue_script('um_account');
        wp_enqueue_script('um_crop');
        wp_enqueue_script('um_modal');
        wp_enqueue_script('um_fileupload');
        wp_enqueue_style('um_styles');
        wp_enqueue_style('um_profile');
        wp_enqueue_style('um_account');
        wp_enqueue_style('um_crop');
        wp_enqueue_style('um_misc');
        wp_enqueue_style('um_modal');
        wp_enqueue_style('um_fileupload');
    }
}, 20);

/* ② 自訂會員頁模板 */
add_filter('template_include', function($tpl) {
    if (!function_exists('um_is_core_page')) return $tpl;
    if (um_is_core_page('user') || get_query_var('um_user')) {
        $c = weixiaoacg_THEME_DIR.'/page-member.php';
        if (file_exists($c)) return $c;
    }
    return $tpl;
}, 99);

/* ③ 停用 UM 前端樣式（個人資料頁） */
add_action('wp_enqueue_scripts', function() {
    if (!function_exists('um_is_core_page')) return;
    if ((um_is_core_page('user') || get_query_var('um_user')) && !um_is_core_page('account')) {
        wp_dequeue_style('um_styles');
        wp_dequeue_style('um_responsive');
        wp_dequeue_style('um_icons');
    }
}, 99);

/* ④ 隱藏 UM 後台警告 */
add_action('admin_notices', function() {
    $s = get_current_screen();
    if ($s && (strpos($s->id,'um_')!==false || strpos($s->id,'ultimate-member')!==false || $s->id==='toplevel_page_um-options'))
        remove_all_actions('admin_notices');
}, 1);

/* ⑤ 停用 UM 登入 nonce 驗證 */
add_filter( 'um_login_allow_nonce_verification', '__return_false' );

/* ⑥ UM 全站中文化 */
add_filter('gettext', function($t,$o,$d) {
    if ($d !== 'ultimate-member') return $t;
    static $map = [
        'An error has been encountered. Probably page was cached. Please try again.' => '發生錯誤，頁面可能已被快取，請重新整理後再試一次。',
        'Username or E-mail'=>'使用者名稱或電子郵件','Username or Email' =>'使用者名稱或電子郵件',
        'Password'=>'密碼','Keep me signed in'=>'保持登入狀態',
        'Sign Up'=>'註冊','Forgot your password?'=>'忘記密碼？',
        'Log In'=>'登入','Login'=>'登入',
        'The username you entered is incorrect'=>'使用者名稱輸入有誤',
        'The email you entered is incorrect'   =>'電子郵件輸入有誤',
        'The password you entered is incorrect'=>'密碼輸入有誤',
        'Invalid username or email'=>'使用者名稱或電子郵件有誤',
        'Invalid username'=>'無效的使用者名稱',
        'Invalid email address'=>'無效的電子郵件',
        'Incorrect password'=>'密碼不正確',
        'This account has been blocked'=>'此帳號已被封鎖',
        'This account is awaiting approval'=>'此帳號正在等待審核',
        'Your account has not been activated yet'=>'你的帳號尚未啟用',
        'A user could not be found with this email address'=>'找不到使用此電子郵件的使用者',
        'Your account was updated successfully.'=>'你的帳號已成功更新。',
        'Your account has been updated successfully.'=>'你的帳號已成功更新。',
        'Changes saved successfully.'=>'變更已成功儲存。',
        'Username'=>'使用者名稱','E-mail'=>'電子郵件','Email'=>'電子郵件',
        'Confirm Password'=>'確認密碼','Already have an account?'=>'已有帳號？','Register'=>'註冊',
        'Your %s must contain at least %d characters'=>'你的%s至少需要 %d 個字元',
        'Your %s must contain at least one uppercase letter'=>'你的%s至少需要一個大寫字母',
        'Your %s must contain at least one lowercase letter'=>'你的%s至少需要一個小寫字母',
        'Your %s must contain at least one number'=>'你的%s至少需要一個數字',
        'Your %s must contain at least one special character'=>'你的%s至少需要一個特殊符號',
        'Your password must contain at least %d characters' =>'你的密碼至少需要 %d 個字元',
        'Your password must contain at least %d characters.'=>'你的密碼至少需要 %d 個字元。',
        'Your password must contain at least one capital letter'=>'你的密碼至少需要一個大寫字母',
        'Your password must contain at least one capital letter.'=>'你的密碼至少需要一個大寫字母。',
        'Your password must contain at least one uppercase letter'=>'你的密碼至少需要一個大寫字母',
        'Your password must contain at least one lowercase letter'=>'你的密碼至少需要一個小寫字母',
        'Your password must contain at least one number'=>'你的密碼至少需要一個數字',
        'Your password must contain at least one special character'=>'你的密碼至少需要一個特殊符號',
        'Your username must contain at least %d characters'=>'你的使用者名稱至少需要 %d 個字元',
        'Your username must contain at least 3 characters'=>'你的使用者名稱至少需要 3 個字元',
        'password'=>'密碼','username'=>'使用者名稱','Password strength'=>'密碼強度',
        'Very Weak'=>'非常弱','Weak'=>'弱','Medium'=>'中等','Strong'=>'強','Very Strong'=>'非常強',
        'Mismatch'=>'密碼不一致','Please enter your password again'=>'請再次輸入密碼',
        'Passwords do not match'=>'兩次輸入的密碼不一致',
        'Password is too short'=>'密碼太短','Password is too weak'=>'密碼強度不足',
        'Forgot Password'=>'忘記密碼','Reset Password'=>'重設密碼',
        'Send Reset Link'=>'發送重設連結','Back to login'=>'返回登入','Back to Login'=>'返回登入',
        'About'=>'關於','Posts'=>'文章','Comments'=>'留言','Friends'=>'朋友',
        'Photos'=>'相片','Videos'=>'影片','Groups'=>'群組','Forums'=>'論壇',
        'Change your cover photo'=>'更換封面照片','Upload a cover photo'=>'上傳封面照片',
        'Remove cover photo'=>'移除封面照片','Change your profile photo'=>'更換個人頭像',
        'Upload a profile photo'=>'上傳個人頭像','Remove profile photo'=>'移除個人頭像',
        '( max: %s/MB )'=>'（最大：%s MB）','( max: %s MB )'=>'（最大：%s MB）',
        'Drop image here or click to upload'=>'拖曳圖片至此或點擊上傳',
        'Drop file here or click to upload'=>'拖曳檔案至此或點擊上傳',
        'Change Photo'=>'更換照片','Upload Photo'=>'上傳照片',
        'Tell us a bit about yourself...'=>'介紹一下你自己…',
        'Tell us a bit about yourself…'=>'介紹一下你自己…',
        'Biography'=>'個人簡介','No biography yet.'=>'尚未填寫個人簡介。',
        'Edit my profile'=>'編輯個人資料','Edit Profile'=>'編輯個人資料',
        'First Name'=>'名字','Last Name'=>'姓氏','Display Name'=>'顯示名稱',
        'No posts found.'=>'尚無文章。','No comments found.'=>'尚無留言。',
        'My Bookmarks'=>'我的書籤','Report'=>'檢舉','Block'=>'封鎖','Unblock'=>'取消封鎖',
        'Message'=>'訊息','Follow'=>'追蹤','Unfollow'=>'取消追蹤','Privacy'=>'隱私權',
        'Update Privacy Settings'=>'更新隱私設定','Profile Privacy'=>'個人資料隱私',
        'Who can view my profile?'=>'誰可以查看我的個人資料？',
        'All visitors'=>'全部使用者','All members'=>'所有會員',
        'Logged in users'=>'已登入的使用者','Only me'=>'只有我自己',
        'Show my last login?'=>'顯示我的最後登入時間？','Show last login'=>'顯示最後登入時間',
        'Download your data'=>'下載你的資料','Request Data Export'=>'請求資料匯出',
        'Export Data'=>'匯出資料','Erase your data'=>'清除你的資料',
        'Delete Account'=>'刪除帳號','Delete my account'=>'刪除我的帳號',
        'Current Password'=>'目前密碼','New Password'=>'新密碼','Confirm New Password'=>'確認新密碼',
        'Change Password'=>'更改密碼','Update Password'=>'更新密碼',
        'Submit'=>'送出','Save Changes'=>'儲存變更','Save'=>'儲存','Update'=>'更新',
        'Update account'=>'更新帳號','Upload'=>'上傳','Remove'=>'移除','Crop'=>'裁切',
        'Apply'=>'套用','Cancel'=>'取消','Yes'=>'是','No'=>'否',
        'General'=>'一般','Account'=>'帳號','Profile'=>'個人資料','Delete'=>'刪除帳號',
        'Member Since'=>'加入時間','Role'=>'角色','Logout'=>'登出','Log Out'=>'登出',
        'Hide my profile from directory'=>'在目錄中隱藏我的個人資料',
        'Enter your current password to confirm a new export of your personal data.'=>'請輸入目前的密碼以確認匯出你的個人資料。',
        'Request data'=>'請求資料',
        'Erase of your data'=>'刪除你的資料',
        'Enter your current password to confirm the erasure of your personal data.'=>'請輸入目前的密碼以確認刪除你的個人資料。',
        'Are you sure you want to delete your account?'=>'你確定要刪除你的帳號嗎？',
        'This will erase all of your account data from the site.'=>'這將會清除你在本站的所有帳號資料。',
        'To delete your account enter your password below.'=>'請在下方輸入密碼以確認刪除帳號。',
        'Are you sure you want to delete your account? This will erase all of your account data from the site. To delete your account enter your password below.'=>'你確定要刪除你的帳號嗎？這將會清除你在本站的所有帳號資料。請在下方輸入密碼以確認刪除帳號。',
        'Are you sure you want to delete your account? This will erase all of your account data from the site. To delete your account, click on the button below.'=>'你確定要刪除你的帳號嗎？這將會清除你在本站的所有帳號資料。請點擊下方按鈕以確認刪除帳號。',
        'Upload photo'=>'上傳頭像','Change photo'=>'更換頭像','Remove photo'=>'移除頭像',
        'Change cover'=>'更換封面','Remove cover'=>'移除封面',
        'Update Account'=>'更新帳號','Update Privacy'=>'更新隱私設定',
        'Avoid indexing my profile by search engines'=>'避免搜尋引擎索引我的個人資料',
        'View profile'=>'查看個人資料','Are you sure?'=>'你確定嗎？',
    ];
    return $map[$o] ?? $t;
}, 10, 3);

add_filter('gettext_with_context', function($t,$o,$ctx,$d) {
    if ($d !== 'ultimate-member') return $t;
    static $m = ['Your %s must contain at least %d characters'=>'你的%s至少需要 %d 個字元'];
    return $m[$o] ?? $t;
}, 10, 4);

/* ⑦ UM JS 端錯誤訊息中文化 */
add_action('wp_footer', function() { ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var s = {
            'An error has been encountered. Probably page was cached. Please try again.': '發生錯誤，頁面可能已被快取，請重新整理後再試一次。',
            'Your password must contain at least one capital letter'   :'你的密碼至少需要一個大寫字母',
            'Your password must contain at least one uppercase letter' :'你的密碼至少需要一個大寫字母',
            'Your password must contain at least one lowercase letter' :'你的密碼至少需要一個小寫字母',
            'Your password must contain at least one number'           :'你的密碼至少需要一個數字',
            'Your password must contain at least one special character':'你的密碼至少需要一個特殊符號',
            'The username you entered is incorrect':'使用者名稱輸入有誤',
            'The password you entered is incorrect':'密碼輸入有誤',
            'Passwords do not match'               :'兩次輸入的密碼不一致',
            'Password is too short'                :'密碼太短'
        };
        function fix(el) {
            Object.keys(s).forEach(function(en) {
                if (el.textContent.indexOf(en) !== -1) el.textContent = el.textContent.replace(en, s[en]);
            });
        }
        new MutationObserver(function(ms) {
            ms.forEach(function(m) {
                m.addedNodes.forEach(function(n) {
                    if (n.nodeType !== 1) return;
                    n.querySelectorAll('.um-field-error,.um-notice,.um-error,.um-form-message').forEach(fix);
                    if (n.classList && (
                        n.classList.contains('um-field-error') || n.classList.contains('um-notice') ||
                        n.classList.contains('um-error')       || n.classList.contains('um-form-message')
                    )) fix(n);
                });
            });
        }).observe(document.body, {childList: true, subtree: true});
        document.querySelectorAll('.um-profile-note,.um-empty-profile,[class*="um-profile"]').forEach(function(el) {
            if (el.innerHTML.indexOf('Your profile is looking a little empty') !== -1)
                el.innerHTML = el.innerHTML.replace(
                    /Your profile is looking a little empty\. Why not <a([^>]*)>add<\/a> some information!/g,
                    '你的個人頁面看起來空空的。來<a$1>新增一些資料</a>吧！'
                );
        });
    });
    </script>
<?php }, 999);

/* ⑧ 每日登入積分 */
add_action('um_user_login', function($uid) {
    $uid = (int)$uid; if (!$uid) return;
    $today = date('Y-m-d');
    if ((string)get_user_meta($uid,'smacg_last_login_date',true) !== $today) {
        update_user_meta($uid,'smacg_last_login_date',$today);
        smacg_add_points($uid, SMACG_POINT_LOGIN, 'daily_login');
    }
});

/* ============================================================
   積分 / 等級系統
   ============================================================ */
function smacg_get_levels(): array {
    return [
        ['min'=>0,    'label'=>'🌱 新手',   'key'=>'newbie'],
        ['min'=>100,  'label'=>'⭐ 動漫迷', 'key'=>'lover'],
        ['min'=>500,  'label'=>'💫 老手',   'key'=>'veteran'],
        ['min'=>2000, 'label'=>'🔥 狂熱者', 'key'=>'fanatic'],
        ['min'=>5000, 'label'=>'👑 大師',   'key'=>'master'],
    ];
}

function smacg_get_user_level(int $uid): array {
    $pts = (int)get_user_meta($uid,'anime_total_points',true);
    $levels = smacg_get_levels(); $cur = $levels[0]; $next = null;
    foreach ($levels as $i => $l) { if ($pts >= $l['min']) { $cur = $l; $next = $levels[$i+1] ?? null; } }
    $pct = 100;
    if ($next) { $r = $next['min']-$cur['min']; $e = $pts-$cur['min']; $pct = $r > 0 ? min(100,round($e/$r*100)) : 100; }
    return ['points'=>$pts,'current'=>$cur,'next'=>$next,'progress_pct'=>$pct];
}

function smacg_add_points(int $uid, int $pts, string $reason=''): void {
    if ($pts <= 0 || !$uid) return;
    update_user_meta($uid,'anime_total_points',(int)get_user_meta($uid,'anime_total_points',true)+$pts);
    $log = json_decode(get_user_meta($uid,'anime_points_log',true)?:'[]',true);
    $log[] = ['points'=>$pts,'reason'=>$reason,'time'=>time()];
    if (count($log) > 100) $log = array_slice($log,-100);
    update_user_meta($uid,'anime_points_log',wp_json_encode($log));
}

function smacg_check_cooldown(int $uid, string $action, int $post_id): bool {
    $key = "smacg_cd_{$action}_{$post_id}";
    if (time()-(int)get_user_meta($uid,$key,true) < DAY_IN_SECONDS) return false;
    update_user_meta($uid,$key,time());
    return true;
}

add_action('comment_post', function($cid,$approved) {
    if ($approved !== 1) return;
    $c = get_comment($cid); $uid = (int)$c->user_id;
    if ($uid && smacg_check_cooldown($uid,'comment',(int)$c->comment_post_ID))
        smacg_add_points($uid, SMACG_POINT_COMMENT, "comment:{$c->comment_post_ID}");
}, 10, 2);

add_action('wp_ajax_smacg_read_article', function() {
    check_ajax_referer('smacg_nonce','nonce');
    $uid = (int)get_current_user_id(); $pid = (int)($_POST['post_id']??0);
    if ($uid && $pid && smacg_check_cooldown($uid,'read',$pid))
        smacg_add_points($uid, SMACG_POINT_READ, "read:{$pid}");
    wp_send_json_success();
});

/* ============================================================
   REST API
   ============================================================ */
add_action('rest_api_init', function() {

    register_rest_route('weixiaoacg/v1','/ranking',['methods'=>'GET','permission_callback'=>'__return_true',
        'args'=>['platform'=>['default'=>'anilist','sanitize_callback'=>'sanitize_text_field'],'period'=>['default'=>'weekly','sanitize_callback'=>'sanitize_text_field'],'limit'=>['default'=>20,'sanitize_callback'=>'absint']],
        'callback'=>function(WP_REST_Request $req) {
            $platform = $req->get_param('platform'); $limit = min($req->get_param('limit'),50);
            $field = match($platform) {'mal'=>'weixiaoacg_score_mal','bangumi'=>'weixiaoacg_score_bangumi',default=>'weixiaoacg_score_anilist'};
            $q = new WP_Query(['post_type'=>'anime','post_status'=>'publish','posts_per_page'=>$limit,'meta_key'=>$field,'orderby'=>'meta_value_num','order'=>'DESC','meta_query'=>[['key'=>$field,'compare'=>'EXISTS']]]);
            $items = []; $rank = 1;
            while ($q->have_posts()) { $q->the_post(); $pid = get_the_ID();
                $items[] = ['rank'=>$rank++,'id'=>$pid,'title_zh'=>get_field('weixiaoacg_title_zh',$pid)?:get_the_title(),'title_jp'=>get_field('weixiaoacg_title_ja',$pid),'cover'=>get_the_post_thumbnail_url($pid,'weixiaoacg-cover')?:get_field('weixiaoacg_cover_url',$pid),'score'=>(float)get_field($field,$pid),'url'=>get_permalink(),'anilist_id'=>(int)get_field('weixiaoacg_anilist_id',$pid)];
            }
            wp_reset_postdata();
            return new WP_REST_Response(['platform'=>$platform,'data'=>$items],200);
        }]);

    register_rest_route('weixiaoacg/v1','/user/favorites',['methods'=>'GET','permission_callback'=>'is_user_logged_in',
        'callback'=>function() {
            $uid = get_current_user_id();
            $items = array_values(array_filter(array_map(function($pid) {
                return $pid ? ['id'=>$pid,'title_zh'=>get_field('weixiaoacg_title_zh',$pid)?:get_the_title($pid),'cover'=>get_the_post_thumbnail_url($pid,'weixiaoacg-thumb'),'url'=>get_permalink($pid)] : null;
            },(array)(get_user_meta($uid,'weixiaoacg_favorites',true)?:[]))));
            return new WP_REST_Response(['favorites'=>$items],200);
        }]);

    register_rest_route('weixiaoacg/v1','/anime-url',['methods'=>'GET','permission_callback'=>'__return_true',
        'args'=>['ids'=>['required'=>true,'sanitize_callback'=>'sanitize_text_field']],
        'callback'=>function(WP_REST_Request $req) {
            $ids = array_filter(array_map('intval',explode(',',$req->get_param('ids'))));
            if (empty($ids)) return new WP_Error('no_ids','ids 參數必填',['status'=>400]);
            $posts = get_posts(['post_type'=>'anime','post_status'=>'publish','posts_per_page'=>count($ids),'no_found_rows'=>true,'meta_query'=>[['key'=>'anime_anilist_id','value'=>$ids,'compare'=>'IN','type'=>'NUMERIC']]]);
            $map = [];
            foreach ($posts as $p) { $al = (int)get_post_meta($p->ID,'anime_anilist_id',true); if ($al) $map[$al] = ['url'=>get_permalink($p->ID),'slug'=>$p->post_name]; }
            return rest_ensure_response($map);
        }]);

    register_rest_route('smacg/v1','/user-level',['methods'=>'GET','permission_callback'=>'is_user_logged_in','callback'=>fn()=>rest_ensure_response(smacg_get_user_level(get_current_user_id()))]);
});

/* ============================================================
   AJAX 處理
   ============================================================ */
add_action('wp_ajax_smacg_submit_rating_detail', function() {
    check_ajax_referer('smacg_nonce','nonce');
    $uid = get_current_user_id(); if (!$uid) wp_send_json_error(['msg'=>'請先登入才能評分'],401);
    $pid = (int)($_POST['post_id']??0);
    if (!$pid || get_post_type($pid) !== 'anime') wp_send_json_error(['msg'=>'無效的動漫 ID'],400);
    $keys = ['story','music','animation','voice']; $scores = [];
    foreach ($keys as $k) {
        $v = isset($_POST[$k]) ? (float)$_POST[$k] : null;
        if ($v === null || $v < 1 || $v > 10) wp_send_json_error(['msg'=>"「{$k}」分數無效，應介於 1–10"],400);
        $scores[$k] = round($v,1);
    }
    $avg = round(array_sum($scores)/count($scores),2);
    update_user_meta($uid,"smacg_rating_detail_{$pid}",array_merge($scores,['avg'=>$avg,'time'=>time()]));
    global $wpdb; $mk = "smacg_rating_detail_{$pid}";
    $all = $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key=%s",$mk));
    $tot = array_fill_keys($keys,0); $cnt = 0;
    foreach ($all as $raw) { $r = maybe_unserialize($raw); if (!is_array($r)) continue; foreach ($keys as $k) $tot[$k] += (float)($r[$k]??0); $cnt++; }
    if ($cnt > 0) {
        $s = []; foreach ($keys as $k) $s[$k] = round($tot[$k]/$cnt,1);
        $sa = round(array_sum($s)/count($s),1);
        foreach (['smacg_site_score'=>$sa,'smacg_site_score_story'=>$s['story'],'smacg_site_score_music'=>$s['music'],'smacg_site_score_animation'=>$s['animation'],'smacg_site_score_voice'=>$s['voice'],'smacg_site_score_count'=>$cnt] as $mk2=>$val)
            update_post_meta($pid,$mk2,$val);
        wp_send_json_success(['msg'=>'評分成功，感謝你的評價！','avg'=>$sa]+$s+['count'=>$cnt]);
    }
    wp_send_json_success(['msg'=>'評分成功！','avg'=>$avg]+$scores+['count'=>1]);
});

add_action('wp_ajax_weixiaoacg_toggle_favorite', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $pid = (int)($_POST['post_id']??0); $uid = get_current_user_id();
    if (!$pid || !$uid) wp_send_json_error(['msg'=>'無效請求']);
    $favs = get_user_meta($uid,'weixiaoacg_favorites',true)?:[];
    $k = array_search($pid,$favs);
    if ($k !== false) { unset($favs[$k]); $act = 'removed'; } else { $favs[] = $pid; $act = 'added'; }
    update_user_meta($uid,'weixiaoacg_favorites',array_values($favs));
    wp_send_json_success(['action'=>$act,'count'=>count($favs)]);
});

add_action('wp_ajax_weixiaoacg_update_progress', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $pid = (int)($_POST['post_id']??0); $uid = get_current_user_id();
    if (!$pid || !$uid) wp_send_json_error(['msg'=>'無效請求']);
    $d = ['progress'=>(int)($_POST['progress']??0),'watch_status'=>sanitize_text_field($_POST['watch_status']??''),'updated_at'=>time()];
    update_user_meta($uid,"weixiaoacg_progress_{$pid}",$d);
    wp_send_json_success($d);
});

add_action('wp_ajax_weixiaoacg_search',        'weixiaoacg_ajax_search');
add_action('wp_ajax_nopriv_weixiaoacg_search', 'weixiaoacg_ajax_search');
function weixiaoacg_ajax_search() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $kw   = sanitize_text_field($_POST['query']??$_POST['keyword']??'');
    $type = sanitize_text_field($_POST['type']??'all');
    if (strlen($kw) < 2) wp_send_json_error(['msg'=>'關鍵字太短']);
    $types = match($type) {'anime'=>['anime'],'manga'=>['manga'],'character'=>['character'],'va'=>['voice-actor'],'music'=>['music'],default=>['anime','manga','novel','game','character','voice-actor','post']};
    $q = new WP_Query(['s'=>$kw,'post_type'=>$types,'posts_per_page'=>12,'post_status'=>'publish']);
    $res = [];
    while ($q->have_posts()) { $q->the_post(); $pid = get_the_ID();
        $res[] = ['id'=>$pid,'title'=>get_the_title(),'title_zh'=>get_field('weixiaoacg_title_zh',$pid)?:get_the_title(),'type'=>get_post_type(),'url'=>get_permalink(),'thumb'=>get_the_post_thumbnail_url($pid,'weixiaoacg-thumb')?:get_field('weixiaoacg_cover_url',$pid),'score'=>get_field('weixiaoacg_score_anilist',$pid)];
    }
    wp_reset_postdata(); wp_send_json_success($res);
}

add_action('wp_ajax_weixiaoacg_submit_rating', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $pid = (int)($_POST['post_id']??0); $score = (float)($_POST['score']??0); $uid = get_current_user_id();
    if (!$pid || !$uid) wp_send_json_error(['msg'=>'請先登入']);
    if ($score < 1 || $score > 10) wp_send_json_error(['msg'=>'評分範圍 1–10']);
    if (function_exists('yasr_save_visitor_vote')) { wp_send_json_success(['msg'=>'評分成功','yasr'=>yasr_save_visitor_vote($pid,$score)]); }
    update_user_meta($uid,"weixiaoacg_rating_{$pid}",$score);
    wp_send_json_success(['msg'=>'評分成功']);
});

add_action('wp_ajax_weixiaoacg_resync_bangumi', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'權限不足']);
    class_exists('Anime_Sync_API_Handler') ? (new Anime_Sync_API_Handler())->ajax_resync_bangumi() : wp_send_json_error(['msg'=>'API Handler 類別未載入']);
});

add_action('wp_ajax_nopriv_weixiaoacg_ajax_login', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $u = sanitize_user($_POST['log']??''); $p = $_POST['pwd']??'';
    if (!$u || !$p) wp_send_json_error(['msg'=>'請輸入帳號和密碼']);
    $user = wp_signon(['user_login'=>$u,'user_password'=>$p,'remember'=>!empty($_POST['rememberme'])],is_ssl());
    is_wp_error($user) ? wp_send_json_error(['msg'=>'帳號或密碼錯誤，請再試一次']) : wp_send_json_success(['msg'=>'登入成功','redirect'=>home_url('/')]);
});

/* ============================================================
   全站外部連結中轉
   ============================================================ */
add_action('wp_footer','weixiao_external_link_redirect_js',99);
function weixiao_external_link_redirect_js(): void {
    $host = parse_url(home_url(),PHP_URL_HOST);
    $go   = home_url('/go/'); ?>
    <script>
    (function(){
        var H=<?php echo json_encode($host); ?>,G=<?php echo json_encode($go); ?>;
        function ext(a){try{var h=a.getAttribute('href')||'';if(!h||/^(#|mailto:|tel:|javascript:)/i.test(h)||h.startsWith(G)||a.hasAttribute('data-go-confirm'))return false;return new URL(h,location.origin).hostname!==H;}catch(e){return false;}}
        document.addEventListener('click',function(e){var a=e.target.closest('a');if(!a||!ext(a))return;e.preventDefault();e.stopPropagation();window.open(G+'?url='+encodeURIComponent(a.href),'_blank','noopener,noreferrer');},true);
    })();
    </script><?php
}

/* 登入後跳轉 */
add_action( 'um_on_login_before_redirect', function( $user_id ) {
    um_fetch_user( $user_id );
    if ( um_user( 'after_login' ) === 'redirect_profile' ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
}, 5 );

/* AJAX 註冊 handler */
add_action('wp_ajax_nopriv_weixiaoacg_ajax_register', function() {
    check_ajax_referer('weixiaoacg_nonce', 'nonce');
    $username = sanitize_user($_POST['user_login'] ?? '');
    $email    = sanitize_email($_POST['user_email'] ?? '');
    $password = $_POST['user_password'] ?? '';
    if (!$username) wp_send_json_error(['msg' => '請輸入使用者名稱']);
    if (!$email || !is_email($email)) wp_send_json_error(['msg' => '請輸入有效的電子郵件']);
    if (!$password) wp_send_json_error(['msg' => '請輸入密碼']);
    if (username_exists($username)) wp_send_json_error(['msg' => '此使用者名稱已被使用']);
    if (email_exists($email)) wp_send_json_error(['msg' => '此電子郵件已被註冊']);
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) wp_send_json_error(['msg' => $user_id->get_error_message()]);
    if (function_exists('um_fetch_user')) {
        update_user_meta($user_id, 'account_status', 'approved');
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
    }
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, false, is_ssl());
    wp_send_json_success(['msg' => '註冊成功！', 'redirect' => home_url('/')]);
});

/* 帳號更新後跳回個人頁面 */
add_action( 'um_after_user_account_updated', function( $user_id, $args ) {
    if ( ! empty( $_POST['um_account_tab'] ) && $_POST['um_account_tab'] === 'general' ) {
        um_fetch_user( $user_id );
        wp_safe_redirect( um_user_profile_url() );
        exit;
    }
}, 10, 2 );

/* /account/ 頁面強制 editing 模式 */
add_action( 'um_account_page_load', function() {
    UM()->fields()->editing = true;
}, 1 );

/* 會員頁頭像上傳 */
add_action('wp_footer', function() {
    if (!function_exists('um_is_core_page')) return;
    if (!um_is_core_page('user') && !get_query_var('um_user')) return;
    $user_id   = get_current_user_id();
    $timestamp = time();
    $nonce     = wp_create_nonce('um_upload_nonce-' . $timestamp);
    ?>
    <input type="file" id="mc-avatar-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" />
    <script>
    (function($){
        var userId    = <?php echo (int)$user_id; ?>;
        var nonce     = <?php echo json_encode($nonce); ?>;
        var timestamp = <?php echo (int)$timestamp; ?>;
        var ajaxUrl   = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            var imgWrap   = document.querySelector('.mc-avatar-img-wrap');
            var fileInput = document.getElementById('mc-avatar-file-input');
            if (!imgWrap || !fileInput) return;

            imgWrap.addEventListener('click', function() { fileInput.click(); });

            fileInput.addEventListener('change', function() {
                var file = fileInput.files[0]; if (!file) return;
                var overlay = document.getElementById('mc-avatar-overlay');
                if (overlay) overlay.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                var formData = new FormData();
                formData.append('action', 'um_imageupload');
                formData.append('key', 'profile_photo');
                formData.append('user_id', userId);
                formData.append('_wpnonce', nonce);
                formData.append('timestamp', timestamp);
                formData.append('set_id', '1520');
                formData.append('set_mode', 'profile');
                formData.append('type', 'image');
                formData.append('max_size', '999999999');
                formData.append('allowed_types', 'jpg,jpeg,png,gif,webp');
                formData.append('file', file);

                fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        var imgUrl = data.data && data.data.url ? data.data.url : null;
                        if (imgUrl) {
                            var avatarImg = document.getElementById('mc-avatar-img');
                            if (avatarImg) avatarImg.src = imgUrl + '?t=' + Date.now();
                        }
                        if (overlay) overlay.innerHTML = '<i class="fa-solid fa-check"></i>';
                        setTimeout(function(){
                            if (overlay) overlay.innerHTML = '<i class="fa-solid fa-camera"></i>';
                            location.reload();
                        }, 1000);
                    } else {
                        var msg = (data.data && data.data.error) ? data.data.error : '上傳失敗';
                        alert(msg);
                        if (overlay) overlay.innerHTML = '<i class="fa-solid fa-camera"></i>';
                    }
                })
                .catch(function(err){
                    console.error('Upload error:', err);
                    alert('上傳時發生錯誤，請稍後再試');
                    if (overlay) overlay.innerHTML = '<i class="fa-solid fa-camera"></i>';
                });
                fileInput.value = '';
            });
        });
    })(jQuery);
    </script>
    <?php
}, 998);


/* ============================================================
   ★ 新增:取得當前使用者對某動畫的評分（給前端 fetch 用）
   原因:LiteSpeed 全頁快取會讓登入者的評分被快取版蓋掉，
        所以改由 JS 在頁面載入後即時抓 user_meta
   ============================================================ */
add_action('wp_ajax_smacg_get_my_rating', function() {
    $post_id = isset($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0;
    if ($post_id <= 0) {
        wp_send_json_error(['msg' => 'invalid post_id'], 400);
    }
    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(['msg' => 'not logged in'], 401);
    }

    $detail = get_user_meta($uid, "smacg_rating_detail_{$post_id}", true);
    if (!is_array($detail)) {
        wp_send_json_success(['rated' => false]);
    }

    wp_send_json_success([
        'rated'     => true,
        'story'     => (float) ($detail['story']     ?? 5),
        'music'     => (float) ($detail['music']     ?? 5),
        'animation' => (float) ($detail['animation'] ?? 5),
        'voice'     => (float) ($detail['voice']     ?? 5),
        'avg'       => (float) ($detail['avg']       ?? 5),
    ]);
});


/**
 * 載入自訂模板專用 CSS
 * 取代直接寫 <link> 在模板裡的方式，讓 LiteSpeed/CDN 能正確處理
 */
add_action( 'wp_enqueue_scripts', function () {
    $base_url = get_stylesheet_directory_uri() . '/assets/css/';
    $base_dir = get_stylesheet_directory()     . '/assets/css/';

    // 用檔案修改時間當版本號，更新 CSS 自動破快取
    $ver_news    = file_exists( $base_dir . 'news.css' )    ? filemtime( $base_dir . 'news.css' )    : '1.0';
    $ver_single  = file_exists( $base_dir . 'single.css' )  ? filemtime( $base_dir . 'single.css' )  : '1.0';
    $ver_columns = file_exists( $base_dir . 'columns.css' ) ? filemtime( $base_dir . 'columns.css' ) : '1.0';

    // 分類 archive（/news/、/review/、/feature/、/announcement/、/news/anime/...）
    if ( is_category() || is_tax( 'channel' ) ) {
        wp_enqueue_style( 'smacg-news', $base_url . 'news.css', [], $ver_news );
    }

    // 單篇文章（/news/anime/post-slug/、/announcement/post-slug/...）
    if ( is_singular( 'post' ) ) {
        wp_enqueue_style( 'smacg-news',   $base_url . 'news.css',   [],            $ver_news );
        wp_enqueue_style( 'smacg-single', $base_url . 'single.css', [ 'smacg-news' ], $ver_single );
    }

    // /columns/ 頁面
    if ( is_page_template( 'page-columns.php' ) ) {
        wp_enqueue_style( 'smacg-news',    $base_url . 'news.css',    [],              $ver_news );
        wp_enqueue_style( 'smacg-columns', $base_url . 'columns.css', [ 'smacg-news' ], $ver_columns );
    }
}, 20 );

