# Anime Sync Pro 2 — 專案總覽 CONTEXT.md

> 最後更新：2026-05-15
> 文件版本：2.0.0（Phase 3 大重構後完整版）
> 維運：微笑動漫 https://smile-acg.com
> 線上站：https://dev.weisianacg.com/
> GitHub：https://github.com/smaacg/hostingerphp8.3wordpress6.9.4

---

## 0. 環境

| 項目 | 版本 |
|---|---|
| Hosting | Hostinger |
| PHP | 8.3 |
| WordPress | 6.9.4 |
| 父主題 | Blocksy |
| 子主題 | weixiaoacg（blocksy-child） |
| 必要第三方外掛 | Ultimate Member、GamiPress、wpForo（論壇） |

---

## 1. 專案頂層結構

hostingerphp8.3wordpress6.9.4/ ├─ CONTEXT.md ← 本檔 ├─ anime-sync-pro/ ← 動畫資料同步外掛（AniList/Bangumi/AnimeThemes/Jikan） ├─ blocksy-child/ ← 子主題（前端、模板、樣式、JS） ├─ smacg-api/ ← REST API + 內容 slug + 外連結處理 ├─ smacg-gamification/ ← EXP / 等級 / 徽章 / 排行榜 / 季節活動 ├─ smacg-members/ ← 會員中心（資料、統計、渲染、AJAX、UM 整合） └─ smacg-social/ ← 追蹤系統 + 通知中心 + 公開個人頁

Copy
### 1.1 外掛 / 主題載入順序（priority 越小越早）

| 載入順序 | 模組 | `plugins_loaded` priority |
|---|---|---|
| 1 | smacg-api | 5 |
| 2 | smacg-gamification | 5（內含 GamiPress bridge） |
| 3 | smacg-members | 10 |
| 4 | smacg-social | 12 |
| 5 | blocksy-child functions.php | after_setup_theme |
| 6 | anime-sync-pro | plugins_loaded 預設 |

依賴方向（A → B 表示 A 依賴 B）：
smacg-social ─┐ smacg-members ─┼─► smacg-gamification ─► smacg-api blocksy-child ─┘ anime-sync-pro 獨立（僅依賴 smacg-api 的 user-status 路由）

Copy
---

## 2. anime-sync-pro 外掛（動畫資料同步）

### 2.1 基本資料
- 版本：1.1.0（2026-05-07）
- 用途：抓取 AniList / Bangumi / AnimeThemes / Jikan 資料、寫入 CPT `anime`、提供前端評分與觀看狀態。

### 2.2 CPT 與分類
- CPT：`anime`
- Taxonomies：`anime_genre`、`anime_studio`、`anime_season`、`anime_tag`

### 2.3 API 速率（最小間隔毫秒）
| 來源 | 間隔 |
|---|---|
| AniList | 2000 ms |
| Bangumi | 1000 ms |
| AnimeThemes | 700 ms |
| Jikan | 1200 ms |

### 2.4 使用者互動限制
- 評分 POST：5 次/分鐘
- user-status 寫入：30 次/分鐘

### 2.5 主要資料夾
anime-sync-pro/ ├─ admin/ （後台 dashboard / import-tool / logs） ├─ includes/ （class-api-handler、class-user-status-manager、class-review-queue…） ├─ public/ （前端模板 archive-anime.php / single-anime.php） ├─ anime_map.json └─ anime-sync-pro.php

Copy
---

## 3. smacg-api 外掛（REST + Slug + 外連結）

### 3.1 基本資料
- 版本：1.0.0
- 入口：`smacg-api.php`（autoloader + bootstrap，priority 5）
- 來源：Phase 3-C 從 blocksy-child v2.7.3 拆出並 OOP 化

### 3.2 結構
smacg-api/ ├─ smacg-api.php ├─ uninstall.php └─ includes/ ├─ class-plugin.php （主類，singleton，載入 3 個模組） ├─ class-rest-routes.php （REST 路由） ├─ class-content-slug.php （Gemini 翻譯產生 ASCII slug） ├─ class-external-links.php （自動 target=_blank + rel=noopener） └─ leagcy/ ⚠️ 拼字錯誤，待改為 legacy ├─ api-rest.php （備份，未載入） ├─ content-slug.php （備份，未載入） └─ external-links.php （備份，未載入）

Copy
### 3.3 REST 命名空間
- `weixiaoacg/v1/ranking`
- `weixiaoacg/v1/user/favorites`
- `weixiaoacg/v1/anime-url`
- `smacg/v1/user-level`（由 smacg-gamification 提供，這裡只是宣告慣例）

### 3.4 常數
`SMACG_API_VERSION`、`SMACG_API_FILE`、`SMACG_API_DIR`、`SMACG_API_URL`、`SMACG_API_BASENAME`
依賴外部常數：`WEIXIAOACG_GEMINI_API_KEY`、`WEIXIAOACG_ID_CATS`、`WEIXIAOACG_LLM_CATS`（在 functions.php 或 wp-config.php 定義）

---

## 4. smacg-gamification 外掛（EXP / 等級 / 徽章 / 排行榜 / 活動）

### 4.1 基本資料
- 版本：2.5.0
- 命名空間：`SMACG\Gamification\*`
- 入口：`smacg-gamification.php`（priority 5）
- 健康檢查：偵測 GamiPress 是否安裝、子主題版本是否 ≥ 2.12.0

### 4.2 結構（19 個檔案，全 OOP）
smacg-gamification/ ├─ smacg-gamification.php ├─ uninstall.php └─ includes/ ├─ class-plugin.php ├─ class-activator.php ├─ class-deactivator.php ├─ gamipress/ │ ├─ class-gamipress-bridge.php │ └─ class-gamipress-notif-bridge.php ├─ level/ │ ├─ class-level-system.php │ ├─ class-level-badge.php │ └─ class-career-ajax.php ├─ exp/ │ ├─ class-exp-config.php │ └─ class-exp-events.php ├─ ranking/ │ ├─ class-ranking-system.php │ ├─ class-ranking-cron.php │ ├─ class-ranking-privacy.php │ ├─ class-leaderboard-ajax.php │ └─ class-leaderboard-widget.php ├─ season-event/ │ ├─ class-event-cpt.php │ ├─ class-event-admin.php （僅 is_admin() 時載入） │ ├─ class-event-tracker.php │ └─ class-event-settle.php ├─ rest/ │ └─ class-rest-api.php （/smacg/v1/user-level） └─ compat/ └─ legacy-functions.php （24 個舊程序式函式相容層）

Copy
### 4.3 等級系統
- 等級範圍：Lv 1 ~ Lv 200
- 五級職業 Tier：rookie / apprentice / expert / master / guru / sage / legend / celestial
- EXP 公式：
  - Lv 1-10：`level × 100`
  - Lv 11-30：`1000 + (level-10) × 250`
  - Lv 31-70：`6000 + (level-30) × 600`
  - Lv 71-120：`30000 + (level-70) × 1500`
  - Lv 121-200：`105000 + (level-120) × 3000`
- 里程碑：Lv 10 / 30 / 70 / 120 / 200 觸發 `smacg_level_milestone_{N}` action

### 4.4 EXP 規則（`Exp_Config::$rules`）
| Action key | EXP | Cap |
|---|---|---|
| register | 100 | once |
| daily_login | 10 | daily |
| streak_7 | 100 | once |
| streak_30 | 500 | once |
| comment_post | 5 | daily |
| follow_action | 2 | daily（追蹤者拿） |
| followed_by | 5 | daily（被追蹤者拿） |
| watchlist_add | 1 | daily |
| watchlist_complete | 8 | none |
| rating_add | 3 | daily |
| badge_unlock | 20 | none |

過濾器：`apply_filters( 'smacg_exp_rules', $rules )` 可擴充。

### 4.5 排行榜
- 類型：`exp_total` / `exp_monthly` / `followers` / `badges`
- 上限：Top 100，每頁 20 筆
- 使用者隱私 meta：`smacg_appear_in_ranking`（user_meta，預設出現）
- Cron 自訂排程：`smacg_10min`（每 600 秒一次）

### 4.6 季節活動
- CPT：`smacg_season_event`（常數 `SMACG_EVENT_CPT`）
- 模板：`single-smacg_season_event.php`
- 流程：`Event_Tracker` 累積進度 → `Event_Settle` 結算發 EXP/徽章

### 4.7 GamiPress 整合
- `Gamipress_Bridge`：EXP slug = `exp`、Badge slug = `badge`
- `Gamipress_Notif_Bridge`：徽章解鎖時呼叫 smacg-social 的 `smacg_create_notification()`

### 4.8 相容層
`compat/legacy-functions.php` 提供 24 個程序式函式（如 `smacg_get_user_level_info`、`smacg_award_exp`），讓主題與其他外掛 0 改動。

---

## 5. smacg-members 外掛（會員中心）

### 5.1 基本資料
- 版本：1.0.0
- 命名空間：`SMACG\Members\*`
- 載入策略：**薄包裝（Thin Wrapper）** — `class-plugin.php` 依序 `require` 5 個 legacy 檔案，保留所有舊函式名稱與簽名。
- 入口：`smacg-members.php`（priority 10）

### 5.2 結構
smacg-members/ ├─ smacg-members.php ├─ uninstall.php └─ includes/ ├─ class-plugin.php ├─ class-activator.php ├─ class-deactivator.php └─ legacy/ ├─ member-functions.php （核心 helper 與等級/點數） ├─ member-stats.php ├─ member-render.php ├─ member-ajax.php └─ um-integration.php

Copy
### 5.3 對外公開函式（reverse dependency）
- `weixiaoacg_get_user_level_int($uid)`
- `weixiaoacg_get_user_points($uid)`
- `weixiaoacg_get_news_thumb($post_id)`
- `weixiaoacg_acf($key, $post_id)` — `get_field` 安全包裝
- `smacg_is_member_page()`
- `smacg_get_member_center_url()`（內含 wp_cache）
- `smacg_flush_member_center_url_cache()`
- `smacg_get_levels()`
- `smacg_get_user_level_legacy($uid)`（舊 anime_total_points 演算法，保留避免 fatal）
- `smacg_add_points($uid, $points, $reason)`（legacy；新功能應改用 smacg-gamification 的 EXP）
- `smacg_check_cooldown($uid, $action, $seconds)`
- `smacg_sort_watchlist($list, $sort_by)`

### 5.4 軟依賴
- smacg-gamification：`smacg_get_user_level_info` / `smacg_award_exp`
- smacg-social：`smacg_get_public_profile_url`
- Ultimate Member：`um-integration.php` 會檢查 `function_exists()` 才掛 hook
- GamiPress：透過 smacg-gamification 間接依賴

### 5.5 健康檢查
缺少 smacg-gamification 時顯示 admin notice（warning，非阻擋），自動降級運作。

---

## 6. smacg-social 外掛（追蹤 + 通知 + 公開頁）

### 6.1 基本資料
- 版本：1.0.0
- 命名空間：`SMACG\Social\*`
- 載入策略：**薄包裝（Thin Wrapper）** — `class-plugin.php` 依序 `require` 9 個 legacy 檔案。
- 入口：`smacg-social.php`（priority 12）

### 6.2 結構
smacg-social/ ├─ smacg-social.php ├─ uninstall.php └─ includes/ ├─ class-plugin.php ├─ class-activator.php ├─ class-deactivator.php └─ legacy/ ├─ follow-system.php （追蹤核心 + 資料表） ├─ follow-ajax.php ├─ notifications-system.php （通知核心 + 偏好 + 資料表） ├─ notifications-events.php （事件監聽 → 寫入通知） ├─ notifications-ajax.php （鈴鐺 polling） ├─ notifications-render.php （前端 HTML 渲染） ├─ notifications-email.php （Email 即時 / 摘要） ├─ public-profile.php （公開個人頁邏輯） └─ public-profile-render.php （公開個人頁 HTML）

Copy
### 6.3 資料表
- `{prefix}smacg_follows`
  - 欄位：`id, follower_id, following_id, created_at`
  - 索引：UNIQUE(follower_id, following_id) + idx_following + idx_follower_created
  - db_version option：`smacg_follows_db_version` = 1.0.0
- `{prefix}smacg_notifications`
  - 欄位：`id, user_id, type, actor_id, object_type, object_id, data(JSON), is_read, created_at`
  - 索引：idx_user_read、idx_created、idx_user_type
  - db_version option：`smacg_notif_db_version` = 1.0.0

### 6.4 追蹤限制（從主題 functions.php 取常數）
- `SMACG_FOLLOW_DAILY_LIMIT = 200`（每日追蹤上限）
- `SMACG_FOLLOW_COOLDOWN = 1`（秒，同一對使用者連點冷卻）

### 6.5 對外公開函式
- `smacg_follow_user($a, $b)` / `smacg_unfollow_user($a, $b)`
- `smacg_is_following($a, $b)`
- `smacg_get_following_count($uid)` / `smacg_get_followers_count($uid)`
- `smacg_get_following_ids($uid, $limit, $offset)` / `smacg_get_followers_ids(...)`
- `smacg_get_follow_today_count($uid)`
- `smacg_create_notification($args)`
- `smacg_get_notifications($uid, $args)`
- `smacg_get_unread_count($uid)`
- `smacg_mark_notification_read($id)` / `smacg_mark_all_read($uid)`
- `smacg_delete_notification($id)` / `smacg_purge_old_notifications()`
- `smacg_get_notification_prefs($uid)` / `smacg_update_notification_prefs($uid, $partial)`
- `smacg_should_notify($uid, $type, $channel)`
- `smacg_get_public_profile_url($uid)`

### 6.6 通知類型
| type | 說明 |
|---|---|
| follow | 被追蹤 |
| comment_reply | 留言被回覆 |
| rating | 動畫被評分 |
| badge | 解鎖徽章 |
| level_up | 等級提升 |
| system | 系統公告（可 `force=true` 略過偏好） |

### 6.7 偏好機制
- user_meta key：`smacg_notification_prefs`（陣列）
- 每個 type 有 `*_site` 與 `*_email` 兩個開關
- Email 摘要頻率：`email_digest` = off / daily / weekly
- 新註冊時自動寫入預設值

### 6.8 Action hooks
- `do_action( 'smacg_user_followed', $follower_id, $following_id )`
- `do_action( 'smacg_user_unfollowed', $follower_id, $following_id )`
- `do_action( 'smacg_notification_created', $notif_id, $user_id, $type, $args )`

### 6.9 Cron
- `smacg_notifications_daily_purge`：每日 03:00 清理 30 天前的通知（常數 `SMACG_NOTIF_RETENTION_DAYS = 30`）

### 6.10 健康檢查
偵測主題是否定義 `SMACG_FOLLOW_DAILY_LIMIT` 與 `SMACG_FOLLOW_COOLDOWN`，未定義時 admin notice error。

---

## 7. blocksy-child 子主題

### 7.1 基本資料
- 版本：2.14.0（2026-05-15，Phase 3-B 完成）
- 入口：`functions.php`（4.0 KB，已精簡為純常數宣告與外掛存在檢查）

### 7.2 根目錄檔案（27 個）
blocksy-child/ ├─ functions.php 4.0 KB （主題啟動） ├─ style.css 0.5 KB ├─ header.php 22.0 KB ├─ footer.php 8.9 KB ├─ 404.php 5.8 KB ├─ category.php 12.6 KB ├─ search.php 6.2 KB ├─ single.php 16.6 KB ├─ single-smacg_season_event.php 15.8 KB ├─ front-page.php 33.4 KB ├─ page-about.php 13.7 KB ├─ page-columns.php 4.9 KB ├─ page-contact.php 24.0 KB ├─ page-disclaimer.php 11.1 KB ├─ page-go.php 6.9 KB ├─ page-join.php 19.1 KB ├─ page-member.php 13.9 KB ├─ page-privacy.php 12.0 KB ├─ page-public-profile.php 7.9 KB ├─ page-ranking-users.php 9.9 KB ├─ page-ranking.php 12.4 KB ├─ page-season.php 13.9 KB ├─ page-sponsor.php 33.1 KB ├─ page-terms.php 8.3 KB ├─ page-year-review.php 11.1 KB ├─ assets/ （css + js） └─ inc/ （4 支模組）

Copy
### 7.3 inc/ 模組（精簡至 4 個）
| 檔案 | 大小 | 用途 |
|---|---|---|
| `setup-theme.php` | 4.9 KB | 主題基礎（menu、image size、textdomain） |
| `setup-enqueue.php` | 28.1 KB | 所有 CSS/JS 註冊與條件 enqueue（v2.6.1） |
| `class-nav-walker.php` | 2.3 KB | 自訂選單 walker |
| `image-optimizer.php` | 13.1 KB | WebP 自動轉換 + `<picture>` 包裝 + 後台批次工具 |

### 7.4 assets/css（27 檔，總計 ~410 KB）
404、account、admin-sync（0 B*）、anime-status（39 B*）、anime（38 B*）、columns、follow、gamification、glass、home（0 B*）、leaderboard-widget、leaderboard、level-badge、member、news、notifications、public-profile、ranking、search、season-event、single、static、style、track-bar、wpforo-override、year-review

\* 標星為近空檔，可清理。

### 7.5 assets/js（16 檔，總計 ~297 KB）
anime-rating、anime-status、anime（104.9 KB）、api、career、follow、leaderboard、main、member、nav、notifications、page-template、public-profile、ranking、utils、year-review

### 7.6 functions.php 常數
- 主題：`weixiaoacg_VERSION`、`weixiaoacg_THEME_URL`、`weixiaoacg_THEME_DIR`
- 點數（舊系統相容）：
  - `SMACG_POINT_FAVORITE = 5`
  - `SMACG_POINT_WANT = 1`
- 追蹤限制：
  - `SMACG_FOLLOW_DAILY_LIMIT = 200`
  - `SMACG_FOLLOW_COOLDOWN = 1`
- API 分類：
  - `WEIXIAOACG_ID_CATS = ['announcement','news']`
  - `WEIXIAOACG_LLM_CATS = ['review','feature']`
- Fallback：`SMACG_BADGE_SLUG`、`SMACG_EVENT_CPT`（若外掛未定義）

### 7.7 enqueue 條件總覽
- jQuery 3.6.4（CDN 取代）+ Font Awesome 6.5
- Cropper.js（僅 member page 頭像上傳時）
- 各 page template 對應自己的 CSS/JS bundle
- 登入使用者：notifications.js + level-badge.css
- 全部以 `filemtime()` 自動 cache busting
- LiteSpeed exclusions：Font Awesome、Cropper

---

## 8. 資料庫表清單

| 資料表 | 建立來源 | db_version option |
|---|---|---|
| `wp_smacg_follows` | smacg-social | `smacg_follows_db_version` |
| `wp_smacg_notifications` | smacg-social | `smacg_notif_db_version` |
| `wp_smacg_ranking_*`（多張） | smacg-gamification | `EVENT_DB_VERSION` / `RANKING_DB_VERSION` |
| anime-sync-pro 自有表 | anime-sync-pro | （見 anime-sync-pro 內文件） |

GamiPress 自有表不在此列。

---

## 9. Cron 排程

| Hook | 頻率 | 來源 | 用途 |
|---|---|---|---|
| `smacg_notifications_daily_purge` | 每日 03:00 | smacg-social | 刪 30 天前通知 |
| `smacg_exp_daily_reset` | 每日 | smacg-gamification | 清 `smacg_exp_daily_*` user_meta |
| Ranking cron | `smacg_10min`（600s） | smacg-gamification | 重算排行榜快取 |
| anime-sync-pro 自有 cron | — | anime-sync-pro | API 同步排程 |

---

## 10. 已完成的 Phase 3 重構摘要

| Phase | 目標 | 狀態 |
|---|---|---|
| 3-A | 拆分會員中心 → smacg-members | ✅ |
| 3-B | 拆分追蹤/通知/公開頁 → smacg-social | ✅（2026-05-15） |
| 3-C | 拆分 REST/Slug/外連結 → smacg-api（並 OOP 化） | ✅ |
| 3-D | 強化 gamification（已完成 v2.5.0） | ✅ |

主題從 v2.7.3（單體巨檔）→ v2.14.0（精簡 functions.php + 4 個純主題模組）。

---

## 11. 已知待處理項目（Pending TODO）

| 優先 | 項目 | 動作 |
|---|---|---|
| P1 | smacg-api/includes/`leagcy` 拼字錯誤 | `git mv leagcy legacy` |
| P2 | 主題 0 byte 殘檔 | 刪除 `admin-sync.css`、`home.css`（39 B 的 anime-status.css 與 38 B 的 anime.css 需先確認是否為刻意 stub） |
| P3 | `image-optimizer.php` 去向 | 暫留主題 3-6 個月，未來視需要建立 `smacg-utils` 外掛 |
| P3 | gamification 內 enqueue 與主題 setup-enqueue 重複 | 擇一保留（建議保留主題版） |
| P4 | `functions.php` `$optional` 清單清理 | v2.15.0 移除社群相關殘留條目 |

---

## 12. 常用對外公開 API（速查表）

### 12.1 會員
- `weixiaoacg_get_user_level_int($uid) : int`
- `weixiaoacg_get_user_points($uid) : int`
- `smacg_get_member_center_url() : string`

### 12.2 等級 / EXP
- `smacg_get_user_level_info($uid) : array`
- `smacg_award_exp($uid, $action_key) : array`
- 過濾器 `smacg_exp_rules`

### 12.3 追蹤
- `smacg_follow_user($a, $b)` / `smacg_unfollow_user($a, $b)`
- `smacg_is_following($a, $b) : bool`

### 12.4 通知
- `smacg_create_notification([ 'user_id', 'type', 'actor_id', 'object_type', 'object_id', 'data', 'force' ])`
- `smacg_get_unread_count($uid) : int`

### 12.5 公開頁
- `smacg_get_public_profile_url($uid) : string`

---

## 13. AJAX action 命名慣例

| Action | 來源 | nonce 名稱 |
|---|---|---|
| `smacg_follow` / `smacg_unfollow` | smacg-social | `smacg_follow_nonce` |
| `smacg_notif_*` | smacg-social | `smacg_notif_nonce` |
| `smacg_career_*` | smacg-gamification | `smacg_career_nonce` |
| `smacg_leaderboard_*` | smacg-gamification | `smacg_leaderboard_nonce` |
| `smacg_member_*` | smacg-members | `smacg_member_nonce` |

---

## 14. 部署 / 升級檢查清單

啟用 / 升級任一外掛後，依序檢查：
1. **Site Health** 無 critical / warning。
2. 全部 4 個 SMACG 外掛皆 active。
3. 資料表存在：`wp_smacg_follows`、`wp_smacg_notifications`。
4. Cron 列表含 `smacg_notifications_daily_purge`、`smacg_exp_daily_reset`。
5. 前端鈴鐺、追蹤按鈕、公開個人頁皆可運作。
6. 徽章解鎖時只收到 1 則通知（非 2 則）。
7. REST：`/wp-json/weixiaoacg/v1/ranking` 與 `/wp-json/smacg/v1/user-level` 均回應 200（後者需登入）。
8. 主題版本 ≥ 2.12.0，否則 smacg-gamification 會跳 warning。

---

## 15. Repository 連結

- 主 repo：https://github.com/smaacg/hostingerphp8.3wordpress6.9.4
- 舊主 repo（已併入）：https://github.com/smaacg/anime-sync-pro-2-
- 線上站：https://dev.weisianacg.com/

## 2026-05-16 修正紀錄（Batch P1‑P2 render bug）

- public-profile.php → v1.2.1 (hot-fix #1 已部署於前述版本)
- page-public-profile.php → 修正 hero args key
- public-profile-render.php → v1.2.1 修正 5 個 key mismatch
- public-profile.js → v1.1.0 修正 4 個 selector mismatch

### 已驗證無需修改
- follow-system.php v1.1.0
- notifications-system.php v1.1.0
- member-stats.php v2.1.0
- member-render.php v1.1.0
- member.js v2.2.0

### Known low‑risk / defensive‑coding TODO（不修）
- deleted_user 不清持久 object cache（TTL 自然失效）
- smacg_create_notification 未驗 user 存在（cron 30 天清理）
- daily follow counter 非原子（cooldown 已防護）
