<?php
/**
 * Archive Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/archive-anime.php
 *
 * ACG v2 – 修正毛玻璃效果：改用 .aaa-wrap::before 偽元素背景，繞過 Elementor body 覆蓋
 *          卡片背景加深，確保 backdrop-filter 有視覺對比
 *          強制 .aaa-wrap 文字顏色，避免主題干擾
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

/* ── 頁面資訊 ─────────────────────────────────────────────── */
$is_archive   = is_post_type_archive( 'anime' );
$is_genre     = is_tax( 'genre' );
$is_season    = is_tax( 'anime_season_tax' );
$is_format    = is_tax( 'anime_format_tax' );
$is_search    = is_search() && get_query_var( 'post_type' ) === 'anime';
$current_term = ( $is_genre || $is_season || $is_format ) ? get_queried_object() : null;

$archive_title = '動漫列表';
$archive_desc  = '';
if ( $is_search ) {
    $archive_title = '搜尋結果：' . get_search_query();
} elseif ( $current_term ) {
    $archive_title = $current_term->name;
    $archive_desc  = term_description( $current_term->term_id );
}

$total_posts  = (int) $GLOBALS['wp_query']->found_posts;
$current_page = max( 1, get_query_var( 'paged' ) );

/* ── 當前篩選狀態 ────────────────────────────────────────── */
$active_genre  = $is_genre  ? $current_term->slug : '';
$active_season = $is_season ? $current_term->slug : '';
$active_format = $is_format ? $current_term->slug : '';

/* ── 抓取 Taxonomy 選項 ───────────────────────────────────── */
$season_terms = get_terms( [
    'taxonomy'   => 'anime_season_tax',
    'orderby'    => 'slug',
    'order'      => 'DESC',
    'hide_empty' => true,
    'parent'     => 0,
    'number'     => 6,
] );

$season_children = [];
if ( ! is_wp_error( $season_terms ) ) {
    foreach ( $season_terms as $year_term ) {
        $children = get_terms( [
            'taxonomy'   => 'anime_season_tax',
            'parent'     => $year_term->term_id,
            'orderby'    => 'slug',
            'order'      => 'DESC',
            'hide_empty' => true,
        ] );
        if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
            $season_children[ $year_term->slug ] = $children;
        }
    }
}

$format_terms = get_terms( [
    'taxonomy'   => 'anime_format_tax',
    'orderby'    => 'count',
    'order'      => 'DESC',
    'hide_empty' => true,
] );

$genre_terms = get_terms( [
    'taxonomy'   => 'genre',
    'orderby'    => 'count',
    'order'      => 'DESC',
    'hide_empty' => true,
    'number'     => 20,
] );

/* ── Schema：canonical URL ───────────────────────────────── */
$canonical_url = ( $is_genre || $is_season || $is_format ) && $current_term
    ? get_term_link( $current_term )
    : get_post_type_archive_link( 'anime' );

/* ── Schema：CollectionPage ──────────────────────────────── */
$schema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'CollectionPage',
    'name'        => $archive_title . ' | 動漫資料庫',
    'description' => $archive_desc
        ? wp_strip_all_tags( $archive_desc )
        : '收錄所有動漫資訊，包含評分、季度、類型、聲優等完整資料。',
    'url'         => $canonical_url,
];

/* ── Schema：麵包屑 ───────────────────────────────────────── */
$breadcrumb_items = [
    [ '@type' => 'ListItem', 'position' => 1, 'name' => '首頁',     'item' => home_url( '/' ) ],
    [ '@type' => 'ListItem', 'position' => 2, 'name' => '動漫列表', 'item' => home_url( '/anime/' ) ],
];
if ( $current_term ) {
    $breadcrumb_items[] = [
        '@type'    => 'ListItem',
        'position' => 3,
        'name'     => $current_term->name,
        'item'     => get_term_link( $current_term ),
    ];
} elseif ( $is_search ) {
    $breadcrumb_items[] = [
        '@type'    => 'ListItem',
        'position' => 3,
        'name'     => '搜尋：' . get_search_query(),
        'item'     => add_query_arg( [ 's' => get_search_query(), 'post_type' => 'anime' ], home_url( '/' ) ),
    ];
}
$breadcrumb_schema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => $breadcrumb_items,
];
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<div class="aaa-wrap">

    <?php /* ── 麵包屑 ─────────────────────────────────────── */ ?>
    <nav class="aaa-breadcrumb" aria-label="麵包屑導航">
        <ol>
            <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">首頁</a></li>
            <li><a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>">動漫列表</a></li>
            <?php if ( $current_term ) : ?>
                <li><?php echo esc_html( $current_term->name ); ?></li>
            <?php elseif ( $is_search ) : ?>
                <li>搜尋：<?php echo esc_html( get_search_query() ); ?></li>
            <?php endif; ?>
        </ol>
    </nav>

    <?php /* ── 頁首 ───────────────────────────────────────── */ ?>
    <div class="aaa-header">
        <h1 class="aaa-title"><?php echo esc_html( $archive_title ); ?></h1>
        <?php if ( $archive_desc ) : ?>
            <p class="aaa-desc"><?php echo wp_kses_post( $archive_desc ); ?></p>
        <?php endif; ?>
        <p class="aaa-count">共 <strong><?php echo esc_html( $total_posts ); ?></strong> 部動漫</p>
    </div>

    <?php /* ── 搜尋框 ─────────────────────────────────────── */ ?>
    <div class="aaa-search-wrap">
        <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="aaa-search-form">
            <input type="hidden" name="post_type" value="anime">
            <div class="aaa-search-inner">
                <span class="aaa-search-icon">🔍</span>
                <input
                    type="search"
                    name="s"
                    class="aaa-search-input"
                    placeholder="搜尋動漫名稱（中文、日文、英文）…"
                    value="<?php echo esc_attr( get_search_query() ); ?>"
                    autocomplete="off"
                >
                <button type="submit" class="aaa-search-btn">搜尋</button>
            </div>
        </form>
    </div>

    <?php /* ── 篩選列（搜尋頁不顯示篩選）────────────────── */ ?>
    <?php if ( ! $is_search ) : ?>
    <div class="aaa-filter-wrap">

        <?php /* 季度篩選 */ ?>
        <div class="aaa-filter-group">
            <div class="aaa-filter-label">📅 播出季度</div>
            <div class="aaa-filter-row">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'anime' ) ); ?>"
                   class="aaa-filter-btn <?php echo ( $is_archive && ! $active_season ) ? 'active' : ''; ?>">全部</a>
                <?php foreach ( $season_children as $year_slug => $children ) : ?>
                    <?php foreach ( $children as $child ) : ?>
                        <a href="<?php echo esc_url( get_term_link( $child ) ); ?>"
                           class="aaa-filter-btn <?php echo ( $active_season === $child->slug ) ? 'active' : ''; ?>">
                            <?php echo esc_html( $child->name ); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <?php /* 格式篩選 */ ?>
        <?php if ( ! is_wp_error( $format_terms ) && $format_terms ) : ?>
        <div class="aaa-filter-group">
            <div class="aaa-filter-label">🎬 動漫格式</div>
            <div class="aaa-filter-row">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'anime' ) ); ?>"
                   class="aaa-filter-btn <?php echo ( $is_archive && ! $active_format ) ? 'active' : ''; ?>">全部</a>
                <?php foreach ( $format_terms as $fmt ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $fmt ) ); ?>"
                       class="aaa-filter-btn <?php echo ( $active_format === $fmt->slug ) ? 'active' : ''; ?>">
                        <?php echo esc_html( $fmt->name ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php /* 類型篩選 */ ?>
        <?php if ( ! is_wp_error( $genre_terms ) && $genre_terms ) : ?>
        <div class="aaa-filter-group">
            <div class="aaa-filter-label">🏷️ 動漫類型</div>
            <div class="aaa-filter-row">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'anime' ) ); ?>"
                   class="aaa-filter-btn <?php echo ( $is_archive && ! $active_genre ) ? 'active' : ''; ?>">全部</a>
                <?php foreach ( $genre_terms as $gn ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $gn ) ); ?>"
                       class="aaa-filter-btn <?php echo ( $active_genre === $gn->slug ) ? 'active' : ''; ?>">
                        <?php echo esc_html( $gn->name ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php /* ── 動漫卡片網格 ──────────────────────────────── */ ?>
    <?php
    $season_labels = [
        'WINTER' => '冬季', 'SPRING' => '春季',
        'SUMMER' => '夏季', 'FALL'   => '秋季',
    ];
    $format_labels = [
        'TV'       => 'TV',      'TV_SHORT' => 'TV短篇', 'MOVIE'   => '劇場版',
        'OVA'      => 'OVA',     'ONA'      => 'ONA',    'SPECIAL' => '特別篇',
        'MUSIC'    => 'MV',
    ];
    $status_labels = [
        'FINISHED'         => '已完結',
        'RELEASING'        => '連載中',
        'NOT_YET_RELEASED' => '尚未播出',
        'CANCELLED'        => '已取消',
        'HIATUS'           => '暫停中',
    ];
    $status_classes = [
        'FINISHED'         => 's-fin',
        'RELEASING'        => 's-rel',
        'NOT_YET_RELEASED' => 's-pre',
        'CANCELLED'        => 's-can',
        'HIATUS'           => 's-hia',
    ];
    ?>

    <?php if ( have_posts() ) : ?>
    <div class="aaa-grid" id="aaa-grid">
    <?php while ( have_posts() ) : the_post();
        $pid        = get_the_ID();
        $cover      = get_post_meta( $pid, 'anime_cover_image',   true )
                   ?: get_the_post_thumbnail_url( $pid, 'medium' );
        $title_zh   = get_post_meta( $pid, 'anime_title_chinese', true ) ?: get_the_title();
        $title_ro   = get_post_meta( $pid, 'anime_title_romaji',  true );
        $score_raw  = get_post_meta( $pid, 'anime_score_anilist', true );
        $score      = ( is_numeric( $score_raw ) && (float) $score_raw > 0 )
            ? number_format( (float) $score_raw / 10, 1 ) : '';
        $season     = get_post_meta( $pid, 'anime_season',        true );
        $year       = (int) get_post_meta( $pid, 'anime_season_year', true );
        $format     = get_post_meta( $pid, 'anime_format',        true );
        $status     = get_post_meta( $pid, 'anime_status',        true );
        $episodes   = (int) get_post_meta( $pid, 'anime_episodes',   true );
        $popularity = (int) get_post_meta( $pid, 'anime_popularity',  true );

        $season_label = $season_labels[ strtoupper( $season ) ] ?? '';
        $format_label = $format_labels[ $format ] ?? $format;
        $status_label = $status_labels[ $status ] ?? '';
        $status_class = $status_classes[ $status ] ?? '';
        $season_str   = ( $year && $season_label ) ? $year . ' ' . $season_label : ( $year ?: '' );
    ?>
        <article class="aaa-card">
            <a href="<?php the_permalink(); ?>" class="aaa-card-link">
                <div class="aaa-card-cover-wrap">
                    <?php if ( $cover ) : ?>
                        <img class="aaa-card-cover"
                             src="<?php echo esc_url( $cover ); ?>"
                             alt="<?php echo esc_attr( $title_zh ); ?> 封面圖"
                             loading="lazy">
                    <?php else : ?>
                        <div class="aaa-card-cover aaa-no-cover">無封面</div>
                    <?php endif; ?>

                    <?php if ( $status_label ) : ?>
                        <span class="aaa-status-badge <?php echo esc_attr( $status_class ); ?>">
                            <?php echo esc_html( $status_label ); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ( $score ) : ?>
                        <span class="aaa-score-badge">⭐ <?php echo esc_html( $score ); ?></span>
                    <?php endif; ?>
                </div>

                <div class="aaa-card-body">
                    <h3 class="aaa-card-title"><?php echo esc_html( $title_zh ); ?></h3>
                    <?php if ( $title_ro ) : ?>
                        <p class="aaa-card-romaji"><?php echo esc_html( $title_ro ); ?></p>
                    <?php endif; ?>

                    <div class="aaa-card-meta">
                        <?php if ( $format_label ) : ?>
                            <span class="aaa-meta-tag aaa-meta-format"><?php echo esc_html( $format_label ); ?></span>
                        <?php endif; ?>
                        <?php if ( $season_str ) : ?>
                            <span class="aaa-meta-tag aaa-meta-season"><?php echo esc_html( $season_str ); ?></span>
                        <?php endif; ?>
                        <?php if ( $episodes ) : ?>
                            <span class="aaa-meta-tag aaa-meta-ep"><?php echo esc_html( $episodes ); ?> 集</span>
                        <?php endif; ?>
                    </div>

                    <?php if ( $popularity ) : ?>
                        <div class="aaa-card-pop">👥 <?php echo esc_html( number_format( $popularity ) ); ?></div>
                    <?php endif; ?>
                </div>
            </a>
        </article>
    <?php endwhile; ?>
    </div>

    <?php /* ── 分頁 ───────────────────────────────────────── */ ?>
    <nav class="aaa-pagination" aria-label="分頁導航">
        <?php echo paginate_links( [
            'prev_text' => '← 上一頁',
            'next_text' => '下一頁 →',
            'mid_size'  => 2,
            'type'      => 'list',
        ] ); ?>
    </nav>

    <?php /* ── SEO 底部內部連結 ────────────────────────────── */ ?>
    <?php if ( ! $is_search ) : ?>
    <div class="aaa-seo-footer">
        <?php if ( ! is_wp_error( $genre_terms ) && $genre_terms ) : ?>
        <div class="aaa-seo-row">
            <span class="aaa-seo-label">動漫類型：</span>
            <?php foreach ( $genre_terms as $g ) : ?>
                <a href="<?php echo esc_url( get_term_link( $g ) ); ?>" class="aaa-seo-tag">
                    <?php echo esc_html( $g->name ); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ( ! is_wp_error( $format_terms ) && $format_terms ) : ?>
        <div class="aaa-seo-row">
            <span class="aaa-seo-label">動漫格式：</span>
            <?php foreach ( $format_terms as $f ) : ?>
                <a href="<?php echo esc_url( get_term_link( $f ) ); ?>" class="aaa-seo-tag">
                    <?php echo esc_html( $f->name ); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="aaa-empty">
        <?php if ( $is_search ) : ?>
            <p>找不到「<?php echo esc_html( get_search_query() ); ?>」的相關動漫</p>
            <a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>" class="aaa-import-btn">回到動漫列表</a>
        <?php else : ?>
            <p>目前沒有動漫資料</p>
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-import' ) ); ?>"
                   class="aaa-import-btn">前往匯入動漫</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- .aaa-wrap -->

<style>
/* ============================================================
   ACG v2：CSS 變數（對齊 anime-single.css 設計語言，獨立定義避免依賴）
============================================================ */
.aaa-wrap {
    --aaa-primary:       #7c5cff;
    --aaa-primary-2:     #4cc9f0;
    --aaa-accent:        #ff78c8;
    --aaa-success:       #34d399;
    --aaa-warning:       #fbbf24;

    --aaa-bg:            #07111f;
    --aaa-bg-2:          #0a1730;
    --aaa-bg-3:          #10213f;

    --aaa-text:          #f7f9ff;
    --aaa-text-soft:     rgba(247,249,255,0.82);
    --aaa-text-muted:    rgba(247,249,255,0.62);
    --aaa-text-faint:    rgba(247,249,255,0.42);

    --aaa-border:        rgba(255,255,255,0.10);
    --aaa-border-strong: rgba(255,255,255,0.18);

    --aaa-surface:       rgba(10,20,40,0.55);
    --aaa-surface-2:     rgba(10,20,40,0.70);
    --aaa-surface-3:     rgba(10,20,40,0.82);

    --aaa-blur:          blur(16px);
    --aaa-radius-sm:     10px;
    --aaa-radius-md:     18px;
    --aaa-radius-lg:     24px;
    --aaa-radius-pill:   999px;

    --aaa-shadow-sm:     0 8px 24px rgba(0,0,0,0.28);
    --aaa-shadow-md:     0 16px 40px rgba(0,0,0,0.38);
    --aaa-shadow-lg:     0 24px 64px rgba(0,0,0,0.50);

    --aaa-transition:    .25s ease;

    /* 強制文字顏色，避免 Elementor / 主題覆蓋 */
    color: #f7f9ff;
    position: relative;
}

/* ============================================================
   ACG v2：背景光暈 — 用 ::before 偽元素 position:fixed
   完全繞過 Elementor 的 body / #page 背景設定
============================================================ */
.aaa-wrap::before {
    content: '';
    position: fixed;
    inset: 0;
    z-index: -1;
    background:
        radial-gradient(circle at 15% 10%,  rgba(124,92,255,0.22), transparent 32%),
        radial-gradient(circle at 85% 8%,   rgba(76,201,240,0.16), transparent 28%),
        radial-gradient(circle at 50% 90%,  rgba(124,92,255,0.10), transparent 40%),
        linear-gradient(180deg, #07111f 0%, #091426 45%, #0a1730 100%);
    pointer-events: none;
}

/* ============================================================
   容器
============================================================ */
.aaa-wrap {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 20px 80px;
    box-sizing: border-box;
}

.aaa-wrap *,
.aaa-wrap *::before,
.aaa-wrap *::after {
    box-sizing: border-box;
}

/* ============================================================
   麵包屑（glass pill）
============================================================ */
.aaa-breadcrumb {
    margin: 20px 0 0;
    padding: 12px 20px;
    border-radius: var(--aaa-radius-pill);
    background: var(--aaa-surface);
    border: 1px solid var(--aaa-border);
    backdrop-filter: var(--aaa-blur);
    -webkit-backdrop-filter: var(--aaa-blur);
    display: inline-block;
}

.aaa-breadcrumb ol {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 0;
    font-size: 13px;
    color: var(--aaa-text-muted);
    align-items: center;
}

.aaa-breadcrumb li + li::before {
    content: '/';
    margin-right: 6px;
    color: var(--aaa-text-faint);
}

.aaa-breadcrumb a {
    color: var(--aaa-text-soft);
    text-decoration: none;
    transition: color var(--aaa-transition);
}

.aaa-breadcrumb a:hover {
    color: #fff;
}

/* ============================================================
   頁首
============================================================ */
.aaa-header {
    text-align: center;
    padding: 48px 20px 28px;
}

.aaa-title {
    font-size: clamp(1.7rem, 4vw, 2.6rem);
    font-weight: 800;
    margin: 0 0 10px;
    color: #fff;
    line-height: 1.2;
    letter-spacing: -0.01em;
}

.aaa-desc {
    color: var(--aaa-text-muted);
    margin: 0 0 10px;
    font-size: 1rem;
    line-height: 1.7;
}

.aaa-count {
    color: var(--aaa-text-faint);
    font-size: 14px;
    margin: 0;
}

.aaa-count strong {
    color: var(--aaa-primary-2);
    font-weight: 700;
}

/* ============================================================
   搜尋框
============================================================ */
.aaa-search-wrap {
    max-width: 620px;
    margin: 0 auto 32px;
}

.aaa-search-form {
    width: 100%;
}

.aaa-search-inner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    border-radius: var(--aaa-radius-pill);
    background: var(--aaa-surface);
    border: 1px solid var(--aaa-border);
    backdrop-filter: var(--aaa-blur);
    -webkit-backdrop-filter: var(--aaa-blur);
    transition: border-color var(--aaa-transition), box-shadow var(--aaa-transition);
}

.aaa-search-inner:focus-within {
    border-color: rgba(124,92,255,0.55);
    box-shadow: 0 0 0 3px rgba(124,92,255,0.14);
}

.aaa-search-icon {
    font-size: 16px;
    flex-shrink: 0;
    opacity: 0.7;
}

.aaa-search-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: var(--aaa-text);
    font-size: 15px;
    min-width: 0;
    padding: 0;
}

.aaa-search-input::placeholder {
    color: var(--aaa-text-faint);
}

.aaa-search-btn {
    flex-shrink: 0;
    padding: 6px 18px;
    border-radius: var(--aaa-radius-pill);
    border: none;
    background: linear-gradient(135deg, var(--aaa-primary), #9d6bff);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: transform var(--aaa-transition), opacity var(--aaa-transition);
    box-shadow: 0 6px 18px rgba(124,92,255,0.28);
}

.aaa-search-btn:hover {
    transform: translateY(-1px);
    opacity: 0.92;
}

/* ============================================================
   篩選列（glass card）
============================================================ */
.aaa-filter-wrap {
    background: var(--aaa-surface);
    border: 1px solid var(--aaa-border);
    border-radius: var(--aaa-radius-lg);
    backdrop-filter: var(--aaa-blur);
    -webkit-backdrop-filter: var(--aaa-blur);
    box-shadow: var(--aaa-shadow-sm);
    padding: 22px;
    margin-bottom: 36px;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.aaa-filter-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.aaa-filter-label {
    font-size: 12px;
    color: var(--aaa-text-muted);
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.aaa-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.aaa-filter-btn {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 0 14px;
    border-radius: var(--aaa-radius-pill);
    font-size: 13px;
    text-decoration: none;
    color: var(--aaa-text-muted);
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--aaa-border);
    transition: all var(--aaa-transition);
    white-space: nowrap;
}

.aaa-filter-btn:hover {
    color: #fff;
    background: rgba(124,92,255,0.18);
    border-color: rgba(124,92,255,0.42);
}

.aaa-filter-btn.active {
    color: #fff;
    background: rgba(124,92,255,0.28);
    border-color: rgba(124,92,255,0.60);
    box-shadow: 0 0 16px rgba(124,92,255,0.28);
}

/* ============================================================
   卡片網格
============================================================ */
.aaa-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

@media (min-width: 600px) {
    .aaa-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
}

@media (min-width: 1024px) {
    .aaa-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
}

/* ============================================================
   卡片（glassmorphism）
   ACG v2：background 改為半透明深色，讓 backdrop-filter 有對比
============================================================ */
.aaa-card {
    border-radius: var(--aaa-radius-md);
    overflow: hidden;
    background: var(--aaa-surface);
    border: 1px solid var(--aaa-border);
    backdrop-filter: var(--aaa-blur);
    -webkit-backdrop-filter: var(--aaa-blur);
    box-shadow: var(--aaa-shadow-sm);
    transition: transform var(--aaa-transition), box-shadow var(--aaa-transition), border-color var(--aaa-transition);
}

.aaa-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--aaa-shadow-md);
    border-color: rgba(124,92,255,0.35);
}

.aaa-card-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

/* ── 封面 ── */
.aaa-card-cover-wrap {
    position: relative;
    aspect-ratio: 2 / 3;
    overflow: hidden;
    background: var(--aaa-bg-3);
}

.aaa-card-cover {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .38s ease;
}

.aaa-card:hover .aaa-card-cover {
    transform: scale(1.06);
}

.aaa-no-cover {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--aaa-text-faint);
    font-size: 13px;
    height: 100%;
    background:
        radial-gradient(circle at 30% 20%, rgba(124,92,255,0.24), transparent 34%),
        linear-gradient(135deg, #0d1d38, #101d35);
}

/* ── 狀態 Badge ── */
.aaa-status-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 3px 10px;
    border-radius: var(--aaa-radius-pill);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.s-fin { background: rgba(52,211,153,0.20);  color: #34d399; border: 1px solid rgba(52,211,153,0.36); }
.s-rel { background: rgba(76,201,240,0.20);  color: #4cc9f0; border: 1px solid rgba(76,201,240,0.36); }
.s-pre { background: rgba(251,191,36,0.20);  color: #fbbf24; border: 1px solid rgba(251,191,36,0.36); }
.s-can { background: rgba(251,113,133,0.20); color: #fb7185; border: 1px solid rgba(251,113,133,0.36); }
.s-hia { background: rgba(251,113,133,0.20); color: #fb7185; border: 1px solid rgba(251,113,133,0.36); }

/* ── 評分 Badge ── */
.aaa-score-badge {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0,0,0,0.72);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #fbbf24;
    padding: 3px 9px;
    border-radius: var(--aaa-radius-pill);
    font-size: 12px;
    font-weight: 800;
    border: 1px solid rgba(251,191,36,0.28);
}

/* ── 卡片內容 ── */
.aaa-card-body {
    padding: 12px 14px 14px;
    background: var(--aaa-surface-2);
}

.aaa-card-title {
    font-size: 13px;
    font-weight: 700;
    margin: 0 0 5px;
    line-height: 1.45;
    color: #fff;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.aaa-card-romaji {
    font-size: 11px;
    color: var(--aaa-text-faint);
    margin: 0 0 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.aaa-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 7px;
}

.aaa-meta-tag {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: var(--aaa-radius-pill);
    font-weight: 600;
}

.aaa-meta-format { background: rgba(124,92,255,0.20); color: #b8a0ff; border: 1px solid rgba(124,92,255,0.34); }
.aaa-meta-season { background: rgba(52,211,153,0.16); color: #34d399; border: 1px solid rgba(52,211,153,0.30); }
.aaa-meta-ep     { background: rgba(76,201,240,0.16); color: #4cc9f0; border: 1px solid rgba(76,201,240,0.30); }

.aaa-card-pop {
    font-size: 11px;
    color: var(--aaa-text-faint);
}

/* ============================================================
   分頁（pill + 紫色主色）
============================================================ */
.aaa-pagination {
    display: flex;
    justify-content: center;
    margin: 36px 0;
}

.aaa-pagination ul {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 0;
    justify-content: center;
}

.aaa-pagination .page-numbers {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    padding: 0 12px;
    border-radius: var(--aaa-radius-pill);
    background: var(--aaa-surface);
    color: var(--aaa-text-muted);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    border: 1px solid var(--aaa-border);
    backdrop-filter: var(--aaa-blur);
    -webkit-backdrop-filter: var(--aaa-blur);
    transition: all var(--aaa-transition);
}

.aaa-pagination .page-numbers:hover {
    color: #fff;
    background: rgba(124,92,255,0.22);
    border-color: rgba(124,92,255,0.48);
}

.aaa-pagination .page-numbers.current {
    background: linear-gradient(135deg, var(--aaa-primary), #9d6bff);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 6px 20px rgba(124,92,255,0.38);
}

.aaa-pagination .page-numbers.dots {
    background: none;
    border: none;
    backdrop-filter: none;
    cursor: default;
    color: var(--aaa-text-faint);
}

/* ============================================================
   SEO 底部連結
============================================================ */
.aaa-seo-footer {
    border-top: 1px solid var(--aaa-border);
    padding-top: 28px;
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.aaa-seo-row {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    align-items: center;
}

.aaa-seo-label {
    font-size: 12px;
    color: var(--aaa-text-faint);
    min-width: 68px;
    font-weight: 600;
}

.aaa-seo-tag {
    font-size: 12px;
    color: var(--aaa-text-muted);
    text-decoration: none;
    padding: 3px 10px;
    border-radius: var(--aaa-radius-pill);
    border: 1px solid var(--aaa-border);
    transition: all var(--aaa-transition);
}

.aaa-seo-tag:hover {
    color: #fff;
    background: rgba(124,92,255,0.14);
    border-color: rgba(124,92,255,0.36);
}

/* ============================================================
   無結果 / 無資料
============================================================ */
.aaa-empty {
    text-align: center;
    padding: 100px 20px;
    color: var(--aaa-text-muted);
}

.aaa-empty p {
    font-size: 1.1rem;
    margin: 0 0 20px;
}

.aaa-import-btn {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding: 0 24px;
    background: linear-gradient(135deg, var(--aaa-primary), #9d6bff);
    color: #fff;
    border-radius: var(--aaa-radius-pill);
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 10px 26px rgba(124,92,255,0.32);
    transition: transform var(--aaa-transition), opacity var(--aaa-transition);
}

.aaa-import-btn:hover {
    transform: translateY(-2px);
    opacity: 0.92;
    color: #fff;
}

/* ============================================================
   手機版 RWD
============================================================ */
@media (max-width: 720px) {
    .aaa-wrap { padding: 0 14px 60px; }
    .aaa-header { padding: 32px 14px 20px; }
    .aaa-filter-wrap { padding: 16px; gap: 14px; }
    .aaa-filter-btn { font-size: 12px; min-height: 28px; padding: 0 11px; }
    .aaa-search-inner { padding: 8px 12px; }
    .aaa-search-btn { padding: 5px 14px; font-size: 12px; }
}

@media (max-width: 480px) {
    .aaa-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .aaa-card-body { padding: 9px 10px 10px; }
    .aaa-card-title { font-size: 12px; }
    .aaa-breadcrumb { padding: 9px 14px; }
}
</style>

<?php get_footer(); ?>
