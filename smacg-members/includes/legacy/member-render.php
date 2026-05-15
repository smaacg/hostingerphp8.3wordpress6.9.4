<?php
/**
 * Member Center - Render Layer
 *
 * 各 tab 的 HTML 輸出。所有清單一律「先渲染前 N 筆」，其餘由 AJAX 載入。
 * 桌機 20 / 行動 12（前端 JS 偵測，PHP 預設輸出 20）
 *
 * @version 2.4.0 (2026-05-14)
 *
 * v2.4.0 (2026-05-14) — Batch 2A-3：徽章 + 職業頁
 *   - 新增 smacg_render_badges( $uid )：列出所有 GamiPress 徽章與解鎖狀態
 *   - 新增 smacg_render_career( $uid, $lvl_info, $job_title )：職業選擇 / 進化路線
 *   - 不動 smacg_render_points()（Batch 2A-0 已遷移到 GamiPress）
 *
 * v2.3.0 (2026-05-13) — Batch 2A-0：
 *   - smacg_render_points() 改用 GamiPress API
 *   - 改顯示 Lv.X / 200、EXP 累積值、進度條到下一級
 *   - 等級稱號改 6 階：新進會員 / 新客 / 常客 / 熟客 / VIP / 黑卡
 *
 * v2.2.0 (2026-05-13) — Batch 1C-4：
 *   - 新增 smacg_render_notification_prefs_card()（通知偏好卡片）
 *   - smacg_render_settings() 卡片 2 之後插入通知偏好卡
 *
 * v2.1.0 (2026-05-13) — Batch C #9 + #14：時間軸 + 年度回顧入口
 * v2.0.6 (2026-05-13) — Batch B：完成率卡 + 留言分頁
 * v2.0.5 (2026-05-13)：移除「暱稱」、新增「顯示繼續觀看」toggle
 * v2.0.1：設定 tab 三張卡片連結重寫
 */

if (!defined('ABSPATH')) exit;

if (!defined('SMACG_PAGE_SIZE')) {
    define('SMACG_PAGE_SIZE', 20);
}

/* ========== 共用：單張 anime 卡片 ========== */
function smacg_render_anime_card($pid, $extra = []) {
    if (!$pid || get_post_status($pid) !== 'publish') return;
    $title     = get_the_title($pid);
    $permalink = get_permalink($pid);
    $thumb     = smacg_get_card_thumb_url($pid);
    $score     = get_post_meta($pid, 'anime_score_anilist', true);
    $score     = $score ? round($score / 10, 1) : null;
    $year      = (int) get_post_meta($pid, 'anime_season_year', true);
    $eps       = (int) get_post_meta($pid, 'anime_episodes', true);

    $status    = $extra['status']    ?? '';
    $progress  = $extra['progress']  ?? 0;
    $favorited = !empty($extra['favorited']);
    $user_score= $extra['user_score'] ?? null;

    $status_label = ['watching'=>'追番中','completed'=>'已看完','want'=>'想看','dropped'=>'棄番','favorited'=>'收藏'];
    ?>
    <article class="mc-anime-card"
         data-status="<?php echo esc_attr($status); ?>"
         data-favorited="<?php echo !empty($favorited) ? '1' : '0'; ?>"
         data-title="<?php echo esc_attr(mb_strtolower($title)); ?>">

        <a href="<?php echo esc_url($permalink); ?>" class="mc-card-thumb">
            <?php if ($thumb): ?>
                <?php
                if ( function_exists( 'smacg_picture_tag' ) && has_post_thumbnail( $pid ) ) {
                    echo smacg_picture_tag( $pid, 'medium', $title );
                } else { ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                <?php } ?>
            <?php else: ?>
                <div class="mc-card-thumb-ph" aria-hidden="true">🎬</div>
            <?php endif; ?>
            <?php if ($favorited): ?><span class="mc-card-heart" title="已收藏">♥</span><?php endif; ?>
            <?php if ($status && isset($status_label[$status])): ?>
                <span class="mc-card-status mc-card-status--<?php echo $status; ?>"><?php echo $status_label[$status]; ?></span>
            <?php endif; ?>
        </a>
        <div class="mc-card-body">
            <h4 class="mc-card-title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h4>
            <div class="mc-card-meta">
                <?php if ($year): ?><span><?php echo $year; ?></span><?php endif; ?>
                <?php if ($eps): ?><span><?php echo $eps; ?> 集</span><?php endif; ?>
                <?php if ($score): ?><span class="mc-card-score">⭐ <?php echo $score; ?></span><?php endif; ?>
            </div>
            <?php if ($status === 'watching' && $eps): ?>
                <div class="mc-card-progress">
                    <div class="mc-card-progress-fill" style="width:<?php echo min(100, round($progress/$eps*100)); ?>%"></div>
                    <span><?php echo $progress; ?> / <?php echo $eps; ?></span>
                </div>
            <?php endif; ?>
            <?php if ($user_score !== null): ?>
                <div class="mc-card-userscore">我的評分：<b><?php echo number_format($user_score, 1); ?></b></div>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

/* ========== 縮圖 fallback ========== */
function smacg_get_card_thumb_url($pid) {
    if (has_post_thumbnail($pid)) return get_the_post_thumbnail_url($pid, 'medium');
    $meta = get_post_meta($pid, 'anime_cover_image', true);
    if ($meta) return esc_url($meta);
    $content = get_post_field('post_content', $pid);
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m)) return $m[1];
    return '';
}

/* =====================================================
 *  Tab 1：總覽 Dashboard
 * ===================================================== */
function smacg_render_dashboard($watchlist, $stats, $recent_cmt, $points_log, $plan_label, $uid = 0) {
    $watching = array_filter($watchlist, fn($w) => $w['status'] === 'watching');
    $watching = array_slice($watching, 0, 6);

    $activity = [];
    if ( $uid > 0 && function_exists( 'smacg_get_recent_activity' ) ) {
        $activity = smacg_get_recent_activity( $uid, 15 );
    }
    $current_year = (int) date( 'Y' );
    ?>
    <div class="mc-dash-grid">
        <div class="mc-widget mc-widget--watching">
            <h3>🎬 追番中 <small>（最近 6 部）</small></h3>
            <?php if ($watching): ?>
                <div class="mc-card-grid mc-card-grid--compact">
                    <?php foreach ($watching as $w) smacg_render_anime_card($w['post_id'], $w); ?>
                </div>
            <?php else: ?>
                <p class="mc-empty">尚未追番，<a href="<?php echo home_url('/anime/'); ?>">去找喜歡的作品</a> →</p>
            <?php endif; ?>
        </div>

        <div class="mc-widget mc-widget--quick">
            <h3>📊 快速概覽</h3>
            <ul class="mc-quick-list">
                <li><span class="mc-dot mc-dot--watching"></span>追番中 <b><?php echo $stats['counts']['watching']; ?></b></li>
                <li><span class="mc-dot mc-dot--completed"></span>已看完 <b><?php echo $stats['counts']['completed']; ?></b></li>
                <li><span class="mc-dot mc-dot--want"></span>想看 <b><?php echo $stats['counts']['want']; ?></b></li>
                <li><span class="mc-dot mc-dot--favorited"></span>收藏 <b><?php echo $stats['counts']['favorited']; ?></b></li>
                <li><span class="mc-dot mc-dot--dropped"></span>棄番 <b><?php echo $stats['counts']['dropped']; ?></b></li>
            </ul>
            <div class="mc-watchtime">
                <span>累計觀看</span>
                <b><?php echo $stats['watch_time']['days']; ?> 天</b>
                <small>（<?php echo number_format($stats['watch_time']['hours']); ?> 小時）</small>
            </div>
        </div>

        <div class="mc-widget mc-widget--plan">
            <h3>👤 我的方案</h3>
            <div class="mc-plan-card"><?php echo esc_html($plan_label); ?></div>
            <a href="<?php echo home_url('/sponsor/'); ?>" class="mc-btn-upgrade">升級方案 →</a>
        </div>

        <div class="mc-widget mc-widget--comments">
            <h3>💬 最近留言</h3>
            <?php if ($recent_cmt): ?>
                <ul class="mc-cmt-list">
                    <?php foreach ($recent_cmt as $c): ?>
                        <li>
                            <a href="<?php echo esc_url(get_comment_link($c)); ?>">
                                <b><?php echo esc_html(get_the_title($c->comment_post_ID)); ?></b>
                                <p><?php echo esc_html(wp_trim_words($c->comment_content, 20)); ?></p>
                                <small><?php echo human_time_diff(strtotime($c->comment_date), current_time('timestamp')); ?> 前</small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="mc-empty">還沒留言</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $uid > 0 ): ?>
        <a href="<?php echo esc_url( home_url( '/year-review/?year=' . $current_year ) ); ?>" class="mc-yr-entry" aria-label="查看 <?php echo $current_year; ?> 年度回顧">
            <div class="mc-yr-entry-icon">✨</div>
            <div class="mc-yr-entry-body">
                <div class="mc-yr-entry-title">查看 <?php echo $current_year; ?> 年度回顧</div>
                <div class="mc-yr-entry-sub">看看這一年你和動畫的故事 →</div>
            </div>
            <div class="mc-yr-entry-arrow">›</div>
        </a>
    <?php endif; ?>

    <?php if ( ! empty( $activity ) ): ?>
        <div class="mc-widget mc-widget--timeline">
            <h3>📜 最近活動 <small>（最新 15 筆）</small></h3>
            <?php smacg_render_activity_timeline( $activity ); ?>
        </div>
    <?php endif; ?>
    <?php
}

/* =====================================================
 *  最近活動時間軸渲染
 * ===================================================== */
function smacg_render_activity_timeline( $events ) {
    if ( empty( $events ) ) {
        echo '<p class="mc-empty">最近沒有活動，開始追番吧！</p>';
        return;
    }
    ?>
    <ul class="mc-timeline">
        <?php foreach ( $events as $e ):
            $color = $e['color'] ?? 'accent';
            $link  = $e['link']  ?? '';
            $post_title = $e['post_id'] ? get_the_title( $e['post_id'] ) : '';
            ?>
            <li class="mc-timeline-item mc-timeline-item--<?php echo esc_attr( $color ); ?>">
                <div class="mc-timeline-dot" aria-hidden="true">
                    <span class="mc-timeline-icon"><?php echo esc_html( $e['icon'] ?? '•' ); ?></span>
                </div>
                <div class="mc-timeline-body">
                    <div class="mc-timeline-main">
                        <span class="mc-timeline-action"><?php echo esc_html( $e['title'] ); ?></span>
                        <?php if ( $post_title ): ?>
                            <?php if ( $link ): ?>
                                <a class="mc-timeline-target" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $post_title ); ?></a>
                            <?php else: ?>
                                <span class="mc-timeline-target"><?php echo esc_html( $post_title ); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ( ! empty( $e['meta'] ) ): ?>
                            <span class="mc-timeline-meta"><?php echo esc_html( $e['meta'] ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mc-timeline-time"><?php echo esc_html( $e['time_human'] ?? '—' ); ?></div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

/* =====================================================
 *  Tab 2：我的清單
 * ===================================================== */
function smacg_render_watchlist($watchlist, $counts) {
    $first = array_slice($watchlist, 0, SMACG_PAGE_SIZE);
    $total = count($watchlist);
    ?>
    <div class="mc-list-toolbar">
        <div class="mc-filter-bar" role="tablist">
            <button class="mc-filter-btn active" data-filter="all">全部 (<?php echo $counts['all']; ?>)</button>
            <button class="mc-filter-btn" data-filter="watching">追番中 (<?php echo $counts['watching']; ?>)</button>
            <button class="mc-filter-btn" data-filter="completed">已看完 (<?php echo $counts['completed']; ?>)</button>
            <button class="mc-filter-btn" data-filter="want">想看 (<?php echo $counts['want']; ?>)</button>
            <button class="mc-filter-btn" data-filter="favorited">收藏 (<?php echo $counts['favorited']; ?>)</button>
            <button class="mc-filter-btn" data-filter="dropped">棄番 (<?php echo $counts['dropped']; ?>)</button>
        </div>
        <div class="mc-list-tools">
            <input type="search" class="mc-search" placeholder="🔍 搜尋標題…" data-target="watchlist">
            <select class="mc-sort" data-target="watchlist">
                <option value="updated">最近更新</option>
                <option value="title">標題</option>
                <option value="progress">進度</option>
                <option value="year">年份</option>
            </select>
        </div>
    </div>

    <div class="mc-card-grid" id="mc-watchlist-grid">
        <?php foreach ($first as $w) smacg_render_anime_card($w['post_id'], $w); ?>
    </div>

    <?php if ($total > SMACG_PAGE_SIZE): ?>
        <div class="mc-loadmore-wrap">
            <button class="mc-loadmore" data-type="watchlist" data-loaded="<?php echo count($first); ?>" data-total="<?php echo $total; ?>">
                載入更多（剩 <span><?php echo $total - count($first); ?></span>）
            </button>
        </div>
        <script type="application/json" id="mc-watchlist-data"><?php echo wp_json_encode($watchlist); ?></script>
    <?php endif; ?>
    <?php
}

/* =====================================================
 *  Tab 3：統計
 * ===================================================== */
function smacg_render_stats($s) {
    $r = $s['rating'];
    $completion_rate = isset($s['completion_rate']) ? (float) $s['completion_rate'] : null;
    ?>
    <div class="mc-stats-grid">

        <div class="mc-stats-banner">
            <div>
                <span class="mc-banner-label">你已經沉浸在動畫世界</span>
                <b class="mc-banner-num"><?php echo $s['watch_time']['days']; ?></b>
                <span class="mc-banner-unit">天</span>
            </div>
            <div class="mc-banner-sub">
                共 <?php echo number_format($s['watch_time']['hours']); ?> 小時 ·
                追完 <?php echo $s['counts']['completed']; ?> 部作品
            </div>
        </div>

        <?php if ($completion_rate !== null):
            $denom = $s['counts']['completed'] + $s['counts']['dropped'] + $s['counts']['watching'];
            if      ($completion_rate >= 80) { $emoji = '🏆'; $remark = '超強毅力，幾乎部部追完！'; }
            elseif  ($completion_rate >= 60) { $emoji = '✨'; $remark = '完成率不錯，繼續保持！'; }
            elseif  ($completion_rate >= 40) { $emoji = '👀'; $remark = '加油，可以多看完幾部！'; }
            elseif  ($completion_rate >= 20) { $emoji = '🤔'; $remark = '挑戰一下，把追番中的看完吧！'; }
            else                             { $emoji = '🌱'; $remark = '剛開始，慢慢累積！'; }
        ?>
            <div class="mc-stats-card mc-stats-card--completion">
                <h3><?php echo $emoji; ?> 完成率</h3>
                <div class="mc-completion-wrap">
                    <div class="mc-completion-big">
                        <b><?php echo $completion_rate; ?></b><span>%</span>
                    </div>
                    <div class="mc-completion-bar">
                        <div class="mc-completion-fill" style="width:<?php echo min(100, $completion_rate); ?>%"></div>
                    </div>
                    <div class="mc-completion-meta">
                        已看完 <b><?php echo $s['counts']['completed']; ?></b> /
                        計入 <b><?php echo $denom; ?></b> 部
                        <small>（含追番中、已看完、棄番）</small>
                    </div>
                    <p class="mc-completion-remark"><?php echo esc_html($remark); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="mc-stats-card">
            <h3>⭐ 評分分布</h3>
            <?php if ($r['count']): ?>
                <div class="mc-stats-summary">
                    <div><b><?php echo $r['avg']; ?></b><span>平均分</span></div>
                    <div><b><?php echo $r['count']; ?></b><span>評分數</span></div>
                </div>
                <?php
                $max = max($r['distribution']) ?: 1;
                for ($i = 10; $i >= 1; $i--):
                    $c = $r['distribution'][$i];
                    $w = $c ? round($c / $max * 100) : 0;
                ?>
                    <div class="mc-bar-row">
                        <span class="mc-bar-label"><?php echo $i; ?> 分</span>
                        <div class="mc-bar"><div class="mc-bar-fill" style="width:<?php echo $w; ?>%"></div></div>
                        <span class="mc-bar-count"><?php echo $c; ?></span>
                    </div>
                <?php endfor; ?>
            <?php else: ?>
                <p class="mc-empty">還沒給作品評分</p>
            <?php endif; ?>
        </div>

        <?php if ($s['genres']):
            $colors = ['#ff6bae','#6bb6ff','#a78bfa','#fbbf24','#34d399','#f87171','#60a5fa','#c084fc'];
            $stops = []; $acc = 0;
            foreach ($s['genres'] as $i => $g) {
                $start = $acc;
                $acc  += $g['percent'];
                $stops[] = $colors[$i % 8] . " {$start}% {$acc}%";
            }
            $pie = 'conic-gradient(' . implode(',', $stops) . ')';
        ?>
            <div class="mc-stats-card">
                <h3>🎭 類型偏好 <small>Top 8</small></h3>
                <div class="mc-pie-wrap">
                    <div class="mc-pie" style="background:<?php echo esc_attr($pie); ?>"></div>
                    <ul class="mc-pie-legend">
                        <?php foreach ($s['genres'] as $i => $g): ?>
                            <li>
                                <span class="mc-pie-dot" style="background:<?php echo $colors[$i % 8]; ?>"></span>
                                <?php echo esc_html($g['name']); ?>
                                <b><?php echo $g['percent']; ?>%</b>
                                <small>（<?php echo $g['count']; ?>）</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($s['studios']): ?>
            <div class="mc-stats-card">
                <h3>🏢 製作公司 <small>Top 5</small></h3>
                <ol class="mc-rank-list">
                    <?php foreach ($s['studios'] as $i => $st): ?>
                        <li>
                            <span class="mc-rank-num">#<?php echo $i + 1; ?></span>
                            <span class="mc-rank-name"><?php echo esc_html($st['name']); ?></span>
                            <span class="mc-rank-count"><?php echo $st['count']; ?> 部</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <?php if ($s['years']):
            $ymax = max(array_column($s['years'], 'count')) ?: 1; ?>
            <div class="mc-stats-card">
                <h3>📅 年代分布</h3>
                <?php foreach ($s['years'] as $y): $w = round($y['count']/$ymax*100); ?>
                    <div class="mc-bar-row">
                        <span class="mc-bar-label"><?php echo esc_html($y['year']); ?></span>
                        <div class="mc-bar"><div class="mc-bar-fill mc-bar-fill--alt" style="width:<?php echo $w; ?>%"></div></div>
                        <span class="mc-bar-count"><?php echo $y['count']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($r['top3']): ?>
            <div class="mc-stats-card">
                <h3>🏆 我給高分的作品</h3>
                <div class="mc-card-grid mc-card-grid--compact">
                    <?php foreach ($r['top3'] as $row) smacg_render_anime_card($row['post_id'], ['user_score' => $row['score']]); ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($r['bottom3'] && count($r['bottom3']) >= 3): ?>
            <div class="mc-stats-card">
                <h3>👀 我給低分的作品</h3>
                <div class="mc-card-grid mc-card-grid--compact">
                    <?php foreach ($r['bottom3'] as $row) smacg_render_anime_card($row['post_id'], ['user_score' => $row['score']]); ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

/* =====================================================
 *  Tab 4：評分
 * ===================================================== */
function smacg_render_ratings($ratings, $rating_stats) {
    $total = count($ratings);
    $first = array_slice($ratings, 0, SMACG_PAGE_SIZE);
    ?>
    <?php if ($total): ?>
        <div class="mc-rating-summary">
            <div><b><?php echo $rating_stats['avg']; ?></b><span>平均分</span></div>
            <div><b><?php echo $total; ?></b><span>已評分</span></div>
        </div>

        <div class="mc-list-tools">
            <input type="search" class="mc-search" placeholder="🔍 搜尋標題…" data-target="ratings">
            <select class="mc-sort" data-target="ratings">
                <option value="updated">最近評分</option>
                <option value="score-desc">分數高 → 低</option>
                <option value="score-asc">分數低 → 高</option>
            </select>
        </div>

        <div class="mc-card-grid" id="mc-ratings-grid">
            <?php foreach ($first as $r) smacg_render_anime_card((int)$r['anime_id'], ['user_score'=>(float)$r['overall_score']]); ?>
        </div>

        <?php if ($total > SMACG_PAGE_SIZE): ?>
            <div class="mc-loadmore-wrap">
                <button class="mc-loadmore" data-type="ratings" data-loaded="<?php echo count($first); ?>" data-total="<?php echo $total; ?>">
                    載入更多（剩 <span><?php echo $total - count($first); ?></span>）
                </button>
            </div>
            <script type="application/json" id="mc-ratings-data"><?php echo wp_json_encode($ratings); ?></script>
        <?php endif; ?>
    <?php else: ?>
        <p class="mc-empty">還沒給任何作品評分</p>
    <?php endif; ?>
    <?php
}

/* =====================================================
 *  Tab 5：留言（v2.0.6：改用分頁）
 * ===================================================== */
if ( ! defined( 'SMACG_COMMENT_PAGE_SIZE' ) ) {
    define( 'SMACG_COMMENT_PAGE_SIZE', 20 );
}

function smacg_render_comments( $uid ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) { echo '<p class="mc-empty">尚無留言</p>'; return; }

    $total = (int) get_comments( [
        'user_id' => $uid,
        'status'  => 'approve',
        'count'   => true,
    ] );

    if ( $total === 0 ) {
        echo '<p class="mc-empty">尚無留言</p>';
        return;
    }

    $cmts = get_comments( [
        'user_id' => $uid,
        'status'  => 'approve',
        'number'  => SMACG_COMMENT_PAGE_SIZE,
        'offset'  => 0,
        'orderby' => 'comment_date',
        'order'   => 'DESC',
    ] );

    $loaded = count( $cmts );
    $nonce  = wp_create_nonce( 'smacg_load_more_comments' );
    ?>
    <div class="mc-comments-wrap">
        <div class="mc-comments-meta">
            共 <b><?php echo $total; ?></b> 則留言
        </div>

        <ul class="mc-cmt-fulllist" id="mc-cmt-list">
            <?php foreach ( $cmts as $c ): ?>
                <li>
                    <a href="<?php echo esc_url( get_comment_link( $c ) ); ?>">
                        <b><?php echo esc_html( get_the_title( $c->comment_post_ID ) ); ?></b>
                        <p><?php echo esc_html( wp_trim_words( $c->comment_content, 40 ) ); ?></p>
                        <small><?php echo esc_html( mysql2date( 'Y-m-d H:i', $c->comment_date ) ); ?></small>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ( $total > $loaded ): ?>
            <div class="mc-loadmore-wrap">
                <button type="button"
                        class="mc-loadmore mc-loadmore-comments"
                        data-loaded="<?php echo $loaded; ?>"
                        data-total="<?php echo $total; ?>"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    載入更多（剩 <span><?php echo $total - $loaded; ?></span>）
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/* =====================================================
 *  Tab 6：點數 / EXP（v2.3.0 - 改用 GamiPress API）
 *  Batch 2A-0：全面遷移到 GamiPress，丟棄舊參數
 * ===================================================== */
function smacg_render_points( $points = null, $lvl = null, $log = null ) {
    // 取得當前用戶（從 query 或當前登入用戶）
    $uid = get_query_var( 'smacg_view_user' );
    if ( ! $uid ) $uid = get_current_user_id();
    $uid = (int) $uid;

    if ( $uid <= 0 ) {
        echo '<p class="mc-empty">請先登入</p>';
        return;
    }

    // 從 GamiPress 取得即時資料
    $info = smacg_get_user_level_info( $uid );
    $log  = smacg_get_exp_log( $uid, 50 );

    $is_max = ! empty( $info['is_max'] );
    ?>
    <div class="smacg-level-summary <?php echo $is_max ? 'is-max' : ''; ?>">
        <div class="smacg-level-header">
            <div class="smacg-level-icon"><?php echo $info['icon']; ?></div>
            <div class="smacg-level-meta">
                <div class="lvl-number">
                    Lv.<?php echo (int) $info['level']; ?>
                    <small>/ <?php echo SMACG_MAX_LEVEL; ?></small>
                </div>
                <div class="lvl-title"><?php echo esc_html( $info['title'] ); ?></div>
            </div>
            <div class="smacg-level-exp">
                <div class="exp-num"><?php echo number_format( (int) $info['exp'] ); ?></div>
                <div class="exp-label">EXP</div>
            </div>
        </div>

        <div class="smacg-level-bar">
            <div class="smacg-level-bar-fill" style="width:<?php echo (int) $info['percent']; ?>%"></div>
        </div>

        <div class="smacg-level-progress-text">
            <?php if ( $is_max ): ?>
                <span>🎉 已達最高等級！</span>
                <span><?php echo number_format( (int) $info['exp'] ); ?> EXP</span>
            <?php else: ?>
                <span>距離 Lv.<?php echo (int) $info['level'] + 1; ?> 還差 <?php echo number_format( (int) $info['to_next'] ); ?> EXP</span>
                <span><?php echo (int) $info['in_level_exp']; ?> / <?php echo (int) $info['level_total_exp']; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <h3 class="mc-section-title">📜 最近 50 筆 EXP 紀錄</h3>
    <?php if ( $log ): ?>
        <table class="mc-points-table">
            <thead>
                <tr>
                    <th>時間</th>
                    <th>變動</th>
                    <th>原因</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $log as $row ):
                    $v   = (int) $row['change_value'];
                    $cls = $v >= 0 ? 'pos' : 'neg';
                ?>
                    <tr>
                        <td data-label="時間"><?php echo esc_html( $row['created_at'] ); ?></td>
                        <td data-label="變動" class="mc-pt-<?php echo $cls; ?>">
                            <?php echo ( $v >= 0 ? '+' : '' ) . number_format( $v ); ?>
                        </td>
                        <td data-label="原因"><?php echo esc_html( $row['reason'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="mc-empty">尚無 EXP 紀錄。多多參與活動就能累積經驗值！</p>
    <?php endif; ?>
    <?php
}

/* =====================================================
 *  Tab：🏆 徽章（v2.4.0 - Batch 2A-3）
 *
 *  從 GamiPress 抓「badge」achievement-type，顯示已解鎖 vs. 未解鎖
 *  簽章：smacg_render_badges( $uid )
 * ===================================================== */
function smacg_render_badges( $uid ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) {
        echo '<p class="mc-empty">請先登入</p>';
        return;
    }

    if ( ! function_exists( 'smacg_gamipress_active' ) || ! smacg_gamipress_active() ) {
        echo '<div class="mc-empty">';
        echo '<p>GamiPress 尚未啟用，徽章功能無法顯示。</p>';
        echo '<p><small>請聯絡管理員設定。</small></p>';
        echo '</div>';
        return;
    }

    $badge_slug = defined( 'SMACG_BADGE_SLUG' ) ? SMACG_BADGE_SLUG : 'badge';

    // 取得所有徽章
    $all_badges = get_posts( array(
        'post_type'      => $badge_slug,
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ) );

    if ( empty( $all_badges ) ) {
        echo '<div class="mc-empty">';
        echo '<p>目前還沒有任何徽章。</p>';
        if ( current_user_can( 'manage_options' ) ) {
            echo '<p><small>管理員可至 <a href="' . esc_url( admin_url( 'edit.php?post_type=' . $badge_slug ) ) . '">GamiPress 後台</a> 建立徽章。</small></p>';
        }
        echo '</div>';
        return;
    }

    $earned_ids = function_exists( 'smacg_get_user_badge_ids' )
        ? smacg_get_user_badge_ids( $uid )
        : array();
    $earned_map = array_flip( $earned_ids );

    $total    = count( $all_badges );
    $unlocked = count( $earned_ids );
    $percent  = $total > 0 ? round( $unlocked / $total * 100 ) : 0;
    ?>
    <div class="mc-badges-wrap">

        <div class="mc-badges-summary">
            <div class="mc-badges-summary-num">
                <b><?php echo $unlocked; ?></b>
                <span>/ <?php echo $total; ?></span>
            </div>
            <div class="mc-badges-summary-bar">
                <div class="mc-badges-summary-bar-fill" style="width:<?php echo $percent; ?>%"></div>
            </div>
            <p class="mc-badges-summary-text">已解鎖 <?php echo $percent; ?>%</p>
        </div>

        <div class="mc-badges-grid">
            <?php foreach ( $all_badges as $badge ) :
                $is_unlocked = isset( $earned_map[ $badge->ID ] );
                $thumb       = get_the_post_thumbnail_url( $badge->ID, 'thumbnail' );
                $excerpt     = wp_strip_all_tags( $badge->post_excerpt ?: $badge->post_content );
                $excerpt     = mb_strimwidth( $excerpt, 0, 60, '…' );
            ?>
                <div class="mc-badge-card <?php echo $is_unlocked ? 'is-unlocked' : 'is-locked'; ?>">
                    <div class="mc-badge-icon">
                        <?php if ( $thumb ) : ?>
                            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $badge->post_title ); ?>" loading="lazy">
                        <?php else : ?>
                            <i class="fa-solid fa-trophy"></i>
                        <?php endif; ?>
                        <?php if ( ! $is_unlocked ) : ?>
                            <span class="mc-badge-lock"><i class="fa-solid fa-lock"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="mc-badge-info">
                        <h4 class="mc-badge-title"><?php echo esc_html( $badge->post_title ); ?></h4>
                        <?php if ( $excerpt ) : ?>
                            <p class="mc-badge-desc"><?php echo esc_html( $excerpt ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
    <?php
}

/* =====================================================
 *  Tab：🎯 職業（v2.4.0 - Batch 2A-3）
 *
 *  Lv.10+ 顯示職業選擇；尚未到 Lv.10 顯示倒數
 *  已選擇職業：顯示當前稱號與未來進化路線
 *  簽章：smacg_render_career( $uid, $lvl_info, $job_title )
 * ===================================================== */
function smacg_render_career( $uid, $lvl_info = null, $job_title = null ) {
    $uid     = (int) $uid;
    if ( $uid <= 0 ) {
        echo '<p class="mc-empty">請先登入</p>';
        return;
    }

    // 若參數沒傳進來，自己抓
    if ( ! is_array( $lvl_info ) && function_exists( 'smacg_get_user_level_info' ) ) {
        $lvl_info = smacg_get_user_level_info( $uid );
    }
    if ( ! is_array( $job_title ) && function_exists( 'smacg_get_user_job_title' ) ) {
        $job_title = smacg_get_user_job_title( $uid );
    }
    $lvl_info  = is_array( $lvl_info )  ? $lvl_info  : array( 'level' => 1, 'exp' => 0 );
    $job_title = is_array( $job_title ) ? $job_title : array();

    $level = (int) ( $lvl_info['level'] ?? 1 );
    $exp   = (int) ( $lvl_info['exp']   ?? 0 );

    // === 尚未一轉（Lv.10）===
    if ( $level < 10 ) {
        $exp_needed = max( 0, 500 - $exp );
        ?>
        <div class="mc-career-locked">
            <div class="mc-career-locked-icon">🔒</div>
            <h3>職業系統 Lv.10 解鎖</h3>
            <p>目前 Lv.<?php echo $level; ?>，距離一轉還需 <b><?php echo number_format( $exp_needed ); ?> EXP</b></p>
            <p class="mc-career-locked-hint">
                繼續觀看動畫、寫評論、追蹤朋友來累積 EXP！
            </p>
        </div>
        <?php
        return;
    }

    if ( ! function_exists( 'smacg_get_jobs' ) ) {
        echo '<p class="mc-empty">職業系統尚未載入</p>';
        return;
    }

    $jobs           = smacg_get_jobs();
    $current_job    = function_exists( 'smacg_get_user_job' ) ? smacg_get_user_job( $uid ) : '';
    $career_stage   = function_exists( 'smacg_get_career_stage' ) ? smacg_get_career_stage( $level ) : 0;
    $milestones     = function_exists( 'smacg_get_career_milestones' ) ? smacg_get_career_milestones() : array();

    // === 尚未選擇職業 ===
    if ( ! $current_job ) {
        ?>
        <div class="mc-career-choose">
            <h2>🎯 選擇你的職業</h2>
            <p class="mc-career-intro">
                恭喜達成 Lv.10！請從下列 8 種職業中選擇一個作為你的天命之路。
                <br><small>※ 選擇後 3 個月內無法變更。每次升級會自動進化稱號。</small>
            </p>

            <div class="mc-career-grid">
                <?php foreach ( $jobs as $key => $job ) : ?>
                    <button class="mc-career-card" data-job-key="<?php echo esc_attr( $key ); ?>" type="button">
                        <div class="mc-career-card-icon"><?php echo esc_html( $job['icon'] ); ?></div>
                        <h4 class="mc-career-card-name"><?php echo esc_html( $job['label'] ); ?></h4>
                        <ul class="mc-career-card-path">
                            <?php foreach ( $job['titles'] as $stage => $t ) :
                                $milestone_lv = isset( $milestones[ $stage ]['level'] ) ? (int) $milestones[ $stage ]['level'] : 0;
                            ?>
                                <li>
                                    <small>Lv.<?php echo $milestone_lv; ?></small>
                                    <?php echo esc_html( $t['name'] ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </button>
                <?php endforeach; ?>
            </div>

            <p class="mc-career-note">
                <i class="fa-solid fa-circle-info"></i>
                ※ 職業選擇 AJAX 介面將於下個 batch（2A-4）實作；目前為 UI 預覽。
            </p>
        </div>
        <?php
        return;
    }

    // === 已選擇職業 — 顯示進化路線 ===
    if ( ! isset( $jobs[ $current_job ] ) ) {
        echo '<p class="mc-empty">職業資料異常，請聯絡管理員</p>';
        return;
    }

    $job       = $jobs[ $current_job ];
    $chosen_at = get_user_meta( $uid, 'smacg_job_chosen_at', true );
    ?>
    <div class="mc-career-view">

        <div class="mc-career-current">
            <div class="mc-career-current-icon"><?php echo esc_html( $job['icon'] ); ?></div>
            <div class="mc-career-current-body">
                <h2><?php echo esc_html( $job['label'] ); ?></h2>
                <?php if ( ! empty( $job_title ) ) : ?>
                    <p class="mc-career-current-title">
                        當前稱號：<b><?php echo esc_html( $job_title['title_name'] ); ?></b>
                        <small>（動漫梗：<?php echo esc_html( $job_title['title_ref'] ); ?>）</small>
                    </p>
                <?php endif; ?>
                <?php if ( $chosen_at ) : ?>
                    <p class="mc-career-current-meta">
                        <small>選擇於 <?php echo esc_html( mysql2date( 'Y-m-d', $chosen_at ) ); ?></small>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <h3 class="mc-career-path-title">🎬 進化路線</h3>
        <ol class="mc-career-path">
            <?php foreach ( $job['titles'] as $stage => $t ) :
                $milestone = $milestones[ $stage ] ?? array( 'level' => 0, 'icon' => '•' );
                $milestone_lv = (int) $milestone['level'];
                $reached   = $career_stage >= $stage;
                $current   = $career_stage === $stage;
                $cls       = $reached ? ( $current ? 'is-current' : 'is-done' ) : 'is-locked';
            ?>
                <li class="mc-career-path-item <?php echo $cls; ?>">
                    <div class="mc-career-path-marker">
                        <?php if ( $reached ) : ?>
                            <i class="fa-solid fa-check"></i>
                        <?php else : ?>
                            <i class="fa-solid fa-lock"></i>
                        <?php endif; ?>
                    </div>
                    <div class="mc-career-path-body">
                        <div class="mc-career-path-stage">
                            <?php echo esc_html( $milestone['icon'] ); ?>
                            <?php echo (int) $stage; ?> 轉 · Lv.<?php echo $milestone_lv; ?>
                        </div>
                        <div class="mc-career-path-name"><?php echo esc_html( $t['name'] ); ?></div>
                        <div class="mc-career-path-ref"><small>動漫梗：<?php echo esc_html( $t['ref'] ); ?></small></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>

    </div>
    <?php
}

/* =====================================================
 *  Tab 7：設定（v2.2.0：卡片 2 後插入「通知偏好」卡）
 * ===================================================== */
function smacg_render_settings( $user, $privacy = null, $is_owner = true ) {
    if ( $privacy === null && function_exists( 'smacg_get_user_privacy' ) ) {
        $privacy = smacg_get_user_privacy( $user->ID );
    }
    $privacy = wp_parse_args( (array) $privacy, [
        'show_email'             => 0,
        'public_profile'         => 1,
        'public_watchlist'       => 1,
        'show_continue_watching' => 1,
    ] );
    $nonce = wp_create_nonce( 'smacg_privacy' );
    ?>
    <div class="mc-settings-grid">

        <!-- 卡片 1：基本資料 -->
        <div class="mc-set-card">
            <h3 class="mc-set-title"><i class="fa-solid fa-id-card"></i> 基本資料</h3>
            <form id="mc-profile-form" class="mc-set-form">
                <?php wp_nonce_field( 'smacg_update_profile', 'smacg_profile_nonce' ); ?>
                <label class="mc-set-label">
                    <span>顯示名稱</span>
                    <input type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" maxlength="40" required>
                </label>
                <label class="mc-set-label">
                    <span>個人簡介</span>
                    <textarea name="description" rows="3" maxlength="300" placeholder="跟其他用戶介紹一下自己…"><?php echo esc_textarea( $user->description ); ?></textarea>
                </label>
                <div class="mc-set-actions">
                    <button type="submit" class="mc-btn-primary">儲存變更</button>
                    <span id="mc-profile-msg" class="mc-set-msg" style="display:none"></span>
                </div>
            </form>
        </div>

        <!-- 卡片 2：隱私 & 顯示設定 -->
        <div class="mc-set-card">
            <h3 class="mc-set-title"><i class="fa-solid fa-user-shield"></i> 隱私 & 顯示</h3>
            <div class="mc-privacy-form" data-nonce="<?php echo esc_attr( $nonce ); ?>">

                <div class="mc-toggle-row">
                    <div class="mc-toggle-info">
                        <div class="mc-toggle-name">公開 Email</div>
                        <div class="mc-toggle-desc">關閉時其他人看到的 email 會遮罩為 a***@example.com</div>
                    </div>
                    <label class="mc-toggle">
                        <input type="checkbox" data-key="show_email" <?php checked( $privacy['show_email'], 1 ); ?>>
                        <span class="mc-toggle-slider"></span>
                    </label>
                </div>

                <div class="mc-toggle-row">
                    <div class="mc-toggle-info">
                        <div class="mc-toggle-name">公開個人頁</div>
                        <div class="mc-toggle-desc">關閉後只有你本人能瀏覽此頁面</div>
                    </div>
                    <label class="mc-toggle">
                        <input type="checkbox" data-key="public_profile" <?php checked( $privacy['public_profile'], 1 ); ?>>
                        <span class="mc-toggle-slider"></span>
                    </label>
                </div>

                <div class="mc-toggle-row">
                    <div class="mc-toggle-info">
                        <div class="mc-toggle-name">公開追番列表</div>
                        <div class="mc-toggle-desc">關閉後其他用戶看不到你的觀看記錄</div>
                    </div>
                    <label class="mc-toggle">
                        <input type="checkbox" data-key="public_watchlist" <?php checked( $privacy['public_watchlist'], 1 ); ?>>
                        <span class="mc-toggle-slider"></span>
                    </label>
                </div>

                <div class="mc-toggle-row">
                    <div class="mc-toggle-info">
                        <div class="mc-toggle-name">顯示「繼續觀看」</div>
                        <div class="mc-toggle-desc">關閉後會員頁不再顯示頂部的橫向追番列</div>
                    </div>
                    <label class="mc-toggle">
                        <input type="checkbox" data-key="show_continue_watching" <?php checked( $privacy['show_continue_watching'], 1 ); ?>>
                        <span class="mc-toggle-slider"></span>
                    </label>
                </div>

                <div id="mc-privacy-msg" class="mc-set-msg" style="display:none"></div>
            </div>
        </div>

        <?php /* v2.2.0：通知偏好卡片 */ ?>
        <?php smacg_render_notification_prefs_card( $user->ID ); ?>

        <!-- 卡片 3：帳號安全 -->
        <div class="mc-set-card">
            <h3 class="mc-set-title"><i class="fa-solid fa-key"></i> 帳號安全</h3>
            <p class="mc-set-hint">若需變更密碼，請點下方按鈕，系統會寄送重設信件至你的 email。</p>
            <div class="mc-set-actions">
                <a class="mc-btn-secondary mc-settings-reset-pwd"
                   href="<?php echo esc_url( home_url( '/password-reset/' ) ); ?>">
                    <i class="fa-solid fa-rotate"></i> 重設密碼
                </a>
            </div>
        </div>

        <!-- 卡片 4：登出 -->
        <div class="mc-set-card mc-set-card--danger">
            <h3 class="mc-set-title"><i class="fa-solid fa-right-from-bracket"></i> 結束工作階段</h3>
            <p class="mc-set-hint">登出後將回到網站首頁。</p>
            <div class="mc-set-actions">
                <a class="mc-btn-danger" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> 登出帳號
                </a>
            </div>
        </div>

    </div>
    <?php
}

/* =====================================================
 *  v2.2.0 — Batch 1C-4：通知偏好卡片
 *
 *  讀 user_meta `smacg_notification_prefs`（陣列），前端透過 AJAX
 *  `smacg_notif_save_prefs` 儲存。包含 5 種類型 × 站內/Email，以及
 *  Email 摘要頻率（off/daily/weekly）。
 * ===================================================== */
function smacg_render_notification_prefs_card( $uid ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) return;

    // 讀取偏好（若 helper 存在則用，否則直接讀 user_meta）
    if ( function_exists( 'smacg_get_notification_prefs' ) ) {
        $prefs = smacg_get_notification_prefs( $uid );
    } else {
        $prefs = get_user_meta( $uid, 'smacg_notification_prefs', true );
        if ( ! is_array( $prefs ) ) $prefs = [];
    }

    // 預設值
    $defaults = [
        'follow_site'        => 1, 'follow_email'        => 1,
        'comment_reply_site' => 1, 'comment_reply_email' => 1,
        'rating_site'        => 1, 'rating_email'        => 0,
        'level_up_site'      => 1, 'level_up_email'      => 0,
        'badge_site'         => 1, 'badge_email'         => 0,
        'system_site'        => 1, 'system_email'        => 1,
        'email_digest'       => 'daily', // off|daily|weekly
    ];
    $prefs = wp_parse_args( $prefs, $defaults );
    $nonce = wp_create_nonce( 'smacg_notif_save_prefs' );

    // 通知類型清單
    $types = [
        'follow'        => [ 'icon' => '👥', 'name' => '有人追蹤我',            'desc' => '當有用戶開始追蹤你時通知' ],
        'comment_reply' => [ 'icon' => '💬', 'name' => '留言被回覆',            'desc' => '當有人回覆你的留言時通知' ],
        'rating'        => [ 'icon' => '⭐', 'name' => '收藏的動畫有人評分',    'desc' => '當你收藏的作品收到新評分時通知' ],
        'level_up'      => [ 'icon' => '🎖', 'name' => '等級提升',              'desc' => '當你升級時通知' ],
        'badge'         => [ 'icon' => '🏅', 'name' => '獲得徽章',              'desc' => '當你解鎖新徽章時通知' ],
        'system'        => [ 'icon' => '📢', 'name' => '系統公告',              'desc' => '網站重要更新與公告' ],
    ];
    ?>
    <div class="mc-set-card mc-set-card--notif-prefs"
         data-uid="<?php echo $uid; ?>"
         data-nonce="<?php echo esc_attr( $nonce ); ?>">
        <h3 class="mc-set-title"><i class="fa-solid fa-bell"></i> 通知偏好</h3>
        <p class="mc-set-hint">選擇你想收到哪些類型的通知。「站內」會顯示在鈴鐺/通知頁，「Email」會依下方摘要頻率寄送。</p>

        <div class="mc-notif-prefs-table">
            <div class="mc-notif-prefs-head">
                <span class="mc-notif-col-name">通知類型</span>
                <span class="mc-notif-col-toggle">站內</span>
                <span class="mc-notif-col-toggle">Email</span>
            </div>

            <?php foreach ( $types as $key => $t ):
                $site_key  = $key . '_site';
                $email_key = $key . '_email';
            ?>
                <div class="mc-notif-prefs-row">
                    <div class="mc-notif-col-name">
                        <span class="mc-notif-type-icon"><?php echo $t['icon']; ?></span>
                        <div class="mc-notif-type-text">
                            <div class="mc-notif-type-name"><?php echo esc_html( $t['name'] ); ?></div>
                            <div class="mc-notif-type-desc"><?php echo esc_html( $t['desc'] ); ?></div>
                        </div>
                    </div>
                    <div class="mc-notif-col-toggle">
                        <label class="mc-toggle mc-toggle--sm">
                            <input type="checkbox" class="mc-notif-pref" data-key="<?php echo esc_attr( $site_key ); ?>" <?php checked( ! empty( $prefs[ $site_key ] ) ); ?>>
                            <span class="mc-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="mc-notif-col-toggle">
                        <label class="mc-toggle mc-toggle--sm">
                            <input type="checkbox" class="mc-notif-pref" data-key="<?php echo esc_attr( $email_key ); ?>" <?php checked( ! empty( $prefs[ $email_key ] ) ); ?>>
                            <span class="mc-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mc-notif-digest-row">
            <label class="mc-set-label mc-set-label--inline">
                <span><i class="fa-solid fa-envelope"></i> Email 摘要頻率</span>
                <select class="mc-notif-digest" data-key="email_digest">
                    <option value="off"    <?php selected( $prefs['email_digest'], 'off' ); ?>>不寄送</option>
                    <option value="daily"  <?php selected( $prefs['email_digest'], 'daily' ); ?>>每日 20:00（推薦）</option>
                    <option value="weekly" <?php selected( $prefs['email_digest'], 'weekly' ); ?>>每週日 20:00</option>
                </select>
            </label>
            <p class="mc-set-hint">摘要會把過去 24 小時（或 7 天）的所有通知整理成一封信寄出，而不是每則發一封。</p>
        </div>

        <div id="mc-notif-prefs-msg" class="mc-set-msg" style="display:none"></div>
    </div>
    <?php
}

/* =====================================================
 *  Continue Watching - 繼續觀看橫向列（P1-2）
 * ===================================================== */
function smacg_render_continue_watching( $watchlist ) {
    if ( empty( $watchlist ) || ! is_array( $watchlist ) ) return;

    $continue = [];
    foreach ( $watchlist as $w ) {
        if ( ( $w['status'] ?? '' ) !== 'watching' ) continue;

        $pid = (int) ( $w['post_id'] ?? 0 );
        if ( ! $pid ) continue;

        $total = (int) get_post_meta( $pid, 'anime_episodes', true );
        $prog  = (int) ( $w['progress'] ?? 0 );

        if ( $total > 0 && $prog >= $total ) continue;

        $w['_total']   = $total;
        $w['_percent'] = $total > 0 ? min( 100, round( $prog / $total * 100 ) ) : 0;
        $continue[] = $w;
        if ( count( $continue ) >= 10 ) break;
    }

    if ( empty( $continue ) ) {
        ?>
        <section class="mc-continue-section mc-continue-empty">
            <div class="mc-continue-header">
                <h2 class="mc-continue-title">🎬 繼續觀看</h2>
            </div>
            <div class="mc-continue-empty-box">
                <p>目前沒有觀看中的動畫</p>
                <a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>" class="mc-btn-primary">
                    <i class="fa-solid fa-compass"></i> 去找一部來追
                </a>
            </div>
        </section>
        <?php
        return;
    }
    ?>
    <section class="mc-continue-section" id="mc-continue-section">
        <div class="mc-continue-header">
            <h2 class="mc-continue-title">🎬 繼續觀看 <small>（<?php echo count( $continue ); ?>）</small></h2>
            <div class="mc-continue-nav">
                <button type="button" class="mc-continue-arrow" data-dir="prev" aria-label="向左捲動">‹</button>
                <button type="button" class="mc-continue-arrow" data-dir="next" aria-label="向右捲動">›</button>
            </div>
        </div>

        <div class="mc-continue-scroll" id="mc-continue-scroll">
            <?php foreach ( $continue as $w ):
                $pid       = (int) $w['post_id'];
                $title     = get_the_title( $pid );
                $permalink = get_permalink( $pid );
                $thumb     = smacg_get_card_thumb_url( $pid );
                $total     = (int) $w['_total'];
                $prog      = (int) $w['progress'];
                $percent   = (int) $w['_percent'];
            ?>
                <article class="mc-anime-card mc-continue-card"
                         data-anime-id="<?php echo $pid; ?>"
                         data-status="watching"
                         data-title="<?php echo esc_attr( mb_strtolower( $title ) ); ?>">

                    <div class="mc-card-actions"
                         data-anime="<?php echo $pid; ?>"
                         data-progress="<?php echo $prog; ?>"
                         data-total="<?php echo $total; ?>">
                        <button type="button" class="mc-card-btn mc-card-btn--plus" title="進度 +1">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                        <button type="button" class="mc-card-btn mc-card-btn--done" title="標記完成">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </div>

                    <a href="<?php echo esc_url( $permalink ); ?>" class="mc-card-thumb">
                        <?php if ( $thumb ): ?>
                            <?php
                            if ( function_exists( 'smacg_picture_tag' ) && has_post_thumbnail( $pid ) ) {
                                echo smacg_picture_tag( $pid, 'medium', $title );
                            } else { ?>
                                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
                            <?php } ?>
                        <?php else: ?>
                            <div class="mc-card-thumb-ph" aria-hidden="true">🎬</div>
                        <?php endif; ?>
                    </a>

                    <div class="mc-card-body">
                        <h4 class="mc-card-title">
                            <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
                        </h4>
                        <div class="mc-card-progress">
                            <div class="mc-card-progress-fill" style="width:<?php echo $percent; ?>%"></div>
                            <span class="mc-card-progress-text"><?php echo $prog; ?> / <?php echo $total ?: '?'; ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}