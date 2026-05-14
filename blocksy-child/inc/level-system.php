<?php
/**
 * Level System - 等級計算與稱號對應
 * 
 * 公式：等級 = floor(sqrt(EXP / 5))
 * 範圍：Lv.1 ~ Lv.200
 * 
 * 等級里程碑：
 * - Lv.10  (500 EXP)    一轉解鎖
 * - Lv.30  (4,500 EXP)  二轉自動升階
 * - Lv.70  (24,500 EXP) 三轉自動升階
 * - Lv.120 (72,000 EXP) 四轉究極稱號
 * - Lv.200 (200,000 EXP) 神級老粉
 *
 * Version: 1.0.0 (2026-05-13)
 * Batch: 2A-0
 *
 * @package Blocksy_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 常數
 * ========================================================= */
if ( ! defined( 'SMACG_MAX_LEVEL' ) ) {
    define( 'SMACG_MAX_LEVEL', 200 );
}

/* =========================================================
 * 1. 等級計算
 * ========================================================= */

/**
 * 由 EXP 計算等級
 * 公式：level = floor(sqrt(exp / 5))，上限 200
 *
 * @param int $exp 累積 EXP
 * @return int 等級
 */
function smacg_calc_level( $exp ) {
    $exp = max( 0, (int) $exp );
    if ( $exp < 5 ) return 1;

    $level = (int) floor( sqrt( $exp / 5 ) );
    return min( SMACG_MAX_LEVEL, max( 1, $level ) );
}

/**
 * 由等級反推所需 EXP
 *
 * @param int $level 目標等級
 * @return int 達到該等級所需的最低 EXP
 */
function smacg_level_to_exp( $level ) {
    $level = max( 1, (int) $level );
    return $level * $level * 5;
}

/* =========================================================
 * 2. 稱號階段（5 階）
 * ========================================================= */

/**
 * 由等級取得稱號階段
 * 
 * Lv.1-9    新進會員
 * Lv.10-29  新客
 * Lv.30-69  常客
 * Lv.70-119 熟客
 * Lv.120-199 VIP
 * Lv.200    黑卡會員
 *
 * @param int $level
 * @return array ['tier'=>1-6, 'title'=>'稱號', 'icon'=>'emoji']
 */
function smacg_get_level_tier( $level ) {
    $level = (int) $level;

    if ( $level >= 200 ) {
        return array( 'tier' => 6, 'title' => '黑卡會員', 'icon' => '💎' );
    }
    if ( $level >= 120 ) {
        return array( 'tier' => 5, 'title' => 'VIP',        'icon' => '👑' );
    }
    if ( $level >= 70 ) {
        return array( 'tier' => 4, 'title' => '熟客',       'icon' => '🎬' );
    }
    if ( $level >= 30 ) {
        return array( 'tier' => 3, 'title' => '常客',       'icon' => '📺' );
    }
    if ( $level >= 10 ) {
        return array( 'tier' => 2, 'title' => '新客',       'icon' => '🌿' );
    }

    return array( 'tier' => 1, 'title' => '新進會員', 'icon' => '🌱' );
}

/* =========================================================
 * 3. 等級進度條資訊
 * ========================================================= */

/**
 * 取得用戶等級完整資訊（給 UI 渲染用）
 *
 * @param int $uid 用戶 ID
 * @return array
 */
function smacg_get_user_level_info( $uid ) {
    $exp           = smacg_get_user_exp( $uid );
    $level         = smacg_calc_level( $exp );
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

/* =========================================================
 * 4. 轉職階段判定
 * ========================================================= */

/**
 * 取得用戶當前可達的轉職階段
 * 
 * @param int $level
 * @return int 0=未轉職, 1=一轉, 2=二轉, 3=三轉, 4=四轉
 */
function smacg_get_career_stage( $level ) {
    $level = (int) $level;
    if ( $level >= 120 ) return 4;
    if ( $level >= 70 )  return 3;
    if ( $level >= 30 )  return 2;
    if ( $level >= 10 )  return 1;
    return 0;
}

/**
 * 取得轉職里程碑列表
 *
 * @return array
 */
function smacg_get_career_milestones() {
    return array(
        1 => array( 'level' => 10,  'label' => '一轉：解鎖職業',     'icon' => '🎓' ),
        2 => array( 'level' => 30,  'label' => '二轉：職業升階',     'icon' => '⭐' ),
        3 => array( 'level' => 70,  'label' => '三轉：高階稱號',     'icon' => '🌟' ),
        4 => array( 'level' => 120, 'label' => '四轉：究極稱號',     'icon' => '👑' ),
    );
}

/* =========================================================
 * 5. EXP 給予 + 升等檢測（核心 wrapper）
 * ========================================================= */

/**
 * 給予 EXP 並自動檢測升等
 * （業務程式碼建議呼叫此函式而非直接 smacg_award_exp）
 *
 * @param int    $uid    用戶 ID
 * @param int    $amount EXP 數量
 * @param string $reason 原因
 * @param array  $args   額外參數
 * @return array ['success'=>bool, 'leveled_up'=>bool, 'old_level'=>int, 'new_level'=>int, 'milestones'=>array]
 */
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

    // 取得舊等級
    $old_exp   = smacg_get_user_exp( $uid );
    $old_level = smacg_calc_level( $old_exp );

    // 發放 EXP
    $success = smacg_award_exp( $uid, $amount, $reason, $args );
    if ( ! $success ) return $result;

    // 取得新等級
    $new_exp   = smacg_get_user_exp( $uid );
    $new_level = smacg_calc_level( $new_exp );

    $result['success']    = true;
    $result['old_level']  = $old_level;
    $result['new_level']  = $new_level;
    $result['leveled_up'] = ( $new_level > $old_level );

    // 檢測是否跨越轉職里程碑
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

        // 觸發升等事件（給通知系統等模組監聽）
        do_action( 'smacg_user_leveled_up', $uid, $old_level, $new_level, $result );
    }

    return $result;
}
