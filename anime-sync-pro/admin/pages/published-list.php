<?php
/**
 * Published / Manage Anime List Page
 *
 * @package Anime_Sync_Pro
 * @version 1.2.0
 *
 * Changelog:
 *  - 1.2.0 (2026-05-10):
 *      • 加入「重新同步」按鈕的完整 JS handler（原本沒有 → 按了沒反應）。
 *      • 加入篩選器：狀態（全部/已發布/草稿/待審）、搜尋框、每頁筆數選擇。
 *      • 發布日期改用 wp_date() 站台時區。
 *      • 加入 current_user_can() 權限檢查。
 *      • 新增 toast 提示與按鈕 disabled 防重複點擊。
 *      • 顯示「上次同步」欄位（取代 Post ID 註腳）。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( esc_html__( '您沒有權限存取此頁面。', 'anime-sync-pro' ) );
}

/* ───────────────────────────────────────────────
   參數
─────────────────────────────────────────────── */
$paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page    = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
$per_page    = in_array( $per_page, [ 10, 20, 50, 100 ], true ) ? $per_page : 20;
$status_arg  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
$keyword     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$status_map_query = [
    'all'     => [ 'publish', 'pending', 'draft' ],
    'publish' => [ 'publish' ],
    'pending' => [ 'pending' ],
    'draft'   => [ 'draft' ],
];
$post_status = $status_map_query[ $status_arg ] ?? $status_map_query['all'];

$query_args = [
    'post_type'      => 'anime',
    'post_status'    => $post_status,
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
];

if ( $keyword !== '' ) {
    $query_args['s'] = $keyword;
}

$query = new WP_Query( $query_args );
?>

<div class="wrap">
    <h1 class="wp-heading-inline">已發布動漫</h1>
    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=anime' ) ); ?>" class="page-title-action">新增動漫</a>
    <hr class="wp-header-end">

    <div class="anime-sync-published-list" style="margin-top:20px;">

        <!-- ─── 篩選列 ─── -->
        <form method="get" style="margin-bottom:12px;">
            <input type="hidden" name="page" value="anime-sync-published" />

            <select name="status" style="vertical-align:middle;">
                <option value="all"     <?php selected( $status_arg, 'all' );     ?>>全部狀態</option>
                <option value="publish" <?php selected( $status_arg, 'publish' ); ?>>已發布</option>
                <option value="draft"   <?php selected( $status_arg, 'draft' );   ?>>草稿</option>
                <option value="pending" <?php selected( $status_arg, 'pending' ); ?>>待審核</option>
            </select>

            <select name="per_page" style="vertical-align:middle;">
                <option value="10"  <?php selected( $per_page, 10 );  ?>>每頁 10 筆</option>
                <option value="20"  <?php selected( $per_page, 20 );  ?>>每頁 20 筆</option>
                <option value="50"  <?php selected( $per_page, 50 );  ?>>每頁 50 筆</option>
                <option value="100" <?php selected( $per_page, 100 ); ?>>每頁 100 筆</option>
            </select>

            <input type="search" name="s" value="<?php echo esc_attr( $keyword ); ?>" placeholder="搜尋標題…" style="vertical-align:middle;width:220px;" />

            <button type="submit" class="button">套用</button>

            <?php if ( $status_arg !== 'all' || $keyword !== '' || $per_page !== 20 ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-published' ) ); ?>" class="button button-link">重設</a>
            <?php endif; ?>

            <span style="margin-left:12px;color:#666;">共 <?php echo esc_html( number_format_i18n( $query->found_posts ) ); ?> 筆</span>

            <span style="float:right;">
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=anime' ) ); ?>" class="button">完整管理介面</a>
            </span>
        </form>

        <!-- ─── 資料表 ─── -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:70px;">封面</th>
                    <th>標題</th>
                    <th style="width:100px;">AniList ID</th>
                    <th style="width:100px;">狀態</th>
                    <th style="width:140px;">發布日期</th>
                    <th style="width:140px;">上次同步</th>
                    <th style="width:230px;">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $query->have_posts() ) : ?>
                <?php while ( $query->have_posts() ) : $query->the_post();
                    $post_id    = get_the_ID();
                    $anilist_id = get_post_meta( $post_id, 'anime_anilist_id', true );

                    // 封面：優先用特色圖，再用 anime_cover_image
                    $cover_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
                    if ( ! $cover_url ) {
                        $cover_url = get_post_meta( $post_id, 'anime_cover_image', true );
                    }

                    // 上次同步時間
                    $last_sync_raw = get_post_meta( $post_id, 'anime_sync_time', true )
                                     ?: get_post_meta( $post_id, 'anime_last_sync', true );
                    $last_sync_str = $last_sync_raw ? wp_date( 'Y-m-d H:i', strtotime( $last_sync_raw ) ) : '—';

                    $current_status = get_post_status( $post_id );
                ?>
                <tr data-post-id="<?php echo esc_attr( $post_id ); ?>">
                    <td>
                        <?php if ( $cover_url ) : ?>
                            <img src="<?php echo esc_url( $cover_url ); ?>"
                                 alt=""
                                 style="width:50px;height:70px;object-fit:cover;border-radius:4px;border:1px solid #ddd;display:block;">
                        <?php else : ?>
                            <div style="width:50px;height:70px;background:#eee;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:10px;border:1px solid #ddd;">無封面</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" style="font-size:14px;text-decoration:none;">
                                <?php the_title(); ?>
                            </a>
                        </strong>
                        <div style="color:#999;font-size:12px;margin-top:3px;">Post ID: <?php echo (int) $post_id; ?></div>
                    </td>
                    <td>
                        <?php if ( $anilist_id ) : ?>
                            <a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/" target="_blank" rel="noopener">
                                <code><?php echo esc_html( $anilist_id ); ?></code>
                            </a>
                        <?php else : ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $status_map = [
                            'publish' => [ '已發布', '#e7f6ed', '#207b45' ],
                            'pending' => [ '待審核', '#fff4e5', '#b25e09' ],
                            'draft'   => [ '草稿',   '#f0f0f1', '#646970' ],
                        ];
                        $s = $status_map[ $current_status ] ?? [ $current_status, '#f0f0f1', '#646970' ];
                        echo '<span style="background:' . esc_attr( $s[1] ) . ';color:' . esc_attr( $s[2] ) . ';padding:3px 8px;border-radius:3px;font-weight:500;font-size:12px;">' . esc_html( $s[0] ) . '</span>';
                        ?>
                    </td>
                    <td>
                        <span class="description"><?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( get_the_date( 'Y-m-d H:i:s' ) ) ) ); ?></span>
                    </td>
                    <td>
                        <span style="font-size:12px;color:#666;"><?php echo esc_html( $last_sync_str ); ?></span>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="button button-small">編輯</a>
                        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="button button-small" target="_blank" rel="noopener">查看</a>
                        <?php if ( $anilist_id ) : ?>
                        <button type="button"
                                class="button button-small resync-anime"
                                data-post-id="<?php echo esc_attr( $post_id ); ?>"
                                data-anilist-id="<?php echo esc_attr( $anilist_id ); ?>"
                                style="color:#2271b1;">
                            重新同步
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:60px;background:#fff;">
                        <span class="dashicons dashicons-video-alt3" style="font-size:48px;width:48px;height:48px;color:#ddd;"></span>
                        <p style="margin-top:15px;font-size:16px;color:#666;">
                            <?php echo $keyword !== '' || $status_arg !== 'all'
                                ? '沒有符合條件的動漫'
                                : '尚未匯入任何動漫'; ?>
                        </p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-import' ) ); ?>" class="button button-primary button-large">立即匯入</a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $query->max_num_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $base_url = add_query_arg( [
                    'page'     => 'anime-sync-published',
                    'status'   => $status_arg,
                    'per_page' => $per_page,
                    's'        => $keyword,
                ], admin_url( 'admin.php' ) );

                echo paginate_links( [
                    'base'      => $base_url . '%_%',
                    'format'    => '&paged=%#%',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $query->max_num_pages,
                    'current'   => $paged,
                ] );
                ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php wp_reset_postdata(); ?>

<!-- Toast -->
<div id="published-toast" style="display:none;position:fixed;top:50px;right:20px;z-index:99999;padding:12px 20px;background:#46b450;color:#fff;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.2);font-size:14px;"></div>

<style>
.wp-list-table th { font-weight:600 !important; background:#f8f9fa; }
.wp-list-table td { vertical-align:middle !important; }
.resync-anime:hover { background:#f0f6fb !important; border-color:#2271b1 !important; }
.resync-anime[disabled] { opacity:.6;cursor:not-allowed; }
</style>

<script>
jQuery(function($){
    'use strict';

    function showToast(message, isError) {
        var $toast = $('#published-toast');
        $toast.text(message)
              .css('background', isError ? '#dc3232' : '#46b450')
              .fadeIn(200);
        setTimeout(function(){ $toast.fadeOut(300); }, 2500);
    }

    // ✅ 重新同步按鈕
    $(document).on('click', '.resync-anime', function(){
        var $btn      = $(this);
        var postId    = $btn.data('post-id');
        var anilistId = $btn.data('anilist-id');

        if (!anilistId) {
            showToast('此筆缺少 AniList ID，無法重新同步', true);
            return;
        }
        if (!confirm('確定重新同步此動漫資料？\n（已鎖定欄位將被跳過。）')) return;

        var cfg = window.animeSyncAdmin || {};
        if (!cfg.ajaxUrl || !cfg.nonce) {
            showToast('系統錯誤：找不到 animeSyncAdmin 設定，請重新整理', true);
            return;
        }

        var originalText = $btn.text();
        $btn.prop('disabled', true).text('同步中…');

        $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 180000,
            data: {
                action:     'anime_sync_import_single',
                nonce:      cfg.nonce,
                anilist_id: anilistId,
                post_id:    postId,
                force:      1
            },
            success: function(resp){
                if (resp && resp.success) {
                    $btn.text('✓ 已同步').css('color','#46b450');
                    showToast('同步成功');
                    setTimeout(function(){
                        $btn.prop('disabled', false).text(originalText).css('color','#2271b1');
                    }, 2500);
                } else {
                    var msg = (resp && resp.data && (resp.data.message || resp.data)) || '同步失敗';
                    showToast(msg, true);
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr){
                showToast('網路錯誤 (HTTP ' + xhr.status + ')', true);
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
