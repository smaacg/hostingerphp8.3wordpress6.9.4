<?php
/**
 * 主題模板 / 舊外掛仍使用的程序式函式名稱橋接。
 * 全部用 function_exists 包住，避免 plugin 在主題尚未停用對應檔時 fatal。
 *
 * @package SMACG_Gamification
 */
defined( 'ABSPATH' ) || exit;

use SMACG\Gamification\Gamipress_Bridge;
use SMACG\Gamification\Level_System;
use SMACG\Gamification\Ranking_System;
use SMACG\Gamification\Event_Tracker;

/* ==========================================================
 * GamiPress
 * ========================================================== */
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

/* ==========================================================
 * Level
 * ========================================================== */
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

/* ==========================================================
 * EXP event trigger
 * ========================================================== */
if ( ! function_exists( 'smacg_trigger_exp_event' ) ) {
    function smacg_trigger_exp_event( $uid, $action_key, $extra_args = [] ) {
        return \SMACG\Gamification\Exp_Events::award_with_cap( $uid, $action_key, $extra_args );
    }
}

/* ==========================================================
 * Ranking
 * ========================================================== */
if ( ! function_exists( 'smacg_ranking_get' ) ) {
    function smacg_ranking_get( $type, $page = 1, $per_page = 20 ) {
        return Ranking_System::get( $type, $page, $per_page );
    }
}
if ( ! function_exists( 'smacg_ranking_user_position' ) ) {
    function smacg_ranking_user_position( $type, $uid ) {
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

/* ==========================================================
 * Season Event
 * ========================================================== */
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
