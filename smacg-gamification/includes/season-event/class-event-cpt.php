<?php
/**
 * Season Event - CPT 註冊 + helper API
 *
 * 原檔：blocksy-child/inc/season-event-cpt.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\SeasonEvent;

defined( 'ABSPATH' ) || exit;

class CPT {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ], 11 );
        add_action( 'init', [ __CLASS__, 'maybe_flush' ], 99 );
    }

    /* ---------- 註冊 CPT ---------- */
    public static function register() {
        $labels = [
            'name'                  => '季度活動',
            'singular_name'         => '季度活動',
            'menu_name'             => '🏆 季度活動',
            'name_admin_bar'        => '季度活動',
            'add_new'               => '新增活動',
            'add_new_item'          => '新增季度活動',
            'edit_item'             => '編輯活動',
            'new_item'              => '新活動',
            'view_item'             => '查看活動',
            'all_items'             => '全部活動',
            'search_items'          => '搜尋活動',
            'not_found'             => '沒有找到活動',
            'not_found_in_trash'    => '垃圾桶中無活動',
            'featured_image'        => '活動 Banner',
            'set_featured_image'    => '設定 Banner',
            'remove_featured_image' => '移除 Banner',
            'use_featured_image'    => '使用為 Banner',
        ];

        register_post_type( SMACG_EVENT_CPT, [
            'labels'              => $labels,
            'description'         => '季度活動 / 排行賽事',
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false,
            'menu_position'       => 30,
            'menu_icon'           => 'dashicons-awards',
            'capability_type'     => 'post',
            'has_archive'         => 'events',
            'rewrite'             => [ 'slug' => 'event', 'with_front' => false ],
            'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'hierarchical'        => false,
        ] );
    }

    public static function maybe_flush() {
        if ( get_option( 'smacg_event_cpt_flushed' ) !== '1' ) {
            flush_rewrite_rules( false );
            update_option( 'smacg_event_cpt_flushed', '1' );
        }
    }

    /* ---------- Task type 設定 ---------- */
    public static function task_options() {
        return [
            'exp_gain' => [
                'label' => '累積 EXP',
                'unit'  => 'EXP',
                'desc'  => '活動期間累積獲得的 EXP 達標',
            ],
            'watchlist_completed' => [
                'label' => '完成觀看數',
                'unit'  => '部',
                'desc'  => '活動期間將觀看清單標記「完成」的作品數',
            ],
            'comment_count' => [
                'label' => '留言數',
                'unit'  => '則',
                'desc'  => '活動期間發表並通過審核的留言數',
            ],
            'rating_count' => [
                'label' => '評分數',
                'unit'  => '次',
                'desc'  => '活動期間給出的作品評分次數',
            ],
            'manual' => [
                'label' => '手動指定',
                'unit'  => '次',
                'desc'  => '由管理員透過 admin 工具手動標記達成',
            ],
        ];
    }

    public static function task_label( $key ) {
        $opts = self::task_options();
        return $opts[ $key ]['label'] ?? $key;
    }

    public static function task_unit( $key ) {
        $opts = self::task_options();
        return $opts[ $key ]['unit'] ?? '';
    }

    /* ---------- Meta 讀取 ---------- */
    public static function get_meta( $post_id ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) return [];

        $banner_id = (int) get_post_meta( $post_id, '_smacg_event_banner', true );
        if ( ! $banner_id ) $banner_id = (int) get_post_thumbnail_id( $post_id );

        return [
            'id'              => $post_id,
            'title'           => get_the_title( $post_id ),
            'permalink'       => get_permalink( $post_id ),
            'excerpt'         => get_the_excerpt( $post_id ),
            'banner_id'       => $banner_id,
            'banner_url'      => $banner_id ? wp_get_attachment_image_url( $banner_id, 'large' ) : '',
            'start'           => (string) get_post_meta( $post_id, '_smacg_event_start', true ),
            'end'             => (string) get_post_meta( $post_id, '_smacg_event_end', true ),
            'task_type'       => (string) get_post_meta( $post_id, '_smacg_event_task_type', true ) ?: 'exp_gain',
            'task_target'     => (int) get_post_meta( $post_id, '_smacg_event_task_target', true ),
            'reward_exp'      => (int) get_post_meta( $post_id, '_smacg_event_reward_exp', true ),
            'reward_badge'    => (int) get_post_meta( $post_id, '_smacg_event_reward_badge', true ),
            'reward_title'    => (string) get_post_meta( $post_id, '_smacg_event_reward_title', true ),
            'max_participants'=> (int) get_post_meta( $post_id, '_smacg_event_max_participants', true ),
            'status'          => self::get_status( $post_id ),
        ];
    }

    public static function get_status( $post_id ) {
        $start = get_post_meta( $post_id, '_smacg_event_start', true );
        $end   = get_post_meta( $post_id, '_smacg_event_end',   true );

        if ( empty( $start ) || empty( $end ) ) return 'invalid';

        $now  = current_time( 'timestamp' );
        $ts_s = strtotime( $start );
        $ts_e = strtotime( $end );

        if ( ! $ts_s || ! $ts_e ) return 'invalid';
        if ( $now < $ts_s ) return 'upcoming';
        if ( $now > $ts_e ) return 'ended';
        return 'active';
    }

    public static function get_active_events( $limit = 10 ) {
        $posts = get_posts( [
            'post_type'      => SMACG_EVENT_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, (int) $limit ),
            'orderby'        => 'meta_value',
            'meta_key'       => '_smacg_event_end',
            'order'          => 'ASC',
        ] );
        return array_filter( $posts, function ( $p ) {
            return self::get_status( $p->ID ) === 'active';
        } );
    }

    public static function get_upcoming_events( $limit = 10 ) {
        $posts = get_posts( [
            'post_type'      => SMACG_EVENT_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, (int) $limit ),
            'orderby'        => 'meta_value',
            'meta_key'       => '_smacg_event_start',
            'order'          => 'ASC',
        ] );
        return array_filter( $posts, function ( $p ) {
            return self::get_status( $p->ID ) === 'upcoming';
        } );
    }

    /* ---------- Badge 選單 ---------- */
    public static function get_badge_options() {
        $cpt = defined( 'SMACG_BADGE_SLUG' ) ? SMACG_BADGE_SLUG : 'badge';
        if ( ! post_type_exists( $cpt ) ) return [];

        $posts = get_posts( [
            'post_type'      => $cpt,
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        $out = [];
        foreach ( $posts as $p ) $out[ $p->ID ] = $p->post_title;
        return $out;
    }
}

CPT::init();
