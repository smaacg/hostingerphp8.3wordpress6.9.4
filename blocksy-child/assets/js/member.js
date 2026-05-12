/**
 * Member Center JS v2.0.3 (2026-05-12)
 * - v2.0.1: 收藏分頁改用 data-favorited 判斷
 * - v2.0.2: 新增頭像即時上傳（AJAX）
 * - v2.0.3: 支援 URL ?tab= / ?profiletab= / #hash 自動切換分頁；切換時同步 hash
 */
(function ($) {
  'use strict';
  if (typeof smacgMember === 'undefined') return;

  const $wrap = $('.mc-wrap');
  if (!$wrap.length) return;

  const PAGE_SIZE = matchMedia('(max-width: 480px)').matches ? 12 : 20;

  /* ===== Tabs ===== */
  function switchTab(name, updateHash) {
    const $tab = $(`.mc-tab[data-tab="${name}"]`);
    if (!$tab.length) return false;
    $('.mc-tab').removeClass('active');
    $tab.addClass('active');
    $('.mc-panel').removeClass('active');
    $(`.mc-panel[data-panel="${name}"]`).addClass('active');
    if (name === 'stats') animateBars();
    if (updateHash && history.replaceState) {
      history.replaceState(null, '', '#' + name);
    }
    return true;
  }

  $wrap.on('click', '.mc-tab', function () {
    const t = $(this).data('tab');
    switchTab(t, true);
  });

  /* ===== URL 自動切換（v2.0.3） ===== */
  (function initTabFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const fromQuery = params.get('tab') || params.get('profiletab');
    const fromHash  = (window.location.hash || '').replace('#', '');
    const target    = fromQuery || fromHash;
    if (!target) return;

    // 等渲染完，避免被預設 active 蓋掉
    setTimeout(function () {
      if (switchTab(target, false)) {
        const $nav = $('.mc-tabs');
        if ($nav.length) {
          $('html, body').animate({ scrollTop: $nav.offset().top - 80 }, 300);
        }
      }
    }, 50);
  })();

  // 監聽 hash 變化（從同頁的 a href="#xxx" 點過來時）
  $(window).on('hashchange', function () {
    const t = (window.location.hash || '').replace('#', '');
    if (t) switchTab(t, false);
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

  /* ===== Filter（收藏走 data-favorited） ===== */
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

  /* ===== Avatar Upload (v2.0.2) ===== */
  const $avatarInput = $('#mc-avatar-input');
  const $avatarImg   = $('#mc-avatar-img');
  const $avatarMsg   = $('#mc-avatar-msg');

  $avatarInput.on('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;

    if (file.size > 1024 * 1024) {
      showAvatarMsg('檔案過大（上限 1 MB）', 'error');
      this.value = '';
      return;
    }

    // 即時預覽
    const reader = new FileReader();
    reader.onload = e => $avatarImg.css('opacity', '.5').attr('src', e.target.result);
    reader.readAsDataURL(file);

    const fd = new FormData();
    fd.append('action', 'smacg_upload_avatar');
    fd.append('nonce', smacgMember.nonce);
    fd.append('avatar', file);

    showAvatarMsg('上傳中…', 'info');

    fetch(smacgMember.ajax, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          $avatarImg.css('opacity', '1').attr('src', res.data.url + '?t=' + Date.now());
          showAvatarMsg(res.data.msg || '頭像已更新', 'success');
          setTimeout(() => $avatarMsg.hide(), 2500);

          // 同步 header / 留言區頭像
          $('img.avatar, .um-user-avatar img').each(function () {
            const $img = $(this);
            const src = $img.attr('src') || '';
            if (src.includes('gravatar.com') || src.includes('avatar')) {
              $img.attr('src', res.data.url + '?t=' + Date.now());
            }
          });
        } else {
          $avatarImg.css('opacity', '1');
          showAvatarMsg((res.data && res.data.msg) || '上傳失敗', 'error');
        }
      })
      .catch(() => {
        $avatarImg.css('opacity', '1');
        showAvatarMsg('網路錯誤', 'error');
      })
      .finally(() => {
        $avatarInput.val('');
      });
  });

  function showAvatarMsg(text, type) {
    $avatarMsg
      .attr('class', 'mc-avatar-msg mc-avatar-msg--' + type)
      .text(text)
      .show();
  }

  /* ===== 設定面板：重設密碼按鈕（v2.0.3） ===== */
  // 若 render_settings 的按鈕沒有自帶連結，這裡兜底
  $wrap.on('click', '.mc-settings-reset-pwd', function (e) {
    e.preventDefault();
    window.location.href = smacgMember.passwordResetUrl || '/password-reset/';
  });
  /* ===== 設定面板：基本資料 inline 儲存 ===== */
  $wrap.on('submit', '#mc-profile-form', function (e) {
    e.preventDefault();
    const $form = $(this);
    const $msg  = $('#mc-profile-msg');
    const $btn  = $form.find('button[type=submit]');

    $btn.prop('disabled', true).text('儲存中…');
    $msg.hide();

    $.post(smacgMember.ajax, {
      action: 'smacg_update_profile',
      nonce: $form.find('[name=smacg_profile_nonce]').val(),
      display_name: $form.find('[name=display_name]').val(),
      nickname:     $form.find('[name=nickname]').val(),
      description:  $form.find('[name=description]').val(),
    }, null, 'json')
    .done(res => {
      if (res.success) {
        $msg.text(res.data.msg).attr('class','mc-set-msg mc-set-msg--ok').show();
        // 同步 hero 區顯示名稱
        $('.mc-hero-name').contents().filter(function(){return this.nodeType===3;}).first()
          .replaceWith(document.createTextNode($form.find('[name=display_name]').val() + ' '));
      } else {
        $msg.text((res.data && res.data.msg) || '儲存失敗').attr('class','mc-set-msg mc-set-msg--err').show();
      }
    })
    .fail(() => $msg.text('網路錯誤').attr('class','mc-set-msg mc-set-msg--err').show())
    .always(() => $btn.prop('disabled', false).text('儲存變更'));
  });

})(jQuery);
