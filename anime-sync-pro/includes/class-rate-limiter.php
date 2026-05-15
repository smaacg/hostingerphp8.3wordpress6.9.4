<?php
/**
 * 檔案名稱: includes/class-rate-limiter.php
 *
 * @version 1.1.0
 *
 * Changelog:
 *   1.1.0 — 強化 singleton + Retry-After 整合 + API 統計
 *           - [變更] constructor 改 private，強制使用 get_instance()
 *                   防止多 instance 各自記時間造成 rate limit 失準
 *           - [變更] handle_rate_limit_error() 不再內部 sleep，改回傳秒數
 *                   由呼叫端（api-handler 的 anilist_request helper）決定 sleep 時機
 *                   讀取 Retry-After header，clamp 5~300 秒，fallback 60 秒
 *           - [新增] record_stat() / get_stats() / reset_stats()
 *                   提供 API 呼叫統計（success/failed/rate_limited/retry）
 *                   寫入 wp_options（autoload=false），規模超過 1500 部時建議改用
 *                   wp_cache + shutdown flush 方案
 *
 *   ACE — 修正 PHP 8.1 Deprecated：
 *         wait_if_needed() 的 usleep() 參數強制轉型為 (int)
 *         microtime 存入 transient 時先 (int) round() 避免浮點數累積
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Rate_Limiter {

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Stats option key
     */
    private const STATS_OPTION = 'anime_sync_api_stats';

    /**
     * 各 API 的最小呼叫間隔（毫秒）
     *
     * 設計考量：以「同步完整」為優先，數值偏保守，遠低於各 API 公告上限。
     * - anilist 公告 90 req/min（每 667ms 一次），這裡用 2000ms（30 req/min）
     * - jikan 公告 60 req/min，這裡用 1200ms（50 req/min）
     * - bangumi 無明確公告，1000ms 安全值
     * - animethemes 寬鬆，700ms
     */
    private array $limits = [
        'anilist'     => 2000,
        'jikan'       => 1200,
        'bangumi'     => 1000,
        'animethemes' => 700,
    ];

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    /**
     * 取得 singleton instance
     *
     * 所有呼叫端必須使用此方法取得 instance，不可使用 new。
     */
    public static function get_instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有 constructor，強制 singleton
     */
    private function __construct() {
        // 預留給未來擴充（例如從 option 讀取自訂 limits）
    }

    /**
     * 防止 clone
     */
    private function __clone() {}

    /**
     * 防止 unserialize
     */
    public function __wakeup(): void {
        throw new \Exception( 'Cannot unserialize singleton Anime_Sync_Rate_Limiter' );
    }

    // -------------------------------------------------------------------------
    // 速率控制
    // -------------------------------------------------------------------------

    /**
     * 等待直到可以發送請求
     *
     * 透過 transient 跨 PHP process 同步「上次呼叫時間」，
     * 確保並發場景（cron + 手動匯入同時）也不會超頻。
     */
    public function wait_if_needed( string $api_name ): void {
        $api_name = strtolower( $api_name );

        if ( ! isset( $this->limits[ $api_name ] ) ) {
            return;
        }

        $interval  = $this->limits[ $api_name ];                 // int，單位 ms
        $cache_key = 'anime_sync_last_request_' . $api_name;
        $last_time = get_transient( $cache_key );                // int ms

        if ( $last_time !== false ) {
            $now     = (int) round( microtime( true ) * 1000 );
            $elapsed = $now - (int) $last_time;

            if ( $elapsed < $interval ) {
                $wait_ms = $interval - $elapsed;                 // int ms
                usleep( $wait_ms * 1000 );                       // 轉為微秒
            }
        }

        // TTL 60 秒，閒置一分鐘後自動清掉，避免 wp_options 永久 row
        set_transient( $cache_key, (int) round( microtime( true ) * 1000 ), 60 );
    }

    // -------------------------------------------------------------------------
    // 429 處理
    // -------------------------------------------------------------------------

    /**
     * 解析 429 回應並回傳建議等待秒數
     *
     * 與 1.0.x 不同：本方法不再內部 sleep。呼叫端取得回傳值後自行決定 sleep 時機，
     * 方便整合到重試迴圈中（例如 api-handler 的 anilist_request helper）。
     *
     * 解析優先序：
     *   1. Retry-After header（HTTP 標準）
     *   2. X-RateLimit-Reset header（Unix timestamp，AniList 格式）
     *   3. Fallback 60 秒
     *
     * 安全範圍：clamp 5~300 秒，避免 header 異常值導致過短或過長等待
     *
     * @param mixed  $response  WP_HTTP_Response | headers array
     * @param string $api_name  api 識別字串（用於 log）
     * @return int  建議等待秒數（5~300）
     */
    public function handle_rate_limit_error( $response, string $api_name ): int {
        $retry_after = 60;

        // 相容多種傳入格式
        if ( is_array( $response ) && isset( $response['headers'] ) ) {
            $headers = $response['headers'];
        } elseif ( is_array( $response ) && (
            isset( $response['Retry-After'] ) ||
            isset( $response['retry-after'] )
        ) ) {
            $headers = $response;
        } else {
            $headers = wp_remote_retrieve_headers( $response );
        }

        // header 取值（不分大小寫）
        if ( ! empty( $headers['retry-after'] ) ) {
            $retry_after = (int) $headers['retry-after'];
        } elseif ( ! empty( $headers['Retry-After'] ) ) {
            $retry_after = (int) $headers['Retry-After'];
        } elseif ( ! empty( $headers['x-ratelimit-reset'] ) ) {
            $retry_after = max( 1, (int) $headers['x-ratelimit-reset'] - time() );
        } elseif ( ! empty( $headers['X-RateLimit-Reset'] ) ) {
            $retry_after = max( 1, (int) $headers['X-RateLimit-Reset'] - time() );
        }

        // Clamp 5~300 秒
        $retry_after = max( 5, min( 300, $retry_after ) );

        if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
            Anime_Sync_Error_Logger::log( 'warning', sprintf(
                '%s API rate limited, suggested wait %d seconds',
                ucfirst( $api_name ),
                $retry_after
            ), [
                'api'         => $api_name,
                'retry_after' => $retry_after,
            ] );
        }

        return $retry_after;
    }

    /**
     * 檢查剩餘配額（< 10% 時警告並 sleep 5 秒）
     */
    public function check_remaining( $response, string $api_name ): void {
        if ( is_array( $response ) && isset( $response['headers'] ) ) {
            $headers = $response['headers'];
        } else {
            $headers = wp_remote_retrieve_headers( $response );
        }

        $remaining_raw = $headers['x-ratelimit-remaining'] ?? $headers['X-RateLimit-Remaining'] ?? null;
        if ( $remaining_raw === null ) return;

        $remaining = (int) $remaining_raw;
        $limit_raw = $headers['x-ratelimit-limit'] ?? $headers['X-RateLimit-Limit'] ?? 90;
        $limit     = max( 1, (int) $limit_raw );

        $percentage = ( $remaining / $limit ) * 100;

        if ( $percentage < 10 ) {
            if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
                Anime_Sync_Error_Logger::log( 'warning', sprintf(
                    '%s API quota low: %d/%d remaining (%.1f%%)',
                    ucfirst( $api_name ),
                    $remaining,
                    $limit,
                    $percentage
                ), [
                    'api'        => $api_name,
                    'remaining'  => $remaining,
                    'limit'      => $limit,
                    'percentage' => $percentage,
                ] );
            }
            sleep( 5 );
        }
    }

    // -------------------------------------------------------------------------
    // API 呼叫統計
    // -------------------------------------------------------------------------

    /**
     * 記錄 API 呼叫統計
     *
     * 目前實作：每次呼叫立即 update_option，適用於每次匯入 < 200 部、
     *           總文章數 < 1500 部的網站。50 部匯入約多 250 次 SQL UPDATE，
     *           總耗時 < 1 秒，可忽略。
     *
     * 升級時機：當網站 anime 文章數 > 1500 部，或單次匯入 > 500 部時，
     *           daily cron 的 stats 寫入會累計超過 5~10 秒，
     *           應改為「記憶體累加 + shutdown 一次性寫入」方案。
     *           實作要點：
     *             1. 改用 static $buffer 變數累加
     *             2. register_shutdown_function() 寫入 option
     *             3. wp_cache_get/set 防止多 process 互蓋
     *
     * 偵測方式：檢查 daily cron log，若單次同步 record_stat 累計耗時 > 5 秒則升級。
     *
     * @param string $api   anilist | bangumi | animethemes | jikan
     * @param string $type  success | failed | rate_limited | retry
     */
    public function record_stat( string $api, string $type ): void {
        $api  = strtolower( $api );
        $type = strtolower( $type );

        $valid_types = [ 'success', 'failed', 'rate_limited', 'retry' ];
        if ( ! in_array( $type, $valid_types, true ) ) {
            return;
        }

        $stats = get_option( self::STATS_OPTION, [] );
        if ( ! is_array( $stats ) ) {
            $stats = [];
        }

        if ( ! isset( $stats[ $api ] ) ) {
            $stats[ $api ] = [
                'success'      => 0,
                'failed'       => 0,
                'rate_limited' => 0,
                'retry'        => 0,
                'last_updated' => '',
            ];
        }

        $stats[ $api ][ $type ]++;
        $stats[ $api ]['last_updated'] = current_time( 'mysql' );

        // autoload=false：避免 stats 在每個 page request 都被讀進記憶體
        update_option( self::STATS_OPTION, $stats, false );
    }

    /**
     * 取得目前累積的 stats
     *
     * 用法：
     *   wp option get anime_sync_api_stats --format=json
     *   或在 PHP 內：
     *   $stats = Anime_Sync_Rate_Limiter::get_instance()->get_stats();
     *
     * @return array
     */
    public function get_stats(): array {
        $stats = get_option( self::STATS_OPTION, [] );
        return is_array( $stats ) ? $stats : [];
    }

    /**
     * 重置 stats
     *
     * 建議使用時機：
     *   - 大批匯入前手動呼叫，這樣匯入完看到的數字代表「這批」結果
     *   - 排定的 cron 任務開始前自動呼叫（後續 cron-manager 整體重構時 hook）
     */
    public function reset_stats(): void {
        delete_option( self::STATS_OPTION );
    }
}
