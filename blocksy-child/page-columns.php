<?php
/**
 * Template Name: 專欄頁面
 * 用途：顯示 review（評論）+ feature（專題）混合內容
 * @version 2.0.0  2026-05-10 修正巢狀 <a> 跑版問題
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// ── 抓最新評論 6 篇 ──
$review_query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 6,
    'category_name'  => 'review',
    'post_status'    => 'publish',
    'no_found_rows'  => true,
]);

// ── 抓最新專題 6 篇 ──
$feature_query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 6,
    'category_name'  => 'feature',
    'post_status'    => 'publish',
    'no_found_rows'  => true,
]);

/**
 * 渲染單張卡片（共用）
 * 重點：避免巢狀 <a>，分類標籤獨立於主連結之外
 */
function asd_render_column_card() {
    $post_id     = get_the_ID();
    $permalink   = get_permalink();
    $title       = get_the_title();
    $date        = get_the_date();
    $excerpt     = wp_trim_words( get_the_excerpt(), 30, '…' );
    $thumb_html  = has_post_thumbnail()
        ? get_the_post_thumbnail( $post_id, 'medium', [ 'loading' => 'lazy', 'alt' => esc_attr( $title ) ] )
        : '<div class="card-thumb-placeholder" aria-hidden="true">🎬</div>';

    // 取得 channel 分類字串（不直接 echo，避免巢狀 a）
    $channels = get_the_term_list( $post_id, 'channel', '', ' · ', '' );
    if ( is_wp_error( $channels ) ) {
        $channels = '';
    }
    ?>
    <article class="column-card">
        <?php /* 圖片區：獨立 <a>，可點 */ ?>
        <a href="<?php echo esc_url( $permalink ); ?>" class="card-thumb-link" aria-label="<?php echo esc_attr( $title ); ?>">
            <div class="card-thumb">
                <?php echo $thumb_html; ?>
            </div>
        </a>

        <div class="card-info">
            <?php if ( ! empty( $channels ) ) : ?>
                <div class="card-channels"><?php echo $channels; ?></div>
            <?php endif; ?>

            <h3 class="card-title">
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
            </h3>

            <div class="card-meta">
                <span class="date"><?php echo esc_html( $date ); ?></span>
            </div>

            <?php if ( $excerpt ) : ?>
                <div class="card-excerpt"><?php echo esc_html( $excerpt ); ?></div>
            <?php endif; ?>
        </div>
    </article>
    <?php
}
?>

<div class="columns-page">
    <header class="columns-page-header">
        <h1 class="page-title">🔍 專欄</h1>
        <p class="page-desc">深度評論與精選專題，帶你看見動漫世界的不同角度</p>
    </header>

    <!-- ── 評論區塊 ── -->
    <section class="columns-section review-section">
        <header class="section-header">
            <h2>📝 評論</h2>
            <a href="<?php echo esc_url( home_url( '/review/' ) ); ?>" class="more-link">查看全部 →</a>
        </header>

        <?php if ( $review_query->have_posts() ) : ?>
            <div class="columns-grid">
                <?php while ( $review_query->have_posts() ) : $review_query->the_post();
                    asd_render_column_card();
                endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <p class="empty-msg">尚未有評論文章</p>
        <?php endif; ?>
    </section>

    <!-- ── 專題區塊 ── -->
    <section class="columns-section feature-section">
        <header class="section-header">
            <h2>📚 專題</h2>
            <a href="<?php echo esc_url( home_url( '/feature/' ) ); ?>" class="more-link">查看全部 →</a>
        </header>

        <?php if ( $feature_query->have_posts() ) : ?>
            <div class="columns-grid">
                <?php while ( $feature_query->have_posts() ) : $feature_query->the_post();
                    asd_render_column_card();
                endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <p class="empty-msg">尚未有專題文章</p>
        <?php endif; ?>
    </section>
</div>

<?php get_footer(); ?>
