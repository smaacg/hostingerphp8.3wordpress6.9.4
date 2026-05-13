<?php
/**
 * Notifications System — Email 摘要 + Cron
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-13)
 *
 * Cron：
 *   smacg_notif_email_daily   每天晚上 20:00（台灣時間）
 *   smacg_notif_email_weekly  每週日晚上 20:00
 *
 * 邏輯：
 *   - 撈出過去 24h（或 7d）有未讀通知的使用者
 *   - 對每位檢查偏好（email_digest === 'daily' / 'weekly'）
 *   - 對每位再依事件類型過濾（xxx_email 是否開啟）
 *   - 彙整成單封 HTML Email 寄出
 *
 * Email 內容：
 *   - 標題：「微笑動漫 - 你今天有 X 則新通知」
 *   - 列表：按類型分組（追蹤 / 留言 / 評分 / 徽章 / 系統）
 *   - 底部：管理偏好連結
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   排程：每天 20:00 / 每週日 20:00
   ============================================================ */
add_action( 'init', function() {

	// 每日摘要
	if ( ! wp_next_scheduled( 'smacg_notif_email_daily' ) ) {
		// 計算下一個 20:00（台灣時區）
		$tz = wp_timezone();
		$next = new DateTime( 'today 20:00:00', $tz );
		if ( $next->getTimestamp() <= time() ) {
			$next->modify( '+1 day' );
		}
		wp_schedule_event( $next->getTimestamp(), 'daily', 'smacg_notif_email_daily' );
	}

	// 每週摘要（星期日 20:00）
	if ( ! wp_next_scheduled( 'smacg_notif_email_weekly' ) ) {
		$tz = wp_timezone();
		$next = new DateTime( 'sunday 20:00:00', $tz );
		if ( $next->getTimestamp() <= time() ) {
			$next->modify( '+7 days' );
		}
		wp_schedule_event( $next->getTimestamp(), 'weekly', 'smacg_notif_email_weekly' );
	}
} );

// WordPress 預設沒有 weekly schedule，加上去
add_filter( 'cron_schedules', function( $s ) {
	if ( ! isset( $s['weekly'] ) ) {
		$s['weekly'] = [
			'interval' => 7 * DAY_IN_SECONDS,
			'display'  => '每週一次',
		];
	}
	return $s;
} );

/* ============================================================
   Cron Handler：每日摘要
   ============================================================ */
add_action( 'smacg_notif_email_daily',  function() { smacg_notif_send_digest( 'daily'  ); } );
add_action( 'smacg_notif_email_weekly', function() { smacg_notif_send_digest( 'weekly' ); } );

/**
 * 寄送摘要的主流程
 *
 * @param string $period  'daily' | 'weekly'
 */
function smacg_notif_send_digest( $period = 'daily' ) {
	global $wpdb;

	$hours = ( $period === 'weekly' ) ? 168 : 24;
	$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$hours} hours" ) );

	$table = smacg_notifications_table();

	// 撈出此期間內有「至少一筆通知」的所有使用者
	$user_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT user_id FROM {$table}
		 WHERE created_at >= %s
		 LIMIT 5000",
		$since
	) );

	if ( empty( $user_ids ) ) {
		return 0;
	}

	$sent = 0;
	$skipped = 0;

	foreach ( $user_ids as $uid ) {
		$uid = (int) $uid;
		$prefs = smacg_get_notification_prefs( $uid );

		// 只寄給選擇此週期的使用者
		if ( ( $prefs['email_digest'] ?? 'daily' ) !== $period ) {
			$skipped++;
			continue;
		}

		// 撈該使用者期間內的通知
		$notifs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE user_id = %d AND created_at >= %s
			 ORDER BY created_at DESC
			 LIMIT 100",
			$uid, $since
		), ARRAY_A );

		if ( empty( $notifs ) ) {
			$skipped++;
			continue;
		}

		// 依使用者偏好過濾（type_email 開關）
		$filtered = [];
		foreach ( $notifs as $n ) {
			$type = $n['type'];
			$email_pref_key = $type . '_email';
			if ( empty( $prefs[ $email_pref_key ] ) ) continue;

			$n['data'] = ! empty( $n['data'] ) ? json_decode( $n['data'], true ) : [];
			$filtered[] = $n;
		}

		if ( empty( $filtered ) ) {
			$skipped++;
			continue;
		}

		// 寄信
		if ( smacg_notif_send_digest_email( $uid, $filtered, $period ) ) {
			$sent++;
		}
	}

	// 記錄到 option 供管理員查看
	update_option( 'smacg_notif_last_digest_' . $period, [
		'time'    => current_time( 'mysql' ),
		'sent'    => $sent,
		'skipped' => $skipped,
		'total'   => count( $user_ids ),
	] );

	return $sent;
}

/**
 * 寄出單封 Email
 *
 * @param int    $uid
 * @param array  $notifs  已過濾後的通知陣列（每筆 data 已 decode）
 * @param string $period
 * @return bool
 */
function smacg_notif_send_digest_email( $uid, $notifs, $period ) {
	$user = get_userdata( $uid );
	if ( ! $user || ! is_email( $user->user_email ) ) return false;

	$count    = count( $notifs );
	$site     = get_bloginfo( 'name' );
	$display  = $user->display_name ?: $user->user_login;
	$mc_url   = home_url( '/mc/?tab=notifications' );
	$pref_url = home_url( '/mc/?tab=settings' );

	$subject = ( $period === 'weekly' )
		? sprintf( '[%s] 本週你有 %d 則新通知', $site, $count )
		: sprintf( '[%s] 你今天有 %d 則新通知', $site, $count );

	// 按類型分組
	$by_type = [];
	foreach ( $notifs as $n ) {
		$by_type[ $n['type'] ][] = $n;
	}

	$type_labels = [
		'follow'        => '👥 新增粉絲',
		'comment_reply' => '💬 留言回覆',
		'rating'        => '⭐ 你收藏的動畫被評分',
		'badge'         => '🏆 解鎖徽章',
		'level_up'      => '🚀 等級提升',
		'system'        => '📢 系統公告',
	];

	ob_start();
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="UTF-8">
		<style>
			body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans TC", sans-serif; background: #f3f4f6; color: #1f2937; }
			.wrap { max-width: 600px; margin: 24px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.06); }
			.header { padding: 24px 28px; background: linear-gradient(135deg, #f472b6, #a78bfa); color: #fff; }
			.header h1 { margin: 0 0 4px; font-size: 22px; }
			.header p { margin: 0; font-size: 14px; opacity: .9; }
			.body { padding: 24px 28px; }
			.group { margin-bottom: 22px; }
			.group:last-child { margin-bottom: 0; }
			.group h2 { margin: 0 0 10px; font-size: 16px; color: #374151; padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; }
			.item { padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
			.item:last-child { border-bottom: 0; }
			.item a { color: #6366f1; text-decoration: none; font-weight: 600; }
			.item .title { font-size: 14px; line-height: 1.5; color: #1f2937; }
			.item .excerpt { margin: 4px 0 0; font-size: 13px; color: #6b7280; line-height: 1.5; }
			.cta { text-align: center; padding: 18px 0 6px; }
			.cta a { display: inline-block; padding: 10px 28px; background: linear-gradient(135deg, #f472b6, #a78bfa); color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
			.footer { padding: 18px 28px; background: #f9fafb; color: #9ca3af; font-size: 12px; text-align: center; line-height: 1.6; }
			.footer a { color: #6b7280; text-decoration: underline; }
		</style>
	</head>
	<body>
	<div class="wrap">
		<div class="header">
			<h1>Hi, <?php echo esc_html( $display ); ?> 👋</h1>
			<p><?php echo ( $period === 'weekly' ) ? '本週' : '今天'; ?> 累積了 <?php echo $count; ?> 則通知，幫你彙整在這裡。</p>
		</div>

		<div class="body">
			<?php foreach ( $type_labels as $type => $label ) :
				if ( empty( $by_type[ $type ] ) ) continue;
				$items = $by_type[ $type ];
			?>
				<div class="group">
					<h2><?php echo $label; ?>（<?php echo count( $items ); ?>）</h2>
					<?php foreach ( $items as $n ) :
						$d = $n['data'];
						$title   = $d['title']   ?? '';
						$excerpt = $d['excerpt'] ?? '';
						$url     = $d['url']     ?? home_url( '/mc/' );
					?>
						<div class="item">
							<div class="title">
								<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
							</div>
							<?php if ( $excerpt ) : ?>
								<div class="excerpt"><?php echo esc_html( $excerpt ); ?></div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>

			<div class="cta">
				<a href="<?php echo esc_url( $mc_url ); ?>">查看所有通知 →</a>
			</div>
		</div>

		<div class="footer">
			你收到這封 Email 是因為訂閱了 <?php echo esc_html( $site ); ?> 的<?php echo ( $period === 'weekly' ) ? '每週' : '每日'; ?>通知摘要。<br>
			<a href="<?php echo esc_url( $pref_url ); ?>">管理通知偏好</a> ・
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $site ); ?></a>
		</div>
	</div>
	</body>
	</html>
	<?php
	$html = ob_get_clean();

	$headers = [
		'Content-Type: text/html; charset=UTF-8',
	];

	return wp_mail( $user->user_email, $subject, $html, $headers );
}

/* ============================================================
   管理員：手動觸發測試
   ------------------------------------------------------------
   訪問 wp-admin/?smacg_notif_test_digest=daily（或 weekly）
   ============================================================ */
add_action( 'admin_init', function() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	if ( empty( $_GET['smacg_notif_test_digest'] ) ) return;

	$period = $_GET['smacg_notif_test_digest'] === 'weekly' ? 'weekly' : 'daily';
	$sent = smacg_notif_send_digest( $period );

	add_action( 'admin_notices', function() use ( $sent, $period ) {
		echo '<div class="notice notice-success"><p><strong>['.$period.']</strong> 摘要 Email 已寄出 '.$sent.' 封</p></div>';
	} );
} );

/* ============================================================
   AJAX：儲存通知偏好（settings 頁用）
   ============================================================ */
add_action( 'wp_ajax_smacg_notif_save_prefs', function() {
	check_ajax_referer( 'smacg_notif_nonce', 'nonce' );

	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( [ 'message' => '請先登入' ], 401 );
	}

	$prefs = isset( $_POST['prefs'] ) ? (array) $_POST['prefs'] : [];

	// sanitize
	$clean = [];
	foreach ( $prefs as $k => $v ) {
		$k = sanitize_key( $k );
		if ( $k === 'email_digest' ) {
			$clean[ $k ] = in_array( $v, [ 'off', 'daily', 'weekly' ], true ) ? $v : 'daily';
		} else {
			$clean[ $k ] = $v ? 1 : 0;
		}
	}

	$ok = smacg_update_notification_prefs( $uid, $clean );

	if ( false === $ok ) {
		wp_send_json_error( [ 'message' => '儲存失敗' ] );
	}
	wp_send_json_success( [
		'message' => '已儲存',
		'prefs'   => smacg_get_notification_prefs( $uid ),
	] );
} );
