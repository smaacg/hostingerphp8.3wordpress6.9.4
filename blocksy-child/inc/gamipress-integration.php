<?php
/**
 * GamiPress Integration Layer
 * 
 * 統一整合層 - 將所有 GamiPress API 包裝成 smacg_* helper
 * 用途：解耦業務邏輯與 GamiPress 內部 API
 * 
 * 設計理念：
 * - 所有業務程式碼只呼叫 smacg_* helper
 * - 未來若 GamiPress 改 API 或更換系統，只需改本檔
 * - GamiPress 未啟用時也能 graceful degradation
 *
 * 依賴：GamiPress 核心外掛
 * Points Type Slug: exp
 * Achievement Type Slug: badge
 *
 * Version: 1.0.0 (2026-05-13)
 * Batch: 2A-0
 *
 * @package Blocksy_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 常數定義
 * ========================================================= */
if ( ! defined( 'SMACG_EXP_SLUG' ) ) {
    define( 'SMACG_EXP_SLUG', 'exp' );
}
if ( ! defined( 'SMACG_BADGE_SLUG' ) ) {
    define( 'SMACG_BADGE_SLUG', 'badge' );
}

/* =========================================================
 * 1. GamiPress 可用性檢查
 * ========================================================= */

/**
 * 檢查 GamiPress 是否已啟用且可用
 *
 * @return bool
 */
function smacg_gamipress_active() {
    return function_exists( 'gamipress_get_user_points' );
}

/* =========================================================
 * 2. EXP（經驗值）相關 Helper
 * ========================================================= */

/**
 * 取得用戶當前 EXP 總值
 *
 * @param int $uid 用戶 ID
 * @return int EXP 數值
 */
function smacg_get_user_exp( $uid ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) return 0;

    if ( ! smacg_gamipress_active() ) {
        // Fallback：GamiPress 未啟用時使用 user_meta
        return (int) get_user_meta( $uid, 'smacg_exp_fallback', true );
    }

    return (int) gamipress_get_user_points( $uid, SMACG_EXP_SLUG );
}

/**
 * 給予用戶 EXP（推薦做法）
 *
 * @param int    $uid       用戶 ID
 * @param int    $amount    EXP 數量（正數）
 * @param string $reason    原因（紀錄用）
 * @param array  $args      額外參數
 * @return bool 是否成功
 */
function smacg_award_exp( $uid, $amount, $reason = '', $args = array() ) {
    $uid    = (int) $uid;
    $amount = (int) $amount;

    if ( $uid <= 0 || $amount <= 0 ) return false;

    if ( ! smacg_gamipress_active() ) {
        // Fallback：用 user_meta 累加
        $current = (int) get_user_meta( $uid, 'smacg_exp_fallback', true );
        update_user_meta( $uid, 'smacg_exp_fallback', $current + $amount );
        return true;
    }

    // 使用 GamiPress 官方 API
    $admin_id = isset( $args['admin_id'] ) ? (int) $args['admin_id'] : 1;
    $reason   = $reason ?: 'smacg_award_exp';

    $result = gamipress_award_points_to_user(
        $uid,
        $amount,
        SMACG_EXP_SLUG,
        array(
            'admin_id'    => $admin_id,
            'reason'      => $reason,
            'log_type'    => 'points_award',
            'achievement' => isset( $args['achievement_id'] ) ? $args['achievement_id'] : 0,
        )
    );

    // 觸發自訂事件（給其他模組監聽，如：通知系統、月度積分系統）
    do_action( 'smacg_exp_awarded', $uid, $amount, $reason, $args );

    return $result !== false;
}

/**
 * 扣除用戶 EXP（少用，目前僅 admin 手動）
 *
 * @param int    $uid    用戶 ID
 * @param int    $amount 扣除數量（正數）
 * @param string $reason 原因
 * @return bool
 */
function smacg_deduct_exp( $uid, $amount, $reason = '' ) {
    $uid    = (int) $uid;
    $amount = (int) $amount;

    if ( $uid <= 0 || $amount <= 0 ) return false;

    if ( ! smacg_gamipress_active() ) {
        $current = (int) get_user_meta( $uid, 'smacg_exp_fallback', true );
        update_user_meta( $uid, 'smacg_exp_fallback', max( 0, $current - $amount ) );
        return true;
    }

    $result = gamipress_deduct_points_to_user(
        $uid,
        $amount,
        SMACG_EXP_SLUG,
        array(
            'admin_id' => 1,
            'reason'   => $reason ?: 'smacg_deduct_exp',
            'log_type' => 'points_deduct',
        )
    );

    do_action( 'smacg_exp_deducted', $uid, $amount, $reason );

    return $result !== false;
}

/* =========================================================
 * 3. 點數紀錄（取代舊 smacg_render_points 用）
 * ========================================================= */

/**
 * 取得用戶 EXP 變動紀錄
 *
 * @param int $uid   用戶 ID
 * @param int $limit 筆數上限
 * @return array
 */
function smacg_get_exp_log( $uid, $limit = 50 ) {
    global $wpdb;
    $uid   = (int) $uid;
    $limit = max( 1, min( 100, (int) $limit ) );

    if ( $uid <= 0 ) return array();

    if ( ! smacg_gamipress_active() ) {
        return array();
    }

    $logs_table = $wpdb->prefix . 'gamipress_logs';
    $meta_table = $wpdb->prefix . 'gamipress_logs_meta';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$logs_table'" ) !== $logs_table ) {
        return array();
    }

    // 取得該用戶所有 points 相關 log
    $rows = $wpdb->get_results( $wpdb->prepare( "
        SELECT l.log_id, l.title, l.date, m1.meta_value AS points, m2.meta_value AS points_type, l.type
        FROM $logs_table l
        LEFT JOIN $meta_table m1 ON l.log_id = m1.log_id AND m1.meta_key = '_gamipress_points'
        LEFT JOIN $meta_table m2 ON l.log_id = m2.log_id AND m2.meta_key = '_gamipress_points_type'
        WHERE l.user_id = %d
        AND l.type IN ('points_award','points_deduct','points_earn','points_expend')
        AND m2.meta_value = %s
        ORDER BY l.date DESC
        LIMIT %d
    ", $uid, SMACG_EXP_SLUG, $limit ), ARRAY_A );

    if ( empty( $rows ) ) return array();

    // 格式化成統一格式
    $out = array();
    foreach ( $rows as $row ) {
        $is_deduct = in_array( $row['type'], array( 'points_deduct', 'points_expend' ), true );
        $value     = (int) $row['points'];
        $value     = $is_deduct ? -abs( $value ) : abs( $value );

        $out[] = array(
            'created_at'   => $row['date'],
            'change_value' => $value,
            'reason'       => $row['title'] ?: '系統調整',
        );
    }

    return $out;
}

/* =========================================================
 * 4. 徽章（Achievement）相關 Helper
 * ========================================================= */

/**
 * 取得用戶已解鎖的徽章 ID 列表
 *
 * @param int $uid 用戶 ID
 * @return array post_id 陣列
 */
function smacg_get_user_badge_ids( $uid ) {
    $uid = (int) $uid;
    if ( $uid <= 0 || ! smacg_gamipress_active() ) return array();

    $earnings = gamipress_get_user_achievements( array(
        'user_id'          => $uid,
        'achievement_type' => SMACG_BADGE_SLUG,
    ) );

    if ( empty( $earnings ) ) return array();

    return wp_list_pluck( $earnings, 'ID' );
}

/**
 * 取得用戶已解鎖徽章數量
 *
 * @param int $uid
 * @return int
 */
function smacg_get_user_badge_count( $uid ) {
    return count( smacg_get_user_badge_ids( $uid ) );
}

/**
 * 手動發放徽章給用戶
 *
 * @param int $uid          用戶 ID
 * @param int $badge_post_id 徽章 post ID
 * @return bool
 */
function smacg_award_badge( $uid, $badge_post_id ) {
    $uid           = (int) $uid;
    $badge_post_id = (int) $badge_post_id;

    if ( $uid <= 0 || $badge_post_id <= 0 ) return false;
    if ( ! smacg_gamipress_active() ) return false;

    if ( function_exists( 'gamipress_award_achievement_to_user' ) ) {
        gamipress_award_achievement_to_user( $badge_post_id, $uid );
        do_action( 'smacg_badge_awarded', $uid, $badge_post_id );
        return true;
    }

    return false;
}

/* =========================================================
 * 5. 安裝/升級檢查（用於通知管理員缺少設定）
 * ========================================================= */

/**
 * 檢查 GamiPress 必要設定是否完整
 *
 * @return array 錯誤訊息陣列（空陣列表示一切正常）
 */
function smacg_gamipress_check_setup() {
    $errors = array();

    if ( ! smacg_gamipress_active() ) {
        $errors[] = 'GamiPress 外掛尚未啟用';
        return $errors;
    }

    // 檢查 Points Type 「exp」是否存在
    $exp_type = get_page_by_path( SMACG_EXP_SLUG, OBJECT, 'points-type' );
    if ( ! $exp_type ) {
        $errors[] = sprintf( '請至 GamiPress 後台建立 Points Type，slug = "%s"', SMACG_EXP_SLUG );
    }

    // 檢查 Achievement Type 「badge」是否存在
    $badge_type = get_page_by_path( SMACG_BADGE_SLUG, OBJECT, 'achievement-type' );
    if ( ! $badge_type ) {
        $errors[] = sprintf( '請至 GamiPress 後台建立 Achievement Type，slug = "%s"', SMACG_BADGE_SLUG );
    }

    return $errors;
}

/**
 * 後台顯示設定警告
 */
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $errors = smacg_gamipress_check_setup();
    if ( empty( $errors ) ) return;

    // 只在外掛頁、儀表板顯示
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'themes' ), true ) ) return;

    echo '<div class="notice notice-warning"><p><strong>微笑動漫 Gamification 系統提示：</strong></p><ul style="margin-left:20px;list-style:disc;">';
    foreach ( $errors as $err ) {
        echo '<li>' . esc_html( $err ) . '</li>';
    }
    echo '</ul></div>';
} );
