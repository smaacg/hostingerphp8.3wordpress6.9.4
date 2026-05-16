<?php
/**
 * SMACG Social — Plugin Bootstrap
 *
 * @package    weixiaoacg
 * @subpackage smacg-social
 * @version    1.2.0
 * @since      1.0.0
 *
 * Changelog:
 * - 1.2.0 (2026-05-16)
 *   * 新增載入 legacy/followers-page.php（粉絲 / 追蹤中子頁）。
 *     載入順序排在 public-profile-render.php 之後，因為依賴：
 *       - smacg_get_public_profile_url()  (public-profile.php)
 *       - smacg_is_following()            (follow-system.php)
 *       - smacg_get_user_privacy()        (smacg-members plugin)
 * - 1.1.0
 *   * 加入 notifications 系列模組與 public-profile 模組。
 * - 1.0.0
 *   * 初始版本：follow-system + follow-ajax。
 */

namespace SMACG\Social;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( self::$instance === null ) {
            self::$instance = new self();
            self::$instance->boot();
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}

    /**
     * 載入所有 legacy 檔案
     *
     * 載入順序很重要：
     *   1. follow-system          — 提供 smacg_follow_user / smacg_is_following 等核心 API
     *   2. follow-ajax            — 依賴 follow-system
     *   3. notifications-system   — 提供 smacg_create_notification 核心 API
     *   4. notifications-events   — 監聽事件，會呼叫 smacg_create_notification
     *   5. notifications-ajax     — 通知 AJAX 端點
     *   6. notifications-render   — 鈴鐺 UI
     *   7. notifications-email    — Email 寄送
     *   8. public-profile         — 提供 smacg_get_public_profile_url 給 notifications-events 使用
     *   9. public-profile-render  — 公開檔案頁面（會呼叫 follow + notifications API）
     *  10. followers-page         — 粉絲 / 追蹤中子頁（v1.2.0 新增，依賴 1, 8, 9）
     */
    private function boot(): void {
        $legacy = SMACG_SOCIAL_DIR . 'includes/legacy/';

        require_once $legacy . 'follow-system.php';
        require_once $legacy . 'follow-ajax.php';

        require_once $legacy . 'notifications-system.php';
        require_once $legacy . 'notifications-events.php';
        require_once $legacy . 'notifications-ajax.php';
        require_once $legacy . 'notifications-render.php';
        require_once $legacy . 'notifications-email.php';

        require_once $legacy . 'public-profile.php';
        require_once $legacy . 'public-profile-render.php';

        // v1.2.0 新增：粉絲 / 追蹤中子頁
        require_once $legacy . 'followers-page.php';

        // 記錄版本
        $stored = get_option( 'smacg_social_version' );
        if ( $stored !== SMACG_SOCIAL_VERSION ) {
            update_option( 'smacg_social_version', SMACG_SOCIAL_VERSION );
        }
    }
}
