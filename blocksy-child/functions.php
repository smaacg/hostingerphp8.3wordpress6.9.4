<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package weixiaoacg
 * @version 2.4.1
 *  * v2.4.1（2026-05-13）— Batch 1C-2：
 *   - [新增] inc/notifications-events.php（5 種事件監聽）
 *   - [新增] inc/notifications-ajax.php（5 個 endpoints）

 *  * v2.4.0 變更（2026-05-13）— Batch 1C-1 通知中心資料層：
 *   - [新增] inc/notifications-system.php（wp_smacg_notifications 資料表 + helpers）
 * v2.3.1 變更（2026-05-13）— Batch 1B-2：載入 inc/follow-ajax.php
 * v2.3.0 變更（2026-05-13）— Batch 1B-1：追蹤系統
 * - [新增] inc/follow-system.php（wp_smacg_follows 資料表 + 核心 helpers）
 *
 * v2.2.0 變更（2026-05-13）— Batch 1A：公開個人頁
 * - [新增] inc/public-profile.php（/u/{username}/ rewrite + 資料準備）
 *
 * v2.1.0 變更（2026-05-13）— Batch C：
 * - [新增] inc/image-optimizer.php（#6 WebP/srcset 處理）
 *
 * v2.0.0 變更（2026-05-12）:
 * - [重構] functions.php 拆分為 inc/*.php，由 1100 行縮減至 ~50 行
 * - [新增] inc/setup-theme.php、class-nav-walker.php、setup-enqueue.php
 * - [新增] inc/member-functions.php、member-ajax.php、um-integration.php
 * - [新增] inc/api-rest.php、content-slug.php、external-links.php
 * - [保留] inc/member-stats.php、member-render.php
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數
   ============================================================ */
define( 'weixiaoacg_VERSION',   '2.3.0' );
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

// Batch 1B-1: 追蹤系統參數
define( 'SMACG_FOLLOW_DAILY_LIMIT', 200 );  // 單日追蹤上限
define( 'SMACG_FOLLOW_COOLDOWN',    1   );  // 同一目標冷卻秒數（防連點）

const WEIXIAOACG_ID_CATS  = [ 'announcement', 'news' ];
const WEIXIAOACG_LLM_CATS = [ 'review', 'feature' ];

/* ============================================================
   載入 inc/*.php （順序很重要）
   ============================================================ */
$inc_dir = weixiaoacg_THEME_DIR . '/inc/';

// 1. 基礎函式（其他檔會用到）
require_once $inc_dir . 'member-functions.php';   // 點數/等級/cooldown helpers
require_once $inc_dir . 'member-stats.php';       // 統計/隱私/email 遮罩
require_once $inc_dir . 'member-render.php';      // 會員頁渲染

// 2. 主題設定
require_once $inc_dir . 'setup-theme.php';        // theme support / menu / image size
require_once $inc_dir . 'class-nav-walker.php';   // 自訂 Nav Walker

// 3. 資源載入
require_once $inc_dir . 'setup-enqueue.php';      // jQuery / CSS / JS

// 4. AJAX 與 REST
require_once $inc_dir . 'member-ajax.php';        // wp_ajax_smacg_*
require_once $inc_dir . 'api-rest.php';           // REST API routes

// 5. 第三方整合
require_once $inc_dir . 'um-integration.php';     // Ultimate Member

// 6. 內容處理
require_once $inc_dir . 'content-slug.php';       // Gemini slug
require_once $inc_dir . 'external-links.php';     // 外部連結 target=_blank

// 7. 影像優化（Batch C #6 - 2026-05-13）
if ( file_exists( $inc_dir . 'image-optimizer.php' ) ) {
    require_once $inc_dir . 'image-optimizer.php';  // WebP / srcset / picture tag
}

// 8. 公開個人頁（Batch 1A - 2026-05-13 新增）
//    用 file_exists 防呆，這樣你可以分批 push 而不會在中間狀態壞站
if ( file_exists( $inc_dir . 'public-profile.php' ) ) {
    require_once $inc_dir . 'public-profile.php';   // /u/{username}/ rewrite + 資料準備
}

// 9. 追蹤系統（Batch 1B-1 - 2026-05-13 新增）
//    提供 smacg_follow_user / smacg_unfollow_user / smacg_is_following / 計數 helpers
if ( file_exists( $inc_dir . 'follow-system.php' ) ) {
    require_once $inc_dir . 'follow-system.php';
}
// 10. 追蹤系統 AJAX（Batch 1B-2 - 2026-05-13）
if ( file_exists( $inc_dir . 'follow-ajax.php' ) ) {
    require_once $inc_dir . 'follow-ajax.php';
}
// 11. 通知中心（Batch 1C-1 - 2026-05-13）
if ( file_exists( $inc_dir . 'notifications-system.php' ) ) {
    require_once $inc_dir . 'notifications-system.php';
}
// 12. 通知中心 事件監聽 + AJAX（Batch 1C-2 - 2026-05-13）
if ( file_exists( $inc_dir . 'notifications-events.php' ) ) {
    require_once $inc_dir . 'notifications-events.php';
}
if ( file_exists( $inc_dir . 'notifications-ajax.php' ) ) {
    require_once $inc_dir . 'notifications-ajax.php';
}

