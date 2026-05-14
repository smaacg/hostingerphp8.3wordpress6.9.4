<?php
/**
 * Leaderboard Widget + Shortcode — Top 10 排行榜
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-14)  Batch 2B-3
 *
 * 提供：
 *   1) WP_Widget「微笑動漫 - 會員排行榜 Top N」
 *      Appearance → Widgets → 拖到 sidebar-1 / footer-1
 *
 *   2) Shortcode [smacg_leaderboard]
 *      參數：
 *        type    = exp_total | exp_monthly | followers | badges  （預設 exp_total）
 *        limit   = 1..20      （預設 10）
 *        title   = 自訂標題    （預設依 type 自動）
 *        compact = 0 | 1      （預設 0；1=極簡 list）
 *        show_more = 0 | 1    （預設 1；顯示「查看完整排行」連結）
 *      範例：
 *        [smacg_leaderboard type="exp_monthly" limit="5"]
 *        [smacg_leaderboard type="badges" compact="1" show_more="0"]
 *
 *   3) Helper smacg_render_leaderboard_top( $type, $limit, $args )
 *      可直接在 PHP 模板呼叫
 *
 * 資料來源：wp_smacg_rankings（由 Cron 每小時更新）
 * 視覺：玻璃擬態，與既有 .glass-mid / .ranku-* 對齊
 *
 * 樣式檔：assets/css/leaderboard-widget.css（由 setup-enqueue.php 全站載入）
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   核心：渲染 Top N（共用給 widget + shortcode）
   ============================================================ */

/**
 * 取 Top N 列表並渲染 HTML
 *
 * @param string $type     ranking type
 * @param int    $limit    1..20
 * @param array  $args     [
 *   'title'      => string (空字串 = 不顯示標題)
 *   'compact'    => bool
 *   'show_more'  => bool
 *   'class'      => string  外層 wrapper 附加 class
 * ]
 * @return string HTML
 */
function smacg_render_leaderboard_top( $type = 'exp_total', $limit = 10, $args = [] ) {

    $valid = [ 'exp_total', 'exp_monthly', 'followers', 'badges' ];
    if ( ! in_array( $type, $valid, true ) ) $type = 'exp_total';
    $limit = max( 1, min( 20, (int) $limit ) );

    $args = wp_parse_args( $args, [
        'title'     => '',
        'compact'   => false,
        'show_more' => true,
        'class'     => '',
    ] );

    // 預設標題
    if ( $args['title'] === '' ) {
        $args['title'] = smacg_lb_default_title( $type );
    }

    if ( ! function_exists( 'smacg_ranking_get' ) ) {
        return '<div class="smacg-lb smacg-lb--err">排行榜系統未啟用</div>';
    }

    $data = smacg_ranking_get( $type, 1, $limit );
    $rows = $data['rows'] ?? [];

    ob_start();
    ?>
    <div class="smacg-lb <?php echo $args['compact'] ? 'smacg-lb--compact' : ''; ?> <?php echo esc_attr( $args['class'] ); ?>" data-type="<?php echo esc_attr( $type ); ?>">

        <?php if ( ! empty( $args['title'] ) ) : ?>
            <h3 class="smacg-lb__title">
                <?php echo smacg_lb_type_icon( $type ); ?>
                <?php echo esc_html( $args['title'] ); ?>
            </h3>
        <?php endif; ?>

        <?php if ( empty( $rows ) ) : ?>
            <p class="smacg-lb__empty">
                <i class="fa-solid fa-hourglass-half"></i>
                尚無資料，每小時更新
            </p>
        <?php else : ?>
            <ol class="smacg-lb__list">
                <?php foreach ( $rows as $r ) :
                    $uid = (int) $r['user_id'];
                    $u   = get_user_by( 'id', $uid );
                    if ( ! $u ) continue;

                    $pos     = (int) $r['rank_pos'];
                    $score   = (int) $r['score'];
                    $display = $u->display_name ?: $u->user_login;

                    $level_info = function_exists( 'smacg_get_user_level_info' )
                        ? smacg_get_user_level_info( $uid )
                        : [ 'level' => 0 ];
                    $level = (int) ( $level_info['level'] ?? 0 );

                    $profile_url = function_exists( 'smacg_get_public_profile_url' )
                        ? smacg_get_public_profile_url( $u->user_login )
                        : '#';

                    $medal = '';
                    if ( $pos === 1 ) $medal = '🥇';
                    elseif ( $pos === 2 ) $medal = '🥈';
                    elseif ( $pos === 3 ) $medal = '🥉';
                ?>
                <li class="smacg-lb__item smacg-lb__item--<?php echo $pos; ?>">
                    <span class="smacg-lb__pos">
                        <?php echo $medal ? '<span class="smacg-lb__medal">' . $medal . '</span>' : '#' . $pos; ?>
                    </span>
                    <a class="smacg-lb__avatar" href="<?php echo esc_url( $profile_url ); ?>" aria-label="<?php echo esc_attr( $display ); ?>">
                        <img src="<?php echo esc_url( get_avatar_url( $uid, [ 'size' => 64 ] ) ); ?>" alt="" loading="lazy">
                    </a>
                    <div class="smacg-lb__info">
                        <a class="smacg-lb__name" href="<?php echo esc_url( $profile_url ); ?>">
                            <?php echo esc_html( $display ); ?>
                        </a>
                        <?php if ( ! $args['compact'] ) : ?>
                        <span class="smacg-lb__lv">Lv.<?php echo $level; ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="smacg-lb__score">
                        <?php echo number_format( $score ); ?>
                        <small><?php echo esc_html( smacg_lb_score_unit( $type ) ); ?></small>
                    </span>
                </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ( $args['show_more'] ) :
            $more_url = home_url( '/ranking-users/?tab=' . $type );
        ?>
            <a class="smacg-lb__more" href="<?php echo esc_url( $more_url ); ?>">
                查看完整排行 <i class="fa-solid fa-arrow-right"></i>
            </a>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}

/**
 * type 對應的預設標題
 */
function smacg_lb_default_title( $type ) {
    switch ( $type ) {
        case 'exp_monthly': return '本月活躍榜 Top';
        case 'followers':   return '人氣會員 Top';
        case 'badges':      return '徽章獵人 Top';
        case 'exp_total':
        default:            return '等級排行 Top';
    }
}

/**
 * type 對應的圖示（emoji + i）
 */
function smacg_lb_type_icon( $type ) {
    switch ( $type ) {
        case 'exp_monthly': return '<i class="fa-solid fa-fire" style="color:#f59e0b;"></i>';
        case 'followers':   return '<i class="fa-solid fa-user-group" style="color:#02a9ff;"></i>';
        case 'badges':      return '<i class="fa-solid fa-medal" style="color:#fbbf24;"></i>';
        case 'exp_total':
        default:            return '<i class="fa-solid fa-bolt" style="color:#a78bfa;"></i>';
    }
}

/**
 * type 對應的單位
 */
function smacg_lb_score_unit( $type ) {
    switch ( $type ) {
        case 'followers': return '粉絲';
        case 'badges':    return '枚';
        case 'exp_total':
        case 'exp_monthly':
        default:          return 'EXP';
    }
}

/* ============================================================
   Shortcode [smacg_leaderboard]
   ============================================================ */
add_shortcode( 'smacg_leaderboard', function ( $atts ) {
    $atts = shortcode_atts( [
        'type'      => 'exp_total',
        'limit'     => 10,
        'title'     => '',
        'compact'   => 0,
        'show_more' => 1,
        'class'     => '',
    ], $atts, 'smacg_leaderboard' );

    return smacg_render_leaderboard_top(
        sanitize_key( $atts['type'] ),
        (int) $atts['limit'],
        [
            'title'     => sanitize_text_field( $atts['title'] ),
            'compact'   => (bool) (int) $atts['compact'],
            'show_more' => (bool) (int) $atts['show_more'],
            'class'     => sanitize_html_class( $atts['class'] ),
        ]
    );
} );

/* ============================================================
   Widget：微笑動漫 - 會員排行榜
   ============================================================ */
class SMACG_Leaderboard_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'smacg_leaderboard',
            '微笑動漫 - 會員排行榜',
            [
                'description' => 'Top N 會員排行榜（累計 EXP / 本月 / 粉絲 / 徽章）',
                'classname'   => 'widget_smacg_leaderboard',
            ]
        );
    }

    public function widget( $args, $instance ) {
        $type      = $instance['type']      ?? 'exp_total';
        $limit     = (int) ( $instance['limit'] ?? 10 );
        $title     = $instance['title']     ?? '';
        $compact   = ! empty( $instance['compact'] );
        $show_more = ! isset( $instance['show_more'] ) || ! empty( $instance['show_more'] );

        echo $args['before_widget'];

        // 不用 $args['before_title']，因為 Widget HTML 由 render 函式自己出
        echo smacg_render_leaderboard_top( $type, $limit, [
            'title'     => $title,
            'compact'   => $compact,
            'show_more' => $show_more,
        ] );

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $type      = $instance['type']      ?? 'exp_total';
        $limit     = (int) ( $instance['limit'] ?? 10 );
        $title     = $instance['title']     ?? '';
        $compact   = ! empty( $instance['compact'] );
        $show_more = ! isset( $instance['show_more'] ) || ! empty( $instance['show_more'] );

        $opts = [
            'exp_total'   => '累計 EXP',
            'exp_monthly' => '本月 EXP',
            'followers'   => '粉絲數',
            'badges'      => '徽章數',
        ];
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">標題（留空使用預設）：</label>
            <input class="widefat" type="text"
                   id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>">類別：</label>
            <select class="widefat"
                    id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>">
                <?php foreach ( $opts as $k => $v ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>>
                        <?php echo esc_html( $v ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>">顯示筆數 (1-20)：</label>
            <input class="tiny-text" type="number" min="1" max="20" step="1"
                   id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>"
                   value="<?php echo esc_attr( $limit ); ?>">
        </p>
        <p>
            <input type="checkbox" class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'compact' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'compact' ) ); ?>"
                   <?php checked( $compact ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'compact' ) ); ?>">極簡模式（不顯示等級）</label>
        </p>
        <p>
            <input type="checkbox" class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_more' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_more' ) ); ?>"
                   <?php checked( $show_more ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_more' ) ); ?>">顯示「查看完整排行」連結</label>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        return [
            'title'     => sanitize_text_field( $new_instance['title'] ?? '' ),
            'type'      => sanitize_key( $new_instance['type'] ?? 'exp_total' ),
            'limit'     => max( 1, min( 20, (int) ( $new_instance['limit'] ?? 10 ) ) ),
            'compact'   => ! empty( $new_instance['compact'] ),
            'show_more' => ! empty( $new_instance['show_more'] ),
        ];
    }
}

add_action( 'widgets_init', function () {
    register_widget( 'SMACG_Leaderboard_Widget' );
} );
