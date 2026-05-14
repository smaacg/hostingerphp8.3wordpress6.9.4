<?php
/**
 * Leaderboard AJAX — 排行榜資料 endpoint
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-2
 *
 * Actions（皆無需登入）：
 *   wp_ajax_smacg_get_ranking
 *   wp_ajax_nopriv_smacg_get_ranking
 */

defined( 'ABSPATH' ) || exit;

/**
 * 取得排行榜資料（含 user 顯示資訊）
 *
 * 參數：
 *   type     exp_total | exp_monthly | followers | badges
 *   page     1-based
 *   per_page 預設 20，最大 50
 */
function smacg_ajax_get_ranking() {
    $type     = isset( $_REQUEST['type'] ) ? sanitize_key( $_REQUEST['type'] ) : 'exp_total';
    $page     = isset( $_REQUEST['page'] ) ? max( 1, (int) $_REQUEST['page'] ) : 1;
    $per_page = isset( $_REQUEST['per_page'] ) ? min( 50, max( 1, (int) $_REQUEST['per_page'] ) ) : 20;

    if ( ! function_exists( 'smacg_ranking_get' ) ) {
        wp_send_json_error( [ 'code' => 'system_unavailable', 'message' => '排行榜系統未啟用' ], 503 );
    }

    if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) {
        wp_send_json_error( [ 'code' => 'bad_type' ], 400 );
    }

    $data = smacg_ranking_get( $type, $page, $per_page );

    // 補上使用者顯示資訊
    $rows = [];
    foreach ( $data['rows'] as $r ) {
        $uid  = (int) $r['user_id'];
        $user = get_user_by( 'id', $uid );
        if ( ! $user ) continue;

        $level_info = function_exists( 'smacg_get_user_level_info' )
            ? smacg_get_user_level_info( $uid )
            : [ 'level' => 0, 'tier' => '', 'title' => '', 'icon' => '' ];

        $profile_url = function_exists( 'smacg_get_public_profile_url' )
            ? smacg_get_public_profile_url( $user->user_login )
            : '#';

        $rows[] = [
            'rank'        => (int) $r['rank_pos'],
            'user_id'     => $uid,
            'username'    => $user->user_login,
            'display'     => $user->display_name ?: $user->user_login,
            'avatar'      => get_avatar_url( $uid, [ 'size' => 96 ] ),
            'profile_url' => $profile_url,
            'level'       => (int) ( $level_info['level'] ?? 0 ),
            'tier'        => $level_info['tier'] ?? '',
            'title'       => $level_info['title'] ?? '',
            'icon'        => $level_info['icon'] ?? '',
            'score'       => (int) $r['score'],
            'score_fmt'   => number_format( (int) $r['score'] ),
        ];
    }

    wp_send_json_success( [
        'type'       => $type,
        'page'       => $page,
        'per_page'   => $per_page,
        'total'      => (int) $data['total'],
        'total_page' => (int) ceil( $data['total'] / $per_page ),
        'updated_at' => $data['updated_at'],
        'rows'       => $rows,
        'my_pos'     => is_user_logged_in() && function_exists( 'smacg_ranking_user_position' )
            ? smacg_ranking_user_position( get_current_user_id(), $type )
            : null,
    ] );
}
add_action( 'wp_ajax_smacg_get_ranking',        'smacg_ajax_get_ranking' );
add_action( 'wp_ajax_nopriv_smacg_get_ranking', 'smacg_ajax_get_ranking' );
