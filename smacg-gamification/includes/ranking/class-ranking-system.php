<?php
/**
 * Ranking System - 資料層
 *
 * 原檔：blocksy-child/inc/ranking-system.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Ranking;

defined( 'ABSPATH' ) || exit;

class System {

    public static function init() {
        // 監聽 EXP 發放 → 寫入月度
        add_action( 'smacg_exp_awarded', [ __CLASS__, 'record_monthly_exp' ], 10, 3 );
    }

    /* ----------------------------------------------
     * 月度 EXP 記錄（UPSERT）
     * ---------------------------------------------- */
    public static function record_monthly_exp( $uid, $amount, $reason = '' ) {
        $uid    = (int) $uid;
        $amount = (int) $amount;
        if ( $uid <= 0 || $amount <= 0 ) return;

        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_monthly_exp';
        $ym  = gmdate( 'Ym', current_time( 'timestamp' ) );

        $sql = $wpdb->prepare(
            "INSERT INTO {$tbl} (user_id, ym, exp_amount, updated_at)
             VALUES (%d, %s, %d, %s)
             ON DUPLICATE KEY UPDATE exp_amount = exp_amount + VALUES(exp_amount), updated_at = VALUES(updated_at)",
            $uid, $ym, $amount, current_time( 'mysql' )
        );
        $wpdb->query( $sql );
    }

    /* ----------------------------------------------
     * 排除名單
     * ---------------------------------------------- */
    public static function get_excluded_user_ids() {
        static $cache = null;
        if ( $cache !== null ) return $cache;

        global $wpdb;
        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
             WHERE meta_key = %s AND meta_value = %s",
            SMACG_RANKING_META_KEY, '0'
        ) );
        $cache = array_map( 'intval', $rows ?: [] );
        return $cache;
    }

    public static function flush_excluded_cache() {
        wp_cache_delete( 'smacg_ranking_excluded', 'smacg' );
    }

    /* ----------------------------------------------
     * 原始計算
     * ---------------------------------------------- */
    public static function compute( $type, $limit = SMACG_RANKING_TOP_N ) {
        global $wpdb;

        $excluded = self::get_excluded_user_ids();
        $not_in   = '';
        if ( ! empty( $excluded ) ) {
            $ids    = implode( ',', $excluded );
            $not_in = "AND user_id NOT IN ({$ids})";
        }

        $limit = max( 1, (int) $limit );

        switch ( $type ) {

            case 'exp_total':
                $sql = "SELECT user_id, CAST(meta_value AS UNSIGNED) AS score
                        FROM {$wpdb->usermeta}
                        WHERE meta_key = '_gamipress_exp_points'
                        {$not_in}
                        ORDER BY score DESC
                        LIMIT {$limit}";
                break;

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

            case 'followers':
                $tbl_follow = $wpdb->prefix . 'smacg_follows';
                $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$tbl_follow}'" );
                if ( ! $exists ) return [];
                $sql = "SELECT followed_id AS user_id, COUNT(*) AS score
                        FROM {$tbl_follow}
                        WHERE 1=1 " . ( $not_in ? str_replace( 'user_id', 'followed_id', $not_in ) : '' ) . "
                        GROUP BY followed_id
                        ORDER BY score DESC
                        LIMIT {$limit}";
                break;

            case 'badges':
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
                usort( $list, function ( $a, $b ) { return $b['score'] - $a['score']; } );
                return array_slice( $list, 0, $limit );

            default:
                return [];
        }

        $results = $wpdb->get_results( $sql, ARRAY_A );
        if ( empty( $results ) ) return [];

        $out = [];
        foreach ( $results as $r ) {
            $out[] = [
                'user_id' => (int) $r['user_id'],
                'score'   => (int) $r['score'],
            ];
        }
        return $out;
    }

    /* ----------------------------------------------
     * 寫入快取表
     * ---------------------------------------------- */
    public static function rebuild_type( $type ) {
        if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) return 0;

        global $wpdb;
        $tbl  = $wpdb->prefix . 'smacg_rankings';
        $rows = self::compute( $type, SMACG_RANKING_TOP_N );

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

    public static function rebuild_all() {
        $log = [];
        foreach ( SMACG_RANKING_TYPES as $type ) {
            $log[ $type ] = self::rebuild_type( $type );
        }
        update_option( 'smacg_ranking_last_rebuild', current_time( 'mysql' ) );
        return $log;
    }

    /* ----------------------------------------------
     * 查詢 API
     * ---------------------------------------------- */
    public static function get( $type, $page = 1, $per_page = SMACG_RANKING_PAGE_SIZE ) {
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

    public static function user_position( $uid, $type ) {
        if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) return null;
        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_rankings';
        $pos = $wpdb->get_var( $wpdb->prepare(
            "SELECT rank_pos FROM {$tbl} WHERE rank_type = %s AND user_id = %d",
            $type, (int) $uid
        ) );
        return $pos ? (int) $pos : null;
    }

    /* ----------------------------------------------
     * 清理舊月度資料
     * ---------------------------------------------- */
    public static function purge_old_monthly() {
        global $wpdb;
        $tbl    = $wpdb->prefix . 'smacg_monthly_exp';
        $cutoff = gmdate( 'Ym', strtotime( '-24 months', current_time( 'timestamp' ) ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl} WHERE ym < %s", $cutoff ) );
    }
}

System::init();
