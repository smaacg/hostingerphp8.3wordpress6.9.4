<?php
/**
 * Bangumi 歷年存檔頁
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-18)
 *
 * 載入方式：由 bangumi-loader.php 的 template_redirect 自動 include
 * URL：/bangumi/archive/
 *
 * 顯示：所有有資料的年份，每年四季方塊（含作品數），可點擊跳轉
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 1. 從 DB 撈出 (year, season) → count
 * ============================================================ */
global $wpdb;

$rows = $wpdb->get_results(
    "SELECT my.meta_value AS year,
            ms.meta_value AS season,
            COUNT(*)      AS cnt
       FROM {$wpdb->posts} p
       INNER JOIN {$wpdb->postmeta} my ON my.post_id = p.ID AND my.meta_key = 'anime_season_year'
       INNER JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = 'anime_season'
      WHERE p.post_type   = 'anime'
        AND p.post_status = 'publish'
        AND my.meta_value <> ''
        AND ms.meta_value <> ''
      GROUP BY my.meta_value, ms.meta_value
      ORDER BY my.meta_value DESC, ms.meta_value ASC",
    ARRAY_A
);

/* ============================================================
 * 2. 整理為 [year][season] = count
 * ============================================================ */
$season_order = [ 'WINTER', 'SPRING', 'SUMMER', 'FALL' ];
$season_meta  = [
    'WINTER' => [ 'zh' => '冬', 'icon' => '❄️', 'month' => '01', 'color' => '#67e8f9' ],
    'SPRING' => [ 'zh' => '春', 'icon' => '🌸', 'month' => '04', 'color' => '#f9a8d4' ],
    'SUMMER' => [ 'zh' => '夏', 'icon' => '🌊', 'month' => '07', 'color' => '#60a5fa' ],
    'FALL'   => [ 'zh' => '秋', 'icon' => '🍁', 'month' => '10', 'color' => '#fb923c' ],
];

$by_year     = [];
$total_anime = 0;
$max_cnt     = 0;

foreach ( $rows as $r ) {
    $y = (int) $r['year'];
    $s = strtoupper( $r['season'] );
    $c = (int) $r['cnt'];
    if ( ! isset( $season_meta[ $s ] ) || $y < 1990 || $y > 2100 ) continue;
    $by_year[ $y ][ $s ] = $c;
    $total_anime += $c;
    if ( $c > $max_cnt ) $max_cnt = $c;
}

krsort( $by_year );
$total_years = count( $by_year );

/* ============================================================
 * 3. SEO
 * ============================================================ */
$canonical = home_url( '/bangumi/archive/' );
$seo_ctx = [
    'label'       => '歷年新番存檔',
    'canonical'   => $canonical,
    'title'       => sprintf( '歷年新番存檔｜%d 年共 %d 部作品 - 微笑動漫', $total_years, $total_anime ),
    'description' => sprintf( '微笑動漫歷年新番完整存檔，涵蓋 %d 年共 %d 部作品。依年份與季度瀏覽。', $total_years, $total_anime ),
    'og_image'    => '',
];

add_action( 'wp_head', function () use ( $seo_ctx ) {
    smacg_bangumi_render_meta( $seo_ctx );
    smacg_bangumi_render_og( $seo_ctx );
}, 1 );

remove_action( 'wp_head', '_wp_render_title_tag', 1 );
add_filter( 'pre_get_document_title', function () use ( $seo_ctx ) {
    return $seo_ctx['title'];
} );

/* ============================================================
 * 4. 麵包屑 schema
 * ============================================================ */
$breadcrumb_render = function () use ( $canonical ) {
    $crumbs = [
        [ 'name' => '首頁',         'url' => home_url( '/' ) ],
        [ 'name' => '新番表',       'url' => home_url( '/bangumi/' ) ],
        [ 'name' => '歷年存檔',     'url' => $canonical ],
    ];
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
};

get_header();
?>

<?php $breadcrumb_render(); ?>

<section class="bgm-arc-hero">
  <div class="bgm-arc-hero-inner">
    <div class="bgm-arc-hero-badge">
      <i class="fa-solid fa-folder-open" aria-hidden="true"></i> 歷年存檔
    </div>
    <h1 class="bgm-arc-hero-title">所有季度番組表</h1>
    <p class="bgm-arc-hero-sub">
      共 <strong><?php echo (int) $total_years; ?></strong> 個年份、
      <strong><?php echo (int) $total_anime; ?></strong> 部作品
    </p>
    <nav class="bgm-arc-back">
      <a href="<?php echo esc_url( home_url( '/bangumi/' ) ); ?>">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> 回本季
      </a>
    </nav>
  </div>
</section>

<main class="bgm-arc-main">

<?php if ( empty( $by_year ) ): ?>
  <div class="bgm-arc-empty">
    <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
    <p>尚無歷史資料。</p>
  </div>
<?php else: ?>

  <div class="bgm-arc-list">
    <?php foreach ( $by_year as $year => $seasons ):
      $year_total = array_sum( $seasons );
    ?>
    <section class="bgm-arc-year" data-year="<?php echo (int) $year; ?>">
      <header class="bgm-arc-year-head">
        <h2 class="bgm-arc-year-title">
          <span class="bgm-arc-year-num"><?php echo (int) $year; ?></span>
          <span class="bgm-arc-year-suffix">年</span>
        </h2>
        <span class="bgm-arc-year-count"><?php echo (int) $year_total; ?> 部</span>
      </header>

      <div class="bgm-arc-seasons">
        <?php foreach ( $season_order as $s ):
          $cnt   = $seasons[ $s ] ?? 0;
          $meta  = $season_meta[ $s ];
          $url   = home_url( "/bangumi/{$year}{$meta['month']}/" );
          $ratio = ( $max_cnt > 0 && $cnt > 0 ) ? min( 1, $cnt / $max_cnt ) : 0;
          $alpha = $cnt > 0 ? ( 0.18 + $ratio * 0.55 ) : 0.04;
        ?>
        <a class="bgm-arc-season<?php echo $cnt === 0 ? ' is-empty' : ''; ?>"
           href="<?php echo esc_url( $url ); ?>"
           style="--s-color: <?php echo esc_attr( $meta['color'] ); ?>; --s-alpha: <?php echo esc_attr( (string) $alpha ); ?>;"
           data-count="<?php echo (int) $cnt; ?>">
          <span class="bgm-arc-season-icon" aria-hidden="true"><?php echo $meta['icon']; ?></span>
          <span class="bgm-arc-season-name"><?php echo esc_html( $meta['zh'] ); ?>季</span>
          <span class="bgm-arc-season-cnt"><?php echo (int) $cnt; ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

</main>

<?php get_footer(); ?>
