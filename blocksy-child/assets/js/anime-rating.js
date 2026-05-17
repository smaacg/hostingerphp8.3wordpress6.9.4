/* ============================================================
   微笑動漫 — 動漫評分滑桿 + 送出
   路徑：/blocksy-child/assets/js/anime-rating.js
   版本：1.1 — 2026-05-17
   說明：追蹤條請看 anime-status.js，這裡不要重複綁 .smacg-track-bar

   Changelog:
     1.1 — 配合 single-anime.php v14.1
           - [新增] 監聽 smacg:userRatingReady 事件，把後端取回的使用者評分
                   套到 4 個滑桿與旁邊的數值顯示（解決登入者滑桿永遠停在
                   預設 5.0 的問題；因 LiteSpeed 全頁快取無法用 PHP 注入）
           - [新增] data-action="smacg-login-prompt" 按鈕委派監聽
                   （取代模板原本的 inline onclick，符合 CSP 規範）
           - [改進] 滑桿初始化抽成函式，方便事件覆寫時重用
     1.0 — 從 anime-status.js 拆出，只負責評分區塊
   ============================================================ */
'use strict';

document.addEventListener('DOMContentLoaded', function () {

    /* ── 公用：把單一滑桿設成指定值，並同步右側數字 ── */
    function setSliderValue(sliderId, value) {
        const slider = document.getElementById(sliderId);
        if (!slider) return;
        const num = parseFloat(value);
        if (!isFinite(num)) return;

        // clamp 到滑桿 min/max
        const min = parseFloat(slider.min) || 1;
        const max = parseFloat(slider.max) || 10;
        const clamped = Math.min(max, Math.max(min, num));

        slider.value = clamped;
        const valEl = document.getElementById(sliderId + '-val');
        if (valEl) valEl.textContent = clamped.toFixed(1);
    }

    /* ── Slider 即時顯示（input 事件） ── */
    document.querySelectorAll('.wacg-slider').forEach(function (slider) {
        const valEl = document.getElementById(slider.id + '-val');
        if (valEl) valEl.textContent = parseFloat(slider.value).toFixed(1);
        slider.addEventListener('input', function () {
            if (valEl) valEl.textContent = parseFloat(this.value).toFixed(1);
        });
    });

    /* ── 初始套用 window.SmacgUserRating（若 fetch 已在本 JS 載入前完成） ── */
    if (window.SmacgUserRating && typeof window.SmacgUserRating === 'object') {
        applyUserRating(window.SmacgUserRating);
    }

    /* ── 監聽模板 fetch 完成事件（v14.1 新增） ── */
    document.addEventListener('smacg:userRatingReady', function (e) {
        if (e && e.detail) applyUserRating(e.detail);
    });

    function applyUserRating(r) {
        if (!r) return;
        setSliderValue('slider-story',     r.story);
        setSliderValue('slider-music',     r.music);
        setSliderValue('slider-animation', r.animation);
        setSliderValue('slider-voice',     r.voice);
    }

    /* ── 登入提示按鈕（v14.1 模板新增，取代 inline onclick） ── */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action="smacg-login-prompt"]');
        if (!btn) return;
        e.preventDefault();

        if (typeof window.smacgOpenLoginModal === 'function') {
            window.smacgOpenLoginModal();
            return;
        }

        // Fallback：跳到 wp-login，帶 redirect 回當前頁
        const cfg = window.SmacgConfig || {};
        const loginUrl = cfg.loginUrl
            || ('/wp-login.php?redirect_to=' + encodeURIComponent(window.location.href));
        window.location.href = loginUrl;
    });

    /* ── 送出評分 ── */
    const ratingForm = document.getElementById('wacg-rating-form');
    const submitBtn  = document.getElementById('wacg-submit-btn');
    if (!ratingForm) return;

    ratingForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const cfg    = window.SmacgConfig || {};
        const postId = parseInt(cfg.postId, 10) || 0;
        const apiUrl = cfg.apiUrl || '/wp-json/weixiaoacg/v1/';
        const nonce  = cfg.nonce  || '';

        if (!postId) {
            alert('找不到動畫 ID（SmacgConfig 未注入）');
            console.error('SmacgConfig:', cfg);
            return;
        }
        if (!cfg.loggedIn) {
            alert('請先登入才能評分');
            return;
        }

        const story     = parseFloat(document.getElementById('slider-story')?.value     || 5);
        const music     = parseFloat(document.getElementById('slider-music')?.value     || 5);
        const animation = parseFloat(document.getElementById('slider-animation')?.value || 5);
        const voice     = parseFloat(document.getElementById('slider-voice')?.value     || 5);

        if (submitBtn) {
            submitBtn.disabled    = true;
            submitBtn.textContent = '送出中…';
        }

        fetch(apiUrl + 'ratings/' + postId, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   nonce,
            },
            body: JSON.stringify({
                score_story:     story,
                score_music:     music,
                score_animation: animation,
                score_voice:     voice,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            console.log('評分回應:', data);
            if (data.success) {
                if (submitBtn) {
                    submitBtn.textContent      = '✅ ' + (data.message || '評分完成！');
                    submitBtn.style.background = 'var(--asd-score-al, #02a9ff)';
                }
                const stats = data.stats || {};
                if (stats.score) {
                    document.querySelectorAll('.wacg-score-main, .wacg-hero-score').forEach(function (el) {
                        el.textContent = parseFloat(stats.score).toFixed(1);
                    });
                }
                const map = {
                    avg_story:     '.wacg-cat-story',
                    avg_music:     '.wacg-cat-music',
                    avg_animation: '.wacg-cat-animation',
                    avg_voice:     '.wacg-cat-voice',
                };
                Object.keys(map).forEach(function (k) {
                    if (stats[k] != null) {
                        const el = document.querySelector(map[k]);
                        if (el) el.textContent = parseFloat(stats[k]).toFixed(1);
                    }
                });
                if (stats.vote_count) {
                    const el = document.querySelector('.wacg-vote-count');
                    if (el) el.textContent = stats.vote_count;
                }
            } else {
                if (submitBtn) {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = '送出評分';
                }
                alert((data.message || '評分失敗') + (data.code ? '\n錯誤代碼：' + data.code : ''));
            }
        })
        .catch(function (err) {
            console.error('評分送出失敗:', err);
            if (submitBtn) {
                submitBtn.disabled    = false;
                submitBtn.textContent = '送出評分';
            }
            alert('評分送出失敗,請稍後再試');
        });
    });
});
