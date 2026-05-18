<?php
/**
 * Bangumi 模組載入器
 *
 * 負責：
 *   - /bangumi/、/bangumi/{YYYYMM}/、/bangumi/archive/ rewrite 規則
 *   - template_redirect 將 URL 接到對應的 page-bangumi*.php
 *   - 季度時間計算 helpers（current_ym / parse_ym / shift_ym / normalize_ym）
 *   - SEO helpers（render_meta / render_og / render_schema / render_breadcrumb）
 *   - sitemap 注入
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-18)
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 1. Rewrite 規則
 * ============================================================ */
add_action( 'init', function () {
    add_rewrite_rule( '^bangumi/?$',                        'index.php?bangumi_view=current',                    'top' );
    add_rewrite_rule( '^bangumi/archive/?$',                'index.php?bangumi_view=archive',                    'top' );
    add_rewrite_rule( '^bangumi/([0-9]{6})/?$',             'index.php?bangumi_view=season&bangumi_ym=$matches[1]', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'bangumi_view';
    $vars[] = 'bangumi_ym';
    return $vars;
} );

/* 啟用主題時自動 flush（一次性） */
add_action( 'after_switch_theme', function () {
    flush_rewrite_rules();
} );

/* ============================================================
 * 2. 路由：template_redirect → include page-bangumi*.php
 * ============================================================ */
add_action( 'template_redirect', function () {
    $view = get_query_var( 'bangumi_view' );
    if ( ! $view ) return;

    // 強制 200（不是 404）
    status_header( 200 );
    nocache_headers();

    if ( $view === 'current' ) {
        wp_safe_redirect( home_url( '/bangumi/' . smacg_bangumi_current_ym() . '/' ), 302 );
        exit;
    }

    if ( $view === 'archive' ) {
        $tpl = get_stylesheet_directory() . '/page-bangumi-archive.php';
        if ( file_exists( $tpl ) ) { include $tpl; exit; }
    }

    if ( $view === 'season' ) {
        $ym  = (string) get_query_var( 'bangumi_ym' );
        $norm = smacg_bangumi_normalize_ym( $ym );
        if ( $norm !== $ym ) {
            wp_safe_redirect( home_url( "/bangumi/{$norm}/" ), 301 );
            exit;
        }
        $tpl = get_stylesheet_directory() . '/page-bangumi.php';
        if ( file_exists( $tpl ) ) { include $tpl; exit; }
    }
}, 0 );

/* ============================================================
 * 3. /season/ 舊網址 301 → /bangumi/
 * ============================================================ */
add_action( 'template_redirect', function () {
    $req = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ) : '';
    if ( $req === 'season' || strpos( $req, 'season/' ) === 0 ) {
        wp_safe_redirect( home_url( '/bangumi/' ), 301 );
        exit;
    }
} );

/* ============================================================
 * 4. 季度時間計算 helpers
 * ============================================================ */

/** 取目前所在季度的代表月（YYYYMM 字串，月份只會是 01/04/07/10） */
function smacg_bangumi_current_ym(): string {
    $y = (int) date( 'Y' );
    $m = (int) date( 'n' );
    $season_m = (int) ( floor( ( $m - 1 ) / 3 ) * 3 + 1 );
    return sprintf( '%04d%02d', $y, $season_m );
}

/** 把任意 YYYYMM 正規化為當季的代表月（如 202605 → 202604） */
function smacg_bangumi_normalize_ym( string $ym ): string {
    if ( ! preg_match( '/^(\d{4})(\d{2})$/', $ym, $m ) ) return smacg_bangumi_current_ym();
    $y  = (int) $m[1];
    $mn = (int) $m[2];
    if ( $mn < 1 || $mn > 12 ) return smacg_bangumi_current_ym();
    $season_m = (int) ( floor( ( $mn - 1 ) / 3 ) * 3 + 1 );
    return sprintf( '%04d%02d', $y, $season_m );
}

/** 解析 YYYYMM 為季度資訊 */
function smacg_bangumi_parse_ym( string $ym ): array {
    $ym = smacg_bangumi_normalize_ym( $ym );
    $y  = (int) substr( $ym, 0, 4 );
    $mn = (int) substr( $ym, 4, 2 );

    $map = [
        1  => [ 'season' => 'WINTER', 'zh' => '冬', 'en' => 'Winter' ],
        4  => [ 'season' => 'SPRING', 'zh' => '春', 'en' => 'Spring' ],
        7  => [ 'season' => 'SUMMER', 'zh' => '夏', 'en' => 'Summer' ],
        10 => [ 'season' => 'FALL',   'zh' => '秋', 'en' => 'Autumn' ],
    ];
    $info = $map[ $mn ];
    return [
        'ym'        => $ym,
        'year'      => $y,
        'month'     => $mn,
        'season'    => $info['season'],
        'season_zh' => $info['zh'],
        'season_en' => $info['en'],
        'label'     => sprintf( '%d 年 %s季新番', $y, $info['zh'] ),
    ];
}

/** 季度位移（正負 1 表示往後/往前一季） */
function smacg_bangumi_shift_ym( string $ym, int $delta ): string {
    $info = smacg_bangumi_parse_ym( $ym );
    $idx  = $info['year'] * 4 + (int) ( ( $info['month'] - 1 ) / 3 );
    $idx += $delta;
    $y    = (int) floor( $idx / 4 );
    $mn   = ( $idx % 4 ) * 3 + 1;
    return sprintf( '%04d%02d', $y, $mn );
}

/* ============================================================
 * 5. SEO helpers
 * ============================================================ */

function smacg_bangumi_render_meta( array $ctx ): void {
    echo "\n<!-- Bangumi SEO -->\n";
    echo '<meta name="description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url( $ctx['canonical'] ) . '">' . "\n";
}

function smacg_bangumi_render_og( array $ctx ): void {
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $ctx['title'] ) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $ctx['description'] ) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $ctx['canonical'] ) . '">' . "\n";
    if ( ! empty( $ctx['og_image'] ) ) {
        echo '<meta property="og:image" content="' . esc_url( $ctx['og_image'] ) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}

function smacg_bangumi_render_schema( array $ctx, array $posts ): void {
    $items = [];
    $i = 1;
    foreach ( array_slice( $posts, 0, 30 ) as $p ) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $i++,
            'item'     => [
                '@type' => 'TVSeries',
                'name'  => $p['title'],
                'url'   => $p['url'],
                'image' => $p['cover'] ?: '',
            ],
        ];
    }
    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => $ctx['title'],
        'description'     => $ctx['description'],
        'url'             => $ctx['canonical'],
        'numberOfItems'   => count( $posts ),
        'itemListElement' => $items,
    ];
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

function smacg_bangumi_render_breadcrumb( array $ctx ): void {
    $crumbs = [
        [ 'name' => '首頁',   'url' => home_url( '/' ) ],
        [ 'name' => '新番表', 'url' => home_url( '/bangumi/' ) ],
        [ 'name' => $ctx['label'], 'url' => $ctx['canonical'] ],
    ];

    // 視覺麵包屑
    echo '<nav class="bangumi-breadcrumb" aria-label="breadcrumb">';
    $last = count( $crumbs ) - 1;
    foreach ( $crumbs as $i => $c ) {
        if ( $i === $last ) {
            echo '<span class="bc-current">' . esc_html( $c['name'] ) . '</span>';
        } else {
            echo '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['name'] ) . '</a>';
            echo '<i class="fa-solid fa-chevron-right" style="font-size:10px;opacity:.5;"></i>';
        }
    }
    echo '</nav>';

    // Schema.org BreadcrumbList
    $list = [];
    foreach ( $crumbs as $i => $c ) {
        $list[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $c['name'],
            'item'     => $c['url'],
        ];
    }
    echo '<script type="application/ld+json">' . wp_json_encode( [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $list,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
}

/* ============================================================
 * 6. Sitemap 注入（過去 3 年 + 未來 1 年的所有季度）
 * ============================================================ */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
    return $provider;
}, 10, 2 );

add_filter( 'wp_sitemaps_posts_entry', function ( $entry ) {
    return $entry;
} );

/** 自訂 sitemap 端點：/bangumi-sitemap.xml */
add_action( 'init', function () {
    add_rewrite_rule( '^bangumi-sitemap\.xml$', 'index.php?bangumi_sitemap=1', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'bangumi_sitemap';
    return $vars;
} );
add_action( 'template_redirect', function () {
    if ( ! get_query_var( 'bangumi_sitemap' ) ) return;

    header( 'Content-Type: application/xml; charset=UTF-8' );
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $now_year = (int) date( 'Y' );
    for ( $y = $now_year - 3; $y <= $now_year + 1; $y++ ) {
        foreach ( [ '01', '04', '07', '10' ] as $m ) {
            echo '<url><loc>' . esc_url( home_url( "/bangumi/{$y}{$m}/" ) ) . "</loc></url>\n";
        }
    }
    echo '<url><loc>' . esc_url( home_url( '/bangumi/archive/' ) ) . "</loc></url>\n";
    echo '</urlset>';
    exit;
} );

/* ============================================================
 * 7. 條件式 enqueue（只在 /bangumi/* 載入）
 * ============================================================ */
add_action( 'wp_enqueue_scripts', function () {
    $req = isset( $_SERVER['REQUEST_URI'] ) ? trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ) : '';
    if ( $req !== 'bangumi' && strpos( $req, 'bangumi/' ) !== 0 ) return;

    $theme_url = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();

    $css = $theme_dir . '/assets/css/bangumi.css';
    if ( file_exists( $css ) ) {
        wp_enqueue_style(
            'smacg-bangumi',
            $theme_url . '/assets/css/bangumi.css',
            [],
            filemtime( $css )
        );
    }

    $js = $theme_dir . '/assets/js/bangumi.js';
    if ( file_exists( $js ) ) {
        wp_enqueue_script(
            'smacg-bangumi',
            $theme_url . '/assets/js/bangumi.js',
            [],
            filemtime( $js ),
            true
        );
    }
} );


