<?php get_header(); ?>

<!-- ============================================================
     HERO
     ============================================================ -->
<?php
/* ── Hero 海報：手動指定圖片連結 ── */
$hero_posters = [
    [
        'img'   => 'https://darkcyan-alpaca-757238.hostingersite.com/wp-content/uploads/2026/05/5jrnQqTH.png',
        'title' => '動漫標題一',
        'url'   => home_url('/anime/'),
    ],
    [
        'img'   => 'https://darkcyan-alpaca-757238.hostingersite.com/wp-content/uploads/2026/05/NtUk2v3C.png',
        'title' => '動漫標題二',
        'url'   => ('https://discord.com/invite/yw73RBZgss'),
    ],
    [
        'img'   => 'https://darkcyan-alpaca-757238.hostingersite.com/wp-content/uploads/2026/05/zyYiYfQY.png',
        'title' => '動漫標題三',
        'url'   => ('https://darkcyan-alpaca-757238.hostingersite.com/join/'),
    ],
];
?>

<section class="hero-section" id="hero">
  <div class="hero-bg-layer" id="hero-bg"></div>
  <div class="hero-noise"></div>
  <div class="container hero-content-wrap">

    <div class="hero-text">
<div class="hero-eyebrow">
  <a href="<?php echo esc_url( home_url('/bangumi/') ); ?>" class="chip active chip-link">
    <i class="fa-solid fa-calendar-days"></i> 動漫新番表
  </a>
  <a href="<?php echo esc_url( home_url('/about/') ); ?>" class="chip chip-link">
    <i class="fa-solid fa-circle-info"></i> 關於微笑動漫
  </a>
</div>
      <h1 class="hero-title">
        成功不是<br>
        <span class="line-gradient">一蹴而就</span><br>
        <span class="line-accent">而是每天的努力</span>
      </h1>
      <p class="hero-subtitle">
        —— 史蒂芬·柯維<br />
      </p>

      <!-- 毛玻璃時鐘 -->
      <div class="hero-stats">
        <div class="hero-clock glass">
          <div class="hero-clock-time" id="hero-clock-time">--:--:--</div>
          <div class="hero-clock-bottom">
            <span class="hero-clock-date" id="hero-clock-date">---- / -- / --</span>
            <span class="hero-clock-sep">・</span>
            <span class="hero-clock-weekday" id="hero-clock-weekday">---</span>
          </div>
        </div>
      </div>

      <div class="hero-actions">
        <a href="#season-section" class="btn btn-primary">
          <i class="fa-solid fa-calendar-check"></i> 本季新番
        </a>
        <a href="<?php echo esc_url( home_url('/?smacg_random_anime=1') ); ?>"
           class="btn btn-secondary"
           rel="nofollow">
          <i class="fa-solid fa-dice"></i> 抽一部動漫
        </a>
      </div>
    </div>

    <!-- Hero 海報 -->
    <div class="hero-posters" id="hero-posters">
      <?php foreach ( $hero_posters as $poster ) : ?>
      <a href="<?php echo esc_url( $poster['url'] ); ?>"
         class="poster-item glass"
         title="<?php echo esc_attr( $poster['title'] ); ?>">
        <img src="<?php echo esc_url( $poster['img'] ); ?>"
             alt="<?php echo esc_attr( $poster['title'] ); ?>"
             loading="lazy"
             onerror="this.style.display='none';this.closest('.poster-item').classList.add('skeleton');">
        <span class="poster-item__title"><?php echo esc_html( $poster['title'] ); ?></span>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<style>
/* ── chip 可點擊版本（關於微笑） ── */
.hero-eyebrow {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.chip.chip-link {
    text-decoration: none;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
    color: rgba(220,230,245,.75);
    transition: all .2s ease;
    cursor: pointer;
}
.chip.chip-link:hover {
    background: rgba(99,168,255,.18);
    border-color: rgba(99,168,255,.45);
    color: #63a8ff;
    transform: translateY(-1px);
}
</style>

<script>
(function () {
    const timeEl   = document.getElementById('hero-clock-time');
    const dateEl   = document.getElementById('hero-clock-date');
    const weekEl   = document.getElementById('hero-clock-weekday');
    const weekdays = ['星期日','星期一','星期二','星期三','星期四','星期五','星期六'];

    function tick() {
        const now = new Date();
        const hh  = String(now.getHours()).padStart(2, '0');
        const mm  = String(now.getMinutes()).padStart(2, '0');
        const ss  = String(now.getSeconds()).padStart(2, '0');
        const y   = now.getFullYear();
        const mo  = String(now.getMonth() + 1).padStart(2, '0');
        const d   = String(now.getDate()).padStart(2, '0');

        if (timeEl) timeEl.textContent = `${hh}：${mm}：${ss}`;
        if (dateEl) dateEl.textContent = `${y} / ${mo} / ${d}`;
        if (weekEl) weekEl.textContent = weekdays[now.getDay()];
    }

    tick();
    setInterval(tick, 1000);
})();
</script>

<!-- ============================================================
     最新新聞
     ============================================================ -->
<section class="section" id="news-section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <i class="fa-solid fa-newspaper" style="margin-right:8px;"></i>最新新聞
      </h2>
      <a href="<?php echo esc_url( home_url('/news/') ); ?>" class="section-link">
        查看全部 <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <?php
    $news_all = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => 6,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    /* ── 跑馬燈標題 ── */
    $ticker_items = [];
    if ( $news_all->have_posts() ) {
        foreach ( $news_all->posts as $p ) {
            $ticker_items[] = esc_html( $p->post_title );
        }
    } else {
        $ticker_items = [
            'SPY×FAMILY Season 3 製作確認',
            '進擊的巨人 OST 原聲帶全球發行',
            '台灣 ACG 展覽 2026 舉辦日期公布',
            '咒術迴戰最終章動畫化正式宣布',
            'LiSA 台灣演唱會門票即日起開放購票',
        ];
    }
    ?>

    <!-- 跑馬燈 -->
    <div class="news-ticker-wrap">
      <span class="news-ticker-label"><i class="fa-solid fa-bolt"></i> 快訊</span>
      <div class="news-ticker-overflow">
        <div class="news-ticker-track" id="tickerTrack">
          <?php foreach ( $ticker_items as $item ) : ?>
            <span><?php echo $item; ?>&nbsp;&nbsp;·&nbsp;&nbsp;</span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- 6 欄卡片網格 -->
    <?php if ( $news_all->have_posts() ) : ?>
    <div class="news-grid">
      <?php while ( $news_all->have_posts() ) : $news_all->the_post();
        $nid       = get_the_ID();
        $cats      = get_the_category( $nid );
        $cat_label = ! empty( $cats ) ? esc_html( $cats[0]->name ) : '新聞';
        $ntime     = human_time_diff( get_the_time('U'), current_time('timestamp') ) . '前';

        /* ── 封面圖：三層 fallback ── */
        $thumb = '';
        if ( function_exists('weixiaoacg_get_news_thumb') ) {
            $thumb = weixiaoacg_get_news_thumb( $nid, 'news-thumb' );
        }
        if ( ! $thumb ) {
            $thumb = get_the_post_thumbnail_url( $nid, 'medium' );
        }
        if ( ! $thumb ) {
            preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', get_the_content(), $img_match );
            $thumb = $img_match[1] ?? '';
        }
      ?>
      <a href="<?php the_permalink(); ?>" class="news-card glass">

        <!-- 封面圖區 -->
        <div class="news-card__thumb">
          <?php if ( $thumb ) : ?>
            <img src="<?php echo esc_url( $thumb ); ?>"
                 alt="<?php the_title_attribute(); ?>"
                 loading="lazy"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="news-card__placeholder" style="display:none">
              <i class="fa-solid fa-newspaper"></i>
            </div>
          <?php else : ?>
            <div class="news-card__placeholder">
              <i class="fa-solid fa-newspaper"></i>
            </div>
          <?php endif; ?>
          <span class="news-card__cat news-tag tag-rose"><?php echo $cat_label; ?></span>
        </div>

        <!-- 文字區 -->
        <div class="news-card__body">
          <h3 class="news-card__title"><?php the_title(); ?></h3>
          <div class="news-card__meta">
            <span><i class="fa-regular fa-clock"></i> <?php echo $ntime; ?></span>
          </div>
        </div>

      </a>
      <?php endwhile; wp_reset_postdata(); ?>
    </div><!-- .news-grid -->

    <?php else : ?>
    <div class="news-empty glass-mid">
      <span style="font-size:2rem;">📭</span>
      <p>目前尚無新聞，請稍後回來查看。</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<style>
/* ================================================================
   新聞 6 欄卡片網格
   ================================================================ */
.news-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 24px;
}
@media (max-width: 1024px) {
    .news-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 540px) {
    .news-grid { grid-template-columns: 1fr; gap: 14px; }
}

.news-card {
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    transition: transform .2s ease, box-shadow .2s ease;
}
.news-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 32px rgba(0,0,0,.45);
}

.news-card__thumb {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: rgba(255,255,255,.06);
    flex-shrink: 0;
}
.news-card__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .35s ease;
}
.news-card:hover .news-card__thumb img {
    transform: scale(1.06);
}

.news-card__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(99,168,255,.12), rgba(168,99,255,.10));
    color: rgba(255,255,255,.25);
    font-size: 36px;
}

.news-card__cat {
    position: absolute;
    top: 10px;
    left: 10px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    backdrop-filter: blur(6px);
}

.news-card__body {
    padding: 14px 16px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}
.news-card__title {
    font-size: 14px;
    font-weight: 700;
    color: rgba(220,230,245,.90);
    margin: 0;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.news-card__meta {
    margin-top: auto;
    font-size: 12px;
    color: rgba(220,230,245,.4);
    display: flex;
    align-items: center;
    gap: 6px;
}
</style>

<!-- ============================================================
     動漫新番表（原「本季新番導航」）
     ============================================================ -->
<?php
/* ── 動態計算當前季度 ── */
$_current_month = (int) date('n');
$_current_year  = (int) date('Y');
$_current_day   = (int) date('N'); // 1=週一…7=週日

if ( $_current_month <= 3 )      { $_current_season = 'WINTER'; }
elseif ( $_current_month <= 6 )  { $_current_season = 'SPRING'; }
elseif ( $_current_month <= 9 )  { $_current_season = 'SUMMER'; }
else                              { $_current_season = 'FALL';   }

$_weekday_zh = [ 0=>'全部', 1=>'週一', 2=>'週二', 3=>'週三', 4=>'週四', 5=>'週五', 6=>'週六', 7=>'週日' ];

/* ── 抓取本季動漫 ── */
$_season_query = new WP_Query( [
    'post_type'      => 'anime',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'no_found_rows'  => true,
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => 'anime_season',        'value' => $_current_season, 'compare' => '='          ],
        [ 'key' => 'anime_season_year',   'value' => $_current_year,   'compare' => '=', 'type' => 'NUMERIC' ],
        [ 'key' => 'anime_title_chinese', 'value' => '',               'compare' => '!='         ],
        [ 'key' => 'anime_cover_image',   'value' => '',               'compare' => '!='         ],
    ],
] );

/* ── 整理資料並分組 ── */
$_by_weekday = [ 0 => [] ];
for ( $i = 1; $i <= 7; $i++ ) $_by_weekday[$i] = [];

if ( $_season_query->have_posts() ) :
    while ( $_season_query->have_posts() ) : $_season_query->the_post();
        $pid = get_the_ID();

        if ( strpos( get_post_field( 'post_name', $pid ), '.html' ) !== false ) continue;

        $title    = get_post_meta( $pid, 'anime_title_chinese', true ) ?: get_the_title();
        $title_jp = get_post_meta( $pid, 'anime_title_japanese', true ) ?: '';
        $cover    = get_post_meta( $pid, 'anime_cover_image', true )
                    ?: get_the_post_thumbnail_url( $pid, 'medium' );

        $site_score  = (float) get_post_meta( $pid, 'smacg_site_score', true );
        $anilist_raw = (float) get_post_meta( $pid, 'anime_score_anilist', true );
        if ( $site_score > 0 ) {
            $score = number_format( $site_score, 1 );
        } elseif ( $anilist_raw > 0 ) {
            $score = number_format( $anilist_raw / 10, 1 );
        } else {
            $score = '';
        }

        $status   = get_post_meta( $pid, 'anime_status', true );
        $ep_total = (int) get_post_meta( $pid, 'anime_episodes', true );
        $ep_aired = (int) get_post_meta( $pid, 'anime_episodes_aired', true );

        $ep_label = '';
        if ( $ep_total > 0 ) {
            $ep_label = ( $ep_aired > 0 && $ep_aired < $ep_total )
                ? "{$ep_aired}/{$ep_total} 集"
                : "{$ep_total} 集";
        } elseif ( $ep_aired > 0 ) {
            $ep_label = "第 {$ep_aired} 集";
        }

        $weekday     = 0;
        $next_airing = get_post_meta( $pid, 'anime_next_airing', true );
        if ( $next_airing ) {
            $ts = strtotime( $next_airing );
            if ( $ts ) $weekday = (int) date( 'N', $ts );
        }
        if ( ! $weekday ) {
            $start = get_post_meta( $pid, 'anime_start_date', true );
            if ( $start ) {
                $ts = strtotime( $start );
                if ( $ts ) $weekday = (int) date( 'N', $ts );
            }
        }

        $post_data = [
            'pid'      => $pid,
            'title'    => $title,
            'title_jp' => $title_jp,
            'cover'    => $cover,
            'score'    => $score,
            'status'   => $status,
            'ep_label' => $ep_label,
            'weekday'  => $weekday,
            'url'      => get_permalink( $pid ),
        ];

        $_by_weekday[0][] = $post_data;
        if ( $weekday >= 1 && $weekday <= 7 ) $_by_weekday[$weekday][] = $post_data;

    endwhile;
    wp_reset_postdata();
endif;

$_season_total = count( $_by_weekday[0] );
?>

<section class="section season-section" id="season-section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <i class="fa-solid fa-calendar-days" style="margin-right:8px;"></i> 本季新番
      </h2>

      <!-- 星期 Tabs -->
      <div class="tab-switch weekday-tabs" id="weekday-tabs">
        <?php foreach ( $_weekday_zh as $d => $label ) :
          $cnt = count( $_by_weekday[$d] );
          if ( $d > 0 && $cnt === 0 ) continue;
          $is_active = ( $d === $_current_day ) ? ' active' : ( $d === 0 && $_current_day === 0 ? ' active' : '' );
        ?>
        <button class="tab-btn weekday-tab<?php echo $is_active; ?>"
                data-day="<?php echo $d; ?>">
          <?php echo esc_html( $label ); ?>
          <?php if ( $d > 0 && $cnt > 0 ) : ?>
            <span style="font-size:10px;opacity:.65;margin-left:3px;"><?php echo $cnt; ?></span>
          <?php endif; ?>
        </button>
        <?php endforeach; ?>
      </div>

      <a href="<?php echo esc_url( home_url('/season/') ); ?>" class="section-link">
        看完整新番表 <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <?php if ( $_season_total > 0 ) : ?>
    <div class="season-cards scroll-row" id="season-cards">

      <?php foreach ( $_by_weekday as $_day_id => $_day_posts ) :
        $is_current = ( $_day_id === $_current_day ) || ( $_day_id === 0 && $_current_day === 0 );
      ?>
      <div class="sf-day-group"
           data-group="<?php echo esc_attr( $_day_id ); ?>"
           style="display:<?php echo $is_current ? 'contents' : 'none'; ?>;">

        <?php foreach ( $_day_posts as $p ) :
          $is_airing = ( $p['status'] === 'RELEASING' );
          $day_label = ( $_day_id === 0 && $p['weekday'] >= 1 ) ? $_weekday_zh[ $p['weekday'] ] : '';
        ?>
        <a href="<?php echo esc_url( $p['url'] ); ?>"
           class="season-card glass"
           data-day="<?php echo esc_attr( $p['weekday'] ); ?>"
           title="<?php echo esc_attr( $p['title'] ); ?>">

          <?php if ( $day_label ) : ?>
            <span class="season-card-day-badge"><?php echo esc_html( $day_label ); ?></span>
          <?php endif; ?>
          <?php if ( $is_airing ) : ?>
            <span class="season-card-airing"></span>
          <?php endif; ?>

          <div class="season-card__cover-wrap">
            <?php if ( $p['cover'] ) : ?>
              <img src="<?php echo esc_url( $p['cover'] ); ?>"
                   alt="<?php echo esc_attr( $p['title'] ); ?>"
                   class="season-card-img" loading="lazy"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <div class="season-card-img-ph" style="display:none;">🎬</div>
            <?php else : ?>
              <div class="season-card-img-ph">🎬</div>
            <?php endif; ?>

            <span class="season-card__status <?php echo $is_airing ? 'status--on-air' : 'status--finished'; ?>">
              <?php echo $is_airing ? '連載中' : '完結'; ?>
            </span>
          </div>

          <div class="season-card-body">
            <div class="season-card-title"><?php echo esc_html( $p['title'] ); ?></div>
            <?php if ( $p['title_jp'] && $p['title_jp'] !== $p['title'] ) : ?>
              <div class="season-card-jp"><?php echo esc_html( $p['title_jp'] ); ?></div>
            <?php endif; ?>
            <div class="season-card-meta">
              <?php if ( $p['score'] ) : ?>
                <span class="season-card-score">★ <?php echo esc_html( $p['score'] ); ?></span>
              <?php endif; ?>
              <?php if ( $p['ep_label'] ) : ?>
                <span class="season-card-ep" style="font-size:11px;color:var(--text-muted);">
                  <?php echo esc_html( $p['ep_label'] ); ?>
                </span>
              <?php endif; ?>
            </div>
          </div>

        </a>
        <?php endforeach; ?>

      </div><!-- .sf-day-group -->
      <?php endforeach; ?>

    </div><!-- #season-cards -->

    <?php else : ?>
    <div class="season-empty glass">
      <i class="fa-solid fa-calendar-xmark fa-2x" aria-hidden="true"></i>
      <p>本季新番資料準備中，敬請期待。</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<script>
(function () {
    const tabs   = document.querySelectorAll('#weekday-tabs .weekday-tab');
    const groups = document.querySelectorAll('#season-cards .sf-day-group');
    if ( !tabs.length ) return;

    function showDay(day) {
        tabs.forEach(t => t.classList.toggle('active', t.dataset.day == day));
        groups.forEach(function(g) {
            const match = parseInt(g.dataset.group) === parseInt(day);
            g.style.display = match ? 'contents' : 'none';
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            showDay(this.dataset.day);
        });
    });

    const jsDay = new Date().getDay();
    const today = jsDay === 0 ? 7 : jsDay;
    const todayTab = document.querySelector(`#weekday-tabs .weekday-tab[data-day="${today}"]`);
    if (todayTab) {
        showDay(today);
    } else {
        showDay(0);
    }
})();
</script>

<!-- ============================================================
     熱門作品
     ============================================================ -->
<?php
/* ── 動態計算：下一季 ── */
$current_month = (int) date('n');
$current_year  = (int) date('Y');

if ( $current_month >= 1 && $current_month <= 3 ) {
    $next_season = 'SPRING'; $next_year = $current_year;
} elseif ( $current_month >= 4 && $current_month <= 6 ) {
    $next_season = 'SUMMER'; $next_year = $current_year;
} elseif ( $current_month >= 7 && $current_month <= 9 ) {
    $next_season = 'FALL';   $next_year = $current_year;
} else {
    $next_season = 'WINTER'; $next_year = $current_year + 1;
}

if ( ! function_exists( 'smacg_get_anime' ) ) {
    function smacg_get_anime( $orderby_meta, $order = 'DESC', $extra_meta = [] ) {
        $args = [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'meta_key'       => $orderby_meta,
            'orderby'        => 'meta_value_num',
            'order'          => $order,
            'no_found_rows'  => true,
        ];
        if ( ! empty( $extra_meta ) ) {
            $args['meta_query'] = array_merge( [ 'relation' => 'AND' ], $extra_meta );
        }
        return new WP_Query( $args );
    }
}

if ( ! function_exists( 'smacg_anime_card' ) ) {
    function smacg_anime_card( $post ) {
        $id    = $post->ID;
        $title = get_post_meta( $id, 'anime_title_chinese', true ) ?: $post->post_title;
        $cover = get_post_meta( $id, 'anime_cover_image', true );
        $score = get_post_meta( $id, 'anime_score_site', true );
        $url   = get_permalink( $id );

        $score_display = $score ? number_format( (float) $score, 1 ) : null;
        $fb = mb_substr( $title, 0, 2 );
        ?>
        <a href="<?php echo esc_url( $url ); ?>" class="smacg-anime-card">
            <div class="smacg-card-thumb">
                <?php if ( $cover ) : ?>
                    <img src="<?php echo esc_url( $cover ); ?>"
                         alt="<?php echo esc_attr( $title ); ?>"
                         loading="lazy"
                         onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="smacg-card-fb" style="display:none"><span><?php echo esc_html( $fb ); ?></span></div>
                <?php else : ?>
                    <div class="smacg-card-fb"><span><?php echo esc_html( $fb ); ?></span></div>
                <?php endif; ?>
                <?php if ( $score_display ) : ?>
                    <span class="smacg-card-score">
                        <i class="fa-solid fa-star"></i> <?php echo esc_html( $score_display ); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="smacg-card-body">
                <h3 class="smacg-card-title"><?php echo esc_html( $title ); ?></h3>
            </div>
        </a>
        <?php
    }
}
?>

<section class="section" id="hot-anime-section">
  <div class="container">

    <div class="section-header">
      <h2 class="section-title">熱門作品</h2>
      <div class="tab-switch">
        <button class="smacg-tab-btn active" data-tab="trending">大家都在看</button>
        <button class="smacg-tab-btn" data-tab="top">歷年神作</button>
        <button class="smacg-tab-btn" data-tab="upcoming">即將開播</button>
      </div>
      <a href="<?php echo esc_url( home_url('/anime/') ); ?>" class="section-link">
        更多作品 <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <div class="smacg-anime-grid" id="smacg-tab-trending">
        <?php
        $q = smacg_get_anime( 'anime_score_site_count' );
        if ( $q->have_posts() ) :
            while ( $q->have_posts() ) { $q->the_post(); smacg_anime_card( get_post() ); }
            wp_reset_postdata();
        else : ?>
            <p class="smacg-tab-empty">暫無資料</p>
        <?php endif; ?>
    </div>

    <div class="smacg-anime-grid" id="smacg-tab-top" style="display:none">
        <?php
        $q = smacg_get_anime( 'anime_score_site' );
        if ( $q->have_posts() ) :
            while ( $q->have_posts() ) { $q->the_post(); smacg_anime_card( get_post() ); }
            wp_reset_postdata();
        else : ?>
            <p class="smacg-tab-empty">暫無資料</p>
        <?php endif; ?>
    </div>

    <div class="smacg-anime-grid" id="smacg-tab-upcoming" style="display:none">
        <?php
        $q = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'anime_season',      'value' => $next_season, 'compare' => '=' ],
                [ 'key' => 'anime_season_year', 'value' => $next_year,   'compare' => '=', 'type' => 'NUMERIC' ],
            ],
        ] );
        if ( $q->have_posts() ) :
            while ( $q->have_posts() ) { $q->the_post(); smacg_anime_card( get_post() ); }
            wp_reset_postdata();
        else : ?>
            <p class="smacg-tab-empty">下一季暫無資料</p>
        <?php endif; ?>
    </div>

  </div>
</section>

<style>
.smacg-anime-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    margin-top: 20px;
}
@media (max-width: 1024px) { .smacg-anime-grid { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 768px)  { .smacg-anime-grid { grid-template-columns: repeat(3, 1fr); gap: 12px; } }
@media (max-width: 480px)  { .smacg-anime-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; } }

.smacg-anime-card { display: flex; flex-direction: column; border-radius: 16px; overflow: hidden; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); text-decoration: none; transition: transform .2s ease, box-shadow .2s ease; }
.smacg-anime-card:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(0,0,0,.4); }
.smacg-card-thumb { position: relative; width: 100%; aspect-ratio: 3/4; overflow: hidden; background: rgba(255,255,255,.06); }
.smacg-card-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .3s ease; }
.smacg-anime-card:hover .smacg-card-thumb img { transform: scale(1.05); }
.smacg-card-fb { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; color: rgba(255,255,255,.3); background: rgba(255,255,255,.04); }
.smacg-card-score { position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,.75); color: #ffd60a; font-size: 12px; font-weight: 700; padding: 3px 8px; border-radius: 20px; backdrop-filter: blur(6px); display: flex; align-items: center; gap: 3px; }
.smacg-card-body { padding: 10px 12px 12px; }
.smacg-card-title { font-size: 13px; font-weight: 600; color: rgba(220,230,245,.85); margin: 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.smacg-tab-btn { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); color: rgba(220,230,245,.55); padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s ease; }
.smacg-tab-btn:hover { background: rgba(255,255,255,.10); color: rgba(220,230,245,.85); }
.smacg-tab-btn.active { background: rgba(99,168,255,.20); border-color: rgba(99,168,255,.45); color: #63a8ff; }
.smacg-tab-empty { color: rgba(220,230,245,.4); font-size: 14px; padding: 40px 0; text-align: center; grid-column: 1 / -1; }
</style>

<script>
(function () {
    const btns   = document.querySelectorAll('#hot-anime-section .smacg-tab-btn');
    const panels = {
        trending : document.getElementById('smacg-tab-trending'),
        top      : document.getElementById('smacg-tab-top'),
        upcoming : document.getElementById('smacg-tab-upcoming'),
    };

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = this.dataset.tab;
            btns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            Object.keys(panels).forEach(function (key) {
                panels[key].style.display = key === target ? 'grid' : 'none';
            });
        });
    });
})();
</script>

<!-- ============================================================
     未來場景 Coming Soon
     ============================================================ -->
<section class="section coming-soon-section">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">微笑動漫未來場景</h2>
        <p style="font-size:13px; color:var(--text-muted); margin-top:6px;">更多精彩，陸續展開</p>
      </div>
    </div>
    <div class="coming-cards-grid">

      <div class="coming-card glass">
        <div class="coming-card-icon">🎵</div>
        <div class="coming-card-title">動漫音樂</div>
        <div class="coming-card-desc">OP/ED 主題曲・OST 原聲帶・聲優演唱會情報</div>
        <span class="coming-card-btn"><i class="fa-solid fa-clock"></i> 敬請期待</span>
      </div>

      <div class="coming-card glass">
        <div class="coming-card-icon">🎭</div>
        <div class="coming-card-title">Cosplayer</div>
        <div class="coming-card-desc">人氣 Coser 介紹・活動場照・作品妝造解析</div>
        <span class="coming-card-btn"><i class="fa-solid fa-clock"></i> 敬請期待</span>
      </div>

      <div class="coming-card glass">
        <div class="coming-card-icon">📺</div>
        <div class="coming-card-title">Vtuber</div>
        <div class="coming-card-desc">主流 Vtuber 介紹・熱門剪輯・直播動態</div>
        <span class="coming-card-btn"><i class="fa-solid fa-clock"></i> 敬請期待</span>
      </div>

      <div class="coming-card glass">
        <div class="coming-card-icon">📚</div>
        <div class="coming-card-title">漫畫小說</div>
        <div class="coming-card-desc">原作漫畫・輕小說・改編作品對照</div>
        <span class="coming-card-btn"><i class="fa-solid fa-clock"></i> 敬請期待</span>
      </div>

    </div>
  </div>
</section>

<!-- ============================================================
     會員 CTA（v2.0 — 改用 level-guide 真實 6 階資料）
     ============================================================ -->
<?php
/* ── 6 階會員稱號（與 level-guide 同步） ── */
$smacg_tiers = [
    [ 'tier' => 1, 'key' => 'rookie',   'title' => '新進會員', 'icon' => '🌱', 'color' => '#8d99ae', 'min_level' => 1,   'min_exp' => 5,      'tag' => 'tag-cyan'   ],
    [ 'tier' => 2, 'key' => 'newcomer', 'title' => '新客',     'icon' => '🌿', 'color' => '#06a77d', 'min_level' => 10,  'min_exp' => 500,    'tag' => 'tag-green'  ],
    [ 'tier' => 3, 'key' => 'regular',  'title' => '常客',     'icon' => '📺', 'color' => '#3a86ff', 'min_level' => 30,  'min_exp' => 4500,   'tag' => 'tag-blue'   ],
    [ 'tier' => 4, 'key' => 'expert',   'title' => '熟客',     'icon' => '🎬', 'color' => '#6a4c93', 'min_level' => 70,  'min_exp' => 24500,  'tag' => 'tag-purple' ],
    [ 'tier' => 5, 'key' => 'vip',      'title' => 'VIP',      'icon' => '👑', 'color' => '#b8860b', 'min_level' => 120, 'min_exp' => 72000,  'tag' => 'tag-orange' ],
    [ 'tier' => 6, 'key' => 'black',    'title' => '黑卡會員', 'icon' => '💎', 'color' => '#1a1a1a', 'min_level' => 200, 'min_exp' => 200000, 'tag' => 'tag-locked' ],
];

/* ── 已登入用戶：取得當前等級資訊 ── */
$smacg_user_info = null;
if ( is_user_logged_in() && function_exists( 'smacg_get_user_level_info' ) ) {
    $smacg_user_info = smacg_get_user_level_info( get_current_user_id() );
}
$smacg_user_level    = is_array( $smacg_user_info ) ? (int) ( $smacg_user_info['level']    ?? 0 ) : 0;
$smacg_user_exp      = is_array( $smacg_user_info ) ? (int) ( $smacg_user_info['exp']      ?? 0 ) : 0;
$smacg_user_tier_key = is_array( $smacg_user_info ) ? (string) ( $smacg_user_info['tier_key'] ?? '' ) : '';
$smacg_user_percent  = is_array( $smacg_user_info ) ? (int) ( $smacg_user_info['percent']  ?? 0 ) : 0;
$smacg_to_next       = is_array( $smacg_user_info ) ? (int) ( $smacg_user_info['to_next']  ?? 0 ) : 0;
?>

<section class="section member-cta-section">
  <div class="container">
    <div class="member-cta-grid">

      <!-- 左欄：CTA 文案 + 按鈕 -->
      <div class="member-cta-left">
        <span class="member-cta-badge"><i class="fa-solid fa-user-plus"></i> 免費加入會員</span>
        <h2 class="member-cta-title">打造你的玻璃收藏牆</h2>
        <p class="member-cta-desc">收藏作品・追番進度・私房清單・解鎖成就・展示頁面</p>

        <div class="member-cta-btns">
          <?php if ( is_user_logged_in() ) : ?>
            <a href="<?php echo esc_url( function_exists('smacg_get_member_center_url') ? smacg_get_member_center_url() : home_url('/') ); ?>"
               class="btn btn-primary">
              <i class="fa-solid fa-user"></i> 前往會員中心
            </a>
          <?php else : ?>
            <button type="button" class="btn btn-primary" id="smacg-cta-register-btn">
              <i class="fa-solid fa-user-plus"></i> 免費註冊
            </button>
          <?php endif; ?>

          <a href="<?php echo esc_url( home_url('/level-guide/') ); ?>" class="btn btn-secondary">
            <i class="fa-solid fa-compass"></i> 探索功能
          </a>
        </div>
      </div>

      <!-- 右欄：會員成長路徑 -->
      <div class="member-level-panel glass-mid">

        <?php if ( $smacg_user_info ) : ?>
          <!-- 已登入：顯示當前進度 -->
          <div class="member-level-progress-card">
            <div class="mlp-row1">
              <span class="mlp-tier-icon" style="color: <?php echo esc_attr( $smacg_user_info['color'] ?? '#fff' ); ?>;">
                <?php echo esc_html( $smacg_user_info['icon'] ?? '🌱' ); ?>
              </span>
              <div class="mlp-row1-text">
                <div class="mlp-tier-name">
                  <?php echo esc_html( $smacg_user_info['title'] ?? '會員' ); ?>
                  <span class="mlp-lv">Lv.<?php echo esc_html( $smacg_user_level ); ?></span>
                </div>
                <div class="mlp-exp-line">
                  EXP <strong><?php echo number_format( $smacg_user_exp ); ?></strong>
                  <?php if ( ! ( $smacg_user_info['is_max'] ?? false ) ) : ?>
                    ・距下一級還差 <strong><?php echo number_format( $smacg_to_next ); ?></strong>
                  <?php else : ?>
                    ・已達最高等級
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="mlp-bar">
              <div class="mlp-bar-fill" style="width: <?php echo (int) $smacg_user_percent; ?>%;"></div>
            </div>
            <div class="mlp-bar-percent"><?php echo (int) $smacg_user_percent; ?>%</div>
          </div>
        <?php endif; ?>

        <div class="member-level-title">
          <?php echo $smacg_user_info ? '會員成長路徑' : '6 階會員成長路徑'; ?>
        </div>

        <div class="member-level-list">
          <?php foreach ( $smacg_tiers as $tier ) :
            $is_reached = $smacg_user_info && $smacg_user_level >= $tier['min_level'];
            $is_current = $smacg_user_info && $smacg_user_tier_key === $tier['key'];
            $is_locked  = in_array( $tier['key'], [ 'vip', 'black' ], true );

            $item_class = 'member-level-item';
            if ( $is_locked && ! $is_reached )           $item_class .= ' member-level-locked';
            if ( $is_reached )                            $item_class .= ' member-level-reached';
            if ( $is_current )                            $item_class .= ' member-level-current';
          ?>
          <div class="<?php echo esc_attr( $item_class ); ?>">
            <div class="member-level-icon"><?php echo esc_html( $tier['icon'] ); ?></div>
            <div class="member-level-info">
              <div class="member-level-name">
                Lv.<?php echo (int) $tier['min_level']; ?>
                <?php if ( $is_current ) : ?>
                  <span class="member-level-here">← 你在這裡</span>
                <?php elseif ( $is_reached ) : ?>
                  <i class="fa-solid fa-check member-level-check"></i>
                <?php endif; ?>
              </div>
              <div class="member-level-sub">
                <?php echo esc_html( $tier['title'] ); ?>
                ・<?php echo number_format( $tier['min_exp'] ); ?> EXP
              </div>
            </div>
            <span class="member-level-tag <?php echo esc_attr( $tier['tag'] ); ?>">
              <?php if ( $is_locked && ! $is_reached ) : ?>
                <i class="fa-solid fa-lock"></i>
              <?php else : ?>
                T<?php echo (int) $tier['tier']; ?>
              <?php endif; ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>

        <a href="<?php echo esc_url( home_url('/level-guide/') ); ?>" class="member-level-more">
          查看完整等級指南 <i class="fa-solid fa-arrow-right"></i>
        </a>
      </div>

    </div>
  </div>
</section>

<style>
/* ── 會員 CTA 補強樣式 ── */
.member-level-progress-card {
    background: rgba(99,168,255,.06);
    border: 1px solid rgba(99,168,255,.20);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 18px;
}
.mlp-row1 { display: flex; align-items: center; gap: 12px; }
.mlp-tier-icon { font-size: 32px; line-height: 1; }
.mlp-row1-text { flex: 1; }
.mlp-tier-name { font-size: 15px; font-weight: 800; color: rgba(220,230,245,.95); }
.mlp-lv { font-size: 12px; font-weight: 700; color: #63a8ff; margin-left: 6px; padding: 2px 8px; background: rgba(99,168,255,.15); border-radius: 10px; }
.mlp-exp-line { font-size: 12px; color: rgba(220,230,245,.55); margin-top: 4px; }
.mlp-exp-line strong { color: rgba(220,230,245,.90); }
.mlp-bar { height: 6px; background: rgba(255,255,255,.06); border-radius: 6px; overflow: hidden; margin-top: 12px; position: relative; }
.mlp-bar-fill { height: 100%; background: linear-gradient(90deg, #63a8ff, #a663ff); border-radius: 6px; transition: width .6s ease; }
.mlp-bar-percent { font-size: 11px; color: rgba(220,230,245,.45); text-align: right; margin-top: 4px; }

.member-level-item { transition: all .2s ease; }
.member-level-item.member-level-current {
    background: rgba(99,168,255,.10);
    border-left: 3px solid #63a8ff;
    padding-left: 12px;
}
.member-level-item.member-level-reached .member-level-icon { opacity: 1; }
.member-level-item.member-level-locked .member-level-icon { opacity: .5; filter: grayscale(.4); }
.member-level-check { color: #06a77d; margin-left: 6px; font-size: 12px; }
.member-level-here { font-size: 11px; font-weight: 700; color: #63a8ff; margin-left: 6px; }
.member-level-sub { font-size: 11px; color: rgba(220,230,245,.45); margin-top: 2px; }

.member-level-tag.tag-green   { background: rgba(6,167,125,.18);  color: #06a77d; }
.member-level-tag.tag-purple  { background: rgba(106,76,147,.20); color: #a98edd; }
.member-level-tag.tag-orange  { background: rgba(184,134,11,.18); color: #d9a92a; }
.member-level-tag.tag-locked  { background: rgba(255,255,255,.06); color: rgba(220,230,245,.45); }

.member-level-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    font-size: 13px;
    font-weight: 600;
    color: #63a8ff;
    text-decoration: none;
    transition: gap .2s ease;
}
.member-level-more:hover { gap: 10px; color: #8ec0ff; }
</style>

<script>
/* ── 免費註冊按鈕 → 觸發 header 的註冊彈窗 ── */
(function () {
    const btn = document.getElementById('smacg-cta-register-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        if (typeof window.smacgOpenLoginModal === 'function') {
            window.smacgOpenLoginModal('register');
        } else {
            // fallback：彈窗 JS 還沒載入時退回原生註冊頁
            window.location.href = '<?php echo esc_js( wp_registration_url() ); ?>';
        }
    });
})();
</script>

<?php get_footer(); ?>
