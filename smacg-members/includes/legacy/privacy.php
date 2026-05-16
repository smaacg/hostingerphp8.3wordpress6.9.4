<?php
/**
 * SMACG Members – User Privacy Settings
 *
 * 提供使用者隱私設定（公開個人頁、清單、Email、繼續觀看）的取得／更新 API，
 * 以及 Email 遮罩工具函式。
 *
 * 資料結構（user_meta，扁平）：
 *   smacg_privacy_public_profile        '1' | '0'
 *   smacg_privacy_public_watchlist      '1' | '0'
 *   smacg_privacy_show_email            '1' | '0'
 *   smacg_privacy_show_continue_watching '1' | '0'
 *
 * 與既有程式對齊：
 *   - member-ajax.php 的 smacg_update_privacy 逐欄寫入上述 meta key
 *   - public-profile.php 的 smacg_can_view_profile_section() 讀取上述 meta key
 *   - page-public-profile.php 呼叫 smacg_get_user_privacy() 取得扁平陣列
 *   - page-member.php 呼叫 smacg_mask_email() 對非本人顯示遮罩 Email
 *
 * @package   SMACG\Members
 * @subpackage Privacy
 * @since     1.0.0
 * @version   1.0.0
 *
 * Changelog:
 *   1.0.0 (2026-05-16) 首版：補上先前散落各處呼叫但未定義的三支函式。
 *                      - smacg_get_user_privacy_defaults()
 *                      - smacg_get_user_privacy()
 *                      - smacg_update_user_privacy()
 *                      - smacg_mask_email()
 *                      - user_register hook：寫入預設隱私 meta
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 預設值
 * ============================================================ */

if ( ! function_exists( 'smacg_get_user_privacy_defaults' ) ) {
	/**
	 * 取得隱私設定預設值。
	 *
	 * 預設策略：個人頁與清單公開、Email 與繼續觀看隱藏。
	 * 可透過 filter `smacg_user_privacy_defaults` 調整。
	 *
	 * @since 1.0.0
	 * @return array<string,string> 扁平結構，值為 '1' | '0'。
	 */
	function smacg_get_user_privacy_defaults() {
		$defaults = [
			'public_profile'           => '1',
			'public_watchlist'         => '1',
			'show_email'               => '0',
			'show_continue_watching'   => '0',
		];
		/**
		 * Filter: smacg_user_privacy_defaults
		 *
		 * @param array $defaults 預設隱私設定。
		 */
		return apply_filters( 'smacg_user_privacy_defaults', $defaults );
	}
}

/* ============================================================
 * Getter
 * ============================================================ */

if ( ! function_exists( 'smacg_get_user_privacy' ) ) {
	/**
	 * 取得指定使用者的隱私設定（扁平陣列）。
	 *
	 * 未設定的欄位會以預設值回填，保證所有 key 都存在，呼叫端可直接讀取。
	 *
	 * @since 1.0.0
	 * @param int $user_id 使用者 ID。
	 * @return array<string,string> 扁平結構，值為 '1' | '0'。
	 */
	function smacg_get_user_privacy( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return smacg_get_user_privacy_defaults();
		}

		$defaults = smacg_get_user_privacy_defaults();
		$out      = [];

		foreach ( $defaults as $key => $default_val ) {
			$meta_key = 'smacg_privacy_' . $key;
			$stored   = get_user_meta( $user_id, $meta_key, true );
			if ( $stored === '' || $stored === false || $stored === null ) {
				$out[ $key ] = $default_val;
			} else {
				$out[ $key ] = ( $stored === '1' ) ? '1' : '0';
			}
		}

		/**
		 * Filter: smacg_user_privacy
		 *
		 * @param array $out     扁平隱私設定。
		 * @param int   $user_id 使用者 ID。
		 */
		return apply_filters( 'smacg_user_privacy', $out, $user_id );
	}
}

/* ============================================================
 * Setter
 * ============================================================ */

if ( ! function_exists( 'smacg_update_user_privacy' ) ) {
	/**
	 * 部分更新使用者隱私設定。
	 *
	 * 只更新 $partial 內存在且為合法 key 的欄位；其他欄位保留原值。
	 *
	 * @since 1.0.0
	 * @param int   $user_id 使用者 ID。
	 * @param array $partial 部分更新內容，key 對應預設 key，value 接受 '1'|'0'|1|0|true|false。
	 * @return bool true 表示有任何欄位被寫入，false 表示無變動或失敗。
	 */
	function smacg_update_user_privacy( $user_id, $partial ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! is_array( $partial ) ) {
			return false;
		}

		$defaults  = smacg_get_user_privacy_defaults();
		$changed   = false;

		foreach ( $partial as $key => $val ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			// 正規化為 '1' | '0' 字串
			$norm = ( $val === '1' || $val === 1 || $val === true ) ? '1' : '0';
			$meta_key = 'smacg_privacy_' . $key;
			$result   = update_user_meta( $user_id, $meta_key, $norm );
			if ( $result !== false ) {
				$changed = true;
			}
		}

		if ( $changed ) {
			/**
			 * Action: smacg_user_privacy_updated
			 *
			 * @param int   $user_id 使用者 ID。
			 * @param array $partial 提交的部分內容。
			 */
			do_action( 'smacg_user_privacy_updated', $user_id, $partial );
		}

		return $changed;
	}
}

/* ============================================================
 * Email 遮罩
 * ============================================================ */

if ( ! function_exists( 'smacg_mask_email' ) ) {
	/**
	 * 將 Email 地址遮罩為 a***@e***.com 格式。
	 *
	 * 規則：
	 *   - 本地部分（@ 前）：保留第 1 個字元，其餘以 *** 取代。
	 *   - 網域主體（最後一個 . 前）：保留第 1 個字元，其餘以 *** 取代。
	 *   - TLD（最後一個 . 後）保留原樣。
	 *   - 非合法 Email 直接回傳空字串。
	 *
	 * @since 1.0.0
	 * @param string $email 原始 Email。
	 * @return string 遮罩後字串。
	 */
	function smacg_mask_email( $email ) {
		$email = is_string( $email ) ? trim( $email ) : '';
		if ( $email === '' || ! is_email( $email ) ) {
			return '';
		}

		$parts = explode( '@', $email, 2 );
		if ( count( $parts ) !== 2 ) {
			return '';
		}

		$local  = $parts[0];
		$domain = $parts[1];

		// 本地部分
		$local_masked = mb_substr( $local, 0, 1, 'UTF-8' ) . '***';

		// 網域：拆出 TLD
		$dot_pos = strrpos( $domain, '.' );
		if ( $dot_pos === false ) {
			$domain_masked = mb_substr( $domain, 0, 1, 'UTF-8' ) . '***';
		} else {
			$domain_body = substr( $domain, 0, $dot_pos );
			$tld         = substr( $domain, $dot_pos ); // 含 dot
			$domain_masked = mb_substr( $domain_body, 0, 1, 'UTF-8' ) . '***' . $tld;
		}

		return $local_masked . '@' . $domain_masked;
	}
}

/* ============================================================
 * user_register hook：寫入預設隱私 meta
 * ============================================================ */

add_action( 'user_register', function ( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return;
	}

	$defaults = smacg_get_user_privacy_defaults();
	foreach ( $defaults as $key => $val ) {
		$meta_key = 'smacg_privacy_' . $key;
		// 僅在未存在時寫入，避免覆寫其他外掛預先寫入的偏好。
		$existing = get_user_meta( $user_id, $meta_key, true );
		if ( $existing === '' || $existing === false || $existing === null ) {
			update_user_meta( $user_id, $meta_key, $val );
		}
	}
}, 10, 1 );
