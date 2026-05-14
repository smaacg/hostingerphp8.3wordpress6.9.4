<?php
/**
 * Plugin Name:       SMACG Gamification
 * Plugin URI:        https://github.com/smaacg/anime-sync-pro-2-
 * Description:       weixiaoacg 站點的等級、經驗值、徽章、排行榜與季賽事件系統。Batch 2.1 提供 EXP + Level 模組。
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            smaacg
 * Text Domain:       smacg-gamification
 * Domain Path:       /languages
 *
 * @package SMACG_Gamification
 */

/* === 版本號 === */
define( 'SMACG_GAMIFY_VERSION', '2.5.0' );
if ( ! defined( 'SMACG_EXP_SLUG' ) )   define( 'SMACG_EXP_SLUG', 'exp' );
if ( ! defined( 'SMACG_BADGE_SLUG' ) ) define( 'SMACG_BADGE_SLUG', 'badge' );

/* === Batch 2.3：Season Event 常數（必須 define 才能全域可見，主題模板會用） === */
if ( ! defined( 'SMACG_EVENT_CPT' ) ) {
    define( 'SMACG_EVENT_CPT', 'smacg_season_event' );
}
if ( ! defined( 'SMACG_EVENT_DB_VERSION' ) ) {
    define( 'SMACG_EVENT_DB_VERSION', '1.0.0' );
}

if ( ! defined( 'SMACG_RANKING_DB_VERSION' ) ) {
    define( 'SMACG_RANKING_DB_VERSION', '1.0.0' );
}
if ( ! defined( 'SMACG_RANKING_TOP_N' ) ) {
    define( 'SMACG_RANKING_TOP_N', 100 );
}
if ( ! defined( 'SMACG_RANKING_PAGE_SIZE' ) ) {
    define( 'SMACG_RANKING_PAGE_SIZE', 20 );
}
if ( ! defined( 'SMACG_RANKING_TYPES' ) ) {
    define( 'SMACG_RANKING_TYPES', [ 'exp_total', 'exp_monthly', 'followers', 'badges' ] );
}
if ( ! defined( 'SMACG_RANKING_META_KEY' ) ) {
    define( 'SMACG_RANKING_META_KEY', 'smacg_appear_in_ranking' );
}

// SMACG_BADGE_SLUG 在主題 functions.php 已定義（GamiPress 成就 CPT slug）。
// 若主題尚未載入，給一個保險預設，避免外掛單獨啟用時 fatal。
if ( ! defined( 'SMACG_BADGE_SLUG' ) ) {
    define( 'SMACG_BADGE_SLUG', 'smacg-badge' );
}

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
 * 主題相依檢查（admin notice）
 * ============================================================ */
add_action( 'admin_notices', function () {
    // 主題若尚未提供 smacg_award_exp / smacg_get_user_exp，警告但不 fatal
    if ( ! function_exists( 'smacg_award_exp' ) || ! function_exists( 'smacg_get_user_exp' ) ) {
        if ( ! current_user_can( 'activate_plugins' ) ) return;
        echo '<div class="notice notice-warning"><p><strong>SMACG Gamification：</strong>'
           . '偵測不到主題的 <code>smacg_award_exp()</code> / <code>smacg_get_user_exp()</code>，'
           . '請確認 weixiaoacg 主題（blocksy-child v2.8.0+）已啟用。EXP 發放將暫時停用。</p></div>';
    }
} );
