<?php
/**
 * Plugin Name: Anime Sync Pro
 * Description: 從 AniList、Bangumi 自動同步動畫資料。
 * Version:     1.0.7
 * Author:      weixiaoacg
 * Requires PHP: 8.0
 * Text Domain: anime-sync-pro
 *
 * Changelog:
 *   1.0.7 — 新增使用者追蹤狀態系統（巴哈級規模）
 *           - wp_anime_user_status 主表（取代 user_meta JSON）
 *           - wp_anime_user_status_stats 彙總表（排行榜預計算）
 *           - REST API: /weixiaoacg/v1/user-status/{anime_id}
 *           - Cron: 每 15 分鐘重算彙總表
 *   1.0.6 — 系列分類、ACF 欄位擴充、Editorial Routing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================
// 1. 常數定義
// ============================================================
define( 'ANIME_SYNC_PRO_VERSION',  '1.0.7' );
define( 'ANIME_SYNC_PRO_DIR',      plugin_dir_path( __FILE__ ) );
define( 'ANIME_SYNC_PRO_URL',      plugin_dir_url( __FILE__ ) );
define( 'ANIME_SYNC_PRO_BASENAME', plugin_basename( __FILE__ ) );

// ============================================================
// 2. Autoloader
// ============================================================
spl_autoload_register( function ( $class ) {
	if ( strpos( $class, 'Anime_Sync_' ) !== 0 ) {
		return;
	}

	$file_name = 'class-' . strtolower(
		str_replace( [ 'Anime_Sync_', '_' ], [ '', '-' ], $class )
	) . '.php';

	$sources = [
		ANIME_SYNC_PRO_DIR . 'includes/',
		ANIME_SYNC_PRO_DIR . 'admin/',
		ANIME_SYNC_PRO_DIR . 'public/',
	];

	foreach ( $sources as $source ) {
		$file = $source . $file_name;
		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}
	}
} );

// ============================================================
// 3. 註冊 Post Type 與 Taxonomy
// ============================================================
add_action( 'init', function () {

	// ----------------------------------------------------------
	// Post Type: anime
	// ----------------------------------------------------------
	register_post_type( 'anime', [
		'labels' => [
			'name'          => '動畫',
			'singular_name' => '動畫',
			'add_new'       => '新增動畫',
			'add_new_item'  => '新增動畫',
			'edit_item'     => '編輯動畫',
			'view_item'     => '檢視動畫',
			'search_items'  => '搜尋動畫',
			'not_found'     => '找不到動畫',
			'all_items'     => '所有動畫',
			'menu_name'     => '動畫',
		],
		'public'             => true,
		'has_archive'        => 'anime',
		'show_in_rest'       => true,
		'show_in_nav_menus'  => true,
		'show_ui'            => true,
		'menu_icon'          => 'dashicons-format-video',
		'menu_position'      => 5,
		'supports'           => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'comments' ],
		'taxonomies'         => [ 'post_tag' ],
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
		'rewrite'            => [ 'slug' => 'anime', 'with_front' => false ],
	] );

	// ----------------------------------------------------------
	// Taxonomy: genre
	// ----------------------------------------------------------
	register_taxonomy( 'genre', [ 'anime', 'manga', 'novel' ], [
		'labels' => [
			'name'          => '類型',
			'singular_name' => '類型',
			'search_items'  => '搜尋類型',
			'all_items'     => '所有類型',
			'edit_item'     => '編輯類型',
			'add_new_item'  => '新增類型',
		],
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'genre', 'with_front' => false ],
	] );

	// ----------------------------------------------------------
	// Taxonomy: anime_season_tax
	// ----------------------------------------------------------
	register_taxonomy( 'anime_season_tax', [ 'anime' ], [
		'labels' => [
			'name'          => '播出季度',
			'singular_name' => '季度',
			'search_items'  => '搜尋季度',
			'all_items'     => '所有季度',
			'edit_item'     => '編輯季度',
			'add_new_item'  => '新增季度',
		],
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'season', 'with_front' => false ],
	] );

	// ----------------------------------------------------------
	// Taxonomy: anime_format_tax
	// ----------------------------------------------------------
	register_taxonomy( 'anime_format_tax', [ 'anime' ], [
		'labels' => [
			'name'          => '動畫格式',
			'singular_name' => '格式',
			'search_items'  => '搜尋格式',
			'all_items'     => '所有格式',
			'edit_item'     => '編輯格式',
			'add_new_item'  => '新增格式',
		],
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'format', 'with_front' => false ],
	] );

	// ----------------------------------------------------------
	// Taxonomy: anime_series_tax
	// ----------------------------------------------------------
	register_taxonomy( 'anime_series_tax', [ 'anime' ], [
		'labels' => [
			'name'          => '系列',
			'singular_name' => '系列',
			'search_items'  => '搜尋系列',
			'all_items'     => '所有系列',
			'edit_item'     => '編輯系列',
			'add_new_item'  => '新增系列',
			'new_item_name' => '新系列名稱',
			'menu_name'     => '系列',
		],
		'hierarchical'      => false,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'series', 'with_front' => false ],
	] );

	// ----------------------------------------------------------
	// Taxonomy: anime_studio_tax
	// ----------------------------------------------------------
	register_taxonomy( 'anime_studio_tax', [ 'anime' ], [
		'labels' => [
			'name'          => '製作公司',
			'singular_name' => '製作公司',
			'search_items'  => '搜尋製作公司',
			'all_items'     => '所有製作公司',
			'edit_item'     => '編輯製作公司',
			'add_new_item'  => '新增製作公司',
			'new_item_name' => '新製作公司名稱',
			'menu_name'     => '製作公司',
		],
		'hierarchical'      => false,
		'show_in_rest'      => true,
		'show_in_nav_menus' => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'studio', 'with_front' => false ],
	] );

}, 10 );

// ============================================================
// 4. 啟用 Hook
// ============================================================
register_activation_hook( __FILE__, function () {

	if ( ! class_exists( 'Anime_Sync_Installer' ) ) {
		$installer_file = ANIME_SYNC_PRO_DIR . 'includes/class-installer.php';
		if ( file_exists( $installer_file ) ) {
			require_once $installer_file;
		}
	}

	if ( class_exists( 'Anime_Sync_Installer' ) ) {
		( new Anime_Sync_Installer() )->activate();
	}

	if ( ! class_exists( 'Anime_Sync_Cron_Manager' ) ) {
		$cron_file = ANIME_SYNC_PRO_DIR . 'includes/class-cron-manager.php';
		if ( file_exists( $cron_file ) ) {
			require_once $cron_file;
		}
	}

	if ( class_exists( 'Anime_Sync_Cron_Manager' ) ) {
		Anime_Sync_Cron_Manager::activate();
	}

	flush_rewrite_rules();
} );

// ============================================================
// 5. 停用 Hook
// ============================================================
register_deactivation_hook( __FILE__, function () {

	if ( class_exists( 'Anime_Sync_Cron_Manager' ) ) {
		Anime_Sync_Cron_Manager::deactivate();
	}

	if ( class_exists( 'Anime_Sync_User_Status_Cron' ) ) {
		Anime_Sync_User_Status_Cron::unschedule();
	}

	if ( class_exists( 'Anime_Sync_Installer' ) ) {
		( new Anime_Sync_Installer() )->deactivate();
	}

	flush_rewrite_rules();
} );

// ============================================================
// 6. 載入外掛核心（plugins_loaded）
// ============================================================
add_action( 'plugins_loaded', function () {

	// 6-1. ACF 相依性檢查
	if ( ! class_exists( 'ACF' ) ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				echo '<strong>Anime Sync Pro</strong> 需要安裝並啟用 ';
				echo '<a href="https://www.advancedcustomfields.com/" target="_blank">Advanced Custom Fields</a> 才能正常運作。';
				echo '</p></div>';
			} );
		}
		return;
	}

	// 6-2. 初始化 ACF 欄位組
	if ( class_exists( 'Anime_Sync_ACF_Fields' ) ) {
		new Anime_Sync_ACF_Fields();
	}

	// 6-2-1. 初始化文章內容路由
	if ( class_exists( 'Anime_Sync_Editorial_Routing' ) ) {
		new Anime_Sync_Editorial_Routing();
	}

	// 6-3. 初始化前台
	if ( class_exists( 'Anime_Sync_Frontend' ) ) {
		new Anime_Sync_Frontend();
	}

	// 6-3-1. 初始化評分系統
	if ( class_exists( 'Anime_Sync_Rating_Manager' ) ) {
		new Anime_Sync_Rating_Manager();
	}

	// 6-3-2. 初始化使用者追蹤狀態系統（巴哈級規模）
	if ( class_exists( 'Anime_Sync_User_Status_Manager' ) ) {
		new Anime_Sync_User_Status_Manager();
	}

	// 6-3-3. 初始化追蹤狀態彙總 cron
	if ( class_exists( 'Anime_Sync_User_Status_Cron' ) ) {
		new Anime_Sync_User_Status_Cron();
	}

	// 6-4. 後台 + Cron 環境
	if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {

		$rate_limiter = class_exists( 'Anime_Sync_Rate_Limiter' )
			? new Anime_Sync_Rate_Limiter()
			: null;

		$id_mapper = class_exists( 'Anime_Sync_ID_Mapper' )
			? new Anime_Sync_ID_Mapper( $rate_limiter )
			: null;

		$converter = class_exists( 'Anime_Sync_CN_Converter' )
			? new Anime_Sync_CN_Converter()
			: null;

		$api_handler = class_exists( 'Anime_Sync_API_Handler' )
			? new Anime_Sync_API_Handler( $rate_limiter, $id_mapper )
			: null;

		$import_manager = ( $api_handler && $converter && class_exists( 'Anime_Sync_Import_Manager' ) )
			? new Anime_Sync_Import_Manager( $api_handler, $converter )
			: null;

		if ( is_admin() && class_exists( 'Anime_Sync_Admin' ) ) {
			new Anime_Sync_Admin( $import_manager );
		}

		if ( $import_manager && class_exists( 'Anime_Sync_Cron_Manager' ) ) {
			new Anime_Sync_Cron_Manager( $import_manager );
		}

		if ( is_admin() && class_exists( 'Anime_Sync_Custom_Post_Type' ) ) {
			new Anime_Sync_Custom_Post_Type();
		}

		if ( $import_manager ) {
			add_action(
				'anime_sync_enrich_post',
				function ( int $post_id ) use ( $import_manager ) {
					if ( get_post_meta( $post_id, '_enriched_at', true ) ) {
						return;
					}
					$import_manager->enrich_single( $post_id );
				}
			);
		}
	}

} );

// ============================================================
// 7. Rewrite Rules 刷新
// ============================================================
add_action( 'init', function () {
	if ( get_option( 'anime_sync_flush_rewrite' ) ) {
		flush_rewrite_rules();
		delete_option( 'anime_sync_flush_rewrite' );
	}
}, 99 );

// ============================================================
// 8. 同步 post_title → anime_title_chinese
// ============================================================
add_action( 'save_post_anime', function ( int $post_id, WP_Post $post, bool $update ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( $post->post_status === 'auto-draft' ) {
		return;
	}

	$new_title = trim( $post->post_title );
	if ( $new_title === '' ) {
		return;
	}

	$current_meta = get_post_meta( $post_id, 'anime_title_chinese', true );
	if ( $current_meta !== $new_title ) {
		update_post_meta( $post_id, 'anime_title_chinese', $new_title );
	}

}, 10, 3 );
