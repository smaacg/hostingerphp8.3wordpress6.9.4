<?php
/**
 * Level Badge Display - 等級徽章顯示元件
 *
 * @package weixiaoacg
 * @subpackage Gamification
 * @version 1.0.0 (2026-05-14)
 *
 * Batch 2A-4：
 *   - 提供 smacg_render_level_badge( $uid, $size ) 共用元件
 *   - hook get_comment_author 在留言區「作者名後方」插入小徽章（A 方案）
 *   - hook 公開頁 hero（透過 action smacg_public_profile_hero_meta）
 *
 * Size 規格：
 *   - 'sm'  → 留言區用（高 18px，僅 Lv 數字 + tier 圖示）
 *   - 'md'  → 一般用（高 24px，Lv + tier 文字）
 *   - 'lg'  → Hero 用（高 36px，含 EXP 進度條）
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   1. 核心：渲染等級徽章
   ============================================================ */

/**
 * @param int    $uid
 * @param string $size 'sm' | 'md' | 'lg'
 * @param array  $args [ 'show_job' => bool ]
 * @return string HTML
 */
function smacg_render_level_badge( $uid, $size = 'sm', $args = [] ) {
    $uid = (int) $uid;
    if ( ! $uid ) return '';
    if ( ! function_exists( 'smacg_get_user_level_info' ) ) return '';

    $size = in_array( $size, [ 'sm', 'md', 'lg' ], true ) ? $size : 'sm';

    $args = wp_parse_args( $args, [
        'show_job' => ( $size === 'lg' ),
        'link'     => false,   // 是否包成連到公開頁的 <a>
    ] );

    $info  = smacg_get_user_level_info( $uid );
    $level = (int) ( $info['level'] ?? 1 );
    $tier  = $info['tier']  ?? [];
    $title = $tier['title'] ?? '新進會員';
    $icon  = $tier['icon']  ?? '🌱';
    $color = $tier['color'] ?? '#94a3b8';

    // 職業（若有）
    $job_html = '';
    if ( $args['show_job'] && function_exists( 'smacg_get_user_career_job' ) ) {
        $job_key = smacg_get_user_career_job( $uid );
        if ( $job_key ) {
            $job = smacg_get_career_job_label( $job_key );
            if ( $job ) {
                $job_html = sprintf(
                    '<span class="smacg-lvbadge__job" style="color:%s">%s %s</span>',
                    esc_attr( $job['color'] ),
                    esc_html( $job['icon'] ),
                    esc_html( $job['label'] )
                );
            }
        }
    }

    // 組 HTML
    ob_start();
    ?>
    <span class="smacg-lvbadge smacg-lvbadge--<?php echo esc_attr( $size ); ?>"
          style="--lv-color: <?php echo esc_attr( $color ); ?>"
          title="<?php echo esc_attr( sprintf( 'Lv.%d %s %s', $level, $icon, $title ) ); ?>">
        <span class="smacg-lvbadge__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
        <span class="smacg-lvbadge__lv">Lv.<?php echo (int) $level; ?></span>
        <?php if ( $size !== 'sm' ) : ?>
            <span class="smacg-lvbadge__title"><?php echo esc_html( $title ); ?></span>
        <?php endif; ?>
        <?php if ( $size === 'lg' && ! empty( $info['percent'] ) ) : ?>
            <span class="smacg-lvbadge__bar">
                <span class="smacg-lvbadge__bar-fill" style="width: <?php echo (float) $info['percent']; ?>%"></span>
            </span>
        <?php endif; ?>
        <?php echo $job_html; // already escaped ?>
    </span>
    <?php
    $html = ob_get_clean();

    if ( $args['link'] && function_exists( 'smacg_get_public_profile_url' ) ) {
        $url  = smacg_get_public_profile_url( $uid );
        $html = sprintf(
            '<a class="smacg-lvbadge-link" href="%s">%s</a>',
            esc_url( $url ),
            $html
        );
    }

    return $html;
}

/* ============================================================
   2. 留言區自動插入小徽章（A 方案：作者名後方）
   ------------------------------------------------------------
   filter: get_comment_author
   ------------------------------------------------------------
   注意：get_comment_author 回傳純字串，多數佈景的 callback 是
   直接 echo，因此把 HTML 接在後面是相對安全的做法。
   ============================================================ */
add_filter( 'get_comment_author', 'smacg_filter_comment_author_with_badge', 20, 3 );
function smacg_filter_comment_author_with_badge( $author, $comment_ID, $comment ) {

    // 後台留言列表不處理（避免破壞表格）
    if ( is_admin() ) return $author;

    // RSS / Feed 不處理
    if ( is_feed() ) return $author;

    // 確保有 comment 物件
    if ( ! $comment instanceof WP_Comment ) {
        $comment = get_comment( $comment_ID );
        if ( ! $comment ) return $author;
    }

    $user_id = (int) $comment->user_id;
    if ( ! $user_id ) return $author;  // 訪客留言不顯示

    // 避免雙重套用：若 author 已含徽章 class，跳過
    if ( strpos( $author, 'smacg-lvbadge' ) !== false ) return $author;

    // 避免被誤用在其他需要純字串的場合：偵測呼叫情境
    // 用簡單的 backtrace 檢查 — 若呼叫者是 wp_notify_postauthor 之類的 email 函式，跳過
    $bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 6 );
    foreach ( $bt as $frame ) {
        if ( ! isset( $frame['function'] ) ) continue;
        $fn = $frame['function'];
        if ( in_array( $fn, [
            'wp_notify_postauthor',
            'wp_notify_moderator',
            'wp_new_comment_notify_postauthor',
            'wp_new_comment_notify_moderator',
        ], true ) ) {
            return $author;
        }
    }

    $badge = smacg_render_level_badge( $user_id, 'sm', [ 'link' => true ] );
    if ( ! $badge ) return $author;

    return $author . ' ' . $badge;
}

/* ============================================================
   3. 公開頁 Hero 區顯示完整等級資訊
   ------------------------------------------------------------
   行為：透過 action 讓 page-public-profile.php 呼叫
       do_action( 'smacg_public_profile_hero_meta', $user_id );
   即可插入大尺寸徽章。
   ============================================================ */
add_action( 'smacg_public_profile_hero_meta', 'smacg_pp_render_hero_level_badge', 10, 1 );
function smacg_pp_render_hero_level_badge( $user_id ) {
    echo smacg_render_level_badge( (int) $user_id, 'lg', [
        'show_job' => true,
        'link'     => false,
    ] );
}

/* ============================================================
   4. 在公開頁顯示已解鎖徽章數量（可供模板呼叫）
   ============================================================ */
function smacg_pp_get_badge_count_text( $user_id ) {
    if ( ! function_exists( 'smacg_get_user_badge_count' ) ) return '';
    $count = (int) smacg_get_user_badge_count( $user_id );
    if ( $count <= 0 ) return '';
    return sprintf( '🏅 %d 枚徽章', $count );
}
