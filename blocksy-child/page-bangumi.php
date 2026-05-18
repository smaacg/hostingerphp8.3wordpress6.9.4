<?php
/**
 * Template Name: 番組表 - 季度詳細列表
 * File: blocksy-child/page-bangumi.php
 * Version: 1.3.0
 * Date: 2026-05-18
 *
 * Changelog
 *  v1.3.0 (2026-05-18)
 *    - Hybrid 模式：保留 grid，新增點擊就地展開長條詳情
 *    - SQL 一次撈完 14 個 postmeta + tw_streaming_url_* LIKE 聚合
 *    - 解析 staff_json / cast_json / themes / streaming / tw_streaming
 *    - trailer_url 多 PV 解析（換行/逗號/分號/空格、可選 | 標題）
 *    - 桌面手風琴、手機 modal（由 bangumi.js 切換）
 *    - URL hash 同步 #anime-{id}
 *    - YouTube PV lazy load（點縮圖才嵌入）
 *  v1.2.0 (2026-05-18)
 *    - 自訂 .bgm-card markup（取代 smacg_render_anime_card）
 *  v1.1.0 (2026-05-18)
 *    - inline CSS/JS 抽離至 assets/css/bangumi.css 與 assets/js/bangumi.js
 *  v1.0.0 - 初版
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
        'season_key' => $season_key,
        'season_zh'  => $season_zh,
        'label'      => sprintf( '%d 年 %s新番', $y, $season_zh ),
    ];
}

$prev_ym = function_exists( 'smacg_bangumi_shift_ym' ) ? smacg_bangumi_shift_ym( $ym, -3 ) : '';
$next_ym = function_exists( 'smacg_bangumi_shift_ym' ) ? smacg_bangumi_shift_ym( $ym, +3 )  : '';

/* 季節主題色 */
$season_themes = [
    'SPRING' => [ 'main' => '#f9a8d4', 'soft' => '#fce7f3', 'icon' => '🌸' ],
    'SUMMER' => [ 'main' => '#60a5fa', 'soft' => '#dbeafe', 'icon' => '🌊' ],
    'FALL'   => [ 'main' => '#fb923c', 'soft' => '#fed7aa', 'icon' => '🍁' ],
    'WINTER' => [ 'main' => '#94a3b8', 'soft' => '#e2e8f0', 'icon' => '❄️' ],
];
$theme = $season_themes[ $ctx['season_key'] ] ?? $season_themes['SPRING'];

/* ============================================================
 * 區段 2：SQL 一次撈完（主資料 + tw_streaming_url LIKE 聚合）
 * ============================================================ */
global $wpdb;

$current_uid = get_current_user_id();
$status_table = $wpdb->prefix . 'anime_user_status';
$has_status_table = (bool) $wpdb->get_var( $wpdb->prepare(
    "SHOW TABLES LIKE %s", $status_table
) );

$season_key = $ctx['season_key'];
$year       = (int) $ctx['year'];

/* 主查詢：一次 LEFT JOIN 14 個 postmeta */
$meta_keys = [
    'cover'        => 'anime_cover_image',
    'title_cn'     => 'anime_title_chinese',
    'title_jp'     => 'anime_title_native',
    'title_en'     => 'anime_title_english',
    'title_romaji' => 'anime_title_romaji',
    'synopsis'     => 'anime_synopsis_chinese',
    'studios'      => 'anime_studios',
    'staff'        => 'anime_staff_json',
    'cast'         => 'anime_cast_json',
    'episodes_json'=> 'anime_episodes_json',
    'themes'       => 'anime_themes',
    'streaming'    => 'anime_streaming',
    'tw_platforms' => 'anime_tw_streaming',
    'tw_other'     => 'anime_tw_streaming_other',
    'tw_broadcast' => 'anime_tw_broadcast',
    'trailer'      => 'anime_trailer_url',
    'score'        => 'anime_score_anilist',
    'popularity'   => 'anime_popularity',
    'ep_total'     => 'anime_episodes',
    'ep_aired'     => 'anime_episodes_aired',
    'next_airing'  => 'anime_next_airing',
    'start_date'   => 'anime_start_date',
    'source'       => 'anime_source',
    'format'       => 'anime_format',
    'status'       => 'anime_status',
    'official'     => 'anime_official_site',
];

$select_parts = [ 'p.ID', 'p.post_title', 'p.post_name', 'p.post_content', 'p.post_excerpt' ];
$join_parts   = [];
$i = 0;
foreach ( $meta_keys as $alias => $mk ) {
    $i++;
    $a = 'm' . $i;
    $select_parts[] = "MAX(CASE WHEN {$a}.meta_key='{$mk}' THEN {$a}.meta_value END) AS `{$alias}`";
    $join_parts[]   = "LEFT JOIN {$wpdb->postmeta} {$a} ON {$a}.post_id=p.ID AND {$a}.meta_key='{$mk}'";
}

/* season 條件用 postmeta（anime_season + anime_season_year） */
$select_sql = implode( ', ', $select_parts );
$join_sql   = implode( "\n", $join_parts );

/* user_status JOIN（可選） */
$us_select = '';
$us_join   = '';
if ( $has_status_table && $current_uid > 0 ) {
    $us_select = ", us.status AS user_status, us.progress AS user_progress, us.score AS user_score";
    $us_join   = $wpdb->prepare(
        "LEFT JOIN {$status_table} us ON us.anime_id=p.ID AND us.user_id=%d",
        $current_uid
    );
}

$sql = "
    SELECT {$select_sql}
        , ms.meta_value AS season_key
        , msy.meta_value AS season_year
        {$us_select}
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} ms  ON ms.post_id=p.ID  AND ms.meta_key='anime_season'
    INNER JOIN {$wpdb->postmeta} msy ON msy.post_id=p.ID AND msy.meta_key='anime_season_year'
    {$join_sql}
    {$us_join}
    WHERE p.post_type='anime'
      AND p.post_status='publish'
      AND ms.meta_value=%s
      AND msy.meta_value=%d
    GROUP BY p.ID
    ORDER BY CAST(MAX(CASE WHEN m18.meta_key='anime_popularity' THEN m18.meta_value END) AS UNSIGNED) DESC, p.ID DESC
";
$rows = $wpdb->get_results( $wpdb->prepare( $sql, $season_key, $year ), ARRAY_A );
if ( ! $rows ) { $rows = []; }

/* 補抓 tw_streaming_url_* （所有勾選平台的直達 URL，一次 LIKE 查詢） */
$tw_urls_by_post = [];
if ( $rows ) {
    $ids = array_map( 'intval', wp_list_pluck( $rows, 'ID' ) );
    $in  = implode( ',', $ids );
    $tw_url_rows = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE post_id IN ({$in})
           AND meta_key LIKE 'anime_tw_streaming_url_%'
           AND meta_value != ''",
        ARRAY_A
    );
    foreach ( $tw_url_rows as $r ) {
        $platform = substr( $r['meta_key'], strlen( 'anime_tw_streaming_url_' ) );
        $tw_urls_by_post[ (int) $r['post_id'] ][ $platform ] = $r['meta_value'];
    }
}

/* ============================================================
 * 區段 3：解析 JSON / 陣列欄位
 * ============================================================ */

/* 台灣平台中文名對照（與 ACF get_tw_platforms 同步） */
$tw_platform_labels = [
    'bahamut'      => '巴哈動畫瘋',
    'hami'         => 'Hami Video',
    'myvideo'      => 'MyVideo',
    'linetv'       => 'LINE TV',
    'friday'       => 'friDay影音',
    'ofiii'        => 'Ofiii',
    'catchplay'    => 'CatchPlay+',
    'bilibili'     => 'B站台灣',
    'ani_one'      => 'Ani-One',
    'muse'         => 'Muse 木棉花',
    'mighty'       => '曼迪 YT',
    'ani_mi'       => 'Ani-Mi',
    'netflix'      => 'Netflix',
    'disney'       => 'Disney+',
    'litv'         => 'LiTV',
    'tropicsanime' => '回歸線娛樂',
    'iqiyi'        => '愛奇藝',
    'renta'        => 'renta!',
    'anipass'      => 'AniPASS',
    'amazon'       => 'Prime Video',
    'crunchyroll'  => 'Crunchyroll',
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

/* 解析 trailer_url（多支） */
$parse_trailers = function( $raw ) {
    if ( ! $raw ) return [];
    $lines = preg_split( '/[\r\n,;]+/u', $raw );
    $out = [];
    $idx = 0;
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

/* 解析 staff_json (Bangumi 格式: [{name, name_cn, relation}, ...]) */
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

/* 解析 cast_json (Bangumi 格式: [{character:{name}, actors:[{name}]}, ...]) */
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

/* 解析 themes (AnimeThemes 格式) → 簡化為 OP/ED 兩組 */
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

/* 解析 streaming (AniList externalLinks) */
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

/* 解析 tw_streaming (ACF checkbox 通常是 serialized array) */
$parse_tw_platforms = function( $raw ) {
    if ( ! $raw ) return [];
    if ( is_array( $raw ) ) return $raw;
    if ( is_serialized( $raw ) ) {
        $arr = @unserialize( $raw );
        return is_array( $arr ) ? $arr : [];
    }
    /* fallback: JSON 或逗號分隔 */
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

foreach ( $rows as $r ) {
    $pid = (int) $r['ID'];

    /* 星期判定（優先 next_airing，否則 start_date） */
    $weekday = 0;
    $ts_src = $r['next_airing'] ?: $r['start_date'];
    if ( $ts_src ) {
        $ts = strtotime( $ts_src );
        if ( $ts ) {
            $w = (int) date( 'N', $ts );
            $weekday = $w;
        }
    }

    $title_cn = $r['title_cn'] ?: ( $r['title_en'] ?: ( $r['title_romaji'] ?: $r['post_title'] ) );
    $score    = $r['score'] !== null ? (float) $r['score'] : null;
    $score_disp = $score !== null ? round( $score / 10, 1 ) : null;
    $ep_total = (int) ( $r['ep_total'] ?: 0 );
    $ep_aired = (int) ( $r['ep_aired'] ?: 0 );

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
        'popularity' => (int) ( $r['popularity'] ?: 0 ),
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
        'user_status'  => $r['user_status']   ?? '',
        'user_progress'=> (int) ( $r['user_progress'] ?? 0 ),
    ];

    $posts[] = $item;
    $by_weekday[ $weekday ][] = $item;

    $stat_total++;
    if ( $item['user_status'] !== '' ) $stat_owned++;
    if ( $item['user_status'] === 'watching' ) $stat_watching++;
    if ( $item['user_status'] === 'completed' ) $stat_completed++;
    if ( $score !== null ) { $score_sum += $score; $score_cnt++; }
    if ( ! $first_cover && $item['cover'] ) $first_cover = $item['cover'];
}
$avg_score = $score_cnt > 0 ? round( $score_sum / $score_cnt / 10, 2 ) : null;

/* ============================================================
 * 區段 5：SEO（title / meta / OG / Schema）
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

    <!-- 麵包屑 -->
    <nav class="bgm-breadcrumb" aria-label="breadcrumb">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">首頁</a>
        <span>›</span>
        <a href="<?php echo esc_url( home_url( '/bangumi/' ) ); ?>">新番表</a>
        <span>›</span>
        <span aria-current="page"><?php echo esc_html( $ctx['label'] ); ?></span>
    </nav>

    <!-- Hero -->
    <section class="bgm-hero">
        <div class="bgm-hero-inner">
            <div class="bgm-hero-badge"><?php echo esc_html( $theme['icon'] ); ?> <?php echo esc_html( $ctx['season_zh'] ); ?></div>
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
                <div class="bgm-stat"><span class="bgm-stat-n"><?php echo (int) $stat_total; ?></span><span class="bgm-stat-l">總作品</span></div>
                <div class="bgm-stat"><span class="bgm-stat-n"><?php echo (int) $stat_owned; ?></span><span class="bgm-stat-l">我的收藏</span></div>
                <div class="bgm-stat"><span class="bgm-stat-n"><?php echo (int) $stat_watching; ?></span><span class="bgm-stat-l">追番中</span></div>
                <div class="bgm-stat"><span class="bgm-stat-n"><?php echo $avg_score !== null ? esc_html( $avg_score ) : '–'; ?></span><span class="bgm-stat-l">平均分</span></div>
            </div>
        </div>
    </section>

    <!-- 工具列：星期 + 排序 -->
    <section class="bgm-toolbar">
        <div class="bgm-days" role="tablist" aria-label="按星期篩選">
            <button class="bgm-day is-active" data-day="all" role="tab" aria-selected="true">全部</button>
            <?php for ( $d = 1; $d <= 7; $d++ ) : if ( empty( $by_weekday[ $d ] ) ) continue; ?>
                <button class="bgm-day" data-day="<?php echo (int) $d; ?>" role="tab" aria-selected="false">
                    <?php echo esc_html( $weekday_zh[ $d ] ); ?>
                    <span class="bgm-day-n">(<?php echo count( $by_weekday[ $d ] ); ?>)</span>
                </button>
            <?php endfor; ?>
            <?php if ( ! empty( $by_weekday[0] ) ) : ?>
                <button class="bgm-day" data-day="0" role="tab" aria-selected="false">
                    其他<span class="bgm-day-n">(<?php echo count( $by_weekday[0] ); ?>)</span>
                </button>
            <?php endif; ?>
        </div>

        <div class="bgm-sort-wrap">
            <label for="bgm-sort" class="bgm-sort-label">排序</label>
            <select id="bgm-sort" class="bgm-sort">
                <option value="default">預設</option>
                <option value="score">評分高 → 低</option>
                <option value="popularity">人氣高 → 低</option>
                <option value="ep">集數多 → 少</option>
            </select>
        </div>
    </section>

    <!-- 卡片網格（Hybrid：點卡片就地展開） -->
    <section class="bgm-grid-wrap">
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
            <h2 class="bgm-group-title">其他　<span class="bgm-group-n"><?php echo count( $by_weekday[0] ); ?> 部</span></h2>
            <div class="bgm-grid">
                <?php foreach ( $by_weekday[0] as $p ) : ?>
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

    <!-- 手機 modal 容器（JS 會把展開內容塞進來） -->
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
 * Helper：渲染單張卡片（含內嵌 <template> 展開內容）
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

                <div class="bgm-card-overlay">
                    <?php if ( $p['weekday'] > 0 ) : ?>
                        <span class="bgm-card-day"><?php echo esc_html( $weekday_zh[ $p['weekday'] ] ); ?></span>
                    <?php endif; ?>
                    <?php if ( $p['ep_total'] > 0 ) : ?>
                        <span class="bgm-card-ep">共 <?php echo (int) $p['ep_total']; ?> 集</span>
                    <?php elseif ( $p['format_zh'] ) : ?>
                        <span class="bgm-card-ep"><?php echo esc_html( $p['format_zh'] ); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ( $progress_pct > 0 ) : ?>
                    <div class="bgm-card-progress"><span style="width:<?php echo (int) $progress_pct; ?>%"></span></div>
                <?php endif; ?>
            </div>

            <div class="bgm-card-meta">
                <div class="bgm-card-title"><?php echo esc_html( $p['title_cn'] ); ?></div>
                <?php if ( $p['title_jp'] ) : ?>
                    <div class="bgm-card-jp"><?php echo esc_html( $p['title_jp'] ); ?></div>
                <?php endif; ?>
            </div>
        </button>

        <!-- 展開詳情（桌面手風琴 / 手機 modal 共用 markup） -->
        <div class="bgm-detail" id="bgm-detail-<?php echo (int) $p['id']; ?>" hidden>
            <?php echo bgm_render_detail( $p, $tw_platform_labels ); ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/* ============================================================
 * Helper：渲染長條詳情（acgsecrets 風格）
 * ============================================================ */
function bgm_render_detail( $p, $tw_platform_labels ) {
    ob_start();
    ?>
    <div class="bgm-d-inner">

        <!-- 標題群 -->
        <header class="bgm-d-header">
            <h3 class="bgm-d-title"><?php echo esc_html( $p['title_cn'] ); ?></h3>
            <?php if ( $p['title_jp'] ) : ?>
                <div class="bgm-d-jp"><?php echo esc_html( $p['title_jp'] ); ?></div>
            <?php endif; ?>
            <?php if ( $p['title_en'] ) : ?>
                <div class="bgm-d-en"><?php echo esc_html( $p['title_en'] ); ?></div>
            <?php endif; ?>
        </header>

        <!-- 標籤群 -->
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

        <!-- 播出資訊 -->
        <?php if ( $p['next_airing'] || $p['start_date'] || $p['tw_broadcast'] ) : ?>
        <div class="bgm-d-row">
            <div class="bgm-d-label">播出時間</div>
            <div class="bgm-d-value">
                <?php
                $airing_lines = [];
                if ( $p['start_date'] ) {
                    $airing_lines[] = '開播：' . esc_html( $p['start_date'] );
                }
                if ( $p['next_airing'] ) {
                    $airing_lines[] = '下集：' . esc_html( $p['next_airing'] ) . '（台灣時間）';
                }
                if ( $p['tw_broadcast'] ) {
                    $airing_lines[] = '台灣播出：' . esc_html( $p['tw_broadcast'] );
                }
                echo implode( '<br>', $airing_lines );
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 故事大綱 -->
        <?php if ( $p['synopsis'] ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">故事大綱</h4>
            <div class="bgm-d-syn"><?php echo wp_kses_post( wpautop( $p['synopsis'] ) ); ?></div>
        </div>
        <?php endif; ?>

        <!-- 主題曲 -->
        <?php if ( ! empty( $p['themes']['op'] ) || ! empty( $p['themes']['ed'] ) ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">主題曲</h4>
            <div class="bgm-d-themes">
                <?php foreach ( $p['themes']['op'] as $t ) : ?>
                    <div class="bgm-theme-row">
                        <span class="bgm-theme-tag bgm-theme-op">OP</span>
                        <span class="bgm-theme-title"><?php echo esc_html( $t['title'] ); ?></span>
                        <?php if ( $t['artist'] ) : ?>
                            <span class="bgm-theme-artist"><?php echo esc_html( $t['artist'] ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ( $p['themes']['ed'] as $t ) : ?>
                    <div class="bgm-theme-row">
                        <span class="bgm-theme-tag bgm-theme-ed">ED</span>
                        <span class="bgm-theme-title"><?php echo esc_html( $t['title'] ); ?></span>
                        <?php if ( $t['artist'] ) : ?>
                            <span class="bgm-theme-artist"><?php echo esc_html( $t['artist'] ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 宣傳片（PV） -->
        <?php if ( ! empty( $p['trailers'] ) ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">宣傳片</h4>
            <div class="bgm-d-trailers">
                <?php foreach ( $p['trailers'] as $tr ) : ?>
                    <?php if ( $tr['vid'] ) : ?>
                        <button class="bgm-pv" type="button"
                                data-vid="<?php echo esc_attr( $tr['vid'] ); ?>"
                                aria-label="<?php echo esc_attr( $tr['title'] ); ?>">
                            <img src="<?php echo esc_url( $tr['thumb'] ); ?>"
                                 alt="<?php echo esc_attr( $tr['title'] ); ?>"
                                 loading="lazy">
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

        <!-- 台灣串流平台 -->
        <?php if ( ! empty( $p['tw_platforms'] ) || $p['tw_other'] ) : ?>
        <div class="bgm-d-section">
            <h4 class="bgm-d-h">台灣播放平台</h4>
            <div class="bgm-d-platforms">
                <?php foreach ( (array) $p['tw_platforms'] as $key ) :
                    $label = $tw_platform_labels[ $key ] ?? $key;
                    $url   = $p['tw_urls'][ $key ] ?? '';
                ?>
                    <?php if ( $url ) : ?>
                        <a class="bgm-plat bgm-plat-<?php echo esc_attr( $key ); ?>"
                           href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
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

        <!-- 配音員 -->
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

        <!-- 製作人員 -->
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

        <!-- 海外串流 + 官網 -->
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

        <!-- CTA -->
        <div class="bgm-d-cta">
            <a class="bgm-d-more" href="<?php echo esc_url( $p['url'] ); ?>">查看完整資訊 →</a>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
