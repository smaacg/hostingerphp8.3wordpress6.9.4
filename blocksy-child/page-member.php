<?php
/**
 * Template Name: 會員中心
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$smacg_members_ready = defined( 'SMACG_MEMBERS_VERSION' )
    && function_exists( 'smacg_build_watchlist' )
    && function_exists( 'smacg_calc_member_stats' )
    && function_exists( 'smacg_render_dashboard' );

if ( ! $smacg_members_ready ) {
    get_header(); ?>
    <div class="mc-wrap">
        <section class="mc-hero" style="padding:40px;text-align:center;">
            <h1>⚠️ 會員中心暫時無法使用</h1>
            <p style="margin-top:16px;color:#666;">
                <strong>SMACG Members</strong> 外掛尚未啟用或載入失敗，會員中心相關功能已停用。
            </p>
            <p style="color:#666;">請聯絡網站管理員至外掛頁啟用 <code>smacg-members</code>。</p>
            <?php if ( current_user_can( 'activate_plugins' ) ) : ?>
                <p style="margin-top:24px;">
                    <a class="mc-btn mc-btn-primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">前往外掛頁</a>
                </p>
            <?php endif; ?>
        </section>
    </div>
    <?php
    get_footer();
    return;
}

$user     = wp_get_current_user();
$uid      = $user->ID;
$display  = $user->display_name ?: $user->user_login;
$email    = $user->user_email;
$reg_date = mysql2date( 'Y-m-d', $user->user_registered );
$bio      = get_user_meta( $uid, 'description', true );

/* 頭像 */
$avatar_url = '';
$custom_aid = (int) get_user_meta( $uid, 'smacg_avatar_id', true );
if ( $custom_aid && wp_attachment_is_image( $custom_aid ) ) {
    $img = wp_get_attachment_image_src( $custom_aid, 'medium' );
    if ( $img ) $avatar_url = $img[0] . '?v=' . get_post_modified_time( 'U', false, $custom_aid );
}
if ( ! $avatar_url && function_exists( 'um_get_user_avatar_url' ) ) {
    $avatar_url = um_get_user_avatar_url( $uid, 'original' );
}
if ( ! $avatar_url ) $avatar_url = get_avatar_url( $uid, [ 'size' => 200 ] );
$account_url = get_permalink();

/* EXP / 等級 */
$lvl_info = function_exists( 'smacg_get_user_level_info' )
    ? smacg_get_user_level_info( $uid )
    : [
        'exp' => 0, 'level' => 1, 'title' => '新進會員', 'icon' => '🌱',
        'percent' => 0, 'in_level_exp' => 0, 'level_total_exp' => 1,
        'to_next' => 5, 'is_max' => false, 'next_floor' => 5,
    ];

/* TFT 段位賽季 */
if ( function_exists( 'smacg_get_user_rank_season_info' ) ) {
    $rank_info = smacg_get_user_rank_season_info( $uid );
} else {
    $rank_info = [
        'season_code' => '', 'season_label' => '', 'score' => 0, 'rank' => 0,
        'tier' => [ 'key'=>'iron','division'=>'IV','min'=>0,'label'=>'鐵 IV','color'=>'#6b6b6b','icon'=>'🥉' ],
        'progress' => [ 'is_max'=>false,'cur_min'=>0,'next_min'=>100,'to_next'=>100,'percent'=>0,'next_label'=>'鐵 III' ],
    ];
}
$career_peak   = function_exists( 'smacg_get_user_career_peak_tier' ) ? smacg_get_user_career_peak_tier( $uid ) : null;
$rank_page_url = home_url( '/ranking-users/?tab=rank_season' );
$guide_url     = home_url( '/level-guide/' );

$job_title  = function_exists( 'smacg_get_user_job_title' ) ? smacg_get_user_job_title( $uid ) : [];
$plan_label = function_exists( 'smacg_get_plan_label' )     ? smacg_get_plan_label( $user )   : '一般會員';

$privacy = function_exists( 'smacg_get_user_privacy' )
    ? smacg_get_user_privacy( $uid )
    : [ 'show_email' => 0, 'show_continue_watching' => 1 ];

$is_owner = ( get_current_user_id() === $uid );
if ( $is_owner || ! empty( $privacy['show_email'] ) ) {
    $display_email = $user->user_email;
} else {
    $display_email = function_exists( 'smacg_mask_email' ) ? smacg_mask_email( $user->user_email ) : '***@***';
}

$watchlist  = function_exists( 'smacg_build_watchlist' )     ? smacg_build_watchlist( $uid )    : [];
$ratings    = function_exists( 'smacg_get_user_ratings' )    ? smacg_get_user_ratings( $uid )   : [];
$points_log = function_exists( 'smacg_get_exp_log' )         ? smacg_get_exp_log( $uid, 50 )    : [];
$recent_cmt = function_exists( 'smacg_get_recent_comments' ) ? smacg_get_recent_comments( $uid, 5 ) : [];

$stats = function_exists( 'smacg_calc_member_stats' )
    ? smacg_calc_member_stats( $watchlist, $ratings )
    : [
        'counts' => [ 'watching'=>0,'completed'=>0,'favorited'=>0 ],
        'rating' => [ 'count' => 0 ], 'watch_time' => [ 'days' => 0 ],
    ];

$public_profile_url = function_exists( 'smacg_get_public_profile_url' ) ? smacg_get_public_profile_url( $uid ) : '';
$mc_followers   = function_exists( 'smacg_get_followers_count' )  ? smacg_get_followers_count( $uid )  : 0;
$mc_following   = function_exists( 'smacg_get_following_count' )  ? smacg_get_following_count( $uid )  : 0;
$mc_badge_count = function_exists( 'smacg_get_user_badge_count' ) ? smacg_get_user_badge_count( $uid ) : 0;

get_header(); ?>

<div class="mc-wrap" data-uid="<?php echo (int) $uid; ?>">

    <section class="mc-hero">
        <div class="mc-hero-avatar">
            <label class="mc-hero-avatar-link" title="點擊更換頭像">
                <img id="mc-avatar-img" src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $display ); ?>" loading="lazy">
                <div class="mc-hero-avatar-overlay">
                    <i class="fa-solid fa-camera"></i><span>更換頭像</span>
                </div>
                <input type="file" id="mc-avatar-input" accept="image/jpeg,image/png,image/webp" hidden>
            </label>

            <div class="mc-avatar-hint">
                <i class="fa-solid fa-circle-info"></i> 支援 JPG / PNG / WebP，最大 5 MB，建議方形圖片
            </div>

            <div id="mc-avatar-progress" class="mc-avatar-progress" hidden>
                <div class="mc-avatar-progress-bar"><div class="mc-avatar-progress-fill" style="width:0%"></div></div>
                <span class="mc-avatar-progress-text">0%</span>
            </div>
            <div id="mc-avatar-msg" class="mc-avatar-msg" hidden></div>
            <noscript>
                <a href="<?php echo esc_url( $account_url ); ?>" class="mc-avatar-fallback">請啟用 JavaScript 修改頭像</a>
            </noscript>
        </div>

        <div class="mc-hero-info">
            <h1 class="mc-hero-name">
                <?php echo esc_html( $display ); ?>
                <span class="mc-plan-badge"><?php echo esc_html( $plan_label ); ?></span>

                <?php if ( ! empty( $job_title ) ) : ?>
                    <span class="mc-job-badge" title="<?php echo esc_attr( $job_title['title_ref'] ?? '' ); ?>">
                        <?php echo esc_html( $job_title['job_icon'] ?? '' ); ?>
                        <?php echo esc_html( $job_title['title_name'] ?? '' ); ?>
                    </span>
                <?php endif; ?>

                <?php
                $tier      = $rank_info['tier'];
                $has_score = $rank_info['score'] > 0;
                $rank_pos  = (int) $rank_info['rank'];
                ?>
                <a href="<?php echo esc_url( $rank_page_url ); ?>"
                   class="mc-tier-mini<?php echo $has_score ? '' : ' mc-tier-mini--zero'; ?>"
                   style="--tier-color: <?php echo esc_attr( $tier['color'] ); ?>;"
                   title="<?php echo esc_attr( sprintf( '%s · %s 分 · %s',
                       $rank_info['season_label'] ?: '本賽季',
                       number_format( $rank_info['score'] ),
                       $rank_pos > 0 ? '#' . $rank_pos : '未上榜' ) ); ?>">
                    <span class="mc-tier-mini__icon"><?php echo esc_html( $tier['icon'] ); ?></span>
                    <span class="mc-tier-mini__label"><?php echo esc_html( $tier['label'] ); ?></span>
                    <?php if ( $rank_pos > 0 && $rank_pos <= 200 ) : ?>
                        <span class="mc-tier-mini__rank">#<?php echo $rank_pos; ?></span>
                    <?php endif; ?>
                </a>

                <?php if ( $career_peak ) : ?>
                    <span class="mc-tier-mini mc-tier-mini--peak" title="生涯最高段位">
                        <span class="mc-tier-mini__icon"><?php echo esc_html( $career_peak['icon'] ); ?></span>
                        <span class="mc-tier-mini__label">生涯 <?php echo esc_html( $career_peak['label'] ); ?></span>
                    </span>
                <?php endif; ?>

                <?php if ( $public_profile_url ) : ?>
                    <a href="<?php echo esc_url( $public_profile_url ); ?>" class="mc-public-link" title="查看公開個人頁">
                        <i class="fa-solid fa-eye"></i><span>公開頁</span>
                    </a>
                <?php endif; ?>
            </h1>

            <p class="mc-hero-meta">
                <span>📧 <?php echo esc_html( $display_email ); ?></span>
                <span>📅 <?php echo esc_html( $reg_date ); ?></span>
            </p>

            <p class="mc-follow-meta">
                <span class="mc-follow-stat"><b><?php echo number_format( $mc_followers ); ?></b><em>粉絲</em></span>
                <span class="mc-follow-stat"><b><?php echo number_format( $mc_following ); ?></b><em>追蹤中</em></span>
                <span class="mc-follow-stat"><b><?php echo number_format( $mc_badge_count ); ?></b><em>徽章</em></span>
            </p>

            <?php if ( $bio ) : ?>
                <p class="mc-hero-bio"><?php echo esc_html( $bio ); ?></p>
            <?php endif; ?>

            <div class="mc-progress-head">
                <span class="mc-progress-head__label">📊 我的進度</span>
                <a href="<?php echo esc_url( $guide_url ); ?>" class="mc-progress-head__guide" title="查看完整指南">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>規則說明</span>
                </a>
            </div>

            <div class="mc-level-bar" title="Lv.<?php echo (int) $lvl_info['level']; ?>　<?php echo (int) $lvl_info['exp']; ?> EXP">
                <div class="mc-level-fill" style="width:<?php echo (int) $lvl_info['percent']; ?>%"></div>
                <span class="mc-level-text">
                    <?php echo esc_html( $lvl_info['icon'] ); ?>
                    Lv.<?php echo (int) $lvl_info['level']; ?> · <?php echo esc_html( $lvl_info['title'] ); ?>
                    （<?php echo (int) $lvl_info['exp']; ?>
                    <?php if ( empty( $lvl_info['is_max'] ) ) : ?>
                        / <?php echo (int) $lvl_info['next_floor']; ?>
                    <?php else : ?>
                        <span class="mc-level-max">MAX</span>
                    <?php endif; ?>
                    EXP）
                </span>
            </div>

            <?php $prog = $rank_info['progress']; ?>
            <a href="<?php echo esc_url( $rank_page_url ); ?>"
               class="mc-rank-bar<?php echo $rank_info['score'] > 0 ? '' : ' mc-rank-bar--zero'; ?>"
               style="--tier-color: <?php echo esc_attr( $rank_info['tier']['color'] ); ?>;"
               title="<?php echo esc_attr( $rank_info['season_label'] ?: '本賽季' ); ?>">
                <div class="mc-rank-fill" style="width:<?php echo (int) $prog['percent']; ?>%"></div>
                <span class="mc-rank-text">
                    <?php echo esc_html( $rank_info['tier']['icon'] ); ?>
                    <?php echo esc_html( $rank_info['tier']['label'] ); ?>
                    ·
                    <?php echo number_format( $rank_info['score'] ); ?> 賽季積分
                    <?php if ( $prog['is_max'] ) : ?>
                        <span class="mc-rank-max">MAX</span>
                    <?php else : ?>
                        （距 <?php echo esc_html( $prog['next_label'] ); ?> 還差
                        <?php echo number_format( $prog['to_next'] ); ?> 分）
                    <?php endif; ?>
                </span>
            </a>

            <div class="mc-hero-stats">
                <div><b><?php echo (int) ( $stats['counts']['watching']  ?? 0 ); ?></b><span>追番中</span></div>
                <div><b><?php echo (int) ( $stats['counts']['completed'] ?? 0 ); ?></b><span>已看完</span></div>
                <div><b><?php echo (int) ( $stats['counts']['favorited'] ?? 0 ); ?></b><span>收藏</span></div>
                <div><b><?php echo (int) ( $stats['rating']['count']     ?? 0 ); ?></b><span>評分</span></div>
                <div><b><?php echo (int) ( $stats['watch_time']['days']  ?? 0 ); ?>天</b><span>觀看時數</span></div>
            </div>
        </div>
    </section>

    <?php if ( ! empty( $privacy['show_continue_watching'] ) && function_exists( 'smacg_render_continue_watching' ) ) : ?>
        <?php smacg_render_continue_watching( $watchlist ); ?>
    <?php endif; ?>

    <nav class="mc-tabs" role="tablist">
        <?php
        $tabs = [
            'dashboard'     => '📊 總覽',
            'watchlist'     => '🎬 我的清單',
            'stats'         => '📈 統計',
            'ratings'       => '⭐ 我的評分',
            'badges'        => '🏆 徽章',
            'career'        => '🎯 職業',
            'notifications' => '🔔 通知',
            'comments'      => '💬 留言',
            'points'        => '🎮 EXP',
            'settings'      => '⚙️ 設定',
        ];
        foreach ( $tabs as $k => $label ) :
            $active = $k === 'dashboard' ? ' active' : '';
            ?>
            <button class="mc-tab<?php echo $active; ?>" data-tab="<?php echo esc_attr( $k ); ?>" role="tab"><?php echo $label; ?></button>
        <?php endforeach; ?>
    </nav>

    <div class="mc-panels">
        <section class="mc-panel active" data-panel="dashboard">
            <?php if ( function_exists( 'smacg_render_dashboard' ) ) {
                smacg_render_dashboard( $watchlist, $stats, $recent_cmt, $points_log, $plan_label, $uid );
            } else { echo '<p class="mc-empty">總覽模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="watchlist">
            <?php if ( function_exists( 'smacg_render_watchlist' ) ) {
                smacg_render_watchlist( $watchlist, $stats['counts'] );
            } else { echo '<p class="mc-empty">清單模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="stats">
            <?php if ( function_exists( 'smacg_render_stats' ) ) {
                smacg_render_stats( $stats );
            } else { echo '<p class="mc-empty">統計模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="ratings">
            <?php if ( function_exists( 'smacg_render_ratings' ) ) {
                smacg_render_ratings( $ratings, $stats['rating'] );
            } else { echo '<p class="mc-empty">評分模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="badges">
            <?php if ( function_exists( 'smacg_render_badges' ) ) {
                smacg_render_badges( $uid );
            } else { echo '<p class="mc-empty">徽章模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="career">
            <?php if ( function_exists( 'smacg_render_career' ) ) {
                smacg_render_career( $uid, $lvl_info, $job_title );
            } else { echo '<p class="mc-empty">職業模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="notifications">
            <?php if ( function_exists( 'smacg_render_notifications_tab' ) ) {
                smacg_render_notifications_tab();
            } else { echo '<p class="mc-empty">通知模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="comments">
            <?php if ( function_exists( 'smacg_render_comments' ) ) {
                smacg_render_comments( $uid );
            } else { echo '<p class="mc-empty">留言模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="points">
            <?php if ( function_exists( 'smacg_render_points' ) ) {
                smacg_render_points( $uid, $lvl_info, $points_log );
            } else { echo '<p class="mc-empty">EXP 模組尚未載入</p>'; } ?>
        </section>
        <section class="mc-panel" data-panel="settings">
            <?php if ( function_exists( 'smacg_render_settings' ) ) {
                smacg_render_settings( $user, $privacy, $is_owner );
            } else { echo '<p class="mc-empty">設定模組尚未載入</p>'; } ?>
        </section>
    </div>

</div>

<style>
/* 「我的進度」標題列 + 規則說明連結 */
.mc-progress-head {
    display: flex; align-items: center; justify-content: space-between;
    margin: 16px 0 6px;
}
.mc-progress-head__label {
    font-size: 14px; font-weight: 700;
    color: var(--text-color, #fff);
    opacity: 0.85;
}
.mc-progress-head__guide {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px;
    background: rgba(40, 200, 214, 0.08);
    border: 1px solid rgba(40, 200, 214, 0.25);
    color: var(--accent-cyan, #28c8d6);
    font-size: 12px; font-weight: 600;
    text-decoration: none;
    transition: all .2s ease;
}
.mc-progress-head__guide:hover {
    background: rgba(40, 200, 214, 0.18);
    transform: translateY(-1px);
}
</style>

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
            <div class="mc-cropper-canvas"><img id="mc-cropper-image" src="" alt="裁切預覽"></div>
            <div class="mc-cropper-tips">
                <i class="fa-solid fa-lightbulb"></i> 拖曳調整裁切框，滾輪縮放。輸出為 400×400 方形頭像。
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
