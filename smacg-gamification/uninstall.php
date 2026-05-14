<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'SMACG_GAMIFY_PURGE_ON_UNINSTALL' ) || ! SMACG_GAMIFY_PURGE_ON_UNINSTALL ) {
    return;
}

global $wpdb;

/* User meta */
$prefixes = [
    'smacg_exp_once_',
    'smacg_exp_daily_',
    'smacg_exp_comment_',
    'smacg_exp_badge_',
    'smacg_milestone_lv_',
];
foreach ( $prefixes as $p ) {
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like( $p ) . '%'
    ) );
}

/* DB tables */
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smacg_monthly_exp" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smacg_rankings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}smacg_event_progress" );

/* Post meta（活動的 ended_flag、final_snapshot）— 只在 purge 模式下清 */
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_smacg_event_%'"
);

/* user meta smacg_event_titles — 保留稱號（用戶資產） */
/* user meta smacg_appear_in_ranking — 保留（用戶隱私偏好） */

/* Options */
delete_option( 'smacg_gamify_version' );
delete_option( 'smacg_gamify_activated_at' );
delete_option( 'smacg_ranking_db_version' );
delete_option( 'smacg_ranking_last_rebuild' );
delete_option( 'smacg_event_db_version' );
delete_option( 'smacg_event_cpt_flushed' );

/* Cron */
foreach ( [
    'smacg_exp_daily_reset',
    'smacg_ranking_recalc',
    'smacg_ranking_monthly_purge',
    'smacg_event_settle_sweep',
    'smacg_event_end_check',
] as $hook ) {
    wp_clear_scheduled_hook( $hook );
}
