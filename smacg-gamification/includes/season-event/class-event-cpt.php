<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * 季賽事件 CPT（搬自 theme/inc/season-event-cpt.php）
 *
 * Post type: smacg_season_event
 * Archive  : /events/
 *
 * Meta keys（_smacg_event_* 前綴）：
 *   _smacg_event_starts_at      DATETIME
 *   _smacg_event_ends_at        DATETIME
 *   _smacg_event_action_type    string (comment / follow / watchlist / rating / login_streak / ...)
 *   _smacg_event_target         int    (目標數值)
 *   _smacg_event_reward_exp     int
 *   _smacg_event_reward_badge   int    (badge post_id)
 *   _smacg_event_reward_title   string
 *   _smacg_event_ended_flag     '1'    (settle 完成標記)
 *   _smacg_event_final_snapshot string (json: top N 用戶 + 完成人數)
 */
class Event_CPT {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ __CLASS__, 'register' ], 10 );
    }

    /* ==========================================================
     * 註冊 CPT
     * ========================================================== */
    public static function register() {
        register_post_type( SMACG_EVENT_CPT, [
            'labels' => [
                'name'               => '季賽活動',
                'singular_name'      => '季賽活動',
                'menu_name'          => '季賽活動',
                'add_new'            => '新增活動',
                'add_new_item'       => '新增季賽活動',
                'edit_item'          => '編輯活動',
                'new_item'           => '新活動',
                'view_item'          => '查看活動',
                'search_items'       => '搜尋活動',
                'not_found'          => '找不到活動',
                'not_found_in_trash' => '回收桶中沒有活動',
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-awards',
            'menu_position'      => 26,
            'has_archive'        => 'events',
            'rewrite'            => [ 'slug' => 'event', 'with_front' => false ],
            'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author' ],
            'capability_type'    => 'post',
        ] );
    }

    /* ==========================================================
     * 取得活動完整 meta（給模板用）
     * ========================================================== */
    public static function get_meta( $event_id ) {
        $event_id = (int) $event_id;
        if ( $event_id <= 0 ) return null;

        $post = get_post( $event_id );
        if ( ! $post || $post->post_type !== SMACG_EVENT_CPT ) return null;

        return [
            'id'           => $event_id,
            'title'        => $post->post_title,
            'excerpt'      => $post->post_excerpt,
            'content'      => $post->post_content,
            'status'       => $post->post_status,
            'permalink'    => get_permalink( $event_id ),
            'thumbnail'    => get_the_post_thumbnail_url( $event_id, 'large' ),
            'starts_at'    => get_post_meta( $event_id, '_smacg_event_starts_at',    true ),
            'ends_at'      => get_post_meta( $event_id, '_smacg_event_ends_at',      true ),
            'action_type'  => get_post_meta( $event_id, '_smacg_event_action_type',  true ),
            'target'       => (int) get_post_meta( $event_id, '_smacg_event_target',       true ),
            'reward_exp'   => (int) get_post_meta( $event_id, '_smacg_event_reward_exp',   true ),
            'reward_badge' => (int) get_post_meta( $event_id, '_smacg_event_reward_badge', true ),
            'reward_title' => get_post_meta( $event_id, '_smacg_event_reward_title',  true ),
            'is_ended'     => (bool) get_post_meta( $event_id, '_smacg_event_ended_flag', true ),
            'is_active'    => self::is_active( $event_id ),
        ];
    }

    public static function is_active( $event_id ) {
        $event_id = (int) $event_id;
        $starts   = get_post_meta( $event_id, '_smacg_event_starts_at', true );
        $ends     = get_post_meta( $event_id, '_smacg_event_ends_at',   true );
        if ( ! $starts || ! $ends ) return false;
        $now = current_time( 'timestamp' );
        return $now >= strtotime( $starts ) && $now <= strtotime( $ends );
    }

    /* ==========================================================
     * 取得目前所有「進行中」活動
     * ========================================================== */
    public static function get_active_events() {
        $now = current_time( 'mysql' );
        $q   = new \WP_Query( [
            'post_type'      => SMACG_EVENT_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => '_smacg_event_starts_at', 'value' => $now, 'compare' => '<=', 'type' => 'DATETIME' ],
                [ 'key' => '_smacg_event_ends_at',   'value' => $now, 'compare' => '>=', 'type' => 'DATETIME' ],
            ],
            'no_found_rows' => true,
            'orderby'       => 'meta_value',
            'meta_key'      => '_smacg_event_ends_at',
            'order'         => 'ASC',
        ] );

        $events = [];
        foreach ( $q->posts as $p ) $events[] = self::get_meta( $p->ID );
        return $events;
    }
}
