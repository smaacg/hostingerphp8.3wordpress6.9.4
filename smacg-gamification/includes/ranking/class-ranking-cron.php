<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Ranking cron + admin bar 重算按鈕（搬自 theme/inc/ranking-cron.php）
 */
class Ranking_Cron {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'smacg_ranking_recalc',         [ __CLASS__, 'on_recalc' ] );
        add_action( 'smacg_ranking_monthly_purge',  [ __CLASS__, 'on_purge' ] );
        add_action( 'admin_bar_menu',               [ __CLASS__, 'admin_bar_button' ], 100 );
        add_action( 'admin_post_smacg_ranking_rebuild', [ __CLASS__, 'handle_manual_rebuild' ] );
        add_action( 'admin_notices',                [ __CLASS__, 'maybe_show_rebuild_notice' ] );
    }

    public static function on_recalc() {
        Ranking_System::rebuild_all();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[SMACG] Ranking auto-recalc complete at ' . current_time( 'mysql' ) );
        }
    }

    public static function on_purge() {
        $n = Ranking_System::purge_old_monthly( 13 );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[SMACG] Monthly EXP purge: deleted $n rows" );
        }
    }

    public static function admin_bar_button( $bar ) {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $url = wp_nonce_url( admin_url( 'admin-post.php?action=smacg_ranking_rebuild' ), 'smacg_ranking_rebuild' );
        $bar->add_node( [
            'id'    => 'smacg-ranking-rebuild',
            'title' => '🏆 重算排行榜',
            'href'  => $url,
            'meta'  => [ 'title' => '立即重新計算所有排行榜' ],
        ] );
    }

    public static function handle_manual_rebuild() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '無權限', 403 );
        check_admin_referer( 'smacg_ranking_rebuild' );

        $count = Ranking_System::rebuild_all();
        set_transient( 'smacg_ranking_rebuild_msg', sprintf( '✅ 已重新計算 %d 筆排行榜資料', $count ), 60 );

        wp_safe_redirect( wp_get_referer() ?: admin_url() );
        exit;
    }

    public static function maybe_show_rebuild_notice() {
        $msg = get_transient( 'smacg_ranking_rebuild_msg' );
        if ( ! $msg ) return;
        delete_transient( 'smacg_ranking_rebuild_msg' );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
    }
}
