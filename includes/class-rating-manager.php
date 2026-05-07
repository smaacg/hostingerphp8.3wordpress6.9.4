<?php
/**
 * Rating Manager Class
 * 負責 WeixiaoACG+ 評分系統的核心邏輯、資料庫讀寫、REST API
 *
 * Bug fixes:
 *   #1 加權平均：改用 SUM(score * weight) / SUM(weight)，讓 weight 真正生效
 *   #2 健壯性：get_user_weight() 補上 get_userdata() null check
 *   #3 防刷：api_submit_rating() 加上每分鐘最多 5 次的 rate limit
 *   #4 效能：get_global_average() 加上 10 分鐘 transient 快取
 *   #5 健壯性：api_submit_rating() 檢查 $wpdb->insert/update 失敗，寫入 error log
 *   #7 效能：update_post_meta_scores() 回傳 stats，避免重複呼叫 get_stats()
 *   #8 邏輯：api_get_site_ranking() 撈 3 倍資料後 PHP 重新依 weighted 排序
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Rating_Manager {

    /**
     * 資料表名稱
     */
    private string $table;

    /**
     * 加權公式最低門檻票數（低於此數時分數向全站均值靠近）
     */
    private int $min_votes = 5;

    /**
     * Bug #3：評分頻率限制（每分鐘 5 次）
     */
    private const RATE_LIMIT_MAX    = 5;
    private const RATE_LIMIT_PERIOD = MINUTE_IN_SECONDS;

    /**
     * Bug #4：全站平均快取時間
     */
    private const GLOBAL_AVG_CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    /**
     * Bug #8：排行榜撈取倍數（撈這個倍數後 PHP 用 weighted 重新排序）
     */
    private const RANKING_FETCH_MULTIPLIER = 3;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'anime_ratings';
        $this->register_rest_routes();
    }

    // ================================================================
    // REST API 路由註冊
    // ================================================================

    private function register_rest_routes(): void {
        add_action( 'rest_api_init', function () {

            // GET / POST  /wp-json/weixiaoacg/v1/ratings/{anime_id}
            register_rest_route( 'weixiaoacg/v1', '/ratings/(?P<anime_id>\d+)', [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'api_get_ratings' ],
                    'permission_callback' => '__return_true',
                    'args'                => [
                        'anime_id' => [
                            'required'          => true,
                            'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                        ],
                    ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'api_submit_rating' ],
                    'permission_callback' => [ $this, 'require_login' ],
                    'args'                => [
                        'anime_id' => [
                            'required'          => true,
                            'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                        ],
                        'score_story' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_score' ],
                            'sanitize_callback' => fn( $v ) => round( (float) $v, 1 ),
                        ],
                        'score_music' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_score' ],
                            'sanitize_callback' => fn( $v ) => round( (float) $v, 1 ),
                        ],
                        'score_animation' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_score' ],
                            'sanitize_callback' => fn( $v ) => round( (float) $v, 1 ),
                        ],
                        'score_voice' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_score' ],
                            'sanitize_callback' => fn( $v ) => round( (float) $v, 1 ),
                        ],
                    ],
                ],
            ] );

            // GET  /wp-json/weixiaoacg/v1/ranking/site
            register_rest_route( 'weixiaoacg/v1', '/ranking/site', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'api_get_site_ranking' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'limit' => [
                        'default'           => 20,
                        'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v >= 1 && (int) $v <= 50,
                        'sanitize_callback' => fn( $v ) => (int) $v,
                    ],
                ],
            ] );

        } );
    }

    // ================================================================
    // Permission Callbacks
    // ================================================================

    public function require_login( WP_REST_Request $request ): bool|WP_Error {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                '請先登入才能評分',
                [ 'status' => 401 ]
            );
        }
        return true;
    }

    // ================================================================
    // 分數驗證
    // ================================================================

    public function validate_score( $value ): bool {
        $v = (float) $value;
        return $v >= 1.0 && $v <= 10.0;
    }

    // ================================================================
    // API：取得評分統計 + 目前使用者的評分
    // ================================================================

    public function api_get_ratings( WP_REST_Request $request ): WP_REST_Response {
        $anime_id = (int) $request->get_param( 'anime_id' );
        $stats    = $this->get_stats( $anime_id );
        $my_score = null;

        if ( is_user_logged_in() ) {
            $my_score = $this->get_user_rating( $anime_id, get_current_user_id() );
        }

        return rest_ensure_response( [
            'stats'    => $stats,
            'my_score' => $my_score,
        ] );
    }

    // ================================================================
    // API：提交 / 修改評分
    // ================================================================

    public function api_submit_rating( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $anime_id    = (int) $request->get_param( 'anime_id' );
        $user_id     = get_current_user_id();
        $score_story = (float) $request->get_param( 'score_story' );
        $score_music = (float) $request->get_param( 'score_music' );
        $score_anim  = (float) $request->get_param( 'score_animation' );
        $score_voice = (float) $request->get_param( 'score_voice' );

        // 驗證 anime post 是否存在
        if ( get_post_type( $anime_id ) !== 'anime' ) {
            return new WP_Error( 'invalid_anime', '找不到此動畫', [ 'status' => 404 ] );
        }

        // ── Bug #3 修正：防刷頻率限制 ─────────────────────────────────
        $rate_key   = 'asp_rate_user_' . $user_id;
        $rate_count = (int) get_transient( $rate_key );
        if ( $rate_count >= self::RATE_LIMIT_MAX ) {
            return new WP_Error(
                'rate_limited',
                '評分過於頻繁，請稍候 1 分鐘後再試',
                [ 'status' => 429 ]
            );
        }
        set_transient( $rate_key, $rate_count + 1, self::RATE_LIMIT_PERIOD );
        // ──────────────────────────────────────────────────────────────

        // 計算各分類平均作為總分
        $score_overall = round( ( $score_story + $score_music + $score_anim + $score_voice ) / 4, 2 );

        // 計算使用者權重（老用戶 ×1.5，新用戶 ×0.5）
        $weight = $this->get_user_weight( $user_id );

        global $wpdb;
        $now      = current_time( 'mysql' );
        $existing = $this->get_user_rating( $anime_id, $user_id );

        // ── Bug #5 修正：寫入結果檢查 ─────────────────────────────────
        if ( $existing ) {
            // 修改既有評分
            $result = $wpdb->update(
                $this->table,
                [
                    'score_story'     => $score_story,
                    'score_music'     => $score_music,
                    'score_animation' => $score_anim,
                    'score_voice'     => $score_voice,
                    'score_overall'   => $score_overall,
                    'weight'          => $weight,
                    'updated_at'      => $now,
                ],
                [ 'anime_id' => $anime_id, 'user_id' => $user_id ],
                [ '%f', '%f', '%f', '%f', '%f', '%f', '%s' ],
                [ '%d', '%d' ]
            );
        } else {
            // 新增評分
            $result = $wpdb->insert(
                $this->table,
                [
                    'anime_id'        => $anime_id,
                    'user_id'         => $user_id,
                    'score_story'     => $score_story,
                    'score_music'     => $score_music,
                    'score_animation' => $score_anim,
                    'score_voice'     => $score_voice,
                    'score_overall'   => $score_overall,
                    'weight'          => $weight,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                [ '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s' ]
            );
        }

                if ( $result === false ) {
            // ── R-H1 修正：寫入失敗時回退 rate count，避免誤殺使用者配額 ──
            $current = (int) get_transient( $rate_key );
            if ( $current > 0 ) {
                set_transient( $rate_key, $current - 1, self::RATE_LIMIT_PERIOD );
            }
            // ──────────────────────────────────────────────────────────

            // 寫入失敗：記錄錯誤並回傳 500
            if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
                Anime_Sync_Error_Logger::error(
                    'Rating DB write failed',

                    [
                        'anime_id'  => $anime_id,
                        'user_id'   => $user_id,
                        'operation' => $existing ? 'update' : 'insert',
                        'db_error'  => $wpdb->last_error,
                    ]
                );
            }

            return new WP_Error(
                'db_write_failed',
                '儲存評分時發生錯誤，請稍後再試',
                [ 'status' => 500 ]
            );
        }
        // ──────────────────────────────────────────────────────────────

        // Bug #1：清除全站平均快取，下次重算
        delete_transient( 'asp_global_avg' );

        // ── Bug #7 修正：一次呼叫，同時拿到 stats ───────────────────
        $stats = $this->update_post_meta_scores( $anime_id );
        // ──────────────────────────────────────────────────────────────

        return rest_ensure_response( [
            'success'  => true,
            'message'  => $existing ? '評分已更新' : '評分成功',
            'stats'    => $stats,
            'my_score' => $this->get_user_rating( $anime_id, $user_id ),
        ] );
    }

    // ================================================================
    // API：站內排行榜 Top N
    // ================================================================

    public function api_get_site_ranking( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $limit = (int) $request->get_param( 'limit' );

        // ── Bug #8 修正：撈 N 倍數量，PHP 端用 weighted 重新排序 ─────
        $fetch_limit = $limit * self::RANKING_FETCH_MULTIPLIER;

        // ── Bug #1 修正：用 weight 加權的平均 ──────────────────────────
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                anime_id,
                COUNT(*) AS vote_count,
                SUM(weight) AS total_weight,
                SUM(score_overall   * weight) / NULLIF(SUM(weight), 0) AS avg_overall,
                SUM(score_story     * weight) / NULLIF(SUM(weight), 0) AS avg_story,
                SUM(score_music     * weight) / NULLIF(SUM(weight), 0) AS avg_music,
                SUM(score_animation * weight) / NULLIF(SUM(weight), 0) AS avg_animation,
                SUM(score_voice     * weight) / NULLIF(SUM(weight), 0) AS avg_voice
             FROM {$this->table}
             GROUP BY anime_id
             HAVING vote_count >= 1
             ORDER BY avg_overall DESC
             LIMIT %d",
            $fetch_limit
        ) );
        // ──────────────────────────────────────────────────────────────

        $global_avg = $this->get_global_average();
        $result     = [];

        foreach ( $rows as $row ) {
            $post = get_post( (int) $row->anime_id );
            if ( ! $post || $post->post_status !== 'publish' ) {
                continue;
            }

            $weighted = $this->calc_weighted_score(
                (float) $row->avg_overall,
                (int)   $row->vote_count,
                $global_avg
            );

            $result[] = [
                'anime_id'      => (int) $row->anime_id,
                'title'         => get_post_meta( $post->ID, 'anime_title_chinese', true ) ?: $post->post_title,
                'cover'         => get_post_meta( $post->ID, 'anime_cover_image', true ),
                'url'           => get_permalink( $post->ID ),
                'vote_count'    => (int) $row->vote_count,
                'score'         => round( $weighted, 2 ),
                'avg_story'     => round( (float) $row->avg_story, 2 ),
                'avg_music'     => round( (float) $row->avg_music, 2 ),
                'avg_animation' => round( (float) $row->avg_animation, 2 ),
                'avg_voice'     => round( (float) $row->avg_voice, 2 ),
            ];
        }

        // ── Bug #8 修正：PHP 端依 weighted score 重新排序 ─────────────
        usort( $result, fn( $a, $b ) => $b['score'] <=> $a['score'] );
        $result = array_slice( $result, 0, $limit );
        // ──────────────────────────────────────────────────────────────

        return rest_ensure_response( $result );
    }

    // ================================================================
    // 核心：取得單部動畫的統計資料
    // ================================================================

    public function get_stats( int $anime_id ): array {
        global $wpdb;

        // ── Bug #1 修正：用 weight 加權的平均 ──────────────────────────
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) AS vote_count,
                SUM(weight) AS total_weight,
                SUM(score_overall   * weight) / NULLIF(SUM(weight), 0) AS avg_overall,
                SUM(score_story     * weight) / NULLIF(SUM(weight), 0) AS avg_story,
                SUM(score_music     * weight) / NULLIF(SUM(weight), 0) AS avg_music,
                SUM(score_animation * weight) / NULLIF(SUM(weight), 0) AS avg_animation,
                SUM(score_voice     * weight) / NULLIF(SUM(weight), 0) AS avg_voice
             FROM {$this->table}
             WHERE anime_id = %d",
            $anime_id
        ) );
        // ──────────────────────────────────────────────────────────────

        if ( ! $row || (int) $row->vote_count === 0 ) {
            return [
                'vote_count'    => 0,
                'score'         => null,
                'avg_story'     => null,
                'avg_music'     => null,
                'avg_animation' => null,
                'avg_voice'     => null,
                'distribution'  => array_fill( 1, 10, 0 ),
            ];
        }

        $global_avg = $this->get_global_average();
        $weighted   = $this->calc_weighted_score(
            (float) $row->avg_overall,
            (int)   $row->vote_count,
            $global_avg
        );

        // 分布圖：整數區間 1~10
        $dist_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT FLOOR(score_overall) AS bucket, COUNT(*) AS cnt
             FROM {$this->table}
             WHERE anime_id = %d
             GROUP BY bucket
             ORDER BY bucket ASC",
            $anime_id
        ) );

        $distribution = array_fill( 1, 10, 0 );
        foreach ( $dist_rows as $d ) {
            $b = (int) $d->bucket;
            if ( $b >= 1 && $b <= 10 ) {
                $distribution[ $b ] = (int) $d->cnt;
            }
        }

        return [
            'vote_count'    => (int) $row->vote_count,
            'score'         => round( $weighted, 2 ),
            'avg_story'     => round( (float) $row->avg_story, 2 ),
            'avg_music'     => round( (float) $row->avg_music, 2 ),
            'avg_animation' => round( (float) $row->avg_animation, 2 ),
            'avg_voice'     => round( (float) $row->avg_voice, 2 ),
            'distribution'  => $distribution,
        ];
    }

    // ================================================================
    // 取得特定使用者對某部動畫的評分
    // ================================================================

    public function get_user_rating( int $anime_id, int $user_id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT score_story, score_music, score_animation, score_voice, score_overall, updated_at
             FROM {$this->table}
             WHERE anime_id = %d AND user_id = %d
             LIMIT 1",
            $anime_id,
            $user_id
        ) );

        if ( ! $row ) return null;

        return [
            'score_story'     => (float) $row->score_story,
            'score_music'     => (float) $row->score_music,
            'score_animation' => (float) $row->score_animation,
            'score_voice'     => (float) $row->score_voice,
            'score_overall'   => (float) $row->score_overall,
            'updated_at'      => $row->updated_at,
        ];
    }

    // ================================================================
    // 加權評分公式（類 MAL Bayesian）
    // ================================================================

    private function calc_weighted_score( float $avg, int $votes, float $global_avg ): float {
        $m = $this->min_votes;
        return ( $votes / ( $votes + $m ) ) * $avg
             + ( $m    / ( $votes + $m ) ) * $global_avg;
    }

    // ================================================================
    // 全站所有評分的平均值（用於加權公式 C 值）
    // ================================================================

    private function get_global_average(): float {
        // ── Bug #4 修正：transient 快取 10 分鐘 ──────────────────────
        $cached = get_transient( 'asp_global_avg' );
        if ( $cached !== false ) {
            return (float) $cached;
        }

        global $wpdb;
        $avg = (float) $wpdb->get_var(
            "SELECT AVG(score_overall) FROM {$this->table}"
        );
        $avg = $avg > 0 ? $avg : 7.0; // 預設基準 7.0

        set_transient( 'asp_global_avg', $avg, self::GLOBAL_AVG_CACHE_TTL );
        return $avg;
        // ──────────────────────────────────────────────────────────────
    }

    // ================================================================
    // 使用者權重計算
    // 帳號 >= 30天 且 評分 >= 10 部 → ×1.5
    // 新用戶 (< 7 天)             → ×0.5
    // 其餘                         → ×1.0
    // ================================================================

    private function get_user_weight( int $user_id ): float {
        // ── Bug #2 修正：補上 get_userdata() null check ─────────────────
        $user = get_userdata( $user_id );
        if ( ! $user || empty( $user->user_registered ) ) {
            return 1.0;
        }

        $reg_date = strtotime( $user->user_registered );
        if ( $reg_date === false ) {
            return 1.0;
        }
        // ──────────────────────────────────────────────────────────────

        $days_old = ( time() - $reg_date ) / DAY_IN_SECONDS;

        global $wpdb;
        $rated_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT anime_id) FROM {$this->table} WHERE user_id = %d",
            $user_id
        ) );

        if ( $days_old >= 30 && $rated_count >= 10 ) {
            return 1.5;
        }
        if ( $days_old < 7 ) {
            return 0.5;
        }
        return 1.0;
    }

    // ================================================================
    // 更新 post meta，供排行榜頁面直接讀取
    // Bug #7 修正：回傳 stats，避免呼叫端再次呼叫 get_stats()
    // ================================================================

    private function update_post_meta_scores( int $anime_id ): array {
        $stats = $this->get_stats( $anime_id );
        update_post_meta( $anime_id, 'anime_score_site',       $stats['score'] ?? '' );
        update_post_meta( $anime_id, 'anime_score_site_count', $stats['vote_count'] ?? 0 );
        return $stats;
    }
}
