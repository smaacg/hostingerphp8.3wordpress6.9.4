<?php
/**
 * Template Name: 會員排行榜
 * Template Post Type: page
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-2
 *
 * 4 個 tab：累計 EXP / 本月 EXP / 粉絲數 / 徽章數
 * 資料來自 wp_smacg_rankings 快取表（由 ranking-cron 每小時更新）
 *
 * 建立頁面：後台→頁面→新增→Template=「會員排行榜」→ Slug 設為 ranking-users
 */

if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$current_uid   = get_current_user_id();
$is_logged_in  = is_user_logged_in();
$updated_at    = get_option( 'smacg_ranking_last_rebuild', '' );
$privacy_data  = function_exists( 'smacg_ranking_privacy_localize_data' )
    ? smacg_ranking_privacy_localize_data()
    : [ 'visible' => true ];

// 預設 tab（可由 ?tab=monthly 切換）
$default_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'exp_total';
$valid_tabs  = [ 'exp_total', 'exp_monthly', 'followers', 'badges' ];
if ( ! in_array( $default_tab, $valid_tabs, true ) ) $default_tab = 'exp_total';
?>

<!-- ================================================================
     HERO
     ================================================================ -->
<section class="ranku-hero">
  <canvas id="ranku-particles" class="ranku-hero__particles" aria-hidden="true"></canvas>
  <div class="ranku-hero__overlay"></div>
  <div class="container ranku-hero__content">
    <div class="ranku-hero__eyebrow">
      <span class="chip"><i class="fa-solid fa-users"></i> <?php esc_html_e( '社群會員排名', 'weixiaoacg' ); ?></span>
      <span class="chip chip--green"><i class="fa-solid fa-clock-rotate-left"></i> <?php esc_html_e( '每小時更新', 'weixiaoacg' ); ?></span>
    </div>
    <h1 class="ranku-hero__title">
      <span class="ranku-hero__trophy">🏆</span>
      <?php esc_html_e( '會員排行榜', 'weixiaoacg' ); ?>
    </h1>
    <p class="ranku-hero__subtitle"><?php esc_html_e( '看看誰才是真正的二次元勇者', 'weixiaoacg' ); ?></p>

    <?php if ( $is_logged_in ) :
        $my_pos = function_exists( 'smacg_ranking_user_position' )
            ? smacg_ranking_user_position( $current_uid, $default_tab )
            : null;
    ?>
    <div class="ranku-hero__me" id="ranku-hero-me" data-tab="<?php echo esc_attr( $default_tab ); ?>">
      <span class="ranku-hero__me-label"><?php esc_html_e( '我的名次：', 'weixiaoacg' ); ?></span>
      <span class="ranku-hero__me-pos" id="ranku-me-pos">
        <?php echo $my_pos ? '#' . esc_html( $my_pos ) : esc_html__( '未上榜', 'weixiaoacg' ); ?>
      </span>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ================================================================
     主體
     ================================================================ -->
<section class="ranku-body section">
  <div class="container">
    <div class="ranku-layout">

      <!-- 左欄：排行榜主區 -->
      <div class="ranku-main">

        <!-- Tabs -->
        <div class="ranku-tabs-row">
          <div class="ranku-tabs" id="ranku-tabs" role="tablist" aria-label="<?php esc_attr_e( '排行榜類別', 'weixiaoacg' ); ?>">
            <button type="button" class="ranku-tab<?php echo $default_tab==='exp_total'?' active':''; ?>"
                    data-type="exp_total" role="tab" aria-selected="<?php echo $default_tab==='exp_total'?'true':'false'; ?>">
              <i class="fa-solid fa-bolt"></i> <?php esc_html_e( '累計 EXP', 'weixiaoacg' ); ?>
            </button>
            <button type="button" class="ranku-tab<?php echo $default_tab==='exp_monthly'?' active':''; ?>"
                    data-type="exp_monthly" role="tab" aria-selected="<?php echo $default_tab==='exp_monthly'?'true':'false'; ?>">
              <i class="fa-solid fa-fire"></i> <?php esc_html_e( '本月 EXP', 'weixiaoacg' ); ?>
            </button>
            <button type="button" class="ranku-tab<?php echo $default_tab==='followers'?' active':''; ?>"
                    data-type="followers" role="tab" aria-selected="<?php echo $default_tab==='followers'?'true':'false'; ?>">
              <i class="fa-solid fa-user-group"></i> <?php esc_html_e( '粉絲數', 'weixiaoacg' ); ?>
            </button>
            <button type="button" class="ranku-tab<?php echo $default_tab==='badges'?' active':''; ?>"
                    data-type="badges" role="tab" aria-selected="<?php echo $default_tab==='badges'?'true':'false'; ?>">
              <i class="fa-solid fa-medal"></i> <?php esc_html_e( '徽章數', 'weixiaoacg' ); ?>
            </button>
          </div>
          <div class="ranku-tabs-meta">
            <span class="ranku-count-info" id="ranku-count-info">
              <?php esc_html_e( '累計 EXP', 'weixiaoacg' ); ?> · Top 100
            </span>
          </div>
        </div>

        <!-- 排行列表 -->
        <div class="ranku-list" id="ranku-list" aria-live="polite">
          <div class="ranku-loading">
            <div class="skeleton" style="height:78px;border-radius:14px;"></div>
            <div class="skeleton" style="height:78px;border-radius:14px;margin-top:10px;"></div>
            <div class="skeleton" style="height:78px;border-radius:14px;margin-top:10px;"></div>
            <div class="skeleton" style="height:78px;border-radius:14px;margin-top:10px;"></div>
            <div class="skeleton" style="height:78px;border-radius:14px;margin-top:10px;"></div>
          </div>
        </div>

        <!-- 分頁 -->
        <div class="ranku-pagination" id="ranku-pagination" hidden>
          <button type="button" class="ranku-page-btn" id="ranku-prev" disabled>
            <i class="fa-solid fa-chevron-left"></i> <?php esc_html_e( '上一頁', 'weixiaoacg' ); ?>
          </button>
          <span class="ranku-page-info" id="ranku-page-info">1 / 1</span>
          <button type="button" class="ranku-page-btn" id="ranku-next" disabled>
            <?php esc_html_e( '下一頁', 'weixiaoacg' ); ?> <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>

        <p class="ranku-updated-at">
          <i class="fa-regular fa-clock"></i>
          <?php esc_html_e( '最後更新：', 'weixiaoacg' ); ?>
          <span id="ranku-updated-time"><?php echo esc_html( $updated_at ?: '—' ); ?></span>
        </p>

      </div>

      <!-- 右欄：側欄 -->
      <aside class="ranku-sidebar">

        <!-- 排行規則 -->
        <div class="ranku-sidebar-card glass-mid">
          <h3 class="ranku-sidebar-title">
            <i class="fa-solid fa-scale-balanced" style="color:var(--accent-cyan);"></i>
            <?php esc_html_e( '排行規則', 'weixiaoacg' ); ?>
          </h3>
          <ul class="ranku-rules">
            <li><i class="fa-solid fa-bolt"></i> <strong><?php esc_html_e( '累計 EXP', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '註冊以來累計獲得的全部 EXP', 'weixiaoacg' ); ?></li>
            <li><i class="fa-solid fa-fire"></i> <strong><?php esc_html_e( '本月 EXP', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '本月新獲得的 EXP（每月 1 日歸零）', 'weixiaoacg' ); ?></li>
            <li><i class="fa-solid fa-user-group"></i> <strong><?php esc_html_e( '粉絲數', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '追蹤你的會員人數', 'weixiaoacg' ); ?></li>
            <li><i class="fa-solid fa-medal"></i> <strong><?php esc_html_e( '徽章數', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '解鎖的成就徽章總數', 'weixiaoacg' ); ?></li>
          </ul>
        </div>

        <!-- 隱私設定（僅登入） -->
        <?php if ( $is_logged_in ) : ?>
        <div class="ranku-sidebar-card glass-mid">
          <h3 class="ranku-sidebar-title">
            <i class="fa-solid fa-user-shield" style="color:var(--accent-violet);"></i>
            <?php esc_html_e( '隱私設定', 'weixiaoacg' ); ?>
          </h3>
          <p class="ranku-priv-desc">
            <?php esc_html_e( '是否要讓自己出現在排行榜？', 'weixiaoacg' ); ?>
          </p>
          <label class="ranku-toggle">
            <input type="checkbox" id="ranku-visibility-toggle"
                   <?php checked( ! empty( $privacy_data['visible'] ) ); ?>>
            <span class="ranku-toggle-slider"></span>
            <span class="ranku-toggle-label" id="ranku-toggle-label">
              <?php echo ! empty( $privacy_data['visible'] )
                  ? esc_html__( '顯示於排行榜', 'weixiaoacg' )
                  : esc_html__( '已隱藏', 'weixiaoacg' ); ?>
            </span>
          </label>
          <p class="ranku-priv-hint">
            <i class="fa-solid fa-circle-info"></i>
            <?php esc_html_e( '隱藏後立即從排行榜移除；下次重算才會重新計入', 'weixiaoacg' ); ?>
          </p>
        </div>
        <?php endif; ?>

        <!-- 升級提示（未登入） -->
        <?php if ( ! $is_logged_in ) : ?>
        <div class="ranku-sidebar-card glass-mid ranku-cta">
          <h3 class="ranku-sidebar-title">
            <i class="fa-solid fa-rocket" style="color:var(--accent-cyan);"></i>
            <?php esc_html_e( '加入競爭', 'weixiaoacg' ); ?>
          </h3>
          <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0 0 12px;">
            <?php esc_html_e( '登入後追蹤動漫、發表評分與留言即可累積 EXP，挑戰榜首！', 'weixiaoacg' ); ?>
          </p>
          <a class="btn btn-primary btn-block" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
            <i class="fa-solid fa-right-to-bracket"></i> <?php esc_html_e( '立即登入', 'weixiaoacg' ); ?>
          </a>
        </div>
        <?php endif; ?>

      </aside>

    </div>
  </div>
</section>

<div class="toast-container" id="ranku-toast-container" aria-live="polite"></div>

<?php get_footer(); ?>
