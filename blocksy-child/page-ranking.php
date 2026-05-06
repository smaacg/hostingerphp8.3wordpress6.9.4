<?php
/**
 * Template Name: 動漫排行榜
 * Template Post Type: page
 */
get_header(); ?>

<!-- ================================================================
     HERO
     ================================================================ -->
<section class="rank-hero">
  <canvas id="rank-particles" class="rank-hero__particles" aria-hidden="true"></canvas>
  <div class="rank-hero__overlay"></div>
  <div class="container rank-hero__content">
    <div class="rank-hero__eyebrow">
      <span class="chip"><i class="fa-solid fa-database"></i> 多平台整合數據</span>
      <span class="chip chip--green"><i class="fa-solid fa-clock-rotate-left"></i> 每小時更新</span>
    </div>
    <h1 class="rank-hero__title">
      <span class="rank-hero__trophy">🏆</span>
      動漫排行榜
    </h1>
    <p class="rank-hero__subtitle">整合全球四大平台數據，發現真正的神作</p>
    <div class="rank-hero__stats">
      <div class="rank-hero__stat">
        <span class="rank-hero__stat-num grad-text">4</span>
        <span class="rank-hero__stat-label">資料平台</span>
      </div>
      <div class="rank-hero__stat-divider"></div>
      <div class="rank-hero__stat">
        <span class="rank-hero__stat-num grad-text">500萬+</span>
        <span class="rank-hero__stat-label">全球評分數</span>
      </div>
      <div class="rank-hero__stat-divider"></div>
      <div class="rank-hero__stat">
        <span class="rank-hero__stat-num grad-text">Top 20</span>
        <span class="rank-hero__stat-label">每榜收錄</span>
      </div>
    </div>
  </div>
</section>

<!-- ================================================================
     第一層分類頁籤
     ================================================================ -->
<div class="rank-category-row">
  <div class="container">
    <div class="rank-category-tabs" id="rank-category-tabs">
      <div class="rank-category-tab active" data-category="anime">🎬 動畫</div>
      <div class="rank-category-tab coming-soon" data-category="manga" title="即將推出">
        📖 漫畫 <span class="soon-badge">Coming Soon</span>
      </div>
      <div class="rank-category-tab coming-soon" data-category="novel" title="即將推出">
        📚 輕小說 <span class="soon-badge">Coming Soon</span>
      </div>
      <div class="rank-category-tab coming-soon" data-category="music" title="即將推出">
        🎵 音樂 <span class="soon-badge">Coming Soon</span>
      </div>
      <div class="rank-category-tab coming-soon" data-category="game" title="即將推出">
        🎮 遊戲 <span class="soon-badge">Coming Soon</span>
      </div>
      <div class="rank-category-tab coming-soon" data-category="vtuber" title="即將推出">
        📺 VTuber <span class="soon-badge">Coming Soon</span>
      </div>
    </div>
  </div>
</div>

<!-- ================================================================
     主體內容區
     ================================================================ -->
<section class="rank-body section">
  <div class="container">
    <div class="rank-layout">

      <!-- 左欄：排行榜主區 -->
      <div class="rank-main">

        <!-- 平台頁籤
             ★ 修改：移除 site tab 上的 style="display:none" 佔位，
               改由 JS 依真實狀態控制顯示 -->
        <div class="rank-platform-row">
          <div class="rank-platform-tabs" id="rank-platform-tabs">
            <button class="rank-platform-tab" data-platform="site">⭐ weixiaoacg+</button>
            <button class="rank-platform-tab active" data-platform="anilist">🌐 AniList</button>
            <button class="rank-platform-tab" data-platform="mal">📊 MAL / Jikan</button>
            <button class="rank-platform-tab" data-platform="bangumi">🎯 Bangumi</button>
          </div>
        </div>

        <!-- 站內子分類
             ★ 修改：切換到 site 時顯示，有 rating 真實資料，
               views / favorites 顯示「即將上線」 -->
        <div class="site-sub-row" id="site-sub-tabs" style="display:none;">
          <button class="site-sub-btn active" data-rank-type="rating">⭐ 評分最高</button>
          <button class="site-sub-btn" data-rank-type="views">🔥 最多瀏覽</button>
          <button class="site-sub-btn" data-rank-type="favorites">💖 最多收藏</button>
        </div>

        <!-- 平台介紹卡 -->
        <div class="platform-info-card glass-card" id="platform-info-card"></div>

        <!-- 時間週期 + 計數
             ★ 修改：site 平台時隱藏 period-btns（站內排行無時間維度） -->
        <div class="rank-toolbar">
          <div class="period-btns" id="period-btns">
            <button class="period-btn" data-period="daily">今日</button>
            <button class="period-btn active" data-period="weekly">本週</button>
            <button class="period-btn" data-period="monthly">本月</button>
            <button class="period-btn" data-period="yearly">年度</button>
          </div>
          <span class="rank-count-info" id="rank-count-info">本週 AniList 排行 · Top 20</span>
        </div>

        <!-- 排行列表 -->
        <div class="rank-list" id="rank-list">
          <div class="rank-loading">
            <div class="skeleton" style="height:90px;border-radius:16px;"></div>
            <div class="skeleton" style="height:90px;border-radius:16px;margin-top:10px;"></div>
            <div class="skeleton" style="height:90px;border-radius:16px;margin-top:10px;"></div>
          </div>
        </div>

        <!-- 站內排行底部提示
             ★ 修改：切換到 site 時才顯示 -->
        <p class="site-rank-note" id="site-rank-note" style="display:none;">
          <i class="fa-solid fa-circle-info"></i>
          數據來自本站會員的真實評分（貝葉斯加權），至少 1 人評分即入榜
        </p>

      </div><!-- /rank-main -->

      <!-- 右欄：側欄 -->
      <aside class="rank-sidebar">

        <!-- 本週新上榜 -->
        <div class="rank-sidebar-card glass-mid">
          <h3 class="rank-sidebar-title">
            <i class="fa-solid fa-circle-plus" style="color:var(--accent-cyan);"></i>
            本週新上榜 🆕
          </h3>
          <div class="sb-rank-list" id="sidebar-new-list"></div>
        </div>

        <!-- 排名變動最大 -->
        <div class="rank-sidebar-card glass-mid">
          <h3 class="rank-sidebar-title">
            <i class="fa-solid fa-chart-line" style="color:var(--accent-violet);"></i>
            本週排名變動 📈
          </h3>
          <div class="sb-rank-list" id="sidebar-movers-list"></div>
        </div>

        <!-- 平台說明 -->
        <div class="rank-sidebar-card glass-mid">
          <h3 class="rank-sidebar-title">
            <i class="fa-solid fa-circle-question" style="color:var(--accent-blue);"></i>
            平台說明
          </h3>
          <div class="platform-guide">
            <div class="pg-item">
              <span class="pg-icon" style="background:rgba(108,99,255,0.15);color:#6c63ff;">⭐</span>
              <div class="pg-info">
                <div class="pg-name">weixiaoacg+</div>
                <div class="pg-desc">本站社群真實數據</div>
              </div>
            </div>
            <div class="pg-item">
              <span class="pg-icon" style="background:rgba(2,169,255,0.15);color:#02a9ff;">🌐</span>
              <div class="pg-info">
                <div class="pg-name">AniList</div>
                <div class="pg-desc">歐美社群・精細評分</div>
              </div>
            </div>
            <div class="pg-item">
              <span class="pg-icon" style="background:rgba(46,81,162,0.15);color:#4d7fff;">📊</span>
              <div class="pg-info">
                <div class="pg-name">MAL / Jikan</div>
                <div class="pg-desc">全球最大・歷史最完整</div>
              </div>
            </div>
            <div class="pg-item">
              <span class="pg-icon" style="background:rgba(240,145,153,0.15);color:#f09199;">🎯</span>
              <div class="pg-info">
                <div class="pg-name">Bangumi</div>
                <div class="pg-desc">華語圈・聲優資料詳盡</div>
              </div>
            </div>
          </div>
          <p style="font-size:11px;color:var(--text-muted);margin-top:12px;line-height:1.6;">
            各平台用戶組成不同，排名順序可能有差異，體現真實的口味多元性。
          </p>
        </div>

      </aside><!-- /rank-sidebar -->

    </div><!-- /rank-layout -->
  </div>
</section>

<div class="toast-container" id="toast-container"></div>

<?php get_footer(); ?>
