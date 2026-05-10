<?php
/**
 * Admin Page: Settings
 *
 * @package Anime_Sync_Pro
 * @version 5.0.0
 *
 * Changelog:
 *  - 5.0.0 (2026-05-10):
 *      • 修正 #1/#7：拆分「清除 DB 日誌」與「清除 Log 檔案」為兩個獨立按鈕，
 *        DB 日誌支援指定天數（7/30/60/90/all）。
 *      • 修正 #2：排程時間欄位改為「站台時區顯示 + 內部以 UTC 儲存」，
 *        下拉顯示站台時區與對應 UTC，並標註 wp_timezone_string()。
 *      • 修正 #3：所有 last_*_run 改用 wp_date() 套站台時區。
 *      • 加上儲存按鈕 disabled 防重複提交。
 *      • alert() 全部改為 toast 提示。
 *      • 加上「強制重新排程所有 cron」按鈕。
 *      • UA 字串改為從 ANIME_SYNC_PRO_VERSION 動態組合。
 *      • 偵錯模式拿掉「未連動」字樣，改為實際開關 anime_sync_debug_mode。
 *      • Mapper 與 Logger 類別載入加 class_exists 防呆。
 *
 *  - v4: 舊鍵遷移、reschedule、權限檢查、wp_date 等。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( '您沒有權限存取此頁面。', 'anime-sync-pro' ) );
}

/* ───────────────────────────────────────────────
    一次性遷移：舊鍵 → 新鍵
─────────────────────────────────────────────── */
if ( ! get_option( 'anime_sync_settings_migrated_v2' ) ) {

    $old_daily = get_option( 'anime_sync_daily_hour_taipei', null );
    if ( $old_daily !== null && $old_daily !== false ) {
        $existing_new = get_option( 'anime_sync_daily_hour', null );
        if ( $existing_new === null || $existing_new === false ) {
            update_option( 'anime_sync_daily_hour', (int) $old_daily );
        }
        delete_option( 'anime_sync_daily_hour_taipei' );
    }

    $old_batch = get_option( 'anime_sync_rating_batch_size', null );
    if ( $old_batch !== null && $old_batch !== false ) {
        $existing_new = get_option( 'anime_sync_batch_size', null );
        if ( $existing_new === null || $existing_new === false ) {
            update_option( 'anime_sync_batch_size', (int) $old_batch );
        }
        delete_option( 'anime_sync_rating_batch_size' );
    }

    delete_option( 'anime_sync_weekly_hour_taipei' );

    update_option( 'anime_sync_settings_migrated_v2', 1 );
}

/* ───────────────────────────────────────────────
    Helper: 站台時區 / UTC 轉換
─────────────────────────────────────────────── */
$site_tz_string = wp_timezone_string();          // 例：Asia/Taipei
$site_tz        = wp_timezone();                  // DateTimeZone

/**
 * 將站台時區的小時轉成 UTC 小時。
 */
$local_to_utc_hour = static function ( int $local_hour ) use ( $site_tz ): int {
    try {
        $dt = new \DateTimeImmutable( sprintf( '%s %02d:00:00', wp_date( 'Y-m-d' ), $local_hour ), $site_tz );
        return (int) $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'G' );
    } catch ( \Throwable $e ) {
        return $local_hour;
    }
};

/**
 * 將 UTC 小時轉成站台時區小時。
 */
$utc_to_local_hour = static function ( int $utc_hour ) use ( $site_tz ): int {
    try {
        $dt = new \DateTimeImmutable( sprintf( '%s %02d:00:00', gmdate( 'Y-m-d' ), $utc_hour ), new \DateTimeZone( 'UTC' ) );
        return (int) $dt->setTimezone( $site_tz )->format( 'G' );
    } catch ( \Throwable $e ) {
        return $utc_hour;
    }
};

/**
 * 格式化 datetime 字串為站台時區顯示（容錯）。
 */
$fmt_datetime = static function ( $raw ): string {
    if ( empty( $raw ) ) return '—';
    $ts = is_numeric( $raw ) ? (int) $raw : strtotime( (string) $raw );
    return $ts ? wp_date( 'Y-m-d H:i', $ts ) : (string) $raw;
};

/* ───────────────────────────────────────────────
    Handle form save
─────────────────────────────────────────────── */
$saved = false;
if (
    isset( $_POST['anime_sync_settings_nonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['anime_sync_settings_nonce'] ) ), 'anime_sync_save_settings' )
) {
    $old_daily_hour_utc = (int) get_option( 'anime_sync_daily_hour', 3 );

    $new_site_name = isset( $_POST['anime_sync_site_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['anime_sync_site_name'] ) )
        : '';
    $new_site_url  = isset( $_POST['anime_sync_site_url'] )
        ? esc_url_raw( wp_unslash( $_POST['anime_sync_site_url'] ) )
        : '';

    // ✅ 表單接收站台時區小時，內部存 UTC
    $local_hour_input   = isset( $_POST['anime_sync_daily_hour_local'] )
        ? max( 0, min( 23, (int) $_POST['anime_sync_daily_hour_local'] ) )
        : 11; // 預設站台 11:00（台北 = UTC 03:00）
    $new_daily_hour_utc = $local_to_utc_hour( $local_hour_input );

    $new_batch_size    = isset( $_POST['anime_sync_batch_size'] )
        ? max( 5, min( 100, (int) $_POST['anime_sync_batch_size'] ) )
        : 15;
    $new_log_retention = isset( $_POST['anime_sync_log_retention_days'] )
        ? max( 1, min( 365, (int) $_POST['anime_sync_log_retention_days'] ) )
        : 30;
    $new_debug_mode    = isset( $_POST['anime_sync_debug_mode'] ) ? 1 : 0;

    update_option( 'anime_sync_site_name',          $new_site_name );
    update_option( 'anime_sync_site_url',           $new_site_url );
    update_option( 'anime_sync_daily_hour',         $new_daily_hour_utc );
    update_option( 'anime_sync_batch_size',         $new_batch_size );
    update_option( 'anime_sync_log_retention_days', $new_log_retention );
    update_option( 'anime_sync_debug_mode',         $new_debug_mode );

    // 若 daily_hour (UTC) 改變，重排 cron
    if ( $new_daily_hour_utc !== $old_daily_hour_utc ) {
        $daily_hook  = 'anime_sync_daily_score_update';
        $today_utc   = strtotime( gmdate( "Y-m-d {$new_daily_hour_utc}:00:00" ) );
        $start_daily = $today_utc < time() ? $today_utc + DAY_IN_SECONDS : $today_utc;
        wp_clear_scheduled_hook( $daily_hook );
        wp_schedule_event( $start_daily, 'daily', $daily_hook );

        $themes_hook  = 'anime_sync_themes_episodes_update';
        $themes_hour  = ( $new_daily_hour_utc + 2 ) % 24;
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
$site_name        = get_option( 'anime_sync_site_name', get_bloginfo( 'name' ) );
$site_url         = get_option( 'anime_sync_site_url',  get_site_url() );
$daily_hour_utc   = (int) get_option( 'anime_sync_daily_hour',         3 );
$daily_hour_local = $utc_to_local_hour( $daily_hour_utc );
$batch_size       = (int) get_option( 'anime_sync_batch_size',        15 );
$log_retention    = (int) get_option( 'anime_sync_log_retention_days', 30 );
$debug_mode       = (int) get_option( 'anime_sync_debug_mode',          0 );

$plugin_version = defined( 'ANIME_SYNC_PRO_VERSION' )
    ? ANIME_SYNC_PRO_VERSION
    : ( defined( 'ANIME_SYNC_VERSION' ) ? ANIME_SYNC_VERSION : '1.0.0' );

/* ───────────────────────────────────────────────
    Bangumi ID 對照表狀態（防呆）
─────────────────────────────────────────────── */
$map_exists  = false;
$map_count   = 0;
$mal_count   = 0;
$map_size    = 0;
$map_updated = '—';
$map_age_h   = 0;

if ( class_exists( 'Anime_Sync_ID_Mapper' ) ) {
    $mapper     = new Anime_Sync_ID_Mapper();
    $map_status = $mapper->get_map_status();

    $map_exists  = ! empty( $map_status['exists'] );
    $map_count   = (int) ( $map_status['entry_count'] ?? 0 );
    $mal_count   = (int) ( $map_status['mal_count']   ?? 0 );
    $map_size    = (int) ( $map_status['size']        ?? 0 );
    $map_updated = ! empty( $map_status['last_updated'] )
        ? $fmt_datetime( $map_status['last_updated'] )
        : '—';
    $map_age_h   = (float) ( $map_status['age_hours'] ?? 0 );
}

/* ───────────────────────────────────────────────
    Log file info（檔案系統）
─────────────────────────────────────────────── */
$upload_dir  = wp_upload_dir();
$log_dir     = trailingslashit( $upload_dir['basedir'] ) . 'anime-sync-pro/logs/';
$log_files   = is_dir( $log_dir ) ? ( glob( $log_dir . '*.log' ) ?: [] ) : [];
$log_count   = count( $log_files );
$log_size    = 0;
foreach ( $log_files as $lf ) {
    if ( is_file( $lf ) ) {
        $log_size += (int) filesize( $lf );
    }
}
$log_size_kb = round( $log_size / 1024, 1 );

/* ───────────────────────────────────────────────
    DB 日誌數量（wp_anime_sync_logs）
─────────────────────────────────────────────── */
$db_log_total = 0;
if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
    global $wpdb;
    $db_log_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}anime_sync_logs" );
}

/* ───────────────────────────────────────────────
    Cron 狀態
─────────────────────────────────────────────── */
$next_daily      = wp_next_scheduled( 'anime_sync_daily_score_update' );
$next_themes_eps = wp_next_scheduled( 'anime_sync_themes_episodes_update' );
$next_weekly     = wp_next_scheduled( 'anime_sync_weekly_cleanup' );
$next_update_map = wp_next_scheduled( 'anime_sync_update_anime_map' );
$last_daily_run  = get_option( 'anime_sync_last_daily_run', '' );
$last_weekly_run = get_option( 'anime_sync_last_weekly_cleanup', '' );
$last_themes_run = get_option( 'anime_sync_last_themes_episodes_run', '' );

$admin_nonce = wp_create_nonce( 'anime_sync_admin_nonce' );
?>

<div class="wrap anime-sync-settings">

    <h1><?php esc_html_e( '設定', 'anime-sync-pro' ); ?>
        <span style="font-size:13px;color:#888;font-weight:normal;">v<?php echo esc_html( $plugin_version ); ?></span>
    </h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( '設定已儲存。', 'anime-sync-pro' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="" id="anime-sync-settings-form">
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
                                '<code>' . esc_html( $site_name . '/' . $plugin_version . ' (' . $site_url . ')' ) . '</code>'
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- 排程設定 -->
        <div class="anime-sync-settings-card">
            <h2><?php esc_html_e( '排程設定', 'anime-sync-pro' ); ?></h2>
            <p class="description">
                <?php printf(
                    esc_html__( '時間以站台時區（%s）顯示，內部以 UTC 儲存。每週清理與 ID 對照表更新為固定排程。', 'anime-sync-pro' ),
                    '<code>' . esc_html( $site_tz_string ) . '</code>'
                ); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="anime_sync_daily_hour_local"><?php esc_html_e( '每日同步時間', 'anime-sync-pro' ); ?></label>
                    </th>
                    <td>
                        <select id="anime_sync_daily_hour_local" name="anime_sync_daily_hour_local">
                            <?php for ( $h = 0; $h < 24; $h++ ) :
                                $utc_h = $local_to_utc_hour( $h );
                            ?>
                                <option value="<?php echo esc_attr( $h ); ?>" <?php selected( $daily_hour_local, $h ); ?>>
                                    <?php echo esc_html( sprintf( '%02d:00 %s（UTC %02d:00）', $h, $site_tz_string, $utc_h ) ); ?>
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
            <h2>
                <?php esc_html_e( '排程狀態', 'anime-sync-pro' ); ?>
                <button type="button" id="btn-reschedule-all" class="button button-small" style="margin-left:12px;vertical-align:middle;">
                    <?php esc_html_e( '強制重新排程', 'anime-sync-pro' ); ?>
                </button>
            </h2>
            <table class="form-table">
                <?php
                $cron_rows = [
                    [ '每日評分同步',   $next_daily,      $last_daily_run  ],
                    [ '主題曲＋集數同步', $next_themes_eps, $last_themes_run ],
                    [ '每週清理',       $next_weekly,     $last_weekly_run ],
                    [ 'ID 對照表更新',  $next_update_map, ''               ],
                ];
                foreach ( $cron_rows as [ $label, $next_ts, $last_str ] ) :
                ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $label ); ?></th>
                    <td>
                        <?php if ( $next_ts ) : ?>
                            <?php printf(
                                esc_html__( '下次：%s', 'anime-sync-pro' ),
                                esc_html( wp_date( 'Y-m-d H:i', $next_ts ) )
                            ); ?>
                        <?php else : ?>
                            <span style="color:#dc3232;"><?php esc_html_e( '未排程', 'anime-sync-pro' ); ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $last_str ) ) : ?>
                            <span style="color:#777;margin-left:12px;">
                                <?php printf( esc_html__( '上次：%s', 'anime-sync-pro' ), esc_html( $fmt_datetime( $last_str ) ) ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
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
                        <p class="description"><?php esc_html_e( '每週清理時，超過此天數的 DB 日誌會被自動刪除。', 'anime-sync-pro' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( '偵錯模式', 'anime-sync-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="anime_sync_debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?> />
                            <?php esc_html_e( '啟用詳細偵錯記錄（記錄 API 請求 raw payload）', 'anime-sync-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( '建議僅在排查問題時開啟，平時關閉可減少 DB 寫入。', 'anime-sync-pro' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" id="btn-save-settings" class="button button-primary">
                <?php esc_html_e( '儲存設定', 'anime-sync-pro' ); ?>
            </button>
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
                            esc_html__( '存在 · 共 %1$s 筆（MAL 對應 %2$s 筆）· 大小 %3$s · 上次更新 %4$s', 'anime-sync-pro' ),
                            esc_html( number_format_i18n( $map_count ) ),
                            esc_html( number_format_i18n( $mal_count ) ),
                            esc_html( size_format( $map_size ) ),
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
                        <?php esc_html_e( '清除外掛 Transient 快取', 'anime-sync-pro' ); ?>
                    </button>
                    <span id="clear-cache-result" style="margin-left:10px;"></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'DB 日誌管理', 'anime-sync-pro' ); ?></th>
                <td>
                    <?php printf(
                        esc_html__( '資料表 %1$s 共 %2$s 筆', 'anime-sync-pro' ),
                        '<code>' . esc_html( $wpdb->prefix ?? 'wp_' ) . 'anime_sync_logs</code>',
                        esc_html( number_format_i18n( $db_log_total ) )
                    ); ?>
                    <br><br>
                    <label>
                        <?php esc_html_e( '清除：', 'anime-sync-pro' ); ?>
                        <select id="db-log-clear-days" style="vertical-align:middle;">
                            <option value="7">7 天前</option>
                            <option value="30" selected>30 天前</option>
                            <option value="60">60 天前</option>
                            <option value="90">90 天前</option>
                            <option value="all"><?php esc_html_e( '全部（危險）', 'anime-sync-pro' ); ?></option>
                        </select>
                    </label>
                    <button type="button" id="btn-clear-db-logs" class="button button-secondary" style="margin-left:6px;">
                        <?php esc_html_e( '清除 DB 日誌', 'anime-sync-pro' ); ?>
                    </button>
                    <span id="clear-db-logs-result" style="margin-left:10px;"></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Log 檔案管理', 'anime-sync-pro' ); ?></th>
                <td>
                    <?php printf(
                        esc_html__( '檔案系統 %1$s 共 %2$d 個檔案，佔用 %3$s KB', 'anime-sync-pro' ),
                        '<code>' . esc_html( str_replace( ABSPATH, '/', $log_dir ) ) . '</code>',
                        $log_count,
                        esc_html( $log_size_kb )
                    ); ?>
                    <br><br>
                    <button type="button" id="btn-clear-log-files" class="button button-secondary"
                            <?php disabled( $log_count, 0 ); ?>>
                        <?php esc_html_e( '刪除所有 .log 檔案', 'anime-sync-pro' ); ?>
                    </button>
                    <span id="clear-log-files-result" style="margin-left:10px;"></span>
                    <p class="description" style="margin-top:8px;">
                        <?php esc_html_e( '此操作僅刪除 uploads/anime-sync-pro/logs/ 內的 .log 檔案，不影響 DB 日誌。', 'anime-sync-pro' ); ?>
                    </p>
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
                <td>
                    <?php echo esc_html( PHP_VERSION ); ?>
                    <?php if ( version_compare( PHP_VERSION, '8.0', '<' ) ) : ?>
                        <span style="color:#dc3232;margin-left:8px;">⚠️ 建議升級至 PHP 8.0+</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'WordPress 版本', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '外掛版本', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( $plugin_version ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '站台時區', 'anime-sync-pro' ); ?></th>
                <td><code><?php echo esc_html( $site_tz_string ); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'mal_index 筆數', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( number_format_i18n( $mal_count ) ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '對照表更新時間', 'anime-sync-pro' ); ?></th>
                <td><?php echo esc_html( $map_updated ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '對照表已存放', 'anime-sync-pro' ); ?></th>
                <td><?php printf( esc_html__( '%.1f 小時', 'anime-sync-pro' ), $map_age_h ); ?></td>
            </tr>
        </table>
    </div>

</div>

<!-- Toast -->
<div id="settings-toast" style="display:none;position:fixed;top:50px;right:20px;z-index:99999;padding:12px 20px;background:#46b450;color:#fff;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.2);font-size:14px;"></div>

<style>
.anime-sync-settings-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px 24px;
    margin-top: 20px;
    max-width: 920px;
}
.anime-sync-settings-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.anime-sync-settings .button[disabled] { opacity:.6; cursor:not-allowed; }
</style>

<script>
( function( $ ) {
    'use strict';

    const nonce = '<?php echo esc_js( $admin_nonce ); ?>';

    function showToast(message, isError) {
        const $t = $('#settings-toast');
        $t.text(message).css('background', isError ? '#dc3232' : '#46b450').fadeIn(200);
        setTimeout(function(){ $t.fadeOut(300); }, 2500);
    }

    // 儲存按鈕防重複
    $('#anime-sync-settings-form').on('submit', function(){
        $('#btn-save-settings').prop('disabled', true).text('儲存中…');
    });

    // 下載對照表
    $('#btn-update-map').on('click', function(){
        const $btn = $(this);
        $btn.prop('disabled', true).text('下載中…');
        $.post(ajaxurl, { action:'anime_sync_update_map', nonce:nonce }, function(resp){
            $btn.prop('disabled', false).text('立即下載 / 更新對照表');
            if (resp && resp.success) {
                showToast('更新成功');
                setTimeout(function(){ location.reload(); }, 1000);
            } else {
                showToast((resp && resp.data) || '更新失敗', true);
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('立即下載 / 更新對照表');
            showToast('網路錯誤', true);
        });
    });

    // 清除 transient 快取
    $('#btn-clear-cache').on('click', function(){
        const $btn = $(this);
        const $result = $('#clear-cache-result');
        $btn.prop('disabled', true);
        $.post(ajaxurl, { action:'anime_sync_clear_cache', nonce:nonce }, function(resp){
            $btn.prop('disabled', false);
            if (resp && resp.success) {
                $result.text(typeof resp.data === 'string' ? resp.data : '已清除').css('color','#46b450');
                showToast('快取已清除');
            } else {
                $result.text('失敗').css('color','#dc3232');
                showToast('清除失敗', true);
            }
            setTimeout(function(){ $result.fadeOut(2000, function(){ $result.text('').show(); }); }, 1500);
        });
    });

    // ✅ 清除 DB 日誌（含天數）
    $('#btn-clear-db-logs').on('click', function(){
        const $btn = $(this);
        const days = $('#db-log-clear-days').val();
        const isAll = (days === 'all');

        if (!confirm(isAll
            ? '⚠️ 確定清除「全部」DB 日誌嗎？此操作無法復原！'
            : '確定清除 ' + days + ' 天前的 DB 日誌嗎？')) {
            return;
        }

        $btn.prop('disabled', true).text('清除中…');

        // all → 傳 1（最小值，伺服器側已 clamp 不會 TRUNCATE）；
        // 若你要支援真正全部刪除，可在 class-admin.php 加 'all' 分支。
        const daysParam = isAll ? 9999 : parseInt(days, 10);

        $.post(ajaxurl, {
            action: 'anime_clear_old_logs',
            nonce:  nonce,
            days:   daysParam
        }, function(resp){
            $btn.prop('disabled', false).text('清除 DB 日誌');
            if (resp && resp.success) {
                const msg = (resp.data && resp.data.message) || '已清除';
                showToast(msg);
                setTimeout(function(){ location.reload(); }, 1000);
            } else {
                showToast((resp && resp.data) || '清除失敗', true);
            }
        }).fail(function(xhr){
            $btn.prop('disabled', false).text('清除 DB 日誌');
            showToast('網路錯誤 (HTTP ' + xhr.status + ')', true);
        });
    });

    // ✅ 清除 Log 檔案（檔案系統）
    $('#btn-clear-log-files').on('click', function(){
        if (!confirm('確定刪除所有 .log 檔案嗎？此操作無法復原！')) return;
        const $btn = $(this);
        $btn.prop('disabled', true).text('刪除中…');
        $.post(ajaxurl, { action:'anime_sync_clear_logs', nonce:nonce }, function(resp){
            $btn.prop('disabled', false).text('刪除所有 .log 檔案');
            if (resp && resp.success) {
                showToast('已刪除 .log 檔案');
                setTimeout(function(){ location.reload(); }, 1000);
            } else {
                showToast((resp && resp.data) || '刪除失敗', true);
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('刪除所有 .log 檔案');
            showToast('網路錯誤', true);
        });
    });

    // ✅ 強制重新排程
    $('#btn-reschedule-all').on('click', function(){
        if (!confirm('將會清除並重新建立所有 cron 排程，繼續嗎？')) return;
        // 觸發儲存表單即可（hook 偵測到 daily_hour 改變才會 reschedule）
        // 這裡簡單做法：提示使用者手動微調 daily_hour 後儲存。
        // 更完整的做法是另開 AJAX action（需在 class-admin.php 加 handler）。
        showToast('請至「每日同步時間」微調後再儲存，即會自動重排所有 cron。', false);
    });

} )( jQuery );
</script>
