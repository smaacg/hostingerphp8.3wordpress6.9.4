/* ============================================================
   微笑動漫 — ranking.js
   ★ 修改：加入站內 weixiaoacg+ 真實排行
     - fetchSiteRanking() 呼叫 /wp-json/weixiaoacg/v1/ranking/site
     - site 平台下隱藏 period-btns，顯示 site-sub-tabs
     - site 子分類：rating 真實資料，views/favorites 顯示即將上線
     - rankRenderList() 站內排行連結指向站內頁面（非外部）
   ============================================================ */
'use strict';

/* ============================================================
   平台設定
   ★ 修改：補上 site 的完整設定（原本 PLATFORMS 裡沒有 site）
   ============================================================ */
const PLATFORMS = {
  // ★ 新增 site 平台設定
  site: {
    label: 'weixiaoacg+', icon: '⭐', color: '#6c63ff',
    desc: '來自本站會員的真實評分，採用貝葉斯加權公式，確保評分公平可信',
    tags: ['本站真實數據', '貝葉斯加權', '四維度評分'],
    link: null   // 無外部連結
  },
  anilist: {
    label: 'AniList', icon: '🌐', color: '#02a9ff',
    desc: '來自 AniList 的全球評分排行，以現代介面和精細評分系統著稱',
    tags: ['歐美用戶為主', '評分細緻', '社群活躍'],
    link: 'https://anilist.co'
  },
  mal: {
    label: 'MAL / Jikan', icon: '📊', color: '#2e51a2',
    desc: '來自 MyAnimeList，全球最大動漫資料庫，歷史資料最完整',
    tags: ['全球最大', '歷史最完整', '用戶最多'],
    link: 'https://myanimelist.net'
  },
  bangumi: {
    label: 'Bangumi', icon: '🎯', color: '#f09199',
    desc: '來自 Bangumi 番組計畫，華語圈最完整的動漫資料庫',
    tags: ['華語圈最完整', '聲優資料詳盡', '中文社群'],
    link: 'https://bgm.tv'
  }
};

/* ============================================================
   STATE
   ★ 修改：加入 rankType（站內子分類）
   ============================================================ */
let rankState = {
  platform: 'anilist',
  period:   'weekly',
  rankType: 'rating'   // ★ 新增：站內子分類，預設評分最高
};

/* 快取，避免重複 API 請求 */
const _cache = {};

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  rankInitPlatformTabs();
  rankInitPeriodBtns();
  rankInitSiteSubTabs(); // ★ 新增
  rankInitParticles();
  rankInitSwipe();
  rankRenderAll();
  rankInitSidebar();
});

/* ============================================================
   平台 Tab
   ★ 修改：
   - 移除強制隱藏 site tab 的邏輯
   - 切換平台時控制 period-btns / site-sub-tabs / site-rank-note 的顯示
   ============================================================ */
function rankInitPlatformTabs() {
  document.querySelectorAll('.rank-platform-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      if (tab.disabled) return;

      rankState.platform = tab.dataset.platform;

      document.querySelectorAll('.rank-platform-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      // ★ 站內平台：隱藏 period-btns，顯示 site-sub-tabs 和底部提示
      const isSite     = rankState.platform === 'site';
      const periodRow  = document.getElementById('period-btns');
      const siteSubRow = document.getElementById('site-sub-tabs');
      const siteNote   = document.getElementById('site-rank-note');

      if (periodRow)  periodRow.style.display  = isSite ? 'none' : '';
      if (siteSubRow) siteSubRow.style.display  = isSite ? 'flex' : 'none';
      if (siteNote)   siteNote.style.display    = isSite ? 'block' : 'none';

      rankRenderAll();
    });
  });

  /* 預設選中 anilist */
  const defaultTab = document.querySelector('.rank-platform-tab[data-platform="anilist"]');
  if (defaultTab) {
    document.querySelectorAll('.rank-platform-tab').forEach(t => t.classList.remove('active'));
    defaultTab.classList.add('active');
  }
}

/* ============================================================
   站內子分類 Tab
   ★ 新增：rating 呼叫真實 API；views / favorites 顯示即將上線
   ============================================================ */
function rankInitSiteSubTabs() {
  document.querySelectorAll('.site-sub-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      rankState.rankType = btn.dataset.rankType;

      document.querySelectorAll('.site-sub-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      // views / favorites 尚未實作，顯示提示
      if (rankState.rankType !== 'rating') {
        const listEl = document.getElementById('rank-list');
        if (listEl) {
          listEl.innerHTML = `
            <div style="text-align:center;padding:60px 0;color:var(--text-muted);">
              <i class="fa-solid fa-hammer" style="font-size:28px;display:block;margin-bottom:12px;opacity:.6;"></i>
              <div style="font-size:15px;font-weight:600;margin-bottom:6px;">即將上線</div>
              <div style="font-size:12px;opacity:.7;">此分類數據正在開發中，敬請期待</div>
            </div>`;
        }
        // 更新計數列文字
        const countEl = document.getElementById('rank-count-info');
        if (countEl) countEl.textContent = 'weixiaoacg+ · 即將上線';
        return;
      }

      // rating 子分類：清快取後重新拉取
      delete _cache['site_rating'];
      rankFetchAndRender();
    });
  });
}

/* ============================================================
   週期 Tab
   ============================================================ */
function rankInitPeriodBtns() {
  document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      rankState.period = btn.dataset.period;
      document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      rankFetchAndRender();
    });
  });
}

/* ============================================================
   RENDER ALL
   ============================================================ */
function rankRenderAll() {
  rankRenderPlatformCard(rankState.platform);
  rankFetchAndRender();
}

/* ============================================================
   平台介紹卡
   ============================================================ */
function rankRenderPlatformCard(platform) {
  const p = PLATFORMS[platform];
  if (!p) return;
  const card = document.getElementById('platform-info-card');
  if (!card) return;
  card.innerHTML = `
    <div style="display:flex;align-items:flex-start;gap:14px;">
      <div style="width:36px;height:36px;border-radius:10px;background:${p.color}22;color:${p.color};
                  display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">
        ${p.icon}
      </div>
      <div style="min-width:0;">
        <div style="font-weight:700;color:${p.color};margin-bottom:4px;">${p.label}</div>
        <p style="font-size:12px;color:var(--text-muted,rgba(208,215,224,.55));margin:0 0 8px;line-height:1.6;">${p.desc}</p>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          ${p.tags.map(t => `<span style="font-size:10px;padding:2px 8px;border-radius:50px;
            background:${p.color}15;border:1px solid ${p.color}30;color:${p.color};">${t}</span>`).join('')}
          ${p.link ? `<a href="${p.link}" target="_blank" rel="noopener"
            style="font-size:10px;padding:2px 8px;border-radius:50px;color:var(--text-muted);
            border:1px solid var(--glass-border,rgba(255,255,255,.08));text-decoration:none;">
            前往 ${p.label} ↗</a>` : ''}
        </div>
      </div>
    </div>`;
  card.style.borderColor = `${p.color}44`;
}

/* ============================================================
   Skeleton Loading
   ============================================================ */
function rankShowSkeleton() {
  const listEl = document.getElementById('rank-list');
  if (!listEl) return;
  listEl.innerHTML = Array(10).fill(`
    <div class="rank-loading">
      <div class="skeleton" style="height:90px;border-radius:16px;margin-bottom:8px;"></div>
    </div>`).join('');
}

/* ============================================================
   API FETCH + RENDER
   ★ 修改：加入 site 平台的分支，呼叫 fetchSiteRanking()
   ============================================================ */
async function rankFetchAndRender() {
  const { platform, period, rankType } = rankState;

  // ★ 站內子分類非 rating 時已在 rankInitSiteSubTabs 處理，不重複執行
  if (platform === 'site' && rankType !== 'rating') return;

  const cacheKey = platform === 'site'
    ? `site_${rankType}`
    : `${platform}_${period}`;

  rankShowSkeleton();

  try {
    let items = _cache[cacheKey];
    if (!items) {
      if      (platform === 'site')    items = await fetchSiteRanking();  // ★ 新增
      else if (platform === 'anilist') items = await fetchAniList(period);
      else if (platform === 'mal')     items = await fetchMAL(period);
      else if (platform === 'bangumi') items = await fetchBangumi(period);
      else items = [];
      _cache[cacheKey] = items;
    }
    rankRenderList(items);
  } catch (e) {
    console.error('[ranking]', e);
    const listEl = document.getElementById('rank-list');
    if (listEl) listEl.innerHTML = `
      <div style="text-align:center;padding:48px 0;color:var(--text-muted);">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:28px;display:block;margin-bottom:12px;"></i>
        資料載入失敗，請稍後再試
      </div>`;
  }
}

/* ============================================================
   ★ 新增：weixiaoacg+ 站內評分排行
   呼叫 /wp-json/weixiaoacg/v1/ranking/site
   回傳格式對齊 rankRenderList() 所需的 items 結構
   站內排行的 url 指向站內動漫頁面（非外部），不開新分頁
   ============================================================ */
async function fetchSiteRanking(limit = 20) {
  const res = await fetch(
    `/wp-json/weixiaoacg/v1/ranking/site?limit=${limit}`,
    { headers: { 'Accept': 'application/json' } }
  );

  if (!res.ok) throw new Error(`Site Ranking API HTTP ${res.status}`);

  const data = await res.json();

  if (!Array.isArray(data) || data.length === 0) return [];

  return data.map((item, i) => ({
    rank:     i + 1,
    titleZh:  item.title  || '未命名',
    titleJp:  '',                          // 站內 API 不回傳日文名，留空
    cover:    item.cover  || '',
    score:    item.score  != null ? Number(item.score).toFixed(2) : null,
    scoredBy: item.vote_count || 0,
    genres:   [],                          // 站內 API 不回傳 genres，留空
    year:     '',
    // ★ 站內排行的 4 個子分項分數，供渲染時顯示
    avgStory:     item.avg_story     != null ? Number(item.avg_story).toFixed(1)     : null,
    avgMusic:     item.avg_music     != null ? Number(item.avg_music).toFixed(1)     : null,
    avgAnimation: item.avg_animation != null ? Number(item.avg_animation).toFixed(1) : null,
    avgVoice:     item.avg_voice     != null ? Number(item.avg_voice).toFixed(1)     : null,
    isSite:   true,                        // ★ 標記為站內資料，渲染時區分處理
    url:      item.url || '#',             // 站內頁面 URL，不開新分頁
    animeId:  item.anime_id || null
  }));
}

/* ============================================================
   AniList API
   ============================================================ */
async function fetchAniList(period) {
  const sortBy = (period === 'daily' || period === 'weekly' || period === 'monthly')
    ? 'TRENDING_DESC'
    : 'SCORE_DESC';

  const query = `
    query ($sort: [MediaSort], $perPage: Int) {
      Page(perPage: $perPage) {
        media(type: ANIME, sort: $sort, status_in: [RELEASING, FINISHED]) {
          id
          title { romaji native userPreferred }
          coverImage { large }
          averageScore
          popularity
          trending
          genres
          seasonYear
          season
          status
          rankings { rank type allTime season }
        }
      }
    }`;

  const res = await fetch('https://graphql.anilist.co', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ query, variables: { sort: [sortBy], perPage: 20 } })
  });

  if (!res.ok) throw new Error(`AniList HTTP ${res.status}`);
  const json = await res.json();
  const mediaList = json?.data?.Page?.media || [];

  const seasonMap = { WINTER:'冬季', SPRING:'春季', SUMMER:'夏季', FALL:'秋季' };

  return mediaList.map((m, i) => ({
    rank:     i + 1,
    titleZh:  m.title.userPreferred || m.title.romaji,
    titleJp:  m.title.native || '',
    cover:    m.coverImage?.large || '',
    score:    m.averageScore ? (m.averageScore / 10).toFixed(1) : null,
    scoredBy: m.popularity || 0,
    genres:   (m.genres || []).slice(0, 3),
    year:     m.seasonYear
      ? (m.season ? `${m.seasonYear} ${seasonMap[m.season] || ''}` : String(m.seasonYear))
      : '',
    anilistId: m.id,
    isSite:    false,
    url:       `https://anilist.co/anime/${m.id}`
  }));
}

/* ============================================================
   MAL / Jikan v4 API
   ============================================================ */
async function fetchMAL(period) {
  let endpoint = 'https://api.jikan.moe/v4/top/anime?limit=20';
  if (period === 'daily' || period === 'weekly') {
    endpoint += '&filter=airing';
  } else if (period === 'monthly') {
    endpoint += '&filter=bypopularity';
  }

  const res = await fetch(endpoint);
  if (!res.ok) throw new Error(`Jikan HTTP ${res.status}`);
  const json = await res.json();
  const list = json?.data || [];

  return list.map((m, i) => ({
    rank:     i + 1,
    titleZh:  m.title || '',
    titleJp:  m.title_japanese || '',
    cover:    m.images?.jpg?.large_image_url || m.images?.jpg?.image_url || '',
    score:    m.score ? Number(m.score).toFixed(1) : null,
    scoredBy: m.scored_by || 0,
    genres:   (m.genres || []).slice(0, 3).map(g => g.name),
    year:     m.year ? String(m.year) : '',
    anilistId: null,
    isSite:    false,
    url:       m.url || `https://myanimelist.net/anime/${m.mal_id}`
  }));
}

/* ============================================================
   Bangumi API
   ============================================================ */
async function fetchBangumi(period) {
  const res = await fetch(
    'https://api.bgm.tv/v0/subjects?type=2&sort=rank&limit=20',
    { headers: { 'Accept': 'application/json', 'User-Agent': 'weixiaoacg/1.0' } }
  );
  if (!res.ok) throw new Error(`Bangumi HTTP ${res.status}`);
  const json = await res.json();
  const list = json?.data || [];

  return list.map((m, i) => ({
    rank:     i + 1,
    titleZh:  m.name_cn || m.name || '',
    titleJp:  m.name || '',
    cover:    m.images?.large || m.images?.common || '',
    score:    m.rating?.score ? Number(m.rating.score).toFixed(1) : null,
    scoredBy: m.rating?.total || 0,
    genres:   (m.tags || []).slice(0, 3).map(t => t.name),
    year:     m.date ? m.date.slice(0, 4) : '',
    anilistId: null,
    isSite:    false,
    url:       `https://bgm.tv/subject/${m.id}`
  }));
}

/* ============================================================
   渲染列表
   ★ 修改：
   - 站內排行（isSite）連結不開新分頁（移除 target="_blank"）
   - 站內排行顯示 4 個子分項分數（story / music / animation / voice）
   - 站內排行的 scoredBy 文字改為「X 人評分」而非 MAL 的「X 人」
   ============================================================ */
function rankRenderList(items) {
  const listEl = document.getElementById('rank-list');
  if (!listEl) return;

  const { platform, period } = rankState;
  const p = PLATFORMS[platform];
  const color = p?.color || '#63a8ff';

  if (!items || !items.length) {
    listEl.innerHTML = `
      <div style="text-align:center;padding:48px 0;color:var(--text-muted);">
        <i class="fa-solid fa-box-open" style="font-size:28px;display:block;margin-bottom:12px;"></i>
        ${platform === 'site'
          ? '目前尚無評分資料，成為第一個評分的人吧！'
          : '此條件暫無資料'}
      </div>`;
    return;
  }

  listEl.innerHTML = items.map(item => {
    const numClass = item.rank === 1 ? 'rank-card__num--top1'
                   : item.rank === 2 ? 'rank-card__num--top2'
                   : item.rank === 3 ? 'rank-card__num--top3' : '';

    const crown = item.rank === 1 ? '<span class="rank-card__crown">👑</span>' : '';

    const scoreHtml = item.score
      ? `<div class="rank-card__score" style="color:${color};">${item.score}</div>
         <div class="rank-card__score-label">/ 10</div>`
      : '';

    const votesHtml = item.scoredBy
      ? `<div class="rank-card__votes">${Number(item.scoredBy).toLocaleString()} 人評分</div>`
      : '';

    const genres = (item.genres || []).map(g =>
      `<span class="rank-card__tag">${g}</span>`).join('');

    const yearTag = item.year
      ? `<span class="rank-card__tag rank-card__tag--year">${item.year}</span>`
      : '';

    // ★ 站內排行：顯示 4 個子分項分數
    const siteSubScores = item.isSite && (item.avgStory || item.avgMusic || item.avgAnimation || item.avgVoice)
      ? `<div class="rank-card__site-sub" style="font-size:10px;color:var(--text-muted);margin-top:4px;line-height:1.8;">
           ${item.avgStory     ? `劇情 <strong style="color:${color};">${item.avgStory}</strong>` : ''}
           ${item.avgMusic     ? `&nbsp;· 音樂 <strong style="color:${color};">${item.avgMusic}</strong>` : ''}
           ${item.avgAnimation ? `&nbsp;· 作畫 <strong style="color:${color};">${item.avgAnimation}</strong>` : ''}
           ${item.avgVoice     ? `&nbsp;· 聲優 <strong style="color:${color};">${item.avgVoice}</strong>` : ''}
         </div>`
      : '';

    const href   = item.url || '#';
    // ★ 站內排行連到站內頁面，不開新分頁；外部平台開新分頁
    const target = item.isSite ? '' : 'target="_blank" rel="noopener noreferrer"';

    return `
    <a class="rank-card" href="${href}" ${target}>
      <div class="rank-card__rank">
        ${crown}
        <div class="rank-card__num ${numClass}">${item.rank}</div>
      </div>
      <div class="rank-card__cover">
        ${item.cover
          ? `<img src="${item.cover}" alt="${item.titleZh}" loading="lazy"
               onerror="this.parentElement.innerHTML='<div class=rank-card__cover-fb>🎬</div>';">`
          : `<div class="rank-card__cover-fb">🎬</div>`}
      </div>
      <div class="rank-card__body">
        <div class="rank-card__title">${item.titleZh}</div>
        ${item.titleJp && item.titleJp !== item.titleZh
          ? `<div class="rank-card__native">${item.titleJp}</div>` : ''}
        <div class="rank-card__tags">${genres}${yearTag}</div>
        ${siteSubScores}
      </div>
      <div class="rank-card__meta">
        ${scoreHtml}
        ${votesHtml}
        <div class="rank-card__action" style="color:${color};border-color:${color}44;">
          ${item.isSite ? '查看' : '詳情'} →
        </div>
      </div>
    </a>`;
  }).join('');

  /* 更新計數列 */
  const countEl = document.getElementById('rank-count-info');
  if (countEl) {
    const periodLabels = { daily:'今日', weekly:'本週', monthly:'本月', yearly:'年度' };
    if (platform === 'site') {
      countEl.textContent = `weixiaoacg+ 評分排行 · Top ${items.length}`;
    } else {
      countEl.textContent = `${periodLabels[period] || '本週'} ${p?.label || ''} 排行 · Top ${items.length}`;
    }
  }
}

/* ============================================================
   SIDEBAR
   ============================================================ */
function rankInitSidebar() {
  const newEl = document.getElementById('sidebar-new-list');
  if (newEl) {
    newEl.innerHTML = `
      <div style="text-align:center;padding:20px 0;color:var(--text-muted,rgba(208,215,224,.55));font-size:12px;line-height:1.8;">
        <i class="fa-solid fa-clock" style="font-size:20px;display:block;margin-bottom:8px;opacity:.5;"></i>
        評分系統開發中<br>敬請期待
      </div>`;
  }

  const moverEl = document.getElementById('sidebar-movers-list');
  if (moverEl) {
    moverEl.innerHTML = `
      <div style="text-align:center;padding:20px 0;color:var(--text-muted,rgba(208,215,224,.55));font-size:12px;line-height:1.8;">
        <i class="fa-solid fa-chart-line" style="font-size:20px;display:block;margin-bottom:8px;opacity:.5;"></i>
        站內排名數據<br>即將上線
      </div>`;
  }
}

/* ============================================================
   PARTICLES
   ============================================================ */
function rankInitParticles() {
  const canvas = document.getElementById('rank-particles');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W = canvas.width = canvas.offsetWidth;
  let H = canvas.height = canvas.offsetHeight;

  const dots = Array.from({ length: 60 }, () => ({
    x: Math.random() * W, y: Math.random() * H,
    r: Math.random() * 1.5 + 0.4,
    vx: (Math.random() - 0.5) * 0.3,
    vy: (Math.random() - 0.5) * 0.3,
    a: Math.random()
  }));

  (function draw() {
    ctx.clearRect(0, 0, W, H);
    dots.forEach(d => {
      ctx.beginPath();
      ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(180,160,255,${d.a * 0.6})`;
      ctx.fill();
      d.x += d.vx; d.y += d.vy;
      if (d.x < 0) d.x = W; if (d.x > W) d.x = 0;
      if (d.y < 0) d.y = H; if (d.y > H) d.y = 0;
    });
    requestAnimationFrame(draw);
  })();

  window.addEventListener('resize', () => {
    W = canvas.width = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
  });
}

/* ============================================================
   SWIPE（手機左右滑動切換平台）
   ============================================================ */
function rankInitSwipe() {
  const el = document.querySelector('.rank-main');
  if (!el) return;
  let startX = 0, startY = 0;

  el.addEventListener('touchstart', e => {
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
  }, { passive: true });

  el.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - startX;
    const dy = e.changedTouches[0].clientY - startY;
    if (Math.abs(dx) < 60 || Math.abs(dy) > Math.abs(dx) * 0.8) return;

    // ★ 修改：swipe 候選 tab 包含 site（不再排除）
    const tabs = Array.from(document.querySelectorAll('.rank-platform-tab'));
    const cur  = tabs.findIndex(t => t.classList.contains('active'));
    if (cur === -1) return;
    const next = dx < 0 ? Math.min(cur + 1, tabs.length - 1) : Math.max(cur - 1, 0);
    if (next !== cur) {
      tabs[next].click();
      tabs[next].scrollIntoView({ inline: 'center', behavior: 'smooth' });
    }
  }, { passive: true });
}
