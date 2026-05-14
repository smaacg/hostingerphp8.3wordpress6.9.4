<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * GamiPress 統一抽象層（搬自 theme/inc/gamipress-integration.php v1.1.0）
 *
 * 全部 EXP / Badge 操作都先呼叫本類別的 static 方法；
 * 對外仍透過 compat/legacy-functions.php 提供 smacg_award_exp() 等舊函式名。
 */
class Gamipress_Bridge {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', [ __CLASS__, 'maybe_show_admin_notice' ] );

        // 設定變動時清掉 transient
        $clear = function () { delete_transient( 'smacg_gamipress_setup_errors' ); };
        add_action( 'save_post_points-type',      $clear );
        add_action( 'save_post_achievement-type', $clear );
        add_action( 'activated_plugin',           $clear );
        add_action( 'deactivated_plugin',         $clear );
        add_action( 'switch_theme',               $clear );
    }

    /* ==========================================================
     * GamiPress 可用性
     * ========================================================== */
    public static function active() {
        return function_exists( 'gamipress_get_user_points' );
    }

    /* ==========================================================
     * EXP
     * ========================================================== */
    public static function get_user_exp( $uid ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return 0;
        if ( ! self::active() ) {
            return (int) get_user_meta( $uid, 'smacg_exp_fallback', true );
        }
        return (int) gamipress_get_user_points( $uid, SMACG_EXP_SLUG );
    }

    public static function award_exp( $uid, $amount, $reason = '', $args = [] ) {
        $uid    = (int) $uid;
        $amount = (int) $amount;
        if ( $uid <= 0 || $amount <= 0 ) return false;

        if ( ! self::active() ) {
            $cur = (int) get_user_meta( $uid, 'smacg_exp_fallback', true );
            update_user_meta( $uid, 'smacg_exp_fallback', $cur + $amount );
            do_action( 'smacg_exp_awarded', $uid, $amount, $reason ?: 'fallback', $args );
            return true;
        }

        $admin_id = isset( $args['admin_id'] ) ? (int) $args['admin_id'] : 1;
        $reason   = $reason ?: 'smacg_award_exp';

        $result = gamipress_award_points_to_user( $uid, $amount, SMACG_EXP_SLUG, [
            'admin_id'    => $admin_id,
            'reason'      => $reason,
            'log_type'    => 'points_award',
            'achievement' => isset( $args['achievement_id'] ) ? $args['achievement_id'] : 0,
        ] );

        do_action( 'smacg_exp_awarded', $uid, $amount, $reason, $args );
        return $result !== false;
    }

    public static function deduct_exp( $uid, $amount, $reason = '' ) {
        $uid    = (int) $uid;
        $amount = (int) $amount;
        if ( $uid <= 0 || $amount <= 0 ) return false;

        if ( ! self::active() ) {
            $cur = (int) get_user_meta( $uid, 'smacg_exp_fallback', true );
            update_user_meta( $uid, 'smacg_exp_fallback', max( 0, $cur - $amount ) );
            return true;
        }

        $result = gamipress_deduct_points_to_user( $uid, $amount, SMACG_EXP_SLUG, [
            'admin_id' => 1,
            'reason'   => $reason ?: 'smacg_deduct_exp',
            'log_type' => 'points_deduct',
        ] );

        do_action( 'smacg_exp_deducted', $uid, $amount, $reason );
        return $result !== false;
    }

    /* ==========================================================
     * Points log
     * ========================================================== */
    public static function get_exp_log( $uid, $limit = 50 ) {
        global $wpdb;
        $uid   = (int) $uid;
        $limit = max( 1, min( 100, (int) $limit ) );

        if ( $uid <= 0 || ! self::active() ) return [];

        $logs_table = $wpdb->prefix . 'gamipress_logs';
        $meta_table = $wpdb->prefix . 'gamipress_logs_meta';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$logs_table'" ) !== $logs_table ) return [];

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

        if ( empty( $rows ) ) return [];

        $out = [];
        foreach ( $rows as $row ) {
            $is_deduct = in_array( $row['type'], [ 'points_deduct', 'points_expend' ], true );
            $value     = (int) $row['points'];
            $value     = $is_deduct ? -abs( $value ) : abs( $value );

            $out[] = [
                'created_at'   => $row['date'],
                'change_value' => $value,
                'reason'       => $row['title'] ?: '系統調整',
            ];
        }
        return $out;
    }

    /* ==========================================================
     * Badge
     * ========================================================== */
    public static function get_user_badge_ids( $uid ) {
        $uid = (int) $uid;
        if ( $uid <= 0 || ! self::active() ) return [];

        $earnings = gamipress_get_user_achievements( [
            'user_id'          => $uid,
            'achievement_type' => SMACG_BADGE_SLUG,
        ] );
        if ( empty( $earnings ) ) return [];
        return wp_list_pluck( $earnings, 'ID' );
    }

    public static function get_user_badge_count( $uid ) {
        return count( self::get_user_badge_ids( $uid ) );
    }

    public static function award_badge( $uid, $badge_post_id ) {
        $uid           = (int) $uid;
        $badge_post_id = (int) $badge_post_id;
        if ( $uid <= 0 || $badge_post_id <= 0 || ! self::active() ) return false;

        if ( function_exists( 'gamipress_award_achievement_to_user' ) ) {
            gamipress_award_achievement_to_user( $badge_post_id, $uid );
            do_action( 'smacg_badge_awarded', $uid, $badge_post_id );
            return true;
        }
        return false;
    }

    /* ==========================================================
     * 後台設定檢查
     * ========================================================== */
    public static function check_setup() {
        $errors = [];
        if ( ! self::active() ) {
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

    public static function maybe_show_admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! is_admin() ) return;

        $errors = get_transient( 'smacg_gamipress_setup_errors' );
        if ( $errors === false ) {
            $errors = self::check_setup();
            set_transient( 'smacg_gamipress_setup_errors', $errors, HOUR_IN_SECONDS );
        }
        if ( empty( $errors ) ) return;

        add_action( 'admin_notices', function () use ( $errors ) {
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'plugins', 'themes' ], true ) ) return;
            echo '<div class="notice notice-warning"><p><strong>SMACG Gamification 系統提示：</strong></p><ul style="margin-left:20px;list-style:disc;">';
            foreach ( $errors as $err ) echo '<li>' . esc_html( $err ) . '</li>';
            echo '</ul></div>';
        } );
    }
}
