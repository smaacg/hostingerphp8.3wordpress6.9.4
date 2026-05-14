/**
 * Leaderboard /ranking-users/ Front-end
 * @version 1.0.0 (2026-05-14) Batch 2B-2
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
  const togBox    = document.getElementById('ranku-visibility-toggle');
  const togLabel  = document.getElementById('ranku-toggle-label');
  const toastBox  = document.getElementById('ranku-toast-container');

  if (!tabsEl || !listEl) return;

  /* state */
  const state = {
    type: cfg.defaultTab || 'exp_total',
    page: 1,
    perPage: 20,
    totalPage: 1,
    loading: false,
  };

  /* labels */
  const typeLabel = {
    exp_total:   '累計 EXP',
    exp_monthly: '本月 EXP',
    followers:   '粉絲數',
    badges:      '徽章數',
  };
  const scoreUnit = {
    exp_total:   'EXP',
    exp_monthly: 'EXP',
    followers:   '粉絲',
    badges:      '徽章',
  };

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

  /* ---------- render ---------- */
  function renderRow(r) {
    const isMe = cfg.currentUid && Number(cfg.currentUid) === r.user_id;
    const cls  = [
      'ranku-row',
      'ranku-row--' + r.rank,
      isMe ? 'ranku-row--me' : '',
    ].filter(Boolean).join(' ');

    const posHtml = r.rank <= 3
      ? `<span class="ranku-pos-medal">${medal(r.rank)}</span>`
      : escapeHtml(String(r.rank));

    return `
      <div class="${cls}" data-uid="${r.user_id}">
        <div class="ranku-pos">${posHtml}</div>
        <img class="ranku-avatar" src="${escapeHtml(r.avatar)}" alt="${escapeHtml(r.display)}" loading="lazy">
        <div class="ranku-userinfo">
          <h4 class="ranku-name">
            <a href="${escapeHtml(r.profile_url)}">${escapeHtml(r.display)}</a>
          </h4>
          <div class="ranku-meta">
            <span class="ranku-lv">Lv.${r.level}</span>
            ${r.title ? `<span class="ranku-tier">${escapeHtml(r.icon || '')} ${escapeHtml(r.title)}</span>` : ''}
          </div>
        </div>
        <div class="ranku-score-wrap">
          <div class="ranku-score">${escapeHtml(r.score_fmt)}</div>
          <span class="ranku-score-unit">${escapeHtml(scoreUnit[state.type] || '')}</span>
        </div>
      </div>
    `;
  }

  function renderList(data) {
    if (!data.rows || data.rows.length === 0) {
      listEl.innerHTML = `
        <div class="ranku-empty">
          <i class="fa-solid fa-hourglass-half"></i>
          <h3 class="ranku-empty-title">尚無排行資料</h3>
          <p class="ranku-empty-desc">系統將每小時自動更新，請稍後再來看看～</p>
        </div>`;
      pageEl.hidden = true;
      return;
    }

    listEl.innerHTML = data.rows.map(renderRow).join('');

    /* pagination */
    state.totalPage = Math.max(1, data.total_page);
    if (state.totalPage <= 1) {
      pageEl.hidden = true;
    } else {
      pageEl.hidden = false;
      pageInfo.textContent = `${state.page} / ${state.totalPage}`;
      prevBtn.disabled = state.page <= 1;
      nextBtn.disabled = state.page >= state.totalPage;
    }

    /* meta */
    if (countInfo) {
      countInfo.textContent = `${typeLabel[state.type]} · Top ${data.total}`;
    }
    if (updatedEl && data.updated_at) updatedEl.textContent = data.updated_at;
    if (mePosEl) {
      mePosEl.textContent = data.my_pos ? '#' + data.my_pos : '未上榜';
      if (heroMeEl) heroMeEl.dataset.tab = state.type;
    }
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
        // 更新網址 query（不重新整理）
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

  /* ---------- privacy toggle ---------- */
  if (togBox && cfg.privacy) {
    togBox.addEventListener('change', () => {
      const visible = togBox.checked ? '1' : '0';
      togBox.disabled = true;

      const fd = new FormData();
      fd.append('action',  'smacg_toggle_ranking_visibility');
      fd.append('nonce',   cfg.privacy.nonce);
      fd.append('visible', visible);

      fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
          togBox.disabled = false;
          if (res.success) {
            togLabel.textContent = res.data.visible ? '顯示於排行榜' : '已隱藏';
            toast(res.data.message, 'ok');
            if (!res.data.visible) load();   // 立即刷新（自己會消失）
          } else {
            togBox.checked = !togBox.checked;
            toast(res.data?.message || '更新失敗', 'err');
          }
        })
        .catch(() => {
          togBox.disabled = false;
          togBox.checked = !togBox.checked;
          toast('網路錯誤', 'err');
        });
    });
  }

  /* ---------- init ---------- */
  load();
})();
