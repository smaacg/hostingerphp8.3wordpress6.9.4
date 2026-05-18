/*!
 * File: blocksy-child/assets/js/bangumi.js
 * Version: 1.3.0
 * Date: 2026-05-18
 *
 * Changelog
 *  v1.3.0 - Hybrid 模式：點卡片展開（桌面手風琴 / 手機 modal）
 *         - URL hash 同步 #anime-{id}（可分享展開狀態）
 *         - YouTube PV lazy load（點縮圖才嵌入 iframe）
 *         - Esc / 下滑 / 點背景 關閉 modal
 *         - matchMedia 自動切換桌面 vs 手機模式
 *  v1.2.0 - sorting 改用 .bgm-card 與 data-* 屬性
 *  v1.1.0 - 從 page-bangumi.php 抽離
 *  v1.0.0 - 初版（archive 動畫 + 鍵盤導覽）
 */
(function () {
    'use strict';

    /* ====================================================
     * 共用：環境偵測
     * ==================================================== */
    var mqMobile = window.matchMedia('(max-width: 768px)');
    function isMobile() { return mqMobile.matches; }

    /* ====================================================
     * 1. Weekday 切換（保留 v1.1.0 邏輯）
     * ==================================================== */
    function initWeekday() {
        var buttons = document.querySelectorAll('.bgm-day');
        var groups  = document.querySelectorAll('.bgm-group');
        if (!buttons.length || !groups.length) return;

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var day = btn.getAttribute('data-day');
                buttons.forEach(function (b) {
                    var on = (b === btn);
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                groups.forEach(function (g) {
                    if (day === 'all') {
                        g.style.display = '';
                    } else {
                        g.style.display = (g.getAttribute('data-group') === day) ? '' : 'none';
                    }
                });
                // 切換 weekday 時關閉所有展開
                closeAllExpanded();
            });
        });
    }

    /* ====================================================
     * 2. Sort（保留 v1.2.0 邏輯，但用 Map 記原順序）
     * ==================================================== */
    function initSort() {
        var select = document.getElementById('bgm-sort');
        if (!select) return;

        // 記錄每個 group 的原始順序，"default" 可還原
        var originalOrder = new Map();
        document.querySelectorAll('.bgm-grid').forEach(function (grid) {
            originalOrder.set(grid, Array.from(grid.querySelectorAll(':scope > .bgm-card')));
        });

        select.addEventListener('change', function () {
            var mode = select.value;
            document.querySelectorAll('.bgm-grid').forEach(function (grid) {
                var cards;
                if (mode === 'default') {
                    cards = originalOrder.get(grid).slice();
                } else {
                    cards = Array.from(grid.querySelectorAll(':scope > .bgm-card'));
                    var key = mode === 'ep' ? 'ep' : (mode === 'popularity' ? 'pop' : 'score');
                    cards.sort(function (a, b) {
                        var va = parseFloat(a.dataset[key]) || 0;
                        var vb = parseFloat(b.dataset[key]) || 0;
                        return vb - va;
                    });
                }
                // 重排：保留 .bgm-detail（位於每張 .bgm-card 內部）
                cards.forEach(function (c) { grid.appendChild(c); });
            });
            // 排序後關閉所有展開（避免位置錯亂）
            closeAllExpanded();
        });
    }

    /* ====================================================
     * 3. Hybrid 展開：桌面手風琴 / 手機 modal
     * ==================================================== */
    var currentExpandedId = null;
    var sheetEl, sheetBody, lastFocus;

    function getSheet() {
        if (!sheetEl) {
            sheetEl   = document.getElementById('bgm-sheet');
            sheetBody = sheetEl ? sheetEl.querySelector('.bgm-sheet-body') : null;
        }
        return sheetEl;
    }

    function closeAllExpanded() {
        document.querySelectorAll('.bgm-card.is-expanded').forEach(function (card) {
            card.classList.remove('is-expanded');
            var detail = card.querySelector('.bgm-detail');
            if (detail) detail.setAttribute('hidden', '');
            var trigger = card.querySelector('.bgm-card-trigger');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
            // 停止所有 PV iframe
            stopPvInside(card);
        });
        currentExpandedId = null;
    }

    function openDesktopAccordion(card) {
        // 關閉其他展開
        closeAllExpanded();
        card.classList.add('is-expanded');
        var detail = card.querySelector('.bgm-detail');
        if (detail) detail.removeAttribute('hidden');
        var trigger = card.querySelector('.bgm-card-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        currentExpandedId = card.getAttribute('data-anime-id');

        // URL hash 同步（不滾動）
        var newHash = '#anime-' + currentExpandedId;
        if (location.hash !== newHash) {
            history.replaceState(null, '', newHash);
        }

        // 平滑捲動到展開位置
        setTimeout(function () {
            var rect = card.getBoundingClientRect();
            if (rect.top < 80 || rect.top > window.innerHeight - 200) {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 80);

        bindPvLazyLoad(detail);
    }

    function openMobileSheet(card) {
        var sheet = getSheet();
        if (!sheet || !sheetBody) return;

        var detail = card.querySelector('.bgm-detail');
        if (!detail) return;

        // 把 detail 內容複製到 sheet（複製 innerHTML，避免影響原 DOM）
        sheetBody.innerHTML = detail.innerHTML;

        lastFocus = document.activeElement;
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        currentExpandedId = card.getAttribute('data-anime-id');
        var newHash = '#anime-' + currentExpandedId;
        if (location.hash !== newHash) {
            history.replaceState(null, '', newHash);
        }

        // focus 跳到關閉按鈕
        var closeBtn = sheet.querySelector('.bgm-sheet-close');
        if (closeBtn) closeBtn.focus();

        bindPvLazyLoad(sheetBody);
    }

    function closeMobileSheet() {
        var sheet = getSheet();
        if (!sheet) return;
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (sheetBody) {
            stopPvInside(sheetBody);
            // 延遲清空，等動畫結束
            setTimeout(function () {
                if (!sheet.classList.contains('is-open')) sheetBody.innerHTML = '';
            }, 350);
        }
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); lastFocus = null; }
        // 移除 URL hash
        if (location.hash && location.hash.indexOf('#anime-') === 0) {
            history.replaceState(null, '', location.pathname + location.search);
        }
        currentExpandedId = null;
    }

    function toggleCard(card) {
        if (!card) return;
        var id = card.getAttribute('data-anime-id');

        if (isMobile()) {
            openMobileSheet(card);
        } else {
            if (currentExpandedId === id) {
                // 再次點擊收合
                closeAllExpanded();
                if (location.hash && location.hash.indexOf('#anime-') === 0) {
                    history.replaceState(null, '', location.pathname + location.search);
                }
            } else {
                openDesktopAccordion(card);
            }
        }
    }

    function initCardToggle() {
        document.querySelectorAll('.bgm-card-trigger').forEach(function (trigger) {
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                var card = trigger.closest('.bgm-card');
                toggleCard(card);
            });
        });
    }

    /* ====================================================
     * 4. Sheet 控制：點關閉 / 點背景 / Esc / 下滑
     * ==================================================== */
    function initSheet() {
        var sheet = getSheet();
        if (!sheet) return;

        // 點關閉按鈕 / backdrop
        sheet.addEventListener('click', function (e) {
            if (e.target.closest('[data-bgm-close]')) {
                closeMobileSheet();
            }
        });

        // Esc 關閉
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sheet.classList.contains('is-open')) {
                closeMobileSheet();
            }
        });

        // 下滑關閉（簡易 swipe detect）
        var startY = 0, currentY = 0, swiping = false;
        var panel = sheet.querySelector('.bgm-sheet-panel');
        if (!panel) return;

        panel.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 1) return;
            startY = e.touches[0].clientY;
            swiping = (panel.scrollTop === 0);
        }, { passive: true });

        panel.addEventListener('touchmove', function (e) {
            if (!swiping) return;
            currentY = e.touches[0].clientY;
            var diff = currentY - startY;
            if (diff > 0) {
                panel.style.transform = 'translateY(' + diff + 'px)';
            }
        }, { passive: true });

        panel.addEventListener('touchend', function () {
            if (!swiping) return;
            var diff = currentY - startY;
            panel.style.transform = '';
            if (diff > 100) {
                closeMobileSheet();
            }
            swiping = false;
            startY = currentY = 0;
        });
    }

    /* ====================================================
     * 5. YouTube PV lazy load
     * ==================================================== */
    function bindPvLazyLoad(scope) {
        if (!scope) return;
        scope.querySelectorAll('.bgm-pv[data-vid]').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var vid = btn.getAttribute('data-vid');
                if (!vid) return;
                var iframe = document.createElement('iframe');
                iframe.src = 'https://www.youtube-nocookie.com/embed/' + vid + '?autoplay=1&rel=0';
                iframe.title = btn.getAttribute('aria-label') || 'YouTube 預告片';
                iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
                iframe.allowFullscreen = true;
                iframe.loading = 'lazy';
                // 替換 button 內容為 iframe
                btn.innerHTML = '';
                btn.appendChild(iframe);
                btn.classList.add('is-playing');
            });
        });
    }

    function stopPvInside(scope) {
        if (!scope) return;
        scope.querySelectorAll('.bgm-pv.is-playing').forEach(function (btn) {
            var vid = btn.getAttribute('data-vid');
            if (!vid) return;
            // 還原為縮圖
            btn.innerHTML =
                '<img src="https://i.ytimg.com/vi/' + vid + '/hqdefault.jpg" alt="" loading="lazy">' +
                '<span class="bgm-pv-play">▶</span>' +
                '<span class="bgm-pv-title">' + (btn.getAttribute('aria-label') || '') + '</span>';
            btn.classList.remove('is-playing');
            btn.dataset.bound = '';
            bindPvLazyLoad(btn.parentElement);
        });
    }

    /* ====================================================
     * 6. URL hash 進入：開啟對應卡片
     * ==================================================== */
    function openFromHash() {
        var hash = location.hash;
        if (!hash || hash.indexOf('#anime-') !== 0) return;
        var id = hash.slice(7);
        if (!/^\d+$/.test(id)) return;
        var card = document.querySelector('.bgm-card[data-anime-id="' + id + '"]');
        if (card) {
            // 若隸屬被隱藏的 weekday group，先切回「全部」
            var group = card.closest('.bgm-group');
            if (group && group.style.display === 'none') {
                var allBtn = document.querySelector('.bgm-day[data-day="all"]');
                if (allBtn) allBtn.click();
            }
            // 延遲執行，等 DOM 安定
            setTimeout(function () { toggleCard(card); }, 100);
        }
    }

    /* ====================================================
     * 7. matchMedia 切換：桌面 ↔ 手機
     * ==================================================== */
    function handleMqChange() {
        // 若目前有展開，需要切換顯示方式
        if (currentExpandedId) {
            var card = document.querySelector('.bgm-card[data-anime-id="' + currentExpandedId + '"]');
            if (!card) return;
            if (isMobile()) {
                // 桌面手風琴 → 手機 modal
                closeAllExpanded();
                openMobileSheet(card);
            } else {
                // 手機 modal → 桌面手風琴
                closeMobileSheet();
                setTimeout(function () { openDesktopAccordion(card); }, 50);
            }
        }
    }

    /* ====================================================
     * 8. Archive 頁面（保留 v1.0.0 邏輯）
     * ==================================================== */
    function initArchive() {
        var years = document.querySelectorAll('.bgm-arc-year');
        if (!years.length) return;

        // IntersectionObserver 進場動畫
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            years.forEach(function (y) { io.observe(y); });
        } else {
            years.forEach(function (y) { y.classList.add('is-visible'); });
        }

        // 鍵盤導覽（左右方向鍵在非空季方塊之間跳動）
        var seasons = Array.from(document.querySelectorAll('.bgm-arc-season:not(.is-empty)'));
        seasons.forEach(function (s, idx) {
            s.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowRight' && seasons[idx + 1]) {
                    e.preventDefault();
                    seasons[idx + 1].focus();
                } else if (e.key === 'ArrowLeft' && seasons[idx - 1]) {
                    e.preventDefault();
                    seasons[idx - 1].focus();
                }
            });
        });
    }

    /* ====================================================
     * 9. Init
     * ==================================================== */
    function init() {
        initWeekday();
        initSort();
        initCardToggle();
        initSheet();
        initArchive();

        // 進入頁面時若有 hash，自動展開
        openFromHash();

        // 監聽 matchMedia 變化（旋轉手機、調整視窗）
        if (mqMobile.addEventListener) {
            mqMobile.addEventListener('change', handleMqChange);
        } else if (mqMobile.addListener) {
            mqMobile.addListener(handleMqChange); // Safari < 14 fallback
        }

        // 監聽 hash 變化（瀏覽器前進/後退）
        window.addEventListener('hashchange', function () {
            if (!location.hash || location.hash.indexOf('#anime-') !== 0) {
                // hash 被清掉 → 關閉展開
                if (isMobile()) {
                    closeMobileSheet();
                } else {
                    closeAllExpanded();
                }
            } else {
                openFromHash();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
