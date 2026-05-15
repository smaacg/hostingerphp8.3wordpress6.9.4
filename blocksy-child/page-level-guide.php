<?php
/**
 * Template Name: 等級・積分・段位 完整指南
 *
 * v2.0.0 (2026-05-15)
 *   - 採新版 sqrt 公式 + 6 階會員稱號 + 8 職業 × 4 階稱號
 *   - 全部資料源動態讀取（Level_System / Career_Jobs / Rank_Tier / Exp_Config）
 *
 * @package blocksy-child
 */
defined( 'ABSPATH' ) || exit;

get_header();

/* ── 資料源（外掛未啟用時提供安全 fallback）── */
$exp_rules    = function_exists( 'smacg_get_all_exp_rules' )         ? smacg_get_all_exp_rules()         : [];
$member_tiers = function_exists( 'smacg_get_all_member_tiers' )      ? smacg_get_all_member_tiers()      : [];
$rank_tiers   = function_exists( 'smacg_get_all_rank_tiers' )        ? smacg_get_all_rank_tiers()        : [];
$seasons      = function_exists( 'smacg_get_all_seasons_schedule' )  ? smacg_get_all_seasons_schedule()  : [];
$jobs         = function_exists( 'smacg_get_jobs' )                   ? smacg_get_jobs()                   : [];
$milestones   = function_exists( 'smacg_get_career_milestones' )      ? smacg_get_career_milestones()      : [];

/* 使用者進度（未登入則為 null） */
$me_uid       = get_current_user_id();
$me_lvl       = ( $me_uid && function_exists( 'smacg_get_user_level_info' ) )   ? smacg_get_user_level_info( $me_uid ) : null;
$me_rank      = ( $me_uid && function_exists( 'smacg_get_user_rank_season_info' ) ) ? smacg_get_user_rank_season_info( $me_uid ) : null;
$me_job       = ( $me_uid && function_exists( 'smacg_get_user_job_title' ) )     ? smacg_get_user_job_title( $me_uid )  : [];

$current_season_code  = function_exists( 'smacg_get_current_season_code' ) ? smacg_get_current_season_code() : '';
$current_season_label = function_exists( 'smacg_get_season_label' )        ? smacg_get_season_label()        : '';
?>

<main id="primary" class="site-main level-guide-page" data-current-season="<?php echo esc_attr( $current_season_code ); ?>">
  <div class="container">

    <!-- ===== Hero ===== -->
    <header class="guide-hero">
      <h1 class="guide-hero-title">等級・積分・段位 完整指南</h1>
      <p class="guide-hero-sub">
        了解微笑動漫的會員成長系統 — 從新進會員到黑卡會員，從學生到逍遙之神。
      </p>
      <nav class="guide-toc" aria-label="目錄">
        <a href="#exp"      class="guide-toc-chip">⚡ EXP 來源</a>
        <a href="#level"    class="guide-toc-chip">📊 等級稱號</a>
        <a href="#career"   class="guide-toc-chip">🎯 職業天命</a>
        <a href="#tier"     class="guide-toc-chip">🏆 賽季段位</a>
        <a href="#season"   class="guide-toc-chip">🗓️ 賽季時程</a>
        <a href="#badges"   class="guide-toc-chip">🎖️ 徽章收集</a>
      </nav>

      <?php if ( $me_lvl ): ?>
        <!-- 登入者：我的進度浮動卡 -->
        <aside class="guide-me-card" aria-label="我的目前進度">
          <div class="guide-me-row">
            <span class="guide-me-icon"><?php echo esc_html( $me_lvl['icon'] ?? '🌱' ); ?></span>
            <div class="guide-me-info">
              <div class="guide-me-name">Lv.<?php echo (int) $me_lvl['level']; ?> · <?php echo esc_html( $me_lvl['title'] ); ?></div>
              <div class="guide-me-exp"><?php echo number_format( (int) $me_lvl['exp'] ); ?> EXP</div>
            </div>
          </div>
          <?php if ( ! empty( $me_job ) ): ?>
            <div class="guide-me-row">
              <span class="guide-me-icon"><?php echo esc_html( $me_job['job_icon'] ?? '🎯' ); ?></span>
              <div class="guide-me-info">
                <div class="guide-me-name"><?php echo esc_html( $me_job['title_name'] ); ?></div>
                <div class="guide-me-exp"><?php echo esc_html( $me_job['job_label'] ); ?> · <?php echo (int) ( $me_job['stage'] ?? 0 ); ?> 轉</div>
              </div>
            </div>
          <?php endif; ?>
          <?php if ( $me_rank && ! empty( $me_rank['tier']['label'] ) ): ?>
            <div class="guide-me-row">
              <span class="guide-me-icon"><?php echo esc_html( $me_rank['tier']['icon'] ?? '🎖️' ); ?></span>
              <div class="guide-me-info">
                <div class="guide-me-name"><?php echo esc_html( $me_rank['tier']['label'] ); ?></div>
                <div class="guide-me-exp"><?php echo number_format( (int) ( $me_rank['score'] ?? 0 ) ); ?> 賽季分</div>
              </div>
            </div>
          <?php endif; ?>
        </aside>
      <?php endif; ?>
    </header>

    <!-- ===== §1 EXP 來源 ===== -->
    <section id="exp" class="guide-section">
      <h2 class="guide-section-title">⚡ EXP 來源 — 我要怎麼獲得經驗值？</h2>
      <p class="guide-section-intro">
        在站上的每個互動都會帶來 EXP，累積到一定數量就會升級。下表是所有 EXP 行為與對應點數。
      </p>

      <?php if ( ! empty( $exp_rules ) ): ?>
        <div class="guide-exp-grid">
          <?php foreach ( $exp_rules as $rule ): ?>
            <div class="guide-exp-card" data-cap="<?php echo esc_attr( $rule['cap_type'] ?? 'none' ); ?>">
              <div class="guide-exp-icon"><?php echo esc_html( $rule['icon'] ); ?></div>
              <div class="guide-exp-body">
                <div class="guide-exp-label"><?php echo esc_html( $rule['label'] ); ?></div>
                <?php if ( ! empty( $rule['desc'] ) ): ?>
                  <div class="guide-exp-desc"><?php echo esc_html( $rule['desc'] ); ?></div>
                <?php endif; ?>
                <div class="guide-exp-meta">
                  <span class="guide-exp-value">+<?php echo (int) $rule['exp']; ?> EXP</span>
                  <span class="guide-exp-cap"><?php echo esc_html( $rule['cap_text'] ); ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="guide-empty">EXP 規則暫無法載入。</p>
      <?php endif; ?>

      <div class="guide-callout">
        <strong>💡 小提醒：</strong>
        EXP 是<b>永久累積</b>的，會影響你的等級與會員稱號。賽季積分另計，每季會重置（詳見「賽季時程」）。
      </div>
    </section>

    <!-- ===== §2 等級稱號 ===== -->
    <section id="level" class="guide-section">
      <h2 class="guide-section-title">📊 等級稱號 — 6 階會員身份</h2>
      <p class="guide-section-intro">
        累積 EXP 會自動提升等級（公式：<code>Lv = ⌊√(EXP/5)⌋</code>，最高 Lv.200）。
        等級對應 6 階會員身份，從「新進會員」一路晉升到「黑卡會員」。
      </p>

      <?php if ( ! empty( $member_tiers ) ): ?>
        <div class="guide-tier-grid">
          <?php foreach ( $member_tiers as $t ):
            $is_mine = ( $me_lvl && (int) $me_lvl['tier'] === (int) $t['tier'] );
          ?>
            <div class="guide-tier-card <?php echo $is_mine ? 'is-mine' : ''; ?>" style="--tier-color: <?php echo esc_attr( $t['color'] ); ?>">
              <div class="guide-tier-icon"><?php echo esc_html( $t['icon'] ); ?></div>
              <div class="guide-tier-name"><?php echo esc_html( $t['title'] ); ?></div>
              <div class="guide-tier-range">
                Lv.<?php echo (int) $t['min_level']; ?>+
                <small>（<?php echo number_format( (int) $t['min_exp'] ); ?> EXP）</small>
              </div>
              <?php if ( $is_mine ): ?>
                <div class="guide-tier-mine">⭐ 你的當前身份</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <details class="guide-details">
        <summary>查看完整 Lv.1 ~ 200 累計 EXP 對照表</summary>
        <div class="guide-level-table-wrap">
          <table class="guide-level-table">
            <thead>
              <tr><th>等級</th><th>累計 EXP</th><th>等級</th><th>累計 EXP</th><th>等級</th><th>累計 EXP</th><th>等級</th><th>累計 EXP</th></tr>
            </thead>
            <tbody>
              <?php
              $table = function_exists( 'smacg_get_full_level_table' ) ? smacg_get_full_level_table() : [];
              $cols  = 4;
              $rows  = (int) ceil( count( $table ) / $cols );
              $keys  = array_keys( $table );
              for ( $r = 1; $r <= $rows; $r++ ) {
                  echo '<tr>';
                  for ( $c = 0; $c < $cols; $c++ ) {
                      $idx = $r + ( $c * $rows );
                      if ( isset( $table[ $idx ] ) ) {
                          $cell_is_mine = ( $me_lvl && (int) $me_lvl['level'] === $idx ) ? ' class="is-mine"' : '';
                          echo '<td' . $cell_is_mine . '>Lv.' . $idx . '</td>';
                          echo '<td' . $cell_is_mine . '>' . number_format( $table[ $idx ] ) . '</td>';
                      } else {
                          echo '<td></td><td></td>';
                      }
                  }
                  echo '</tr>';
              }
              ?>
            </tbody>
          </table>
        </div>
      </details>
    </section>

    <!-- ===== §3 職業天命 ===== -->
    <section id="career" class="guide-section">
      <h2 class="guide-section-title">🎯 職業天命 — 8 大職業 × 4 階稱號</h2>
      <p class="guide-section-intro">
        會員等級代表「身份」，職業則代表「玩家風格」。
        達到 <b>Lv.10（一轉）</b> 後可從 8 個職業中選擇一個，
        隨等級升高，職業稱號會自動進化（一轉 → 二轉 → 三轉 → 四轉）。
      </p>

      <div class="guide-milestone-bar" aria-label="4 個轉職里程碑">
        <?php foreach ( $milestones as $stage => $m ):
          $reached = ( $me_lvl && (int) $me_lvl['level'] >= (int) $m['level'] );
        ?>
          <div class="guide-milestone <?php echo $reached ? 'is-reached' : ''; ?>">
            <div class="guide-milestone-icon"><?php echo esc_html( $m['icon'] ); ?></div>
            <div class="guide-milestone-stage"><?php echo (int) $stage; ?> 轉</div>
            <div class="guide-milestone-level">Lv.<?php echo (int) $m['level']; ?></div>
            <div class="guide-milestone-label"><?php echo esc_html( $m['label'] ); ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ( ! empty( $jobs ) ): ?>
        <div class="guide-job-grid">
          <?php foreach ( $jobs as $key => $job ):
            $is_mine = ( ! empty( $me_job['job_key'] ) && $me_job['job_key'] === $key );
          ?>
            <article class="guide-job-card <?php echo $is_mine ? 'is-mine' : ''; ?>" id="job-<?php echo esc_attr( $key ); ?>">
              <header class="guide-job-head">
                <div class="guide-job-icon"><?php echo esc_html( $job['icon'] ); ?></div>
                <div class="guide-job-headinfo">
                  <h3 class="guide-job-name"><?php echo esc_html( $job['label'] ); ?></h3>
                  <?php if ( ! empty( $job['desc'] ) ): ?>
                    <p class="guide-job-desc"><?php echo esc_html( $job['desc'] ); ?></p>
                  <?php endif; ?>
                </div>
                <?php if ( $is_mine ): ?>
                  <span class="guide-job-mine">⭐ 你的職業</span>
                <?php endif; ?>
              </header>
              <ol class="guide-job-titles">
                <?php foreach ( $job['titles'] as $stage => $t ):
                  $ms_lv   = (int) ( $milestones[ $stage ]['level'] ?? 0 );
                  $reached = ( $me_lvl && (int) $me_lvl['level'] >= $ms_lv );
                  $cur     = ( $is_mine && (int) ( $me_job['stage'] ?? 0 ) === (int) $stage );
                  $cls     = $reached ? ( $cur ? 'is-current' : 'is-done' ) : 'is-locked';
                ?>
                  <li class="guide-job-title-row <?php echo $cls; ?>">
                    <span class="guide-job-stage">Lv.<?php echo $ms_lv; ?></span>
                    <span class="guide-job-tname"><?php echo esc_html( $t['name'] ); ?></span>
                    <span class="guide-job-tref"><small><?php echo esc_html( $t['ref'] ); ?></small></span>
                  </li>
                <?php endforeach; ?>
              </ol>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="guide-callout">
        <strong>⚠️ 注意：</strong>
        職業一旦選擇，<b>3 個月內無法變更</b>，請慎重考慮。
        <?php if ( ! is_user_logged_in() ): ?>
          <a class="guide-callout-cta" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">登入以選擇職業 →</a>
        <?php elseif ( $me_lvl && (int) $me_lvl['level'] < 10 ): ?>
          目前你 Lv.<?php echo (int) $me_lvl['level']; ?>，達到 Lv.10 即可在
          <a class="guide-callout-cta" href="<?php echo esc_url( home_url( '/mc/?tab=career' ) ); ?>">會員中心 → 職業頁</a> 選擇。
        <?php elseif ( empty( $me_job ) ): ?>
          <a class="guide-callout-cta" href="<?php echo esc_url( home_url( '/mc/?tab=career' ) ); ?>">前往會員中心選擇職業 →</a>
        <?php endif; ?>
      </div>
    </section>

   <!-- ===== Section: 賽季段位（10 階精簡版）===== -->
<section id="rank" class="lg-section">
    <h2><span class="lg-num">4</span>賽季段位</h2>
    <p>賽季積分由互動行為累積（留言、追蹤、評分等），每季結算重置。<strong>大師以下</strong>純看積分；<strong>大師、宗師、菁英</strong>同屬 7600+ 分，再依全站名次切分。</p>

    <?php
    // 10 階段位摘要（對應 Rank_Tier::TIERS + 名次修正）
    $lg_rank_tiers = [
        [
            'key'   => 'iron',
            'name'  => '鐵',
            'icon'  => '🥉',
            'color' => '#6b6b6b',
            'range' => '0 – 399',
            'divs'  => 'IV 0 ‧ III 100 ‧ II 200 ‧ I 300',
            'note'  => '',
        ],
        [
            'key'   => 'bronze',
            'name'  => '銅',
            'icon'  => '🟫',
            'color' => '#a97142',
            'range' => '400 – 999',
            'divs'  => 'IV 400 ‧ III 550 ‧ II 700 ‧ I 850',
            'note'  => '',
        ],
        [
            'key'   => 'silver',
            'name'  => '銀',
            'icon'  => '⚪',
            'color' => '#b8b8b8',
            'range' => '1000 – 1799',
            'divs'  => 'IV 1000 ‧ III 1200 ‧ II 1400 ‧ I 1600',
            'note'  => '',
        ],
        [
            'key'   => 'gold',
            'name'  => '金',
            'icon'  => '🟡',
            'color' => '#f0c040',
            'range' => '1800 – 2799',
            'divs'  => 'IV 1800 ‧ III 2050 ‧ II 2300 ‧ I 2550',
            'note'  => '',
        ],
        [
            'key'   => 'platinum',
            'name'  => '白金',
            'icon'  => '🟦',
            'color' => '#4ad6c0',
            'range' => '2800 – 3999',
            'divs'  => 'IV 2800 ‧ III 3100 ‧ II 3400 ‧ I 3700',
            'note'  => '',
        ],
        [
            'key'   => 'emerald',
            'name'  => '翡翠',
            'icon'  => '🟢',
            'color' => '#28b463',
            'range' => '4000 – 5599',
            'divs'  => 'IV 4000 ‧ III 4400 ‧ II 4800 ‧ I 5200',
            'note'  => '',
        ],
        [
            'key'   => 'diamond',
            'name'  => '鑽石',
            'icon'  => '💎',
            'color' => '#5dade2',
            'range' => '5600 – 7599',
            'divs'  => 'IV 5600 ‧ III 6100 ‧ II 6600 ‧ I 7100',
            'note'  => '',
        ],
        [
            'key'   => 'master',
            'name'  => '大師',
            'icon'  => '👑',
            'color' => '#bb8fce',
            'range' => '7600+ 分',
            'divs'  => '',
            'note'  => '達門檻但未進前 200 名',
        ],
        [
            'key'   => 'grandmaster',
            'name'  => '宗師',
            'icon'  => '🔥',
            'color' => '#ff9f43',
            'range' => '7600+ 分',
            'divs'  => '',
            'note'  => '全站名次 51 – 200',
        ],
        [
            'key'   => 'challenger',
            'name'  => '菁英',
            'icon'  => '⚡',
            'color' => '#ff6b6b',
            'range' => '7600+ 分',
            'divs'  => '',
            'note'  => '全站名次 1 – 50',
        ],
    ];
    ?>

    <div class="lg-rank-grid">
        <?php foreach ( $lg_rank_tiers as $t ) :
            $is_apex = in_array( $t['key'], [ 'master', 'grandmaster', 'challenger' ], true );
        ?>
            <div class="lg-rank-card<?php echo $is_apex ? ' is-apex' : ''; ?>"
                 data-rank="<?php echo esc_attr( $t['key'] ); ?>"
                 style="--rank-color: <?php echo esc_attr( $t['color'] ); ?>;">
                <div class="lg-rank-icon"><?php echo esc_html( $t['icon'] ); ?></div>
                <div class="lg-rank-name"><?php echo esc_html( $t['name'] ); ?></div>
                <div class="lg-rank-range"><?php echo esc_html( $t['range'] ); ?></div>

                <?php if ( ! empty( $t['divs'] ) ) : ?>
                    <div class="lg-rank-divs"><?php echo esc_html( $t['divs'] ); ?></div>
                <?php endif; ?>

                <?php if ( ! empty( $t['note'] ) ) : ?>
                    <div class="lg-rank-note"><?php echo esc_html( $t['note'] ); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="lg-note">
        <strong>提醒：</strong>宗師與菁英席位每日依排行榜重算，掉到 200 名外會自動降回大師。賽季結束時依當季最終排名發放紀念徽章。
    </div>
</section>

    <!-- ===== §5 賽季時程 ===== -->
    <section id="season" class="guide-section">
      <h2 class="guide-section-title">🗓️ 賽季時程 — 四季輪替</h2>
      <p class="guide-section-intro">
        每年分為 4 個賽季：春（3/1–5/31）、夏（6/1–8/31）、秋（9/1–11/30）、冬（12/1–次年 2/28）。
        賽季結束時會頒發紀念徽章，並重置賽季積分。
      </p>

      <?php if ( ! empty( $seasons ) ): ?>
        <ol class="guide-season-list">
          <?php foreach ( $seasons as $s ):
            $status_label = [ 'upcoming' => '即將開始', 'active' => '進行中', 'ended' => '已結束' ][ $s['status'] ] ?? '';
          ?>
            <li class="guide-season-item is-<?php echo esc_attr( $s['status'] ); ?>">
              <div class="guide-season-label"><?php echo esc_html( $s['label'] ); ?></div>
              <div class="guide-season-dates">
                <?php echo esc_html( date_i18n( 'Y/m/d', $s['start'] ) ); ?>
                ~
                <?php echo esc_html( date_i18n( 'Y/m/d', $s['end'] ) ); ?>
              </div>
              <div class="guide-season-status"><?php echo esc_html( $status_label ); ?></div>
              <?php if ( $s['status'] === 'active' ): ?>
                <div class="guide-season-countdown" data-end="<?php echo (int) $s['end']; ?>">
                  距離結算還有 <b class="js-countdown">…</b>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <!-- ===== §6 徽章收集 ===== -->
    <section id="badges" class="guide-section">
      <h2 class="guide-section-title">🎖️ 徽章收集 — 解鎖你的成就</h2>
      <p class="guide-section-intro">
        在站上完成各種挑戰可解鎖徽章。完整徽章列表請至會員中心查看。
      </p>

      <?php
      $badge_slug = defined( 'SMACG_BADGE_SLUG' ) ? SMACG_BADGE_SLUG : 'badge';
      $badges = get_posts( [
          'post_type'      => $badge_slug,
          'post_status'    => 'publish',
          'posts_per_page' => 12,
          'orderby'        => 'menu_order title',
          'order'          => 'ASC',
      ] );

      $earned_ids = ( $me_uid && function_exists( 'smacg_get_user_badge_ids' ) ) ? smacg_get_user_badge_ids( $me_uid ) : [];
      $earned_map = array_flip( $earned_ids );
      ?>

      <?php if ( ! empty( $badges ) ): ?>
        <div class="guide-badge-grid">
          <?php foreach ( $badges as $b ):
            $unlocked = isset( $earned_map[ $b->ID ] );
            $thumb    = get_the_post_thumbnail_url( $b->ID, 'thumbnail' );
          ?>
            <div class="guide-badge-card <?php echo $unlocked ? 'is-unlocked' : 'is-locked'; ?>" title="<?php echo esc_attr( $b->post_title ); ?>">
              <div class="guide-badge-icon">
                <?php if ( $thumb ): ?>
                  <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $b->post_title ); ?>" loading="lazy">
                <?php else: ?>
                  <i class="fa-solid fa-trophy"></i>
                <?php endif; ?>
              </div>
              <div class="guide-badge-name"><?php echo esc_html( $b->post_title ); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="guide-badge-more">
          <a href="<?php echo esc_url( home_url( '/mc/?tab=badges' ) ); ?>">查看完整徽章列表 →</a>
        </p>
      <?php else: ?>
        <p class="guide-empty">徽章系統正在建置中，敬請期待。</p>
      <?php endif; ?>
    </section>

    <!-- ===== Footer CTA ===== -->
    <footer class="guide-cta">
      <?php if ( ! is_user_logged_in() ): ?>
        <h3>準備好開始你的旅程？</h3>
        <p>登入後即可獲得 EXP、選擇職業、累積賽季積分。</p>
        <a href="<?php echo esc_url( wp_login_url( home_url( '/mc/' ) ) ); ?>" class="guide-cta-btn">立即登入 / 註冊 →</a>
      <?php else: ?>
        <h3>前往會員中心查看你的進度</h3>
        <a href="<?php echo esc_url( home_url( '/mc/' ) ); ?>" class="guide-cta-btn">會員中心 →</a>
      <?php endif; ?>
    </footer>

  </div>
</main>

<?php get_footer(); ?>
