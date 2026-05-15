<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * v2.0.0 (2026-05-15)
 *   - 載入新 Career_Jobs（8 職業）
 *   - Level_System / Career_Ajax 改用新版 sqrt 公式 + 6 階稱號
 *   - 新增 career-select.js enqueue
 */
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

        // 2. Level（新版：sqrt 公式 + 6 階會員稱號）
        require_once $base . 'level/class-level-system.php';
        Level_System::instance();

        // 3. EXP
        require_once $base . 'exp/class-exp-config.php';
        require_once $base . 'exp/class-exp-events.php';
        Exp_Events::instance();

        // 4. Career Jobs（8 職業 × 4 階稱號）+ AJAX endpoint
        require_once $base . 'level/class-career-jobs.php';
        Career_Jobs::instance();
        require_once $base . 'level/class-career-ajax.php';
        Career_Ajax::instance();

        // 5. Level UI（留言徽章 / hero 大徽章）
        require_once $base . 'level/class-level-badge.php';
        Level_Badge::instance();

        // 6. Ranking
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

        // 6b. Rank Season（TFT 段位賽季）
        require_once $base . 'ranking/class-rank-tier.php';
        require_once $base . 'ranking/class-rank-season.php';
        Rank_Season::instance();

        // 7. Season Event
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

        // 8. GamiPress Notification Bridge
        require_once $base . 'gamipress/class-gamipress-notif-bridge.php';
        Gamipress_Notif_Bridge::instance();

        // 9. REST
        require_once $base . 'rest/class-rest-api.php';
        Rest_Api::instance();

        // 10. Compat
        require_once $base . 'compat/legacy-functions.php';

        // === Hooks ===
        add_action( 'wp_enqueue_scripts', [ $this, 'localize_career_nonce' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_leaderboard_css' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_season_event_css' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_rank_season_css' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_level_guide_assets' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_career_select_js' ], 25 );
        add_action( 'init', [ $this, 'maybe_upgrade_db' ], 5 );
        add_action( 'init', [ $this, 'maybe_flush_rewrite' ], 99 );
    }

    /**
     * 注入 AJAX 設定（給 career-select.js 用）
     */
    public function localize_career_nonce() {
        if ( ! is_user_logged_in() ) return;
        $data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'smacg_career_nonce' ),
        ];
        // 1) 若 career-select.js 已 enqueued，直接 localize
        if ( wp_script_is( 'smacg-career-select', 'enqueued' ) ) {
            wp_localize_script( 'smacg-career-select', 'SmacgCareer', $data );
            return;
        }
        // 2) 否則在 footer 注入 inline（保留兼容性）
        add_action( 'wp_footer', function () use ( $data ) {
            echo '<script>window.SmacgCareer = ' . wp_json_encode( $data ) . ';</script>';
        }, 5 );
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

    public function enqueue_level_guide_assets() {
        if ( ! is_page_template( 'page-level-guide.php' ) ) return;

        $css = get_stylesheet_directory() . '/assets/css/level-guide.css';
        if ( file_exists( $css ) ) {
            wp_enqueue_style( 'smacg-level-guide',
                get_stylesheet_directory_uri() . '/assets/css/level-guide.css',
                [], filemtime( $css ) );
        }

        $js = get_stylesheet_directory() . '/assets/js/level-guide.js';
        if ( file_exists( $js ) ) {
            wp_enqueue_script( 'smacg-level-guide',
                get_stylesheet_directory_uri() . '/assets/js/level-guide.js',
                [], filemtime( $js ), true );
        }
    }

    /**
     * 職業選擇 JS（會員中心 + level-guide 兩處都會用到）
     */
    public function enqueue_career_select_js() {
        if ( ! is_user_logged_in() ) return;
        // 只在會員中心或 level-guide 頁載入
        if ( ! is_page_template( 'page-member.php' )
            && ! is_page_template( 'page-level-guide.php' )
            && ! is_page( 'mc' ) ) return;

        $js = get_stylesheet_directory() . '/assets/js/career-select.js';
        if ( file_exists( $js ) ) {
            wp_enqueue_script( 'smacg-career-select',
                get_stylesheet_directory_uri() . '/assets/js/career-select.js',
                [], filemtime( $js ), true );

            wp_localize_script( 'smacg-career-select', 'SmacgCareer', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'smacg_career_nonce' ),
            ] );
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
