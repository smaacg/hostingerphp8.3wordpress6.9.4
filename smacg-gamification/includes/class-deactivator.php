<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

class Deactivator {
    public static function run() {
        $hooks = [
            'smacg_exp_daily_reset',
            'smacg_ranking_recalc',
            'smacg_ranking_monthly_purge',
            'smacg_event_settle_sweep',
            'smacg_event_end_check',
        ];
        foreach ( $hooks as $h ) {
            $ts = wp_next_scheduled( $h );
            if ( $ts ) wp_unschedule_event( $ts, $h );
            wp_clear_scheduled_hook( $h );
        }
        flush_rewrite_rules( false );
    }
}
