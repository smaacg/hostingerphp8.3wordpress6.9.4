<?php
/**
 * Cron Manager — 排程同步管理
 *
 * 修正紀錄：
 * - 新增 transient lock，防止 season import / daily update 重複執行
 * - run_daily_score_update() 改為分頁查詢，不再 posts_per_page => -1
 * - Rate Limiter 改為單例，不在 loop 內重複 new
 * - run_season_auto_import() 支援 wp_schedule_single_event 傳參
 * - fetch_season_list() 加入 429 / WP_Error 重試機制
 *
 * [修改] 新增：
 * - HOOK_THEMES_EPISODES_UPDATE 常數（每日主題曲＋集數同步）
 * - activate() 加入新排程
 * - deactivate() 加入新排程取消
 * - get_schedule_status() 加入新排程狀態顯示
 * - run_themes_episodes_update() 主方法（主題曲只補缺少 type、集數只補新增集數）
 * - 主題曲鎖定：讀取 anime_themes_locked_keys，跳過已鎖定的 type+sequence
 * - 集數鎖定：讀取 anime_episodes_locked_ids，跳過已鎖定的集數 ID
 * - 人工新增的資料（來源標記 manual）不會被刪除
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Cron_Manager {

    // =========================================================================
    // 排程 Hook 名稱常數
    // =========================================================================
    const HOOK_DAILY_SCORE_UPDATE       = 'anime_sync_daily_score_update';
    const HOOK_WEEKLY_CLEANUP           = 'anime_sync_weekly_cleanup';
    const HOOK_SEASON_IMPORT            = 'anime_sync_season_auto_import';
    const HOOK_UPDATE_MAP               = 'anime_sync_update_anime_map';
    // [修改] 新增：主題曲＋集數每日同步 Hook
    const HOOK_THEMES_EPISODES_UPDATE   = 'anime_sync_themes_episodes_update';

    // Lock TTL（秒）：超過此時間視為鎖已過期（防止崩潰後死鎖）
    const LOCK_TTL_DAILY          = 1800;   // 30 分鐘
    const LOCK_TTL_SEASON         = 3600;   // 60 分鐘
    // [修改] 新增：主題曲＋集數同步鎖 TTL
    const LOCK_TTL_THEMES_EPISODES = 1800;  // 30 分鐘

    private Anime_Sync_Import_Manager $import_manager;
    private Anime_Sync_Error_Logger   $logger;
    private Anime_Sync_Rate_Limiter   $rate_limiter;

    public function __construct( Anime_Sync_Import_Manager $import_manager ) {
        $this->import_manager = $import_manager;
        $this->logger         = new Anime_Sync_Error_Logger();
        $this->rate_limiter = Anime_Sync_Rate_Limiter::get_instance();

        add_filter( 'cron_schedules', [ $this, 'add_custom_schedules' ] );

        add_action( self::HOOK_DAILY_SCORE_UPDATE,     [ $this, 'run_daily_score_update' ] );
        add_action( self::HOOK_WEEKLY_CLEANUP,          [ $this, 'run_weekly_cleanup' ] );
        add_action( self::HOOK_UPDATE_MAP,              [ $this, 'run_update_map' ] );
        add_action( self::HOOK_SEASON_IMPORT,           [ $this, 'run_season_auto_import' ], 10, 2 );
        // [修改] 新增：掛勾主題曲＋集數每日同步
        add_action( self::HOOK_THEMES_EPISODES_UPDATE, [ $this, 'run_themes_episodes_update' ] );
    }

    // =========================================================================
    // 排程間隔定義
    // =========================================================================

    public function add_custom_schedules( array $schedules ): array {
        $schedules['anime_sync_twice_daily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __( 'Anime Sync: 每12小時', 'anime-sync-pro' ),
        ];
        $schedules['anime_sync_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Anime Sync: 每週', 'anime-sync-pro' ),
        ];
        return $schedules;
    }

    // =========================================================================
    // 排程啟用 / 停用
    // =========================================================================

    public static function activate(): void {
        if ( ! wp_next_scheduled( self::HOOK_DAILY_SCORE_UPDATE ) ) {
            $daily_hour = (int) get_option( 'anime_sync_daily_hour', 3 );
            $today_utc  = strtotime( gmdate( "Y-m-d {$daily_hour}:00:00" ) );
            $start_time = $today_utc < time() ? $today_utc + DAY_IN_SECONDS : $today_utc;
            wp_schedule_event( $start_time, 'daily', self::HOOK_DAILY_SCORE_UPDATE );
        }

        if ( ! wp_next_scheduled( self::HOOK_WEEKLY_CLEANUP ) ) {
            wp_schedule_event( strtotime( 'next sunday 04:00:00' ), 'anime_sync_weekly', self::HOOK_WEEKLY_CLEANUP );
        }

        if ( ! wp_next_scheduled( self::HOOK_UPDATE_MAP ) ) {
            wp_schedule_event( strtotime( 'next monday 02:00:00' ), 'anime_sync_weekly', self::HOOK_UPDATE_MAP );
        }

        // [修改] 新增：每日 05:30 執行主題曲＋集數同步（錯開評分更新時間）
        if ( ! wp_next_scheduled( self::HOOK_THEMES_EPISODES_UPDATE ) ) {
            $daily_hour    = (int) get_option( 'anime_sync_daily_hour', 3 );
            $themes_hour   = ( $daily_hour + 2 ) % 24; // 比評分更新晚 2 小時，避免同時壓 API（%24 防止溢位）
            $today_themes  = strtotime( gmdate( "Y-m-d {$themes_hour}:30:00" ) );
            $start_themes  = $today_themes < time() ? $today_themes + DAY_IN_SECONDS : $today_themes;
            wp_schedule_event( $start_themes, 'daily', self::HOOK_THEMES_EPISODES_UPDATE );
        }
    }

    public static function deactivate(): void {
        $hooks = [
            self::HOOK_DAILY_SCORE_UPDATE,
            self::HOOK_WEEKLY_CLEANUP,
            self::HOOK_SEASON_IMPORT,
            self::HOOK_UPDATE_MAP,
            // [修改] 新增：停用時同步取消主題曲＋集數排程
            self::HOOK_THEMES_EPISODES_UPDATE,
        ];
        foreach ( $hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }
    }

    // =========================================================================
    // 任務一：每日評分 / 熱度 / 狀態更新
    // =========================================================================

    public function run_daily_score_update(): void {

        if ( get_transient( 'anime_sync_lock_daily' ) ) {
            $this->logger->log( 'warning', '每日評分更新：已有另一個程序在執行，本次跳過' );
            return;
        }
        set_transient( 'anime_sync_lock_daily', 1, self::LOCK_TTL_DAILY );

        try {
            $this->_run_daily_score_update_inner();
        } finally {
            delete_transient( 'anime_sync_lock_daily' );
        }
    }

    private function _run_daily_score_update_inner(): void {
        Anime_Sync_Performance::set_time_limit( 300 );
        Anime_Sync_Performance::increase_memory_limit( '256M' );

        $this->logger->log( 'info', '每日評分更新開始' );

        $batch_size  = (int) get_option( 'anime_sync_batch_size', 15 );
        $paged       = 1;
        $updated     = 0;
        $skipped     = 0;
        $failed      = 0;
        $cutoff_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );

        do {
            $query = new WP_Query( [
                'post_type'      => 'anime',
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'paged'          => $paged,
                'fields'         => 'ids',
                'no_found_rows'  => false,
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'anime_status',
                        'value'   => 'RELEASING',
                        'compare' => '=',
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key'     => 'anime_status',
                            'value'   => 'FINISHED',
                            'compare' => '=',
                        ],
                        [
                            'key'     => 'anime_end_date',
                            'value'   => $cutoff_date,
                            'compare' => '>=',
                            'type'    => 'DATE',
                        ],
                    ],
                ],
            ] );

            if ( empty( $query->posts ) ) {
                break;
            }

            Anime_Sync_Performance::batch_process(
                $query->posts,
                function( int $post_id ) use ( &$updated, &$skipped, &$failed ): void {
                    $anilist_id = (int) get_post_meta( $post_id, 'anime_anilist_id', true );
                    if ( ! $anilist_id ) return;

                    $this->rate_limiter->wait_if_needed( 'anilist' );

                    $result = $this->import_manager->import_single( $anilist_id, null, 'anilist' );

                    if ( ! empty( $result['skipped'] ) ) {
                        $skipped++;
                    } elseif ( ! empty( $result['success'] ) ) {
                        $updated++;
                    } else {
                        $failed++;
                        $this->logger->log( 'warning', '評分更新失敗', [
                            'post_id'    => $post_id,
                            'anilist_id' => $anilist_id,
                            'error'      => $result['message'] ?? '未知錯誤',
                        ] );
                    }
                },
                $batch_size
            );

            $max_pages = (int) $query->max_num_pages;
            $paged++;

        } while ( $paged <= $max_pages );

        $this->logger->log( 'info', sprintf(
            '每日評分更新完成：成功 %d / 略過 %d / 失敗 %d',
            $updated,
            $skipped,
            $failed
        ) );

        update_option( 'anime_sync_last_daily_run', current_time( 'mysql' ) );
    }

    // =========================================================================
    // 任務二：每週清理
    // =========================================================================

    public function run_weekly_cleanup(): void {
        $this->logger->log( 'info', '每週清理開始' );

        $retention_days = (int) get_option( 'anime_sync_log_retention_days', 30 );
        $deleted_logs   = $this->logger->delete_old_logs( $retention_days );
        $this->logger->log( 'info', "已清除 {$deleted_logs} 筆舊日誌" );

        Anime_Sync_Performance::clear_all_caches();

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_anime_sync_last_request_%'
                OR option_name LIKE '_transient_timeout_anime_sync_last_request_%'
                OR option_name LIKE '_transient_anime_sync_import_lock_%'
                OR option_name LIKE '_transient_timeout_anime_sync_import_lock_%'"
        );

        delete_transient( 'anime_sync_lock_daily' );
        delete_transient( 'anime_sync_lock_season' );
        // [修改] 新增：每週清理順便清除主題曲＋集數同步殘留鎖
        delete_transient( 'anime_sync_lock_themes_episodes' );

        $this->logger->log( 'info', '每週清理完成' );
        update_option( 'anime_sync_last_weekly_cleanup', current_time( 'mysql' ) );
    }

    // =========================================================================
    // 任務三：季度自動匯入
    // =========================================================================

    public function run_season_auto_import( string $season = '', int $year = 0 ): array {

        if ( get_transient( 'anime_sync_lock_season' ) ) {
            $this->logger->log( 'warning', '季度匯入：已有另一個程序在執行，本次跳過' );
            return [ 'success' => false, 'message' => '已鎖定，跳過', 'imported' => 0 ];
        }
        set_transient( 'anime_sync_lock_season', 1, self::LOCK_TTL_SEASON );

        try {
            return $this->_run_season_import_inner( $season, $year );
        } finally {
            delete_transient( 'anime_sync_lock_season' );
        }
    }

    private function _run_season_import_inner( string $season, int $year ): array {
        Anime_Sync_Performance::set_time_limit( 600 );
        Anime_Sync_Performance::increase_memory_limit( '512M' );

        if ( empty( $season ) || $year === 0 ) {
            [ $season, $year ] = $this->get_current_season();
        }

        $this->logger->log( 'info', "季度匯入開始：{$year} {$season}" );

        $media_list = $this->fetch_season_list( $season, $year );

        if ( empty( $media_list ) ) {
            $this->logger->log( 'warning', "季度匯入：{$year} {$season} 無資料" );
            return [ 'success' => false, 'message' => '無資料', 'imported' => 0 ];
        }

        $imported = 0;
        $skipped  = 0;
        $failed   = 0;

        Anime_Sync_Performance::batch_process(
            $media_list,
            function( array $media ) use ( &$imported, &$skipped, &$failed ): void {
                $anilist_id = (int) ( $media['id'] ?? 0 );
                if ( ! $anilist_id ) return;

                $this->rate_limiter->wait_if_needed( 'anilist' );

                $result = $this->import_manager->import_single( $anilist_id, null, 'anilist' );

                if ( ! empty( $result['skipped'] ) ) {
                    $skipped++;
                } elseif ( ! empty( $result['success'] ) ) {
                    $imported++;
                } else {
                    $failed++;
                    $this->logger->log( 'warning', '季度匯入單筆失敗', [
                        'anilist_id' => $anilist_id,
                        'error'      => $result['message'] ?? '未知錯誤',
                    ] );
                }
            },
            15
        );

        $summary = [
            'success'  => true,
            'season'   => $season,
            'year'     => $year,
            'total'    => count( $media_list ),
            'imported' => $imported,
            'skipped'  => $skipped,
            'failed'   => $failed,
        ];

        $this->logger->log( 'info', '季度匯入完成', $summary );
        return $summary;
    }

    // =========================================================================
    // 任務四：Bangumi ID 地圖更新
    // =========================================================================

    public function run_update_map(): void {
        $this->logger->log( 'info', 'Bangumi ID 地圖更新開始' );

        $mapper = new Anime_Sync_ID_Mapper();
        $result = $mapper->download_and_cache_map();

        if ( $result ) {
            $this->logger->log( 'info', 'Bangumi ID 地圖更新成功，寫入 ' . $result . ' bytes' );
        } else {
            $this->logger->log( 'error', 'Bangumi ID 地圖更新失敗' );
        }
    }

    // =========================================================================
    // [修改] 任務五：每日主題曲＋集數同步（新增整個方法）
    //
    // 設計原則：
    //   主題曲 — 以 type+sequence（如 OP1、ED2）為唯一鍵，
    //             只補 API 回傳但本地沒有的項目；
    //             anime_themes_locked_keys（JSON 陣列，存 "OP1"、"ED1" 等）
    //             中的鍵會跳過，永不覆寫；
    //             來源標記為 manual 的項目（source === 'manual'）永不刪除。
    //
    //   集數   — 以集數 ID（Bangumi ep.id）為唯一鍵，
    //             只補 API 回傳但本地沒有的項目；
    //             anime_episodes_locked_ids（JSON 陣列，存 ep.id）
    //             中的 ID 會跳過；
    //             本地有但 API 沒有的項目（含人工新增）一律保留不刪除。
    // =========================================================================

    public function run_themes_episodes_update(): void {

        // 防止重複執行
        if ( get_transient( 'anime_sync_lock_themes_episodes' ) ) {
            $this->logger->log( 'warning', '主題曲＋集數同步：已有另一個程序在執行，本次跳過' );
            return;
        }
        set_transient( 'anime_sync_lock_themes_episodes', 1, self::LOCK_TTL_THEMES_EPISODES );

        try {
            $this->_run_themes_episodes_inner();
        } finally {
            delete_transient( 'anime_sync_lock_themes_episodes' );
        }
    }

    private function _run_themes_episodes_inner(): void {
        Anime_Sync_Performance::set_time_limit( 600 );
        Anime_Sync_Performance::increase_memory_limit( '256M' );

        // 取得當前季節，只同步本季連載中的新番
        [ $season, $year ] = $this->get_current_season();

        $this->logger->log( 'info', "主題曲＋集數同步開始：{$year} {$season}" );

        // 查詢本季所有 RELEASING 狀態的已發布文章
        $post_ids = get_posts( [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'anime_status',
                    'value'   => 'RELEASING',
                    'compare' => '=',
                ],
                [
                    'key'     => 'anime_season',
                    'value'   => $season,
                    'compare' => '=',
                ],
                [
                    'key'     => 'anime_season_year',
                    'value'   => (string) $year,
                    'compare' => '=',
                ],
            ],
        ] );

        if ( empty( $post_ids ) ) {
            $this->logger->log( 'info', "主題曲＋集數同步：本季無連載中作品，跳過" );
            return;
        }

        $themes_updated   = 0;
        $episodes_updated = 0;
        $total            = count( $post_ids );

        $this->logger->log( 'info', "主題曲＋集數同步：共 {$total} 部作品待處理" );

        foreach ( $post_ids as $post_id ) {

            // ----------------------------------------------------------------
            // 主題曲同步
            // ----------------------------------------------------------------
            $mal_id = (int) get_post_meta( $post_id, 'anime_mal_id', true );

            if ( $mal_id ) {
                $this->rate_limiter->wait_if_needed( 'animethemes' );

                // [修改] 呼叫 import_manager 的公開包裝方法（需在 class-import-manager.php 新增）
                $api_result = $this->import_manager->fetch_themes_only( $mal_id );

                if ( ! empty( $api_result['themes'] ) && is_array( $api_result['themes'] ) ) {

                    // 讀取現有主題曲（本地）
                    $old_json   = (string) get_post_meta( $post_id, 'anime_themes', true );
                    $old_themes = json_decode( $old_json, true );
                    if ( ! is_array( $old_themes ) ) {
                        $old_themes = [];
                    }

                    // [修改] 讀取已鎖定的 type+sequence 鍵（例如 ["OP1","ED1"]）
                    // 鎖定後，即使 API 回傳新資料也不會覆寫該鍵
                    $locked_keys_json = (string) get_post_meta( $post_id, 'anime_themes_locked_keys', true );
                    $locked_keys      = json_decode( $locked_keys_json, true );
                    if ( ! is_array( $locked_keys ) ) {
                        $locked_keys = [];
                    }
                    $locked_keys_index = array_flip( $locked_keys ); // 快速查表

                    // 建立本地主題曲索引（type+sequence → 陣列位置）
                    // 同時保留 source=manual 的項目（人工新增）
                    $old_index = []; // key => true
                    foreach ( $old_themes as $t ) {
                        $key             = ( $t['type'] ?? '' ) . ( $t['sequence'] ?? '' );
                        $old_index[$key] = true;
                    }

                    $added = 0;
                    foreach ( $api_result['themes'] as $new_theme ) {
                        $key = ( $new_theme['type'] ?? '' ) . ( $new_theme['sequence'] ?? '' );

                        // [修改] 若該鍵已鎖定，跳過（不新增也不覆寫）
                        if ( isset( $locked_keys_index[$key] ) ) {
                            continue;
                        }

                        // 本地已存在該 type+sequence，跳過（保留人工整理的資料）
                        if ( isset( $old_index[$key] ) ) {
                            continue;
                        }

                        // 新鍵，補入
                        $old_themes[]    = $new_theme;
                        $old_index[$key] = true;
                        $added++;
                    }

                    // [修改] 人工新增的項目（source === 'manual'）已包含在 old_themes 中，
                    // 因為我們只做 append，不做 replace，所以永遠不會被刪除

                    if ( $added > 0 ) {
                        update_post_meta(
                            $post_id,
                            'anime_themes',
                            wp_json_encode( array_values( $old_themes ), JSON_UNESCAPED_UNICODE )
                        );
                        // 更新同步時間戳（ACF 欄位 anime_themes_synced_at 已在 class-acf-fields.php 定義）
                        update_post_meta( $post_id, 'anime_themes_synced_at', current_time( 'mysql' ) );
                        $this->logger->log( 'info', "主題曲新增 {$added} 首", [
                            'post_id' => $post_id,
                            'mal_id'  => $mal_id,
                        ] );
                        $themes_updated++;
                    }
                }
            }

            // ----------------------------------------------------------------
            // 集數同步
            // ----------------------------------------------------------------
            $bangumi_id = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );

            if ( $bangumi_id ) {
                $this->rate_limiter->wait_if_needed( 'bangumi' );

                // [修改] 呼叫 import_manager 的公開包裝方法（需在 class-import-manager.php 新增）
                $api_episodes = $this->import_manager->fetch_episodes_only( $bangumi_id );

                if ( ! empty( $api_episodes ) && is_array( $api_episodes ) ) {

                    // 讀取現有集數（本地）
                    $old_ep_json  = (string) get_post_meta( $post_id, 'anime_episodes_json', true );
                    $old_episodes = json_decode( $old_ep_json, true );
                    if ( ! is_array( $old_episodes ) ) {
                        $old_episodes = [];
                    }

                    // [修改] 讀取已鎖定的集數 ID 陣列（例如 [1001, 1002]）
                    // 鎖定後即使 API 有新資料也不覆寫該集數
                    $locked_ep_json = (string) get_post_meta( $post_id, 'anime_episodes_locked_ids', true );
                    $locked_ep_ids  = json_decode( $locked_ep_json, true );
                    if ( ! is_array( $locked_ep_ids ) ) {
                        $locked_ep_ids = [];
                    }
                    $locked_ep_index = array_flip( $locked_ep_ids ); // 快速查表

                    // 建立本地集數索引（ep.id → true）
                    // [修改] 所有本地集數（含 source=manual 人工新增）都加入索引，
                    // 因為我們只做 append，不做 replace，人工新增的永遠不會被刪除
                    $old_ep_index = [];
                    foreach ( $old_episodes as $ep ) {
                        $ep_id = $ep['id'] ?? null;
                        if ( $ep_id !== null ) {
                            $old_ep_index[$ep_id] = true;
                        }
                    }

                    $added_eps = 0;
                    foreach ( $api_episodes as $new_ep ) {
                        $ep_id = $new_ep['id'] ?? null;
                        if ( $ep_id === null ) continue;

                        // [修改] 若該集數 ID 已鎖定，跳過（不新增也不覆寫）
                        if ( isset( $locked_ep_index[$ep_id] ) ) {
                            continue;
                        }

                        // 本地已有該集數，跳過（保留已翻譯內容）
                        if ( isset( $old_ep_index[$ep_id] ) ) {
                            continue;
                        }

                        // 新集數，補入
                        $old_episodes[]      = $new_ep;
                        $old_ep_index[$ep_id] = true;
                        $added_eps++;
                    }

                    if ( $added_eps > 0 ) {
                        update_post_meta(
                            $post_id,
                            'anime_episodes_json',
                            wp_json_encode( array_values( $old_episodes ), JSON_UNESCAPED_UNICODE )
                        );
                        // 更新同步時間戳（ACF 欄位 anime_episodes_synced_at 已在 class-acf-fields.php 定義）
                        update_post_meta( $post_id, 'anime_episodes_synced_at', current_time( 'mysql' ) );
                        $this->logger->log( 'info', "集數新增 {$added_eps} 集", [
                            'post_id'    => $post_id,
                            'bangumi_id' => $bangumi_id,
                        ] );
                        $episodes_updated++;
                    }
                }
            }

            // 每篇文章處理完休息 1 秒，避免 API 速率限制
            sleep( 1 );
        }

        $this->logger->log( 'info', sprintf(
            '主題曲＋集數同步完成：主題曲更新 %d 部 / 集數更新 %d 部',
            $themes_updated,
            $episodes_updated
        ) );

        update_option( 'anime_sync_last_themes_episodes_run', current_time( 'mysql' ) );
    }

    // =========================================================================
    // 輔助方法
    // =========================================================================

    private function get_current_season(): array {
        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );

        $season = match ( true ) {
            $month >= 1  && $month <= 3  => 'WINTER',
            $month >= 4  && $month <= 6  => 'SPRING',
            $month >= 7  && $month <= 9  => 'SUMMER',
            $month >= 10 && $month <= 12 => 'FALL',
            default                      => 'WINTER',
        };

        return [ $season, $year ];
    }

    private function fetch_season_list( string $season, int $year ): array {
        $query = <<<'GQL'
        query ($season: MediaSeason, $year: Int, $page: Int) {
            Page(page: $page, perPage: 50) {
                pageInfo { hasNextPage }
                media(
                    season: $season
                    seasonYear: $year
                    type: ANIME
                    format_in: [TV, TV_SHORT, ONA, OVA, MOVIE, SPECIAL]
                    sort: [POPULARITY_DESC]
                ) {
                    id
                    title { romaji native }
                    format
                    episodes
                    status
                }
            }
        }
        GQL;

        $all_media = [];
        $page      = 1;

        do {
            $this->rate_limiter->wait_if_needed( 'anilist' );

            $body = $this->anilist_request( $query, [
                'season' => $season,
                'year'   => $year,
                'page'   => $page,
            ] );

            if ( $body === null ) {
                $this->logger->log( 'error', "fetch_season_list 第 {$page} 頁請求失敗，停止" );
                break;
            }

            $page_data   = $body['data']['Page'] ?? [];
            $media_items = $page_data['media'] ?? [];
            $has_next    = $page_data['pageInfo']['hasNextPage'] ?? false;

            $all_media = array_merge( $all_media, $media_items );
            $page++;

        } while ( $has_next && $page <= 10 );

        return $all_media;
    }

    private function anilist_request( string $gql, array $variables, int $max_retries = 3 ): ?array {
        $attempt = 0;

        while ( $attempt < $max_retries ) {
            $response = wp_remote_post( 'https://graphql.anilist.co', [
                'body'    => wp_json_encode( [
                    'query'     => $gql,
                    'variables' => $variables,
                ] ),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'timeout' => 20,
            ] );

            if ( is_wp_error( $response ) ) {
                $attempt++;
                $this->logger->log( 'warning', 'AniList 請求 WP_Error，第 ' . $attempt . ' 次重試', [
                    'error' => $response->get_error_message(),
                ] );
                sleep( 2 ** $attempt );
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( $code === 429 ) {
                $this->rate_limiter->handle_rate_limit_error( $response, 'anilist' );
                $attempt++;
                continue;
            }

            if ( $code !== 200 ) {
                $attempt++;
                $this->logger->log( 'warning', "AniList 回應 HTTP {$code}，第 {$attempt} 次重試" );
                sleep( 2 ** $attempt );
                continue;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! is_array( $body ) ) {
                $attempt++;
                sleep( 2 ** $attempt );
                continue;
            }

            return $body;
        }

        return null;
    }

    // =========================================================================
    // 工具方法：取得排程狀態
    // =========================================================================

    public static function get_schedule_status(): array {
        $hooks = [
            self::HOOK_DAILY_SCORE_UPDATE       => '每日評分更新',
            self::HOOK_WEEKLY_CLEANUP            => '每週清理',
            self::HOOK_UPDATE_MAP                => 'Bangumi 地圖更新',
            self::HOOK_SEASON_IMPORT             => '季度自動匯入',
            // [修改] 新增：顯示主題曲＋集數同步排程狀態
            self::HOOK_THEMES_EPISODES_UPDATE   => '每日主題曲＋集數同步',
        ];

        $status = [];
        foreach ( $hooks as $hook => $label ) {
            $timestamp        = wp_next_scheduled( $hook );
            $status[ $label ] = $timestamp
                ? wp_date( 'Y-m-d H:i:s', $timestamp )
                : null;
        }
        return $status;
    }
}
