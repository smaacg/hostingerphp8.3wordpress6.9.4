<?php
/**
 * Template Name: 會員中心
 * Version: 2.0.1 (2026-05-11)
 *
 * 架構：本檔僅負責登入檢查 + 框架，資料統計交給 inc/member-stats.php，
 *      各 tab render 交給 inc/member-render.php。
 *
 * v2.0.1 變更：
 *   - 最外層 <main> 改回 <div>，避免 Blocksy 偵測到 <main> 觸發兩欄 grid 佈局
 *     導致右半邊空白。其餘結構與功能不變。
 */

if (!defined('ABSPATH')) exit;

// 未登入導向
if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}

// 載入模組
require_once get_stylesheet_directory() . '/inc/member-stats.php';
require_once get_stylesheet_directory() . '/inc/member-render.php';

// ---------- 基本資料 ----------
$user        = wp_get_current_user();
$uid         = $user->ID;
$display     = $user->display_name ?: $user->user_login;
$email       = $user->user_email;
$reg_date    = mysql2date('Y-m-d', $user->user_registered);
$bio         = get_user_meta($uid, 'description', true);

// 頭像（優先 UM）
$avatar_url = function_exists('um_get_user_avatar_url')
    ? um_get_user_avatar_url($uid, 'original')
    : get_avatar_url($uid, ['size' => 200]);

// ---------- 點數 / 等級 ----------
$points      = (int) get_user_meta($uid, 'smacg_points', true);
$lvl_info    = smacg_calc_level($points);   // [level, current, next, percent, title]

// ---------- 會員方案 ----------
$plan_label  = smacg_get_plan_label($user);

// ---------- 清單 / 評分 / 點數紀錄 / 留言 ----------
$watchlist   = smacg_build_watchlist($uid);              // 已整合 favorited 顯示
$ratings     = smacg_get_user_ratings($uid);             // 批次預載 post 資料
$points_log  = smacg_get_points_log($uid, 50);
$recent_cmt  = smacg_get_recent_comments($uid, 5);

// ---------- 統計 ----------
$stats       = smacg_calc_member_stats($watchlist, $ratings);
/* $stats 結構：
   ['counts' => ['all','watching','completed','want','favorited','dropped'],
    'watch_time' => ['minutes','days','hours'],
    'rating' => ['count','avg','distribution'=>[1..10],'top3','bottom3'],
    'genres' => [['name','count','percent'],...],
    'studios'=> [['name','count'],...],
    'years'  => [['year','count'],...]]
*/

get_header(); ?>

<div class="mc-wrap" data-uid="<?php echo (int)$uid; ?>">

    <?php /* === Hero === */ ?>
    <section class="mc-hero">
        <div class="mc-hero-avatar">
            <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display); ?>" loading="lazy">
        </div>
        <div class="mc-hero-info">
            <h1 class="mc-hero-name"><?php echo esc_html($display); ?>
                <span class="mc-plan-badge"><?php echo esc_html($plan_label); ?></span>
            </h1>
            <p class="mc-hero-meta">
                <span>📧 <?php echo esc_html($email); ?></span>
                <span>📅 <?php echo esc_html($reg_date); ?></span>
            </p>
            <?php if ($bio): ?><p class="mc-hero-bio"><?php echo esc_html($bio); ?></p><?php endif; ?>

            <div class="mc-level-bar" title="Lv.<?php echo $lvl_info['level']; ?>　<?php echo $points; ?> 點">
                <div class="mc-level-fill" style="width:<?php echo $lvl_info['percent']; ?>%"></div>
                <span class="mc-level-text">
                    Lv.<?php echo $lvl_info['level']; ?> · <?php echo esc_html($lvl_info['title']); ?>
                    （<?php echo $points; ?> / <?php echo $lvl_info['next']; ?>）
                </span>
            </div>

            <div class="mc-hero-stats">
                <div><b><?php echo $stats['counts']['watching']; ?></b><span>追番中</span></div>
                <div><b><?php echo $stats['counts']['completed']; ?></b><span>已看完</span></div>
                <div><b><?php echo $stats['counts']['favorited']; ?></b><span>收藏</span></div>
                <div><b><?php echo $stats['rating']['count']; ?></b><span>評分</span></div>
                <div><b><?php echo $stats['watch_time']['days']; ?>天</b><span>觀看時數</span></div>
            </div>
        </div>
    </section>

    <?php /* === Tabs === */ ?>
    <nav class="mc-tabs" role="tablist">
        <?php
        $tabs = [
            'dashboard' => '📊 總覽',
            'watchlist' => '🎬 我的清單',
            'stats'     => '📈 統計',
            'ratings'   => '⭐ 我的評分',
            'comments'  => '💬 留言',
            'points'    => '🪙 點數',
            'settings'  => '⚙️ 設定',
        ];
        foreach ($tabs as $k => $label):
            $active = $k === 'dashboard' ? ' active' : '';
        ?>
            <button class="mc-tab<?php echo $active; ?>" data-tab="<?php echo $k; ?>" role="tab"><?php echo $label; ?></button>
        <?php endforeach; ?>
    </nav>

    <?php /* === Panels === */ ?>
    <div class="mc-panels">
        <section class="mc-panel active" data-panel="dashboard">
            <?php smacg_render_dashboard($watchlist, $stats, $recent_cmt, $points_log, $plan_label); ?>
        </section>

        <section class="mc-panel" data-panel="watchlist">
            <?php smacg_render_watchlist($watchlist, $stats['counts']); ?>
        </section>

        <section class="mc-panel" data-panel="stats">
            <?php smacg_render_stats($stats); ?>
        </section>

        <section class="mc-panel" data-panel="ratings">
            <?php smacg_render_ratings($ratings, $stats['rating']); ?>
        </section>

        <section class="mc-panel" data-panel="comments">
            <?php smacg_render_comments($uid); ?>
        </section>

        <section class="mc-panel" data-panel="points">
            <?php smacg_render_points($points, $lvl_info, $points_log); ?>
        </section>

        <section class="mc-panel" data-panel="settings">
            <?php smacg_render_settings($user); ?>
        </section>
    </div>

</div>

<?php get_footer(); ?>
