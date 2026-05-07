<?php
/**
 * 檔案名稱: includes/class-import-manager.php
 *
 * @version 1.1.0
 *
 * Changelog:
 *   1.1.0 — Taxonomy 匯入優化（配合 class-installer.php 1.3.0 seed 範圍縮減）
 *           - [新增] save_taxonomies() genre 加入 AniList 19 項中英對照表
 *                   AniList 回傳的 Action / Adventure / Comedy 等英文 genre
 *                   會自動轉為「動作 / 冒險 / 喜劇」中文 term，
 *                   命中 seed 預建的 27 項中文 genre（共通 18 項命中）。
 *                   未命中對照表的 genre fallback 使用原文，避免匯入失敗。
 *           - [新增] save_taxonomies() anime_season_tax 動態補父年份 term
 *                   匯入超出 seed 範圍的年份（例如 2003 春番）時，
 *                   先確保「2003」父 term 存在，再把「2003 春季」掛在其下，
 *                   解決孤兒 term 與層級錯亂問題。
 *           - [改進] save_taxonomies() anime_format_tax 改用中文對照表建立 term
 *                   原本 ucfirst($format_slug) 會建出 Tv / Movie / Ova 英文 term，
 *                   現改為 TV / 劇場版 / OVA / ONA / 特別篇 / 音樂MV / TV短篇，
 *                   與 seed 預建的 anime_format_tax 一致。
 *
 *   原 ACD / ACK / ACL / 標題保留邏輯與雙重 sync 寫入維持不變。
 *
 * ACD – 新增 analyze_series()：呼叫 api_handler->get_series_tree()，
 * 供 Tab 4 AJAX 分析系列使用。
 * 新增 assign_series_taxonomy()：建立或查找 anime_series_tax term，
 * 並將指定文章歸入該系列。
 * 新增 get_popularity_ranking()：委派給 api_handler->fetch_anilist_popularity()，
 * 供 Tab 5 AJAX 人氣排行使用。
 * import_single() 新增第三參數 $source（預設 'manual'），
 * 相容 class-cron-manager.php 呼叫 import_single($id, null, 'anilist')。
 * generate_slug() 新增 $exclude_id 參數，更新時排除自身避免無限加 suffix。
 * ACK – 新增 map_streaming_to_tw_fields()：解析 externalLinks 自動寫入台灣串流平台欄位。
 * ACL – import_single() enrich 排程改為依 post_id 尾數錯開時間，
 *        避免批量匯入時同時觸發大量 API 請求撞 rate limit。
 *
 * import_single() 更新時保留現有文章標題，不讓 API 覆寫人工編輯的標題
 * fetch_themes_only()：公開包裝方法，供 class-cron-manager.php 呼叫 AnimeThemes API
 * fetch_episodes_only()：公開包裝方法，供 class-cron-manager.php 呼叫 Bangumi 集數 API
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Import_Manager {

	private Anime_Sync_API_Handler $api_handler;
	private Anime_Sync_CN_Converter $cn_converter;

	public function __construct(
		Anime_Sync_API_Handler $api_handler,
		Anime_Sync_CN_Converter $cn_converter
	) {
		$this->api_handler  = $api_handler;
		$this->cn_converter = $cn_converter;
	}

	// =========================================================================
	// PUBLIC – 單筆匯入（ACD：新增第三參數 $source）
	// =========================================================================

	public function import_single( int $anilist_id, ?int $bangumi_id = null, string $source = 'manual', array $args = [] ): array {

		$force      = ! empty( $args['force'] );
		$lock_token = $this->acquire_import_lock( $anilist_id, $force );

		if ( $lock_token === '' ) {
			$existing_id = $this->find_existing( $anilist_id );

			if ( $existing_id > 0 ) {
				$existing_bangumi_id = (int) get_post_meta( $existing_id, 'anime_bangumi_id', true );
				if ( $existing_bangumi_id <= 0 ) {
					$existing_bangumi_id = (int) get_post_meta( $existing_id, 'bangumi_id', true );
				}

				return [
					'success'         => true,
					'skipped'         => true,
					'skip_enrich'     => true,
					'message'         => '此作品已有同步程序，已直接沿用既有草稿',
					'post_id'         => $existing_id,
					'title'           => get_the_title( $existing_id ) ?: "ID {$anilist_id}",
					'edit_url'        => get_edit_post_link( $existing_id, 'raw' ),
					'bangumi_missing' => $existing_bangumi_id <= 0,
					'needs_enrich'    => ! (bool) get_post_meta( $existing_id, '_enriched_at', true ),
				];
			}

			return [
				'success' => false,
				'message' => '此作品正在同步中，請稍後再試',
				'locked'  => true,
			];
		}

		try {
			$existing_id = $this->find_existing( $anilist_id );
			$is_update   = (bool) $existing_id;

		// 先重用既有文章已保存的 Bangumi ID，讓 API Handler / ID Mapper 的 Layer 0 真正生效。
		if ( ( ! $bangumi_id || $bangumi_id <= 0 ) && $existing_id > 0 ) {
			$stored_bangumi_id = (int) get_post_meta( $existing_id, 'anime_bangumi_id', true );
			if ( $stored_bangumi_id <= 0 ) {
				$stored_bangumi_id = (int) get_post_meta( $existing_id, 'bangumi_id', true );
			}
			if ( $stored_bangumi_id > 0 ) {
				$bangumi_id = $stored_bangumi_id;
			}
		}

		$anime_data = $this->api_handler->get_core_anime_data( $anilist_id, $existing_id, $bangumi_id );

		if ( is_wp_error( $anime_data ) ) {
			return [
				'success' => false,
				'message' => '資料取得失敗：' . $anime_data->get_error_message(),
			];
		}

		if ( empty( $anime_data['anilist_id'] ) ) {
			return [
				'success' => false,
				'message' => '無效的 AniList 資料（缺少 anilist_id）',
			];
		}

		$has_bangumi   = ! empty( $anime_data['bangumi_id'] ) && (int) $anime_data['bangumi_id'] > 0;
		$has_chinese   = ! empty( $anime_data['anime_title_chinese'] );
		$has_synopsis  = ! empty( $anime_data['anime_synopsis_chinese'] );
		$has_cover     = ! empty( $anime_data['anime_cover_image'] );
		$has_streaming = ! empty( $anime_data['anime_streaming'] ) && $anime_data['anime_streaming'] !== '[]';

		$summary = implode( ' | ', array_filter( [
			$has_chinese   ? '✅ 中文標題' : '⚠️ 無中文標題',
			$has_bangumi   ? '✅ Bangumi'  : '⚠️ 缺 Bangumi',
			$has_synopsis  ? '✅ 簡介'     : null,
			$has_cover     ? '✅ 封面'     : '⚠️ 無封面',
			$has_streaming ? '✅ 串流'     : null,
			'⏳ 待補抓：聲優/主題曲/Wikipedia',
		] ) );

		if ( ! $is_update ) {
			$existing_id = $this->find_existing( $anilist_id );
			$is_update   = (bool) $existing_id;
		}

		// 標題邏輯：
		// - 首次匯入（!$is_update）：使用 API 回傳的中文標題或 Romaji。
		// - 更新（$is_update）：優先保留現有文章的 post_title（人工編輯過的標題），
		//   避免每日排程覆寫人工修改。只有在現有標題為空時才 fallback 至 API 標題。
		if ( $is_update ) {
			$existing_post = get_post( $existing_id );
			$post_title    = ( $existing_post && trim( $existing_post->post_title ) !== '' )
				? $existing_post->post_title
				: ( ! empty( $anime_data['anime_title_chinese'] )
					? (string) $anime_data['anime_title_chinese']
					: ( $anime_data['anime_title_romaji'] ?? "Anime {$anilist_id}" ) );
		} else {
			$post_title = ! empty( $anime_data['anime_title_chinese'] )
				? (string) $anime_data['anime_title_chinese']
				: ( $anime_data['anime_title_romaji'] ?? "Anime {$anilist_id}" );
		}

		$post_slug   = $this->generate_slug( $anime_data, $existing_id );
		$post_fields = $this->extract_post_fields( $anime_data, $existing_id );

		$post_data = [
			'post_type'   => 'anime',
			'post_title'  => $post_title,
			'post_name'   => $post_slug,
			'post_status' => 'draft',
			'post_author' => get_current_user_id() ?: 1,
		];

		if ( ! empty( $post_fields ) ) {
			$post_data = array_merge( $post_data, $post_fields );
		}

		if ( $is_update ) {
			$post_data['ID'] = $existing_id;
			$post_id = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return [
				'success' => false,
				'message' => '文章建立失敗：' . $post_id->get_error_message(),
			];
		}

		$this->save_post_meta( $post_id, $anime_data );
		update_post_meta( $post_id, 'anime_last_updated', current_time( 'mysql' ) );

		if ( ! $is_update ) {
			$this->apply_first_import_locks( $post_id, $anime_data );
		}

		if ( ! empty( $anime_data['anime_cover_image'] ) ) {
			$this->set_featured_image( $post_id, $anime_data['anime_cover_image'], $post_title );
		}

		$this->save_taxonomies( $post_id, $anime_data );

		update_post_meta( $post_id, '_import_source', sanitize_text_field( $source ) );
		update_post_meta( $post_id, 'anime_last_sync', current_time( 'mysql' ) );
		delete_post_meta( $post_id, '_enriched_at' );

		// ✅ ACL：依 post_id 尾數錯開 enrich 排程，避免批量匯入時同時觸發大量 API 請求。
		// 每部動畫間隔 90 秒，post_id 尾數決定在哪個 slot 執行：
		//   post_id % 40 → 0~39，乘以 90 秒 → 最多分散在 60 分鐘內完成。
		if ( ! wp_next_scheduled( 'anime_sync_enrich_post', [ $post_id ] ) ) {
			$slot  = ( $post_id % 40 );           // 0 ~ 39
			$delay = 60 + ( $slot * 90 );          // 60s ~ 3570s（約 1 分鐘 ~ 60 分鐘）
			wp_schedule_single_event( time() + $delay, 'anime_sync_enrich_post', [ $post_id ] );
		}

		$display_title   = $anime_data['anime_title_chinese'] ?: $anime_data['anime_title_romaji'] ?: "ID {$anilist_id}";
		$action_label    = $is_update ? '已更新' : '已匯入';
		$base_message    = "{$action_label} – {$display_title} (ID {$anilist_id})";
		$bangumi_missing = ! $has_bangumi;

		if ( $bangumi_missing ) {
			$base_message .= ' ⚠️ Bangumi ID 未找到，將於背景補抓';
		}

		return [
			'success'         => true,
			'message'         => $base_message,
			'post_id'         => $post_id,
			'mal_id'          => $anime_data['mal_id'] ?? 0,
			'title'           => $display_title,
			'edit_url'        => get_edit_post_link( $post_id, 'raw' ),
			'summary'         => $summary,
			'bangumi_missing' => $bangumi_missing,
			'needs_enrich'    => true,
		];
		} finally {
			$this->release_import_lock( $anilist_id, $lock_token );
		}
	}

	// =========================================================================
	// PUBLIC – 補抓第二段資料（ACB，供 WP-Cron 或手動觸發）
	// =========================================================================

	public function enrich_single( int $post_id ): array|\WP_Error {
		if ( get_post_meta( $post_id, '_enriched_at', true ) ) {
			return new \WP_Error( 'already_enriched', "Post {$post_id} already enriched." );
		}

		$result = $this->api_handler->enrich_anime_data( $post_id );

		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $post_id, '_enriched_at', current_time( 'mysql' ) );
			delete_post_meta( $post_id, '_needs_enrich' );
		}

		return $result;
	}

	// =========================================================================
	// PUBLIC – ACD 新增：系列分析（供 Tab 4 AJAX）
	// =========================================================================

	public function analyze_series( int $anilist_id ): array|\WP_Error {
		return $this->api_handler->get_series_tree( $anilist_id );
	}

	// =========================================================================
	// PUBLIC – ACD 新增：人氣排行（供 Tab 5 AJAX）
	// =========================================================================

	public function get_popularity_ranking( int $page = 1 ): array|\WP_Error {
		return $this->api_handler->fetch_anilist_popularity( $page );
	}

	// =========================================================================
	// PUBLIC – ACD 新增：系列 Taxonomy 歸類
	// =========================================================================

	public function assign_series_taxonomy( int $post_id, string $series_name, int $root_id = 0, string $series_romaji = '' ): bool {
		if ( ! $post_id || $series_name === '' ) return false;

		$series_name = trim( $series_name );
		$term        = term_exists( $series_name, 'anime_series_tax' );

		if ( ! $term ) {
			$slug   = $series_romaji !== '' ? sanitize_title( $series_romaji ) : sanitize_title( $series_name );
			$result = wp_insert_term( $series_name, 'anime_series_tax', [ 'slug' => $slug ] );
			if ( is_wp_error( $result ) ) return false;
			$term_id = (int) $result['term_id'];
		} else {
			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		}

		$result = wp_set_post_terms( $post_id, [ $term_id ], 'anime_series_tax', false );
		if ( is_wp_error( $result ) ) return false;

		if ( $root_id > 0 ) {
			update_post_meta( $post_id, '_series_root_anilist_id', $root_id );
		}

		return true;
	}

	// =========================================================================
	// PUBLIC – 主題曲 API 公開包裝方法（供 class-cron-manager.php 呼叫）
	// =========================================================================

	public function fetch_themes_only( int $mal_id ): array {
		if ( $mal_id <= 0 ) {
			return [];
		}
		return $this->api_handler->fetch_animethemes( $mal_id );
	}

	// =========================================================================
	// PUBLIC – 集數列表 API 公開包裝方法（供 class-cron-manager.php 呼叫）
	// =========================================================================

	public function fetch_episodes_only( int $bangumi_id ): array {
		if ( $bangumi_id <= 0 ) {
			return [];
		}
		return $this->api_handler->fetch_bgm_episodes( $bangumi_id );
	}

	// =========================================================================
	// PRIVATE – 首次匯入鎖定欄位
	// =========================================================================

	private function apply_first_import_locks( int $post_id, array $data ): void {
		$lock_fields = [
			'anime_cover_image'      => $data['anime_cover_image'] ?? '',
			'anime_banner_image'     => $data['anime_banner_image'] ?? '',
			'anime_trailer_url'      => $data['anime_trailer_url'] ?? '',
			'anime_synopsis_chinese' => $data['anime_synopsis_chinese'] ?? '',
		];

		foreach ( $lock_fields as $key => $val ) {
			if ( $val !== '' ) {
				update_post_meta( $post_id, "_lock_{$key}", 1 );
			}
		}
	}

	// =========================================================================
	// PRIVATE – 產生 Slug
	// =========================================================================

	private function generate_slug( array $data, int $exclude_id = 0 ): string {
		$candidates = array_filter( [
			$data['anime_title_romaji'] ?? '',
			$data['anime_title_english'] ?? '',
			'anime-' . ( $data['anilist_id'] ?? 0 ),
		] );

		$raw  = reset( $candidates );
		$slug = sanitize_title( $raw );
		if ( $slug === '' ) $slug = 'anime-' . ( $data['anilist_id'] ?? 0 );

		$original = $slug;
		$suffix   = 1;

		while ( true ) {
			$found = get_page_by_path( $slug, OBJECT, 'anime' );
			if ( ! $found || ( $exclude_id > 0 && (int) $found->ID === $exclude_id ) ) {
				break;
			}
			$slug = $original . '-' . $suffix++;
		}

		return $slug;
	}

	// =========================================================================
	// PRIVATE – 儲存 Post Meta
	// =========================================================================

	private function save_post_meta( int $post_id, array $data ): void {
		$animethemes_id   = isset( $data['anime_animethemes_id'] ) ? trim( (string) $data['anime_animethemes_id'] ) : '';
		$animethemes_slug = isset( $data['anime_animethemes_slug'] )
			? trim( (string) $data['anime_animethemes_slug'] )
			: trim( (string) ( $data['animethemes_slug'] ?? '' ) );

		if ( $animethemes_id !== '' && ! ctype_digit( $animethemes_id ) && $animethemes_slug === '' ) {
			$animethemes_slug = $animethemes_id;
			$animethemes_id   = '';
		}

		$meta_map = [
			'anime_anilist_id'       => $data['anilist_id'] ?? 0,
			'anime_mal_id'           => $data['mal_id'] ?? 0,
			'anime_animethemes_id'   => $animethemes_id,
			'anime_animethemes_slug' => $animethemes_slug,
			'animethemes_slug'       => $animethemes_slug,
			'anime_title_chinese'    => $data['anime_title_chinese'] ?? '',
			'anime_title_romaji'     => $data['anime_title_romaji'] ?? '',
			'anime_title_english'    => $data['anime_title_english'] ?? '',
			'anime_title_native'     => $data['anime_title_native'] ?? '',
			'anime_format'           => $data['anime_format'] ?? '',
			'anime_status'           => $data['anime_status'] ?? '',
			'anime_season'           => strtoupper( $data['anime_season'] ?? '' ),
			'anime_season_year'      => $data['anime_season_year'] ?? 0,
			'anime_source'           => $data['anime_source'] ?? '',
			'anime_episodes'         => $data['anime_episodes'] ?? 0,
			'anime_duration'         => $data['anime_duration'] ?? 0,
			'anime_studios'          => $data['anime_studios'] ?? '',
			'anime_score_anilist'    => $data['anime_score_anilist'] ?? 0,
			'anime_score_bangumi'    => $data['anime_score_bangumi'] ?? 0,
			'anime_score_mal'        => $data['anime_score_mal'] ?? 0,
			'anime_popularity'       => $data['anime_popularity'] ?? 0,
			'anime_cover_image'      => $data['anime_cover_image'] ?? '',
			'anime_banner_image'     => $data['anime_banner_image'] ?? '',
			'anime_trailer_url'      => $data['anime_trailer_url'] ?? '',
			'anime_synopsis_chinese' => $data['anime_synopsis_chinese'] ?? '',
			'anime_synopsis_english' => $data['anime_synopsis_english'] ?? '',
			'anime_start_date'       => $data['anime_start_date'] ?? '',
			'anime_end_date'         => $data['anime_end_date'] ?? '',
			'anime_streaming'        => $data['anime_streaming'] ?? '[]',
			'anime_themes'           => $data['anime_themes'] ?? '[]',
			'anime_staff_json'       => $data['anime_staff_json'] ?? '[]',
			'anime_cast_json'        => $data['anime_cast_json'] ?? '[]',
			'anime_relations_json'   => $data['anime_relations_json'] ?? '[]',
			'anime_episodes_json'    => $data['anime_episodes_json'] ?? '[]',
			'anime_official_site'    => $data['anime_official_site'] ?? '',
			'anime_twitter_url'      => $data['anime_twitter_url'] ?? '',
			'anime_wikipedia_url'    => $data['anime_wikipedia_url'] ?? '',
			'anime_external_links'   => $data['anime_external_links'] ?? '[]',
			'anime_next_airing'      => $data['anime_next_airing'] ?? '',
			'anime_sync_time'        => current_time( 'mysql' ),
		];

		foreach ( $meta_map as $key => $value ) {
			update_post_meta( $post_id, $key, $this->prepare_meta_value( $key, $value ) );
		}

		// 停用：不要自動把 AniList externalLinks 映射到台灣欄位。
		// 台灣平台改為完全由人工在 ACF 欄位維護。
		// $this->map_streaming_to_tw_fields( $post_id, $data['anime_external_links'] ?? '[]' );

		$bgm_id_raw        = $data['bangumi_id'] ?? null;
		$bgm_id            = $bgm_id_raw !== null ? abs( intval( $bgm_id_raw ) ) : 0;
		$manually_set      = (bool) get_post_meta( $post_id, '_bangumi_id_manually_set', true );
		$existing_bgm_id   = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );
		$existing_bangumi  = $existing_bgm_id > 0 ? $existing_bgm_id : (int) get_post_meta( $post_id, 'bangumi_id', true );

		if ( $bgm_id > 0 && ! $manually_set ) {
			update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
			update_post_meta( $post_id, 'bangumi_id', $bgm_id );
			delete_post_meta( $post_id, '_bangumi_id_pending' );
		} elseif ( ! $manually_set ) {
			if ( $existing_bangumi > 0 ) {
				update_post_meta( $post_id, 'anime_bangumi_id', $existing_bangumi );
				update_post_meta( $post_id, 'bangumi_id', $existing_bangumi );
				delete_post_meta( $post_id, '_bangumi_id_pending' );
			} else {
				delete_post_meta( $post_id, 'anime_bangumi_id' );
				delete_post_meta( $post_id, 'bangumi_id' );
				update_post_meta( $post_id, '_bangumi_id_pending', 1 );
			}
		}

		if ( ! empty( $data['_needs_enrich'] ) ) {
			update_post_meta( $post_id, '_needs_enrich', 1 );
		}
	}

	private function prepare_meta_value( string $key, $value ) {
		if ( $this->is_json_meta_key( $key ) ) {
			return is_string( $value )
				? $this->cn_converter->convert_json_string( $value )
				: wp_json_encode( $this->cn_converter->convert_mixed( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		if ( $this->is_convertible_text_meta_key( $key ) ) {
			return is_string( $value ) ? $this->cn_converter->convert( $value ) : $value;
		}

		return $value;
	}

	private function is_convertible_text_meta_key( string $key ): bool {
		return in_array( $key, [
			'anime_synopsis_chinese',
			'anime_studios',
		], true );
	}

	private function is_json_meta_key( string $key ): bool {
		return in_array( $key, [
			'anime_staff_json',
			'anime_cast_json',
			'anime_episodes_json',
		], true );
	}

	private function extract_post_fields( array $data, int $existing_id = 0 ): array {
		$content_candidates = [
			'post_content',
			'content',
			'article_content',
			'generated_content',
			'draft_content',
			'body',
		];

		$excerpt_candidates = [
			'post_excerpt',
			'excerpt',
			'article_excerpt',
			'generated_excerpt',
			'summary',
		];

		$post_fields   = [];
		$existing_post = null;

		$content = $this->pick_first_string( $data, $content_candidates );
		if ( $content !== '' ) {
			$post_fields['post_content'] = $this->cn_converter->convert( $content );
		} elseif ( $existing_id > 0 ) {
			$existing_post = get_post( $existing_id );
			if ( $existing_post && ! empty( $existing_post->post_content ) ) {
				$post_fields['post_content'] = $existing_post->post_content;
			}
		}

		$excerpt = $this->pick_first_string( $data, $excerpt_candidates );
		if ( $excerpt !== '' ) {
			$post_fields['post_excerpt'] = $this->cn_converter->convert( $excerpt );
		} elseif ( $existing_id > 0 ) {
			$existing_post = $existing_post ?: get_post( $existing_id );
			if ( $existing_post && ! empty( $existing_post->post_excerpt ) ) {
				$post_fields['post_excerpt'] = $existing_post->post_excerpt;
			}
		}

		return $post_fields;
	}

	private function pick_first_string( array $data, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$value = trim( $data[ $key ] );
				if ( $value !== '' ) {
					return $value;
				}
			}
		}
		return '';
	}

	// =========================================================================
	// PRIVATE – 設定特色圖片
	// =========================================================================

	private function set_featured_image( int $post_id, string $image_url, string $title ): void {
		if ( has_post_thumbnail( $post_id ) && get_post_meta( $post_id, '_lock_anime_cover_image', true ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$filename   = sanitize_file_name( 'anime-cover-' . $post_id . '-' . md5( $image_url ) . '.jpg' );
		$file_path  = $upload_dir['path'] . '/' . $filename;

		if ( ! file_exists( $file_path ) ) {
			$response = wp_remote_get( $image_url, [ 'timeout' => 15 ] );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return;
			$image_data = wp_remote_retrieve_body( $response );
			if ( empty( $image_data ) ) return;
			file_put_contents( $file_path, $image_data );
		}

		$file_type  = wp_check_filetype( $filename );
		$attachment = [
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_text_field( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attach_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( is_wp_error( $attach_id ) ) return;

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		set_post_thumbnail( $post_id, $attach_id );
	}

	// =========================================================================
	// PRIVATE – 儲存分類法
	//
	// [1.1.0 修改]
	//   1. genre 加入 AniList 19 項中英對照表（命中對照取中文，否則 fallback 英文）
	//   2. anime_season_tax 動態補父年份 term（解決孤兒 term 問題）
	//   3. anime_format_tax 改用中文對照表建立 term（與 seed 一致）
	// =========================================================================

	private function save_taxonomies( int $post_id, array $data ): void {

		// ───────────────────────────────────────────────────
		// 1. genre — AniList 19 項中英對照
		// ───────────────────────────────────────────────────
		if ( ! empty( $data['anime_genres'] ) && is_array( $data['anime_genres'] ) ) {
			$genre_map = $this->get_anilist_genre_map();
			$genre_ids = [];

			foreach ( $data['anime_genres'] as $genre_name ) {
				$genre_name = trim( (string) $genre_name );
				if ( $genre_name === '' ) continue;

				// 命中對照表用中文，否則 fallback 原文（避免新 genre 出現時匯入失敗）
				$zh_name = $genre_map[ $genre_name ] ?? $genre_name;

				$term = term_exists( $zh_name, 'genre' );
				if ( ! $term ) {
					$term = wp_insert_term( $zh_name, 'genre' );
				}
				if ( ! is_wp_error( $term ) ) {
					$genre_ids[] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				}
			}

			if ( ! empty( $genre_ids ) ) {
				wp_set_post_terms( $post_id, $genre_ids, 'genre' );
			}
		}

		// ───────────────────────────────────────────────────
		// 2. anime_season_tax — 動態補父年份 term
		// ───────────────────────────────────────────────────
		$season_year = (int) ( $data['anime_season_year'] ?? 0 );
		$season      = strtoupper( $data['anime_season'] ?? '' );

		if ( $season_year && $season ) {
			$season_map = [
				'SPRING' => '春季',
				'SUMMER' => '夏季',
				'FALL'   => '秋季',
				'WINTER' => '冬季',
			];
			$season_suffix_map = [
				'SPRING' => 'spring',
				'SUMMER' => 'summer',
				'FALL'   => 'fall',
				'WINTER' => 'winter',
			];

			$season_zh    = $season_map[ $season ] ?? ucfirst( strtolower( $season ) );
			$season_label = "{$season_year} {$season_zh}";
			$season_slug  = $season_suffix_map[ $season ] ?? sanitize_title( $season );

			// 2-1. 先確保父年份 term 存在
			$year_term_id = $this->ensure_year_parent_term( $season_year );

			// 2-2. 再建立 / 取得季度子 term，掛在父年份下
			$child_slug = "{$season_year}-{$season_slug}";
			$child_term = get_term_by( 'slug', $child_slug, 'anime_season_tax' );

			if ( ! $child_term ) {
				$insert_args = [ 'slug' => $child_slug ];
				if ( $year_term_id > 0 ) {
					$insert_args['parent'] = $year_term_id;
				}
				$result = wp_insert_term( $season_label, 'anime_season_tax', $insert_args );

				// 處理 race condition：另一個請求剛好建立了同 slug
				if ( is_wp_error( $result ) && $result->get_error_code() === 'term_exists' ) {
					$existing_id = (int) ( $result->get_error_data() ?: 0 );
					if ( $existing_id > 0 ) {
						$child_term_id = $existing_id;
					}
				} elseif ( ! is_wp_error( $result ) ) {
					$child_term_id = (int) $result['term_id'];
				}
			} else {
				$child_term_id = (int) $child_term->term_id;

				// 修補既有的孤兒 term：若父年份已建立但子 term 仍掛在頂層，補上 parent
				if ( $year_term_id > 0 && (int) $child_term->parent === 0 ) {
					wp_update_term( $child_term_id, 'anime_season_tax', [ 'parent' => $year_term_id ] );
				}
			}

			if ( ! empty( $child_term_id ) ) {
				wp_set_post_terms( $post_id, [ $child_term_id ], 'anime_season_tax' );
			}
		}

		// ───────────────────────────────────────────────────
		// 3. anime_format_tax — 中文對照
		// ───────────────────────────────────────────────────
		$format = $data['anime_format'] ?? '';
		if ( $format !== '' ) {
			$format_zh_map = $this->get_anilist_format_map();
			$format_key    = strtoupper( $format );
			$format_slug   = strtolower( str_replace( '_', '-', $format ) );

			// 命中對照取中文標題；未命中 fallback 用 ucfirst 英文（罕見格式）
			$format_zh_name = $format_zh_map[ $format_key ]['name'] ?? ucfirst( strtolower( $format_key ) );
			// slug 優先用對照表的（與 seed 一致），否則由 format 字串轉換
			$format_zh_slug = $format_zh_map[ $format_key ]['slug'] ?? $format_slug;

			$term = get_term_by( 'slug', $format_zh_slug, 'anime_format_tax' );
			if ( ! $term ) {
				$result = wp_insert_term( $format_zh_name, 'anime_format_tax', [ 'slug' => $format_zh_slug ] );
				if ( ! is_wp_error( $result ) ) {
					$tid = (int) $result['term_id'];
				} elseif ( $result->get_error_code() === 'term_exists' ) {
					$tid = (int) ( $result->get_error_data() ?: 0 );
				}
			} else {
				$tid = (int) $term->term_id;
			}

			if ( ! empty( $tid ) ) {
				wp_set_post_terms( $post_id, [ $tid ], 'anime_format_tax' );
			}
		}

		// ───────────────────────────────────────────────────
		// 4. 標籤 (post_tag)
		// ───────────────────────────────────────────────────
		if ( ! empty( $data['anime_tags'] ) && is_array( $data['anime_tags'] ) ) {
			$tag_ids = [];
			foreach ( $data['anime_tags'] as $tag_name ) {
				$tag_name = trim( (string) $tag_name );
				if ( $tag_name === '' ) continue;
				$zh_name = $this->resolve_tag_name( $tag_name );
				$tag_id  = $this->find_or_create_tag( $zh_name );
				if ( $tag_id ) $tag_ids[] = $tag_id;
			}
			if ( ! empty( $tag_ids ) ) wp_set_post_terms( $post_id, $tag_ids, 'post_tag' );
		}

		// ───────────────────────────────────────────────────
		// 5. 製作公司 (anime_studio_tax)
		// ───────────────────────────────────────────────────
		$studios_raw = $data['anime_studios'] ?? '';
		if ( ! empty( $studios_raw ) ) {
			$studio_names    = array_filter( array_map( 'trim', explode( ',', $studios_raw ) ) );
			$studio_term_ids = [];
			foreach ( $studio_names as $studio_name ) {
				if ( $studio_name === '' ) continue;
				$term = term_exists( $studio_name, 'anime_studio_tax' );
				if ( ! $term ) {
					$term = wp_insert_term( $studio_name, 'anime_studio_tax', [
						'slug' => sanitize_title( $studio_name ),
					] );
				}
				if ( ! is_wp_error( $term ) ) {
					$studio_term_ids[] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				}
			}
			if ( ! empty( $studio_term_ids ) ) {
				wp_set_object_terms( $post_id, $studio_term_ids, 'anime_studio_tax', false );
			}
		}
	}

	/**
	 * 確保 anime_season_tax 的父年份 term 存在。
	 * 用於匯入超出 seed 範圍的舊年份（例如 2003）時自動補建。
	 *
	 * @return int term_id（0 表失敗）
	 */
	private function ensure_year_parent_term( int $year ): int {
		if ( $year <= 0 ) return 0;

		$slug = (string) $year;
		$term = get_term_by( 'slug', $slug, 'anime_season_tax' );

		if ( $term ) {
			return (int) $term->term_id;
		}

		$result = wp_insert_term( (string) $year, 'anime_season_tax', [ 'slug' => $slug ] );

		if ( is_wp_error( $result ) ) {
			// race condition：另一個請求剛建好同 slug
			if ( $result->get_error_code() === 'term_exists' ) {
				$existing_id = (int) ( $result->get_error_data() ?: 0 );
				return $existing_id;
			}
			return 0;
		}

		return (int) $result['term_id'];
	}

	/**
	 * AniList 19 項 genre 中英對照表（含 Hentai）。
	 * 對應 setup-taxonomy.php / class-installer.php seed 出的中文 genre slug。
	 *
	 * 來源：https://anilist.co/forum/thread/4824
	 */
	private function get_anilist_genre_map(): array {
		return [
			'Action'        => '動作',
			'Adventure'     => '冒險',
			'Comedy'        => '喜劇',
			'Drama'         => '劇情',
			'Ecchi'         => '輕色情',
			'Fantasy'       => '奇幻',
			'Hentai'        => '成人',         // seed 未含；fallback 自動建立
			'Horror'        => '恐怖',
			'Mahou Shoujo'  => '魔法少女',
			'Mecha'         => '機甲',
			'Music'         => '音樂',
			'Mystery'       => '推理',
			'Psychological' => '心理',
			'Romance'       => '戀愛',
			'Sci-Fi'        => '科幻',
			'Slice of Life' => '日常',
			'Sports'        => '運動',
			'Supernatural'  => '超自然',
			'Thriller'      => '驚悚',
		];
	}

	/**
	 * AniList 7 項 format 中英對照表，slug 與 seed 一致。
	 *
	 * 對應 anime_format_tax 預建 term：tv / tv-short / movie / ova / ona / special / music
	 */
	private function get_anilist_format_map(): array {
		return [
			'TV'         => [ 'name' => 'TV',     'slug' => 'tv'       ],
			'TV_SHORT'   => [ 'name' => 'TV短篇', 'slug' => 'tv-short' ],
			'MOVIE'      => [ 'name' => '劇場版', 'slug' => 'movie'    ],
			'OVA'        => [ 'name' => 'OVA',    'slug' => 'ova'      ],
			'ONA'        => [ 'name' => 'ONA',    'slug' => 'ona'      ],
			'SPECIAL'    => [ 'name' => '特別篇', 'slug' => 'special'  ],
			'MUSIC'      => [ 'name' => '音樂MV', 'slug' => 'music'    ],
		];
	}

	// =========================================================================
	// PRIVATE – 解析 externalLinks 自動寫入台灣串流平台欄位
	// （目前未啟用，台灣平台改為人工維護）
	// =========================================================================

	private function map_streaming_to_tw_fields( int $post_id, string $external_links_json ): void {
		$links = json_decode( $external_links_json, true );
		if ( ! is_array( $links ) || empty( $links ) ) return;

		$platform_map = [
			'Crunchyroll'        => 'anime_tw_streaming_url_crunchyroll',
			'Netflix'            => 'anime_tw_streaming_url_netflix',
			'Disney Plus'        => 'anime_tw_streaming_url_disney',
			'Disney+'            => 'anime_tw_streaming_url_disney',
			'Amazon Prime Video' => 'anime_tw_streaming_url_amazon',
			'Hulu'               => 'anime_tw_streaming_url_hulu',
			'HIDIVE'             => 'anime_tw_streaming_url_hidive',
			'Bilibili'           => 'anime_tw_streaming_url_bilibili',
			'YouTube'            => 'anime_tw_streaming_url_youtube',
			'WeTV'               => 'anime_tw_streaming_url_wetv',
			'Viu'                => 'anime_tw_streaming_url_viu',
			'Ani-One Asia'       => 'anime_tw_streaming_url_ani_one',
			'Muse Asia'          => 'anime_tw_streaming_url_muse',
		];

		$platform_to_checkbox = [
			'Netflix'      => 'netflix',
			'Disney Plus'  => 'disney',
			'Disney+'      => 'disney',
			'Crunchyroll'  => 'crunchyroll',
			'Ani-One Asia' => 'ani_one',
			'Muse Asia'    => 'muse',
		];

		$checked_platforms = get_post_meta( $post_id, 'anime_tw_streaming', true );
		if ( ! is_array( $checked_platforms ) ) {
			$checked_platforms = [];
		}

		$has_existing = ! empty( $checked_platforms );

		foreach ( $links as $link ) {
			$site = $link['site'] ?? '';
			$url  = $link['url']  ?? '';
			$type = strtoupper( $link['type'] ?? '' );

			if ( $site === '' || $url === '' ) continue;
			if ( $type !== '' && $type !== 'STREAMING' ) continue;

			if ( $site === 'YouTube' ) {
				if ( stripos( $url, 'AniOneAsia' ) !== false || stripos( $url, 'ani-one' ) !== false ) {
					$site = 'Ani-One Asia';
				} elseif ( stripos( $url, 'MuseAsia' ) !== false || stripos( $url, 'muse' ) !== false ) {
					$site = 'Muse Asia';
				}
			}

			if ( isset( $platform_map[ $site ] ) ) {
				$meta_key = $platform_map[ $site ];
				$existing = get_post_meta( $post_id, $meta_key, true );
				if ( empty( $existing ) ) {
					update_post_meta( $post_id, $meta_key, esc_url_raw( $url ) );
				}
			}

			if ( ! $has_existing && isset( $platform_to_checkbox[ $site ] ) ) {
				$val = $platform_to_checkbox[ $site ];
				if ( ! in_array( $val, $checked_platforms, true ) ) {
					$checked_platforms[] = $val;
				}
			}
		}

		if ( ! $has_existing && ! empty( $checked_platforms ) ) {
			update_post_meta( $post_id, 'anime_tw_streaming', array_values( $checked_platforms ) );
		}
	}

	// =========================================================================
	// PRIVATE – Tag helpers
	// =========================================================================

	private function resolve_tag_name( string $en_name ): string {
		$map = $this->get_tag_map();
		if ( isset( $map[ $en_name ] ) ) return $map[ $en_name ];
		$cache_key = 'anime_sync_tag_' . md5( $en_name );
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) return (string) $cached;
		$zh = $this->google_translate( $en_name );
		$zh = $zh ?: $en_name;
		set_transient( $cache_key, $zh, 30 * DAY_IN_SECONDS );
		return $zh;
	}

	private function google_translate( string $text ): string {
		$api_key = defined( 'GOOGLE_TRANSLATE_API_KEY' ) ? GOOGLE_TRANSLATE_API_KEY : '';
		if ( ! $api_key ) return '';
		$url = 'https://translation.googleapis.com/language/translate/v2'
			. '?q=' . rawurlencode( $text )
			. '&target=zh-TW&source=en&format=text'
			. '&key=' . rawurlencode( $api_key );
		$log_url = preg_replace( '/key=[^&]+/', 'key=***REDACTED***', $url );
		Anime_Sync_Error_Logger::log( 'debug', "Google Translate: {$log_url}" );
		$response = wp_remote_get( $url, [
			'timeout'    => 8,
			'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
			'headers'    => [ 'Accept' => 'application/json' ],
		] );
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return '';
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['data']['translations'][0]['translatedText'] ?? '';
	}

	private function find_or_create_tag( string $name ): ?int {
		$name = trim( $name );
		if ( $name === '' ) return null;
		$term = term_exists( $name, 'post_tag' );
		if ( ! $term ) $term = wp_insert_term( $name, 'post_tag' );
		if ( is_wp_error( $term ) ) return null;
		return is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	}

	private function get_tag_map(): array {
		return [
			'Amnesia'                      => '失憶',
			'Revenge'                      => '復仇',
			'Reincarnation'                => '轉生',
			'Time Travel'                  => '時間旅行',
			'Time Loop'                    => '時間循環',
			'Isekai'                       => '異世界',
			'Parallel World'               => '平行世界',
			'Virtual Reality'              => '虛擬實境',
			'Augmented Reality'            => '擴增實境',
			'Post-Apocalyptic'             => '末日後',
			'Dystopia'                     => '反烏托邦',
			'Utopia'                       => '烏托邦',
			'Alternate History'            => '架空歷史',
			'Historical'                   => '歷史',
			'Fictional World'              => '架空世界',
			'Space'                        => '宇宙',
			'Space Opera'                  => '太空歌劇',
			'Cyberpunk'                    => '賽博龐克',
			'Steampunk'                    => '蒸汽龐克',
			'Dieselpunk'                   => '柴油龐克',
			'Fantasy World'                => '奇幻世界',
			'High Fantasy'                 => '高奇幻',
			'Low Fantasy'                  => '低奇幻',
			'Urban Fantasy'                => '都市奇幻',
			'Mythology'                    => '神話',
			'Feudal Japan'                 => '日本戰國',
			'Anti-Hero'                    => '反英雄',
			'Villain Protagonist'          => '反派主角',
			'Overpowered Protagonist'      => '無敵主角',
			'Female Protagonist'           => '女主角',
			'Male Protagonist'             => '男主角',
			'Non-Human Protagonist'        => '非人類主角',
			'Ensemble Cast'                => '群像劇',
			'Kuudere'                      => '酷蛋',
			'Tsundere'                     => '傲嬌',
			'Yandere'                      => '病嬌',
			'Dandere'                      => '呆萌',
			'Coming of Age'                => '成長故事',
			'Redemption'                   => '救贖',
			'Found Family'                 => '羈絆家族',
			'Tragedy'                      => '悲劇',
			'Comedy'                       => '喜劇',
			'Parody'                       => '搞笑惡搞',
			'Romance'                      => '戀愛',
			'Harem'                        => '後宮',
			'Reverse Harem'                => '逆後宮',
			'Love Triangle'                => '三角戀',
			'Forbidden Love'               => '禁忌之戀',
			'Arranged Marriage'            => '包辦婚姻',
			'Slice of Life'                => '日常',
			'School Life'                  => '校園生活',
			'Work Life'                    => '職場生活',
			'Magic'                        => '魔法',
			'Superpowers'                  => '超能力',
			'Supernatural'                 => '超自然',
			'Demons'                       => '惡魔',
			'Angels'                       => '天使',
			'Vampires'                     => '吸血鬼',
			'Werewolves'                   => '狼人',
			'Ghosts'                       => '鬼魂',
			'Undead'                       => '不死族',
			'Gods'                         => '神明',
			'Spirits'                      => '精靈/靈魂',
			'Witches'                      => '女巫',
			'Curses'                       => '詛咒',
			'Alchemy'                      => '煉金術',
			'Necromancy'                   => '死靈術',
			'Action'                       => '動作',
			'Martial Arts'                 => '武術',
			'Swordplay'                    => '劍術',
			'Archery'                      => '弓術',
			'Gunfights'                    => '槍戰',
			'Mechs'                        => '機甲',
			'Military'                     => '軍事',
			'War'                          => '戰爭',
			'Battle Royale'                => '大逃殺',
			'Survival'                     => '求生',
			'Tournament'                   => '競技賽',
			'Strategy Game'                => '策略遊戲',
			'Idol'                         => '偶像',
			'Musician'                     => '音樂人',
			'Detective'                    => '偵探',
			'Police'                       => '警察',
			'Samurai'                      => '武士',
			'Ninja'                        => '忍者',
			'Pirate'                       => '海盜',
			'Doctor'                       => '醫生',
			'Teacher'                      => '教師',
			'Chef'                         => '廚師',
			'Athlete'                      => '運動員',
			'Adventurer'                   => '冒險者',
			'Guild'                        => '公會',
			'Siblings'                     => '兄弟姊妹',
			'Twins'                        => '雙胞胎',
			'Master-Servant'               => '主僕關係',
			'Senpai-Kohai'                 => '前輩後輩',
			'Childhood Friends'            => '青梅竹馬',
			'Rivals'                       => '對手',
			'Bromance'                     => '兄弟情誼',
			'Psychological'                => '心理',
			'Trauma'                       => '心理創傷',
			'Mental Illness'               => '精神疾病',
			'Social Commentary'            => '社會批評',
			'Politics'                     => '政治',
			'Philosophy'                   => '哲學',
			'Religion'                     => '宗教',
			'Gore'                         => '血腥暴力',
			'Horror'                       => '恐怖',
			'Ecchi'                        => '輕微色情',
			'Fanservice'                   => '福利',
			'Chibi'                        => '超可愛',
			'Moe'                          => '萌',
			'Cute Girls Doing Cute Things' => '日常萌系',
			'Anthropomorphism'             => '擬人化',
			'Dragons'                      => '龍',
			'Cats'                         => '貓咪',
			'Dogs'                         => '狗狗',
			'Iyashikei'                    => '療癒系',
			'CGDCT'                        => '日常萌系',
			'Music'                        => '音樂',
			'Sports'                       => '運動',
			'Racing'                       => '賽車',
			'Cooking'                      => '料理',
			'Gaming'                       => '遊戲',
			'Card Games'                   => '卡牌遊戲',
			'Mahjong'                      => '麻將',
			'Shounen'                      => '少年',
			'Shoujo'                       => '少女',
			'Seinen'                       => '青年',
			'Josei'                        => '女性向',
			'Mecha'                        => '機器人',
			'Sci-Fi'                       => '科幻',
			'Science Fiction'              => '科幻',
			'Adventure'                    => '冒險',
			'Mystery'                      => '推理',
			'Thriller'                     => '驚悚',
			'Suspense'                     => '懸疑',
			'Drama'                        => '劇情',
			'Family'                       => '家庭',
			'Kids'                         => '兒童',
		];
	}


	// =========================================================================
	// PRIVATE – 匯入鎖（避免同 AniList ID 併發建立重複草稿）
	// =========================================================================

	private function get_import_lock_key( int $anilist_id ): string {
		return 'anime_sync_import_lock_' . $anilist_id;
	}

	private function acquire_import_lock( int $anilist_id, bool $force = false ): string {
		if ( $anilist_id <= 0 ) {
			return '';
		}

		$key      = $this->get_import_lock_key( $anilist_id );
		$existing = get_transient( $key );

		if ( is_array( $existing ) && ! empty( $existing['token'] ) ) {
			$age = time() - absint( $existing['created_at'] ?? 0 );
			if ( ! $force || $age < 30 ) {
				return '';
			}
		}

		$token = wp_generate_uuid4();
		set_transient( $key, [
			'token'      => $token,
			'created_at' => time(),
		], 2 * MINUTE_IN_SECONDS );

		$stored = get_transient( $key );
		if ( is_array( $stored ) && ( $stored['token'] ?? '' ) === $token ) {
			return $token;
		}

		return '';
	}

	private function release_import_lock( int $anilist_id, string $token ): void {
		if ( $anilist_id <= 0 || $token === '' ) {
			return;
		}

		$key    = $this->get_import_lock_key( $anilist_id );
		$stored = get_transient( $key );
		if ( is_array( $stored ) && ( $stored['token'] ?? '' ) === $token ) {
			delete_transient( $key );
		}
	}

	// =========================================================================
	// PRIVATE – 查找現有文章
	// =========================================================================

	private function find_existing( int $anilist_id ): int {
		if ( $anilist_id <= 0 ) return 0;

		$query = new WP_Query( [
			'post_type'      => 'anime',
			'post_status'    => 'any',
			'posts_per_page' => 5,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'     => 'anime_anilist_id',
					'value'   => $anilist_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		if ( count( $query->posts ) > 1 && class_exists( 'Anime_Sync_Error_Logger' ) ) {
			Anime_Sync_Error_Logger::log( 'warning', '偵測到重複 anime_anilist_id 文章', [
				'anilist_id' => $anilist_id,
				'post_ids'   => array_map( 'intval', $query->posts ),
			] );
		}

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}
}
