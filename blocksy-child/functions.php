<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package weixiaoacg
 * @version 2.14.0 (2026-05-15)
 *
 * Changelog：
 *   2.14.0 (2026-05-15) Phase 3-A：會員核心模組搬遷至 smacg-members 外掛
 *                       移除主載入清單內 member-functions / member-stats / member-render /
 *                                       member-ajax / um-integration
 *   2.13.0 (2026-05-15) 整合 smacg-api 外掛
 *                       移除主載入清單內 api-rest / content-slug / external-links
 *   2.12.0 (2026-05-15) Phase 2：所有 gamification 模組搬遷至 smacg-gamification
 *   2.7.3  (2026-05-14) Batch 2B-5 — season-event 模組
 */
defined( 'ABSPATH' ) || exit;

define( 'weixiaoacg_VERSION',   '2.14.0' );
define( 'weixiaoacg_THEME_URL', get_stylesheet_directory_uri() );
define( 'weixiaoacg_THEME_DIR', get_stylesheet_directory() );

/* === 點數常數（非 gamification，其他主題模組仍引用） === */
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

/* === 內容分類常數（smacg-api 外掛會讀） === */
const WEIXIAOACG_ID_CATS  = [ 'announcement', 'news' ];
const WEIXIAOACG_LLM_CATS = [ 'review', 'feature' ];

/* === 保險常數（外掛未啟用時的 fallback） === */
if ( ! defined( 'SMACG_BADGE_SLUG' ) ) define( 'SMACG_BADGE_SLUG', 'badge' );
if ( ! defined( 'SMACG_EVENT_CPT' ) )  define( 'SMACG_EVENT_CPT',  'smacg_season_event' );

$inc = weixiaoacg_THEME_DIR . '/inc/';

/* === 主載入：純主題層級（選單、enqueue、佈景設定） ===
       注意：member-* 與 um-integration 已搬至 smacg-members 外掛
*/
foreach ( [
    'setup-theme',
    'class-nav-walker',
    'setup-enqueue',
] as $f ) {
    require_once $inc . $f . '.php';
}

/* === 選擇性載入（仍在主題的非 gamification、非 members 模組） === */
$optional = [
    'image-optimizer',
    'public-profile','public-profile-render',
    'follow-system','follow-ajax',
    'notifications-system','notifications-events','notifications-ajax','notifications-render','notifications-email', 'ajax-news-filter',  // ← 新增這行（v2.15.0 - 2026-05-16, News Filter AJAX endpoint）
];

/* === 已搬至 smacg-api 外掛（不再從主題載入） ===
       api-rest, content-slug, external-links
*/
/* === 已搬至 smacg-gamification 外掛（不再從主題載入） ===
       gamipress-integration, gamipress-notif-bridge,
       level-system, exp-config, exp-events, career-ajax, level-badge-display,
       ranking-system, ranking-cron, ranking-privacy,
       leaderboard-ajax, leaderboard-widget,
       season-event-cpt, season-event-admin, season-event-tracker, season-event-settle
*/
/* === 已搬至 smacg-members 外掛（不再從主題載入） ===
       member-functions, member-stats, member-render, member-ajax, um-integration
*/

foreach ( $optional as $f ) {
    $path = $inc . $f . '.php';
    if ( file_exists( $path ) ) require_once $path;
}

unset( $inc, $optional, $f, $path );

/* === 外掛狀態檢查 === */
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'activate_plugins' ) ) return;
    $missing = [];
    if ( ! defined( 'SMACG_GAMIFY_VERSION' ) )  $missing[] = 'SMACG Gamification';
    if ( ! defined( 'SMACG_API_VERSION' ) )     $missing[] = 'SMACG API';
    if ( ! defined( 'SMACG_MEMBERS_VERSION' ) ) $missing[] = 'SMACG Members';
    if ( empty( $missing ) ) return;
    echo '<div class="notice notice-error"><p><strong>weixiaoacg 主題：</strong>'
       . '以下外掛未啟用，相關功能將完全停用：<code>'
       . esc_html( implode( '、', $missing ) )
       . '</code>。請至 <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">外掛頁</a> 啟用。</p></div>';
} );
