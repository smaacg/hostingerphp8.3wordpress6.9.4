<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Leaderboard AJAX
 *
 * Action:  wp_ajax_smacg_get_ranking / wp_ajax_nopriv_smacg_get_ranking
 * Params:  type (string), page (int), per_page (int)
 *
 * v1.1：新增 rank_season 類型（TFT 段位賽季排行）
 */
class Leaderboard_Ajax {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_smacg_get_ranking',        [ __CLASS__, 'handle' ] );
        add_action( 'wp_ajax_nopriv_smacg_get_ranking', [ __CLASS__, 'handle' ] );
    }

    public static function handle() {
        $type     = sanitize_text_field( $_POST['type']     ?? $_GET['type']     ?? 'exp_total' );
        $page     = max( 1, (int) ( $_POST['page']     ?? $_GET['page']     ?? 1 ) );
        $per_page = max( 1, min( 100, (int) ( $_POST['per_page'] ?? $_GET['per_page'] ?? SMACG_RANKING_PAGE_SIZE ) ) );

        if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) {
            wp_send_json_error( [ 'message' => '無效的排行榜類型' ], 400 );
        }

        // 賽季排位走獨立資料源
        if ( $type === 'rank_season' ) {
            wp_send_json_success( self::get_rank_season( $page, $per_page ) );
        }

        $result = Ranking_System::get( $type, $page, $per_page );
        wp_send_json_success( $result );
    }

    /**
     * 賽季排位 leaderboard
     */
    private static function get_rank_season( $page, $per_page ) {
        $offset = ( $page - 1 ) * $per_page;
        $rows   = Rank_Season::get_leaderboard( $per_page, $offset );

        // 包裝成與 Ranking_System::get() 相同的結構
        $items = [];
        foreach ( $rows as $r ) {
            $u = get_userdata( $r['user_id'] );
            if ( ! $u ) continue;

            $items[] = [
                'rank_pos'    => $r['rank'],
                'user_id'     => $r['user_id'],
                'score'       => $r['score'],
                'display_name'=> $u->display_name ?: $u->user_login,
                'avatar_url'  => get_avatar_url( $r['user_id'], [ 'size' => 64 ] ),
                'profile_url' => function_exists( 'smacg_get_public_profile_url' )
                    ? smacg_get_public_profile_url( $u )
                    : home_url( '/u/' . urlencode( $u->user_login ) . '/' ),
                'extra' => [
                    'tier_key'   => $r['tier']['key'],
                    'tier_label' => $r['tier']['label'],
                    'tier_icon'  => $r['tier']['icon'],
                    'tier_color' => $r['tier']['color'],
                ],
            ];
        }

        return [
            'type'        => 'rank_season',
            'season_code' => \SMACG\Gamification\Rank_Tier::current_season_code(),
            'season_label'=> \SMACG\Gamification\Rank_Tier::season_label(
                \SMACG\Gamification\Rank_Tier::current_season_code()
            ),
            'page'        => $page,
            'per_page'    => $per_page,
            'items'       => $items,
            'total'       => self::get_rank_season_total(),
        ];
    }

    private static function get_rank_season_total() {
        global $wpdb;
        $tbl  = Rank_Season::table_current();
        $code = \SMACG\Gamification\Rank_Tier::current_season_code();
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl} WHERE season_code=%s AND season_score > 0",
            $code
        ) );
    }
}
