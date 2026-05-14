<?php
/**
 * Season Event Settle — 結算（發獎勵 + Cron 收尾）
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-5
 *
 * 三條結算路徑：
 *   A. 即時結算：監聽 smacg_event_reached → 立即發 EXP + Badge + 稱號（先達先得效果）
 *   B. Cron 兜底：每 10 分鐘檢查已結束但仍有 reached_at 未 awarded_at 的紀錄，補發
 *   C. 活動結束 hook：smacg_event_ended（一次性）發出「活動結束公告」通知，並產生最終排行快照
 *
 * 發獎內容：
 *   - reward_exp   → smacg_award_exp()  + 通知
 *   - reward_badge → smacg_award_badge() + 通知（由 gamipress-notif-bridge 接手）
 *   - reward_title → user_meta smacg_event_titles (array)
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   A. 即時結算（達標當下）
   ============================================================ */
add_action( 'smacg_event_reached', function ( $event_id, $uid, $meta ) {
    smacg_event_settle_user( $event_id, $uid, $meta );
}, 10, 3 );

/**
 * 對單一使用者結算單一活動的獎勵
 *
 * @param int   $event_id
 * @param int   $uid
 * @param array $meta  smacg_get_event_meta() 結果
 * @return bool 是否真的發了獎（之前已發則 false）
 */
function smacg_event_settle_user( $event_id, $uid, $meta = null ) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_event_progress';

    if ( $meta === null ) $meta = smacg_get_event_meta( $event_id );

    // 重新讀進度（避免快取）
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT reached_at, awarded_at FROM {$tbl} WHERE event_id = %d AND user_id = %d",
        $event_id, $uid
    ) );
    if ( ! $row ) return false;
    if ( $row->awarded_at ) return false;                                  // 已發過
    if ( ! $row->reached_at || $row->reached_at === '1970-01-01 00:00:00' ) return false; // 未達標 / 超額

    // ---- 發 EXP ----
    if ( $meta['reward_exp'] > 0 && function_exists( 'smacg_award_exp' ) ) {
        smacg_award_exp(
            $uid,
            (int) $meta['reward_exp'],
            '活動獎勵：' . $meta['title'],
            [ 'event_id' => $event_id ]
        );
    }

    // ---- 發 Badge ----
    if ( $meta['reward_badge'] > 0 && function_exists( 'smacg_award_badge' ) ) {
        smacg_award_badge( $uid, (int) $meta['reward_badge'] );
    }

    // ---- 發稱號（user_meta 陣列） ----
    if ( ! empty( $meta['reward_title'] ) ) {
        $titles = (array) get_user_meta( $uid, 'smacg_event_titles', true );
        $titles[] = [
            'title'    => $meta['reward_title'],
            'event_id' => $event_id,
            'date'     => current_time( 'mysql' ),
        ];
        update_user_meta( $uid, 'smacg_event_titles', $titles );
    }

    // ---- 推通知 ----
    if ( function_exists( 'smacg_create_notification' ) ) {
        smacg_create_notification( $uid, 'event_reward', [
            'title'    => '🎉 達成活動：' . $meta['title'],
            'excerpt'  => smacg_event_compose_reward_text( $meta ),
            'url'      => $meta['permalink'],
            'icon'     => '🏆',
            'force'    => true,
            'metadata' => [ 'event_id' => $event_id ],
        ] );
    }

    // ---- 標記已發 ----
    $wpdb->update( $tbl,
        [ 'awarded_at' => current_time( 'mysql' ) ],
        [ 'event_id'  => $event_id, 'user_id' => $uid ],
        [ '%s' ], [ '%d', '%d' ]
    );

    do_action( 'smacg_event_settled', $event_id, $uid, $meta );
    return true;
}

/**
 * 把獎勵組合成文字
 */
function smacg_event_compose_reward_text( $meta ) {
    $parts = [];
    if ( $meta['reward_exp'] > 0 )   $parts[] = '+' . number_format( $meta['reward_exp'] ) . ' EXP';
    if ( $meta['reward_badge'] > 0 ) $parts[] = '徽章「' . get_the_title( $meta['reward_badge'] ) . '」';
    if ( ! empty( $meta['reward_title'] ) ) $parts[] = '稱號「' . $meta['reward_title'] . '」';
    return implode( '、', $parts ) ?: '感謝參與';
}

/* ============================================================
   B. Cron 兜底（每 10 分鐘掃一次未發獎的達標紀錄）
   ============================================================ */
add_filter( 'cron_schedules', function ( $s ) {
    if ( ! isset( $s['smacg_10min'] ) ) {
        $s['smacg_10min'] = [ 'interval' => 600, 'display' => 'Every 10 Minutes' ];
    }
    return $s;
} );

add_action( 'after_switch_theme', function () {
    if ( ! wp_next_scheduled( 'smacg_event_settle_sweep' ) ) {
        wp_schedule_event( time() + 120, 'smacg_10min', 'smacg_event_settle_sweep' );
    }
    if ( ! wp_next_scheduled( 'smacg_event_end_check' ) ) {
        wp_schedule_event( time() + 60, 'hourly', 'smacg_event_end_check' );
    }
} );

add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'smacg_event_settle_sweep' ) ) {
        wp_schedule_event( time() + 120, 'smacg_10min', 'smacg_event_settle_sweep' );
    }
    if ( ! wp_next_scheduled( 'smacg_event_end_check' ) ) {
        wp_schedule_event( time() + 60, 'hourly', 'smacg_event_end_check' );
    }
}, 21 );

add_action( 'switch_theme', function () {
    foreach ( [ 'smacg_event_settle_sweep', 'smacg_event_end_check' ] as $hook ) {
        $ts = wp_next_scheduled( $hook );
        if ( $ts ) wp_unschedule_event( $ts, $hook );
    }
} );

/**
 * 兜底掃描：找出 reached_at 不為 NULL 且 awarded_at 為 NULL 的全部 row，補發
 */
add_action( 'smacg_event_settle_sweep', function () {
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
        smacg_event_settle_user( (int) $r->event_id, (int) $r->user_id );
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[smacg event] settle sweep handled ' . count( $rows ) . ' rows' );
    }
} );

/* ============================================================
   C. 活動結束檢查（每小時）→ 觸發 smacg_event_ended（每活動一次）
   ============================================================ */
add_action( 'smacg_event_end_check', function () {
    global $wpdb;
    $now = current_time( 'mysql' );

    // 找出 end < now 但尚未標記 _smacg_event_ended_flag 的活動
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
        $meta = smacg_get_event_meta( $eid );

        // 標記，避免重複觸發
        update_post_meta( $eid, '_smacg_event_ended_flag', current_time( 'mysql' ) );

        // 觸發 hook（外部可監聽：寄信、廣播、做月報等）
        do_action( 'smacg_event_ended', $eid, $meta );

        // 廣播給所有曾參與此活動者：活動結束公告
        smacg_event_broadcast_end_notice( $eid, $meta );

        // 計算最終排行快照存到 post_meta
        smacg_event_take_final_snapshot( $eid );
    }
} );

/**
 * 廣播活動結束公告給參與者
 */
function smacg_event_broadcast_end_notice( $event_id, $meta ) {
    if ( ! function_exists( 'smacg_create_notification' ) ) return;

    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_event_progress';
    $uids = $wpdb->get_col( $wpdb->prepare(
        "SELECT user_id FROM {$tbl} WHERE event_id = %d",
        $event_id
    ) );
    if ( empty( $uids ) ) return;

    foreach ( $uids as $uid ) {
        smacg_create_notification( (int) $uid, 'event_ended', [
            'title'   => '🏁 活動已結束：' . $meta['title'],
            'excerpt' => '點擊查看最終排行與你的成績',
            'url'     => $meta['permalink'],
            'icon'    => '🏁',
            'force'   => false,
        ] );
    }
}

/**
 * 取最終 Top 100 進度榜，存到 post_meta（供前端顯示，不用每次查 DB）
 */
function smacg_event_take_final_snapshot( $event_id ) {
    $top = smacg_event_top_progress( $event_id, 100 );
    update_post_meta( $event_id, '_smacg_event_final_snapshot', $top );
    update_post_meta( $event_id, '_smacg_event_final_snapshot_time', current_time( 'mysql' ) );
}

/* ============================================================
   Admin 工具：手動結算單一活動（給管理員用）
   /wp-admin/admin-post.php?action=smacg_event_resettle&event=ID
   ============================================================ */
add_action( 'admin_post_smacg_event_resettle', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( '權限不足' );
    $eid = (int) ( $_GET['event'] ?? 0 );
    if ( ! $eid ) wp_die( '缺少參數' );
    check_admin_referer( 'smacg_event_resettle_' . $eid );

    do_action( 'smacg_event_settle_sweep' );
    smacg_event_take_final_snapshot( $eid );

    wp_safe_redirect( add_query_arg( 'smacg_msg', rawurlencode( '活動 #' . $eid . ' 已重新結算' ), admin_url( 'edit.php?post_type=' . SMACG_EVENT_CPT ) ) );
    exit;
} );

// 在編輯活動頁面側欄加按鈕
add_action( 'post_submitbox_misc_actions', function ( $post ) {
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
} );
