<?php
/**
 * Season Event Tracker — 進度追蹤
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-5
 *
 * 負責：
 *   1. 建立 / 升級進度表 wp_smacg_event_progress
 *   2. 監聽 EXP/留言/評分/追蹤/觀看 等事件，遞增使用者在進行中活動的進度
 *   3. 偵測達標 → 標記 reached_at（實際發獎勵由 season-event-settle 處理，避免結算搶名額時跑兩次）
 *   4. 提供查詢 API：使用者在某活動的進度、活動的 Top N 進度榜、報名者人數
 *
 * Schema：wp_smacg_event_progress
 *   event_id     BIGINT
 *   user_id      BIGINT
 *   progress     BIGINT   累計進度
 *   reached_at   DATETIME NULL  達標時間（NULL = 未達標）
 *   awarded_at   DATETIME NULL  發獎時間（NULL = 尚未發獎）
 *   updated_at   DATETIME
 *   PRIMARY KEY  (event_id, user_id)
 *   KEY          event_progress (event_id, progress DESC)
 *   KEY          event_reached  (event_id, reached_at)
 *
 * 注意：用「自動加入」模式 — 第一次有相關事件就建立 row，不用顯式報名。
 *       若 max_participants > 0，達標時才檢查是否還有名額。
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數 + 建表
   ============================================================ */
const SMACG_EVENT_DB_VERSION = '1.0.0';

function smacg_event_install_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $tbl     = $wpdb->prefix . 'smacg_event_progress';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$tbl} (
        event_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        progress BIGINT(20) NOT NULL DEFAULT 0,
        reached_at DATETIME NULL DEFAULT NULL,
        awarded_at DATETIME NULL DEFAULT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (event_id, user_id),
        KEY event_progress (event_id, progress),
        KEY event_reached (event_id, reached_at)
    ) {$charset};";

    dbDelta( $sql );
    update_option( 'smacg_event_db_version', SMACG_EVENT_DB_VERSION );
}

add_action( 'after_switch_theme', 'smacg_event_install_tables' );
add_action( 'init', function () {
    if ( get_option( 'smacg_event_db_version' ) !== SMACG_EVENT_DB_VERSION ) {
        smacg_event_install_tables();
    }
}, 6 );

/* ============================================================
   核心：對指定 task_type 找出進行中活動 + 遞增進度
   ============================================================ */

/**
 * 取得目前進行中、且任務類型符合的活動 ID 陣列（含快取）
 *
 * @param string $task_type
 * @return int[]
 */
function smacg_event_active_ids_by_task( $task_type ) {
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

/**
 * 對指定使用者，於所有符合的進行中活動，遞增進度
 *
 * @param int    $uid
 * @param string $task_type
 * @param int    $delta      預設 1
 */
function smacg_event_bump_progress( $uid, $task_type, $delta = 1 ) {
    $uid   = (int) $uid;
    $delta = (int) $delta;
    if ( $uid <= 0 || $delta <= 0 ) return;

    $event_ids = smacg_event_active_ids_by_task( $task_type );
    if ( empty( $event_ids ) ) return;

    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_event_progress';
    $now = current_time( 'mysql' );

    foreach ( $event_ids as $eid ) {

        // UPSERT 進度
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$tbl} (event_id, user_id, progress, updated_at)
             VALUES (%d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE progress = progress + VALUES(progress), updated_at = VALUES(updated_at)",
            $eid, $uid, $delta, $now
        ) );

        // 偵測是否剛達標
        smacg_event_check_reached( $eid, $uid );
    }
}

/**
 * 檢查使用者是否剛達標；若是，標記 reached_at
 * 注意：實際發獎勵由 Cron / 結束時統一處理（season-event-settle.php）
 * 但若想「先到先得」可在此立即發 — 由 max_participants 控制名額
 */
function smacg_event_check_reached( $event_id, $uid ) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_event_progress';

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT progress, reached_at FROM {$tbl} WHERE event_id = %d AND user_id = %d",
        $event_id, $uid
    ) );
    if ( ! $row || ! is_null( $row->reached_at ) ) return false; // 已達標或無紀錄

    $meta = smacg_get_event_meta( $event_id );
    if ( $meta['task_target'] <= 0 ) return false;
    if ( (int) $row->progress < (int) $meta['task_target'] ) return false;

    // 檢查名額（若有設限）
    if ( $meta['max_participants'] > 0 ) {
        $reached_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl} WHERE event_id = %d AND reached_at IS NOT NULL",
            $event_id
        ) );
        if ( $reached_count >= $meta['max_participants'] ) {
            // 已滿額，標記為 over_limit（用 awarded_at 留 NULL，reached_at 設特殊值）
            // 設定 reached_at 為 1970，後續 settle 可忽略
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

    /**
     * 觸發 smacg_event_reached
     * 由 season-event-settle.php 監聽 → 立即發獎（先達先得效果）
     */
    do_action( 'smacg_event_reached', $event_id, $uid, $meta );
    return true;
}

/* ============================================================
   事件監聽 — 將外部事件轉換為進度
   ============================================================ */

/**
 * EXP 累積 → exp_gain
 * smacg_exp_awarded 由 gamipress-integration 的 smacg_award_exp 觸發
 *   參數：(uid, amount, reason)
 */
add_action( 'smacg_exp_awarded', function ( $uid, $amount, $reason = '' ) {
    smacg_event_bump_progress( $uid, 'exp_gain', (int) $amount );
}, 30, 3 );

/**
 * 觀看完成 → watchlist_completed
 */
add_action( 'smacg_watchlist_completed', function ( $uid, $anime_id ) {
    smacg_event_bump_progress( $uid, 'watchlist_completed', 1 );
}, 30, 2 );

/**
 * 留言 → comment_count
 */
add_action( 'comment_post', function ( $comment_id, $approved ) {
    if ( $approved !== 1 && $approved !== '1' ) return;
    $c = get_comment( $comment_id );
    if ( ! $c || empty( $c->user_id ) ) return;
    smacg_event_bump_progress( (int) $c->user_id, 'comment_count', 1 );
}, 30, 2 );

/**
 * 評分 → rating_count
 */
add_action( 'smacg_rating_added', function ( $uid, $anime_id, $rating ) {
    smacg_event_bump_progress( $uid, 'rating_count', 1 );
}, 30, 3 );

/**
 * manual 任務不自動觸發，由 admin 工具呼叫 smacg_event_manual_grant_progress()
 */

/* ============================================================
   公開 API
   ============================================================ */

/**
 * 取得使用者在某活動的進度
 *
 * @return array{
 *   progress:int, target:int, percent:float,
 *   reached_at:?string, awarded_at:?string,
 *   over_limit:bool
 * }
 */
function smacg_event_get_user_progress( $event_id, $uid ) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_event_progress';

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT progress, reached_at, awarded_at
         FROM {$tbl} WHERE event_id = %d AND user_id = %d",
        $event_id, $uid
    ), ARRAY_A );

    $meta = smacg_get_event_meta( $event_id );
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

/**
 * 取某活動的 Top N 進度榜（不含 over_limit）
 */
function smacg_event_top_progress( $event_id, $limit = 100 ) {
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

/**
 * 取某活動的達標人數 / 報名人數
 */
function smacg_event_counts( $event_id ) {
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

/**
 * 手動為使用者增加進度（manual 任務 / admin 工具用）
 */
function smacg_event_manual_grant_progress( $event_id, $uid, $delta = 1 ) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_event_progress';
    $now = current_time( 'mysql' );

    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$tbl} (event_id, user_id, progress, updated_at)
         VALUES (%d, %d, %d, %s)
         ON DUPLICATE KEY UPDATE progress = progress + VALUES(progress), updated_at = VALUES(updated_at)",
        $event_id, $uid, max( 1, (int) $delta ), $now
    ) );
    smacg_event_check_reached( $event_id, $uid );
}
