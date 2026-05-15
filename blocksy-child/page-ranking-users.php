<?php
/**
 * Template Name: 會員排行榜
 * Template Post Type: page
 * @package weixiaoacg
 */

if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$current_uid  = get_current_user_id();
$is_logged_in = is_user_logged_in();
$updated_at   = get_option( 'smacg_ranking_last_rebuild', '' );
$privacy_data = function_exists( 'smacg_ranking_privacy_localize_data' )
    ? smacg_ranking_privacy_localize_data()
    : [ 'visible' => true ];

$default_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'exp_total';
$valid_tabs  = [ 'exp_total', 'exp_monthly', 'followers', 'badges', 'rank_season' ];
if ( ! in_array( $default_tab, $valid_tabs, true ) ) $default_tab = 'exp_total';

$season_info = null;
if ( $is_logged_in && function_exists( 'smacg_get_user_rank_season_info' ) ) {
    $season_info = smacg_get_user_rank_season_info( $current_uid );
}
$season_label  = function_exists( 'smacg_get_season_label' ) ? smacg_get_season_label() : '';
$guide_url     = home_url( '/level-guide/' );
?>

<section class="ranku-hero">
  <canvas id="ranku-particles" class="ranku-hero__particles" aria-hidden="true"></canvas>
  <div class="ranku-hero__overlay"></div>
  <div class="container ranku-hero__content">
    <div class="ranku-hero__eyebrow">
      <span class="chip"><i class="fa-solid fa-users"></i> <?php esc_html_e( '社群會員排名', 'weixiaoacg' ); ?></span>
      <span class="chip chip--green"><i class="fa-solid fa-clock-rotate-left"></i> <?php esc_html_e( '每小時更新', 'weixiaoacg' ); ?></span>
      <?php if ( $season_label ) : ?>
        <span class="chip chip--violet"><i class="fa-solid fa-trophy"></i> <?php echo esc_html( $season_label ); ?></span>
      <?php endif; ?>
    </div>
    <h1 class="ranku-hero__title">
      <span class="ranku-hero__trophy">🏆</span>
      <?php esc_html_e( '會員排行榜', 'weixiaoacg' ); ?>
    </h1>
    <p class="ranku-hero__subtitle"><?php esc_html_e( '看看誰才是真正的二次元勇者', 'weixiaoacg' ); ?></p>

    <?php if ( $is_logged_in ) :
        $my_pos = function_exists( 'smacg_ranking_user_position' )
            ? smacg_ranking_user_position( $default_tab, $current_uid )
            : null;
    ?>
      <div class="ranku-hero__me" id="ranku-hero-me" data-tab="<?php echo esc_attr( $default_tab ); ?>">
        <span class="ranku-hero__me-label"><?php esc_html_e( '我的名次：', 'weixiaoacg' ); ?></span>
        <span class="ranku-hero__me-pos" id="ranku-me-pos">
          <?php echo $my_pos ? '#' . esc_html( $my_pos ) : esc_html__( '未上榜', 'weixiaoacg' ); ?>
        </span>
      </div>

      <?php if ( $season_info ) :
          $tier = $season_info['tier'];
          $prog = $season_info['progress'];
      ?>
      <div class="ranku-hero__tier" style="--tier-color: <?php echo esc_attr( $tier['color'] ); ?>;">
        <div class="ranku-hero__tier-badge">
          <span class="ranku-hero__tier-icon"><?php echo esc_html( $tier['icon'] ); ?></span>
          <span class="ranku-hero__tier-label"><?php echo esc_html( $tier['label'] ); ?></span>
        </div>
        <div class="ranku-hero__tier-meta">
          <span><?php echo esc_html( number_format( $season_info['score'] ) ); ?> <?php esc_html_e( '賽季積分', 'weixiaoacg' ); ?></span>
          <?php if ( ! $prog['is_max'] ) : ?>
            <span class="ranku-hero__tier-to-next">
              <?php echo esc_html( sprintf( __( '距離 %s 還差 %d 分', 'weixiaoacg' ),
                  $prog['next_label'], $prog['to_next'] ) ); ?>
            </span>
          <?php else : ?>
            <span class="ranku-hero__tier-to-next"><?php esc_html_e( '已達最高段位', 'weixiaoacg' ); ?></span>
          <?php endif; ?>
        </div>
        <div class="ranku-hero__tier-bar">
          <div class="ranku-hero__tier-fill" style="width: <?php echo (int) $prog['percent']; ?>%;"></div>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<section class="ranku-body section">
  <div class="container">
    <div class="ranku-layout">

      <div class="ranku-main">

        <div class="ranku-tabs-row">
          <div class="ranku-tabs" id="ranku-tabs" role="tablist" aria-label="<?php esc_attr_e( '排行榜類別', 'weixiaoacg' ); ?>">
            <?php
            $tabs = [
              'exp_total'   => [ 'fa-bolt',       __( '累計 EXP', 'weixiaoacg' ) ],
              'exp_monthly' => [ 'fa-fire',       __( '本月 EXP', 'weixiaoacg' ) ],
              'rank_season' => [ 'fa-trophy',     __( '賽季排位', 'weixiaoacg' ) ],
              'followers'   => [ 'fa-user-group', __( '粉絲數', 'weixiaoacg' ) ],
              'badges'      => [ 'fa-medal',      __( '徽章數', 'weixiaoacg' ) ],
            ];
            foreach ( $tabs as $key => $info ) {
                [ $icon, $label ] = $info;
                $active = ( $key === $default_tab ) ? ' active' : '';
                printf(
                    '<button type="button" class="ranku-tab%s" data-type="%s" role="tab" aria-selected="%s"><i class="fa-solid %s"></i> %s</button>',
                    esc_attr( $active ), esc_attr( $key ),
                    $active ? 'true' : 'false',
                    esc_attr( $icon ), esc_html( $label )
                );
            }
            ?>
          </div>
          <div class="ranku-tabs-meta">
            <span class="ranku-count-info" id="ranku-count-info">
              <?php esc_html_e( '累計 EXP', 'weixiaoacg' ); ?> · Top 100
            </span>
          </div>
        </div>

        <div class="ranku-list" id="ranku-list" aria-live="polite">
          <div class="ranku-loading">
            <?php for ( $i = 0; $i < 5; $i++ ) : ?>
              <div class="skeleton" style="height:78px;border-radius:14px;margin-top:<?php echo $i ? 10 : 0; ?>px;"></div>
            <?php endfor; ?>
          </div>
        </div>

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

      <aside class="ranku-sidebar">

        <div class="ranku-sidebar-card glass-mid">
          <h3 class="ranku-sidebar-title">
            <i class="fa-solid fa-scale-balanced" style="color:var(--accent-cyan);"></i>
            <?php esc_html_e( '排行規則', 'weixiaoacg' ); ?>
          </h3>
          <ul class="ranku-rules">
            <li><i class="fa-solid fa-bolt"></i> <strong><?php esc_html_e( '累計 EXP', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '註冊以來累計獲得的全部 EXP', 'weixiaoacg' ); ?></li>
            <li><i class="fa-solid fa-fire"></i> <strong><?php esc_html_e( '本月 EXP', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '本月新獲得的 EXP（每月 1 日歸零）', 'weixiaoacg' ); ?></li>
            <li><i class="fa-solid fa-trophy"></i> <strong><?php esc_html_e( '賽季排位', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '本季活躍積分，按 TFT 段位制（鐵～菁英），每季結束結算並重置', 'weixiaoacg' ); ?></li>
            <li><i class="fa-solid fa-user-group"></i> <strong><?php esc_html_e( '粉絲數', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '追蹤你的會員人數', 'weixiaoacg' ); ?></li>
            <li><i class="fa-solid fa-medal"></i> <strong><?php esc_html_e( '徽章數', 'weixiaoacg' ); ?></strong> — <?php esc_html_e( '解鎖的成就徽章總數', 'weixiaoacg' ); ?></li>
          </ul>
          <a href="<?php echo esc_url( $guide_url ); ?>" class="ranku-guide-link">
            <i class="fa-solid fa-book"></i>
            <?php esc_html_e( '查看完整規則指南', 'weixiaoacg' ); ?>
            <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>

        <?php if ( $is_logged_in ) : ?>
        <div class="ranku-sidebar-card glass-mid">
          <h3 class="ranku-sidebar-title">
            <i class="fa-solid fa-user-shield" style="color:var(--accent-violet);"></i>
            <?php esc_html_e( '隱私設定', 'weixiaoacg' ); ?>
          </h3>
          <p class="ranku-priv-desc"><?php esc_html_e( '是否要讓自己出現在排行榜？', 'weixiaoacg' ); ?></p>
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

<style>
/* 規則卡底部「完整規則」連結 */
.ranku-guide-link {
  display: flex; align-items: center; gap: 8px;
  margin-top: 14px; padding: 10px 14px;
  background: rgba(40, 200, 214, 0.08);
  border: 1px solid rgba(40, 200, 214, 0.25);
  border-radius: 10px;
  color: var(--accent-cyan, #28c8d6);
  font-size: 13px; font-weight: 600;
  text-decoration: none;
  transition: all .2s ease;
}
.ranku-guide-link:hover {
  background: rgba(40, 200, 214, 0.15);
  transform: translateX(2px);
}
.ranku-guide-link i:last-child { margin-left: auto; }
</style>

<?php get_footer(); ?>
