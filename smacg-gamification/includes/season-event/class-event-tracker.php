<?php
/**
 * Season Event Tracker - 進度追蹤
 *
 * 原檔：blocksy-child/inc/season-event-tracker.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\SeasonEvent;

defined( 'ABSPATH' ) || exit;

class Tracker {

    public static function init() {
        // 事件監聽
        add_action( 'smacg_exp_awarded',         [ __CLASS__, 'on_exp_awarded' ], 30, 3 );
        add_action( 'smacg_watchlist_completed', [ __CLASS__, 'on_watchlist_completed' ], 30, 2 );
        add_action( 'comment_post',              [ __CLASS__, 'on_comment_post' ], 30, 2 );
        add_action( 'smacg_rating_added',        [ __CLASS__, 'on_rating_added' ], 30, 3 );
    }

    /* ---------------------------------------------
     * 取得目前進行中且任務類型符合的活動 ID（含 request-level 快取）
     * --------------------------------------------- */
    public static function active_ids_by_task( $task_type ) {
        static $cache = [];
        if ( isset( $cache[ $task_type ] ) ) return $cache[ $task_type ];

        global $wpdb;
        $now = current_time( 'mysql' );

        $sql = $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} mt ON mt.post_id = p.ID AND mt.meta_key = '_smacg_event_task_type'
             INNER JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = '_smacg_event_start'
             INNER JOIN {$wpdb->postmeta} me ON me.post_id = p.ID AND me.meta_key = '_smacg_event_end'
             WHERE p.post_type = %s
               AND p.post_status = 'publish'
               AND mt.meta_value = %s
               AND ms.meta_value <= %s
               AND me.meta_value >= %s",
            SMACG_EVENT_CPT, $task_type, $now, $now
        );

        $ids = $wpdb->get_col( $sql );
        $cache[ $task_type ] = array_map( 'intval', $ids ?: [] );
        return $cache[ $task_type ];
    }

    /* ---------------------------------------------
     * 對使用者所有符合的進行中活動，遞增進度
     * --------------------------------------------- */
    public static function bump_progress( $uid, $task_type, $delta = 1 ) {
        $uid   = (int) $uid;
        $delta = (int) $delta;
        if ( $uid <= 0 || $delta <= 0 ) return;

        $event_ids = self::active_ids_by_task( $task_type );
        if ( empty( $event_ids ) ) return;

        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';
        $now = current_time( 'mysql' );

        foreach ( $event_ids as $eid ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$tbl} (event_id, user_id, progress, updated_at)
                 VALUES (%d, %d, %d, %s)
                 ON DUPLICATE KEY UPDATE progress = progress + VALUES(progress), updated_at = VALUES(updated_at)",
                $eid, $uid, $delta, $now
            ) );
            self::check_reached( $eid, $uid );
        }
    }

    /* ---------------------------------------------
     * 偵測達標
     * --------------------------------------------- */
    public static function check_reached( $event_id, $uid ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT progress, reached_at FROM {$tbl} WHERE event_id = %d AND user_id = %d",
            $event_id, $uid
        ) );
        if ( ! $row || ! is_null( $row->reached_at ) ) return false;

        $meta = CPT::get_meta( $event_id );
        if ( $meta['task_target'] <= 0 ) return false;
        if ( (int) $row->progress < (int) $meta['task_target'] ) return false;

        // 檢查名額
        if ( $meta['max_participants'] > 0 ) {
            $reached_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tbl} WHERE event_id = %d AND reached_at IS NOT NULL",
                $event_id
            ) );
            if ( $reached_count >= $meta['max_participants'] ) {
                // 標記為超額（reached_at = 1970-01-01）
                $wpdb->update( $tbl,
                    [ 'reached_at' => '1970-01-01 00:00:00' ],
                    [ 'event_id' => $event_id, 'user_id' => $uid ],
                    [ '%s' ], [ '%d', '%d' ]
                );
                do_action( 'smacg_event_over_limit', $event_id, $uid );
                return false;
            }
        }

        // 正式達標
        $wpdb->update( $tbl,
            [ 'reached_at' => current_time( 'mysql' ) ],
            [ 'event_id' => $event_id, 'user_id' => $uid ],
            [ '%s' ], [ '%d', '%d' ]
        );

        do_action( 'smacg_event_reached', $event_id, $uid, $meta );
        return true;
    }

    /* ---------------------------------------------
     * Hook handlers
     * --------------------------------------------- */
    public static function on_exp_awarded( $uid, $amount, $reason = '' ) {
        self::bump_progress( $uid, 'exp_gain', (int) $amount );
    }

    public static function on_watchlist_completed( $uid, $anime_id ) {
        self::bump_progress( $uid, 'watchlist_completed', 1 );
    }

    public static function on_comment_post( $comment_id, $approved ) {
        if ( $approved !== 1 && $approved !== '1' ) return;
        $c = get_comment( $comment_id );
        if ( ! $c || empty( $c->user_id ) ) return;
        self::bump_progress( (int) $c->user_id, 'comment_count', 1 );
    }

    public static function on_rating_added( $uid, $anime_id, $rating ) {
        self::bump_progress( $uid, 'rating_count', 1 );
    }

    /* ---------------------------------------------
     * 公開 API
     * --------------------------------------------- */
    public static function get_user_progress( $event_id, $uid ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT progress, reached_at, awarded_at
             FROM {$tbl} WHERE event_id = %d AND user_id = %d",
            $event_id, $uid
        ), ARRAY_A );

        $meta = CPT::get_meta( $event_id );
        $tgt  = max( 1, (int) $meta['task_target'] );

        $progress = $row ? (int) $row['progress'] : 0;
        $reached  = $row && $row['reached_at'] && $row['reached_at'] !== '1970-01-01 00:00:00' ? $row['reached_at'] : null;
        $over     = $row && $row['reached_at'] === '1970-01-01 00:00:00';

        return [
            'progress'   => $progress,
            'target'     => $tgt,
            'percent'    => min( 100, round( $progress / $tgt * 100, 1 ) ),
            'reached_at' => $reached,
            'awarded_at' => $row ? ( $row['awarded_at'] ?: null ) : null,
            'over_limit' => $over,
        ];
    }

    public static function top_progress( $event_id, $limit = 100 ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, progress, reached_at, awarded_at
             FROM {$tbl}
             WHERE event_id = %d
               AND (reached_at IS NULL OR reached_at != '1970-01-01 00:00:00')
             ORDER BY progress DESC, reached_at ASC
             LIMIT %d",
            $event_id, max( 1, (int) $limit )
        ), ARRAY_A ) ?: [];
    }

    public static function counts( $event_id ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';

        $total   = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl} WHERE event_id = %d AND (reached_at IS NULL OR reached_at != '1970-01-01 00:00:00')",
            $event_id
        ) );
        $reached = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl} WHERE event_id = %d AND reached_at IS NOT NULL AND reached_at != '1970-01-01 00:00:00'",
            $event_id
        ) );
        return [ 'total' => $total, 'reached' => $reached ];
    }

    public static function manual_grant_progress( $event_id, $uid, $delta = 1 ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';
        $now = current_time( 'mysql' );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$tbl} (event_id, user_id, progress, updated_at)
             VALUES (%d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE progress = progress + VALUES(progress), updated_at = VALUES(updated_at)",
            $event_id, $uid, max( 1, (int) $delta ), $now
        ) );
        self::check_reached( $event_id, $uid );
    }
}

Tracker::init();
