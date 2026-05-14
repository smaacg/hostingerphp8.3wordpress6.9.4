<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package weixiaoacg
 * @version 2.5.4 (2026-05-14)
 *
 * Changelog（只列近期；完整歷史見 git log）：
 *   2.5.4 (2026-05-14) Batch 2A-4 — career-ajax + level-badge-display
 *   2.5.3 (2026-05-14) Batch 2A-3 — 解決 smacg_calc_level 命名衝突、徽章/職業 tab
 *   2.5.2 (2026-05-14) Batch 2A-2 — 升級偵測 + gamipress-notif-bridge
 *   2.5.1 (2026-05-14) Batch 2A-1 — exp-config + exp-events（自動發放 EXP）
 *   2.5.0 (2026-05-14) Batch 2A-0 — gamipress-integration + level-system
 *   2.4.3 (2026-05-13) Batch 1C-4 — notifications-email
 *   2.4.0–2.4.2        Batch 1C-1~3 — 通知中心
 *   2.3.0–2.3.1        Batch 1B-1~2 — 追蹤系統
 *   2.2.0              Batch 1A     — 公開個人頁
 *   2.1.0              Batch C      — image-optimizer
 *   2.0.0 (2026-05-12) 重構：functions.php → inc/*.php
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數
   ============================================================ */
define( 'weixiaoacg_VERSION',   '2.5.4' );
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
   ------------------------------------------------------------
   順序很重要：
     1) 基礎 helpers → 主題設定 → 資源 → AJAX/REST → 整合
     2) Phase 1：公開頁 / 追蹤 / 通知
     3) Phase 2A：GamiPress 整合 → 等級 → EXP 規則 → EXP 事件
                  → 通知橋接 → 職業 AJAX → 等級徽章顯示
   ------------------------------------------------------------
   分兩組：core（必載，缺檔即 fatal）、optional（缺檔靜默略過）
   ============================================================ */
$inc = weixiaoacg_THEME_DIR . '/inc/';

// ---- core：必須存在，沿用 require_once 強制報錯 ----
foreach ( [
    'member-functions',   // 點數/等級/cooldown helpers（舊版）
    'member-stats',       // 統計 / 隱私 / email 遮罩
    'member-render',      // 會員頁渲染
    'setup-theme',        // theme support / menu / image size
    'class-nav-walker',   // 自訂 Nav Walker
    'setup-enqueue',      // jQuery / CSS / JS
    'member-ajax',        // wp_ajax_smacg_*
    'api-rest',           // REST API routes
    'um-integration',     // Ultimate Member
    'content-slug',       // Gemini slug
    'external-links',     // 外部連結 target=_blank
] as $f ) {
    require_once $inc . $f . '.php';
}

// ---- optional：file_exists 防呆，允許分批 push 不壞站 ----
//      順序仍須維持：依賴在前、使用方在後
$optional = [
    // Batch C / 1A / 1B / 1C
    'image-optimizer',          // Batch C #6
    'public-profile',           // Batch 1A
    'follow-system',            // Batch 1B-1
    'follow-ajax',              // Batch 1B-2
    'notifications-system',     // Batch 1C-1
    'notifications-events',     // Batch 1C-2
    'notifications-ajax',       // Batch 1C-2
    'notifications-render',     // Batch 1C-3
    'notifications-email',      // Batch 1C-4

    // Phase 2A：Gamification
    'gamipress-integration',    // 2A-0 GamiPress wrapper
    'level-system',             // 2A-0 Lv.1-200 + 4 職業
    'exp-config',               // 2A-1 EXP 規則
    'exp-events',               // 2A-1 自動發 EXP（依賴 exp-config + integration）
    'gamipress-notif-bridge',   // 2A-2 升級／里程碑通知橋接（依賴 exp-events + notifications）
    'career-ajax',              // 2A-4 職業選擇 AJAX（Lv≥10、永久鎖定）
    'level-badge-display',      // 2A-4 留言/公開頁 等級徽章渲染（依賴 career-ajax）
];

foreach ( $optional as $f ) {
    $path = $inc . $f . '.php';
    if ( file_exists( $path ) ) require_once $path;
}

unset( $inc, $optional, $f, $path );
