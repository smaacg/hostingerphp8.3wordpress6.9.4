<?php
/**
 * 微笑動漫 — Taxonomy Seeder v5
 * 路徑：wp-content/plugins/anime-sync-pro/setup-taxonomy.php
 *
 * 用途：
 * 1. 建立文章內容型 category：announcement / news / review / feature
 * 2. 建立文章頻道 taxonomy：channel（12 個頻道）
 * 3. 建立 anime 作品庫用 taxonomy term：genre / anime_season_tax / anime_format_tax
 *
 * v5 變更：
 * - category 新增「公告 announcement」
 * - channel 新增「聲優 voice-actor」「音樂 music」「周邊 merchandise」「活動 event」「業界 industry」
 *
 * 注意：
 * - 這支檔案只負責建立 term
 * - /news/anime/post-slug/ 類型網址需要搭配 class-editorial-routing.php
 * - 執行完成後請立即刪除此檔案
 */

$wp_load = dirname( __FILE__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	die( '找不到 wp-load.php' );
}

require_once $wp_load;

if ( ! current_user_can( 'manage_options' ) ) {
	die( '請先登入 WordPress 管理員帳號' );
}

function wx_upsert_term( string $name, string $taxonomy, array $args = [] ): int {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		echo "<p style='color:red'>❌ taxonomy 不存在：{$taxonomy}</p>";
		return 0;
	}

	$slug   = $args['slug'] ?? sanitize_title( $name );
	$parent = isset( $args['parent'] ) ? (int) $args['parent'] : 0;

	$existing = get_term_by( 'slug', $slug, $taxonomy );

	if ( $existing && ! is_wp_error( $existing ) ) {
		$updated = wp_update_term( (int) $existing->term_id, $taxonomy, [
			'name'   => $name,
			'slug'   => $slug,
			'parent' => $parent,
		] );

		if ( is_wp_error( $updated ) ) {
			echo "<p style='color:orange'>⚠️ 更新失敗：{$name} ({$slug})：" . esc_html( $updated->get_error_message() ) . '</p>';
			return (int) $existing->term_id;
		}

		echo "<p style='color:#888'>⏭️ 已存在並同步：{$name} ({$slug})</p>";
		return (int) $existing->term_id;
	}

	$result = wp_insert_term( $name, $taxonomy, [
		'slug'   => $slug,
		'parent' => $parent,
	] );

	if ( is_wp_error( $result ) ) {
		echo "<p style='color:orange'>⚠️ 建立失敗：{$name} ({$slug})：" . esc_html( $result->get_error_message() ) . '</p>';
		return 0;
	}

	echo "<p style='color:green'>✅ 建立：{$name} ({$slug})</p>";
	return (int) $result['term_id'];
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
echo '<title>微笑動漫 Taxonomy Seeder v5</title>';
echo '<style>
	body {
		font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
		padding: 28px;
		background: #f6f7f7;
		color: #1d2327;
	}
	h1, h2 { margin-top: 1.2em; }
	code {
		background: #fff;
		padding: 2px 6px;
		border-radius: 4px;
	}
	.note { color: #555; }
	.warn { color: #b32d2e; font-weight: 700; }
</style>';
echo '</head><body>';

echo '<h1>微笑動漫 Taxonomy Seeder v5</h1>';
echo '<p class="note">v5 新增「公告」category 與 5 個 channel（聲優 / 音樂 / 周邊 / 活動 / 業界）</p>';


// =========================================================
// 1. 文章內容型 category
// =========================================================
echo '<h2>文章內容型（category）</h2>';

$editorial_categories = [
	[ '公告', 'announcement' ],
	[ '新聞', 'news'         ],
	[ '評論', 'review'       ],
	[ '專題', 'feature'      ],
];

foreach ( $editorial_categories as [ $name, $slug ] ) {
	wx_upsert_term( $name, 'category', [ 'slug' => $slug ] );
}


// =========================================================
// 2. 文章頻道 channel
// =========================================================
echo '<h2>文章頻道（channel）</h2>';
echo '<p class="note">需先由外掛註冊 channel taxonomy，否則這裡會顯示 taxonomy 不存在。</p>';

$channels = [
	[ '動漫',    'anime'        ],
	[ '漫畫',    'manga'        ],
	[ '輕小說',  'novel'        ],
	[ '遊戲',    'game'         ],
	[ 'VTuber',  'vtuber'       ],
	[ 'Cosplay', 'cosplay'      ],
	[ 'AI工具',  'ai-tools'     ],
	[ '聲優',    'voice-actor'  ],
	[ '音樂',    'music'        ],
	[ '周邊',    'merchandise'  ],
	[ '活動',    'event'        ],
	[ '業界',    'industry'     ],
];

foreach ( $channels as [ $name, $slug ] ) {
	wx_upsert_term( $name, 'channel', [ 'slug' => $slug ] );
}


// =========================================================
// 3. genre
// =========================================================
echo '<h2>動漫類型（genre）</h2>';

$genres = [
	[ '動作',     'action'        ],
	[ '冒險',     'adventure'     ],
	[ '喜劇',     'comedy'        ],
	[ '劇情',     'drama'         ],
	[ '奇幻',     'fantasy'       ],
	[ '恐怖',     'horror'        ],
	[ '魔法少女', 'mahou-shoujo'  ],
	[ '機甲',     'mecha'         ],
	[ '音樂',     'music'         ],
	[ '推理',     'mystery'       ],
	[ '懸疑',     'suspense'      ],
	[ '心理',     'psychological' ],
	[ '科幻',     'sci-fi'        ],
	[ '日常',     'slice-of-life' ],
	[ '運動',     'sports'        ],
	[ '超自然',   'supernatural'  ],
	[ '驚悚',     'thriller'      ],
	[ '異世界',   'isekai'        ],
	[ '後宮',     'harem'         ],
	[ '百合',     'yuri'          ],
	[ '耽美',     'bl'            ],
	[ '歷史',     'historical'    ],
	[ '武俠',     'wuxia'         ],
	[ '校園',     'school'        ],
	[ '兒童',     'kids'          ],
	[ '輕色情',   'ecchi'         ],
	[ '戀愛',     'romance'       ],
];

foreach ( $genres as [ $name, $slug ] ) {
	wx_upsert_term( $name, 'genre', [ 'slug' => $slug ] );
}


// =========================================================
// 4. anime_season_tax
// =========================================================
echo '<h2>播出季度（anime_season_tax）</h2>';

$seasons = [
	'winter' => '冬季',
	'spring' => '春季',
	'summer' => '夏季',
	'fall'   => '秋季',
];

for ( $year = 2000; $year <= 2035; $year++ ) {
	$parent_id = wx_upsert_term( (string) $year, 'anime_season_tax', [
		'slug' => (string) $year,
	] );

	foreach ( $seasons as $suffix => $label ) {
		wx_upsert_term( "{$year} {$label}", 'anime_season_tax', [
			'slug'   => "{$year}-{$suffix}",
			'parent' => $parent_id,
		] );
	}
}


// =========================================================
// 5. anime_format_tax
// =========================================================
echo '<h2>動漫格式（anime_format_tax）</h2>';

$formats = [
	[ 'TV',     'tv'       ],
	[ 'TV短篇', 'tv-short' ],
	[ '劇場版', 'movie'    ],
	[ 'OVA',    'ova'      ],
	[ 'ONA',    'ona'      ],
	[ '特別篇', 'special'  ],
	[ '音樂MV', 'music'    ],
];

foreach ( $formats as [ $name, $slug ] ) {
	wx_upsert_term( $name, 'anime_format_tax', [ 'slug' => $slug ] );
}

update_option( 'weixiaoacg_taxonomy_v5_done', current_time( 'mysql' ) );

echo '<hr>';
echo '<h2 style="color:green">✅ 完成</h2>';
echo '<ul>';
echo '<li>請確認外掛已載入 <code>Anime_Sync_Editorial_Routing</code></li>';
echo '<li>請到「設定 → 固定網址」按一次儲存，刷新 rewrite rules</li>';
echo '<li>舊文章分類（例如 anime / manga / games）不會自動刪除，請視情況遷移</li>';
echo '<li class="warn">⚠️ 執行完成後請立即刪除此檔案</li>';
echo '</ul>';

echo '</body></html>';
