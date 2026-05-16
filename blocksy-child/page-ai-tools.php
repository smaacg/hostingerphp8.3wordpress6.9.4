<?php
/**
 * Template Name: AI 工具整合
 *
 * 訪問路徑：/ai-tools/
 * Path: wp-content/themes/blocksy-child/page-ai-tools.php
 *
 * @version 1.0.0
 * @since   2026-05-16
 *
 * Changelog:
 *   1.0.0 (2026-05-16) 初版：ACG × AI 工具整合頁，6 大類別 filter，玻璃擬態卡片
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

/* ============================================================
   工具資料定義（未來新增工具只要改這裡）
   ------------------------------------------------------------
   - icon       emoji 或單一字元
   - bg         icon 背景色（rgba）
   - name       工具名
   - tag        副標籤（短）
   - desc       描述
   - category   分類 slug：drawing / writing / voice / video / character / music
   - badge      pricing：free / paid / freemium
   - badge_text 顯示文字
   - url        外部連結
   ============================================================ */
$ai_tools = [
    [
        'icon'       => '🎨',
        'bg'         => 'rgba(139,92,246,0.15)',
        'name'       => 'NovelAI',
        'tag'        => 'AI 繪圖・動漫風格',
        'desc'       => '專為動漫風格設計的 AI 繪圖工具，精準掌握二次元美學，支援角色設計、場景生成。',
        'category'   => 'drawing',
        'badge'      => 'paid',
        'badge_text' => '付費',
        'url'        => 'https://novelai.net/',
    ],
    [
        'icon'       => '✨',
        'bg'         => 'rgba(59,130,246,0.15)',
        'name'       => 'Stable Diffusion',
        'tag'        => 'AI 繪圖・開源',
        'desc'       => '最強大的開源 AI 繪圖模型，搭配 Anything V5 等動漫模型，可免費本地部署使用。',
        'category'   => 'drawing',
        'badge'      => 'free',
        'badge_text' => '免費',
        'url'        => 'https://stability.ai/',
    ],
    [
        'icon'       => '🎙️',
        'bg'         => 'rgba(249,115,22,0.15)',
        'name'       => 'ElevenLabs',
        'tag'        => 'AI 配音・克隆聲音',
        'desc'       => '業界頂級 AI 語音生成，可複製聲優音色，生成高品質動漫配音，支援多語言。',
        'category'   => 'voice',
        'badge'      => 'freemium',
        'badge_text' => '免費試用',
        'url'        => 'https://elevenlabs.io/',
    ],
    [
        'icon'       => '📝',
        'bg'         => 'rgba(34,197,94,0.15)',
        'name'       => 'Claude',
        'tag'        => 'AI 寫作・劇本創作',
        'desc'       => 'Anthropic 出品的 AI 助理，擅長創意寫作、動漫劇本生成、角色對白設計，中文能力強。',
        'category'   => 'writing',
        'badge'      => 'freemium',
        'badge_text' => '免費試用',
        'url'        => 'https://claude.ai/',
    ],
    [
        'icon'       => '🎬',
        'bg'         => 'rgba(236,72,153,0.15)',
        'name'       => 'Runway Gen-3',
        'tag'        => 'AI 影片生成',
        'desc'       => '將靜態動漫插圖動態化，生成短篇動畫影片，適合創作 MAD 或宣傳影片。',
        'category'   => 'video',
        'badge'      => 'freemium',
        'badge_text' => '免費試用',
        'url'        => 'https://runwayml.com/',
    ],
    [
        'icon'       => '🎵',
        'bg'         => 'rgba(168,85,247,0.15)',
        'name'       => 'Suno AI',
        'tag'        => 'AI 音樂生成',
        'desc'       => '輸入歌詞即可生成完整歌曲，支援 J-POP、動漫風格，適合創作自製 OP/ED。',
        'category'   => 'music',
        'badge'      => 'freemium',
        'badge_text' => '免費試用',
        'url'        => 'https://suno.ai/',
    ],
    [
        'icon'       => '🖌️',
        'bg'         => 'rgba(14,165,233,0.15)',
        'name'       => 'Midjourney',
        'tag'        => 'AI 繪圖・藝術風',
        'desc'       => '生成質感極高的藝術風插畫，雖然偏寫實但透過 prompt 可調出動漫風格。',
        'category'   => 'drawing',
        'badge'      => 'paid',
        'badge_text' => '付費',
        'url'        => 'https://www.midjourney.com/',
    ],
    [
        'icon'       => '👤',
        'bg'         => 'rgba(244,114,182,0.15)',
        'name'       => 'Character.AI',
        'tag'        => '角色設計・對話',
        'desc'       => '與 AI 角色互動聊天，可作為角色性格設計、對白測試的輔助工具。',
        'category'   => 'character',
        'badge'      => 'free',
        'badge_text' => '免費',
        'url'        => 'https://character.ai/',
    ],
    [
        'icon'       => '🎭',
        'bg'         => 'rgba(251,191,36,0.15)',
        'name'       => 'Waifu Labs',
        'tag'        => '動漫角色生成',
        'desc'       => '專門生成動漫女角色的 AI 工具，操作簡單，適合快速產出角色設計概念。',
        'category'   => 'character',
        'badge'      => 'free',
        'badge_text' => '免費',
        'url'        => 'https://waifulabs.com/',
    ],
];

/* ── 分類定義 ── */
$ai_categories = [
    'all'       => '全部',
    'drawing'   => 'AI 繪圖',
    'writing'   => 'AI 寫作',
    'voice'     => 'AI 配音',
    'video'     => '影片生成',
    'character' => '角色設計',
    'music'     => 'AI 音樂',
];
?>

<div class="page-hero ai-hero">
  <div class="container">
    <div class="page-badge ai-badge">
      <i class="fa-solid fa-robot"></i> AI 工具
    </div>
    <h1 class="page-title">ACG × AI 工具整合</h1>
    <p class="page-subtitle">精選動漫創作者必備 AI 工具，涵蓋繪圖、寫作、配音、影片生成</p>
  </div>
</div>

<main class="container" style="padding: 0 0 64px;">

  <!-- ── Filter ── -->
  <div class="tool-filter" id="ai-tool-filter">
    <?php foreach ( $ai_categories as $slug => $label ) :
        $active = ( $slug === 'all' ) ? ' active' : '';
    ?>
      <button type="button"
              class="tool-filter-btn<?php echo $active; ?>"
              data-category="<?php echo esc_attr( $slug ); ?>">
        <?php echo esc_html( $label ); ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- ── Tools Grid ── -->
  <div class="tools-grid" id="ai-tools-grid">
    <?php foreach ( $ai_tools as $tool ) : ?>
      <article class="tool-card glass"
               data-category="<?php echo esc_attr( $tool['category'] ); ?>">

        <div class="tool-card-header">
          <div class="tool-icon" style="background: <?php echo esc_attr( $tool['bg'] ); ?>;">
            <?php echo esc_html( $tool['icon'] ); ?>
          </div>
          <div>
            <div class="tool-name"><?php echo esc_html( $tool['name'] ); ?></div>
            <div class="tool-tag"><?php echo esc_html( $tool['tag'] ); ?></div>
          </div>
        </div>

        <div class="tool-desc"><?php echo esc_html( $tool['desc'] ); ?></div>

        <div class="tool-footer">
          <span class="tool-badge <?php echo esc_attr( $tool['badge'] ); ?>">
            <?php echo esc_html( $tool['badge_text'] ); ?>
          </span>
          <a href="<?php echo esc_url( $tool['url'] ); ?>"
             target="_blank"
             rel="noopener nofollow"
             class="tool-link">
            前往 <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </a>
        </div>

      </article>
    <?php endforeach; ?>
  </div>

  <!-- ── Empty State（filter 後無結果時 JS 顯示） ── -->
  <div class="ai-empty glass" id="ai-tools-empty" hidden>
    <i class="fa-solid fa-magnifying-glass"></i>
    <p>這個分類目前沒有工具，歡迎推薦給我們！</p>
  </div>

  <!-- ── Disclaimer ── -->
  <div class="ai-disclaimer glass">
    <i class="fa-solid fa-circle-info"></i>
    <div>
      <strong>免責聲明：</strong>本頁所列工具皆為第三方服務，使用前請詳閱各服務條款。
      AI 生成內容請尊重原作著作權，避免商業濫用。
    </div>
  </div>

</main>

<?php get_footer(); ?>
