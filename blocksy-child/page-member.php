<?php
/**
 * Template Name: 會員中心
 * Path: wp-content/themes/blocksy-child/page-member.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'smacg_get_level_title' ) ) {
    function smacg_get_level_title( int $level ): string {
        return match ( true ) {
            $level >= 180 => '🌟 傳說動漫神',
            $level >= 150 => '👑 動漫大師',
            $level >= 120 => '💎 鑽石動迷',
            $level >= 100 => '🔮 白金動迷',
            $level >= 80  => '⭐ 黃金動迷',
            $level >= 60  => '🥈 白銀動迷',
            $level >= 40  => '🥉 青銅動迷',
            $level >= 20  => '📺 資深動迷',
            $level >= 10  => '🎌 動漫愛好者',
            default       => '🌱 新手動迷',
        };
    }
}

if ( ! function_exists( 'smacg_decode_user_json_meta' ) ) {
    function smacg_decode_user_json_meta( int $user_id, string $meta_key ): array {
        $raw = get_user_meta( $user_id, $meta_key, true );

        if ( empty( $raw ) ) {
            return [];
        }

        if ( is_array( $raw ) ) {
            return $raw;
        }

        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            return is_array( $decoded ) ? $decoded : [];
        }

        return [];
    }
}

/**
 * 未登入導向登入頁
 */
if ( ! is_user_logged_in() ) {
    $redirect = function_exists( 'um_get_core_page' ) && um_get_core_page( 'login' )
        ? um_get_core_page( 'login' )
        : wp_login_url( home_url( '/member/' ) );

    wp_safe_redirect( $redirect );
    exit;
}

$current_user    = wp_get_current_user();
$user_id         = (int) $current_user->ID;
$display_name    = $current_user->display_name ?: $current_user->user_login;
$user_email      = $current_user->user_email;
$user_registered = $current_user->user_registered;

/**
 * UM / Account URL
 */
$account_url = function_exists( 'um_get_core_page' ) && um_get_core_page( 'account' )
    ? um_get_core_page( 'account' )
    : admin_url( 'profile.php' );

$password_url = $account_url ? add_query_arg( 'um_tab', 'password', $account_url ) : admin_url( 'profile.php' );

/**
 * 頭像：優先 Ultimate Member
 */
$avatar_url = function_exists( 'weixiaoacg_get_um_avatar_url' )
    ? weixiaoacg_get_um_avatar_url( $user_id, 190 )
    : get_avatar_url( $user_id, [ 'size' => 190 ] );

$avatar_preview_url = function_exists( 'weixiaoacg_get_um_avatar_url' )
    ? weixiaoacg_get_um_avatar_url( $user_id, 120 )
    : get_avatar_url( $user_id, [ 'size' => 120 ] );

/**
 * 使用者資料
 */
$user_bio = get_user_meta( $user_id, 'description', true );
if ( ! $user_bio && function_exists( 'um_fetch_user' ) && function_exists( 'um_user' ) ) {
    um_fetch_user( $user_id );
    $user_bio = (string) um_user( 'description' );
    if ( function_exists( 'um_reset_user' ) ) {
        um_reset_user();
    }
}

/**
 * 積分
 */
$user_points = (int) get_user_meta( $user_id, 'smacg_points', true );

/**
 * 等級
 */
$user_level     = max( 1, min( 200, (int) floor( $user_points / 1000 ) + 1 ) );
$level_progress = $user_points % 1000;
$level_percent  = (int) round( $level_progress / 1000 * 100 );
$next_points    = 1000 - $level_progress;
if ( $next_points === 1000 ) {
    $next_points = 0;
}
$level_title = smacg_get_level_title( $user_level );

/**
 * 會員方案：兼容 UM role / WP roles
 */
$role_candidates = [];

$um_role = get_user_meta( $user_id, 'um_member_role', true );
if ( $um_role ) {
    $role_candidates[] = $um_role;
}

if ( ! empty( $current_user->roles ) && is_array( $current_user->roles ) ) {
    $role_candidates = array_merge( $role_candidates, $current_user->roles );
}

$role_candidates = array_unique( array_filter( array_map( 'strval', $role_candidates ) ) );

$plan_label = '免費會員';
foreach ( $role_candidates as $role_slug ) {
    if ( in_array( $role_slug, [ 'vvip', 'weixiaoacg_vvip' ], true ) ) {
        $plan_label = '👑 VVIP 會員';
        break;
    }

    if ( in_array( $role_slug, [ 'vip', 'weixiaoacg_vip', 'weixiaoacg_pro' ], true ) ) {
        $plan_label = '⭐ VIP 會員';
    }
}

/**
 * 追蹤清單（從 wp_anime_user_status 表讀）
 */
$watchlist = [];
if ( class_exists( 'Anime_Sync_User_Status_Manager' ) ) {
    $usm  = new Anime_Sync_User_Status_Manager();
    $list = $usm->get_user_list( $user_id );

    // status int → string，並映射到會員頁用的命名
    $status_map = [
        'want'      => 'planned',   // 想看
        'watching'  => 'watching',  // 追番中
        'completed' => 'completed', // 已看完
        'dropped'   => 'dropped',   // 棄坑
    ];

    foreach ( $list as $entry ) {
        $pid = (int) ( $entry['anime_id'] ?? 0 );
        if ( ! $pid ) continue;

        $raw_status = $entry['status'] ?? '';
        $status     = $status_map[ $raw_status ] ?? '';

        // 全破等同已看完
        if ( ! empty( $entry['fullcleared'] ) ) {
            $status = 'completed';
        }

        // 若沒狀態、沒收藏、沒全破，跳過（純空白資料）
        if ( $status === '' && empty( $entry['favorited'] ) && empty( $entry['fullcleared'] ) ) {
            continue;
        }

        $watchlist[] = [
            'post_id'     => $pid,
            'status'      => $status ?: 'planned',  // 純收藏者預設 planned，但 favorited 旗標仍保留
            'progress'    => (int) ( $entry['progress'] ?? 0 ),
            'score'       => 0,  // 之後從 wp_anime_ratings 補
            'favorited'   => (bool) ( $entry['favorited'] ?? false ),
            'fullcleared' => (bool) ( $entry['fullcleared'] ?? false ),
        ];
    }
}

/**
 * 評分（從 wp_anime_ratings 表讀）
 */
$ratings = [];
global $wpdb;
$ratings_table = $wpdb->prefix . 'anime_ratings';
if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $ratings_table ) ) ) {
    $rating_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT anime_id, score_overall, score_story, score_music, score_animation, score_voice, updated_at
         FROM {$ratings_table}
         WHERE user_id = %d
         ORDER BY updated_at DESC",
        $user_id
    ) );

    $rating_lookup = [];
    foreach ( (array) $rating_rows as $r ) {
        $score_100 = (int) round( (float) $r->score_overall * 10 );
        $rating_lookup[ (int) $r->anime_id ] = $score_100;

        $ratings[] = [
            'post_id'  => (int) $r->anime_id,
            'score'    => $score_100,  // 0–100 給會員頁用
            'rated_at' => $r->updated_at,
        ];
    }

    // 把分數合併進 watchlist
    if ( ! empty( $rating_lookup ) ) {
        foreach ( $watchlist as &$w ) {
            if ( isset( $rating_lookup[ $w['post_id'] ] ) ) {
                $w['score'] = $rating_lookup[ $w['post_id'] ];
            }
        }
        unset( $w );
    }
}

/**
 * 積分 log（保留舊的 user_meta 來源）
 */
$points_log = smacg_decode_user_json_meta( $user_id, 'smacg_points_log' );

/**
 * 留言
 */
$user_comments = get_comments( [
    'user_id' => $user_id,
    'number'  => 10,
    'status'  => 'approve',
] );

/**
 * 統計
 */
$stat_watching  = count( array_filter( $watchlist, fn( $w ) => ( $w['status'] ?? '' ) === 'watching' ) );
$stat_completed = count( array_filter( $watchlist, fn( $w ) => ( $w['status'] ?? '' ) === 'completed' ) );
$stat_planned   = count( array_filter( $watchlist, fn( $w ) => ( $w['status'] ?? '' ) === 'planned' ) );
$stat_ratings   = count( $ratings );
$stat_comments  = count( $user_comments );

/**
 * 觀看時長
 */
$watch_minutes = (int) get_user_meta( $user_id, 'smacg_watch_minutes', true );
$watch_hours   = (int) floor( $watch_minutes / 60 );
$watch_mins    = $watch_minutes % 60;

$today_minutes = (int) get_user_meta( $user_id, 'smacg_today_minutes', true );
$today_hours   = (int) floor( $today_minutes / 60 );
$today_mins    = $today_minutes % 60;

get_header();
?>

<div class="mc-wrap">

  <!-- ── 頂部資訊卡 ── -->
  <div class="mc-hero glass">
    <div class="mc-hero-left">
      <div class="mc-avatar-wrap" id="mc-avatar-wrap">
        <a href="<?php echo esc_url( $account_url ); ?>" class="mc-avatar-img-wrap" title="前往帳號頁修改頭像">
          <img
            src="<?php echo esc_url( $avatar_url ); ?>"
            alt="<?php echo esc_attr( $display_name ); ?>"
            class="mc-avatar"
            id="mc-avatar-img"
          />
          <div class="mc-avatar-overlay" id="mc-avatar-overlay">
            <i class="fa-solid fa-camera"></i>
            <span>修改頭像</span>
          </div>
        </a>
        <div class="mc-avatar-level"><?php echo (int) $user_level; ?></div>
      </div>

      <div class="mc-hero-info">
        <h1 class="mc-username"><?php echo esc_html( $display_name ); ?></h1>

        <div class="mc-badges">
          <span class="mc-badge mc-badge--plan"><?php echo esc_html( $plan_label ); ?></span>
          <span class="mc-badge mc-badge--title"><?php echo esc_html( $level_title ); ?></span>
        </div>

        <div class="mc-email">
          <i class="fa-regular fa-envelope"></i>
          <?php echo esc_html( $user_email ); ?>
        </div>

        <?php if ( ! empty( $user_bio ) ) : ?>
          <div class="mc-bio">
            <?php echo esc_html( $user_bio ); ?>
          </div>
        <?php endif; ?>

        <div class="mc-level-bar-wrap">
          <div class="mc-level-label">
            Lv.<?php echo (int) $user_level; ?>
            <span><?php echo number_format( $level_progress ); ?> / 1,000 pt</span>
          </div>
          <div class="mc-level-bar">
            <div class="mc-level-fill" style="width:<?php echo (int) $level_percent; ?>%"></div>
          </div>
          <div class="mc-level-next">
            距離下一級還需 <?php echo number_format( $next_points ); ?> pt
          </div>
        </div>
      </div>
    </div>

    <div class="mc-hero-stats">
      <div class="mc-stat-card glass-light">
        <div class="mc-stat-icon"><i class="fa-solid fa-coins"></i></div>
        <div class="mc-stat-value"><?php echo number_format( $user_points ); ?></div>
        <div class="mc-stat-label">累積積分 (PT)</div>
      </div>

      <div class="mc-stat-card glass-light">
        <div class="mc-stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="mc-stat-value">
          <?php echo $today_hours > 0 ? esc_html( $today_hours . '時 ' ) : ''; ?>
          <?php echo esc_html( $today_mins ); ?>分
        </div>
        <div class="mc-stat-label">今日觀看時長</div>
      </div>

      <div class="mc-stat-card glass-light">
        <div class="mc-stat-icon"><i class="fa-solid fa-film"></i></div>
        <div class="mc-stat-value"><?php echo (int) $stat_completed; ?></div>
        <div class="mc-stat-label">已看完</div>
      </div>

      <div class="mc-stat-card glass-light">
        <div class="mc-stat-icon"><i class="fa-solid fa-star"></i></div>
        <div class="mc-stat-value"><?php echo (int) $stat_ratings; ?></div>
        <div class="mc-stat-label">已評分</div>
      </div>
    </div>
  </div>

  <!-- ── Tab 導覽 ── -->
  <div class="mc-tabs">
    <button class="mc-tab active" data-tab="dashboard">
      <i class="fa-solid fa-house"></i> 總覽
    </button>
    <button class="mc-tab" data-tab="watchlist">
      <i class="fa-solid fa-list-check"></i> 我的清單
    </button>
    <button class="mc-tab" data-tab="ratings">
      <i class="fa-solid fa-star"></i> 我的評分
    </button>
    <button class="mc-tab" data-tab="comments">
      <i class="fa-solid fa-comments"></i> 我的留言
    </button>
    <button class="mc-tab" data-tab="points">
      <i class="fa-solid fa-coins"></i> 積分紀錄
    </button>
    <button class="mc-tab" data-tab="settings">
      <i class="fa-solid fa-gear"></i> 帳號設定
    </button>
  </div>

  <div class="mc-content">

    <!-- 總覽 -->
    <div class="mc-panel active" id="tab-dashboard">
      <div class="mc-dashboard-grid">

        <!-- 我的清單快覽 -->
        <div class="mc-widget glass">
          <div class="mc-widget-head">
            <span><i class="fa-solid fa-list-check"></i> 我的清單</span>
            <a href="#" class="mc-widget-more" data-switch-tab="watchlist">查看全部</a>
          </div>

          <?php if ( ! empty( $watchlist ) ) : ?>
            <div class="mc-watchlist-mini">
              <?php foreach ( array_slice( $watchlist, 0, 5 ) as $item ) :
                $anime_id    = (int) ( $item['post_id'] ?? 0 );
                $anime_title = $anime_id ? get_the_title( $anime_id ) : ( $item['title'] ?? '未知' );
                $anime_thumb = $anime_id ? get_the_post_thumbnail_url( $anime_id, 'thumbnail' ) : '';
                $anime_url   = $anime_id ? get_permalink( $anime_id ) : '#';
                $status      = $item['status'] ?? 'planned';
                $progress    = (int) ( $item['progress'] ?? 0 );
                $total_ep    = $anime_id ? (int) get_post_meta( $anime_id, 'anime_episodes', true ) : 0;

                $status_map  = [
                    'watching'  => [ 'label' => '追中',   'class' => 'watching' ],
                    'completed' => [ 'label' => '已看完', 'class' => 'completed' ],
                    'planned'   => [ 'label' => '想看',   'class' => 'planned' ],
                    'dropped'   => [ 'label' => '已放棄', 'class' => 'dropped' ],
                    'paused'    => [ 'label' => '暫停',   'class' => 'paused' ],
                ];
                $s = $status_map[ $status ] ?? $status_map['planned'];
              ?>
                <a href="<?php echo esc_url( $anime_url ); ?>" class="mc-watchlist-item">
                  <div class="mc-wl-thumb">
                    <?php if ( $anime_thumb ) : ?>
                      <img src="<?php echo esc_url( $anime_thumb ); ?>" alt="<?php echo esc_attr( $anime_title ); ?>" />
                    <?php else : ?>
                      <span>🎬</span>
                    <?php endif; ?>
                  </div>

                  <div class="mc-wl-info">
                    <div class="mc-wl-title"><?php echo esc_html( $anime_title ); ?></div>
                    <div class="mc-wl-meta">
                      <span class="mc-status-dot mc-status-dot--<?php echo esc_attr( $s['class'] ); ?>"></span>
                      <?php echo esc_html( $s['label'] ); ?>
                      <?php if ( $total_ep > 0 ) : ?>
                        &nbsp;·&nbsp; <?php echo (int) $progress; ?> / <?php echo (int) $total_ep; ?> 集
                      <?php endif; ?>
                    </div>

                    <?php if ( $total_ep > 0 ) : ?>
                      <div class="mc-wl-bar">
                        <div class="mc-wl-fill" style="width:<?php echo min( 100, (int) round( $progress / max( 1, $total_ep ) * 100 ) ); ?>%"></div>
                      </div>
                    <?php endif; ?>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else : ?>
            <div class="mc-empty">
              <i class="fa-regular fa-folder-open"></i>
              <p>還沒有加入任何動漫</p>
            </div>
          <?php endif; ?>

          <div class="mc-wl-stats">
            <div class="mc-wl-stat">
              <span class="mc-status-dot mc-status-dot--watching"></span>
              追中 <strong><?php echo (int) $stat_watching; ?></strong>
            </div>
            <div class="mc-wl-stat">
              <span class="mc-status-dot mc-status-dot--completed"></span>
              已看完 <strong><?php echo (int) $stat_completed; ?></strong>
            </div>
            <div class="mc-wl-stat">
              <span class="mc-status-dot mc-status-dot--planned"></span>
              想看 <strong><?php echo (int) $stat_planned; ?></strong>
            </div>
          </div>
        </div>

        <!-- 社群互動 -->
        <div class="mc-widget glass">
          <div class="mc-widget-head">
            <span><i class="fa-solid fa-comments"></i> 社群互動</span>
            <a href="#" class="mc-widget-more" data-switch-tab="comments">查看全部</a>
          </div>

          <?php if ( ! empty( $user_comments ) ) : ?>
            <div class="mc-comment-list">
              <?php foreach ( array_slice( $user_comments, 0, 4 ) as $comment ) :
                $comment_post = get_post( $comment->comment_post_ID );
              ?>
                <a href="<?php echo esc_url( get_comment_link( $comment ) ); ?>" class="mc-comment-item">
                  <i class="fa-regular fa-comment-dots mc-comment-icon"></i>
                  <div>
                    <div class="mc-comment-text">
                      <?php echo esc_html( mb_substr( strip_tags( $comment->comment_content ), 0, 40 ) ); ?>…
                    </div>
                    <div class="mc-comment-meta">
                      <?php echo esc_html( $comment_post->post_title ?? '' ); ?>
                      &nbsp;·&nbsp;
                      <?php echo esc_html( human_time_diff( strtotime( $comment->comment_date ), current_time( 'timestamp' ) ) ); ?>前
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else : ?>
            <div class="mc-empty">
              <i class="fa-regular fa-comment"></i>
              <p>還沒有留言紀錄</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- 積分概覽 -->
        <div class="mc-widget glass">
          <div class="mc-widget-head">
            <span><i class="fa-solid fa-coins"></i> 積分概覽</span>
            <a href="#" class="mc-widget-more" data-switch-tab="points">查看明細</a>
          </div>

          <div class="mc-points-overview">
            <div class="mc-points-big"><?php echo number_format( $user_points ); ?> <span>PT</span></div>
            <div class="mc-points-level">Lv.<?php echo (int) $user_level; ?> · <?php echo esc_html( $level_title ); ?></div>
            <div class="mc-level-bar" style="margin:12px 0 6px;">
              <div class="mc-level-fill" style="width:<?php echo (int) $level_percent; ?>%"></div>
            </div>
            <div class="mc-points-next">距下一級還需 <strong><?php echo number_format( $next_points ); ?> pt</strong></div>
          </div>

          <div class="mc-milestone-list">
            <?php
            $milestones = [
              10  => '動漫愛好者',
              20  => '資深動迷',
              40  => '青銅動迷',
              60  => '白銀動迷',
              80  => '黃金動迷',
              100 => '白金動迷',
            ];

            foreach ( $milestones as $lv => $name ) :
              $reached = $user_level >= $lv;
            ?>
              <div class="mc-milestone <?php echo $reached ? 'reached' : ''; ?>">
                <div class="mc-milestone-dot"></div>
                <span>Lv.<?php echo (int) $lv; ?> <?php echo esc_html( $name ); ?></span>
                <?php if ( $reached ) : ?><i class="fa-solid fa-check"></i><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 會員方案 -->
        <div class="mc-widget glass mc-plan-widget">
          <div class="mc-widget-head">
            <span><i class="fa-solid fa-crown"></i> 會員方案</span>
          </div>

          <div class="mc-plan-current">
            <div class="mc-plan-badge"><?php echo esc_html( $plan_label ); ?></div>
            <div class="mc-plan-desc">
              <?php if ( str_contains( $plan_label, 'VVIP' ) ) : ?>
                享有所有功能，無廣告，優先客服
              <?php elseif ( str_contains( $plan_label, 'VIP' ) ) : ?>
                享有 VIP 專屬功能與優先服務
              <?php else : ?>
                升級 VIP 解鎖更多功能
              <?php endif; ?>
            </div>

            <?php if ( ! str_contains( $plan_label, 'VVIP' ) ) : ?>
              <a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="btn btn-primary mc-upgrade-btn">
                <i class="fa-solid fa-arrow-up"></i> 升級方案
              </a>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- 我的清單 -->
    <div class="mc-panel" id="tab-watchlist">
      <div class="mc-filter-bar">
        <button class="mc-filter-btn active" data-filter="all">全部 (<?php echo count( $watchlist ); ?>)</button>
        <button class="mc-filter-btn" data-filter="watching">追中 (<?php echo (int) $stat_watching; ?>)</button>
        <button class="mc-filter-btn" data-filter="completed">已看完 (<?php echo (int) $stat_completed; ?>)</button>
        <button class="mc-filter-btn" data-filter="planned">想看 (<?php echo (int) $stat_planned; ?>)</button>
      </div>

      <?php if ( ! empty( $watchlist ) ) : ?>
        <div class="mc-anime-grid">
          <?php foreach ( $watchlist as $item ) :
            $anime_id    = (int) ( $item['post_id'] ?? 0 );
            $anime_title = $anime_id ? get_the_title( $anime_id ) : ( $item['title'] ?? '未知' );
            $anime_thumb = $anime_id ? get_the_post_thumbnail_url( $anime_id, 'medium' ) : '';
            $anime_url   = $anime_id ? get_permalink( $anime_id ) : '#';
            $status      = $item['status'] ?? 'planned';
            $progress    = (int) ( $item['progress'] ?? 0 );
            $total_ep    = $anime_id ? (int) get_post_meta( $anime_id, 'anime_episodes', true ) : 0;
            $user_score  = (int) ( $item['score'] ?? 0 );

            $status_map  = [
                'watching'  => [ 'label' => '追中',   'class' => 'watching' ],
                'completed' => [ 'label' => '已看完', 'class' => 'completed' ],
                'planned'   => [ 'label' => '想看',   'class' => 'planned' ],
                'dropped'   => [ 'label' => '已放棄', 'class' => 'dropped' ],
                'paused'    => [ 'label' => '暫停',   'class' => 'paused' ],
            ];
            $s = $status_map[ $status ] ?? $status_map['planned'];
          ?>
            <div class="mc-anime-card glass" data-status="<?php echo esc_attr( $status ); ?>">
              <a href="<?php echo esc_url( $anime_url ); ?>" class="mc-anime-thumb">
                <?php if ( $anime_thumb ) : ?>
                  <img src="<?php echo esc_url( $anime_thumb ); ?>" alt="<?php echo esc_attr( $anime_title ); ?>" />
                <?php else : ?>
                  <div class="mc-anime-thumb-placeholder">🎬</div>
                <?php endif; ?>

                <div class="mc-anime-status-tag mc-anime-status-tag--<?php echo esc_attr( $s['class'] ); ?>">
                  <?php echo esc_html( $s['label'] ); ?>
                </div>
              </a>

              <div class="mc-anime-info">
                <a href="<?php echo esc_url( $anime_url ); ?>" class="mc-anime-title">
                  <?php echo esc_html( $anime_title ); ?>
                </a>

                <?php if ( $total_ep > 0 ) : ?>
                  <div class="mc-anime-progress-wrap">
                    <div class="mc-anime-progress-bar">
                      <div class="mc-anime-progress-fill" style="width:<?php echo min( 100, (int) round( $progress / max( 1, $total_ep ) * 100 ) ); ?>%"></div>
                    </div>
                    <span class="mc-anime-progress-text"><?php echo (int) $progress; ?>/<?php echo (int) $total_ep; ?></span>
                  </div>
                <?php endif; ?>

                <?php if ( $user_score > 0 ) : ?>
                  <div class="mc-anime-score">
                    <i class="fa-solid fa-star" style="color:#fbbf24;"></i>
                    <?php echo number_format( $user_score / 10, 1 ); ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <div class="mc-empty glass">
          <i class="fa-regular fa-folder-open"></i>
          <p>清單是空的，去追一些動漫吧！</p>
          <a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>" class="btn btn-primary">瀏覽動漫</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- 我的評分 -->
    <div class="mc-panel" id="tab-ratings">
      <?php if ( ! empty( $ratings ) ) : ?>
        <div class="mc-rating-list">
          <?php foreach ( $ratings as $r ) :
            $anime_id    = (int) ( $r['post_id'] ?? 0 );
            $anime_title = $anime_id ? get_the_title( $anime_id ) : ( $r['title'] ?? '未知' );
            $anime_thumb = $anime_id ? get_the_post_thumbnail_url( $anime_id, 'thumbnail' ) : '';
            $anime_url   = $anime_id ? get_permalink( $anime_id ) : '#';
            $score       = (int) ( $r['score'] ?? 0 );
            $score_show  = number_format( $score / 10, 1 );
            $rated_at    = $r['rated_at'] ?? '';
          ?>
            <a href="<?php echo esc_url( $anime_url ); ?>" class="mc-rating-item glass">
              <div class="mc-rating-thumb">
                <?php if ( $anime_thumb ) : ?>
                  <img src="<?php echo esc_url( $anime_thumb ); ?>" alt="<?php echo esc_attr( $anime_title ); ?>" />
                <?php else : ?>
                  <span>🎬</span>
                <?php endif; ?>
              </div>

              <div class="mc-rating-info">
                <div class="mc-rating-title"><?php echo esc_html( $anime_title ); ?></div>
                <div class="mc-rating-stars">
                  <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                    <span class="mc-star <?php echo $i <= round( $score / 10 ) ? 'filled' : ''; ?>">★</span>
                  <?php endfor; ?>
                </div>

                <?php if ( $rated_at ) : ?>
                  <div class="mc-rating-date">
                    <i class="fa-regular fa-clock"></i>
                    <?php echo esc_html( date( 'Y-m-d', strtotime( $rated_at ) ) ); ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="mc-rating-score"><?php echo esc_html( $score_show ); ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <div class="mc-empty glass">
          <i class="fa-regular fa-star"></i>
          <p>還沒有評分紀錄</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- 我的留言 -->
    <div class="mc-panel" id="tab-comments">
      <?php if ( ! empty( $user_comments ) ) : ?>
        <div class="mc-comment-full-list">
          <?php foreach ( $user_comments as $comment ) :
            $comment_post  = get_post( $comment->comment_post_ID );
            $comment_thumb = get_the_post_thumbnail_url( $comment->comment_post_ID, 'thumbnail' );
          ?>
            <div class="mc-comment-full-item glass">
              <div class="mc-cf-thumb">
                <?php if ( $comment_thumb ) : ?>
                  <img src="<?php echo esc_url( $comment_thumb ); ?>" alt="<?php echo esc_attr( $comment_post->post_title ?? '' ); ?>" />
                <?php else : ?>
                  <span>📰</span>
                <?php endif; ?>
              </div>

              <div class="mc-cf-body">
                <div class="mc-cf-post-title">
                  <a href="<?php echo esc_url( get_permalink( $comment->comment_post_ID ) ); ?>">
                    <?php echo esc_html( $comment_post->post_title ?? '' ); ?>
                  </a>
                </div>
                <div class="mc-cf-content">
                  <?php echo esc_html( mb_substr( strip_tags( $comment->comment_content ), 0, 120 ) ); ?>
                </div>
                <div class="mc-cf-meta">
                  <i class="fa-regular fa-clock"></i>
                  <?php echo esc_html( human_time_diff( strtotime( $comment->comment_date ), current_time( 'timestamp' ) ) ); ?>前
                </div>
              </div>

              <a href="<?php echo esc_url( get_comment_link( $comment ) ); ?>" class="mc-cf-link" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <div class="mc-empty glass">
          <i class="fa-regular fa-comment"></i>
          <p>還沒有留言紀錄</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- 積分紀錄 -->
    <div class="mc-panel" id="tab-points">
      <div class="mc-points-header glass">
        <div class="mc-points-total">
          <div class="mc-points-total-num"><?php echo number_format( $user_points ); ?></div>
          <div class="mc-points-total-label">累積積分 (PT)</div>
        </div>

        <div class="mc-points-level-info">
          <div class="mc-points-lv">Lv.<?php echo (int) $user_level; ?></div>
          <div class="mc-points-lv-title"><?php echo esc_html( $level_title ); ?></div>
          <div class="mc-level-bar" style="margin-top:10px;">
            <div class="mc-level-fill" style="width:<?php echo (int) $level_percent; ?>%"></div>
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
            下一級還需 <?php echo number_format( $next_points ); ?> pt
          </div>
        </div>
      </div>

      <?php if ( ! empty( $points_log ) ) : ?>
        <div class="mc-points-log">
          <?php foreach ( array_reverse( $points_log ) as $log ) : ?>
            <div class="mc-points-log-item glass">
              <div class="mc-pl-icon">
                <?php
                echo match ( $log['type'] ?? '' ) {
                    'watch'   => '<i class="fa-solid fa-play"></i>',
                    'rate'    => '<i class="fa-solid fa-star"></i>',
                    'comment' => '<i class="fa-solid fa-comment"></i>',
                    'login'   => '<i class="fa-solid fa-right-to-bracket"></i>',
                    default   => '<i class="fa-solid fa-coins"></i>',
                };
                ?>
              </div>

              <div class="mc-pl-info">
                <div class="mc-pl-desc"><?php echo esc_html( $log['desc'] ?? '積分變動' ); ?></div>
                <div class="mc-pl-date">
                  <?php echo esc_html( date( 'Y-m-d H:i', strtotime( $log['time'] ?? 'now' ) ) ); ?>
                </div>
              </div>

              <div class="mc-pl-pts <?php echo ( ( $log['pts'] ?? 0 ) > 0 ) ? 'plus' : 'minus'; ?>">
                <?php echo ( ( $log['pts'] ?? 0 ) > 0 ) ? '+' : ''; ?>
                <?php echo (int) ( $log['pts'] ?? 0 ); ?> pt
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <div class="mc-empty glass">
          <i class="fa-solid fa-coins"></i>
          <p>還沒有積分紀錄，開始使用網站來獲得積分吧！</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- 帳號設定 -->
    <div class="mc-panel" id="tab-settings">
      <div class="mc-settings-grid">

        <div class="mc-settings-section glass">
          <div class="mc-settings-title">
            <i class="fa-solid fa-user"></i> 基本資料
          </div>

          <div class="mc-settings-info-list">
            <div class="mc-settings-info-item">
              <span class="mc-settings-info-label">顯示名稱</span>
              <span class="mc-settings-info-value"><?php echo esc_html( $display_name ); ?></span>
            </div>
            <div class="mc-settings-info-item">
              <span class="mc-settings-info-label">Email</span>
              <span class="mc-settings-info-value"><?php echo esc_html( $user_email ); ?></span>
            </div>
            <div class="mc-settings-info-item">
              <span class="mc-settings-info-label">會員 ID</span>
              <span class="mc-settings-info-value">#<?php echo (int) $user_id; ?></span>
            </div>
            <div class="mc-settings-info-item">
              <span class="mc-settings-info-label">註冊日期</span>
              <span class="mc-settings-info-value"><?php echo esc_html( date( 'Y-m-d', strtotime( $user_registered ) ) ); ?></span>
            </div>
          </div>

          <a href="<?php echo esc_url( $account_url ); ?>" class="btn btn-ghost mc-settings-btn">
            <i class="fa-solid fa-pen"></i> 編輯帳號資料
          </a>
        </div>

        <div class="mc-settings-section glass">
          <div class="mc-settings-title">
            <i class="fa-solid fa-image"></i> 頭像設定
          </div>

          <div class="mc-avatar-settings">
            <img src="<?php echo esc_url( $avatar_preview_url ); ?>" alt="頭像" class="mc-avatar-preview" />
            <div class="mc-avatar-info">
              <p style="font-size:13px;color:var(--text-muted);line-height:1.6;margin-bottom:12px;">
                目前頭像由 Ultimate Member 帳號系統管理，點擊下方按鈕即可前往修改。
              </p>
              <a href="<?php echo esc_url( $account_url ); ?>" class="btn btn-ghost mc-settings-btn">
                <i class="fa-solid fa-camera"></i> 更換頭像
              </a>
            </div>
          </div>
        </div>

        <div class="mc-settings-section glass">
          <div class="mc-settings-title">
            <i class="fa-solid fa-shield-halved"></i> 安全設定
          </div>
          <div class="mc-settings-info-list">
            <div class="mc-settings-info-item">
              <span class="mc-settings-info-label">密碼</span>
              <span class="mc-settings-info-value">••••••••</span>
            </div>
          </div>
          <a href="<?php echo esc_url( $password_url ); ?>" class="btn btn-ghost mc-settings-btn">
            <i class="fa-solid fa-lock"></i> 修改密碼
          </a>
        </div>

        <div class="mc-settings-section glass">
          <div class="mc-settings-title">
            <i class="fa-solid fa-right-from-bracket"></i> 登出
          </div>
          <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
            登出後需要重新輸入帳號密碼才能登入
          </p>
          <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="btn mc-logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> 登出帳號
          </a>
        </div>

      </div>
    </div>

  </div>
</div>

<script>
(function () {
  'use strict';

  const tabs   = document.querySelectorAll('.mc-tab');
  const panels = document.querySelectorAll('.mc-panel');

  function switchTab(tabId) {
    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
    panels.forEach(p => p.classList.toggle('active', p.id === 'tab-' + tabId));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
  });

  document.querySelectorAll('[data-switch-tab]').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      switchTab(el.dataset.switchTab);
    });
  });

  const filterBtns = document.querySelectorAll('.mc-filter-btn');
  const animeCards = document.querySelectorAll('.mc-anime-card');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.filter;
      animeCards.forEach(card => {
        card.style.display = (filter === 'all' || card.dataset.status === filter) ? '' : 'none';
      });
    });
  });

})();
</script>

<?php get_footer(); ?>
