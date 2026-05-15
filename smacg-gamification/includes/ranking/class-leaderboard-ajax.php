<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Leaderboard AJAX（搬自 theme/inc/leaderboard-ajax.php）
 *
 * Action:  wp_ajax_smacg_get_ranking / wp_ajax_nopriv_smacg_get_ranking
 * Params:  type (string), page (int), per_page (int)
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

        $result = Ranking_System::get( $type, $page, $per_page );
        wp_send_json_success( $result );
    }
}
