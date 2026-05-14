<?php
/**
 * Career Selection AJAX - 職業選擇端點
 *
 * @package weixiaoacg
 * @subpackage Gamification
 * @version 1.0.0 (2026-05-14)
 *
 * Batch 2A-4：
 *   - 端點 wp_ajax_smacg_select_career
 *   - 規則：Lv ≥ 10 才能選；一旦選定永久鎖定（依使用者建議 A 方案）
 *   - 寫入 user meta：smacg_career_job（string，如 'streamer' / 'critic' / 'archivist' / 'social'）
 *   - 同步寫入 smacg_career_job_locked_at（時間戳，作為審計記錄）
 *
 * 提供 helper：
 *   smacg_get_user_career_job( $user_id ) → string|''
 *   smacg_get_career_job_label( $job_key ) → ['key','label','icon','desc']
 *   smacg_get_all_career_jobs() → array
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   1. 職業定義（4 種）
   ============================================================ */
function smacg_get_all_career_jobs() {
    return [
        'streamer' => [
            'key'   => 'streamer',
            'label' => '追番達人',
            'icon'  => '📺',
            'desc'  => '完整追完最多季番，月度更新王者',
            'color' => '#60a5fa',
        ],
        'critic' => [
            'key'   => 'critic',
            'label' => '評論家',
            'icon'  => '🎬',
            'desc'  => '評分與評論獨到，影響社群口味',
            'color' => '#f59e0b',
        ],
        'archivist' => [
            'key'   => 'archivist',
            'label' => '收藏家',
            'icon'  => '📚',
            'desc'  => '清單管理完整，跨年度作品收藏家',
            'color' => '#a78bfa',
        ],
        'social' => [
            'key'   => 'social',
            'label' => '社交家',
            'icon'  => '💬',
            'desc'  => '人氣追蹤者、留言互動王',
            'color' => '#34d399',
        ],
    ];
}

/**
 * 取得單一職業設定
 */
function smacg_get_career_job_label( $job_key ) {
    $all = smacg_get_all_career_jobs();
    return $all[ $job_key ] ?? null;
}

/**
 * 取得使用者已選職業
 *
 * @return string '' 表示尚未選擇
 */
function smacg_get_user_career_job( $user_id ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) return '';
    $job = get_user_meta( $user_id, 'smacg_career_job', true );
    return is_string( $job ) ? $job : '';
}

/* ============================================================
   2. AJAX 端點
   ============================================================ */
add_action( 'wp_ajax_smacg_select_career', 'smacg_ajax_select_career' );
add_action( 'wp_ajax_nopriv_smacg_select_career', function () {
    wp_send_json_error( [ 'message' => '請先登入', 'code' => 'not_logged_in' ], 401 );
} );

function smacg_ajax_select_career() {
    // 1) 驗證 nonce
    if ( ! check_ajax_referer( 'smacg_career_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => '安全驗證失敗，請重新整理頁面', 'code' => 'bad_nonce' ], 403 );
    }

    // 2) 取得 user
    $uid = get_current_user_id();
    if ( ! $uid ) {
        wp_send_json_error( [ 'message' => '請先登入', 'code' => 'not_logged_in' ], 401 );
    }

    // 3) 驗證 job_key
    $job_key = isset( $_POST['job'] ) ? sanitize_key( $_POST['job'] ) : '';
    $jobs    = smacg_get_all_career_jobs();
    if ( ! isset( $jobs[ $job_key ] ) ) {
        wp_send_json_error( [ 'message' => '無效的職業選擇', 'code' => 'invalid_job' ], 400 );
    }

    // 4) 檢查是否已選過（A 方案：永久鎖定）
    $existing = smacg_get_user_career_job( $uid );
    if ( ! empty( $existing ) ) {
        $existing_label = smacg_get_career_job_label( $existing );
        wp_send_json_error( [
            'message'  => sprintf( '你已選擇「%s」，職業一經選定無法變更', $existing_label['label'] ?? $existing ),
            'code'     => 'already_locked',
            'existing' => $existing,
        ], 409 );
    }

    // 5) 檢查等級（必須 Lv ≥ 10）
    if ( ! function_exists( 'smacg_get_user_level_info' ) ) {
        wp_send_json_error( [ 'message' => '等級系統未載入', 'code' => 'level_system_missing' ], 500 );
    }
    $lvl_info = smacg_get_user_level_info( $uid );
    $level    = (int) ( $lvl_info['level'] ?? 0 );
    if ( $level < 10 ) {
        wp_send_json_error( [
            'message' => sprintf( '需要 Lv.10 才能選擇職業（目前 Lv.%d）', $level ),
            'code'    => 'level_too_low',
            'level'   => $level,
            'required' => 10,
        ], 403 );
    }

    // 6) 寫入
    update_user_meta( $uid, 'smacg_career_job', $job_key );
    update_user_meta( $uid, 'smacg_career_job_locked_at', current_time( 'mysql' ) );

    // 7) 觸發 hook（供其他模組監聽，例如未來授予職業 EXP）
    do_action( 'smacg_career_job_selected', $uid, $job_key );

    // 8) 回傳
    $job_data = smacg_get_career_job_label( $job_key );
    wp_send_json_success( [
        'message' => sprintf( '已成為「%s %s」！', $job_data['icon'], $job_data['label'] ),
        'job'     => $job_key,
        'label'   => $job_data['label'],
        'icon'    => $job_data['icon'],
        'color'   => $job_data['color'],
    ] );
}

/* ============================================================
   3. 提供 nonce 給前端（直接在 setup-enqueue.php localize）
   ============================================================ */
