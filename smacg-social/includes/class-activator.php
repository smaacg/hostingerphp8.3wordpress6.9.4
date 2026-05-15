<?php
/**
 * SMACG Social — Activator
 *
 * 啟用時建立資料表與排程 cron。
 * 注意：follow-system.php 與 notifications-system.php 內部也有 dbDelta 自我建表邏輯，
 *       這裡再呼叫一次是為了確保啟用當下立即生效，不必等 admin_init / init。
 */

namespace SMACG\Social;

defined( 'ABSPATH' ) || exit;

final class Activator {
    public static function run(): void {

        // 記錄啟用時間
        if ( ! get_option( 'smacg_social_activated_at' ) ) {
            update_option( 'smacg_social_activated_at', time() );
        }

        // 記錄版本
        update_option( 'smacg_social_version', SMACG_SOCIAL_VERSION );

        // 載入並執行建表
        $legacy = SMACG_SOCIAL_DIR . 'includes/legacy/';

        if ( file_exists( $legacy . 'follow-system.php' ) ) {
            require_once $legacy . 'follow-system.php';
            if ( function_exists( 'smacg_follows_install' ) ) {
                smacg_follows_install();
            }
        }

        if ( file_exists( $legacy . 'notifications-system.php' ) ) {
            require_once $legacy . 'notifications-system.php';
            if ( function_exists( 'smacg_notifications_install' ) ) {
                smacg_notifications_install();
            }
        }

        // 排程 cron（如果還沒排程的話）
        if ( ! wp_next_scheduled( 'smacg_notifications_daily_purge' ) ) {
            $tomorrow_3am = strtotime( 'tomorrow 03:00:00 ' . wp_timezone_string() );
            wp_schedule_event( $tomorrow_3am, 'daily', 'smacg_notifications_daily_purge' );
        }
    }
}
