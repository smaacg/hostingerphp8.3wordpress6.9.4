# CONTEXT.md — Anime Sync Pro 插件開發紀錄

最後更新：2026-05-07
版本：1.1.0

---

## 專案基本資訊

- **插件名稱**：Anime Sync Pro
- **GitHub**：https://github.com/smaacg/anime-sync-pro-2-
- **主分支**：main
- **WordPress 版本**：6.9.4
- **PHP 最低需求**：8.0
- **ACF 版本**：6.8.0（免費版，無 conditional_logic）
- **自訂文章類型**：`anime`
- **網站**：https://dev.weisianacg.com/
- **作者標記**：weixiaoacg

> 本文件涵蓋範圍：插件本體（anime-sync-pro/）。
> 主題（blocksy-child/）不在本文件管轄範圍內。

---

## 1. 檔案結構（plugin 本體）

```
anime-sync-pro/
├── anime-sync-pro.php              主入口 v1.1.0
├── CONTEXT.md
├── anime_map.json                  ID 對應快取（4MB+）
├── admin/
│   ├── class-admin.php             AJAX handlers + 資源載入 + 簡繁轉換 metabox
│   ├── assets/
│   │   ├── css/admin.css
│   │   └── js/admin.js
│   └── pages/
│       ├── dashboard.php
│       ├── import-tool.php
│       ├── logs.php
│       ├── published-list.php
│       ├── review-queue.php
│       └── settings.php
├── includes/
│   ├── class-acf-fields.php        ACF 欄位群組（10 群組）
│   ├── class-api-handler.php       v1.1.0 — 加 anilist_request() helper
│   ├── class-cn-converter.php      OpenCC S2TWP + 字典 fallback
│   ├── class-cron-manager.php      v1.1.0 — 新增 themes_episodes hook
│   ├── class-custom-post-type.php  v1.1.0 — 後台欄位排序修正
│   ├── class-editorial-routing.php v3 — channel taxonomy + rewrite
│   ├── class-error-logger.php      v1.0.3
│   ├── class-id-mapper.php         多層 Bangumi ID 對應
│   ├── class-image-handler.php     v1.1.0 — atomic resize + UA
│   ├── class-import-manager.php    v1.1.0 — taxonomy 中英對照
│   ├── class-installer.php         v1.3.0 — taxonomy seeder 內建
│   ├── class-performance.php       批次 / 快取 / 記憶體工具
│   ├── class-rate-limiter.php      v1.1.0 — Singleton + API 統計
│   ├── class-rating-manager.php    多維度評分 + REST + 7 項 bug 修正
│   ├── class-review-queue.php      審核佇列（gzcompress）
│   ├── class-security.php
│   ├── class-user-status-cron.php  v1.1.0 — UPSERT 統計
│   └── class-user-status-manager.php 觀看狀態 CRUD + REST
└── public/
    ├── class-frontend.php          前台模板載入 + 搜尋擴展 + REST
    ├── assets/
    │   ├── css/anime-single.css
    │   ├── css/public.css
    │   ├── img/providers/          台灣串流平台 icon
    │   └── js/frontend.js
    └── templates/
        ├── single-anime.php        v14.0
        ├── archive-anime.php
        └── archive-series.php
```

> **重要**：`includes/class-youranimes-fetcher.php` **不存在於目前 main 分支**（之前的對話誤植），請勿誤用。

> **重要**：`admin/pages/review-preview.php` **不存在**。審核流程直接在 `review-queue.php` 操作 `post_status=draft` 的 anime 文章。

---

## 2. API 資料來源與 Rate Limit

| 來源 | Endpoint | 用途 | 最小間隔 |
|------|----------|------|----------|
| AniList GraphQL | `https://graphql.anilist.co` | 主資料、人氣、Series tree | 2000ms |
| Bangumi TV | `https://api.bgm.tv/v0/subjects/`、`/episodes` | 中文標題/簡介、Staff/Cast、集數、評分 | 1000ms |
| AnimeThemes | `https://api.animethemes.moe/anime` | OP/ED 主題曲（OGG 音訊、WebM 影片） | 700ms |
| Jikan (MAL) | `https://api.jikan.moe/v4/anime/` | MAL 評分回填、external links bridge | 1200ms |
| Wikipedia ZH | `https://zh.wikipedia.org/w/api.php` | 中文 wiki 連結 | – |
| Wikipedia EN | `https://en.wikipedia.org/api/rest_v1/page/summary/` | 英文摘要 | – |

**統一 User-Agent**：
- AniList helper：`Mozilla/5.0 (compatible; AnimeSyncPro/1.1; +https://anime-sync-pro)`（image-handler 也用同一 UA）
- 其他：`Anime_Sync_API_Handler::USER_AGENT = 'weixiaoacg-Project/1.0 (https://weixiaoacg.com)'`

---

## 3. 自訂 Post Type 與 Taxonomy

**Post Type**：`anime`（slug `anime`，hierarchical=false，supports title/editor/thumbnail/custom-fields/comments，has_archive=true）

**Taxonomy**：

| Slug | 說明 | hierarchical | 備註 |
|------|------|--------------|------|
| `genre` | 類型 | true | seed 27 個中文 term，配合 import-manager 19 項中英對照 |
| `anime_season_tax` | 播出季度 | true | 父=年份、子=`{年份} 春/夏/秋/冬季`；seed 動態範圍 = 當年-3 ~ 當年+1（5 年） |
| `anime_format_tax` | 動畫格式 | true | seed：TV / TV短篇 / 劇場版 / OVA / ONA / 特別篇 / 音樂MV |
| `anime_series_tax` | 系列 | false | slug=romaji sanitized、name=中文、term meta `anime_series_root_id` |
| `anime_studio_tax` | 製作公司 | false | – |
| `post_tag` | 標籤 | – | 與一般文章共用 |
| `channel` | 文章頻道 | false | 綁定 `post`，配合 Editorial Routing |

**channel 允許值**（共 12）：anime / manga / novel / game / vtuber / cosplay / ai-tools / voice-actor / music / merchandise / event / industry

**內容型 category**（共 4）：announcement / news / review / feature
**channelless types**：announcement（即 `/announcement/post-slug/`，其餘走 `/{type}/{channel}/{post-slug}/`）

---

## 4. Meta 欄位

### 識別 ID
| Key | 說明 |
|-----|------|
| `anime_anilist_id` | AniList ID（required=0，避免 wp_insert_post race） |
| `anime_mal_id` | MyAnimeList ID（由 AniList idMal 自動填入） |
| `anime_bangumi_id` | Bangumi ID（多層查找；舊欄位 `bangumi_id` 也會被讀取） |
| `anime_animethemes_id` | AnimeThemes anime.id（純數字） |
| `anime_animethemes_slug` | AnimeThemes slug（fallback；舊欄位 `animethemes_slug` 也會被讀取） |

### 標題
| Key | 來源 |
|-----|------|
| `anime_title_chinese` | Bangumi name_cn → AniList english → romaji（僅在現有為空時 fallback） |
| `anime_title_native` | AniList native（日文） |
| `anime_title_romaji` | AniList romaji（同時作為 post_name slug 來源） |
| `anime_title_english` | AniList english |

### 屬性
`anime_format` / `anime_status` / `anime_season` / `anime_season_year` / `anime_episodes` / `anime_episodes_aired` / `anime_duration` / `anime_start_date` / `anime_end_date` / `anime_studios`（複數，逗號分隔，**注意 key 是複數型 studios**） / `anime_source` / `anime_popularity`

### 評分（**0–100 整數儲存，前台 ÷10 顯示**）
- `anime_score_anilist`（AniList averageScore，原始 0–100）
- `anime_score_mal`（Jikan score × 10）
- `anime_score_bangumi`（Bangumi rating.score × 10）

### JSON 欄位
| Key | 結構 | 來源 |
|-----|------|------|
| `anime_staff_json` | `[{id,name,role,image,source:'bangumi'}]` | Bangumi（**直接取代** AniList，不合併） |
| `anime_cast_json` | `[{id,name,role,image,voice_actors:[{id,name,image}],source}]` | Bangumi（直接取代） |
| `anime_relations_json` | `[{id,type,relation_type,title}]` | AniList |
| `anime_episodes_json` | `[{id,ep,name,name_cn,airdate,comment}]` | Bangumi |
| `anime_themes` | `[{type,sequence,slug,song_title,audio_url,video_url,resolution}]` | AnimeThemes（含 videos.audio） |
| `anime_streaming` | `[{site,url}]` | AniList externalLinks |
| `anime_external_links` | AniList 原始格式 | AniList |
| `anime_next_airing` | `{airingAt,episode}` | AniList |
| `anime_faq_json` | `[{q,a}]` | 手動填寫 |

### 台灣在地化
- `anime_tw_streaming`（checkbox 陣列）
- `anime_tw_streaming_other`（其他平台，逗號分隔文字）
- `anime_tw_streaming_url_{key}`（21 個個別 URL 欄位，見下方 key 列表）
- `anime_tw_distributor` / `anime_tw_distributor_custom` / `anime_tw_broadcast`

**台灣串流平台 key 列表（21 個，由 single-anime.php v14.0 確認）**：
bahamut、hami、myvideo、linetv、friday、ofiii、catchplay、bilibili、ani_one、muse、mighty、ani_mi、netflix、disney、litv、tropicsanime、iqiyi、renta、anipass、amazon、crunchyroll

> ⚠️ checkbox key 與 URL meta key 對應規則：底線分隔（`ani_one`），但有 legacy alias `ani-one` → `ani_one`、`myVideo` → `myvideo`、`line_tv` → `linetv` 由 single-anime.php 自動修正。

### 控制 Meta
| Key | 說明 |
|-----|------|
| `_needs_enrich` | 1 = 待補抓 |
| `_enriched_at` | 最後 enrich 完成時間 |
| `_enrich_retry` | enrich 重試次數（最多 3） |
| `_enrich_failed` | enrich 最終失敗時間 |
| `_import_source` | manual / anilist / cron |
| `_bangumi_id_manually_set` | 1 = 不被覆蓋 |
| `_bangumi_id_pending` | 待自動補抓 Bangumi ID |
| `_series_root_anilist_id` | 系列根 AniList ID |
| `anime_locked_fields` | 鎖定欄位陣列（不被自動更新） |
| `anime_themes_locked_keys` | 主題曲鎖定 key（type+sequence） |
| `anime_episodes_locked_ids` | 集數鎖定 ID |

### 同步時間（**注意：兩處 key 不一致，仍是 known issue**）
| Key | 寫入處 | 讀取處 |
|-----|--------|--------|
| `anime_last_sync` | `import-manager.php` 寫 | – |
| `anime_sync_time` | （目前主流程**沒有寫入**） | `class-custom-post-type.php` 1.1.0 後台列表讀此 key |
| `anime_last_updated` | `import-manager.php` | – |
| `anime_themes_synced_at` | `cron-manager` themes_episodes 任務 | – |
| `anime_episodes_synced_at` | `cron-manager` themes_episodes 任務 | – |

---

## 5. 資料表（由 Anime_Sync_Installer 建立）

| 表名 | 用途 |
|------|------|
| `{prefix}anime_review_queue` | API 資料暫存（gzcompress 壓縮 BLOB） |
| `{prefix}anime_sync_logs` | 錯誤/事件日誌（level: info/warning/error/critical） |
| `{prefix}anime_ratings` | 多維度評分（story/music/animation/voice/overall + weight） |
| `{prefix}anime_user_status` | 觀看狀態（status 0–3、progress、started_at、completed_at、favorited、private、note） |
| `{prefix}anime_user_status_stats` | 每部動畫狀態統計（want/watching/completed/dropped/favorited/total，UPSERT 維護） |

`SEASON_YEARS_RANGE = 5`：每次升級自動補當年新季度 term，不再寫死年份。

---

## 6. REST API

### 6.1 評分系統 — namespace `weixiaoacg/v1`

| Method | Path | 說明 |
|--------|------|------|
| GET | `/ratings/{anime_id}` | 取得統計 + 當前使用者分數 |
| POST | `/ratings/{anime_id}` | 提交/更新評分（**rate limit 5/min**） |
| GET | `/ranking/site?limit=N` (1–50) | 全站加權排行（撈 N×3 後 PHP 重排） |

**參數**：score_story、score_music、score_animation、score_voice 各 1.0–10.0（0.1 步進）。
**Bayesian 加權**：低於 `min_votes=5` 時向全站均值靠近；全站平均 transient 快取 10 分鐘。
**使用者權重**：註冊 ≥30 天且評分 ≥10 部 → 1.5、註冊 <7 天 → 0.5、其他 → 1.0。

### 6.2 觀看狀態 — namespace `weixiaoacg/v1`

| Method | Path | 說明 |
|--------|------|------|
| GET | `/user-status/list` | 個人清單（需登入） |
| GET | `/user-status/{anime_id}` | 單部狀態（未登入回空） |
| POST | `/user-status/{anime_id}` | 更新（action: status/progress/progress_set/favorite/fullclear/note/private） |
| DELETE | `/user-status/{anime_id}` | 移除 |

**寫入 rate limit**：30 次/分鐘。
**狀態值**：want=0, watching=1, completed=2, dropped=3。
**業務規則**：未播出（`anime_status=NOT_YET_RELEASED`）只能設 want / dropped；設為 completed 時自動補滿 progress = `anime_episodes`；progress 上限 = `anime_episodes`（沒有則 9999）。
**全部使用 atomic UPSERT**（`INSERT ... ON DUPLICATE KEY UPDATE`）。

### 6.3 前台資料 — namespace `anime-sync/v1`

由 `Anime_Sync_Frontend::register_rest_routes()` 註冊（給既有前端使用，向後相容）。
JS 端透過 `animeSyncData.restUrl` / `.animeRestUrl` / `.ratingRestUrl` 三個 URL 取用。

---

## 7. Cron 排程（Anime_Sync_Cron_Manager）

| Hook 常數 | 頻率 | Lock TTL | 啟動時間 | 內容 |
|-----------|------|----------|----------|------|
| `HOOK_DAILY_SCORE_UPDATE`（`anime_sync_daily_score_update`） | daily | 1800s | 設定中 `anime_sync_daily_hour`（預設 03:00 UTC） | RELEASING + 90 天內 FINISHED 的評分回填 |
| `HOOK_THEMES_EPISODES_UPDATE`（`anime_sync_themes_episodes_update`） | daily | 1800s | hour+2:30（預設 05:30 UTC） | 當季 RELEASING themes/episodes（**尊重 locked**） |
| `HOOK_WEEKLY_CLEANUP`（`anime_sync_weekly_cleanup`） | weekly（自訂） | – | 每週日 04:00 | 清舊 log |
| `HOOK_UPDATE_MAP`（`anime_sync_update_anime_map`） | weekly | – | 每週一 02:00 | 更新 anime_map.json |
| `HOOK_SEASON_IMPORT`（`anime_sync_season_auto_import`） | single event（手動觸發） | 3600s | – | 季度批次匯入 |

**自訂排程間隔**：`anime_sync_twice_daily`（12h）、`anime_sync_weekly`（7d）

### 觀看狀態統計（Anime_Sync_User_Status_Cron）

| Hook | 頻率 | 內容 |
|------|------|------|
| `anime_sync_recalc_user_status_stats` | every 15 min（`asp_every_15_minutes`） | UPSERT user_status_stats、清孤兒 anime_id |

---

## 8. Singleton / 共享實例

| 類別 | 取得方式 | 注意 |
|------|----------|------|
| `Anime_Sync_Rate_Limiter` | `Anime_Sync_Rate_Limiter::get_instance()` | constructor private、**禁止 `new`**、clone/unserialize 已封鎖 |
| `Anime_Sync_Error_Logger` | static helper：`info()` / `warning()` / `error()` / `critical()` / `static_log()` | 內部會 `new self()` 寫入 `anime_sync_logs` 表 |

### Rate Limiter 用法

```php
$rl = Anime_Sync_Rate_Limiter::get_instance();
$rl->wait_if_needed( 'anilist' );

$resp = wp_remote_post( 'https://graphql.anilist.co', [...] );

if ( wp_remote_retrieve_response_code( $resp ) === 429 ) {
    $wait = $rl->handle_rate_limit_error( $resp, 'anilist' ); // 回傳 5–300 秒
    sleep( $wait );
    $rl->record_stat( 'anilist', 'rate_limited' );
    // 重試…
} else {
    $rl->record_stat( 'anilist', 'success' );
}
$rl->check_remaining( $resp, 'anilist' );  // <10% 配額時警告 + sleep 5s
```

> 1.1.0 起：`handle_rate_limit_error()` **不再內部 sleep**，由呼叫端決定時機（用於 `Anime_Sync_API_Handler::anilist_request()` 重試迴圈）。

---

## 9. AJAX Actions 對照表（Anime_Sync_Admin）

| Action 字串 | PHP Handler | 說明 |
|-------------|-------------|------|
| `anime_sync_import_single` | `handle_ajax_import_single` | 單筆匯入（內含 auto enrich） |
| `anime_sync_enrich_single` | `handle_ajax_enrich_single` | 手動補抓 |
| `anime_sync_query_season` | `handle_ajax_query_season` | 季度查詢 |
| `anime_sync_bulk_action` | `handle_ajax_bulk_action` | 批次操作 |
| `anime_sync_save_bangumi_id` | `handle_ajax_save_bangumi_id` | 儲存 Bangumi ID |
| `anime_sync_update_map` | `handle_ajax_update_map` | 更新 anime_map.json |
| `anime_sync_clear_cache` | `handle_ajax_clear_cache` | 清快取 |
| `anime_sync_clear_logs` | `handle_ajax_clear_logs` | 清日誌 |
| `anime_clear_old_logs` | `handle_ajax_clear_logs` | 同上（向後相容 alias） |
| `anime_sync_analyze_series` | `handle_ajax_analyze_series` | 系列分析 |
| `anime_sync_import_series` | `handle_ajax_import_series` | 系列批次匯入 |
| `anime_sync_popularity_ranking` | `handle_ajax_popularity_ranking` | 人氣排行 |
| **`anime_resync_bangumi`** | `handle_ajax_resync_bangumi` | **注意：無 `_sync_` 中綴** |
| `anime_sync_scan_series_gaps` | `handle_ajax_scan_series_gaps` | 系列缺漏掃描 |
| `anime_sync_convert_post` | `ajax_convert_post_to_tw` | 簡繁轉換 metabox |

### 觀看狀態手動重算（Anime_Sync_User_Status_Cron）

| Action | Handler | Nonce |
|--------|---------|-------|
| `asp_recalc_user_status_stats` | `ajax_manual_recalc` | `asp_recalc_stats` |

### YourAnimes 同步（**目前 main 無此檔案**）

之前提到的 `asp_sync_youranimes` 在 main 分支不存在。如未來實作，nonce 規則為 `asp_sync_youranimes_{post_id}`。

---

## 10. Nonce 規範

| 用途 | 建立 | 驗證 |
|------|------|------|
| 後台主流程 AJAX | `wp_create_nonce('anime_sync_admin_nonce')` | `check_ajax_referer('anime_sync_admin_nonce', 'nonce')` |
| 簡繁轉換 metabox | `wp_create_nonce('anime_sync_nonce')` | `check_ajax_referer('anime_sync_nonce', 'nonce')` |
| 觀看狀態重算 | `asp_recalc_stats` | – |
| 前台 REST | `wp_create_nonce('wp_rest')`（`animeSyncData.nonce`） | – |

**JS 端取值**：`animeSyncAdmin.nonce`、`animeSyncAdmin.actions.{key}`、`animeSyncAdmin.i18n.{key}`（admin.js 使用 helper `t(key, fallback)`）。

> ⚠️ `anime-sync-pro.php` **不可**重複註冊 `wp_ajax_anime_resync_bangumi` 或 `admin_enqueue_scripts`，統一由 `Anime_Sync_Admin` 建構子處理。

---

## 11. animeSyncAdmin localize 物件

```php
wp_localize_script( 'anime-sync-admin', 'animeSyncAdmin', [
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'anime_sync_admin_nonce' ),
    'actions' => [ /* PHP 端真實 action 名稱對照表 */ ],
    'i18n'    => [ /* 所有訊息字串 */ ],
] );
```

JS 一律走 `A.import_single` / `A.query_season` 而非硬編碼字串；i18n 一律 `t('key','fallback')`。

---

## 12. animeSyncData localize 物件（前台）

```php
wp_localize_script( 'anime-sync-frontend', 'animeSyncData', [
    'restUrl'       => esc_url_raw( rest_url( 'anime-sync/v1/' ) ),
    'animeRestUrl'  => esc_url_raw( rest_url( 'anime-sync/v1/' ) ),
    'ratingRestUrl' => esc_url_raw( rest_url( 'weixiaoacg/v1/' ) ),
    'nonce'         => wp_create_nonce( 'wp_rest' ),
    'debug'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
] );
```

`enqueue_assets()` 觸發條件：`is_singular('anime')` || `is_post_type_archive('anime')` || `is_tax(['genre','anime_season_tax','anime_format_tax','anime_series_tax','anime_studio_tax'])` || (`is_search()` && `post_type=anime`)。

CSS / JS 版本號使用 `filemtime()` 取代 `time()`，避免每次都 cache miss。

---

## 13. anime-sync-pro.php 載入順序

1. **constants**（VERSION 1.1.0、DIR、URL、BASENAME）
2. **autoloader**：`Anime_Sync_*` → `class-*.php`（搜尋 includes/、admin/、public/）
3. **`init` priority 10**：register_post_type + 5 個 taxonomy
4. **`init` priority 99**：`flush_rewrite_rules`（如有 `anime_sync_flush_rewrite` option）+ `Anime_Sync_Installer::run_pending_seed()`
5. **3.5 段**：圖片尺寸最佳化（停用 medium_large / 1536 / 2048，由常數 `ANIME_SYNC_DISABLE_LARGE_SIZES` 控制，預設 true）
6. **`activation_hook`**：installer.activate() + cron_manager.activate() + 設 `anime_sync_flush_rewrite=1`
7. **`deactivation_hook`**：cron_manager.deactivate() + user_status_cron.unschedule() + installer.deactivate() + flush_rewrite_rules
8. **`plugins_loaded`**：
   - **6A** 不依賴 ACF：`Editorial_Routing` / `Rating_Manager` / `User_Status_Manager` / `User_Status_Cron`（**前端評分與追蹤系統在 ACF 缺失時仍可用**）
   - **6C** `Installer::maybe_upgrade()`
   - **6B** ACF 檢查；缺 ACF 時跳出 admin notice 並 return
   - 建立 `ACF_Fields` / `Frontend`
   - `is_admin() || DOING_CRON` 時建立：`Rate_Limiter::get_instance()` → `ID_Mapper` → `CN_Converter` → `API_Handler` → `Import_Manager` → `Admin`（**無條件 if `is_admin() && class_exists('Anime_Sync_Admin')`**）→ `Cron_Manager` → `Custom_Post_Type`
   - 註冊 `anime_sync_enrich_post` action（含 3 次指數退避：HOUR × 4^(retry-1)）
9. **`save_post_anime` priority 20**：`post_title` → `anime_title_chinese`（**僅在 meta 為空時 fallback**，避免覆蓋人工編輯）

---

## 14. Bangumi ID 多層查找（Anime_Sync_ID_Mapper）

| Layer | 來源 | 條件 |
|-------|------|------|
| 0 | WP post meta `anime_bangumi_id` / `bangumi_id` | post_id > 0 |
| -1 | `STATIC_BGM_MAP` 靜態表 | anilist_id |
| 0.5 | `al_index.json`（AniList ID → Bangumi ID） | anilist_id |
| 1 | `mal_index.json`（anime-offline-database） | mal_id + 年份/集數驗證 |
| 1.5 | BangumiExtLinker mal_id | mal_id + 年份差 ≤ 1 |
| 1.6 | BangumiExtLinker 日文名 | title_native + season_year |
| 1.7 | Jikan external links（`/anime/{id}/external`） | mal_id |
| 1.8 | AniDB 橋接 | externalLinks 含 anidb.net |
| 2 | AniList externalLinks → bgm.tv / bangumi.tv | URL 正則 |
| 3 | Bangumi 搜尋 + 模糊比對 | 相似度 ≥ 45%（fallback） |

匹配成功會**自動寫入 post meta** 並清除 `_bangumi_id_pending`（ACA 修正）。

對應檔案：
- `wp-content/uploads/anime-sync-pro/anime_map.json`（manami-project）
- `mal_index.json` / `al_index.json` / `name_cache.json`
- `bgm_ext_mal_index.json` / `bgm_ext_name_index.json` / `bgm_ext_anidb_index.json`
- 對應 meta 檔：`anime_map_meta.json` / `bgm_ext_meta.json`

---

## 15. Resync Bangumi 流程（已驗證可用）

```
ACF 欄位填 Bangumi ID
  ↓
admin.js 啟用「🔄 重新同步 Bangumi 資料」按鈕
  ↓
$.ajax POST → action: 'anime_resync_bangumi'（注意：非 anime_sync_resync_bangumi）
  ↓
Anime_Sync_Admin::handle_ajax_resync_bangumi()
  → check_ajax_referer('anime_sync_admin_nonce','nonce')
  → new Anime_Sync_API_Handler()->ajax_resync_bangumi($post_id, $bangumi_id)
  ↓
寫入 meta：anime_title_chinese / anime_synopsis_chinese / anime_cover_image
       / anime_score_bangumi / anime_staff_json / anime_cast_json
       / anime_episodes_json / anime_last_sync_time
  → 尊重 anime_locked_fields
  ↓
{"success":true,"data":{"message":"✅ 同步完成","updated":[...]}}
  ↓
JS 1.5 秒後 location.reload()
```

---

## 16. import_and_enrich 流程

由 `Anime_Sync_Admin::import_and_enrich()` 統一執行（所有匯入入口都走這裡）：

1. `import_manager->import_single($anilist_id, null, $source, ['force' => …])`
   - 取得 import lock（防同時匯入同一 ID）
   - 寫入 / 更新文章（**標題：更新時優先保留現有 post_title**）
   - 儲存 meta、taxonomy（genre 中英對照、season 動態補父年份、format 中文化、studio）
   - **首次匯入** → `apply_first_import_locks()` 預設鎖定欄位
   - 設定 featured image
   - **依 post_id 尾數錯開 enrich 排程**：`delay = 60 + (post_id % 40) * 90` 秒（最多 60 分鐘分散）
   - 釋放 lock
2. `wp_cache_flush()`
3. 寫入 `anime_mal_id`（如有）
4. `delete_post_meta($post_id, '_enriched_at')`
5. `new Anime_Sync_API_Handler()->enrich_anime_data($post_id)`
6. `delete_transient('anime_sync_series_gaps')`

### enrich 重試機制（anime-sync-pro.php 第 6 段）

```
失敗 → _enrich_retry++ → wp_schedule_single_event(time + HOUR × 4^(retry-1))
retry ≥ 3 → update _enrich_failed = now，停止重試
```

---

## 17. AniList Request Helper（v1.1.0 新）

`Anime_Sync_API_Handler::anilist_request()`（private）：

- 統一處理 `wp_remote_post`
- timeout：主 query / popularity = 15s；relations / node_data = 12s
- 429 → 讀 `Retry-After` / `X-RateLimit-Reset` → `handle_rate_limit_error()` → sleep → 重試
- 重試上限 3 次
- 每次呼叫寫入 `anime_sync_api_stats` option（`success` / `failed` / `rate_limited` / `retry`）

`fetch_anilist_data` / `fetch_anilist_relations` / `fetch_anilist_node_data` / `fetch_anilist_popularity` 4 處統一改用此 helper。

### API 統計 option 格式

```json
{
  "anilist":     { "success": 1240, "failed": 3, "rate_limited": 12, "retry": 9 },
  "jikan":       { "success": 850,  "failed": 1, "rate_limited": 4,  "retry": 4 },
  "bangumi":     { "success": 770,  "failed": 0, "rate_limited": 0,  "retry": 0 },
  "animethemes": { "success": 620,  "failed": 2, "rate_limited": 1,  "retry": 1 }
}
```

> 規模 > 1500 部時建議改為「記憶體累加 + shutdown 一次性寫入」（已在 record_stat 註解標明升級時機）。

---

## 18. 圖片處理（Anime_Sync_Image_Handler v1.1.0）

| 常數 | 值 |
|------|----|
| `COVER_WIDTH` × `COVER_HEIGHT` | 460 × 651 |
| `VALIDATE_TIMEOUT` | 5s（H4：8 → 5） |
| `DOWNLOAD_TIMEOUT` | 8s（O4：15 → 8） |
| `HTTP_USER_AGENT` | `Mozilla/5.0 (compatible; AnimeSyncPro/1.1; +https://anime-sync-pro)` |

三種模式由 `anime_sync_image_method` option 決定：
- **api_url**（預設）：只記原始 URL，不下載
- **media_library**：下載 → sideload → set_post_thumbnail → resize（**atomic write**：寫 `.tmp` → rename）→ 重新生成 metadata → 清 intermediate sizes
- **cdn**：建構 imgproxy / Cloudflare URL（base 為空時 fallback 原 URL）

resize 失敗保留原檔（前台 CSS aspect-ratio 救援）。所有 log 改走 `Anime_Sync_Error_Logger`。

---

## 19. wpDiscuz 留言設定

1. Settings → wpDiscuz → Forms → Post Types 勾選 `anime`
2. `single-anime.php` 已內含 `comments_template()`（v14.0）

---

## 20. 已知問題

| # | 問題 | 狀態 |
|---|------|------|
| 1 | 既有動漫的 `anime_themes` 為空者，需重新 enrich（或等每日 themes_episodes cron） | ⚠️ 需手動觸發或等排程 |
| 2 | `_enriched_at` 已設會擋重 enrich 回傳 `already_enriched` | ⚠️ 已知限制；`delete_post_meta` 後可重跑 |
| 3 | Bangumi 找不到對應時，Staff/Cast 維持 AniList 英文 | ✅ 符合設計 |
| 4 | `anime_tw_streaming_url_ani_one`（底線）對應 checkbox key `ani-one`（連字號） | ✅ single-anime.php v14.0 已用 legacy alias 修正 |
| 5 | 系列 term 若以中文 slug 建立，需手動更新為 romaji slug | ⚠️ 需手動處理 |
| 6 | `sort_series_archive()` 必須用 `$query->is_tax()` 而非全域 `is_tax()` | ✅ 已修正 |
| 7 | 匯入後需清除 `anime_sync_series_gaps` transient | ✅ 已修正（import_and_enrich 內） |
| 8 | `handle_ajax_scan_series_gaps()` 原本用錯誤 key | ✅ 已修正 |
| 9 | admin.js Resync Bangumi handler 在 IIFE 閉包外 | ✅ 已修正 |
| 10 | 主檔 Admin 實例化條件含 `&& $import_manager` | ✅ 已修正（無條件） |
| 11 | 瀏覽器快取舊 admin.js | ✅ 已用 `filemtime()` |
| 12 | Bangumi ID 對照表不含所有作品 | ⚠️ 多層 fallback；手動填入會寫 `_bangumi_id_manually_set=1` 不被覆蓋 |
| 13 | **`anime_sync_time` 與 `anime_last_sync` meta key 不一致**：custom-post-type 1.1.0 讀 `anime_sync_time`，但 import-manager 寫的是 `anime_last_sync` | ⚠️ **後台「上次 API 同步時間」欄位實際仍會空白**，需擇一統一（建議 import-manager 也寫 `anime_sync_time`） |
| 14 | Rate Limiter transient 寫入非原子，極端並發可能多 sleep（可接受） | ⚠️ 已知限制 |
| 15 | user_status_stats 孤兒 anime_id 靠每 15 分鐘 cron 清 | ✅ 已實作 UPSERT + DELETE LEFT JOIN |
| 16 | 評分公式變更後，舊資料需重算 | ⚠️ 待提供 admin 工具 |
| 17 | CDN base 未設時影像走原 URL，無 imgproxy 變換 | ✅ fallback 已實作 |
| 18 | OpenCC 未安裝時 fallback 字典精度較低 | ⚠️ 設計權衡 |
| 19 | Editorial Routing 切換 channel 後舊 URL 301 redirect，注意 SEO | ✅ canonical redirect 已加 preview / password / customizer 跳過 |
| 20 | review-queue 為直接掃 `post_type=anime, post_status=draft` 的後台頁，**而非**讀 `anime_review_queue` 表（該表是 import 中繼） | ✅ 符合設計，但容易誤解 |

---

## 21. Todo / 觀察清單

優先序高：
- 修正 #13：統一 `anime_sync_time` / `anime_last_sync` meta key
- 評分系統 admin 統計儀表板（投票分布、加權前後對比）
- User Status 匯出 CSV / API token

中：
- AnimeThemes 多 OP/ED 顯示 UI 改善
- Series tree 視覺化
- 評分公式變更後的歷史資料重算工具

低：
- imgproxy preset 切換 UI
- Cron schedule 後台可視化編輯

---

## 22. 部署驗證清單

1. `php -l` 對所有 .php 語法檢查
2. 前台 anime archive / single 無 fatal、無 PHP notice
3. 後台 anime 列表所有自訂欄位正確顯示且可排序
4. 匯入測試：review_queue 寫入 → enrich 成功 → meta 完整
5. `get_option('anime_sync_api_stats')` 數值上升（success 增加）
6. user_status_stats 在 15 分鐘 cron 後正確更新（無孤兒）
7. `anime_sync_logs` 表無新 critical
8. 故意觸發 429 → log 顯示 `rate_limited` + retry
9. `wp cron event list` 含 `anime_sync_themes_episodes_update`
10. 評分提交 5 次後第 6 次回 429（rate limit 生效）
11. 觀看狀態 30 次內可寫，第 31 次回 429
12. CSS / JS 版本號跟 `filemtime` 對齊
13. 部署後 24 小時：API 統計、log 錯誤率、cron 是否準時、評分 / 觀看狀態提交是否正常
