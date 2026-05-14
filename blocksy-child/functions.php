<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package weixiaoacg
 * @version 2.6.0 (2026-05-14)
 *
 * Changelog（只列近期；完整歷史見 git log）：
 *   2.6.0 (2026-05-14) Batch 2B-1 — ranking-system + ranking-cron + ranking-privacy
 *   2.5.4 (2026-05-14) Batch 2A-4 — career-ajax + level-badge-display
 *   2.5.3 (2026-05-14) Batch 2A-3 — 解決 smacg_calc_level 命名衝突、徽章/職業 tab
 *   2.5.2 (2026-05-14) Batch 2A-2 — 升級偵測 + gamipress-notif-bridge
 *   2.5.1 (2026-05-14) Batch 2A-1 — exp-config + exp-events
 *   2.5.0 (2026-05-14) Batch 2A-0 — gamipress-integration + level-system
 *   2.4.x              Batch 1C   — 通知中心
 *   2.3.x              Batch 1B   — 追蹤系統
 *   2.2.0              Batch 1A   — 公開個人頁
 *   2.1.0              Batch C    — image-optimizer
 *   2.0.0              重構：functions.php → inc/*.php
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數
   ============================================================ */
define( 'weixiaoacg_VERSION',   '2.6.0' );
define( 'weixiaoacg_THEME_URL', get_stylesheet_directory_uri() );
define( 'weixiaoacg_THEME_DIR', get_stylesheet_directory() );

// 舊版自定點數常數（保留相容，將由 EXP 系統取代）
define( 'SMACG_POINT_FAVORITE',  5  );
define( 'SMACG_POINT_WANT',      1  );
define( 'SMACG_POINT_WATCHING',  3  );
define( 'SMACG_POINT_COMPLETED', 8  );
define( 'SMACG_POINT_FULLCLEAR', 10 );
define( 'SMACG_POINT_EPISODE',   1  );
define( 'SMACG_POINT_READ',      2  );
define( 'SMACG_POINT_COMMENT',   3  );
define( 'SMACG_POINT_LOGIN',     1  );

// 追蹤系統（Batch 1B-1）
define( 'SMACG_FOLLOW_DAILY_LIMIT', 200 );
define( 'SMACG_FOLLOW_COOLDOWN',    1   );

const WEIXIAOACG_ID_CATS  = [ 'announcement', 'news' ];
const WEIXIAOACG_LLM_CATS = [ 'review', 'feature' ];

/* ============================================================
   載入 inc/*.php
   ============================================================ */
$inc = weixiaoacg_THEME_DIR . '/inc/';

// ---- core ----
foreach ( [
    'member-functions',
    'member-stats',
    'member-render',
    'setup-theme',
    'class-nav-walker',
    'setup-enqueue',
    'member-ajax',
    'api-rest',
    'um-integration',
    'content-slug',
    'external-links',
] as $f ) {
    require_once $inc . $f . '.php';
}

// ---- optional ----
$optional = [
    // Batch C / 1A / 1B / 1C
    'image-optimizer',
    'public-profile',
    'public-profile-render',
    'follow-system',
    'follow-ajax',
    'notifications-system',
    'notifications-events',
    'notifications-ajax',
    'notifications-render',
    'notifications-email',

    // Phase 2A：Gamification
    'gamipress-integration',
    'level-system',
    'exp-config',
    'exp-events',
    'gamipress-notif-bridge',
    'career-ajax',
    'level-badge-display',

    // Phase 2B-1：Ranking
    'ranking-system',     // 資料層 + 兩張表 + smacg_exp_awarded listener
    'ranking-cron',       // 每小時 Cron + admin bar 手動觸發
    'ranking-privacy',    // 隱私開關（appear_in_ranking）
];

foreach ( $optional as $f ) {
    $path = $inc . $f . '.php';
    if ( file_exists( $path ) ) require_once $path;
}

unset( $inc, $optional, $f, $path );
