<?php
/**
 * EXP 規則設定（集中管理）
 *
 * 原檔：blocksy-child/inc/exp-config.php v1.0.0
 *
 * @package SMACG_Gamification
 */

namespace SMACG\Gamification\Exp;

defined( 'ABSPATH' ) || exit;

class Config {

    /**
     * 取得 EXP 發放規則表
     *
     * @return array
     */
    public static function rules() {
        $rules = [

            // === 帳號類（一生一次） ===
            'register' => [
                'exp'       => 50,
                'daily_cap' => -1,
                'label'     => '註冊獎勵',
            ],

            // === 每日活躍 ===
            'daily_login' => [
                'exp'       => 10,
                'daily_cap' => 1,
                'label'     => '每日首次登入',
            ],

            // === 觀看相關 ===
            'watchlist_completed' => [
                'exp'       => 30,
                'daily_cap' => 10,
                'label'     => '完成觀看作品',
            ],
            'watchlist_added' => [
                'exp'       => 5,
                'daily_cap' => 20,
                'label'     => '加入追蹤清單',
            ],

            // === 評分 ===
            'rating_added' => [
                'exp'       => 15,
                'daily_cap' => 20,
                'label'     => '評分作品',
            ],

            // === 留言 ===
            'comment_posted' => [
                'exp'       => 5,
                'daily_cap' => 10,
                'label'     => '發表留言',
            ],
            'comment_liked' => [
                'exp'       => 3,
                'daily_cap' => 10,
                'label'     => '留言被按讚',
            ],

            // === 追蹤 ===
            'follow_action' => [
                'exp'       => 2,
                'daily_cap' => 10,
                'label'     => '追蹤其他用戶',
            ],
            'gained_follower' => [
                'exp'       => 5,
                'daily_cap' => 10,
                'label'     => '獲得新追蹤者',
            ],

            // === 連續登入 ===
            'streak_7' => [
                'exp'       => 100,
                'daily_cap' => 1,
                'label'     => '連續登入 7 天',
            ],
            'streak_30' => [
                'exp'       => 500,
                'daily_cap' => 1,
                'label'     => '連續登入 30 天',
            ],

            // === 徽章解鎖 ===
            'badge_unlocked' => [
                'exp'       => 20,
                'daily_cap' => 0,
                'label'     => '解鎖徽章',
            ],
        ];

        /** filter 名稱不變，保持外部相容 */
        return apply_filters( 'smacg_exp_rules', $rules );
    }

    public static function get_exp( $action_key ) {
        $rules = self::rules();
        return isset( $rules[ $action_key ]['exp'] ) ? (int) $rules[ $action_key ]['exp'] : 0;
    }

    public static function get_cap( $action_key ) {
        $rules = self::rules();
        return isset( $rules[ $action_key ]['daily_cap'] ) ? (int) $rules[ $action_key ]['daily_cap'] : 0;
    }

    public static function get_label( $action_key ) {
        $rules = self::rules();
        return isset( $rules[ $action_key ]['label'] ) ? (string) $rules[ $action_key ]['label'] : $action_key;
    }
}
