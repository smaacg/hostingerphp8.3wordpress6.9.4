<?php
/**
 * Plugin Name: Anime Sync Pro
 * Description: 從 AniList、Bangumi 自動同步動畫資料。
 * Version:     1.0.9
 * Author:      weixiaoacg
 * Requires PHP: 8.0
 * Text Domain: anime-sync-pro
 *
 * Changelog:
 *   1.0.9 — Taxonomy seeder 內建化
 *           - [新增] init priority 99 觸發 Anime_Sync_Installer::run_pending_seed()
 *                   啟用 / 升級時自動建立 category / channel / genre /
 *                   anime_format_tax / anime_season_tax 種子 term
 *                   （取代舊的 setup-taxonomy.php，該檔案可從外掛根目錄刪除）
 *           - [改進] 配合 class-installer.php 1.3.0：
 *                   季度年份範圍動態計算（當年-3 ~ 當年+1，N=5），
 *                   每次升級自動往後滑動，不再寫死 2000–2035
 *   1.0.8 — 主檔優化
 *           - [修正] 啟用時 flush_rewrite_rules() 時機過早（CPT 還沒註冊）
 *                   改用 anime_sync_flush_rewrite option 標記，由 init priority 99 處理
 *           - [修正] genre taxonomy 不再註冊到不存在的 manga / novel CPT
 *           - [修正] save_post_anime 與 ACF 同步衝突：priority 改 20，
 *                   且只在 meta 為空時才用 post_title 回填，避免覆蓋人工編輯
 *           - [修正] save_post_anime 補上 wp_is_post_revision() 與 REST 自動草稿過濾
 *           - [改進] 拆分 ACF-依賴 與 非 ACF-依賴 的初始化，
 *                   讓評分系統與使用者狀態系統在 ACF 缺失時仍可運作
 *           - [改進] anime_sync_enrich_post 加入錯誤處理與指數退避重試（最多 3 次）
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
define( 'ANIME_SYNC_PRO_VERSION',  '1.0.9' );
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

	// Anime_Sync_API_Handler → class-api-handler.php
	$file_name = 'class-' . strtolower(
		str_replace( [ 'Anime_Sync_', '_' ], [ '', '-' ], $class )
	) . '.php';

	// 防呆：避免連續底線產生連續連字號
	$file_name = preg_replace( '/-+/', '-', $file_name );

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
	// [修正] 原本註冊到 [ 'anime', 'manga', 'novel' ]，
	//        但 manga/novel CPT 並未註冊，會造成 _doing_it_wrong 警告。
	//        若日後新增 manga/novel CPT，可在那邊用
	//        register_taxonomy_for_object_type() 動態加入。
	// ----------------------------------------------------------
	register_taxonomy( 'genre', [ 'anime' ], [
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
// [修正] 移除原本的 flush_rewrite_rules() 直接呼叫。
//        register_activation_hook 在 init 之前執行，CPT 尚未註冊，
//        flush 出來的規則不包含 anime 的 rewrite，會導致前台 404。
//        改用 option 標記，交給第 7 段（init priority 99）處理。
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

	// [修正] 不直接 flush，改設旗標讓 init hook 在 CPT 註冊完成後再 flush
	update_option( 'anime_sync_flush_rewrite', 1 );
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
// [改進] 拆成兩段：
//        6A. 不依賴 ACF 的元件（評分、使用者狀態、Editorial Routing）
//        6B. 依賴 ACF 的元件（ACF Fields、Frontend、後台、Cron）
//        這樣 ACF 暫時停用時，前端評分與追蹤系統仍能運作。
// [新增] 6C. maybe_upgrade()：版本變動時自動補建漏失資料表 / 選項，
//             並設置 seed 旗標（由第 7 段在 init 99 觸發）。
// ============================================================
add_action( 'plugins_loaded', function () {

	// ----------------------------------------------------------
	// 6A. 不依賴 ACF 的元件
	// ----------------------------------------------------------

	// 6A-1. 文章內容路由（純 rewrite 規則，不需 ACF）
	if ( class_exists( 'Anime_Sync_Editorial_Routing' ) ) {
		new Anime_Sync_Editorial_Routing();
	}

	// 6A-2. 評分系統（自有資料表 anime_ratings，不需 ACF）
	if ( class_exists( 'Anime_Sync_Rating_Manager' ) ) {
		new Anime_Sync_Rating_Manager();
	}

	// 6A-3. 使用者追蹤狀態系統（自有資料表，不需 ACF）
	if ( class_exists( 'Anime_Sync_User_Status_Manager' ) ) {
		new Anime_Sync_User_Status_Manager();
	}

	// 6A-4. 追蹤狀態彙總 cron
	if ( class_exists( 'Anime_Sync_User_Status_Cron' ) ) {
		new Anime_Sync_User_Status_Cron();
	}

	// ----------------------------------------------------------
	// 6C. 版本升級檢查（每次載入都跑，但只在版本不符時實際動作）
	// ----------------------------------------------------------
	if ( class_exists( 'Anime_Sync_Installer' ) ) {
		( new Anime_Sync_Installer() )->maybe_upgrade();
	}

	// ----------------------------------------------------------
	// 6B. ACF 相依性檢查
	// ----------------------------------------------------------
	if ( ! class_exists( 'ACF' ) ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-warning"><p>';
				echo '<strong>Anime Sync Pro</strong>：未偵測到 ';
				echo '<a href="https://www.advancedcustomfields.com/" target="_blank" rel="noopener">Advanced Custom Fields</a>';
				echo '，匯入 / 同步 / 後台管理功能將停用。前端評分與追蹤狀態系統仍可使用。';
				echo '</p></div>';
			} );
		}
		return;
	}

	// 6B-1. 初始化 ACF 欄位組
	if ( class_exists( 'Anime_Sync_ACF_Fields' ) ) {
		new Anime_Sync_ACF_Fields();
	}

	// 6B-2. 初始化前台
	if ( class_exists( 'Anime_Sync_Frontend' ) ) {
		new Anime_Sync_Frontend();
	}

	// 6B-3. 後台 + Cron 環境
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

		// ------------------------------------------------------
		// [改進] enrich 補抓動作：加入錯誤處理與指數退避重試
		// ------------------------------------------------------
		if ( $import_manager ) {
			add_action(
				'anime_sync_enrich_post',
				function ( int $post_id ) use ( $import_manager ) {

					if ( get_post_meta( $post_id, '_enriched_at', true ) ) {
						return;
					}

					$result = $import_manager->enrich_single( $post_id );

					if ( ! is_wp_error( $result ) ) {
						// 成功：清掉重試計數
						delete_post_meta( $post_id, '_enrich_retry' );
						return;
					}

					// 失敗：指數退避重試（1h → 4h → 16h）
					$retry_count = (int) get_post_meta( $post_id, '_enrich_retry', true );
					$max_retries = 3;

					if ( $retry_count < $max_retries ) {
						$retry_count++;
						update_post_meta( $post_id, '_enrich_retry', $retry_count );
						$delay = HOUR_IN_SECONDS * ( 4 ** ( $retry_count - 1 ) );
						wp_schedule_single_event(
							time() + $delay,
							'anime_sync_enrich_post',
							[ $post_id ]
						);
					} else {
						// 超過重試次數，標記放棄
						update_post_meta( $post_id, '_enrich_failed', current_time( 'mysql' ) );
					}

					if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
						Anime_Sync_Error_Logger::error(
							"Enrich failed for post {$post_id}: " . $result->get_error_message(),
							[
								'post_id'     => $post_id,
								'retry'       => $retry_count,
								'max_retries' => $max_retries,
								'error_code'  => $result->get_error_code(),
							]
						);
					}
				}
			);
		}
	}

} );

// ============================================================
// 7. Init priority 99：rewrite flush + taxonomy seed
//     兩個動作都需要在 CPT / taxonomy 註冊完成後（priority 10）才能跑，
//     所以統一掛在 priority 99。
// ============================================================
add_action( 'init', function () {

	// 7-1. Rewrite Rules 刷新
	if ( get_option( 'anime_sync_flush_rewrite' ) ) {
		flush_rewrite_rules();
		delete_option( 'anime_sync_flush_rewrite' );
	}

	// 7-2. [新增] Taxonomy seed 觸發
	//      由 Anime_Sync_Installer::activate() / maybe_upgrade() 設置 transient，
	//      此處在 taxonomy 註冊完成後才實際執行 wp_insert_term()。
	if ( class_exists( 'Anime_Sync_Installer' ) ) {
		( new Anime_Sync_Installer() )->run_pending_seed();
	}

}, 99 );

// ============================================================
// 8. 同步 post_title → anime_title_chinese
// [修正] 原本 priority 10 與 ACF 衝突，且會無條件覆寫 ACF 欄位。
//        現在：
//        - priority 改 20，跑在 ACF（priority 10）之後
//        - 補上 wp_is_post_revision() 與 REST 自動草稿過濾
//        - 只在「meta 為空」時才用 post_title 回填，
//          不再覆寫使用者已透過 ACF 編輯的 anime_title_chinese
// ============================================================
add_action( 'save_post_anime', function ( int $post_id, WP_Post $post, bool $update ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// auto-draft / inherit / trash 全部跳過
	if ( in_array( $post->post_status, [ 'auto-draft', 'inherit', 'trash' ], true ) ) {
		return;
	}

	$new_title = trim( $post->post_title );
	if ( $new_title === '' ) {
		return;
	}

	$current_meta = get_post_meta( $post_id, 'anime_title_chinese', true );

	// 只在 meta 為空（尚未填入中文標題）時才用 post_title 回填。
	// 若 meta 已有值，代表使用者已透過 ACF 編輯過，不應被 post_title 覆寫。
	if ( $current_meta === '' || $current_meta === null ) {
		update_post_meta( $post_id, 'anime_title_chinese', $new_title );
	}

}, 20, 3 );
