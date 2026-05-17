<?php
/**
 * Public Profile - Render Functions
 *
 * 公開個人頁渲染層：hero、overview、watchlist、ratings、badges、activity。
 *
 * @package    weixiaoacg
 * @subpackage smacg-social
 * @version    1.2.1
 * @since      1.0.0
 *
 * Changelog:
 * - 1.2.1 (2026-05-16) — Bug fix release
 *   * Bug #11：smacg_pp_render_activity() 讀取 $ev['time_human']（資料層 key），
 *     若缺則 fallback 用 $ev['time'] 算 human_time_diff；移除錯誤的 'timestamp' key。
 *   * Bug #12 + #14：smacg_pp_render_watchlist() 統一使用 'favorited' 鍵
 *     （資料層 smacg_build_watchlist 實際輸出）。
 *     - $counts['favorited'] 取代 $counts['favorite']
 *     - filter button data-filter="favorited" 對齊卡片 data-status / data-favorited
 *     - 計入純收藏項目（status='favorited' 由資料層產出）
 *   * Bug #13：smacg_pp_render_ratings() 用 $r['post_id'] ?? $r['anime_id']
 *     兼容資料層實際輸出（smacg_get_user_ratings 回傳 anime_id）。
 *   * Bug #15：smacg_pp_render_overview() 讀 $stats['genres']
 *     （資料層 smacg_calc_member_stats 實際輸出 key，非 top_genres）。
 *   * smacg_pp_render_anime_card()：$is_favorite 改讀 $extra['favorited']，
 *     data-favorited 屬性對齊；保留 $extra['favorite'] fallback 以防其他呼叫端傳舊 key。
 * - 1.2.0 (2026-05-16)
 *   * Hero 區粉絲/追蹤中數字改為 <a> 連結，指向 /u/{username}/followers/ 與 /following/。
 *   * 追蹤按鈕右側新增互追膠囊（🤝 互相追蹤），透過 smacg_is_mutual_follow() 判定。
 *   * 加入 smacg_is_mutual_follow() function_exists 守衛，缺失時 fallback 為兩次 smacg_is_following()。
 *   * 加入一次性 inline CSS（.pp-count-link / .pp-mutual-badge），使用 static guard 避免重複輸出。
 * - 1.1.0 (2026-05-13)
 *   * Hero 顯示粉絲/追蹤中數字。
 *   * 追蹤按鈕改用 .smacg-follow-btn class，交由 follow.js 統一處理。
 * - 1.0.0 (2026-05-13)
 *   * 初始版本。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ========================================================================
 * Hero 區塊
 * ====================================================================== */

if ( ! function_exists( 'smacg_pp_render_hero' ) ) :
function smacg_pp_render_hero( $user, $args ) {
    $uid          = (int) $user->ID;
    $display_name = ! empty( $args['display_name'] ) ? $args['display_name'] : $user->display_name;
    $bio          = ! empty( $args['bio'] )          ? $args['bio']          : '';
    $reg_date     = ! empty( $args['reg_date'] )     ? $args['reg_date']     : '';
    $email        = isset( $args['email'] )          ? $args['email']        : '';
    $points       = isset( $args['points'] )         ? (int) $args['points'] : 0;
    $plan_label   = ! empty( $args['plan_label'] )   ? $args['plan_label']   : '';
    $avatar_url   = ! empty( $args['avatar_url'] )   ? $args['avatar_url']   : get_avatar_url( $uid, [ 'size' => 200 ] );
    $lvl          = ! empty( $args['lvl_info'] )     ? $args['lvl_info']     : null;
    $is_owner     = ! empty( $args['is_owner'] );

    // 粉絲 / 追蹤中數字
    $followers_count = function_exists( 'smacg_get_followers_count' ) ? (int) smacg_get_followers_count( $uid ) : 0;
    $following_count = function_exists( 'smacg_get_following_count' ) ? (int) smacg_get_following_count( $uid ) : 0;

    // 連結到 followers / following 子頁
    $profile_url        = function_exists( 'smacg_get_public_profile_url' ) ? smacg_get_public_profile_url( $user ) : home_url( '/u/' . $user->user_login . '/' );
    $profile_url        = trailingslashit( $profile_url );
    $followers_url      = $profile_url . 'followers/';
    $following_url      = $profile_url . 'following/';

    // 互追判定（僅在登入且非本人時計算）
    $is_mutual = false;
    if ( ! $is_owner && is_user_logged_in() ) {
        $viewer_id = get_current_user_id();
        if ( function_exists( 'smacg_is_mutual_follow' ) ) {
            $is_mutual = (bool) smacg_is_mutual_follow( $viewer_id, $uid );
        } elseif ( function_exists( 'smacg_is_following' ) ) {
            $is_mutual = smacg_is_following( $viewer_id, $uid ) && smacg_is_following( $uid, $viewer_id );
        }
    }
    ?>
    <section class="pp-hero">
        <div class="pp-hero-inner">

            <div class="pp-hero-avatar">
                <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" loading="eager" decoding="async">
                <?php if ( $lvl && ! empty( $lvl['icon'] ) ) : ?>
                    <span class="pp-hero-level-icon" title="<?php echo esc_attr( 'Lv.' . $lvl['level'] . ' ' . $lvl['title'] ); ?>"><?php echo esc_html( $lvl['icon'] ); ?></span>
                <?php endif; ?>
            </div>

            <div class="pp-hero-main">
                <div class="pp-hero-title">
                    <h1 class="pp-hero-name"><?php echo esc_html( $display_name ); ?></h1>
                    <?php if ( $plan_label ) : ?>
                        <span class="pp-hero-plan"><?php echo esc_html( $plan_label ); ?></span>
                    <?php endif; ?>
                </div>

                <div class="pp-hero-meta">
                    <?php if ( $reg_date ) : ?>
                        <span class="pp-meta-item">📅 <?php echo esc_html( '加入於 ' . $reg_date ); ?></span>
                    <?php endif; ?>
                    <?php if ( $email ) : ?>
                        <span class="pp-meta-item">✉️ <?php echo esc_html( $email ); ?></span>
                    <?php endif; ?>
                    <?php if ( $points ) : ?>
                        <span class="pp-meta-item">💎 <?php echo esc_html( number_format_i18n( $points ) ); ?> 點</span>
                    <?php endif; ?>
                    <a class="pp-meta-item pp-count-link" href="<?php echo esc_url( $followers_url ); ?>">
                        👥 粉絲 <strong class="pp-followers-count"><?php echo esc_html( number_format_i18n( $followers_count ) ); ?></strong>
                    </a>
                    <a class="pp-meta-item pp-count-link" href="<?php echo esc_url( $following_url ); ?>">
                        ➡️ 追蹤中 <strong class="pp-following-count"><?php echo esc_html( number_format_i18n( $following_count ) ); ?></strong>
                    </a>
                </div>

                <?php if ( $bio ) : ?>
                    <p class="pp-hero-bio"><?php echo esc_html( $bio ); ?></p>
                <?php endif; ?>

                <?php if ( $lvl ) : ?>
                    <div class="pp-hero-level">
                        <div class="pp-level-info">
                            <span class="pp-level-title">Lv.<?php echo (int) $lvl['level']; ?> <?php echo esc_html( $lvl['title'] ); ?></span>
                            <?php if ( empty( $lvl['is_max'] ) ) : ?>
                                <span class="pp-level-exp"><?php echo esc_html( number_format_i18n( (int) $lvl['exp'] ) ); ?> EXP</span>
                            <?php else : ?>
                                <span class="pp-level-exp">MAX</span>
                            <?php endif; ?>
                        </div>
                        <div class="pp-level-bar">
                            <div class="pp-level-bar-fill" style="width: <?php echo esc_attr( (float) ( $lvl['percent'] ?? 0 ) ); ?>%;"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pp-hero-actions">
                    <?php if ( $is_owner ) : ?>
                        <a class="pp-btn pp-btn-primary" href="<?php echo esc_url( home_url( '/mc/#settings' ) ); ?>">⚙️ 編輯個人資料</a>
                    <?php elseif ( is_user_logged_in() ) : ?>
                        <?php
                        $is_following = function_exists( 'smacg_is_following' ) ? smacg_is_following( get_current_user_id(), $uid ) : false;
                        ?>
                        <button class="smacg-follow-btn <?php echo $is_following ? 'is-following' : ''; ?>"
                                data-user-id="<?php echo esc_attr( $uid ); ?>"
                                data-following="<?php echo $is_following ? '1' : '0'; ?>">
                            <?php echo $is_following ? '✓ 追蹤中' : '+ 追蹤'; ?>
                        </button>
                        <?php if ( $is_mutual ) : ?>
                            <span class="pp-mutual-badge" title="你們互相追蹤對方">🤝 互相追蹤</span>
                        <?php endif; ?>
                    <?php else : ?>
                        <a class="pp-btn pp-btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">登入後追蹤</a>
                    <?php endif; ?>
                    <button class="pp-btn pp-btn-ghost pp-share-btn" data-url="<?php echo esc_attr( get_permalink() ); ?>">🔗 分享</button>
                </div>

            </div>
        </div>
    </section>
    <?php
    smacg_pp_render_inline_css();
}
endif;

/* ========================================================================
 * 一次性 inline CSS
 * ====================================================================== */

if ( ! function_exists( 'smacg_pp_render_inline_css' ) ) :
function smacg_pp_render_inline_css() {
    static $printed = false;
    if ( $printed ) return;
    $printed = true;
    ?>
    <style id="smacg-pp-inline-v121">
        .pp-count-link{text-decoration:none;color:inherit;transition:color .15s ease, transform .15s ease;display:inline-flex;align-items:center;gap:4px}
        .pp-count-link:hover{color:var(--theme-palette-color-1,#4a6cf7);transform:translateY(-1px)}
        .pp-count-link strong{font-weight:700}
        .pp-mutual-badge{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:999px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-size:13px;font-weight:600;line-height:1;white-space:nowrap;box-shadow:0 2px 6px rgba(16,185,129,.25);cursor:default;user-select:none}
        .pp-hero-actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
        @media (max-width:480px){.pp-mutual-badge{font-size:12px;padding:5px 10px}}
    </style>
    <?php
}
endif;

/* ========================================================================
 * Overview Tab
 * ====================================================================== */

if ( ! function_exists( 'smacg_pp_render_overview' ) ) :
function smacg_pp_render_overview( $user, $watchlist, $stats, $can_w, $can_r ) {
    $uid = (int) $user->ID;
    ?>
    <section class="pp-section pp-overview">
        <div class="pp-grid pp-grid-2">

            <div class="pp-card">
                <h2 class="pp-card-title">📺 最近更新</h2>
                <?php
                $recent = [];
                if ( $can_w && ! empty( $watchlist ) ) {
                    $recent = array_slice( $watchlist, 0, 8 );
                }
                if ( $recent ) :
                ?>
                    <div class="pp-anime-grid">
                        <?php foreach ( $recent as $item ) {
                            $pid = is_array( $item ) ? ( $item['post_id'] ?? 0 ) : (int) $item;
                            if ( $pid ) smacg_pp_render_anime_card( $pid, $item );
                        } ?>
                    </div>
                <?php else : ?>
                    <p class="pp-empty">尚無資料</p>
                <?php endif; ?>
            </div>

            <div class="pp-card">
                <h2 class="pp-card-title">🏷️ 喜愛類型</h2>
                <?php
                /**
                 * Bug #15：資料層 smacg_calc_member_stats() 實際輸出 key 為 'genres'
                 * （結構：[ ['name'=>..,'count'=>..,'percent'=>..], ... ]）。
                 * 保留 'top_genres' fallback 以防其他資料來源沿用舊 key。
                 */
                $genres_src = [];
                if ( ! empty( $stats['genres'] ) ) {
                    $genres_src = (array) $stats['genres'];
                } elseif ( ! empty( $stats['top_genres'] ) ) {
                    $genres_src = (array) $stats['top_genres'];
                }
                $top_genres = $genres_src ? array_slice( $genres_src, 0, 10 ) : [];
                if ( $top_genres ) :
                ?>
                    <div class="pp-tag-cloud">
                        <?php foreach ( $top_genres as $g ) :
                            $name = is_array( $g ) ? ( $g['name'] ?? '' ) : (string) $g;
                            $cnt  = is_array( $g ) ? (int) ( $g['count'] ?? 0 ) : 0;
                            if ( ! $name ) continue;
                        ?>
                            <span class="pp-tag"><?php echo esc_html( $name ); ?><?php if ( $cnt ) : ?> <em>×<?php echo (int) $cnt; ?></em><?php endif; ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="pp-empty">尚無偏好統計</p>
                <?php endif; ?>
            </div>

        </div>
    </section>
    <?php
}
endif;

/* ========================================================================
 * Watchlist Tab
 * ====================================================================== */

if ( ! function_exists( 'smacg_pp_render_watchlist' ) ) :
function smacg_pp_render_watchlist( $watchlist ) {
    if ( empty( $watchlist ) ) {
        echo '<section class="pp-section"><p class="pp-empty">尚未加入任何作品</p></section>';
        return;
    }

    /**
     * Bug #12 + #14：
     *  - 資料層 smacg_build_watchlist() 寫入 'favorited' (bool) 與
     *    純收藏項目的 status='favorited'。
     *  - 此處 counts/filter/data 屬性全部統一改為 'favorited'。
     *  - 'favorited' 計入：status==='favorited' OR favorited===true。
     */
    $counts = [ 'all' => 0, 'watching' => 0, 'completed' => 0, 'favorited' => 0, 'want' => 0, 'dropped' => 0 ];
    foreach ( $watchlist as $w ) {
        $counts['all']++;
        $s = is_array( $w ) ? ( $w['status'] ?? '' ) : '';
        if ( isset( $counts[ $s ] ) ) $counts[ $s ]++;
        // 額外把 favorited flag 為 true 但 status 不是 'favorited' 的也計入
        if ( ! empty( $w['favorited'] ) && $s !== 'favorited' ) {
            $counts['favorited']++;
        }
    }
    ?>
    <section class="pp-section pp-watchlist">
        <div class="pp-filter-bar">
            <button class="pp-filter pp-filter-active" data-filter="all">全部 <em><?php echo (int) $counts['all']; ?></em></button>
            <button class="pp-filter" data-filter="watching">👀 觀看中 <em><?php echo (int) $counts['watching']; ?></em></button>
            <button class="pp-filter" data-filter="completed">✅ 已完結 <em><?php echo (int) $counts['completed']; ?></em></button>
            <button class="pp-filter" data-filter="favorited">❤️ 收藏 <em><?php echo (int) $counts['favorited']; ?></em></button>
            <button class="pp-filter" data-filter="want">📌 想看 <em><?php echo (int) $counts['want']; ?></em></button>
        </div>
        <div class="pp-anime-grid">
            <?php foreach ( $watchlist as $item ) {
                $pid = is_array( $item ) ? ( $item['post_id'] ?? 0 ) : (int) $item;
                if ( $pid ) smacg_pp_render_anime_card( $pid, $item );
            } ?>
        </div>
    </section>
    <?php
}
endif;

/* ========================================================================
 * Ratings Tab
 * ====================================================================== */

if ( ! function_exists( 'smacg_pp_render_ratings' ) ) :
function smacg_pp_render_ratings( $ratings ) {
    if ( empty( $ratings ) ) {
        echo '<section class="pp-section"><p class="pp-empty">尚未評分任何作品</p></section>';
        return;
    }
    usort( $ratings, function( $a, $b ) {
        $sa = (float) ( $a['overall_score'] ?? 0 );
        $sb = (float) ( $b['overall_score'] ?? 0 );
        return $sb <=> $sa;
    } );
    ?>
    <section class="pp-section pp-ratings">
        <p class="pp-section-info">共評分 <strong><?php echo count( $ratings ); ?></strong> 部作品</p>
        <div class="pp-anime-grid">
            <?php foreach ( $ratings as $r ) {
                /**
                 * Bug #13：資料層 smacg_get_user_ratings() 輸出 key 為 'anime_id'，
                 * 不是 'post_id'。保留 post_id 優先以兼容其他資料來源。
                 */
                $pid = (int) ( $r['post_id'] ?? $r['anime_id'] ?? 0 );
                if ( $pid ) smacg_pp_render_anime_card( $pid, $r );
            } ?>
        </div>
    </section>
    <?php
}
endif;

/* ========================================================================
 * Badges Tab
 * ====================================================================== */

if ( ! function_exists( 'smacg_pp_render_badges' ) ) :
function smacg_pp_render_badges( $uid, $stats ) {
    $badges = [];
    $completed   = (int) ( $stats['completed'] ?? 0 );
    $rated_count = (int) ( $stats['rated_count'] ?? 0 );
    $watch_days  = (int) ( $stats['watch_days'] ?? 0 );
    $favorites   = (int) ( $stats['favorites'] ?? 0 );

    if ( $completed >= 1 )   $badges[] = [ '🎬', '初心者', '完結第一部作品' ];
    if ( $completed >= 10 )  $badges[] = [ '📺', '觀劇達人', '完結 10 部作品' ];
    if ( $completed >= 50 )  $badges[] = [ '🏆', '資深觀眾', '完結 50 部作品' ];
    if ( $completed >= 100 ) $badges[] = [ '👑', '動畫王者', '完結 100 部作品' ];
    if ( $rated_count >= 10 )  $badges[] = [ '⭐', '評論家', '評分 10 部作品' ];
    if ( $rated_count >= 50 )  $badges[] = [ '🌟', '專業評論', '評分 50 部作品' ];
    if ( $watch_days >= 30 )   $badges[] = [ '📅', '常駐觀眾', '觀看 30 天' ];
    if ( $favorites >= 10 )    $badges[] = [ '❤️', '收藏家', '收藏 10 部作品' ];

    if ( function_exists( 'gamipress_get_user_achievements' ) ) {
        $achievements = gamipress_get_user_achievements( [ 'user_id' => $uid ] );
        if ( $achievements ) {
            foreach ( $achievements as $a ) {
                $badges[] = [ '🏅', get_the_title( $a->ID ), wp_strip_all_tags( get_post_field( 'post_excerpt', $a->ID ) ) ];
            }
        }
    }
    ?>
    <section class="pp-section pp-badges">
        <?php if ( $badges ) : ?>
            <div class="pp-badge-grid">
                <?php foreach ( $badges as $b ) : ?>
                    <div class="pp-badge">
                        <div class="pp-badge-icon"><?php echo esc_html( $b[0] ); ?></div>
                        <div class="pp-badge-name"><?php echo esc_html( $b[1] ); ?></div>
                        <div class="pp-badge-desc"><?php echo esc_html( $b[2] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="pp-empty">尚未獲得任何徽章</p>
        <?php endif; ?>
    </section>
    <?php
}
endif;

/* ========================================================================
 * Activity Tab
 * ====================================================================== */

if ( ! function_exists( 'smacg_pp_render_activity' ) ) :
function smacg_pp_render_activity( $activity ) {
    if ( empty( $activity ) ) {
        echo '<section class="pp-section"><p class="pp-empty">尚無近期活動</p></section>';
        return;
    }
    ?>
    <section class="pp-section pp-activity">
        <ul class="pp-timeline">
            <?php foreach ( $activity as $ev ) :
                $icon  = $ev['icon']  ?? '📝';
                $title = $ev['title'] ?? '';
                $meta  = $ev['meta']  ?? '';
                $url   = $ev['url']   ?? ( $ev['link'] ?? '' );

                /**
                 * Bug #11：資料層 smacg_get_recent_activity() 輸出 'time' (unix int)
                 * 與 'time_human' (string)，無 'timestamp' 鍵。
                 * 優先用 time_human（已含「剛剛 / X 天前 / Y‑M‑D」邏輯），
                 * 若缺再 fallback 用 time 即時計算。
                 */
                $diff = '';
                if ( ! empty( $ev['time_human'] ) ) {
                    $diff = (string) $ev['time_human'];
                } else {
                    $ts = (int) ( $ev['time'] ?? $ev['timestamp'] ?? 0 );
                    if ( $ts ) {
                        $diff = human_time_diff( $ts, current_time( 'timestamp' ) ) . '前';
                    }
                }
            ?>
                <li class="pp-timeline-item">
                    <span class="pp-timeline-icon"><?php echo esc_html( $icon ); ?></span>
                    <div class="pp-timeline-body">
                        <div class="pp-timeline-title">
                            <?php if ( $url ) : ?>
                                <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
                            <?php else : ?>
                                <?php echo esc_html( $title ); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ( $meta ) : ?>
                            <div class="pp-timeline-meta"><?php echo esc_html( $meta ); ?></div>
                        <?php endif; ?>
                        <?php if ( $diff ) : ?>
                            <div class="pp-timeline-time"><?php echo esc_html( $diff ); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
}
endif;

/* ========================================================================
 * 共用 Anime 卡片（公開版，無 quick-action 按鈕）
 * ====================================================================== */

if ( ! function_exists( 'smacg_pp_render_anime_card' ) ) :
function smacg_pp_render_anime_card( $pid, $extra = [] ) {
    $pid = (int) $pid;
    if ( ! $pid ) return;
    $post = get_post( $pid );
    if ( ! $post || $post->post_status !== 'publish' ) return;

    $title = get_the_title( $pid );
    $url   = get_permalink( $pid );
    $thumb = '';
    if ( has_post_thumbnail( $pid ) ) {
        $thumb = get_the_post_thumbnail_url( $pid, 'weixiaoacg-cover' );
    }
    if ( ! $thumb && function_exists( 'weixiaoacg_acf' ) ) {
        $thumb = weixiaoacg_acf( 'cover_image', $pid );
    }
    if ( ! $thumb ) {
        $thumb = get_stylesheet_directory_uri() . '/assets/images/placeholder.svg';
    }

    $status = is_array( $extra ) ? ( $extra['status'] ?? '' ) : '';

    /**
     * Bug #12：資料層使用 'favorited' 鍵，保留舊 'favorite' fallback。
     */
    $is_favorite = false;
    if ( is_array( $extra ) ) {
        $is_favorite = ! empty( $extra['favorited'] ) || ! empty( $extra['favorite'] );
    }

    $score      = is_array( $extra ) ? (float) ( $extra['overall_score'] ?? 0 ) : 0;
    $watched_ep = is_array( $extra ) ? (int) ( $extra['watched_ep'] ?? 0 ) : 0;
    $total_ep   = is_array( $extra ) ? (int) ( $extra['total_ep'] ?? 0 ) : 0;

    $status_labels = [
        'watching'  => '👀 觀看中',
        'completed' => '✅ 已完結',
        'favorited' => '❤️ 收藏',
        'want'      => '📌 想看',
        'dropped'   => '⛔ 棄追',
    ];
    $status_label = $status_labels[ $status ] ?? '';
    ?>
    <article class="pp-anime-card"
             data-status="<?php echo esc_attr( $status ); ?>"
             data-favorited="<?php echo $is_favorite ? '1' : '0'; ?>">
        <a class="pp-anime-thumb" href="<?php echo esc_url( $url ); ?>">
            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" decoding="async">
            <?php if ( $is_favorite ) : ?>
                <span class="pp-anime-fav">❤️</span>
            <?php endif; ?>
            <?php if ( $status_label ) : ?>
                <span class="pp-anime-status"><?php echo esc_html( $status_label ); ?></span>
            <?php endif; ?>
        </a>
        <div class="pp-anime-info">
            <h3 class="pp-anime-title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
            <?php if ( $score > 0 ) : ?>
                <div class="pp-anime-score">⭐ <?php echo esc_html( number_format( $score, 1 ) ); ?></div>
            <?php endif; ?>
            <?php if ( $status === 'watching' && $total_ep > 0 ) :
                $percent = min( 100, round( $watched_ep / $total_ep * 100 ) );
            ?>
                <div class="pp-anime-progress">
                    <div class="pp-anime-progress-bar"><div class="pp-anime-progress-fill" style="width:<?php echo (float) $percent; ?>%"></div></div>
                    <div class="pp-anime-progress-text"><?php echo (int) $watched_ep; ?> / <?php echo (int) $total_ep; ?></div>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
}
endif;
