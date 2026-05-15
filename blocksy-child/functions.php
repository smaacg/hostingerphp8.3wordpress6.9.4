<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package weixiaoacg
 * @version 2.12.0 (2026-05-15)
 *
 * Changelog：
 *   2.12.0 (2026-05-15) Phase 2 完成：所有 gamification 模組搬遷至 smacg-gamification plugin
 *                       移除 $optional 內 14 個 gamification 檔案載入
 *   2.7.3 (2026-05-14) Batch 2B-5 — season-event-tracker + settle + single 模板
 *   2.7.2 (2026-05-14) Batch 2B-4 — season-event CPT + admin UI
 *   2.7.1 (2026-05-14) Batch 2B-3 — leaderboard widget + shortcode
 *   2.7.0 (2026-05-14) Batch 2B-2 — leaderboard ajax + /ranking-users/
 *   2.6.0 (2026-05-14) Batch 2B-1 — ranking system + cron + privacy
 *   2.5.x              Batch 2A — gamification（已搬至 plugin）
 *   2.4.x              Batch 1C — 通知中心
 *   2.3.x              Batch 1B — 追蹤系統
 *   2.2.0              Batch 1A — 公開個人頁
 *   2.1.0              Batch C  — image-optimizer
 *   2.0.0              重構：functions.php → inc/*.php
 */
defined( 'ABSPATH' ) || exit;

define( 'weixiaoacg_VERSION',   '2.12.0' );
define( 'weixiaoacg_THEME_URL', get_stylesheet_directory_uri() );
define( 'weixiaoacg_THEME_DIR', get_stylesheet_directory() );

/* === 非 gamification 的點數常數（如有其他 inc 仍引用，先保留） === */
define( 'SMACG_POINT_FAVORITE',  5  );
define( 'SMACG_POINT_WANT',      1  );
define( 'SMACG_POINT_WATCHING',  3  );
define( 'SMACG_POINT_COMPLETED', 8  );
define( 'SMACG_POINT_FULLCLEAR', 10 );
define( 'SMACG_POINT_EPISODE',   1  );
define( 'SMACG_POINT_READ',      2  );
define( 'SMACG_POINT_COMMENT',   3  );
define( 'SMACG_POINT_LOGIN',     1  );

define( 'SMACG_FOLLOW_DAILY_LIMIT', 200 );
define( 'SMACG_FOLLOW_COOLDOWN',    1   );

const WEIXIAOACG_ID_CATS  = [ 'announcement', 'news' ];
const WEIXIAOACG_LLM_CATS = [ 'review', 'feature' ];

/* === SMACG_BADGE_SLUG / SMACG_EXP_SLUG / SMACG_EVENT_CPT 等
       已由 smacg-gamification plugin 主檔定義（plugins_loaded 早於 after_setup_theme），
       此處不再 define，避免衝突。如需在 plugin 停用時的 fallback，可解開以下保險： */
if ( ! defined( 'SMACG_BADGE_SLUG' ) ) define( 'SMACG_BADGE_SLUG', 'badge' );
if ( ! defined( 'SMACG_EVENT_CPT' ) )  define( 'SMACG_EVENT_CPT',  'smacg_season_event' );

$inc = weixiaoacg_THEME_DIR . '/inc/';

/* === 主載入（保留：與 anime 站點核心相關，非 gamification） === */
foreach ( [
    'member-functions','member-stats','member-render','setup-theme',
    'class-nav-walker','setup-enqueue','member-ajax','api-rest',
    'um-integration','content-slug','external-links',
] as $f ) {
    require_once $inc . $f . '.php';
}

/* === 選擇性載入（仍存於主題的非 gamification 檔） === */
$optional = [
    'image-optimizer',
    'public-profile','public-profile-render',
    'follow-system','follow-ajax',
    'notifications-system','notifications-events','notifications-ajax','notifications-render','notifications-email',
];

/* === Gamification 已搬至 plugin（smacg-gamification），不再從主題載入 ===
   原本載入清單（保留於註解，方便追溯）：
       gamipress-integration, gamipress-notif-bridge,
       level-system, exp-config, exp-events,
       career-ajax, level-badge-display,
       ranking-system, ranking-cron, ranking-privacy,
       leaderboard-ajax, leaderboard-widget,
       season-event-cpt, season-event-admin,
       season-event-tracker, season-event-settle
*/

foreach ( $optional as $f ) {
    $path = $inc . $f . '.php';
    if ( file_exists( $path ) ) require_once $path;
}

unset( $inc, $optional, $f, $path );

/* === Plugin 狀態提示：如未啟用，提醒管理員 === */
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'activate_plugins' ) ) return;
    if ( defined( 'SMACG_GAMIFY_VERSION' ) ) return;
    echo '<div class="notice notice-error"><p><strong>weixiaoacg 主題：</strong>'
       . '偵測不到 <code>smacg-gamification</code> 外掛已啟用。本主題 v2.12.0+ 已將等級、EXP、徽章、排行榜、季賽事件整體搬遷至該外掛。'
       . '請至 <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">外掛頁</a> 啟用 SMACG Gamification，否則相關功能將完全停用。</p></div>';
} );
