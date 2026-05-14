<?php
/**
 * EXP 事件監聽器
 *
 * 監聽 WordPress 核心 hook 與站內自訂 hook，自動發放 EXP。
 * 所有發放走 smacg_award_exp_with_cap()，自動套用每日上限／一生一次限制。
 *
 * 監聽事件：
 *   - user_register             → 註冊獎勵
 *   - wp_login                  → 每日首次登入 + 連續登入判定
 *   - comment_post              → 發表留言
 *   - smacg_followed            → 追蹤他人（自訂 hook，由 follow-ajax 觸發）
 *   - smacg_follow_gained       → 被追蹤（自訂 hook）
 *   - smacg_watchlist_completed → 完成觀看（自訂 hook，預留給 anime-sync-pro）
 *   - smacg_watchlist_added     → 加入清單
 *   - smacg_rating_added        → 評分
 *   - gamipress_award_achievement → 徽章解鎖
 *
 * Version: 1.0.0 (2026-05-14)
 * Batch: 2A-1
 *
 * @package Blocksy_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 0. 核心：帶上限的發 EXP（私有 API）
 * ========================================================= */

/**
 * 發放 EXP 並自動套用每日上限／一生一次限制
 *
 * @param int    $uid          用戶 ID
 * @param string $action_key   smacg_exp_rules() 中的 key
 * @param array  $extra_args   傳給 smacg_award_exp 的額外資料
 * @return bool 是否實際發放
 */
function smacg_award_exp_with_cap( $uid, $action_key, $extra_args = array() ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) return false;

    $exp   = smacg_get_exp_value( $action_key );
    $cap   = smacg_get_exp_daily_cap( $action_key );
    $label = smacg_get_exp_label( $action_key );

    if ( $exp <= 0 ) return false;

    // === 上限檢查 ===
    if ( $cap === -1 ) {
        // 一生一次
        $meta_key = 'smacg_exp_once_' . $action_key;
        if ( get_user_meta( $uid, $meta_key, true ) ) {
            return false;
        }
        update_user_meta( $uid, $meta_key, current_time( 'mysql' ) );

    } elseif ( $cap > 0 ) {
        // 每日 N 次
        $today    = current_time( 'Y-m-d' );
        $meta_key = 'smacg_exp_daily_' . $action_key;
        $data     = get_user_meta( $uid, $meta_key, true );

        if ( ! is_array( $data ) || ! isset( $data['date'] ) || $data['date'] !== $today ) {
            $data = array( 'date' => $today, 'count' => 0 );
        }

        if ( $data['count'] >= $cap ) {
            return false; // 超過每日上限
        }

        $data['count']++;
        update_user_meta( $uid, $meta_key, $data );
    }
    // $cap === 0 → 無上限，不檢查

    // === 實際發放 ===
    return smacg_award_exp( $uid, $exp, $label, $extra_args );
}

/* =========================================================
 * 1. 註冊獎勵（一生一次）
 * ========================================================= */
add_action( 'user_register', function( $user_id ) {
    smacg_award_exp_with_cap( $user_id, 'register' );
}, 20, 1 );

/* =========================================================
 * 2. 每日首次登入 + 連續登入判定
 * ========================================================= */
add_action( 'wp_login', function( $user_login, $user ) {
    if ( ! $user instanceof WP_User ) return;

    $uid   = (int) $user->ID;
    $today = current_time( 'Y-m-d' );

    // 取得最後登入日期
    $last_login_date = get_user_meta( $uid, 'smacg_last_login_date', true );

    // 已經今天登入過 → 不重複發
    if ( $last_login_date === $today ) {
        return;
    }

    // 發每日登入 EXP
    smacg_award_exp_with_cap( $uid, 'daily_login' );

    // === 連續登入判定 ===
    $streak = (int) get_user_meta( $uid, 'smacg_login_streak', true );

    if ( $last_login_date ) {
        $yesterday = date( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );
        if ( $last_login_date === $yesterday ) {
            $streak++;
        } else {
            $streak = 1; // 中斷重新計
        }
    } else {
        $streak = 1;
    }

    update_user_meta( $uid, 'smacg_last_login_date', $today );
    update_user_meta( $uid, 'smacg_login_streak', $streak );

    // 達到里程碑發獎勵
    if ( $streak === 7 ) {
        smacg_award_exp_with_cap( $uid, 'streak_7' );
        do_action( 'smacg_streak_milestone', $uid, 7 );
    } elseif ( $streak === 30 ) {
        smacg_award_exp_with_cap( $uid, 'streak_30' );
        do_action( 'smacg_streak_milestone', $uid, 30 );
    }

}, 20, 2 );

/* =========================================================
 * 3. 留言（WordPress 核心 comment）
 * ========================================================= */
add_action( 'comment_post', function( $comment_id, $comment_approved ) {
    // 只計算已核准的留言
    if ( $comment_approved !== 1 && $comment_approved !== '1' ) return;

    $comment = get_comment( $comment_id );
    if ( ! $comment || empty( $comment->user_id ) ) return;

    smacg_award_exp_with_cap( (int) $comment->user_id, 'comment_posted', array(
        'comment_id' => (int) $comment_id,
    ) );
}, 20, 2 );

// 若留言由 pending → approved（後台手動審核通過），也補發
add_action( 'transition_comment_status', function( $new_status, $old_status, $comment ) {
    if ( $new_status !== 'approved' || $old_status === 'approved' ) return;
    if ( empty( $comment->user_id ) ) return;

    // 防重複：已發過就跳過
    $meta_key = 'smacg_exp_comment_' . $comment->comment_ID;
    if ( get_user_meta( $comment->user_id, $meta_key, true ) ) return;

    if ( smacg_award_exp_with_cap( (int) $comment->user_id, 'comment_posted', array(
        'comment_id' => (int) $comment->comment_ID,
    ) ) ) {
        update_user_meta( $comment->user_id, $meta_key, 1 );
    }
}, 20, 3 );

/* =========================================================
 * 4. 追蹤系統（由 follow-ajax.php 觸發自訂 hook）
 * ========================================================= */
add_action( 'smacg_followed', function( $follower_id, $target_id ) {
    // 追蹤者得 EXP
    smacg_award_exp_with_cap( (int) $follower_id, 'follow_action', array(
        'target_id' => (int) $target_id,
    ) );

    // 被追蹤者也得 EXP
    smacg_award_exp_with_cap( (int) $target_id, 'gained_follower', array(
        'follower_id' => (int) $follower_id,
    ) );
}, 20, 2 );

/* =========================================================
 * 5. 觀看清單／評分（預留 hook，由 anime-sync-pro 未來呼叫）
 * ========================================================= */
add_action( 'smacg_watchlist_completed', function( $uid, $anime_id ) {
    smacg_award_exp_with_cap( (int) $uid, 'watchlist_completed', array(
        'anime_id' => (int) $anime_id,
    ) );
}, 20, 2 );

add_action( 'smacg_watchlist_added', function( $uid, $anime_id ) {
    smacg_award_exp_with_cap( (int) $uid, 'watchlist_added', array(
        'anime_id' => (int) $anime_id,
    ) );
}, 20, 2 );

add_action( 'smacg_rating_added', function( $uid, $anime_id, $rating ) {
    smacg_award_exp_with_cap( (int) $uid, 'rating_added', array(
        'anime_id' => (int) $anime_id,
        'rating'   => $rating,
    ) );
}, 20, 3 );

/* =========================================================
 * 6. 徽章解鎖 → 發 EXP
 * ========================================================= */
add_action( 'gamipress_award_achievement', function( $user_id, $achievement_id ) {
    // 只處理 type = badge
    $achievement = get_post( $achievement_id );
    if ( ! $achievement ) return;
    if ( $achievement->post_type !== SMACG_BADGE_SLUG ) return;

    // 防重複發 EXP（同一徽章只給一次）
    $meta_key = 'smacg_exp_badge_' . $achievement_id;
    if ( get_user_meta( $user_id, $meta_key, true ) ) return;
    update_user_meta( $user_id, $meta_key, 1 );

    // 允許每個徽章自訂 EXP 獎勵（GamiPress 自訂欄位 _smacg_badge_exp）
    $custom_exp = (int) get_post_meta( $achievement_id, '_smacg_badge_exp', true );
    if ( $custom_exp > 0 ) {
        smacg_award_exp( $user_id, $custom_exp, '解鎖徽章：' . $achievement->post_title, array(
            'achievement_id' => $achievement_id,
        ) );
    } else {
        smacg_award_exp_with_cap( $user_id, 'badge_unlocked', array(
            'achievement_id' => $achievement_id,
        ) );
    }
}, 20, 2 );

/* =========================================================
 * 7. 公開 API：給其他模組手動觸發
 * ========================================================= */

/**
 * 手動觸發 EXP 發放（外部模組推薦呼叫此函式）
 *
 * @param int    $uid
 * @param string $action_key
 * @param array  $extra
 * @return bool
 */
function smacg_trigger_exp_event( $uid, $action_key, $extra = array() ) {
    return smacg_award_exp_with_cap( $uid, $action_key, $extra );
}

/* =========================================================
 * 8. 重置每日紀錄（cron，每日 00:05 執行）
 * ========================================================= */

// 註冊 cron
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'smacg_exp_daily_reset' ) ) {
        // 設定為 Taipei 時區 00:05
        $timestamp = strtotime( 'tomorrow 00:05:00', current_time( 'timestamp' ) );
        wp_schedule_event( $timestamp, 'daily', 'smacg_exp_daily_reset' );
    }
} );

// 不做任何事（每日紀錄會在發放時自動判定日期重置，cron 純粹預留給未來擴充）
add_action( 'smacg_exp_daily_reset', function() {
    do_action( 'smacg_exp_daily_reset_done' );
} );

// 主題停用時清除 cron
add_action( 'switch_theme', function() {
    wp_clear_scheduled_hook( 'smacg_exp_daily_reset' );
} );
