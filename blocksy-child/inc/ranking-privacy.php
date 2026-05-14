<?php
/**
 * Ranking Privacy — 使用者隱私開關
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-1
 *
 * 提供：
 *   user_meta 'smacg_appear_in_ranking'   '1' = 公開（預設）, '0' = 隱藏
 *   AJAX  smacg_toggle_ranking_visibility（登入用戶）
 *   helper smacg_user_appears_in_ranking( $uid )
 *   新註冊預設值（user_register hook）
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數 + 預設值
   ============================================================ */
const SMACG_RANKING_META_KEY = 'smacg_appear_in_ranking';

/**
 * 是否出現在排行榜
 */
function smacg_user_appears_in_ranking( $uid ) {
    $v = get_user_meta( (int) $uid, SMACG_RANKING_META_KEY, true );
    // 未設定 → 預設出現
    if ( $v === '' || $v === false ) return true;
    return $v === '1';
}

/**
 * 新註冊預設「出現」
 */
add_action( 'user_register', function ( $uid ) {
    if ( get_user_meta( $uid, SMACG_RANKING_META_KEY, true ) === '' ) {
        update_user_meta( $uid, SMACG_RANKING_META_KEY, '1' );
    }
}, 20 );

/* ============================================================
   AJAX：切換顯示狀態
   ------------------------------------------------------------
   前端送：
     action=smacg_toggle_ranking_visibility
     nonce=smacgRanking.nonce
     visible=0 | 1
   ============================================================ */
add_action( 'wp_ajax_smacg_toggle_ranking_visibility', function () {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'code' => 'not_logged_in' ], 401 );
    }
    if ( ! check_ajax_referer( 'smacg_ranking_privacy', 'nonce', false ) ) {
        wp_send_json_error( [ 'code' => 'bad_nonce' ], 403 );
    }

    $uid     = get_current_user_id();
    $visible = isset( $_POST['visible'] ) && (string) $_POST['visible'] === '1' ? '1' : '0';

    update_user_meta( $uid, SMACG_RANKING_META_KEY, $visible );

    // 立即從快取表移除（若隱藏）或下次 Cron 再加入
    if ( $visible === '0' ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'smacg_rankings',
            [ 'user_id' => $uid ],
            [ '%d' ]
        );
    }

    if ( function_exists( 'smacg_ranking_flush_excluded_cache' ) ) {
        smacg_ranking_flush_excluded_cache();
    }

    wp_send_json_success( [
        'visible' => $visible === '1',
        'message' => $visible === '1' ? '已顯示於排行榜' : '已從排行榜隱藏',
    ] );
} );

/* ============================================================
   提供前端用的 nonce / 狀態（給 member-render & ranking page 使用）
   ============================================================ */
function smacg_ranking_privacy_localize_data() {
    return [
        'ajax'    => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'smacg_ranking_privacy' ),
        'visible' => is_user_logged_in() ? smacg_user_appears_in_ranking( get_current_user_id() ) : true,
    ];
}
