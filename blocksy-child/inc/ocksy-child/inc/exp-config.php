<?php
/**
 * EXP 規則設定（集中管理）
 * 
 * 所有 EXP 發放數值統一於此設定，方便日後調整或做成後台選項。
 * 修改數值不需改業務碼，只需改本檔。
 *
 * Version: 1.0.0 (2026-05-14)
 * Batch: 2A-1
 *
 * @package Blocksy_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 取得 EXP 發放規則表
 *
 * 結構：
 *   action_key => array(
 *     'exp'        => int,    // 單次發放 EXP
 *     'daily_cap'  => int,    // 每日最多幾次（0 = 無上限，-1 = 一生一次）
 *     'label'      => string, // 後台 log 顯示用標籤
 *   )
 *
 * @return array
 */
function smacg_exp_rules() {
    $rules = array(

        // === 帳號類（一生一次） ===
        'register' => array(
            'exp'       => 50,
            'daily_cap' => -1,
            'label'     => '註冊獎勵',
        ),

        // === 每日活躍 ===
        'daily_login' => array(
            'exp'       => 10,
            'daily_cap' => 1,
            'label'     => '每日首次登入',
        ),

        // === 觀看相關（給 anime-sync-pro 未來呼叫） ===
        'watchlist_completed' => array(
            'exp'       => 30,
            'daily_cap' => 10,
            'label'     => '完成觀看作品',
        ),
        'watchlist_added' => array(
            'exp'       => 5,
            'daily_cap' => 20,
            'label'     => '加入追蹤清單',
        ),

        // === 評分相關 ===
        'rating_added' => array(
            'exp'       => 15,
            'daily_cap' => 20,
            'label'     => '評分作品',
        ),

        // === 留言／社群 ===
        'comment_posted' => array(
            'exp'       => 5,
            'daily_cap' => 10, // 每日最多 10 次 = 50 EXP
            'label'     => '發表留言',
        ),
        'comment_liked' => array(
            'exp'       => 3,
            'daily_cap' => 10,
            'label'     => '留言被按讚',
        ),

        // === 追蹤 ===
        'follow_action' => array(
            'exp'       => 2,
            'daily_cap' => 10,
            'label'     => '追蹤其他用戶',
        ),
        'gained_follower' => array(
            'exp'       => 5,
            'daily_cap' => 10,
            'label'     => '獲得新追蹤者',
        ),

        // === 連續登入獎勵 ===
        'streak_7' => array(
            'exp'       => 100,
            'daily_cap' => 1,
            'label'     => '連續登入 7 天',
        ),
        'streak_30' => array(
            'exp'       => 500,
            'daily_cap' => 1,
            'label'     => '連續登入 30 天',
        ),

        // === 徽章解鎖 ===
        'badge_unlocked' => array(
            'exp'       => 20, // 預設值，可被 filter 覆寫
            'daily_cap' => 0,  // 無上限（解一個給一次）
            'label'     => '解鎖徽章',
        ),

    );

    /**
     * 允許其他模組／後台修改 EXP 規則
     *
     * @param array $rules
     */
    return apply_filters( 'smacg_exp_rules', $rules );
}

/**
 * 取得指定 action 的 EXP 數值
 *
 * @param string $action_key
 * @return int
 */
function smacg_get_exp_value( $action_key ) {
    $rules = smacg_exp_rules();
    return isset( $rules[ $action_key ]['exp'] ) ? (int) $rules[ $action_key ]['exp'] : 0;
}

/**
 * 取得指定 action 的每日上限
 *
 * @param string $action_key
 * @return int  0 = 無上限, -1 = 一生一次, 其他 = 每日次數
 */
function smacg_get_exp_daily_cap( $action_key ) {
    $rules = smacg_exp_rules();
    return isset( $rules[ $action_key ]['daily_cap'] ) ? (int) $rules[ $action_key ]['daily_cap'] : 0;
}

/**
 * 取得指定 action 的標籤
 *
 * @param string $action_key
 * @return string
 */
function smacg_get_exp_label( $action_key ) {
    $rules = smacg_exp_rules();
    return isset( $rules[ $action_key ]['label'] ) ? (string) $rules[ $action_key ]['label'] : $action_key;
}
