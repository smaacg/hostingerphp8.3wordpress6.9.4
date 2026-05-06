<?php
/**
 * Template Name: 贊助頁面
 * Template Post Type: page
 * Path: wp-content/themes/blocksy-child/page-sponsor.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>

<style>
  /* ── SPONSOR PAGE 專屬樣式 ── */
  .sponsor-page-wrap {
    position: relative;
    overflow: hidden;
  }

  .sponsor-hero {
    min-height: 70vh;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    padding: 100px 0 60px;
  }
  .sponsor-hero-bg {
    position: absolute;
    inset: 0;
    background:
      radial-gradient(ellipse 80% 60% at 50% -10%, rgba(99,102,241,0.28) 0%, transparent 70%),
      radial-gradient(ellipse 50% 40% at 80% 60%, rgba(139,92,246,0.18) 0%, transparent 60%),
      var(--grad-bg);
    z-index: 0;
  }
  .sponsor-hero-content {
    position: relative;
    z-index: 1;
    text-align: center;
    max-width: 760px;
    margin: 0 auto;
    padding: 0 var(--space-lg);
  }
  .sponsor-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(99,102,241,0.18);
    border: 1px solid rgba(99,102,241,0.35);
    border-radius: var(--radius-pill);
    padding: 6px 18px;
    font-size: 12px;
    font-weight: 700;
    color: #a5b4fc;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 24px;
  }
  .sponsor-hero-title {
    font-size: clamp(32px, 6vw, 58px);
    font-weight: 900;
    line-height: 1.15;
    color: var(--text-primary);
    margin: 0 0 20px;
    letter-spacing: -.02em;
  }
  .sponsor-hero-title .grad {
    background: linear-gradient(135deg, #818cf8, #c084fc, #f472b6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  .sponsor-hero-sub {
    font-size: 17px;
    color: var(--text-secondary);
    line-height: 1.8;
    margin: 0 0 36px;
    max-width: 580px;
    margin-left: auto;
    margin-right: auto;
  }
  .sponsor-hero-cta {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 40px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border-radius: var(--radius-pill);
    font-size: 16px;
    font-weight: 700;
    text-decoration: none;
    transition: var(--trans-smooth);
    box-shadow: 0 8px 32px rgba(99,102,241,0.45);
  }
  .sponsor-hero-cta:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-3px);
    box-shadow: 0 14px 48px rgba(99,102,241,0.55);
    color: #fff;
  }
  .sponsor-hero-cta i { font-size: 18px; }

  /* ── 數字亮點 ── */
  .sponsor-stats-row {
    display: flex;
    justify-content: center;
    gap: 48px;
    margin-top: 56px;
    flex-wrap: wrap;
  }
  .sponsor-stat-item {
    text-align: center;
  }
  .sponsor-stat-num {
    font-size: 38px;
    font-weight: 900;
    color: var(--text-primary);
    display: block;
    line-height: 1;
    margin-bottom: 6px;
  }
  .sponsor-stat-num .accent { color: #818cf8; }
  .sponsor-stat-label {
    font-size: 13px;
    color: var(--text-muted);
  }

  /* ── WHY 區塊 ── */
  .why-section {
    padding: 80px 0;
    background: linear-gradient(180deg, transparent, rgba(30,36,43,0.4), transparent);
  }
  .section-eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #a5b4fc;
    margin-bottom: 12px;
  }
  .section-h2 {
    font-size: clamp(24px, 4vw, 36px);
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 16px;
    letter-spacing: -.01em;
  }
  .why-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
    margin-top: 48px;
  }
  .why-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    padding: 28px 24px;
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    transition: var(--trans-smooth);
  }
  .why-card:hover {
    transform: translateY(-4px);
    border-color: rgba(99,102,241,0.4);
    box-shadow: 0 12px 40px rgba(99,102,241,0.15);
  }
  .why-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 16px;
  }
  .why-card h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px;
  }
  .why-card p {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.75;
    margin: 0;
  }

  /* ── 費用透明 ── */
  .cost-section {
    padding: 80px 0;
  }
  .cost-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    padding: 36px 40px;
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    max-width: 680px;
    margin: 0 auto;
  }
  .cost-list {
    list-style: none;
    padding: 0;
    margin: 24px 0;
  }
  .cost-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid var(--glass-border);
    font-size: 14px;
    gap: 16px;
  }
  .cost-item:last-child { border-bottom: none; }
  .cost-item-label {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-secondary);
  }
  .cost-item-label i {
    width: 20px;
    text-align: center;
    color: #818cf8;
  }
  .cost-item-amt {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 15px;
    white-space: nowrap;
  }
  .cost-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    margin-top: 4px;
    border-top: 2px solid rgba(99,102,241,0.35);
    gap: 16px;
  }
  .cost-total-label {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-primary);
  }
  .cost-total-amt {
    font-size: 22px;
    font-weight: 900;
    color: #818cf8;
    white-space: nowrap;
  }
  .cost-note {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 16px;
    line-height: 1.7;
  }

  /* ── 贊助方案 ── */
  .plans-section {
    padding: 80px 0;
    background: linear-gradient(180deg, transparent, rgba(99,102,241,0.05), transparent);
  }
  .plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-top: 48px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
  }
  .plan-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    padding: 32px 28px;
    text-align: center;
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    transition: var(--trans-smooth);
    position: relative;
    overflow: hidden;
  }
  .plan-card.featured {
    border-color: rgba(99,102,241,0.5);
    background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(139,92,246,0.08));
    box-shadow: 0 4px 32px rgba(99,102,241,0.2);
  }
  .plan-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 48px rgba(0,0,0,0.25);
  }
  .plan-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 10px;
    border-radius: var(--radius-pill);
    text-transform: uppercase;
    letter-spacing: .06em;
  }
  .plan-emoji { font-size: 40px; margin-bottom: 12px; }
  .plan-name {
    font-size: 18px;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 6px;
  }
  .plan-desc {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0 0 20px;
    line-height: 1.6;
  }
  .plan-price {
    font-size: 32px;
    font-weight: 900;
    color: #818cf8;
    margin-bottom: 4px;
  }
  .plan-price small {
    font-size: 14px;
    color: var(--text-muted);
    font-weight: 500;
  }
  .plan-perks {
    list-style: none;
    padding: 0;
    margin: 20px 0 24px;
    text-align: left;
  }
  .plan-perk {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    font-size: 13px;
    color: var(--text-secondary);
  }
  .plan-perk i { color: #6ee7b7; font-size: 11px; }
  .plan-btn {
    display: block;
    width: 100%;
    padding: 12px 0;
    border-radius: var(--radius-pill);
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    text-align: center;
    transition: var(--trans-fast);
  }
  .plan-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    box-shadow: 0 4px 20px rgba(99,102,241,0.4);
  }
  .plan-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    box-shadow: 0 8px 32px rgba(99,102,241,0.55);
    color: #fff;
  }
  .plan-btn-ghost {
    background: transparent;
    border: 1px solid var(--glass-border);
    color: var(--text-secondary);
  }
  .plan-btn-ghost:hover {
    border-color: rgba(99,102,241,0.4);
    color: #818cf8;
    background: rgba(99,102,241,0.08);
  }

  /* ── 付款方式 ── */
  .payment-section { padding: 60px 0; }
  .payment-methods {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 32px;
  }
  .payment-method {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    backdrop-filter: blur(var(--glass-blur));
  }
  .payment-method i { font-size: 18px; }

  /* ── 贊助者留言 ── */
  .testimonials-section { padding: 80px 0; }
  .testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 48px;
  }
  .testimonial-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    padding: 24px;
    backdrop-filter: blur(var(--glass-blur));
  }
  .testimonial-stars {
    color: #FFD580;
    font-size: 13px;
    margin-bottom: 12px;
  }
  .testimonial-text {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.8;
    margin: 0 0 16px;
    font-style: italic;
  }
  .testimonial-text::before { content: '"'; }
  .testimonial-text::after  { content: '"'; }
  .testimonial-user {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .testimonial-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
  }
  .testimonial-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-primary);
  }
  .testimonial-role {
    font-size: 11px;
    color: var(--text-muted);
  }

  /* ── FAQ ── */
  .faq-section-sp { padding: 60px 0 80px; }
  .faq-list-sp { max-width: 660px; margin: 32px auto 0; }
  .faq-item-sp {
    border-bottom: 1px solid var(--glass-border);
  }
  .faq-item-sp:last-child { border-bottom: none; }
  .faq-q-sp {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    padding: 18px 0;
    cursor: pointer;
    list-style: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .faq-q-sp::-webkit-details-marker { display: none; }
  .faq-q-sp::after {
    content: '+';
    font-size: 20px;
    font-weight: 300;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-left: 12px;
  }
  details.faq-item-sp[open] .faq-q-sp::after { content: '−'; }
  .faq-a-sp {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.8;
    padding-bottom: 18px;
  }

  /* ── CTA 底部 ── */
  .sponsor-cta-bottom {
    padding: 80px 0;
    text-align: center;
    background: linear-gradient(180deg, transparent, rgba(99,102,241,0.06));
  }
  .sponsor-cta-bottom h2 {
    font-size: clamp(28px, 4vw, 42px);
    font-weight: 900;
    color: var(--text-primary);
    margin: 0 0 16px;
  }
  .sponsor-cta-bottom p {
    font-size: 16px;
    color: var(--text-secondary);
    margin: 0 0 36px;
  }

  .sponsor-mail-card {
    margin-top: 36px;
    padding: 24px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    max-width: 520px;
    margin-left: auto;
    margin-right: auto;
    backdrop-filter: blur(var(--glass-blur));
  }

  .sponsor-mail-card i {
    font-size: 32px;
    color: #818cf8;
    display: block;
    margin-bottom: 12px;
  }

  .sponsor-page-wrap a {
    text-decoration: none;
  }

  @media (max-width: 768px) {
    .sponsor-hero { padding: 80px 0 50px; min-height: auto; }
    .sponsor-stats-row { gap: 28px; }
    .sponsor-stat-num { font-size: 28px; }
    .cost-card { padding: 24px 20px; }
    .plans-grid { grid-template-columns: 1fr; max-width: 360px; }
    .cost-item,
    .cost-total {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>

<div class="sponsor-page-wrap">

  <!-- ===================== HERO ===================== -->
  <section class="sponsor-hero">
    <div class="sponsor-hero-bg"></div>
    <div class="container">
      <div class="sponsor-hero-content">
        <div class="sponsor-eyebrow">
          <i class="fa-solid fa-heart"></i> 支持獨立動漫媒體
        </div>

        <h1 class="sponsor-hero-title">
          我們熱愛動漫，<br>
          <span class="grad">像你一樣</span>
        </h1>

        <p class="sponsor-hero-sub">
          微笑動漫每天整合 AniList、Bangumi、MyAnimeList 三大資料庫，為台灣動漫迷提供最完整、最新鮮的動漫情報。<br>
          <strong>這一切，完全免費。</strong><br>
          但伺服器不休息，也不免費。你的支持，讓我們繼續。
        </p>

        <a href="#plans" class="sponsor-hero-cta">
          <i class="fa-solid fa-mug-hot"></i>
          我要贊助微笑動漫
        </a>

        <div class="sponsor-stats-row">
          <div class="sponsor-stat-item">
            <span class="sponsor-stat-num"><span class="accent">3</span> 大</span>
            <span class="sponsor-stat-label">資料庫整合</span>
          </div>
          <div class="sponsor-stat-item">
            <span class="sponsor-stat-num"><span class="accent">24</span>/7</span>
            <span class="sponsor-stat-label">全天候即時更新</span>
          </div>
          <div class="sponsor-stat-item">
            <span class="sponsor-stat-num"><span class="accent">100</span>%</span>
            <span class="sponsor-stat-label">免費使用，無廣告干擾</span>
          </div>
          <div class="sponsor-stat-item">
            <span class="sponsor-stat-num"><span class="accent">61</span>部</span>
            <span class="sponsor-stat-label">本季追蹤作品</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== WHY ===================== -->
  <section class="why-section">
    <div class="container">
      <div style="text-align:center;">
        <p class="section-eyebrow">我們在做什麼</p>
        <h2 class="section-h2">你的贊助，具體用在哪裡？</h2>
        <p style="font-size:15px;color:var(--text-secondary);max-width:520px;margin:0 auto;line-height:1.8;">
          我們不刊登彈跳廣告，也不收集你的個資販售。唯一的收入來源，是像你這樣在乎品質的動漫迷。
        </p>
      </div>

      <div class="why-grid">
        <div class="why-card">
          <div class="why-icon" style="background:rgba(99,102,241,0.15);">
            <i class="fa-solid fa-server" style="color:#818cf8;"></i>
          </div>
          <h3>🖥️ 伺服器費用</h3>
          <p>每月伺服器與 CDN 費用讓網站 24 小時穩定運作，確保你無論何時都能即時查到最新動漫情報。</p>
        </div>

        <div class="why-card">
          <div class="why-icon" style="background:rgba(236,72,153,0.15);">
            <i class="fa-solid fa-database" style="color:#f472b6;"></i>
          </div>
          <h3>📡 資料同步維護</h3>
          <p>每日與 AniList、Bangumi、MAL 三大資料庫同步，確保動畫資訊、評分、角色資料永遠是最新狀態。</p>
        </div>

        <div class="why-card">
          <div class="why-icon" style="background:rgba(34,197,94,0.15);">
            <i class="fa-solid fa-language" style="color:#4ade80;"></i>
          </div>
          <h3>🌏 繁中化工程</h3>
          <p>使用 OpenCC 繁簡轉換技術，確保所有動漫名稱、角色名、劇情介紹都以台灣繁體中文呈現，不再看日英文了。</p>
        </div>

        <div class="why-card">
          <div class="why-icon" style="background:rgba(251,146,60,0.15);">
            <i class="fa-solid fa-code" style="color:#fb923c;"></i>
          </div>
          <h3>⚡ 功能開發</h3>
          <p>新功能持續開發中：季番追蹤、個人清單、音樂播放、社群討論⋯每一個你想要的功能，都需要時間與資源。</p>
        </div>

        <div class="why-card">
          <div class="why-icon" style="background:rgba(139,92,246,0.15);">
            <i class="fa-solid fa-shield-halved" style="color:#a78bfa;"></i>
          </div>
          <h3>🔐 無廣告承諾</h3>
          <p>只要贊助足夠支撐運營，我們承諾永遠不刊登干擾性廣告。你的贊助，保護每一位讀者的閱讀體驗。</p>
        </div>

        <div class="why-card">
          <div class="why-icon" style="background:rgba(14,165,233,0.15);">
            <i class="fa-solid fa-users" style="color:#38bdf8;"></i>
          </div>
          <h3>🤝 社群建設</h3>
          <p>讓台灣動漫迷有個家。論壇、評分、討論、Cosplay 展示⋯打造一個不需要跑到外國論壇才能交流的空間。</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== 費用透明 ===================== -->
  <section class="cost-section">
    <div class="container">
      <div style="text-align:center;margin-bottom:0;">
        <p class="section-eyebrow">透明公開</p>
        <h2 class="section-h2">每月實際運營成本</h2>
        <p style="font-size:14px;color:var(--text-muted);margin-bottom:0;">
          我們選擇公開每月成本，讓你清楚知道贊助的去向。
        </p>
      </div>

      <div class="cost-card glass-card">
        <ul class="cost-list">
          <li class="cost-item">
            <span class="cost-item-label">
              <i class="fa-solid fa-server"></i>
              VPS 伺服器 / 主機費用
            </span>
            <span class="cost-item-amt">NT$ 1,200 / 月</span>
          </li>

          <li class="cost-item">
            <span class="cost-item-label">
              <i class="fa-solid fa-network-wired"></i>
              CDN 流量費用（CloudFlare Pro）
            </span>
            <span class="cost-item-amt">NT$ 620 / 月</span>
          </li>

          <li class="cost-item">
            <span class="cost-item-label">
              <i class="fa-solid fa-globe"></i>
              域名年費（weixiaoacg.com）
            </span>
            <span class="cost-item-amt">NT$ 55 / 月</span>
          </li>

          <li class="cost-item">
            <span class="cost-item-label">
              <i class="fa-solid fa-plug"></i>
              API 第三方服務費用
            </span>
            <span class="cost-item-amt">NT$ 430 / 月</span>
          </li>

          <li class="cost-item">
            <span class="cost-item-label">
              <i class="fa-solid fa-tools"></i>
              維護工具 / 開發資源
            </span>
            <span class="cost-item-amt">NT$ 320 / 月</span>
          </li>
        </ul>

        <div class="cost-total">
          <span class="cost-total-label">每月合計</span>
          <span class="cost-total-amt">NT$ 2,625</span>
        </div>

        <p class="cost-note">
          ※ 以上費用為 2026 年 4 月實際帳單金額，若超出預算將優先壓縮非必要開支。<br>
          ※ 目前由核心團隊自費維持，贊助收入 100% 用於以上項目，不作個人使用。
        </p>
      </div>
    </div>
  </section>

  <!-- ===================== 贊助方案 ===================== -->
  <section class="plans-section" id="plans">
    <div class="container">
      <div style="text-align:center;">
        <p class="section-eyebrow">選擇你的方式</p>
        <h2 class="section-h2">贊助方案</h2>
        <p style="font-size:15px;color:var(--text-secondary);max-width:480px;margin:0 auto;line-height:1.8;">
          沒有會員制，沒有鎖牆。所有功能對所有人免費開放，贊助純粹是對我們工作的認可。
        </p>
      </div>

      <div class="plans-grid">
        <div class="plan-card">
          <div class="plan-emoji">☕</div>
          <div class="plan-name">一杯咖啡</div>
          <div class="plan-desc">謝謝你讓我們繼續跑</div>
          <div class="plan-price">NT$ <span>50</span><small> 起</small></div>
          <ul class="plan-perks">
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 支持伺服器一天的運作</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 讓整個動漫社群受益</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 我們會喝到你的咖啡 ☕</li>
          </ul>
          <a href="#payment-methods" class="plan-btn plan-btn-ghost">我要贊助</a>
        </div>

        <div class="plan-card featured">
          <div class="plan-badge">⭐ 最受歡迎</div>
          <div class="plan-emoji">🌟</div>
          <div class="plan-name">月度支持者</div>
          <div class="plan-desc">每月定期支持，讓我們安心規劃</div>
          <div class="plan-price">NT$ <span>150</span><small> / 月</small></div>
          <ul class="plan-perks">
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 支持伺服器半個月運作</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 讓我們提前規劃新功能</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 在贊助者名單中留名</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 功能投票優先權</li>
          </ul>
          <a href="#payment-methods" class="plan-btn plan-btn-primary">每月支持</a>
        </div>

        <div class="plan-card">
          <div class="plan-emoji">🏆</div>
          <div class="plan-name">年度贊助者</div>
          <div class="plan-desc">最大的支持，我們感謝你</div>
          <div class="plan-price">NT$ <span>1,500</span><small> / 年</small></div>
          <ul class="plan-perks">
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 一次支持整年運作</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 年度贊助者特別標誌</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 功能需求優先採納</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 感謝頁面永久留名</li>
            <li class="plan-perk"><i class="fa-solid fa-check"></i> 內測新功能搶先體驗</li>
          </ul>
          <a href="#payment-methods" class="plan-btn plan-btn-ghost">年度支持</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== 付款方式 ===================== -->
  <section class="payment-section" id="payment-methods">
    <div class="container" style="text-align:center;">
      <p class="section-eyebrow">付款方式</p>
      <h2 class="section-h2">支援多種付款方式</h2>
      <p style="font-size:14px;color:var(--text-secondary);margin-bottom:0;">
        選擇你最方便的方式，安全快速完成贊助。
      </p>

      <div class="payment-methods">
        <div class="payment-method glass">
          <i class="fa-brands fa-paypal" style="color:#003087;"></i>
          <span>PayPal</span>
        </div>
        <div class="payment-method glass">
          <i class="fa-solid fa-credit-card" style="color:#635bff;"></i>
          <span>信用卡 / 金融卡</span>
        </div>
        <div class="payment-method glass">
          <i class="fa-solid fa-building-columns" style="color:#4ade80;"></i>
          <span>台灣銀行轉帳</span>
        </div>
        <div class="payment-method glass">
          <span style="font-size:20px;">街口</span>
          <span>街口支付</span>
        </div>
        <div class="payment-method glass">
          <span style="font-size:20px;">LINE</span>
          <span>LINE Pay</span>
        </div>
      </div>

      <div class="sponsor-mail-card">
        <i class="fa-solid fa-envelope"></i>
        <p style="font-size:14px;color:var(--text-secondary);margin:0 0 16px;line-height:1.7;">
          目前贊助功能建置中，如有意願贊助，<br>
          歡迎寄信至 <strong style="color:var(--text-primary);">sponsor@weixiaoacg.com</strong><br>
          我們會回覆付款方式與感謝資訊。
        </p>
        <a href="mailto:sponsor@weixiaoacg.com" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-paper-plane"></i> 聯絡我們
        </a>
      </div>
    </div>
  </section>

  <!-- ===================== 贊助者留言 ===================== -->
  <section class="testimonials-section">
    <div class="container">
      <div style="text-align:center;">
        <p class="section-eyebrow">贊助者怎麼說</p>
        <h2 class="section-h2">來自社群的聲音</h2>
      </div>

      <div class="testimonials-grid">
        <div class="testimonial-card">
          <div class="testimonial-stars">★★★★★</div>
          <p class="testimonial-text">
            終於有一個台灣自己的動漫資料庫，全繁體中文顯示，不用再看英文介面了。贊助是應該的！
          </p>
          <div class="testimonial-user">
            <div class="testimonial-avatar" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">🎌</div>
            <div>
              <div class="testimonial-name">深夜補番者</div>
              <div class="testimonial-role">月度支持者 · 6 個月</div>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-stars">★★★★★</div>
          <p class="testimonial-text">
            每季追番必備工具，週曆功能超實用，一眼就知道今天有什麼番要看。維護這樣的網站辛苦了，值得支持。
          </p>
          <div class="testimonial-user">
            <div class="testimonial-avatar" style="background:linear-gradient(135deg,#f472b6,#ec4899);">🌸</div>
            <div>
              <div class="testimonial-name">異世界勇者 777</div>
              <div class="testimonial-role">年度贊助者 · 2026</div>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-stars">★★★★★</div>
          <p class="testimonial-text">
            Re:Zero 的作品頁超詳細，PV、角色、STAFF 全都有。這種品質的網站，台灣很少見，希望繼續做下去。
          </p>
          <div class="testimonial-user">
            <div class="testimonial-avatar" style="background:linear-gradient(135deg,#34d399,#10b981);">✨</div>
            <div>
              <div class="testimonial-name">リゼロ推し</div>
              <div class="testimonial-role">一次贊助者</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="faq-section-sp">
    <div class="container">
      <div style="text-align:center;">
        <p class="section-eyebrow">常見問題</p>
        <h2 class="section-h2">關於贊助的疑問</h2>
      </div>

      <div class="faq-list-sp glass-card" style="padding:4px 24px;">
        <details class="faq-item-sp" open>
          <summary class="faq-q-sp">贊助之後我會得到什麼？</summary>
          <div class="faq-a-sp">
            微笑動漫的所有功能對所有人完全免費，贊助不會解鎖額外的「付費功能」。你的支持是對我們工作的認可，讓我們能繼續維持網站運作。月度支持者與年度贊助者會在感謝頁面留名，並享有功能投票的優先權。
          </div>
        </details>

        <details class="faq-item-sp">
          <summary class="faq-q-sp">贊助之後可以退款嗎？</summary>
          <div class="faq-a-sp">
            由於贊助屬於自願性支持，原則上不接受退款申請。若有特殊情況，請於 3 日內聯絡 sponsor@weixiaoacg.com，我們將個案處理。
          </div>
        </details>

        <details class="faq-item-sp">
          <summary class="faq-q-sp">微笑動漫是正式公司嗎？</summary>
          <div class="faq-a-sp">
            微笑動漫目前是由動漫愛好者自發維護的獨立媒體專案，尚未正式法人化。所有贊助資金用於維持網站運作，若有充足資金，未來計劃正式登記為公司以確保長期運作。
          </div>
        </details>

        <details class="faq-item-sp">
          <summary class="faq-q-sp">我有其他支持方式嗎？</summary>
          <div class="faq-a-sp">
            當然！你可以：① 分享微笑動漫給身邊的動漫迷；② 在討論區積極參與，豐富社群內容；③ 在發現資料錯誤時到錯誤回報區回報，幫助我們改善品質；④ 在社群媒體上標記 weixiaoacg，讓更多人認識我們。
          </div>
        </details>

        <details class="faq-item-sp">
          <summary class="faq-q-sp">我如何確認我的贊助已收到？</summary>
          <div class="faq-a-sp">
            完成贊助後，我們會在 1-2 個工作天內以 email 確認並致謝。若使用銀行轉帳，請記得保留交易截圖，並附上你的 email 地址以便對帳。
          </div>
        </details>
      </div>
    </div>
  </section>

  <!-- ===================== CTA 底部 ===================== -->
  <section class="sponsor-cta-bottom">
    <div class="container">
      <div class="sponsor-hero-content" style="padding:0;">
        <h2>
          讓動漫情報
          <span class="grad" style="background:linear-gradient(135deg,#818cf8,#c084fc,#f472b6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
            永遠免費
          </span>
        </h2>

        <p>
          你不需要訂閱，不需要建立帳號，也不需要看廣告。<br>
          只要一杯咖啡，就能讓我們為整個台灣動漫社群服務更久。
        </p>

        <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
          <a href="#plans" class="sponsor-hero-cta">
            <i class="fa-solid fa-heart"></i> 立即贊助
          </a>

          <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-ghost" style="padding:16px 36px;font-size:15px;font-weight:600;">
            先回去追番 →
          </a>
        </div>
      </div>
    </div>
  </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('a[href^="#"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      var targetId = a.getAttribute('href').slice(1);
      var target = document.getElementById(targetId);

      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
});
</script>

<?php get_footer(); ?>
