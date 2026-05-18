/**
 * Bangumi 模組互動
 * v1.1.0 (2026-05-18)
 *
 * 範圍：/bangumi/、/bangumi/{ym}/、/bangumi/archive/
 *
 * 變更紀錄：
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
       Season 頁：排序
    ════════════════════════════════════════ */
    const sort = document.getElementById('bgm-sort');
    if (sort) {
      sort.addEventListener('change', function () {
        const mode = this.value;

        groups.forEach(group => {
          const cards = Array.from(group.querySelectorAll('.bgm-card'));
          cards.sort((a, b) => {
            const getScore = el => parseFloat(
              (el.querySelector('.mc-card-score')?.textContent || '').replace(/[^0-9.]/g, '')
            ) || 0;
            const getEp = el => parseInt(
              el.querySelector('.mc-card-meta span:nth-child(2)')?.textContent || '0',
              10
            ) || 0;
            if (mode === 'score') return getScore(b) - getScore(a);
            if (mode === 'ep')    return getEp(b) - getEp(a);
            return 0;
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
     Archive 頁：鍵盤導航
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
