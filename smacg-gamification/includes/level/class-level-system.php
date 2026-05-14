<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Level / Job system（搬自 theme/inc/level-system.php）
 *
 * 等級規則：Lv.1 → Lv.200，5 階職業
 * 職業階層：
 *   Lv.1-9    新人
 *   Lv.10-29  見習（轉職一次）
 *   Lv.30-69  二轉
 *   Lv.70-119 三轉
 *   Lv.120-199 四轉
 *   Lv.200    滿級
 *
 * EXP 公式（每級所需 EXP）：
 *   Lv.1-10:   level * 100
 *   Lv.11-30:  1000 + (level - 10) * 250
 *   Lv.31-70:  6000 + (level - 30) * 600
 *   Lv.71-120: 30000 + (level - 70) * 1500
 *   Lv.121-200: 105000 + (level - 120) * 3000
 */
class Level_System {

    private static $instance = null;
    private static $level_table = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    /* ==========================================================
     * 8 職業表（含轉職階層 + 稱號）
     * ========================================================== */
    public static function get_jobs() {
        return [
            [ 'min' => 1,   'max' => 9,   'tier' => 0, 'key' => 'rookie',    'title' => '新人' ],
            [ 'min' => 10,  'max' => 29,  'tier' => 1, 'key' => 'apprentice','title' => '見習生' ],
            [ 'min' => 30,  'max' => 49,  'tier' => 2, 'key' => 'expert',    'title' => '專家' ],
            [ 'min' => 50,  'max' => 69,  'tier' => 2, 'key' => 'master',    'title' => '達人' ],
            [ 'min' => 70,  'max' => 99,  'tier' => 3, 'key' => 'guru',      'title' => '宗師' ],
            [ 'min' => 100, 'max' => 119, 'tier' => 3, 'key' => 'sage',      'title' => '賢者' ],
            [ 'min' => 120, 'max' => 199, 'tier' => 4, 'key' => 'legend',    'title' => '傳奇' ],
            [ 'min' => 200, 'max' => 200, 'tier' => 5, 'key' => 'celestial', 'title' => '滿級・天界' ],
        ];
    }

    public static function get_job_by_level( $lv ) {
        $lv = (int) $lv;
        foreach ( self::get_jobs() as $job ) {
            if ( $lv >= $job['min'] && $lv <= $job['max'] ) {
                return $job['title'];
            }
        }
        return '新人';
    }

    public static function get_job_data_by_level( $lv ) {
        $lv = (int) $lv;
        foreach ( self::get_jobs() as $job ) {
            if ( $lv >= $job['min'] && $lv <= $job['max'] ) return $job;
        }
        return self::get_jobs()[0];
    }

    /* ==========================================================
     * Tier（轉職階段）
     * ========================================================== */
    public static function get_tier( $lv ) {
        $j = self::get_job_data_by_level( $lv );
        return (int) $j['tier'];
    }

    /* ==========================================================
     * EXP <-> Level
     * ========================================================== */
    public static function get_level_table() {
        if ( self::$level_table !== null ) return self::$level_table;

        $table = [ 0 ]; // index 0 = Lv.1（accumulated EXP needed）
        $acc   = 0;
        for ( $lv = 1; $lv <= 200; $lv++ ) {
            $acc += self::exp_for_level( $lv );
            $table[ $lv ] = $acc;
        }
        self::$level_table = $table;
        return $table;
    }

    /** 升到指定等級「下一級」所需的 EXP */
    public static function exp_for_level( $lv ) {
        if ( $lv <= 10 )  return $lv * 100;
        if ( $lv <= 30 )  return 1000 + ( $lv - 10 ) * 250;
        if ( $lv <= 70 )  return 6000 + ( $lv - 30 ) * 600;
        if ( $lv <= 120 ) return 30000 + ( $lv - 70 ) * 1500;
        return 105000 + ( $lv - 120 ) * 3000;
    }

    public static function calc_level_from_exp( $exp ) {
        $exp   = max( 0, (int) $exp );
        $table = self::get_level_table();
        $lv    = 1;
        for ( $i = 1; $i <= 200; $i++ ) {
            if ( $exp >= $table[ $i ] ) $lv = $i;
            else break;
        }
        return $lv;
    }

    /* ==========================================================
     * 對外 API：取得用戶完整等級資訊
     * ========================================================== */
    public static function get_user_level( $uid ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) {
            return self::empty_struct();
        }

        $exp = Gamipress_Bridge::get_user_exp( $uid );
        $lv  = self::calc_level_from_exp( $exp );
        $job = self::get_job_data_by_level( $lv );

        $table = self::get_level_table();
        $cur_lv_exp  = $table[ $lv ] ?? 0;
        $next_lv_exp = $table[ min( $lv + 1, 200 ) ] ?? $cur_lv_exp;
        $delta       = max( 1, $next_lv_exp - $cur_lv_exp );
        $progress    = $lv >= 200 ? 100 : floor( ( $exp - $cur_lv_exp ) / $delta * 100 );

        /* 自訂職業（career-ajax 選擇後寫入 smacg_career_job） */
        $custom_career = get_user_meta( $uid, 'smacg_career_job', true );

        return [
            'user_id'        => $uid,
            'exp'            => $exp,
            'level'          => $lv,
            'tier'           => (int) $job['tier'],
            'job_key'        => $job['key'],
            'job_title'      => $job['title'],
            'custom_career'  => $custom_career ?: '',
            'exp_current'    => $exp,
            'exp_this_level' => $cur_lv_exp,
            'exp_next_level' => $next_lv_exp,
            'exp_needed'     => $lv >= 200 ? 0 : ( $next_lv_exp - $exp ),
            'progress_pct'   => (int) $progress,
            'is_max'         => $lv >= 200,
            'badge_count'    => Gamipress_Bridge::get_user_badge_count( $uid ),
        ];
    }

    private static function empty_struct() {
        return [
            'user_id'        => 0,
            'exp'            => 0,
            'level'          => 1,
            'tier'           => 0,
            'job_key'        => 'rookie',
            'job_title'      => '新人',
            'custom_career'  => '',
            'exp_current'    => 0,
            'exp_this_level' => 0,
            'exp_next_level' => 100,
            'exp_needed'     => 100,
            'progress_pct'   => 0,
            'is_max'         => false,
            'badge_count'    => 0,
        ];
    }

    /* ==========================================================
     * 主動發放 EXP（API 風格，回傳詳細結果）
     * ========================================================== */
    public static function grant_exp( $uid, $amount, $reason = '' ) {
        $uid    = (int) $uid;
        $amount = (int) $amount;
        if ( $uid <= 0 || $amount <= 0 ) {
            return [ 'ok' => false, 'reason' => 'invalid_args' ];
        }

        $before_exp = Gamipress_Bridge::get_user_exp( $uid );
        $before_lv  = self::calc_level_from_exp( $before_exp );

        $ok = Gamipress_Bridge::award_exp( $uid, $amount, $reason ?: 'smacg_grant_exp' );
        if ( ! $ok ) {
            return [ 'ok' => false, 'reason' => 'gamipress_failed' ];
        }

        $after_exp = Gamipress_Bridge::get_user_exp( $uid );
        $after_lv  = self::calc_level_from_exp( $after_exp );

        if ( $after_lv > $before_lv ) {
            Exp_Events::handle_level_up( $uid, $before_lv, $after_lv );
        }

        return [
            'ok'         => true,
            'awarded'    => $amount,
            'exp_before' => $before_exp,
            'exp_after'  => $after_exp,
            'lv_before'  => $before_lv,
            'lv_after'   => $after_lv,
            'leveled_up' => $after_lv > $before_lv,
        ];
    }
}
