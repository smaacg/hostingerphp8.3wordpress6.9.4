/* ============================================================
   微笑動漫 — 動漫追蹤 + 評分 UI 腳本
   路徑：/blocksy-child/assets/js/anime-status.js
   版本：15.1 — P0 cutover，統一追番 API 到 smileacg/v1
   ============================================================ */
'use strict';

/* ============================================================
   一、追蹤列（Track Bar）
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {

    const cfg      = window.SmacgConfig || {};
    const bar      = document.querySelector('.smacg-track-bar');
    if (!bar) return;

    const postId   = parseInt(bar.dataset.postId, 10);
    const totalEp  = parseInt(bar.dataset.episodes, 10) || 0;
    const loggedIn = cfg.loggedIn === true || cfg.loggedIn === '1' || cfg.loggedIn === 1;
    const apiBase  = cfg.apiUrl  || '/wp-json/smileacg/v1/';
    const nonce    = cfg.nonce   || '';

    /* ── 未登入彈出 Modal ── */
    function requireLogin() {
        if (typeof window.smacgOpenLoginModal === 'function') {
            window.smacgOpenLoginModal();
            return;
        }
        const modal = document.getElementById('login-modal');
        if (modal) {
            const loginTab    = modal.querySelector('.lm-tab[data-tab="login"]');
            const registerTab = modal.querySelector('.lm-tab[data-tab="register"]');
            const loginPanel  = document.getElementById('lm-panel-login');
            const regPanel    = document.getElementById('lm-panel-register');
            if (loginTab)    loginTab.classList.add('active');
            if (registerTab) registerTab.classList.remove('active');
            if (loginPanel)  loginPanel.hidden = false;
            if (regPanel)    regPanel.hidden    = true;
            document.body.style.overflow = 'hidden';
            setTimeout(function () { modal.classList.add('lm-open'); }, 10);
        }
    }

    /* ── 本地狀態 ── */
    let state = {
        status:       bar.dataset.status      || null,
        progress:     parseInt(bar.dataset.progress, 10) || 0,
        favorited:    bar.dataset.favorited   === '1',
        fullcleared:  bar.dataset.fullcleared === '1',
        _prevCleared: bar.dataset.fullcleared === '1',
    };

    /* ── 元素參考 ── */
    const statusBtns  = bar.querySelectorAll('.smacg-status-btn');
    const progCurrent = bar.querySelector('.smacg-prog-current');
    const progBar     = bar.querySelector('.smacg-prog-bar');
    const progBtns    = bar.querySelectorAll('.smacg-prog-btn');
    const favBtn      = bar.querySelector('.smacg-fav-btn');
    const clearBtn    = bar.querySelector('.smacg-clear-btn');
    const shareBtn    = bar.querySelector('.smacg-share-btn');
    const pointToast  = bar.querySelector('.smacg-point-toast');
    const shareModal  = document.getElementById('smacg-share-modal');
    const shareClose  = document.getElementById('smacg-share-close');
    const copyBtn     = document.getElementById('smacg-copy-link');

    /* ── 工具：呼叫 API ── */
    function callApi(action, value) {
        if (!loggedIn) {
            requireLogin();
            return Promise.reject('not_logged_in');
        }
        return fetch(apiBase + 'user-status/' + postId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({
                action:  action,
                value:   value,
            }),
        }).then(function (res) {
            if (!res.ok) throw new Error('API error ' + res.status);
            return res.json();
        });
    }


    /* ── 積分 Toast ── */
    function showPointToast(points) {
        if (!points || points <= 0 || !pointToast) return;
        pointToast.textContent = '+' + points + ' ✨';
        pointToast.classList.remove('show');
        void pointToast.offsetWidth;
        pointToast.classList.add('show');
        setTimeout(function () { pointToast.classList.remove('show'); }, 1900);
    }

    /* ── 彩紙 ── */
    function launchConfetti() {
        const wrap   = document.createElement('div');
        wrap.className = 'smacg-confetti-wrap';
        document.body.appendChild(wrap);
        const colors = ['#63a8ff','#8f6bff','#ff6bae','#ffd60a','#34c759','#ff9500'];
        for (let i = 0; i < 60; i++) {
            const piece = document.createElement('div');
            piece.className = 'smacg-confetti-piece';
            piece.style.left              = Math.random() * 100 + 'vw';
            piece.style.background        = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDuration = (1.2 + Math.random() * 1.4) + 's';
            piece.style.animationDelay    = (Math.random() * 0.6) + 's';
            piece.style.width             = (6 + Math.random() * 6) + 'px';
            piece.style.height            = (6 + Math.random() * 6) + 'px';
            wrap.appendChild(piece);
        }
        setTimeout(function () { wrap.remove(); }, 2800);
    }

    /* ── Render ── */
    function renderStatus(newStatus) {
        statusBtns.forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.value === newStatus);
        });
    }

    function renderProgress(prog) {
        if (progCurrent) progCurrent.textContent = prog;
        if (progBar && totalEp > 0) {
            const pct   = Math.min(100, Math.round((prog / totalEp) * 100));
            progBar.style.width = pct + '%';
            const pctEl = bar.querySelector('.smacg-prog-pct');
            if (pctEl) pctEl.textContent = pct + '%';
        }
        const labelEl = bar.querySelector('.smacg-prog-label');
        if (labelEl) {
            if (state.fullcleared)  labelEl.textContent = '🎉 已全破！';
            else if (prog > 0)      labelEl.textContent = '📺 觀看中';
            else                    labelEl.innerHTML   = '&nbsp;';
        }
    }

    function renderFav(fav) {
        if (!favBtn) return;
        favBtn.classList.toggle('is-active', fav);
        const icon = favBtn.querySelector('i');
        if (icon) icon.className = fav ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
        favBtn.title = fav ? '取消收藏' : '收藏';
    }

    function renderClear(cleared) {
        if (!clearBtn) return;
        clearBtn.classList.toggle('is-active', cleared);
        clearBtn.title = cleared ? '已全破' : '標記全破';
    }

    /* ── 初始渲染 ── */
    renderStatus(state.status);
    renderProgress(state.progress);
    renderFav(state.favorited);
    renderClear(state.fullcleared);

      /* ── 狀態按鈕 ── */
    statusBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!loggedIn) { requireLogin(); return; }

            // 🚫 未播出時擋掉「追番中 / 已看完」
            if (btn.disabled) {
                const tip = btn.getAttribute('title') || '尚未播出，無法操作';
                if (typeof window.smacgShowToast === 'function') {
                    window.smacgShowToast(tip);
                } else if (pointToast) {
                    pointToast.textContent = tip;
                    pointToast.classList.remove('show');
                    void pointToast.offsetWidth;
                    pointToast.classList.add('show');
                    setTimeout(function () { pointToast.classList.remove('show'); }, 1900);
                } else {
                    alert(tip);
                }
                return;
            }

            const value     = btn.dataset.value;
            const newVal    = state.status === value ? 'none' : value;
            const oldStatus = state.status;
            state.status = newVal === 'none' ? null : newVal;
            renderStatus(state.status);
            btn.classList.add('is-loading');
            callApi('status', newVal)
                .then(function (res) {
                    if (res.success) {
                        state.status = res.entry.status;
                        renderStatus(state.status);
                        if (res.entry.status === 'completed' && totalEp > 0) {
                            state.progress    = totalEp;
                            state.fullcleared = true;
                            renderProgress(totalEp);
                            renderClear(true);
                            if (!state._prevCleared) launchConfetti();
                            state._prevCleared = true;
                        } else if (typeof res.entry.progress !== 'undefined') {
                            state.progress = res.entry.progress;
                            renderProgress(state.progress);
                        }
                        showPointToast(res.points_earned);
                    }
                })
                .catch(function () { state.status = oldStatus; renderStatus(state.status); })
                .finally(function () { btn.classList.remove('is-loading'); });
        });
    });

    /* ── 進度按鈕 ── */
    progBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!loggedIn) { requireLogin(); return; }
            const delta   = parseInt(btn.dataset.value, 10);
            const oldProg = state.progress;
            const newProg = Math.max(0, Math.min(totalEp || Infinity, oldProg + delta));
            if (newProg === oldProg) return;
            state.progress = newProg;
            renderProgress(newProg);
            btn.classList.add('is-loading');
            callApi('progress', delta)
                .then(function (res) {
                    if (res.success) {
                        state.progress    = res.entry.progress;
                        state.fullcleared = res.entry.fullcleared;
                        state.status      = res.entry.status;
                        renderProgress(state.progress);
                        renderStatus(state.status);
                        if (res.entry.fullcleared && !state._prevCleared) {
                            renderClear(true);
                            launchConfetti();
                        }
                        state._prevCleared = res.entry.fullcleared;
                        showPointToast(res.points_earned);
                    }
                })
                .catch(function () { state.progress = oldProg; renderProgress(oldProg); })
                .finally(function () { btn.classList.remove('is-loading'); });
        });
    });

    /* ── 收藏按鈕 ── */
    if (favBtn) {
        favBtn.addEventListener('click', function () {
            if (!loggedIn) { requireLogin(); return; }
            const oldFav    = state.favorited;
            state.favorited = !oldFav;
            renderFav(state.favorited);
            favBtn.classList.add('is-loading');
            callApi('favorite', null)
                .then(function (res) {
                    if (res.success) {
                        state.favorited = res.entry.favorited;
                        renderFav(state.favorited);
                        showPointToast(res.points_earned);
                    }
                })
                .catch(function () { state.favorited = oldFav; renderFav(oldFav); })
                .finally(function () { favBtn.classList.remove('is-loading'); });
        });
    }

    /* ── 全破按鈕 ── */
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (!loggedIn) { requireLogin(); return; }
            if (state.fullcleared) return;
            clearBtn.classList.add('is-loading');
            callApi('fullclear', null)
                .then(function (res) {
                    if (res.success) {
                        state.fullcleared  = res.entry.fullcleared;
                        state.progress     = res.entry.progress ?? totalEp;
                        state.status       = res.entry.status;
                        renderClear(state.fullcleared);
                        renderProgress(state.progress);
                        renderStatus(state.status);
                        if (state.fullcleared && !state._prevCleared) launchConfetti();
                        state._prevCleared = state.fullcleared;
                        showPointToast(res.points_earned);
                    }
                })
                .finally(function () { clearBtn.classList.remove('is-loading'); });
        });
    }

    /* ── 分享按鈕 ── */
    if (shareBtn && shareModal) {
        shareBtn.addEventListener('click', function () {
            const title = shareBtn.dataset.title || document.title;
            const url   = shareBtn.dataset.url   || location.href;
            if (navigator.share) {
                navigator.share({ title: title, url: url }).catch(function () {});
                return;
            }
            shareModal.classList.add('is-open');
        });
        if (shareClose) shareClose.addEventListener('click', function () { shareModal.classList.remove('is-open'); });
        shareModal.addEventListener('click', function (e) { if (e.target === shareModal) shareModal.classList.remove('is-open'); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') shareModal.classList.remove('is-open'); });
    }

    /* ── 複製連結 ── */
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            const url = cfg.permalink || location.href;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    copyBtn.textContent = '✅ 已複製！';
                    setTimeout(function () { copyBtn.textContent = '📋 複製連結'; }, 2000);
                });
            } else {
                const ta = document.createElement('textarea');
                ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
                copyBtn.textContent = '✅ 已複製！';
                setTimeout(function () { copyBtn.textContent = '📋 複製連結'; }, 2000);
            }
        });
    }

    /* ── 閱讀積分（3 秒後）── */
    if (loggedIn && cfg.ajaxUrl && cfg.ajaxNonce) {
        setTimeout(function () {
            fetch(cfg.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'smacg_read_article', nonce: cfg.ajaxNonce, post_id: postId }),
            }).catch(function () {});
        }, 3000);
    }

}); /* END DOMContentLoaded */


/* ============================================================
   二、評分滑桿 + 送出（獨立 IIFE，DOMContentLoaded 後執行）
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {

    const ratingForm = document.getElementById('wacg-rating-form');
    if (!ratingForm) return; /* 未登入時表單不存在，直接跳出 */

    const cfg      = window.SmacgConfig    || {};
    const postId   = parseInt(cfg.postId, 10) || 0;
    const ajaxUrl  = cfg.ajaxUrl           || '/wp-admin/admin-ajax.php';
    const nonce    = cfg.ajaxNonce         || '';
    const submitBtn = document.getElementById('wacg-submit-btn');

    /* ── Slider 初始化（支援動態載入評分覆蓋 LiteSpeed 快取） ── */
    const sliderKeys = ['story', 'music', 'animation', 'voice'];

    /* 套用評分到滑桿 UI（可重複呼叫，給 fetch 完成後使用） */
    function applyRatingToSliders(rating) {
        rating = rating || {};
        sliderKeys.forEach(function (key) {
            const slider = document.getElementById('slider-' + key);
            const valEl  = document.getElementById('slider-' + key + '-val');
            if (!slider) return;
            const v = (rating[key] !== undefined) ? parseFloat(rating[key]) : parseFloat(slider.value);
            if (!isNaN(v)) {
                slider.value = v;
                if (valEl) valEl.textContent = v.toFixed(1);
            }
        });
    }

    /* 初次渲染：用 PHP 注入的預設值（通常是 5） */
    applyRatingToSliders(window.SmacgUserRating || {});

    /* 綁定拖動事件（只綁一次） */
    sliderKeys.forEach(function (key) {
        const slider = document.getElementById('slider-' + key);
        const valEl  = document.getElementById('slider-' + key + '-val');
        if (!slider) return;
        slider.addEventListener('input', function () {
            if (valEl) valEl.textContent = parseFloat(this.value).toFixed(1);
        });
    });

    /* 監聽動態載入的真實評分 → 覆蓋滑桿 */
    document.addEventListener('smacg:userRatingReady', function (e) {
        applyRatingToSliders(e.detail || window.SmacgUserRating || {});
    });


    /* ── 送出 ── */
    ratingForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!postId) return;

        const story     = parseFloat(document.getElementById('slider-story')?.value     || 5);
        const music     = parseFloat(document.getElementById('slider-music')?.value     || 5);
        const animation = parseFloat(document.getElementById('slider-animation')?.value || 5);
        const voice     = parseFloat(document.getElementById('slider-voice')?.value     || 5);
        const avg       = (story + music + animation + voice) / 4;

        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '送出中…'; }

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:    'smacg_submit_rating_detail',
                nonce:     nonce,
                post_id:   postId,
                story:     story.toFixed(1),
                music:     music.toFixed(1),
                animation: animation.toFixed(1),
                voice:     voice.toFixed(1),
                score:     avg.toFixed(2),
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                if (submitBtn) {
                    submitBtn.textContent      = '✅ 評分完成！';
                    submitBtn.style.background = 'var(--asd-score-al, #02a9ff)';
                }
                const d = data.data || {};
                /* 更新頁面顯示 */
                if (d.avg) {
                    document.querySelectorAll('.wacg-score-main, .wacg-hero-score').forEach(function (el) {
                        el.textContent = parseFloat(d.avg).toFixed(1);
                    });
                }
                const catMap = { story: '.wacg-cat-story', music: '.wacg-cat-music', animation: '.wacg-cat-animation', voice: '.wacg-cat-voice' };
                Object.entries(catMap).forEach(function ([key, sel]) {
                    if (d[key]) { const el = document.querySelector(sel); if (el) el.textContent = parseFloat(d[key]).toFixed(1); }
                });
                if (d.count) { const el = document.querySelector('.wacg-vote-count'); if (el) el.textContent = d.count + ' 人評分'; }
            } else {
                if (submitBtn) {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = '❌ ' + (data.data?.msg || '送出失敗，請重試');
                    setTimeout(function () { submitBtn.textContent = '送出評分'; submitBtn.style.background = ''; }, 3000);
                }
            }
        })
        .catch(function () {
            if (submitBtn) {
                submitBtn.disabled    = false;
                submitBtn.textContent = '網路錯誤，請重試';
                setTimeout(function () { submitBtn.textContent = '送出評分'; }, 3000);
            }
        });
    });

}); /* END 評分區塊 */
