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
        // === Level ===
        require_once SMACG_GAMIFY_DIR . 'includes/level/class-level-system.php';

        // === EXP ===
        require_once SMACG_GAMIFY_DIR . 'includes/exp/class-exp-config.php';
        require_once SMACG_GAMIFY_DIR . 'includes/exp/class-exp-events.php';

        // === Level UI ===
        require_once SMACG_GAMIFY_DIR . 'includes/level/class-level-badge.php';
        require_once SMACG_GAMIFY_DIR . 'includes/level/class-career-ajax.php';

        // === Ranking（Batch 2.2） ===
        require_once SMACG_GAMIFY_DIR . 'includes/ranking/class-ranking-system.php';
        require_once SMACG_GAMIFY_DIR . 'includes/ranking/class-ranking-cron.php';
        require_once SMACG_GAMIFY_DIR . 'includes/ranking/class-ranking-privacy.php';
        require_once SMACG_GAMIFY_DIR . 'includes/ranking/class-leaderboard-ajax.php';
        require_once SMACG_GAMIFY_DIR . 'includes/ranking/class-leaderboard-widget.php';

        // === Season Event（Batch 2.3） ===
        require_once SMACG_GAMIFY_DIR . 'includes/season-event/class-event-cpt.php';
        require_once SMACG_GAMIFY_DIR . 'includes/season-event/class-event-tracker.php';
        require_once SMACG_GAMIFY_DIR . 'includes/season-event/class-event-settle.php';
        if ( is_admin() ) {
            require_once SMACG_GAMIFY_DIR . 'includes/season-event/class-event-admin.php';
        }

        // === Compat ===
        require_once SMACG_GAMIFY_DIR . 'includes/compat/legacy-functions.php';

        // === Enqueue ===
        add_action( 'wp_enqueue_scripts', [ $this, 'localize_career_nonce' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_leaderboard_css' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_season_event_css' ], 20 );

        // === DB 版本檢查 ===
        add_action( 'init', [ $this, 'maybe_upgrade_db' ], 5 );
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
        $theme_css = get_stylesheet_directory()     . '/assets/css/leaderboard-widget.css';
        $theme_url = get_stylesheet_directory_uri() . '/assets/css/leaderboard-widget.css';
        if ( file_exists( $theme_css ) ) {
            wp_enqueue_style( 'smacg-leaderboard-widget', $theme_url, [], filemtime( $theme_css ) );
        }
    }

    /**
     * 載入 season-event.css。
     * 過渡期：CSS 仍在主題 assets/css/，僅在單篇活動頁 / 活動 archive 載入。
     */
    public function enqueue_season_event_css() {
        if ( ! ( is_singular( SMACG_EVENT_CPT ) || is_post_type_archive( SMACG_EVENT_CPT ) ) ) {
            return;
        }
        $theme_css = get_stylesheet_directory()     . '/assets/css/season-event.css';
        $theme_url = get_stylesheet_directory_uri() . '/assets/css/season-event.css';
        if ( file_exists( $theme_css ) ) {
            wp_enqueue_style( 'smacg-season-event', $theme_url, [], filemtime( $theme_css ) );
        }
    }

    public function maybe_upgrade_db() {
        require_once SMACG_GAMIFY_DIR . 'includes/class-activator.php';

        // Ranking tables
        if ( get_option( 'smacg_ranking_db_version', '0' ) !== SMACG_RANKING_DB_VERSION ) {
            Activator::install_ranking_tables();
        }
        // Event tables
        if ( get_option( 'smacg_event_db_version', '0' ) !== SMACG_EVENT_DB_VERSION ) {
            Activator::install_event_tables();
        }
    }
}
