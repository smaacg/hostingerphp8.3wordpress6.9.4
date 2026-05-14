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

        // 1. GamiPress Bridge（最底層，必須最先；後面 EXP / Badge 都依賴它）
        require_once $base . 'gamipress/class-gamipress-bridge.php';
        Gamipress_Bridge::instance();

        // 2. Level（EXP 升級偵測需要用 level table）
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

        // 7. GamiPress Notification Bridge（依賴 1 + 通知中心）
        require_once $base . 'gamipress/class-gamipress-notif-bridge.php';
        Gamipress_Notif_Bridge::instance();

        // 8. REST
        require_once $base . 'rest/class-rest-api.php';
        Rest_Api::instance();

        // 9. Compat（function wrappers，主題模板要用）
        require_once $base . 'compat/legacy-functions.php';

        // === Hooks ===
        add_action( 'wp_enqueue_scripts', [ $this, 'localize_career_nonce' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_leaderboard_css' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_season_event_css' ], 20 );
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
        $theme_css = get_stylesheet_directory()     . '/assets/css/leaderboard-widget.css';
        $theme_url = get_stylesheet_directory_uri() . '/assets/css/leaderboard-widget.css';
        if ( file_exists( $theme_css ) ) {
            wp_enqueue_style( 'smacg-leaderboard-widget', $theme_url, [], filemtime( $theme_css ) );
        }
    }

    public function enqueue_season_event_css() {
        if ( ! ( is_singular( SMACG_EVENT_CPT ) || is_post_type_archive( SMACG_EVENT_CPT ) ) ) return;
        $theme_css = get_stylesheet_directory()     . '/assets/css/season-event.css';
        $theme_url = get_stylesheet_directory_uri() . '/assets/css/season-event.css';
        if ( file_exists( $theme_css ) ) {
            wp_enqueue_style( 'smacg-season-event', $theme_url, [], filemtime( $theme_css ) );
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
    }

    /**
     * CPT 註冊在 init priority 10，因此 flush 必須在 ≥ 11；
     * 我們在 init 99 檢查一次性 flag，flush 完即清除。
     */
    public function maybe_flush_rewrite() {
        if ( get_option( 'smacg_event_cpt_flushed', '1' ) === '0' ) {
            flush_rewrite_rules( false );
            update_option( 'smacg_event_cpt_flushed', '1' );
        }
    }
}
