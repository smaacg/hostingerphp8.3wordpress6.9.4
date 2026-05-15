<?php
/**
 * SMACG Members — Plugin Bootstrap
 */

namespace SMACG\Members;

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
     *   1. member-functions.php — 提供其他檔案使用的 helper
     *   2. member-stats.php     — 統計（被 render 與 ajax 使用）
     *   3. member-render.php    — 頁面渲染
     *   4. member-ajax.php      — AJAX 端點
     *   5. um-integration.php   — UM 整合（最後，因為會覆寫前面的 hook）
     */
    private function boot(): void {
        $legacy = SMACG_MEMBERS_DIR . 'includes/legacy/';

        require_once $legacy . 'member-functions.php';
        require_once $legacy . 'member-stats.php';
        require_once $legacy . 'member-render.php';
        require_once $legacy . 'member-ajax.php';

        // UM 整合僅在 UM 外掛存在時載入
        if ( $this->is_um_active() ) {
            require_once $legacy . 'um-integration.php';
        }

        // 記錄版本（供 DB 升級邏輯使用）
        $stored = get_option( 'smacg_members_version' );
        if ( $stored !== SMACG_MEMBERS_VERSION ) {
            update_option( 'smacg_members_version', SMACG_MEMBERS_VERSION );
        }
    }

    /**
     * 是否啟用 Ultimate Member
     */
    private function is_um_active(): bool {
        return class_exists( 'UM' ) || function_exists( 'UM' ) || function_exists( 'um_user' );
    }
}
