<?php
/**
 * 主題模板 / 舊外掛仍使用的程序式函式名稱橋接
 *
 * v2.0.0 (2026-05-15)
 *   - 改採 sqrt(exp/5) 公式 + 6 階會員稱號 + 8 職業天命之路
 *   - 廢除舊 8 階轉職稱號 / 4 職業 Career_Ajax
 *
 * @package SMACG_Gamification
 */
defined( 'ABSPATH' ) || exit;

use SMACG\Gamification\Gamipress_Bridge;
use SMACG\Gamification\Level_System;
use SMACG\Gamification\Career_Jobs;
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

/* ========================================================== Level（新版：sqrt + 6 階會員稱號）========================================================== */
if ( ! function_exists( 'smacg_calc_user_level' ) ) {
    function smacg_calc_user_level( $exp ) { return Level_System::calc_level_from_exp( $exp ); }
}
if ( ! function_exists( 'smacg_calc_level_from_exp' ) ) {
    function smacg_calc_level_from_exp( $exp ) { return Level_System::calc_level_from_exp( $exp ); }
}
if ( ! function_exists( 'smacg_level_to_exp' ) ) {
    function smacg_level_to_exp( $level ) { return Level_System::level_to_exp( $level ); }
}
if ( ! function_exists( 'smacg_get_level_table' ) ) {
    function smacg_get_level_table() { return Level_System::get_level_table(); }
}
if ( ! function_exists( 'smacg_get_full_level_table' ) ) {
    function smacg_get_full_level_table() { return Level_System::get_level_table(); }
}
if ( ! function_exists( 'smacg_get_user_level_info' ) ) {
    function smacg_get_user_level_info( $uid ) { return Level_System::get_user_level( $uid ); }
}
if ( ! function_exists( 'smacg_get_level_tier' ) ) {
    function smacg_get_level_tier( $level ) { return Level_System::get_tier( $level ); }
}
if ( ! function_exists( 'smacg_get_level_title' ) ) {
    function smacg_get_level_title( $level ) {
        $t = Level_System::get_tier( $level );
        return $t['icon'] . ' ' . $t['title'];
    }
}
if ( ! function_exists( 'smacg_get_all_member_tiers' ) ) {
    /** /level-guide/ 用：6 階會員稱號完整表 */
    function smacg_get_all_member_tiers() { return Level_System::get_all_tiers(); }
}
if ( ! function_exists( 'smacg_grant_exp' ) ) {
    function smacg_grant_exp( $uid, $amount, $reason = '', $args = [] ) {
        return Level_System::grant_exp( $uid, $amount, $reason );
    }
}

/* ========================================================== Career Jobs（8 職業 × 4 階稱號）========================================================== */
if ( ! function_exists( 'smacg_get_jobs' ) ) {
    function smacg_get_jobs() { return Career_Jobs::all(); }
}
if ( ! function_exists( 'smacg_get_career_milestones' ) ) {
    function smacg_get_career_milestones() { return Career_Jobs::milestones(); }
}
if ( ! function_exists( 'smacg_get_career_stage' ) ) {
    function smacg_get_career_stage( $level ) { return Career_Jobs::career_stage( $level ); }
}
if ( ! function_exists( 'smacg_get_user_job' ) ) {
    function smacg_get_user_job( $uid ) { return Career_Jobs::user_job( $uid ); }
}
if ( ! function_exists( 'smacg_set_user_job' ) ) {
    function smacg_set_user_job( $uid, $key ) { return Career_Jobs::set_user_job( $uid, $key ); }
}
if ( ! function_exists( 'smacg_get_user_job_title' ) ) {
    function smacg_get_user_job_title( $uid ) { return Career_Jobs::user_title( $uid ); }
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

/* ========================================================== Rank Season（TFT 段位）========================================================== */
if ( ! function_exists( 'smacg_get_user_rank_season_info' ) ) {
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
    function smacg_get_current_season_code() { return Rank_Tier::current_season_code(); }
}
if ( ! function_exists( 'smacg_get_season_label' ) ) {
    function smacg_get_season_label( $code = null ) {
        return Rank_Tier::season_label( $code ?: Rank_Tier::current_season_code() );
    }
}
if ( ! function_exists( 'smacg_get_user_career_peak_tier' ) ) {
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

/* ========================================================== Level Guide 頁面 ========================================================== */
if ( ! function_exists( 'smacg_get_all_exp_rules' ) ) {
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
                'cap_text' => smacg_describe_exp_cap( $rule['cap_type'] ?? null ),
            ];
        }
        return $out;
    }
}

if ( ! function_exists( 'smacg_describe_exp_cap' ) ) {
    function smacg_describe_exp_cap( $cap_type ) {
        return [
            'once'  => '一次性',
            'daily' => '每日 1 次',
            null    => '無上限',
        ][ $cap_type ] ?? '無上限';
    }
}

if ( ! function_exists( 'smacg_get_all_rank_tiers' ) ) {
    /** /level-guide/ 用：TFT 段位完整表 */
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
