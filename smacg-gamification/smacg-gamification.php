<?php
/**
 * Plugin Name: SMACG Gamification
 * Plugin URI:  https://github.com/smaacg/anime-sync-pro-2-
 * Description: weixiaoacg 站點的等級、經驗值、徽章、排行榜、季賽事件、TFT 排位賽季系統。
 * Version:     2.6.1
 * Author:      smaacg
 * Text Domain: smacg-gamification
 *
 * v2.6.1 (2026-05-17)
 *   - SMACG_RANKING_TYPES 新增 'rank_last_season'（上季牌位排行）
 */

defined( 'ABSPATH' ) || exit;

define( 'SMACG_GAMIFY_VERSION', '2.6.1' );
define( 'SMACG_GAMIFY_FILE',    __FILE__ );
define( 'SMACG_GAMIFY_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SMACG_GAMIFY_URL',     plugin_dir_url( __FILE__ ) );
define( 'SMACG_GAMIFY_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'SMACG_EXP_SLUG' ) )   define( 'SMACG_EXP_SLUG',   'exp' );
if ( ! defined( 'SMACG_BADGE_SLUG' ) ) define( 'SMACG_BADGE_SLUG', 'badge' );

if ( ! defined( 'SMACG_EVENT_CPT' ) )        define( 'SMACG_EVENT_CPT',        'smacg_season_event' );
if ( ! defined( 'SMACG_EVENT_DB_VERSION' ) ) define( 'SMACG_EVENT_DB_VERSION', '1.0.0' );

if ( ! defined( 'SMACG_RANKING_DB_VERSION' ) ) define( 'SMACG_RANKING_DB_VERSION', '1.0.0' );
if ( ! defined( 'SMACG_RANKING_TOP_N' ) )      define( 'SMACG_RANKING_TOP_N',      100 );
if ( ! defined( 'SMACG_RANKING_PAGE_SIZE' ) )  define( 'SMACG_RANKING_PAGE_SIZE',  20 );
if ( ! defined( 'SMACG_RANKING_TYPES' ) )      define( 'SMACG_RANKING_TYPES',      [ 'exp_total', 'exp_monthly', 'followers', 'badges', 'rank_season', 'rank_last_season' ] );
if ( ! defined( 'SMACG_RANKING_META_KEY' ) )   define( 'SMACG_RANKING_META_KEY',   'smacg_appear_in_ranking' );

if ( ! defined( 'SMACG_RANK_SEASON_DB_VERSION' ) ) define( 'SMACG_RANK_SEASON_DB_VERSION', '1.0.0' );

add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['smacg_10min'] ) ) {
        $schedules['smacg_10min'] = [ 'interval' => 600, 'display' => __( 'Every 10 Minutes', 'smacg-gamification' ) ];
    }
    return $schedules;
} );

register_activation_hook( __FILE__, function () {
    require_once SMACG_GAMIFY_DIR . 'includes/class-activator.php';
    require_once SMACG_GAMIFY_DIR . 'includes/ranking/class-rank-tier.php';
    \SMACG\Gamification\Activator::run();
} );

register_deactivation_hook( __FILE__, function () {
    require_once SMACG_GAMIFY_DIR . 'includes/class-deactivator.php';
    \SMACG\Gamification\Deactivator::run();
} );

require_once SMACG_GAMIFY_DIR . 'includes/class-plugin.php';
add_action( 'plugins_loaded', [ '\SMACG\Gamification\Plugin', 'instance' ], 5 );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'activate_plugins' ) ) return;
    $msgs = [];
    if ( ! function_exists( 'gamipress_get_user_points' ) ) {
        $msgs[] = '偵測不到 <strong>GamiPress</strong> 外掛，EXP / 徽章功能將降級為 user_meta fallback。';
    }
    if ( defined( 'weixiaoacg_VERSION' ) && version_compare( weixiaoacg_VERSION, '2.12.0', '<' ) ) {
        $msgs[] = sprintf( '主題 <strong>weixiaoacg</strong> 版本為 %s，需升級到 ≥ 2.12.0。', esc_html( weixiaoacg_VERSION ) );
    }
    if ( empty( $msgs ) ) return;
    echo '<div class="notice notice-warning"><p><strong>SMACG Gamification：</strong></p><ul style="margin-left:20px;list-style:disc;">';
    foreach ( $msgs as $m ) echo '<li>' . wp_kses_post( $m ) . '</li>';
    echo '</ul></div>';
} );
