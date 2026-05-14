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
 * Version: 1.1.0 (2026-05-14) Hotfix:
 *   - [修正] admin_notices 中的 get_page_by_path() 觸發過早翻譯載入
 *     → 改為 admin_init 階段執行 + transient 緩存結果
 *     → 解決 WP 6.7+ _load_textdomain_just_in_time notice
 *
 * Version: 1.0.0 (2026-05-13) Batch 2A-0
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

function smacg_gamipress_active() {
    return function_exists( 'gamipress_get_user_points' );
}

/* =========================================================
 * 2. EXP（經驗值）相關 Helper
 * ========================================================= */

function smacg_get_user_exp( $uid ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) return 0;

    if ( ! smacg_gamipress_active() ) {
        return (int) get_user_meta( $uid, 'smacg_exp_fallback', true );
    }

    return (int) gamipress_get_user_points( $uid, SMACG_EXP_SLUG );
}

function smacg_award_exp( $uid, $amount, $reason = '', $args = array() ) {
    $uid    = (int) $uid;
    $amount = (int) $amount;

    if ( $uid <= 0 || $amount <= 0 ) return false;

    if ( ! smacg_gamipress_active() ) {
        $current = (int) get_user_meta( $uid, 'smacg_exp_fallback', true );
        update_user_meta( $uid, 'smacg_exp_fallback', $current + $amount );
        return true;
    }

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

    do_action( 'smacg_exp_awarded', $uid, $amount, $reason, $args );

    return $result !== false;
}

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
 * 3. 點數紀錄
 * ========================================================= */

function smacg_get_exp_log( $uid, $limit = 50 ) {
    global $wpdb;
    $uid   = (int) $uid;
    $limit = max( 1, min( 100, (int) $limit ) );

    if ( $uid <= 0 ) return array();
    if ( ! smacg_gamipress_active() ) return array();

    $logs_table = $wpdb->prefix . 'gamipress_logs';
    $meta_table = $wpdb->prefix . 'gamipress_logs_meta';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$logs_table'" ) !== $logs_table ) {
        return array();
    }

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

function smacg_get_user_badge_count( $uid ) {
    return count( smacg_get_user_badge_ids( $uid ) );
}

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
 * 5. 安裝/升級檢查
 *
 * 注意：本函式內部使用 get_page_by_path() 查詢 GamiPress CPT，
 * 必須在 init 之後執行，否則會觸發 WP 6.7+ _load_textdomain_just_in_time notice。
 * ========================================================= */

function smacg_gamipress_check_setup() {
    $errors = array();

    if ( ! smacg_gamipress_active() ) {
        $errors[] = 'GamiPress 外掛尚未啟用';
        return $errors;
    }

    $exp_type = get_page_by_path( SMACG_EXP_SLUG, OBJECT, 'points-type' );
    if ( ! $exp_type ) {
        $errors[] = sprintf( '請至 GamiPress 後台建立 Points Type，slug = "%s"', SMACG_EXP_SLUG );
    }

    $badge_type = get_page_by_path( SMACG_BADGE_SLUG, OBJECT, 'achievement-type' );
    if ( ! $badge_type ) {
        $errors[] = sprintf( '請至 GamiPress 後台建立 Achievement Type，slug = "%s"', SMACG_BADGE_SLUG );
    }

    return $errors;
}

/* =========================================================
 * 6. 後台警告：延後到 admin_init 並用 transient 緩存
 *    解決 WP 6.7+ _load_textdomain_just_in_time notice
 * ========================================================= */

add_action( 'admin_init', 'smacg_gamipress_setup_admin_notice' );
function smacg_gamipress_setup_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! is_admin() ) return;

    // 用 transient 緩存檢查結果，避免每個 admin 頁面都重跑一次 DB
    $errors = get_transient( 'smacg_gamipress_setup_errors' );
    if ( $errors === false ) {
        $errors = smacg_gamipress_check_setup();
        set_transient( 'smacg_gamipress_setup_errors', $errors, HOUR_IN_SECONDS );
    }

    if ( empty( $errors ) ) return;

    add_action( 'admin_notices', function () use ( $errors ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'themes' ), true ) ) return;

        echo '<div class="notice notice-warning"><p><strong>微笑動漫 Gamification 系統提示：</strong></p><ul style="margin-left:20px;list-style:disc;">';
        foreach ( $errors as $err ) {
            echo '<li>' . esc_html( $err ) . '</li>';
        }
        echo '</ul></div>';
    } );
}

/**
 * GamiPress 設定變動時清掉 transient（下次 admin_init 重新檢測）
 */
add_action( 'save_post_points-type',      function () { delete_transient( 'smacg_gamipress_setup_errors' ); } );
add_action( 'save_post_achievement-type', function () { delete_transient( 'smacg_gamipress_setup_errors' ); } );
add_action( 'activated_plugin',           function () { delete_transient( 'smacg_gamipress_setup_errors' ); } );
add_action( 'deactivated_plugin',         function () { delete_transient( 'smacg_gamipress_setup_errors' ); } );
add_action( 'switch_theme',               function () { delete_transient( 'smacg_gamipress_setup_errors' ); } );
