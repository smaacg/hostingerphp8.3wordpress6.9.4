<?php
/**
 * SMACG Social — Deactivator
 *
 * 停用時清除 cron，但保留資料表（資料寶貴）。
 */

namespace SMACG\Social;

defined( 'ABSPATH' ) || exit;

final class Deactivator {
    public static function run(): void {
        wp_clear_scheduled_hook( 'smacg_notifications_daily_purge' );
    }
}
