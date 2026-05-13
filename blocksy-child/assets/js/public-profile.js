/**
 * Public Profile JS - /u/{username}/
 * Version: 1.0.0 (2026-05-13)
 *
 * 功能：
 *  - Tab 切換 + URL hash 同步
 *  - 清單篩選（沿用 member.js 風格）
 *  - 分享按鈕（Web Share API + 複製連結 fallback）
 *  - 預留：載入更多、追蹤按鈕（Batch 1B 啟用）
 */
(function () {
  'use strict';

  const wrap = document.querySelector('.pp-wrap');
  if (!wrap) return;

  /* =========================================================
   * Tab 切換
   * ========================================================= */
  const tabs   = wrap.querySelectorAll('.pp-tab');
  const panels = wrap.querySelectorAll('.pp-panel');

  function switchTab(name, updateHash) {
    let found = false;
    tabs.forEach(t => {
      const match = t.dataset.tab === name;
      t.classList.toggle('active', match);
      if (match) found = true;
    });
    panels.forEach(p => {
      p.classList.toggle('active', p.dataset.panel === name);
    });
    if (found && updateHash && history.replaceState) {
      history.replaceState(null, '', '#' + name);
    }
    return found;
  }

  tabs.forEach(t => {
    t.addEventListener('click', () => switchTab(t.dataset.tab, true));
  });

  // 初始 hash → tab
  (function initFromHash() {
    const hash = (window.location.hash || '').replace('#', '');
    if (hash) {
      // 等版面就緒
      setTimeout(() => switchTab(hash, false), 30);
    }
  })();

  window.addEventListener('hashchange', () => {
    const hash = (window.location.hash || '').replace('#', '');
    if (hash) switchTab(hash, false);
  });

  /* =========================================================
   * Watchlist 篩選
   * ========================================================= */
  const filterBtns = wrap.querySelectorAll('.pp-filter-btn');
  const cards      = wrap.querySelectorAll('#pp-watchlist-grid .pp-anime-card');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const filter = btn.dataset.filter;

      filterBtns.forEach(b => b.classList.toggle('active', b === btn));

      cards.forEach(card => {
        const status = card.dataset.status || '';
        const fav    = card.dataset.favorited === '1';
        let show     = false;

        if (filter === 'all')           show = true;
        else if (filter === 'favorited') show = fav;
        else                             show = (status === filter);

        card.style.display = show ? '' : 'none';
      });
    });
  });

  /* =========================================================
   * 分享按鈕
   * ========================================================= */
  const shareBtn = wrap.querySelector('.pp-btn-share');
  if (shareBtn) {
    shareBtn.addEventListener('click', async () => {
      const url   = shareBtn.dataset.url   || window.location.href;
      const title = shareBtn.dataset.title || document.title;

      // 優先用原生 Web Share API（手機）
      if (navigator.share) {
        try {
          await navigator.share({ title, url });
          return;
        } catch (e) {
          // 使用者取消分享 → 不做事
          if (e.name === 'AbortError') return;
        }
      }

      // Fallback：複製到剪貼簿
      try {
        await navigator.clipboard.writeText(url);
        toast('連結已複製 ✓', true);
      } catch (e) {
        // 再 fallback：用 input 選取
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        try {
          document.execCommand('copy');
          toast('連結已複製 ✓', true);
        } catch (err) {
          toast('複製失敗，請手動複製網址', false);
        }
        document.body.removeChild(input);
      }
    });
  }

  /* =========================================================
   * Toast（公開頁專用，避免污染 member.js 的 toast）
   * ========================================================= */
  function toast(text, ok) {
    let el = document.getElementById('pp-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'pp-toast';
      el.className = 'pp-toast';
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.className = 'pp-toast pp-toast--show pp-toast--' + (ok ? 'ok' : 'err');

    clearTimeout(el._t);
    el._t = setTimeout(() => {
      el.classList.remove('pp-toast--show');
    }, 1800);
  }

  /* =========================================================
   * 追蹤按鈕（Batch 1B 啟用，目前 disabled）
   * ========================================================= */
  const followBtn = wrap.querySelector('.pp-btn-follow');
  if (followBtn && !followBtn.disabled) {
    followBtn.addEventListener('click', () => {
      toast('追蹤功能即將推出', true);
    });
  }

  /* =========================================================
   * 平滑滾動到 tab（從 URL hash 進來時）
   * ========================================================= */
  (function scrollToTabIfHash() {
    if (!window.location.hash) return;
    const tabsNav = wrap.querySelector('.pp-tabs');
    if (!tabsNav) return;
    setTimeout(() => {
      const top = tabsNav.getBoundingClientRect().top + window.pageYOffset - 80;
      window.scrollTo({ top, behavior: 'smooth' });
    }, 100);
  })();

})();
