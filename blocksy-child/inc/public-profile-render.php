<?php
/**
 * Public Profile - Render Functions
 *
 * @package weixiaoacg
 * @version 1.0.0 (2026-05-13)
 *
 * 公開個人頁的所有 render 函式集中於此。
 * 受限於隱私 + 訪客身份，僅顯示「公開可見」的內容。
 *
 * 提供函式：
 *   smacg_pp_render_hero( $user, $args )
 *   smacg_pp_render_overview( $user, $watchlist, $stats, $can_w, $can_r )
 *   smacg_pp_render_watchlist( $watchlist )
 *   smacg_pp_render_ratings( $ratings )
 *   smacg_pp_render_badges( $uid, $stats )
 *   smacg_pp_render_activity( $activity )
 *
 * 共用卡片：reuse smacg_render_anime_card()（member-render.php 已定義）
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   Hero
   ============================================================ */
function smacg_pp_render_hero( $user, $args ) {
    $a = $args;
    ?>
    <section class="pp-hero">
        <div class="pp-hero-avatar">
            <img src="<?php echo esc_url( $a['avatar'] ); ?>"
                 alt="<?php echo esc_attr( $a['display'] ); ?>"
                 loading="lazy">
        </div>

        <div class="pp-hero-info">
            <h1 class="pp-hero-name">
                <?php echo esc_html( $a['display'] ); ?>
                <span class="pp-plan-badge"><?php echo esc_html( $a['plan'] ); ?></span>
            </h1>

            <p class="pp-hero-meta">
                <span><i class="fa-solid fa-calendar-days"></i> 加入於 <?php echo esc_html( $a['reg_date'] ); ?></span>
                <?php if ( $a['can_view_email'] && $a['email_display'] ) : ?>
                    <span><i class="fa-solid fa-envelope"></i> <?php echo esc_html( $a['email_display'] ); ?></span>
                <?php endif; ?>
                <span><i class="fa-solid fa-coins"></i> <?php echo number_format( $a['points'] ); ?> 點</span>
            </p>

            <?php if ( ! empty( $a['bio'] ) ) : ?>
                <p class="pp-hero-bio"><?php echo esc_html( $a['bio'] ); ?></p>
            <?php endif; ?>

            <div class="pp-level-bar" title="Lv.<?php echo (int) $a['lvl_info']['level']; ?>　<?php echo (int) $a['points']; ?> 點">
                <div class="pp-level-fill" style="width:<?php echo (int) $a['lvl_info']['percent']; ?>%"></div>
                <span class="pp-level-text">
                    Lv.<?php echo (int) $a['lvl_info']['level']; ?> · <?php echo esc_html( $a['lvl_info']['title'] ); ?>
                    （<?php echo (int) $a['points']; ?> / <?php echo (int) $a['lvl_info']['next']; ?>）
                </span>
            </div>

            <?php /* 互動按鈕區（Phase 1B 會啟用「追蹤」） */ ?>
            <div class="pp-hero-actions">
                <?php if ( $a['is_owner'] ) : ?>
                    <a href="<?php echo esc_url( home_url( '/mc/' ) ); ?>" class="pp-btn pp-btn-primary">
                        <i class="fa-solid fa-gear"></i> 編輯資料
                    </a>
                <?php elseif ( $a['is_logged_in'] ) : ?>
                    <button class="pp-btn pp-btn-primary pp-btn-follow" disabled title="即將推出">
                        <i class="fa-solid fa-user-plus"></i> 追蹤
                    </button>
                <?php else : ?>
                    <a href="<?php echo esc_url( wp_login_url( get_permalink() ?: home_url( '/' ) ) ); ?>"
                       class="pp-btn pp-btn-primary">
                        <i class="fa-solid fa-right-to-bracket"></i> 登入後追蹤
                    </a>
                <?php endif; ?>

                <button class="pp-btn pp-btn-ghost pp-btn-share" type="button"
                        data-url="<?php echo esc_attr( smacg_get_public_profile_url( $user ) ); ?>"
                        data-title="<?php echo esc_attr( $a['display'] ); ?>">
                    <i class="fa-solid fa-share-nodes"></i> 分享
                </button>
            </div>

            <?php /* 統計 chips */ ?>
            <div class="pp-hero-stats">
                <div><b><?php echo (int) ( $a['stats']['counts']['watching'] ?? 0 ); ?></b><span>追番中</span></div>
                <div><b><?php echo (int) ( $a['stats']['counts']['completed'] ?? 0 ); ?></b><span>已看完</span></div>
                <div><b><?php echo (int) ( $a['stats']['counts']['favorited'] ?? 0 ); ?></b><span>收藏</span></div>
                <div><b><?php echo (int) ( $a['stats']['rating']['count'] ?? 0 ); ?></b><span>評分</span></div>
                <div><b><?php echo (int) ( $a['stats']['watch_time']['days'] ?? 0 ); ?>天</b><span>觀看時數</span></div>
            </div>
        </div>
    </section>
    <?php
}

/* ============================================================
   Overview (總覽 tab)
   ============================================================ */
function smacg_pp_render_overview( $user, $watchlist, $stats, $can_w, $can_r ) {
    $uid = (int) $user->ID;
    ?>
    <div class="pp-overview">

        <?php if ( $can_w && ! empty( $watchlist ) ) : ?>
            <?php
            // 最近更新 8 部
            $recent = array_slice( $watchlist, 0, 8 );
            ?>
            <div class="pp-section">
                <h2 class="pp-section-title">
                    <i class="fa-solid fa-clock-rotate-left"></i> 最近更新
                </h2>
                <div class="pp-grid">
                    <?php foreach ( $recent as $w ) : ?>
                        <?php smacg_pp_render_anime_card( $w['post_id'], $w ); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $stats['top_genres'] ) ) : ?>
            <div class="pp-section">
                <h2 class="pp-section-title">
                    <i class="fa-solid fa-tags"></i> 最常看的類型
                </h2>
                <div class="pp-tag-cloud">
                    <?php
                    $top = array_slice( $stats['top_genres'], 0, 10, true );
                    foreach ( $top as $name => $count ) :
                    ?>
                        <span class="pp-tag">
                            <?php echo esc_html( $name ); ?>
                            <em><?php echo (int) $count; ?></em>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( empty( $watchlist ) && empty( $stats['top_genres'] ) ) : ?>
            <div class="pp-empty">
                <i class="fa-solid fa-folder-open"></i>
                <p>這位使用者尚未有公開內容</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/* ============================================================
   Watchlist (清單 tab)
   ============================================================ */
function smacg_pp_render_watchlist( $watchlist ) {
    if ( empty( $watchlist ) ) {
        echo '<div class="pp-empty"><i class="fa-solid fa-list"></i><p>清單為空</p></div>';
        return;
    }

    // 統計各 status
    $by_status = [
        'all'       => count( $watchlist ),
        'watching'  => 0,
        'completed' => 0,
        'favorited' => 0,
        'want'      => 0,
        'dropped'   => 0,
    ];
    foreach ( $watchlist as $w ) {
        if ( isset( $by_status[ $w['status'] ] ) ) $by_status[ $w['status'] ]++;
        if ( $w['favorited'] && $w['status'] !== 'favorited' ) $by_status['favorited']++;
    }

    $filters = [
        'all'       => [ '全部',   '🎬' ],
        'watching'  => [ '追番中', '▶️' ],
        'completed' => [ '已看完', '✅' ],
        'favorited' => [ '收藏',   '⭐' ],
        'want'      => [ '想看',   '📌' ],
    ];
    ?>
    <div class="pp-watchlist">
        <div class="pp-filter-bar">
            <?php foreach ( $filters as $key => $info ) :
                [ $label, $icon ] = $info;
                $count = $by_status[ $key ] ?? 0;
                $active = $key === 'all' ? ' active' : '';
            ?>
                <button class="pp-filter-btn<?php echo $active; ?>" data-filter="<?php echo esc_attr( $key ); ?>">
                    <?php echo $icon; ?> <?php echo esc_html( $label ); ?>
                    <em><?php echo (int) $count; ?></em>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="pp-grid" id="pp-watchlist-grid">
            <?php foreach ( $watchlist as $w ) : ?>
                <?php smacg_pp_render_anime_card( $w['post_id'], $w ); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/* ============================================================
   Ratings (評分 tab)
   ============================================================ */
function smacg_pp_render_ratings( $ratings ) {
    if ( empty( $ratings ) ) {
        echo '<div class="pp-empty"><i class="fa-solid fa-star"></i><p>尚未評分</p></div>';
        return;
    }

    // 預設依 overall_score DESC
    usort( $ratings, fn( $a, $b ) => (float) $b['overall_score'] <=> (float) $a['overall_score'] );

    ?>
    <div class="pp-ratings">
        <p class="pp-section-desc">依評分從高到低排序，共 <?php echo count( $ratings ); ?> 部作品</p>
        <div class="pp-grid">
            <?php foreach ( $ratings as $r ) : ?>
                <?php smacg_pp_render_anime_card( (int) $r['anime_id'], [
                    'user_score' => (float) $r['overall_score'],
                ] ); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/* ============================================================
   Badges (徽章 tab)
   ============================================================ */
function smacg_pp_render_badges( $uid, $stats ) {
    $badges = [];

    $counts = $stats['counts'] ?? [];
    $rating_count = (int) ( $stats['rating']['count'] ?? 0 );
    $days = (int) ( $stats['watch_time']['days'] ?? 0 );

    // 動態徽章
    if ( ( $counts['completed'] ?? 0 ) >= 100 ) {
        $badges[] = [ '🏆', '百番達人', '看完 100 部以上' ];
    } elseif ( ( $counts['completed'] ?? 0 ) >= 50 ) {
        $badges[] = [ '🎖️', '半百達人', '看完 50 部以上' ];
    } elseif ( ( $counts['completed'] ?? 0 ) >= 10 ) {
        $badges[] = [ '⭐', '入門達人', '看完 10 部以上' ];
    }

    if ( $rating_count >= 100 ) {
        $badges[] = [ '⭐', '評分大師', '評分 100 部以上' ];
    } elseif ( $rating_count >= 30 ) {
        $badges[] = [ '✨', '評分愛好者', '評分 30 部以上' ];
    }

    if ( $days >= 30 ) {
        $badges[] = [ '⏰', '時間旅人', '觀看時數達 30 天' ];
    }

    if ( ( $counts['favorited'] ?? 0 ) >= 20 ) {
        $badges[] = [ '💖', '收藏家', '收藏 20 部以上' ];
    }

    // GamiPress 徽章（如果有裝）
    if ( function_exists( 'gamipress_get_user_achievements' ) ) {
        $gp = gamipress_get_user_achievements( [ 'user_id' => $uid ] );
        if ( ! empty( $gp ) ) {
            foreach ( array_slice( $gp, 0, 12 ) as $a ) {
                $thumb = get_the_post_thumbnail_url( $a->ID, 'thumbnail' );
                $title = get_the_title( $a->ID );
                $badges[] = [
                    'gp',
                    $title,
                    '',
                    $thumb,
                ];
            }
        }
    }

    if ( empty( $badges ) ) {
        echo '<div class="pp-empty"><i class="fa-solid fa-medal"></i><p>尚未獲得任何徽章</p></div>';
        return;
    }

    ?>
    <div class="pp-badges">
        <?php foreach ( $badges as $b ) :
            $icon = $b[0]; $title = $b[1]; $desc = $b[2] ?? ''; $thumb = $b[3] ?? '';
        ?>
            <div class="pp-badge">
                <div class="pp-badge-icon">
                    <?php if ( $icon === 'gp' && $thumb ) : ?>
                        <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                    <?php else : ?>
                        <?php echo esc_html( $icon ); ?>
                    <?php endif; ?>
                </div>
                <div class="pp-badge-info">
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( $desc ) : ?><p><?php echo esc_html( $desc ); ?></p><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/* ============================================================
   Activity (動態 tab)
   ============================================================ */
function smacg_pp_render_activity( $activity ) {
    if ( empty( $activity ) ) {
        echo '<div class="pp-empty"><i class="fa-solid fa-satellite-dish"></i><p>暫無動態</p></div>';
        return;
    }
    ?>
    <ul class="pp-timeline">
        <?php foreach ( $activity as $a ) :
            $type  = $a['type'] ?? 'default';
            $icon  = $a['icon'] ?? '📌';
            $title = $a['title'] ?? '';
            $meta  = $a['meta'] ?? '';
            $time  = $a['time'] ?? 0;
            $pid   = (int) ( $a['post_id'] ?? 0 );
        ?>
            <li class="pp-timeline-item pp-timeline-item--<?php echo esc_attr( $type ); ?>">
                <div class="pp-timeline-dot">
                    <span class="pp-timeline-icon"><?php echo esc_html( $icon ); ?></span>
                </div>
                <div class="pp-timeline-body">
                    <div class="pp-timeline-main">
                        <?php if ( $meta ) : ?>
                            <span class="pp-timeline-meta"><?php echo esc_html( $meta ); ?></span>
                        <?php endif; ?>
                        <?php if ( $pid && get_post_status( $pid ) === 'publish' ) : ?>
                            <a class="pp-timeline-target" href="<?php echo esc_url( get_permalink( $pid ) ); ?>">
                                <?php echo esc_html( $title ); ?>
                            </a>
                        <?php else : ?>
                            <span class="pp-timeline-target"><?php echo esc_html( $title ); ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="pp-timeline-time">
                        <?php echo esc_html( human_time_diff( $time, current_time( 'U' ) ) ); ?>前
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

/* ============================================================
   共用：動畫卡片（公開頁版，不顯示快速操作按鈕）
   ------------------------------------------------------------
   會員中心的卡片有 +1/完成/移除 按鈕，公開頁要把它們關掉。
   ============================================================ */
function smacg_pp_render_anime_card( $post_id, $args = [] ) {
    $post_id = (int) $post_id;
    if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) return;

    $title = get_the_title( $post_id );
    $url   = get_permalink( $post_id );
    $thumb = get_the_post_thumbnail_url( $post_id, 'weixiaoacg-thumb' )
          ?: get_the_post_thumbnail_url( $post_id, 'medium' );
    if ( ! $thumb && function_exists( 'weixiaoacg_acf' ) ) {
        $thumb = weixiaoacg_acf( 'weixiaoacg_cover_url', $post_id, '' );
    }

    $status     = $args['status']     ?? '';
    $favorited  = ! empty( $args['favorited'] );
    $progress   = (int) ( $args['progress'] ?? 0 );
    $user_score = isset( $args['user_score'] ) ? (float) $args['user_score'] : null;

    $total_ep = (int) get_post_meta( $post_id, 'anime_episodes', true );
    ?>
    <div class="pp-anime-card"
         data-pid="<?php echo $post_id; ?>"
         data-status="<?php echo esc_attr( $status ); ?>"
         data-favorited="<?php echo $favorited ? '1' : '0'; ?>"
         data-title="<?php echo esc_attr( mb_strtolower( $title ) ); ?>">
        <a href="<?php echo esc_url( $url ); ?>" class="pp-card-thumb">
            <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
            <?php else : ?>
                <div class="pp-card-no-image"><i class="fa-solid fa-image"></i></div>
            <?php endif; ?>

            <?php if ( $status ) : ?>
                <span class="pp-card-status pp-card-status--<?php echo esc_attr( $status ); ?>">
                    <?php
                    echo esc_html( [
                        'watching'  => '追番中',
                        'completed' => '已看完',
                        'favorited' => '收藏',
                        'want'      => '想看',
                        'dropped'   => '棄追',
                    ][ $status ] ?? $status );
                    ?>
                </span>
            <?php endif; ?>

            <?php if ( $favorited && $status !== 'favorited' ) : ?>
                <span class="pp-card-fav"><i class="fa-solid fa-heart"></i></span>
            <?php endif; ?>
        </a>

        <div class="pp-card-body">
            <h3 class="pp-card-title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>

            <?php if ( $user_score !== null ) : ?>
                <div class="pp-card-score">
                    <i class="fa-solid fa-star"></i>
                    <b><?php echo number_format( $user_score, 1 ); ?></b>
                    <span>/ 10</span>
                </div>
            <?php endif; ?>

            <?php if ( $total_ep > 0 && $progress > 0 && $status === 'watching' ) : ?>
                <div class="pp-card-progress">
                    <div class="pp-card-progress-bar">
                        <div class="pp-card-progress-fill"
                             style="width:<?php echo min( 100, round( $progress / $total_ep * 100 ) ); ?>%"></div>
                    </div>
                    <span class="pp-card-progress-text"><?php echo $progress; ?> / <?php echo $total_ep; ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
