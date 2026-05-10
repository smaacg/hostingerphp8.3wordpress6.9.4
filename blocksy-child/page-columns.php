<?php
/**
 * Template Name: 專欄頁面
 * 用途：顯示 review（評論）+ feature（專題）混合內容
 * @version 2.1.0  2026-05-10 加入圖片 fallback + 深色主題美化
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$review_query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 6,
    'category_name'  => 'review',
    'post_status'    => 'publish',
    'no_found_rows'  => true,
]);

$feature_query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 6,
    'category_name'  => 'feature',
    'post_status'    => 'publish',
    'no_found_rows'  => true,
]);

/**
 * 取得卡片用的封面圖 URL（多層 fallback）
 */
function asd_get_card_thumb_url( $post_id ) {
    // 1. 特色圖片
    if ( has_post_thumbnail( $post_id ) ) {
        $url = get_the_post_thumbnail_url( $post_id, 'medium_large' );
        if ( $url ) return $url;
    }
    // 2. anime_cover_image meta（若文章與 anime CPT 關聯）
    $cover = get_post_meta( $post_id, 'anime_cover_image', true );
    if ( $cover ) return $cover;

    // 3. 文章內第一張圖
    $content = get_post_field( 'post_content', $post_id );
    if ( $content && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m ) ) {
        return $m[1];
    }
    // 4. 無圖
    return '';
}

/**
 * 渲染單張卡片
 */
function asd_render_column_card() {
    $post_id   = get_the_ID();
    $permalink = get_permalink();
    $title     = get_the_title();
    $date      = get_the_date();
    $excerpt   = wp_trim_words( get_the_excerpt(), 30, '…' );
    $thumb_url = asd_get_card_thumb_url( $post_id );

    $channels = get_the_term_list( $post_id, 'channel', '', ' · ', '' );
    if ( is_wp_error( $channels ) ) $channels = '';
    ?>
    <article class="column-card">
        <a href="<?php echo esc_url( $permalink ); ?>" class="card-thumb-link" aria-label="<?php echo esc_attr( $title ); ?>">
            <?php if ( $thumb_url ) : ?>
                <img src="<?php echo esc_url( $thumb_url ); ?>"
                     alt="<?php echo esc_attr( $title ); ?>"
                     class="card-thumb-img"
                     loading="lazy" />
            <?php else : ?>
                <div class="card-thumb-placeholder" aria-hidden="true">
                    <span class="placeholder-icon">🎬</span>
                </div>
            <?php endif; ?>
        </a>

        <div class="card-info">
            <?php if ( ! empty( $channels ) ) : ?>
                <div class="card-channels"><?php echo $channels; ?></div>
            <?php endif; ?>

            <h3 class="card-title">
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
            </h3>

            <div class="card-meta">
                <span class="date">📅 <?php echo esc_html( $date ); ?></span>
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
        <h1 class="page-title"><span class="title-icon">🔍</span>專欄</h1>
        <p class="page-desc">深度評論與精選專題，帶你看見動漫世界的不同角度</p>
    </header>

    <section class="columns-section review-section">
        <header class="section-header">
            <h2 class="section-title"><span class="section-icon">📝</span>評論</h2>
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

    <section class="columns-section feature-section">
        <header class="section-header">
            <h2 class="section-title"><span class="section-icon">📚</span>專題</h2>
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
