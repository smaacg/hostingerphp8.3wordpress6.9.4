<?php
/**
 * Category / Channel Archive Template
 *
 * Path: wp-content/themes/blocksy-child/category.php
 * @version 2.0.0 (2026-05-16)
 *
 * Changelog:
 *   2.0.0 (2026-05-16) AJAX 切換版：
 *     - Filter Tabs 改用 AJAX 切換，不再整頁刷新
 *     - 文章列表本體拆到 template-parts/news-list.php，PHP/AJAX 共用同一份
 *     - 列表外層加 #news-list-root 容器，data-* 給 JS 用
 *     - 直接訪問 URL（含外站連入、SEO）仍走完整 PHP 渲染（漸進增強）
 *     - PHP 端會根據 channel query var 自動點亮對應 tab
 *   1.0.0 初版（玻璃擬態 + Swiper 輪播 + 側欄）
 *
 * 服務 URL：
 *   /news/  /review/  /feature/  /announcement/
 *   /news/anime/  /news/voice-actor/  /review/game/  ...
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// ── 取得目前 archive 的 term ──
$queried        = get_queried_object();
$current_term   = ( $queried instanceof WP_Term ) ? $queried : null;
$current_tax    = $current_term ? $current_term->taxonomy : '';
$current_termid = $current_term ? $current_term->term_id  : 0;
$current_slug   = $current_term ? $current_term->slug     : '';

// ── 取得目前 channel（若有，例如 /news/cosplay/） ──
$current_channel = (string) get_query_var( 'channel' );

// ── 頁面標題 / 副標題（依分類動態顯示） ──
$page_titles = [
    'announcement' => [ '本站公告', '官方訊息・系統公告・重要通知' ],
    'news'         => [ '最新動漫資訊', '聲優消息・新番公告・活動報導・業界動態，每日更新' ],
    'review'       => [ '動漫評論', '深度解析・心得分享・作品評價' ],
    'feature'      => [ '專題報導', '深度專題・年度回顧・主題企劃' ],
];
if ( 'category' === $current_tax && isset( $page_titles[ $current_slug ] ) ) {
    $hero_title    = $page_titles[ $current_slug ][0];
    $hero_subtitle = $page_titles[ $current_slug ][1];
    $hero_badge    = $current_term->name;
} elseif ( $current_term ) {
    $hero_title    = single_term_title( '', false );
    $hero_subtitle = $current_term->description ?: '相關文章列表';
    $hero_badge    = $current_term->name;
} else {
    $hero_title    = '所有文章';
    $hero_subtitle = '';
    $hero_badge    = '文章';
}

// ── 共用：依目前 archive 過濾 tax_query ──
$archive_tax_query = [];
if ( $current_term ) {
    $archive_tax_query[] = [
        'taxonomy' => $current_tax,
        'field'    => 'term_id',
        'terms'    => $current_termid,
    ];
}

// ── 輪播：本分類最新 5 篇 ──
$carousel_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'tax_query'           => $archive_tax_query,
] );

// ── 熱門：本分類留言數最多 5 篇 ──
$popular_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'orderby'             => 'comment_count',
    'order'               => 'DESC',
    'tax_query'           => $archive_tax_query,
] );

// ── 熱門標籤（全站） ──
$popular_tags = get_tags( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 15,
    'hide_empty' => true,
] );

// ── Filter Tabs ──
// 在 category 頁顯示 channel 列表；在 channel 頁顯示 category 列表
$filter_label  = '';
$filter_terms  = [];
$filter_all_url = '';
if ( 'category' === $current_tax ) {
    $filter_label   = '頻道';
    $filter_terms   = get_terms( [ 'taxonomy' => 'channel', 'hide_empty' => true ] );
    $filter_all_url = get_term_link( $current_term );
} elseif ( 'channel' === $current_tax ) {
    $filter_label   = '類型';
    $filter_terms   = get_categories( [
        'slug'       => [ 'announcement', 'news', 'review', 'feature' ],
        'hide_empty' => false,
    ] );
    $filter_all_url = get_term_link( $current_term );
}
?>

<!-- 額外載入本頁專用 CSS / JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/css/news.css' ); ?>" />

<!-- ===== PAGE HERO ===== -->
<div class="page-hero">
  <div class="container">
    <div class="page-badge"><i class="fa-solid fa-newspaper"></i> <?php echo esc_html( $hero_badge ); ?></div>
    <h1 class="page-title"><?php echo esc_html( $hero_title ); ?></h1>
    <?php if ( $hero_subtitle ) : ?>
      <p class="page-subtitle"><?php echo esc_html( $hero_subtitle ); ?></p>
    <?php endif; ?>
  </div>
</div>

<!-- ===== MAIN ===== -->
<main class="container" style="padding: 32px 0 64px;">

  <!-- ── 海報輪播 ── -->
  <?php if ( $carousel_query->have_posts() ) : ?>
  <div class="news-carousel-wrap">
    <div class="swiper news-swiper">
      <div class="swiper-wrapper">
        <?php while ( $carousel_query->have_posts() ) : $carousel_query->the_post();
          $cats      = get_the_category();
          $cat_label = ! empty( $cats ) ? esc_html( $cats[0]->name ) : '最新';

          $carousel_img_url = '';
          if ( has_post_thumbnail() ) {
              $carousel_img_url = get_the_post_thumbnail_url( get_the_ID(), 'large' );
          } else {
              preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', get_the_content(), $m );
              if ( ! empty( $m[1] ) ) $carousel_img_url = $m[1];
          }
        ?>
        <div class="swiper-slide">
          <a href="<?php the_permalink(); ?>" class="swiper-slide-inner">
            <?php if ( $carousel_img_url ) : ?>
              <div class="swiper-slide-bg" style="background-image: url('<?php echo esc_url( $carousel_img_url ); ?>');"></div>
              <img class="carousel-main-img"
                   src="<?php echo esc_url( $carousel_img_url ); ?>"
                   alt="<?php echo esc_attr( get_the_title() ); ?>"
                   loading="lazy" />
            <?php else : ?>
              <div class="carousel-no-img">📰</div>
            <?php endif; ?>

            <div class="swiper-slide-caption">
              <div class="swiper-slide-tag"><?php echo $cat_label; ?></div>
              <div class="swiper-slide-title"><?php the_title(); ?></div>
              <div class="swiper-slide-meta">
                <i class="fa-regular fa-clock"></i> <?php echo get_the_date( 'Y-m-d' ); ?>
                &nbsp;·&nbsp;
                <i class="fa-regular fa-user"></i> <?php the_author(); ?>
              </div>
            </div>
          </a>
        </div>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
      <div class="swiper-pagination"></div>
      <div class="swiper-button-prev"></div>
      <div class="swiper-button-next"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Filter Tabs（頻道 / 類型切換，AJAX） ── -->
  <?php if ( ! empty( $filter_terms ) ) : ?>
  <div class="news-filter" id="news-filter-bar"
       data-base-slug="<?php echo esc_attr( $current_slug ); ?>"
       data-base-tax="<?php echo esc_attr( $current_tax ); ?>">

    <a href="<?php echo esc_url( $filter_all_url ); ?>"
       class="news-filter-btn<?php echo $current_channel === '' ? ' active' : ''; ?>"
       data-ajax="1"
       data-target="all">全部</a>

    <?php foreach ( $filter_terms as $t ) :
        if ( 'category' === $current_tax ) {
            $tab_url    = home_url( '/' . $current_slug . '/' . $t->slug . '/' );
            $target_val = $t->slug;
            $is_active  = ( $current_channel === $t->slug );
        } else {
            $tab_url    = home_url( '/' . $t->slug . '/' . $current_slug . '/' );
            $target_val = $t->slug;
            $is_active  = false; // channel 頁的 active 邏輯保留給未來擴充
        }
    ?>
      <a href="<?php echo esc_url( $tab_url ); ?>"
         class="news-filter-btn<?php echo $is_active ? ' active' : ''; ?>"
         data-ajax="1"
         data-target="<?php echo esc_attr( $target_val ); ?>">
        <?php echo esc_html( $t->name ); ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="news-layout">

    <!-- ── 主要新聞區（AJAX 切換目標容器） ── -->
    <div class="news-main-grid"
         id="news-list-root"
         data-content-type="<?php echo esc_attr( $current_slug ); ?>"
         data-channel="<?php echo esc_attr( $current_channel ); ?>">

      <?php
      // 列表本體拆成 partial，AJAX 也回傳同一份內容
      set_query_var( 'news_main_query', $wp_query );
      get_template_part( 'template-parts/news-list' );
      ?>

    </div>

    <!-- ── 側欄 ── -->
    <aside class="news-sidebar">

      <!-- 熱門新聞 -->
      <div class="sidebar-widget glass">
        <div class="sidebar-widget-title">
          <i class="fa-solid fa-fire" style="color:#f97316;"></i> 熱門文章
        </div>
        <?php if ( $popular_query->have_posts() ) :
          $pop_i = 0;
          while ( $popular_query->have_posts() ) : $popular_query->the_post();
            $pop_i++;
            $is_top = $pop_i <= 3 ? 'top-3' : '';
        ?>
        <a href="<?php the_permalink(); ?>" class="sidebar-list-item">
          <div class="sidebar-item-num <?php echo $is_top; ?>"><?php echo $pop_i; ?></div>
          <div>
            <div class="sidebar-item-title"><?php the_title(); ?></div>
            <div class="sidebar-item-date">
              <?php echo human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ); ?>前
            </div>
          </div>
        </a>
        <?php endwhile; wp_reset_postdata(); ?>
        <?php endif; ?>
      </div>

      <!-- 熱門標籤 -->
      <?php if ( ! empty( $popular_tags ) ) : ?>
      <div class="sidebar-widget glass">
        <div class="sidebar-widget-title">
          <i class="fa-solid fa-tags" style="color:var(--accent-blue);"></i> 熱門標籤
        </div>
        <div class="tag-cloud">
          <?php foreach ( $popular_tags as $tag ) : ?>
          <a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="tag-pill">
            #<?php echo esc_html( $tag->name ); ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- 訂閱快報 -->
      <div class="sidebar-widget glass subscribe-box">
        <div class="sidebar-widget-title">
          <i class="fa-solid fa-bell" style="color:var(--accent-blue);"></i> 訂閱快報
        </div>
        <p class="subscribe-desc">每週精選重要動漫資訊，直送你的信箱</p>
        <input type="email" class="subscribe-input" placeholder="your@email.com" />
        <button class="btn btn-primary subscribe-btn">訂閱</button>
      </div>

    </aside>
  </div>
</main>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
(function () {
  'use strict';
  if ( document.querySelector('.news-swiper') ) {
    new Swiper('.news-swiper', {
      loop:       true,
      autoplay:   { delay: 5000, disableOnInteraction: false },
      speed:      700,
      effect:     'fade',
      fadeEffect: { crossFade: true },
      pagination: { el: '.swiper-pagination', clickable: true },
      navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
      a11y:       { enabled: true },
    });
  }
})();
</script>

<?php
get_footer();
