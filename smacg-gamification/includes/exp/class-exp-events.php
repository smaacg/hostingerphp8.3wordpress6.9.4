<?php
/**
 * EXP 事件監聽器
 *
 * 原檔：blocksy-child/inc/exp-events.php v1.2.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Exp;

defined( 'ABSPATH' ) || exit;

class Events {

    public static function init() {
        // 註冊獎勵
        add_action( 'user_register', [ __CLASS__, 'on_register' ], 20, 1 );

        // 每日登入 + 連續登入
        add_action( 'wp_login', [ __CLASS__, 'on_login' ], 20, 2 );

        // 留言
        add_action( 'comment_post', [ __CLASS__, 'on_comment_post' ], 20, 2 );
        add_action( 'transition_comment_status', [ __CLASS__, 'on_comment_approved' ], 20, 3 );

        // 追蹤
        add_action( 'smacg_user_followed', [ __CLASS__, 'on_followed' ], 20, 2 );

        // 觀看清單／評分（給 anime-sync-pro 呼叫）
        add_action( 'smacg_watchlist_completed', [ __CLASS__, 'on_watchlist_completed' ], 20, 2 );
        add_action( 'smacg_watchlist_added',     [ __CLASS__, 'on_watchlist_added' ], 20, 2 );
        add_action( 'smacg_rating_added',        [ __CLASS__, 'on_rating_added' ], 20, 3 );

        // GamiPress 徽章
        add_action( 'gamipress_award_achievement', [ __CLASS__, 'on_badge_unlocked' ], 20, 2 );

        // Cron
        add_action( 'init', [ __CLASS__, 'maybe_schedule_daily_reset' ] );
        add_action( 'smacg_exp_daily_reset', [ __CLASS__, 'on_daily_reset' ] );
        add_action( 'switch_theme', [ __CLASS__, 'clear_cron' ] );
    }

    /* ---------------------------------------------------
     * 核心：發 EXP + 升級偵測
     * --------------------------------------------------- */

    /**
     * 發放 EXP 並自動套用每日上限／一生一次限制 + 偵測升級
     */
    public static function award_with_cap( $uid, $action_key, $extra_args = [] ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return false;

        // 主題函式還沒載入 → 不發
        if ( ! function_exists( 'smacg_award_exp' ) || ! function_exists( 'smacg_get_user_exp' ) ) {
            return false;
        }

        $exp   = Config::get_exp( $action_key );
        $cap   = Config::get_cap( $action_key );
        $label = Config::get_label( $action_key );

        if ( $exp <= 0 ) return false;

        // 上限檢查
        if ( $cap === -1 ) {
            $meta_key = 'smacg_exp_once_' . $action_key;
            if ( get_user_meta( $uid, $meta_key, true ) ) return false;
            update_user_meta( $uid, $meta_key, current_time( 'mysql' ) );

        } elseif ( $cap > 0 ) {
            $today    = current_time( 'Y-m-d' );
            $meta_key = 'smacg_exp_daily_' . $action_key;
            $data     = get_user_meta( $uid, $meta_key, true );

            if ( ! is_array( $data ) || ! isset( $data['date'] ) || $data['date'] !== $today ) {
                $data = [ 'date' => $today, 'count' => 0 ];
            }
            if ( $data['count'] >= $cap ) return false;
            $data['count']++;
            update_user_meta( $uid, $meta_key, $data );
        }

        // 升級偵測：發前
        $level_before = \smacg_calc_user_level( \smacg_get_user_exp( $uid ) );

        // 實際發放（呼叫主題函式）
        $result = \smacg_award_exp( $uid, $exp, $label, $extra_args );

        // 升級偵測：發後
        if ( $result ) {
            $level_after = \smacg_calc_user_level( \smacg_get_user_exp( $uid ) );
            if ( $level_after > $level_before ) {
                self::handle_level_up( $uid, $level_before, $level_after );
            }
        }

        return $result;
    }

    /**
     * 處理升級（可能跨多級）
     */
    public static function handle_level_up( $uid, $from_level, $to_level ) {
        if ( $to_level <= $from_level ) return;

        $level_title = '';
        if ( function_exists( 'smacg_get_level_title' ) ) {
            $level_title = \smacg_get_level_title( $to_level );
        } elseif ( function_exists( 'smacg_get_user_level_info' ) ) {
            $info        = \smacg_get_user_level_info( $uid );
            $level_title = isset( $info['title'] ) ? $info['title'] : '';
        }

        do_action( 'smacg_level_up', $uid, $to_level, $level_title );

        foreach ( [ 10, 30, 70, 120, 200 ] as $milestone ) {
            if ( $from_level < $milestone && $to_level >= $milestone ) {
                $meta_key = 'smacg_milestone_lv_' . $milestone;
                if ( ! get_user_meta( $uid, $meta_key, true ) ) {
                    update_user_meta( $uid, $meta_key, current_time( 'mysql' ) );
                    do_action( 'smacg_level_milestone', $uid, $milestone, $from_level, $to_level );
                }
            }
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[SMACG] User #%d level up: %d → %d (%s)', $uid, $from_level, $to_level, $level_title ) );
        }
    }

    /* ---------------------------------------------------
     * Hook handlers
     * --------------------------------------------------- */

    public static function on_register( $user_id ) {
        self::award_with_cap( $user_id, 'register' );
    }

    public static function on_login( $user_login, $user ) {
        if ( ! $user instanceof \WP_User ) return;

        $uid   = (int) $user->ID;
        $today = current_time( 'Y-m-d' );

        $last = get_user_meta( $uid, 'smacg_last_login_date', true );
        if ( $last === $today ) return;

        self::award_with_cap( $uid, 'daily_login' );

        $streak = (int) get_user_meta( $uid, 'smacg_login_streak', true );
        if ( $last ) {
            $yesterday = date( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );
            $streak = ( $last === $yesterday ) ? $streak + 1 : 1;
        } else {
            $streak = 1;
        }
        update_user_meta( $uid, 'smacg_last_login_date', $today );
        update_user_meta( $uid, 'smacg_login_streak', $streak );

        if ( $streak === 7 ) {
            self::award_with_cap( $uid, 'streak_7' );
            do_action( 'smacg_streak_milestone', $uid, 7 );
        } elseif ( $streak === 30 ) {
            self::award_with_cap( $uid, 'streak_30' );
            do_action( 'smacg_streak_milestone', $uid, 30 );
        }
    }

    public static function on_comment_post( $comment_id, $comment_approved ) {
        if ( $comment_approved !== 1 && $comment_approved !== '1' ) return;
        $comment = get_comment( $comment_id );
        if ( ! $comment || empty( $comment->user_id ) ) return;

        self::award_with_cap( (int) $comment->user_id, 'comment_posted', [
            'comment_id' => (int) $comment_id,
        ] );
    }

    public static function on_comment_approved( $new_status, $old_status, $comment ) {
        if ( $new_status !== 'approved' || $old_status === 'approved' ) return;
        if ( empty( $comment->user_id ) ) return;

        $meta_key = 'smacg_exp_comment_' . $comment->comment_ID;
        if ( get_user_meta( $comment->user_id, $meta_key, true ) ) return;

        if ( self::award_with_cap( (int) $comment->user_id, 'comment_posted', [
            'comment_id' => (int) $comment->comment_ID,
        ] ) ) {
            update_user_meta( $comment->user_id, $meta_key, 1 );
        }
    }

    public static function on_followed( $follower_id, $target_id ) {
        self::award_with_cap( (int) $follower_id, 'follow_action', [ 'target_id' => (int) $target_id ] );
        self::award_with_cap( (int) $target_id,   'gained_follower', [ 'follower_id' => (int) $follower_id ] );
    }

    public static function on_watchlist_completed( $uid, $anime_id ) {
        self::award_with_cap( (int) $uid, 'watchlist_completed', [ 'anime_id' => (int) $anime_id ] );
    }

    public static function on_watchlist_added( $uid, $anime_id ) {
        self::award_with_cap( (int) $uid, 'watchlist_added', [ 'anime_id' => (int) $anime_id ] );
    }

    public static function on_rating_added( $uid, $anime_id, $rating ) {
        self::award_with_cap( (int) $uid, 'rating_added', [
            'anime_id' => (int) $anime_id,
            'rating'   => $rating,
        ] );
    }

    public static function on_badge_unlocked( $user_id, $achievement_id ) {
        $achievement = get_post( $achievement_id );
        if ( ! $achievement ) return;
        if ( $achievement->post_type !== SMACG_BADGE_SLUG ) return;

        $meta_key = 'smacg_exp_badge_' . $achievement_id;
        if ( get_user_meta( $user_id, $meta_key, true ) ) return;
        update_user_meta( $user_id, $meta_key, 1 );

        $custom_exp = (int) get_post_meta( $achievement_id, '_smacg_badge_exp', true );

        if ( ! function_exists( 'smacg_award_exp' ) || ! function_exists( 'smacg_get_user_exp' ) ) return;

        $level_before = \smacg_calc_user_level( \smacg_get_user_exp( $user_id ) );

        if ( $custom_exp > 0 ) {
            \smacg_award_exp( $user_id, $custom_exp, '解鎖徽章：' . $achievement->post_title, [
                'achievement_id' => $achievement_id,
            ] );
        } else {
            self::award_with_cap( $user_id, 'badge_unlocked', [ 'achievement_id' => $achievement_id ] );
        }

        if ( $custom_exp > 0 ) {
            $level_after = \smacg_calc_user_level( \smacg_get_user_exp( $user_id ) );
            if ( $level_after > $level_before ) {
                self::handle_level_up( $user_id, $level_before, $level_after );
            }
        }
    }

    /* ---------------------------------------------------
     * Cron
     * --------------------------------------------------- */
    public static function maybe_schedule_daily_reset() {
        if ( ! wp_next_scheduled( 'smacg_exp_daily_reset' ) ) {
            $timestamp = strtotime( 'tomorrow 00:05:00', current_time( 'timestamp' ) );
            wp_schedule_event( $timestamp, 'daily', 'smacg_exp_daily_reset' );
        }
    }

    public static function on_daily_reset() {
        do_action( 'smacg_exp_daily_reset_done' );
    }

    public static function clear_cron() {
        wp_clear_scheduled_hook( 'smacg_exp_daily_reset' );
    }
}

Events::init();
