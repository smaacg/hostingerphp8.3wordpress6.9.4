<?php
/**
 * 主題模板 / 舊外掛仍使用的程序式函式名稱橋接。
 *
 * v1.2.0：新增 Rank Season 函式
 *
 * @package SMACG_Gamification
 */
defined( 'ABSPATH' ) || exit;

use SMACG\Gamification\Gamipress_Bridge;
use SMACG\Gamification\Level_System;
use SMACG\Gamification\Ranking_System;
use SMACG\Gamification\Rank_Season;
use SMACG\Gamification\Rank_Tier;
use SMACG\Gamification\Event_Tracker;

if ( ! defined( 'SMACG_MAX_LEVEL' ) ) define( 'SMACG_MAX_LEVEL', 200 );

/* ========================================================== GamiPress ========================================================== */
if ( ! function_exists( 'smacg_gamipress_active' ) ) {
    function smacg_gamipress_active() { return Gamipress_Bridge::active(); }
}
if ( ! function_exists( 'smacg_get_user_exp' ) ) {
    function smacg_get_user_exp( $uid ) { return Gamipress_Bridge::get_user_exp( $uid ); }
}
if ( ! function_exists( 'smacg_award_exp' ) ) {
    function smacg_award_exp( $uid, $amount, $reason = '', $args = [] ) {
        return Gamipress_Bridge::award_exp( $uid, $amount, $reason, $args );
    }
}
if ( ! function_exists( 'smacg_deduct_exp' ) ) {
    function smacg_deduct_exp( $uid, $amount, $reason = '' ) {
        return Gamipress_Bridge::deduct_exp( $uid, $amount, $reason );
    }
}
if ( ! function_exists( 'smacg_get_exp_log' ) ) {
    function smacg_get_exp_log( $uid, $limit = 50 ) { return Gamipress_Bridge::get_exp_log( $uid, $limit ); }
}
if ( ! function_exists( 'smacg_get_user_badge_ids' ) ) {
    function smacg_get_user_badge_ids( $uid ) { return Gamipress_Bridge::get_user_badge_ids( $uid ); }
}
if ( ! function_exists( 'smacg_get_user_badge_count' ) ) {
    function smacg_get_user_badge_count( $uid ) { return Gamipress_Bridge::get_user_badge_count( $uid ); }
}
if ( ! function_exists( 'smacg_award_badge' ) ) {
    function smacg_award_badge( $uid, $badge_post_id ) { return Gamipress_Bridge::award_badge( $uid, $badge_post_id ); }
}

/* ========================================================== Level ========================================================== */
if ( ! function_exists( 'smacg_get_user_level' ) ) {
    function smacg_get_user_level( $uid ) { return Level_System::get_user_level( $uid ); }
}
if ( ! function_exists( 'smacg_calc_level_from_exp' ) ) {
    function smacg_calc_level_from_exp( $exp ) { return Level_System::calc_level_from_exp( $exp ); }
}
if ( ! function_exists( 'smacg_get_level_table' ) ) {
    function smacg_get_level_table() { return Level_System::get_level_table(); }
}
if ( ! function_exists( 'smacg_get_job_by_level' ) ) {
    function smacg_get_job_by_level( $lv ) { return Level_System::get_job_by_level( $lv ); }
}
if ( ! function_exists( 'smacg_grant_exp' ) ) {
    function smacg_grant_exp( $uid, $amount, $reason = '' ) { return Level_System::grant_exp( $uid, $amount, $reason ); }
}

if ( ! function_exists( 'smacg_get_user_level_info' ) ) {
    function smacg_get_user_level_info( $uid ) {
        $uid = (int) $uid;
        $exp = function_exists( 'smacg_get_user_exp' ) ? (int) smacg_get_user_exp( $uid ) : 0;

        $level = function_exists( 'smacg_calc_level_from_exp' )
            ? (int) smacg_calc_level_from_exp( $exp ) : 1;
        if ( $level < 1 ) $level = 1;
        if ( $level > SMACG_MAX_LEVEL ) $level = SMACG_MAX_LEVEL;

        $is_max = ( $level >= SMACG_MAX_LEVEL );
        $table  = function_exists( 'smacg_get_level_table' ) ? smacg_get_level_table() : [];
        $cur    = isset( $table[ $level ] ) ? (int) $table[ $level ] : 0;
        $next   = $is_max ? $cur : ( isset( $table[ $level + 1 ] ) ? (int) $table[ $level + 1 ] : $cur );

        $total   = max( 0, $next - $cur );
        $in_lv   = max( 0, $exp - $cur );
        $to_next = $is_max ? 0 : max( 0, $next - $exp );
        $percent = ( $is_max || $total <= 0 ) ? 100 : min( 100, (int) floor( $in_lv * 100 / $total ) );

        $job   = function_exists( 'smacg_get_job_by_level' ) ? smacg_get_job_by_level( $level ) : [];
        return [
            'exp'             => $exp,
            'level'           => $level,
            'title'           => $job['title'] ?? '見習',
            'icon'            => $job['icon']  ?? '🌱',
            'percent'         => $percent,
            'in_level_exp'    => $in_lv,
            'level_total_exp' => $total,
            'to_next'         => $to_next,
            'is_max'          => $is_max,
            'next_floor'      => $next,
            'cur_floor'       => $cur,
        ];
    }
}

/* ========================================================== EXP event ========================================================== */
if ( ! function_exists( 'smacg_trigger_exp_event' ) ) {
    function smacg_trigger_exp_event( $uid, $action_key, $extra_args = [] ) {
        return \SMACG\Gamification\Exp_Events::award_with_cap( $uid, $action_key, $extra_args );
    }
}

/* ========================================================== Ranking ========================================================== */
if ( ! function_exists( 'smacg_ranking_get' ) ) {
    function smacg_ranking_get( $type, $page = 1, $per_page = 20 ) {
        return Ranking_System::get( $type, $page, $per_page );
    }
}
if ( ! function_exists( 'smacg_ranking_user_position' ) ) {
    function smacg_ranking_user_position( $type, $uid ) {
        // 賽季排位走獨立資料源
        if ( $type === 'rank_season' ) {
            $info = Rank_Season::get_user_info( (int) $uid );
            return $info['rank'] > 0 ? $info['rank'] : null;
        }
        return Ranking_System::user_position( $type, $uid );
    }
}
if ( ! function_exists( 'smacg_ranking_rebuild' ) ) {
    function smacg_ranking_rebuild( $type = null ) {
        return $type ? Ranking_System::rebuild_type( $type ) : Ranking_System::rebuild_all();
    }
}
if ( ! function_exists( 'smacg_user_appears_in_ranking' ) ) {
    function smacg_user_appears_in_ranking( $uid ) {
        return get_user_meta( (int) $uid, SMACG_RANKING_META_KEY, true ) !== '0';
    }
}

/* ========================================================== Rank Season（TFT 段位） ========================================================== */
if ( ! function_exists( 'smacg_get_user_rank_season_info' ) ) {
    /**
     * 取得使用者的當季段位資訊
     * 回傳：season_code, season_label, score, rank, tier{key,division,label,color,icon},
     *       progress{is_max, cur_min, next_min, to_next, percent, next_label}
     */
    function smacg_get_user_rank_season_info( $uid, $season_code = null ) {
        return Rank_Season::get_user_info( (int) $uid, $season_code );
    }
}
if ( ! function_exists( 'smacg_get_rank_season_leaderboard' ) ) {
    function smacg_get_rank_season_leaderboard( $limit = 100, $offset = 0, $season_code = null ) {
        return Rank_Season::get_leaderboard( $limit, $offset, $season_code );
    }
}
if ( ! function_exists( 'smacg_get_current_season_code' ) ) {
    function smacg_get_current_season_code() {
        return Rank_Tier::current_season_code();
    }
}
if ( ! function_exists( 'smacg_get_season_label' ) ) {
    function smacg_get_season_label( $code = null ) {
        return Rank_Tier::season_label( $code ?: Rank_Tier::current_season_code() );
    }
}
if ( ! function_exists( 'smacg_get_user_career_peak_tier' ) ) {
    /** 生涯最高段位（公開頁顯示用） */
    function smacg_get_user_career_peak_tier( $uid ) {
        $peak = get_user_meta( (int) $uid, 'smacg_rank_career_peak', true );
        return is_array( $peak ) ? $peak : null;
    }
}

/* ========================================================== Season Event ========================================================== */
if ( ! function_exists( 'smacg_get_event_meta' ) ) {
    function smacg_get_event_meta( $event_id ) {
        return \SMACG\Gamification\Event_CPT::get_meta( $event_id );
    }
}
if ( ! function_exists( 'smacg_get_user_event_progress' ) ) {
    function smacg_get_user_event_progress( $event_id, $uid ) {
        return Event_Tracker::get_progress( $event_id, $uid );
    }
}
if ( ! function_exists( 'smacg_get_event_leaderboard' ) ) {
    function smacg_get_event_leaderboard( $event_id, $limit = 20 ) {
        return Event_Tracker::get_leaderboard( $event_id, $limit );
    }
}
if ( ! function_exists( 'smacg_get_event_progress_count' ) ) {
    function smacg_get_event_progress_count( $event_id ) {
        return Event_Tracker::get_progress_count( $event_id );
    }
}
/* ========================================================== Level Guide 頁面用 ========================================================== */
if ( ! function_exists( 'smacg_get_all_exp_rules' ) ) {
    /**
     * 取得所有 EXP 行為規則 + 中文標籤
     */
    function smacg_get_all_exp_rules() {
        $rules  = \SMACG\Gamification\Exp_Config::rules();
        $labels = [
            'register'           => [ '🎉', '註冊帳號',       '完成註冊立即獲得' ],
            'daily_login'        => [ '📅', '每日登入',       '每天首次登入' ],
            'streak_7'           => [ '🔥', '連續登入 7 天',  '里程碑獎勵' ],
            'streak_30'          => [ '💎', '連續登入 30 天', '里程碑獎勵' ],
            'comment_post'       => [ '💬', '發表評論',       '評論通過審核後' ],
            'follow_action'      => [ '👥', '追蹤他人',       '追蹤其他會員' ],
            'followed_by'        => [ '⭐', '被他人追蹤',     '獲得新粉絲' ],
            'watchlist_add'      => [ '🎬', '加入清單',       '加入想看 / 在看 / 已看' ],
            'watchlist_complete' => [ '🏁', '完成觀看',       '將動畫標為已完成' ],
            'rating_add'         => [ '⭐', '評分動畫',       '為動畫評分' ],
            'badge_unlock'       => [ '🏆', '解鎖徽章',       '獲得任一徽章預設值' ],
        ];

        $out = [];
        foreach ( $rules as $key => $rule ) {
            $meta = $labels[ $key ] ?? [ '📌', $key, '' ];
            $out[ $key ] = [
                'key'      => $key,
                'icon'     => $meta[0],
                'label'    => $meta[1],
                'desc'     => $meta[2],
                'exp'      => (int) $rule['exp'],
                'cap_type' => $rule['cap_type'] ?? null,
                'cap_text' => self_describe_cap( $rule['cap_type'] ?? null ),
            ];
        }
        return $out;
    }
}

if ( ! function_exists( 'self_describe_cap' ) ) {
    function self_describe_cap( $cap_type ) {
        return [
            'once'  => '一次性',
            'daily' => '每日 1 次',
            null    => '無上限',
        ][ $cap_type ] ?? '無上限';
    }
}

if ( ! function_exists( 'smacg_get_all_level_jobs' ) ) {
    function smacg_get_all_level_jobs() {
        return \SMACG\Gamification\Level_System::get_jobs();
    }
}

if ( ! function_exists( 'smacg_get_full_level_table' ) ) {
    /**
     * 取得 Lv.1~200 累計 EXP 表（key = level, value = accumulated exp）
     */
    function smacg_get_full_level_table() {
        return \SMACG\Gamification\Level_System::get_level_table();
    }
}

if ( ! function_exists( 'smacg_get_all_rank_tiers' ) ) {
    /**
     * 取得 TFT 段位完整表
     */
    function smacg_get_all_rank_tiers() {
        $tiers = \SMACG\Gamification\Rank_Tier::TIERS;
        $out   = [];
        foreach ( $tiers as $row ) {
            $out[] = [
                'key'      => $row[0],
                'division' => $row[1],
                'min'      => $row[2],
                'label'    => $row[3],
                'color'    => $row[4],
                'icon'     => \SMACG\Gamification\Rank_Tier::ICONS[ $row[0] ] ?? '🎖️',
            ];
        }
        return $out;
    }
}

if ( ! function_exists( 'smacg_get_all_seasons_schedule' ) ) {
    /**
     * 取得本年度與下一年度的賽季時程
     */
    function smacg_get_all_seasons_schedule() {
        $cur_year = (int) date( 'Y', current_time( 'timestamp' ) );
        $codes    = [];
        foreach ( [ $cur_year, $cur_year + 1 ] as $y ) {
            foreach ( [ 'spring', 'summer', 'fall', 'winter' ] as $s ) {
                $codes[] = $y . '-' . $s;
            }
        }

        $now = current_time( 'timestamp' );
        $out = [];
        foreach ( $codes as $code ) {
            [ $start, $end ] = \SMACG\Gamification\Rank_Tier::season_range( $code );
            $status = ( $now < $start ) ? 'upcoming'
                    : ( ( $now <= $end ) ? 'active' : 'ended' );
            $out[] = [
                'code'   => $code,
                'label'  => \SMACG\Gamification\Rank_Tier::season_label( $code ),
                'start'  => $start,
                'end'    => $end,
                'status' => $status,
            ];
        }
        return $out;
    }
}
