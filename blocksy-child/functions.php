<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package weixiaoacg
 * @version 2.7.3 (2026-05-14)
 *
 * Changelog（近期）：
 *   2.7.3 (2026-05-14) Batch 2B-5 — season-event-tracker + settle + single 模板
 *   2.7.2 (2026-05-14) Batch 2B-4 — season-event CPT + admin UI
 *   2.7.1 (2026-05-14) Batch 2B-3 — leaderboard widget + shortcode
 *   2.7.0 (2026-05-14) Batch 2B-2 — leaderboard ajax + /ranking-users/
 *   2.6.0 (2026-05-14) Batch 2B-1 — ranking system + cron + privacy
 *   2.5.4 (2026-05-14) Batch 2A-4 — career + level badge
 *   2.5.0~2.5.3        Batch 2A — gamification
 *   2.4.x              Batch 1C — 通知中心
 *   2.3.x              Batch 1B — 追蹤系統
 *   2.2.0              Batch 1A — 公開個人頁
 *   2.1.0              Batch C  — image-optimizer
 *   2.0.0              重構：functions.php → inc/*.php
 */
defined( 'ABSPATH' ) || exit;

define( 'weixiaoacg_VERSION',   '2.7.3' );
define( 'weixiaoacg_THEME_URL', get_stylesheet_directory_uri() );
define( 'weixiaoacg_THEME_DIR', get_stylesheet_directory() );

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

$inc = weixiaoacg_THEME_DIR . '/inc/';

foreach ( [
    'member-functions','member-stats','member-render','setup-theme',
    'class-nav-walker','setup-enqueue','member-ajax','api-rest',
    'um-integration','content-slug','external-links',
] as $f ) {
    require_once $inc . $f . '.php';
}

$optional = [
    'image-optimizer',
    'public-profile','public-profile-render',
    'follow-system','follow-ajax',
    'notifications-system','notifications-events','notifications-ajax','notifications-render','notifications-email',

    // Phase 2A
    'gamipress-integration','level-system','exp-config','exp-events',
    'gamipress-notif-bridge','career-ajax','level-badge-display',

    // Phase 2B-1
    'ranking-system','ranking-cron','ranking-privacy',

    // Phase 2B-2
    'leaderboard-ajax',

    // Phase 2B-3
    'leaderboard-widget',

    // Phase 2B-4
    'season-event-cpt','season-event-admin',

    // Phase 2B-5
    'season-event-tracker',   // 進度表 + 事件監聽
    'season-event-settle',    // 即時/Cron 結算 + 結束公告
];

foreach ( $optional as $f ) {
    $path = $inc . $f . '.php';
    if ( file_exists( $path ) ) require_once $path;
}

unset( $inc, $optional, $f, $path );
