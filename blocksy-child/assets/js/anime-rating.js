/* ============================================================
   微笑動漫 — 動漫評分滑桿 + 送出
   路徑：/blocksy-child/assets/js/anime-rating.js
   版本：1.0 — 從 anime-status.js 拆出，只負責評分區塊
   說明：追蹤條請看 anime-status.js，這裡不要重複綁 .smacg-track-bar
   ============================================================ */
'use strict';

document.addEventListener('DOMContentLoaded', function () {

    /* ── Slider 即時顯示 ── */
    document.querySelectorAll('.wacg-slider').forEach(function (slider) {
        const valEl = document.getElementById(slider.id + '-val');
        if (valEl) valEl.textContent = parseFloat(slider.value).toFixed(1);
        slider.addEventListener('input', function () {
            if (valEl) valEl.textContent = parseFloat(this.value).toFixed(1);
        });
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
