<?php
/**
 * Installer Class
 *
 * @package Anime_Sync_Pro
 * @version 1.2.0
 *
 * Changelog:
 *   1.2.0 — 安裝器優化
 *           - [修正] activate() 移除多餘的 flush_rewrite_rules()
 *                   原本在 register_cpt_for_flush() 已設定 anime_sync_flush_rewrite option，
 *                   由主檔 init priority 99 在 CPT 註冊完成後再執行 flush，
 *                   activate 階段呼叫 flush 是無效的（CPT 尚未註冊）。
 *           - [修正] deactivate() 移除 flush_rewrite_rules()
 *                   停用時 CPT 已 unregister，WordPress 會自動處理 rewrite，無需手動 flush。
 *           - [改進] 新增 maybe_upgrade()：版本變動時自動執行升級邏輯
 *                   （補建漏失的資料表、補上新增的預設選項）
 *           - [改進] create_upload_dirs() 加入錯誤回傳與 logger 紀錄
 *           - [改進] set_default_options() 改用 ?? 方式，新版可隨意新增鍵
 *           - [新增] is_table_missing() 工具方法
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anime_Sync_Installer {

	/**
	 * 預設選項定義（升級時也會用到）
	 */
	private function get_default_options(): array {
		return [
			'anime_sync_cn_method'           => 'dict',
			'anime_sync_image_method'        => 'api_url',
			'anime_sync_cdn_provider'        => 'cloudflare',
			'anime_sync_cdn_base_url'        => '',
			'anime_sync_api_delay'           => 1000,
			'anime_sync_batch_size'          => 15,
			'anime_sync_log_email_notify'    => 1,
			'anime_sync_log_retention_days'  => 30,
			'anime_sync_debug_mode'          => 0,
			'anime_sync_delete_on_uninstall' => 0,
		];
	}

	// =========================================================================
	// 啟用 / 停用
	// =========================================================================

	/**
	 * 插件啟用時執行
	 *
	 * [修正] 移除原本的 flush_rewrite_rules()。
	 *        register_cpt_for_flush() 已設定 anime_sync_flush_rewrite option，
	 *        會由主檔 init priority 99 在 CPT 註冊完成後執行 flush。
	 */
	public function activate(): void {
		$this->create_tables();
		$this->set_default_options();
		$this->create_upload_dirs();
		$this->register_cpt_for_flush();

		update_option( 'anime_sync_activated_at', current_time( 'mysql' ) );
		update_option( 'anime_sync_version',      ANIME_SYNC_PRO_VERSION );
	}

	/**
	 * 插件停用時執行
	 *
	 * [修正] 移除原本的 flush_rewrite_rules()。
	 *        WordPress 在 CPT unregister 後會自動處理 rewrite cache，無需手動 flush。
	 */
	public function deactivate(): void {
		delete_transient( 'anime_sync_pending_count' );
	}

	// =========================================================================
	// 升級檢查（每次 plugins_loaded 時可呼叫，用於補建漏失的表/選項）
	// =========================================================================

	/**
	 * 版本檢查與升級
	 *
	 * 用於以下情境：
	 *   1. 已安裝外掛升級到新版本，需要新增資料表或選項
	 *   2. 直接覆蓋外掛檔案（沒走啟用流程）時的補救
	 *
	 * 主檔可在 plugins_loaded hook 中呼叫：
	 *   ( new Anime_Sync_Installer() )->maybe_upgrade();
	 *
	 * @return bool 是否執行了升級
	 */
	public function maybe_upgrade(): bool {
		$current_version = get_option( 'anime_sync_version', '0.0.0' );

		if ( version_compare( $current_version, ANIME_SYNC_PRO_VERSION, '>=' ) ) {
			return false;
		}

		// 安全地補建缺失的資料表（CREATE TABLE IF NOT EXISTS 不會破壞既有資料）
		$this->create_tables();

		// 補上新版本新增的預設選項（既有選項不會被覆蓋）
		$this->set_default_options();

		// 標記新版本
		update_option( 'anime_sync_version',         ANIME_SYNC_PRO_VERSION );
		update_option( 'anime_sync_last_upgrade_at', current_time( 'mysql' ) );
		update_option( 'anime_sync_last_upgrade_from', $current_version );

		// 觸發 rewrite 重整（以防新版有新的 rewrite 規則）
		update_option( 'anime_sync_flush_rewrite', 1 );

		if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
			Anime_Sync_Error_Logger::info(
				"Anime Sync Pro upgraded from {$current_version} to " . ANIME_SYNC_PRO_VERSION
			);
		}

		return true;
	}

	// =========================================================================
	// 資料表建立
	// =========================================================================

	/**
	 * 建立資料庫資料表
	 *
	 * 使用 CREATE TABLE IF NOT EXISTS + dbDelta，
	 * 對既有資料表是安全的（只會新增缺失欄位/索引，不會刪除資料）。
	 */
	private function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ────────────────────────────────────────────
		// 表 1：審核佇列
		// ────────────────────────────────────────────
		$queue_table = $wpdb->prefix . 'anime_review_queue';
		$queue_sql   = "CREATE TABLE IF NOT EXISTS {$queue_table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			anilist_id  INT(11) UNSIGNED NOT NULL,
			title       VARCHAR(255) NOT NULL DEFAULT '',
			api_data    LONGBLOB,
			status      ENUM('pending','approved','rejected','published') NOT NULL DEFAULT 'pending',
			source      VARCHAR(20) NOT NULL DEFAULT 'manual',
			wp_post_id  BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			created_at  DATETIME NOT NULL,
			updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY anilist_id (anilist_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $queue_sql );

		// ────────────────────────────────────────────
		// 表 2：錯誤日誌
		// ────────────────────────────────────────────
		$logs_table = $wpdb->prefix . 'anime_sync_logs';
		$logs_sql   = "CREATE TABLE IF NOT EXISTS {$logs_table} (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			level      ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info',
			message    TEXT NOT NULL,
			context    LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $logs_sql );

		// ────────────────────────────────────────────
		// 表 3：評分資料表
		// ────────────────────────────────────────────
		$ratings_table = $wpdb->prefix . 'anime_ratings';
		$ratings_sql   = "CREATE TABLE IF NOT EXISTS {$ratings_table} (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			anime_id         BIGINT(20) UNSIGNED NOT NULL,
			user_id          BIGINT(20) UNSIGNED NOT NULL,
			score_story      DECIMAL(4,2) NOT NULL DEFAULT 0.00,
			score_music      DECIMAL(4,2) NOT NULL DEFAULT 0.00,
			score_animation  DECIMAL(4,2) NOT NULL DEFAULT 0.00,
			score_voice      DECIMAL(4,2) NOT NULL DEFAULT 0.00,
			score_overall    DECIMAL(4,2) NOT NULL DEFAULT 0.00,
			weight           DECIMAL(4,2) NOT NULL DEFAULT 1.00,
			created_at       DATETIME NOT NULL,
			updated_at       DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_anime (user_id, anime_id),
			KEY anime_id (anime_id),
			KEY score_overall (score_overall)
		) {$charset_collate};";
		dbDelta( $ratings_sql );

		// ────────────────────────────────────────────
		// 表 4：使用者追蹤狀態（取代 user_meta 'anime_user_data' JSON）
		// 巴哈級規模設計：百萬會員、五千萬筆紀錄
		// ────────────────────────────────────────────
		$user_status_table = $wpdb->prefix . 'anime_user_status';
		$user_status_sql   = "CREATE TABLE IF NOT EXISTS {$user_status_table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id       BIGINT(20) UNSIGNED NOT NULL,
			anime_id      BIGINT(20) UNSIGNED NOT NULL,
			status        TINYINT UNSIGNED DEFAULT NULL,
			progress      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			favorited     TINYINT(1) NOT NULL DEFAULT 0,
			fullcleared   TINYINT(1) NOT NULL DEFAULT 0,
			started_at    DATETIME DEFAULT NULL,
			completed_at  DATETIME DEFAULT NULL,
			note          VARCHAR(500) DEFAULT NULL,
			is_private    TINYINT(1) NOT NULL DEFAULT 0,
			created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_anime (user_id, anime_id),
			KEY user_status (user_id, status, updated_at),
			KEY user_favorited (user_id, favorited, updated_at),
			KEY anime_status (anime_id, status),
			KEY anime_favorited (anime_id, favorited),
			KEY updated_at (updated_at)
		) {$charset_collate};";
		dbDelta( $user_status_sql );

		// ────────────────────────────────────────────
		// 表 5：使用者追蹤狀態彙總（排行榜預計算）
		// 由 cron 每 15 分鐘重算
		// ────────────────────────────────────────────
		$us_stats_table = $wpdb->prefix . 'anime_user_status_stats';
		$us_stats_sql   = "CREATE TABLE IF NOT EXISTS {$us_stats_table} (
			anime_id        BIGINT(20) UNSIGNED NOT NULL,
			want_count      INT UNSIGNED NOT NULL DEFAULT 0,
			watching_count  INT UNSIGNED NOT NULL DEFAULT 0,
			completed_count INT UNSIGNED NOT NULL DEFAULT 0,
			dropped_count   INT UNSIGNED NOT NULL DEFAULT 0,
			favorited_count INT UNSIGNED NOT NULL DEFAULT 0,
			total_count     INT UNSIGNED NOT NULL DEFAULT 0,
			updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (anime_id),
			KEY watching_count (watching_count),
			KEY favorited_count (favorited_count),
			KEY total_count (total_count)
		) {$charset_collate};";
		dbDelta( $us_stats_sql );
	}

	/**
	 * 工具方法：檢查資料表是否存在
	 *
	 * @param string $table_name_without_prefix 不含 wp_ 前綴的表名
	 */
	public function is_table_missing( string $table_name_without_prefix ): bool {
		global $wpdb;
		$full_name = $wpdb->prefix . $table_name_without_prefix;
		$result    = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name )
		);
		return $result !== $full_name;
	}

	// =========================================================================
	// 預設選項
	// =========================================================================

	/**
	 * 設定預設選項
	 *
	 * 使用 add_option() 而非 update_option()，
	 * 既有選項不會被覆蓋（保留使用者調整過的設定）。
	 */
	private function set_default_options(): void {
		foreach ( $this->get_default_options() as $key => $value ) {
			if ( get_option( $key, '__not_set__' ) === '__not_set__' ) {
				add_option( $key, $value );
			}
		}
	}

	// =========================================================================
	// 上傳目錄
	// =========================================================================

	/**
	 * 建立上傳目錄與安全防護檔
	 *
	 * [改進] 加入錯誤紀錄，避免 silent fail
	 *
	 * @return array{created:int, failed:int, errors:array<string>}
	 */
	private function create_upload_dirs(): array {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
				Anime_Sync_Error_Logger::error(
					'Cannot create upload dirs: ' . $upload_dir['error']
				);
			}
			return [ 'created' => 0, 'failed' => 0, 'errors' => [ $upload_dir['error'] ] ];
		}

		$dirs = [
			$upload_dir['basedir'] . '/anime-sync-pro',
			$upload_dir['basedir'] . '/anime-covers',
			$upload_dir['basedir'] . '/anime-sync-cache',
		];

		$created = 0;
		$failed  = 0;
		$errors  = [];

		foreach ( $dirs as $dir ) {
			if ( file_exists( $dir ) ) {
				continue;
			}

			if ( ! wp_mkdir_p( $dir ) ) {
				$failed++;
				$errors[] = "Failed to create: {$dir}";
				continue;
			}

			// 防護檔案：避免目錄列表外洩
			$htaccess_path = $dir . '/.htaccess';
			$index_path    = $dir . '/index.php';

			if ( ! file_exists( $htaccess_path ) ) {
				@file_put_contents( $htaccess_path, "Options -Indexes\n" );
			}
			if ( ! file_exists( $index_path ) ) {
				@file_put_contents( $index_path, "<?php // Silence is golden.\n" );
			}

			$created++;
		}

		if ( $failed > 0 && class_exists( 'Anime_Sync_Error_Logger' ) ) {
			Anime_Sync_Error_Logger::warning(
				"create_upload_dirs: {$failed} dir(s) failed",
				[ 'errors' => $errors ]
			);
		}

		return [
			'created' => $created,
			'failed'  => $failed,
			'errors'  => $errors,
		];
	}

	// =========================================================================
	// Rewrite 觸發旗標
	// =========================================================================

	/**
	 * 設定 rewrite flush 旗標
	 *
	 * CPT 在主檔的 init hook 中註冊，
	 * 此處只設定 option，由主檔 init priority 99 統一處理 flush。
	 */
	private function register_cpt_for_flush(): void {
		update_option( 'anime_sync_flush_rewrite', 1 );
	}
}
