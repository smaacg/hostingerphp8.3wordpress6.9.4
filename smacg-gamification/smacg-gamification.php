<?php
/**
 * Plugin Name:       SMACG Gamification
 * Plugin URI:        https://github.com/smaacg/anime-sync-pro-2-
 * Description:       weixiaoacg 站點的等級、經驗值、徽章、排行榜與季賽事件系統。
 * Version:           2.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            smaacg
 * Text Domain:       smacg-gamification
 * Domain Path:       /languages
 *
 * @package SMACG_Gamification
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 版本與路徑常數
 * ============================================================ */
define( 'SMACG_GAMIFY_VERSION', '2.5.0' );
define( 'SMACG_GAMIFY_FILE',    __FILE__ );
define( 'SMACG_GAMIFY_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SMACG_GAMIFY_URL',     plugin_dir_url( __FILE__ ) );
define( 'SMACG_GAMIFY_BASENAME', plugin_basename( __FILE__ ) );

/* ============================================================
 * 業務常數（與主題共用，必須 define 才能在主題模板讀到）
 * ============================================================ */
if ( ! defined( 'SMACG_EXP_SLUG' ) )   define( 'SMACG_EXP_SLUG',   'exp' );
if ( ! defined( 'SMACG_BADGE_SLUG' ) ) define( 'SMACG_BADGE_SLUG', 'badge' );

/* Season Event */
if ( ! defined( 'SMACG_EVENT_CPT' ) )        define( 'SMACG_EVENT_CPT',        'smacg_season_event' );
if ( ! defined( 'SMACG_EVENT_DB_VERSION' ) ) define( 'SMACG_EVENT_DB_VERSION', '1.0.0' );

/* Ranking */
if ( ! defined( 'SMACG_RANKING_DB_VERSION' ) ) define( 'SMACG_RANKING_DB_VERSION', '1.0.0' );
if ( ! defined( 'SMACG_RANKING_TOP_N' ) )      define( 'SMACG_RANKING_TOP_N',      100 );
if ( ! defined( 'SMACG_RANKING_PAGE_SIZE' ) )  define( 'SMACG_RANKING_PAGE_SIZE',  20 );
if ( ! defined( 'SMACG_RANKING_TYPES' ) )      define( 'SMACG_RANKING_TYPES',      [ 'exp_total', 'exp_monthly', 'followers', 'badges' ] );
if ( ! defined( 'SMACG_RANKING_META_KEY' ) )   define( 'SMACG_RANKING_META_KEY',   'smacg_appear_in_ranking' );

/* ============================================================
 * 自訂 cron 排程（10 分鐘）
 *    必須在 plugins_loaded 之前註冊，否則 wp_schedule_event 拿不到 interval。
 * ============================================================ */
add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['smacg_10min'] ) ) {
        $schedules['smacg_10min'] = [
            'interval' => 600,
            'display'  => __( 'Every 10 Minutes', 'smacg-gamification' ),
        ];
    }
    return $schedules;
} );

/* ============================================================
 * Activation / Deactivation
 * ============================================================ */
register_activation_hook( __FILE__, function () {
    require_once SMACG_GAMIFY_DIR . 'includes/class-activator.php';
    \SMACG\Gamification\Activator::run();
} );

register_deactivation_hook( __FILE__, function () {
    require_once SMACG_GAMIFY_DIR . 'includes/class-deactivator.php';
    \SMACG\Gamification\Deactivator::run();
} );

/* ============================================================
 * Bootstrap
 * ============================================================ */
require_once SMACG_GAMIFY_DIR . 'includes/class-plugin.php';
add_action( 'plugins_loaded', [ '\SMACG\Gamification\Plugin', 'instance' ], 5 );

/* ============================================================
 * 啟用前置條件警告（GamiPress 未啟用 / 主題版本過舊）
 * ============================================================ */
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'activate_plugins' ) ) return;

    $msgs = [];

    if ( ! function_exists( 'gamipress_get_user_points' ) ) {
        $msgs[] = '偵測不到 <strong>GamiPress</strong> 外掛，EXP / 徽章功能將降級為 user_meta fallback。';
    }

    if ( defined( 'weixiaoacg_VERSION' ) && version_compare( weixiaoacg_VERSION, '2.12.0', '<' ) ) {
        $msgs[] = sprintf(
            '主題 <strong>weixiaoacg</strong> 版本為 %s，需升級到 ≥ 2.12.0 才能與本外掛協同運作（避免雙重發放 EXP）。',
            esc_html( weixiaoacg_VERSION )
        );
    }

    if ( empty( $msgs ) ) return;

    echo '<div class="notice notice-warning"><p><strong>SMACG Gamification：</strong></p><ul style="margin-left:20px;list-style:disc;">';
    foreach ( $msgs as $m ) echo '<li>' . wp_kses_post( $m ) . '</li>';
    echo '</ul></div>';
} );
