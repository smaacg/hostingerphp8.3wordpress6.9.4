/**
 * Member Center JS v2.0.1
 * 修正：收藏分頁改用 data-favorited 判斷
 */
(function ($) {
  'use strict';
  if (typeof smacgMember === 'undefined') return;

  const $wrap = $('.mc-wrap');
  if (!$wrap.length) return;

  const PAGE_SIZE = matchMedia('(max-width: 480px)').matches ? 12 : 20;

  /* ===== Tabs ===== */
  $wrap.on('click', '.mc-tab', function () {
    const t = $(this).data('tab');
    $('.mc-tab').removeClass('active');
    $(this).addClass('active');
    $('.mc-panel').removeClass('active');
    $(`.mc-panel[data-panel="${t}"]`).addClass('active');
    if (t === 'stats') animateBars();
  });

  /* ===== 圖表動畫 ===== */
  function animateBars() {
    $('.mc-bar-fill').each(function () {
      const w = this.style.width;
      this.style.width = '0';
      requestAnimationFrame(() => { this.style.width = w; });
    });
  }
  if ($('.mc-panel[data-panel="stats"].active').length) animateBars();

  /* ===== Filter（修正：收藏走 data-favorited） ===== */
  let currentFilter = 'all';
  $wrap.on('click', '.mc-filter-btn', function () {
    currentFilter = String($(this).data('filter'));
    $('.mc-filter-btn').removeClass('active');
    $(this).addClass('active');

    $('#mc-watchlist-grid .mc-anime-card').each(function () {
      const $c = $(this);
      const status = String($c.data('status') || '');
      const fav = String($c.data('favorited')) === '1';
      let show = false;
      if (currentFilter === 'all') show = true;
      else if (currentFilter === 'favorited') show = fav;
      else show = (status === currentFilter);
      $c.toggle(show);
    });
  });

  /* ===== Search ===== */
  let searchTimer;
  $wrap.on('input', '.mc-search', function () {
    clearTimeout(searchTimer);
    const $input = $(this);
    const target = $input.data('target');
    const q = $input.val().trim().toLowerCase();
    searchTimer = setTimeout(() => {
      $(`#mc-${target}-grid`).find('.mc-anime-card').each(function () {
        const t = $(this).data('title') || '';
        $(this).toggle(t.indexOf(q) !== -1);
      });
    }, 200);
  });

  /* ===== Sort ===== */
  $wrap.on('change', '.mc-sort', function () {
    reloadList($(this).data('target'), { sort: $(this).val(), offset: 0, replace: true });
  });

  /* ===== Load more ===== */
  $wrap.on('click', '.mc-loadmore', function () {
    const $btn = $(this);
    if ($btn.prop('disabled')) return;
    reloadList($btn.data('type'), {
      offset: parseInt($btn.data('loaded'), 10) || 0,
      append: true, $btn
    });
  });

  /* ===== AJAX ===== */
  function reloadList(type, opts) {
    const $btn = opts.$btn || $(`.mc-loadmore[data-type="${type}"]`);
    const $grid = $(`#mc-${type}-grid`);
    const $sort = $(`.mc-sort[data-target="${type}"]`);
    const $search = $(`.mc-search[data-target="${type}"]`);

    $btn.addClass('loading').prop('disabled', true);

    $.ajax({
      url: smacgMember.ajax, method: 'POST', dataType: 'json',
      data: {
        action: 'smacg_member_loadmore',
        nonce: smacgMember.nonce,
        type, offset: opts.offset || 0, limit: PAGE_SIZE,
        filter: type === 'watchlist' ? currentFilter : 'all',
        sort: opts.sort || $sort.val() || 'updated',
        search: $search.val() || '',
      }
    }).done(res => {
      if (!res.success) { alert(res.data?.msg || '載入失敗'); return; }
      if (opts.replace) $grid.html(res.data.html);
      else $grid.append(res.data.html);
      $btn.data('loaded', res.data.loaded);
      if (res.data.has_more) $btn.find('span').text(res.data.total - res.data.loaded);
      else $btn.closest('.mc-loadmore-wrap').remove();
    }).fail(() => alert('網路錯誤，請稍後再試'))
      .always(() => $btn.removeClass('loading').prop('disabled', false));
  }
})(jQuery);
