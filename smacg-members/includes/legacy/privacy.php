<?php
/**
 * SMACG Members - Privacy API (Legacy)
 *
 * 公開個人頁與會員中心隱私偏好的讀寫 API。
 * 採 flat meta key（與 member-ajax.php 對齊），並對舊版 array meta（smacg_privacy）做一次性遷移。
 *
 * Flat meta keys（user_meta）:
 *   - public_profile           (1|0) 是否公開個人頁
 *   - public_watchlist         (1|0) 是否公開觀看清單
 *   - show_email               (1|0) 是否顯示 Email
 *   - show_continue_watching   (1|0) 是否顯示「繼續觀看」橫向列
 *
 * Legacy meta key（一次性遷移後保留作為 backup）:
 *   - smacg_privacy            (array) 舊版本格式
 *
 * @package    weixiaoacg
 * @subpackage smacg-members
 * @version    1.0.0
 * @since      1.0.0
 *
 * Changelog:
 * - 1.0.0 (2026-05-16)
 *   * 初始版本：定義 flat-key 預設值、getter、setter、email 遮罩、user_register 鉤子。
 *   * 提供 smacg_privacy_migrate_legacy() 將舊版 array meta 遷移為 flat key。
 *   * 預設值決議：show_continue_watching = '1'（顯示），與 member-render.php fallback 對齊。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ========================================================================
 * 預設值 & 白名單
 * ====================================================================== */

if ( ! function_exists( 'smacg_get_user_privacy_defaults' ) ) :
/**
 * 取得隱私偏好預設值（flat structure，值為字串 '1' / '0' 以匹配 user_meta 儲存格式）。
 *
 * @return array
 */
function smacg_get_user_privacy_defaults() {
    return [
        'public_profile'         => '1',
        'public_watchlist'       => '1',
        'show_email'             => '0',
        'show_continue_watching' => '1',
    ];
}
endif;

if ( ! function_exists( 'smacg_get_user_privacy_keys' ) ) :
/**
 * 取得所有合法的隱私 meta key（白名單）。
 *
 * @return string[]
 */
function smacg_get_user_privacy_keys() {
    return array_keys( smacg_get_user_privacy_defaults() );
}
endif;

/* ========================================================================
 * 舊版 array meta 遷移
 * ====================================================================== */

if ( ! function_exists( 'smacg_privacy_migrate_legacy' ) ) :
/**
 * 若使用者有舊版 'smacg_privacy' array meta 且尚未遷移，則寫入 flat keys。
 * 遷移後不刪除舊 meta（作為 backup），但會寫入 'smacg_privacy_migrated' = '1' 避免重複。
 *
 * @param int $uid
 * @return bool 是否實際進行了遷移
 */
function smacg_privacy_migrate_legacy( $uid ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) return false;

    // 已遷移過則不再處理
    if ( get_user_meta( $uid, 'smacg_privacy_migrated', true ) === '1' ) {
        return false;
    }

    $legacy = get_user_meta( $uid, 'smacg_privacy', true );
    if ( ! is_array( $legacy ) || empty( $legacy ) ) {
        return false;
    }

    $keys = smacg_get_user_privacy_keys();
    foreach ( $keys as $k ) {
        // 僅在 flat key 不存在時才寫入，避免覆蓋使用者已調整的值
        $existing = get_user_meta( $uid, $k, true );
        if ( $existing === '' ) {
            $val = isset( $legacy[ $k ] ) ? ( $legacy[ $k ] ? '1' : '0' ) : null;
            if ( $val !== null ) {
                update_user_meta( $uid, $k, $val );
            }
        }
    }

    update_user_meta( $uid, 'smacg_privacy_migrated', '1' );
    return true;
}
endif;

/* ========================================================================
 * Getter / Setter
 * ====================================================================== */

if ( ! function_exists( 'smacg_get_user_privacy' ) ) :
/**
 * 取得使用者完整隱私偏好（flat structure）。
 *
 * 讀取順序：
 *   1. 觸發 legacy migration（若需要）
 *   2. 逐 key 讀取 flat meta；缺失則使用 defaults
 *
 * @param int $uid
 * @return array flat array of '1'/'0' strings, keyed by privacy keys
 */
function smacg_get_user_privacy( $uid ) {
    $uid = (int) $uid;
    $defaults = smacg_get_user_privacy_defaults();
    if ( $uid <= 0 ) return $defaults;

    // 嘗試遷移舊版 array meta
    smacg_privacy_migrate_legacy( $uid );

    $out = [];
    foreach ( $defaults as $key => $default_val ) {
        $val = get_user_meta( $uid, $key, true );
        if ( $val === '' || $val === false || $val === null ) {
            $out[ $key ] = $default_val;
        } else {
            // 正規化為 '1' / '0'
            $out[ $key ] = ( (string) $val === '1' || (int) $val === 1 || $val === true ) ? '1' : '0';
        }
    }
    return $out;
}
endif;

if ( ! function_exists( 'smacg_update_user_privacy' ) ) :
/**
 * 部分更新使用者隱私偏好（僅更新傳入的 key，其餘維持）。
 *
 * @param int   $uid
 * @param array $partial 例如 ['public_profile' => 1, 'show_email' => 0]
 * @return bool|WP_Error true 成功，WP_Error 失敗
 */
function smacg_update_user_privacy( $uid, $partial ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) {
        return new WP_Error( 'invalid_user', 'Invalid user id.' );
    }
    if ( ! is_array( $partial ) || empty( $partial ) ) {
        return new WP_Error( 'invalid_data', 'Partial data must be a non-empty array.' );
    }

    $allowed = smacg_get_user_privacy_keys();
    foreach ( $partial as $key => $val ) {
        if ( ! in_array( $key, $allowed, true ) ) continue;
        $normalized = ( (string) $val === '1' || (int) $val === 1 || $val === true ) ? '1' : '0';
        update_user_meta( $uid, $key, $normalized );
    }
    return true;
}
endif;

/* ========================================================================
 * Email 遮罩
 * ====================================================================== */

if ( ! function_exists( 'smacg_mask_email' ) ) :
/**
 * 將 email 遮罩成 a***@e***.com 形式。
 *
 * @param string $email
 * @return string
 */
function smacg_mask_email( $email ) {
    $email = (string) $email;
    if ( ! is_email( $email ) ) return '';

    $parts = explode( '@', $email );
    if ( count( $parts ) !== 2 ) return '';

    list( $local, $domain ) = $parts;
    $domain_parts = explode( '.', $domain );
    $tld          = array_pop( $domain_parts );
    $domain_main  = implode( '.', $domain_parts );

    $mask_local  = mb_substr( $local, 0, 1 ) . '***';
    $mask_domain = mb_substr( $domain_main, 0, 1 ) . '***';

    return $mask_local . '@' . $mask_domain . '.' . $tld;
}
endif;

/* ========================================================================
 * user_register 鉤子：寫入預設值
 * ====================================================================== */

if ( ! function_exists( 'smacg_privacy_set_defaults_on_register' ) ) :
/**
 * 新使用者註冊時寫入隱私預設值（僅在 meta 不存在時寫入，冪等）。
 *
 * @param int $uid
 */
function smacg_privacy_set_defaults_on_register( $uid ) {
    $uid = (int) $uid;
    if ( $uid <= 0 ) return;

    $defaults = smacg_get_user_privacy_defaults();
    foreach ( $defaults as $key => $val ) {
        $existing = get_user_meta( $uid, $key, true );
        if ( $existing === '' ) {
            update_user_meta( $uid, $key, $val );
        }
    }

    // 註冊即標記為已遷移（無 legacy 資料需處理）
    update_user_meta( $uid, 'smacg_privacy_migrated', '1' );
}
add_action( 'user_register', 'smacg_privacy_set_defaults_on_register', 20 );
endif;
