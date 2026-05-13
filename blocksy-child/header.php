<?php
/**
 * 微笑動漫 Child Theme — header.php
 * v1.5.0 (2026-05-13)
 *
 * 變更紀錄：
 * - v1.5.0：
 *   1. 「個人頁面」/「設定」連結改用 smacg_get_member_center_url()
 *      動態解析掛 page-member.php 模板的頁面（例如 /mc/），
 *      完全繞開 Ultimate Member 的 /user/{username}/ 路徑，
 *      解決 "We are sorry. We cannot find any users..." 錯誤
 *   2. 移除 um_user_profile_url() 與 /user/{user_login}/ fallback
 *   3. helper 未載入時 fallback 到首頁，避免 fatal
 *
 * - v1.4.0：
 *   1. 頭像下拉選單移除 /account/、/account/privacy/ 兩個項目
 *   2. 新增「設定」項目，使用 #settings hash 直接切到會員中心設定 tab
 *      （搭配 member.js v2.0.3 的 URL/hash 解析）
 *   3. 移除 modal 寫死的 dev.weixiaoacg.com/account/ form action
 *      改用 home_url() 動態取得目前站點 URL
 *   4. 加上 modal 內 esc_js() 安全處理
 *
 * - v1.3.0：
 *   - login modal 加 is_user_logged_in() 判斷
 *   - smacgOpenLoginModal 定義在 modal 外，已登入用戶不報錯
 *   - 搜尋送出統一用 /?s= 格式
 *   - 註冊改為跳轉方案，避免 nonce 衝突
 *   - 登入後顯示頭像下拉選單，未登入只顯示登入／註冊按鈕
 *
 * @package weixiaoacg
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- 引入 Google Fonts：主標題用快樂體，副標題用思源黑體 -->
 <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@500;900&display=swap" rel="stylesheet">
  <?php wp_head(); ?>
  
<!-- ══════════════════════════════════════════
     登入 Modal（訪客才輸出 HTML）
══════════════════════════════════════════ -->
<?php if ( ! is_user_logged_in() ) : ?>
<div id="login-modal" class="lm-overlay" role="dialog" aria-modal="true" aria-label="登入">
  <div class="lm-box">

    <button class="lm-close" id="lm-close" aria-label="關閉">
      <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="lm-logo">
      <span class="logo-icon-box" aria-hidden="true"><img src="https://darkcyan-alpaca-757238.hostingersite.com/wp-content/uploads/2026/05/DHBdKsLa-scaled-e1778709031191.jpg" alt="weixiaoacg" style="height:38px;width:auto;vertical-align:middle;display:inline-block;" /></span>
      <span class="logo-text">微笑動漫<span class="logo-plus">+</span></span>
    </div>

    <p class="lm-subtitle">登入以解鎖完整功能</p>

    <div class="lm-tabs">
      <button class="lm-tab active" data-tab="login">登入</button>
      <button class="lm-tab" data-tab="register">註冊</button>
    </div>

    <!-- 登入表單 -->
    <div class="lm-panel" id="lm-panel-login">
      <form id="lm-login-form" class="lm-custom-form">
        <div class="lm-field">
          <label for="lm-username">使用者名稱或電子郵件</label>
          <input type="text" id="lm-username" name="log" autocomplete="username" required />
        </div>
        <div class="lm-field">
          <label for="lm-password">密碼</label>
          <input type="password" id="lm-password" name="pwd" autocomplete="current-password" required />
        </div>
        <div class="lm-field lm-remember">
          <label><input type="checkbox" name="rememberme" value="1" /> 保持登入狀態</label>
        </div>
        <div id="lm-login-error" class="lm-error" style="display:none"></div>
        <button type="submit" class="um-button">登入</button>
        <div class="lm-links">
          <button type="button" class="lm-link-btn lm-switch-register">還沒有帳號？註冊</button>
          <a href="<?php echo esc_url(home_url('/password-reset/')); ?>">忘記密碼？</a>
        </div>
      </form>

      <?php if ( class_exists( 'NextendSocialLogin', false ) ) : ?>
      <div class="lm-social-divider">
        <span>或使用以下方式登入</span>
      </div>
      <div class="lm-social-buttons">
        <?php echo NextendSocialLogin::renderButtonsWithContainer(); ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 註冊表單 -->
    <div class="lm-panel" id="lm-panel-register" hidden>
      <form id="lm-register-form" class="lm-custom-form">
        <div class="lm-field">
          <label for="lm-reg-username">使用者名稱</label>
          <input type="text" id="lm-reg-username" name="user_login" autocomplete="username" required />
        </div>
        <div class="lm-field">
          <label for="lm-reg-email">電子郵件</label>
          <input type="email" id="lm-reg-email" name="user_email" autocomplete="email" required />
        </div>
        <div class="lm-field">
          <label for="lm-reg-password">密碼</label>
          <input type="password" id="lm-reg-password" name="user_password" autocomplete="new-password" required />
        </div>
        <div id="lm-register-error" class="lm-error" style="display:none"></div>
        <div id="lm-register-success" class="lm-success" style="display:none"></div>
        <button type="submit" class="um-button">註冊</button>
        <p class="lm-switch-login-wrap">已有帳號？<button type="button" class="lm-switch-login lm-link-btn">登入</button></p>
      </form>

      <p class="lm-terms-hint">
        註冊即代表你同意
        <a href="<?php echo esc_url( home_url('/terms/') ); ?>" target="_blank">使用條款</a>
        及
        <a href="<?php echo esc_url( home_url('/privacy/') ); ?>" target="_blank">隱私政策</a>
      </p>

      <?php if ( class_exists( 'NextendSocialLogin', false ) ) : ?>
      <div class="lm-social-divider">
        <span>或使用以下方式註冊</span>
      </div>
      <div class="lm-social-buttons">
        <?php echo NextendSocialLogin::renderButtonsWithContainer(); ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php endif; ?>


<!-- ══════════════════════════════════════════
     Header
══════════════════════════════════════════ -->
<header class="site-header glass-mid" id="site-header">

  <div class="header-top container">

    <!-- Logo -->
    <a href="<?php echo esc_url( home_url('/') ); ?>" class="site-logo" aria-label="微笑動漫首頁">
      <span class="logo-icon-box" aria-hidden="true"><img src="https://darkcyan-alpaca-757238.hostingersite.com/wp-content/uploads/2026/05/DHBdKsLa.png" alt="weixiaoacg" style="height:38px;width:auto;vertical-align:middle;display:inline-block;" /></span>
      <div class="logo-text-wrap">
        <span class="logo-text">微笑動漫<span class="logo-plus"></span></span>
        <span class="logo-tagline">&nbsp&nbsp動漫的便利商店</span>
      </div>
    </a>

    <!-- 搜尋 -->
    <div class="header-search-wrap">
      <div class="header-search-box" id="header-search-box">
        <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
        <input type="text"
               id="header-search-input"
               class="header-search-input"
               placeholder="搜尋…"
               autocomplete="off"
               aria-label="搜尋" />
        <button class="header-search-btn" id="header-search-submit" aria-label="送出搜尋">搜尋</button>
      </div>
      <div class="header-search-dropdown" id="header-search-dropdown" aria-live="polite"></div>
    </div>

    <!-- 右側 Actions -->
    <div class="header-actions">

      <?php if ( is_user_logged_in() ) :
        $user = wp_get_current_user();

        /**
         * v1.5.0：個人頁面 / 設定 一律指向自家會員中心（page-member.php 模板）
         * 完全繞開 Ultimate Member 的 /user/{username}/ 路徑。
         * helper 未載入時 fallback 到首頁，避免 fatal。
         */
        $profile_url = function_exists( 'smacg_get_member_center_url' )
                       ? smacg_get_member_center_url()
                       : home_url( '/' );
      ?>

        <!-- 已登入：頭像下拉選單（v1.4.0 簡化為三項） -->
        <div class="header-user-dropdown">
          <button class="header-avatar-btn" id="header-avatar-btn" aria-expanded="false" aria-haspopup="true">
            <?php echo get_avatar( $user->ID, 32, '', '', ['class' => 'header-avatar'] ); ?>
            <span class="header-username"><?php echo esc_html( $user->display_name ); ?></span>
            <i class="fa-solid fa-chevron-down header-avatar-arrow" aria-hidden="true"></i>
          </button>

          <div class="header-user-menu" id="header-user-menu" role="menu">
            <a href="<?php echo esc_url( $profile_url ); ?>" role="menuitem">
              <i class="fa-solid fa-user"></i> 個人頁面
            </a>
            <a href="<?php echo esc_url( $profile_url . '#settings' ); ?>" role="menuitem">
              <i class="fa-solid fa-gear"></i> 設定
            </a>
            <div class="header-user-menu-divider" role="separator"></div>
            <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>"
               class="logout-link" role="menuitem">
              <i class="fa-solid fa-right-from-bracket"></i> 登出
            </a>
          </div>
        </div>

      <?php else : ?>

        <!-- 未登入：登入／註冊按鈕 -->
        <button type="button"
                class="btn btn-ghost btn-sm header-login-btn"
                id="open-login-modal">
          <i class="fa-solid fa-right-to-bracket"></i> 登入
        </button>
        <button type="button"
                class="btn btn-primary btn-sm header-reg-btn"
                id="open-register-modal">註冊</button>

      <?php endif; ?>

      <!-- 手機漢堡選單 -->
      <button class="btn-icon btn-ghost mobile-menu-btn"
              id="mobile-menu-toggle"
              aria-label="開關選單"
              aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
      </button>

    </div>
  </div>

  <!-- 導覽列 -->
  <div class="header-nav-bar">
    <div class="container">
      <nav class="primary-nav" id="primary-nav" aria-label="主選單">
        <?php
        if ( has_nav_menu( 'primary-menu' ) ) {
            wp_nav_menu( [
                'theme_location' => 'primary-menu',
                'container'      => false,
                'fallback_cb'    => false,
                'items_wrap'     => '%3$s',
                'walker'         => new weixiaoacg_Nav_Walker(),
            ] );
        } else {
            $nav_links = [
                [ 'url' => home_url('/'),         'label' => '首頁',    'icon' => 'fa-solid fa-house',                'check' => is_front_page() ],
                [ 'url' => home_url('/season/'),  'label' => '新番',    'icon' => 'fa-solid fa-calendar-days',        'check' => is_page('season') ],
                [ 'url' => home_url('/news/'),    'label' => '新聞',    'icon' => 'fa-solid fa-newspaper',            'check' => is_page('news') ],
                [ 'url' => home_url('/anime/'),   'label' => '動漫',    'icon' => 'fa-solid fa-film',                 'check' => ( is_post_type_archive('anime') || is_singular('anime') ) ],
                [ 'url' => home_url('/ranking/'), 'label' => '排行',    'icon' => 'fa-solid fa-trophy',              'check' => is_page('ranking') ],
                [ 'url' => home_url('/music/'),   'label' => '音樂',    'icon' => 'fa-solid fa-music',               'check' => is_page('music') ],
                [ 'url' => home_url('/cosplay/'), 'label' => 'COSPLAY', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'check' => is_page('cosplay') ],
                [ 'url' => home_url('/about/'),   'label' => '關於',    'icon' => 'fa-solid fa-circle-info',         'check' => is_page('about') ],
            ];
            foreach ( $nav_links as $link ) {
                $active = $link['check'] ? ' active' : '';
                echo '<div class="nav-item">';
                echo '<a href="' . esc_url( $link['url'] ) . '" class="nav-link' . $active . '">';
                echo '<i class="' . esc_attr( $link['icon'] ) . '" aria-hidden="true"></i> ';
                echo esc_html( $link['label'] );
                echo '</a>';
                echo '</div>';
            }
        }
        ?>
      </nav>
    </div>
  </div>

</header>

<script>
(function () {

  /* ══════════════════════════════════════════
     搜尋
  ══════════════════════════════════════════ */
  const input     = document.getElementById('header-search-input');
  const dropdown  = document.getElementById('header-search-dropdown');
  const submitBtn = document.getElementById('header-search-submit');
  let timer;

  if (input) {
    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = this.value.trim();
      if (q.length < 2) {
        if (dropdown) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); }
        return;
      }
      timer = setTimeout(() => fetchResults(q), 300);
    });
    input.addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });
  }

  if (submitBtn) submitBtn.addEventListener('click', doSearch);

  function doSearch() {
    const q = input ? input.value.trim() : '';
    if (!q) return;
    window.location.href = '<?php echo esc_js( home_url('/?s=') ); ?>' + encodeURIComponent(q);
  }

  function fetchResults(q) {
    if (typeof weixiaoacg_ajax === 'undefined' || !dropdown) return;
    fetch(weixiaoacg_ajax.ajax_url, {
      method : 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body   : new URLSearchParams({
        action: 'weixiaoacg_search',
        nonce : weixiaoacg_ajax.nonce,
        query : q
      }),
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.data || !data.data.length) {
        dropdown.innerHTML = '<div class="search-no-result">找不到相關結果</div>';
        dropdown.classList.add('open');
        return;
      }
      dropdown.innerHTML = data.data.map(item => `
        <a href="${item.url}" class="search-result-item">
          ${item.thumb
            ? `<img src="${item.thumb}" alt="" class="search-result-thumb" loading="lazy">`
            : `<span class="search-result-thumb-ph"><i class="fa-solid fa-film"></i></span>`}
          <span class="search-result-info">
            <span class="search-result-title">${item.title}</span>
            <span class="search-result-type">${item.type === 'anime' ? '動漫' : '新聞'}</span>
          </span>
        </a>`).join('');
      dropdown.classList.add('open');
    })
    .catch(() => {});
  }

  document.addEventListener('click', e => {
    if (!dropdown) return;
    if (!e.target.closest('#header-search-box') && !e.target.closest('#header-search-dropdown')) {
      dropdown.classList.remove('open');
    }
  });

  /* ══════════════════════════════════════════
     頭像下拉選單（已登入）
  ══════════════════════════════════════════ */
  const avatarBtn = document.getElementById('header-avatar-btn');
  const userMenu  = document.getElementById('header-user-menu');

  if (avatarBtn && userMenu) {
    avatarBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = userMenu.classList.toggle('open');
      avatarBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function () {
      if (userMenu.classList.contains('open')) {
        userMenu.classList.remove('open');
        avatarBtn.setAttribute('aria-expanded', 'false');
      }
    });

    // ESC 關閉
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && userMenu.classList.contains('open')) {
        userMenu.classList.remove('open');
        avatarBtn.setAttribute('aria-expanded', 'false');
        avatarBtn.focus();
      }
    });
  }

  /* ══════════════════════════════════════════
     登入 Modal JS
  ══════════════════════════════════════════ */
  const modal    = document.getElementById('login-modal');
  const openBtn  = document.getElementById('open-login-modal');
  const closeBtn = document.getElementById('lm-close');
  const tabs     = document.querySelectorAll('.lm-tab');
  const panels   = {
    login    : document.getElementById('lm-panel-login'),
    register : document.getElementById('lm-panel-register'),
  };

  function openModal() {
    if (!modal) return;
    document.body.style.overflow = 'hidden';
    setTimeout(() => modal.classList.add('lm-open'), 10);
    // v1.4.0：移除寫死的 dev.weixiaoacg.com/account/，改用當前站點 URL
    modal.querySelectorAll('form.um-form').forEach(f => {
      f.setAttribute('action', '<?php echo esc_js( home_url('/') ); ?>');
    });
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('lm-open');
    document.body.style.overflow = '';
  }

  function switchTab(target) {
    tabs.forEach(t => t.classList.remove('active'));
    document.querySelector(`.lm-tab[data-tab="${target}"]`)?.classList.add('active');
    Object.entries(panels).forEach(([key, el]) => {
      if (el) el.hidden = (key !== target);
    });
  }

  // 全域函式，供其他地方呼叫
  window.smacgOpenLoginModal = function (tab) {
    switchTab(tab || 'login');
    openModal();
  };

  if (openBtn) openBtn.addEventListener('click', () => { switchTab('login'); openModal(); });

  const openRegBtn = document.getElementById('open-register-modal');
  if (openRegBtn) openRegBtn.addEventListener('click', () => { switchTab('register'); openModal(); });

  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  if (modal) {
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal && modal.classList.contains('lm-open')) closeModal();
  });

  tabs.forEach(tab => {
    tab.addEventListener('click', function () { switchTab(this.dataset.tab); });
  });

  // 註冊面板「返回登入」按鈕
  const switchLoginBtn = document.querySelector('.lm-switch-login');
  if (switchLoginBtn) {
    switchLoginBtn.addEventListener('click', () => switchTab('login'));
  }

  // 登入面板「切換到註冊」按鈕
  const switchRegBtn = document.querySelector('.lm-switch-register');
  if (switchRegBtn) {
    switchRegBtn.addEventListener('click', () => switchTab('register'));
  }

  // AJAX 註冊處理
  const registerForm = document.getElementById('lm-register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const errBox = document.getElementById('lm-register-error');
      const sucBox = document.getElementById('lm-register-success');
      const btn = registerForm.querySelector('button[type="submit"]');
      errBox.style.display = 'none';
      sucBox.style.display = 'none';
      btn.disabled = true;
      btn.textContent = '註冊中...';
      const fd = new FormData(registerForm);
      fd.append('action', 'weixiaoacg_ajax_register');
      fd.append('nonce', weixiaoacg_ajax.nonce);
      fetch(weixiaoacg_ajax.ajax_url, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            window.location.href = d.data.redirect || '/';
          } else {
            errBox.textContent = d.data.msg || '註冊失敗，請再試一次';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = '註冊';
          }
        })
        .catch(() => {
          errBox.textContent = '網路錯誤，請再試一次';
          errBox.style.display = 'block';
          btn.disabled = false;
          btn.textContent = '註冊';
        });
    });
  }

  // AJAX 登入處理
  const loginForm = document.getElementById('lm-login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const errBox = document.getElementById('lm-login-error');
      const btn = loginForm.querySelector('button[type="submit"]');
      errBox.style.display = 'none';
      btn.disabled = true;
      btn.textContent = '登入中...';
      const fd = new FormData(loginForm);
      fd.append('action', 'weixiaoacg_ajax_login');
      fd.append('nonce', weixiaoacg_ajax.nonce);
      fetch(weixiaoacg_ajax.ajax_url, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            window.location.href = d.data.redirect || '/';
          } else {
            errBox.textContent = d.data.msg || '登入失敗，請再試一次';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = '登入';
          }
        })
        .catch(() => {
          errBox.textContent = '網路錯誤，請再試一次';
          errBox.style.display = 'block';
          btn.disabled = false;
          btn.textContent = '登入';
        });
    });
  }

})();
</script>
