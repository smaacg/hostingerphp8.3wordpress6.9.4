<?php
/**
 * SMACG Members – User Privacy Settings
 *
 * 提供使用者隱私設定（公開個人頁、清單、Email、繼續觀看）的取得／更新 API，
 * 以及 Email 遮罩工具函式。
 *
 * 資料結構（user_meta，扁平、裸 key，與既有 member-ajax.php / public-profile.php 對齊）：
 *   public_profile           '1' | '0'
 *   public_watchlist         '1' | '0'
 *   show_email               '1' | '0'
 *   show_continue_watching   '1' | '0'
 *
 * 與既有程式對齊：
 *   - member-ajax.php 的 smacg_update_privacy AJAX：以裸 key 寫入 user_meta
 *   - public-profile.php 的 smacg_can_view_profile_section()：以裸 key 讀取
 *   - public-profile.php 的 user_register hook：以裸 key 寫入預設值
 *     （本檔保留同名 hook 作為冪等備援；既有 hook 不需移除）
 *   - page-public-profile.php 呼叫 smacg_get_user_privacy() 取得扁平陣列
 *   - page-member.php 呼叫 smacg_mask_email() 對非本人顯示遮罩 Email
 *
 * @package   SMACG\Members
 * @subpackage Privacy
 * @since     1.0.0
 * @version   1.0.0
 *
 * Changelog:
 *   1.0.0 (2026-05-16) 首版：補上先前散落各處呼叫但未定義的函式。
 *                      Meta key 使用裸 key（不加前綴）以對齊既有 AJAX 寫入位置。
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 預設值
 * ============================================================ */

if ( ! function_exists( 'smacg_get_user_privacy_defaults' ) ) {
	/**
	 * 取得隱私設定預設值。
	 *
	 * @since 1.0.0
	 * @return array<string,string>
	 */
	function smacg_get_user_privacy_defaults() {
		$defaults = [
			'public_profile'         => '1',
			'public_watchlist'       => '1',
			'show_email'             => '0',
			'show_continue_watching' => '0',
		];
		return apply_filters( 'smacg_user_privacy_defaults', $defaults );
	}
}

/* ============================================================
 * 合法 key 白名單
 * ============================================================ */

if ( ! function_exists( 'smacg_get_user_privacy_keys' ) ) {
	/**
	 * 取得所有合法隱私 key 的清單。
	 *
	 * @since 1.0.0
	 * @return string[]
	 */
	function smacg_get_user_privacy_keys() {
		return array_keys( smacg_get_user_privacy_defaults() );
	}
}

/* ============================================================
 * Getter
 * ============================================================ */

if ( ! function_exists( 'smacg_get_user_privacy' ) ) {
	/**
	 * 取得指定使用者的隱私設定。
	 *
	 * 未設定欄位會以預設值回填。
	 *
	 * @since 1.0.0
	 * @param int $user_id 使用者 ID。
	 * @return array<string,string>
	 */
	function smacg_get_user_privacy( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return smacg_get_user_privacy_defaults();
		}

		$defaults = smacg_get_user_privacy_defaults();
		$out      = [];

		foreach ( $defaults as $key => $default_val ) {
			$stored = get_user_meta( $user_id, $key, true );
			if ( $stored === '' || $stored === false || $stored === null ) {
				$out[ $key ] = $default_val;
			} else {
				$out[ $key ] = ( $stored === '1' || $stored === 1 || $stored === true ) ? '1' : '0';
			}
		}

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
	 * @since 1.0.0
	 * @param int   $user_id 使用者 ID。
	 * @param array $partial 部分更新內容。
	 * @return bool 任何欄位被寫入時回傳 true。
	 */
	function smacg_update_user_privacy( $user_id, $partial ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! is_array( $partial ) ) {
			return false;
		}

		$valid_keys = smacg_get_user_privacy_keys();
		$changed    = false;

		foreach ( $partial as $key => $val ) {
			if ( ! in_array( $key, $valid_keys, true ) ) {
				continue;
			}
			$norm   = ( $val === '1' || $val === 1 || $val === true ) ? '1' : '0';
			$result = update_user_meta( $user_id, $key, $norm );
			if ( $result !== false ) {
				$changed = true;
			}
		}

		if ( $changed ) {
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
	 * Email 遮罩：a***@e***.com 格式。
	 *
	 * @since 1.0.0
	 * @param string $email
	 * @return string 非合法 Email 回傳空字串。
	 */
	function smacg_mask_email( $email ) {
		$email = is_string( $email ) ? trim( $email ) : '';
		if ( $email === '' || ! is_email( $email ) ) {
			return '';
		}

		$parts = explode( '@', $email, 2 );
		if ( count( $parts ) !== 2 || $parts[0] === '' || $parts[1] === '' ) {
			return '';
		}

		$local  = $parts[0];
		$domain = $parts[1];

		$local_masked = mb_substr( $local, 0, 1, 'UTF-8' ) . '***';

		$dot_pos = strrpos( $domain, '.' );
		if ( $dot_pos === false || $dot_pos === 0 ) {
			$domain_masked = mb_substr( $domain, 0, 1, 'UTF-8' ) . '***';
		} else {
			$domain_body   = substr( $domain, 0, $dot_pos );
			$tld           = substr( $domain, $dot_pos ); // 含 dot
			$domain_masked = mb_substr( $domain_body, 0, 1, 'UTF-8' ) . '***' . $tld;
		}

		return $local_masked . '@' . $domain_masked;
	}
}

/* ============================================================
 * user_register hook（冪等備援）
 *
 * 既有 public-profile.php 已有 user_register hook 寫入相同的裸 key 預設值。
 * 本 hook 僅在欄位不存在時補寫，不會覆寫既有 hook 的結果。
 * 兩個 hook 同時存在不會造成衝突。
 * ============================================================ */

add_action( 'user_register', function ( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return;
	}

	$defaults = smacg_get_user_privacy_defaults();
	foreach ( $defaults as $key => $val ) {
		$existing = get_user_meta( $user_id, $key, true );
		if ( $existing === '' || $existing === false || $existing === null ) {
			update_user_meta( $user_id, $key, $val );
		}
	}
}, 20, 1 );
