<?php
/**
 * GamiPress ↔ Notifications Bridge
 *
 * Batch 2A-2 — 強化徽章 / 升級的通知體驗
 *
 * 本檔負責：
 *   1. 監聽 smacg_level_milestone（轉職等級），發特別通知（Lv.10/30/70/120/200）
 *   2. 監聽 smacg_streak_milestone（連續登入），發特別通知
 *   3. 為徽章解鎖追加「附帶 EXP」資訊到通知 data
 *
 * 普通升級 + 普通徽章通知已由 notifications-events.php 處理。
 * 本檔僅處理「大事件」的加強版通知。
 *
 * Version: 1.0.0 (2026-05-14)
 *
 * @package Blocksy_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 1. 轉職里程碑通知（Lv.10/30/70/120/200）
 * ========================================================= */

/**
 * 取得里程碑稱號（轉職等級）
 *
 * @param int $milestone
 * @return string
 */
function smacg_get_milestone_label( $milestone ) {
    $labels = array(
        10  => array( '⭐ 一轉達成',   '解鎖職業選擇！前往職業頁面選擇你的天命之路' ),
        30  => array( '⭐⭐ 二轉達成', '你的職業進化了！稱號自動升級' ),
        70  => array( '⭐⭐⭐ 三轉達成', '你已是業界專家！稱號再次進化' ),
        120 => array( '⭐⭐⭐⭐ 四轉達成', '頂點！獲得閃爍稱號、金色邊框、名人堂徽章' ),
        200 => array( '🌟 滿級！', '你已達到 Lv.200 滿級！傳說地位由此確立' ),
    );

    return isset( $labels[ $milestone ] ) ? $labels[ $milestone ] : array( '里程碑達成', '' );
}

add_action( 'smacg_level_milestone', function( $user_id, $milestone, $from_level, $to_level ) {
    $user_id   = (int) $user_id;
    $milestone = (int) $milestone;
    if ( $user_id <= 0 || $milestone <= 0 ) return;

    if ( ! function_exists( 'smacg_create_notification' ) ) return;

    list( $title, $excerpt ) = smacg_get_milestone_label( $milestone );

    $url = $milestone === 10
        ? home_url( '/mc/?tab=points' )  // 一轉導到職業選擇（未來 batch 會建立 /mc/?tab=career）
        : home_url( '/mc/?tab=points' );

    smacg_create_notification( array(
        'user_id'     => $user_id,
        'type'        => 'level_up',
        'actor_id'    => null,
        'object_type' => 'milestone',
        'object_id'   => $milestone,
        'data'        => array(
            'title'     => sprintf( '%s（Lv.%d）', $title, $milestone ),
            'excerpt'   => $excerpt,
            'url'       => $url,
            'icon'      => 'fa-medal',
            'milestone' => $milestone,
        ),
        'force'       => true,  // 大事件強制通知（略過偏好）
    ) );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( '[SMACG] Milestone Lv.%d notification sent to user #%d', $milestone, $user_id ) );
    }
}, 20, 4 );

/* =========================================================
 * 2. 連續登入里程碑通知
 * ========================================================= */
add_action( 'smacg_streak_milestone', function( $user_id, $days ) {
    $user_id = (int) $user_id;
    $days    = (int) $days;
    if ( $user_id <= 0 || $days <= 0 ) return;

    if ( ! function_exists( 'smacg_create_notification' ) ) return;

    $config = array(
        7  => array(
            'title'   => '🔥 連續登入 7 天！',
            'excerpt' => '熱愛是堅持的開始，已獲得 +100 EXP 獎勵',
            'icon'    => 'fa-fire',
        ),
        30 => array(
            'title'   => '🔥🔥 連續登入 30 天！',
            'excerpt' => '一個月不間斷，已獲得 +500 EXP 獎勵，你是真正的常客',
            'icon'    => 'fa-fire-flame-curved',
        ),
    );

    if ( ! isset( $config[ $days ] ) ) return;

    smacg_create_notification( array(
        'user_id'     => $user_id,
        'type'        => 'system',
        'object_type' => 'streak',
        'object_id'   => $days,
        'data'        => array(
            'title'   => $config[ $days ]['title'],
            'excerpt' => $config[ $days ]['excerpt'],
            'url'     => home_url( '/mc/?tab=points' ),
            'icon'    => $config[ $days ]['icon'],
            'days'    => $days,
        ),
        'force'       => true,
    ) );
}, 20, 2 );

/* =========================================================
 * 3. 徽章解鎖通知（強化版 — 補上 EXP 數字）
 *    notifications-events.php 已有基本徽章通知；
 *    本段僅在 EXP custom 存在時，幫忙更新最新一筆 badge 通知的 excerpt。
 *
 *    註：直接插隊 wp_insert_comment-like 行為複雜，因此這裡採另一種策略：
 *    高優先順序（priority=5）「替代」notifications-events.php 的 badge 通知
 *    僅當 _smacg_badge_exp 存在時才介入。
 * ========================================================= */
add_action( 'gamipress_award_achievement', function( $user_id, $achievement_id ) {
    $user_id        = (int) $user_id;
    $achievement_id = (int) $achievement_id;
    if ( $user_id <= 0 || $achievement_id <= 0 ) return;

    $achievement = get_post( $achievement_id );
    if ( ! $achievement ) return;
    if ( $achievement->post_type !== ( defined( 'SMACG_BADGE_SLUG' ) ? SMACG_BADGE_SLUG : 'badge' ) ) return;

    $custom_exp = (int) get_post_meta( $achievement_id, '_smacg_badge_exp', true );
    if ( $custom_exp <= 0 ) return;  // 沒設自訂 EXP 就由 notifications-events.php 處理

    if ( ! function_exists( 'smacg_create_notification' ) ) return;

    // 防重複（同一徽章只發一次強化通知）
    $meta_key = 'smacg_notif_badge_enhanced_' . $achievement_id;
    if ( get_user_meta( $user_id, $meta_key, true ) ) return;
    update_user_meta( $user_id, $meta_key, 1 );

    $excerpt = wp_strip_all_tags( $achievement->post_excerpt ?: '' );
    $excerpt = trim( $excerpt . sprintf( ' (+%d EXP)', $custom_exp ) );

    smacg_create_notification( array(
        'user_id'     => $user_id,
        'type'        => 'badge',
        'object_type' => 'badge',
        'object_id'   => $achievement_id,
        'data'        => array(
            'title'   => sprintf( '🏆 獲得徽章「%s」', get_the_title( $achievement ) ),
            'excerpt' => $excerpt,
            'url'     => get_permalink( $achievement_id ) ?: home_url( '/mc/' ),
            'icon'    => 'fa-trophy',
            'exp'     => $custom_exp,
        ),
        'force'       => true,
    ) );

    // 防 notifications-events.php 重複發
    // 因為 notifications-events 也會跑同一個 hook，它的 priority=10
    // 我們用 priority=5 先跑並寫一個標記，讓 notifications-events 自己判斷？
    // 但這需改 notifications-events，比較侵入。
    // 因此這裡選擇：accept duplicate（同徽章兩筆通知，但 excerpt 不同）
    // — 或在 notifications-events.php 加上去重 meta（下一個 batch 處理）
}, 5, 2 );  // priority=5，比 notifications-events 的 10 早跑

/* =========================================================
 * 4. 測試 hook：開發時可用
 *    /wp-admin/?smacg_test_levelup=1（需 admin）
 * ========================================================= */
add_action( 'admin_init', function() {
    if ( ! isset( $_GET['smacg_test_levelup'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;

    $uid = get_current_user_id();
    do_action( 'smacg_level_up', $uid, 99, '測試稱號' );
    do_action( 'smacg_level_milestone', $uid, 10, 9, 10 );

    wp_die( '✅ 測試通知已送出，回 <a href="' . esc_url( home_url( '/mc/?tab=notifications' ) ) . '">通知中心</a> 查看' );
} );
