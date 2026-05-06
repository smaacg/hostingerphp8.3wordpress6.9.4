<?php
/**
 * Search Results — 微笑動漫 weixiaoacg
 *
 * @package weixiaoacg
 */
get_header();

$search_query = get_search_query();
$total        = $GLOBALS['wp_query']->found_posts;
?>

<main class="search-page">

  <!-- ── Hero 搜尋欄 ── -->
  <section class="search-hero">
    <div class="container">
      <p class="search-hero-label">
        <?php if ( $search_query ) : ?>
          共找到 <strong><?php echo number_format( $total ); ?></strong> 筆關於
          「<span class="search-hero-kw"><?php echo esc_html( $search_query ); ?></span>」的結果
        <?php else : ?>
          請輸入搜尋關鍵字
        <?php endif; ?>
      </p>

      <form role="search" method="get" action="<?php echo esc_url( home_url('/') ); ?>" class="search-hero-form">
        <div class="search-hero-box">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <input
            type="search"
            name="s"
            class="search-hero-input"
            value="<?php echo esc_attr( $search_query ); ?>"
            placeholder="搜尋動漫、角色、新聞…"
            autocomplete="off"
            aria-label="搜尋"
            autofocus
          >
          <?php if ( $search_query ) : ?>
            <a href="<?php echo esc_url( home_url('/') ); ?>" class="search-hero-clear" title="清除搜尋" aria-label="清除搜尋">
              <i class="fa-solid fa-xmark"></i>
            </a>
          <?php endif; ?>
          <button type="submit" class="search-hero-submit">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <span>搜尋</span>
          </button>
        </div>
      </form>
    </div>
  </section>

  <!-- ── 搜尋結果 ── -->
  <div class="container search-body">

    <?php if ( have_posts() ) : ?>

      <div class="search-results-grid">
        <?php while ( have_posts() ) : the_post(); ?>

          <article class="search-card" id="post-<?php the_ID(); ?>">

            <!-- 縮圖 -->
            <a href="<?php the_permalink(); ?>" class="search-card-thumb" tabindex="-1" aria-hidden="true">
              <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'medium', [ 'class' => 'search-card-img', 'loading' => 'lazy', 'alt' => get_the_title() ] ); ?>
              <?php else : ?>
                <div class="search-card-img-ph">
                  <i class="fa-solid fa-image" aria-hidden="true"></i>
                </div>
              <?php endif; ?>
            </a>

            <!-- 內容 -->
            <div class="search-card-body">

              <!-- 分類 -->
              <?php
              $cats = get_the_category();
              if ( $cats ) :
                $cat = $cats[0];
              ?>
                <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"
                   class="search-card-cat news-tag tag-blue">
                  <?php echo esc_html( $cat->name ); ?>
                </a>
              <?php endif; ?>

              <!-- 標題 -->
              <h2 class="search-card-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h2>

              <!-- 摘要 -->
              <p class="search-card-excerpt">
                <?php echo wp_trim_words( get_the_excerpt(), 30, '…' ); ?>
              </p>

              <!-- Meta -->
              <div class="search-card-meta">
                <span>
                  <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                  <?php echo get_the_date('Y年m月d日'); ?>
                </span>
                <span>
                  <i class="fa-regular fa-user" aria-hidden="true"></i>
                  <?php the_author(); ?>
                </span>
                <?php if ( comments_open() ) : ?>
                  <span>
                    <i class="fa-regular fa-comment" aria-hidden="true"></i>
                    <?php comments_number('0', '1', '%'); ?>
                  </span>
                <?php endif; ?>
              </div>

            </div><!-- /.search-card-body -->

          </article>

        <?php endwhile; ?>
      </div><!-- /.search-results-grid -->

      <!-- ── 分頁 ── -->
      <?php if ( $total > get_option('posts_per_page') ) : ?>
        <nav class="search-pagination" aria-label="搜尋結果分頁">
          <?php
          echo paginate_links([
            'prev_text' => '<i class="fa-solid fa-chevron-left"></i> 上一頁',
            'next_text' => '下一頁 <i class="fa-solid fa-chevron-right"></i>',
            'type'      => 'list',
          ]);
          ?>
        </nav>
      <?php endif; ?>

    <?php else : ?>

      <!-- ── 無結果 ── -->
      <div class="search-empty">
        <div class="search-empty-icon">🔍</div>
        <h2 class="search-empty-title">找不到相關結果</h2>
        <p class="search-empty-desc">
          沒有找到關於「<strong><?php echo esc_html( $search_query ); ?></strong>」的內容。<br>
          試試看其他關鍵字，或瀏覽以下熱門分類：
        </p>
        <div class="search-empty-links">
          <a href="<?php echo esc_url( home_url('/season/') ); ?>" class="search-empty-btn">
            <i class="fa-solid fa-calendar-days"></i> 新番導覽
          </a>
          <a href="<?php echo esc_url( home_url('/anime/') ); ?>" class="search-empty-btn">
            <i class="fa-solid fa-database"></i> 動漫百科
          </a>
          <a href="<?php echo esc_url( home_url('/ranking/') ); ?>" class="search-empty-btn">
            <i class="fa-solid fa-trophy"></i> 排行榜
          </a>
          <a href="<?php echo esc_url( home_url('/news/') ); ?>" class="search-empty-btn">
            <i class="fa-solid fa-newspaper"></i> 最新新聞
          </a>
        </div>
      </div>

    <?php endif; ?>

  </div><!-- /.container -->

</main>

<?php get_footer(); ?>
