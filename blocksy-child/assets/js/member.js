/**
 * Member Center JS v2.1.0 (2026-05-13)
 * - v2.0.1: 收藏分頁改用 data-favorited 判斷
 * - v2.0.2: 新增頭像即時上傳（AJAX）
 * - v2.0.3: 支援 URL ?tab= / ?profiletab= / #hash 自動切換分頁；切換時同步 hash
 * - v2.1.0 (Batch B): 留言分頁載入（.mc-loadmore-comments）
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

  /* ===== Load more（watchlist / ratings） ===== */
  $wrap.on('click', '.mc-loadmore', function () {
    // 留言的 load more 由獨立 handler 處理（避免衝突）
    if ($(this).hasClass('mc-loadmore-comments')) return;

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

  /* =========================================================
   * Batch B (v2.1.0)：留言載入更多
   * 對應 PHP：wp_ajax_smacg_load_more_comments
   * 按鈕由 smacg_render_comments() 產生，class="mc-loadmore mc-loadmore-comments"
   * data-loaded / data-total / data-nonce
   * ========================================================= */
  $wrap.on('click', '.mc-loadmore-comments', function () {
    const $btn = $(this);
    if ($btn.prop('disabled')) return;

    const loaded = parseInt($btn.data('loaded'), 10) || 0;
    const total  = parseInt($btn.data('total'), 10) || 0;
    const nonce  = $btn.data('nonce');

    if (loaded >= total) {
      $btn.closest('.mc-loadmore-wrap').remove();
      return;
    }

    $btn.addClass('loading').prop('disabled', true);
    const originalLabel = $btn.html();
    $btn.text('載入中…');

    $.ajax({
      url: smacgMember.ajax,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'smacg_load_more_comments',
        nonce:  nonce,
        offset: loaded
      }
    })
    .done(function (res) {
      if (!res || !res.success) {
        alert((res && res.data && res.data.msg) || '載入失敗');
        $btn.html(originalLabel);
        return;
      }

      // append 新留言
      $('#mc-cmt-list').append(res.data.html);

      // 更新按鈕狀態
      $btn.data('loaded', res.data.loaded);

      if (res.data.has_more) {
        const remain = res.data.total - res.data.loaded;
        $btn.html('載入更多（剩 <span>' + remain + '</span>）');
      } else {
        $btn.closest('.mc-loadmore-wrap').fadeOut(200, function () {
          $(this).remove();
        });
      }
    })
    .fail(function () {
      alert('網路錯誤，請稍後再試');
      $btn.html(originalLabel);
    })
    .always(function () {
      $btn.removeClass('loading').prop('disabled', false);
    });
  });

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

/* =========================================================
 * P0-1: 隱私 toggle 開關
 * ========================================================= */
$wrap.on('change', '.mc-privacy-form input[type=checkbox]', function(){
    const $box   = $(this);
    const $form  = $box.closest('.mc-privacy-form');
    const $row   = $box.closest('.mc-toggle-row');
    const $msg   = $('#mc-privacy-msg');
    const key    = $box.data('key');
    const value  = $box.is(':checked') ? 1 : 0;
    const nonce  = $form.data('nonce');

    $box.prop('disabled', true);
    $.post(smacgMember.ajax, {
        action: 'smacg_update_privacy',
        nonce:  nonce,
        key:    key,
        value:  value
    }, null, 'json')
    .done(r => {
        if (r.success) {
            $msg.text(r.data.msg).attr('class','mc-set-msg mc-set-msg--ok').show();
            setTimeout(()=>$msg.fadeOut(),1800);

            // 整列高亮 1.2 秒
            $row.addClass('is-saved');
            setTimeout(()=>$row.removeClass('is-saved'), 1200);
        } else {
            $box.prop('checked', !value);
            $msg.text(r.data.msg || '儲存失敗').attr('class','mc-set-msg mc-set-msg--err').show();
        }
    })
    .fail(()=>{
        $box.prop('checked', !value);
        $msg.text('網路錯誤').attr('class','mc-set-msg mc-set-msg--err').show();
    })
    .always(()=> $box.prop('disabled', false));
});

/* =========================================================
 * P0-2: 卡片快速操作（+1 集 / 完成 / 移除）
 * 直接呼叫既有 REST：/wp-json/weixiaoacg/v1/user-status/{id}
 * ========================================================= */
const REST_BASE = (window.wpApiSettings && wpApiSettings.root)
    ? wpApiSettings.root + 'weixiaoacg/v1/user-status/'
    : '/wp-json/weixiaoacg/v1/user-status/';
const REST_NONCE = (window.wpApiSettings && wpApiSettings.nonce) || '';

function restCall(animeId, method, body){
    return fetch(REST_BASE + animeId, {
        method: method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce':   REST_NONCE
        },
        body: body ? JSON.stringify(body) : undefined
    }).then(r => r.json());
}

function toast(text, ok){
    let $t = $('#mc-toast');
    if(!$t.length){
        $t = $('<div id="mc-toast" class="mc-toast"></div>').appendTo('body');
    }
    $t.text(text)
      .attr('class', 'mc-toast ' + (ok ? 'mc-toast--ok' : 'mc-toast--err'))
      .stop(true,true).fadeIn(120).delay(1400).fadeOut(300);
}

// +1 集
$wrap.on('click', '.mc-card-btn--plus', function(){
    const $btn  = $(this);
    const $bar  = $btn.closest('.mc-card-actions');
    const id    = $bar.data('anime');
    const total = parseInt($bar.data('total'),10) || 0;
    let   cur   = parseInt($bar.data('progress'),10) || 0;
    if(total && cur >= total){
        toast('已達總集數', false); return;
    }
    $btn.prop('disabled', true);
    restCall(id, 'POST', {action:'progress', value: 1}).then(r => {
        if(r && r.success){
            cur += 1;
            $bar.data('progress', cur);
            // 更新進度條 + 數字（卡片 DOM 自行尋找）
            const $card = $btn.closest('.mc-anime-card, .mc-card');
            const $fill = $card.find('.mc-progress-bar, .mc-card-progress-fill');
            const $txt  = $card.find('.mc-progress-text, .mc-card-progress-text');
            if(total){
                const pct = Math.min(100, Math.round(cur/total*100));
                $fill.css('width', pct + '%');
                $txt.text(cur + ' / ' + total);
                if(cur >= total){
                    toast('🎉 已看完所有集數！', true);
                } else {
                    toast('進度 +1 ✓', true);
                }
            } else {
                $txt.text(cur + ' 集');
                toast('進度 +1 ✓', true);
            }
        } else {
            toast((r && r.message) || '更新失敗', false);
        }
    }).catch(() => toast('網路錯誤', false))
      .finally(() => $btn.prop('disabled', false));
});

// 標記完成
$wrap.on('click', '.mc-card-btn--done', function(){
    const $btn = $(this);
    const id   = $btn.closest('.mc-card-actions').data('anime');
    if(!confirm('確定標記為看完？')) return;
    $btn.prop('disabled', true);
    restCall(id, 'POST', {action:'status', value: 'completed'}).then(r => {
        if(r && r.success){
            toast('已標記完成 ✓', true);
            setTimeout(()=>location.reload(), 600);
        } else {
            toast((r && r.message) || '更新失敗', false);
            $btn.prop('disabled', false);
        }
    }).catch(()=>{ toast('網路錯誤', false); $btn.prop('disabled', false); });
});

// 從清單移除
$wrap.on('click', '.mc-card-btn--remove', function(){
    const $btn  = $(this);
    const $card = $btn.closest('.mc-anime-card, .mc-card');
    const id    = $btn.closest('.mc-card-actions').data('anime');
    if(!confirm('確定要從清單中移除？此動作不可復原。')) return;
    $btn.prop('disabled', true);
    restCall(id, 'DELETE').then(r => {
        if(r && r.success){
            $card.fadeOut(200, function(){ $(this).remove(); });
            toast('已移除 ✓', true);
        } else {
            toast((r && r.message) || '移除失敗', false);
            $btn.prop('disabled', false);
        }
    }).catch(()=>{ toast('網路錯誤', false); $btn.prop('disabled', false); });
});

  /* =========================================================
   * P1-2: Continue Watching 左右箭頭捲動
   * +1 按鈕直接重用 P0-2 的 .mc-card-btn--plus
   * ========================================================= */
  $wrap.on('click', '.mc-continue-arrow', function () {
    const $scroll = $('#mc-continue-scroll');
    if (!$scroll.length) return;
    const dir = $(this).data('dir');
    const cardWidth = $scroll.find('.mc-continue-card').outerWidth(true) || 200;
    const distance  = cardWidth * 2; // 每次滑 2 張
    $scroll[0].scrollBy({
      left: dir === 'next' ? distance : -distance,
      behavior: 'smooth'
    });
  });

  // 進度滿時自動移除卡片（搭配 P0-2 的 +1 完成事件）
  // 由於 P0-2 已有 toast「🎉 已看完所有集數」邏輯，這裡延伸：把卡片移出 Continue Watching
  $wrap.on('click', '.mc-continue-card .mc-card-btn--plus', function () {
    const $card    = $(this).closest('.mc-continue-card');
    const $actions = $(this).closest('.mc-card-actions');
    const total    = parseInt($actions.data('total'), 10) || 0;
    // 在 P0-2 fetch 完成後檢查：因為 P0-2 會更新 data-progress
    setTimeout(function () {
      const cur = parseInt($actions.data('progress'), 10) || 0;
      if (total > 0 && cur >= total) {
        $card.fadeOut(400, function () {
          $(this).remove();
          // 若全空，整區顯示空狀態
          if (!$('#mc-continue-scroll .mc-continue-card').length) {
            $('#mc-continue-section').fadeOut(300);
          }
        });
      }
    }, 800); // 等 REST 回來 + DOM 更新
  });


})(jQuery);
