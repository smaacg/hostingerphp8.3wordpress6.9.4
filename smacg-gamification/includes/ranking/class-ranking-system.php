<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * 排行榜核心（搬自 theme/inc/ranking-system.php）
 *
 * 提供 4 種排行榜：
 *   exp_total    - 累積 EXP
 *   exp_monthly  - 當月 EXP（依 wp_smacg_monthly_exp 表）
 *   followers    - 粉絲數（依 wp_smacg_follows 表）
 *   badges       - 徽章數（依 GamiPress earnings）
 *
 * Hook：
 *   smacg_exp_awarded → record_monthly_exp()
 */
class Ranking_System {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'smacg_exp_awarded', [ __CLASS__, 'record_monthly_exp' ], 10, 4 );
    }

    /* ==========================================================
     * 月累積：聽 smacg_exp_awarded 寫入 monthly_exp 表
     * ========================================================== */
    public static function record_monthly_exp( $uid, $amount, $reason = '', $args = [] ) {
        $uid    = (int) $uid;
        $amount = (int) $amount;
        if ( $uid <= 0 || $amount <= 0 ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'smacg_monthly_exp';
        $ym    = current_time( 'Ym' );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table} (user_id, ym, exp_amount, updated_at)
             VALUES (%d, %s, %d, NOW())
             ON DUPLICATE KEY UPDATE exp_amount = exp_amount + VALUES(exp_amount), updated_at = NOW()",
            $uid, $ym, $amount
        ) );
    }

    /* ==========================================================
     * 對外 API：取得排行榜資料（分頁）
     * ========================================================== */
    public static function get( $type, $page = 1, $per_page = 20 ) {
        if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) {
            return [ 'items' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page ];
        }
        $page     = max( 1, (int) $page );
        $per_page = max( 1, min( 100, (int) $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = $wpdb->prefix . 'smacg_rankings';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT rank_pos, user_id, score, extra
             FROM {$table}
             WHERE rank_type = %s
             ORDER BY rank_pos ASC
             LIMIT %d OFFSET %d",
            $type, $per_page, $offset
        ), ARRAY_A );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE rank_type = %s", $type
        ) );

        $items = [];
        foreach ( $rows as $r ) {
            $uid  = (int) $r['user_id'];
            $u    = get_userdata( $uid );
            $info = function_exists( 'smacg_get_user_level' ) ? smacg_get_user_level( $uid ) : null;
            $items[] = [
                'rank'         => (int) $r['rank_pos'],
                'user_id'      => $uid,
                'score'        => (int) $r['score'],
                'display_name' => $u ? $u->display_name : '(已刪除)',
                'avatar'       => get_avatar_url( $uid, [ 'size' => 96 ] ),
                'profile_url'  => function_exists( 'smacg_get_public_profile_url' ) ? smacg_get_public_profile_url( $uid ) : '',
                'level'        => $info['level'] ?? 1,
                'job_title'    => $info['job_title'] ?? '',
                'extra'        => $r['extra'] ? json_decode( $r['extra'], true ) : null,
            ];
        }

        return [ 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $per_page ];
    }

    /* ==========================================================
     * 取得單一用戶在某排行榜的名次（沒有則 null）
     * ========================================================== */
    public static function user_position( $type, $uid ) {
        if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) return null;
        $uid = (int) $uid;
        if ( $uid <= 0 ) return null;

        global $wpdb;
        $table = $wpdb->prefix . 'smacg_rankings';
        $pos   = $wpdb->get_var( $wpdb->prepare(
            "SELECT rank_pos FROM {$table} WHERE rank_type = %s AND user_id = %d",
            $type, $uid
        ) );
        return $pos ? (int) $pos : null;
    }

    /* ==========================================================
     * 重算排行榜（全量）
     * ========================================================== */
    public static function rebuild_all() {
        $count = 0;
        foreach ( SMACG_RANKING_TYPES as $type ) {
            $count += self::rebuild_type( $type );
        }
        update_option( 'smacg_ranking_last_rebuild', current_time( 'mysql' ) );
        return $count;
    }

    public static function rebuild_type( $type ) {
        if ( ! in_array( $type, SMACG_RANKING_TYPES, true ) ) return 0;

        global $wpdb;
        $table = $wpdb->prefix . 'smacg_rankings';
        $rows  = self::compute( $type, SMACG_RANKING_TOP_N );

        /* 清掉舊紀錄 */
        $wpdb->delete( $table, [ 'rank_type' => $type ], [ '%s' ] );

        $now      = current_time( 'mysql' );
        $inserted = 0;
        foreach ( $rows as $i => $r ) {
            $wpdb->insert( $table, [
                'rank_type'  => $type,
                'rank_pos'   => $i + 1,
                'user_id'    => (int) $r['user_id'],
                'score'      => (int) $r['score'],
                'extra'      => isset( $r['extra'] ) ? wp_json_encode( $r['extra'] ) : null,
                'updated_at' => $now,
            ], [ '%s', '%d', '%d', '%d', '%s', '%s' ] );
            $inserted++;
        }
        return $inserted;
    }

    /* ==========================================================
     * 計算單一排行榜（產生 [ ['user_id'=>, 'score'=>, 'extra'=>], ... ]）
     * ========================================================== */
    public static function compute( $type, $limit = 100 ) {
        global $wpdb;
        $limit       = (int) $limit;
        $excluded    = self::excluded_user_ids();
        $excluded_in = empty( $excluded ) ? '0' : implode( ',', array_map( 'intval', $excluded ) );

        switch ( $type ) {
            case 'exp_total':
                $sql = $wpdb->prepare( "
                    SELECT user_id, CAST(meta_value AS UNSIGNED) AS score
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = '_gamipress_" . esc_sql( SMACG_EXP_SLUG ) . "_points'
                      AND user_id NOT IN ($excluded_in)
                    ORDER BY score DESC
                    LIMIT %d
                ", $limit );
                $rows = $wpdb->get_results( $sql, ARRAY_A );
                return $rows ?: [];

            case 'exp_monthly':
                $ym  = current_time( 'Ym' );
                $tbl = $wpdb->prefix . 'smacg_monthly_exp';
                $sql = $wpdb->prepare( "
                    SELECT user_id, exp_amount AS score
                    FROM {$tbl}
                    WHERE ym = %s
                      AND user_id NOT IN ($excluded_in)
                    ORDER BY score DESC
                    LIMIT %d
                ", $ym, $limit );
                $rows = $wpdb->get_results( $sql, ARRAY_A );
                return $rows ?: [];

            case 'followers':
                $tbl = $wpdb->prefix . 'smacg_follows';
                if ( $wpdb->get_var( "SHOW TABLES LIKE '$tbl'" ) !== $tbl ) return [];
                $sql = $wpdb->prepare( "
                    SELECT followee_id AS user_id, COUNT(*) AS score
                    FROM {$tbl}
                    WHERE followee_id NOT IN ($excluded_in)
                    GROUP BY followee_id
                    ORDER BY score DESC
                    LIMIT %d
                ", $limit );
                $rows = $wpdb->get_results( $sql, ARRAY_A );
                return $rows ?: [];

            case 'badges':
                $sql = $wpdb->prepare( "
                    SELECT user_id, COUNT(*) AS score
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = '_gamipress_achievements'
                      AND user_id NOT IN ($excluded_in)
                    GROUP BY user_id
                    ORDER BY score DESC
                    LIMIT %d
                ", $limit );
                /* 註：GamiPress 的徽章紀錄不在 usermeta，而在 wp_gamipress_user_earnings；
                 *    如果該表存在則優先使用它。 */
                $earnings_tbl = $wpdb->prefix . 'gamipress_user_earnings';
                if ( $wpdb->get_var( "SHOW TABLES LIKE '$earnings_tbl'" ) === $earnings_tbl ) {
                    $sql = $wpdb->prepare( "
                        SELECT user_id, COUNT(*) AS score
                        FROM {$earnings_tbl}
                        WHERE post_type = %s
                          AND user_id NOT IN ($excluded_in)
                        GROUP BY user_id
                        ORDER BY score DESC
                        LIMIT %d
                    ", SMACG_BADGE_SLUG, $limit );
                }
                $rows = $wpdb->get_results( $sql, ARRAY_A );
                return $rows ?: [];
        }
        return [];
    }

    /* ==========================================================
     * 隱私：被排除的用戶 ID（user_meta = '0'）
     * ========================================================== */
    public static function excluded_user_ids() {
        global $wpdb;
        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '0'",
            SMACG_RANKING_META_KEY
        ) );
        return array_map( 'intval', $rows ?: [] );
    }

    /* ==========================================================
     * 清理：刪除 N 個月前的 monthly_exp 列
     * ========================================================== */
    public static function purge_old_monthly( $keep_months = 13 ) {
        global $wpdb;
        $tbl    = $wpdb->prefix . 'smacg_monthly_exp';
        $cutoff = date( 'Ym', strtotime( "-{$keep_months} months", current_time( 'timestamp' ) ) );

        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$tbl} WHERE ym < %s", $cutoff
        ) );
        return (int) $deleted;
    }
}
