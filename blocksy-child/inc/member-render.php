<?php
/**
 * Member Center - Render Layer
 * Version: 2.0.0
 *
 * 各 tab 的 HTML 輸出。所有清單一律「先渲染前 N 筆」，其餘由 AJAX 載入。
 * 桌機 20 / 行動 12（前端 JS 偵測，PHP 預設輸出 20）
 */
if (!defined('ABSPATH')) exit;

const SMACG_PAGE_SIZE = 20;

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
    <article class="mc-anime-card" data-status="<?php echo esc_attr($status); ?>" data-title="<?php echo esc_attr(mb_strtolower($title)); ?>">
        <a href="<?php echo esc_url($permalink); ?>" class="mc-card-thumb">
            <?php if ($thumb): ?>
                <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
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

/* ========== 縮圖 fallback（與 page-columns.php 一致）========== */
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
function smacg_render_dashboard($watchlist, $stats, $recent_cmt, $points_log, $plan_label) {
    $watching = array_filter($watchlist, fn($w) => $w['status'] === 'watching');
    $watching = array_slice($watching, 0, 6);
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
    <?php
}

/* =====================================================
 *  Tab 2：我的清單（含分頁/搜尋/排序）
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
 *  Tab 3：統計（純 CSS 圖表）
 * ===================================================== */
function smacg_render_stats($s) {
    $r = $s['rating']; ?>

    <div class="mc-stats-grid">

        <?php /* 觀看時數 banner */ ?>
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

        <?php /* 評分分布 */ ?>
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

        <?php /* 類型偏好（CSS pie：用 conic-gradient）*/
        if ($s['genres']):
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

        <?php /* 製作公司 Top 5 */
        if ($s['studios']): ?>
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

        <?php /* 年代分布 */
        if ($s['years']):
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

        <?php /* 最高 / 最低分 */ ?>
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
 *  Tab 4：評分（分頁）
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
 *  Tab 5：留言
 * ===================================================== */
function smacg_render_comments($uid) {
    $cmts = get_comments(['user_id'=>$uid, 'status'=>'approve', 'number'=>50, 'orderby'=>'comment_date', 'order'=>'DESC']);
    if (!$cmts) { echo '<p class="mc-empty">尚無留言</p>'; return; }
    echo '<ul class="mc-cmt-fulllist">';
    foreach ($cmts as $c) {
        printf(
            '<li><a href="%s"><b>%s</b><p>%s</p><small>%s</small></a></li>',
            esc_url(get_comment_link($c)),
            esc_html(get_the_title($c->comment_post_ID)),
            esc_html(wp_trim_words($c->comment_content, 40)),
            esc_html(mysql2date('Y-m-d H:i', $c->comment_date))
        );
    }
    echo '</ul>';
}

/* =====================================================
 *  Tab 6：點數
 * ===================================================== */
function smacg_render_points($points, $lvl, $log) { ?>
    <div class="mc-points-summary">
        <div class="mc-points-big">
            <b><?php echo number_format($points); ?></b>
            <span>目前點數 · Lv.<?php echo $lvl['level']; ?> <?php echo esc_html($lvl['title']); ?></span>
        </div>
        <div class="mc-points-progress">
            <div class="mc-level-bar"><div class="mc-level-fill" style="width:<?php echo $lvl['percent']; ?>%"></div></div>
            <small>距離下一級還差 <?php echo max(0, $lvl['next'] - $points); ?> 點</small>
        </div>
    </div>
    <h3 class="mc-section-title">📜 最近 50 筆紀錄</h3>
    <?php if ($log): ?>
        <table class="mc-points-table">
            <thead><tr><th>時間</th><th>變動</th><th>原因</th></tr></thead>
            <tbody>
                <?php foreach ($log as $row):
                    $v = (int)$row['change_value'];
                    $cls = $v >= 0 ? 'pos' : 'neg'; ?>
                    <tr>
                        <td><?php echo esc_html($row['created_at']); ?></td>
                        <td class="mc-pt-<?php echo $cls; ?>"><?php echo ($v >= 0 ? '+' : '') . $v; ?></td>
                        <td><?php echo esc_html($row['reason']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="mc-empty">尚無點數紀錄</p>
    <?php endif; ?>
<?php }

/* =====================================================
 *  Tab 7：設定
 * ===================================================== */
function smacg_render_settings($user) { ?>
    <div class="mc-settings-grid">
        <div class="mc-set-card">
            <h3>👤 基本資料</h3>
            <p>修改顯示名稱、簡介、頭像請至</p>
            <a href="<?php echo esc_url(um_get_core_page('account')); ?>" class="mc-btn-primary">帳號設定 →</a>
        </div>
        <div class="mc-set-card">
            <h3>🔐 密碼安全</h3>
            <a href="<?php echo wp_lostpassword_url(); ?>" class="mc-btn-secondary">重設密碼</a>
        </div>
        <div class="mc-set-card mc-set-card--danger">
            <h3>🚪 登出</h3>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="mc-btn-danger">登出帳號</a>
        </div>
    </div>
<?php }
