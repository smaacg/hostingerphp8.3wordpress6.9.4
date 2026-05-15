<?php
/**
 * Archive Series Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/archive-series.php
 *
 * 系列列表頁模板
 * 兩欄佈局：左側主內容（動漫卡片）＋右側 Sidebar（統計、快速跳轉、相關標籤）
 * CSS 前綴：.asa-*（archive-series-anime），避免與 .aaa-* 衝突
 * 所有 CSS inline 於檔案底部，與 archive-anime.php 同模式
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

/* ── 系列 Term 資訊 ──────────────────────────────────────── */
$series_term   = get_queried_object();
$series_name   = $series_term->name   ?? '系列';
$series_slug   = $series_term->slug   ?? '';
$series_desc   = term_description( $series_term->term_id ?? 0 );
$series_count  = (int) ( $series_term->count ?? 0 );
$series_url    = get_term_link( $series_term );
$root_anilist_id = get_term_meta( $series_term->term_id, 'anime_series_root_id', true );

/* ── 一次 Query 取得所有系列作品（排序由 pre_get_posts 控制）── */
global $wp_query;
$all_posts = [];
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        $pid = get_the_ID();
        $all_posts[] = [
            'id'         => $pid,
            'permalink'  => get_the_permalink(),
            'cover'      => get_post_meta( $pid, 'anime_cover_image',   true )
                         ?: get_the_post_thumbnail_url( $pid, 'medium' ),
            'title_zh'   => get_post_meta( $pid, 'anime_title_chinese', true ) ?: get_the_title(),
            'title_ro'   => get_post_meta( $pid, 'anime_title_romaji',  true ),
            'title_na'   => get_post_meta( $pid, 'anime_title_native',  true ),
            'format'     => get_post_meta( $pid, 'anime_format',        true ),
            'status'     => get_post_meta( $pid, 'anime_status',        true ),
            'season'     => get_post_meta( $pid, 'anime_season',        true ),
            'year'       => (int) get_post_meta( $pid, 'anime_season_year', true ),
            'episodes'   => (int) get_post_meta( $pid, 'anime_episodes',    true ),
            'score_raw'  => get_post_meta( $pid, 'anime_score_anilist', true ),
        ];
    }
    wp_reset_postdata();
}

/* ── Sidebar 統計：從同一陣列計算，不重複 Query ────────────── */
$total_episodes = 0;
$years          = [];
foreach ( $all_posts as $p ) {
    $total_episodes += $p['episodes'];
    if ( $p['year'] ) {
        $years[] = $p['year'];
    }
}
$year_min  = $years ? min( $years ) : '';
$year_max  = $years ? max( $years ) : '';
$year_span = ( $year_min && $year_max && $year_min !== $year_max )
    ? $year_min . '–' . $year_max
    : ( $year_min ?: '—' );

/* ── 格式 / 狀態 / 季節 對照表 ──────────────────────────── */
$format_labels = [
    'TV'       => 'TV',    'TV_SHORT' => 'TV短篇', 'MOVIE'   => '劇場版',
    'OVA'      => 'OVA',   'ONA'      => 'ONA',    'SPECIAL' => '特別篇',
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
$season_labels = [
    'WINTER' => '冬', 'SPRING' => '春',
    'SUMMER' => '夏', 'FALL'   => '秋',
];

/* ── Schema：TVSeries ────────────────────────────────────── */
$schema_series = [
    '@context'    => 'https://schema.org',
    '@type'       => 'TVSeries',
    'name'        => $series_name,
    'url'         => is_wp_error( $series_url ) ? '' : $series_url,
    'numberOfEpisodes' => $total_episodes ?: null,
    'startDate'   => $year_min ?: null,
    'endDate'     => ( $year_max && $year_max !== $year_min ) ? $year_max : null,
    'description' => $series_desc ? wp_strip_all_tags( $series_desc ) : null,
];
$schema_series = array_filter( $schema_series, fn( $v ) => $v !== null && $v !== '' );

/* ── Schema：BreadcrumbList ──────────────────────────────── */
$schema_breadcrumb = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        [ '@type' => 'ListItem', 'position' => 1, 'name' => '首頁',     'item' => home_url( '/' ) ],
        [ '@type' => 'ListItem', 'position' => 2, 'name' => '動漫列表', 'item' => home_url( '/anime/' ) ],
        [ '@type' => 'ListItem', 'position' => 3, 'name' => $series_name,
          'item' => is_wp_error( $series_url ) ? '' : $series_url ],
    ],
];
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema_series,     JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $schema_breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<div class="asa-wrap">

    <?php /* ── 麵包屑 ─────────────────────────────────────── */ ?>
    <nav class="asa-breadcrumb" aria-label="麵包屑導航">
        <ol>
            <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">首頁</a></li>
            <li><a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>">動漫列表</a></li>
            <li><?php echo esc_html( $series_name ); ?></li>
        </ol>
    </nav>

    <?php /* ── Hero ────────────────────────────────────────── */ ?>
    <div class="asa-hero">
        <div class="asa-hero-inner">
            <div class="asa-hero-label">📺 動漫系列</div>
            <h1 class="asa-hero-title"><?php echo esc_html( $series_name ); ?></h1>
            <?php if ( $series_slug && $series_slug !== sanitize_title( $series_name ) ) : ?>
                <p class="asa-hero-romaji"><?php echo esc_html( $series_slug ); ?></p>
            <?php endif; ?>
            <div class="asa-hero-meta">
                <span class="asa-hero-meta-item">
                    🎬 <strong><?php echo esc_html( $series_count ); ?></strong> 部作品
                </span>
                <?php if ( $total_episodes ) : ?>
                <span class="asa-hero-meta-sep">·</span>
                <span class="asa-hero-meta-item">
                    📋 <strong><?php echo esc_html( $total_episodes ); ?></strong> 集
                </span>
                <?php endif; ?>
                <?php if ( $year_span ) : ?>
                <span class="asa-hero-meta-sep">·</span>
                <span class="asa-hero-meta-item">
                    📅 <strong><?php echo esc_html( $year_span ); ?></strong>
                </span>
                <?php endif; ?>
            </div>
            <?php if ( $series_desc ) : ?>
                <p class="asa-hero-desc"><?php echo wp_kses_post( $series_desc ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php /* ── 主體：左主內容 + 右 Sidebar ─────────────────── */ ?>
    <div class="asa-layout">

        <?php /* ══ 左：動漫卡片列表 ══════════════════════════ */ ?>
        <main class="asa-main">

            <?php if ( empty( $all_posts ) ) : ?>
                <div class="asa-empty">
                    <p>這個系列目前沒有已匯入的作品。</p>
                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-import' ) ); ?>"
                           class="asa-btn">前往匯入動漫</a>
                    <?php endif; ?>
                </div>
            <?php else : ?>

                <?php
                /* 依年份分組，用於快速跳轉錨點 */
                $grouped = [];
                foreach ( $all_posts as $p ) {
                    $key = $p['year'] ?: '未知年份';
                    $grouped[ $key ][] = $p;
                }
                ksort( $grouped );
                ?>

                <?php foreach ( $grouped as $group_year => $group_posts ) : ?>
                    <section class="asa-year-section" id="year-<?php echo esc_attr( $group_year ); ?>">
                        <h2 class="asa-year-heading"><?php echo esc_html( $group_year ); ?></h2>
                        <div class="asa-cards">
                            <?php foreach ( $group_posts as $p ) :
                                $score        = ( is_numeric( $p['score_raw'] ) && (float) $p['score_raw'] > 0 )
                                    ? number_format( (float) $p['score_raw'] / 10, 1 ) : '';
                                $format_label = $format_labels[ $p['format'] ] ?? $p['format'];
                                $status_label = $status_labels[ $p['status'] ] ?? '';
                                $status_class = $status_classes[ $p['status'] ] ?? '';
                                $season_label = $season_labels[ strtoupper( $p['season'] ) ] ?? '';
                                $season_str   = ( $p['year'] && $season_label )
                                    ? $p['year'] . ' ' . $season_label : '';
                            ?>
                            <article class="asa-card">
                                <a href="<?php echo esc_url( $p['permalink'] ); ?>" class="asa-card-link">

                                    <?php /* 封面 */ ?>
                                    <div class="asa-card-cover-wrap">
                                        <?php if ( $p['cover'] ) : ?>
                                            <img class="asa-card-cover"
                                                 src="<?php echo esc_url( $p['cover'] ); ?>"
                                                 alt="<?php echo esc_attr( $p['title_zh'] ); ?> 封面圖"
                                                 loading="lazy">
                                        <?php else : ?>
                                            <div class="asa-card-cover asa-no-cover">無封面</div>
                                        <?php endif; ?>

                                        <?php if ( $status_label ) : ?>
                                            <span class="asa-status-badge <?php echo esc_attr( $status_class ); ?>">
                                                <?php echo esc_html( $status_label ); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ( $score ) : ?>
                                            <span class="asa-score-badge">⭐ <?php echo esc_html( $score ); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php /* 卡片內容 */ ?>
                                    <div class="asa-card-body">
                                        <h3 class="asa-card-title"><?php echo esc_html( $p['title_zh'] ); ?></h3>

                                        <?php if ( $p['title_na'] ) : ?>
                                            <p class="asa-card-native"><?php echo esc_html( $p['title_na'] ); ?></p>
                                        <?php endif; ?>

                                        <div class="asa-card-meta">
                                            <?php if ( $format_label ) : ?>
                                                <span class="asa-meta-tag asa-meta-format">
                                                    <?php echo esc_html( $format_label ); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $season_str ) : ?>
                                                <span class="asa-meta-tag asa-meta-season">
                                                    <?php echo esc_html( $season_str ); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $p['episodes'] ) : ?>
                                                <span class="asa-meta-tag asa-meta-ep">
                                                    <?php echo esc_html( $p['episodes'] ); ?> 集
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </a>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

            <?php endif; ?>

        </main><!-- .asa-main -->

        <?php /* ══ 右：Sidebar ═══════════════════════════════ */ ?>
        <aside class="asa-sidebar">

            <?php /* 系列統計 */ ?>
            <div class="asa-sidebar-card">
                <h3 class="asa-sidebar-title">📊 系列統計</h3>
                <ul class="asa-stat-list">
                    <li>
                        <span class="asa-stat-label">作品數</span>
                        <span class="asa-stat-value"><?php echo esc_html( $series_count ); ?> 部</span>
                    </li>
                    <?php if ( $total_episodes ) : ?>
                    <li>
                        <span class="asa-stat-label">總集數</span>
                        <span class="asa-stat-value"><?php echo esc_html( $total_episodes ); ?> 集</span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $year_span ) : ?>
                    <li>
                        <span class="asa-stat-label">年份</span>
                        <span class="asa-stat-value"><?php echo esc_html( $year_span ); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $root_anilist_id ) : ?>
                    <li>
                        <span class="asa-stat-label">AniList</span>
                        <span class="asa-stat-value">
                            <a href="https://anilist.co/anime/<?php echo esc_attr( $root_anilist_id ); ?>/"
                               target="_blank" rel="noopener noreferrer" class="asa-ext-link">
                                查看根作品 ↗
                            </a>
                        </span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php /* 快速跳轉（依年份）*/ ?>
            <?php if ( count( $grouped ?? [] ) > 1 ) : ?>
            <div class="asa-sidebar-card">
                <h3 class="asa-sidebar-title">🗂️ 快速跳轉</h3>
                <nav class="asa-jump-nav" aria-label="依年份跳轉">
                    <?php foreach ( array_keys( $grouped ?? [] ) as $jump_year ) : ?>
                        <a href="#year-<?php echo esc_attr( $jump_year ); ?>" class="asa-jump-btn">
                            <?php echo esc_html( $jump_year ); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <?php endif; ?>

            <?php /* 相關類型標籤 */ ?>
            <?php
            $related_formats = [];
            foreach ( $all_posts as $p ) {
                if ( $p['format'] && isset( $format_labels[ $p['format'] ] ) ) {
                    $related_formats[ $p['format'] ] = $format_labels[ $p['format'] ];
                }
            }
            ?>
            <?php if ( $related_formats ) : ?>
            <div class="asa-sidebar-card">
                <h3 class="asa-sidebar-title">🏷️ 作品類型</h3>
                <div class="asa-tag-row">
                    <?php foreach ( $related_formats as $fmt_key => $fmt_label ) :
                        $fmt_term = get_term_by( 'slug', strtolower( $fmt_key ), 'anime_format_tax' );
                        $fmt_url  = $fmt_term ? get_term_link( $fmt_term ) : '#';
                    ?>
                        <a href="<?php echo esc_url( is_wp_error( $fmt_url ) ? '#' : $fmt_url ); ?>"
                           class="asa-tag">
                            <?php echo esc_html( $fmt_label ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </aside><!-- .asa-sidebar -->

    </div><!-- .asa-layout -->

</div><!-- .asa-wrap -->

<?php /* ── 平滑滾動 inline JS ──────────────────────────── */ ?>
<script>
(function () {
    document.querySelectorAll('.asa-jump-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
})();
</script>

<style>
/* ============================================================
   ASA（Archive Series Anime）CSS
   前綴 .asa-* 獨立命名，不依賴 .aaa-* 或 .asd-*
   所有 CSS variables 自行定義於 .asa-wrap
============================================================ */
.asa-wrap {
    /* ── 色彩 ── */
    --asa-primary:       #7c5cff;
    --asa-primary-2:     #4cc9f0;
    --asa-accent:        #ff78c8;
    --asa-success:       #34d399;
    --asa-warning:       #fbbf24;

    /* ── 背景 ── */
    --asa-bg:            #07111f;
    --asa-bg-2:          #0a1730;
    --asa-bg-3:          #10213f;

    /* ── 文字 ── */
    --asa-text:          #f7f9ff;
    --asa-text-soft:     rgba(247,249,255,0.82);
    --asa-text-muted:    rgba(247,249,255,0.62);
    --asa-text-faint:    rgba(247,249,255,0.42);

    /* ── 邊框 ── */
    --asa-border:        rgba(255,255,255,0.10);
    --asa-border-strong: rgba(255,255,255,0.18);

    /* ── 玻璃表面 ── */
    --asa-surface:       rgba(10,20,40,0.55);
    --asa-surface-2:     rgba(10,20,40,0.70);
    --asa-surface-3:     rgba(10,20,40,0.82);

    /* ── 其他 ── */
    --asa-blur:          blur(16px);
    --asa-radius-sm:     10px;
    --asa-radius-md:     18px;
    --asa-radius-lg:     24px;
    --asa-radius-pill:   999px;
    --asa-shadow-sm:     0 8px 24px rgba(0,0,0,0.28);
    --asa-shadow-md:     0 16px 40px rgba(0,0,0,0.38);
    --asa-transition:    .25s ease;

    color: #f7f9ff;
    position: relative;
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 20px 80px;
    box-sizing: border-box;
}

.asa-wrap *,
.asa-wrap *::before,
.asa-wrap *::after {
    box-sizing: border-box;
}

/* 全頁背景光暈 */
.asa-wrap::before {
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
   麵包屑
============================================================ */
.asa-breadcrumb {
    margin: 20px 0 0;
    padding: 12px 20px;
    border-radius: var(--asa-radius-pill);
    background: var(--asa-surface);
    border: 1px solid var(--asa-border);
    backdrop-filter: var(--asa-blur);
    -webkit-backdrop-filter: var(--asa-blur);
    display: inline-block;
}

.asa-breadcrumb ol {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 0;
    font-size: 13px;
    color: var(--asa-text-muted);
    align-items: center;
}

.asa-breadcrumb li + li::before {
    content: '/';
    margin-right: 6px;
    color: var(--asa-text-faint);
}

.asa-breadcrumb a {
    color: var(--asa-text-soft);
    text-decoration: none;
    transition: color var(--asa-transition);
}

.asa-breadcrumb a:hover { color: #fff; }

/* ============================================================
   Hero
============================================================ */
.asa-hero {
    padding: 56px 20px 40px;
    text-align: center;
}

.asa-hero-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--asa-primary-2);
    margin-bottom: 12px;
}

.asa-hero-title {
    font-size: clamp(1.8rem, 5vw, 3rem);
    font-weight: 900;
    margin: 0 0 8px;
    color: #fff;
    line-height: 1.15;
    letter-spacing: -0.02em;
}

.asa-hero-romaji {
    font-size: 1rem;
    color: var(--asa-text-muted);
    margin: 0 0 18px;
    font-style: italic;
}

.asa-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 18px;
    justify-content: center;
    align-items: center;
    font-size: 14px;
    color: var(--asa-text-muted);
    margin-bottom: 16px;
}

.asa-hero-meta strong {
    color: var(--asa-primary-2);
    font-weight: 700;
}

.asa-hero-meta-sep {
    color: var(--asa-text-faint);
}

.asa-hero-desc {
    max-width: 640px;
    margin: 0 auto;
    font-size: 0.95rem;
    color: var(--asa-text-muted);
    line-height: 1.75;
}

/* ============================================================
   兩欄佈局
============================================================ */
.asa-layout {
    display: grid;
    grid-template-columns: 1fr 260px;
    gap: 28px;
    align-items: start;
}

@media (max-width: 900px) {
    .asa-layout {
        grid-template-columns: 1fr;
    }
    .asa-sidebar {
        order: -1;
    }
}

/* ============================================================
   主內容：年份分組
============================================================ */
.asa-year-section {
    margin-bottom: 36px;
    scroll-margin-top: 80px;
}

.asa-year-heading {
    font-size: 1rem;
    font-weight: 700;
    color: var(--asa-text-muted);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin: 0 0 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--asa-border);
}

/* ============================================================
   卡片網格
============================================================ */
.asa-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 16px;
}

@media (min-width: 600px) {
    .asa-cards { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
}

/* ============================================================
   卡片（glassmorphism，與 .aaa-card 同風格）
============================================================ */
.asa-card {
    border-radius: var(--asa-radius-md);
    overflow: hidden;
    background: var(--asa-surface);
    border: 1px solid var(--asa-border);
    backdrop-filter: var(--asa-blur);
    -webkit-backdrop-filter: var(--asa-blur);
    box-shadow: var(--asa-shadow-sm);
    transition: transform var(--asa-transition), box-shadow var(--asa-transition), border-color var(--asa-transition);
}

.asa-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--asa-shadow-md);
    border-color: rgba(124,92,255,0.35);
}

.asa-card-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

/* 封面 */
.asa-card-cover-wrap {
    position: relative;
    aspect-ratio: 2 / 3;
    overflow: hidden;
    background: var(--asa-bg-3);
}

.asa-card-cover {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .38s ease;
}

.asa-card:hover .asa-card-cover {
    transform: scale(1.06);
}

.asa-no-cover {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--asa-text-faint);
    font-size: 13px;
    height: 100%;
    background:
        radial-gradient(circle at 30% 20%, rgba(124,92,255,0.24), transparent 34%),
        linear-gradient(135deg, #0d1d38, #101d35);
}

/* 狀態 Badge */
.asa-status-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 3px 9px;
    border-radius: var(--asa-radius-pill);
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

/* 評分 Badge */
.asa-score-badge {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(0,0,0,0.72);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #fbbf24;
    padding: 3px 8px;
    border-radius: var(--asa-radius-pill);
    font-size: 11px;
    font-weight: 800;
    border: 1px solid rgba(251,191,36,0.28);
}

/* 卡片內容 */
.asa-card-body {
    padding: 10px 12px 12px;
    background: var(--asa-surface-2);
}

.asa-card-title {
    font-size: 13px;
    font-weight: 700;
    margin: 0 0 4px;
    line-height: 1.45;
    color: #fff;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.asa-card-native {
    font-size: 11px;
    color: var(--asa-text-faint);
    margin: 0 0 7px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.asa-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.asa-meta-tag {
    font-size: 11px;
    padding: 2px 7px;
    border-radius: var(--asa-radius-pill);
    font-weight: 600;
}

.asa-meta-format { background: rgba(124,92,255,0.20); color: #b8a0ff; border: 1px solid rgba(124,92,255,0.34); }
.asa-meta-season { background: rgba(52,211,153,0.16); color: #34d399; border: 1px solid rgba(52,211,153,0.30); }
.asa-meta-ep     { background: rgba(76,201,240,0.16); color: #4cc9f0; border: 1px solid rgba(76,201,240,0.30); }

/* ============================================================
   Sidebar 通用
============================================================ */
.asa-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: sticky;
    top: 80px;
}

.asa-sidebar-card {
    background: var(--asa-surface);
    border: 1px solid var(--asa-border);
    border-radius: var(--asa-radius-md);
    backdrop-filter: var(--asa-blur);
    -webkit-backdrop-filter: var(--asa-blur);
    box-shadow: var(--asa-shadow-sm);
    padding: 18px 20px;
}

.asa-sidebar-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--asa-text-muted);
    letter-spacing: 0.07em;
    text-transform: uppercase;
    margin: 0 0 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--asa-border);
}

/* ── 統計列表 ── */
.asa-stat-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.asa-stat-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
}

.asa-stat-label {
    color: var(--asa-text-muted);
}

.asa-stat-value {
    color: #fff;
    font-weight: 600;
}

.asa-ext-link {
    color: var(--asa-primary-2);
    text-decoration: none;
    font-weight: 600;
    font-size: 12px;
    transition: opacity var(--asa-transition);
}

.asa-ext-link:hover { opacity: 0.75; }

/* ── 快速跳轉 ── */
.asa-jump-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.asa-jump-btn {
    display: inline-flex;
    align-items: center;
    padding: 5px 13px;
    border-radius: var(--asa-radius-pill);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    color: var(--asa-text-muted);
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--asa-border);
    transition: all var(--asa-transition);
}

.asa-jump-btn:hover {
    color: #fff;
    background: rgba(124,92,255,0.18);
    border-color: rgba(124,92,255,0.42);
}

/* ── 相關類型標籤 ── */
.asa-tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.asa-tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: var(--asa-radius-pill);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    color: var(--asa-text-muted);
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--asa-border);
    transition: all var(--asa-transition);
}

.asa-tag:hover {
    color: #fff;
    background: rgba(124,92,255,0.18);
    border-color: rgba(124,92,255,0.42);
}

/* ============================================================
   空狀態
============================================================ */
.asa-empty {
    text-align: center;
    padding: 80px 20px;
    color: var(--asa-text-muted);
}

.asa-empty p {
    font-size: 1.05rem;
    margin: 0 0 18px;
}

.asa-btn {
    display: inline-flex;
    align-items: center;
    min-height: 42px;
    padding: 0 22px;
    background: linear-gradient(135deg, var(--asa-primary), #9d6bff);
    color: #fff;
    border-radius: var(--asa-radius-pill);
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 10px 26px rgba(124,92,255,0.32);
    transition: transform var(--asa-transition), opacity var(--asa-transition);
}

.asa-btn:hover {
    transform: translateY(-2px);
    opacity: 0.92;
    color: #fff;
}

/* ============================================================
   手機版 RWD
============================================================ */
@media (max-width: 720px) {
    .asa-wrap  { padding: 0 14px 60px; }
    .asa-hero  { padding: 36px 14px 28px; }
    .asa-sidebar-card { padding: 14px 16px; }
}

@media (max-width: 480px) {
    .asa-cards { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .asa-card-body { padding: 8px 9px 10px; }
    .asa-card-title { font-size: 12px; }
}
</style>

<?php get_footer(); ?>
