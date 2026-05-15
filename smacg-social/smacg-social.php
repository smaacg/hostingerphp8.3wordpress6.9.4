<?php
/**
 * Plugin Name:       SMACG Social
 * Plugin URI:        https://github.com/smaacg/anime-sync-pro-2-
 * Description:       社交功能：追蹤系統、通知中心、公開檔案。從 blocksy-child v2.14.0 拆分而來。
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            微笑動漫
 * Author URI:        https://smile-acg.com
 * License:           GPL v2 or later
 * Text Domain:       smacg-social
 *
 * Phase 3-B 遷移（2026-05-15）：
 *   來源：blocksy-child v2.14.0
 *     - inc/follow-system.php           → includes/legacy/follow-system.php
 *     - inc/follow-ajax.php             → includes/legacy/follow-ajax.php
 *     - inc/notifications-system.php    → includes/legacy/notifications-system.php
 *     - inc/notifications-events.php    → includes/legacy/notifications-events.php
 *     - inc/notifications-ajax.php      → includes/legacy/notifications-ajax.php
 *     - inc/notifications-render.php    → includes/legacy/notifications-render.php
 *     - inc/notifications-email.php     → includes/legacy/notifications-email.php
 *     - inc/public-profile.php          → includes/legacy/public-profile.php
 *     - inc/public-profile-render.php   → includes/legacy/public-profile-render.php
 *
 *   遷移策略：薄包裝（Thin Wrapper），函式名稱與簽名 0 改動
 *
 *   軟依賴：
 *     - smacg-members（提供 weixiaoacg_get_user_level_int / smacg_get_member_center_url）
 *     - smacg-gamification（提供 smacg_get_user_level_info；負責 badge 通知，避免重複）
 *     - Ultimate Member 外掛（public-profile 會用 um_user 取資料）
 *
 *   反向依賴（其他外掛/主題會呼叫的本外掛函式）：
 *     - smacg_create_notification()           → 被 smacg-gamification 呼叫
 *     - smacg_get_public_profile_url()        → 被多處呼叫
 *     - smacg_follow_user / smacg_is_following → 被 public-profile-render 呼叫
 *
 *   資料表（首次啟用會 dbDelta 建立）：
 *     - {prefix}smacg_follows         追蹤關係
 *     - {prefix}smacg_notifications   通知記錄
 *
 *   Cron 排程：
 *     - smacg_notifications_daily_purge  每日凌晨 3:00 清除 30 天前通知
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數
   ============================================================ */
if ( ! defined( 'SMACG_SOCIAL_VERSION' ) )  define( 'SMACG_SOCIAL_VERSION',  '1.0.0' );
if ( ! defined( 'SMACG_SOCIAL_FILE' ) )     define( 'SMACG_SOCIAL_FILE',     __FILE__ );
if ( ! defined( 'SMACG_SOCIAL_DIR' ) )      define( 'SMACG_SOCIAL_DIR',      plugin_dir_path( __FILE__ ) );
if ( ! defined( 'SMACG_SOCIAL_URL' ) )      define( 'SMACG_SOCIAL_URL',      plugin_dir_url( __FILE__ ) );
if ( ! defined( 'SMACG_SOCIAL_BASENAME' ) ) define( 'SMACG_SOCIAL_BASENAME', plugin_basename( __FILE__ ) );

/* ============================================================
   啟用 / 停用
   ============================================================ */
register_activation_hook( __FILE__, function() {
    require_once SMACG_SOCIAL_DIR . 'includes/class-activator.php';
    \SMACG\Social\Activator::run();
} );

register_deactivation_hook( __FILE__, function() {
    require_once SMACG_SOCIAL_DIR . 'includes/class-deactivator.php';
    \SMACG\Social\Deactivator::run();
} );

/* ============================================================
   Bootstrap（priority 12：在 smacg-members=10 之後）
   ============================================================ */
add_action( 'plugins_loaded', function() {
    require_once SMACG_SOCIAL_DIR . 'includes/class-plugin.php';
    \SMACG\Social\Plugin::instance();
}, 12 );

/* ============================================================
   健康檢查
   ============================================================ */
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $missing = [];

    // SMACG_FOLLOW_DAILY_LIMIT 與 SMACG_FOLLOW_COOLDOWN 必須在主題 functions.php 定義
    if ( ! defined( 'SMACG_FOLLOW_DAILY_LIMIT' ) ) {
        $missing[] = 'SMACG_FOLLOW_DAILY_LIMIT（請在主題 functions.php 定義）';
    }
    if ( ! defined( 'SMACG_FOLLOW_COOLDOWN' ) ) {
        $missing[] = 'SMACG_FOLLOW_COOLDOWN（請在主題 functions.php 定義）';
    }

    if ( empty( $missing ) ) return;

    echo '<div class="notice notice-error"><p><strong>SMACG Social：</strong>偵測到下列常數未定義，追蹤系統將無法正常運作：</p><ul style="list-style:disc;margin-left:24px">';
    foreach ( $missing as $m ) echo '<li>' . esc_html( $m ) . '</li>';
    echo '</ul></div>';
} );
