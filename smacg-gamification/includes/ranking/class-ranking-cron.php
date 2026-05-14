<?php
/**
 * Ranking Cron - 排程重算 + 手動觸發
 *
 * 原檔：blocksy-child/inc/ranking-cron.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Ranking;

defined( 'ABSPATH' ) || exit;

class Cron {

    public static function init() {
        // 保險：init 時補排程
        add_action( 'init', [ __CLASS__, 'maybe_schedule' ], 20 );

        // Cron handlers
        add_action( 'smacg_ranking_recalc',        [ __CLASS__, 'on_recalc' ] );
        add_action( 'smacg_ranking_monthly_purge', [ __CLASS__, 'on_purge' ] );

        // 主題切換時清排程
        add_action( 'switch_theme', [ __CLASS__, 'unschedule' ] );

        // 手動觸發 endpoint
        add_action( 'admin_post_smacg_ranking_rebuild', [ __CLASS__, 'handle_manual_rebuild' ] );

        // Admin bar 快捷鈕
        add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_node' ], 100 );

        // 顯示重算結果
        add_action( 'admin_notices', [ __CLASS__, 'show_rebuild_notice' ] );
    }

    public static function maybe_schedule() {
        if ( ! wp_next_scheduled( 'smacg_ranking_recalc' ) ) {
            wp_schedule_event( time() + 60, 'hourly', 'smacg_ranking_recalc' );
        }
        if ( ! wp_next_scheduled( 'smacg_ranking_monthly_purge' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 04:00' ), 'daily', 'smacg_ranking_monthly_purge' );
        }
    }

    public static function unschedule() {
        foreach ( [ 'smacg_ranking_recalc', 'smacg_ranking_monthly_purge' ] as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) wp_unschedule_event( $ts, $hook );
        }
    }

    public static function on_recalc() {
        $log = System::rebuild_all();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[smacg ranking] rebuild ' . wp_json_encode( $log ) );
        }
    }

    public static function on_purge() {
        System::purge_old_monthly();
    }

    public static function handle_manual_rebuild() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '權限不足', 403 );
        }
        check_admin_referer( 'smacg_ranking_rebuild' );

        $log = System::rebuild_all();
        $msg = '排行榜已重算：' . wp_json_encode( $log, JSON_UNESCAPED_UNICODE );

        wp_safe_redirect( add_query_arg( [
            'smacg_msg' => rawurlencode( $msg ),
        ], admin_url( 'index.php' ) ) );
        exit;
    }

    public static function add_admin_bar_node( $bar ) {
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
    }

    public static function show_rebuild_notice() {
        if ( empty( $_GET['smacg_msg'] ) ) return;
        echo '<div class="notice notice-success is-dismissible"><p>'
           . esc_html( rawurldecode( wp_unslash( $_GET['smacg_msg'] ) ) )
           . '</p></div>';
    }
}

Cron::init();
