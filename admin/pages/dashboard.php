<?php
/**
 * Dashboard Page
 *
 * @package Anime_Sync_Pro
 * @version 1.1.0
 *
 * Changelog:
 *  - 1.1.0 (2026-05-10):
 *      • 版本常數判讀改為 ANIME_SYNC_PRO_VERSION 優先（修正一直顯示 1.0.0 的 bug）。
 *      • 時間欄位改用 wp_date() 套用站台時區。
 *      • 統計卡片數字加上千分位格式。
 *      • 加入「未匯入動漫」空狀態提示與快速匯入連結。
 *      • 系統資訊增列：DB 版本、上次升級時間、字典模式徽章。
 *      • 加上 class_exists / method_exists 防呆，避免類別缺失時 fatal。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( '您沒有權限存取此頁面。', 'anime-sync-pro' ) );
}

/* ───────────────────────────────────────────────
   取得統計資料（含防呆）
─────────────────────────────────────────────── */
$pending_count   = 0;
$approved_count  = 0;

if ( class_exists( 'Anime_Sync_Review_Queue' ) ) {
    $review_queue   = new Anime_Sync_Review_Queue();
    $pending_count  = method_exists( $review_queue, 'get_count' ) ? (int) $review_queue->get_count( 'pending' )  : 0;
    $approved_count = method_exists( $review_queue, 'get_count' ) ? (int) $review_queue->get_count( 'approved' ) : 0;
}

$published_count = wp_count_posts( 'anime' );
$published_total = isset( $published_count->publish ) ? (int) $published_count->publish : 0;
$draft_total     = isset( $published_count->draft )   ? (int) $published_count->draft   : 0;

$log_stats = [
    'total'    => 0,
    'info'     => 0,
    'warning'  => 0,
    'error'    => 0,
    'critical' => 0,
];
$recent_logs = [];
if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
    $logger      = new Anime_Sync_Error_Logger();
    $log_stats   = $logger->get_statistics( 7 );
    $recent_logs = $logger->get_recent_logs( 10 );
}

/* ───────────────────────────────────────────────
   版本字串（修正）
─────────────────────────────────────────────── */
if ( defined( 'ANIME_SYNC_PRO_VERSION' ) ) {
    $plugin_version = ANIME_SYNC_PRO_VERSION;
} elseif ( defined( 'ANIME_SYNC_VERSION' ) ) {
    $plugin_version = ANIME_SYNC_VERSION;
} else {
    $plugin_version = '1.0.0';
}

$db_version       = get_option( 'anime_sync_db_version', '—' );
$last_upgrade     = get_option( 'anime_sync_last_upgrade_at', '' );
$last_upgrade_str = $last_upgrade ? wp_date( 'Y-m-d H:i', strtotime( $last_upgrade ) ) : '—';
?>

<div class="wrap">
    <h1>Anime Sync Pro 儀表板 <span style="font-size:13px;color:#888;font-weight:normal;">v<?php echo esc_html( $plugin_version ); ?></span></h1>

    <div class="anime-sync-dashboard">

        <!-- ─── 統計卡片 ─── -->
        <div class="anime-sync-stats-grid">

            <div class="anime-sync-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3>待審核</h3>
                    <p class="stat-number"><?php echo esc_html( number_format_i18n( $pending_count ) ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-queue' ) ); ?>" class="stat-link">查看佇列 →</a>
                </div>
            </div>

            <div class="anime-sync-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <h3>已通過</h3>
                    <p class="stat-number"><?php echo esc_html( number_format_i18n( $approved_count ) ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-queue&filter_status=draft' ) ); ?>" class="stat-link">查看已通過 →</a>
                </div>
            </div>

            <div class="anime-sync-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-megaphone"></span>
                </div>
                <div class="stat-content">
                    <h3>已發布</h3>
                    <p class="stat-number"><?php echo esc_html( number_format_i18n( $published_total ) ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-published' ) ); ?>" class="stat-link">查看動漫 →</a>
                </div>
            </div>

            <div class="anime-sync-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="stat-content">
                    <h3>錯誤 (7天)</h3>
                    <p class="stat-number"><?php echo esc_html( number_format_i18n( (int) $log_stats['error'] + (int) $log_stats['critical'] ) ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-logs' ) ); ?>" class="stat-link">查看日誌 →</a>
                </div>
            </div>

        </div>

        <!-- ─── 空狀態提示 ─── -->
        <?php if ( $published_total === 0 && $draft_total === 0 ) : ?>
            <div class="notice notice-info inline" style="margin-top:20px;padding:16px;">
                <p style="margin:0;">
                    <strong>👋 還沒匯入任何動漫</strong>，建議從匯入工具開始：
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-import' ) ); ?>" class="button button-primary" style="margin-left:8px;">立即匯入</a>
                </p>
            </div>
        <?php endif; ?>

        <!-- ─── 快速操作 ─── -->
        <div class="anime-sync-quick-actions">
            <h2>快速操作</h2>
            <div class="quick-actions-grid">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-import' ) ); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-download"></span> 匯入動漫
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-queue' ) ); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-list-view"></span> 審核佇列
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-settings' ) ); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-admin-settings"></span> 設定
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=anime' ) ); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-edit"></span> 管理動漫
                </a>
            </div>
        </div>

        <!-- ─── 繁簡轉換器狀態 ─── -->
        <div class="anime-sync-converter-test" style="margin-top:20px;padding:20px;background:#fff;border:1px solid #ccd0d4;">
            <h2 style="margin-top:0;">繁簡轉換器狀態</h2>
            <?php if ( class_exists( 'Anime_Sync_CN_Converter' ) ) : ?>
                <?php
                $cn_converter = new Anime_Sync_CN_Converter();
                $stats        = method_exists( $cn_converter, 'get_stats' ) ? $cn_converter->get_stats() : [];
                $test_cn      = '动画制作、脚本、监督、角色设计';
                $test_tw      = $cn_converter->convert( $test_cn );
                $is_working   = ( $test_cn !== $test_tw );
                ?>
                <table class="wp-list-table widefat fixed">
                    <tr>
                        <th style="width:200px;">字典檔案路徑</th>
                        <td><code style="font-size:12px;"><?php echo esc_html( $stats['dict_path'] ?? '—' ); ?></code></td>
                    </tr>
                    <tr>
                        <th>檔案狀態</th>
                        <td>
                            <?php if ( ! empty( $stats['dict_file_size'] ) && $stats['dict_file_size'] > 0 ) : ?>
                                <span style="color:#46b450;">✓ 檔案存在 (<?php echo esc_html( size_format( $stats['dict_file_size'] ) ); ?>)</span>
                            <?php else : ?>
                                <span style="color:#dc3232;">✗ 檔案不存在或為空</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>詞條總數</th>
                        <td><?php echo esc_html( number_format_i18n( (int) ( $stats['dict_entry_count'] ?? 0 ) ) ); ?> 條</td>
                    </tr>
                    <tr>
                        <th>轉換模式</th>
                        <td>
                            <span style="display:inline-block;padding:2px 8px;background:#e7f6ed;color:#207b45;border-radius:3px;font-size:12px;">
                                <?php echo esc_html( $stats['mode'] ?? 'dict' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>轉換測試</th>
                        <td>
                            原文：<code><?php echo esc_html( $test_cn ); ?></code><br>
                            結果：<strong><?php echo esc_html( $test_tw ); ?></strong><br>
                            狀態：<?php echo $is_working
                                ? '<span style="color:#46b450;font-weight:600;">✓ 運作正常</span>'
                                : '<span style="color:#dc3232;font-weight:600;">✗ 轉換無效（請檢查字典內容）</span>'; ?>
                        </td>
                    </tr>
                </table>
            <?php else : ?>
                <p style="color:#dc3232;">⚠️ 錯誤：找不到 <code>Anime_Sync_CN_Converter</code> 類別。</p>
            <?php endif; ?>
        </div>

        <!-- ─── 最近日誌 ─── -->
        <div class="anime-sync-recent-logs" style="margin-top:20px;">
            <h2>最近日誌 (7天)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:120px;">等級</th>
                        <th>訊息</th>
                        <th style="width:180px;">時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $recent_logs ) ) : ?>
                        <?php foreach ( $recent_logs as $log ) :
                            $level       = $log['level'] ?? 'info';
                            $level_class = 'log-level-' . esc_attr( $level );
                            $created_at  = $log['created_at'] ?? '';
                            $time_str    = $created_at ? wp_date( 'Y-m-d H:i', strtotime( $created_at ) ) : '—';
                        ?>
                            <tr>
                                <td>
                                    <span class="log-level-badge <?php echo $level_class; ?>">
                                        <?php echo esc_html( strtoupper( $level ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $log['message'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $time_str ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="3" style="text-align:center;color:#999;padding:20px;">尚無日誌記錄</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ─── 系統資訊 ─── -->
        <div class="anime-sync-system-info" style="margin-top:20px;">
            <h2>系統資訊</h2>
            <table class="wp-list-table widefat fixed">
                <tbody>
                    <tr>
                        <th style="width:200px;">插件版本</th>
                        <td><strong><?php echo esc_html( $plugin_version ); ?></strong></td>
                    </tr>
                    <tr>
                        <th>DB 版本</th>
                        <td><?php echo esc_html( $db_version ); ?></td>
                    </tr>
                    <tr>
                        <th>上次升級時間</th>
                        <td><?php echo esc_html( $last_upgrade_str ); ?></td>
                    </tr>
                    <tr>
                        <th>WordPress 版本</th>
                        <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                    </tr>
                    <tr>
                        <th>PHP 版本</th>
                        <td>
                            <?php echo esc_html( PHP_VERSION ); ?>
                            <?php if ( version_compare( PHP_VERSION, '8.0', '<' ) ) : ?>
                                <span style="color:#dc3232;margin-left:8px;">⚠️ 建議升級至 PHP 8.0+</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>記憶體使用</th>
                        <td>
                            <?php
                            if ( class_exists( 'Anime_Sync_Performance' ) && method_exists( 'Anime_Sync_Performance', 'get_memory_usage' ) ) {
                                $memory = Anime_Sync_Performance::get_memory_usage();
                                echo esc_html( ( $memory['current'] ?? '—' ) . ' / ' . ( $memory['limit'] ?? '—' ) );
                            } else {
                                echo esc_html( size_format( memory_get_usage( true ) ) . ' / ' . ini_get( 'memory_limit' ) );
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>動漫總數（含草稿）</th>
                        <td>
                            <?php echo esc_html( number_format_i18n( $published_total ) ); ?> 已發布
                            <span style="color:#999;">/</span>
                            <?php echo esc_html( number_format_i18n( $draft_total ) ); ?> 草稿
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>
