/**
 * Bangumi 模組互動
 * v1.2.0 (2026-05-18)
 *
 * 範圍：/bangumi/、/bangumi/{ym}/、/bangumi/archive/
 *
 * 變更紀錄：
 *   1.2.0 (2026-05-18) 排序改用 .bgm-card + data-* 屬性（搭配 page-bangumi.php v1.2.0 專屬卡片）
 *   1.1.0 (2026-05-18) 收納 page-bangumi.php 抽出的星期切換 / 排序 JS
 *   1.0.0 (2026-05-18) Archive 頁滑入動畫 + 鍵盤導航
 */
(function () {
  'use strict';

  /* ══════════════════════════════════════════
     Season 頁：星期切換
  ══════════════════════════════════════════ */
  const bar  = document.getElementById('bgm-weekday-bar');
  const grid = document.getElementById('bgm-grid');

  if (bar && grid) {
    const groups = grid.querySelectorAll('.bgm-group');

    bar.addEventListener('click', function (e) {
      const btn = e.target.closest('.bgm-day');
      if (!btn) return;
      const day = parseInt(btn.dataset.day, 10);

      document.querySelectorAll('.bgm-day').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      groups.forEach(g => {
        g.hidden = (parseInt(g.dataset.group, 10) !== day);
      });
    });

    /* ════════════════════════════════════════
       Season 頁：排序（依 data-score / data-ep / data-pop）
    ════════════════════════════════════════ */
    const sort = document.getElementById('bgm-sort');
    if (sort) {
      // 記錄每組的預設順序，供「預設排序」還原
      const originalOrder = new Map();
      groups.forEach(group => {
        originalOrder.set(group, Array.from(group.querySelectorAll('.bgm-card')));
      });

      sort.addEventListener('change', function () {
        const mode = this.value;

        groups.forEach(group => {
          if (mode === 'default') {
            // 還原原始順序
            const orig = originalOrder.get(group);
            if (orig) orig.forEach(c => group.appendChild(c));
            return;
          }

          const attr = mode === 'ep'         ? 'ep'
                     : mode === 'popularity' ? 'pop'
                     : 'score';

          const cards = Array.from(group.querySelectorAll('.bgm-card'));
          cards.sort((a, b) => {
            const va = parseFloat(a.dataset[attr]) || 0;
            const vb = parseFloat(b.dataset[attr]) || 0;
            return vb - va;
          });
          cards.forEach(c => group.appendChild(c));
        });
      });
    }
  }

  /* ══════════════════════════════════════════
     Archive 頁：滑入動畫（IntersectionObserver）
  ══════════════════════════════════════════ */
  const years = document.querySelectorAll('.bgm-arc-year');
  if (years.length && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.style.opacity = '1';
          e.target.style.transform = 'translateY(0)';
          io.unobserve(e.target);
        }
      });
    }, { rootMargin: '0px 0px -40px 0px' });

    years.forEach(y => {
      y.style.opacity = '0';
      y.style.transform = 'translateY(12px)';
      y.style.transition = 'opacity .35s ease, transform .35s ease';
      io.observe(y);
    });
  }

  /* ══════════════════════════════════════════
     Archive 頁：鍵盤導航（方向鍵在四季方塊間切換）
  ══════════════════════════════════════════ */
  document.querySelectorAll('.bgm-arc-seasons').forEach(group => {
    const seasons = Array.from(group.querySelectorAll('.bgm-arc-season:not(.is-empty)'));
    seasons.forEach((el, idx) => {
      el.addEventListener('keydown', e => {
        let next = null;
        if (e.key === 'ArrowRight') next = seasons[idx + 1];
        if (e.key === 'ArrowLeft')  next = seasons[idx - 1];
        if (next) { e.preventDefault(); next.focus(); }
      });
    });
  });

})();
