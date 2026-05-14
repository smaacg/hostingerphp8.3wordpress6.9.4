<?php
/**
 * Ranking Privacy
 *
 * 原檔：blocksy-child/inc/ranking-privacy.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Ranking;

defined( 'ABSPATH' ) || exit;

class Privacy {

    public static function init() {
        add_action( 'user_register', [ __CLASS__, 'set_default' ], 20 );
        add_action( 'wp_ajax_smacg_toggle_ranking_visibility', [ __CLASS__, 'ajax_toggle' ] );
    }

    public static function user_appears( $uid ) {
        $v = get_user_meta( (int) $uid, SMACG_RANKING_META_KEY, true );
        if ( $v === '' || $v === false ) return true;
        return $v === '1';
    }

    public static function set_default( $uid ) {
        if ( get_user_meta( $uid, SMACG_RANKING_META_KEY, true ) === '' ) {
            update_user_meta( $uid, SMACG_RANKING_META_KEY, '1' );
        }
    }

    public static function ajax_toggle() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'code' => 'not_logged_in' ], 401 );
        }
        if ( ! check_ajax_referer( 'smacg_ranking_privacy', 'nonce', false ) ) {
            wp_send_json_error( [ 'code' => 'bad_nonce' ], 403 );
        }

        $uid     = get_current_user_id();
        $visible = isset( $_POST['visible'] ) && (string) $_POST['visible'] === '1' ? '1' : '0';

        update_user_meta( $uid, SMACG_RANKING_META_KEY, $visible );

        // 隱藏時立即從快取表移除
        if ( $visible === '0' ) {
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'smacg_rankings',
                [ 'user_id' => $uid ],
                [ '%d' ]
            );
        }

        System::flush_excluded_cache();

        wp_send_json_success( [
            'visible' => $visible === '1',
            'message' => $visible === '1' ? '已顯示於排行榜' : '已從排行榜隱藏',
        ] );
    }

    public static function localize_data() {
        return [
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'smacg_ranking_privacy' ),
            'visible' => is_user_logged_in() ? self::user_appears( get_current_user_id() ) : true,
        ];
    }
}

Privacy::init();
