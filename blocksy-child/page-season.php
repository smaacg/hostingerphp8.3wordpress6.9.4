<?php
/**
 * Bangumi 番組表主頁面
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-18)
 *
 * 載入方式：由 functions.php 的 template_redirect 自動 include
 * URL 範圍：/bangumi/{YYYYMM}/（YYYYMM 必須為季首月）
 *
 * 依賴：
 *   - smacg_bangumi_parse_ym() / smacg_bangumi_shift_ym() / smacg_bangumi_render_*()（functions.php）
 *   - smacg_render_anime_card()（smacg-members 外掛 member-render.php）
 *   - anime CPT meta：anime_season, anime_season_year, anime_score_anilist, ...
 *   - wp_anime_user_status 表（anime-sync-pro 外掛）
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 1. 解析 URL，建立季度上下文
 * ============================================================ */
$ym       = (string) get_query_var( 'bangumi_ym' );
if ( ! $ym ) $ym = smacg_bangumi_current_ym();

$info     = smacg_bangumi_parse_ym( $ym );
$prev_ym  = smacg_bangumi_shift_ym( $ym, -1 );
$next_ym  = smacg_bangumi_shift_ym( $ym, +1 );
$cur_ym   = smacg_bangumi_current_ym();
$is_current_season = ( $ym === $cur_ym );

$canonical = home_url( "/bangumi/{$ym}/" );

/* ============================================================
 * 2. 查詢本季所有 anime（一次 SQL，含使用者狀態 LEFT JOIN）
 * ============================================================ */
global $wpdb;
$uid = get_current_user_id();

$query = $wpdb->prepare(
    "SELECT p.ID,
            p.post_title,
            ms.meta_value AS anime_status,
            mep.meta_value AS anime_episodes,
            mea.meta_value AS anime_episodes_aired,
            mna.meta_value AS anime_next_airing,
            msd.meta_value AS anime_start_date,
            mtj.meta_value AS anime_title_japanese,
            mtc.meta_value AS anime_title_chinese,
            mc.meta_value  AS anime_cover_image,
            msc.meta_value AS anime_score_anilist,
            mpo.meta_value AS anime_popularity,
            us.status      AS user_status,
            us.progress    AS user_progress,
            us.favorited   AS user_favorited
       FROM {$wpdb->posts} p
       INNER JOIN {$wpdb->postmeta} mse  ON mse.post_id = p.ID AND mse.meta_key  = 'anime_season'      AND mse.meta_value  = %s
       INNER JOIN {$wpdb->postmeta} msy  ON msy.post_id = p.ID AND msy.meta_key  = 'anime_season_year' AND msy.meta_value  = %d
       LEFT  JOIN {$wpdb->postmeta} ms   ON ms.post_id  = p.ID AND ms.meta_key   = 'anime_status'
       LEFT  JOIN {$wpdb->postmeta} mep  ON mep.post_id = p.ID AND mep.meta_key  = 'anime_episodes'
       LEFT  JOIN {$wpdb->postmeta} mea  ON mea.post_id = p.ID AND mea.meta_key  = 'anime_episodes_aired'
       LEFT  JOIN {$wpdb->postmeta} mna  ON mna.post_id = p.ID AND mna.meta_key  = 'anime_next_airing'
       LEFT  JOIN {$wpdb->postmeta} msd  ON msd.post_id = p.ID AND msd.meta_key  = 'anime_start_date'
       LEFT  JOIN {$wpdb->postmeta} mtj  ON mtj.post_id = p.ID AND mtj.meta_key  = 'anime_title_japanese'
       LEFT  JOIN {$wpdb->postmeta} mtc  ON mtc.post_id = p.ID AND mtc.meta_key  = 'anime_title_chinese'
       LEFT  JOIN {$wpdb->postmeta} mc   ON mc.post_id  = p.ID AND mc.meta_key   = 'anime_cover_image'
       LEFT  JOIN {$wpdb->postmeta} msc  ON msc.post_id = p.ID AND msc.meta_key  = 'anime_score_anilist'
       LEFT  JOIN {$wpdb->postmeta} mpo  ON mpo.post_id = p.ID AND mpo.meta_key  = 'anime_popularity'
       LEFT  JOIN {$wpdb->prefix}anime_user_status us ON us.anime_id = p.ID AND us.user_id = %d
      WHERE p.post_type   = 'anime'
        AND p.post_status = 'publish'
      ORDER BY (msc.meta_value + 0) DESC, p.ID ASC",
    $info['season'],
    $info['year'],
    $uid
);

$rows = $wpdb->get_results( $query, ARRAY_A );

/* ============================================================
 * 3. 整理資料、計算統計、分組星期
 * ============================================================ */
$status_map = [ 0 => 'want', 1 => 'watching', 2 => 'completed', 3 => 'dropped' ];

$all_posts   = [];
$by_weekday  = [ 0 => [] ];
for ( $i = 1; $i <= 7; $i++ ) $by_weekday[ $i ] = [];

$stat_total      = 0;
$stat_mine       = 0;
$stat_watching   = 0;
$stat_completed  = 0;
$score_sum       = 0.0;
$score_n         = 0;

foreach ( $rows as $r ) {
    $pid = (int) $r['ID'];

    $title    = $r['anime_title_chinese'] ?: $r['post_title'];
    $title_jp = $r['anime_title_japanese'] ?: '';
    $cover    = $r['anime_cover_image'] ?: get_the_post_thumbnail_url( $pid, 'medium' );
    $score_raw = (float) $r['anime_score_anilist'];
    $score    = $score_raw > 0 ? round( $score_raw / 10, 1 ) : null;
    $status   = $r['anime_status'] ?: '';
    $ep_total = (int) $r['anime_episodes'];
    $ep_aired = (int) $r['anime_episodes_aired'];
    $popularity = (int) $r['anime_popularity'];

    // 星期
    $weekday = 0;
    if ( $r['anime_next_airing'] ) {
        $ts = strtotime( $r['anime_next_airing'] );
        if ( $ts ) $weekday = (int) date( 'N', $ts );
    }
    if ( ! $weekday && $r['anime_start_date'] ) {
        $ts = strtotime( $r['anime_start_date'] );
        if ( $ts ) $weekday = (int) date( 'N', $ts );
    }

    // 使用者狀態
    $u_status   = isset( $r['user_status'] ) && $r['user_status'] !== null
                    ? ( $status_map[ (int) $r['user_status'] ] ?? '' )
                    : '';
    $u_progress = (int) $r['user_progress'];
    $u_favorite = (bool) $r['user_favorited'];

    $post_data = [
        'pid'        => $pid,
        'title'      => $title,
        'title_jp'   => $title_jp,
        'cover'      => $cover,
        'score'      => $score,
        'status'     => $status,
        'ep_total'   => $ep_total,
        'ep_aired'   => $ep_aired,
        'url'        => get_permalink( $pid ),
        'weekday'    => $weekday,
        'popularity' => $popularity,
        // 給 card render 用
        'user_status'    => $u_status,
        'user_progress'  => $u_progress,
        'user_favorited' => $u_favorite,
    ];

    $all_posts[]      = $post_data;
    $by_weekday[0][]  = $post_data;
    if ( $weekday >= 1 && $weekday <= 7 ) $by_weekday[ $weekday ][] = $post_data;

    // 統計
    $stat_total++;
    if ( $u_status )                 $stat_mine++;
    if ( $u_status === 'watching' )  $stat_watching++;
    if ( $u_status === 'completed' ) $stat_completed++;
    if ( $score !== null ) { $score_sum += $score; $score_n++; }
}

$avg_score = $score_n > 0 ? round( $score_sum / $score_n, 1 ) : 0;
$total     = count( $all_posts );

/* ============================================================
 * 4. 建立 SEO 上下文
 * ============================================================ */
$seo_ctx = [
    'label'       => $info['label'],
    'canonical'   => $canonical,
    'title'       => sprintf( '%s｜%d 部作品、平均評分 %s - 微笑動漫', $info['label'], $total, $avg_score > 0 ? $avg_score : '—' ),
    'description' => $total > 0
        ? sprintf( '%d 年 %s季新番完整列表，共 %d 部作品，平均評分 %s。每集播出時間、評分、串流平台一次看。', $info['year'], $info['season_zh'], $total, $avg_score > 0 ? $avg_score : '—' )
        : sprintf( '%s 番組表：本季作品列表，包含播出時間、評分、串流平台等資訊。', $info['label'] ),
    'og_image'    => ! empty( $all_posts[0]['cover'] ) ? $all_posts[0]['cover'] : '',
];

/* ============================================================
 * 5. 注入 <head>（SEO meta / OG / Schema.org）
 * ============================================================ */
add_action( 'wp_head', function () use ( $seo_ctx, $all_posts ) {
    smacg_bangumi_render_meta( $seo_ctx );
    smacg_bangumi_render_og( $seo_ctx );
    smacg_bangumi_render_schema( $seo_ctx, $all_posts );
}, 1 );

// 移除 WP 預設 title（避免重複）
remove_action( 'wp_head', '_wp_render_title_tag', 1 );
add_filter( 'pre_get_document_title', function () use ( $seo_ctx ) {
    return $seo_ctx['title'];
} );

/* ============================================================
 * 6. 季度視覺主題（春粉/夏藍/秋橙/冬青）
 * ============================================================ */
$theme_colors = [
    'SPRING' => [ 'main' => '#f9a8d4', 'soft' => 'rgba(249,168,212,0.15)', 'icon' => '🌸' ],
    'SUMMER' => [ 'main' => '#60a5fa', 'soft' => 'rgba(96,165,250,0.15)',  'icon' => '🌊' ],
    'FALL'   => [ 'main' => '#fb923c', 'soft' => 'rgba(251,146,60,0.15)',  'icon' => '🍁' ],
    'WINTER' => [ 'main' => '#67e8f9', 'soft' => 'rgba(103,232,249,0.15)', 'icon' => '❄️' ],
];
$theme = $theme_colors[ $info['season'] ];

/* ============================================================
 * 7. 渲染
 * ============================================================ */
$now_day      = (int) date( 'N' );
$weekday_zh   = [ 0 => '全部', 1 => '週一', 2 => '週二', 3 => '週三', 4 => '週四', 5 => '週五', 6 => '週六', 7 => '週日' ];

get_header();
?>

<style>
:root {
  --bgm-main: <?php echo esc_attr( $theme['main'] ); ?>;
  --bgm-soft: <?php echo esc_attr( $theme['soft'] ); ?>;
}
.bangumi-breadcrumb {
  max-width: 1200px; margin: 16px auto 0; padding: 0 16px;
  font-size: 13px; color: var(--text-muted);
  display: flex; align-items: center; gap: 8px;
}
.bangumi-breadcrumb a { color: var(--text-secondary); text-decoration: none; }
.bangumi-breadcrumb a:hover { color: var(--bgm-main); }
.bangumi-breadcrumb .bc-current { color: var(--text-primary); font-weight: 600; }

.bgm-hero {
  background: linear-gradient(135deg, var(--bgm-soft) 0%, rgba(139,92,246,0.08) 100%);
  border-bottom: 1px solid var(--glass-border);
  padding: 36px 0 28px;
}
.bgm-hero-inner { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
.bgm-hero-icon { font-size: 32px; margin-right: 8px; vertical-align: middle; }
.bgm-hero-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,0.08); border: 1px solid var(--bgm-main);
  color: var(--bgm-main); border-radius: 999px;
  padding: 4px 14px; font-size: 12px; font-weight: 600; margin-bottom: 12px;
}
.bgm-hero-title { font-size: 30px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px; }
.bgm-hero-sub { font-size: 14px; color: var(--text-muted); }

.bgm-season-nav {
  display: flex; gap: 8px; flex-wrap: wrap; margin-top: 20px;
}
.bgm-season-nav a {
  padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  color: var(--text-secondary); text-decoration: none; transition: all .2s;
  display: inline-flex; align-items: center; gap: 6px;
}
.bgm-season-nav a:hover {
  background: var(--bgm-soft); border-color: var(--bgm-main); color: var(--bgm-main);
  transform: translateY(-1px);
}
.bgm-season-nav a.bgm-archive {
  background: rgba(139,92,246,0.1); border-color: rgba(139,92,246,0.3); color: #a78bfa;
}

.bgm-stats {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px; margin: 24px auto 0; max-width: 1200px; padding: 0 16px;
}
.bgm-stat {
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  border-radius: 12px; padding: 14px 16px;
}
.bgm-stat-label { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; }
.bgm-stat-value { font-size: 22px; font-weight: 700; color: var(--text-primary); }
.bgm-stat-value small { font-size: 12px; color: var(--text-muted); font-weight: 500; margin-left: 4px; }

.bgm-main { max-width: 1200px; margin: 0 auto; padding: 28px 16px 60px; }

.bgm-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  flex-wrap: wrap; margin-bottom: 18px;
}
.bgm-weekday-bar { display: flex; gap: 6px; flex-wrap: wrap; }
.bgm-day {
  padding: 7px 16px; border-radius: 999px; font-size: 13px; font-weight: 600;
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  color: var(--text-secondary); cursor: pointer; transition: all .2s; white-space: nowrap;
}
.bgm-day:hover { color: var(--text-primary); background: var(--glass-bg-mid); }
.bgm-day.active { background: var(--bgm-main); border-color: var(--bgm-main); color: #fff; }
.bgm-day-count { font-size: 11px; opacity: .75; margin-left: 4px; }

.bgm-tools { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.bgm-sort {
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  color: var(--text-secondary); border-radius: 999px;
  padding: 6px 14px; font-size: 13px; cursor: pointer; outline: none;
}

.bgm-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 18px;
}
.bgm-group { display: contents; }
.bgm-group[hidden] { display: none; }

.bgm-empty {
  grid-column: 1/-1; text-align: center; color: var(--text-muted);
  padding: 60px 0; font-size: 14px;
}

@media (max-width: 768px) {
  .bgm-hero-title { font-size: 22px; }
  .bgm-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
}
@media (max-width: 480px) {
  .bgm-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
  .bgm-toolbar { flex-direction: column; align-items: stretch; }
}
</style>

<?php smacg_bangumi_render_breadcrumb( $seo_ctx ); ?>

<section class="bgm-hero">
  <div class="bgm-hero-inner">
    <div class="bgm-hero-badge">
      <i class="fa-solid fa-calendar-week"></i>
      <?php echo $is_current_season ? '本季新番' : '歷史季度'; ?>
    </div>
    <h1 class="bgm-hero-title">
      <span class="bgm-hero-icon"><?php echo $theme['icon']; ?></span>
      <?php echo esc_html( $info['label'] ); ?>
    </h1>
    <p class="bgm-hero-sub">依星期瀏覽 <?php echo $total; ?> 部作品，資料來源：站內資料庫</p>

    <nav class="bgm-season-nav" aria-label="季度切換">
      <a href="<?php echo esc_url( home_url( "/bangumi/{$prev_ym}/" ) ); ?>" rel="prev">
        <i class="fa-solid fa-arrow-left"></i> 上一季
      </a>
      <?php if ( ! $is_current_season ): ?>
      <a href="<?php echo esc_url( home_url( "/bangumi/{$cur_ym}/" ) ); ?>">
        <i class="fa-solid fa-house"></i> 本季
      </a>
      <?php endif; ?>
      <a href="<?php echo esc_url( home_url( "/bangumi/{$next_ym}/" ) ); ?>" rel="next">
        下一季 <i class="fa-solid fa-arrow-right"></i>
      </a>
      <a class="bgm-archive" href="<?php echo esc_url( home_url( '/bangumi/archive/' ) ); ?>">
        <i class="fa-solid fa-folder-open"></i> 歷年存檔
      </a>
    </nav>
  </div>
</section>

<?php if ( $total > 0 && $uid > 0 ): ?>
<div class="bgm-stats">
  <div class="bgm-stat">
    <div class="bgm-stat-label">本季作品</div>
    <div class="bgm-stat-value"><?php echo $total; ?><small>部</small></div>
  </div>
  <div class="bgm-stat">
    <div class="bgm-stat-label">你已追番</div>
    <div class="bgm-stat-value"><?php echo $stat_mine; ?><small>/ <?php echo $total; ?></small></div>
  </div>
  <div class="bgm-stat">
    <div class="bgm-stat-label">追番中</div>
    <div class="bgm-stat-value"><?php echo $stat_watching; ?><small>部</small></div>
  </div>
  <div class="bgm-stat">
    <div class="bgm-stat-label">平均評分</div>
    <div class="bgm-stat-value"><?php echo $avg_score > 0 ? $avg_score : '—'; ?><small>/ 10</small></div>
  </div>
</div>
<?php endif; ?>

<main class="bgm-main">

  <div class="bgm-toolbar">
    <div class="bgm-weekday-bar" id="bgm-weekday-bar">
      <?php foreach ( $weekday_zh as $d => $label ):
        $cnt = count( $by_weekday[ $d ] );
        if ( $d > 0 && $cnt === 0 ) continue;
        $is_active = ( $d === $now_day || ( $d === 0 && $total === 0 ) );
      ?>
      <button class="bgm-day<?php echo $is_active ? ' active' : ''; ?>" data-day="<?php echo $d; ?>">
        <?php echo esc_html( $label ); ?>
        <?php if ( $cnt > 0 ): ?><span class="bgm-day-count"><?php echo $cnt; ?></span><?php endif; ?>
      </button>
      <?php endforeach; ?>
    </div>

    <div class="bgm-tools">
      <select class="bgm-sort" id="bgm-sort">
        <option value="default">預設排序</option>
        <option value="score">依評分</option>
        <option value="popularity">依人氣</option>
        <option value="ep">依集數</option>
      </select>
    </div>
  </div>

  <div class="bgm-grid" id="bgm-grid">
    <?php if ( empty( $all_posts ) ): ?>
      <div class="bgm-empty">
        <i class="fa-solid fa-calendar-xmark" style="font-size:32px;display:block;margin-bottom:12px;"></i>
        本季暫無資料，請稍後回來查看。
      </div>
    <?php else:
      foreach ( $by_weekday as $day_id => $posts ):
        if ( empty( $posts ) && $day_id > 0 ) continue;
        $is_visible_group = ( $day_id === $now_day || ( $day_id === 0 && $total === 0 ) );
    ?>
      <div class="bgm-group" data-group="<?php echo esc_attr( $day_id ); ?>" <?php echo $is_visible_group ? '' : 'hidden'; ?>>
        <?php foreach ( $posts as $p ):
            // 重用 member-render.php 的卡片（自動帶追番狀態、進度條、收藏愛心）
            if ( function_exists( 'smacg_render_anime_card' ) ) {
                smacg_render_anime_card( $p['pid'], [
                    'status'    => $p['user_status'],
                    'progress'  => $p['user_progress'],
                    'favorited' => $p['user_favorited'],
                ] );
            } else {
                // Fallback：smacg-members 外掛未啟用時
                ?>
                <a class="mc-anime-card" href="<?php echo esc_url( $p['url'] ); ?>">
                  <div class="mc-card-thumb">
                    <?php if ( $p['cover'] ): ?>
                      <img src="<?php echo esc_url( $p['cover'] ); ?>" alt="<?php echo esc_attr( $p['title'] ); ?>" loading="lazy">
                    <?php endif; ?>
                  </div>
                  <div class="mc-card-body">
                    <h4 class="mc-card-title"><?php echo esc_html( $p['title'] ); ?></h4>
                  </div>
                </a>
                <?php
            }
        endforeach; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>

</main>

<script>
(function () {
  const bar = document.getElementById('bgm-weekday-bar');
  const grid = document.getElementById('bgm-grid');
  const groups = grid.querySelectorAll('.bgm-group');

  /* 星期切換 */
  if (bar) {
    bar.addEventListener('click', function (e) {
      const btn = e.target.closest('.bgm-day');
      if (!btn) return;
      const day = parseInt(btn.dataset.day, 10);
      document.querySelectorAll('.bgm-day').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      groups.forEach(g => {
        g.hidden = (parseInt(g.dataset.group, 10) !== day);
      });
    });
  }

  /* 排序 */
  const sort = document.getElementById('bgm-sort');
  if (sort) {
    sort.addEventListener('change', function () {
      const mode = this.value;
      groups.forEach(group => {
        const cards = Array.from(group.querySelectorAll('.mc-anime-card, article'));
        cards.sort((a, b) => {
          const getScore = el => parseFloat((el.querySelector('.mc-card-score')?.textContent || '').replace(/[^0-9.]/g, '')) || 0;
          const getEp = el => parseInt((el.querySelector('.mc-card-meta span:nth-child(2)')?.textContent || '0'), 10) || 0;
          if (mode === 'score') return getScore(b) - getScore(a);
          if (mode === 'ep') return getEp(b) - getEp(a);
          return 0;
        });
        cards.forEach(c => group.appendChild(c));
      });
    });
  }
})();
</script>

<?php get_footer(); ?>
