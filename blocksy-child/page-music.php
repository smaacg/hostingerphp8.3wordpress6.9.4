<?php
/**
 * Template Name: 動漫音樂中心（Music Hub）
 * Description : 音樂頻道首頁 — OP/ED、OST、聲優歌手、演唱會、MV/PV、歌單
 * Version     : 1.0.0
 * Date        : 2026-05-16
 *
 * Path: blocksy-child/page-music.php
 *
 * 建議搭配：
 *   - assets/css/music.css                 (本檔對應的樣式)
 *   - 自訂文章類型 `music`（可後續擴充）
 *
 * 使用方式：
 *   1. WP 後台 → 頁面 → 新增頁面
 *   2. 標題：動漫音樂
 *   3. Slug：music
 *   4. 範本：動漫音樂中心（Music Hub）
 *   5. 發布後 https://test.weixiaoacg.com/music/
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

/* ============================================================
   資料載入區 — 目前為示範資料；之後可改為 WP_Query / CPT / ACF
   ============================================================ */

// 本週主打（單筆）
$weekly_hero = [
    'badge_text'  => '本週主打',
    'live_text'   => '熱播中',
    'title'       => '魂之歌 / Soul Resonance',
    'artist'      => '藍澤優音（CV：水瀬いのり）',
    'source'      => '聲鳴之響 OP1',
    'season'      => '2026 春季',
    'rank'        => '本週 #1',
    'bg_image'    => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1200&q=80',
];

// OP / ED 清單
$oped_list = [
    ['type'=>'op','label'=>'OP1','emoji'=>'🎵','song'=>'魂之歌 / Soul Resonance','artist'=>'藍澤優音（CV：水瀬いのり） · 聲鳴之響'],
    ['type'=>'ed','label'=>'ED1','emoji'=>'🌸','song'=>'星が降る夜に','artist'=>'Luna Aria（CV：悠木碧） · 聲鳴之響'],
    ['type'=>'op','label'=>'OP1','emoji'=>'⚡','song'=>'BERSERK ANTHEM','artist'=>'崎廉太郎 · 破滅召喚師 第2期'],
    ['type'=>'ed','label'=>'ED1','emoji'=>'🌙','song'=>'静寂の彼方','artist'=>'Yumiko Haze · 破滅召喚師 第2期'],
    ['type'=>'op','label'=>'OP1','emoji'=>'🌊','song'=>'TIDAL FORCE','artist'=>'MYTH&ROID · 藍海傳說'],
    ['type'=>'ed','label'=>'ED1','emoji'=>'🦋','song'=>'蒼の記憶','artist'=>'春野 · 藍海傳說'],
];

// OST 原聲帶
$ost_list = [
    ['emoji'=>'🎼','grad'=>'linear-gradient(135deg,rgba(108,99,255,0.35),rgba(168,85,247,0.2))','title'=>'聲鳴之響 Original Soundtrack Vol.1','tracks'=>'28 首曲目'],
    ['emoji'=>'🔥','grad'=>'linear-gradient(135deg,rgba(255,107,107,0.3),rgba(168,85,247,0.2))','title'=>'破滅召喚師 2nd Season OST','tracks'=>'34 首曲目'],
    ['emoji'=>'🌊','grad'=>'linear-gradient(135deg,rgba(111,236,255,0.25),rgba(108,99,255,0.2))','title'=>'藍海傳說 OST','tracks'=>'22 首曲目'],
    ['emoji'=>'🌸','grad'=>'linear-gradient(135deg,rgba(255,107,174,0.3),rgba(168,85,247,0.15))','title'=>'春色物語 Original Score','tracks'=>'19 首曲目'],
    ['emoji'=>'⚔️','grad'=>'linear-gradient(135deg,rgba(82,214,138,0.25),rgba(108,99,255,0.2))','title'=>'劍界神話 Complete Soundtrack','tracks'=>'41 首曲目'],
];

// 聲優歌手
$va_list = [
    ['emoji'=>'🎤','name'=>'水瀬いのり','role'=>'聲鳴之響 藍澤優音','tag'=>'INORI MINASE'],
    ['emoji'=>'🌟','name'=>'悠木碧','role'=>'聲鳴之響 Luna Aria','tag'=>'AOI YUUKI'],
    ['emoji'=>'🎵','name'=>'LiSA','role'=>'歌手・鬼滅 HINOKAMI','tag'=>'LISA'],
    ['emoji'=>'🦋','name'=>'春野','role'=>'藍海傳說 ED「蒼の記憶」','tag'=>'HARUNO'],
    ['emoji'=>'🎸','name'=>'MYTH&amp;ROID','role'=>'藍海傳說 OP「TIDAL FORCE」','tag'=>'MYTH&amp;ROID'],
    ['emoji'=>'🎹','name'=>'梶浦由記','role'=>'作曲家・劍界神話 OST','tag'=>'YUKI KAJIURA'],
];

// 演唱會
$concert_list = [
    ['month'=>'APR','day'=>'19','name'=>'水瀬いのり LIVE TOUR 2026 "AURORA"','venue'=>'國立台灣大學綜合體育館','status'=>'hot','label'=>'🔥 搶票中'],
    ['month'=>'MAY','day'=>'03','name'=>'LiSA LIVE 2026 "RED SONIC" 台灣場','venue'=>'台北小巨蛋','status'=>'upcoming','label'=>'即將開票'],
    ['month'=>'JUN','day'=>'14','name'=>'聲鳴之響 SPECIAL CONCERT —響鳴祭—','venue'=>'線上直播（NicoNico）','status'=>'upcoming','label'=>'預售中'],
    ['month'=>'JUL','day'=>'20','name'=>'Animelo Summer Live 2026','venue'=>'埼玉超級競技場，東京','status'=>'upcoming','label'=>'即將公布'],
];

// MV / PV
$mv_list = [
    ['type'=>'mv','label'=>'MV','emoji'=>'🎬','grad'=>'linear-gradient(135deg,rgba(108,99,255,0.3),rgba(10,10,15,0.85))','title'=>'魂之歌 Official MV'],
    ['type'=>'pv','label'=>'PV','emoji'=>'🌸','grad'=>'linear-gradient(135deg,rgba(255,107,174,0.3),rgba(10,10,15,0.85))','title'=>'聲鳴之響 PV2'],
    ['type'=>'live','label'=>'LIVE','emoji'=>'⚡','grad'=>'linear-gradient(135deg,rgba(255,107,107,0.3),rgba(10,10,15,0.85))','title'=>'BERSERK ANTHEM Live Ver.'],
    ['type'=>'mv','label'=>'MV','emoji'=>'🌊','grad'=>'linear-gradient(135deg,rgba(111,236,255,0.25),rgba(10,10,15,0.85))','title'=>'TIDAL FORCE Official MV'],
];

// 風格標籤
$style_tags = [
    ['label'=>'🔥 熱門','active'=>true],
    ['label'=>'⚡ J-POP'],['label'=>'🎸 搖滾'],['label'=>'🎹 鋼琴曲'],
    ['label'=>'🌙 療癒系'],['label'=>'⚔️ 史詩'],['label'=>'💃 電子'],
    ['label'=>'🌸 春季新番'],['label'=>'🏆 2026 年度'],['label'=>'👑 神話級'],
    ['label'=>'🎤 聲優歌手'],['label'=>'🎼 管弦樂'],
];

// 精選歌單
$playlist_list = [
    ['emoji'=>'🔥','grad'=>'linear-gradient(135deg,rgba(108,99,255,0.4),rgba(168,85,247,0.25))','count'=>'24首','name'=>'2026 春季 OP 全收錄','sub'=>'本季精選'],
    ['emoji'=>'🌸','grad'=>'linear-gradient(135deg,rgba(255,107,174,0.35),rgba(168,85,247,0.2))','count'=>'18首','name'=>'聲優女神神曲合集','sub'=>'聲優音樂'],
    ['emoji'=>'🌙','grad'=>'linear-gradient(135deg,rgba(111,236,255,0.3),rgba(108,99,255,0.2))','count'=>'31首','name'=>'深夜漫聽 lo-fi 動漫','sub'=>'療癒系'],
    ['emoji'=>'⚔️','grad'=>'linear-gradient(135deg,rgba(255,165,0,0.3),rgba(255,107,107,0.2))','count'=>'15首','name'=>'戰鬥系必備 Playlist','sub'=>'熱血系'],
    ['emoji'=>'🎹','grad'=>'linear-gradient(135deg,rgba(82,214,138,0.3),rgba(108,99,255,0.15))','count'=>'22首','name'=>'鋼琴 OST 名曲精選','sub'=>'管弦樂'],
];
?>

<main id="music-page" class="music-page">

  <!-- ============================ PAGE HERO ============================ -->
  <section class="music-page-hero">
    <div class="container music-page-hero__inner">
      <div class="mph__head">
        <div class="mph__icon">🎵</div>
        <div>
          <div class="mph__eyebrow">ANIME MUSIC HUB</div>
          <h1 class="mph__title">動漫音樂</h1>
        </div>
      </div>
      <p class="mph__desc">探索最新動漫 OP／ED、原聲帶 OST、聲優歌手情報與演唱會資訊</p>

      <div class="mph__filter">
        <span class="music-tag active">🔥 全部</span>
        <span class="music-tag">🎬 OP / ED</span>
        <span class="music-tag">🎼 OST 原聲帶</span>
        <span class="music-tag">🎤 聲優歌手</span>
        <span class="music-tag">🎪 演唱會</span>
        <span class="music-tag">📺 MV / PV</span>
      </div>
    </div>
  </section>

  <!-- ============================ TWO-COLUMN LAYOUT ============================ -->
  <div class="music-page-wrapper">
    <div class="music-layout">

      <!-- ============== LEFT (55%) ============== -->
      <div class="music-left">

        <!-- 本週主打 -->
        <div class="music-hero">
          <div class="music-hero__bg" style="background-image:url('<?php echo esc_url( $weekly_hero['bg_image'] ); ?>');"></div>
          <div class="music-hero__overlay"></div>
          <div class="music-hero__content">
            <div class="music-hero__eyebrow">
              <span class="music-hero__badge"><i class="fa-solid fa-star"></i> <?php echo esc_html( $weekly_hero['badge_text'] ); ?></span>
              <span class="music-hero__badge live"><i class="fa-solid fa-circle"></i> <?php echo esc_html( $weekly_hero['live_text'] ); ?></span>
            </div>
            <h2 class="music-hero__title"><?php echo esc_html( $weekly_hero['title'] ); ?></h2>
            <div class="music-hero__artist"><?php echo esc_html( $weekly_hero['artist'] ); ?></div>
            <div class="music-hero__meta">
              <span class="music-hero__source"><?php echo esc_html( $weekly_hero['source'] ); ?></span>
              <span class="music-hero__type"><i class="fa-regular fa-calendar"></i> <?php echo esc_html( $weekly_hero['season'] ); ?></span>
              <span class="music-hero__type"><i class="fa-solid fa-fire"></i> <?php echo esc_html( $weekly_hero['rank'] ); ?></span>
            </div>
          </div>
          <div class="music-hero__controls">
            <button class="music-ctrl-btn secondary" title="加入收藏" aria-label="收藏"><i class="fa-regular fa-heart"></i></button>
            <button class="music-ctrl-btn secondary" title="分享" aria-label="分享"><i class="fa-solid fa-share-nodes"></i></button>
            <button class="music-ctrl-btn play" title="播放" aria-label="播放"><i class="fa-solid fa-play"></i></button>
          </div>
        </div>

        <!-- OP / ED -->
        <div class="music-section-card">
          <div class="msc-header">
            <div class="msc-title"><i class="fa-solid fa-music"></i> OP / ED 精選</div>
            <a href="#" class="msc-link">全部 <i class="fa-solid fa-chevron-right"></i></a>
          </div>
          <div class="oped-list">
            <?php foreach ( $oped_list as $i => $o ) : ?>
              <div class="oped-item" data-music-id="<?php echo (int)($i+1); ?>" data-type="<?php echo esc_attr( $o['type'] ); ?>">
                <div class="oped-num <?php echo esc_attr( $o['type'] ); ?>"><?php echo esc_html( strtoupper( $o['type'] ) ); ?></div>
                <div class="oped-cover-placeholder"><?php echo $o['emoji']; ?></div>
                <div class="oped-info">
                  <div class="oped-song"><?php echo esc_html( $o['song'] ); ?></div>
                  <div class="oped-artist"><?php echo esc_html( $o['artist'] ); ?></div>
                </div>
                <span class="oped-tag <?php echo esc_attr( $o['type'] ); ?>"><?php echo esc_html( $o['label'] ); ?></span>
                <button class="oped-play" aria-label="播放"><i class="fa-solid fa-play"></i></button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- OST 原聲帶 -->
        <div class="music-section-card">
          <div class="msc-header">
            <div class="msc-title"><i class="fa-solid fa-compact-disc"></i> OST 原聲帶</div>
            <a href="#" class="msc-link">更多 <i class="fa-solid fa-chevron-right"></i></a>
          </div>
          <div class="ost-carousel">
            <?php foreach ( $ost_list as $ost ) : ?>
              <div class="ost-card">
                <div class="ost-card-img-placeholder" style="background:<?php echo esc_attr( $ost['grad'] ); ?>;"><?php echo $ost['emoji']; ?></div>
                <div class="ost-card-body">
                  <div class="ost-card-title"><?php echo esc_html( $ost['title'] ); ?></div>
                  <div class="ost-card-tracks"><?php echo esc_html( $ost['tracks'] ); ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 熱門聲優歌手 -->
        <div class="music-section-card">
          <div class="msc-header">
            <div class="msc-title"><i class="fa-solid fa-microphone"></i> 熱門聲優歌手</div>
            <a href="#" class="msc-link">更多 <i class="fa-solid fa-chevron-right"></i></a>
          </div>
          <div class="va-grid">
            <?php foreach ( $va_list as $i => $va ) : ?>
              <div class="va-card" data-va-id="<?php echo (int)($i+1); ?>">
                <div class="va-avatar-placeholder"><?php echo $va['emoji']; ?></div>
                <div class="va-name"><?php echo $va['name']; ?></div>
                <div class="va-role"><?php echo esc_html( $va['role'] ); ?></div>
                <span class="va-tag"><?php echo $va['tag']; ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- 統計列 -->
          <div class="music-stats-strip">
            <div class="ms-stat">
              <i class="fa-solid fa-users"></i>
              <span class="ms-stat-num">248</span>
              <span class="ms-stat-label">收錄聲優歌手</span>
            </div>
            <div class="ms-stat">
              <i class="fa-solid fa-compact-disc"></i>
              <span class="ms-stat-num">1,340</span>
              <span class="ms-stat-label">收錄歌曲</span>
            </div>
            <div class="ms-stat">
              <i class="fa-solid fa-headphones"></i>
              <span class="ms-stat-num">89萬+</span>
              <span class="ms-stat-label">本月播放</span>
            </div>
          </div>
        </div>

      </div><!-- /music-left -->

      <!-- ============== RIGHT (45%) ============== -->
      <div class="music-right">

        <!-- 即將舉辦演唱會 -->
        <div class="music-section-card">
          <div class="msc-header">
            <div class="msc-title"><i class="fa-solid fa-ticket"></i> 即將舉辦演唱會</div>
            <a href="#" class="msc-link">全部活動 <i class="fa-solid fa-chevron-right"></i></a>
          </div>
          <div class="concert-list">
            <?php foreach ( $concert_list as $i => $c ) : ?>
              <div class="concert-item" data-concert-id="<?php echo (int)($i+1); ?>">
                <div class="concert-date">
                  <div class="concert-date-month"><?php echo esc_html( $c['month'] ); ?></div>
                  <div class="concert-date-day"><?php echo esc_html( $c['day'] ); ?></div>
                </div>
                <div class="concert-info">
                  <div class="concert-name"><?php echo esc_html( $c['name'] ); ?></div>
                  <div class="concert-venue"><i class="fa-solid fa-location-dot"></i> <?php echo esc_html( $c['venue'] ); ?></div>
                </div>
                <span class="concert-status <?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $c['label'] ); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- MV / PV -->
        <div class="music-section-card">
          <div class="msc-header">
            <div class="msc-title"><i class="fa-solid fa-film"></i> MV / PV 精選</div>
            <a href="#" class="msc-link">更多 <i class="fa-solid fa-chevron-right"></i></a>
          </div>
          <div class="mv-grid">
            <?php foreach ( $mv_list as $i => $m ) : ?>
              <div class="mv-card" data-mv-id="<?php echo (int)($i+1); ?>">
                <div class="mv-thumb-placeholder" style="background:<?php echo esc_attr( $m['grad'] ); ?>;"><?php echo $m['emoji']; ?></div>
                <span class="mv-type <?php echo esc_attr( $m['type'] ); ?>"><?php echo esc_html( $m['label'] ); ?></span>
                <div class="mv-play-icon"><i class="fa-solid fa-play"></i></div>
                <div class="mv-label"><?php echo esc_html( $m['title'] ); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 音樂風格探索 -->
        <div class="music-section-card">
          <div class="msc-header">
            <div class="msc-title"><i class="fa-solid fa-tags"></i> 音樂風格探索</div>
          </div>
          <div class="music-tags">
            <?php foreach ( $style_tags as $t ) : ?>
              <span class="music-tag<?php echo ! empty( $t['active'] ) ? ' active' : ''; ?>"><?php echo $t['label']; ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 精選歌單 -->
        <div class="music-section-card">
          <div class="msc-header">
            <div class="msc-title"><i class="fa-solid fa-list"></i> 精選歌單</div>
            <a href="#" class="msc-link">全部 <i class="fa-solid fa-chevron-right"></i></a>
          </div>
          <div class="playlist-row">
            <?php foreach ( $playlist_list as $p ) : ?>
              <div class="playlist-card">
                <div class="playlist-thumb" style="background:<?php echo esc_attr( $p['grad'] ); ?>;">
                  <?php echo $p['emoji']; ?>
                  <span class="playlist-count"><?php echo esc_html( $p['count'] ); ?></span>
                </div>
                <div class="playlist-body">
                  <div class="playlist-name"><?php echo esc_html( $p['name'] ); ?></div>
                  <div class="playlist-sub"><?php echo esc_html( $p['sub'] ); ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /music-right -->

    </div><!-- /music-layout -->

    <!-- Coming Soon -->
    <div class="coming-soon-card">
      <div class="coming-soon-inner">
        <div class="cs-icon">🚀</div>
        <div class="cs-text">更多內容即將上線！敬請期待 ✨</div>
        <div class="cs-sub">歌詞資料庫、即時播放、演唱會訂票整合、音樂評分等功能正在開發中</div>
      </div>
    </div>

  </div><!-- /music-page-wrapper -->

</main>

<?php
// 簡易互動腳本（filter tag active 切換、播放鈕 toggle）
?>
<script>
(function(){
  document.querySelectorAll('.music-tags').forEach(function(group){
    group.addEventListener('click', function(e){
      var t = e.target.closest('.music-tag');
      if(!t) return;
      group.querySelectorAll('.music-tag').forEach(function(x){ x.classList.remove('active'); });
      t.classList.add('active');
    });
  });
  document.querySelectorAll('.oped-play').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      var icon = this.querySelector('i');
      document.querySelectorAll('.oped-play i').forEach(function(i){ if(i!==icon){ i.className='fa-solid fa-play'; }});
      icon.classList.toggle('fa-play');
      icon.classList.toggle('fa-pause');
    });
  });
})();
</script>

<?php get_footer(); ?>
