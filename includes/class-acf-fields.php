<?php
/**
 * 檔案名稱: includes/class-acf-fields.php
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_ACF_Fields {

    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_all_field_groups' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_resync_metabox' ] );
    }

    public function register_all_field_groups(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $this->register_basic_info();
        $this->register_ratings();
        $this->register_synopsis();
        $this->register_media();
        $this->register_production();
        $this->register_themes_and_streaming();
        $this->register_external_links();
        $this->register_taiwan_info();
        $this->register_faq();
        $this->register_sync_control();
    }

    // =========================================================================
    // 群組 1：基本資訊
    // =========================================================================
    private function register_basic_info(): void {
        acf_add_local_field_group( [
            'key'                   => 'group_anime_basic_info',
            'title'                 => '📋 基本資訊',
            'fields'                => [
                [
                    'key'           => 'field_anime_anilist_id',
                    'label'         => 'AniList ID',
                    'name'          => 'anime_anilist_id',
                    'type'          => 'number',
                    'instructions'  => '請填入 AniList 作品 ID（數字），例如：21。',
                    'required'      => 1,
                    'min'           => 1,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_mal_id',
                    'label'         => 'MyAnimeList ID',
                    'name'          => 'anime_mal_id',
                    'type'          => 'number',
                    'instructions'  => '由 AniList API 自動填入（idMal 欄位）。若為空表示 MAL 無對應條目。',
                    'required'      => 0,
                    'min'           => 1,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_bangumi_id',
                    'label'         => 'Bangumi ID',
                    'name'          => 'anime_bangumi_id',
                    'type'          => 'number',
                    'instructions'  => '由三層查找自動填入。若自動查找失敗，請手動填入 Bangumi 條目 ID。',
                    'required'      => 0,
                    'min'           => 1,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_animethemes_id',
                    'label'         => 'AnimeThemes Anime ID',
                    'name'          => 'anime_animethemes_id',
                    'type'          => 'text',
                    'instructions'  => '由 AnimeThemes API 自動填入 anime.id。若舊資料曾把 slug 寫在這裡，系統會在重新同步時自動搬移到下方 Slug 欄位。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '20' ],
                ],
                [
                    'key'           => 'field_anime_animethemes_slug',
                    'label'         => 'AnimeThemes Slug',
                    'name'          => 'anime_animethemes_slug',
                    'type'          => 'text',
                    'instructions'  => 'AnimeThemes slug（例如 shingeki-no-kyojin）。找不到 anime.id 時，系統與人工補抓都會以此欄位作為 fallback。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '30' ],
                ],
                [
                    'key'           => 'field_anime_title_chinese',
                    'label'         => '中文標題（台灣繁體）',
                    'name'          => 'anime_title_chinese',
                    'type'          => 'text',
                    'instructions'  => '優先使用 Bangumi name_cn，若為空則 fallback 至 AniList english → AniList romaji。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_title_native',
                    'label'         => '日文原名',
                    'name'          => 'anime_title_native',
                    'type'          => 'text',
                    'instructions'  => '由 AniList title.native 自動填入（日文原始標題）。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_title_romaji',
                    'label'         => 'Romaji 標題',
                    'name'          => 'anime_title_romaji',
                    'type'          => 'text',
                    'instructions'  => '由 AniList title.romaji 自動填入。同時作為文章 slug 的產生來源。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_title_english',
                    'label'         => '英文標題',
                    'name'          => 'anime_title_english',
                    'type'          => 'text',
                    'instructions'  => '由 AniList title.english 自動填入。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_format',
                    'label'         => '作品類型',
                    'name'          => 'anime_format',
                    'type'          => 'select',
                    'instructions'  => '由 AniList format 欄位自動填入。',
                    'required'      => 0,
                    'choices'       => [
                        'TV'        => '電視動漫 (TV)',
                        'TV_SHORT'  => '短篇電視動漫 (TV_SHORT)',
                        'MOVIE'     => '劇場版 (MOVIE)',
                        'SPECIAL'   => '特別篇 (SPECIAL)',
                        'OVA'       => 'OVA',
                        'ONA'       => '網路動漫 (ONA)',
                        'MUSIC'     => '音樂 (MUSIC)',
                    ],
                    'default_value' => 'TV',
                    'wrapper'       => [ 'width' => '33' ],
                ],
                [
                    'key'           => 'field_anime_status',
                    'label'         => '播出狀態',
                    'name'          => 'anime_status',
                    'type'          => 'select',
                    'instructions'  => '由每日 cron 自動更新。',
                    'required'      => 0,
                    'choices'       => [
                        'FINISHED'          => '已完結',
                        'RELEASING'         => '連載中',
                        'NOT_YET_RELEASED'  => '尚未播出',
                        'CANCELLED'         => '已取消',
                        'HIATUS'            => '休播中',
                    ],
                    'default_value' => 'FINISHED',
                    'wrapper'       => [ 'width' => '33' ],
                ],
                [
                    'key'           => 'field_anime_source',
                    'label'         => '原作來源',
                    'name'          => 'anime_source',
                    'type'          => 'select',
                    'instructions'  => '由 AniList source 欄位自動填入。',
                    'required'      => 0,
                    'choices'       => [
                        'ORIGINAL'           => '原創',
                        'MANGA'              => '漫畫',
                        'LIGHT_NOVEL'        => '輕小說',
                        'VISUAL_NOVEL'       => '視覺小說',
                        'VIDEO_GAME'         => '遊戲',
                        'OTHER'              => '其他',
                        'NOVEL'              => '小說',
                        'DOUJINSHI'          => '同人誌',
                        'ANIME'              => '動漫',
                        'WEB_NOVEL'          => '網路小說',
                        'LIVE_ACTION'        => '真人影視',
                        'GAME'               => '遊戲',
                        'COMIC'              => '漫畫',
                        'MULTIMEDIA_PROJECT' => '多媒體企劃',
                        'PICTURE_BOOK'       => '繪本',
                    ],
                    'default_value' => 'ORIGINAL',
                    'wrapper'       => [ 'width' => '34' ],
                ],
                [
                    'key'           => 'field_anime_season',
                    'label'         => '播出季度',
                    'name'          => 'anime_season',
                    'type'          => 'select',
                    'instructions'  => '由 AniList season 欄位自動填入。',
                    'required'      => 0,
                    'choices'       => [
                        'WINTER' => '冬季（1月）',
                        'SPRING' => '春季（4月）',
                        'SUMMER' => '夏季（7月）',
                        'FALL'   => '秋季（10月）',
                    ],
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_season_year',
                    'label'         => '播出年份',
                    'name'          => 'anime_season_year',
                    'type'          => 'number',
                    'instructions'  => '由 AniList seasonYear 欄位自動填入。',
                    'required'      => 0,
                    'min'           => 1900,
                    'max'           => 2100,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_episodes',
                    'label'         => '總集數',
                    'name'          => 'anime_episodes',
                    'type'          => 'number',
                    'instructions'  => '由 AniList episodes 欄位自動填入。',
                    'required'      => 0,
                    'min'           => 0,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_episodes_aired',
                    'label'         => '已播集數',
                    'name'          => 'anime_episodes_aired',
                    'type'          => 'number',
                    'instructions'  => '播出中時由每日 cron 自動更新。',
                    'required'      => 0,
                    'min'           => 0,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_duration',
                    'label'         => '每集時長（分鐘）',
                    'name'          => 'anime_duration',
                    'type'          => 'number',
                    'instructions'  => '由 AniList duration 欄位自動填入。',
                    'required'      => 0,
                    'min'           => 0,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'            => 'field_anime_start_date',
                    'label'          => '開播日期',
                    'name'           => 'anime_start_date',
                    'type'           => 'date_picker',
                    'instructions'   => '由 AniList startDate 欄位自動填入。格式：YYYY-MM-DD。',
                    'required'       => 0,
                    'display_format' => 'Y-m-d',
                    'return_format'  => 'Y-m-d',
                    'first_day'      => 1,
                    'wrapper'        => [ 'width' => '33' ],
                ],
                [
                    'key'            => 'field_anime_end_date',
                    'label'          => '完結日期',
                    'name'           => 'anime_end_date',
                    'type'           => 'date_picker',
                    'instructions'   => '完結後由 cron 自動填入。播出中時留空。',
                    'required'       => 0,
                    'display_format' => 'Y-m-d',
                    'return_format'  => 'Y-m-d',
                    'first_day'      => 1,
                    'wrapper'        => [ 'width' => '33' ],
                ],
                [
                    'key'           => 'field_anime_next_airing',
                    'label'         => '下一集播出時間',
                    'name'          => 'anime_next_airing',
                    'type'          => 'text',
                    'instructions'  => '格式：YYYY-MM-DD HH:MM（台灣時間）。由每日 cron 自動更新；完結後清空。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '34' ],
                ],
            ],
            'location'              => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'            => 10,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
        ] );
    }

    // =========================================================================
    // 群組 2：評分資訊
    // =========================================================================
    private function register_ratings(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_ratings',
            'title'  => '⭐ 評分資訊',
            'fields' => [
                [
                    'key'           => 'field_anime_score_anilist',
                    'label'         => 'AniList 評分',
                    'name'          => 'anime_score_anilist',
                    'type'          => 'number',
                    'instructions'  => '範圍 0–100。由每週 cron 自動更新。',
                    'required'      => 0,
                    'min'           => 0,
                    'max'           => 100,
                    'step'          => 0.01,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_score_mal',
                    'label'         => 'MyAnimeList 評分',
                    'name'          => 'anime_score_mal',
                    'type'          => 'number',
                    'instructions'  => '範圍 0–100（原始分數 × 10）。由每週 cron 透過 Jikan API 自動更新。',
                    'required'      => 0,
                    'min'           => 0,
                    'max'           => 100,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_score_bangumi',
                    'label'         => 'Bangumi 評分',
                    'name'          => 'anime_score_bangumi',
                    'type'          => 'number',
                    'instructions'  => '範圍 0–10。由每週 cron 自動更新。',
                    'required'      => 0,
                    'min'           => 0,
                    'max'           => 100,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_popularity',
                    'label'         => 'AniList 人氣數',
                    'name'          => 'anime_popularity',
                    'type'          => 'number',
                    'instructions'  => '由 AniList popularity 欄位自動填入（收藏人數）。每週更新。',
                    'required'      => 0,
                    'min'           => 0,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_anime_ranking',
                    'label'         => 'AniList 排名',
                    'name'          => 'anime_ranking',
                    'type'          => 'number',
                    'instructions'  => '由 AniList rankings 欄位自動填入（全時期評分排名）。每週更新。',
                    'required'      => 0,
                    'min'           => 0,
                    'step'          => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
            ],
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 20,
            'position'    => 'normal',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

    // =========================================================================
    // 群組 3：簡介
    // =========================================================================
    private function register_synopsis(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_synopsis',
            'title'  => '📝 簡介',
            'fields' => [
                [
                    'key'           => 'field_anime_synopsis_chinese',
                    'label'         => '中文簡介（台灣繁體）',
                    'name'          => 'anime_synopsis_chinese',
                    'type'          => 'textarea',
                    'instructions'  => '優先使用 Bangumi summary（自動簡繁轉換）。若 Bangumi 無資料，留空並請人工填入。修改後請在「同步控制」勾選「鎖定中文簡介」。',
                    'required'      => 0,
                    'rows'          => 6,
                    'new_lines'     => 'br',
                    'wrapper'       => [ 'width' => '100' ],
                ],
            ],
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 30,
            'position'    => 'normal',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

    // =========================================================================
    // 群組 4：媒體素材
    // =========================================================================
    private function register_media(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_media',
            'title'  => '🖼️ 媒體素材',
            'fields' => [
                [
                    'key'           => 'field_anime_cover_image',
                    'label'         => '封面圖片網址',
                    'name'          => 'anime_cover_image',
                    'type'          => 'url',
                    'instructions'  => '由 AniList coverImage.extraLarge 自動填入。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '100' ],
                ],
                [
                    'key'           => 'field_anime_banner_image',
                    'label'         => '橫幅圖片網址',
                    'name'          => 'anime_banner_image',
                    'type'          => 'url',
                    'instructions'  => '由 AniList bannerImage 自動填入。可留空。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '100' ],
                ],
[
    'key'           => 'field_anime_trailer_url',
    'label'         => 'YouTube 預告片網址（支援多支 PV）',
    'name'          => 'anime_trailer_url',
    'type'          => 'textarea',
    'instructions'  => '可填一支或多支 YouTube 網址，分隔方式：換行 / 逗號 / 分號 / 空格 皆可。' . "\n"
                     . '可選擇加標題（用 | 分隔），未填標題會自動編號 PV 1、PV 2…' . "\n\n"
                     . '範例（單支）：https://www.youtube.com/watch?v=XXXXX' . "\n"
                     . '範例（多支，每行一筆，建議寫法）：' . "\n"
                     . 'https://youtu.be/abc12345678 | 主視覺PV' . "\n"
                     . 'https://youtu.be/def09876543 | 第二彈PV' . "\n"
                     . 'https://youtu.be/ghi13579246 | 角色PV',
    'rows'          => 4,
    'new_lines'     => '',
    'required'      => 0,
    'wrapper'       => [ 'width' => '100' ],
],

            ],
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 40,
            'position'    => 'normal',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

    // =========================================================================
    // 群組 5：製作資訊
    // =========================================================================
    private function register_production(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_production',
            'title'  => '🎬 製作資訊',
            'fields' => [
                [
                    'key'           => 'field_anime_studio',
                    'label'         => '製作公司',
                    'name'          => 'anime_studio',
                    'type'          => 'text',
                    'instructions'  => '由 AniList studios（isMain: true）自動填入主要製作公司名稱。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '100' ],
                ],
                [
                    'key'           => 'field_anime_staff_json',
                    'label'         => 'STAFF 資料（JSON）',
                    'name'          => 'anime_staff_json',
                    'type'          => 'textarea',
                    'instructions'  => '由 Bangumi STAFF API 自動填入。可手動修正繁簡轉換錯誤後儲存。修改後請在「同步控制」勾選「鎖定 STAFF 製作資料」。',
                    'required'      => 0,
                    'rows'          => 6,
                    'new_lines'     => '',
                    'wrapper'       => [ 'width' => '100' ],
                ],
                [
                    'key'           => 'field_anime_cast_json',
                    'label'         => 'CAST 角色資料（JSON）',
                    'name'          => 'anime_cast_json',
                    'type'          => 'textarea',
                    'instructions'  => '由 Bangumi CAST API 自動填入。可手動修正繁簡轉換錯誤後儲存。修改後請在「同步控制」勾選「鎖定 CAST 角色資料」。',
                    'required'      => 0,
                    'rows'          => 6,
                    'new_lines'     => '',
                    'wrapper'       => [ 'width' => '100' ],
                ],
                [
                    'key'           => 'field_anime_episodes_json',
                    'label'         => '集數列表（JSON）',
                    'name'          => 'anime_episodes_json',
                    'type'          => 'textarea',
                    'instructions'  => '由 Bangumi Episodes API 自動填入。可手動修正繁簡轉換錯誤後儲存。修改後請在「同步控制」勾選「鎖定集數列表」。格式：[{"ep":1,"name":"...","name_cn":"...","airdate":"YYYY-MM-DD"}]',
                    'required'      => 0,
                    'rows'          => 6,
                    'new_lines'     => '',
                    'wrapper'       => [ 'width' => '100' ],
                ],
            ],
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 50,
            'position'    => 'normal',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

    // =========================================================================
    // 群組 6：主題曲與串流平台
    // =========================================================================
    private function register_themes_and_streaming(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_themes_streaming',
            'title'  => '🎵 主題曲與串流平台',
            'fields' => [
                [
                    'key'           => 'field_anime_themes_json',
                    'label'         => 'OP/ED 主題曲資料（JSON）',
                    'name'          => 'anime_themes',
                    'type'          => 'textarea',
                    'instructions'  => '由 AnimeThemes API 自動抓取。請勿手動編輯。格式：[{"type":"OP1","song_title":"...","artist":"...","audio_url":"https://a.animethemes.moe/..."}]',
                    'required'      => 0,
                    'rows'          => 4,
                    'new_lines'     => '',
                    'wrapper'       => [ 'width' => '100' ],
                    'readonly'      => 1,
                ],
                [
                    'key'           => 'field_anime_streaming_json',
                    'label'         => '串流平台資料（JSON）',
                    'name'          => 'anime_streaming',
                    'type'          => 'textarea',
                    'instructions'  => '由 AniList externalLinks（type: STREAMING）自動填入。請勿手動編輯。',
                    'required'      => 0,
                    'rows'          => 4,
                    'new_lines'     => '',
                    'wrapper'       => [ 'width' => '100' ],
                    'readonly'      => 1,
                ],
            ],
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 60,
            'position'    => 'normal',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

    // =========================================================================
    // 群組 7：外部連結
    // =========================================================================
    private function register_external_links(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_external_links',
            'title'  => '🔗 外部連結',
            'fields' => [
                [
                    'key'           => 'field_anime_official_site',
                    'label'         => '官方網站',
                    'name'          => 'anime_official_site',
                    'type'          => 'url',
                    'instructions'  => '由 AniList externalLinks 自動填入。可人工覆寫。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_twitter_url',
                    'label'         => 'Twitter / X 官方帳號',
                    'name'          => 'anime_twitter_url',
                    'type'          => 'url',
                    'instructions'  => '由 AniList externalLinks 自動填入。可人工覆寫。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_wikipedia_url',
                    'label'         => 'Wikipedia 頁面',
                    'name'          => 'anime_wikipedia_url',
                    'type'          => 'url',
                    'instructions'  => '請人工填入中文或日文維基百科連結。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_tiktok_url',
                    'label'         => 'TikTok 官方帳號',
                    'name'          => 'anime_tiktok_url',
                    'type'          => 'url',
                    'instructions'  => '請人工填入 TikTok 官方帳號連結（選填）。',
                    'required'      => 0,
                    'wrapper'       => [ 'width' => '50' ],
                ],
            ],
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 70,
            'position'    => 'normal',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

      // =========================================================================
    // 群組 8：台灣在地資訊
    // ★ 修改：get_tw_platforms() 全改 underscore key，移除 str_replace
    // ★ 新增：anime_youranimes_url 欄位（YourAnimes 同步來源網址）
    // =========================================================================
    private function register_taiwan_info(): void {

        $platforms  = $this->get_tw_platforms();
        $url_fields = [];

        foreach ( $platforms as $key => $label ) {
            // key 已確保為 underscore 格式，直接使用，不需 str_replace
            $url_fields[] = [
                'key'          => 'field_anime_tw_streaming_url_' . $key,
                'label'        => $label . ' 直達連結',
                'name'         => 'anime_tw_streaming_url_' . $key,
                'type'         => 'url',
                'instructions' => '勾選上方「' . $label . '」後，可在此填入該動漫的直達連結（留空則顯示純文字）。',
                'required'     => 0,
                'wrapper'      => [ 'width' => '50' ],
            ];
        }

        acf_add_local_field_group( [
            'key'    => 'group_anime_taiwan_info',
            'title'  => '🇹🇼 台灣在地資訊',
            'fields' => array_merge(
                [
                    [
                        'key'           => 'field_anime_tw_streaming',
                        'label'         => '台灣串流平台',
                        'name'          => 'anime_tw_streaming',
                        'type'          => 'checkbox',
                        'instructions'  => '勾選有上架的平台；下方可對應填入該動漫的直達連結。',
                        'required'      => 0,
                        'choices'       => $platforms,  // key 與 URL 欄位名稱完全一致
                        'layout'        => 'horizontal',
                        'toggle'        => 0,
                        'return_format' => 'value',
                        'wrapper'       => [ 'width' => '100' ],
                    ],
                ],
                $url_fields,
                [
                    [
                        'key'           => 'field_anime_tw_streaming_other',
                        'label'         => '其他串流平台（自訂）',
                        'name'          => 'anime_tw_streaming_other',
                        'type'          => 'text',
                        'instructions'  => '上方平台以外的服務，多個請用逗號分隔。',
                        'required'      => 0,
                        'wrapper'       => [ 'width' => '100' ],
                    ],
                    [
                        'key'           => 'field_anime_tw_distributor',
                        'label'         => '台灣代理商／發行商',
                        'name'          => 'anime_tw_distributor',
                        'type'          => 'select',
                        'instructions'  => '請選擇台灣代理商；若不在清單中請選「其他（自訂）」並於下方填寫。',
                        'required'      => 0,
                        'choices'       => [
                            ''            => '── 請選擇 ──',
                            'muse'        => '木棉花',
                            'medialink'   => '曼迪傳播',
                            'linbang'     => '羚邦',
                            'tropic'      => '回歸線娛樂',
                            'proware'     => '普威爾',
                            'kadokawa'    => '台灣角川',
                            'gungho'      => '群英社',
                            'tien'        => '提恩傳媒',
                            'garage'      => '車庫娛樂',
                            'carsun'      => '采昌國際',
                            'jbf'         => '日本橋文化（JBF）',
                            'righttime'   => '利得時代（Right Time）',
                            'aniplus'     => 'ANIPLUS Asia',
                            'tongli'      => '東立出版社',
                            'remow'       => 'REMOW',
                            'gaga'        => 'GaGa OOLala',
                            'other'       => '其他（自訂）',
                        ],
                        'default_value' => '',
                        'allow_null'    => 1,
                        'wrapper'       => [ 'width' => '50' ],
                    ],
                    [
                        'key'           => 'field_anime_tw_distributor_custom',
                        'label'         => '台灣代理商（自訂名稱）',
                        'name'          => 'anime_tw_distributor_custom',
                        'type'          => 'text',
                        'instructions'  => '僅在上方選「其他（自訂）」時生效。',
                        'required'      => 0,
                        'wrapper'       => [ 'width' => '50' ],
                    ],
                    [
                        'key'           => 'field_anime_tw_broadcast',
                        'label'         => '台灣播出時間',
                        'name'          => 'anime_tw_broadcast',
                        'type'          => 'text',
                        'instructions'  => '請人工填入台灣播出時間（例：每週六 23:00 Netflix）。',
                        'required'      => 0,
                        'wrapper'       => [ 'width' => '100' ],
                    ],
                    [
                        'key'           => 'field_anime_youranimes_url',
                        'label'         => 'YourAnimes 網址',
                        'name'          => 'anime_youranimes_url',
                        'type'          => 'url',
                        'instructions'  => '貼上 YourAnimes 動畫頁網址（例：https://youranimes.tw/animes/5480/onair），點下方按鈕同步台灣串流連結。同步會覆蓋既有 URL，並自動勾選對應平台。',
                        'required'      => 0,
                        'placeholder'   => 'https://youranimes.tw/animes/XXXX/onair',
                        'wrapper'       => [ 'width' => '100', 'class' => 'anime-youranimes-url-field' ],
                    ],
                ]
            ),
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 80,
            'position'    => 'normal',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

    // =========================================================================
    // 群組 9：常見問題（FAQ）
    // =========================================================================
    private function register_faq(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_faq',
            'title'  => '❓ 常見問題（FAQ）',
            'fields' => [
                [
                    'key'           => 'field_anime_faq_json',
                    'label'         => 'FAQ JSON',
                    'name'          => 'anime_faq_json',
                    'type'          => 'textarea',
                    'instructions'  => "完全人工輸入，留空則不顯示 FAQ 區塊與 Schema.org FAQPage。\n格式範例：\n[\n  {\"q\": \"問題一\", \"a\": \"答案一\"},\n  {\"q\": \"問題二\", \"a\": \"答案二\"}\n]",
                    'required'      => 0,
                    'rows'          => 8,
                    'new_lines'     => '',
                    'placeholder'   => '[{"q":"問題","a":"答案"}]',
                    'wrapper'       => [ 'width' => '100' ],
                ],
            ],
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 85,
            'position'    => 'normal',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

    // =========================================================================
    // 群組 10：同步控制
    // =========================================================================
    private function register_sync_control(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_sync_control',
            'title'  => '⚙️ 同步控制',
            'fields' => [
                [
                    'key'           => 'field_anime_last_sync',
                    'label'         => '上次 API 同步時間',
                    'name'          => 'anime_last_sync',
                    'type'          => 'text',
                    'instructions'  => '由系統自動記錄。請勿手動修改。',
                    'required'      => 0,
                    'readonly'      => 1,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_last_updated',
                    'label'         => '資料最後更新時間',
                    'name'          => 'anime_last_updated',
                    'type'          => 'text',
                    'instructions'  => '每次任何欄位更新時由系統自動記錄。',
                    'required'      => 0,
                    'readonly'      => 1,
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_locked_fields',
                    'label'         => '鎖定欄位（防止自動覆寫）',
                    'name'          => 'anime_locked_fields',
                    'type'          => 'checkbox',
                    'instructions'  => '勾選後，自動更新 cron 與重新同步 Bangumi 將跳過該欄位，保留您的人工修改。',
                    'required'      => 0,
                    'choices'       => [
                        'anime_title_chinese'    => '中文標題',
                        'anime_synopsis_chinese' => '中文簡介',
                        'anime_cover_image'      => '封面圖片',
                        'anime_banner_image'     => '橫幅圖片',
                        'anime_trailer_url'      => 'YouTube 預告片',
                        'anime_cast_json'        => 'CAST 角色資料',
                        'anime_staff_json'       => 'STAFF 製作資料',
                        'anime_episodes_json'    => '集數列表',
                    ],
                    'layout'        => 'horizontal',
                    'toggle'        => 0,
                    'return_format' => 'value',
                    'wrapper'       => [ 'width' => '100' ],
                ],
            ],
            'location'    => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'  => 90,
            'position'    => 'side',
            'style'       => 'default',
            'active'      => true,
        ] );
    }

    // =========================================================================
    // Meta Box：重新同步 Bangumi
    // =========================================================================
    public function register_resync_metabox(): void {
        add_meta_box(
            'anime_resync_bangumi',
            '🔄 重新同步 Bangumi',
            [ $this, 'render_resync_metabox' ],
            'anime',
            'side',
            'default'
        );
    }

    public function render_resync_metabox( WP_Post $post ): void {
        $bangumi_id = get_post_meta( $post->ID, 'anime_bangumi_id', true );
        $last_sync  = get_post_meta( $post->ID, 'anime_last_sync',  true );
        ?>
        <div id="anime-resync-wrap">
            <?php if ( $bangumi_id ) : ?>
                <p style="margin:0 0 8px;">
                    Bangumi ID：<strong><?php echo esc_html( $bangumi_id ); ?></strong>
                </p>
            <?php else : ?>
                <p style="margin:0 0 8px;color:#999;">尚未設定 Bangumi ID。</p>
            <?php endif; ?>
            <?php if ( $last_sync ) : ?>
                <p style="margin:0 0 8px;font-size:11px;color:#666;">
                    上次同步：<?php echo esc_html( $last_sync ); ?>
                </p>
            <?php endif; ?>
            <button
                type="button"
                id="anime-resync-bangumi-btn"
                class="button button-secondary"
                style="width:100%;"
            >
                🔄 重新同步 Bangumi 資料
            </button>
            <p id="anime-resync-bangumi-msg" style="margin:8px 0 0;min-height:20px;font-size:12px;"></p>
        </div>
        <?php
    }

    // =========================================================================
    // Helper：台灣串流平台定義（僅調整顯示與欄位，不動既有同步邏輯）
    // ★ 所有 key 維持 underscore 格式，checkbox 儲存值 = URL 欄位後綴
    // =========================================================================
    private function get_tw_platforms(): array {
        return [
            'bahamut'      => '巴哈姆特動畫瘋',
            'hami'         => '中華電信 Hami Video',
            'myvideo'      => '台灣大哥大 MyVideo',
            'linetv'       => 'LINE TV',
            'friday'       => 'friDay影音',
            'ofiii'        => 'Ofiii 歐飛',
            'catchplay'    => 'CatchPlay+',
            'bilibili'     => 'Bilibili 台灣',
            'ani_one'      => 'Ani-One 羚邦集團 YouTube（官方頻道）',
            'muse'         => 'Muse 木棉花 YouTube（官方頻道）',
            'mighty'       => '曼迪 YouTube（官方頻道）',
            'ani_mi'       => 'Ani-Mi 動漫迷動畫頻道（官方頻道）',
            'netflix'      => 'Netflix',
            'disney'       => 'Disney+',
            'litv'         => 'LiTV 立視線上影視',
            'tropicsanime' => '回歸線娛樂 YouTube（官方頻道）',
            'iqiyi'        => '愛奇藝',
            'renta'        => 'renta!亂搭',
            'anipass'      => 'AniPASS 車庫娛樂旗下',
            'amazon'       => 'Amazon Prime Video',
        ];
    }

    // =========================================================================
    // 靜態輔助方法
    // =========================================================================
    public static function get_auto_update_fields(): array {
        return [
            'anime_episodes_aired' => '已播集數',
            'anime_status'         => '播出狀態',
            'anime_next_airing'    => '下一集播出時間',
            'anime_score_anilist'  => 'AniList 評分',
            'anime_score_mal'      => 'MAL 評分',
            'anime_score_bangumi'  => 'Bangumi 評分',
            'anime_popularity'     => 'AniList 人氣數',
            'anime_ranking'        => 'AniList 排名',
            'anime_end_date'       => '完結日期',
        ];
    }

    public static function get_enrich_fields(): array {
        return [
            'anime_cast_json'     => 'CAST 角色資料',
            'anime_staff_json'    => 'STAFF 製作資料',
            'anime_episodes_json' => '集數列表',
            'anime_themes'        => 'OP/ED 主題曲資料',
        ];
    }
}
