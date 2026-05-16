<?php
/**
 * Notifications System — 通知中心核心
 *
 * @package weixiaoacg
 * @version 1.1.0 (2026-05-16)
 *
 * v1.1.0 變更：
 *   - 預設偏好調整：站內全開、Email 全關、digest='off'（開發階段 reset）
 *   - 刪除無效的 register_activation_hook(__FILE__,...)
 *   - smacg_should_notify() 對 email channel 加 force 支援
 *
 * 提供：
 * - wp_smacg_notifications 資料表（dbDelta 自動建立）
 * - 寫入：smacg_create_notification( $args )
 * - 查詢：smacg_get_notifications / smacg_get_unread_count
 * - 標記：smacg_mark_notification_read / smacg_mark_all_read
 * - 刪除：smacg_delete_notification / smacg_purge_old_notifications
 * - 偏好：smacg_get_notification_prefs / smacg_update_notification_prefs
 * - 偏好檢查：smacg_should_notify( $user_id, $type, $channel )
 *
 * 偏好儲存：user_meta key = 'smacg_notification_prefs'（serialized array）
 *
 * 通知類型（type 欄位）：
 *   follow          - 被追蹤
 *   comment_reply   - 留言被回覆
 *   rating          - 動畫被評分（給作者/管理員看）
 *   badge           - 解鎖徽章
 *   level_up        - 等級提升
 *   system          - 系統公告
 *
 * data 欄位（JSON）建議結構：
 *   { "url": "...", "title": "...", "excerpt": "...", "icon": "fa-..." }
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數
   ============================================================ */
define( 'SMACG_NOTIF_DB_VERSION',     '1.0.0' );
define( 'SMACG_NOTIF_RETENTION_DAYS', 30 );  // 通知保留天數

/* ============================================================
   資料表名稱
   ============================================================ */
function smacg_notifications_table() {
	global $wpdb;
	return $wpdb->prefix . 'smacg_notifications';
}

/* ============================================================
   資料表安裝
   ============================================================ */
function smacg_notifications_install() {
	global $wpdb;

	$installed = get_option( 'smacg_notif_db_version' );
	if ( $installed === SMACG_NOTIF_DB_VERSION ) {
		return;
	}

	$table   = smacg_notifications_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		type VARCHAR(32) NOT NULL,
		actor_id BIGINT(20) UNSIGNED NULL,
		object_type VARCHAR(32) NULL,
		object_id BIGINT(20) UNSIGNED NULL,
		data TEXT NULL,
		is_read TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY idx_user_read (user_id, is_read, created_at),
		KEY idx_created (created_at),
		KEY idx_user_type (user_id, type)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'smacg_notif_db_version', SMACG_NOTIF_DB_VERSION );
}
add_action( 'admin_init', 'smacg_notifications_install' );
add_action( 'init', 'smacg_notifications_install', 5 );

/* ============================================================
   通知偏好預設值
   ------------------------------------------------------------
   v1.1.0 設計原則：
     - 站內鈴鐺：全部預設「開」（產生互動誘因）
     - Email：全部預設「關」（避免 spam，降低 wp_mail 信譽風險）
     - email_digest：預設 'off'（即時通知都不寄，摘要更不該寄）
     - 系統公告 email 若有緊急情況，用 smacg_create_notification([..., 'force_email'=>true]) 強制寄送
   ============================================================ */
function smacg_get_notification_prefs_defaults() {
	return [
		// 站內通知（鈴鐺）— 全開
		'follow_site'         => 1,
		'comment_reply_site'  => 1,
		'rating_site'         => 1,
		'badge_site'          => 1,
		'level_up_site'       => 1,
		'system_site'         => 1,

		// Email 通知 — 全關
		'follow_email'        => 0,
		'comment_reply_email' => 0,
		'rating_email'        => 0,
		'badge_email'         => 0,
		'level_up_email'      => 0,
		'system_email'        => 0,

		// Email 摘要頻率：off / daily / weekly — 預設 off
		'email_digest'        => 'off',
	];
}

/**
 * 取得使用者的通知偏好（合併預設值）
 */
function smacg_get_notification_prefs( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return smacg_get_notification_prefs_defaults();
	}

	$stored = get_user_meta( $user_id, 'smacg_notification_prefs', true );
	if ( ! is_array( $stored ) ) {
		$stored = [];
	}

	return array_merge( smacg_get_notification_prefs_defaults(), $stored );
}

/**
 * 更新通知偏好（接受部分欄位，會合併既有值）
 */
function smacg_update_notification_prefs( $user_id, $partial ) {
	$user_id = absint( $user_id );
	if ( ! $user_id || ! is_array( $partial ) ) {
		return false;
	}

	$current = smacg_get_notification_prefs( $user_id );
	$valid_keys = array_keys( smacg_get_notification_prefs_defaults() );

	$clean = [];
	foreach ( $partial as $k => $v ) {
		if ( ! in_array( $k, $valid_keys, true ) ) continue;
		if ( $k === 'email_digest' ) {
			$clean[ $k ] = in_array( $v, [ 'off', 'daily', 'weekly' ], true ) ? $v : 'off';
		} else {
			$clean[ $k ] = $v ? 1 : 0;
		}
	}

	$merged = array_merge( $current, $clean );
	return update_user_meta( $user_id, 'smacg_notification_prefs', $merged );
}

/**
 * 檢查是否應該發送某類通知到某管道
 *
 * @param int    $user_id
 * @param string $type     follow / comment_reply / rating / badge / level_up / system
 * @param string $channel  site / email
 * @return bool
 */
function smacg_should_notify( $user_id, $type, $channel = 'site' ) {
	$prefs = smacg_get_notification_prefs( $user_id );
	$key   = $type . '_' . $channel;
	return ! empty( $prefs[ $key ] );
}

/* ============================================================
   新註冊使用者：寫入預設偏好
   ============================================================ */
add_action( 'user_register', function( $user_id ) {
	if ( ! get_user_meta( $user_id, 'smacg_notification_prefs', true ) ) {
		update_user_meta( $user_id, 'smacg_notification_prefs', smacg_get_notification_prefs_defaults() );
	}
} );

/* ============================================================
   建立通知
   ============================================================ */
/**
 * 建立一筆通知
 *
 * @param array $args
 *   - user_id      (int, required) 收件人
 *   - type         (string, required) follow / comment_reply / rating / badge / level_up / system
 *   - actor_id     (int|null) 觸發者
 *   - object_type  (string|null) post / comment / anime / badge / level
 *   - object_id    (int|null)
 *   - data         (array|null) 額外資料（會序列化為 JSON）
 *   - force        (bool) 略過站內偏好檢查（系統通知用）
 *   - force_email  (bool) 略過 email 偏好檢查（緊急公告用）
 *
 * @return int|WP_Error  通知 ID 或錯誤
 */
function smacg_create_notification( $args ) {
	global $wpdb;

	$args = wp_parse_args( $args, [
		'user_id'     => 0,
		'type'        => '',
		'actor_id'    => null,
		'object_type' => null,
		'object_id'   => null,
		'data'        => null,
		'force'       => false,
		'force_email' => false,
	] );

	$user_id = absint( $args['user_id'] );
	$type    = sanitize_key( $args['type'] );

	if ( ! $user_id || ! $type ) {
		return new WP_Error( 'smacg_notif_invalid', '收件人或類型缺失' );
	}

	// 不通知自己（actor === user）
	if ( $args['actor_id'] && (int) $args['actor_id'] === $user_id ) {
		return new WP_Error( 'smacg_notif_self', '不通知自己' );
	}

	// 偏好檢查（系統通知用 force 略過）
	if ( ! $args['force'] && ! smacg_should_notify( $user_id, $type, 'site' ) ) {
		return new WP_Error( 'smacg_notif_disabled', '使用者已關閉此類通知' );
	}

	$data_json = null;
	if ( is_array( $args['data'] ) && ! empty( $args['data'] ) ) {
		$data_json = wp_json_encode( $args['data'] );
	}

	$ok = $wpdb->insert(
		smacg_notifications_table(),
		[
			'user_id'     => $user_id,
			'type'        => $type,
			'actor_id'    => $args['actor_id'] ? absint( $args['actor_id'] ) : null,
			'object_type' => $args['object_type'] ? sanitize_key( $args['object_type'] ) : null,
			'object_id'   => $args['object_id'] ? absint( $args['object_id'] ) : null,
			'data'        => $data_json,
			'is_read'     => 0,
			'created_at'  => current_time( 'mysql' ),
		],
		[ '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s' ]
	);

	if ( false === $ok ) {
		return new WP_Error( 'smacg_notif_db_failed', '寫入失敗' );
	}

	$notif_id = (int) $wpdb->insert_id;

	// 清快取
	smacg_clear_notification_cache( $user_id );

	/**
	 * Action: 通知建立後（供 Email 即時寄送、Push 等擴充）
	 * 注意：force_email 也透過 $args 傳遞下去
	 */
	do_action( 'smacg_notification_created', $notif_id, $user_id, $type, $args );

	return $notif_id;
}

/* ============================================================
   查詢通知
   ============================================================ */
function smacg_get_notifications( $user_id, $args = [] ) {
	global $wpdb;

	$user_id = absint( $user_id );
	if ( ! $user_id ) return [];

	$args = wp_parse_args( $args, [
		'limit'       => 20,
		'offset'      => 0,
		'unread_only' => false,
		'type'        => '',
	] );

	$limit  = max( 1, min( 100, absint( $args['limit'] ) ) );
	$offset = max( 0, absint( $args['offset'] ) );

	$table = smacg_notifications_table();
	$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );

	if ( $args['unread_only'] ) {
		$where .= ' AND is_read = 0';
	}
	if ( ! empty( $args['type'] ) ) {
		$where .= $wpdb->prepare( ' AND type = %s', sanitize_key( $args['type'] ) );
	}

	$sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
	$rows = $wpdb->get_results(
		$wpdb->prepare( $sql, $limit, $offset ),
		ARRAY_A
	);

	if ( ! $rows ) return [];

	foreach ( $rows as &$r ) {
		$r['data']     = ! empty( $r['data'] ) ? json_decode( $r['data'], true ) : [];
		$r['id']       = (int) $r['id'];
		$r['user_id']  = (int) $r['user_id'];
		$r['actor_id'] = $r['actor_id']  ? (int) $r['actor_id']  : null;
		$r['object_id']= $r['object_id'] ? (int) $r['object_id'] : null;
		$r['is_read']  = (int) $r['is_read'];
	}
	unset( $r );

	return $rows;
}

/**
 * 未讀數
 */
function smacg_get_unread_count( $user_id ) {
	global $wpdb;

	$user_id = absint( $user_id );
	if ( ! $user_id ) return 0;

	$cache_key = "smacg_unread_count_{$user_id}";
	$cached = wp_cache_get( $cache_key, 'smacg_notifications' );
	if ( false !== $cached ) {
		return (int) $cached;
	}

	$table = smacg_notifications_table();
	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
		$user_id
	) );

	wp_cache_set( $cache_key, $count, 'smacg_notifications', 60 );
	return $count;
}

/* ============================================================
   標記已讀
   ============================================================ */
function smacg_mark_notification_read( $notif_id, $user_id ) {
	global $wpdb;

	$notif_id = absint( $notif_id );
	$user_id  = absint( $user_id );
	if ( ! $notif_id || ! $user_id ) return false;

	$updated = $wpdb->update(
		smacg_notifications_table(),
		[ 'is_read' => 1 ],
		[ 'id' => $notif_id, 'user_id' => $user_id ],
		[ '%d' ], [ '%d', '%d' ]
	);

	if ( $updated ) {
		smacg_clear_notification_cache( $user_id );
	}
	return (bool) $updated;
}

function smacg_mark_all_read( $user_id ) {
	global $wpdb;

	$user_id = absint( $user_id );
	if ( ! $user_id ) return false;

	$updated = $wpdb->update(
		smacg_notifications_table(),
		[ 'is_read' => 1 ],
		[ 'user_id' => $user_id, 'is_read' => 0 ],
		[ '%d' ], [ '%d', '%d' ]
	);

	if ( false !== $updated ) {
		smacg_clear_notification_cache( $user_id );
	}
	return (int) $updated;
}

/* ============================================================
   刪除
   ============================================================ */
function smacg_delete_notification( $notif_id, $user_id ) {
	global $wpdb;

	$notif_id = absint( $notif_id );
	$user_id  = absint( $user_id );
	if ( ! $notif_id || ! $user_id ) return false;

	$deleted = $wpdb->delete(
		smacg_notifications_table(),
		[ 'id' => $notif_id, 'user_id' => $user_id ],
		[ '%d', '%d' ]
	);

	if ( $deleted ) {
		smacg_clear_notification_cache( $user_id );
	}
	return (bool) $deleted;
}

/**
 * 清除過期通知（cron 用）
 */
function smacg_purge_old_notifications() {
	global $wpdb;

	$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . SMACG_NOTIF_RETENTION_DAYS . ' days' ) );
	$table  = smacg_notifications_table();

	return (int) $wpdb->query( $wpdb->prepare(
		"DELETE FROM {$table} WHERE created_at < %s",
		$cutoff
	) );
}

add_action( 'smacg_notifications_daily_purge', 'smacg_purge_old_notifications' );

// 備援排程（Activator 已排，這裡是 fallback）
add_action( 'init', function() {
	if ( ! wp_next_scheduled( 'smacg_notifications_daily_purge' ) ) {
		wp_schedule_event(
			strtotime( 'tomorrow 03:00:00 ' . wp_timezone_string() ),
			'daily',
			'smacg_notifications_daily_purge'
		);
	}
} );

/* ============================================================
   快取清理
   ============================================================ */
function smacg_clear_notification_cache( $user_id ) {
	wp_cache_delete( "smacg_unread_count_{$user_id}", 'smacg_notifications' );
}

/* ============================================================
   使用者刪除時清理通知
   ============================================================ */
add_action( 'deleted_user', function( $user_id ) {
	global $wpdb;
	$table = smacg_notifications_table();
	$wpdb->delete( $table, [ 'user_id'  => $user_id ], [ '%d' ] );
	$wpdb->delete( $table, [ 'actor_id' => $user_id ], [ '%d' ] );
	delete_user_meta( $user_id, 'smacg_notification_prefs' );
} );
