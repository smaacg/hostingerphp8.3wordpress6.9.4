<?php
/**
 * Template Name: 番組表 - 季度詳細列表
 * File: blocksy-child/page-bangumi.php
 * Version: 1.3.3
 * Date: 2026-05-18
 *
 * Changelog
 *  v1.3.3 (2026-05-18)
 *    - 改用 WP_Query + get_post_meta（避開 MySQL 8 strict mode 對 GROUP BY 的問題）
 *    - 是否顯示「星期分頁」改為條件式：當季 + 至少 1 部有實際 weekday
 *    - 非當季或全部 weekday=0 時：隱藏星期分頁、平鋪所有卡片、不顯示「其他」標題
 *    - 平均分顯示修正為 10 分制（原 100 / 10）
 *    - 卡片新增資料條（製作公司 + 台灣播放平台 icons + 播出時間）
 *
 * @package weixiaoacg
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 * 區段 1：URL 解析、季節判定
 * ============================================================ */
$ym_raw = get_query_var( 'bangumi_ym' );
if ( ! $ym_raw && isset( $_SERVER['REQUEST_URI'] ) ) {
    if ( preg_match( '#/bangumi/(\d{6})/?#', $_SERVER['REQUEST_URI'], $m ) ) {
        $ym_raw = $m[1];
    }
}
if ( function_exists( 'smacg_bangumi_normalize_ym' ) ) {
    $ym = smacg_bangumi_normalize_ym( $ym_raw );
} else {
    $ym = $ym_raw ?: date_i18n( 'Ym' );
}

if ( function_exists( 'smacg_bangumi_parse_ym' ) ) {
    $ctx = smacg_bangumi_parse_ym( $ym );
} else {
    $y = (int) substr( $ym, 0, 4 );
    $m = (int) substr( $ym, 4, 2 );
    $season_key = $m <= 3 ? 'WINTER' : ( $m <= 6 ? 'SPRING' : ( $m <= 9 ? 'SUMMER' : 'FALL' ) );
    $season_zh  = [ 'WINTER' => '冬季', 'SPRING' => '春季', 'SUMMER' => '夏季', 'FALL' => '秋季' ][ $season_key ];
    $ctx = [
        'year'       => $y,
        'month'      => $m,
        'season'     => $season_key,
        'season_key' => $season_key,
        'season_zh'  => $season_zh,
        'label'      => sprintf( '%d 年 %s新番', $y, $season_zh ),
    ];
}

$prev_ym = function_exists( 'smacg_bangumi_shift_ym' ) ? smacg_bangumi_shift_ym( $ym, -1 ) : '';
$next_ym = function_exists( 'smacg_bangumi_shift_ym' ) ? smacg_bangumi_shift_ym( $ym, +1 ) : '';


/* 當季判定 */
$current_ym = function_exists( 'smacg_bangumi_current_ym' ) ? smacg_bangumi_current_ym() : date_i18n( 'Ym' );
$is_current_season = ( $ym === $current_ym );

/* 季節主題色 */
$season_themes = [
    'SPRING' => [ 'main' => '#f9a8d4', 'soft' => '#fce7f3', 'icon' => '🌸' ],
    'SUMMER' => [ 'main' => '#60a5fa', 'soft' => '#dbeafe', 'icon' => '🌊' ],
    'FALL'   => [ 'main' => '#fb923c', 'soft' => '#fed7aa', 'icon' => '🍁' ],
    'WINTER' => [ 'main' => '#94a3b8', 'soft' => '#e2e8f0', 'icon' => '❄️' ],
];
$theme_key = $ctx['season_key'] ?? ( $ctx['season'] ?? 'SPRING' );
$theme = $season_themes[ $theme_key ] ?? $season_themes['SPRING'];

/* ============================================================
 * 區段 2：WP_Query 撈本季作品 + 個別 get_post_meta
 * ============================================================ */
global $wpdb;

$season_key = $ctx['season_key'] ?? $ctx['season'] ?? 'SPRING';
$year       = (int) $ctx['year'];

$q = new WP_Query( [
    'post_type'      => 'anime',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'no_found_rows'  => true,
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => 'anime_season',      'value' => $season_key, 'compare' => '=' ],
        [ 'key' => 'anime_season_year', 'value' => $year,       'compare' => '=', 'type' => 'NUMERIC' ],
    ],
    'orderby'        => 'meta_value_num',
    'meta_key'       => 'anime_popularity',
    'order'          => 'DESC',
] );

$rows = [];
$tw_urls_by_post = [];

if ( $q->have_posts() ) {
    foreach ( $q->posts as $post_obj ) {
        $pid = (int) $post_obj->ID;
        $m   = get_post_meta( $pid );

        $tw_urls = [];
        foreach ( $m as $mk => $mv ) {
            if ( strpos( $mk, 'anime_tw_streaming_url_' ) === 0 && ! empty( $mv[0] ) ) {
                $platform = substr( $mk, strlen( 'anime_tw_streaming_url_' ) );
                $tw_urls[ $platform ] = $mv[0];
            }
        }
        $tw_urls_by_post[ $pid ] = $tw_urls;

        $rows[] = [
            'ID'             => $pid,
            'post_title'     => $post_obj->post_title,
            'post_name'      => $post_obj->post_name,
            'post_content'   => $post_obj->post_content,
            'post_excerpt'   => $post_obj->post_excerpt,
            'cover'          => $m['anime_cover_image'][0]        ?? '',
            'title_cn'       => $m['anime_title_chinese'][0]      ?? '',
            'title_jp'       => $m['anime_title_native'][0]       ?? '',
            'title_en'       => $m['anime_title_english'][0]      ?? '',
            'title_romaji'   => $m['anime_title_romaji'][0]       ?? '',
            'synopsis'       => $m['anime_synopsis_chinese'][0]   ?? '',
            'studios'        => $m['anime_studios'][0]            ?? '',
            'staff'          => $m['anime_staff_json'][0]         ?? '',
            'cast'           => $m['anime_cast_json'][0]          ?? '',
            'episodes_json'  => $m['anime_episodes_json'][0]      ?? '',
            'themes'         => $m['anime_themes'][0]             ?? '',
            'streaming'      => $m['anime_streaming'][0]          ?? '',
            'tw_platforms'   => $m['anime_tw_streaming'][0]       ?? '',
            'tw_other'       => $m['anime_tw_streaming_other'][0] ?? '',
            'tw_broadcast'   => $m['anime_tw_broadcast'][0]       ?? '',
            'trailer'        => $m['anime_trailer_url'][0]        ?? '',
            'score'          => isset( $m['anime_score_anilist'][0] ) ? (float) $m['anime_score_anilist'][0] : null,
            'popularity'     => (int) ( $m['anime_popularity'][0] ?? 0 ),
            'ep_total'       => (int) ( $m['anime_episodes'][0]   ?? 0 ),
            'ep_aired'       => (int) ( $m['anime_episodes_aired'][0] ?? 0 ),
            'next_airing'    => $m['anime_next_airing'][0]        ?? '',
            'start_date'     => $m['anime_start_date'][0]         ?? '',
            'source'         => $m['anime_source'][0]             ?? '',
            'format'         => $m['anime_format'][0]             ?? '',
            'status'         => $m['anime_status'][0]             ?? '',
            'official'       => $m['anime_official_site'][0]      ?? '',
            'user_status'    => '',
            'user_progress'  => 0,
        ];
    }
}
wp_reset_postdata();

/* user_status 表（如果有） */
$current_uid     = get_current_user_id();
$status_table    = $wpdb->prefix . 'anime_user_status';
$has_status_table = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $status_table ) );

if ( $has_status_table && $current_uid > 0 && $rows ) {
    $ids = array_map( 'intval', wp_list_pluck( $rows, 'ID' ) );
    $in  = implode( ',', $ids );
    $us_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT anime_id, status, progress FROM {$status_table}
         WHERE user_id=%d AND anime_id IN ({$in})",
        $current_uid
    ), ARRAY_A );
    $us_map = [];
    foreach ( $us_rows as $u ) {
        $us_map[ (int) $u['anime_id'] ] = $u;
    }
    foreach ( $rows as $i => $r ) {
        if ( isset( $us_map[ $r['ID'] ] ) ) {
            $rows[ $i ]['user_status']   = $us_map[ $r['ID'] ]['status'];
            $rows[ $i ]['user_progress'] = (int) $us_map[ $r['ID'] ]['progress'];
        }
    }
}

/* ============================================================
 * 區段 3：解析 JSON / 陣列欄位
 * ============================================================ */
$tw_platform_labels = [
    'bahamut'      => '巴哈動畫瘋',  'hami'      => 'Hami Video',  'myvideo'   => 'MyVideo',
    'linetv'       => 'LINE TV',     'friday'    => 'friDay影音',  'ofiii'     => 'Ofiii',
    'catchplay'    => 'CatchPlay+',  'bilibili'  => 'B站台灣',     'ani_one'   => 'Ani-One',
    'muse'         => 'Muse 木棉花', 'mighty'    => '曼迪 YT',     'ani_mi'    => 'Ani-Mi',
    'netflix'      => 'Netflix',     'disney'    => 'Disney+',     'litv'      => 'LiTV',
    'tropicsanime' => '回歸線娛樂',  'iqiyi'     => '愛奇藝',      'renta'     => 'renta!',
    'anipass'      => 'AniPASS',     'amazon'    => 'Prime Video', 'crunchyroll' => 'Crunchyroll',
];

$source_labels = [
    'ORIGINAL' => '原創', 'MANGA' => '漫畫改編', 'LIGHT_NOVEL' => '輕小說改編',
    'NOVEL' => '小說改編', 'VISUAL_NOVEL' => '視覺小說改編', 'VIDEO_GAME' => '遊戲改編',
    'GAME' => '遊戲改編', 'COMIC' => '漫畫改編', 'WEB_NOVEL' => '網路小說改編',
    'DOUJINSHI' => '同人誌改編', 'LIVE_ACTION' => '真人改編', 'ANIME' => '動畫改編',
    'MULTIMEDIA_PROJECT' => '多媒體企劃', 'PICTURE_BOOK' => '繪本改編', 'OTHER' => '其他',
];

$format_labels = [
    'TV' => 'TV', 'TV_SHORT' => 'TV 短篇', 'MOVIE' => '劇場版',
    'SPECIAL' => '特別篇', 'OVA' => 'OVA', 'ONA' => 'ONA', 'MUSIC' => '音樂',
];

$weekday_zh = [ 1 => '週一', 2 => '週二', 3 => '週三', 4 => '週四', 5 => '週五', 6 => '週六', 7 => '週日' ];

$parse_trailers = function( $raw ) {
    if ( ! $raw ) return [];
    $lines = preg_split( '/[\r\n,;]+/u', $raw );
    $out = []; $idx = 0;
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        $title = '';
        if ( strpos( $line, '|' ) !== false ) {
            list( $url, $title ) = array_map( 'trim', explode( '|', $line, 2 ) );
        } else {
            $parts = preg_split( '/\s+/', $line );
            $url   = trim( $parts[0] );
            if ( count( $parts ) > 1 ) {
                $title = trim( implode( ' ', array_slice( $parts, 1 ) ) );
            }
        }
        if ( ! preg_match( '#^https?://#i', $url ) ) continue;
        $vid = '';
        if ( preg_match( '#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})#i', $url, $mm ) ) {
            $vid = $mm[1];
        }
        $idx++;
        $out[] = [
            'url'   => $url,
            'vid'   => $vid,
            'thumb' => $vid ? "https://i.ytimg.com/vi/{$vid}/hqdefault.jpg" : '',
            'title' => $title !== '' ? $title : ( 'PV ' . $idx ),
        ];
    }
    return $out;
};

$parse_staff = function( $json ) {
    if ( ! $json ) return [];
    $arr = json_decode( $json, true );
    if ( ! is_array( $arr ) ) return [];
    $out = [];
    foreach ( $arr as $row ) {
        if ( ! is_array( $row ) ) continue;
        $name = $row['name_cn'] ?? ( $row['name'] ?? '' );
        $role = $row['relation'] ?? ( $row['role'] ?? '' );
        if ( $name === '' ) continue;
        $out[] = [ 'role' => $role, 'name' => $name ];
    }
    return $out;
};

$parse_cast = function( $json ) {
    if ( ! $json ) return [];
    $arr = json_decode( $json, true );
    if ( ! is_array( $arr ) ) return [];
    $out = [];
    foreach ( $arr as $row ) {
        if ( ! is_array( $row ) ) continue;
        $char_name = '';
        if ( isset( $row['character']['name_cn'] ) && $row['character']['name_cn'] !== '' ) {
            $char_name = $row['character']['name_cn'];
        } elseif ( isset( $row['character']['name'] ) ) {
            $char_name = $row['character']['name'];
        } elseif ( isset( $row['character_name'] ) ) {
            $char_name = $row['character_name'];
        }
        $actor_name = '';
        if ( isset( $row['actors'][0]['name'] ) ) {
            $actor_name = $row['actors'][0]['name'];
        } elseif ( isset( $row['actor_name'] ) ) {
            $actor_name = $row['actor_name'];
        }
        if ( $char_name === '' || $actor_name === '' ) continue;
        $out[] = [ 'char' => $char_name, 'actor' => $actor_name ];
    }
    return $out;
};

$parse_themes = function( $json ) {
    if ( ! $json ) return [ 'op' => [], 'ed' => [] ];
    $arr = json_decode( $json, true );
    if ( ! is_array( $arr ) ) return [ 'op' => [], 'ed' => [] ];
    $out = [ 'op' => [], 'ed' => [] ];
    foreach ( $arr as $row ) {
        if ( ! is_array( $row ) ) continue;
        $slug  = strtoupper( $row['slug'] ?? ( $row['type'] ?? '' ) );
        $title = $row['title'] ?? ( $row['song']['title'] ?? '' );
        $artist = '';
        if ( isset( $row['song']['artists'][0]['name'] ) ) {
            $artist = $row['song']['artists'][0]['name'];
        } elseif ( isset( $row['artist'] ) ) {
            $artist = $row['artist'];
        }
        $key = strpos( $slug, 'OP' ) === 0 ? 'op' : ( strpos( $slug, 'ED' ) === 0 ? 'ed' : '' );
        if ( $key === '' || $title === '' ) continue;
        $out[ $key ][] = [ 'title' => $title, 'artist' => $artist ];
    }
    return $out;
};

$parse_streaming = function( $json ) {
    if ( ! $json ) return [];
    $arr = json_decode( $json, true );
    if ( ! is_array( $arr ) ) return [];
    $out = [];
    foreach ( $arr as $row ) {
        if ( ! is_array( $row ) ) continue;
        $site = $row['site'] ?? ( $row['name'] ?? '' );
        $url  = $row['url']  ?? '';
        if ( $site === '' || $url === '' ) continue;
        $out[] = [ 'site' => $site, 'url' => $url ];
    }
    return $out;
};

$parse_tw_platforms = function( $raw ) {
    if ( ! $raw ) return [];
    if ( is_array( $raw ) ) return $raw;
    if ( is_serialized( $raw ) ) {
        $arr = @unserialize( $raw );
        return is_array( $arr ) ? $arr : [];
    }
    $decoded = json_decode( $raw, true );
    if ( is_array( $decoded ) ) return $decoded;
    return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
};

/* ============================================================
 * 區段 4：處理每筆資料 + 統計
 * ============================================================ */
$posts = [];
$by_weekday = [ 0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => [] ];
$stat_total = 0;
$stat_owned = 0;
$stat_watching = 0;
$stat_completed = 0;
$score_sum = 0;
$score_cnt = 0;
$first_cover = '';
$has_real_weekday = false;

foreach ( $rows as $r ) {
    $pid = (int) $r['ID'];

    $weekday = 0;
    $ts_src = $r['next_airing'] ?: $r['start_date'];
    if ( $ts_src ) {
        $ts = strtotime( $ts_src );
        if ( $ts ) {
            $weekday = (int) date( 'N', $ts );
        }
    }

    $title_cn   = $r['title_cn'] ?: ( $r['title_en'] ?: ( $r['title_romaji'] ?: $r['post_title'] ) );
    $score      = $r['score'] !== null ? (float) $r['score'] : null;
    $score_disp = $score !== null ? round( $score / 10, 1 ) : null;
    $ep_total   = (int) $r['ep_total'];
    $ep_aired   = (int) $r['ep_aired'];

    $tw_platforms = $parse_tw_platforms( $r['tw_platforms'] );
    $tw_urls      = $tw_urls_by_post[ $pid ] ?? [];

    $item = [
        'id'         => $pid,
        'url'        => get_permalink( $pid ),
        'title_cn'   => $title_cn,
        'title_jp'   => $r['title_jp'] ?: '',
        'title_en'   => $r['title_en'] ?: '',
        'cover'      => $r['cover'] ?: '',
        'synopsis'   => $r['synopsis'] ?: ( $r['post_excerpt'] ?: '' ),
        'studios'    => $r['studios'] ?: '',
        'source'     => $r['source'] ?: '',
        'source_zh'  => $source_labels[ $r['source'] ?? '' ] ?? '',
        'format'     => $r['format'] ?: '',
        'format_zh'  => $format_labels[ $r['format'] ?? '' ] ?? '',
        'status'     => $r['status'] ?: '',
        'score'      => $score,
        'score_disp' => $score_disp,
        'popularity' => (int) $r['popularity'],
        'ep_total'   => $ep_total,
        'ep_aired'   => $ep_aired,
        'next_airing'=> $r['next_airing'] ?: '',
        'start_date' => $r['start_date'] ?: '',
        'weekday'    => $weekday,
        'tw_broadcast' => $r['tw_broadcast'] ?: '',
        'tw_other'   => $r['tw_other'] ?: '',
        'official'   => $r['official'] ?: '',
        'trailers'   => $parse_trailers( $r['trailer'] ),
        'staff'      => $parse_staff( $r['staff'] ),
        'cast'       => $parse_cast( $r['cast'] ),
        'themes'     => $parse_themes( $r['themes'] ),
        'streaming'  => $parse_streaming( $r['streaming'] ),
        'tw_platforms' => $tw_platforms,
        'tw_urls'    => $tw_urls,
        'user_status'  => $r['user_status'] ?? '',
        'user_progress'=> (int) ( $r['user_progress'] ?? 0 ),
    ];

    $posts[] = $item;
    $by_weekday[ $weekday ][] = $item;
    if ( $weekday >= 1 && $weekday <= 7 ) $has_real_weekday = true;

    $stat_total++;
    if ( $item['user_status'] !== '' ) $stat_owned++;
    if ( $item['user_status'] === 'watching' ) $stat_watching++;
    if ( $item['user_status'] === 'completed' ) $stat_completed++;
    if ( $score !== null ) { $score_sum += $score; $score_cnt++; }
    if ( ! $first_cover && $item['cover'] ) $first_cover = $item['cover'];
}

/* 平均分（10 分制） */
$avg_score = $score_cnt > 0 ? round( $score_sum / $score_cnt / 10, 1 ) : null;

/* 是否顯示星期分頁：當季 + 至少 1 部有實際 weekday */
$show_weekday_tabs = ( $is_current_season && $has_real_weekday );

/* ============================================================
 * 區段 5：SEO
 * ============================================================ */
$canonical = home_url( "/bangumi/{$ym}/" );
$seo_title = sprintf(
    '%s｜共 %d 部%s｜微笑動漫',
    $ctx['label'],
    $stat_total,
    $avg_score !== null ? '・平均 ' . $avg_score . ' 分' : ''
);
$seo_desc = sprintf(
    '%s 完整列表，共 %d 部新番動畫，提供大綱、配音、製作人員、OP/ED、PV、台灣串流平台、海外播放時間。',
    $ctx['label'],
    $stat_total
);

$seo_ctx = [
    'label'     => $ctx['label'],
    'canonical' => $canonical,
    'title'     => $seo_title,
    'desc'      => $seo_desc,
    'description' => $seo_desc,
    'og_image'  => $first_cover,
    'total'     => $stat_total,
    'avg_score' => $avg_score,
];

if ( function_exists( 'smacg_bangumi_render_meta' ) ) {
    add_action( 'wp_head', function () use ( $seo_ctx ) { smacg_bangumi_render_meta( $seo_ctx ); }, 1 );
}
if ( function_exists( 'smacg_bangumi_render_og' ) ) {
    add_action( 'wp_head', function () use ( $seo_ctx ) { smacg_bangumi_render_og( $seo_ctx ); }, 2 );
}
if ( function_exists( 'smacg_bangumi_render_schema' ) ) {
    add_action( 'wp_head', function () use ( $seo_ctx, $posts ) { smacg_bangumi_render_schema( $seo_ctx, $posts ); }, 3 );
}
add_filter( 'pre_get_document_title', function () use ( $seo_title ) { return $seo_title; }, 99 );
add_filter( 'body_class', function ( $c ) { $c[] = 'is-bangumi-season'; return $c; } );

get_header();
?>

<style id="bgm-vars">
:root,
.is-bangumi-season {
    --bgm-main: <?php echo esc_attr( $theme['main'] ); ?>;
    --bgm-soft: <?php echo esc_attr( $theme['soft'] ); ?>;
}
</style>

<main class="bgm-main">

    <nav class="bgm-breadcrumb" aria-label="breadcrumb">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">首頁</a>
        <span>›</span>
        <a href="<?php echo esc_url( home_url( '/bangumi/' ) ); ?>">新番表</a>
        <span>›</span>
        <span aria-current="page"><?php echo esc_html( $ctx['label'] ); ?></span>
    </nav>

    <section class="bgm-hero">
        <div class="bgm-hero-inner">
            <div class="bgm-hero-badge"><?php echo esc_html( $theme['icon'] ); ?> <?php echo esc_html( $ctx['season_zh'] ); ?>季</div>
            <h1 class="bgm-hero-title"><?php echo esc_html( $ctx['label'] ); ?></h1>
            <p class="bgm-hero-sub"><?php echo esc_html( $seo_desc ); ?></p>

            <div class="bgm-nav">
                <?php if ( $prev_ym ) : ?>
                <a class="bgm-nav-btn" href="<?php echo esc_url( home_url( "/bangumi/{$prev_ym}/" ) ); ?>" rel="prev">← 上一季</a>
                <?php endif; ?>
                <a class="bgm-nav-btn is-current" href="<?php echo esc_url( $canonical ); ?>" aria-current="page"><?php echo esc_html( $ctx['label'] ); ?></a>
                <?php if ( $next_ym ) : ?>
                <a class="bgm-nav-btn" href="<?php echo esc_url( home_url( "/bangumi/{$next_ym}/" ) ); ?>" rel="next">下一季 →</a>
                <?php endif; ?>
                <a class="bgm-nav-btn is-archive" href="<?php echo esc_url( home_url( '/bangumi/archive/' ) ); ?>">📚 歷年存檔</a>
            </div>

            <div class="bgm-stats">
                <div class="bgm-stat">
                    <span class="bgm-stat-n"><?php echo (int) $stat_total; ?></span>
                    <span class="bgm-stat-l">總作品</span>
                </div>
                <div class="bgm-stat">
                    <span class="bgm-stat-n"><?php echo $avg_score !== null ? esc_html( $avg_score ) : '–'; ?></span>
                    <span class="bgm-stat-l">平均分</span>
                </div>
                <?php if ( $current_uid > 0 ) : ?>
                <div class="bgm-stat">
                    <span class="bgm-stat-n"><?php echo (int) $stat_owned; ?></span>
                    <span class="bgm-stat-l">我的收藏</span>
                </div>
                <div class="bgm-stat">
                    <span class="bgm-stat-n"><?php echo (int) $stat_watching; ?></span>
                    <span class="bgm-stat-l">追番中</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="bgm-toolbar">
        <?php if ( $show_weekday_tabs ) : ?>
        <div class="bgm-days" role="tablist" aria-label="按星期篩選">
            <button class="bgm-day is-active" data-day="all" role="tab" aria-selected="true">
                全部<span class="bgm-day-n">(<?php echo (int) $stat_total; ?>)</span>
            </button>
            <?php for ( $d = 1; $d <= 7; $d++ ) : if ( empty( $by_weekday[ $d ] ) ) continue; ?>
                <button class="bgm-day" data-day="<?php echo (int) $d; ?>" role="tab" aria-selected="false">
                    <?php echo esc_html( $weekday_zh[ $d ] ); ?>
                    <span class="bgm-day-n">(<?php echo count( $by_weekday[ $d ] ); ?>)</span>
                </button>
            <?php endfor; ?>
            <?php if ( ! empty( $by_weekday[0] ) ) : ?>
                <button class="bgm-day" data-day="0" role="tab" aria-selected="false">
                    待定<span class="bgm-day-n">(<?php echo count( $by_weekday[0] ); ?>)</span>
                </button>
            <?php endif; ?>
        </div>
        <?php else : ?>
        <div class="bgm-days-empty"><span class="bgm-result-count">共 <?php echo (int) $stat_total; ?> 部作品</span></div>
        <?php endif; ?>

        <div class="bgm-sort-wrap">
            <label for="bgm-sort" class="bgm-sort-label">排序</label>
            <select id="bgm-sort" class="bgm-sort">
                <option value="default">人氣高 → 低</option>
                <option value="score">評分高 → 低</option>
                <option value="ep">集數多 → 少</option>
            </select>
        </div>
    </section>

    <section class="bgm-grid-wrap">
    <?php if ( $show_weekday_tabs ) : ?>
        <?php for ( $d = 1; $d <= 7; $d++ ) :
            $list = $by_weekday[ $d ];
            if ( empty( $list ) ) continue; ?>
            <div class="bgm-group" data-group="<?php echo (int) $d; ?>">
                <h2 class="bgm-group-title"><?php echo esc_html( $weekday_zh[ $d ] ); ?>　<span class="bgm-group-n"><?php echo count( $list ); ?> 部</span></h2>
                <div class="bgm-grid">
                    <?php foreach ( $list as $p ) : ?>
                        <?php echo bgm_render_card( $p, $tw_platform_labels, $weekday_zh ); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endfor; ?>
        <?php if ( ! empty( $by_weekday[0] ) ) : ?>
            <div class="bgm-group" data-group="0">
                <h2 class="bgm-group-title">播出時間待定　<span class="bgm-group-n"><?php echo count( $by_weekday[0] ); ?> 部</span></h2>
                <div class="bgm-grid">
                    <?php foreach ( $by_weekday[0] as $p ) : ?>
                        <?php echo bgm_render_card( $p, $tw_platform_labels, $weekday_zh ); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <div class="bgm-group is-flat" data-group="all">
            <div class="bgm-grid">
                <?php foreach ( $posts as $p ) : ?>
                    <?php echo bgm_render_card( $p, $tw_platform_labels, $weekday_zh ); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( $stat_total === 0 ) : ?>
        <div class="bgm-empty">
            <p>本季尚未有作品資料。</p>
            <a class="bgm-nav-btn" href="<?php echo esc_url( home_url( '/bangumi/archive/' ) ); ?>">查看歷年存檔 →</a>
        </div>
    <?php endif; ?>
    </section>

    <div class="bgm-sheet" id="bgm-sheet" role="dialog" aria-modal="true" aria-label="作品詳情" aria-hidden="true">
        <div class="bgm-sheet-backdrop" data-bgm-close></div>
        <div class="bgm-sheet-panel" role="document">
            <button class="bgm-sheet-close" type="button" aria-label="關閉" data-bgm-close>×</button>
            <div class="bgm-sheet-body"></div>
        </div>
    </div>

</main>

<?php
get_footer();

/* ============================================================
 * Helper：渲染單張卡片（acgsecrets 風：小封面 + 資料條）
 * ============================================================ */
function bgm_render_card( $p, $tw_platform_labels, $weekday_zh ) {
    $status_label = [
        'watching'  => '追番中',
        'want'      => '想看',
        'completed' => '已完結',
        'dropped'   => '已棄',
    ];
    $is_hot = ( $p['score'] !== null && $p['score'] >= 80 );
    $progress_pct = ( $p['ep_total'] > 0 && $p['user_progress'] > 0 )
        ? min( 100, round( $p['user_progress'] / $p['ep_total'] * 100 ) ) : 0;

    /* 平台 icons（最多 4 個，超過顯示 +N） */
    $tw_icons = [];
    if ( ! empty( $p['tw_platforms'] ) ) {
        foreach ( (array) $p['tw_platforms'] as $key ) {
            if ( isset( $tw_platform_labels[ $key ] ) ) {
                $tw_icons[] = [ 'key' => $key, 'label' => $tw_platform_labels[ $key ] ];
            }
        }
    }

    ob_start();
    ?>
    <article class="bgm-card<?php echo $p['user_status'] ? ' has-status status-' . esc_attr( $p['user_status'] ) : ''; ?>"
             id="anime-<?php echo (int) $p['id']; ?>"
             data-anime-id="<?php echo (int) $p['id']; ?>"
             data-score="<?php echo esc_attr( (string) ( $p['score'] ?? 0 ) ); ?>"
             data-ep="<?php echo esc_attr( (string) $p['ep_total'] ); ?>"
             data-pop="<?php echo esc_attr( (string) $p['popularity'] ); ?>">
        <button class="bgm-card-trigger" type="button" aria-expanded="false" aria-controls="bgm-detail-<?php echo (int) $p['id']; ?>">
            <div class="bgm-card-cover">
                <?php if ( $p['cover'] ) : ?>
                    <img src="<?php echo esc_url( $p['cover'] ); ?>"
                         alt="<?php echo esc_attr( $p['title_cn'] ); ?>"
                         loading="lazy" decoding="async">
                <?php else : ?>
                    <div class="bgm-card-noimg">?</div>
                <?php endif; ?>

                <?php if ( $p['score_disp'] !== null ) : ?>
                    <span class="bgm-card-score<?php echo $is_hot ? ' is-hot' : ''; ?>">
                        ★ <?php echo esc_html( $p['score_disp'] ); ?>
                    </span>
                <?php endif; ?>

                <?php if ( $p['user_status'] && isset( $status_label[ $p['user_status'] ] ) ) : ?>
                    <span class="bgm-card-chip"><?php echo esc_html( $status_label[ $p['user_status'] ] ); ?></span>
                <?php endif; ?>

                <?php if ( $progress_pct > 0 ) : ?>
                    <div class="bgm-card-progress"><span style="width:<?php echo (int) $progress_pct; ?>%"></span></div>
                <?php endif; ?>
            </div>

            <div class="bgm-card-meta">
                <div class="bgm-card-title"><?php echo esc_html( $p['title_cn'] ); ?></div>
                <?php if ( $p['title_jp'] ) : ?>
                    <div class="bgm-card-jp"><?php echo esc_html( $p['title_jp'] ); ?></div>
                <?php endif; ?>

                <div class="bgm-card-bar">
                    <?php if ( $p['format_zh'] ) : ?>
                        <span class="bgm-card-pill"><?php echo esc_html( $p['format_zh'] ); ?></span>
                    <?php endif; ?>
                    <?php if ( $p['ep_total'] > 0 ) : ?>
                        <span class="bgm-card-pill">共 <?php echo (int) $p['ep_total']; ?> 集</span>
                    <?php endif; ?>
                    <?php if ( $p['weekday'] > 0 && isset( $weekday_zh[ $p['weekday'] ] ) ) : ?>
                        <span class="bgm-card-pill is-day"><?php echo esc_html( $weekday_zh[ $p['weekday'] ] ); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ( $p['studios'] ) : ?>
                    <div class="bgm-card-studio" title="<?php echo esc_attr( $p['studios'] ); ?>">
                        🎬 <?php echo esc_html( $p['studios'] ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( $tw_icons ) : ?>
                    <div class="bgm-card-plats">
                        <?php
                        $shown = array_slice( $tw_icons, 0, 4 );
                        $extra = count( $tw_icons ) - count( $shown );
                        foreach ( $shown as $plat ) : ?>
                            <span class="bgm-plat-mini bgm-plat-<?php echo esc_attr( $plat['key'] ); ?>"
                                  title="<?php echo esc_attr( $plat['label'] ); ?>">
                                <?php echo esc_html( mb_substr( $plat['label'], 0, 2 ) ); ?>
                            </span>
                        <?php endforeach;
                        if ( $extra > 0 ) : ?>
                            <span class="bgm-plat-mini is-more">+<?php echo (int) $extra; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </button>

        <div class="bgm-detail" id="bgm-detail-<?php echo (int) $p['id']; ?>" hidden>
            <?php echo bgm_render_detail( $p, $tw_platform_labels ); ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/* ============================================================
 * Helper：渲染長條詳情（acgsecrets 風）
 * ============================================================ */
function bgm_render_detail( $p, $tw_platform_labels ) {
    ob_start();
    ?>
    <div class="bgm-d-inner">

        <header class="bgm-d-header">
            <h3 class="bgm-d-title"><?php echo esc_html( $p['title_cn'] ); ?></h3>
            <?php if ( $p['title_jp'] ) : ?>
                <div class="bgm-d-jp"><?php echo esc_html( $p['title_jp'] ); ?></div>
            <?php endif; ?>
            <?php if ( $p['title_en'] ) : ?>
                <div class="bgm-d-en"><?php echo esc_html( $p['title_en'] ); ?></div>
            <?php endif; ?>
        </header>

        <div class="bgm-d-tags">
            <?php if ( $p['source_zh'] ) : ?>
                <span class="bgm-tag bgm-tag-source"><?php echo esc_html( $p['source_zh'] ); ?></span>
            <?php endif; ?>
            <?php if ( $p['format_zh'] ) : ?>
                <span class="bgm-tag"><?php echo esc_html( $p['format_zh'] ); ?></span>
            <?php endif; ?>
            <?php if ( $p['ep_total'] > 0 ) : ?>
                <span class="bgm-tag">共 <?php echo (int) $p['ep_total']; ?> 集</span>
            <?php endif; ?>
            <?php if ( $p['score_disp'] !== null ) : ?>
                <span class="bgm-tag bgm-tag-score">★ <?php echo esc_html( $p['score_disp'] ); ?></span>
            <?php endif; ?>
            <?php if ( $p['popularity'] > 0 ) : ?>
                <span class="bgm-tag">人氣 <?php echo number_format( $p['popularity'] ); ?></span>
            <?php endif; ?>
        </div>

        <?php if ( $p['next_airing'] || $p['start_date'] || $p['tw_broadcast'] ) : ?>
        <div class="bgm-d-row">
            <div class="bgm-d-label">播出時間</div>
            <div class="bgm-d-value">
                <?php
                $airing_lines = [];
                if ( $p['start_date'] )  $airing_lines[] = '開播：' . esc_html( $p['start_date'] );
                if ( $p['next_airing'] ) $airing_lines[] = '下集：' . esc_html( $p['next_airing'] ) . '（台灣時間）';
                if ( $p['tw_broadcast'] ) $airing_lines[] = '台灣播出：' . esc_html( $p['tw_broadcast'] );
                echo implode( '<br>', $airing_lines );
                ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $p['synopsis'] ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">故事大綱</h4>
            <div class="bgm-d-syn"><?php echo wp_kses_post( wpautop( $p['synopsis'] ) ); ?></div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $p['themes']['op'] ) || ! empty( $p['themes']['ed'] ) ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">主題曲</h4>
            <div class="bgm-d-themes">
                <?php foreach ( $p['themes']['op'] as $t ) : ?>
                    <div class="bgm-theme-row">
                        <span class="bgm-theme-tag bgm-theme-op">OP</span>
                        <span class="bgm-theme-title"><?php echo esc_html( $t['title'] ); ?></span>
                        <?php if ( $t['artist'] ) : ?><span class="bgm-theme-artist"><?php echo esc_html( $t['artist'] ); ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ( $p['themes']['ed'] as $t ) : ?>
                    <div class="bgm-theme-row">
                        <span class="bgm-theme-tag bgm-theme-ed">ED</span>
                        <span class="bgm-theme-title"><?php echo esc_html( $t['title'] ); ?></span>
                        <?php if ( $t['artist'] ) : ?><span class="bgm-theme-artist"><?php echo esc_html( $t['artist'] ); ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $p['trailers'] ) ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">宣傳片</h4>
            <div class="bgm-d-trailers">
                <?php foreach ( $p['trailers'] as $tr ) : ?>
                    <?php if ( $tr['vid'] ) : ?>
                        <button class="bgm-pv" type="button" data-vid="<?php echo esc_attr( $tr['vid'] ); ?>" aria-label="<?php echo esc_attr( $tr['title'] ); ?>">
                            <img src="<?php echo esc_url( $tr['thumb'] ); ?>" alt="<?php echo esc_attr( $tr['title'] ); ?>" loading="lazy">
                            <span class="bgm-pv-play">▶</span>
                            <span class="bgm-pv-title"><?php echo esc_html( $tr['title'] ); ?></span>
                        </button>
                    <?php else : ?>
                        <a class="bgm-pv" href="<?php echo esc_url( $tr['url'] ); ?>" target="_blank" rel="noopener">
                            <span class="bgm-pv-title"><?php echo esc_html( $tr['title'] ); ?> ↗</span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $p['tw_platforms'] ) || $p['tw_other'] ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">台灣播放平台</h4>
            <div class="bgm-d-platforms">
                <?php foreach ( (array) $p['tw_platforms'] as $key ) :
                    $label = $tw_platform_labels[ $key ] ?? $key;
                    $url   = $p['tw_urls'][ $key ] ?? ''; ?>
                    <?php if ( $url ) : ?>
                        <a class="bgm-plat bgm-plat-<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html( $label ); ?> ↗
                        </a>
                    <?php else : ?>
                        <span class="bgm-plat bgm-plat-<?php echo esc_attr( $key ); ?> is-nolink">
                            <?php echo esc_html( $label ); ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ( $p['tw_other'] ) : ?>
                    <span class="bgm-plat is-other"><?php echo esc_html( $p['tw_other'] ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $p['cast'] ) ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">配音員</h4>
            <div class="bgm-d-list">
                <?php foreach ( array_slice( $p['cast'], 0, 12 ) as $c ) : ?>
                    <div class="bgm-d-li">
                        <span class="bgm-d-role"><?php echo esc_html( $c['char'] ); ?>：</span>
                        <span class="bgm-d-name"><?php echo esc_html( $c['actor'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $p['staff'] ) || $p['studios'] ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">製作人員</h4>
            <div class="bgm-d-list">
                <?php foreach ( array_slice( $p['staff'], 0, 16 ) as $s ) : ?>
                    <div class="bgm-d-li">
                        <span class="bgm-d-role"><?php echo esc_html( $s['role'] ); ?>：</span>
                        <span class="bgm-d-name"><?php echo esc_html( $s['name'] ); ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ( $p['studios'] ) : ?>
                    <div class="bgm-d-li bgm-d-studios">
                        <span class="bgm-d-role">動畫製作：</span>
                        <span class="bgm-d-name"><?php echo esc_html( $p['studios'] ); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $p['streaming'] ) || $p['official'] ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">外部連結</h4>
            <div class="bgm-d-links">
                <?php if ( $p['official'] ) : ?>
                    <a class="bgm-link" href="<?php echo esc_url( $p['official'] ); ?>" target="_blank" rel="noopener">官方網站 ↗</a>
                <?php endif; ?>
                <?php foreach ( $p['streaming'] as $s ) : ?>
                    <a class="bgm-link" href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html( $s['site'] ); ?> ↗
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bgm-d-cta">
            <a class="bgm-d-more" href="<?php echo esc_url( $p['url'] ); ?>">查看完整資訊 →</a>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
