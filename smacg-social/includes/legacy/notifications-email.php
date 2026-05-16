<?php
/**
 * Notifications Email Digest System
 *
 * @version 1.1.0 (2026-05-16)
 *
 * v1.1.0 變更：
 *   - Bug #1 修正：偏好欄位統一為扁平結構（follow_email、follow_site...）
 *   - Bug #2 修正：移除無效的 register_deactivation_hook（清除 cron 改由 Deactivator 處理）
 *   - AJAX 儲存改為呼叫 smacg_update_notification_prefs()（會 merge 不會覆蓋）
 *
 * 功能：
 * 1. WP-Cron 每日/每週 Email 摘要（台北時間 20:00 寄送）
 * 2. AJAX 端點：smacg_notif_save_prefs（儲存通知偏好）
 * 3. 管理員測試 URL：/wp-admin/?smacg_notif_test_digest=daily|weekly
 *
 * 依賴：
 * - notifications-system.php（提供 smacg_get_notification_prefs / smacg_update_notification_prefs）
 * - 資料表 wp_smacg_notifications
 *
 * @package SMACG_Social
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 1. 自訂 Cron 排程（weekly）
 * ========================================================= */
add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['weekly'] ) ) {
        $schedules['weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Once Weekly', 'smacg-social' ),
        );
    }
    return $schedules;
} );

/* =========================================================
 * 2. 註冊 Cron（台北 20:00 寄送）
 * ========================================================= */
add_action( 'init', 'smacg_notif_schedule_email_cron' );
function smacg_notif_schedule_email_cron() {
    $tz_tw   = new DateTimeZone( 'Asia/Taipei' );
    $now_tw  = new DateTime( 'now', $tz_tw );
    $next_tw = new DateTime( 'today 20:00', $tz_tw );
    if ( $next_tw <= $now_tw ) {
        $next_tw->modify( '+1 day' );
    }
    $next_utc = $next_tw->getTimestamp();

    if ( ! wp_next_scheduled( 'smacg_notif_email_daily' ) ) {
        wp_schedule_event( $next_utc, 'daily', 'smacg_notif_email_daily' );
    }
    if ( ! wp_next_scheduled( 'smacg_notif_email_weekly' ) ) {
        $monday_tw = new DateTime( 'next monday 20:00', $tz_tw );
        wp_schedule_event( $monday_tw->getTimestamp(), 'weekly', 'smacg_notif_email_weekly' );
    }
}

/* =========================================================
 * 3. Cron Handlers
 * ========================================================= */
add_action( 'smacg_notif_email_daily',  function() { smacg_notif_send_digest( 'daily' );  } );
add_action( 'smacg_notif_email_weekly', function() { smacg_notif_send_digest( 'weekly' ); } );

/* =========================================================
 * 4. 寄送摘要主邏輯
 * ========================================================= */
function smacg_notif_send_digest( $frequency = 'daily' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'smacg_notifications';

    // 撈所有有 smacg_notification_prefs 的 user，再用 PHP 端比對
    // （比 LIKE serialize 字串穩定多）
    $user_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
        'smacg_notification_prefs'
    ) );

    if ( empty( $user_ids ) ) {
        return 0;
    }

    // 時間範圍
    $hours_ago = ( $frequency === 'weekly' ) ? 168 : 24;
    $since     = gmdate( 'Y-m-d H:i:s', time() - $hours_ago * HOUR_IN_SECONDS );

    $type_label = array(
        'follow'        => '新追蹤者',
        'comment_reply' => '留言回覆',
        'rating'        => '評分互動',
        'level_up'      => '等級提升',
        'badge'         => '徽章解鎖',
        'system'        => '系統公告',
    );

    $sent = 0;
    foreach ( $user_ids as $uid ) {
        $uid = (int) $uid;
        if ( ! $uid ) continue;

        // 偏好檢查：digest 頻率符合才繼續
        $prefs = smacg_get_notification_prefs( $uid );
        if ( ( $prefs['email_digest'] ?? 'off' ) !== $frequency ) continue;

        $user = get_userdata( $uid );
        if ( ! $user || empty( $user->user_email ) ) continue;

        // 取該用戶區間內通知，分類統計
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT type, COUNT(*) AS cnt
             FROM {$table}
             WHERE user_id = %d AND created_at >= %s
             GROUP BY type",
            $uid, $since
        ) );

        if ( empty( $rows ) ) continue;

        // 過濾掉「該類 email 偏好關閉」的類型（扁平結構：{type}_email）
        $lines = array();
        $total = 0;
        foreach ( $rows as $r ) {
            $key = $r->type . '_email';
            if ( empty( $prefs[ $key ] ) ) continue;

            $label   = $type_label[ $r->type ] ?? $r->type;
            $lines[] = "・{$label}：{$r->cnt} 則";
            $total  += (int) $r->cnt;
        }

        if ( $total === 0 ) continue;

        // 組信
        $site    = get_bloginfo( 'name' );
        $mc_url  = function_exists( 'smacg_get_member_center_url' )
            ? smacg_get_member_center_url()
            : home_url( '/mc/' );
        $period  = ( $frequency === 'weekly' ) ? '本週' : '今日';
        $subject = sprintf( '[%s] %s共有 %d 則新通知', $site, $period, $total );

        $body  = "Hi {$user->display_name}，\n\n";
        $body .= "{$period}您在 {$site} 的通知摘要：\n\n";
        $body .= implode( "\n", $lines ) . "\n\n";
        $body .= "👉 前往會員中心查看：{$mc_url}?tab=notifications\n\n";
        $body .= "──\n";
        $body .= "若您不想再收到摘要信，請至會員中心 → 設定 → 通知偏好 → 將「Email 摘要」改為「不寄送」。\n";
        $body .= "{$site}\n";

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        if ( wp_mail( $user->user_email, $subject, $body, $headers ) ) {
            $sent++;
        }
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "[SMACG Notif] {$frequency} digest sent: {$sent} users" );
    }

    return $sent;
}

/* =========================================================
 * 5. AJAX：儲存通知偏好
 *    輸入格式：$_POST['prefs'] = [
 *      'follow'        => ['site'=>1,'email'=>0],
 *      'comment_reply' => ['site'=>1,'email'=>0],
 *      ...
 *      'email_digest'  => 'off' | 'daily' | 'weekly'
 *    ]
 *    內部會轉換為扁平 key 寫入 user_meta
 * ========================================================= */
add_action( 'wp_ajax_smacg_notif_save_prefs', 'smacg_ajax_notif_save_prefs' );
function smacg_ajax_notif_save_prefs() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => '請先登入' ), 401 );
    }
    check_ajax_referer( 'smacg_notif_save_prefs', 'nonce' );

    $uid   = get_current_user_id();
    $input = isset( $_POST['prefs'] ) ? (array) $_POST['prefs'] : array();

    $allowed_types = array( 'follow', 'comment_reply', 'rating', 'level_up', 'badge', 'system' );

    // 把巢狀 input 轉為扁平 key
    $clean = array();
    foreach ( $allowed_types as $t ) {
        $clean[ $t . '_site' ]  = ! empty( $input[ $t ]['site'] )  ? 1 : 0;
        $clean[ $t . '_email' ] = ! empty( $input[ $t ]['email'] ) ? 1 : 0;
    }

    $digest = isset( $input['email_digest'] ) ? sanitize_text_field( $input['email_digest'] ) : 'off';
    if ( ! in_array( $digest, array( 'off', 'daily', 'weekly' ), true ) ) {
        $digest = 'off';
    }
    $clean['email_digest'] = $digest;

    // 統一走 smacg_update_notification_prefs（會 merge 不會覆蓋）
    if ( function_exists( 'smacg_update_notification_prefs' ) ) {
        smacg_update_notification_prefs( $uid, $clean );
    } else {
        wp_send_json_error( array( 'message' => '通知系統未載入' ), 500 );
    }

    wp_send_json_success( array(
        'message' => '已儲存',
        'prefs'   => smacg_get_notification_prefs( $uid ),
    ) );
}

/* =========================================================
 * 6. 管理員測試入口
 *    /wp-admin/?smacg_notif_test_digest=daily
 *    /wp-admin/?smacg_notif_test_digest=weekly
 * ========================================================= */
add_action( 'admin_init', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( empty( $_GET['smacg_notif_test_digest'] ) ) return;

    $freq = sanitize_text_field( $_GET['smacg_notif_test_digest'] );
    if ( ! in_array( $freq, array( 'daily', 'weekly' ), true ) ) return;

    $sent = smacg_notif_send_digest( $freq );
    wp_die( sprintf( '✅ Test digest (%s) executed. Emails sent: %d', esc_html( $freq ), (int) $sent ) );
} );
