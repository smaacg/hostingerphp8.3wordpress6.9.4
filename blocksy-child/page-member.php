<?php
/**
 * Template Name: 會員中心
 * Version: 2.3.0 (2026-05-13)
 *
 * 架構：本檔僅負責登入檢查 + 框架，資料統計交給 inc/member-stats.php，
 *      各 tab render 交給 inc/member-render.php。
 *
 * v2.0.1：<main> 改回 <div>，避免 Blocksy 雙欄 grid 觸發空白
 * v2.0.2：頭像改為 <label> + 隱藏 <input type="file">，支援即時上傳
 * v2.0.3：移除 /account/ 依賴；fallback URL 改為自家會員頁
 * v2.0.4：P1-2 新增 Continue Watching 橫向列（Hero 下方、Tab 上方）
 * v2.1.0：頭像上傳優化 - A/B/E/G
 *   - A: 頭像上傳區新增格式/大小提示文字
 *   - B: <input accept> 移除 image/gif（後端原本就拒絕）
 *   - E+G: 新增 Cropper.js 裁切 Modal DOM（#mc-cropper-modal）
 *   - 新增進度條 DOM（#mc-avatar-progress）
 * v2.2.0：Batch 1A 公開個人頁
 *   - Hero plan badge 旁新增「公開頁」連結
 * v2.3.0：Batch 1B-3 追蹤系統 UI
 *   - Hero 區新增「粉絲 / 追蹤中」數字顯示
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

// 頭像：自訂上傳 > UM > Gravatar
$avatar_url = '';
$custom_aid = (int) get_user_meta($uid, 'smacg_avatar_id', true);
if ($custom_aid && wp_attachment_is_image($custom_aid)) {
    $img = wp_get_attachment_image_src($custom_aid, 'medium');
    if ($img) {
        $avatar_url = $img[0] . '?v=' . get_post_modified_time('U', false, $custom_aid);
    }
}
if (!$avatar_url && function_exists('um_get_user_avatar_url')) {
    $avatar_url = um_get_user_avatar_url($uid, 'original');
}
if (!$avatar_url) {
    $avatar_url = get_avatar_url($uid, ['size' => 200]);
}
$account_url = get_permalink();

// ---------- 點數 / 等級 ----------
$points      = (int) get_user_meta($uid, 'smacg_points', true);
$lvl_info    = smacg_calc_level($points);

// ---------- 會員方案 ----------
$plan_label  = smacg_get_plan_label($user);

// ---------- 隱私設定 + Email 遮罩 ----------
$privacy  = smacg_get_user_privacy( $uid );

$is_owner      = ( get_current_user_id() === $uid );
$display_email = $is_owner
    ? $user->user_email
    : ( ! empty( $privacy['show_email'] ) ? $user->user_email : smacg_mask_email( $user->user_email ) );

// ---------- 清單 / 評分 / 點數 / 留言 ----------
$watchlist   = smacg_build_watchlist($uid);
$ratings     = smacg_get_user_ratings($uid);
$points_log  = smacg_get_points_log($uid, 50);
$recent_cmt  = smacg_get_recent_comments($uid, 5);

// ---------- 統計 ----------
$stats       = smacg_calc_member_stats($watchlist, $ratings);

// ---------- v2.2.0: 公開頁 URL ----------
$public_profile_url = function_exists('smacg_get_public_profile_url')
    ? smacg_get_public_profile_url($uid)
    : '';

// ---------- v2.3.0: 追蹤數 ----------
$mc_followers = function_exists('smacg_get_followers_count')
    ? smacg_get_followers_count($uid) : 0;
$mc_following = function_exists('smacg_get_following_count')
    ? smacg_get_following_count($uid) : 0;

get_header(); ?>

<div class="mc-wrap" data-uid="<?php echo (int)$uid; ?>">

    <?php /* === Hero === */ ?>
    <section class="mc-hero">
        <div class="mc-hero-avatar">
            <label class="mc-hero-avatar-link" title="點擊更換頭像">
                <img id="mc-avatar-img"
                     src="<?php echo esc_url($avatar_url); ?>"
                     alt="<?php echo esc_attr($display); ?>"
                     loading="lazy">
                <div class="mc-hero-avatar-overlay">
                    <i class="fa-solid fa-camera"></i>
                    <span>更換頭像</span>
                </div>
                <input type="file"
                       id="mc-avatar-input"
                       accept="image/jpeg,image/png,image/webp"
                       hidden>
            </label>

            <div class="mc-avatar-hint">
                <i class="fa-solid fa-circle-info"></i>
                支援 JPG / PNG / WebP，最大 5 MB，建議方形圖片
            </div>

            <div id="mc-avatar-progress" class="mc-avatar-progress" hidden>
                <div class="mc-avatar-progress-bar">
                    <div class="mc-avatar-progress-fill" style="width:0%"></div>
                </div>
                <span class="mc-avatar-progress-text">0%</span>
            </div>

            <div id="mc-avatar-msg" class="mc-avatar-msg" hidden></div>

            <noscript>
                <a href="<?php echo esc_url($account_url); ?>" class="mc-avatar-fallback">請啟用 JavaScript 修改頭像</a>
            </noscript>
        </div>

        <div class="mc-hero-info">
            <h1 class="mc-hero-name"><?php echo esc_html($display); ?>
                <span class="mc-plan-badge"><?php echo esc_html($plan_label); ?></span>

                <?php /* v2.2.0：公開頁入口 */ ?>
                <?php if ( $public_profile_url ) : ?>
                    <a href="<?php echo esc_url( $public_profile_url ); ?>"
                       class="mc-public-link"
                       title="查看公開個人頁（其他人看到的樣子）">
                        <i class="fa-solid fa-eye"></i>
                        <span>公開頁</span>
                    </a>
                <?php endif; ?>
            </h1>

            <p class="mc-hero-meta">
                <span>📧 <?php echo esc_html($display_email); ?></span>
                <span>📅 <?php echo esc_html($reg_date); ?></span>
            </p>

            <?php /* v2.3.0：追蹤數 */ ?>
            <p class="mc-follow-meta">
                <span class="mc-follow-stat">
                    <b><?php echo number_format( $mc_followers ); ?></b>
                    <em>粉絲</em>
                </span>
                <span class="mc-follow-stat">
                    <b><?php echo number_format( $mc_following ); ?></b>
                    <em>追蹤中</em>
                </span>
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

    <?php /* === Continue Watching - P1-2 === */ ?>
    <?php if ( ! empty( $privacy['show_continue_watching'] ) ) : ?>
        <?php smacg_render_continue_watching( $watchlist ); ?>
    <?php endif; ?>

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
            <?php smacg_render_dashboard($watchlist, $stats, $recent_cmt, $points_log, $plan_label, $uid); ?>
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
            <?php smacg_render_settings($user, $privacy, $is_owner); ?>
        </section>
    </div>

</div>

<?php /* Cropper.js 裁切 Modal */ ?>
<div id="mc-cropper-modal" class="mc-cropper-modal" hidden aria-hidden="true" role="dialog">
    <div class="mc-cropper-backdrop"></div>
    <div class="mc-cropper-dialog" role="document">
        <div class="mc-cropper-header">
            <h3><i class="fa-solid fa-crop-simple"></i> 裁切頭像</h3>
            <button type="button" class="mc-cropper-close" id="mc-cropper-close" aria-label="關閉">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="mc-cropper-body">
            <div class="mc-cropper-canvas">
                <img id="mc-cropper-image" src="" alt="裁切預覽">
            </div>

            <div class="mc-cropper-tips">
                <i class="fa-solid fa-lightbulb"></i>
                拖曳調整裁切框，滾輪縮放。輸出為 400×400 方形頭像。
            </div>
        </div>

        <div class="mc-cropper-footer">
            <button type="button" class="mc-btn mc-btn-secondary" id="mc-cropper-cancel">
                <i class="fa-solid fa-rotate-left"></i> 取消
            </button>
            <button type="button" class="mc-btn mc-btn-primary" id="mc-cropper-confirm">
                <i class="fa-solid fa-check"></i> 確認上傳
            </button>
        </div>
    </div>
</div>

<?php get_footer(); ?>
