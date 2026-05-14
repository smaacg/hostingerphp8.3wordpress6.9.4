<?php
/**
 * Legacy Function Bridge
 *
 * 把外掛 class 的方法重新導出為全域函式，保持與主題、其他模組的相容性。
 * 所有函式皆 wrapped in function_exists() 以避免與主題舊版重複定義。
 *
 * @package SMACG_Gamification
 */

defined( 'ABSPATH' ) || exit;

use SMACG\Gamification\Exp\Config       as ExpConfig;
use SMACG\Gamification\Exp\Events       as ExpEvents;
use SMACG\Gamification\Level\System     as LevelSystem;
use SMACG\Gamification\Level\Badge      as LevelBadge;
use SMACG\Gamification\Level\Career     as LevelCareer;

/* ============ EXP Config ============ */

if ( ! function_exists( 'smacg_exp_rules' ) ) {
    function smacg_exp_rules() { return ExpConfig::rules(); }
}
if ( ! function_exists( 'smacg_get_exp_value' ) ) {
    function smacg_get_exp_value( $action_key ) { return ExpConfig::get_exp( $action_key ); }
}
if ( ! function_exists( 'smacg_get_exp_daily_cap' ) ) {
    function smacg_get_exp_daily_cap( $action_key ) { return ExpConfig::get_cap( $action_key ); }
}
if ( ! function_exists( 'smacg_get_exp_label' ) ) {
    function smacg_get_exp_label( $action_key ) { return ExpConfig::get_label( $action_key ); }
}

/* ============ EXP Events ============ */

if ( ! function_exists( 'smacg_award_exp_with_cap' ) ) {
    function smacg_award_exp_with_cap( $uid, $action_key, $extra_args = [] ) {
        return ExpEvents::award_with_cap( $uid, $action_key, $extra_args );
    }
}
if ( ! function_exists( 'smacg_handle_level_up' ) ) {
    function smacg_handle_level_up( $uid, $from, $to ) {
        return ExpEvents::handle_level_up( $uid, $from, $to );
    }
}
if ( ! function_exists( 'smacg_trigger_exp_event' ) ) {
    function smacg_trigger_exp_event( $uid, $action_key, $extra = [] ) {
        return ExpEvents::award_with_cap( $uid, $action_key, $extra );
    }
}

/* ============ Level System ============ */

if ( ! function_exists( 'smacg_calc_user_level' ) ) {
    function smacg_calc_user_level( $exp ) { return LevelSystem::calc_level( $exp ); }
}
if ( ! function_exists( 'smacg_level_to_exp' ) ) {
    function smacg_level_to_exp( $level ) { return LevelSystem::level_to_exp( $level ); }
}
if ( ! function_exists( 'smacg_get_level_tier' ) ) {
    function smacg_get_level_tier( $level ) { return LevelSystem::get_tier( $level ); }
}
if ( ! function_exists( 'smacg_get_level_title' ) ) {
    function smacg_get_level_title( $level ) { return LevelSystem::get_title( $level ); }
}
if ( ! function_exists( 'smacg_get_user_level_info' ) ) {
    function smacg_get_user_level_info( $uid ) { return LevelSystem::get_user_info( $uid ); }
}
if ( ! function_exists( 'smacg_get_career_stage' ) ) {
    function smacg_get_career_stage( $level ) { return LevelSystem::get_career_stage( $level ); }
}
if ( ! function_exists( 'smacg_get_career_milestones' ) ) {
    function smacg_get_career_milestones() { return LevelSystem::get_career_milestones(); }
}
if ( ! function_exists( 'smacg_get_jobs' ) ) {
    function smacg_get_jobs() { return LevelSystem::get_jobs(); }
}
if ( ! function_exists( 'smacg_get_user_job' ) ) {
    function smacg_get_user_job( $uid ) { return LevelSystem::get_user_job( $uid ); }
}
if ( ! function_exists( 'smacg_set_user_job' ) ) {
    function smacg_set_user_job( $uid, $job_key ) { return LevelSystem::set_user_job( $uid, $job_key ); }
}
if ( ! function_exists( 'smacg_get_user_job_title' ) ) {
    function smacg_get_user_job_title( $uid ) { return LevelSystem::get_user_job_title( $uid ); }
}
if ( ! function_exists( 'smacg_grant_exp' ) ) {
    function smacg_grant_exp( $uid, $amount, $reason = '', $args = [] ) {
        return LevelSystem::grant_exp( $uid, $amount, $reason, $args );
    }
}

/* ============ Level Badge ============ */

if ( ! function_exists( 'smacg_render_level_badge' ) ) {
    function smacg_render_level_badge( $uid, $size = 'sm', $args = [] ) {
        return LevelBadge::render( $uid, $size, $args );
    }
}
if ( ! function_exists( 'smacg_pp_get_badge_count_text' ) ) {
    function smacg_pp_get_badge_count_text( $user_id ) {
        return LevelBadge::get_badge_count_text( $user_id );
    }
}

/* ============ Career (4 職業) ============ */

if ( ! function_exists( 'smacg_get_all_career_jobs' ) ) {
    function smacg_get_all_career_jobs() { return LevelCareer::get_all_jobs(); }
}
if ( ! function_exists( 'smacg_get_career_job_label' ) ) {
    function smacg_get_career_job_label( $job_key ) { return LevelCareer::get_job_label( $job_key ); }
}
if ( ! function_exists( 'smacg_get_user_career_job' ) ) {
    function smacg_get_user_career_job( $user_id ) { return LevelCareer::get_user_job( $user_id ); }
}
/* ============ Ranking System ============ */

use SMACG\Gamification\Ranking\System    as RankingSystem;
use SMACG\Gamification\Ranking\Privacy   as RankingPrivacy;
use SMACG\Gamification\Ranking\Leaderboard_View as LeaderboardView;

if ( ! function_exists( 'smacg_ranking_install_tables' ) ) {
    function smacg_ranking_install_tables() {
        require_once SMACG_GAMIFY_DIR . 'includes/class-activator.php';
        \SMACG\Gamification\Activator::install_ranking_tables();
    }
}
if ( ! function_exists( 'smacg_ranking_db_version' ) ) {
    function smacg_ranking_db_version() {
        return get_option( 'smacg_ranking_db_version', '0' );
    }
}
if ( ! function_exists( 'smacg_ranking_record_monthly_exp' ) ) {
    function smacg_ranking_record_monthly_exp( $uid, $amount, $reason = '' ) {
        return RankingSystem::record_monthly_exp( $uid, $amount, $reason );
    }
}
if ( ! function_exists( 'smacg_ranking_get_excluded_user_ids' ) ) {
    function smacg_ranking_get_excluded_user_ids() {
        return RankingSystem::get_excluded_user_ids();
    }
}
if ( ! function_exists( 'smacg_ranking_flush_excluded_cache' ) ) {
    function smacg_ranking_flush_excluded_cache() {
        return RankingSystem::flush_excluded_cache();
    }
}
if ( ! function_exists( 'smacg_ranking_compute' ) ) {
    function smacg_ranking_compute( $type, $limit = SMACG_RANKING_TOP_N ) {
        return RankingSystem::compute( $type, $limit );
    }
}
if ( ! function_exists( 'smacg_ranking_rebuild_type' ) ) {
    function smacg_ranking_rebuild_type( $type ) {
        return RankingSystem::rebuild_type( $type );
    }
}
if ( ! function_exists( 'smacg_ranking_rebuild_all' ) ) {
    function smacg_ranking_rebuild_all() {
        return RankingSystem::rebuild_all();
    }
}
if ( ! function_exists( 'smacg_ranking_get' ) ) {
    function smacg_ranking_get( $type, $page = 1, $per_page = SMACG_RANKING_PAGE_SIZE ) {
        return RankingSystem::get( $type, $page, $per_page );
    }
}
if ( ! function_exists( 'smacg_ranking_user_position' ) ) {
    function smacg_ranking_user_position( $uid, $type ) {
        return RankingSystem::user_position( $uid, $type );
    }
}
if ( ! function_exists( 'smacg_ranking_purge_old_monthly' ) ) {
    function smacg_ranking_purge_old_monthly() {
        return RankingSystem::purge_old_monthly();
    }
}

/* ============ Ranking Privacy ============ */

if ( ! function_exists( 'smacg_user_appears_in_ranking' ) ) {
    function smacg_user_appears_in_ranking( $uid ) {
        return RankingPrivacy::user_appears( $uid );
    }
}
if ( ! function_exists( 'smacg_ranking_privacy_localize_data' ) ) {
    function smacg_ranking_privacy_localize_data() {
        return RankingPrivacy::localize_data();
    }
}

/* ============ Leaderboard View ============ */

if ( ! function_exists( 'smacg_render_leaderboard_top' ) ) {
    function smacg_render_leaderboard_top( $type = 'exp_total', $limit = 10, $args = [] ) {
        return LeaderboardView::render( $type, $limit, $args );
    }
}
if ( ! function_exists( 'smacg_lb_default_title' ) ) {
    function smacg_lb_default_title( $type ) { return LeaderboardView::default_title( $type ); }
}
if ( ! function_exists( 'smacg_lb_type_icon' ) ) {
    function smacg_lb_type_icon( $type ) { return LeaderboardView::type_icon( $type ); }
}
if ( ! function_exists( 'smacg_lb_score_unit' ) ) {
    function smacg_lb_score_unit( $type ) { return LeaderboardView::score_unit( $type ); }
}
/* ============ Season Event ============ */

use SMACG\Gamification\SeasonEvent\CPT     as EventCPT;
use SMACG\Gamification\SeasonEvent\Tracker as EventTracker;
use SMACG\Gamification\SeasonEvent\Settle  as EventSettle;

/* ---- CPT helpers ---- */
if ( ! function_exists( 'smacg_event_task_options' ) ) {
    function smacg_event_task_options() { return EventCPT::task_options(); }
}
if ( ! function_exists( 'smacg_event_task_label' ) ) {
    function smacg_event_task_label( $key ) { return EventCPT::task_label( $key ); }
}
if ( ! function_exists( 'smacg_event_task_unit' ) ) {
    function smacg_event_task_unit( $key ) { return EventCPT::task_unit( $key ); }
}
if ( ! function_exists( 'smacg_get_event_meta' ) ) {
    function smacg_get_event_meta( $post_id ) { return EventCPT::get_meta( $post_id ); }
}
if ( ! function_exists( 'smacg_event_get_status' ) ) {
    function smacg_event_get_status( $post_id ) { return EventCPT::get_status( $post_id ); }
}
if ( ! function_exists( 'smacg_get_active_events' ) ) {
    function smacg_get_active_events( $limit = 10 ) { return EventCPT::get_active_events( $limit ); }
}
if ( ! function_exists( 'smacg_get_upcoming_events' ) ) {
    function smacg_get_upcoming_events( $limit = 10 ) { return EventCPT::get_upcoming_events( $limit ); }
}
if ( ! function_exists( 'smacg_event_get_badge_options' ) ) {
    function smacg_event_get_badge_options() { return EventCPT::get_badge_options(); }
}

/* ---- Tracker ---- */
if ( ! function_exists( 'smacg_event_install_tables' ) ) {
    function smacg_event_install_tables() {
        require_once SMACG_GAMIFY_DIR . 'includes/class-activator.php';
        \SMACG\Gamification\Activator::install_event_tables();
    }
}
if ( ! function_exists( 'smacg_event_active_ids_by_task' ) ) {
    function smacg_event_active_ids_by_task( $task_type ) {
        return EventTracker::active_ids_by_task( $task_type );
    }
}
if ( ! function_exists( 'smacg_event_bump_progress' ) ) {
    function smacg_event_bump_progress( $uid, $task_type, $delta = 1 ) {
        return EventTracker::bump_progress( $uid, $task_type, $delta );
    }
}
if ( ! function_exists( 'smacg_event_check_reached' ) ) {
    function smacg_event_check_reached( $event_id, $uid ) {
        return EventTracker::check_reached( $event_id, $uid );
    }
}
if ( ! function_exists( 'smacg_event_get_user_progress' ) ) {
    function smacg_event_get_user_progress( $event_id, $uid ) {
        return EventTracker::get_user_progress( $event_id, $uid );
    }
}
if ( ! function_exists( 'smacg_event_top_progress' ) ) {
    function smacg_event_top_progress( $event_id, $limit = 100 ) {
        return EventTracker::top_progress( $event_id, $limit );
    }
}
if ( ! function_exists( 'smacg_event_counts' ) ) {
    function smacg_event_counts( $event_id ) {
        return EventTracker::counts( $event_id );
    }
}
if ( ! function_exists( 'smacg_event_manual_grant_progress' ) ) {
    function smacg_event_manual_grant_progress( $event_id, $uid, $delta = 1 ) {
        return EventTracker::manual_grant_progress( $event_id, $uid, $delta );
    }
}

/* ---- Settle ---- */
if ( ! function_exists( 'smacg_event_settle_user' ) ) {
    function smacg_event_settle_user( $event_id, $uid, $meta = null ) {
        return EventSettle::settle_user( $event_id, $uid, $meta );
    }
}
if ( ! function_exists( 'smacg_event_compose_reward_text' ) ) {
    function smacg_event_compose_reward_text( $meta ) {
        return EventSettle::compose_reward_text( $meta );
    }
}
if ( ! function_exists( 'smacg_event_broadcast_end_notice' ) ) {
    function smacg_event_broadcast_end_notice( $event_id, $meta ) {
        return EventSettle::broadcast_end_notice( $event_id, $meta );
    }
}
if ( ! function_exists( 'smacg_event_take_final_snapshot' ) ) {
    function smacg_event_take_final_snapshot( $event_id ) {
        return EventSettle::take_final_snapshot( $event_id );
    }
}
