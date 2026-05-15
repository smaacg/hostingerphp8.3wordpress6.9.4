<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

class Plugin {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
            self::$instance->load();
        }
        return self::$instance;
    }

    private function __construct() {}

    private function load() {
        $base = SMACG_GAMIFY_DIR . 'includes/';

        // 1. GamiPress Bridge
        require_once $base . 'gamipress/class-gamipress-bridge.php';
        Gamipress_Bridge::instance();

        // 2. Level
        require_once $base . 'level/class-level-system.php';
        Level_System::instance();

        // 3. EXP
        require_once $base . 'exp/class-exp-config.php';
        require_once $base . 'exp/class-exp-events.php';
        Exp_Events::instance();

        // 4. Level UI
        require_once $base . 'level/class-level-badge.php';
        Level_Badge::instance();
        require_once $base . 'level/class-career-ajax.php';
        Career_Ajax::instance();

        // 5. Ranking
        require_once $base . 'ranking/class-ranking-system.php';
        Ranking_System::instance();
        require_once $base . 'ranking/class-ranking-cron.php';
        Ranking_Cron::instance();
        require_once $base . 'ranking/class-ranking-privacy.php';
        Ranking_Privacy::instance();
        require_once $base . 'ranking/class-leaderboard-ajax.php';
        Leaderboard_Ajax::instance();
        require_once $base . 'ranking/class-leaderboard-widget.php';
        Leaderboard_Widget::instance();

        // 5b. Rank Season（TFT 段位賽季系統）
        require_once $base . 'ranking/class-rank-tier.php';
        require_once $base . 'ranking/class-rank-season.php';
        Rank_Season::instance();

        // 6. Season Event
        require_once $base . 'season-event/class-event-cpt.php';
        Event_CPT::instance();
        require_once $base . 'season-event/class-event-tracker.php';
        Event_Tracker::instance();
        require_once $base . 'season-event/class-event-settle.php';
        Event_Settle::instance();
        if ( is_admin() ) {
            require_once $base . 'season-event/class-event-admin.php';
            Event_Admin::instance();
        }

        // 7. GamiPress Notification Bridge
        require_once $base . 'gamipress/class-gamipress-notif-bridge.php';
        Gamipress_Notif_Bridge::instance();

        // 8. REST
        require_once $base . 'rest/class-rest-api.php';
        Rest_Api::instance();

        // 9. Compat
        require_once $base . 'compat/legacy-functions.php';

        // === Hooks ===
        add_action( 'wp_enqueue_scripts', [ $this, 'localize_career_nonce' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_leaderboard_css' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_season_event_css' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_rank_season_css' ], 20 );
        add_action( 'init', [ $this, 'maybe_upgrade_db' ], 5 );
        add_action( 'init', [ $this, 'maybe_flush_rewrite' ], 99 );
    }

    public function localize_career_nonce() {
        if ( ! is_user_logged_in() ) return;
        $data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'smacg_career_nonce' ),
        ];
        if ( wp_script_is( 'smacg-member', 'enqueued' ) ) {
            wp_localize_script( 'smacg-member', 'SmacgCareer', $data );
        } else {
            add_action( 'wp_footer', function () use ( $data ) {
                echo '<script>window.SmacgCareer = ' . wp_json_encode( $data ) . ';</script>';
            }, 5 );
        }
    }

    public function enqueue_leaderboard_css() {
        $f = get_stylesheet_directory() . '/assets/css/leaderboard-widget.css';
        if ( file_exists( $f ) ) {
            wp_enqueue_style( 'smacg-leaderboard-widget',
                get_stylesheet_directory_uri() . '/assets/css/leaderboard-widget.css',
                [], filemtime( $f ) );
        }
    }

    public function enqueue_season_event_css() {
        if ( ! ( is_singular( SMACG_EVENT_CPT ) || is_post_type_archive( SMACG_EVENT_CPT ) ) ) return;
        $f = get_stylesheet_directory() . '/assets/css/season-event.css';
        if ( file_exists( $f ) ) {
            wp_enqueue_style( 'smacg-season-event',
                get_stylesheet_directory_uri() . '/assets/css/season-event.css',
                [], filemtime( $f ) );
        }
    }

    public function enqueue_rank_season_css() {
        if ( ! is_page_template( 'page-ranking-users.php' ) && ! is_page( 'mc' ) ) return;
        $f = get_stylesheet_directory() . '/assets/css/rank-season.css';
        if ( file_exists( $f ) ) {
            wp_enqueue_style( 'smacg-rank-season',
                get_stylesheet_directory_uri() . '/assets/css/rank-season.css',
                [], filemtime( $f ) );
        }
    }

    public function maybe_upgrade_db() {
        require_once SMACG_GAMIFY_DIR . 'includes/class-activator.php';
        if ( get_option( 'smacg_ranking_db_version', '0' ) !== SMACG_RANKING_DB_VERSION ) {
            Activator::install_ranking_tables();
        }
        if ( get_option( 'smacg_event_db_version', '0' ) !== SMACG_EVENT_DB_VERSION ) {
            Activator::install_event_tables();
        }
        if ( get_option( 'smacg_rank_season_db_version', '0' ) !== SMACG_RANK_SEASON_DB_VERSION ) {
            Activator::install_rank_season_tables();
        }
    }

    public function maybe_flush_rewrite() {
        if ( get_option( 'smacg_event_cpt_flushed', '1' ) === '0' ) {
            flush_rewrite_rules( false );
            update_option( 'smacg_event_cpt_flushed', '1' );
        }
    }
}
