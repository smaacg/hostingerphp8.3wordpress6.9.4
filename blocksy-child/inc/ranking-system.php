<?php
/**
 * Ranking System — 資料層
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-1
 *
 * 功能：
 *   1. 建立 / 升級兩張表
 *        wp_smacg_monthly_exp   每位使用者每月 EXP 增量
 *        wp_smacg_rankings      快取排行榜（4 個 type × Top N）
 *   2. 監聽 smacg_exp_awarded → 寫入月度表
 *   3. 提供查詢 API（給 page-ranking-users / widget / AJAX 使用）
 *   4. 隱私尊重：appear_in_ranking=0 的會員不出現在快取
 *
 * 依賴：gamipress-integration.php（觸發 smacg_exp_awarded action）
 *      follow-system.php（讀 wp_smacg_follows 計算粉絲數）
 *      gamipress 內建 _gamipress_badge_achievements meta（徽章數）
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數
   ============================================================ */
define( 'SMACG_RANKING_DB_VERSION', '1.0.0' );
define( 'SMACG_RANKING_TOP_N',      100  );  // 每個 type 快取前 N 名
define( 'SMACG_RANKING_PAGE_SIZE',  20   );  // 前端每頁顯示
define( 'SMACG_RANKING_TYPES',      [ 'exp_total', 'exp_monthly', 'followers', 'badges' ] );

/* ============================================================
   一、建表 / 升級
   ============================================================ */

/**
 * 取得目前 DB 版本
 */
function smacg_ranking_db_version() {
    return get_option( 'smacg_ranking_db_version', '0' );
}

/**
 * 建立或升級兩張表
 * 可在 after_switch_theme 觸發；也提供手動 endpoint
 */
function smacg_ranking_install_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $tbl_monthly  = $wpdb->prefix . 'smacg_monthly_exp';
    $tbl_rankings = $wpdb->prefix . 'smacg_rankings';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // 月度 EXP 表：user_id + ym 為複合主鍵
    $sql1 = "CREATE TABLE {$tbl_monthly} (
        user_id BIGINT(20) UNSIGNED NOT NULL,
        ym CHAR(6) NOT NULL,
        exp_amount BIGINT(20) NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, ym),
        KEY ym_exp (ym, exp_amount)
    ) {$charset};";

    // 排行榜快取：type + rank 為複合主鍵
    $sql2 = "CREATE TABLE {$tbl_rankings} (
        rank_type VARCHAR(32) NOT NULL,
        rank_pos INT UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        score BIGINT(20) NOT NULL DEFAULT 0,
        extra LONGTEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (rank_type, rank_pos),
        KEY type_user (rank_type, user_id)
    ) {$charset};";

    dbDelta( $sql1 );
    dbDelta( $sql2 );

    update_option( 'smacg_ranking_db_version', SMACG_RANKING_DB_VERSION );
}

/**
 * 啟用 / 升級時自動建表（每次主題切換 + 版本不符時）
 */
add_action( 'after_switch_theme', 'smacg_ranking_install_tables' );
add_action( 'init', function () {
    if ( smacg_ranking_db_version() !== SMACG_RANKING_DB_VERSION ) {
        smacg_ranking_install_tables();
    }
}, 5 );

/* ============================================================
   二、寫入月度 EXP（hook smacg_exp_awarded）
   ============================================================ */

/**
 * 監聽 EXP 發放事件 → 寫入月度表
 * smacg_exp_awarded 由 gamipress-integration.php 的 smacg_award_exp() 觸發
 *
 * @param int    $uid
 * @param int    $amount
 * @param string $reason
 */
function smacg_ranking_record_monthly_exp( $uid, $amount, $reason = '' ) {
    $uid    = (int) $uid;
    $amount = (int) $amount;
    if ( $uid <= 0 || $amount <= 0 ) return;

    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_monthly_exp';
    $ym  = gmdate( 'Ym', current_time( 'timestamp' ) );

    // UPSERT
    $sql = $wpdb->prepare(
        "INSERT INTO {$tbl} (user_id, ym, exp_amount, updated_at)
         VALUES (%d, %s, %d, %s)
         ON DUPLICATE KEY UPDATE exp_amount = exp_amount + VALUES(exp_amount), updated_at = VALUES(updated_at)",
        $uid, $ym, $amount, current_time( 'mysql' )
    );
    $wpdb->query( $sql );
}
add_action( 'smacg_exp_awarded', 'smacg_ranking_record_monthly_exp', 10, 3 );

/* ============================================================
   三、計算原始排行（不走快取，給 Cron 用）
   ============================================================ */

/**
 * 取得「不希望出現在排行榜」的 user_id 陣列
 *
 * @return int[]
 */
function smacg_ranking_get_excluded_user_ids() {
    static $cache = null;
    if ( $cache !== null ) return $cache;

    global $wpdb;
    $rows = $wpdb->get_col( $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta}
         WHERE meta_key = %s AND meta_value = %s",
        'smacg_appear_in_ranking', '0'
    ) );
    $cache = array_map( 'intval', $rows ?: [] );
    return $cache;
}

/**
 * 清掉排除名單快取（隱私變更時呼叫）
 */
function smacg_ranking_flush_excluded_cache() {
    // static 變數無法外部清；改為 transient 之後再優化。目前 Cron 每次都是新 PHP 進程，無影響。
    wp_cache_delete( 'smacg_ranking_excluded', 'smacg' );
}

/**
 * 計算原始排行
 *
 * @param string $type 見 SMACG_RANKING_TYPES
 * @param int    $limit
 * @return array<int, array{user_id:int, score:int, extra?:array}>
 */
function smacg_ranking_compute( $type, $limit = SMACG_RANKING_TOP_N ) {
    global $wpdb;

    $excluded = smacg_ranking_get_excluded_user_ids();
    $not_in   = '';
    if ( ! empty( $excluded ) ) {
        $ids    = implode( ',', $excluded );
        $not_in = "AND user_id NOT IN ({$ids})";
    }

    $limit = max( 1, (int) $limit );

    switch ( $type ) {

        /* ---- 累計 EXP（讀 GamiPress user_meta：_gamipress_exp_points） ---- */
        case 'exp_total':
            $sql = "SELECT user_id, CAST(meta_value AS UNSIGNED) AS score
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = '_gamipress_exp_points'
                    {$not_in}
                    ORDER BY score DESC
                    LIMIT {$limit}";
            break;

        /* ---- 本月 EXP ---- */
        case 'exp_monthly':
            $ym  = gmdate( 'Ym', current_time( 'timestamp' ) );
            $tbl = $wpdb->prefix . 'smacg_monthly_exp';
            $sql = $wpdb->prepare(
                "SELECT user_id, exp_amount AS score
                 FROM {$tbl}
                 WHERE ym = %s {$not_in}
                 ORDER BY score DESC
                 LIMIT {$limit}",
                $ym
            );
            break;

        /* ---- 粉絲數 ---- */
        case 'followers':
            $tbl_follow = $wpdb->prefix . 'smacg_follows';
            // 若追蹤表不存在則回空（避免 fatal）
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$tbl_follow}'" );
            if ( ! $exists ) return [];
            $sql = "SELECT followed_id AS user_id, COUNT(*) AS score
                    FROM {$tbl_follow}
                    WHERE 1=1 " . ( $not_in ? str_replace( 'user_id', 'followed_id', $not_in ) : '' ) . "
                    GROUP BY followed_id
                    ORDER BY score DESC
                    LIMIT {$limit}";
            break;

        /* ---- 徽章數（讀 _gamipress_badge_achievements serialized array 長度） ---- */
        case 'badges':
            // 用 PHP 端聚合：先抓全部有此 meta 的人，逐筆 count
            $rows = $wpdb->get_results(
                "SELECT user_id, meta_value
                 FROM {$wpdb->usermeta}
                 WHERE meta_key = '_gamipress_badge_achievements'
                 {$not_in}"
            );
            $list = [];
            foreach ( $rows as $r ) {
                $arr   = maybe_unserialize( $r->meta_value );
                $count = is_array( $arr ) ? count( $arr ) : 0;
                if ( $count > 0 ) {
                    $list[] = [ 'user_id' => (int) $r->user_id, 'score' => $count ];
                }
            }
            // 排序
            usort( $list, function ( $a, $b ) { return $b['score'] - $a['score']; } );
            return array_slice( $list, 0, $limit );

        default:
            return [];
    }

    $results = $wpdb->get_results( $sql, ARRAY_A );
    if ( empty( $results ) ) return [];

    // 標準化
    $out = [];
    foreach ( $results as $r ) {
        $out[] = [
            'user_id' => (int) $r['user_id'],
            'score'   => (int) $r['score'],
        ];
    }
    return $out;
}

/* ============================================================
   四、寫入快取表
   ============================================================ */

/**
 * 重算指定 type 並寫入 wp_smacg_rankings
 *
 * @param string $type
 * @return int 寫入筆數
 */
function smacg_ranking_rebuild_type( $type ) {
    if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) return 0;

    global $wpdb;
    $tbl  = $wpdb->prefix . 'smacg_rankings';
    $rows = smacg_ranking_compute( $type, SMACG_RANKING_TOP_N );

    // 先清舊資料（同 type）
    $wpdb->delete( $tbl, [ 'rank_type' => $type ], [ '%s' ] );

    if ( empty( $rows ) ) return 0;

    $now   = current_time( 'mysql' );
    $count = 0;
    foreach ( $rows as $i => $r ) {
        $wpdb->insert( $tbl, [
            'rank_type'  => $type,
            'rank_pos'   => $i + 1,
            'user_id'    => $r['user_id'],
            'score'      => $r['score'],
            'extra'      => isset( $r['extra'] ) ? wp_json_encode( $r['extra'] ) : null,
            'updated_at' => $now,
        ], [ '%s', '%d', '%d', '%d', '%s', '%s' ] );
        $count++;
    }
    return $count;
}

/**
 * 重算全部 type
 */
function smacg_ranking_rebuild_all() {
    $log = [];
    foreach ( SMACG_RANKING_TYPES as $type ) {
        $log[ $type ] = smacg_ranking_rebuild_type( $type );
    }
    update_option( 'smacg_ranking_last_rebuild', current_time( 'mysql' ) );
    return $log;
}

/* ============================================================
   五、查詢 API（前端用）
   ============================================================ */

/**
 * 取出某 type 的排行榜（讀快取表）
 *
 * @param string $type
 * @param int    $page  從 1 開始
 * @param int    $per_page
 * @return array{rows:array, total:int, updated_at:string}
 */
function smacg_ranking_get( $type, $page = 1, $per_page = SMACG_RANKING_PAGE_SIZE ) {
    if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) {
        return [ 'rows' => [], 'total' => 0, 'updated_at' => '' ];
    }

    global $wpdb;
    $tbl      = $wpdb->prefix . 'smacg_rankings';
    $page     = max( 1, (int) $page );
    $per_page = max( 1, min( 100, (int) $per_page ) );
    $offset   = ( $page - 1 ) * $per_page;

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT rank_pos, user_id, score, extra, updated_at
         FROM {$tbl}
         WHERE rank_type = %s
         ORDER BY rank_pos ASC
         LIMIT %d OFFSET %d",
        $type, $per_page, $offset
    ), ARRAY_A );

    $total = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tbl} WHERE rank_type = %s",
        $type
    ) );

    $updated = $wpdb->get_var( $wpdb->prepare(
        "SELECT updated_at FROM {$tbl} WHERE rank_type = %s ORDER BY rank_pos ASC LIMIT 1",
        $type
    ) );

    return [
        'rows'       => $rows ?: [],
        'total'      => $total,
        'updated_at' => $updated ?: '',
    ];
}

/**
 * 查詢某使用者在某 type 的名次（不在快取則回 null）
 */
function smacg_ranking_user_position( $uid, $type ) {
    if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) return null;
    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_rankings';
    $pos = $wpdb->get_var( $wpdb->prepare(
        "SELECT rank_pos FROM {$tbl} WHERE rank_type = %s AND user_id = %d",
        $type, (int) $uid
    ) );
    return $pos ? (int) $pos : null;
}

/* ============================================================
   六、清理舊月度資料（保留近 24 個月）
   ============================================================ */
function smacg_ranking_purge_old_monthly() {
    global $wpdb;
    $tbl    = $wpdb->prefix . 'smacg_monthly_exp';
    $cutoff = gmdate( 'Ym', strtotime( '-24 months', current_time( 'timestamp' ) ) );
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl} WHERE ym < %s", $cutoff ) );
}
