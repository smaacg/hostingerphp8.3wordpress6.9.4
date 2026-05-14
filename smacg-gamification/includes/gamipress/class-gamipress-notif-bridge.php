<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * GamiPress ↔ 通知中心 橋接（搬自 theme/inc/gamipress-notif-bridge.php）
 *
 * 依賴：smacg_create_notification()（仍在主題 notifications-system.php）
 */
class Gamipress_Notif_Bridge {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'smacg_level_milestone',     [ __CLASS__, 'on_milestone' ],   20, 4 );
        add_action( 'smacg_streak_milestone',    [ __CLASS__, 'on_streak' ],      20, 2 );
        add_action( 'gamipress_award_achievement', [ __CLASS__, 'on_badge' ],      5, 2 );

        // dev only：?smacg_test_levelup=1
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            add_action( 'admin_init', [ __CLASS__, 'test_levelup' ] );
        }
    }

    private static function milestone_label( $m ) {
        $labels = [
            10  => [ '⭐ 一轉達成',     '解鎖職業選擇！前往職業頁面選擇你的天命之路' ],
            30  => [ '⭐⭐ 二轉達成',   '你的職業進化了！稱號自動升級' ],
            70  => [ '⭐⭐⭐ 三轉達成', '你已是業界專家！稱號再次進化' ],
            120 => [ '⭐⭐⭐⭐ 四轉達成', '頂點！獲得閃爍稱號、金色邊框、名人堂徽章' ],
            200 => [ '🌟 滿級！',         '你已達到 Lv.200 滿級！傳說地位由此確立' ],
        ];
        return $labels[ $m ] ?? [ '里程碑達成', '' ];
    }

    public static function on_milestone( $user_id, $milestone, $from_level, $to_level ) {
        $user_id   = (int) $user_id;
        $milestone = (int) $milestone;
        if ( $user_id <= 0 || $milestone <= 0 ) return;
        if ( ! function_exists( 'smacg_create_notification' ) ) return;

        list( $title, $excerpt ) = self::milestone_label( $milestone );
        $url = home_url( '/mc/?tab=points' );

        smacg_create_notification( [
            'user_id'     => $user_id,
            'type'        => 'level_up',
            'actor_id'    => null,
            'object_type' => 'milestone',
            'object_id'   => $milestone,
            'data'        => [
                'title'     => sprintf( '%s（Lv.%d）', $title, $milestone ),
                'excerpt'   => $excerpt,
                'url'       => $url,
                'icon'      => 'fa-medal',
                'milestone' => $milestone,
            ],
            'force'       => true,
        ] );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[SMACG] Milestone Lv.%d notification sent to user #%d', $milestone, $user_id ) );
        }
    }

    public static function on_streak( $user_id, $days ) {
        $user_id = (int) $user_id;
        $days    = (int) $days;
        if ( $user_id <= 0 || $days <= 0 ) return;
        if ( ! function_exists( 'smacg_create_notification' ) ) return;

        $config = [
            7  => [ 'title' => '🔥 連續登入 7 天！', 'excerpt' => '熱愛是堅持的開始，已獲得 +100 EXP 獎勵', 'icon' => 'fa-fire' ],
            30 => [ 'title' => '🔥🔥 連續登入 30 天！', 'excerpt' => '一個月不間斷，已獲得 +500 EXP 獎勵，你是真正的常客', 'icon' => 'fa-fire-flame-curved' ],
        ];
        if ( ! isset( $config[ $days ] ) ) return;

        smacg_create_notification( [
            'user_id'     => $user_id,
            'type'        => 'system',
            'object_type' => 'streak',
            'object_id'   => $days,
            'data'        => [
                'title'   => $config[ $days ]['title'],
                'excerpt' => $config[ $days ]['excerpt'],
                'url'     => home_url( '/mc/?tab=points' ),
                'icon'    => $config[ $days ]['icon'],
                'days'    => $days,
            ],
            'force'       => true,
        ] );
    }

    public static function on_badge( $user_id, $achievement_id ) {
        $user_id        = (int) $user_id;
        $achievement_id = (int) $achievement_id;
        if ( $user_id <= 0 || $achievement_id <= 0 ) return;

        $achievement = get_post( $achievement_id );
        if ( ! $achievement ) return;
        if ( $achievement->post_type !== SMACG_BADGE_SLUG ) return;

        $custom_exp = (int) get_post_meta( $achievement_id, '_smacg_badge_exp', true );
        if ( $custom_exp <= 0 ) return;

        if ( ! function_exists( 'smacg_create_notification' ) ) return;

        $meta_key = 'smacg_notif_badge_enhanced_' . $achievement_id;
        if ( get_user_meta( $user_id, $meta_key, true ) ) return;
        update_user_meta( $user_id, $meta_key, 1 );

        $excerpt = wp_strip_all_tags( $achievement->post_excerpt ?: '' );
        $excerpt = trim( $excerpt . sprintf( ' (+%d EXP)', $custom_exp ) );

        smacg_create_notification( [
            'user_id'     => $user_id,
            'type'        => 'badge',
            'object_type' => 'badge',
            'object_id'   => $achievement_id,
            'data'        => [
                'title'   => sprintf( '🏆 獲得徽章「%s」', get_the_title( $achievement ) ),
                'excerpt' => $excerpt,
                'url'     => get_permalink( $achievement_id ) ?: home_url( '/mc/' ),
                'icon'    => 'fa-trophy',
                'exp'     => $custom_exp,
            ],
            'force'       => true,
        ] );
    }

    public static function test_levelup() {
        if ( ! isset( $_GET['smacg_test_levelup'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        $uid = get_current_user_id();
        do_action( 'smacg_level_up', $uid, 99, '測試稱號' );
        do_action( 'smacg_level_milestone', $uid, 10, 9, 10 );
        wp_die( '✅ 測試通知已送出，回 <a href="' . esc_url( home_url( '/mc/?tab=notifications' ) ) . '">通知中心</a> 查看' );
    }
}
