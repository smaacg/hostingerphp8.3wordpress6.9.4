# CONTEXT.md — Anime Sync Pro 插件開發紀錄

最後更新：2026-04-16

---

## 專案基本資訊

- **插件名稱**：Anime Sync Pro
- **GitHub**：https://github.com/weixiaoacg/anime-sync-pro-2-
- **WordPress 版本**：依主機環境（6.9.4）
- **ACF 版本**：6.8.0（免費版，不支援 conditional_logic）
- **自訂文章類型**：`anime`
- **網站**：https://dev.weisianacg.com/

---

## 檔案結構

anime-sync-pro/ ├── anime-sync-pro.php ├── includes/ │ ├── class-api-handler.php ← 核心 API 處理 │ ├── class-acf-fields.php ← ACF 欄位定義 + Resync Meta Box │ ├── class-import-manager.php ← 匯入管理 │ ├── class-cn-converter.php ← 簡繁轉換 │ ├── class-cron-manager.php ← 排程 │ ├── class-custom-post-type.php ← CPT 定義 │ ├── class-error-logger.php ← 錯誤記錄 │ ├── class-id-mapper.php ← ID 對應 │ ├── class-image-handler.php ← 圖片處理 │ ├── class-installer.php ← 安裝器 │ ├── class-performance.php ← 效能 │ ├── class-rate-limiter.php ← API 速率限制 │ ├── class-review-queue.php ← 審核佇列 │ └── class-security.php ← 安全 ├── admin/ │ ├── class-admin.php ← 後台 AJAX handlers + 資源載入 │ ├── assets/ │ │ ├── js/admin.js │ │ └── css/admin.css │ └── pages/ │ ├── import-tool.php │ ├── dashboard.php │ ├── logs.php │ ├── published-list.php │ ├── review-preview.php │ ├── review-queue.php │ └── settings.php └── public/ ├── class-frontend.php ├── assets/ │ ├── css/anime-single.css │ └── js/frontend.js └── templates/ ├── single-anime.php ← v12.1 ├── archive-anime.php └── archive-series.php

Copy
---

## API 資料來源

| 來源 | 用途 | Endpoint |
|------|------|----------|
| AniList（GraphQL） | 主要動漫資料、Staff/Cast fallback | https://graphql.anilist.co |
| Bangumi TV | 中文標題、簡介、Staff、Cast、集數（主要） | https://api.bgm.tv/v0/ |
| AnimeThemes | OP/ED 音訊（OGG）與影片（WebM） | https://api.animethemes.moe/anime |
| Jikan（MAL） | MAL 評分 | https://api.jikan.moe/v4/anime/ |
| Wikipedia ZH/EN | Wikipedia 連結 | https://zh.wikipedia.org/w/api.php |

---

## 重要 Meta 欄位清單

### 基本資訊
| Meta Key | 說明 |
|----------|------|
| `anime_anilist_id` | AniList ID |
| `anime_mal_id` | MyAnimeList ID |
| `anime_bangumi_id` | Bangumi ID |
| `anime_animethemes_id` | AnimeThemes slug |
| `anime_title_chinese` | 繁體中文標題（Bangumi） |
| `anime_title_native` | 日文原名 |
| `anime_title_romaji` | Romaji |
| `anime_title_english` | 英文標題 |
| `anime_format` | TV/MOVIE/OVA 等 |
| `anime_status` | FINISHED/RELEASING 等 |
| `anime_season` | WINTER/SPRING/SUMMER/FALL |
| `anime_season_year` | 播出年份 |
| `anime_episodes` | 總集數 |
| `anime_duration` | 每集時長（分鐘） |
| `anime_start_date` | 開始日期（YYYY-MM-DD） |
| `anime_end_date` | 結束日期 |
| `anime_studios` | 製作公司（逗號分隔） |
| `anime_source` | 原作來源 |
| `anime_popularity` | AniList 人氣數值 |

### 評分（重要：儲存格式）
| Meta Key | 儲存格式 | 前台顯示 |
|----------|----------|----------|
| `anime_score_anilist` | 0–100（AniList 原始） | 除以 10 → 0–10 |
| `anime_score_mal` | 0–100（×10 儲存） | 除以 10 → 0–10 |
| `anime_score_bangumi` | 0–100（×10 儲存） | 除以 10 → 0–10 |

### JSON 欄位
| Meta Key | 結構 | 來源 |
|----------|------|------|
| `anime_staff_json` | `[{id, name, role, image, source:'bangumi'}]` | Bangumi（取代 AniList） |
| `anime_cast_json` | `[{id, name, role, image, voice_actors:[{id,name,image}], source:'bangumi'}]` | Bangumi（取代 AniList） |
| `anime_relations_json` | `[{id, type, relation_type, title}]` | AniList |
| `anime_episodes_json` | `[{id, ep, name, name_cn, airdate, comment}]` | Bangumi |
| `anime_themes` | `[{type, sequence, slug, song_title, audio_url, video_url, resolution}]` | AnimeThemes |
| `anime_streaming` | `[{site, url}]` | AniList externalLinks |
| `anime_external_links` | AniList 原始格式 | AniList |
| `anime_next_airing` | `{airingAt: Unix時間戳, episode: 集數}` | AniList |

### 台灣在地資訊
| Meta Key | 說明 |
|----------|------|
| `anime_tw_streaming` | 陣列，勾選的平台 key |
| `anime_tw_streaming_other` | 其他平台（逗號分隔文字） |
| `anime_tw_streaming_url_{key}` | 各平台個別連結（16 個） |
| `anime_tw_distributor` | 代理商 key |
| `anime_tw_distributor_custom` | 自訂代理商名稱 |
| `anime_tw_broadcast` | 播出時間文字 |

### FAQ
| Meta Key | 說明 |
|----------|------|
| `anime_faq_json` | 手動 JSON：`[{"q":"問題","a":"答案"}]`，空值則不輸出 FAQ |

### 控制 Meta
| Meta Key | 說明 |
|----------|------|
| `_needs_enrich` | 1 = 待補抓（enrich） |
| `_enriched_at` | 最後 enrich 時間 |
| `_import_source` | manual/anilist/cron |
| `_bangumi_id_manually_set` | 1 = 手動設定，不被覆蓋 |
| `_series_root_anilist_id` | 系列根源 AniList ID |
| `anime_locked_fields` | 鎖定不被自動更新的欄位陣列 |

---

## 分類法（Taxonomy）

| Slug | 說明 |
|------|------|
| `genre` | 動漫類型（動作/愛情等） |
| `anime_season_tax` | 播出季度（2024 Spring 等） |
| `anime_format_tax` | 動漫格式（tv/movie 等） |
| `anime_series_tax` | 系列（進擊的巨人系列等） |
| `post_tag` | WordPress 標籤 |

**`anime_series_tax` 命名規則：**
- Term **slug** = romaji sanitized（e.g., `fate-series`）
- Term **name** = 中文名稱（e.g., `Fate 系列`）
- Term meta `anime_series_root_id` = 系列根 AniList ID（integer）

---

## AJAX Actions 對照表

| Action 字串 | PHP Handler | 說明 |
|---|---|---|
| `anime_sync_import_single` | `handle_ajax_import_single()` | 單筆匯入（自動 enrich） |
| `anime_sync_enrich_single` | `handle_ajax_enrich_single()` | 手動補抓 |
| `anime_sync_query_season` | `handle_ajax_query_season()` | 季度查詢（最多 10 頁 / 500 筆） |
| `anime_sync_bulk_action` | `handle_ajax_bulk_action()` | 批次操作 |
| `anime_sync_save_bangumi_id` | `handle_ajax_save_bangumi_id()` | 儲存 Bangumi ID |
| `anime_sync_update_map` | `handle_ajax_update_map()` | 更新 ID 對照表 |
| `anime_sync_clear_cache` | `handle_ajax_clear_cache()` | 清除快取 |
| `anime_sync_clear_logs` | `handle_ajax_clear_logs()` | 清除日誌 |
| `anime_sync_analyze_series` | `handle_ajax_analyze_series()` | 系列分析（含 series_romaji） |
| `anime_sync_import_series` | `handle_ajax_import_series()` | 系列匯入（接收 series_romaji） |
| `anime_sync_popularity_ranking` | `handle_ajax_popularity_ranking()` | 人氣排行 |
| `anime_resync_bangumi` | `handle_ajax_resync_bangumi()` | 重新同步 Bangumi ✅ |
| `anime_sync_scan_series_gaps` | `handle_ajax_scan_series_gaps()` | 系列缺漏掃描（6h 快取） |
| `anime_sync_get_stats` | dashboard stats | 儀表板統計 |

---

## Nonce 規範（統一，勿再修改）

| 位置 | 值 |
|---|---|
| `wp_localize_script` 建立 | `wp_create_nonce('anime_sync_admin_nonce')` |
| PHP handler 驗證 | `check_ajax_referer('anime_sync_admin_nonce', 'nonce')` |
| JS 送出 | `animeSyncAdmin.nonce` |

> ⚠️ `anime-sync-pro.php` **不可**再重複註冊 `wp_ajax_anime_resync_bangumi`
> 或 `admin_enqueue_scripts`，統一由 `Anime_Sync_Admin` 建構子處理。

---

## animeSyncAdmin 物件內容

```php
wp_localize_script( 'anime-sync-admin', 'animeSyncAdmin', [
    'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
    'nonce'         => wp_create_nonce( 'anime_sync_admin_nonce' ),
    'syncing'       => '同步中，請稍候…',
    'sync_success'  => '✅ 同步完成，頁面即將重新整理…',
    'error_no_id'   => '請先填入 Bangumi ID 並儲存文章。',
    'network_error' => '網路錯誤，請重試。',
] );
⚠️ JS 內使用 animeSyncAdmin.syncing（非 animeSyncAdmin.i18n.syncing）

enqueue_admin_assets() 載入條件
Copy$is_plugin_page = strpos( $hook, 'anime-sync' ) !== false;
$is_anime_edit  = in_array( $hook, [ 'post.php', 'post-new.php' ], true )
                  && ( get_post_type() === 'anime'
                       || sanitize_key( $_GET['post_type'] ?? '' ) === 'anime' );
版本號使用 time() 避免瀏覽器快取：

Copywp_enqueue_script( 'anime-sync-admin', $url . 'admin/assets/js/admin.js', [ 'jquery' ], time(), true );
Resync Bangumi 完整流程（✅ 已驗證正常）
CopyACF 欄位填入 Bangumi ID
    ↓
JS 監聽 input/change → 啟用按鈕
    ↓
點擊「🔄 重新同步 Bangumi 資料」
    ↓
admin.js 讀取：
  postId    = $('#post_ID').val()
  bangumiId = $('#acf-field_anime_bangumi_id').val()
    ↓
$.ajax POST → action: 'anime_resync_bangumi'
    ↓
handle_ajax_resync_bangumi()
  check_ajax_referer('anime_sync_admin_nonce', 'nonce')
  new Anime_Sync_API_Handler()->ajax_resync_bangumi($post_id, $bangumi_id)
    ↓
寫入 post_meta：
  anime_title_chinese, anime_synopsis_chinese,
  anime_cover_image, anime_score_bangumi,
  anime_staff_json, anime_cast_json,
  anime_episodes_json, anime_last_sync_time
    ↓
{"success":true,"data":{"message":"✅ 同步完成","updated":[...]}}
    ↓
JS 顯示成功 → 1.5 秒後 location.reload()
anime-sync-pro.php 關鍵注意事項
第 6-4 段 Admin 實例化條件必須為：

Copy// ✅ 正確：不依賴 $import_manager 是否存在
if ( is_admin() && class_exists( 'Anime_Sync_Admin' ) ) {
    new Anime_Sync_Admin( $import_manager );
}
Copy// ❌ 錯誤：$import_manager 為 null 時 Admin 不會被建立，所有 AJAX handler 失效
if ( is_admin() && $import_manager && class_exists( 'Anime_Sync_Admin' ) ) {
    new Anime_Sync_Admin( $import_manager );
}
import_and_enrich() 流程
所有匯入方式統一走此 helper：

import_manager->import_single($anilist_id)
寫入 anime_mal_id
清除 _enriched_at 鎖
new Anime_Sync_API_Handler()->enrich_anime_data($post_id)
清除 anime_sync_series_gaps transient
wpDiscuz 留言設定（✅ 已完成）
Settings → wpDiscuz → Forms → Post Types 需勾選 anime
single-anime.php 在 FAQ endif 之後、</main> 之前加入：
Copy<?php if ( comments_open() || get_comments_number() ) : ?>
  <div class="asd-section asd-comments-wrap">
    <?php comments_template(); ?>
  </div>
<?php endif; ?>
已知問題與狀態
編號	問題	狀態
1	現有已入庫動漫的 anime_themes 需重新 enrich 才能取得 audio_url	⚠️ 需手動觸發 enrich
2	_enriched_at 存在時 enrich_single() 回傳 already_enriched	⚠️ 已知限制，手動刪除 meta 可重跑
3	Bangumi API 無法找到對應時，Staff/Cast 維持 AniList 英文資料	✅ 符合設計
4	anime_tw_streaming_url_ani_one（底線）對應 checkbox key ani-one（連字號）	⚠️ 注意對應關係
5	現有系列 term 若以中文建立 slug，需至 WP 後台手動更新為 romaji slug	⚠️ 需手動處理
6	sort_series_archive() 必須使用 $query->is_tax() 而非全域 is_tax()	✅ 已修正
7	成功匯入後需清除 anime_sync_series_gaps transient	✅ 已修正（import_and_enrich 內）
8	handle_ajax_scan_series_gaps() 原本用錯誤 key，應為 relation_type / id	✅ 已修正
9	admin.js Resync Bangumi handler 被放在 IIFE 閉包外導致 $ is not a function	✅ 已修正（移回閉包內）
10	anime-sync-pro.php Admin 實例化條件含 && $import_manager 導致 AJAX 失效	✅ 已修正（移除該條件）
11	瀏覽器快取舊版 admin.js 導致修正後仍無反應	✅ 已修正（version 改為 time()）
12	Bangumi ID 對照表不含所有作品，需手動填入	⚠️ 手動儲存後寫入 _bangumi_id_manually_set=1 不被覆蓋
