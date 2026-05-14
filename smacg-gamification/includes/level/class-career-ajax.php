<?php
/**
 * Career Selection AJAX - 4 職業選擇端點
 *
 * 原檔：blocksy-child/inc/career-ajax.php v1.0.0
 * 注意：本職業表（4 種）與 System::get_jobs()（8 種）並存，
 *       對應不同的 user_meta：
 *         - 4 職業：user_meta 'smacg_career_job'
 *         - 8 職業：user_meta 'smacg_job_key'
 *       兩套都保留，未來規劃整併時再處理。
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Level;

defined( 'ABSPATH' ) || exit;

class Career {

    public static function init() {
        add_action( 'wp_ajax_smacg_select_career',        [ __CLASS__, 'ajax_select' ] );
        add_action( 'wp_ajax_nopriv_smacg_select_career', [ __CLASS__, 'ajax_nopriv' ] );
    }

    /* ---------- 職業定義（4 種） ---------- */

    public static function get_all_jobs() {
        return [
            'streamer' => [
                'key' => 'streamer', 'label' => '追番達人', 'icon' => '📺',
                'desc' => '完整追完最多季番，月度更新王者', 'color' => '#60a5fa',
            ],
            'critic' => [
                'key' => 'critic',   'label' => '評論家',   'icon' => '🎬',
                'desc' => '評分與評論獨到，影響社群口味', 'color' => '#f59e0b',
            ],
            'archivist' => [
                'key' => 'archivist','label' => '收藏家',   'icon' => '📚',
                'desc' => '清單管理完整，跨年度作品收藏家', 'color' => '#a78bfa',
            ],
            'social' => [
                'key' => 'social',   'label' => '社交家',   'icon' => '💬',
                'desc' => '人氣追蹤者、留言互動王',         'color' => '#34d399',
            ],
        ];
    }

    public static function get_job_label( $job_key ) {
        $all = self::get_all_jobs();
        return $all[ $job_key ] ?? null;
    }

    public static function get_user_job( $user_id ) {
        $user_id = (int) $user_id;
        if ( ! $user_id ) return '';
        $job = get_user_meta( $user_id, 'smacg_career_job', true );
        return is_string( $job ) ? $job : '';
    }

    /* ---------- AJAX ---------- */

    public static function ajax_nopriv() {
        wp_send_json_error( [ 'message' => '請先登入', 'code' => 'not_logged_in' ], 401 );
    }

    public static function ajax_select() {
        if ( ! check_ajax_referer( 'smacg_career_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => '安全驗證失敗，請重新整理頁面', 'code' => 'bad_nonce' ], 403 );
        }

        $uid = get_current_user_id();
        if ( ! $uid ) {
            wp_send_json_error( [ 'message' => '請先登入', 'code' => 'not_logged_in' ], 401 );
        }

        $job_key = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
        $jobs    = self::get_all_jobs();
        if ( ! isset( $jobs[ $job_key ] ) ) {
            wp_send_json_error( [ 'message' => '無效的職業選擇', 'code' => 'invalid_job' ], 400 );
        }

        // 永久鎖定
        $existing = self::get_user_job( $uid );
        if ( ! empty( $existing ) ) {
            $existing_label = self::get_job_label( $existing );
            wp_send_json_error( [
                'message'  => sprintf( '你已選擇「%s」，職業一經選定無法變更', $existing_label['label'] ?? $existing ),
                'code'     => 'already_locked',
                'existing' => $existing,
            ], 409 );
        }

        // 等級檢查
        if ( ! function_exists( 'smacg_get_user_level_info' ) ) {
            wp_send_json_error( [ 'message' => '等級系統未載入', 'code' => 'level_system_missing' ], 500 );
        }
        $lvl_info = \smacg_get_user_level_info( $uid );
        $level    = (int) ( $lvl_info['level'] ?? 0 );
        if ( $level < 10 ) {
            wp_send_json_error( [
                'message'  => sprintf( '需要 Lv.10 才能選擇職業（目前 Lv.%d）', $level ),
                'code'     => 'level_too_low',
                'level'    => $level,
                'required' => 10,
            ], 403 );
        }

        update_user_meta( $uid, 'smacg_career_job', $job_key );
        update_user_meta( $uid, 'smacg_career_job_locked_at', current_time( 'mysql' ) );

        do_action( 'smacg_career_job_selected', $uid, $job_key );

        $job_data = self::get_job_label( $job_key );
        wp_send_json_success( [
            'message' => sprintf( '已成為「%s %s」！', $job_data['icon'], $job_data['label'] ),
            'job'     => $job_key,
            'label'   => $job_data['label'],
            'icon'    => $job_data['icon'],
            'color'   => $job_data['color'],
        ] );
    }
}

Career::init();
