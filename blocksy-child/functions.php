<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package weixiaoacg
 * @version 2.2.0
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
define( 'weixiaoacg_VERSION',   '2.2.0' );
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
