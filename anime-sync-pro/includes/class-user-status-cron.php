<?php
/**
 * User Status Cron
 *
 * @package Anime_Sync_Pro
 * @version 1.1.0
 *
 * Changelog:
 *   1.1.0 — Stats 重算優化
 *     - [USC-H1] recalc_stats() 改 INSERT...ON DUPLICATE KEY UPDATE，
 *               不再 TRUNCATE，避免重算期間 get_ranking() 撈到空表。
 *     - [USC-H1] 新增孤兒列清理：刪除主表已沒紀錄但 stats 表還留著的 anime_id。
 *     - [USC-M1] flush_ranking_cache() 改用 wp_cache_flush_group()，
 *                避免 limit 不在白名單時 cache 殘留。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_User_Status_Cron {

    const HOOK     = 'anime_sync_recalc_user_status_stats';
    const SCHEDULE = 'asp_every_15_minutes';

    public function __construct() {
        add_filter( 'cron_schedules', [ $this, 'add_schedule' ] );
        add_action( self::HOOK, [ $this, 'recalc_stats' ] );
        add_action( 'init', [ $this, 'maybe_schedule' ] );
        add_action( 'wp_ajax_asp_recalc_user_status_stats', [ $this, 'ajax_manual_recalc' ] );
    }

    public function add_schedule( $schedules ) {
        $schedules[ self::SCHEDULE ] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => 'Every 15 minutes (Anime Sync)',
        ];
        return $schedules;
    }

    public function maybe_schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, self::SCHEDULE, self::HOOK );
        }
    }

    public static function unschedule(): void {
        $ts = wp_next_scheduled( self::HOOK );
        if ( $ts ) wp_unschedule_event( $ts, self::HOOK );
    }

    public function recalc_stats(): int {
        global $wpdb;

        $main_table  = $wpdb->prefix . 'anime_user_status';
        $stats_table = $wpdb->prefix . 'anime_user_status_stats';

        $start_time = microtime( true );

        // [USC-H1] 改用 INSERT ... ON DUPLICATE KEY UPDATE，
        // 不再 TRUNCATE，避免重算期間排行榜撈到空表。
        $sql = "
            INSERT INTO {$stats_table}
                (anime_id, want_count, watching_count, completed_count,
                 dropped_count, favorited_count, total_count)
            SELECT
                anime_id,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS want_count,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS watching_count,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS dropped_count,
                SUM(favorited)                              AS favorited_count,
                COUNT(*)                                    AS total_count
            FROM {$main_table}
            GROUP BY anime_id
            ON DUPLICATE KEY UPDATE
                want_count      = VALUES(want_count),
                watching_count  = VALUES(watching_count),
                completed_count = VALUES(completed_count),
                dropped_count   = VALUES(dropped_count),
                favorited_count = VALUES(favorited_count),
                total_count     = VALUES(total_count)
        ";

        $rows_affected = $wpdb->query( $sql );

        // [USC-H1] 清掉主表已沒紀錄但 stats 表還留著的 anime_id
        // （該動畫被所有使用者移除後，stats 應同步歸零/刪除）
        $wpdb->query( "
            DELETE s FROM {$stats_table} s
            LEFT JOIN {$main_table} m ON s.anime_id = m.anime_id
            WHERE m.anime_id IS NULL
        " );

        $duration = round( microtime( true ) - $start_time, 3 );

        $this->flush_ranking_cache();

        if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
            Anime_Sync_Error_Logger::info( 'User status stats recalculated', [
                'rows_affected' => (int) $rows_affected,
                'duration_sec'  => $duration,
            ] );
        }

        return (int) $rows_affected;
    }

    public function ajax_manual_recalc(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
        }
        check_ajax_referer( 'asp_recalc_stats', 'nonce' );

        $count = $this->recalc_stats();
        wp_send_json_success( [
            'message' => "Recalculated {$count} anime stats",
            'count'   => $count,
        ] );
    }

    /**
     * [USC-M1] 清除排行榜快取
     *
     * 原本只列舉特定 limit 值，若 get_ranking() 用其他 limit 會殘留。
     * 改用 wp_cache_flush_group() 一次清光整個 group，順便連個人狀態 cache
     * 也清掉（個人 cache TTL 60 秒，每 15 分鐘清一次無感）。
     */
    private function flush_ranking_cache(): void {
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'anime_user_status' );
            return;
        }

        // WordPress < 6.1 fallback
        $types  = [ 'favorited', 'watching', 'completed', 'want', 'dropped', 'total' ];
        $limits = [ 5, 10, 15, 20, 25, 30, 50, 100 ];
        foreach ( $types as $t ) {
            foreach ( $limits as $l ) {
                wp_cache_delete( "us_rank_{$t}_{$l}", 'anime_user_status' );
            }
        }
    }
}
