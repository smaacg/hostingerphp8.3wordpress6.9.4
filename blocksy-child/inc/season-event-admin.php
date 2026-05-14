<?php
/**
 * Season Event — 後台管理 UI
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-4
 *
 * 提供：
 *   - Meta Box：活動設定（時間 / 任務 / 獎勵 / 名額）
 *   - 列表頁新增欄位：狀態、開始、結束、任務、獎勵
 *   - 狀態 quick filter（全部 / 即將開始 / 進行中 / 已結束）
 *   - 行內動作：複製活動（複製為下一季）
 *   - Admin notice：活動結束時提醒去 settle
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   Meta Box 註冊
   ============================================================ */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'smacg_event_settings',
        '🏆 活動設定',
        'smacg_event_render_metabox',
        SMACG_EVENT_CPT,
        'normal',
        'high'
    );
} );

/* ============================================================
   Meta Box 渲染
   ============================================================ */
function smacg_event_render_metabox( $post ) {
    wp_nonce_field( 'smacg_event_save', 'smacg_event_nonce' );

    $meta = smacg_get_event_meta( $post->ID );
    $opts = smacg_event_task_options();
    $bads = smacg_event_get_badge_options();
    ?>

    <style>
    .smacg-event-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
    .smacg-event-grid > .full{grid-column:1 / -1;}
    .smacg-event-field label{display:block;font-weight:600;margin-bottom:6px;font-size:13px;}
    .smacg-event-field input[type=text],
    .smacg-event-field input[type=number],
    .smacg-event-field input[type=datetime-local],
    .smacg-event-field select{width:100%;max-width:420px;}
    .smacg-event-field .desc{font-size:12px;color:#666;margin:4px 0 0;}
    .smacg-event-status{
      display:inline-block;padding:3px 12px;border-radius:999px;
      font-size:12px;font-weight:700;color:#fff;
    }
    .smacg-event-status--upcoming{background:#3b82f6;}
    .smacg-event-status--active{background:#10b981;}
    .smacg-event-status--ended{background:#6b7280;}
    .smacg-event-status--invalid{background:#ef4444;}
    .smacg-event-preview-card{
      margin-top:12px;padding:14px 16px;
      background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;
      font-size:13px;line-height:1.7;
    }
    .smacg-event-preview-card strong{color:#111;}
    </style>

    <p style="margin-bottom:14px;">
        目前狀態：
        <span class="smacg-event-status smacg-event-status--<?php echo esc_attr( $meta['status'] ); ?>">
            <?php
            $status_zh = [
                'upcoming' => '即將開始',
                'active'   => '進行中',
                'ended'    => '已結束',
                'invalid'  => '時間未設定',
            ];
            echo esc_html( $status_zh[ $meta['status'] ] ?? $meta['status'] );
            ?>
        </span>
    </p>

    <div class="smacg-event-grid">

        <!-- 時間 -->
        <div class="smacg-event-field">
            <label for="smacg_event_start">開始時間 <span style="color:#ef4444;">*</span></label>
            <input type="datetime-local" id="smacg_event_start" name="smacg_event_start"
                   value="<?php echo esc_attr( smacg_event_to_input( $meta['start'] ) ); ?>" required>
            <p class="desc">伺服器時區，活動到此時間才開始計算進度</p>
        </div>

        <div class="smacg-event-field">
            <label for="smacg_event_end">結束時間 <span style="color:#ef4444;">*</span></label>
            <input type="datetime-local" id="smacg_event_end" name="smacg_event_end"
                   value="<?php echo esc_attr( smacg_event_to_input( $meta['end'] ) ); ?>" required>
            <p class="desc">過此時間後活動結束，由系統發放獎勵</p>
        </div>

        <!-- 任務 -->
        <div class="smacg-event-field">
            <label for="smacg_event_task_type">任務類型</label>
            <select id="smacg_event_task_type" name="smacg_event_task_type">
                <?php foreach ( $opts as $k => $v ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $meta['task_type'], $k ); ?>>
                        <?php echo esc_html( $v['label'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="desc" id="smacg_event_task_desc">
                <?php echo esc_html( $opts[ $meta['task_type'] ]['desc'] ?? '' ); ?>
            </p>
        </div>

        <div class="smacg-event-field">
            <label for="smacg_event_task_target">任務目標數值</label>
            <input type="number" min="1" step="1" id="smacg_event_task_target" name="smacg_event_task_target"
                   value="<?php echo esc_attr( $meta['task_target'] ?: 100 ); ?>">
            <p class="desc">完成此數值即達標（單位：<span id="smacg_event_unit"><?php echo esc_html( smacg_event_task_unit( $meta['task_type'] ) ); ?></span>）</p>
        </div>

        <!-- 獎勵 -->
        <div class="smacg-event-field">
            <label for="smacg_event_reward_exp">獎勵 EXP</label>
            <input type="number" min="0" step="1" id="smacg_event_reward_exp" name="smacg_event_reward_exp"
                   value="<?php echo esc_attr( $meta['reward_exp'] ?: 0 ); ?>">
            <p class="desc">達標後一次性發放</p>
        </div>

        <div class="smacg-event-field">
            <label for="smacg_event_reward_badge">獎勵徽章</label>
            <select id="smacg_event_reward_badge" name="smacg_event_reward_badge">
                <option value="0">— 不發放徽章 —</option>
                <?php foreach ( $bads as $bid => $bname ) : ?>
                    <option value="<?php echo esc_attr( $bid ); ?>" <?php selected( $meta['reward_badge'], $bid ); ?>>
                        <?php echo esc_html( $bname ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="desc">從 GamiPress 徽章中選擇。沒看到？
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . ( defined( 'SMACG_BADGE_SLUG' ) ? SMACG_BADGE_SLUG : 'badge' ) ) ); ?>" target="_blank">新增徽章</a>
            </p>
        </div>

        <div class="smacg-event-field">
            <label for="smacg_event_reward_title">獎勵稱號（選填）</label>
            <input type="text" id="smacg_event_reward_title" name="smacg_event_reward_title" maxlength="32"
                   placeholder="例：2026 春季優勝者"
                   value="<?php echo esc_attr( $meta['reward_title'] ); ?>">
            <p class="desc">顯示於個人頁與留言區（≤32 字）</p>
        </div>

        <div class="smacg-event-field">
            <label for="smacg_event_max_participants">名額上限</label>
            <input type="number" min="0" step="1" id="smacg_event_max_participants" name="smacg_event_max_participants"
                   value="<?php echo esc_attr( $meta['max_participants'] ?: 0 ); ?>">
            <p class="desc">先達標先得；0 = 無限</p>
        </div>

        <!-- 預覽 -->
        <div class="full">
            <div class="smacg-event-preview-card" id="smacg_event_preview">
                <strong>📋 活動概要：</strong><br>
                於 <strong id="prv-period">—</strong> 期間，
                <strong id="prv-task">—</strong> 達成 <strong id="prv-target">—</strong> <span id="prv-unit">—</span>，
                即可獲得 <strong id="prv-exp">—</strong> EXP
                <span id="prv-badge"></span>
                <span id="prv-title"></span>
                <span id="prv-limit"></span>。
            </div>
        </div>

    </div>

    <script>
    (function(){
      const $ = id => document.getElementById(id);
      const opts = <?php echo wp_json_encode( $opts ); ?>;
      const badges = <?php echo wp_json_encode( $bads ); ?>;

      function update(){
        const t = $('smacg_event_task_type').value;
        $('smacg_event_task_desc').textContent = opts[t]?.desc || '';
        $('smacg_event_unit').textContent = opts[t]?.unit || '';

        $('prv-period').textContent =
          ($('smacg_event_start').value || '?') + ' ~ ' + ($('smacg_event_end').value || '?');
        $('prv-task').textContent   = opts[t]?.label || '?';
        $('prv-target').textContent = $('smacg_event_task_target').value || '?';
        $('prv-unit').textContent   = opts[t]?.unit || '';
        $('prv-exp').textContent    = $('smacg_event_reward_exp').value || '0';

        const bid = $('smacg_event_reward_badge').value;
        $('prv-badge').innerHTML = (bid && bid !== '0')
          ? ' + 徽章「<strong>' + (badges[bid] || '?') + '</strong>」'
          : '';
        const title = $('smacg_event_reward_title').value;
        $('prv-title').innerHTML = title ? ' + 稱號「<strong>' + title + '</strong>」' : '';
        const lim = parseInt($('smacg_event_max_participants').value || '0', 10);
        $('prv-limit').textContent = lim > 0 ? '（限前 ' + lim + ' 名）' : '';
      }

      ['smacg_event_start','smacg_event_end','smacg_event_task_type','smacg_event_task_target',
       'smacg_event_reward_exp','smacg_event_reward_badge','smacg_event_reward_title',
       'smacg_event_max_participants'].forEach(id => {
         const el = $(id);
         if (el) el.addEventListener('input', update);
      });
      update();
    })();
    </script>
    <?php
}

/**
 * datetime → input[type=datetime-local] 格式
 */
function smacg_event_to_input( $val ) {
    if ( empty( $val ) ) return '';
    $ts = strtotime( $val );
    return $ts ? date( 'Y-m-d\TH:i', $ts ) : '';
}

/* ============================================================
   Meta Box 儲存
   ============================================================ */
add_action( 'save_post_' . SMACG_EVENT_CPT, function ( $post_id ) {
    if ( ! isset( $_POST['smacg_event_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['smacg_event_nonce'], 'smacg_event_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $fields = [
        '_smacg_event_start'             => [ 'datetime', 'smacg_event_start' ],
        '_smacg_event_end'               => [ 'datetime', 'smacg_event_end' ],
        '_smacg_event_task_type'         => [ 'key',      'smacg_event_task_type' ],
        '_smacg_event_task_target'       => [ 'int_min1', 'smacg_event_task_target' ],
        '_smacg_event_reward_exp'        => [ 'int',      'smacg_event_reward_exp' ],
        '_smacg_event_reward_badge'      => [ 'int',      'smacg_event_reward_badge' ],
        '_smacg_event_reward_title'      => [ 'text32',   'smacg_event_reward_title' ],
        '_smacg_event_max_participants'  => [ 'int',      'smacg_event_max_participants' ],
    ];

    foreach ( $fields as $meta_key => [ $type, $post_key ] ) {
        $val = $_POST[ $post_key ] ?? '';
        switch ( $type ) {
            case 'datetime':
                $ts = strtotime( $val );
                $val = $ts ? date( 'Y-m-d H:i:s', $ts ) : '';
                break;
            case 'key':
                $val = sanitize_key( $val );
                // 限定在允許清單內
                if ( ! array_key_exists( $val, smacg_event_task_options() ) ) $val = 'exp_gain';
                break;
            case 'int_min1':
                $val = max( 1, (int) $val );
                break;
            case 'int':
                $val = max( 0, (int) $val );
                break;
            case 'text32':
                $val = mb_substr( sanitize_text_field( $val ), 0, 32 );
                break;
        }
        update_post_meta( $post_id, $meta_key, $val );
    }
}, 10, 1 );

/* ============================================================
   列表頁欄位
   ============================================================ */
add_filter( 'manage_' . SMACG_EVENT_CPT . '_posts_columns', function ( $cols ) {
    $new = [];
    foreach ( $cols as $k => $v ) {
        $new[ $k ] = $v;
        if ( $k === 'title' ) {
            $new['smacg_status']  = '狀態';
            $new['smacg_period']  = '期間';
            $new['smacg_task']    = '任務';
            $new['smacg_reward']  = '獎勵';
        }
    }
    return $new;
} );

add_action( 'manage_' . SMACG_EVENT_CPT . '_posts_custom_column', function ( $col, $post_id ) {
    $m = smacg_get_event_meta( $post_id );
    switch ( $col ) {
        case 'smacg_status':
            $status_zh = [
                'upcoming' => ['即將開始','#3b82f6'],
                'active'   => ['進行中',  '#10b981'],
                'ended'    => ['已結束',  '#6b7280'],
                'invalid'  => ['未設定',  '#ef4444'],
            ];
            $s = $status_zh[ $m['status'] ] ?? [ $m['status'], '#666' ];
            printf(
                '<span style="background:%s;color:#fff;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;">%s</span>',
                esc_attr( $s[1] ), esc_html( $s[0] )
            );
            break;

        case 'smacg_period':
            echo $m['start'] ? esc_html( mysql2date( 'Y-m-d H:i', $m['start'] ) ) : '—';
            echo '<br><span style="color:#888;">→ ';
            echo $m['end'] ? esc_html( mysql2date( 'Y-m-d H:i', $m['end'] ) ) : '—';
            echo '</span>';
            break;

        case 'smacg_task':
            echo esc_html( smacg_event_task_label( $m['task_type'] ) );
            echo ' · <strong>' . number_format( $m['task_target'] ) . '</strong> ' . esc_html( smacg_event_task_unit( $m['task_type'] ) );
            break;

        case 'smacg_reward':
            $parts = [];
            if ( $m['reward_exp'] > 0 )   $parts[] = '⚡ ' . number_format( $m['reward_exp'] ) . ' EXP';
            if ( $m['reward_badge'] > 0 ) $parts[] = '🏅 ' . esc_html( get_the_title( $m['reward_badge'] ) ?: '徽章' );
            if ( $m['reward_title'] )     $parts[] = '👑 ' . esc_html( $m['reward_title'] );
            echo $parts ? implode( '<br>', $parts ) : '—';
            break;
    }
}, 10, 2 );

/* ============================================================
   列表頁狀態 filter（quick filter dropdown）
   ============================================================ */
add_action( 'restrict_manage_posts', function () {
    global $typenow;
    if ( $typenow !== SMACG_EVENT_CPT ) return;

    $cur = $_GET['smacg_event_status'] ?? '';
    ?>
    <select name="smacg_event_status">
        <option value="">— 所有狀態 —</option>
        <option value="upcoming" <?php selected( $cur, 'upcoming' ); ?>>即將開始</option>
        <option value="active"   <?php selected( $cur, 'active' );   ?>>進行中</option>
        <option value="ended"    <?php selected( $cur, 'ended' );    ?>>已結束</option>
    </select>
    <?php
} );

add_action( 'pre_get_posts', function ( $q ) {
    if ( ! is_admin() || ! $q->is_main_query() ) return;
    if ( $q->get( 'post_type' ) !== SMACG_EVENT_CPT ) return;
    $f = $_GET['smacg_event_status'] ?? '';
    if ( ! in_array( $f, [ 'upcoming', 'active', 'ended' ], true ) ) return;

    $now = current_time( 'mysql' );
    switch ( $f ) {
        case 'upcoming':
            $q->set( 'meta_query', [ [
                'key' => '_smacg_event_start', 'value' => $now, 'compare' => '>', 'type' => 'DATETIME',
            ] ] );
            break;
        case 'ended':
            $q->set( 'meta_query', [ [
                'key' => '_smacg_event_end', 'value' => $now, 'compare' => '<', 'type' => 'DATETIME',
            ] ] );
            break;
        case 'active':
            $q->set( 'meta_query', [
                'relation' => 'AND',
                [ 'key' => '_smacg_event_start', 'value' => $now, 'compare' => '<=', 'type' => 'DATETIME' ],
                [ 'key' => '_smacg_event_end',   'value' => $now, 'compare' => '>=', 'type' => 'DATETIME' ],
            ] );
            break;
    }
} );

/* ============================================================
   複製活動（row action）
   ============================================================ */
add_filter( 'post_row_actions', function ( $actions, $post ) {
    if ( $post->post_type !== SMACG_EVENT_CPT ) return $actions;
    if ( ! current_user_can( 'edit_posts' ) ) return $actions;

    $url = wp_nonce_url(
        admin_url( 'admin-post.php?action=smacg_event_duplicate&post=' . $post->ID ),
        'smacg_event_duplicate_' . $post->ID
    );
    $actions['smacg_duplicate'] = '<a href="' . esc_url( $url ) . '">📋 複製為新活動</a>';
    return $actions;
}, 10, 2 );

add_action( 'admin_post_smacg_event_duplicate', function () {
    $post_id = (int) ( $_GET['post'] ?? 0 );
    if ( ! $post_id || ! current_user_can( 'edit_posts' ) ) wp_die( '權限不足' );
    check_admin_referer( 'smacg_event_duplicate_' . $post_id );

    $src = get_post( $post_id );
    if ( ! $src || $src->post_type !== SMACG_EVENT_CPT ) wp_die( '找不到活動' );

    $new_id = wp_insert_post( [
        'post_type'    => SMACG_EVENT_CPT,
        'post_status'  => 'draft',
        'post_title'   => $src->post_title . '（複本）',
        'post_content' => $src->post_content,
        'post_excerpt' => $src->post_excerpt,
    ] );

    if ( is_wp_error( $new_id ) || ! $new_id ) wp_die( '複製失敗' );

    // 複製 meta（時間欄位清空，避免誤觸發）
    $meta_keys = [
        '_smacg_event_task_type', '_smacg_event_task_target',
        '_smacg_event_reward_exp', '_smacg_event_reward_badge',
        '_smacg_event_reward_title', '_smacg_event_max_participants',
    ];
    foreach ( $meta_keys as $k ) {
        $v = get_post_meta( $post_id, $k, true );
        if ( $v !== '' ) update_post_meta( $new_id, $k, $v );
    }

    wp_safe_redirect( admin_url( 'post.php?post=' . $new_id . '&action=edit' ) );
    exit;
} );

/* ============================================================
   後台 admin notice：列表頁顯示「目前活動數」
   ============================================================ */
add_action( 'admin_notices', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'edit-' . SMACG_EVENT_CPT ) return;

    $active   = count( smacg_get_active_events( 99 ) );
    $upcoming = count( smacg_get_upcoming_events( 99 ) );
    ?>
    <div class="notice notice-info">
        <p>
            🟢 進行中：<strong><?php echo $active; ?></strong> 個 ·
            🔵 即將開始：<strong><?php echo $upcoming; ?></strong> 個 ·
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SMACG_EVENT_CPT . '&smacg_event_status=active' ) ); ?>">查看進行中</a>
        </p>
    </div>
    <?php
} );
