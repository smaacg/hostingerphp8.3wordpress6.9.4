<?php
/**
 * SMACG Social — Plugin Bootstrap
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

        // 記錄版本
        $stored = get_option( 'smacg_social_version' );
        if ( $stored !== SMACG_SOCIAL_VERSION ) {
            update_option( 'smacg_social_version', SMACG_SOCIAL_VERSION );
        }
    }
}
