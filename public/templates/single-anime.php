<?php
/**
 * Single Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
 * @version 14.0 — 移除重複 enqueue，加入使用者評分注入
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ✅ enqueue 已移至 functions.php，這裡不再重複呼叫
get_header();

while ( have_posts() ) :
    the_post();

    $post_id = get_the_ID();

    /* ── 輔助函式 ── */
    $get_meta = function ( $key, $default = '' ) use ( $post_id ) {
        $value = get_post_meta( $post_id, $key, true );
        return ( $value === '' || $value === null ) ? $default : $value;
    };

    $decode_json = function ( $raw ) {
        if ( is_array( $raw ) ) return $raw;
        if ( ! is_string( $raw ) || $raw === '' ) return [];
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    };

    $format_date = function ( $raw ) {
        if ( empty( $raw ) ) return '';
        $raw = trim( (string) $raw );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) return $raw;
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw, $m ) ) return "{$m[1]}-{$m[2]}-{$m[3]}";
        $ts = strtotime( $raw );
        return $ts !== false ? gmdate( 'Y-m-d', $ts ) : $raw;
    };

    $starts_with = function ( $haystack, $needle ) {
        return $needle !== '' && strpos( $haystack, $needle ) === 0;
    };

    $substr_safe = function ( $text, $start, $length = null ) {
        $text = (string) $text;
        if ( function_exists( 'mb_substr' ) ) {
            return $length === null ? mb_substr( $text, $start ) : mb_substr( $text, $start, $length );
        }
        return $length === null ? substr( $text, $start ) : substr( $text, $start, $length );
    };

    $fallback_text = function ( $text, $length = 2 ) use ( $substr_safe ) {
        $text = trim( wp_strip_all_tags( (string) $text ) );
        return $text === '' ? 'AN' : $substr_safe( $text, 0, $length );
    };

    $normalize_news_item = function ( $item ) {
        if ( ! is_array( $item ) ) return null;
        $title = $item['title'] ?? $item['name'] ?? $item['headline'] ?? '';
        $url   = $item['url']   ?? $item['link']  ?? '';
        $title = trim( (string) $title );
        $url   = trim( (string) $url );
        return $title !== '' ? [ 'title' => $title, 'url' => $url ] : null;
    };

    /* ── Meta ── */
    $anilist_id = (int) $get_meta( 'anime_anilist_id', 0 );
    $mal_id     = (int) $get_meta( 'anime_mal_id', 0 );
    $bangumi_id = (int) $get_meta( 'anime_bangumi_id', 0 );

    $title_chinese = $get_meta( 'anime_title_chinese' );
    $title_native  = $get_meta( 'anime_title_native' );
    $title_romaji  = $get_meta( 'anime_title_romaji' );
    $title_english = $get_meta( 'anime_title_english' );
    $display_title = $title_chinese ?: get_the_title();

    $format      = $get_meta( 'anime_format' );
    $status      = $get_meta( 'anime_status' );
    $season      = $get_meta( 'anime_season' );
    $season_year = (int) $get_meta( 'anime_season_year', 0 );
    $episodes    = (int) $get_meta( 'anime_episodes', 0 );
    $ep_aired    = (int) $get_meta( 'anime_episodes_aired', 0 );
    $duration    = (int) $get_meta( 'anime_duration', 0 );
    $source      = $get_meta( 'anime_source' );
    $studio      = $get_meta( 'anime_studios' );
    $popularity  = (int) $get_meta( 'anime_popularity', 0 );

    $tw_streaming_raw   = $get_meta( 'anime_tw_streaming' );
    $tw_streaming_other = $get_meta( 'anime_tw_streaming_other' );
    $tw_distributor     = $get_meta( 'anime_tw_distributor' );
    $tw_dist_custom     = $get_meta( 'anime_tw_distributor_custom' );
    $tw_broadcast       = $get_meta( 'anime_tw_broadcast' );

    $tw_stream_url_map = [
        'bahamut'      => $get_meta( 'anime_tw_streaming_url_bahamut' ),
        'hami'         => $get_meta( 'anime_tw_streaming_url_hami' ),
        'myvideo'      => $get_meta( 'anime_tw_streaming_url_myvideo' ),
        'linetv'       => $get_meta( 'anime_tw_streaming_url_linetv' ),
        'friday'       => $get_meta( 'anime_tw_streaming_url_friday' ),
        'ofiii'        => $get_meta( 'anime_tw_streaming_url_ofiii' ),
        'catchplay'    => $get_meta( 'anime_tw_streaming_url_catchplay' ),
        'bilibili'     => $get_meta( 'anime_tw_streaming_url_bilibili' ),
        'ani_one'      => $get_meta( 'anime_tw_streaming_url_ani_one' ),
        'muse'         => $get_meta( 'anime_tw_streaming_url_muse' ),
        'mighty'       => $get_meta( 'anime_tw_streaming_url_mighty' ),
        'ani_mi'       => $get_meta( 'anime_tw_streaming_url_ani_mi' ),
        'netflix'      => $get_meta( 'anime_tw_streaming_url_netflix' ),
        'disney'       => $get_meta( 'anime_tw_streaming_url_disney' ),
        'litv'         => $get_meta( 'anime_tw_streaming_url_litv' ),
        'tropicsanime' => $get_meta( 'anime_tw_streaming_url_tropicsanime' ),
        'iqiyi'        => $get_meta( 'anime_tw_streaming_url_iqiyi' ),
        'renta'        => $get_meta( 'anime_tw_streaming_url_renta' ),
        'anipass'      => $get_meta( 'anime_tw_streaming_url_anipass' ),
        'amazon'       => $get_meta( 'anime_tw_streaming_url_amazon' ),
        'crunchyroll'  => $get_meta( 'anime_tw_streaming_url_crunchyroll' ),
    ];

    $tw_dist_labels = [
        'muse'      => '木棉花', 'medialink' => '曼迪傳播', 'linbang'   => '羚邦',
        'tropic'    => '回歸線娛樂', 'proware' => '普威爾', 'kadokawa' => '台灣角川',
        'gungho'    => '群英社', 'tien'     => '提恩傳媒', 'garage'    => '車庫娛樂',
        'carsun'    => '采昌國際', 'jbf'    => '日本橋文化（JBF）', 'righttime' => '利得時代',
        'aniplus'   => 'ANIPLUS Asia', 'tongli' => '東立出版社', 'remow'  => 'REMOW',
        'gaga'      => 'GaGa OOLala', 'other' => '',
    ];

    $tw_dist_display = '';
    if ( $tw_distributor === 'other' ) {
        $tw_dist_display = $tw_dist_custom ?: '';
    } elseif ( $tw_distributor ) {
        $tw_dist_display = $tw_dist_labels[ $tw_distributor ] ?? $tw_distributor;
    }

    $provider_icon_base = trailingslashit( ANIME_SYNC_PRO_URL . 'public/assets/img/providers' );
    $provider_icon_map  = [
        'bahamut'      => 'anigamer_icon.webp',
        'hami'         => 'hami_icon.webp',
        'myvideo'      => 'myvideo_icon.webp',
        'linetv'       => 'linetv_icon.webp',
        'friday'       => 'friday_icon.webp',
        'ofiii'        => 'ofiii_icon.webp',
        'catchplay'    => 'catchplay_icon.webp',
        'bilibili'     => 'bilibili_icon.webp',
        'ani_one'      => 'ani-one.webp',
        'muse'         => 'Muse.webp',
        'mighty'       => 'Mighty.webp',
        'ani_mi'       => 'ani-mi.webp',
        'netflix'      => 'netflix_icon.webp',
        'disney'       => 'disneyplus_icon.webp',
        'litv'         => 'litv_icon.webp',
        'tropicsanime' => 'tropicsanime.webp',
        'iqiyi'        => 'iqiyi_icon.webp',
        'renta'        => 'renta.webp',
        'anipass'      => 'anipass_icon.webp',
        'amazon'       => 'amazon_prime_video_icon.webp',
        'crunchyroll'  => 'crunchyroll_icon.webp',
    ];

    $tw_stream_labels = [
        'bahamut'      => '巴哈姆特動畫瘋',
        'hami'         => '中華電信Hami Video',
        'myvideo'      => '台灣大哥大MyVideo',
        'linetv'       => 'LINE TV',
        'friday'       => 'friDay影音',
        'ofiii'        => 'Ofiii 歐飛',
        'catchplay'    => 'CatchPlay+',
        'bilibili'     => 'Bilibili台灣',
        'ani_one'      => 'Ani-One 羚邦集團 YouTube（官方頻道）',
        'muse'         => 'Muse 木棉花 YouTube（官方頻道）',
        'mighty'       => '曼迪 YouTube（官方頻道）',
        'ani_mi'       => 'Ani-Mi動漫迷動畫頻道（官方頻道）',
        'netflix'      => 'Netflix',
        'disney'       => 'Disney+',
        'litv'         => 'LiTV 立視線上影視',
        'tropicsanime' => '回歸線娛樂YouTube（官方頻道）',
        'iqiyi'        => '愛奇藝',
        'renta'        => 'renta!亂搭',
        'anipass'      => 'AniPASS 車庫娛樂旗下',
        'amazon'       => 'Amazon Prime Video',
        'crunchyroll'  => 'Crunchyroll',
    ];

    $tw_stream_legacy_aliases = [
        'ani-one'  => 'ani_one',
        'myVideo'  => 'myvideo',
        'my_video' => 'myvideo',
        'line_tv'  => 'linetv',
    ];

    $streaming_list = $decode_json( $get_meta( 'anime_streaming' ) );

    $tw_streaming_items = [];
    $tw_streaming_keys  = [];
    if ( ! empty( $tw_streaming_raw ) ) {
        $raw_arr = is_array( $tw_streaming_raw ) ? $tw_streaming_raw : [ $tw_streaming_raw ];
        foreach ( $raw_arr as $key ) {
            $key = trim( (string) $key );
            if ( isset( $tw_stream_legacy_aliases[ $key ] ) ) {
                $key = $tw_stream_legacy_aliases[ $key ];
            }
            if ( $key === '' || ! isset( $tw_stream_labels[ $key ] ) || isset( $tw_streaming_keys[ $key ] ) ) continue;

            $tw_streaming_keys[ $key ] = true;
            $tw_streaming_items[] = [
                'key'       => $key,
                'label'     => $tw_stream_labels[ $key ],
                'url'       => $tw_stream_url_map[ $key ] ?? '',
                'icon_url'  => isset( $provider_icon_map[ $key ] ) ? $provider_icon_base . $provider_icon_map[ $key ] : '',
                'icon_only' => false,
            ];
        }
    }
    if ( $tw_streaming_other ) {
        foreach ( array_map( 'trim', explode( ',', $tw_streaming_other ) ) as $extra ) {
            if ( $extra !== '' ) {
                $tw_streaming_items[] = [
                    'key'       => '',
                    'label'     => $extra,
                    'url'       => '',
                    'icon_url'  => '',
                    'icon_only' => false,
                ];
            }
        }
    }

    /* ── 攤平 streaming 資料：相容舊格式（一維）與新格式（taiwan/overseas）── */
    $streaming_flat = [];
    if ( isset( $streaming_list['taiwan'] ) || isset( $streaming_list['overseas'] ) ) {
        // 新格式
        $streaming_flat = array_merge(
            is_array( $streaming_list['taiwan'] ?? null )   ? $streaming_list['taiwan']   : [],
            is_array( $streaming_list['overseas'] ?? null ) ? $streaming_list['overseas'] : []
        );
    } else {
        // 舊格式（一維陣列）
        foreach ( $streaming_list as $sl ) {
            if ( is_array( $sl ) && isset( $sl['site'] ) ) {
                $streaming_flat[] = $sl;
            }
        }
    }

    /* ── 海外串流（從 AniList 自動抓的，台灣不可看的平台）── */
    $overseas_streams = [];
    if ( isset( $streaming_list['overseas'] ) && is_array( $streaming_list['overseas'] ) ) {
        $overseas_streams = $streaming_list['overseas'];
    } else {
        // 舊格式：用平台名稱黑名單把 Crunchyroll/Funimation/HIDIVE/VRV 歸到海外
        $os_blacklist = [ 'crunchyroll', 'funimation', 'hidive', 'vrv', 'hulu', 'wakanim' ];
        foreach ( $streaming_flat as $sl ) {
            $sl_site = strtolower( trim( $sl['site'] ?? '' ) );
            if ( in_array( $sl_site, $os_blacklist, true ) ) {
                $overseas_streams[] = $sl;
            }
        }
    }

    /* ⚠️ 不再自動把 Crunchyroll 補到台灣區（台灣根本看不到 Crunchyroll） */
    $auto_crunchyroll_item = null;


    $start_date = $format_date( $get_meta( 'anime_start_date' ) );
    $end_date   = $format_date( $get_meta( 'anime_end_date' ) );

    $score_anilist_raw = $get_meta( 'anime_score_anilist' );
    $score_anilist_num = is_numeric( $score_anilist_raw ) ? (float) $score_anilist_raw : 0;
    $score_anilist     = $score_anilist_num > 0 ? number_format( $score_anilist_num / 10, 1 ) : '';

    $score_mal_raw = $get_meta( 'anime_score_mal' );
    $score_mal_num = is_numeric( $score_mal_raw ) ? (float) $score_mal_raw : 0;
    $score_mal     = $score_mal_num > 0 ? number_format( $score_mal_num / 10, 1 ) : '';

    $score_bangumi_raw = $get_meta( 'anime_score_bangumi' );
    $score_bangumi_num = is_numeric( $score_bangumi_raw ) ? (float) $score_bangumi_raw : 0;
    $score_bangumi     = $score_bangumi_num > 0 ? number_format( $score_bangumi_num / 10, 1 ) : '';

    $cover_image  = $get_meta( 'anime_cover_image' );
    $banner_image = $get_meta( 'anime_banner_image' );
    $trailer_url  = $get_meta( 'anime_trailer_url' );

/* ── 解析多支 PV ── */
$trailer_items = [];   // [ ['id' => 'xxx', 'label' => 'PV 1'], ... ]
if ( $trailer_url ) {
    $idx = 0;
    foreach ( preg_split( '/[,，、;；\r\n]+/u', (string) $trailer_url ) as $t_url ) {
        $t_url = trim( $t_url );
        if ( $t_url === '' ) continue;

        // 支援 "URL|標題" 格式
        $custom_label = '';
        if ( strpos( $t_url, '|' ) !== false ) {
            list( $t_url, $custom_label ) = array_map( 'trim', explode( '|', $t_url, 2 ) );
        }

        $vid = '';
        if ( preg_match( '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/))([A-Za-z0-9_-]{11})/', $t_url, $m ) ) {
            $vid = $m[1];
        } elseif ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $t_url ) ) {
            $vid = $t_url;
        }
        if ( $vid === '' ) continue;

        // 去重
        foreach ( $trailer_items as $exist ) {
            if ( $exist['id'] === $vid ) { $vid = ''; break; }
        }
        if ( $vid === '' ) continue;

        $idx++;
        $trailer_items[] = [
            'id'    => $vid,
            'label' => $custom_label !== '' ? $custom_label : ( 'PV ' . $idx ),
        ];
    }
}
// 向後相容：保留 $youtube_id 給其他地方用（例如 hero 按鈕、tab 條件）
$youtube_id = ! empty( $trailer_items ) ? $trailer_items[0]['id'] : '';
$has_trailer = ! empty( $trailer_items );


    $official_site  = $get_meta( 'anime_official_site' );
    $twitter_url    = $get_meta( 'anime_twitter_url' );
    $wikipedia_url  = $get_meta( 'anime_wikipedia_url' );
    $tiktok_url     = $get_meta( 'anime_tiktok_url' );
    $affiliate_html = $get_meta( 'anime_affiliate_html' );

    $next_airing_raw = $get_meta( 'anime_next_airing' );
    $airing_data     = [];
    if ( $next_airing_raw ) {
        $decoded = is_array( $next_airing_raw ) ? $next_airing_raw : json_decode( $next_airing_raw, true );
        if ( is_array( $decoded ) ) $airing_data = $decoded;
    }

    $synopsis_raw = $get_meta( 'anime_synopsis_chinese' );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = $get_meta( 'anime_synopsis' );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_the_content();
    $synopsis = trim( (string) $synopsis_raw );

    $themes_list    = $decode_json( $get_meta( 'anime_themes' ) );
    $cast_list      = $decode_json( $get_meta( 'anime_cast_json' ) );
    $staff_list     = $decode_json( $get_meta( 'anime_staff_json' ) );
    $relations_list = $decode_json( $get_meta( 'anime_relations_json' ) );
    $episodes_list  = $decode_json( $get_meta( 'anime_episodes_json' ) );

    $news_items = $decode_json( $get_meta( 'anime_related_news_json' ) );
    if ( empty( $news_items ) ) $news_items = $decode_json( $get_meta( 'anime_news_json' ) );
    $normalized_news = [];
    foreach ( $news_items as $ni ) {
        $n = $normalize_news_item( $ni );
        if ( $n ) $normalized_news[] = $n;
    }
    $news_items = $normalized_news;

    /* ── Themes ── */
    $seen = []; $openings = []; $endings = [];
    foreach ( $themes_list as $t ) {
        $type  = strtoupper( trim( $t['type']  ?? '' ) );
        $slug  = trim( $t['slug']  ?? '' );
        $stitle= trim( $t['title'] ?? '' );
        $key   = $slug !== '' ? $slug : ( $type . '||' . $stitle );
        if ( isset( $seen[ $key ] ) ) continue;
        $seen[ $key ] = true;
        if ( $starts_with( $type, 'OP' ) )       $openings[] = $t;
        elseif ( $starts_with( $type, 'ED' ) )   $endings[]  = $t;
    }

    /* ── Labels ── */
    $season_labels  = [ 'WINTER' => '冬季', 'SPRING' => '春季', 'SUMMER' => '夏季', 'FALL' => '秋季' ];
    $format_labels  = [ 'TV' => 'TV', 'TV_SHORT' => 'TV', 'MOVIE' => '劇場版', 'OVA' => 'OVA', 'ONA' => 'ONA', 'SPECIAL' => '特別篇', 'MUSIC' => 'MV' ];
    $status_labels  = [ 'FINISHED' => '已完結', 'RELEASING' => '連載中', 'NOT_YET_RELEASED' => '尚未播出', 'CANCELLED' => '已取消', 'HIATUS' => '暫停中' ];
    $status_classes = [ 'FINISHED' => 's-fin', 'RELEASING' => 's-rel', 'NOT_YET_RELEASED' => 's-pre', 'CANCELLED' => 's-can', 'HIATUS' => 's-hia' ];
    $source_labels  = [
        'ORIGINAL' => '原創', 'MANGA' => '漫畫改編', 'LIGHT_NOVEL' => '輕小說改編',
        'NOVEL' => '小說改編', 'VISUAL_NOVEL' => '視覺小說改編', 'VIDEO_GAME' => '電玩改編',
        'WEB_MANGA' => '網路漫畫改編', 'BOOK' => '書籍改編', 'MUSIC' => '音樂改編',
        'GAME' => '遊戲改編', 'LIVE_ACTION' => '真人改編', 'MULTIMEDIA_PROJECT' => '跨媒體企劃', 'OTHER' => '其他',
    ];

    $season_label = $season_labels[ $season ] ?? $season;
    $format_label = $format_labels[ $format ] ?? $format;
    $status_label = $status_labels[ $status ] ?? $status;
    $status_class = $status_classes[ $status ] ?? '';
    $source_label = $source_labels[ $source ] ?? $source;

    $ep_str = '';
    if ( $episodes ) {
        $ep_str = ( $ep_aired && $ep_aired < $episodes )
            ? $ep_aired . ' / ' . $episodes . ' 集'
            : $episodes . ' 集';
    }

    $season_str = '';
    if ( $season_year && $season_label ) $season_str = $season_year . ' ' . $season_label;
    elseif ( $season_year )              $season_str = (string) $season_year;

    $genre_terms  = get_the_terms( $post_id, 'genre' );
    $season_terms = get_the_terms( $post_id, 'anime_season_tax' );
    $genre_terms  = is_array( $genre_terms )  ? $genre_terms  : [];
    $season_terms = is_array( $season_terms ) ? $season_terms : [];
    $season_child_terms = [];
    foreach ( $season_terms as $term ) {
        if ( ! empty( $term->parent ) ) $season_child_terms[] = $term;
    }

    /* ── Relations ── */
    $site_relations = [];
    foreach ( $relations_list as $rel ) {
        $rel_anilist_id = (int) ( $rel['anilist_id'] ?? $rel['id'] ?? 0 );
        if ( ! $rel_anilist_id ) continue;
        $qr = get_posts( [
            'post_type' => 'anime', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true,
            'meta_query' => [ [ 'key' => 'anime_anilist_id', 'value' => $rel_anilist_id, 'type' => 'NUMERIC' ] ],
        ] );
        if ( empty( $qr ) ) continue;
        $rp  = $qr[0];
        $relation_labels = [
            'PREQUEL' => '前作', 'SEQUEL' => '續作', 'PARENT' => '本篇', 'SIDE_STORY' => '外傳',
            'CHARACTER' => '角色', 'SUMMARY' => '總集篇', 'ALTERNATIVE' => '替代版本', 'SPIN_OFF' => '衍生作',
            'OTHER' => '相關', 'SOURCE' => '原作', 'COMPILATION' => '編輯版', 'CONTAINS' => '收錄', 'ANIME' => '動畫',
        ];
        $raw_label = $rel['relation_label'] ?? $rel['type'] ?? '';
        $site_relations[] = [
            'title_zh'       => get_post_meta( $rp->ID, 'anime_title_chinese', true ) ?: ( $rel['title_zh'] ?? $rel['title'] ?? '' ),
            'title_native'   => $rel['title_native'] ?? $rel['native'] ?? '',
            'relation_label' => $relation_labels[ $raw_label ] ?? $raw_label,
            'format'         => $rel['format'] ?? '',
            'cover_image'    => get_post_meta( $rp->ID, 'anime_cover_image', true ) ?: ( $rel['cover_image'] ?? '' ),
            'url'            => get_permalink( $rp->ID ),
        ];
    }

    /* ── Schema ── */
    $schema_type = 'TVSeries';
    if ( $format === 'MOVIE' ) $schema_type = 'Movie';
    elseif ( $format === 'MUSIC' ) $schema_type = 'MusicVideoObject';

    $schema_genres   = array_map( fn($t) => $t->name, $genre_terms );
    $alternate_names = array_values( array_filter( [ $title_native, $title_romaji, $title_english ] ) );
    $schema_description = $substr_safe( wp_strip_all_tags( $synopsis ), 0, 200 );

    $schema = [
        '@context' => 'https://schema.org', '@type' => $schema_type,
        'name' => $display_title, 'description' => $schema_description,
        'image' => $cover_image ?: get_the_post_thumbnail_url( $post_id, 'large' ),
        'genre' => $schema_genres, 'datePublished' => $start_date,
        'url'   => get_permalink( $post_id ),
    ];
    if ( ! empty( $alternate_names ) ) $schema['alternateName'] = $alternate_names;
    if ( $episodes ) $schema['numberOfEpisodes'] = $episodes;
    if ( $score_anilist_num > 0 ) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format( $score_anilist_num / 10, 1 ),
            'bestRating' => '10', 'worstRating' => '1',
            'ratingCount' => max( 1, $popularity ),
        ];
    }

    $breadcrumb_schema = [
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [ '@type' => 'ListItem', 'position' => 1, 'name' => '首頁',   'item' => home_url( '/' ) ],
            [ '@type' => 'ListItem', 'position' => 2, 'name' => '動畫',   'item' => home_url( '/anime/' ) ],
            [ '@type' => 'ListItem', 'position' => 3, 'name' => $display_title, 'item' => get_permalink( $post_id ) ],
        ],
    ];

    $faq_items = [];
    $faq_json_raw = $get_meta( 'anime_faq_json' );
    if ( $faq_json_raw ) {
        $faq_decoded = json_decode( $faq_json_raw, true );
        if ( is_array( $faq_decoded ) ) $faq_items = $faq_decoded;
    }
    $faq_schema = null;
    if ( ! empty( $faq_items ) ) {
        $faq_main = [];
        foreach ( $faq_items as $f ) {
            if ( empty( $f['q'] ) || empty( $f['a'] ) ) continue;
            $faq_main[] = [ '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $f['a'] ) ] ];
        }
        if ( ! empty( $faq_main ) ) {
            $faq_schema = [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq_main ];
        }
    }

    /* ── Cast ── */
    $cast_to_display = []; $cast_seen = [];
    foreach ( $cast_list as $c ) {
        $name = trim( $c['name'] ?? '' );
        $role = trim( $c['role'] ?? '' );
        $key  = md5( wp_json_encode( $c ) );
        if ( $name === '' || isset( $cast_seen[ $key ] ) ) continue;
        if ( $role === '主角' || strtoupper( $role ) === 'MAIN' ) { $cast_to_display[] = $c; $cast_seen[ $key ] = true; }
    }
    foreach ( $cast_list as $c ) {
        $name = trim( $c['name'] ?? '' );
        $key  = md5( wp_json_encode( $c ) );
        if ( $name === '' || isset( $cast_seen[ $key ] ) ) continue;
        $cast_to_display[] = $c; $cast_seen[ $key ] = true;
    }

    $poster_fallback = $fallback_text( $display_title, 2 );

    /* ── 追蹤資料（從新表 wp_anime_user_status 讀） ── */
    $uid              = get_current_user_id();
    $user_anime_entry = [ 'status' => null, 'progress' => 0, 'favorited' => false, 'fullcleared' => false ];
        /* ── 未播出動畫：禁用部分按鈕 ── */
    $is_not_aired = ( $status === 'NOT_YET_RELEASED' );
    if ( $uid && class_exists( 'Anime_Sync_User_Status_Manager' ) ) {
        $usm   = new Anime_Sync_User_Status_Manager();
        $entry = $usm->get_entry( (int) $uid, (int) $post_id );
        $user_anime_entry = [
            'status'      => $entry['status'],
            'progress'    => (int) $entry['progress'],
            'favorited'   => (bool) $entry['favorited'],
            'fullcleared' => (bool) $entry['fullcleared'],
        ];
    }

    /* ── ✅ 使用者既有評分（注入給 JS，預設 5.0，前端 JS 會動態覆寫）── */
    $user_rating = [ 'story' => 5.0, 'music' => 5.0, 'animation' => 5.0, 'voice' => 5.0 ];

    /* ── 站台平均評分 ── */
    $site_score = $site_story = $site_music = $site_animation = $site_voice = 0.0;
    $site_count = 0;


    if ( class_exists( 'Anime_Sync_Rating_Manager' ) ) {
        $rating_manager = new Anime_Sync_Rating_Manager();
        $site_stats     = $rating_manager->get_stats( $post_id );

        if ( is_array( $site_stats ) ) {
            $site_score     = (float) ( $site_stats['score']         ?? 0 );
            $site_story     = (float) ( $site_stats['avg_story']     ?? 0 );
            $site_music     = (float) ( $site_stats['avg_music']     ?? 0 );
            $site_animation = (float) ( $site_stats['avg_animation'] ?? 0 );
            $site_voice     = (float) ( $site_stats['avg_voice']     ?? 0 );
            $site_count     = (int)   ( $site_stats['vote_count']    ?? 0 );
        }
    } // ✅ 補上這個 }，原本缺失導致 endwhile 被吃掉

    /* ⚠️ 不從 PHP 讀取使用者評分（會破壞 LiteSpeed 快取）
       改由前端 JS 透過 admin-ajax 動態載入
       預設值維持 [story=>5, music=>5, animation=>5, voice=>5] */

    if ( $site_score <= 0 ) {
        $site_score = (float) get_post_meta( $post_id, 'anime_score_site', true );
    }
    if ( $site_count <= 0 ) {
        $site_count = (int) get_post_meta( $post_id, 'anime_score_site_count', true );
    }

    if ( $site_score <= 0 ) {
        $site_score     = (float) get_post_meta( $post_id, 'smacg_site_score',           true );
        $site_story     = (float) get_post_meta( $post_id, 'smacg_site_score_story',     true );
        $site_music     = (float) get_post_meta( $post_id, 'smacg_site_score_music',     true );
        $site_animation = (float) get_post_meta( $post_id, 'smacg_site_score_animation', true );
        $site_voice     = (float) get_post_meta( $post_id, 'smacg_site_score_voice',     true );
        if ( $site_count <= 0 ) {
            $site_count = (int) get_post_meta( $post_id, 'smacg_site_score_count', true );
        }
    }

?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php if ( $faq_schema ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( $faq_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php endif; ?>

<?php /* ✅ 預設使用者評分（HTML 對所有人一致，可被 LiteSpeed 快取） */ ?>
<script>
window.SmacgUserRating = <?php echo wp_json_encode( $user_rating ); ?>;
<?php if ( is_user_logged_in() ) : ?>
/* ── 動態載入當前使用者評分（繞過全頁快取） ── */
(function(){
    var url = '<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>'
            + '?action=smacg_get_my_rating&post_id=<?php echo (int) $post_id; ?>';
    fetch(url, { credentials: 'same-origin' })
    .then(function(r){ return r.ok ? r.json() : null; })
    .then(function(res){
        if (!res || !res.success || !res.data || !res.data.rated) return;
        var d = res.data;
        window.SmacgUserRating = {
            story:     parseFloat(d.story)     || 5,
            music:     parseFloat(d.music)     || 5,
            animation: parseFloat(d.animation) || 5,
            voice:     parseFloat(d.voice)     || 5
        };
        document.dispatchEvent(new CustomEvent('smacg:userRatingReady', {
            detail: window.SmacgUserRating
        }));
    })
    .catch(function(){});
})();
<?php endif; ?>
</script>

<div class="asd-wrap">

    <?php /* ── Banner ── */ ?>
    <?php if ( $banner_image ) : ?>
        <div class="asd-banner" style="background-image:url(<?php echo esc_url( $banner_image ); ?>)">
            <div class="asd-banner-fade"></div>
        </div>
    <?php else : ?>
        <div class="asd-banner asd-banner--fallback"></div>
    <?php endif; ?>

    <?php /* ── Hero ── */ ?>
    <div class="asd-hero-new">

        <div class="asd-hero-poster-wrap">
            <div class="asd-hero-poster">
                <?php if ( $cover_image ) : ?>
                    <img src="<?php echo esc_url( $cover_image ); ?>"
                         alt="<?php echo esc_attr( $display_title ); ?> 封面"
                         class="asd-poster-img" loading="eager"
                         onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="asd-poster-fallback" style="display:none"><span><?php echo esc_html( $poster_fallback ); ?></span></div>
                <?php elseif ( has_post_thumbnail() ) : ?>
                    <?php echo get_the_post_thumbnail( $post_id, 'large', [ 'class' => 'asd-poster-img', 'loading' => 'eager', 'alt' => $display_title . ' 封面' ] ); ?>
                <?php else : ?>
                    <div class="asd-poster-fallback"><span><?php echo esc_html( $poster_fallback ); ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="asd-hero-body">

            <div class="asd-hero-breadcrumb">
                <span>動畫</span>
                <?php if ( $season_str ) : ?><span class="asd-hbc-sep">›</span><span><?php echo esc_html( $season_str ); ?></span><?php endif; ?>
                <?php if ( ! empty( $genre_terms ) ) : ?><span class="asd-hbc-sep">›</span><span><?php echo esc_html( $genre_terms[0]->name ); ?></span><?php endif; ?>
            </div>

            <h1 class="asd-hero-title"><?php echo esc_html( $display_title ); ?></h1>

            <?php if ( $title_native ) : ?>
                <p class="asd-hero-native"><?php echo esc_html( $title_native ); ?></p>
            <?php endif; ?>
            <?php if ( $title_romaji && $title_romaji !== $title_native ) : ?>
                <p class="asd-hero-native asd-hero-romaji"><?php echo esc_html( $title_romaji ); ?></p>
            <?php endif; ?>

            <?php
            $series_tax_terms = get_the_terms( $post_id, 'anime_series_tax' );
            if ( ! empty( $series_tax_terms ) && ! is_wp_error( $series_tax_terms ) ) :
                $series_tax     = $series_tax_terms[0];
                $series_tax_url = get_term_link( $series_tax );
                if ( $series_tax->count >= 2 && ! is_wp_error( $series_tax_url ) ) :
            ?>
                <a href="<?php echo esc_url( $series_tax_url ); ?>" class="asd-series-entry-badge">
                    📺 <?php echo esc_html( $series_tax->name ); ?> 系列
                </a>
            <?php endif; endif; ?>

            <div class="asd-hero-badges">
                <?php
                if ( $status_label ) echo '<span class="asd-hbadge' . ( $status_class ? ' asd-hbadge--' . esc_attr( $status_class ) : '' ) . '">' . esc_html( $status_label ) . '</span>';
                if ( $format_label ) echo '<span class="asd-hbadge">' . esc_html( $format_label ) . '</span>';
                if ( $season_str )   echo '<span class="asd-hbadge">' . esc_html( $season_str ) . '</span>';
                if ( $ep_str )       echo '<span class="asd-hbadge">' . esc_html( $ep_str ) . '</span>';
                foreach ( array_slice( $genre_terms, 0, 3 ) as $gt ) {
                    echo '<span class="asd-hbadge asd-hbadge--genre">' . esc_html( $gt->name ) . '</span>';
                }
                ?>
            </div>

            <div class="asd-hero-scores-new">
                <?php if ( $score_anilist ) : ?>
                    <div class="asd-score-pill asd-score-pill--al">
                        <span class="asd-sp-dot"></span>
                        <span class="asd-sp-val"><?php echo esc_html( $score_anilist ); ?></span>
                        <span class="asd-sp-label">AniList</span>
                    </div>
                <?php endif; ?>
                <?php if ( $score_mal ) : ?>
                    <div class="asd-score-pill asd-score-pill--mal">
                        <span class="asd-sp-dot"></span>
                        <span class="asd-sp-val"><?php echo esc_html( $score_mal ); ?></span>
                        <span class="asd-sp-label">MAL</span>
                    </div>
                <?php endif; ?>
                <?php if ( $score_bangumi ) : ?>
                    <div class="asd-score-pill asd-score-pill--bgm">
                        <span class="asd-sp-dot"></span>
                        <span class="asd-sp-val"><?php echo esc_html( $score_bangumi ); ?></span>
                        <span class="asd-sp-label">Bangumi</span>
                    </div>
                <?php endif; ?>
                <div class="asd-score-pill asd-score-pill--site">
                    <span class="asd-sp-dot"></span>
                    <span class="asd-sp-val wacg-hero-score"><?php echo $site_score > 0 ? number_format( $site_score, 1 ) : '暫無'; ?></span>
                    <span class="asd-sp-label">WeixiaoACG+</span>
                </div>
            </div>

<div class="asd-hero-actions">
    <?php if ( $youtube_id ) : ?>
        <a href="#asd-sec-trailer" class="asd-action-btn asd-action-btn--primary">▶ 觀看預告</a>
    <?php endif; ?>
    <?php if ( ! empty( $tw_streaming_items ) || $auto_crunchyroll_item ) : ?>
        <a href="#asd-sec-stream" class="asd-action-btn asd-action-btn--ghost" title="<?php echo esc_attr( $display_title ); ?> 線上觀看">📺 線上觀看</a>
    <?php endif; ?>
    <a href="<?php echo esc_url( home_url('/contact/') . '?type=bug&ref=' . urlencode( get_permalink() ) ); ?>" target="_blank" rel="noopener noreferrer" class="asd-action-btn asd-action-btn--ghost">✏ 糾錯回報</a>
    <?php if ( $official_site ) : ?>
        <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener noreferrer" class="asd-action-btn asd-action-btn--ghost">🌐 官方網站</a>
    <?php endif; ?>

    <?php
    // 🗺 動畫巡禮地圖（資料來源：anitabi.cn，CC BY-NC-SA 4.0）
    $bangumi_id = (int) get_post_meta( get_the_ID(), 'anime_bangumi_id', true );
    if ( ! $bangumi_id ) {
        $bangumi_id = (int) get_post_meta( get_the_ID(), 'bangumi_id', true );
    }
    if ( $bangumi_id > 0 ) :
    ?>
        <a href="<?php echo esc_url( 'https://anitabi.cn/map?bangumiId=' . $bangumi_id ); ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="asd-action-btn asd-action-btn--ghost"
           title="<?php echo esc_attr( $display_title ); ?> 動畫巡禮地圖（資料來源：anitabi.cn）">
            🗺 巡禮地圖
        </a>
    <?php endif; ?>
</div>


        </div><!-- /.asd-hero-body -->

        <?php /* ── 右側評分區塊 ── */ ?>
        <div class="asd-hside-block" id="wacg-rating-block">

            <div class="asd-hside-title">評分</div>

            <?php if ( $score_anilist ) : ?>
                <div class="asd-hside-row">
                    <span class="asd-hside-dot" style="background:var(--asd-score-al)"></span>
                    <span class="asd-hside-key">AniList</span>
                    <span class="asd-hside-val"><?php echo esc_html( $score_anilist ); ?></span>
                </div>
            <?php endif; ?>
            <?php if ( $score_mal ) : ?>
                <div class="asd-hside-row">
                    <span class="asd-hside-dot" style="background:var(--asd-score-mal)"></span>
                    <span class="asd-hside-key">MAL</span>
                    <span class="asd-hside-val"><?php echo esc_html( $score_mal ); ?></span>
                </div>
            <?php endif; ?>
            <?php if ( $score_bangumi ) : ?>
                <div class="asd-hside-row">
                    <span class="asd-hside-dot" style="background:var(--asd-score-bgm)"></span>
                    <span class="asd-hside-key">Bangumi</span>
                    <span class="asd-hside-val"><?php echo esc_html( $score_bangumi ); ?></span>
                </div>
            <?php endif; ?>

            <div class="wacg-rating-divider"></div>

            <?php /* ── 站台平均 ── */ ?>
            <div id="wacg-rating-stats" class="wacg-rating-stats">
                <div class="wacg-score-row">
                    <span class="asd-hside-dot wacg-dot-site"></span>
                    <span class="asd-hside-key">WeixiaoACG+</span>
                    <span class="asd-hside-val wacg-score-main"><?php echo $site_score > 0 ? number_format( $site_score, 1 ) : '—'; ?></span>
                </div>
                <div class="wacg-vote-count"><?php echo $site_count > 0 ? $site_count . ' 人評分' : ''; ?></div>
                <div class="wacg-cats">
                    <div class="wacg-cat-row"><span class="wacg-cat-label">劇情</span><span class="wacg-cat-val wacg-cat-story"><?php echo $site_story > 0 ? number_format( $site_story, 1 ) : '—'; ?></span></div>
                    <div class="wacg-cat-row"><span class="wacg-cat-label">音樂</span><span class="wacg-cat-val wacg-cat-music"><?php echo $site_music > 0 ? number_format( $site_music, 1 ) : '—'; ?></span></div>
                    <div class="wacg-cat-row"><span class="wacg-cat-label">作畫</span><span class="wacg-cat-val wacg-cat-animation"><?php echo $site_animation > 0 ? number_format( $site_animation, 1 ) : '—'; ?></span></div>
                    <div class="wacg-cat-row"><span class="wacg-cat-label">聲優</span><span class="wacg-cat-val wacg-cat-voice"><?php echo $site_voice > 0 ? number_format( $site_voice, 1 ) : '—'; ?></span></div>
                </div>
            </div>

            <?php /* ── 使用者評分表單 ── */ ?>
            <?php if ( is_user_logged_in() ) : ?>
                <form id="wacg-rating-form" class="wacg-rating-form">
                    <?php
                    $sliders = [
                        [ 'key' => 'story',     'label' => '劇情' ],
                        [ 'key' => 'music',     'label' => '音樂' ],
                        [ 'key' => 'animation', 'label' => '作畫' ],
                        [ 'key' => 'voice',     'label' => '聲優' ],
                    ];
                    foreach ( $sliders as $s ) :
                        $init_val = $user_rating[ $s['key'] ];
                    ?>
                        <div class="wacg-slider-row">
                            <label class="wacg-slider-label" for="slider-<?php echo esc_attr( $s['key'] ); ?>"><?php echo esc_html( $s['label'] ); ?></label>
                            <input type="range"
                                   id="slider-<?php echo esc_attr( $s['key'] ); ?>"
                                   class="wacg-slider"
                                   min="1" max="10" step="0.1"
                                   value="<?php echo esc_attr( $init_val ); ?>">
                            <span id="slider-<?php echo esc_attr( $s['key'] ); ?>-val" class="wacg-slider-val"><?php echo number_format( $init_val, 1 ); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" id="wacg-submit-btn" class="wacg-submit-btn">送出評分</button>
                </form>
            <?php else : ?>
                <button type="button" class="wacg-login-prompt" onclick="window.smacgOpenLoginModal && smacgOpenLoginModal()">
                    登入後即可評分
                </button>
            <?php endif; ?>

            <div class="wacg-rating-divider"></div>

            <?php /* ── 快速資訊 ── */ ?>
            <?php
            $meta_rows = [
                '集數' => $ep_str,
                '時長' => $duration ? $duration . ' 分鐘' : '',
                '原作' => $source_label,
                '季度' => $season_str,
                '製作' => $studio,
            ];
            $has_any_meta = false;
            foreach ( $meta_rows as $mk => $mv ) :
                if ( ! strlen( (string) $mv ) ) continue;
                $has_any_meta = true;
            ?>
                <div class="asd-hside-info-row">
                    <span class="asd-hside-info-key"><?php echo esc_html( $mk ); ?></span>
                    <span class="asd-hside-info-val"><?php echo esc_html( $mv ); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ( ! $has_any_meta ) : ?>
                <p style="font-size:12px;color:var(--asd-text-muted);text-align:center;padding:8px 0;margin:0;">暫無資料</p>
            <?php endif; ?>

        </div><!-- /.asd-hside-block -->

    </div><!-- /.asd-hero-new -->

     <?php /* ── 追蹤列 ── */ ?>
    <div class="smacg-track-bar"
         data-post-id="<?php echo esc_attr( $post_id ); ?>"
         data-episodes="<?php echo esc_attr( $episodes ); ?>"
         data-status="<?php echo esc_attr( $user_anime_entry['status'] ?? '' ); ?>"
         data-progress="<?php echo esc_attr( $user_anime_entry['progress'] ?? 0 ); ?>"
         data-favorited="<?php echo ( $user_anime_entry['favorited'] ?? false ) ? '1' : '0'; ?>"
         data-fullcleared="<?php echo ( $user_anime_entry['fullcleared'] ?? false ) ? '1' : '0'; ?>">

        <div class="smacg-track-main">

<div class="smacg-status-group">
    <button class="smacg-status-btn <?php echo ( $user_anime_entry['status'] ?? '' ) === 'want' ? 'is-active' : ''; ?>" data-action="status" data-value="want" title="想看"><span class="smacg-ico">🔖</span><span>想看</span></button>

    <button class="smacg-status-btn <?php echo ( $user_anime_entry['status'] ?? '' ) === 'watching' ? 'is-active' : ''; ?>"
            data-action="status" data-value="watching"
            <?php echo $is_not_aired ? 'disabled aria-disabled="true" title="尚未播出，無法追番"' : 'title="追番中"'; ?>>
        <span class="smacg-ico">▶</span><span>追番中</span>
    </button>

    <button class="smacg-status-btn <?php echo ( $user_anime_entry['status'] ?? '' ) === 'completed' ? 'is-active' : ''; ?>"
            data-action="status" data-value="completed"
            <?php echo $is_not_aired ? 'disabled aria-disabled="true" title="尚未播出，無法標記已看完"' : 'title="已看完"'; ?>>
        <span class="smacg-ico">✓</span><span>已看完</span>
    </button>

    <button class="smacg-status-btn <?php echo ( $user_anime_entry['status'] ?? '' ) === 'dropped' ? 'is-active' : ''; ?>" data-action="status" data-value="dropped" title="棄坑"><span class="smacg-ico">✕</span><span>棄坑</span></button>
</div>


            <div class="smacg-track-sep"></div>

            <?php
                $prog_val      = intval( $user_anime_entry['progress'] ?? 0 );
                $is_full       = ! empty( $user_anime_entry['fullcleared'] );
                $has_total     = ( $episodes > 0 );
                $prog_pct      = $has_total ? min( 100, round( ( $prog_val / max( 1, $episodes ) ) * 100 ) ) : 0;
                // 連載中沒有總集數時，用已播出集數當參考總數（如果有）
                $display_total = $has_total ? $episodes : ( $ep_aired > 0 ? $ep_aired : '?' );
            ?>
            <div class="smacg-progress-group">
                <div class="smacg-prog-top">
                    <span class="smacg-prog-label">
                        <?php
                        if ( $is_full ) {
                            echo '🎉 已全破！';
                        } elseif ( ! $has_total && $ep_aired > 0 ) {
                            echo '📡 連載中（已播 ' . esc_html( $ep_aired ) . ' 集）';
                        } elseif ( ! $has_total ) {
                            echo '📡 連載中';
                        } elseif ( $prog_val > 0 ) {
                            echo '📺 觀看中';
                        } else {
                            echo '&nbsp;';
                        }
                        ?>
                    </span>
                    <?php if ( $has_total ) : ?>
                        <span class="smacg-prog-pct"><?php echo $prog_pct; ?>%</span>
                    <?php else : ?>
                        <span class="smacg-prog-pct">—</span>
                    <?php endif; ?>
                </div>

                <?php if ( $has_total ) : ?>
                    <div class="smacg-prog-bar-wrap">
                        <div class="smacg-prog-bar" style="width:<?php echo esc_attr( $prog_pct ); ?>%"></div>
                    </div>
                <?php endif; ?>

                <div class="smacg-prog-controls">
                    <button class="smacg-prog-btn" data-action="progress" data-value="-1">−</button>
                    <span class="smacg-prog-display">
                        <span class="smacg-prog-current"><?php echo esc_html( $prog_val ); ?></span>
                        <span class="smacg-prog-sep"> / </span>
                        <span class="smacg-prog-total"><?php echo esc_html( $display_total ); ?></span>
                        <span class="smacg-prog-unit"> 集</span>
                    </span>
                    <button class="smacg-prog-btn" data-action="progress" data-value="1">＋</button>
                </div>
            </div>
            <div class="smacg-track-sep"></div>


<div class="smacg-action-group">
    <button class="smacg-icon-btn smacg-fav-btn <?php echo ( $user_anime_entry['favorited'] ?? false ) ? 'is-active' : ''; ?>" data-action="favorite" title="收藏">
        <span class="smacg-ico"><?php echo ( $user_anime_entry['favorited'] ?? false ) ? '⭐' : '☆'; ?></span>
        <span class="smacg-icon-label">收藏</span>
    </button>
    <button class="smacg-icon-btn smacg-clear-btn <?php echo ( $user_anime_entry['fullcleared'] ?? false ) ? 'is-active' : ''; ?>" data-action="fullclear" title="全破">
        <span class="smacg-ico">🏆</span>
        <span class="smacg-icon-label">全破</span>
    </button>
    <button class="smacg-icon-btn smacg-share-btn"
            data-action="share"
            data-title="<?php echo esc_attr( $display_title ); ?>"
            data-url="<?php echo esc_attr( get_permalink() ); ?>"
            title="分享">
        <span class="smacg-ico">🔗</span>
        <span class="smacg-icon-label">分享</span>
    </button>
</div>


        </div><!-- /.smacg-track-main -->

        <div class="smacg-point-toast" aria-live="polite"></div>

    </div><!-- /.smacg-track-bar -->

    <?php /* ── 分享浮窗 ── */ ?>
    <div class="smacg-share-modal" id="smacg-share-modal" role="dialog" aria-modal="true" style="display:none">
        <div class="smacg-share-inner">
            <p class="smacg-share-title">分享《<?php echo esc_html( $display_title ); ?>》</p>
            <div class="smacg-share-btns">
                <a class="smacg-share-link smacg-share-x"
                   href="https://twitter.com/intent/tweet?text=<?php echo urlencode( $display_title . ' | 微笑動漫 ' . get_permalink() ); ?>"
                   target="_blank" rel="noopener">𝕏 / Twitter</a>
                <a class="smacg-share-link smacg-share-fb"
                   href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode( get_permalink() ); ?>"
                   target="_blank" rel="noopener">Facebook</a>
                <button class="smacg-share-link smacg-share-copy" id="smacg-copy-link">📋 複製連結</button>
            </div>
            <button class="smacg-share-close" id="smacg-share-close">✕</button>
        </div>
    </div>

    <?php /* ── Tabs ── */ ?>
    <div class="asd-tabs-wrap">
        <nav class="asd-tabs" id="asd-tabs" aria-label="頁面導航">
            <a class="asd-tab" href="#asd-sec-info">📋 基本資訊</a>
            <?php if ( $synopsis ) : ?><a class="asd-tab" href="#asd-sec-synopsis">📝 劇情簡介</a><?php endif; ?>
            <?php if ( $youtube_id ) : ?><a class="asd-tab" href="#asd-sec-trailer">🎞 預告片</a><?php endif; ?>
            <?php if ( ! empty( $episodes_list ) ) : ?><a class="asd-tab" href="#asd-sec-episodes">📺 集數列表</a><?php endif; ?>
            <?php if ( ! empty( $staff_list ) ) : ?><a class="asd-tab" href="#asd-sec-staff">🎬 STAFF</a><?php endif; ?>
            <?php if ( ! empty( $cast_to_display ) ) : ?><a class="asd-tab" href="#asd-sec-cast">🎭 CAST</a><?php endif; ?>
            <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?><a class="asd-tab" href="#asd-sec-music">🎵 主題曲</a><?php endif; ?>
            <?php if ( ! empty( $faq_items ) ) : ?><a class="asd-tab" href="#asd-sec-faq">❓ 常見問題</a><?php endif; ?>
            <?php if ( $official_site || $twitter_url || $wikipedia_url || $tiktok_url || $anilist_id || $mal_id || $bangumi_id ) : ?>
                <a class="asd-tab" href="#asd-sec-links">🔗 外部連結</a>
            <?php endif; ?>
            <a class="asd-tab" href="#asd-sec-comments">💬 留言</a>
        </nav>

        <div class="asd-container asd-container--has-sidebar">
            <main class="asd-main" id="asd-main">

                <?php /* ── 基本資訊 ── */ ?>
                <section class="asd-section" id="asd-sec-info">
                    <h2 class="asd-section-title">📋 基本資訊</h2>
                    <div class="asd-info-grid">
                        <?php
                        $info_rows = [
                            '類型'     => $format_label,
                            '集數'     => $ep_str,
                            '狀態'     => $status_label,
                            '播出季度' => $season_str,
                            '每集時長' => $duration ? $duration . ' 分鐘' : '',
                            '開始日期' => $start_date,
                            '結束日期' => ( $end_date && $status === 'FINISHED' ) ? $end_date : '',
                            '原作來源' => $source_label,
                            '製作公司' => $studio,
                            '台灣代理' => $tw_dist_display,
                            '播出頻道' => $tw_broadcast,
                            '最後更新' => get_the_modified_date( 'Y-m-d' ), //
                        ];
                        foreach ( $info_rows as $label => $val ) :
                            if ( $val === '' || $val === null ) continue;
                        ?>
                            <div class="asd-info-row">
                                <span class="asd-info-label"><?php echo esc_html( $label ); ?></span>
                                <span class="asd-info-val"><?php echo esc_html( $val ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( $status === 'RELEASING' && ! empty( $airing_data['airingAt'] ) ) : ?>
                        <div class="asd-airing-bar">
                            <span>第 <?php echo esc_html( $airing_data['episode'] ?? '' ); ?> 集播出倒數</span>
                            <strong class="asd-countdown" data-ts="<?php echo esc_attr( $airing_data['airingAt'] ); ?>"></strong>
                        </div>
                    <?php endif; ?>
                </section>

                <?php /* ── 劇情簡介 ── */ ?>
                <?php if ( $synopsis ) : ?>
                    <section class="asd-section" id="asd-sec-synopsis">
                        <h2 class="asd-section-title">📝 劇情簡介</h2>
                        <div class="asd-synopsis"><?php echo wp_kses_post( wpautop( $synopsis ) ); ?></div>
                    </section>
                <?php endif; ?>

<?php if ( $has_trailer ) : ?>
    <section class="asd-section" id="asd-sec-trailer">
        <h2 class="asd-section-title">🎞 預告片<?php echo count( $trailer_items ) > 1 ? ' <span class="asd-pv-count">（' . count( $trailer_items ) . '）</span>' : ''; ?></h2>

        <div class="asd-pv-box" data-pv-count="<?php echo count( $trailer_items ); ?>">

            <?php if ( count( $trailer_items ) > 1 ) : ?>
                <div class="asd-pv-tabs" role="tablist" aria-label="預告片切換">
                    <?php foreach ( $trailer_items as $i => $pv ) : ?>
                        <button type="button"
                                class="asd-pv-tab<?php echo $i === 0 ? ' is-active' : ''; ?>"
                                role="tab"
                                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                                aria-controls="asd-pv-panel-<?php echo (int) $i; ?>"
                                data-pv-index="<?php echo (int) $i; ?>"
                                data-pv-id="<?php echo esc_attr( $pv['id'] ); ?>">
                            <span class="asd-pv-tab-icon">▶</span>
                            <span class="asd-pv-tab-label"><?php echo esc_html( $pv['label'] ); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="asd-pv-panels">
                <?php foreach ( $trailer_items as $i => $pv ) : ?>
                    <div class="asd-pv-panel<?php echo $i === 0 ? ' is-active' : ''; ?>"
                         id="asd-pv-panel-<?php echo (int) $i; ?>"
                         role="tabpanel"
                         data-pv-index="<?php echo (int) $i; ?>"
                         data-pv-id="<?php echo esc_attr( $pv['id'] ); ?>">
                        <div class="asd-trailer-wrap">
                            <?php if ( $i === 0 ) : ?>
                                <iframe src="https://www.youtube.com/embed/<?php echo esc_attr( $pv['id'] ); ?>?rel=0&modestbranding=1"
                                        title="<?php echo esc_attr( $display_title . ' ' . $pv['label'] ); ?>"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen loading="lazy"></iframe>
                            <?php else : ?>
                                <?php /* 非首張延後載入：點擊 tab 才注入 iframe，省流量 */ ?>
                                <div class="asd-pv-placeholder"
                                     style="background-image:url('https://i.ytimg.com/vi/<?php echo esc_attr( $pv['id'] ); ?>/hqdefault.jpg')">
                                    <button type="button" class="asd-pv-play"
                                            data-pv-id="<?php echo esc_attr( $pv['id'] ); ?>"
                                            data-pv-title="<?php echo esc_attr( $display_title . ' ' . $pv['label'] ); ?>"
                                            aria-label="播放 <?php echo esc_attr( $pv['label'] ); ?>">
                                        <span class="asd-pv-play-icon">▶</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
<?php endif; ?>

                <?php /* ── 集數列表 ── */ ?>
                <?php if ( ! empty( $episodes_list ) ) : ?>
                    <section class="asd-section" id="asd-sec-episodes">
                        <h2 class="asd-section-title">📺 集數列表</h2>
                        <div class="asd-ep-list" id="asd-ep-list">
                            <?php foreach ( $episodes_list as $i => $ep ) :
                                $ep_num     = (int) ( $ep['ep']      ?? 0 );
                                $ep_name_cn = trim( $ep['name_cn']   ?? '' );
                                $ep_name_ja = trim( $ep['name']      ?? '' );
                                $ep_airdate = $ep['airdate']          ?? '';
                                if ( $ep_name_cn !== '' && class_exists( 'Anime_Sync_CN_Converter' ) ) {
                                    $ep_name_cn = Anime_Sync_CN_Converter::static_convert( $ep_name_cn );
                                }
                                $ep_name    = $ep_name_cn ?: $ep_name_ja;
                                $ep_display = $ep_num > 0 ? '第' . $ep_num . '集' : '第' . ( $i + 1 ) . '集';
                            ?>
                                <div class="asd-ep-row<?php echo $i >= 3 ? ' asd-ep-hidden' : ''; ?>">
                                    <span class="asd-ep-num"><?php echo esc_html( $ep_display ); ?></span>
                                    <div class="asd-ep-body">
                                        <?php if ( $ep_name ) : ?><span class="asd-ep-title"><?php echo esc_html( $ep_name ); ?></span><?php endif; ?>
                                        <?php if ( $ep_name_ja && $ep_name_cn && $ep_name_ja !== $ep_name_cn ) : ?>
                                            <span class="asd-ep-title-ja"><?php echo esc_html( $ep_name_ja ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ( $ep_airdate ) : ?><span class="asd-ep-date"><?php echo esc_html( $ep_airdate ); ?></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( count( $episodes_list ) > 3 ) : ?>
                            <div style="display:flex;justify-content:center;margin-top:12px;">
                                <button class="asd-ep-toggle" type="button">顯示全部 <?php echo count( $episodes_list ); ?> 集▼</button>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php /* ── Staff ── */ ?>
                <?php if ( ! empty( $staff_list ) ) : ?>
                    <section class="asd-section" id="asd-sec-staff">
                        <h2 class="asd-section-title">🎬 STAFF</h2>
                        <div class="asd-staff-grid-v2" id="asd-staff-grid">
                            <?php foreach ( $staff_list as $i => $s ) :
                                $s_name   = trim( $s['name']   ?? '' );
                                $s_native = trim( $s['native'] ?? '' );
                                $s_role   = trim( $s['role']   ?? '' );
                            ?>
                                <div class="asd-staff-card-v2<?php echo $i >= 10 ? ' asd-staff-hidden' : ''; ?>">
                                    <div class="asd-staff-info">
                                        <span class="asd-staff-role"><?php echo esc_html( $s_role ); ?></span>
                                        <span class="asd-staff-name"><?php echo esc_html( $s_name ); ?></span>
                                        <?php if ( $s_native && $s_native !== $s_name ) : ?>
                                            <span class="asd-staff-native"><?php echo esc_html( $s_native ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( count( $staff_list ) > 10 ) : ?>
                            <div style="display:flex;justify-content:center;margin-top:12px;">
                                <button class="asd-staff-toggle" id="asd-staff-toggle" type="button">顯示全部 <?php echo count( $staff_list ); ?> 人 ▼</button>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php /* ── Cast ── */ ?>
                <?php if ( ! empty( $cast_to_display ) ) : ?>
                    <section class="asd-section" id="asd-sec-cast">
                        <h2 class="asd-section-title">🎭 CAST</h2>
                        <div class="asd-cast-grid" id="asd-cast-grid">
                            <?php foreach ( $cast_to_display as $i => $c ) :
                                $c_char_name   = trim( $c['name']   ?? '' );
                                $c_char_native = trim( $c['native'] ?? '' );
                                $c_char_image  = trim( $c['image']  ?? '' );
                                $va            = ( ! empty( $c['voice_actors'] ) && is_array( $c['voice_actors'] ) ) ? $c['voice_actors'][0] : [];
                                $c_va_name     = trim( $va['name']   ?? '' );
                                $c_va_native   = trim( $va['native'] ?? '' );
                                $c_fb          = function_exists( 'mb_substr' ) ? mb_substr( $c_char_name, 0, 2 ) : substr( $c_char_name, 0, 2 );
                            ?>
                                <div class="asd-cast-card<?php echo $i >= 6 ? ' asd-cast-hidden' : ''; ?>">
                                    <div class="asd-cast-avatar-wrap">
                                        <?php if ( $c_char_image ) : ?>
                                            <img src="<?php echo esc_url( $c_char_image ); ?>"
                                                 alt="<?php echo esc_attr( $c_char_name ); ?>"
                                                 loading="lazy"
                                                 onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            <div class="asd-cast-avatar-fb" style="display:none"><span><?php echo esc_html( $c_fb ); ?></span></div>
                                        <?php else : ?>
                                            <div class="asd-cast-avatar-fb"><span><?php echo esc_html( $c_fb ); ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="asd-cast-info">
                                        <span class="asd-cast-char"><?php echo esc_html( $c_char_name ); ?></span>
                                        <?php if ( $c_char_native && $c_char_native !== $c_char_name ) : ?>
                                            <span class="asd-cast-char-native"><?php echo esc_html( $c_char_native ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $c_va_name ) : ?>
                                            <div class="asd-cast-va">
                                                <div class="asd-cast-va-info">
                                                    <span class="asd-cast-va-name">CV.<?php echo esc_html( $c_va_name ); ?></span>
                                                    <?php if ( $c_va_native && $c_va_native !== $c_va_name ) : ?>
                                                        <span class="asd-cast-va-native"><?php echo esc_html( $c_va_native ); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( count( $cast_to_display ) > 4 ) : ?>
                            <div style="display:flex;justify-content:center;margin-top:12px;">
                                <button class="asd-cast-toggle" id="asd-cast-toggle" type="button">顯示全部 <?php echo count( $cast_to_display ); ?> 人 ▼</button>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php /* ── 主題曲 ── */ ?>
                <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?>
                    <section class="asd-section" id="asd-sec-music">
                        <h2 class="asd-section-title">🎵 主題曲</h2>
                        <?php foreach ( [ 'OP' => $openings, 'ED' => $endings ] as $music_type => $music_list ) : ?>
                            <?php if ( empty( $music_list ) ) continue; ?>
                            <div class="asd-music-group">
                                <h3 class="asd-music-group-title"><?php echo $music_type === 'OP' ? '片頭曲 OP' : '片尾曲 ED'; ?></h3>
                                <?php foreach ( $music_list as $t ) :
                                    $t_type      = strtoupper( trim( $t['type'] ?? '' ) );
                                    $t_title     = trim( $t['title'] ?? '' );
                                    $t_native    = trim( $t['title_native'] ?? '' );
                                    $t_artists_raw = $t['artists'] ?? [];
                                    $t_artist_names = []; $t_artist_romaji_parts = [];
                                    foreach ( $t_artists_raw as $a ) {
                                        $dn = trim( $a['name_native'] ?? $a['name'] ?? '' );
                                        if ( $dn !== '' ) $t_artist_names[] = $dn;
                                        $rn = trim( $a['name'] ?? '' );
                                        if ( $rn !== '' ) $t_artist_romaji_parts[] = $rn;
                                    }
                                    $t_artist        = implode( '、', $t_artist_names );
                                    $t_artist_romaji = implode( ', ', $t_artist_romaji_parts );
                                    $t_audio_url = trim( $t['audio_url'] ?? '' );
                                    $t_video_url = trim( $t['video_url'] ?? '' );
                                    $open_url    = $t_video_url ?: $t_audio_url;
                                    $badge_class = ( strpos( $t_type, 'OP' ) === 0 ) ? 'asd-music-type-badge--op' : 'asd-music-type-badge--ed';
                                ?>
                                    <div class="asd-music-card-v2">
                                        <span class="asd-music-type-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $t_type ); ?></span>
                                        <div class="asd-music-body">
                                            <?php if ( $t_native ) : ?><span class="asd-music-title"><?php echo esc_html( $t_native ); ?></span><?php endif; ?>
                                            <?php if ( $t_title && $t_title !== $t_native ) : ?><span class="asd-music-native"><?php echo esc_html( $t_title ); ?></span><?php endif; ?>
                                            <?php if ( $t_artist !== '' ) : ?>
                                                <span class="asd-music-artist">by <?php echo esc_html( $t_artist ); ?>
                                                    <?php if ( $t_artist_romaji !== '' && $t_artist_romaji !== $t_artist ) : ?>
                                                        <span class="asd-music-artist-romaji">(<?php echo esc_html( $t_artist_romaji ); ?>)</span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php elseif ( $t_artist_romaji !== '' ) : ?>
                                                <span class="asd-music-artist">by <?php echo esc_html( $t_artist_romaji ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ( $t_audio_url || $t_video_url ) : ?>
                                            <div class="asd-music-player-wrap"
                                                 data-audio-src="<?php echo esc_url( $t_audio_url ); ?>"
                                                 data-video-src="<?php echo esc_url( $t_video_url ); ?>">
                                                <audio class="asd-music-audio" preload="none"></audio>
                                                <video class="asd-music-video" preload="none" playsinline style="display:none;width:0;height:0;opacity:0;pointer-events:none;"></video>
                                                <button class="asd-music-play-btn" type="button" aria-label="播放"></button>
                                                <div class="asd-music-progress-wrap"><div class="asd-music-progress-bar"></div></div>
                                                <span class="asd-music-time">0:00</span>
                                                <?php if ( $open_url ) : ?>
                                                    <a class="asd-music-open-link" href="<?php echo esc_url( $open_url ); ?>" target="_blank" rel="noopener noreferrer">看片</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
<?php /* ── 串流平台 ── */ ?>
<?php if ( ! empty( $tw_streaming_items ) || ! empty( $overseas_streams ) ) : ?>
    <section class="asd-section" id="asd-sec-stream">
        <h2 class="asd-section-title">📺 串流平台</h2>

        <?php /* 台灣地區（手填 ACF 欄位） */ ?>
        <?php if ( ! empty( $tw_streaming_items ) ) : ?>
        <div class="asd-stream-region asd-stream-region--tw">
            <div class="asd-stream-region-head">
                <span class="asd-stream-dot asd-stream-dot--tw"></span><span>台灣地區</span>
            </div>
            <div class="asd-stream-list">
                <?php foreach ( $tw_streaming_items as $si ) :
                    $si_label     = $si['label'] ?? '';
                    $si_url       = $si['url'] ?? '';
                    $si_icon_url  = $si['icon_url'] ?? '';
                    $si_icon_only = ! empty( $si['icon_only'] );
                    if ( $si_label === '' ) continue;
                    $btn_class = 'asd-stream-btn' . ( $si_icon_only ? ' asd-stream-btn--icon-only' : '' ) . ( $si_url ? '' : ' asd-stream-btn--no-link' );
                ?>
                    <?php if ( $si_url ) : ?>
                        <a href="<?php echo esc_url( $si_url ); ?>" target="_blank" rel="noopener noreferrer" class="<?php echo esc_attr( $btn_class ); ?>" title="<?php echo esc_attr( $si_label ); ?>">
                            <?php if ( $si_icon_url ) : ?><img src="<?php echo esc_url( $si_icon_url ); ?>" alt="<?php echo esc_attr( $si_label ); ?>" class="asd-stream-icon"><?php endif; ?>
                            <?php if ( ! $si_icon_only ) : ?><span class="asd-stream-label"><?php echo esc_html( $si_label ); ?></span><?php endif; ?>
                        </a>
                    <?php else : ?>
                        <span class="<?php echo esc_attr( $btn_class ); ?>" title="<?php echo esc_attr( $si_label ); ?>">
                            <?php if ( $si_icon_url ) : ?><img src="<?php echo esc_url( $si_icon_url ); ?>" alt="<?php echo esc_attr( $si_label ); ?>" class="asd-stream-icon"><?php endif; ?>
                            <?php if ( ! $si_icon_only ) : ?><span class="asd-stream-label"><?php echo esc_html( $si_label ); ?></span><?php endif; ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

<?php /* 海外平台（從 AniList 自動抓的） */ ?>
<?php if ( ! empty( $overseas_streams ) ) :
    /* 海外平台 site 名稱 → icon 檔對照（AniList 回傳的 site 是英文）*/
$overseas_icon_map = [
    'crunchyroll'        => 'crunchyroll_icon.webp',
    'funimation'         => 'funimation_icon.webp',
    'netflix'            => 'netflix_icon.webp',
    'hidive'             => 'hidive_icon.webp',
    'vrv'                => 'vrv_icon.webp',
    'hulu'               => 'disneyplus_icon.webp',
    'wakanim'            => 'wakanim_icon.webp',
    'amazon prime video' => 'amazon_prime_video_icon.webp',
    'disney plus'        => 'disneyplus_icon.webp',
    'bilibili'           => 'bilibili_icon.webp',
    'iqiyi'              => 'iqiyi_icon.webp',
];
?>
<div class="asd-stream-region asd-stream-region--os" style="margin-top:16px;">
    <div class="asd-stream-region-head">
        <span class="asd-stream-dot asd-stream-dot--os" style="background:#888;"></span>
        <span>海外平台</span>
        <span style="font-size:12px;color:var(--asd-text-muted);margin-left:8px;">（台灣可能無法觀看）</span>
    </div>
    <div class="asd-stream-list">
        <?php foreach ( $overseas_streams as $os ) :
            $os_site = trim( $os['site'] ?? '' );
            $os_url  = trim( $os['url']  ?? '' );
            if ( $os_site === '' || $os_url === '' ) continue;

            $os_key  = strtolower( $os_site );
            $os_icon = isset( $overseas_icon_map[ $os_key ] )
                ? $provider_icon_base . $overseas_icon_map[ $os_key ]
                : '';
        ?>
            <a href="<?php echo esc_url( $os_url ); ?>"
               target="_blank" rel="noopener noreferrer"
               class="asd-stream-btn<?php echo $os_icon ? ' asd-stream-btn--icon-only' : ''; ?> asd-stream-btn--os"
               title="<?php echo esc_attr( $os_site ); ?>">
                <?php if ( $os_icon ) : ?>
                    <img src="<?php echo esc_url( $os_icon ); ?>"
                         alt="<?php echo esc_attr( $os_site ); ?>"
                         class="asd-stream-icon"
                         onerror="this.onerror=null;this.outerHTML='<span class=\'asd-stream-label\'><?php echo esc_js( $os_site ); ?></span>';">
                <?php else : ?>
                    <span class="asd-stream-label"><?php echo esc_html( $os_site ); ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

      <?php /* ── 免責聲明 ── */ ?>
        <p class="asd-stream-disclaimer" style="margin-top:16px;font-size:0.85em;color:var(--asd-text-muted,#888);line-height:1.6;">
            ⚠️ 串流連結可能因平台授權異動而失效，建議以官方平台公告為準。
        </p>
    </section>
<?php endif; ?>

                <?php /* ── FAQ ── */ ?>
                <?php if ( ! empty( $faq_items ) ) : ?>
                    <section class="asd-section" id="asd-sec-faq">
                        <h2 class="asd-section-title">❓ 常見問題</h2>
                        <div class="asd-faq-list">
                            <?php foreach ( $faq_items as $f ) :
                                if ( empty( $f['q'] ) || empty( $f['a'] ) ) continue;
                            ?>
                                <div class="asd-faq-item">
                                    <div class="asd-faq-q">
                                        <span class="asd-faq-q-label">Q.</span>
                                        <span class="asd-faq-q-text"><?php echo esc_html( $f['q'] ); ?></span>
                                    </div>
                                    <div class="asd-faq-a">
                                        <span class="asd-faq-a-label">A.</span>
                                        <div class="asd-faq-a-text"><?php echo wp_kses_post( wpautop( $f['a'] ) ); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php /* ── 外部連結 ── */ ?>
                <?php if ( $official_site || $twitter_url || $wikipedia_url || $tiktok_url || $anilist_id || $mal_id || $bangumi_id ) : ?>
                    <section class="asd-section" id="asd-sec-links">
                        <h2 class="asd-section-title">🔗 外部連結</h2>
                        <div class="asd-ext-links-grid">
                            <?php if ( $official_site ) : ?><a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener noreferrer" class="asd-ext-link-card"><span class="asd-ext-site">🌐 官方網站</span><span class="asd-ext-arrow">→</span></a><?php endif; ?>
                            <?php if ( $twitter_url ) : ?><a href="<?php echo esc_url( $twitter_url ); ?>" target="_blank" rel="noopener noreferrer" class="asd-ext-link-card"><span class="asd-ext-site">𝕏 Twitter / X</span><span class="asd-ext-arrow">→</span></a><?php endif; ?>
                            <?php if ( $wikipedia_url ) : ?><a href="<?php echo esc_url( $wikipedia_url ); ?>" target="_blank" rel="noopener noreferrer" class="asd-ext-link-card"><span class="asd-ext-site">📖 Wikipedia</span><span class="asd-ext-arrow">→</span></a><?php endif; ?>
                            <?php if ( $tiktok_url ) : ?><a href="<?php echo esc_url( $tiktok_url ); ?>" target="_blank" rel="noopener noreferrer" class="asd-ext-link-card"><span class="asd-ext-site">🎵 TikTok</span><span class="asd-ext-arrow">→</span></a><?php endif; ?>
                            <?php if ( $anilist_id ) : ?><a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/" target="_blank" rel="noopener noreferrer" class="asd-ext-link-card asd-ext--al"><span class="asd-ext-site">🔵 AniList</span><span class="asd-ext-arrow">→</span></a><?php endif; ?>
                            <?php if ( $mal_id ) : ?><a href="https://myanimelist.net/anime/<?php echo esc_attr( $mal_id ); ?>/" target="_blank" rel="noopener noreferrer" class="asd-ext-link-card asd-ext--mal"><span class="asd-ext-site">🔵 MyAnimeList</span><span class="asd-ext-arrow">→</span></a><?php endif; ?>
                            <?php if ( $bangumi_id ) : ?><a href="https://bgm.tv/subject/<?php echo esc_attr( $bangumi_id ); ?>/" target="_blank" rel="noopener noreferrer" class="asd-ext-link-card asd-ext--bgm"><span class="asd-ext-site">🍡 Bangumi</span><span class="asd-ext-arrow">→</span></a><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php /* ── 留言 ── */ ?>
                <section class="asd-section asd-comments" id="asd-sec-comments">
                    <h2 class="asd-section-title">💬 留言</h2>
                    <div class="asd-comments-inner">
                        <?php comments_template(); ?>
                    </div>
                </section>

            </main><!-- /.asd-main -->

            <aside class="asd-sidebar" aria-label="側邊欄">

                <div class="asd-side-section">
                    <div class="asd-side-section__head"><h3>🏷️ 作品標籤</h3></div>
                    <div class="asd-tags-wrap">
                        <?php if ( ! empty( $studio ) ) :
                            $studio_term = get_terms( [ 'taxonomy' => 'anime_studio_tax', 'name' => $studio, 'hide_empty' => false, 'number' => 1 ] );
                            $studio_url  = ( ! is_wp_error( $studio_term ) && ! empty( $studio_term ) ) ? get_term_link( $studio_term[0] ) : home_url( '/anime/' );
                        ?>
                            <a href="<?php echo esc_url( $studio_url ); ?>" class="asd-tag-item asd-tag-item--studio">🎬 <?php echo esc_html( $studio ); ?></a>
                        <?php endif; ?>
                        <?php foreach ( $season_child_terms as $st ) : ?>
                            <a href="<?php echo esc_url( get_term_link( $st ) ); ?>" class="asd-tag-item asd-tag-item--season"><?php echo esc_html( $st->name ); ?></a>
                        <?php endforeach; ?>
                        <?php foreach ( $genre_terms as $gt ) : ?>
                            <a href="<?php echo esc_url( get_term_link( $gt ) ); ?>" class="asd-tag-item"><?php echo esc_html( $gt->name ); ?></a>
                        <?php endforeach; ?>
                        <?php if ( empty( $studio ) && empty( $season_child_terms ) && empty( $genre_terms ) ) : ?>
                            <p class="asd-side-empty">暫無標籤資料</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="asd-side-section">
                    <div class="asd-side-section__head"><h3>📰 相關新聞</h3></div>
                    <div class="asd-side-news">
                        <?php if ( ! empty( $news_items ) ) : ?>
                            <?php foreach ( $news_items as $ni ) : ?>
                                <?php if ( ! empty( $ni['url'] ) ) : ?>
                                    <a href="<?php echo esc_url( $ni['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="asd-news-card">
                                        <span class="asd-news-card__title"><?php echo esc_html( $ni['title'] ); ?></span>
                                        <span class="asd-news-arrow">→</span>
                                    </a>
                                <?php else : ?>
                                    <div class="asd-news-card"><span class="asd-news-card__title"><?php echo esc_html( $ni['title'] ); ?></span></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="asd-side-empty">暫無相關新聞</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="asd-side-section">
                    <div class="asd-side-section__head"><h3>🔗 相關作品</h3></div>
                    <div class="asd-side-cards">
                        <?php if ( ! empty( $site_relations ) ) : ?>
                            <?php foreach ( $site_relations as $rel ) : ?>
                                <a href="<?php echo esc_url( $rel['url'] ); ?>" class="asd-mini-card">
                                    <div class="asd-mini-card__thumb">
                                        <?php if ( ! empty( $rel['cover_image'] ) ) : ?>
                                            <img src="<?php echo esc_url( $rel['cover_image'] ); ?>" alt="<?php echo esc_attr( $rel['title_zh'] ); ?>" loading="lazy">
                                        <?php else : ?>
                                            <div class="asd-mini-card__thumb-fb"><span><?php echo esc_html( mb_substr( $rel['title_zh'], 0, 2 ) ); ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="asd-mini-card__body">
                                        <span class="asd-mini-card__title"><?php echo esc_html( $rel['title_zh'] ); ?></span>
                                        <span class="asd-mini-card__meta"><?php echo esc_html( $rel['relation_label'] ); ?><?php echo $rel['format'] ? ' · ' . esc_html( $rel['format'] ) : ''; ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="asd-side-empty">暫無相關作品</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( $affiliate_html ) : ?>
                    <div class="asd-side-section">
                        <div class="asd-side-section__head"><h3>🛒 購買連結</h3></div>
                        <div class="asd-affiliate-box"><?php echo wp_kses_post( $affiliate_html ); ?></div>
                    </div>
                <?php endif; ?>

                <div class="asd-side-section asd-sponsor-block">
                    <div class="asd-sponsor-title">支持微笑動漫</div>
                    <div class="asd-sponsor-desc">喜歡這部作品的資訊嗎？微笑動漫每天整合來自全球三大資料庫的動漫情報，你的咖啡讓我們繼續走下去 ☕</div>
                    <a href="https://YOUR-SPONSOR-URL-HERE" target="_blank" rel="noopener noreferrer" class="asd-sponsor-btn">贊助微笑動漫</a>
                    <div class="asd-sponsor-note">贊助費用於伺服器維護，感謝每一位支持者</div>
                </div>

                <div class="asd-ad-placeholder" aria-label="廣告版位" role="complementary">
                    <div class="asd-ad-inner"></div>
                </div>

            </aside><!-- /.asd-sidebar -->

        </div><!-- /.asd-container -->

    </div><!-- /.asd-tabs-wrap -->

</div><!-- /.asd-wrap -->

<?php endwhile; get_footer(); ?>
