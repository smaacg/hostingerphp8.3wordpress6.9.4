<?php
/**
 * Level Badge Display - 等級徽章顯示元件
 *
 * 原檔：blocksy-child/inc/level-badge-display.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Level;

defined( 'ABSPATH' ) || exit;

class Badge {

    public static function init() {
        add_filter( 'get_comment_author', [ __CLASS__, 'filter_comment_author' ], 20, 3 );
        add_action( 'smacg_public_profile_hero_meta', [ __CLASS__, 'render_hero_badge' ], 10, 1 );
    }

    /**
     * 渲染等級徽章
     */
    public static function render( $uid, $size = 'sm', $args = [] ) {
        $uid = (int) $uid;
        if ( ! $uid ) return '';
        if ( ! function_exists( 'smacg_get_user_level_info' ) ) return '';

        $size = in_array( $size, [ 'sm', 'md', 'lg' ], true ) ? $size : 'sm';
        $args = wp_parse_args( $args, [
            'show_job' => ( $size === 'lg' ),
            'link'     => false,
        ] );

        $info  = \smacg_get_user_level_info( $uid );
        $level = (int) ( $info['level'] ?? 1 );
        $tier  = $info['tier']  ?? [];
        $title = $tier['title'] ?? '新進會員';
        $icon  = $tier['icon']  ?? '🌱';
        $color = $tier['color'] ?? '#94a3b8';

        $job_html = '';
        if ( $args['show_job'] && function_exists( 'smacg_get_user_career_job' ) ) {
            $job_key = \smacg_get_user_career_job( $uid );
            if ( $job_key && function_exists( 'smacg_get_career_job_label' ) ) {
                $job = \smacg_get_career_job_label( $job_key );
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

        ob_start(); ?>
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
            <?php echo $job_html; ?>
        </span>
        <?php
        $html = ob_get_clean();

        if ( $args['link'] && function_exists( 'smacg_get_public_profile_url' ) ) {
            $url  = \smacg_get_public_profile_url( $uid );
            $html = sprintf( '<a class="smacg-lvbadge-link" href="%s">%s</a>', esc_url( $url ), $html );
        }

        return $html;
    }

    /**
     * 留言區作者名後方插入小徽章
     */
    public static function filter_comment_author( $author, $comment_ID, $comment ) {
        if ( is_admin() || is_feed() ) return $author;

        if ( ! $comment instanceof \WP_Comment ) {
            $comment = get_comment( $comment_ID );
            if ( ! $comment ) return $author;
        }

        $user_id = (int) $comment->user_id;
        if ( ! $user_id ) return $author;
        if ( strpos( $author, 'smacg-lvbadge' ) !== false ) return $author;

        // 避免被信件函式呼叫到
        $bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 6 );
        foreach ( $bt as $frame ) {
            if ( ! isset( $frame['function'] ) ) continue;
            if ( in_array( $frame['function'], [
                'wp_notify_postauthor',
                'wp_notify_moderator',
                'wp_new_comment_notify_postauthor',
                'wp_new_comment_notify_moderator',
            ], true ) ) {
                return $author;
            }
        }

        $badge = self::render( $user_id, 'sm', [ 'link' => true ] );
        if ( ! $badge ) return $author;
        return $author . ' ' . $badge;
    }

    /**
     * 公開頁 hero
     */
    public static function render_hero_badge( $user_id ) {
        echo self::render( (int) $user_id, 'lg', [ 'show_job' => true, 'link' => false ] );
    }

    /**
     * 公開頁徽章數量文字
     */
    public static function get_badge_count_text( $user_id ) {
        if ( ! function_exists( 'smacg_get_user_badge_count' ) ) return '';
        $count = (int) \smacg_get_user_badge_count( $user_id );
        if ( $count <= 0 ) return '';
        return sprintf( '🏅 %d 枚徽章', $count );
    }
}

Badge::init();
