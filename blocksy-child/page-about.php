<?php
/**
 * Template Name: 關於本站
 * Template Post Type: page
 *
 * @package weixiaoacg
 * @version 1.3.0 (2026-05-17)
 *
 * Changelog:
 *   1.3.0 — 「我們的故事」改為完整品牌歷史時間軸（2008 → 2026）
 *           涵蓋微笑動漫組起源、RC 語音社群、轉折、沉寂、品牌延續、AI 時代重啟
 *   1.2.0 — 新增「我們的故事」獨立區塊（漸層卡片）
 *   1.1.0 — 手機 RWD 優化、Logo 改用 header 的真實 logo、
 *           「推廣台灣 ACG 文化」改為「推廣華人 ACG 文化」
 */
get_header(); ?>

<style>
/* ── Hero ── */
.page-hero--about {
  background-color: #0f1119;
  background-image:
    radial-gradient(circle at 20% 20%, rgba(59,130,246,0.28) 0%, transparent 55%),
    radial-gradient(circle at 80% 30%, rgba(139,92,246,0.28) 0%, transparent 55%),
    linear-gradient(135deg, rgba(59,130,246,0.18) 0%, rgba(139,92,246,0.18) 60%, rgba(236,72,153,0.12) 100%);
  border-bottom: 1px solid var(--glass-border);
  padding: 64px 20px 48px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.page-hero--about::before {
  content: "";
  position: absolute; inset: 0;
  background: radial-gradient(ellipse at center top, rgba(255,255,255,0.06), transparent 70%);
  pointer-events: none;
}
.page-hero--about > .container { position: relative; z-index: 1; }

.about-page-title { font-size: 40px; font-weight: 800; color: var(--text-primary); margin-bottom: 12px; line-height: 1.25; }
.about-page-subtitle { font-size: 16px; color: var(--text-muted); max-width: 560px; margin: 0 auto; line-height: 1.7; }

.about-logo-box {
  width: 88px; height: 88px;
  border-radius: 22px;
  background: linear-gradient(135deg, rgba(59,130,246,0.18), rgba(139,92,246,0.18));
  display: inline-flex; align-items: center; justify-content: center;
  margin: 0 auto 20px;
  box-shadow: 0 8px 32px rgba(59,130,246,0.35);
  border: 1px solid rgba(255,255,255,0.08);
}
.about-logo-box img { width: 64px; height: 64px; object-fit: contain; display: block; }

/* ══════════════════════════════════════════
   「我們的故事」時間軸區塊
══════════════════════════════════════════ */
.story-section { padding: 72px 0 56px; position: relative; }

.story-header {
  text-align: center;
  margin-bottom: 56px;
  position: relative;
}
.story-eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 4px;
  color: var(--accent-blue);
  margin-bottom: 14px;
  text-transform: uppercase;
}
.story-main-title {
  font-size: 36px;
  font-weight: 800;
  color: var(--text-primary);
  margin-bottom: 14px;
  line-height: 1.3;
}
.story-main-title .accent {
  background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
.story-main-subtitle {
  font-size: 15px;
  color: var(--text-muted);
  max-width: 580px;
  margin: 0 auto;
  line-height: 1.7;
}

/* 時間軸主體 */
.timeline {
  position: relative;
  max-width: 880px;
  margin: 0 auto;
  padding: 0 0 20px 0;
}
.timeline::before {
  content: "";
  position: absolute;
  left: 32px;
  top: 8px;
  bottom: 8px;
  width: 2px;
  background: linear-gradient(180deg,
    rgba(59,130,246,0.6) 0%,
    rgba(139,92,246,0.6) 50%,
    rgba(236,72,153,0.5) 100%);
  border-radius: 2px;
}

.timeline-item {
  position: relative;
  padding-left: 80px;
  margin-bottom: 36px;
}
.timeline-item:last-child { margin-bottom: 0; }

.timeline-dot {
  position: absolute;
  left: 16px;
  top: 18px;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  box-shadow: 0 0 0 4px #0f1119, 0 4px 16px rgba(59,130,246,0.4);
  z-index: 1;
}
.timeline-item.is-warning .timeline-dot { background: linear-gradient(135deg, #f59e0b, #ef4444); box-shadow: 0 0 0 4px #0f1119, 0 4px 16px rgba(245,158,11,0.4); }
.timeline-item.is-growth .timeline-dot  { background: linear-gradient(135deg, #22c55e, #10b981); box-shadow: 0 0 0 4px #0f1119, 0 4px 16px rgba(34,197,94,0.4); }
.timeline-item.is-restart .timeline-dot { background: linear-gradient(135deg, #ec4899, #f472b6); box-shadow: 0 0 0 4px #0f1119, 0 4px 16px rgba(236,72,153,0.4); }

.timeline-card {
  border-radius: 20px;
  padding: 24px 26px;
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
  transition: var(--trans-smooth);
  position: relative;
}
.timeline-card:hover {
  transform: translateX(4px);
  border-color: rgba(139,92,246,0.35);
  box-shadow: 0 12px 32px rgba(0,0,0,0.3);
}

.timeline-year {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 2px;
  color: var(--accent-blue);
  background: rgba(59,130,246,0.12);
  padding: 4px 10px;
  border-radius: var(--radius-pill);
  margin-bottom: 12px;
}
.timeline-item.is-warning .timeline-year { color: #fbbf24; background: rgba(245,158,11,0.12); }
.timeline-item.is-growth .timeline-year  { color: #4ade80; background: rgba(34,197,94,0.12); }
.timeline-item.is-restart .timeline-year { color: #f472b6; background: rgba(236,72,153,0.14); }

.timeline-title {
  font-size: 19px;
  font-weight: 800;
  color: var(--text-primary);
  margin-bottom: 12px;
  line-height: 1.4;
}
.timeline-text {
  font-size: 14.5px;
  color: var(--text-secondary);
  line-height: 1.85;
  margin-bottom: 10px;
}
.timeline-text:last-child { margin-bottom: 0; }
.timeline-text strong { color: var(--text-primary); font-weight: 700; }

.timeline-list {
  margin: 12px 0 4px 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.timeline-list li {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.7;
  padding-left: 24px;
  position: relative;
}
.timeline-list li::before {
  content: "→";
  position: absolute;
  left: 0; top: 0;
  color: var(--accent-blue);
  font-weight: 800;
}
.timeline-item.is-warning .timeline-list li::before { color: #fbbf24; }
.timeline-item.is-growth .timeline-list li::before  { color: #4ade80; }
.timeline-item.is-restart .timeline-list li::before { color: #f472b6; }

/* 結尾語錄卡 */
.story-quote {
  margin-top: 48px;
  max-width: 720px;
  margin-left: auto;
  margin-right: auto;
  padding: 36px 32px;
  border-radius: 24px;
  text-align: center;
  background:
    radial-gradient(circle at 15% 20%, rgba(59,130,246,0.18) 0%, transparent 50%),
    radial-gradient(circle at 85% 80%, rgba(236,72,153,0.16) 0%, transparent 50%),
    linear-gradient(135deg, rgba(139,92,246,0.12), rgba(59,130,246,0.08));
  border: 1px solid rgba(255,255,255,0.10);
  position: relative;
  overflow: hidden;
}
.story-quote-mark {
  font-size: 48px;
  line-height: 1;
  color: rgba(139,92,246,0.5);
  font-family: Georgia, serif;
  margin-bottom: 8px;
}
.story-quote-text {
  font-size: 17px;
  color: var(--text-primary);
  line-height: 1.85;
  font-weight: 600;
  margin-bottom: 8px;
}
.story-quote-text .accent {
  background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
.story-quote-sub {
  font-size: 14px;
  color: var(--text-muted);
  line-height: 1.7;
}

/* ── 章節 ── */
.about-section { padding: 56px 0; }
.about-section + .about-section { border-top: 1px solid var(--glass-border); }
.about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: center; }
.about-h2 { font-size: 26px; font-weight: 800; color: var(--text-primary); margin-bottom: 16px; line-height: 1.3; }
.about-p { font-size: 15px; color: var(--text-secondary); line-height: 1.8; margin-bottom: 14px; }

/* ── 使命卡 ── */
.mission-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 32px; }
.mission-card { border-radius: 20px; padding: 28px 24px; background: var(--glass-bg); border: 1px solid var(--glass-border); transition: var(--trans-smooth); }
.mission-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.3); }
.mission-icon { font-size: 28px; margin-bottom: 12px; line-height: 1; }
.mission-title { font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; line-height: 1.4; }
.mission-desc { font-size: 13px; color: var(--text-muted); line-height: 1.75; }

/* ── 功能卡 ── */
.feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 48px; }
.feature-card { border-radius: 20px; padding: 28px 24px; background: var(--glass-bg); border: 1px solid var(--glass-border); transition: var(--trans-smooth); }
.feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.3); }
.feature-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px; }
.feature-title { font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; line-height: 1.4; }
.feature-desc { font-size: 13px; color: var(--text-muted); line-height: 1.75; }

/* ── 數據 ── */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 48px; }
.stat-box { text-align: center; padding: 28px 20px; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 20px; }
.stat-num { font-size: 36px; font-weight: 800; color: var(--accent-blue); margin-bottom: 4px; line-height: 1.1; }
.stat-label { font-size: 13px; color: var(--text-muted); }

/* ── API ── */
.api-list { display: flex; flex-direction: column; gap: 12px; }
.api-item { display: flex; align-items: center; gap: 16px; padding: 16px 20px; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 14px; flex-wrap: wrap; }
.api-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.api-name { font-size: 14px; font-weight: 700; color: var(--text-primary); min-width: 140px; }
.api-desc { font-size: 13px; color: var(--text-muted); flex: 1; min-width: 0; }

/* ── 結語 ── */
.closing-box { text-align: center; padding: 48px 32px; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 24px; margin-top: 48px; }
.closing-box h3 { font-size: 22px; font-weight: 800; color: var(--text-primary); margin-bottom: 16px; line-height: 1.4; }
.closing-box p { font-size: 15px; color: var(--text-muted); line-height: 1.8; max-width: 520px; margin: 0 auto 12px; }

/* ── 法律 ── */
.legal-links { display: flex; gap: 20px; flex-wrap: wrap; }
.legal-link {
  padding: 12px 22px;
  border-radius: var(--radius-pill);
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  transition: var(--trans-fast);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
}
.legal-link:hover { color: var(--accent-blue); border-color: rgba(59,130,246,0.4); }

/* ══════════════════════════════════════════
   RWD：平板（≤900px）
══════════════════════════════════════════ */
@media (max-width: 900px) {
  .about-grid { grid-template-columns: 1fr; gap: 24px; }
  .feature-grid { grid-template-columns: 1fr 1fr; }
  .stats-row { grid-template-columns: repeat(2, 1fr); }
  .mission-grid { grid-template-columns: 1fr; }
  .story-main-title { font-size: 30px; }
}

/* ══════════════════════════════════════════
   RWD：手機（≤600px）
══════════════════════════════════════════ */
@media (max-width: 600px) {
  .page-hero--about { padding: 40px 16px 32px; }
  .about-logo-box { width: 72px; height: 72px; border-radius: 18px; margin-bottom: 16px; }
  .about-logo-box img { width: 52px; height: 52px; }
  .about-page-title { font-size: 26px; margin-bottom: 10px; }
  .about-page-subtitle { font-size: 14px; line-height: 1.65; padding: 0 4px; }
  .about-page-subtitle br { display: none; }

  /* 故事區塊手機緊湊化 */
  .story-section { padding: 48px 0 36px; }
  .story-header { margin-bottom: 36px; }
  .story-eyebrow { font-size: 11px; letter-spacing: 3px; margin-bottom: 10px; }
  .story-main-title { font-size: 24px; margin-bottom: 10px; }
  .story-main-subtitle { font-size: 14px; padding: 0 8px; }

  /* 時間軸手機收緊 */
  .timeline::before { left: 22px; }
  .timeline-item { padding-left: 60px; margin-bottom: 28px; }
  .timeline-dot { left: 6px; width: 32px; height: 32px; font-size: 15px; }
  .timeline-card { padding: 20px 18px; border-radius: 16px; }
  .timeline-card:hover { transform: none; }
  .timeline-title { font-size: 17px; margin-bottom: 10px; }
  .timeline-text { font-size: 14px; line-height: 1.8; }
  .timeline-list li { font-size: 13.5px; padding-left: 20px; }

  /* 語錄卡 */
  .story-quote { margin-top: 36px; padding: 28px 20px; border-radius: 18px; }
  .story-quote-mark { font-size: 38px; }
  .story-quote-text { font-size: 15.5px; }
  .story-quote-sub { font-size: 13px; }

  .about-section { padding: 36px 0; }
  .about-h2 { font-size: 22px; margin-bottom: 12px; }
  .about-p { font-size: 14px; line-height: 1.75; }

  .feature-grid,
  .stats-row { grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 28px; }
  .mission-grid { gap: 14px; margin-top: 24px; }

  .mission-card,
  .feature-card { padding: 22px 18px; border-radius: 16px; }
  .mission-icon { font-size: 26px; }
  .mission-title,
  .feature-title { font-size: 15px; }
  .mission-desc,
  .feature-desc { font-size: 13px; }

  .stat-box { padding: 22px 14px; border-radius: 16px; }
  .stat-num { font-size: 28px; }
  .stat-label { font-size: 12px; }

  .api-item { padding: 14px 16px; gap: 10px; }
  .api-name { min-width: 0; flex: 1 0 100%; }
  .api-desc { flex: 1 0 100%; }

  .closing-box { padding: 32px 20px; border-radius: 18px; margin-top: 32px; }
  .closing-box h3 { font-size: 19px; }
  .closing-box p { font-size: 14px; }

  .legal-links { gap: 12px; }
  .legal-link { padding: 11px 18px; font-size: 13px; }
}

@media (max-width: 380px) {
  .feature-grid,
  .stats-row { grid-template-columns: 1fr; }
  .about-page-title { font-size: 23px; }
  .story-main-title { font-size: 22px; }
}
</style>

<!-- HERO -->
<div class="page-hero--about">
  <div class="container">
    <div class="about-logo-box">
      <img src="https://darkcyan-alpaca-757238.hostingersite.com/wp-content/uploads/2026/05/DHBdKsLa.png"
           alt="微笑動漫 Logo"
           loading="eager"
           decoding="async" />
    </div>
    <h1 class="about-page-title">微笑動漫 WeixiaoACG</h1>
    <p class="about-page-subtitle">一個剛起步、但認真的動漫資訊小站。<br>我們在這裡，慢慢長大。</p>
  </div>
</div>

<main class="container">

  <!-- ★ 我們的故事（時間軸） ★ -->
  <section class="story-section">
    <div class="story-header">
      <span class="story-eyebrow">OUR STORY</span>
      <h2 class="story-main-title">🌸 我們的<span class="accent">故事</span></h2>
      <p class="story-main-subtitle">從 2008 年的微笑動漫組，到 2026 年的 AI 時代重啟，這是一段橫跨將近二十年的旅程。</p>
    </div>

    <div class="timeline">

      <!-- 2008：起點 -->
      <div class="timeline-item">
        <div class="timeline-dot">🌱</div>
        <div class="timeline-card">
          <span class="timeline-year">2008 · 起點</span>
          <h3 class="timeline-title">我們的起點 — 微笑動漫組誕生</h3>
          <p class="timeline-text">微笑動漫的故事，最早可以追溯到 <strong>2008 年</strong>。當時的網路環境仍在發展階段，連線速度有限，觀看動畫多半需要先下載檔案，再透過 <strong>FLV、RMVB 或 MP4</strong> 等格式播放。</p>
          <p class="timeline-text">在這樣的時代背景下，「微笑動漫組」誕生了。最初，我們只是一群因為熱愛動漫而聚集的人，從分享作品、整理資訊開始，逐漸形成一個小型社群。</p>
          <p class="timeline-text">隨著時間推進，社群曾擴展至數十位成員，包含字幕製作、內容整理與交流討論，成為當時 ACG 圈中活躍的一部分。</p>
          <p class="timeline-text" style="color:var(--text-muted);font-style:italic;">那是一段單純的時光——因為喜歡，所以聚在一起。</p>
        </div>
      </div>

      <!-- RC 語音時期 -->
      <div class="timeline-item">
        <div class="timeline-dot">🎙️</div>
        <div class="timeline-card">
          <span class="timeline-year">早期 · 社群高峰</span>
          <h3 class="timeline-title">社群的成長與交流 — RC 語音時代</h3>
          <p class="timeline-text">除了網站之外，我們也曾在 <strong>RC 語音</strong>等平台活躍，與許多動漫愛好者一起建立線上社群。</p>
          <p class="timeline-text">在那個時期，大家會一起聽音樂、聊天、唱歌、分享作品與日常，形成一個充滿互動與溫度的 ACG 交流空間。</p>
          <p class="timeline-text">那段時間，是微笑動漫社群文化的重要階段，也讓我們理解——<strong>動漫不只是作品，更是一種連結彼此的文化</strong>。</p>
        </div>
      </div>

      <!-- 轉折 -->
      <div class="timeline-item is-warning">
        <div class="timeline-dot">⚠️</div>
        <div class="timeline-card">
          <span class="timeline-year">轉折 · 學習</span>
          <h3 class="timeline-title">成長與轉折 — 我們學到了重要的一課</h3>
          <p class="timeline-text">在早期的網路環境與經驗尚未成熟的階段，我們對於著作權與授權制度的理解並不完整，平台內容曾涉及未經授權的整理與分享。</p>
          <p class="timeline-text">隨著相關規範的落實與社群環境變化，網站與社群最終停止運作。這段經歷讓我們深刻理解：</p>
          <ul class="timeline-list">
            <li>創作需要被尊重</li>
            <li>內容需要正確的授權與來源</li>
            <li>產業需要健康的循環</li>
          </ul>
          <p class="timeline-text" style="margin-top:14px;">這也成為微笑動漫<strong>最重要的一次學習與轉變</strong>。</p>
        </div>
      </div>

      <!-- 沉寂 -->
      <div class="timeline-item">
        <div class="timeline-dot">🌙</div>
        <div class="timeline-card">
          <span class="timeline-year">沉寂期</span>
          <h3 class="timeline-title">沉寂與重新思考</h3>
          <p class="timeline-text">隨著時間推進，原有社群逐漸分散，大家也各自進入不同的人生階段。但「微笑動漫」這個名字與理念，<strong>始終沒有真正消失</strong>，它一直存在於我們對動漫文化的熱愛之中。</p>
          <p class="timeline-text">多年來，我們持續思考一件事——<strong>如何用更好的方式，重新建立一個屬於動漫愛好者的空間</strong>。</p>
        </div>
      </div>

      <!-- 品牌延續 -->
      <div class="timeline-item is-growth">
        <div class="timeline-dot">🌐</div>
        <div class="timeline-card">
          <span class="timeline-year">品牌延續</span>
          <h3 class="timeline-title">品牌延續與網域轉換</h3>
          <p class="timeline-text">早期使用的 <strong>smaacg.com</strong> 曾承載微笑動漫的社群與內容。隨著網域進入市場機制後，續用成本顯著提高，已不適合長期維運。</p>
          <p class="timeline-text">因此，我們選擇以新的主要網域：<br>
            👉 <strong style="color:var(--accent-blue);">weixiaoacg.com</strong> 作為微笑動漫的正式平台，重新建立穩定且可持續的內容架構。</p>
          <p class="timeline-text" style="color:var(--text-primary);font-weight:700;">這是一個新的開始，而不是結束。</p>
        </div>
      </div>

      <!-- 2026：AI 時代重啟 -->
      <div class="timeline-item is-restart">
        <div class="timeline-dot">🚀</div>
        <div class="timeline-card">
          <span class="timeline-year">2026 · 重新出發</span>
          <h3 class="timeline-title">AI 時代的微笑動漫</h3>
          <p class="timeline-text">進入 <strong>2026 年</strong>後，隨著 AI 與數位內容技術快速發展，我們開始重新思考動漫文化的可能性。</p>
          <p class="timeline-text">我們相信，未來的動漫平台不只是資訊整理，而可以是：</p>
          <ul class="timeline-list">
            <li>更<strong>互動</strong>的內容呈現</li>
            <li>更<strong>有創意</strong>的表達方式</li>
            <li>更<strong>具參與感</strong>的文化空間</li>
          </ul>
          <p class="timeline-text" style="margin-top:14px;color:var(--text-primary);font-weight:700;">微笑動漫，正式重新啟動。</p>
        </div>
      </div>

      <!-- 現在 -->
      <div class="timeline-item">
        <div class="timeline-dot">✨</div>
        <div class="timeline-card">
          <span class="timeline-year">現在</span>
          <h3 class="timeline-title">我們現在在做的事</h3>
          <p class="timeline-text">目前微笑動漫專注於：</p>
          <ul class="timeline-list">
            <li>動漫作品介紹與整理</li>
            <li>ACG 文化內容分享</li>
            <li>新番與熱門作品資訊</li>
            <li>社群交流與討論</li>
            <li>結合新技術的內容呈現方式</li>
          </ul>
          <p class="timeline-text" style="margin-top:14px;">我們希望打造一個<strong>可以長期運作、持續成長的動漫文化平台</strong>。</p>
        </div>
      </div>

    </div><!-- /.timeline -->

    <!-- 結尾語錄 -->
    <div class="story-quote">
      <div class="story-quote-mark">“</div>
      <p class="story-quote-text">喜歡動漫，不只是觀看，而是一種可以<span class="accent">共同參與的文化</span>。</p>
      <p class="story-quote-sub">從 FC2 時代的起點，到 RC 語音的社群高峰，再到沉寂與重啟——微笑動漫並不是消失，而是經歷了一段長時間的成長與轉變。<br>如今，我們選擇在 AI 時代重新開始。<strong style="color:var(--text-primary);">不是回到過去，而是走向下一個階段。</strong></p>
    </div>
  </section>

  <!-- 關於我們 -->
  <section class="about-section">
    <div class="about-grid">
      <div>
        <h2 class="about-h2">關於微笑動漫</h2>
        <p class="about-p">老實說，我們現在還很小。資料還在慢慢建立，社群也才剛起步。但從第一天開始，我們對動漫的熱情和認真的態度從來沒有變過。</p>
        <p class="about-p">微笑動漫 WeixiaoACG 是一個由動漫愛好者建立的繁體中文 ACG 資訊平台。我們整合 AniList、Bangumi、MyAnimeList 三大國際資料庫，以認真的態度為每一部動漫作品留下完整的中文資訊。</p>
        <p class="about-p">這裡還小，但會一直長大。歡迎你一起見證這個過程。</p>
      </div>
      <div>
        <h2 class="about-h2">為什麼叫「微笑動漫」</h2>
        <p class="about-p">「微笑」是我們的初心。動漫帶給我們的，從來不只是娛樂，而是那些讓人會心一笑、熱淚盈眶、甚至改變人生觀的瞬間。</p>
        <p class="about-p">我們希望每一個來到這裡的人，都能帶著輕鬆愉快的心情享受屬於自己的動漫時光。ACG 不應該是爭論與對立的地方，而是讓人微笑、讓人感動、讓人找到共鳴的地方。</p>
      </div>
    </div>
  </section>

  <!-- 使命 -->
  <section class="about-section">
    <h2 class="about-h2" style="text-align:center;margin-bottom:8px;">我們想做的事</h2>
    <p style="text-align:center;color:var(--text-muted);font-size:14px;margin-bottom:0;">不只是資料庫，是一個讓華人動漫文化被看見的地方</p>
    <div class="mission-grid">
      <div class="mission-card">
        <div class="mission-icon">🌏</div>
        <div class="mission-title">讓動漫被更多人看見</div>
        <div class="mission-desc">無論是當季新番、被遺忘的冷門佳作，還是陪伴一代人成長的經典老番，每一部作品都應該有機會被發現。我們希望成為那個讓好作品不被埋沒的地方。</div>
      </div>
      <div class="mission-card">
        <div class="mission-icon">🌏</div>
        <div class="mission-title">推廣華人 ACG 文化</div>
        <div class="mission-desc">華人世界有深厚的動漫文化底蘊，有無數默默熱愛 ACG 的人。微笑動漫希望成為這股力量的聚集地，讓所有華人動漫愛好者有一個真正屬於自己的高品質繁體中文平台。</div>
      </div>
      <div class="mission-card">
        <div class="mission-icon">💡</div>
        <div class="mission-title">翻轉動漫的刻板印象</div>
        <div class="mission-desc">「看動漫是幼稚的」——這個時代這句話早該被翻轉了。動漫裡有哲學、有歷史、有人性、有藝術。我們希望透過認真的資訊呈現，讓更多人重新認識 ACG 文化的深度與價值。</div>
      </div>
      <div class="mission-card">
        <div class="mission-icon">🤝</div>
        <div class="mission-title">建立正能量的 ACG 社群</div>
        <div class="mission-desc">不管你是剛入坑的新手還是追了二十年的老宅，在微笑動漫都能找到屬於你的位置。沒有資歷歧視，只有對動漫共同的熱愛。</div>
      </div>
    </div>
  </section>

  <!-- 特色功能 -->
  <section class="about-section">
    <h2 class="about-h2" style="text-align:center;margin-bottom:8px;">目前我們提供</h2>
    <p style="text-align:center;color:var(--text-muted);font-size:14px;margin-bottom:0;">整合多個頂級動漫資料庫，為你帶來最完整的 ACG 體驗</p>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(59,130,246,0.15);color:var(--accent-blue);">📅</div>
        <div class="feature-title">本季新番週曆</div>
        <div class="feature-desc">依星期分組瀏覽當季所有播出作品，掌握最新播出動態，不漏追任何一部。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(139,92,246,0.15);color:#a78bfa;">🎵</div>
        <div class="feature-title">OP/ED 試聽</div>
        <div class="feature-desc">整合 AnimeThemes.moe，完整收錄歷年動畫 OP/ED，隨時試聽你最喜愛的主題曲。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">📖</div>
        <div class="feature-title">繁體中文資料</div>
        <div class="feature-desc">整合 Bangumi 資料庫並自動轉換為繁體中文，劇情、製作資訊完整呈現。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(249,115,22,0.15);color:#f97316;">📊</div>
        <div class="feature-title">三平台評分</div>
        <div class="feature-desc">同時顯示 AniList、MyAnimeList、Bangumi 三大平台評分，讓你做出最佳選擇。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(239,68,68,0.15);color:#f87171;">🎬</div>
        <div class="feature-title">角色與聲優</div>
        <div class="feature-desc">完整列出主要角色及配音聲優資訊，搭配頭像一目了然。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(251,191,36,0.15);color:#fbbf24;">⭐</div>
        <div class="feature-title">追番進度記錄</div>
        <div class="feature-desc">本地記錄追番進度與收藏清單，快速掌握你的追番狀態。</div>
      </div>
    </div>
  </section>

  <!-- 數據統計 -->
  <section class="about-section">
    <h2 class="about-h2" style="text-align:center;">資料規模</h2>
    <div class="stats-row">
      <div class="stat-box">
        <div class="stat-num"><?php echo number_format( wp_count_posts('anime')->publish ?? 0 ); ?></div>
        <div class="stat-label">動畫作品</div>
      </div>
      <div class="stat-box"><div class="stat-num">4</div><div class="stat-label">資料來源 API</div></div>
      <div class="stat-box"><div class="stat-num">100%</div><div class="stat-label">繁體中文介面</div></div>
      <div class="stat-box"><div class="stat-num">免費</div><div class="stat-label">完全免費使用</div></div>
    </div>
  </section>

  <!-- 資料來源 -->
  <section class="about-section">
    <div class="about-grid">
      <div>
        <h2 class="about-h2">資料來源</h2>
        <p class="about-p">本站整合多個業界頂級動漫資料庫，所有資料均透過官方公開 API 取得並快取於本站伺服器，定期同步更新，僅供資訊展示用途。</p>
        <p class="about-p">版權歸各原始資料庫及內容創作者所有。</p>
      </div>
      <div class="api-list">
        <div class="api-item"><div class="api-dot" style="background:#02a9ff;"></div><div class="api-name">AniList</div><div class="api-desc">主要資料、Banner、評分、倒數、PV</div></div>
        <div class="api-item"><div class="api-dot" style="background:#f39c12;"></div><div class="api-name">Bangumi</div><div class="api-desc">中文名稱、劇情簡介、集數列表、關聯作品</div></div>
        <div class="api-item"><div class="api-dot" style="background:#2e51a2;"></div><div class="api-name">Jikan (MAL)</div><div class="api-desc">角色聲優、MAL 評分、串流平台</div></div>
        <div class="api-item"><div class="api-dot" style="background:#22c55e;"></div><div class="api-name">AnimeThemes.moe</div><div class="api-desc">OP/ED 主題曲試聽影片</div></div>
      </div>
    </div>
  </section>

  <!-- 結語 -->
  <section class="about-section">
    <div class="closing-box">
      <h3>謝謝你找到這個還很小的地方 🙏</h3>
      <p>每一個現在很大的社群，都曾經是這樣的小站。我們不急，只希望每天都能比昨天做得更好一點。</p>
      <p>讓更多人因為微笑動漫而愛上動漫，或者重新愛上它。</p>
      <p style="font-weight:700;color:var(--text-primary);margin-top:20px;">我們在這裡，慢慢長大。</p>
    </div>
  </section>

  <!-- 法律 -->
  <section class="about-section">
    <h2 class="about-h2">法律資訊</h2>
    <p class="about-p">本站為動漫資訊分享平台，所有動畫資料、圖片、影片均來自第三方公開 API，版權歸各自持有人所有。本站不提供任何版權影片的非法串流或下載服務。若有任何版權疑慮，請透過聯絡頁面與我們聯繫，我們將於 72 小時內處理。</p>
    <div class="legal-links">
      <a href="<?php echo esc_url( home_url('/privacy/') ); ?>" class="legal-link"><i class="fa-solid fa-shield-halved"></i> 隱私權政策</a>
      <a href="<?php echo esc_url( home_url('/terms/') ); ?>" class="legal-link"><i class="fa-solid fa-file-contract"></i> 服務條款</a>
    </div>
  </section>

</main>

<?php get_footer(); ?>
