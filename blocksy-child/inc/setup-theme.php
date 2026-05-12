<?php
/**
 * 主題設定：after_setup_theme / 選單 / 防快取 / Widgets / 摘要 / 搜尋表單
 *
 * @package weixiaoacg
 * @subpackage Setup
 */
defined( 'ABSPATH' ) || exit;

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

/* ============================================================
   防快取：UM 用戶頁 + 自訂會員頁
   ============================================================ */
add_action('template_redirect', function() {
    $is_um  = function_exists('um_is_core_page') && um_is_core_page('user');
    $is_mem = is_page_template('page-member.php');
    if ($is_um || $is_mem) {
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('Surrogate-Control: no-store');
    }
}, 1);

/* ============================================================
   摘要
   ============================================================ */
add_filter('excerpt_length', fn()=>60);
add_filter('excerpt_more',   fn()=>'…');

/* ============================================================
   搜尋表單
   ============================================================ */
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
