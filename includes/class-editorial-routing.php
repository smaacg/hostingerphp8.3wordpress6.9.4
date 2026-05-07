<?php
/**
 * Editorial Routing v3
 *
 * 文章內容層專用：
 * - 註冊 channel taxonomy（綁定 post）
 * - 提供 /news/anime/post-slug/、/review/manga/post-slug/、/feature/game/post-slug/
 * - 提供 /announcement/post-slug/ 獨立公告路由（無 channel）
 *
 * v3 變更：
 * - [修正] rewrite 規則 6 限定為 announcement 專用，
 *          解決 /news/anime/ 等 channel 列表頁被吃成單篇 404 的問題
 * - [改進] filter_post_permalink 加入 fallback：
 *          news/review/feature 缺 channel 時回傳原始 permalink，
 *          避免產出語意不清的 /news/post-slug/ 與 announcement 撞型
 * - [改進] maybe_redirect_canonical_post_url 加入 password / customizer 跳過
 * - [清理] 移除冗餘的 register_query_vars()
 *          register_taxonomy 已透過 'query_var' => 'channel' 自動註冊
 *
 * v2 變更（保留紀錄）：
 * - CONTENT_TYPES 新增 announcement
 * - CHANNELS 新增 voice-actor / music / merchandise / event / industry
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anime_Sync_Editorial_Routing {

	/**
	 * 允許的文章內容型態（category slug）
	 */
	private const CONTENT_TYPES = [
		'announcement',
		'news',
		'review',
		'feature',
	];

	/**
	 * 不需要 channel 即可直接訪問單篇的內容型
	 *
	 * 屬於這份清單的 content_type，URL 結構為 /<type>/<post-slug>/。
	 * 不在這份清單的 content_type，URL 結構為 /<type>/<channel>/<post-slug>/。
	 */
	private const CHANNELLESS_TYPES = [
		'announcement',
	];

	/**
	 * 允許的文章頻道（channel slug）
	 */
	private const CHANNELS = [
		'anime',
		'manga',
		'novel',
		'game',
		'vtuber',
		'cosplay',
		'ai-tools',
		'voice-actor',
		'music',
		'merchandise',
		'event',
		'industry',
	];

	public function __construct() {
		add_action( 'init', [ $this, 'register_channel_taxonomy' ], 20 );
		add_action( 'init', [ $this, 'add_rewrite_rules' ], 30 );

		add_filter( 'post_link', [ $this, 'filter_post_permalink' ], 10, 3 );

		add_action( 'pre_get_posts',     [ $this, 'tune_editorial_queries' ] );
		add_action( 'template_redirect', [ $this, 'maybe_redirect_canonical_post_url' ] );
	}

	/**
	 * 註冊文章頻道 taxonomy
	 *
	 * query_var=channel 已會自動把 channel 加進 query_vars 白名單，
	 * 因此不再額外掛 query_vars filter。
	 */
	public function register_channel_taxonomy(): void {
		register_taxonomy( 'channel', [ 'post' ], [
			'labels' => [
				'name'                       => '內容頻道',
				'singular_name'              => '內容頻道',
				'search_items'               => '搜尋頻道',
				'popular_items'              => '常用頻道',
				'all_items'                  => '所有頻道',
				'edit_item'                  => '編輯頻道',
				'update_item'                => '更新頻道',
				'add_new_item'               => '新增頻道',
				'new_item_name'              => '新頻道名稱',
				'separate_items_with_commas' => '請用逗號分隔頻道',
				'add_or_remove_items'        => '新增或移除頻道',
				'choose_from_most_used'      => '從最常用頻道選擇',
				'menu_name'                  => '內容頻道',
			],
			'public'             => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'hierarchical'       => false,
			'query_var'          => 'channel',
			'rewrite'            => false,
			'show_in_nav_menus'  => true,
		] );
	}

	/**
	 * 新增文章 rewrite 規則
	 *
	 * 規則優先級（add_rewrite_rule with 'top' 是堆疊到最前，
	 * 後 add 的會排在更前面，因此實際比對順序為 6 → 5 → 4 → 3 → 2 → 1）：
	 *
	 *   1. /news/                              → category 列表
	 *   2. /news/page/2/                       → category 分頁
	 *   3. /news/anime/                        → category + channel 列表
	 *   4. /news/anime/page/2/                 → category + channel 分頁
	 *   5. /news/anime/post-slug/              → 單篇文章
	 *   6. /announcement/post-slug/            → 公告類單篇（無 channel）
	 *
	 * [修正 v3] 原本規則 6 開放給所有 content type，
	 * 導致 /news/anime/ 在比對時優先命中規則 6（把 anime 當成 post slug），
	 * channel 列表頁因此 404。改為只允許 CHANNELLESS_TYPES（目前僅 announcement）。
	 */
	public function add_rewrite_rules(): void {
		$content_regex      = implode( '|', array_map( [ $this, 'quote_for_regex' ], self::CONTENT_TYPES ) );
		$channel_regex      = implode( '|', array_map( [ $this, 'quote_for_regex' ], self::CHANNELS ) );
		$channelless_regex  = implode( '|', array_map( [ $this, 'quote_for_regex' ], self::CHANNELLESS_TYPES ) );

		// 1. /news/
		add_rewrite_rule(
			'^(' . $content_regex . ')/?$',
			'index.php?category_name=$matches[1]',
			'top'
		);

		// 2. /news/page/2/
		add_rewrite_rule(
			'^(' . $content_regex . ')/page/([0-9]{1,})/?$',
			'index.php?category_name=$matches[1]&paged=$matches[2]',
			'top'
		);

		// 3. /news/anime/
		add_rewrite_rule(
			'^(' . $content_regex . ')/(' . $channel_regex . ')/?$',
			'index.php?category_name=$matches[1]&channel=$matches[2]',
			'top'
		);

		// 4. /news/anime/page/2/
		add_rewrite_rule(
			'^(' . $content_regex . ')/(' . $channel_regex . ')/page/([0-9]{1,})/?$',
			'index.php?category_name=$matches[1]&channel=$matches[2]&paged=$matches[3]',
			'top'
		);

		// 5. /news/anime/post-slug/
		add_rewrite_rule(
			'^(' . $content_regex . ')/(' . $channel_regex . ')/([^/]+)/?$',
			'index.php?post_type=post&name=$matches[3]&category_name=$matches[1]&channel=$matches[2]',
			'top'
		);

		// 6. /announcement/post-slug/  公告類專用單篇路由（無 channel）
		// [修正 v3] content type 限定為 CHANNELLESS_TYPES，避免吃掉規則 3 的 channel 列表頁
		add_rewrite_rule(
			'^(' . $channelless_regex . ')/([^/]+)/?$',
			'index.php?post_type=post&name=$matches[2]&category_name=$matches[1]',
			'top'
		);
	}

	/**
	 * 文章 permalink 改寫
	 *
	 * 結構：
	 * - announcement（CHANNELLESS_TYPES）         → /announcement/post-slug/
	 * - news/review/feature 且設了 channel        → /news/anime/post-slug/
	 * - news/review/feature 但沒設 channel        → 回傳原始 permalink（不改寫）
	 *
	 * [改進 v3] 原本 news/review/feature 缺 channel 時會被改寫成 /news/post-slug/，
	 * 與 announcement 路徑撞型，且該 URL 在新版規則 6 限定 announcement 後無法匹配。
	 * 改為缺 channel 時回退到 WordPress 預設 permalink。
	 */
	public function filter_post_permalink( string $permalink, WP_Post $post, bool $leavename ): string {
		if ( $post->post_type !== 'post' ) {
			return $permalink;
		}

		$content_type = $this->get_primary_content_type_slug( $post->ID );
		if ( $content_type === '' ) {
			return $permalink;
		}

		$post_slug = $leavename ? '%postname%' : $post->post_name;

		// 公告類（無 channel）→ /announcement/post-slug/
		if ( $this->is_channelless_type( $content_type ) ) {
			return home_url( user_trailingslashit( "{$content_type}/{$post_slug}" ) );
		}

		// 一般文章必須有 channel 才改寫
		$channel = $this->get_primary_channel_slug( $post->ID );
		if ( $channel === '' ) {
			return $permalink;
		}

		return home_url( user_trailingslashit( "{$content_type}/{$channel}/{$post_slug}" ) );
	}

	/**
	 * 調整文章列表查詢
	 */
	public function tune_editorial_queries( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$category_name = (string) $query->get( 'category_name' );
		if ( ! $this->is_allowed_content_type( $category_name ) ) {
			return;
		}

		$query->set( 'post_type', 'post' );
		$query->set( 'ignore_sticky_posts', true );

		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'date' );
		}
		if ( ! $query->get( 'order' ) ) {
			$query->set( 'order', 'DESC' );
		}
	}

	/**
	 * 若文章被錯誤網址打開，301 導向 canonical permalink
	 *
	 * [改進 v3] 加入 password protected post 與 customizer 跳過判斷
	 */
	public function maybe_redirect_canonical_post_url(): void {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return;
		}

		// 預覽、密碼保護未驗證、Customizer 預覽 → 不導向
		if ( is_preview() || post_password_required() ) {
			return;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return;
		}

		if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || $post->post_type !== 'post' ) {
			return;
		}

		$canonical = get_permalink( $post );
		if ( ! $canonical ) {
			return;
		}

		$request_uri  = $_SERVER['REQUEST_URI'] ?? '';
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$target_path  = wp_parse_url( $canonical,   PHP_URL_PATH );

		if ( ! is_string( $request_path ) || ! is_string( $target_path ) ) {
			return;
		}

		if ( untrailingslashit( $request_path ) !== untrailingslashit( $target_path ) ) {
			wp_safe_redirect( $canonical, 301 );
			exit;
		}
	}

	/**
	 * 取得主內容型 category slug
	 */
	private function get_primary_content_type_slug( int $post_id ): string {
		$terms = get_the_category( $post_id );

		if ( empty( $terms ) ) {
			return '';
		}

		foreach ( $terms as $term ) {
			if ( isset( $term->slug ) && $this->is_allowed_content_type( $term->slug ) ) {
				return (string) $term->slug;
			}
		}

		return '';
	}

	/**
	 * 取得主 channel slug
	 */
	private function get_primary_channel_slug( int $post_id ): string {
		$terms = get_the_terms( $post_id, 'channel' );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		foreach ( $terms as $term ) {
			if ( isset( $term->slug ) && $this->is_allowed_channel( $term->slug ) ) {
				return (string) $term->slug;
			}
		}

		return '';
	}

	/**
	 * 是否為允許的內容型
	 */
	private function is_allowed_content_type( string $slug ): bool {
		return in_array( $slug, self::CONTENT_TYPES, true );
	}

	/**
	 * 是否為允許的頻道
	 */
	private function is_allowed_channel( string $slug ): bool {
		return in_array( $slug, self::CHANNELS, true );
	}

	/**
	 * 是否為「無 channel」型內容
	 */
	private function is_channelless_type( string $slug ): bool {
		return in_array( $slug, self::CHANNELLESS_TYPES, true );
	}

	/**
	 * regex quote helper
	 */
	private function quote_for_regex( string $value ): string {
		return preg_quote( $value, '/' );
	}
}
