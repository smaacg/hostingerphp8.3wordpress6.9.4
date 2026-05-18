<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * EXP 事件監聽
 *
 * v2.6.0：award_with_cap() 在發放 EXP 後同步寫入 Rank_Season（賽季積分）
 * v2.6.1 (2026-05-18)：Fix #11 — daily_reset() 改用單一 DELETE SQL，避免
 *                       N 筆 usermeta 各跑一次 DELETE 造成 cron 拖時間。
 */
class Exp_Events {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'user_register', function ( $uid ) {
            self::award_with_cap( $uid, 'register' );
        }, 20 );

        add_action( 'wp_login', [ __CLASS__, 'on_login' ], 20, 2 );

        add_action( 'comment_post', [ __CLASS__, 'on_comment_post' ], 20, 3 );
        add_action( 'transition_comment_status', [ __CLASS__, 'on_comment_approved' ], 20, 3 );

        add_action( 'smacg_user_followed', [ __CLASS__, 'on_follow' ], 20, 2 );

        add_action( 'smacg_watchlist_completed', function ( $uid ) {
            self::award_with_cap( $uid, 'watchlist_complete' );
        }, 20 );
        add_action( 'smacg_watchlist_added', function ( $uid ) {
            self::award_with_cap( $uid, 'watchlist_add' );
        }, 20 );
        add_action( 'smacg_rating_added', function ( $uid ) {
            self::award_with_cap( $uid, 'rating_add' );
        }, 20 );

        add_action( 'gamipress_award_achievement', [ __CLASS__, 'on_badge_unlock' ], 20, 2 );

        add_action( 'smacg_exp_daily_reset', [ __CLASS__, 'daily_reset' ] );
    }

    public static function award_with_cap( $uid, $action_key, $extra_args = [] ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return false;

        $rule = Exp_Config::get( $action_key );
        if ( ! $rule ) return false;

        $amount = (int) ( $extra_args['exp_override'] ?? $rule['exp'] );
        if ( $amount <= 0 ) return false;

        if ( $rule['cap_type'] === 'once' && $rule['cap_key'] ) {
            $meta = 'smacg_exp_once_' . $rule['cap_key'];
            if ( get_user_meta( $uid, $meta, true ) ) return false;
            update_user_meta( $uid, $meta, current_time( 'mysql' ) );
        } elseif ( $rule['cap_type'] === 'daily' && $rule['cap_key'] ) {
            $today = current_time( 'Ymd' );
            $meta  = 'smacg_exp_daily_' . $rule['cap_key'] . '_' . $today;
            $cnt   = (int) get_user_meta( $uid, $meta, true );
            $max   = (int) ( $rule['daily_max'] ?? 1 );
            if ( $cnt >= $max ) return false;
            update_user_meta( $uid, $meta, $cnt + 1 );
        }

        $before = function_exists( 'smacg_calc_level_from_exp' )
            ? smacg_calc_level_from_exp( Gamipress_Bridge::get_user_exp( $uid ) )
            : 0;

        $reason = $extra_args['reason'] ?? ( 'EXP: ' . $action_key );
        $ok = Gamipress_Bridge::award_exp( $uid, $amount, $reason, $extra_args );
        if ( ! $ok ) return false;

        // ★ v2.6.0：同步寫入賽季積分（一套分數兩個用途：等級 + 段位）
        if ( class_exists( '\SMACG\Gamification\Rank_Season' ) ) {
            Rank_Season::add_score( $uid, $amount );
        }

        $after = function_exists( 'smacg_calc_level_from_exp' )
            ? smacg_calc_level_from_exp( Gamipress_Bridge::get_user_exp( $uid ) )
            : 0;

        if ( $after > $before ) {
            self::handle_level_up( $uid, $before, $after );
        }

        return true;
    }

    public static function handle_level_up( $uid, $from_level, $to_level ) {
        $job = function_exists( 'smacg_get_job_by_level' )
            ? smacg_get_job_by_level( $to_level )
            : '';

        do_action( 'smacg_level_up', $uid, $to_level, $job );

        $milestones = [ 10, 30, 70, 120, 200 ];
        foreach ( $milestones as $m ) {
            if ( $from_level < $m && $to_level >= $m ) {
                $meta = 'smacg_milestone_lv_' . $m;
                if ( get_user_meta( $uid, $meta, true ) ) continue;
                update_user_meta( $uid, $meta, current_time( 'mysql' ) );
                do_action( 'smacg_level_milestone', $uid, $m, $from_level, $to_level );

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf( '[SMACG] user #%d hit milestone Lv.%d', $uid, $m ) );
                }
            }
        }
    }

    public static function on_login( $user_login, $user ) {
        if ( ! $user instanceof \WP_User ) return;
        $uid = (int) $user->ID;

        self::award_with_cap( $uid, 'daily_login' );

        $today  = current_time( 'Ymd' );
        $last   = get_user_meta( $uid, 'smacg_last_login_date', true );
        $streak = (int) get_user_meta( $uid, 'smacg_login_streak', true );

        if ( $last === $today ) return;

        $yesterday = date( 'Ymd', strtotime( '-1 day', current_time( 'timestamp' ) ) );
        $streak    = ( $last === $yesterday ) ? ( $streak + 1 ) : 1;

        update_user_meta( $uid, 'smacg_last_login_date', $today );
        update_user_meta( $uid, 'smacg_login_streak',    $streak );

        if ( $streak === 7 ) {
            self::award_with_cap( $uid, 'streak_7' );
            do_action( 'smacg_streak_milestone', $uid, 7 );
        } elseif ( $streak === 30 ) {
            self::award_with_cap( $uid, 'streak_30' );
            do_action( 'smacg_streak_milestone', $uid, 30 );
        }
    }

    public static function on_comment_post( $comment_id, $approved, $commentdata ) {
        if ( $approved !== 1 ) return;
        $uid = (int) ( $commentdata['user_id'] ?? 0 );
        if ( $uid <= 0 ) return;

        $meta = 'smacg_exp_comment_' . $comment_id;
        if ( get_user_meta( $uid, $meta, true ) ) return;
        update_user_meta( $uid, $meta, 1 );

        self::award_with_cap( $uid, 'comment_post' );
    }

    public static function on_comment_approved( $new_status, $old_status, $comment ) {
        if ( $new_status !== 'approved' || $old_status === 'approved' ) return;
        $uid = (int) $comment->user_id;
        if ( $uid <= 0 ) return;

        $meta = 'smacg_exp_comment_' . $comment->comment_ID;
        if ( get_user_meta( $uid, $meta, true ) ) return;
        update_user_meta( $uid, $meta, 1 );

        self::award_with_cap( $uid, 'comment_post' );
    }

    public static function on_follow( $follower_id, $followee_id ) {
        $follower_id = (int) $follower_id;
        $followee_id = (int) $followee_id;
        if ( $follower_id > 0 ) self::award_with_cap( $follower_id, 'follow_action' );
        if ( $followee_id > 0 ) self::award_with_cap( $followee_id, 'followed_by' );
    }

    public static function on_badge_unlock( $user_id, $achievement_id ) {
        $user_id        = (int) $user_id;
        $achievement_id = (int) $achievement_id;
        if ( $user_id <= 0 || $achievement_id <= 0 ) return;

        $post = get_post( $achievement_id );
        if ( ! $post || $post->post_type !== SMACG_BADGE_SLUG ) return;

        $meta = 'smacg_exp_badge_' . $achievement_id;
        if ( get_user_meta( $user_id, $meta, true ) ) return;
        update_user_meta( $user_id, $meta, 1 );

        $custom = (int) get_post_meta( $achievement_id, '_smacg_badge_exp', true );
        $args   = $custom > 0
            ? [ 'exp_override' => $custom, 'achievement_id' => $achievement_id, 'reason' => 'Badge: ' . $post->post_title ]
            : [ 'achievement_id' => $achievement_id, 'reason' => 'Badge: ' . $post->post_title ];

        self::award_with_cap( $user_id, 'badge_unlock', $args );
    }

    /**
     * 每日清理：刪除 2 天前以上的 smacg_exp_daily_* usermeta
     *
     * ★ Fix #11 (2026-05-18)：
     * 舊版作法是先 SELECT 所有 smacg_exp_daily_% 的 row，PHP 端逐筆 preg_match
     * 抓出日期，再逐筆 DELETE。當會員 / 累積天數多時會放大成上千次 query。
     * 改成單一 DELETE SQL，用 SUBSTRING 抽出 meta_key 結尾 8 位日期字串直接比較。
     *
     * meta_key 命名規則：smacg_exp_daily_{cap_key}_{YYYYMMDD}
     * 故結尾 8 碼 = SUBSTRING(meta_key, LENGTH(meta_key) - 7)
     */
    public static function daily_reset() {
        global $wpdb;
        $two_days_ago = date( 'Ymd', strtotime( '-2 days', current_time( 'timestamp' ) ) );

        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta}
             WHERE meta_key LIKE %s
               AND LENGTH(meta_key) >= 8
               AND SUBSTRING(meta_key, LENGTH(meta_key) - 7) REGEXP '^[0-9]{8}$'
               AND SUBSTRING(meta_key, LENGTH(meta_key) - 7) < %s",
            'smacg_exp_daily_%',
            $two_days_ago
        ) );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[SMACG] Daily EXP reset complete: %d rows deleted', (int) $deleted ) );
        }
    }
}
