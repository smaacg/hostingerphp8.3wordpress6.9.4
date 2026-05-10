/**
 * Member Center JS v2.0
 * Tab 切換 · 篩選 · 搜尋 · 排序 · 載入更多 · 圖表入場動畫
 */
(function ($) {
  'use strict';
  if (typeof smacgMember === 'undefined') return;

  const $wrap = $('.mc-wrap');
  if (!$wrap.length) return;

  const isMobile = matchMedia('(max-width: 480px)').matches;
  const PAGE_SIZE = isMobile ? 12 : 20;

  /* ========== Tabs ========== */
  $wrap.on('click', '.mc-tab', function () {
    const t = $(this).data('tab');
    $('.mc-tab').removeClass('active');
    $(this).addClass('active');
    $('.mc-panel').removeClass('active');
    $(`.mc-panel[data-panel="${t}"]`).addClass('active');

    // 進到 stats 時觸發圖表動畫
    if (t === 'stats') animateBars();
  });

  /* ========== 圖表動畫 ========== */
  function animateBars() {
    $('.mc-bar-fill').each(function () {
      const w = this.style.width;
      this.style.width = '0';
      requestAnimationFrame(() => { this.style.width = w; });
    });
  }
  // 首次若已在 stats 也要跑
  if ($('.mc-panel[data-panel="stats"].active').length) animateBars();

  /* ========== Filter (我的清單) ========== */
  let currentFilter = 'all';
  $wrap.on('click', '.mc-filter-btn', function () {
    currentFilter = $(this).data('filter');
    $('.mc-filter-btn').removeClass('active');
    $(this).addClass('active');

    $('#mc-watchlist-grid .mc-anime-card').each(function () {
      const s = $(this).data('status');
      const show = currentFilter === 'all'
        || s === currentFilter
        || (currentFilter === 'favorited' && $(this).find('.mc-card-heart').length);
      $(this).toggle(!!show);
    });
  });

  /* ========== Search (即時前端過濾) ========== */
  let searchTimer;
  $wrap.on('input', '.mc-search', function () {
    clearTimeout(searchTimer);
    const $input = $(this);
    const target = $input.data('target');
    const q = $input.val().trim().toLowerCase();

    searchTimer = setTimeout(() => {
      const $grid = $(`#mc-${target}-grid`);
      $grid.find('.mc-anime-card').each(function () {
        const title = $(this).data('title') || '';
        $(this).toggle(title.indexOf(q) !== -1);
      });
    }, 200);
  });

  /* ========== Sort (向後端拿排序後資料) ========== */
  $wrap.on('change', '.mc-sort', function () {
    const target = $(this).data('target');
    const sort = $(this).val();
    reloadList(target, { sort, offset: 0, replace: true });
  });

  /* ========== Load more ========== */
  $wrap.on('click', '.mc-loadmore', function () {
    const $btn = $(this);
    if ($btn.prop('disabled')) return;
    const type = $btn.data('type');
    const offset = parseInt($btn.data('loaded'), 10) || 0;
    reloadList(type, { offset, append: true, $btn });
  });

  /* ========== AJAX 統一入口 ========== */
  function reloadList(type, opts) {
    const $btn = opts.$btn || $(`.mc-loadmore[data-type="${type}"]`);
    const $grid = $(`#mc-${type}-grid`);
    const $sort = $(`.mc-sort[data-target="${type}"]`);
    const $search = $(`.mc-search[data-target="${type}"]`);

    $btn.addClass('loading').prop('disabled', true);

    $.ajax({
      url: smacgMember.ajax,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'smacg_member_loadmore',
        nonce: smacgMember.nonce,
        type,
        offset: opts.offset || 0,
        limit: PAGE_SIZE,
        filter: type === 'watchlist' ? currentFilter : 'all',
        sort: opts.sort || $sort.val() || 'updated',
        search: $search.val() || '',
      }
    }).done(res => {
      if (!res.success) { alert(res.data?.msg || '載入失敗'); return; }
      if (opts.replace) $grid.html(res.data.html);
      else $grid.append(res.data.html);

      $btn.data('loaded', res.data.loaded);
      if (res.data.has_more) {
        $btn.find('span').text(res.data.total - res.data.loaded);
      } else {
        $btn.closest('.mc-loadmore-wrap').remove();
      }
    }).fail(() => {
      alert('網路錯誤，請稍後再試');
    }).always(() => {
      $btn.removeClass('loading').prop('disabled', false);
    });
  }

})(jQuery);
