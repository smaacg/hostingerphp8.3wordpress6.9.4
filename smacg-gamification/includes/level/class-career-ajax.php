<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * 職業選擇 AJAX（搬自 theme/inc/career-ajax.php）
 *
 * 規則：
 *   - Lv ≥ 10 才能選
 *   - 4 個職業（aniholic / collector / critic / commentator）
 *   - 只能選一次（鎖定）
 *
 * Endpoint: wp_ajax_smacg_select_career
 * Nonce:    smacg_career_nonce（由 class-plugin.php 的 localize_career_nonce 注入）
 */
class Career_Ajax {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_smacg_select_career', [ __CLASS__, 'handle' ] );
    }

    /* ==========================================================
     * 4 職業表（與 Level_System 的 8 職業表獨立）
     * ========================================================== */
    public static function careers() {
        return [
            'aniholic'    => [
                'title' => '動畫狂熱者',
                'desc'  => '看番狂魔，擅長刷觀看記錄',
                'icon'  => 'fa-tv',
            ],
            'collector'   => [
                'title' => '收藏家',
                'desc'  => '追蹤、收藏一把抓',
                'icon'  => 'fa-bookmark',
            ],
            'critic'      => [
                'title' => '評論家',
                'desc'  => '銳利筆鋒，言之有物',
                'icon'  => 'fa-pen-fancy',
            ],
            'commentator' => [
                'title' => '留言王',
                'desc'  => '社群互動的核心',
                'icon'  => 'fa-comments',
            ],
        ];
    }

    /* ==========================================================
     * AJAX handler
     * ========================================================== */
    public static function handle() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => '未登入' ], 401 );
        }
        if ( ! check_ajax_referer( 'smacg_career_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce 驗證失敗' ], 403 );
        }

        $uid = get_current_user_id();

        /* 等級檢查 */
        $info = Level_System::get_user_level( $uid );
        if ( $info['level'] < 10 ) {
            wp_send_json_error( [
                'message' => '需達到 Lv.10 才能選擇職業',
                'level'   => $info['level'],
            ], 400 );
        }

        /* 是否已選 */
        $existing = get_user_meta( $uid, 'smacg_career_job', true );
        if ( $existing ) {
            wp_send_json_error( [
                'message' => '已選過職業，無法重選',
                'current' => $existing,
            ], 400 );
        }

        /* 職業驗證 */
        $career_key = sanitize_text_field( $_POST['career'] ?? '' );
        $careers    = self::careers();
        if ( ! isset( $careers[ $career_key ] ) ) {
            wp_send_json_error( [ 'message' => '無效的職業代碼' ], 400 );
        }

        /* 寫入 */
        update_user_meta( $uid, 'smacg_career_job',       $career_key );
        update_user_meta( $uid, 'smacg_career_chosen_at', current_time( 'mysql' ) );

        do_action( 'smacg_career_chosen', $uid, $career_key );

        wp_send_json_success( [
            'message' => sprintf( '已選擇職業：%s', $careers[ $career_key ]['title'] ),
            'career'  => $career_key,
            'title'   => $careers[ $career_key ]['title'],
        ] );
    }
}
