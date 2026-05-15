<?php
/**
 * Notifications Email Digest System
 * 
 * 功能：
 * 1. WP-Cron 每日/每週 Email 摘要（台北時間 20:00 寄送）
 * 2. AJAX 端點：smacg_notif_save_prefs（儲存通知偏好）
 * 3. 管理員測試 URL：/wp-admin/?smacg_notif_test_digest=daily|weekly
 * 
 * 依賴：
 * - inc/notifications-system.php（提供 smacg_get_notification_prefs / smacg_update_notification_prefs）
 * - 資料表 wp_smacg_notifications
 * 
 * Version: 1.0.0
 * Date: 2026-05-13
 * Batch: 1C-4
 *
 * @package Blocksy_Child
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
            'display'  => __( 'Once Weekly', 'blocksy-child' ),
        );
    }
    return $schedules;
} );

/* =========================================================
 * 2. 註冊 Cron（台北 20:00 寄送）
 * ========================================================= */
add_action( 'init', 'smacg_notif_schedule_email_cron' );
function smacg_notif_schedule_email_cron() {
    // 計算下一個台北時間 20:00 的 UTC timestamp
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
        // 週寄送固定週一 20:00
        $monday_tw = new DateTime( 'next monday 20:00', $tz_tw );
        wp_schedule_event( $monday_tw->getTimestamp(), 'weekly', 'smacg_notif_email_weekly' );
    }
}

// 主題停用時清除 cron
register_deactivation_hook( __FILE__, 'smacg_notif_clear_email_cron' );
function smacg_notif_clear_email_cron() {
    wp_clear_scheduled_hook( 'smacg_notif_email_daily' );
    wp_clear_scheduled_hook( 'smacg_notif_email_weekly' );
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

    // 取得所有設定該頻率的使用者
    $user_ids = $wpdb->get_col( $wpdb->prepare( "
        SELECT user_id 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'smacg_notification_prefs' 
        AND meta_value LIKE %s
    ", '%' . $wpdb->esc_like( '"email_digest";s:' . strlen( $frequency ) . ':"' . $frequency . '"' ) . '%' ) );

    if ( empty( $user_ids ) ) {
        return 0;
    }

    // 時間範圍
    $hours_ago = ( $frequency === 'weekly' ) ? 168 : 24;
    $since     = gmdate( 'Y-m-d H:i:s', time() - $hours_ago * HOUR_IN_SECONDS );

    $sent = 0;
    foreach ( $user_ids as $uid ) {
        $uid = (int) $uid;
        if ( ! $uid ) continue;

        $user = get_userdata( $uid );
        if ( ! $user || empty( $user->user_email ) ) continue;

        // 取該用戶區間內通知
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT type, COUNT(*) AS cnt 
             FROM $table 
             WHERE user_id = %d AND created_at >= %s 
             GROUP BY type",
            $uid, $since
        ) );

        if ( empty( $rows ) ) continue;

        // 比對使用者 email 偏好（過濾關閉的類型）
        $prefs = smacg_get_notification_prefs( $uid );
        $type_label = array(
            'follow'        => '新追蹤者',
            'comment_reply' => '留言回覆',
            'rating'        => '評分互動',
            'level_up'      => '等級提升',
            'badge'         => '徽章解鎖',
            'system'        => '系統公告',
        );

        $lines = array();
        $total = 0;
        foreach ( $rows as $r ) {
            if ( empty( $prefs[ $r->type ]['email'] ) ) continue; // 該類 email 關閉就跳過
            $label = isset( $type_label[ $r->type ] ) ? $type_label[ $r->type ] : $r->type;
            $lines[] = "・{$label}：{$r->cnt} 則";
            $total  += (int) $r->cnt;
        }

        if ( $total === 0 ) continue;

        // 組信
        $site    = get_bloginfo( 'name' );
        $mc_url  = function_exists( 'smacg_get_member_center_url' ) ? smacg_get_member_center_url() : home_url( '/mc/' );
        $period  = ( $frequency === 'weekly' ) ? '本週' : '今日';
        $subject = sprintf( '[%s] %s共有 %d 則新通知', $site, $period, $total );

        $body  = "Hi {$user->display_name}，\n\n";
        $body .= "{$period}您在 {$site} 的通知摘要：\n\n";
        $body .= implode( "\n", $lines ) . "\n\n";
        $body .= "👉 前往會員中心查看：{$mc_url}?tab=notifications\n\n";
        $body .= "──\n";
        $body .= "若您不想再收到摘要信，請至會員中心 → 設定 → 通知偏好調整。\n";
        $body .= "{$site}\n";

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        if ( wp_mail( $user->user_email, $subject, $body, $headers ) ) {
            $sent++;
        }
    }

    // 寫入日誌（除錯用）
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "[SMACG Notif] {$frequency} digest sent: {$sent} users" );
    }

    return $sent;
}

/* =========================================================
 * 5. AJAX：儲存通知偏好
 * ========================================================= */
add_action( 'wp_ajax_smacg_notif_save_prefs', 'smacg_ajax_notif_save_prefs' );
function smacg_ajax_notif_save_prefs() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => '請先登入' ), 401 );
    }
    check_ajax_referer( 'smacg_notif_save_prefs', 'nonce' );

    $uid   = get_current_user_id();
    $input = isset( $_POST['prefs'] ) ? (array) $_POST['prefs'] : array();

    // 清洗：只接受預期欄位
    $allowed_types = array( 'follow', 'comment_reply', 'rating', 'level_up', 'badge', 'system' );
    $clean = array();
    foreach ( $allowed_types as $t ) {
        $clean[ $t ] = array(
            'site'  => ! empty( $input[ $t ]['site'] )  ? 1 : 0,
            'email' => ! empty( $input[ $t ]['email'] ) ? 1 : 0,
        );
    }
    $digest = isset( $input['email_digest'] ) ? sanitize_text_field( $input['email_digest'] ) : 'daily';
    if ( ! in_array( $digest, array( 'off', 'daily', 'weekly' ), true ) ) {
        $digest = 'daily';
    }
    $clean['email_digest'] = $digest;

    // 由 notifications-system.php 提供的 helper 寫入
    if ( function_exists( 'smacg_update_notification_prefs' ) ) {
        smacg_update_notification_prefs( $uid, $clean );
    } else {
        update_user_meta( $uid, 'smacg_notification_prefs', $clean );
    }

    wp_send_json_success( array(
        'message' => '已儲存',
        'prefs'   => $clean,
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
