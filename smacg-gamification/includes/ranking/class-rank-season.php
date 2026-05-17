<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Rank Season — 賽季積分累積 + 自動換季結算
 *
 * 資料表：wp_smacg_rank_season（當季）/ wp_smacg_rank_season_archive（歷季）
 *
 * 流程：
 *   1. EXP 觸發 → Exp_Events 呼叫 self::add_score()
 *   2. 寫入 wp_smacg_rank_season（user_id + season_code 為 PK）
 *   3. cron smacg_rank_season_check（每小時）檢查當前 season_code
 *      與 option smacg_rank_current_season 不一致 → 觸發結算
 *
 * v1.1.0 (2026-05-17)
 *   - 新增 get_last_settled_season_code() : 取得 archive 內最近一個結算過的賽季 code
 *   - 新增 get_archive_leaderboard()      : 讀取上季 / 任意已結算賽季的 Top N 排行
 *   - 新增 get_archive_user_position()    : 查單一使用者在上季的最終名次
 *   - 新增 archive_total()                : 查上季 archive 總人數（給 pagination 用）
 */
class Rank_Season {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'smacg_rank_season_check', [ __CLASS__, 'check_rollover' ] );

        // 註冊 cron（若 Activator 漏排）
        if ( ! wp_next_scheduled( 'smacg_rank_season_check' ) ) {
            wp_schedule_event( time() + 300, 'hourly', 'smacg_rank_season_check' );
        }
    }

    /* ==========================================================
     * Table accessors
     * ========================================================== */
    public static function table_current() {
        global $wpdb;
        return $wpdb->prefix . 'smacg_rank_season';
    }
    public static function table_archive() {
        global $wpdb;
        return $wpdb->prefix . 'smacg_rank_season_archive';
    }

    /* ==========================================================
     * 寫入分數（由 Exp_Events 呼叫）
     * ========================================================== */
    public static function add_score( $uid, $amount ) {
        global $wpdb;
        $uid    = (int) $uid;
        $amount = (int) $amount;
        if ( $uid <= 0 || $amount <= 0 ) return false;

        $code = Rank_Tier::current_season_code();
        $tbl  = self::table_current();

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$tbl} (user_id, season_code, season_score, updated_at)
             VALUES (%d, %s, %d, %s)
             ON DUPLICATE KEY UPDATE
                season_score = season_score + VALUES(season_score),
                updated_at   = VALUES(updated_at)",
            $uid, $code, $amount, current_time( 'mysql' )
        ) );
        return true;
    }

    /* ==========================================================
     * 讀取：個人賽季積分 + 名次 + 段位（當季）
     * ========================================================== */
    public static function get_user_info( $uid, $season_code = null ) {
        global $wpdb;
        $uid  = (int) $uid;
        $code = $season_code ?: Rank_Tier::current_season_code();
        $tbl  = self::table_current();

        $score = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT season_score FROM {$tbl} WHERE user_id=%d AND season_code=%s",
            $uid, $code
        ) );

        $rank = $score > 0 ? (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)+1 FROM {$tbl}
             WHERE season_code=%s AND season_score > %d",
            $code, $score
        ) ) : 0;

        $tier     = Rank_Tier::tier_from_score_and_rank( $score, $rank );
        $progress = Rank_Tier::progress_to_next( $score );

        return [
            'season_code'  => $code,
            'season_label' => Rank_Tier::season_label( $code ),
            'score'        => $score,
            'rank'         => $rank,
            'tier'         => $tier,
            'progress'     => $progress,
        ];
    }

    /* ==========================================================
     * Top N 排行（當季，給 rank_season tab 用）
     * ========================================================== */
    public static function get_leaderboard( $limit = 100, $offset = 0, $season_code = null ) {
        global $wpdb;
        $code   = $season_code ?: Rank_Tier::current_season_code();
        $limit  = max( 1, min( 500, (int) $limit ) );
        $offset = max( 0, (int) $offset );
        $tbl    = self::table_current();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, season_score FROM {$tbl}
             WHERE season_code=%s AND season_score > 0
             ORDER BY season_score DESC, user_id ASC
             LIMIT %d OFFSET %d",
            $code, $limit, $offset
        ), ARRAY_A );

        $out = [];
        foreach ( $rows as $i => $r ) {
            $rank = $offset + $i + 1;
            $tier = Rank_Tier::tier_from_score_and_rank( (int) $r['season_score'], $rank );
            $out[] = [
                'rank'    => $rank,
                'user_id' => (int) $r['user_id'],
                'score'   => (int) $r['season_score'],
                'tier'    => $tier,
            ];
        }
        return $out;
    }

    /* ==========================================================
     * v1.1.0：上季 / 任意已結算賽季的 archive 排行
     * ========================================================== */

    /**
     * 取得 archive 內最近一個結算過的賽季 code（依 settled_at desc）
     * 若 archive 為空（系統還沒經歷過換季）→ 回傳空字串
     */
    public static function get_last_settled_season_code() {
        global $wpdb;
        $arc = self::table_archive();

        // 表本身不存在（升級流程未跑） → 直接視為無資料
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$arc'" ) !== $arc ) return '';

        $code = $wpdb->get_var(
            "SELECT season_code FROM {$arc}
             ORDER BY settled_at DESC, season_code DESC
             LIMIT 1"
        );
        return $code ?: '';
    }

    /**
     * 讀取 archive 排行（Top N）
     *
     * @param string|null $season_code  null → 自動取最近一季
     * @return array {
     *   season_code: string,
     *   season_label: string,
     *   items: list of [rank, user_id, score, tier],
     *   total: int,
     * }
     */
    public static function get_archive_leaderboard( $season_code = null, $limit = 100, $offset = 0 ) {
        global $wpdb;

        $code = $season_code ?: self::get_last_settled_season_code();
        if ( ! $code ) {
            return [
                'season_code'  => '',
                'season_label' => '',
                'items'        => [],
                'total'        => 0,
            ];
        }

        $arc    = self::table_archive();
        $limit  = max( 1, min( 500, (int) $limit ) );
        $offset = max( 0, (int) $offset );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, final_rank, final_score, tier_key, tier_label
             FROM {$arc}
             WHERE season_code = %s
             ORDER BY final_rank ASC
             LIMIT %d OFFSET %d",
            $code, $limit, $offset
        ), ARRAY_A );

        $items = [];
        foreach ( $rows as $r ) {
            $tier_icon = Rank_Tier::ICONS[ $r['tier_key'] ] ?? '🎖️';
            $items[] = [
                'rank'    => (int) $r['final_rank'],
                'user_id' => (int) $r['user_id'],
                'score'   => (int) $r['final_score'],
                'tier'    => [
                    'key'   => (string) $r['tier_key'],
                    'label' => (string) $r['tier_label'],
                    'icon'  => $tier_icon,
                    'color' => self::tier_color_from_key( $r['tier_key'] ),
                ],
            ];
        }

        return [
            'season_code'  => $code,
            'season_label' => Rank_Tier::season_label( $code ),
            'items'        => $items,
            'total'        => self::archive_total( $code ),
        ];
    }

    /**
     * 上季 / 任意賽季 archive 的總人數
     */
    public static function archive_total( $season_code ) {
        global $wpdb;
        if ( ! $season_code ) return 0;
        $arc = self::table_archive();
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$arc'" ) !== $arc ) return 0;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$arc} WHERE season_code = %s",
            $season_code
        ) );
    }

    /**
     * 查單一使用者在 archive 內某季的最終名次
     */
    public static function get_archive_user_position( $uid, $season_code = null ) {
        global $wpdb;
        $uid = (int) $uid;
        if ( $uid <= 0 ) return null;

        $code = $season_code ?: self::get_last_settled_season_code();
        if ( ! $code ) return null;

        $arc = self::table_archive();
        $row = $wpdb->get_var( $wpdb->prepare(
            "SELECT final_rank FROM {$arc}
             WHERE user_id = %d AND season_code = %s",
            $uid, $code
        ) );
        return $row ? (int) $row : null;
    }

    /**
     * 由 tier_key 反查顏色（archive 表沒存 color 欄位，需從 TIERS 內反查）
     */
    private static function tier_color_from_key( $key ) {
        // 菁英 / 宗師（不在 TIERS 列表中，獨立給色）
        if ( $key === 'challenger' )  return '#ff6b6b';
        if ( $key === 'grandmaster' ) return '#ff9f43';

        foreach ( Rank_Tier::TIERS as $row ) {
            if ( $row[0] === $key ) return $row[4];
        }
        return '#888888';
    }

    /* ==========================================================
     * 換季檢查（cron 每小時呼叫）
     * ========================================================== */
    public static function check_rollover() {
        $current  = Rank_Tier::current_season_code();
        $recorded = get_option( 'smacg_rank_current_season', '' );

        if ( $recorded === '' ) {
            update_option( 'smacg_rank_current_season', $current );
            return;
        }
        if ( $recorded === $current ) return;

        // 不同 → 結算 recorded，啟動 current
        self::settle_season( $recorded );
        update_option( 'smacg_rank_current_season', $current );

        do_action( 'smacg_rank_season_rolled', $recorded, $current );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[SMACG] Rank season rolled: %s -> %s', $recorded, $current ) );
        }
    }

    /* ==========================================================
     * 結算：歸檔 + 發紀念徽章
     * ========================================================== */
    public static function settle_season( $season_code ) {
        global $wpdb;
        if ( ! $season_code ) return;

        $cur = self::table_current();
        $arc = self::table_archive();

        // 取出該賽季所有資料並計算名次
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, season_score FROM {$cur}
             WHERE season_code=%s AND season_score > 0
             ORDER BY season_score DESC, user_id ASC",
            $season_code
        ), ARRAY_A );

        $now = current_time( 'mysql' );
        foreach ( $rows as $i => $r ) {
            $rank  = $i + 1;
            $score = (int) $r['season_score'];
            $tier  = Rank_Tier::tier_from_score_and_rank( $score, $rank );

            // 寫入歸檔表
            $wpdb->replace( $arc, [
                'user_id'     => (int) $r['user_id'],
                'season_code' => $season_code,
                'final_rank'  => $rank,
                'final_score' => $score,
                'tier_key'    => $tier['key'],
                'tier_label'  => $tier['label'],
                'settled_at'  => $now,
            ] );

            // 觸發發獎 hook（其他模組可掛入）
            do_action( 'smacg_rank_season_settle_user', (int) $r['user_id'], $tier, $rank, $score, $season_code );

            // 記錄個人「生涯最高段位」
            self::maybe_update_career_peak( (int) $r['user_id'], $tier );
        }

        // 清空當季資料
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$cur} WHERE season_code=%s",
            $season_code
        ) );

        do_action( 'smacg_rank_season_settled', $season_code, count( $rows ) );
    }

    /**
     * 更新使用者生涯最高段位（user_meta）
     */
    private static function maybe_update_career_peak( $uid, $tier ) {
        $order = [
            'iron' => 1, 'bronze' => 2, 'silver' => 3, 'gold' => 4,
            'platinum' => 5, 'emerald' => 6, 'diamond' => 7,
            'master' => 8, 'grandmaster' => 9, 'challenger' => 10,
        ];
        $cur_peak = get_user_meta( $uid, 'smacg_rank_career_peak', true );
        $cur_lvl  = $order[ $cur_peak['key'] ?? '' ] ?? 0;
        $new_lvl  = $order[ $tier['key'] ] ?? 0;

        if ( $new_lvl > $cur_lvl ) {
            update_user_meta( $uid, 'smacg_rank_career_peak', [
                'key'   => $tier['key'],
                'label' => $tier['label'],
                'icon'  => $tier['icon'],
            ] );
        }
    }
}
