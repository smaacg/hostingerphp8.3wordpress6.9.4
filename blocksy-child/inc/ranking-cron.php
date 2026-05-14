<?php
/**
 * Ranking Cron — 排程重算 + 手動觸發 endpoint
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-1
 *
 * 排程：
 *   smacg_ranking_recalc       每小時重算 4 種排行
 *   smacg_ranking_monthly_purge 每天凌晨清理 24 個月前的月度資料
 *
 * 手動觸發（限管理員）：
 *   /wp-admin/admin-post.php?action=smacg_ranking_rebuild&_wpnonce=...
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   排程註冊
   ============================================================ */

/**
 * 啟用時排程（after_switch_theme）
 */
function smacg_ranking_schedule_events() {
    if ( ! wp_next_scheduled( 'smacg_ranking_recalc' ) ) {
        wp_schedule_event( time() + 60, 'hourly', 'smacg_ranking_recalc' );
    }
    if ( ! wp_next_scheduled( 'smacg_ranking_monthly_purge' ) ) {
        // 每天凌晨 04:00（伺服器時間）
        $first = strtotime( 'tomorrow 04:00' );
        wp_schedule_event( $first, 'daily', 'smacg_ranking_monthly_purge' );
    }
}
add_action( 'after_switch_theme', 'smacg_ranking_schedule_events' );

/**
 * 確保 init 時也檢查一次（避免主題切換後 hook 丟失）
 */
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'smacg_ranking_recalc' ) ) {
        wp_schedule_event( time() + 60, 'hourly', 'smacg_ranking_recalc' );
    }
}, 20 );

/* ============================================================
   Cron handlers
   ============================================================ */

/**
 * 每小時重算
 */
add_action( 'smacg_ranking_recalc', function () {
    if ( ! function_exists( 'smacg_ranking_rebuild_all' ) ) return;
    $log = smacg_ranking_rebuild_all();
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[smacg ranking] rebuild ' . wp_json_encode( $log ) );
    }
} );

/**
 * 每天清理
 */
add_action( 'smacg_ranking_monthly_purge', function () {
    if ( function_exists( 'smacg_ranking_purge_old_monthly' ) ) {
        smacg_ranking_purge_old_monthly();
    }
} );

/* ============================================================
   反排程（移除主題時呼叫）
   ============================================================ */
function smacg_ranking_unschedule_events() {
    foreach ( [ 'smacg_ranking_recalc', 'smacg_ranking_monthly_purge' ] as $hook ) {
        $ts = wp_next_scheduled( $hook );
        if ( $ts ) wp_unschedule_event( $ts, $hook );
    }
}
add_action( 'switch_theme', 'smacg_ranking_unschedule_events' );

/* ============================================================
   手動觸發 endpoint（admin only）
   ------------------------------------------------------------
   用途：剛部署時、調整 EXP 規則後不想等 1 小時。
   URL：/wp-admin/admin-post.php?action=smacg_ranking_rebuild&_wpnonce=XXX
        nonce 可在 admin bar 取得（見下方 admin bar 連結）
   ============================================================ */
add_action( 'admin_post_smacg_ranking_rebuild', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '權限不足', 403 );
    }
    check_admin_referer( 'smacg_ranking_rebuild' );

    $log = smacg_ranking_rebuild_all();
    $msg = '排行榜已重算：' . wp_json_encode( $log, JSON_UNESCAPED_UNICODE );

    wp_safe_redirect( add_query_arg( [
        'smacg_msg' => rawurlencode( $msg ),
    ], admin_url( 'index.php' ) ) );
    exit;
} );

/* ---- 在 admin bar 加快捷鈕（給管理員） ---- */
add_action( 'admin_bar_menu', function ( $bar ) {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $bar->add_node( [
        'id'    => 'smacg-ranking-rebuild',
        'title' => '🏆 重算排行榜',
        'href'  => wp_nonce_url(
            admin_url( 'admin-post.php?action=smacg_ranking_rebuild' ),
            'smacg_ranking_rebuild'
        ),
        'meta'  => [ 'title' => '立即重算 wp_smacg_rankings' ],
    ] );
}, 100 );

/* ---- 顯示重算結果 admin notice ---- */
add_action( 'admin_notices', function () {
    if ( empty( $_GET['smacg_msg'] ) ) return;
    echo '<div class="notice notice-success is-dismissible"><p>'
       . esc_html( rawurldecode( wp_unslash( $_GET['smacg_msg'] ) ) )
       . '</p></div>';
} );
