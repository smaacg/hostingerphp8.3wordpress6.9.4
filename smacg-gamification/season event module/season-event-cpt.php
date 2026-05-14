<?php
/**
 * Season Event — CPT 註冊 + helper API
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-4
 *
 * 註冊：
 *   post_type:  smacg_season_event
 *   slug base:  event   →  /event/{post_slug}/
 *
 * Meta keys（皆 post_meta）：
 *   _smacg_event_start            datetime  YYYY-mm-dd HH:ii:ss
 *   _smacg_event_end              datetime
 *   _smacg_event_task_type        string    exp_gain | watchlist_completed | comment_count | rating_count | manual
 *   _smacg_event_task_target      int       目標數值（>=1）
 *   _smacg_event_reward_exp       int       EXP 獎勵
 *   _smacg_event_reward_badge     int       GamiPress badge post_id（0 = 不發 badge）
 *   _smacg_event_reward_title     string    稱號（選填）
 *   _smacg_event_max_participants int       0 = 無限
 *   _smacg_event_banner           int       attachment id
 *
 * 動態狀態（不存 DB，每次讀取時即時計算）：
 *   smacg_event_get_status( $post_id ) → upcoming | active | ended | invalid
 *
 * Helper：
 *   smacg_get_event_meta( $post_id )
 *   smacg_get_active_events()
 *   smacg_get_upcoming_events()
 *   smacg_event_get_status( $post_id )
 *   smacg_event_task_label( $key )
 *   smacg_event_task_options()
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數
   ============================================================ */
const SMACG_EVENT_CPT = 'smacg_season_event';

/* ============================================================
   一、註冊 CPT
   ============================================================ */
add_action( 'init', function () {
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
        'show_in_rest'        => false, // 全部用 classic editor + meta box
        'menu_position'       => 30,
        'menu_icon'           => 'dashicons-awards',
        'capability_type'     => 'post',
        'has_archive'         => 'events',          // /events/
        'rewrite'             => [ 'slug' => 'event', 'with_front' => false ],
        'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
        'hierarchical'        => false,
    ] );
}, 11 );

/* ---- 啟用時 flush rewrite ---- */
add_action( 'after_switch_theme', function () {
    flush_rewrite_rules( false );
} );

/* ---- 一次性 flush（CPT 剛上線時） ---- */
add_action( 'init', function () {
    if ( get_option( 'smacg_event_cpt_flushed' ) !== '1' ) {
        flush_rewrite_rules( false );
        update_option( 'smacg_event_cpt_flushed', '1' );
    }
}, 99 );

/* ============================================================
   二、Task type 設定
   ============================================================ */

/**
 * 任務類型清單
 *
 * @return array<string, array{label:string, unit:string, desc:string}>
 */
function smacg_event_task_options() {
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

function smacg_event_task_label( $key ) {
    $opts = smacg_event_task_options();
    return $opts[ $key ]['label'] ?? $key;
}
function smacg_event_task_unit( $key ) {
    $opts = smacg_event_task_options();
    return $opts[ $key ]['unit'] ?? '';
}

/* ============================================================
   三、Meta 讀取 helper
   ============================================================ */

/**
 * 讀取活動完整 meta（標準化）
 *
 * @param int $post_id
 * @return array
 */
function smacg_get_event_meta( $post_id ) {
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
        'status'          => smacg_event_get_status( $post_id ),
    ];
}

/**
 * 計算活動狀態
 *
 * @return string upcoming | active | ended | invalid
 */
function smacg_event_get_status( $post_id ) {
    $start = get_post_meta( $post_id, '_smacg_event_start', true );
    $end   = get_post_meta( $post_id, '_smacg_event_end',   true );

    if ( empty( $start ) || empty( $end ) ) return 'invalid';

    $now    = current_time( 'timestamp' );
    $ts_s   = strtotime( $start );
    $ts_e   = strtotime( $end );

    if ( ! $ts_s || ! $ts_e ) return 'invalid';
    if ( $now < $ts_s ) return 'upcoming';
    if ( $now > $ts_e ) return 'ended';
    return 'active';
}

/**
 * 取目前進行中的活動
 *
 * @param int $limit
 * @return WP_Post[]
 */
function smacg_get_active_events( $limit = 10 ) {
    $posts = get_posts( [
        'post_type'      => SMACG_EVENT_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => max( 1, (int) $limit ),
        'orderby'        => 'meta_value',
        'meta_key'       => '_smacg_event_end',
        'order'          => 'ASC',
    ] );

    return array_filter( $posts, function ( $p ) {
        return smacg_event_get_status( $p->ID ) === 'active';
    } );
}

/**
 * 取即將開始的活動
 */
function smacg_get_upcoming_events( $limit = 10 ) {
    $posts = get_posts( [
        'post_type'      => SMACG_EVENT_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => max( 1, (int) $limit ),
        'orderby'        => 'meta_value',
        'meta_key'       => '_smacg_event_start',
        'order'          => 'ASC',
    ] );

    return array_filter( $posts, function ( $p ) {
        return smacg_event_get_status( $p->ID ) === 'upcoming';
    } );
}

/* ============================================================
   四、Badge 清單 helper（給後台 select 用）
   ============================================================ */

/**
 * 取得所有 GamiPress badge（成就 post）
 *
 * @return array<int, string>  [ post_id => title ]
 */
function smacg_event_get_badge_options() {
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
