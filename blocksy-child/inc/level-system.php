<?php
/**
 * Level System - 等級計算與稱號對應
 *
 * 公式：等級 = floor(sqrt(EXP / 5))，範圍：Lv.1 ~ Lv.200
 *
 * 等級里程碑：
 * - Lv.10  (500 EXP)     一轉解鎖
 * - Lv.30  (4,500 EXP)   二轉自動升階
 * - Lv.70  (24,500 EXP)  三轉自動升階
 * - Lv.120 (72,000 EXP)  四轉究極稱號
 * - Lv.200 (200,000 EXP) 神級老粉
 *
 * Version: 1.2.0 (2026-05-14)
 *   - [移除] smacg_get_user_level() 包裝（避免與 member-functions.php 舊函式同名）
 *           外部請改用：
 *             - smacg_calc_user_level( $exp )       → 由 EXP 算 level（純函式）
 *             - smacg_get_user_level_info( $uid )   → 完整等級資訊（含進度條）
 *
 * Version: 1.1.0 (2026-05-14)
 *   - [重新命名] smacg_calc_level → smacg_calc_user_level
 *   - [新增] smacg_get_level_title($level)
 *
 * @package Blocksy_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SMACG_MAX_LEVEL' ) ) {
    define( 'SMACG_MAX_LEVEL', 200 );
}

/* =========================================================
 * 1. 等級計算（核心）
 * ========================================================= */

/**
 * 由 EXP 計算等級（純函式）
 * 公式：level = floor(sqrt(exp / 5))，上限 200
 *
 * @param int $exp 累積 EXP
 * @return int 等級（1-200）
 */
if ( ! function_exists( 'smacg_calc_user_level' ) ) {
    function smacg_calc_user_level( $exp ) {
        $exp = max( 0, (int) $exp );
        if ( $exp < 5 ) return 1;

        $level = (int) floor( sqrt( $exp / 5 ) );
        return min( SMACG_MAX_LEVEL, max( 1, $level ) );
    }
}

/**
 * 由等級反推所需 EXP
 *
 * @param int $level
 * @return int
 */
if ( ! function_exists( 'smacg_level_to_exp' ) ) {
    function smacg_level_to_exp( $level ) {
        $level = max( 1, (int) $level );
        return $level * $level * 5;
    }
}

/* =========================================================
 * 2. 稱號階段（6 階）
 * ========================================================= */

if ( ! function_exists( 'smacg_get_level_tier' ) ) {
    function smacg_get_level_tier( $level ) {
        $level = (int) $level;

        if ( $level >= 200 ) return array( 'tier' => 6, 'title' => '黑卡會員', 'icon' => '💎' );
        if ( $level >= 120 ) return array( 'tier' => 5, 'title' => 'VIP',      'icon' => '👑' );
        if ( $level >= 70  ) return array( 'tier' => 4, 'title' => '熟客',     'icon' => '🎬' );
        if ( $level >= 30  ) return array( 'tier' => 3, 'title' => '常客',     'icon' => '📺' );
        if ( $level >= 10  ) return array( 'tier' => 2, 'title' => '新客',     'icon' => '🌿' );
        return array( 'tier' => 1, 'title' => '新進會員', 'icon' => '🌱' );
    }
}

/**
 * 取得等級稱號（含 icon）— exp-events.php / 通知用
 *
 * @param int $level
 * @return string  例：'🌿 新客'
 */
if ( ! function_exists( 'smacg_get_level_title' ) ) {
    function smacg_get_level_title( $level ) {
        $tier = smacg_get_level_tier( $level );
        return $tier['icon'] . ' ' . $tier['title'];
    }
}

/* =========================================================
 * 3. 等級進度條資訊
 * ========================================================= */

if ( ! function_exists( 'smacg_get_user_level_info' ) ) {
    function smacg_get_user_level_info( $uid ) {
        $exp           = function_exists( 'smacg_get_user_exp' ) ? smacg_get_user_exp( $uid ) : 0;
        $level         = smacg_calc_user_level( $exp );
        $current_floor = smacg_level_to_exp( $level );
        $next_floor    = smacg_level_to_exp( $level + 1 );
        $tier          = smacg_get_level_tier( $level );

        $in_level_exp    = $exp - $current_floor;
        $level_total_exp = max( 1, $next_floor - $current_floor );
        $percent         = max( 0, min( 100, round( $in_level_exp / $level_total_exp * 100 ) ) );

        return array(
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
        );
    }
}

/* =========================================================
 * 4. 轉職階段判定
 * ========================================================= */

if ( ! function_exists( 'smacg_get_career_stage' ) ) {
    function smacg_get_career_stage( $level ) {
        $level = (int) $level;
        if ( $level >= 120 ) return 4;
        if ( $level >= 70  ) return 3;
        if ( $level >= 30  ) return 2;
        if ( $level >= 10  ) return 1;
        return 0;
    }
}

if ( ! function_exists( 'smacg_get_career_milestones' ) ) {
    function smacg_get_career_milestones() {
        return array(
            1 => array( 'level' => 10,  'label' => '一轉：解鎖職業', 'icon' => '🎓' ),
            2 => array( 'level' => 30,  'label' => '二轉：職業升階', 'icon' => '⭐' ),
            3 => array( 'level' => 70,  'label' => '三轉：高階稱號', 'icon' => '🌟' ),
            4 => array( 'level' => 120, 'label' => '四轉：究極稱號', 'icon' => '👑' ),
        );
    }
}

/* =========================================================
 * 5. 8 個職業 + 4 階稱號表（給 Career tab 使用）
 * ========================================================= */

if ( ! function_exists( 'smacg_get_jobs' ) ) {
    function smacg_get_jobs() {
        return array(
            'student' => array(
                'label'  => '學生',
                'icon'   => '🎓',
                'titles' => array(
                    1 => array( 'name' => '懵懂新生', 'ref' => '我的英雄學院' ),
                    2 => array( 'name' => '優等生',   'ref' => '為美好的世界獻上祝福' ),
                    3 => array( 'name' => '學生會長', 'ref' => '輝夜姬想讓人告白' ),
                    4 => array( 'name' => '神級學者', 'ref' => '文豪Stray Dogs' ),
                ),
            ),
            'it' => array(
                'label'  => '資訊／軟體業',
                'icon'   => '💻',
                'titles' => array(
                    1 => array( 'name' => '程式新手',   'ref' => '無職轉生' ),
                    2 => array( 'name' => '鏈鋸碼農',   'ref' => '鏈鋸人' ),
                    3 => array( 'name' => '咒術工程師', 'ref' => '咒術迴戰' ),
                    4 => array( 'name' => '開發超人',   'ref' => 'SPY×FAMILY' ),
                ),
            ),
            'design' => array(
                'label'  => '設計／創作',
                'icon'   => '🎨',
                'titles' => array(
                    1 => array( 'name' => '美術新手',   'ref' => '排球少年' ),
                    2 => array( 'name' => '色彩魔法師', 'ref' => '魔法少女小圓' ),
                    3 => array( 'name' => '藝術鬼才',   'ref' => '進擊的巨人' ),
                    4 => array( 'name' => '神之筆者',   'ref' => '哆啦A夢' ),
                ),
            ),
            'office' => array(
                'label'  => '行政／內勤',
                'icon'   => '💼',
                'titles' => array(
                    1 => array( 'name' => '社畜新血',   'ref' => 'NEW GAME!' ),
                    2 => array( 'name' => '會議達人',   'ref' => '我推的孩子' ),
                    3 => array( 'name' => '部門核心',   'ref' => '半澤直樹' ),
                    4 => array( 'name' => '辦公室之神', 'ref' => '魔法少女小圓' ),
                ),
            ),
            'sales' => array(
                'label'  => '業務／行銷',
                'icon'   => '📊',
                'titles' => array(
                    1 => array( 'name' => '業務新兵',   'ref' => '機動戰士鋼彈' ),
                    2 => array( 'name' => '簽單獵人',   'ref' => '獵人' ),
                    3 => array( 'name' => '王牌業務',   'ref' => 'JOJO的奇妙冒險' ),
                    4 => array( 'name' => '銷售之神',   'ref' => '海賊王' ),
                ),
            ),
            'medical' => array(
                'label'  => '醫療／護理',
                'icon'   => '🏥',
                'titles' => array(
                    1 => array( 'name' => '見習醫師', 'ref' => '怪醫黑傑克' ),
                    2 => array( 'name' => '白衣天使', 'ref' => '工作細胞' ),
                    3 => array( 'name' => '主治醫師', 'ref' => '怪醫黑傑克' ),
                    4 => array( 'name' => '醫術之神', 'ref' => 'Dr. STONE' ),
                ),
            ),
            'service' => array(
                'label'  => '餐飲／服務業',
                'icon'   => '🍽️',
                'titles' => array(
                    1 => array( 'name' => '服務員',   'ref' => '異世界食堂' ),
                    2 => array( 'name' => '迎賓武士', 'ref' => '銀魂' ),
                    3 => array( 'name' => '微笑大使', 'ref' => 'SPY×FAMILY' ),
                    4 => array( 'name' => '傳奇店長', 'ref' => '中華一番' ),
                ),
            ),
            'freelance' => array(
                'label'  => '自由／其他',
                'icon'   => '🌱',
                'titles' => array(
                    1 => array( 'name' => '自由人',   'ref' => '葬送的芙莉蓮' ),
                    2 => array( 'name' => '斜槓達人', 'ref' => '為美好的世界獻上祝福' ),
                    3 => array( 'name' => '人生玩家', 'ref' => 'JOJO的奇妙冒險' ),
                    4 => array( 'name' => '逍遙之神', 'ref' => '聖哥傳' ),
                ),
            ),
        );
    }
}

if ( ! function_exists( 'smacg_get_user_job' ) ) {
    function smacg_get_user_job( $uid ) {
        return (string) get_user_meta( (int) $uid, 'smacg_job_key', true );
    }
}

if ( ! function_exists( 'smacg_set_user_job' ) ) {
    function smacg_set_user_job( $uid, $job_key ) {
        $uid     = (int) $uid;
        $job_key = sanitize_key( $job_key );
        $jobs    = smacg_get_jobs();

        if ( $uid <= 0 || ! isset( $jobs[ $job_key ] ) ) return false;

        update_user_meta( $uid, 'smacg_job_key',       $job_key );
        update_user_meta( $uid, 'smacg_job_chosen_at', current_time( 'mysql' ) );

        do_action( 'smacg_user_job_chosen', $uid, $job_key );

        return true;
    }
}

if ( ! function_exists( 'smacg_get_user_job_title' ) ) {
    function smacg_get_user_job_title( $uid ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return array();

        $job_key = smacg_get_user_job( $uid );
        if ( ! $job_key ) return array();

        $jobs = smacg_get_jobs();
        if ( ! isset( $jobs[ $job_key ] ) ) return array();

        $info  = smacg_get_user_level_info( $uid );
        $stage = max( 1, smacg_get_career_stage( $info['level'] ) );
        if ( $stage < 1 ) return array();

        $title = $jobs[ $job_key ]['titles'][ $stage ];

        return array(
            'job_key'    => $job_key,
            'job_label'  => $jobs[ $job_key ]['label'],
            'job_icon'   => $jobs[ $job_key ]['icon'],
            'stage'      => $stage,
            'title_name' => $title['name'],
            'title_ref'  => $title['ref'],
        );
    }
}

/* =========================================================
 * 6. EXP 給予 + 升等檢測（wrapper，向下相容）
 * ========================================================= */

if ( ! function_exists( 'smacg_grant_exp' ) ) {
    function smacg_grant_exp( $uid, $amount, $reason = '', $args = array() ) {
        $uid    = (int) $uid;
        $amount = (int) $amount;

        $result = array(
            'success'    => false,
            'leveled_up' => false,
            'old_level'  => 0,
            'new_level'  => 0,
            'milestones' => array(),
        );

        if ( $uid <= 0 || $amount <= 0 ) return $result;

        $old_exp   = smacg_get_user_exp( $uid );
        $old_level = smacg_calc_user_level( $old_exp );

        $success = smacg_award_exp( $uid, $amount, $reason, $args );
        if ( ! $success ) return $result;

        $new_exp   = smacg_get_user_exp( $uid );
        $new_level = smacg_calc_user_level( $new_exp );

        $result['success']    = true;
        $result['old_level']  = $old_level;
        $result['new_level']  = $new_level;
        $result['leveled_up'] = ( $new_level > $old_level );

        if ( $result['leveled_up'] ) {
            $milestones = smacg_get_career_milestones();
            foreach ( $milestones as $stage => $info ) {
                if ( $old_level < $info['level'] && $new_level >= $info['level'] ) {
                    $result['milestones'][] = array(
                        'stage' => $stage,
                        'level' => $info['level'],
                        'label' => $info['label'],
                        'icon'  => $info['icon'],
                    );
                }
            }
            do_action( 'smacg_user_leveled_up', $uid, $old_level, $new_level, $result );
        }

        return $result;
    }
}
