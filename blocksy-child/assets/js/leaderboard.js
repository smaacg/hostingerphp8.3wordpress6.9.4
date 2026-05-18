/**
 * Leaderboard /ranking-users/ Front-end
 * @version 2.0.0 (2026-05-17)
 *
 * v2.0.0 變更：
 *   - 移除 exp_monthly / followers / badges 三個 type（page 已不再產生對應 tab）
 *   - 新增 rank_season / rank_last_season type 的 label / 單位 / 渲染
 *   - 修正欄位對齊 bug：後端回 avatar_url / display_name / score / extra.tier_*
 *     舊版只認 avatar / display / score_fmt → 改成同時 fallback
 *   - 新增 empty_reason 處理（上季 archive 尚未結算時顯示說明）
 *   - 移除隱私 toggle 邏輯（UI 已從 page 中拿掉）
 *   - 牌位 tab 顯示 tier badge（圖示 + 段位名稱）取代 Lv.
 */
(function () {
  'use strict';

  if (typeof window.smacgRanku === 'undefined') {
    console.warn('[ranku] smacgRanku localize missing');
    return;
  }

  const cfg = window.smacgRanku;
  const tabsEl    = document.getElementById('ranku-tabs');
  const listEl    = document.getElementById('ranku-list');
  const pageEl    = document.getElementById('ranku-pagination');
  const prevBtn   = document.getElementById('ranku-prev');
  const nextBtn   = document.getElementById('ranku-next');
  const pageInfo  = document.getElementById('ranku-page-info');
  const countInfo = document.getElementById('ranku-count-info');
  const updatedEl = document.getElementById('ranku-updated-time');
  const mePosEl   = document.getElementById('ranku-me-pos');
  const heroMeEl  = document.getElementById('ranku-hero-me');
  const toastBox  = document.getElementById('ranku-toast-container');

  if (!tabsEl || !listEl) return;

  /* ---------- state ---------- */
  const state = {
    type: cfg.defaultTab || 'exp_total',
    page: 1,
    perPage: 20,
    totalPage: 1,
    loading: false,
  };

  /* ---------- labels ---------- */
  const typeLabel = {
    exp_total:        '等級排行',
    rank_season:      '本季牌位排行',
    rank_last_season: '上季牌位排行',
  };
  const scoreUnit = {
    exp_total:        'EXP',
    rank_season:      '分',
    rank_last_season: '分',
  };

  const isRankTab = (t) => t === 'rank_season' || t === 'rank_last_season';

  /* ---------- helpers ---------- */
  function toast(msg, kind) {
    if (!toastBox) return;
    const t = document.createElement('div');
    t.className = 'ranku-toast ranku-toast--' + (kind || 'ok');
    t.textContent = msg;
    toastBox.appendChild(t);
    requestAnimationFrame(() => t.classList.add('ranku-toast--show'));
    setTimeout(() => {
      t.classList.remove('ranku-toast--show');
      setTimeout(() => t.remove(), 300);
    }, 2400);
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function medal(pos) {
    if (pos === 1) return '🥇';
    if (pos === 2) return '🥈';
    if (pos === 3) return '🥉';
    return '#' + pos;
  }

  function fmtNum(n) {
    const v = Number(n || 0);
    return v.toLocaleString();
  }

  /* ---------- 欄位 fallback：兼容兩種後端格式 ----------
   * Ranking_System::get() 回傳：{rank, score, display_name, avatar, profile_url, level, job_title}
   * Leaderboard_Ajax::get_rank_season() 回傳：{rank_pos, score, display_name, avatar_url, profile_url, extra:{tier_*}}
   */
  function normalizeRow(r) {
    return {
      rank:        r.rank ?? r.rank_pos ?? 0,
      user_id:     r.user_id,
      display:     r.display_name ?? r.display ?? '',
      avatar:      r.avatar_url ?? r.avatar ?? '',
      profile_url: r.profile_url ?? '#',
      score:       r.score ?? 0,
      score_fmt:   r.score_fmt ?? fmtNum(r.score ?? 0),
      level:       r.level ?? null,
      job_title:   r.job_title ?? r.title ?? '',
      tier:        r.extra && r.extra.tier_key ? {
        key:   r.extra.tier_key,
        label: r.extra.tier_label,
        icon:  r.extra.tier_icon,
        color: r.extra.tier_color,
      } : null,
    };
  }

  /* ---------- render ---------- */
  function renderRow(raw) {
    const r    = normalizeRow(raw);
    const isMe = cfg.currentUid && Number(cfg.currentUid) === Number(r.user_id);
    const cls  = [
      'ranku-row',
      'ranku-row--' + r.rank,
      isMe ? 'ranku-row--me' : '',
    ].filter(Boolean).join(' ');

    const posHtml = r.rank <= 3
      ? `<span class="ranku-pos-medal">${medal(r.rank)}</span>`
      : escapeHtml(String(r.rank));

    /* 牌位 tab：顯示 tier badge；等級 tab：顯示 Lv.X + 職業 */
    let metaHtml = '';
    if (r.tier) {
      const color = r.tier.color || '#888';
      metaHtml = `
        <span class="ranku-tier-badge"
              style="--tier-color:${color};color:${color};border-color:${color}55;background:${color}1a;">
          <span class="ranku-tier-icon">${escapeHtml(r.tier.icon || '🎖️')}</span>
          <span class="ranku-tier-label">${escapeHtml(r.tier.label || '')}</span>
        </span>`;
    } else {
      metaHtml = `
        ${r.level ? `<span class="ranku-lv">Lv.${escapeHtml(String(r.level))}</span>` : ''}
        ${r.job_title ? `<span class="ranku-tier">${escapeHtml(r.job_title)}</span>` : ''}
      `;
    }

    return `
      <div class="${cls}" data-uid="${r.user_id}">
        <div class="ranku-pos">${posHtml}</div>
        <img class="ranku-avatar" src="${escapeHtml(r.avatar)}" alt="${escapeHtml(r.display)}" loading="lazy">
        <div class="ranku-userinfo">
          <h4 class="ranku-name">
            <a href="${escapeHtml(r.profile_url)}">${escapeHtml(r.display)}</a>
          </h4>
          <div class="ranku-meta">${metaHtml}</div>
        </div>
        <div class="ranku-score-wrap">
          <div class="ranku-score">${escapeHtml(r.score_fmt)}</div>
          <span class="ranku-score-unit">${escapeHtml(scoreUnit[state.type] || '')}</span>
        </div>
      </div>
    `;
  }

  function renderEmpty(data) {
    /* 上季 archive 尚未結算的專屬訊息 */
    if (data && data.empty_reason === 'no_settled_season') {
      const msg = data.empty_message || '本賽季尚未結束，等到結算後就能看到完整的上季排行～';
      listEl.innerHTML = `
        <div class="ranku-empty">
          <i class="fa-solid fa-scroll" style="font-size:32px;color:var(--accent-violet,#a78bfa);"></i>
          <h3 class="ranku-empty-title">上季排行尚未產生</h3>
          <p class="ranku-empty-desc">${escapeHtml(msg)}</p>
          <button type="button" class="btn btn-secondary" id="ranku-jump-current"
                  style="margin-top:14px;font-size:13px;">
            <i class="fa-solid fa-trophy"></i> 查看本季牌位排行
          </button>
        </div>`;

      const jumpBtn = document.getElementById('ranku-jump-current');
      if (jumpBtn) {
        jumpBtn.addEventListener('click', () => {
          const currentTab = tabsEl.querySelector('.ranku-tab[data-type="rank_season"]');
          if (currentTab) currentTab.click();
        });
      }
      return;
    }

    /* 一般空狀態 */
    listEl.innerHTML = `
      <div class="ranku-empty">
        <i class="fa-solid fa-hourglass-half"></i>
        <h3 class="ranku-empty-title">尚無排行資料</h3>
        <p class="ranku-empty-desc">系統將每小時自動更新，請稍後再來看看～</p>
      </div>`;
  }

  function renderList(data) {
    /* 後端兩種格式：rows (Ranking_System) 或 items (Leaderboard_Ajax 賽季) */
    const rows = data.rows ?? data.items ?? [];

    if (!rows.length) {
      renderEmpty(data);
      pageEl.hidden = true;
      updateCountInfo(data);
      return;
    }

    listEl.innerHTML = rows.map(renderRow).join('');

    /* pagination */
    const total    = Number(data.total ?? rows.length);
    const perPage  = Number(data.per_page ?? state.perPage);
    const totalPg  = data.total_page ?? Math.max(1, Math.ceil(total / perPage));
    state.totalPage = Math.max(1, totalPg);

    if (state.totalPage <= 1) {
      pageEl.hidden = true;
    } else {
      pageEl.hidden = false;
      pageInfo.textContent = `${state.page} / ${state.totalPage}`;
      prevBtn.disabled = state.page <= 1;
      nextBtn.disabled = state.page >= state.totalPage;
    }

    updateCountInfo(data);

    if (updatedEl && data.updated_at) updatedEl.textContent = data.updated_at;
    if (mePosEl) {
      mePosEl.textContent = data.my_pos ? '#' + data.my_pos : '未上榜';
      if (heroMeEl) heroMeEl.dataset.tab = state.type;
    }
  }

  function updateCountInfo(data) {
    if (!countInfo) return;
    const label   = typeLabel[state.type] || '排行';
    const total   = Number(data?.total ?? 0);
    const extra   = data?.season_label ? ` · ${data.season_label}` : '';
    countInfo.textContent = total > 0
      ? `${label} · Top ${total}${extra}`
      : `${label}${extra}`;
  }

  function renderLoading() {
    listEl.innerHTML = Array(5).fill(0).map(() =>
      `<div class="skeleton" style="height:78px;border-radius:14px;margin-bottom:10px;"></div>`
    ).join('');
  }

  /* ---------- fetch ---------- */
  function load() {
    if (state.loading) return;
    state.loading = true;
    renderLoading();

    const params = new URLSearchParams({
      action:   'smacg_get_ranking',
      type:     state.type,
      page:     state.page,
      per_page: state.perPage,
    });

    fetch(cfg.ajax + '?' + params.toString(), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(res => {
        state.loading = false;
        if (!res.success) {
          toast(res.data?.message || '載入失敗', 'err');
          listEl.innerHTML = `<div class="ranku-empty"><i class="fa-solid fa-circle-exclamation"></i><h3 class="ranku-empty-title">載入失敗</h3><p class="ranku-empty-desc">請稍後再試</p></div>`;
          return;
        }
        renderList(res.data);
        /* 更新網址 query（不重新整理） */
        try {
          const url = new URL(window.location.href);
          url.searchParams.set('tab', state.type);
          window.history.replaceState({}, '', url.toString());
        } catch (e) {}
      })
      .catch(() => {
        state.loading = false;
        toast('網路錯誤', 'err');
      });
  }

  /* ---------- events ---------- */
  tabsEl.addEventListener('click', e => {
    const btn = e.target.closest('.ranku-tab');
    if (!btn) return;
    const type = btn.dataset.type;
    if (!type || type === state.type) return;

    tabsEl.querySelectorAll('.ranku-tab').forEach(b => {
      b.classList.toggle('active', b === btn);
      b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
    });

    state.type = type;
    state.page = 1;
    load();
  });

  prevBtn?.addEventListener('click', () => {
    if (state.page > 1) { state.page--; load(); window.scrollTo({ top: listEl.offsetTop - 80, behavior: 'smooth' }); }
  });
  nextBtn?.addEventListener('click', () => {
    if (state.page < state.totalPage) { state.page++; load(); window.scrollTo({ top: listEl.offsetTop - 80, behavior: 'smooth' }); }
  });

  /* ---------- init ---------- */
  load();
})();
