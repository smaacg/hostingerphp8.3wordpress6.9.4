<?php
/**
 * Admin Page: Settings
 *
 * File: admin/pages/settings.php
 * Plugin-wide configuration: API keys, cron schedule, cache control,
 * log management, map download, and debug tools.
 *
 * v4 修正：
 * 1. 選項鍵名 anime_sync_daily_hour_taipei → anime_sync_daily_hour
 *    （與 class-cron-manager.php 對齊，含一次性遷移）
 * 2. anime_sync_rating_batch_size → anime_sync_batch_size（同上）
 * 3. 移除 anime_sync_weekly_day / weekly_hour_taipei UI（cron 寫死，UI 為擺設）
 * 4. 「下次執行」hook 名對齊 cron-manager 真實常數
 * 5. 儲存後自動 reschedule daily / themes_episodes hook
 * 6. sanitizer 加範圍 clamp
 * 7. 移除孤兒 cache_ttl_hours
 * 8. 加入 manage_options 權限檢查
 * 9. 時間顯示改用 wp_date() 套用站台時區
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( '您沒有權限存取此頁面。', 'anime-sync-pro' ) );
}

/* ───────────────────────────────────────────────
    一次性遷移：舊鍵 → 新鍵（與 cron-manager 對齊）
─────────────────────────────────────────────── */
if ( ! get_option( 'anime_sync_settings_migrated_v2' ) ) {

    // anime_sync_daily_hour_taipei → anime_sync_daily_hour
    $old_daily = get_option( 'anime_sync_daily_hour_taipei', null );
    if ( $old_daily !== null && $old_daily !== false ) {
        $existing_new = get_option( 'anime_sync_daily_hour', null );
        if ( $existing_new === null || $existing_new === false ) {
            update_option( 'anime_sync_daily_hour', (int) $old_daily );
        }
        delete_option( 'anime_sync_daily_hour_taipei' );
    }

    // anime_sync_rating_batch_size → anime_sync_batch_size
    $old_batch = get_option( 'anime_sync_rating_batch_size', null );
    if ( $old_batch !== null && $old_batch !== false ) {
        $existing_new = get_option( 'anime_sync_batch_size', null );
        if ( $existing_new === null || $existing_new === false ) {
            update_option( 'anime_sync_batch_size', (int) $old_batch );
        }
        delete_option( 'anime_sync_rating_batch_size' );
    }

    // 清掉孤兒選項（如果有的話）
    delete_option( 'anime_sync_weekly_hour_taipei' );

    update_option( 'anime_sync_settings_migrated_v2', 1 );
}

/* ───────────────────────────────────────────────
    Handle form save
─────────────────────────────────────────────── */
$saved = false;
if (
    isset( $_POST['anime_sync_settings_nonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['anime_sync_settings_nonce'] ) ), 'anime_sync_save_settings' )
) {
    // 取舊值，用於判斷是否需要 reschedule
    $old_daily_hour = (int) get_option( 'anime_sync_daily_hour', 3 );

    // 帶 clamp 的個別處理
    $new_site_name      = isset( $_POST['anime_sync_site_name'] )
                            ? sanitize_text_field( wp_unslash( $_POST['anime_sync_site_name'] ) )
                            : '';
    $new_site_url       = isset( $_POST['anime_sync_site_url'] )
                            ? esc_url_raw( wp_unslash( $_POST['anime_sync_site_url'] ) )
                            : '';
    $new_daily_hour     = isset( $_POST['anime_sync_daily_hour'] )
                            ? max( 0, min( 23, (int) $_POST['anime_sync_daily_hour'] ) )
                            : 3;
    $new_batch_size     = isset( $_POST['anime_sync_batch_size'] )
                            ? max( 5, min( 100, (int) $_POST['anime_sync_batch_size'] ) )
                            : 25;
    $new_log_retention  = isset( $_POST['anime_sync_log_retention_days'] )
                            ? max( 1, min( 365, (int) $_POST['anime_sync_log_retention_days'] ) )
                            : 30;
    $new_debug_mode     = isset( $_POST['anime_sync_debug_mode'] ) ? 1 : 0;

    update_option( 'anime_sync_site_name',          $new_site_name );
    update_option( 'anime_sync_site_url',           $new_site_url );
    update_option( 'anime_sync_daily_hour',         $new_daily_hour );
    update_option( 'anime_sync_batch_size',         $new_batch_size );
    update_option( 'anime_sync_log_retention_days', $new_log_retention );
    update_option( 'anime_sync_debug_mode',         $new_debug_mode );

    // ── 若 daily_hour 改變，重排 cron ──
    if ( $new_daily_hour !== $old_daily_hour ) {

        // Daily score update：當天 new_daily_hour:00:00（已過則順延一日）
        $daily_hook  = 'anime_sync_daily_score_update';
        $today_utc   = strtotime( gmdate( "Y-m-d {$new_daily_hour}:00:00" ) );
        $start_daily = $today_utc < time() ? $today_utc + DAY_IN_SECONDS : $today_utc;
        wp_clear_scheduled_hook( $daily_hook );
        wp_schedule_event( $start_daily, 'daily', $daily_hook );

        // Themes / Episodes update：daily_hour + 2:30，% 24 防溢位
        $themes_hook  = 'anime_sync_themes_episodes_update';
        $themes_hour  = ( $new_daily_hour + 2 ) % 24;
        $today_themes = strtotime( gmdate( "Y-m-d {$themes_hour}:30:00" ) );
        $start_themes = $today_themes < time() ? $today_themes + DAY_IN_SECONDS : $today_themes;
        wp_clear_scheduled_hook( $themes_hook );
        wp_schedule_event( $start_themes, 'daily', $themes_hook );
    }

    $saved = true;
}

/* ───────────────────────────────────────────────
    Read current values
─────────────────────────────────────────────── */
$site_name     = get_option( 'anime_sync_site_name',           get_bloginfo( 'name' ) );
$site_url      = get_option( 'anime_sync_site_url',            get_site_url() );
$daily_hour    = (int) get_option( 'anime_sync_daily_hour',         3 );
$batch_size    = (int) get_option( 'anime_sync_batch_size',        15 );
$log_retention = (int) get_option( 'anime_sync_log_retention_days', 30 );
$debug_mode    = (int) get_option( 'anime_sync_debug_mode',          0 );

/* ───────────────────────────────────────────────
    Bangumi ID 對照表狀態
─────────────────────────────────────────────── */
$mapper     = new Anime_Sync_ID_Mapper();
$map_status = $mapper->get_map_status();

$map_exists  = $map_status['exists'];
$map_count   = $map_status['entry_count'];
$mal_count   = $map_status['mal_count'];
$map_size    = $map_status['size'];
$map_updated = $map_status['last_updated']
               ? wp_date( 'Y-m-d H:i', strtotime( $map_status['last_updated'] ) )
               : '—';
$map_age_h   = $map_status['age_hours'];

/* ───────────────────────────────────────────────
    Log file info
─────────────────────────────────────────────── */
$upload_dir  = wp_upload_dir();
$log_dir     = trailingslashit( $upload_dir['basedir'] ) . 'anime-sync-pro/logs/';
$log_files   = glob( $log_dir . '*.log' ) ?: [];
$log_count   = count( $log_files );
$log_size    = 0;
foreach ( $log_files as $lf ) {
    if ( file_exists( $lf ) ) {
        $log_size += filesize( $lf );
    }
}
$log_size_kb = round( $log_size / 1024, 1 );

/* ───────────────────────────────────────────────
    Cron 狀態（hook 名對齊 class-cron-manager.php 常數）
─────────────────────────────────────────────── */
$next_daily        = wp_next_scheduled( 'anime_sync_daily_score_update' );
$next_themes_eps   = wp_next_scheduled( 'anime_sync_themes_episodes_update' );
$next_weekly       = wp_next_scheduled( 'anime_sync_weekly_cleanup' );
$next_update_map   = wp_next_scheduled( 'anime_sync_update_anime_map' );
$last_daily_run    = get_option( 'anime_sync_last_daily_run', '' );
$last_weekly_run   = get_option( 'anime_sync_last_weekly_cleanup', '' );
$last_themes_run   = get_option( 'anime_sync_last_themes_episodes_run', '' );
?>

<div class="wrap anime-sync-settings">

    <h1><?php esc_html_e( '設定', 'anime-sync-pro' ); ?></h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( '設定已儲存。', 'anime-sync-pro' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'anime_sync_save_settings', 'anime_sync_settings_nonce' ); ?>

        <!-- 網站識別 -->
        <div class="anime-sync-settings-card">
            <h2><?php esc_html_e( '網站識別 (User-Agent)', 'anime-sync-pro' ); ?></h2>
            <p class="description">
                <?php esc_html_e( '用於 API 請求的 User-Agent 標頭，部分 API（如 Bangumi）要求必填。', 'anime-sync-pro' ); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="anime_sync_site_name"><?php esc_html_e( '網站名稱', 'anime-sync-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="anime_sync_site_name" name="anime_sync_site_name"
                               value="<?php echo esc_attr( $site_name ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="anime_sync_site_url"><?php esc_html_e( '網站 URL', 'anime-sync-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="url" id="anime_sync_site_url" name="anime_sync_site_url"
                               value="<?php echo esc_attr( $site_url ); ?>" class="regular-text" />
                        <p class="description">
                            <?php printf(
                                esc_html__( '產生的 UA：%s', 'anime-sync-pro' ),
                                '<code>' . esc_html( $site_name . '/1.0 (' . $site_url . ')' ) . '</code>'
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- 排程設定 -->
        <div class="anime-sync-settings-card">
            <h2><?php esc_html_e( '排程設定（站台時區）', 'anime-sync-pro' ); ?></h2>
            <p class="description">
                <?php esc_html_e( '每週清理（星期日 04:00 UTC）與 ID 對照表更新（星期一 02:00 UTC）為固定排程，無法在此調整。', 'anime-sync-pro' ); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="anime_sync_daily_hour"><?php esc_html_e( '每日同步時間', 'anime-sync-pro' ); ?></label>
                    </th>
                    <td>
                        <select id="anime_sync_daily_hour" name="anime_sync_daily_hour">
                            <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                                <option value="<?php echo esc_attr( $h ); ?>" <?php selected( $daily_hour, $h ); ?>>
                                    <?php echo esc_html( sprintf( '%02d:00 UTC', $h ) ); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( '評分／熱度／狀態同步的執行時間。主題曲＋集數同步會自動排在此時間 +2:30。', 'anime-sync-pro' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="anime_sync_batch_size"><?php esc_html_e( '匯入批次大小', 'anime-sync-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="anime_sync_batch_size" name="anime_sync_batch_size"
                               value="<?php echo esc_attr( $batch_size ); ?>" min="5" max="100" class="small-text" />
                        <p class="description"><?php esc_html_e( '建議 15–30。每批次處理完後釋放記憶體。', 'anime-sync-pro' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- 排程狀態 -->
        <div class="anime-sync-settings-card">
            <h2><?php esc_html_e( '排程狀態', 'anime-sync-pro' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( '每日評分同步', 'anime-sync-pro' ); ?></th>
                    <td>
                        <?php if ( $next_daily ) : ?>
                            <?php printf(
                                esc_html__( '下次：%s', 'anime-sync-pro' ),
                                esc_html( wp_date( 'Y-m-d H:i', $next_daily ) )
                            ); ?>
                        <?php else : ?>
                            <span style="color:#dc3232;"><?php esc_html_e( '未排程', 'anime-sync-pro' ); ?></span>
                        <?php endif; ?>
                        <?php if ( $last_daily_run ) : ?>
                            <span style="color:#777;margin-left:12px;">
                                <?php printf( esc_html__( '上次：%s', 'anime-sync-pro' ), esc_html( $last_daily_run ) ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( '主題曲＋集數同步', 'anime-sync-pro' ); ?></th>
                    <td>
                        <?php if ( $next_themes_eps ) : ?>
                            <?php printf(
                                esc_html__( '下次：%s', 'anime-sync-pro' ),
                                esc_html( wp_date( 'Y-m-d H:i', $next_themes_eps ) )
                            ); ?>
                        <?php else : ?>
                            <span style="color:#dc3232;"><?php esc_html_e( '未排程', 'anime-sync-pro' ); ?></span>
                        <?php endif; ?>
                        <?php if ( $last_themes_run ) : ?>
                            <span style="color:#777;margin-left:12px;">
                                <?php printf( esc_html__( '上次：%s', 'anime-sync-pro' ), esc_html( $last_themes_run ) ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( '每週清理', 'anime-sync-pro' ); ?></th>
                    <td>
                        <?php if ( $next_weekly ) : ?>
                            <?php printf(
                                esc_html__( '下次：%s', 'anime-sync-pro' ),
                                esc_html( wp_date( 'Y-m-d H:i', $next_weekly ) )
                            ); ?>
                        <?php else : ?>
                            <span style="color:#dc3232;"><?php esc_html_e( '未排程', 'anime-sync-pro' ); ?></span>
                        <?php endif; ?>
                        <?php if ( $last_weekly_run ) : ?>
                            <span style="color:#777;margin-left:12px;">
                                <?php printf( esc_html__( '上次：%s', 'anime-sync-pro' ), esc_html( $last_weekly_run ) ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'ID 對照表更新', 'anime-sync-pro' ); ?></th>
                    <td>
                        <?php if ( $next_update_map ) : ?>
                            <?php printf(
                                esc_html__( '下次：%s', 'anime-sync-pro' ),
                                esc_html( wp_date( 'Y-m-d H:i', $next_update_map ) )
                            ); ?>
                        <?php else : ?>
                            <span style="color:#dc3232;"><?php esc_html_e( '未排程', 'anime-sync-pro' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- 記錄與偵錯 -->
        <div class="anime-sync-settings-card">
            <h2><?php esc_html_e( '記錄與偵錯', 'anime-sync-pro' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="anime_sync_log_retention_days"><?php esc_html_e( '記錄保留天數', 'anime-sync-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="anime_sync_log_retention_days" name="anime_sync_log_retention_days"
                               value="<?php echo esc_attr( $log_retention ); ?>" min="1" max="365" class="small-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( '偵錯模式', 'anime-sync-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="anime_sync_debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?> />
                            <?php esc_html_e( '啟用詳細偵錯記錄（保留欄位，目前未連動 logger）', 'anime-sync-pro' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( '儲存設定', 'anime-sync-pro' ); ?></button>
        </p>
    </form>

    <!-- Bangumi ID 對照表 -->
    <div class="anime-sync-settings-card">
        <h2><?php esc_html_e( 'Bangumi ID 對照表 (anime_map.json)', 'anime-sync-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( '檔案狀態', 'anime-sync-pro' ); ?></th>
                <td>
                    <?php if ( $map_exists ) : ?>
                        <span style="color:#46b450;">&#10003;</span>
                        <?php printf(
                            esc_html__( '存在 · 共 %1$s 筆（MAL 對應 %2$s 筆）· 大小 %3$s MB · 上次更新 %4$s', 'anime-sync-pro' ),
                            esc_html( number_format( $map_count ) ),
                            esc_html( number_format( $mal_count ) ),
                            esc_html( number_format( $map_size / 1024 / 1024, 2 ) ),
                            esc_html( $map_updated )
                        ); ?>
                        <?php if ( $map_age_h > 168 ) : ?>
                            <span style="color:#d63638;margin-left:8px;">
                                ⚠️ <?php esc_html_e( '超過 7 天未更新', 'anime-sync-pro' ); ?>
                            </span>
                        <?php endif; ?>
                    <?php else : ?>
                        <span style="color:#dc3232;">
                            &#10007; <?php esc_html_e( '檔案不存在，請點擊下方下載', 'anime-sync-pro' ); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '手動更新', 'anime-sync-pro' ); ?></th>
                <td>
                    <button type="button" id="btn-update-map" class="button button-secondary">
                        <?php esc_html_e( '立即下載 / 更新對照表', 'anime-sync-pro' ); ?>
                    </button>
                    <span id="update-map-result" style="margin-left:12px;"></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- 手動功能 -->
    <div class="anime-sync-settings-card">
        <h2><?php esc_html_e( '手動功能', 'anime-sync-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( '快取管理', 'anime-sync-pro' ); ?></th>
                <td>
                    <button type="button" id="btn-clear-cache" class="button button-secondary">
                        <?php esc_html_e( '清除外掛快取', 'anime-sync-pro' ); ?>
                    </button>
                    <span id="clear-cache-result" style="margin-left:10px;"></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '記錄管理', 'anime-sync-pro' ); ?></th>
                <td>
                    <?php printf(
                        esc_html__( '共 %1$d 個檔案，佔用 %2$s KB', 'anime-sync-pro' ),
                        $log_count,
                        esc_html( $log_size_kb )
                    ); ?>
                    <br><br>
                    <button type="button" id="btn-clear-logs" class="button button-secondary">
                        <?php esc_html_e( '清除所有 Log 檔案', 'anime-sync-pro' ); ?>
                    </button>
                    <span id="clear-logs-result" style="margin-left:10px;"></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- 系統資訊 -->
    <div class="anime-sync-settings-card">
        <h2><?php esc_html_e( '系統資訊', 'anime-sync-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'PHP 版本', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( PHP_VERSION ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'WordPress 版本', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '外掛版本', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( defined( 'ANIME_SYNC_PRO_VERSION' ) ? ANIME_SYNC_PRO_VERSION : '1.0.0' ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'mal_index 筆數', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( number_format( $mal_count ) ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '對照表更新時間', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( $map_updated ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '對照表已存放', 'anime-sync-pro' ); ?></th>
                <td>
                    <?php printf(
                        esc_html__( '%.1f 小時', 'anime-sync-pro' ),
                        $map_age_h
                    ); ?>
                </td>
            </tr>
        </table>
    </div>

</div>

<style>
.anime-sync-settings-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px 24px;
    margin-top: 20px;
    max-width: 900px;
}
.anime-sync-settings-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
</style>

<script>
( function( $ ) {
    'use strict';
    const ajaxParams = { nonce: '<?php echo esc_js( wp_create_nonce( 'anime_sync_admin_nonce' ) ); ?>' };

    $( '#btn-update-map' ).on( 'click', function () {
        const $btn = $( this );
        $btn.prop( 'disabled', true ).text( '下載中...' );
        $.post( ajaxurl, { action: 'anime_sync_update_map', nonce: ajaxParams.nonce }, function ( resp ) {
            $btn.prop( 'disabled', false ).text( '立即下載 / 更新對照表' );
            alert( resp.success ? '更新成功' : '更新失敗：' + resp.data );
            location.reload();
        } );
    } );

    $( '#btn-clear-cache' ).on( 'click', function () {
        const $result = $( '#clear-cache-result' );
        $.post( ajaxurl, { action: 'anime_sync_clear_cache', nonce: ajaxParams.nonce }, function ( resp ) {
            $result.text( resp.success ? '成功' : '失敗' );
            setTimeout( function(){ $result.fadeOut( 2000, function(){ $result.text('').show(); } ); }, 500 );
        } );
    } );

    $( '#btn-clear-logs' ).on( 'click', function () {
        if ( ! confirm( '確定要刪除所有記錄檔嗎？' ) ) return;
        $.post( ajaxurl, { action: 'anime_sync_clear_logs', nonce: ajaxParams.nonce }, function ( resp ) {
            alert( resp.success ? '已清除' : '失敗' );
            location.reload();
        } );
    } );

} )( jQuery );
</script>
