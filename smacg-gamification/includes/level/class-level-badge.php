<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * 等級徽章顯示（搬自 theme/inc/level-badge-display.php）
 *
 * 用途：
 *   - 留言作者名稱旁顯示等級徽章
 *   - 公開個人頁顯示英雄徽章
 *   - 提供 render_badge( $uid, $opts ) helper
 */
class Level_Badge {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'get_comment_author', [ __CLASS__, 'append_to_comment_author' ], 10, 3 );
    }

    /* ==========================================================
     * 主要 render
     * ========================================================== */
    public static function render_badge( $uid, $opts = [] ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return '';

        $info = Level_System::get_user_level( $uid );
        if ( $info['level'] <= 0 ) return '';

        $size      = $opts['size']      ?? 'sm';      // sm / md / lg
        $with_job  = $opts['with_job']  ?? true;
        $with_link = $opts['with_link'] ?? false;

        $tier_class = 'smacg-tier-' . $info['tier'];
        $size_class = 'smacg-badge-' . $size;
        $career     = $info['custom_career'] ?: $info['job_title'];

        $title = sprintf( 'Lv.%d %s', $info['level'], esc_attr( $career ) );

        ob_start();
        ?>
        <span class="smacg-level-badge <?php echo esc_attr( $tier_class . ' ' . $size_class ); ?>"
              title="<?php echo esc_attr( $title ); ?>">
            <span class="smacg-level-num">Lv.<?php echo (int) $info['level']; ?></span>
            <?php if ( $with_job ) : ?>
                <span class="smacg-level-job"><?php echo esc_html( $career ); ?></span>
            <?php endif; ?>
        </span>
        <?php
        $html = ob_get_clean();

        if ( $with_link && function_exists( 'smacg_get_public_profile_url' ) ) {
            $url = smacg_get_public_profile_url( $uid );
            if ( $url ) {
                $html = '<a href="' . esc_url( $url ) . '" class="smacg-level-badge-link">' . $html . '</a>';
            }
        }

        return $html;
    }

    /* ==========================================================
     * Hero 大徽章（公開個人頁用）
     * ========================================================== */
    public static function render_hero_badge( $uid ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return '';

        $info = Level_System::get_user_level( $uid );
        $career = $info['custom_career'] ?: $info['job_title'];
        $tier   = (int) $info['tier'];

        ob_start();
        ?>
        <div class="smacg-hero-badge smacg-hero-tier-<?php echo $tier; ?>">
            <div class="smacg-hero-level">
                <span class="smacg-hero-lv-label">Lv.</span>
                <span class="smacg-hero-lv-num"><?php echo (int) $info['level']; ?></span>
            </div>
            <div class="smacg-hero-job"><?php echo esc_html( $career ); ?></div>
            <div class="smacg-hero-progress">
                <div class="smacg-hero-progress-bar" style="width:<?php echo (int) $info['progress_pct']; ?>%"></div>
            </div>
            <div class="smacg-hero-exp">
                <?php
                if ( $info['is_max'] ) {
                    echo '滿級 (' . number_format( $info['exp'] ) . ' EXP)';
                } else {
                    printf(
                        '%s / %s EXP（距離下一級 %s）',
                        number_format( $info['exp'] ),
                        number_format( $info['exp_next_level'] ),
                        number_format( $info['exp_needed'] )
                    );
                }
                ?>
            </div>
            <?php
            $badge_count = (int) $info['badge_count'];
            if ( $badge_count > 0 ) :
                ?>
                <div class="smacg-hero-badges">
                    🏆 持有 <?php echo $badge_count; ?> 枚徽章
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ==========================================================
     * Hook: comment author append
     * ========================================================== */
    public static function append_to_comment_author( $author, $comment_id, $comment ) {
        if ( is_admin() ) return $author;
        if ( ! $comment instanceof \WP_Comment ) return $author;

        $uid = (int) $comment->user_id;
        if ( $uid <= 0 ) return $author;

        $badge = self::render_badge( $uid, [ 'size' => 'sm', 'with_job' => false ] );
        if ( ! $badge ) return $author;

        return $author . ' ' . $badge;
    }
}
