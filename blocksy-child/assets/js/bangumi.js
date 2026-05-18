/**
 * Bangumi 模組互動
 * v1.0.0 (2026-05-18)
 *
 * 範圍：/bangumi/、/bangumi/{ym}/、/bangumi/archive/
 * 由 bangumi-loader.php 條件式 enqueue
 */
(function () {
  'use strict';

  /* ══════════════════════════════════════════
     Archive：滑入動畫（IntersectionObserver）
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
     Archive：鍵盤導航（年份卡片內 Tab 順序保持，方向鍵在四季方塊間切換）
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

  /* ══════════════════════════════════════════
     Season 頁：（預留位）未來 AJAX 快速追番按鈕、
     hover preview、即時排序…現階段不啟用，
     避免影響 page-bangumi.php 內的 inline JS。
  ══════════════════════════════════════════ */

})();
