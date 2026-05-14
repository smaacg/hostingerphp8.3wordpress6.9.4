<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * EXP 規則表（搬自 theme/inc/exp-config.php）
 *
 * 規則欄位：
 *   exp      - 給予的 EXP 點數
 *   cap_type - 'once' | 'daily' | null（無上限）
 *   cap_key  - user_meta key 字首（once_/daily_ 自動加）
 *
 * 透過 filter 'smacg_exp_rules' 可被擴充。
 */
class Exp_Config {

    private static $rules = null;

    public static function rules() {
        if ( self::$rules !== null ) return self::$rules;

        self::$rules = [
            /* 註冊 / 登入 / 互動 */
            'register'        => [ 'exp' => 100, 'cap_type' => 'once',  'cap_key' => 'register' ],
            'daily_login'     => [ 'exp' => 10,  'cap_type' => 'daily', 'cap_key' => 'login' ],
            'streak_7'        => [ 'exp' => 100, 'cap_type' => 'once',  'cap_key' => 'streak_7' ],
            'streak_30'       => [ 'exp' => 500, 'cap_type' => 'once',  'cap_key' => 'streak_30' ],

            'comment_post'    => [ 'exp' => 5,   'cap_type' => 'daily', 'cap_key' => 'comment' ],

            /* 追蹤系統 */
            'follow_action'   => [ 'exp' => 2,   'cap_type' => 'daily', 'cap_key' => 'follow' ],
            'followed_by'     => [ 'exp' => 5,   'cap_type' => 'daily', 'cap_key' => 'followed' ],

            /* anime-sync-pro 觀看記錄 */
            'watchlist_add'      => [ 'exp' => 1,  'cap_type' => 'daily', 'cap_key' => 'wl_add' ],
            'watchlist_complete' => [ 'exp' => 8,  'cap_type' => null,    'cap_key' => null ],
            'rating_add'         => [ 'exp' => 3,  'cap_type' => 'daily', 'cap_key' => 'rating' ],

            /* GamiPress 徽章解鎖（預設值；個別徽章可用 _smacg_badge_exp 覆蓋） */
            'badge_unlock'    => [ 'exp' => 20,  'cap_type' => null,    'cap_key' => null ],
        ];

        self::$rules = apply_filters( 'smacg_exp_rules', self::$rules );
        return self::$rules;
    }

    public static function get( $action_key ) {
        $rules = self::rules();
        return $rules[ $action_key ] ?? null;
    }
}
