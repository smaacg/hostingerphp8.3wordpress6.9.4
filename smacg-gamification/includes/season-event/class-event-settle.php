<?php
/**
 * Season Event Settle - 結算（發獎勵 + Cron 收尾）
 *
 * 原檔：blocksy-child/inc/season-event-settle.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\SeasonEvent;

defined( 'ABSPATH' ) || exit;

class Settle {

    public static function init() {
        // 即時結算
        add_action( 'smacg_event_reached', [ __CLASS__, 'on_reached' ], 10, 3 );

        // 自訂 cron schedule
        add_filter( 'cron_schedules', [ __CLASS__, 'register_schedule' ] );

        // Cron handlers
        add_action( 'smacg_event_settle_sweep', [ __CLASS__, 'on_sweep' ] );
        add_action( 'smacg_event_end_check',    [ __CLASS__, 'on_end_check' ] );

        // 保險：init 補排程（避免 cron 被外部清掉）
        add_action( 'init', [ __CLASS__, 'maybe_schedule' ], 21 );

        // Admin 工具
        add_action( 'admin_post_smacg_event_resettle',     [ __CLASS__, 'handle_resettle' ] );
        add_action( 'post_submitbox_misc_actions',         [ __CLASS__, 'add_resettle_button' ] );
    }

    public static function register_schedule( $s ) {
        if ( ! isset( $s['smacg_10min'] ) ) {
            $s['smacg_10min'] = [ 'interval' => 600, 'display' => 'Every 10 Minutes' ];
        }
        return $s;
    }

    public static function maybe_schedule() {
        if ( ! wp_next_scheduled( 'smacg_event_settle_sweep' ) ) {
            wp_schedule_event( time() + 120, 'smacg_10min', 'smacg_event_settle_sweep' );
        }
        if ( ! wp_next_scheduled( 'smacg_event_end_check' ) ) {
            wp_schedule_event( time() + 60, 'hourly', 'smacg_event_end_check' );
        }
    }

    /* ---------------------------------------------
     * A. 即時結算
     * --------------------------------------------- */
    public static function on_reached( $event_id, $uid, $meta ) {
        self::settle_user( $event_id, $uid, $meta );
    }

    public static function settle_user( $event_id, $uid, $meta = null ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';

        if ( $meta === null ) $meta = CPT::get_meta( $event_id );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT reached_at, awarded_at FROM {$tbl} WHERE event_id = %d AND user_id = %d",
            $event_id, $uid
        ) );
        if ( ! $row ) return false;
        if ( $row->awarded_at ) return false;
        if ( ! $row->reached_at || $row->reached_at === '1970-01-01 00:00:00' ) return false;

        // EXP
        if ( $meta['reward_exp'] > 0 && function_exists( 'smacg_award_exp' ) ) {
            \smacg_award_exp(
                $uid,
                (int) $meta['reward_exp'],
                '活動獎勵：' . $meta['title'],
                [ 'event_id' => $event_id ]
            );
        }

        // Badge
        if ( $meta['reward_badge'] > 0 && function_exists( 'smacg_award_badge' ) ) {
            \smacg_award_badge( $uid, (int) $meta['reward_badge'] );
        }

        // 稱號
        if ( ! empty( $meta['reward_title'] ) ) {
            $titles = (array) get_user_meta( $uid, 'smacg_event_titles', true );
            $titles[] = [
                'title'    => $meta['reward_title'],
                'event_id' => $event_id,
                'date'     => current_time( 'mysql' ),
            ];
            update_user_meta( $uid, 'smacg_event_titles', $titles );
        }

        // 通知
        if ( function_exists( 'smacg_create_notification' ) ) {
            \smacg_create_notification( $uid, 'event_reward', [
                'title'    => '🎉 達成活動：' . $meta['title'],
                'excerpt'  => self::compose_reward_text( $meta ),
                'url'      => $meta['permalink'],
                'icon'     => '🏆',
                'force'    => true,
                'metadata' => [ 'event_id' => $event_id ],
            ] );
        }

        // 標記已發
        $wpdb->update( $tbl,
            [ 'awarded_at' => current_time( 'mysql' ) ],
            [ 'event_id' => $event_id, 'user_id' => $uid ],
            [ '%s' ], [ '%d', '%d' ]
        );

        do_action( 'smacg_event_settled', $event_id, $uid, $meta );
        return true;
    }

    public static function compose_reward_text( $meta ) {
        $parts = [];
        if ( $meta['reward_exp'] > 0 )   $parts[] = '+' . number_format( $meta['reward_exp'] ) . ' EXP';
        if ( $meta['reward_badge'] > 0 ) $parts[] = '徽章「' . get_the_title( $meta['reward_badge'] ) . '」';
        if ( ! empty( $meta['reward_title'] ) ) $parts[] = '稱號「' . $meta['reward_title'] . '」';
        return implode( '、', $parts ) ?: '感謝參與';
    }

    /* ---------------------------------------------
     * B. Cron 兜底
     * --------------------------------------------- */
    public static function on_sweep() {
        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';

        $rows = $wpdb->get_results(
            "SELECT event_id, user_id FROM {$tbl}
             WHERE awarded_at IS NULL
               AND reached_at IS NOT NULL
               AND reached_at != '1970-01-01 00:00:00'
             LIMIT 200"
        );
        if ( empty( $rows ) ) return;

        foreach ( $rows as $r ) {
            self::settle_user( (int) $r->event_id, (int) $r->user_id );
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[smacg event] settle sweep handled ' . count( $rows ) . ' rows' );
        }
    }

    /* ---------------------------------------------
     * C. 活動結束檢查
     * --------------------------------------------- */
    public static function on_end_check() {
        global $wpdb;
        $now = current_time( 'mysql' );

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} me ON me.post_id = p.ID AND me.meta_key = '_smacg_event_end'
             LEFT JOIN {$wpdb->postmeta} mf ON mf.post_id = p.ID AND mf.meta_key = '_smacg_event_ended_flag'
             WHERE p.post_type = %s
               AND p.post_status = 'publish'
               AND me.meta_value < %s
               AND mf.meta_value IS NULL
             LIMIT 50",
            SMACG_EVENT_CPT, $now
        ) );

        if ( empty( $ids ) ) return;

        foreach ( $ids as $eid ) {
            $eid  = (int) $eid;
            $meta = CPT::get_meta( $eid );

            update_post_meta( $eid, '_smacg_event_ended_flag', current_time( 'mysql' ) );
            do_action( 'smacg_event_ended', $eid, $meta );

            self::broadcast_end_notice( $eid, $meta );
            self::take_final_snapshot( $eid );
        }
    }

    public static function broadcast_end_notice( $event_id, $meta ) {
        if ( ! function_exists( 'smacg_create_notification' ) ) return;

        global $wpdb;
        $tbl = $wpdb->prefix . 'smacg_event_progress';
        $uids = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$tbl} WHERE event_id = %d",
            $event_id
        ) );
        if ( empty( $uids ) ) return;

        foreach ( $uids as $uid ) {
            \smacg_create_notification( (int) $uid, 'event_ended', [
                'title'   => '🏁 活動已結束：' . $meta['title'],
                'excerpt' => '點擊查看最終排行與你的成績',
                'url'     => $meta['permalink'],
                'icon'    => '🏁',
                'force'   => false,
            ] );
        }
    }

    public static function take_final_snapshot( $event_id ) {
        $top = Tracker::top_progress( $event_id, 100 );
        update_post_meta( $event_id, '_smacg_event_final_snapshot', $top );
        update_post_meta( $event_id, '_smacg_event_final_snapshot_time', current_time( 'mysql' ) );
    }

    /* ---------------------------------------------
     * Admin 工具
     * --------------------------------------------- */
    public static function handle_resettle() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '權限不足' );
        $eid = (int) ( $_GET['event'] ?? 0 );
        if ( ! $eid ) wp_die( '缺少參數' );
        check_admin_referer( 'smacg_event_resettle_' . $eid );

        do_action( 'smacg_event_settle_sweep' );
        self::take_final_snapshot( $eid );

        wp_safe_redirect( add_query_arg(
            'smacg_msg',
            rawurlencode( '活動 #' . $eid . ' 已重新結算' ),
            admin_url( 'edit.php?post_type=' . SMACG_EVENT_CPT )
        ) );
        exit;
    }

    public static function add_resettle_button( $post ) {
        if ( $post->post_type !== SMACG_EVENT_CPT ) return;
        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=smacg_event_resettle&event=' . $post->ID ),
            'smacg_event_resettle_' . $post->ID
        );
        ?>
        <div style="padding:10px 0;border-top:1px solid #eee;margin-top:8px;">
            <a href="<?php echo esc_url( $url ); ?>" class="button">🔄 立即結算 / 補發獎勵</a>
            <p class="description" style="margin-top:6px;">補發所有達標但未領獎的紀錄</p>
        </div>
        <?php
    }
}

Settle::init();
