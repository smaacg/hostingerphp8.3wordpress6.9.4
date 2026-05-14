<?php
/**
 * Level System - 等級計算與稱號對應
 *
 * 原檔：blocksy-child/inc/level-system.php v1.2.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Level;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SMACG_MAX_LEVEL' ) ) {
    define( 'SMACG_MAX_LEVEL', 200 );
}

class System {

    /* ---------- 等級計算 ---------- */

    public static function calc_level( $exp ) {
        $exp = max( 0, (int) $exp );
        if ( $exp < 5 ) return 1;
        $level = (int) floor( sqrt( $exp / 5 ) );
        return min( SMACG_MAX_LEVEL, max( 1, $level ) );
    }

    public static function level_to_exp( $level ) {
        $level = max( 1, (int) $level );
        return $level * $level * 5;
    }

    /* ---------- 稱號階段（6 階） ---------- */

    public static function get_tier( $level ) {
        $level = (int) $level;
        if ( $level >= 200 ) return [ 'tier' => 6, 'title' => '黑卡會員', 'icon' => '💎' ];
        if ( $level >= 120 ) return [ 'tier' => 5, 'title' => 'VIP',      'icon' => '👑' ];
        if ( $level >= 70  ) return [ 'tier' => 4, 'title' => '熟客',     'icon' => '🎬' ];
        if ( $level >= 30  ) return [ 'tier' => 3, 'title' => '常客',     'icon' => '📺' ];
        if ( $level >= 10  ) return [ 'tier' => 2, 'title' => '新客',     'icon' => '🌿' ];
        return [ 'tier' => 1, 'title' => '新進會員', 'icon' => '🌱' ];
    }

    public static function get_title( $level ) {
        $tier = self::get_tier( $level );
        return $tier['icon'] . ' ' . $tier['title'];
    }

    /* ---------- 等級進度條 ---------- */

    public static function get_user_info( $uid ) {
        $exp = function_exists( 'smacg_get_user_exp' ) ? \smacg_get_user_exp( $uid ) : 0;
        $level         = self::calc_level( $exp );
        $current_floor = self::level_to_exp( $level );
        $next_floor    = self::level_to_exp( $level + 1 );
        $tier          = self::get_tier( $level );

        $in_level_exp    = $exp - $current_floor;
        $level_total_exp = max( 1, $next_floor - $current_floor );
        $percent         = max( 0, min( 100, round( $in_level_exp / $level_total_exp * 100 ) ) );

        return [
            'exp'             => $exp,
            'level'           => $level,
            'tier'            => $tier['tier'],
            'title'           => $tier['title'],
            'icon'            => $tier['icon'],
            'current_floor'   => $current_floor,
            'next_floor'      => $next_floor,
            'in_level_exp'    => $in_level_exp,
            'level_total_exp' => $level_total_exp,
            'percent'         => $percent,
            'to_next'         => max( 0, $next_floor - $exp ),
            'is_max'          => $level >= SMACG_MAX_LEVEL,
        ];
    }

    /* ---------- 轉職階段 ---------- */

    public static function get_career_stage( $level ) {
        $level = (int) $level;
        if ( $level >= 120 ) return 4;
        if ( $level >= 70  ) return 3;
        if ( $level >= 30  ) return 2;
        if ( $level >= 10  ) return 1;
        return 0;
    }

    public static function get_career_milestones() {
        return [
            1 => [ 'level' => 10,  'label' => '一轉：解鎖職業', 'icon' => '🎓' ],
            2 => [ 'level' => 30,  'label' => '二轉：職業升階', 'icon' => '⭐' ],
            3 => [ 'level' => 70,  'label' => '三轉：高階稱號', 'icon' => '🌟' ],
            4 => [ 'level' => 120, 'label' => '四轉：究極稱號', 'icon' => '👑' ],
        ];
    }

    /* ---------- 8 職業 × 4 階稱號（舊系統，user_meta = smacg_job_key） ---------- */

    public static function get_jobs() {
        return [
            'student'   => [ 'label' => '學生',         'icon' => '🎓', 'titles' => [
                1 => [ 'name' => '懵懂新生', 'ref' => '我的英雄學院' ],
                2 => [ 'name' => '優等生',   'ref' => '為美好的世界獻上祝福' ],
                3 => [ 'name' => '學生會長', 'ref' => '輝夜姬想讓人告白' ],
                4 => [ 'name' => '神級學者', 'ref' => '文豪Stray Dogs' ],
            ] ],
            'it'        => [ 'label' => '資訊／軟體業',  'icon' => '💻', 'titles' => [
                1 => [ 'name' => '程式新手',   'ref' => '無職轉生' ],
                2 => [ 'name' => '鏈鋸碼農',   'ref' => '鏈鋸人' ],
                3 => [ 'name' => '咒術工程師', 'ref' => '咒術迴戰' ],
                4 => [ 'name' => '開發超人',   'ref' => 'SPY×FAMILY' ],
            ] ],
            'design'    => [ 'label' => '設計／創作',    'icon' => '🎨', 'titles' => [
                1 => [ 'name' => '美術新手',   'ref' => '排球少年' ],
                2 => [ 'name' => '色彩魔法師', 'ref' => '魔法少女小圓' ],
                3 => [ 'name' => '藝術鬼才',   'ref' => '進擊的巨人' ],
                4 => [ 'name' => '神之筆者',   'ref' => '哆啦A夢' ],
            ] ],
            'office'    => [ 'label' => '行政／內勤',    'icon' => '💼', 'titles' => [
                1 => [ 'name' => '社畜新血',   'ref' => 'NEW GAME!' ],
                2 => [ 'name' => '會議達人',   'ref' => '我推的孩子' ],
                3 => [ 'name' => '部門核心',   'ref' => '半澤直樹' ],
                4 => [ 'name' => '辦公室之神', 'ref' => '魔法少女小圓' ],
            ] ],
            'sales'     => [ 'label' => '業務／行銷',    'icon' => '📊', 'titles' => [
                1 => [ 'name' => '業務新兵',   'ref' => '機動戰士鋼彈' ],
                2 => [ 'name' => '簽單獵人',   'ref' => '獵人' ],
                3 => [ 'name' => '王牌業務',   'ref' => 'JOJO的奇妙冒險' ],
                4 => [ 'name' => '銷售之神',   'ref' => '海賊王' ],
            ] ],
            'medical'   => [ 'label' => '醫療／護理',    'icon' => '🏥', 'titles' => [
                1 => [ 'name' => '見習醫師', 'ref' => '怪醫黑傑克' ],
                2 => [ 'name' => '白衣天使', 'ref' => '工作細胞' ],
                3 => [ 'name' => '主治醫師', 'ref' => '怪醫黑傑克' ],
                4 => [ 'name' => '醫術之神', 'ref' => 'Dr. STONE' ],
            ] ],
            'service'   => [ 'label' => '餐飲／服務業',  'icon' => '🍽️', 'titles' => [
                1 => [ 'name' => '服務員',   'ref' => '異世界食堂' ],
                2 => [ 'name' => '迎賓武士', 'ref' => '銀魂' ],
                3 => [ 'name' => '微笑大使', 'ref' => 'SPY×FAMILY' ],
                4 => [ 'name' => '傳奇店長', 'ref' => '中華一番' ],
            ] ],
            'freelance' => [ 'label' => '自由／其他',    'icon' => '🌱', 'titles' => [
                1 => [ 'name' => '自由人',   'ref' => '葬送的芙莉蓮' ],
                2 => [ 'name' => '斜槓達人', 'ref' => '為美好的世界獻上祝福' ],
                3 => [ 'name' => '人生玩家', 'ref' => 'JOJO的奇妙冒險' ],
                4 => [ 'name' => '逍遙之神', 'ref' => '聖哥傳' ],
            ] ],
        ];
    }

    public static function get_user_job( $uid ) {
        return (string) get_user_meta( (int) $uid, 'smacg_job_key', true );
    }

    public static function set_user_job( $uid, $job_key ) {
        $uid     = (int) $uid;
        $job_key = sanitize_key( $job_key );
        $jobs    = self::get_jobs();
        if ( $uid <= 0 || ! isset( $jobs[ $job_key ] ) ) return false;

        update_user_meta( $uid, 'smacg_job_key',       $job_key );
        update_user_meta( $uid, 'smacg_job_chosen_at', current_time( 'mysql' ) );

        do_action( 'smacg_user_job_chosen', $uid, $job_key );
        return true;
    }

    public static function get_user_job_title( $uid ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return [];

        $job_key = self::get_user_job( $uid );
        if ( ! $job_key ) return [];

        $jobs = self::get_jobs();
        if ( ! isset( $jobs[ $job_key ] ) ) return [];

        $info  = self::get_user_info( $uid );
        $stage = max( 1, self::get_career_stage( $info['level'] ) );
        if ( $stage < 1 ) return [];

        $title = $jobs[ $job_key ]['titles'][ $stage ];

        return [
            'job_key'    => $job_key,
            'job_label'  => $jobs[ $job_key ]['label'],
            'job_icon'   => $jobs[ $job_key ]['icon'],
            'stage'      => $stage,
            'title_name' => $title['name'],
            'title_ref'  => $title['ref'],
        ];
    }

    /* ---------- EXP 給予包裝（含完整結果） ---------- */

    public static function grant_exp( $uid, $amount, $reason = '', $args = [] ) {
        $uid    = (int) $uid;
        $amount = (int) $amount;

        $result = [
            'success'    => false,
            'leveled_up' => false,
            'old_level'  => 0,
            'new_level'  => 0,
            'milestones' => [],
        ];

        if ( $uid <= 0 || $amount <= 0 ) return $result;
        if ( ! function_exists( 'smacg_award_exp' ) || ! function_exists( 'smacg_get_user_exp' ) ) return $result;

        $old_exp   = \smacg_get_user_exp( $uid );
        $old_level = self::calc_level( $old_exp );

        $success = \smacg_award_exp( $uid, $amount, $reason, $args );
        if ( ! $success ) return $result;

        $new_exp   = \smacg_get_user_exp( $uid );
        $new_level = self::calc_level( $new_exp );

        $result['success']    = true;
        $result['old_level']  = $old_level;
        $result['new_level']  = $new_level;
        $result['leveled_up'] = ( $new_level > $old_level );

        if ( $result['leveled_up'] ) {
            foreach ( self::get_career_milestones() as $stage => $info ) {
                if ( $old_level < $info['level'] && $new_level >= $info['level'] ) {
                    $result['milestones'][] = [
                        'stage' => $stage,
                        'level' => $info['level'],
                        'label' => $info['label'],
                        'icon'  => $info['icon'],
                    ];
                }
            }
            do_action( 'smacg_user_leveled_up', $uid, $old_level, $new_level, $result );
        }

        return $result;
    }
}
