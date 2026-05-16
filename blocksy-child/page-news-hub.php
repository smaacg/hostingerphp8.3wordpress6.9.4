<?php
/**
 * Template Name: 新聞中心（News Hub）
 *
 * 路徑：blocksy-child/page-news-hub.php
 * 用途：在後台建立一個 Page（slug = news），套用此範本，
 *      訪問 /news/ 即可看到與 /category/news/ 相同版面但獨立網址。
 *
 * @version 1.0.0
 * @since   2026-05-16
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// ── 鎖定為 news 分類 ──────────────────────────────
$current_term = get_category_by_slug( 'news' );

if ( ! $current_term ) {
    echo '<div class="container" style="padding:60px 0;text-align:center;">';
    echo '<h2>找不到「news」分類</h2>';
    echo '<p>請先在後台建立 slug 為 <code>news</code> 的分類。</p>';
    echo '</div>';
    get_footer();
    return;
}

$current_tax    = 'category';
$current_termid = $current_term->term_id;
$current_slug   = $current_term->slug;

// ── 頁面標題（沿用 category.php 設定）──
$hero_title    = '最新動漫資訊';
$hero_subtitle = '聲優消息・新番公告・活動報導・業界動態，每日更新';
$hero_badge    = $current_term->name;

// ── 分頁參數（支援 /news/page/2/）──
$paged = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );

// ── 共用 tax_query ──
$archive_tax_query = [
    [
        'taxonomy' => $current_tax,
        'field'    => 'term_id',
        'terms'    => $current_termid,
    ],
];

// ── 輪播：最新 5 篇 ──
$carousel_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'tax_query'           => $archive_tax_query,
] );

// ── 熱門：留言數最多 5 篇 ──
$popular_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'orderby'             => 'comment_count',
    'order'               => 'DESC',
    'tax_query'           => $archive_tax_query,
] );

// ── 主要文章列表（手動 query 取代主迴圈）──
$main_query = new WP_Query( [
    'post_type'      => 'post',
    'posts_per_page' => (int) get_option( 'posts_per_page', 10 ),
    'post_status'    => 'publish',
    'paged'          => $paged,
    'tax_query'      => $archive_tax_query,
] );

// ── 熱門標籤 ──
$popular_tags = get_tags( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 15,
    'hide_empty' => true,
] );

// ── Filter Tabs：列出所有 channel ──
$filter_label   = '頻道';
$filter_terms   = get_terms( [ 'taxonomy' => 'channel', 'hide_empty' => true ] );
$filter_all_url = get_permalink(); // 指回本頁
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/css/news.css' ); ?>" />

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

  <!-- ── Filter Tabs ── -->
  <?php if ( ! empty( $filter_terms ) ) : ?>
  <div class="news-filter">
    <a href="<?php echo esc_url( $filter_all_url ); ?>" class="news-filter-btn active">全部</a>
    <?php foreach ( $filter_terms as $t ) :
      // 在 /news/ 頁，列出頻道：連到 /news/{channel}/
      $tab_url = home_url( '/' . $current_slug . '/' . $t->slug . '/' );
    ?>
      <a href="<?php echo esc_url( $tab_url ); ?>" class="news-filter-btn">
        <?php echo esc_html( $t->name ); ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="news-layout">

    <!-- ── 主要新聞區 ── -->
    <div class="news-main-grid">

      <?php if ( $main_query->have_posts() ) : ?>
        <div class="news-card-list">
          <?php while ( $main_query->have_posts() ) : $main_query->the_post();
            $cats      = get_the_category();
            $cat_label = ! empty( $cats ) ? esc_html( $cats[0]->name ) : '文章';
          ?>
          <a href="<?php the_permalink(); ?>" class="news-card glass">
            <div class="news-card-img">
              <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'medium', [ 'alt' => get_the_title(), 'loading' => 'lazy' ] ); ?>
              <?php else :
                preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', get_the_content(), $cm );
                if ( ! empty( $cm[1] ) ) : ?>
                  <img src="<?php echo esc_url( $cm[1] ); ?>"
                       alt="<?php echo esc_attr( get_the_title() ); ?>"
                       loading="lazy" />
                <?php else : ?>
                  <span class="news-card-placeholder">📰</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="news-card-body">
              <div class="news-card-tag"><?php echo $cat_label; ?></div>
              <div class="news-card-title"><?php the_title(); ?></div>
              <div class="news-card-meta">
                <i class="fa-regular fa-clock"></i> <?php echo get_the_date( 'Y-m-d' ); ?>
              </div>
            </div>
          </a>
          <?php endwhile; ?>
        </div>

        <!-- ── 分頁（手動 query 用 paginate_links）── -->
        <div class="news-pagination">
          <?php
          $big = 999999999;
          echo paginate_links( [
              'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
              'format'    => '?paged=%#%',
              'current'   => $paged,
              'total'     => $main_query->max_num_pages,
              'prev_text' => '<i class="fa-solid fa-chevron-left"></i>',
              'next_text' => '<i class="fa-solid fa-chevron-right"></i>',
              'end_size'  => 2,
              'mid_size'  => 1,
          ] );
          ?>
        </div>

        <?php wp_reset_postdata(); ?>

      <?php else : ?>
        <div class="news-empty glass">
          <i class="fa-regular fa-newspaper"></i>
          <p>目前沒有文章，請稍後再來查看。</p>
        </div>
      <?php endif; ?>

    </div>

    <!-- ── 側欄 ── -->
    <aside class="news-sidebar">

      <!-- 熱門文章 -->
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
