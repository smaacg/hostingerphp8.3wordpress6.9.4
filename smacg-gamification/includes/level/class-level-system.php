<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Level System — 等級計算 + 6 階會員稱號
 *
 * 公式：level = floor( sqrt( exp / 5 ) )，上限 Lv.200
 * 反推：exp  = level² × 5
 *
 *   Lv.1   → 5 EXP
 *   Lv.10  → 500 EXP（一轉解鎖）
 *   Lv.30  → 4,500 EXP（二轉）
 *   Lv.70  → 24,500 EXP（三轉）
 *   Lv.120 → 72,000 EXP（四轉）
 *   Lv.200 → 200,000 EXP（究極）
 *
 * 6 階會員稱號：
 *   Lv.1-9    🌱 新進會員
 *   Lv.10-29  🌿 新客
 *   Lv.30-69  📺 常客
 *   Lv.70-119 🎬 熟客
 *   Lv.120-199 👑 VIP
 *   Lv.200    💎 黑卡會員
 *
 * @since 2.0.0 (2026-05-15) 改用 sqrt 公式 + 6 階會員稱號（取代 8 階轉職稱號）
 */
class Level_System {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    /* ==========================================================
     * 1. EXP <-> Level
     * ========================================================== */

    /**
     * 由 EXP 算 Level
     */
    public static function calc_level_from_exp( $exp ) {
        $exp = max( 0, (int) $exp );
        if ( $exp < 5 ) return 1;
        $lv = (int) floor( sqrt( $exp / 5 ) );
        return min( SMACG_MAX_LEVEL, max( 1, $lv ) );
    }

    /**
     * 由 Level 反推所需累計 EXP
     */
    public static function level_to_exp( $level ) {
        $level = max( 1, (int) $level );
        return $level * $level * 5;
    }

    /**
     * Lv.1~200 累計 EXP 表
     */
    public static function get_level_table() {
        static $table = null;
        if ( $table !== null ) return $table;

        $table = [];
        for ( $lv = 1; $lv <= SMACG_MAX_LEVEL; $lv++ ) {
            $table[ $lv ] = self::level_to_exp( $lv );
        }
        return $table;
    }

    /* ==========================================================
     * 2. 6 階會員稱號
     * ========================================================== */

    /**
     * 取得指定等級的稱號資料
     *
     * @return array { tier:int(1-6), key:string, title:string, icon:string, color:string }
     */
    public static function get_tier( $level ) {
        $level = (int) $level;

        if ( $level >= 200 ) return [ 'tier' => 6, 'key' => 'black',   'title' => '黑卡會員', 'icon' => '💎', 'color' => '#1a1a1a' ];
        if ( $level >= 120 ) return [ 'tier' => 5, 'key' => 'vip',     'title' => 'VIP',      'icon' => '👑', 'color' => '#b8860b' ];
        if ( $level >= 70  ) return [ 'tier' => 4, 'key' => 'expert',  'title' => '熟客',     'icon' => '🎬', 'color' => '#6a4c93' ];
        if ( $level >= 30  ) return [ 'tier' => 3, 'key' => 'regular', 'title' => '常客',     'icon' => '📺', 'color' => '#3a86ff' ];
        if ( $level >= 10  ) return [ 'tier' => 2, 'key' => 'newcomer','title' => '新客',     'icon' => '🌿', 'color' => '#06a77d' ];
        return                       [ 'tier' => 1, 'key' => 'rookie',  'title' => '新進會員', 'icon' => '🌱', 'color' => '#8d99ae' ];
    }

    /** 6 階完整表（給 /level-guide/ 用） */
    public static function get_all_tiers() {
        return [
            [ 'tier' => 1, 'key' => 'rookie',   'title' => '新進會員', 'icon' => '🌱', 'color' => '#8d99ae', 'min_level' => 1,   'min_exp' => 5      ],
            [ 'tier' => 2, 'key' => 'newcomer', 'title' => '新客',     'icon' => '🌿', 'color' => '#06a77d', 'min_level' => 10,  'min_exp' => 500    ],
            [ 'tier' => 3, 'key' => 'regular',  'title' => '常客',     'icon' => '📺', 'color' => '#3a86ff', 'min_level' => 30,  'min_exp' => 4500   ],
            [ 'tier' => 4, 'key' => 'expert',   'title' => '熟客',     'icon' => '🎬', 'color' => '#6a4c93', 'min_level' => 70,  'min_exp' => 24500  ],
            [ 'tier' => 5, 'key' => 'vip',      'title' => 'VIP',      'icon' => '👑', 'color' => '#b8860b', 'min_level' => 120, 'min_exp' => 72000  ],
            [ 'tier' => 6, 'key' => 'black',    'title' => '黑卡會員', 'icon' => '💎', 'color' => '#1a1a1a', 'min_level' => 200, 'min_exp' => 200000 ],
        ];
    }

    /* ==========================================================
     * 3. 對外 API：取得用戶完整等級資訊
     * ========================================================== */

    public static function get_user_level( $uid ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return self::empty_struct();

        $exp   = (int) Gamipress_Bridge::get_user_exp( $uid );
        $level = self::calc_level_from_exp( $exp );
        $tier  = self::get_tier( $level );

        $cur_floor  = self::level_to_exp( $level );
        $next_floor = self::level_to_exp( min( $level + 1, SMACG_MAX_LEVEL ) );
        $is_max     = ( $level >= SMACG_MAX_LEVEL );

        $in_lv_exp = max( 0, $exp - $cur_floor );
        $lv_total  = max( 1, $next_floor - $cur_floor );
        $percent   = $is_max ? 100 : min( 100, (int) floor( $in_lv_exp / $lv_total * 100 ) );

        return [
            'user_id'         => $uid,
            'exp'             => $exp,
            'level'           => $level,
            'tier'            => (int) $tier['tier'],
            'tier_key'        => $tier['key'],
            'title'           => $tier['title'],
            'icon'            => $tier['icon'],
            'color'           => $tier['color'],
            'cur_floor'       => $cur_floor,
            'next_floor'      => $next_floor,
            'in_level_exp'    => $in_lv_exp,
            'level_total_exp' => $lv_total,
            'percent'         => $percent,
            'to_next'         => $is_max ? 0 : max( 0, $next_floor - $exp ),
            'is_max'          => $is_max,
            'badge_count'     => (int) Gamipress_Bridge::get_user_badge_count( $uid ),
        ];
    }

    private static function empty_struct() {
        return [
            'user_id'         => 0,
            'exp'             => 0,
            'level'           => 1,
            'tier'            => 1,
            'tier_key'        => 'rookie',
            'title'           => '新進會員',
            'icon'            => '🌱',
            'color'           => '#8d99ae',
            'cur_floor'       => 5,
            'next_floor'      => 20,
            'in_level_exp'    => 0,
            'level_total_exp' => 15,
            'percent'         => 0,
            'to_next'         => 5,
            'is_max'          => false,
            'badge_count'     => 0,
        ];
    }

    /* ==========================================================
     * 4. 主動發放 EXP（API 風格，回傳詳細結果）
     * ========================================================== */

    public static function grant_exp( $uid, $amount, $reason = '' ) {
        $uid    = (int) $uid;
        $amount = (int) $amount;

        $result = [
            'success'    => false,
            'awarded'    => 0,
            'exp_before' => 0,
            'exp_after'  => 0,
            'lv_before'  => 0,
            'lv_after'   => 0,
            'leveled_up' => false,
            'milestones' => [],
        ];

        if ( $uid <= 0 || $amount <= 0 ) {
            $result['reason'] = 'invalid_args';
            return $result;
        }

        $before_exp = Gamipress_Bridge::get_user_exp( $uid );
        $before_lv  = self::calc_level_from_exp( $before_exp );

        $ok = Gamipress_Bridge::award_exp( $uid, $amount, $reason ?: 'smacg_grant_exp' );
        if ( ! $ok ) {
            $result['reason'] = 'gamipress_failed';
            return $result;
        }

        $after_exp = Gamipress_Bridge::get_user_exp( $uid );
        $after_lv  = self::calc_level_from_exp( $after_exp );

        $result['success']    = true;
        $result['awarded']    = $amount;
        $result['exp_before'] = $before_exp;
        $result['exp_after']  = $after_exp;
        $result['lv_before']  = $before_lv;
        $result['lv_after']   = $after_lv;
        $result['leveled_up'] = ( $after_lv > $before_lv );

        if ( $result['leveled_up'] ) {
            /* 偵測里程碑跨越 */
            $milestones = Career_Jobs::milestones();
            foreach ( $milestones as $stage => $m ) {
                if ( $before_lv < $m['level'] && $after_lv >= $m['level'] ) {
                    $result['milestones'][] = [
                        'stage' => $stage,
                        'level' => $m['level'],
                        'label' => $m['label'],
                        'icon'  => $m['icon'],
                    ];
                }
            }

            /* 觸發升等 hook（Exp_Events 會接） */
            Exp_Events::handle_level_up( $uid, $before_lv, $after_lv );
            do_action( 'smacg_user_leveled_up', $uid, $before_lv, $after_lv, $result );
        }

        return $result;
    }
}
