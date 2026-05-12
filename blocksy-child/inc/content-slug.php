<?php
/**
 * 文章 Slug 處理：Gemini AI 翻譯 + 中文防呆
 *
 * @package weixiaoacg
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   Gemini Slug Helper
   ============================================================ */

/**
 * 呼叫 Gemini API 將中文標題翻譯成英文 slug
 *
 * @param string $title 文章標題
 * @return string|false 英文 slug 或 false
 */
function weixiaoacg_gemini_slug( $title ) {
    if ( empty( $title ) ) return false;

    // 快取：相同標題 24 小時內不重複呼叫
    $cache_key = 'weixiaoacg_slug_' . md5( $title );
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    // 取 API Key（建議在 wp-config.php 定義）
    $api_key = defined( 'WEIXIAOACG_GEMINI_API_KEY' ) ? WEIXIAOACG_GEMINI_API_KEY : '';
    if ( empty( $api_key ) ) {
        error_log( '[weixiaoacg] Gemini API Key 未設定' );
        return false;
    }

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
    $prompt   = "Translate this Chinese article title into a short, SEO-friendly English URL slug (lowercase, words separated by hyphens, no special characters, max 60 chars). Only output the slug itself without any explanation.\n\nTitle: {$title}";

    $response = wp_remote_post( $endpoint, [
        'timeout' => 15,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode([
            'contents' => [[
                'parts' => [[ 'text' => $prompt ]],
            ]],
            'generationConfig' => [
                'temperature'     => 0.2,
                'maxOutputTokens' => 60,
            ],
        ]),
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[weixiaoacg] Gemini API 錯誤：' . $response->get_error_message() );
        return false;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $slug = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $slug = trim( $slug );
    $slug = sanitize_title( $slug );

    if ( empty( $slug ) ) return false;

    // 快取 24 小時
    set_transient( $cache_key, $slug, DAY_IN_SECONDS );
    return $slug;
}

/* ============================================================
   wp_insert_post_data：插入/更新文章時處理 slug
   ============================================================ */
add_filter( 'wp_insert_post_data', function( $data, $postarr ) {
    // 只處理 post 類型
    if ( $data['post_type'] !== 'post' ) return $data;

    // 草稿/auto-draft 不處理
    if ( in_array( $data['post_status'], [ 'auto-draft', 'inherit' ], true ) ) return $data;

    // 取分類
    $post_id = $postarr['ID'] ?? 0;
    $cats    = [];
    if ( $post_id ) {
        $terms = wp_get_post_categories( $post_id );
        foreach ( $terms as $tid ) {
            $term = get_term( $tid, 'category' );
            if ( $term && ! is_wp_error( $term ) ) $cats[] = $term->slug;
        }
    }

    // 從 $postarr 補抓（Gutenberg 草稿時序問題）
    if ( empty( $cats ) && ! empty( $postarr['post_category'] ) ) {
        foreach ( (array) $postarr['post_category'] as $tid ) {
            $term = get_term( $tid, 'category' );
            if ( $term && ! is_wp_error( $term ) ) $cats[] = $term->slug;
        }
    }

    $title = $data['post_title'];
    $slug  = $data['post_name'];

    // ID 系列分類（announcement, news）：用日期 slug
    if ( array_intersect( $cats, WEIXIAOACG_ID_CATS ) ) {
        $data['post_name'] = date( 'Ymd-His' ) . '-' . wp_rand( 100, 999 );
        return $data;
    }

    // LLM 系列分類（review, feature）：用 Gemini 翻譯
    $need_gemini = (bool) array_intersect( $cats, WEIXIAOACG_LLM_CATS );

    // 強制條件：slug 含中文也強制送 Gemini（避免中文 URL）
    if ( ! $need_gemini && preg_match( '/[\x{4e00}-\x{9fff}]/u', $slug ) ) {
        $need_gemini = true;
    }

    if ( $need_gemini ) {
        $new_slug = weixiaoacg_gemini_slug( $title );
        if ( $new_slug ) {
            $data['post_name'] = $new_slug;
        }
    }

    return $data;
}, 10, 2 );

/* ============================================================
   wp_unique_post_slug：避免中文 slug 流出
   ============================================================ */
add_filter( 'wp_unique_post_slug', function( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
    if ( $post_type !== 'post' ) return $slug;

    // 仍含中文 → 用 post-{ID} 兜底
    if ( preg_match( '/[\x{4e00}-\x{9fff}]/u', $slug ) ) {
        return 'post-' . $post_id;
    }
    return $slug;
}, 10, 6 );

/* ============================================================
   Admin Notice：slug 異常警示
   ============================================================ */
add_action( 'admin_notices', function() {
    global $post;
    if ( ! $post || $post->post_type !== 'post' ) return;
    if ( ! preg_match( '/[\x{4e00}-\x{9fff}]/u', $post->post_name ) ) return;

    echo '<div class="notice notice-warning"><p><strong>⚠️ 警告：</strong>此文章 slug 仍含中文（<code>' . esc_html( $post->post_name ) . '</code>），請檢查分類設定或 Gemini API。</p></div>';
} );
