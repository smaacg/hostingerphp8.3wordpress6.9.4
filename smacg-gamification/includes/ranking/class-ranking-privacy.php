<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * 排行榜隱私設定（搬自 theme/inc/ranking-privacy.php）
 *
 * user_meta SMACG_RANKING_META_KEY:
 *   '1'（或不存在）→ 顯示
 *   '0'           → 隱藏
 */
class Ranking_Privacy {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_smacg_toggle_ranking_visibility', [ __CLASS__, 'handle_toggle' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'localize' ], 25 );
    }

    public static function is_visible( $uid ) {
        return get_user_meta( (int) $uid, SMACG_RANKING_META_KEY, true ) !== '0';
    }

    public static function handle_toggle() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => '未登入' ], 401 );
        }
        if ( ! check_ajax_referer( 'smacg_ranking_privacy', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce 驗證失敗' ], 403 );
        }

        $uid     = get_current_user_id();
        $current = self::is_visible( $uid );
        $new     = $current ? '0' : '1';
        update_user_meta( $uid, SMACG_RANKING_META_KEY, $new );

        /* 隱私變更後立即從排行榜中移除（或在下一次 cron 加回） */
        if ( $new === '0' ) {
            global $wpdb;
            $wpdb->delete( $wpdb->prefix . 'smacg_rankings', [ 'user_id' => $uid ], [ '%d' ] );
        }

        wp_send_json_success( [
            'visible' => $new === '1',
            'message' => $new === '1' ? '已加入排行榜' : '已從排行榜隱藏',
        ] );
    }

    public static function localize() {
        if ( ! is_user_logged_in() ) return;
        $data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'smacg_ranking_privacy' ),
            'visible'  => self::is_visible( get_current_user_id() ),
        ];
        if ( wp_script_is( 'smacg-member', 'enqueued' ) ) {
            wp_localize_script( 'smacg-member', 'SmacgRankingPrivacy', $data );
        }
    }
}
