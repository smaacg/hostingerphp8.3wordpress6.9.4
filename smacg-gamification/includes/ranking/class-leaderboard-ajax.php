<?php
/**
 * Leaderboard AJAX
 *
 * 原檔：blocksy-child/inc/leaderboard-ajax.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Ranking;

defined( 'ABSPATH' ) || exit;

class Leaderboard_Ajax {

    public static function init() {
        add_action( 'wp_ajax_smacg_get_ranking',        [ __CLASS__, 'handle' ] );
        add_action( 'wp_ajax_nopriv_smacg_get_ranking', [ __CLASS__, 'handle' ] );
    }

    public static function handle() {
        $type     = isset( $_REQUEST['type'] ) ? sanitize_key( $_REQUEST['type'] ) : 'exp_total';
        $page     = isset( $_REQUEST['page'] ) ? max( 1, (int) $_REQUEST['page'] ) : 1;
        $per_page = isset( $_REQUEST['per_page'] ) ? min( 50, max( 1, (int) $_REQUEST['per_page'] ) ) : 20;

        if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) {
            wp_send_json_error( [ 'code' => 'bad_type' ], 400 );
        }

        $data = System::get( $type, $page, $per_page );

        $rows = [];
        foreach ( $data['rows'] as $r ) {
            $uid  = (int) $r['user_id'];
            $user = get_user_by( 'id', $uid );
            if ( ! $user ) continue;

            $level_info = function_exists( 'smacg_get_user_level_info' )
                ? \smacg_get_user_level_info( $uid )
                : [ 'level' => 0, 'tier' => '', 'title' => '', 'icon' => '' ];

            $profile_url = function_exists( 'smacg_get_public_profile_url' )
                ? \smacg_get_public_profile_url( $user->user_login )
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
            'my_pos'     => is_user_logged_in()
                ? System::user_position( get_current_user_id(), $type )
                : null,
        ] );
    }
}

Leaderboard_Ajax::init();
