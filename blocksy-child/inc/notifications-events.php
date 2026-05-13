<?php
/**
 * Notifications System — 事件監聽（Event Producers）
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-13)
 *
 * 監聽各種站內事件，自動產生通知：
 *   - smacg_user_followed       → 'follow' 通知
 *   - wp_insert_comment         → 'comment_reply' 通知（被回覆者）
 *   - smacg_anime_rated         → 'rating' 通知（給該動畫的收藏者）
 *   - smacg_level_up            → 'level_up' 通知
 *   - gamipress_award_achievement → 'badge' 通知
 *
 * 所有通知透過 smacg_create_notification() 寫入，
 * 該函式內部會檢查使用者通知偏好（site channel）。
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   #1 追蹤事件 → follow 通知
   ============================================================ */
add_action( 'smacg_user_followed', function( $follower_id, $following_id ) {

	$follower = get_userdata( $follower_id );
	if ( ! $follower ) return;

	$follower_name = $follower->display_name ?: $follower->user_login;
	$profile_url   = function_exists( 'smacg_get_public_profile_url' )
		? smacg_get_public_profile_url( $follower_id )
		: home_url( '/' );

	smacg_create_notification( [
		'user_id'     => $following_id,
		'type'        => 'follow',
		'actor_id'    => $follower_id,
		'object_type' => 'user',
		'object_id'   => $follower_id,
		'data'        => [
			'title'   => sprintf( '%s 開始追蹤你', $follower_name ),
			'excerpt' => '',
			'url'     => $profile_url,
			'icon'    => 'fa-user-plus',
		],
	] );
}, 10, 2 );

/* ============================================================
   #2 留言被回覆 → comment_reply 通知
   ------------------------------------------------------------
   觸發點：wp_insert_comment（wpDiscuz 與原生留言都會走這裡）
   條件：comment_parent > 0 且 父留言作者 ≠ 新留言作者
   ============================================================ */
add_action( 'wp_insert_comment', function( $comment_id, $comment ) {

	// 必須為已核可的留言才通知（避免 spam 噪音）
	if ( (int) $comment->comment_approved !== 1 ) return;

	$parent_id = (int) $comment->comment_parent;
	if ( ! $parent_id ) return;

	$parent = get_comment( $parent_id );
	if ( ! $parent ) return;

	// 父留言的作者
	$parent_uid = (int) $parent->user_id;
	if ( ! $parent_uid ) return;  // 訪客留言無法通知

	// 新留言的作者
	$author_uid = (int) $comment->user_id;
	if ( ! $author_uid || $author_uid === $parent_uid ) return;  // 自己回自己不通知

	$author = get_userdata( $author_uid );
	if ( ! $author ) return;
	$author_name = $author->display_name ?: $author->user_login;

	$post = get_post( $comment->comment_post_ID );
	if ( ! $post ) return;

	$excerpt = wp_strip_all_tags( $comment->comment_content );
	if ( mb_strlen( $excerpt ) > 80 ) {
		$excerpt = mb_substr( $excerpt, 0, 80 ) . '…';
	}

	smacg_create_notification( [
		'user_id'     => $parent_uid,
		'type'        => 'comment_reply',
		'actor_id'    => $author_uid,
		'object_type' => 'comment',
		'object_id'   => (int) $comment_id,
		'data'        => [
			'title'   => sprintf( '%s 回覆了你的留言', $author_name ),
			'excerpt' => $excerpt,
			'url'     => get_comment_link( $comment_id ),
			'icon'    => 'fa-reply',
			'post_title' => get_the_title( $post ),
		],
	] );
}, 10, 2 );

/* ============================================================
   #3 動畫被評分 → rating 通知（給該動畫的收藏者）
   ------------------------------------------------------------
   觸發點：smacg_anime_rated（你的評分系統需要 do_action 觸發；
   若目前還沒觸發，這段會靜靜等待，不會出錯）
   參數：( $anime_id, $rater_id, $overall_score )
   ============================================================ */
add_action( 'smacg_anime_rated', function( $anime_id, $rater_id, $overall_score = null ) {
	global $wpdb;

	$anime_id = absint( $anime_id );
	$rater_id = absint( $rater_id );
	if ( ! $anime_id || ! $rater_id ) return;

	$rater = get_userdata( $rater_id );
	if ( ! $rater ) return;
	$rater_name = $rater->display_name ?: $rater->user_login;

	// 找出此動畫的收藏者（status = favorited 或 favorited = 1）
	// 從 wp_smacg_watchlist 表（你的清單表）
	$watchlist_table = $wpdb->prefix . 'smacg_watchlist';

	// 先檢查表是否存在
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $watchlist_table ) ) !== $watchlist_table ) {
		return;  // 表不存在就跳過
	}

	$collector_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT user_id FROM {$watchlist_table}
		 WHERE post_id = %d AND (status = 'favorited' OR favorited = 1)
		 LIMIT 200",
		$anime_id
	) );

	if ( empty( $collector_ids ) ) return;

	$title    = get_the_title( $anime_id );
	$url      = get_permalink( $anime_id );
	$score_str = is_numeric( $overall_score ) ? sprintf( ' %.1f 分', (float) $overall_score ) : '';

	foreach ( $collector_ids as $uid ) {
		$uid = (int) $uid;
		if ( $uid === $rater_id ) continue;  // 不通知評分者本人

		smacg_create_notification( [
			'user_id'     => $uid,
			'type'        => 'rating',
			'actor_id'    => $rater_id,
			'object_type' => 'anime',
			'object_id'   => $anime_id,
			'data'        => [
				'title'   => sprintf( '%s 評分了你收藏的《%s》%s', $rater_name, $title, $score_str ),
				'excerpt' => '',
				'url'     => $url,
				'icon'    => 'fa-star',
			],
		] );
	}
}, 10, 3 );

/* ============================================================
   #4 等級提升 → level_up 通知
   ------------------------------------------------------------
   觸發點：smacg_level_up（你的等級系統需要 do_action）
   參數：( $user_id, $new_level, $title )
   ============================================================ */
add_action( 'smacg_level_up', function( $user_id, $new_level, $level_title = '' ) {
	$user_id   = absint( $user_id );
	$new_level = absint( $new_level );
	if ( ! $user_id || ! $new_level ) return;

	smacg_create_notification( [
		'user_id'     => $user_id,
		'type'        => 'level_up',
		'actor_id'    => null,
		'object_type' => 'level',
		'object_id'   => $new_level,
		'data'        => [
			'title'   => sprintf( '恭喜升上 Lv.%d %s', $new_level, $level_title ),
			'excerpt' => '繼續觀看、評分、留言可以累積更多點數！',
			'url'     => home_url( '/mc/?tab=points' ),
			'icon'    => 'fa-arrow-up',
		],
	] );
}, 10, 3 );

/* ============================================================
   #5 GamiPress 解鎖徽章 → badge 通知
   ------------------------------------------------------------
   GamiPress hook: gamipress_award_achievement
   參數：( $user_id, $achievement_id, $trigger, $site_id, $args )
   若未裝 GamiPress，hook 永遠不會觸發，無副作用
   ============================================================ */
add_action( 'gamipress_award_achievement', function( $user_id, $achievement_id ) {
	$user_id        = absint( $user_id );
	$achievement_id = absint( $achievement_id );
	if ( ! $user_id || ! $achievement_id ) return;

	$achievement = get_post( $achievement_id );
	if ( ! $achievement ) return;

	smacg_create_notification( [
		'user_id'     => $user_id,
		'type'        => 'badge',
		'actor_id'    => null,
		'object_type' => 'badge',
		'object_id'   => $achievement_id,
		'data'        => [
			'title'   => sprintf( '🏆 獲得徽章「%s」', get_the_title( $achievement ) ),
			'excerpt' => wp_strip_all_tags( $achievement->post_excerpt ?: '' ),
			'url'     => get_permalink( $achievement_id ) ?: home_url( '/mc/' ),
			'icon'    => 'fa-trophy',
		],
	] );
}, 10, 2 );

/* ============================================================
   #6 系統公告 helper（供管理員使用）
   ------------------------------------------------------------
   用法（在你的後台或外掛中呼叫）：
   smacg_broadcast_system_notification( '網站維護通知', '5/15 將進行維護', home_url('/news/maintenance/') );
   ============================================================ */
function smacg_broadcast_system_notification( $title, $excerpt = '', $url = '', $user_ids = null ) {
	$title = wp_strip_all_tags( $title );
	if ( ! $title ) return 0;

	// 預設：所有訂閱級別以上的使用者
	if ( $user_ids === null ) {
		$user_ids = get_users( [
			'fields' => 'ID',
			'number' => -1,
		] );
	}
	if ( empty( $user_ids ) ) return 0;

	$sent = 0;
	foreach ( $user_ids as $uid ) {
		$r = smacg_create_notification( [
			'user_id' => (int) $uid,
			'type'    => 'system',
			'data'    => [
				'title'   => $title,
				'excerpt' => wp_strip_all_tags( $excerpt ),
				'url'     => esc_url_raw( $url ) ?: home_url( '/' ),
				'icon'    => 'fa-bullhorn',
			],
			'force'   => false,  // 仍然尊重使用者偏好（除非極重要才 force=true）
		] );
		if ( ! is_wp_error( $r ) ) $sent++;
	}
	return $sent;
}
