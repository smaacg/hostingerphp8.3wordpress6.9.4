<?php
/**
 * 微笑動漫 Child Theme — footer.php
 *
 * v1.2.0 (2026-05-17)
 *  - Footer brand logo 由「微」字方塊改為與 header 一致的 PNG logo
 *  - Schema.org Organization.logo 同步改為 PNG URL
 *
 * v1.1.0 (2026-05-15)
 *  - 站內欄目新增「等級指南」連結 → /level-guide/
 */

/* ── Logo URL（與 header.php 一致）── */
$brand_logo_url = 'https://darkcyan-alpaca-757238.hostingersite.com/wp-content/uploads/2026/05/DHBdKsLa.png';

/* ── 社群連結 ── */
$social = [
    'discord'        => [ 'https://discord.com/invite/yw73RBZgss',               'fa-brands fa-discord',   'Discord'   ],
    'facebook_group' => [ 'https://www.facebook.com/groups/148714851855091/',     'fa-brands fa-facebook',  'FB 社團'   ],
    'facebook_page'  => [ 'https://www.facebook.com/weixiaoacg/?locale=zh_CN',       'fa-brands fa-facebook',  'FB 粉專'   ],
    'line'      => [ 'https://line.me/ti/g2/E5swfdtaIoC4UWHLpm4u-bUr_FTjVATPwOxX9A?utm_source=invitation&utm_medium=link_copy&utm_campaign=default',  'fa-brands fa-line', 'LINE社團' ],
    'plurk'          => [ 'https://www.plurk.com/weixiaoacg',                        'fa-solid fa-p',          'Plurk'     ],
    'vocus'          => [ 'https://vocus.cc/user/69c92cdcfd89780001abdd8e',       'fa-solid fa-pen-nib',    'Vocus'     ],
    'tiktok'         => [ '',                                                      'fa-brands fa-tiktok',    '抖音'      ],
    'youtube'        => [ '',                                                      'fa-brands fa-youtube',   'YouTube'   ],
    'twitter'        => [ '',                                                      'fa-brands fa-x-twitter', 'X'         ],
    'instagram'      => [ '',                                                      'fa-brands fa-instagram', 'Instagram' ],
];

$site_name = get_bloginfo('name') ?: '微笑動漫';

/* ── Schema.org sameAs（只收錄有 URL 的平台）── */
$schema_same_as = [];
foreach ( $social as [ $url ] ) {
    if ( $url !== '' ) {
        $schema_same_as[] = $url;
    }
}
?>

<footer class="site-footer glass-mid" role="contentinfo">
  <div class="container">
    <div class="footer-top">

      <!-- ── Brand ── -->
      <div class="footer-brand">
        <a href="<?php echo esc_url( home_url('/') ); ?>"
           class="site-logo footer-logo"
           aria-label="<?php echo esc_attr( $site_name ); ?> 首頁">
          <span class="logo-icon-box" aria-hidden="true">
            <img src="<?php echo esc_url( $brand_logo_url ); ?>"
                 alt="<?php echo esc_attr( $site_name ); ?>"
                 style="height:38px;width:auto;vertical-align:middle;display:inline-block;"
                 loading="lazy"
                 decoding="async" />
          </span>
          <span class="logo-text">
            <?php echo esc_html( $site_name ); ?><span class="logo-plus" aria-hidden="true">+</span>
          </span>
        </a>

        <p class="footer-tagline">
          <?php echo esc_html( get_bloginfo('description') ?: '高質感 ACG 情報中心，動漫・音樂・COSPLAY・百科，讓你不錯過任何精彩。' ); ?>
        </p>

        <!-- 社群圖示 -->
        <nav class="social-links" aria-label="社群媒體連結">
          <?php foreach ( $social as $key => [ $url, $icon, $label ] ) : ?>
            <?php if ( $url !== '' ) : ?>
              <a href="<?php echo esc_url( $url ); ?>"
                 class="footer-social-btn"
                 aria-label="<?php echo esc_attr( $site_name . ' ' . $label ); ?>"
                 title="<?php echo esc_attr( $site_name . ' ' . $label ); ?>"
                 target="_blank" rel="noopener noreferrer">
                <i class="<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
              </a>
            <?php else : ?>
              <span class="footer-social-btn footer-social-placeholder"
                    title="<?php echo esc_attr( $label ); ?> 即將上線"
                    aria-label="<?php echo esc_attr( $label ); ?>（即將上線）">
                <i class="<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
              </span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      </div>

      <!-- ── 站內欄目 ── -->
      <nav class="footer-nav-group" aria-label="站內欄目">
        <h5>站內欄目</h5>
        <?php
        $col1 = [
            '首頁'      => home_url('/'),
            '新番導覽'  => home_url('/season/'),
            '最新新聞'  => home_url('/news/'),
            '等級指南'  => home_url('/level-guide/'),
            '會員排行榜' => home_url('/ranking-users/'),
        ];
        foreach ( $col1 as $label => $url ) : ?>
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>

      <!-- ── 資料庫 ── -->
      <nav class="footer-nav-group" aria-label="資料庫">
        <h5>資料庫</h5>
        <?php
        $col2 = [
            '動畫百科' => home_url('/anime/'),
        ];
        foreach ( $col2 as $label => $url ) : ?>
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>

      <!-- ── 社群 ── -->
      <nav class="footer-nav-group" aria-label="社群">
        <h5>社群</h5>
        <?php
        $col3 = [
            '討論區'       => home_url('/forum/'),
            '季番投票'     => home_url('/vote/'),
            '投稿須知'     => home_url('/submit/'),
        ];
        foreach ( $col3 as $label => $url ) : ?>
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>

      <!-- ── 關於 ── -->
      <nav class="footer-nav-group" aria-label="關於">
        <h5>關於</h5>
        <?php
        $col4 = [
            '關於微笑動漫' => home_url('/about/'),
            '加入我們'     => home_url('/join/'),
            '聯絡／合作'   => home_url('/contact/'),
            '免責聲明'     => home_url('/disclaimer/'),
            '隱私政策'     => home_url('/privacy/'),
        ];
        foreach ( $col4 as $label => $url ) : ?>
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>

    </div><!-- .footer-top -->

    <div class="footer-divider" role="separator"></div>

    <!-- ── Footer Bottom ── -->
    <div class="footer-bottom">
      <p class="footer-copy">
        © <?php echo esc_html( date('Y') ); ?> 微笑動漫 WeixiaoACG．All rights reserved．
        資料來源包含
        <a href="https://bgm.tv" target="_blank" rel="noopener noreferrer">Bangumi</a>、
        <a href="https://anilist.co" target="_blank" rel="noopener noreferrer">AniList</a>、
        <a href="https://myanimelist.net" target="_blank" rel="noopener noreferrer">MyAnimeList</a>、
        <a href="https://jikan.moe" target="_blank" rel="noopener noreferrer">Jikan</a>、
        <a href="https://www.wikipedia.org" target="_blank" rel="noopener noreferrer">Wikipedia</a>，
        OP／ED 來源
        <a href="https://animethemes.moe" target="_blank" rel="noopener noreferrer">AnimeThemes.moe</a>。
      </p>

  </div>
</footer>

<!-- Toast 通知容器 -->
<div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="false"></div>

<!-- ── Back to Top ── -->
<button id="back-to-top" aria-label="回到頂端" title="回到頂端">
  <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
</button>

<!-- ── Cookie Banner ── -->
<div id="cookie-banner" role="dialog" aria-live="polite" aria-label="Cookie 使用通知" hidden>
  <div class="cookie-inner">
    <div class="cookie-text">
      <strong>🍪 Cookie 使用通知</strong>
      <p>本網站使用 Cookie 以提升瀏覽體驗及分析流量。繼續使用即代表您同意我們的
        <a href="<?php echo esc_url( home_url('/privacy/') ); ?>">隱私政策</a>。
      </p>
    </div>
    <button class="cookie-btn" id="cookie-accept">我知道了</button>
  </div>
</div>

<!-- ── Schema.org Organization ── -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "微笑動漫",
  "alternateName": "weixiaoacg",
  "url": "<?php echo esc_js( home_url('/') ); ?>",
  "logo": "<?php echo esc_js( $brand_logo_url ); ?>",
  "sameAs": <?php echo wp_json_encode( $schema_same_as, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>,
  "contactPoint": {
    "@type": "ContactPoint",
    "email": "weixiaoacg.com@gmail.com",
    "contactType": "customer support",
    "availableLanguage": ["Chinese (Traditional)", "Chinese (Simplified)"]
  }
}
</script>

<!-- ── JS：Back to Top ── -->
<script>
(function () {
  'use strict';

  /* ── Back to Top ── */
  var btn = document.getElementById('back-to-top');
  if ( btn ) {
    var scrollHandler = function () {
      if ( window.scrollY > 300 ) {
        btn.classList.add('visible');
      } else {
        btn.classList.remove('visible');
      }
    };
    window.addEventListener('scroll', scrollHandler, { passive: true });
    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

})();
</script>

<?php wp_footer(); ?>
</body>
</html>
