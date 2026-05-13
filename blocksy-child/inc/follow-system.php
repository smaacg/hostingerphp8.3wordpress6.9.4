<?php
/**
 * Follow System — 追蹤系統核心
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-13)
 *
 * 提供功能：
 * - wp_smacg_follows 資料表自動建立 / 升級（dbDelta）
 * - smacg_follow_user( $follower, $following )
 * - smacg_unfollow_user( $follower, $following )
 * - smacg_is_following( $follower, $following )
 * - smacg_get_following_count( $user_id )
 * - smacg_get_followers_count( $user_id )
 * - smacg_get_following_ids( $user_id, $limit, $offset )
 * - smacg_get_followers_ids( $user_id, $limit, $offset )
 *
 * Action hooks（供 Phase 1C 通知系統接上）：
 * - do_action( 'smacg_user_followed',   $follower_id, $following_id )
 * - do_action( 'smacg_user_unfollowed', $follower_id, $following_id )
 *
 * 限制：
 * - 不能追蹤自己
 * - 不能追蹤未啟用 / 不存在的使用者
 * - UNIQUE(follower_id, following_id) 防止重複追蹤
 * - 單日上限 SMACG_FOLLOW_DAILY_LIMIT（預設 200）
 * - 連點冷卻 SMACG_FOLLOW_COOLDOWN 秒（預設 1）
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   資料表名稱 helper
   ============================================================ */
function smacg_follows_table() {
	global $wpdb;
	return $wpdb->prefix . 'smacg_follows';
}

/* ============================================================
   資料表安裝 / 升級
   ============================================================ */
define( 'SMACG_FOLLOWS_DB_VERSION', '1.0.0' );

function smacg_follows_install() {
	global $wpdb;

	$installed = get_option( 'smacg_follows_db_version' );
	if ( $installed === SMACG_FOLLOWS_DB_VERSION ) {
		return;
	}

	$table   = smacg_follows_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		follower_id BIGINT(20) UNSIGNED NOT NULL,
		following_id BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY uniq_pair (follower_id, following_id),
		KEY idx_following (following_id),
		KEY idx_follower_created (follower_id, created_at)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'smacg_follows_db_version', SMACG_FOLLOWS_DB_VERSION );
}
// 每次 admin_init 檢查一次（成本極低，只在 version mismatch 時跑 dbDelta）
add_action( 'admin_init', 'smacg_follows_install' );
// 也在前端首次載入時嘗試一次，避免管理員從未進後台時資料表沒建立
add_action( 'init', 'smacg_follows_install', 5 );

/* ============================================================
   核心：追蹤
   ============================================================ */
/**
 * 追蹤一位使用者
 *
 * @param int $follower_id   追蹤者
 * @param int $following_id  被追蹤者
 * @return true|WP_Error
 */
function smacg_follow_user( $follower_id, $following_id ) {
	global $wpdb;

	$follower_id  = absint( $follower_id );
	$following_id = absint( $following_id );

	// 基本驗證
	if ( ! $follower_id || ! $following_id ) {
		return new WP_Error( 'smacg_invalid_user', '使用者 ID 無效' );
	}
	if ( $follower_id === $following_id ) {
		return new WP_Error( 'smacg_self_follow', '不能追蹤自己' );
	}
	if ( ! get_userdata( $following_id ) ) {
		return new WP_Error( 'smacg_target_not_found', '目標使用者不存在' );
	}

	// 連點冷卻（同一對 follower-following 在 SMACG_FOLLOW_COOLDOWN 秒內只能操作一次）
	$cd_key = 'smacg_follow_cd_' . $follower_id . '_' . $following_id;
	if ( get_transient( $cd_key ) ) {
		return new WP_Error( 'smacg_follow_cooldown', '操作太頻繁，請稍候' );
	}

	// 單日上限
	$today_count = smacg_get_follow_today_count( $follower_id );
	if ( $today_count >= SMACG_FOLLOW_DAILY_LIMIT ) {
		return new WP_Error(
			'smacg_follow_daily_limit',
			sprintf( '今日追蹤已達上限 %d 人，明天再試', SMACG_FOLLOW_DAILY_LIMIT )
		);
	}

	// 已追蹤？
	if ( smacg_is_following( $follower_id, $following_id ) ) {
		return new WP_Error( 'smacg_already_following', '已經在追蹤了' );
	}

	$table = smacg_follows_table();
	$ok = $wpdb->insert(
		$table,
		[
			'follower_id'  => $follower_id,
			'following_id' => $following_id,
			'created_at'   => current_time( 'mysql' ),
		],
		[ '%d', '%d', '%s' ]
	);

	if ( false === $ok ) {
		return new WP_Error( 'smacg_db_insert_failed', '資料庫寫入失敗' );
	}

	// 設定冷卻
	set_transient( $cd_key, 1, SMACG_FOLLOW_COOLDOWN );

	// 清快取
	smacg_clear_follow_cache( $follower_id, $following_id );

	/**
	 * Action: smacg_user_followed
	 * Phase 1C 通知系統會接這個 hook
	 */
	do_action( 'smacg_user_followed', $follower_id, $following_id );

	return true;
}

/**
 * 取消追蹤
 *
 * @param int $follower_id
 * @param int $following_id
 * @return true|WP_Error
 */
function smacg_unfollow_user( $follower_id, $following_id ) {
	global $wpdb;

	$follower_id  = absint( $follower_id );
	$following_id = absint( $following_id );

	if ( ! $follower_id || ! $following_id ) {
		return new WP_Error( 'smacg_invalid_user', '使用者 ID 無效' );
	}

	$table = smacg_follows_table();
	$deleted = $wpdb->delete(
		$table,
		[
			'follower_id'  => $follower_id,
			'following_id' => $following_id,
		],
		[ '%d', '%d' ]
	);

	if ( false === $deleted ) {
		return new WP_Error( 'smacg_db_delete_failed', '資料庫刪除失敗' );
	}
	if ( 0 === $deleted ) {
		return new WP_Error( 'smacg_not_following', '尚未追蹤' );
	}

	smacg_clear_follow_cache( $follower_id, $following_id );

	do_action( 'smacg_user_unfollowed', $follower_id, $following_id );

	return true;
}

/* ============================================================
   查詢 helpers
   ============================================================ */
/**
 * 是否正在追蹤
 */
function smacg_is_following( $follower_id, $following_id ) {
	global $wpdb;

	$follower_id  = absint( $follower_id );
	$following_id = absint( $following_id );
	if ( ! $follower_id || ! $following_id ) {
		return false;
	}

	$cache_key = "smacg_is_following_{$follower_id}_{$following_id}";
	$cached    = wp_cache_get( $cache_key, 'smacg_follows' );
	if ( false !== $cached ) {
		return (bool) $cached;
	}

	$table = smacg_follows_table();
	$row   = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$table} WHERE follower_id = %d AND following_id = %d LIMIT 1",
		$follower_id,
		$following_id
	) );

	$result = ! empty( $row );
	wp_cache_set( $cache_key, $result ? 1 : 0, 'smacg_follows', 300 );

	return $result;
}

/**
 * 追蹤中人數（user 追蹤了多少人）
 */
function smacg_get_following_count( $user_id ) {
	global $wpdb;

	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return 0;
	}

	$cache_key = "smacg_following_count_{$user_id}";
	$cached    = wp_cache_get( $cache_key, 'smacg_follows' );
	if ( false !== $cached ) {
		return (int) $cached;
	}

	$table = smacg_follows_table();
	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE follower_id = %d",
		$user_id
	) );

	wp_cache_set( $cache_key, $count, 'smacg_follows', 300 );
	return $count;
}

/**
 * 粉絲數（多少人追蹤這個 user）
 */
function smacg_get_followers_count( $user_id ) {
	global $wpdb;

	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return 0;
	}

	$cache_key = "smacg_followers_count_{$user_id}";
	$cached    = wp_cache_get( $cache_key, 'smacg_follows' );
	if ( false !== $cached ) {
		return (int) $cached;
	}

	$table = smacg_follows_table();
	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE following_id = %d",
		$user_id
	) );

	wp_cache_set( $cache_key, $count, 'smacg_follows', 300 );
	return $count;
}

/**
 * 取得 user 追蹤的 ID 清單（最新優先）
 */
function smacg_get_following_ids( $user_id, $limit = 20, $offset = 0 ) {
	global $wpdb;

	$user_id = absint( $user_id );
	$limit   = max( 1, min( 100, absint( $limit ) ) );
	$offset  = max( 0, absint( $offset ) );
	if ( ! $user_id ) {
		return [];
	}

	$table = smacg_follows_table();
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT following_id FROM {$table}
		 WHERE follower_id = %d
		 ORDER BY created_at DESC
		 LIMIT %d OFFSET %d",
		$user_id, $limit, $offset
	) );

	return array_map( 'intval', $ids ?: [] );
}

/**
 * 取得 user 的粉絲 ID 清單（最新優先）
 */
function smacg_get_followers_ids( $user_id, $limit = 20, $offset = 0 ) {
	global $wpdb;

	$user_id = absint( $user_id );
	$limit   = max( 1, min( 100, absint( $limit ) ) );
	$offset  = max( 0, absint( $offset ) );
	if ( ! $user_id ) {
		return [];
	}

	$table = smacg_follows_table();
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT follower_id FROM {$table}
		 WHERE following_id = %d
		 ORDER BY created_at DESC
		 LIMIT %d OFFSET %d",
		$user_id, $limit, $offset
	) );

	return array_map( 'intval', $ids ?: [] );
}

/**
 * 今日追蹤次數（防刷用）
 */
function smacg_get_follow_today_count( $user_id ) {
	global $wpdb;

	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return 0;
	}

	$table     = smacg_follows_table();
	$today_gmt = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table}
		 WHERE follower_id = %d AND created_at >= %s",
		$user_id, $today_gmt
	) );
}

/* ============================================================
   快取清理
   ============================================================ */
function smacg_clear_follow_cache( $follower_id, $following_id ) {
	wp_cache_delete( "smacg_is_following_{$follower_id}_{$following_id}", 'smacg_follows' );
	wp_cache_delete( "smacg_following_count_{$follower_id}",              'smacg_follows' );
	wp_cache_delete( "smacg_followers_count_{$following_id}",             'smacg_follows' );
}

/* ============================================================
   使用者刪除時清理追蹤記錄
   ============================================================ */
add_action( 'deleted_user', function( $user_id ) {
	global $wpdb;
	$table = smacg_follows_table();
	$wpdb->delete( $table, [ 'follower_id'  => $user_id ], [ '%d' ] );
	$wpdb->delete( $table, [ 'following_id' => $user_id ], [ '%d' ] );
} );
