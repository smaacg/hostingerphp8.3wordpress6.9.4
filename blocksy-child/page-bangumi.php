<?php
/**
 * Bangumi 番組表主頁面
 *
 * @package weixiaoacg
 * @version 1.2.0 (2026-05-18)
 *
 * 變更紀錄：
 *   1.2.0 (2026-05-18) 改用番組表專屬卡片 .bgm-card（不再呼叫 smacg_render_anime_card）
 *   1.1.0 (2026-05-18) 抽出 inline CSS / JS
 *   1.0.0 (2026-05-18) 初版
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 1. 解析 URL，建立季度上下文
 * ============================================================ */
$ym = (string) get_query_var( 'bangumi_ym' );
if ( ! $ym ) $ym = smacg_bangumi_current_ym();

$info     = smacg_bangumi_parse_ym( $ym );
$prev_ym  = smacg_bangumi_shift_ym( $ym, -1 );
$next_ym  = smacg_bangumi_shift_ym( $ym, +1 );
$cur_ym   = smacg_bangumi_current_ym();
$is_current_season = ( $ym === $cur_ym );

$canonical = home_url( "/bangumi/{$ym}/" );

/* ============================================================
 * 2. 查詢本季所有 anime
 * ============================================================ */
global $wpdb;
$uid = get_current_user_id();

$query = $wpdb->prepare(
    "SELECT p.ID,
            p.post_title,
            ms.meta_value  AS anime_status,
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
       INNER JOIN {$wpdb->postmeta} mse ON mse.post_id = p.ID AND mse.meta_key = 'anime_season'      AND mse.meta_value = %s
       INNER JOIN {$wpdb->postmeta} msy ON msy.post_id = p.ID AND msy.meta_key = 'anime_season_year' AND msy.meta_value = %d
       LEFT  JOIN {$wpdb->postmeta} ms  ON ms.post_id  = p.ID AND ms.meta_key  = 'anime_status'
       LEFT  JOIN {$wpdb->postmeta} mep ON mep.post_id = p.ID AND mep.meta_key = 'anime_episodes'
       LEFT  JOIN {$wpdb->postmeta} mea ON mea.post_id = p.ID AND mea.meta_key = 'anime_episodes_aired'
       LEFT  JOIN {$wpdb->postmeta} mna ON mna.post_id = p.ID AND mna.meta_key = 'anime_next_airing'
       LEFT  JOIN {$wpdb->postmeta} msd ON msd.post_id = p.ID AND msd.meta_key = 'anime_start_date'
       LEFT  JOIN {$wpdb->postmeta} mtj ON mtj.post_id = p.ID AND mtj.meta_key = 'anime_title_japanese'
       LEFT  JOIN {$wpdb->postmeta} mtc ON mtc.post_id = p.ID AND mtc.meta_key = 'anime_title_chinese'
       LEFT  JOIN {$wpdb->postmeta} mc  ON mc.post_id  = p.ID AND mc.meta_key  = 'anime_cover_image'
       LEFT  JOIN {$wpdb->postmeta} msc ON msc.post_id = p.ID AND msc.meta_key = 'anime_score_anilist'
       LEFT  JOIN {$wpdb->postmeta} mpo ON mpo.post_id = p.ID AND mpo.meta_key = 'anime_popularity'
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

$all_posts  = [];
$by_weekday = [ 0 => [] ];
for ( $i = 1; $i <= 7; $i++ ) $by_weekday[ $i ] = [];

$stat_total     = 0;
$stat_mine      = 0;
$stat_watching  = 0;
$stat_completed = 0;
$score_sum      = 0.0;
$score_n        = 0;

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

    $weekday = 0;
    if ( $r['anime_next_airing'] ) {
        $ts = strtotime( $r['anime_next_airing'] );
        if ( $ts ) $weekday = (int) date( 'N', $ts );
    }
    if ( ! $weekday && $r['anime_start_date'] ) {
        $ts = strtotime( $r['anime_start_date'] );
        if ( $ts ) $weekday = (int) date( 'N', $ts );
    }

    $u_status = isset( $r['user_status'] ) && $r['user_status'] !== null
                  ? ( $status_map[ (int) $r['user_status'] ] ?? '' )
                  : '';
    $u_progress = (int) $r['user_progress'];
    $u_favorite = (bool) $r['user_favorited'];

    $post_data = [
        'pid'            => $pid,
        'title'          => $title,
        'title_jp'       => $title_jp,
        'cover'          => $cover,
        'score'          => $score,
        'status'         => $status,
        'ep_total'       => $ep_total,
        'ep_aired'       => $ep_aired,
        'url'            => get_permalink( $pid ),
        'weekday'        => $weekday,
        'popularity'     => $popularity,
        'user_status'    => $u_status,
        'user_progress'  => $u_progress,
        'user_favorited' => $u_favorite,
    ];

    $all_posts[]     = $post_data;
    $by_weekday[0][] = $post_data;
    if ( $weekday >= 1 && $weekday <= 7 ) $by_weekday[ $weekday ][] = $post_data;

    $stat_total++;
    if ( $u_status )                 $stat_mine++;
    if ( $u_status === 'watching' )  $stat_watching++;
    if ( $u_status === 'completed' ) $stat_completed++;
    if ( $score !== null ) { $score_sum += $score; $score_n++; }
}

$avg_score = $score_n > 0 ? round( $score_sum / $score_n, 1 ) : 0;
$total     = count( $all_posts );

/* ============================================================
 * 4. SEO
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

add_action( 'wp_head', function () use ( $seo_ctx, $all_posts ) {
    smacg_bangumi_render_meta( $seo_ctx );
    smacg_bangumi_render_og( $seo_ctx );
    smacg_bangumi_render_schema( $seo_ctx, $all_posts );
}, 1 );

remove_action( 'wp_head', '_wp_render_title_tag', 1 );
add_filter( 'pre_get_document_title', function () use ( $seo_ctx ) {
    return $seo_ctx['title'];
} );

/* ============================================================
 * 5. 季度視覺主題
 * ============================================================ */
$theme_colors = [
    'SPRING' => [ 'main' => '#f9a8d4', 'soft' => 'rgba(249,168,212,0.15)', 'icon' => '🌸' ],
    'SUMMER' => [ 'main' => '#60a5fa', 'soft' => 'rgba(96,165,250,0.15)',  'icon' => '🌊' ],
    'FALL'   => [ 'main' => '#fb923c', 'soft' => 'rgba(251,146,60,0.15)',  'icon' => '🍁' ],
    'WINTER' => [ 'main' => '#67e8f9', 'soft' => 'rgba(103,232,249,0.15)', 'icon' => '❄️' ],
];
$theme = $theme_colors[ $info['season'] ];

add_filter( 'body_class', function ( $classes ) {
    $classes[] = 'is-bangumi-season';
    return $classes;
} );
add_action( 'wp_footer', function () use ( $theme ) {
    echo '<style id="bgm-vars">body.is-bangumi-season{--bgm-main:' . esc_attr( $theme['main'] ) . ';--bgm-soft:' . esc_attr( $theme['soft'] ) . ';}</style>';
}, 1 );

/* ============================================================
 * 6. 渲染
 * ============================================================ */
$now_day    = (int) date( 'N' );
$weekday_zh = [ 0 => '全部', 1 => '週一', 2 => '週二', 3 => '週三', 4 => '週四', 5 => '週五', 6 => '週六', 7 => '週日' ];

$card_status_label = [
    'watching'  => '追番中',
    'want'      => '想看',
    'completed' => '已完結',
    'dropped'   => '已棄',
];
$weekday_short = [ 1 => '一', 2 => '二', 3 => '三', 4 => '四', 5 => '五', 6 => '六', 7 => '日' ];

get_header();
?>

<?php smacg_bangumi_render_breadcrumb( $seo_ctx ); ?>

<section class="bgm-hero">
  <div class="bgm-hero-inner">
    <div class="bgm-hero-badge">
      <i class="fa-solid fa-calendar-week" aria-hidden="true"></i>
      <?php echo $is_current_season ? '本季新番' : '歷史季度'; ?>
    </div>
    <h1 class="bgm-hero-title">
      <span class="bgm-hero-icon"><?php echo $theme['icon']; ?></span>
      <?php echo esc_html( $info['label'] ); ?>
    </h1>
    <p class="bgm-hero-sub">依星期瀏覽 <?php echo (int) $total; ?> 部作品，資料來源：站內資料庫</p>

    <nav class="bgm-season-nav" aria-label="季度切換">
      <a href="<?php echo esc_url( home_url( "/bangumi/{$prev_ym}/" ) ); ?>" rel="prev">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> 上一季
      </a>
      <?php if ( ! $is_current_season ): ?>
      <a href="<?php echo esc_url( home_url( "/bangumi/{$cur_ym}/" ) ); ?>">
        <i class="fa-solid fa-house" aria-hidden="true"></i> 本季
      </a>
      <?php endif; ?>
      <a href="<?php echo esc_url( home_url( "/bangumi/{$next_ym}/" ) ); ?>" rel="next">
        下一季 <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
      </a>
      <a class="bgm-archive" href="<?php echo esc_url( home_url( '/bangumi/archive/' ) ); ?>">
        <i class="fa-solid fa-folder-open" aria-hidden="true"></i> 歷年存檔
      </a>
    </nav>
  </div>
</section>

<?php if ( $total > 0 && $uid > 0 ): ?>
<div class="bgm-stats">
  <div class="bgm-stat">
    <div class="bgm-stat-label">本季作品</div>
    <div class="bgm-stat-value"><?php echo (int) $total; ?><small>部</small></div>
  </div>
  <div class="bgm-stat">
    <div class="bgm-stat-label">你已追番</div>
    <div class="bgm-stat-value"><?php echo (int) $stat_mine; ?><small>/ <?php echo (int) $total; ?></small></div>
  </div>
  <div class="bgm-stat">
    <div class="bgm-stat-label">追番中</div>
    <div class="bgm-stat-value"><?php echo (int) $stat_watching; ?><small>部</small></div>
  </div>
  <div class="bgm-stat">
    <div class="bgm-stat-label">平均評分</div>
    <div class="bgm-stat-value"><?php echo $avg_score > 0 ? esc_html( (string) $avg_score ) : '—'; ?><small>/ 10</small></div>
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
      <button class="bgm-day<?php echo $is_active ? ' active' : ''; ?>" type="button" data-day="<?php echo (int) $d; ?>">
        <?php echo esc_html( $label ); ?>
        <?php if ( $cnt > 0 ): ?><span class="bgm-day-count"><?php echo (int) $cnt; ?></span><?php endif; ?>
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
        <i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i>
        本季暫無資料，請稍後回來查看。
      </div>
    <?php else:
      foreach ( $by_weekday as $day_id => $posts ):
        if ( empty( $posts ) && $day_id > 0 ) continue;
        $is_visible_group = ( $day_id === $now_day || ( $day_id === 0 && $total === 0 ) );
    ?>
      <div class="bgm-group" data-group="<?php echo (int) $day_id; ?>" <?php echo $is_visible_group ? '' : 'hidden'; ?>>
        <?php foreach ( $posts as $p ):
            $is_hot       = ( $p['score'] !== null && $p['score'] >= 8.0 );
            $progress_pct = ( $p['ep_total'] > 0 && $p['user_progress'] > 0 )
                            ? min( 100, round( $p['user_progress'] / $p['ep_total'] * 100 ) )
                            : 0;
        ?>
        <a class="bgm-card<?php echo $p['user_status'] ? ' has-status status-' . esc_attr( $p['user_status'] ) : ''; ?>"
           href="<?php echo esc_url( $p['url'] ); ?>"
           data-score="<?php echo esc_attr( (string) ( $p['score'] ?? 0 ) ); ?>"
           data-ep="<?php echo esc_attr( (string) $p['ep_total'] ); ?>"
           data-pop="<?php echo esc_attr( (string) $p['popularity'] ); ?>">

          <div class="bgm-card-cover">
            <?php if ( $p['cover'] ): ?>
              <img src="<?php echo esc_url( $p['cover'] ); ?>"
                   alt="<?php echo esc_attr( $p['title'] ); ?>"
                   loading="lazy">
            <?php else: ?>
              <div class="bgm-card-cover-ph"><i class="fa-solid fa-film" aria-hidden="true"></i></div>
            <?php endif; ?>

            <?php if ( $p['score'] !== null ): ?>
              <div class="bgm-card-score<?php echo $is_hot ? ' is-hot' : ''; ?>">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                <?php echo esc_html( (string) $p['score'] ); ?>
              </div>
            <?php endif; ?>

            <?php if ( $p['user_status'] && isset( $card_status_label[ $p['user_status'] ] ) ): ?>
              <div class="bgm-card-chip"><?php echo esc_html( $card_status_label[ $p['user_status'] ] ); ?></div>
            <?php endif; ?>

            <div class="bgm-card-overlay">
              <?php if ( $p['weekday'] >= 1 && $p['weekday'] <= 7 ): ?>
                <span class="bgm-card-day"><i class="fa-solid fa-calendar" aria-hidden="true"></i> 週<?php echo esc_html( $weekday_short[ $p['weekday'] ] ); ?></span>
              <?php endif; ?>
              <?php if ( $p['ep_total'] > 0 ): ?>
                <span class="bgm-card-ep">
                  <?php if ( $p['ep_aired'] > 0 && $p['ep_aired'] < $p['ep_total'] ): ?>
                    <?php echo (int) $p['ep_aired']; ?>/<?php echo (int) $p['ep_total']; ?> 集
                  <?php else: ?>
                    <?php echo (int) $p['ep_total']; ?> 集
                  <?php endif; ?>
                </span>
              <?php endif; ?>
            </div>

            <?php if ( $progress_pct > 0 ): ?>
              <div class="bgm-card-progress"><span style="width:<?php echo (int) $progress_pct; ?>%"></span></div>
            <?php endif; ?>
          </div>

          <div class="bgm-card-meta">
            <h3 class="bgm-card-title"><?php echo esc_html( $p['title'] ); ?></h3>
            <?php if ( $p['title_jp'] ): ?>
              <p class="bgm-card-jp"><?php echo esc_html( $p['title_jp'] ); ?></p>
            <?php endif; ?>
          </div>

        </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>

</main>

<?php get_footer(); ?>
