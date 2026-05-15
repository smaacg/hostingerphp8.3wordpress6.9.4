<?php
/**
 * Template Name: 等級・積分・段位 完整指南
 * Template Post Type: page
 *
 * 動態資料源：
 *   smacg_get_all_exp_rules()       — EXP 行為表
 *   smacg_get_all_level_jobs()      — 8 職業階層
 *   smacg_get_full_level_table()    — 200 級 EXP 表
 *   smacg_get_all_rank_tiers()      — TFT 段位表
 *   smacg_get_all_seasons_schedule()— 賽季時程
 *
 * 建立頁面：後台 → 新增頁面 → Template 選此模板 → Slug 設為 level-guide
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$current_uid  = get_current_user_id();
$is_logged_in = is_user_logged_in();

/* ---------- 資料源 ---------- */
$exp_rules  = function_exists( 'smacg_get_all_exp_rules' )       ? smacg_get_all_exp_rules()       : [];
$jobs       = function_exists( 'smacg_get_all_level_jobs' )      ? smacg_get_all_level_jobs()      : [];
$lv_table   = function_exists( 'smacg_get_full_level_table' )    ? smacg_get_full_level_table()    : [];
$tiers      = function_exists( 'smacg_get_all_rank_tiers' )      ? smacg_get_all_rank_tiers()      : [];
$seasons    = function_exists( 'smacg_get_all_seasons_schedule' )? smacg_get_all_seasons_schedule(): [];

/* ---------- 我的進度（登入者） ---------- */
$me_lvl  = null;
$me_rank = null;
if ( $is_logged_in ) {
    if ( function_exists( 'smacg_get_user_level_info' ) ) {
        $me_lvl = smacg_get_user_level_info( $current_uid );
    }
    if ( function_exists( 'smacg_get_user_rank_season_info' ) ) {
        $me_rank = smacg_get_user_rank_season_info( $current_uid );
    }
}

/* ---------- 當前賽季倒數 ---------- */
$cur_season = null;
foreach ( $seasons as $s ) {
    if ( $s['status'] === 'active' ) { $cur_season = $s; break; }
}

/* ---------- 徽章圖鑑（GamiPress） ---------- */
$badges_grouped = [];
if ( post_type_exists( SMACG_BADGE_SLUG ) ) {
    $badge_types = get_terms( [
        'taxonomy'   => 'badge-type',
        'hide_empty' => false,
    ] );
    $user_badges = $is_logged_in && function_exists( 'smacg_get_user_badge_ids' )
        ? array_map( 'intval', (array) smacg_get_user_badge_ids( $current_uid ) )
        : [];

    $q = new WP_Query( [
        'post_type'      => SMACG_BADGE_SLUG,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'OR',
            [ 'key' => '_gamipress_hidden', 'compare' => 'NOT EXISTS' ],
            [ 'key' => '_gamipress_hidden', 'value' => 'show' ],
        ],
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ] );
    foreach ( $q->posts as $p ) {
        $types = wp_get_post_terms( $p->ID, 'badge-type', [ 'fields' => 'names' ] );
        $cat   = $types[0] ?? '一般徽章';
        $badges_grouped[ $cat ][] = [
            'id'        => $p->ID,
            'title'     => $p->post_title,
            'desc'      => wp_strip_all_tags( $p->post_content ),
            'unlocked'  => in_array( $p->ID, $user_badges, true ),
            'thumb'     => get_the_post_thumbnail_url( $p->ID, 'thumbnail' ),
            'how_to'    => get_post_meta( $p->ID, '_smacg_badge_how_to', true ),
        ];
    }
    wp_reset_postdata();
}
?>

<div class="lg-wrap">

  <!-- ============ HERO ============ -->
  <section class="lg-hero">
    <div class="container">
      <div class="lg-hero__eyebrow">
        <span class="chip"><i class="fa-solid fa-book"></i> 完整指南</span>
        <?php if ( $cur_season ) : ?>
          <span class="chip chip--violet"><i class="fa-solid fa-trophy"></i> <?php echo esc_html( $cur_season['label'] ); ?></span>
        <?php endif; ?>
      </div>
      <h1 class="lg-hero__title">
        <span>📖</span> 等級・積分・段位 完整指南
      </h1>
      <p class="lg-hero__subtitle">了解 EXP 怎麼累積、等級怎麼升、段位怎麼算，掌握所有遊戲規則</p>

      <nav class="lg-toc" aria-label="章節導覽">
        <a href="#exp"     class="lg-toc__item"><span>⚡</span> EXP 怎麼來</a>
        <a href="#level"   class="lg-toc__item"><span>🎯</span> 等級系統</a>
        <a href="#tier"    class="lg-toc__item"><span>💎</span> TFT 段位</a>
        <a href="#season"  class="lg-toc__item"><span>🏆</span> 賽季時程</a>
        <a href="#badges"  class="lg-toc__item"><span>🏅</span> 徽章圖鑑</a>
      </nav>
    </div>
  </section>

  <!-- ============ 我的進度浮動卡 ============ -->
  <?php if ( $is_logged_in && $me_lvl ) : ?>
  <div class="lg-myprogress" id="lg-myprogress">
    <div class="lg-myprogress__row">
      <span class="lg-myprogress__icon"><?php echo esc_html( $me_lvl['icon'] ); ?></span>
      <div>
        <div class="lg-myprogress__lv">Lv.<?php echo (int) $me_lvl['level']; ?> · <?php echo esc_html( $me_lvl['title'] ); ?></div>
        <div class="lg-myprogress__exp"><?php echo number_format( $me_lvl['exp'] ); ?> EXP</div>
      </div>
    </div>
    <?php if ( $me_rank ) :
        $t = $me_rank['tier'];
    ?>
    <div class="lg-myprogress__row" style="--tier-color: <?php echo esc_attr( $t['color'] ); ?>;">
      <span class="lg-myprogress__icon"><?php echo esc_html( $t['icon'] ); ?></span>
      <div>
        <div class="lg-myprogress__lv" style="color: var(--tier-color);"><?php echo esc_html( $t['label'] ); ?></div>
        <div class="lg-myprogress__exp"><?php echo number_format( $me_rank['score'] ); ?> 賽季積分</div>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="container lg-body">

    <!-- ============ §1 EXP 怎麼來 ============ -->
    <section class="lg-section" id="exp">
      <h2 class="lg-h2"><span>⚡</span> EXP 怎麼來？</h2>
      <p class="lg-lead">EXP 是站上的核心積分，每個行為都會獲得對應的 EXP。<strong>EXP 同時用於兩個系統</strong>：累積決定<em>等級</em>，本季累積決定<em>段位</em>。</p>

      <div class="lg-table-wrap">
        <table class="lg-table">
          <thead>
            <tr><th>行為</th><th class="lg-num">EXP</th><th>限制</th><th>說明</th></tr>
          </thead>
          <tbody>
            <?php foreach ( $exp_rules as $r ) : ?>
            <tr>
              <td><span class="lg-emoji"><?php echo esc_html( $r['icon'] ); ?></span> <?php echo esc_html( $r['label'] ); ?></td>
              <td class="lg-num lg-num--accent">+<?php echo (int) $r['exp']; ?></td>
              <td><span class="lg-pill"><?php echo esc_html( $r['cap_text'] ); ?></span></td>
              <td class="lg-desc"><?php echo esc_html( $r['desc'] ); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="lg-callout lg-callout--info">
        <i class="fa-solid fa-circle-info"></i>
        <div>
          <strong>EXP vs 賽季積分：一套來源、兩個用途</strong><br>
          你的每一筆 EXP 會同時加到「累計 EXP」（永久，給等級用）和「賽季積分」（每季歸零，給段位用）。
          就像 LoL 的召喚師等級永久累積，排位 LP 每季重置一樣。
        </div>
      </div>
    </section>

    <!-- ============ §2 等級系統 ============ -->
    <section class="lg-section" id="level">
      <h2 class="lg-h2"><span>🎯</span> 等級系統（Lv.1 ~ Lv.200）</h2>
      <p class="lg-lead">站上共 200 級，每升一級需要的 EXP 越來越多。每個等級區間對應一個職業稱號。</p>

      <div class="lg-jobs-grid">
        <?php foreach ( $jobs as $job ) :
            $is_mine = $me_lvl && $me_lvl['level'] >= $job['min'] && $me_lvl['level'] <= $job['max'];
        ?>
          <div class="lg-job-card<?php echo $is_mine ? ' lg-job-card--mine' : ''; ?>" data-tier="<?php echo (int) $job['tier']; ?>">
            <div class="lg-job-card__range">Lv.<?php echo (int) $job['min']; ?>
              <?php if ( $job['min'] !== $job['max'] ) echo ' ~ ' . (int) $job['max']; ?>
            </div>
            <div class="lg-job-card__title"><?php echo esc_html( $job['title'] ); ?></div>
            <?php if ( $is_mine ) : ?>
              <div class="lg-job-card__badge">你在這 ✨</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <h3 class="lg-h3">完整等級表</h3>
      <p class="lg-lead lg-lead--small">點下方按鈕可展開全部 200 級；預設只顯示你所在等級附近。</p>

      <div class="lg-table-wrap">
        <table class="lg-table lg-table--level" id="lg-level-table">
          <thead>
            <tr><th>等級</th><th>職業</th><th class="lg-num">累計 EXP</th><th class="lg-num">本級所需</th></tr>
          </thead>
          <tbody>
            <?php
            $my_lv     = $me_lvl['level'] ?? 1;
            $show_from = max( 1, $my_lv - 5 );
            $show_to   = min( 200, $my_lv + 10 );
            for ( $lv = 1; $lv <= 200; $lv++ ) :
                $job_title = '';
                foreach ( $jobs as $j ) {
                    if ( $lv >= $j['min'] && $lv <= $j['max'] ) { $job_title = $j['title']; break; }
                }
                $cur_exp  = $lv_table[ $lv ] ?? 0;
                $prev_exp = $lv_table[ $lv - 1 ] ?? 0;
                $delta    = $cur_exp - $prev_exp;
                $is_mine  = ( $lv === $my_lv );
                $in_range = ( $lv >= $show_from && $lv <= $show_to );
            ?>
            <tr class="lg-lv-row<?php echo $is_mine ? ' lg-lv-row--mine' : ''; ?><?php echo $in_range ? '' : ' lg-lv-row--hidden'; ?>" data-lv="<?php echo $lv; ?>">
              <td><strong>Lv.<?php echo $lv; ?></strong> <?php echo $is_mine ? '👈' : ''; ?></td>
              <td><?php echo esc_html( $job_title ); ?></td>
              <td class="lg-num"><?php echo number_format( $cur_exp ); ?></td>
              <td class="lg-num lg-num--muted"><?php echo $lv > 1 ? '+' . number_format( $delta ) : '—'; ?></td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
      <button type="button" class="lg-btn lg-btn--ghost" id="lg-level-toggle">
        <i class="fa-solid fa-chevron-down"></i> <span>展開全部 200 級</span>
      </button>

      <div class="lg-callout">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
          <strong>注意「宗師」一詞有兩種用法</strong><br>
          等級 70-99 的職業稱號叫「宗師」（永久身分），TFT 段位制裡也有「宗師」（賽季制，全站前 200 名）。
          兩者是獨立的，不要混淆。
        </div>
      </div>
    </section>

    <!-- ============ §3 TFT 段位 ============ -->
    <section class="lg-section" id="tier">
      <h2 class="lg-h2"><span>💎</span> TFT 段位制（賽季）</h2>
      <p class="lg-lead">本站段位採用《聯盟戰旗》同款設計：8 大段位 + 菁英 / 宗師人數上限。<strong>賽季結束會重置</strong>。</p>

      <div class="lg-tier-grid">
        <?php
        // 將 TIERS 依 key 分組，每個 key 顯示一張卡
        $tier_groups = [];
        foreach ( $tiers as $t ) {
            $tier_groups[ $t['key'] ][] = $t;
        }
        $my_tier_key = $me_rank['tier']['key'] ?? '';

        $tier_order = [ 'iron','bronze','silver','gold','platinum','emerald','diamond','master' ];
        foreach ( $tier_order as $key ) :
            if ( empty( $tier_groups[ $key ] ) ) continue;
            $rows    = $tier_groups[ $key ];
            $first   = $rows[0];
            $is_mine = ( $my_tier_key === $key );
        ?>
          <div class="lg-tier-card<?php echo $is_mine ? ' lg-tier-card--mine' : ''; ?>"
               style="--tier-color: <?php echo esc_attr( $first['color'] ); ?>;">
            <div class="lg-tier-card__head">
              <span class="lg-tier-card__icon"><?php echo esc_html( $first['icon'] ); ?></span>
              <span class="lg-tier-card__name">
                <?php echo esc_html( explode( ' ', $first['label'] )[0] ); ?>
              </span>
            </div>
            <ul class="lg-tier-card__divs">
              <?php foreach ( $rows as $r ) : ?>
                <li>
                  <span class="lg-tier-card__div"><?php echo esc_html( $r['division'] ?: '單階' ); ?></span>
                  <span class="lg-tier-card__min"><?php echo number_format( $r['min'] ); ?> 分</span>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if ( $is_mine ) : ?>
              <div class="lg-tier-card__badge">你在這 ✨</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <!-- 宗師 / 菁英 -->
        <div class="lg-tier-card lg-tier-card--special" style="--tier-color: #ff9f43;">
          <div class="lg-tier-card__head">
            <span class="lg-tier-card__icon">🔥</span>
            <span class="lg-tier-card__name">宗師</span>
          </div>
          <ul class="lg-tier-card__divs">
            <li><span class="lg-tier-card__div">全站 前 200</span></li>
            <li><span class="lg-tier-card__min">需達大師 7,600 分</span></li>
          </ul>
        </div>

        <div class="lg-tier-card lg-tier-card--special" style="--tier-color: #ff6b6b;">
          <div class="lg-tier-card__head">
            <span class="lg-tier-card__icon">⚡</span>
            <span class="lg-tier-card__name">菁英</span>
          </div>
          <ul class="lg-tier-card__divs">
            <li><span class="lg-tier-card__div">全站 前 50</span></li>
            <li><span class="lg-tier-card__min">需達大師 7,600 分</span></li>
          </ul>
        </div>
      </div>

      <div class="lg-callout lg-callout--info">
        <i class="fa-solid fa-circle-info"></i>
        <div>
          <strong>升段規則</strong><br>
          鐵 ~ 鑽石：分數達標即升段，<strong>不會掉段</strong>。<br>
          大師以上：分數達 7,600 後，按全站名次決定是宗師、菁英或大師。賽季中名次會動態變動。
        </div>
      </div>
    </section>

    <!-- ============ §4 賽季時程 ============ -->
    <section class="lg-section" id="season">
      <h2 class="lg-h2"><span>🏆</span> 賽季時程</h2>
      <p class="lg-lead">每年 4 個賽季，對應春夏秋冬四個動畫季。賽季結束時自動結算前一季段位、發送紀念徽章，並重置賽季積分。</p>

      <?php if ( $cur_season ) : ?>
      <div class="lg-season-now" data-end="<?php echo esc_attr( $cur_season['end'] ); ?>">
        <div class="lg-season-now__label">
          <i class="fa-solid fa-fire"></i> 進行中
        </div>
        <div class="lg-season-now__title"><?php echo esc_html( $cur_season['label'] ); ?></div>
        <div class="lg-season-now__countdown" id="lg-countdown">
          倒數中…
        </div>
      </div>
      <?php endif; ?>

      <div class="lg-season-timeline">
        <?php foreach ( $seasons as $s ) :
            $status_class = 'lg-season-item--' . $s['status'];
        ?>
          <div class="lg-season-item <?php echo $status_class; ?>">
            <div class="lg-season-item__title"><?php echo esc_html( $s['label'] ); ?></div>
            <div class="lg-season-item__range">
              <?php echo esc_html( date_i18n( 'Y/m/d', $s['start'] ) ); ?>
              ~
              <?php echo esc_html( date_i18n( 'Y/m/d', $s['end'] ) ); ?>
            </div>
            <div class="lg-season-item__status">
              <?php
              echo match ( $s['status'] ) {
                  'active'   => '<i class="fa-solid fa-circle-play"></i> 進行中',
                  'upcoming' => '<i class="fa-regular fa-clock"></i> 即將開始',
                  default    => '<i class="fa-solid fa-flag-checkered"></i> 已結束',
              };
              ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ============ §5 徽章圖鑑 ============ -->
    <section class="lg-section" id="badges">
      <h2 class="lg-h2"><span>🏅</span> 徽章圖鑑</h2>
      <p class="lg-lead">完成特定條件可解鎖徽章，每個徽章還會額外給予 EXP。</p>

      <?php if ( empty( $badges_grouped ) ) : ?>
        <p class="lg-empty">徽章資料載入中或尚未建立。請至後台 GamiPress → Badges 新增徽章。</p>
      <?php else : foreach ( $badges_grouped as $cat => $badges ) : ?>
        <h3 class="lg-h3"><?php echo esc_html( $cat ); ?></h3>
        <div class="lg-badge-grid">
          <?php foreach ( $badges as $b ) : ?>
            <div class="lg-badge-card<?php echo $b['unlocked'] ? ' lg-badge-card--unlocked' : ' lg-badge-card--locked'; ?>">
              <div class="lg-badge-card__img">
                <?php if ( $b['thumb'] ) : ?>
                  <img src="<?php echo esc_url( $b['thumb'] ); ?>" alt="" loading="lazy">
                <?php else : ?>
                  <span class="lg-badge-card__placeholder">🏆</span>
                <?php endif; ?>
                <?php if ( ! $b['unlocked'] ) : ?>
                  <div class="lg-badge-card__lock"><i class="fa-solid fa-lock"></i></div>
                <?php endif; ?>
              </div>
              <div class="lg-badge-card__title"><?php echo esc_html( $b['title'] ); ?></div>
              <div class="lg-badge-card__desc">
                <?php
                if ( $b['unlocked'] ) {
                    echo esc_html( $b['desc'] ?: '已解鎖' );
                } else {
                    echo esc_html( $b['how_to'] ?: $b['desc'] ?: '完成特定條件解鎖' );
                }
                ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; endif; ?>
    </section>

    <!-- ============ Footer CTA ============ -->
    <section class="lg-cta">
      <?php if ( ! $is_logged_in ) : ?>
        <h3>還沒加入？立刻註冊開始累積 EXP</h3>
        <p>註冊立即獲得 +100 EXP，每天登入再 +10，搶先卡位本季段位榜！</p>
        <a class="lg-btn lg-btn--primary" href="<?php echo esc_url( wp_registration_url() ); ?>">
          <i class="fa-solid fa-rocket"></i> 立即註冊
        </a>
      <?php else : ?>
        <h3>開始挑戰段位</h3>
        <p>多評論、多評分、多追蹤，每日上線就有 EXP！</p>
        <div class="lg-cta__actions">
          <a class="lg-btn lg-btn--primary" href="<?php echo esc_url( home_url( '/ranking-users/?tab=rank_season' ) ); ?>">
            <i class="fa-solid fa-trophy"></i> 查看賽季排行
          </a>
          <a class="lg-btn lg-btn--ghost" href="<?php echo esc_url( home_url( '/mc/' ) ); ?>">
            <i class="fa-solid fa-user"></i> 回會員中心
          </a>
        </div>
      <?php endif; ?>
    </section>

  </div>
</div>

<?php get_footer(); ?>
