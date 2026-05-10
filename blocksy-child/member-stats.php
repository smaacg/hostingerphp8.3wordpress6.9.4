<?php
/**
 * Member Center - Stats & Data Layer
 * Version: 2.0.0
 *
 * 所有資料抓取 / 統計計算集中於此。重點：
 * - 一律批次預載 post（解 N+1）
 * - 觀看時數使用 anime_duration 真實值，fallback 24
 * - 收藏列入獨立分類，不再誤判為「想看」
 */
if (!defined('ABSPATH')) exit;

/* ===== 等級 ===== */
function smacg_calc_level($points) {
    // 每級門檻：100, 300, 600, 1000, 1500, 2100, 2800, 3600, 4500, 5500...
    $thresholds = [0,100,300,600,1000,1500,2100,2800,3600,4500,5500];
    $level = 1; $cur = 0; $next = 100;
    foreach ($thresholds as $i => $t) {
        if ($points >= $t) { $level = $i + 1; $cur = $t; $next = $thresholds[$i+1] ?? $t + 1000; }
        else break;
    }
    $span    = max(1, $next - $cur);
    $percent = min(100, round(($points - $cur) / $span * 100));
    $titles  = ['新手','見習','愛好者','資深','達人','專家','大師','傳奇','神級','至尊','超凡'];
    return [
        'level'   => $level,
        'current' => $cur,
        'next'    => $next,
        'percent' => $percent,
        'title'   => $titles[$level-1] ?? '至尊',
    ];
}

/* ===== 會員方案 ===== */
function smacg_get_plan_label($user) {
    $roles = (array) $user->roles;
    $map = [
        'um_vip'        => 'VIP 會員',
        'um_premium'    => '進階會員',
        'administrator' => '管理員',
        'editor'        => '編輯',
        'subscriber'    => '一般會員',
    ];
    foreach ($map as $r => $label) {
        if (in_array($r, $roles, true)) return $label;
    }
    return '一般會員';
}

/* ===== 清單建構（修復收藏歸類）===== */
function smacg_build_watchlist($uid) {
    if (!class_exists('Anime_Sync_User_Status_Manager')) return [];
    $mgr  = new Anime_Sync_User_Status_Manager();
    $rows = $mgr->get_user_list($uid);
    if (empty($rows)) return [];

    // 批次預載 post（解 N+1）
    $ids = array_column($rows, 'anime_id');
    smacg_prime_posts($ids);

    $list = [];
    foreach ($rows as $r) {
        $pid = (int) $r['anime_id'];
        if (get_post_status($pid) !== 'publish') continue;

        $status    = $r['status'] ?? '';   // 'want'|'watching'|'completed'|'dropped'|''
        $favorited = !empty($r['favorited']);
        $fullclear = !empty($r['fullcleared']);

        // 修復：純收藏（無 status）不再誤判為 planned
        if ($status === '') {
            if (!$favorited && !$fullclear) continue;
            $display = $favorited ? 'favorited' : 'completed';
        } else {
            $display = $status;
        }

        $list[] = [
            'post_id'   => $pid,
            'status'    => $display,
            'favorited' => $favorited,
            'fullclear' => $fullclear,
            'progress'  => (int) ($r['progress'] ?? 0),
            'note'      => $r['note'] ?? '',
            'updated'   => $r['updated_at'] ?? '',
        ];
    }
    return $list;
}

/* 批次預載 post（避免 get_the_title 等觸發 N 次 SQL）*/
function smacg_prime_posts(array $ids) {
    $ids = array_unique(array_filter(array_map('intval', $ids)));
    if (!$ids) return;
    _prime_post_caches($ids, true, true); // WP core，會一次抓 post + meta + term
}

/* ===== 評分（批次預載）===== */
function smacg_get_user_ratings($uid) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'anime_ratings';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT anime_id, overall_score, story_score, art_score, sound_score,
                character_score, enjoyment_score, review_text, updated_at
         FROM {$tbl} WHERE user_id = %d ORDER BY updated_at DESC",
        $uid
    ), ARRAY_A);
    if (!$rows) return [];

    smacg_prime_posts(array_column($rows, 'anime_id'));
    return $rows;
}

/* ===== 點數紀錄 ===== */
function smacg_get_points_log($uid, $limit = 50) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'smacg_points_log';
    // 表不存在則回空，避免錯誤
    if ($wpdb->get_var("SHOW TABLES LIKE '{$tbl}'") !== $tbl) return [];
    return $wpdb->get_results($wpdb->prepare(
        "SELECT change_value, reason, created_at FROM {$tbl}
         WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
        $uid, $limit
    ), ARRAY_A) ?: [];
}

/* ===== 最近留言 ===== */
function smacg_get_recent_comments($uid, $limit = 5) {
    return get_comments([
        'user_id' => $uid,
        'number'  => $limit,
        'status'  => 'approve',
        'orderby' => 'comment_date',
        'order'   => 'DESC',
    ]);
}

/* ===========================================================
 *  統計核心：一次走訪 watchlist + ratings，產出所有圖表資料
 * =========================================================== */
function smacg_calc_member_stats($watchlist, $ratings) {
    // 計數
    $counts = ['all'=>0,'watching'=>0,'completed'=>0,'want'=>0,'favorited'=>0,'dropped'=>0];
    $genre_map = $studio_map = $year_map = [];
    $total_min = 0;

    foreach ($watchlist as $w) {
        $counts['all']++;
        if (isset($counts[$w['status']])) $counts[$w['status']]++;
        if ($w['favorited'] && $w['status'] !== 'favorited') $counts['favorited']++; // 重疊計入

        // 只計算「已看完」的觀看時數
        if ($w['status'] === 'completed' || $w['fullclear']) {
            $pid = $w['post_id'];
            $ep  = (int) get_post_meta($pid, 'anime_episodes', true) ?: 12;
            $dur = (int) get_post_meta($pid, 'anime_duration', true) ?: 24;
            $total_min += $ep * $dur;
        } elseif ($w['status'] === 'watching') {
            $pid = $w['post_id'];
            $dur = (int) get_post_meta($pid, 'anime_duration', true) ?: 24;
            $total_min += $w['progress'] * $dur;
        }

        // 類型 / 製作公司 / 年代
        $pid = $w['post_id'];
        $genres = wp_get_post_terms($pid, 'genre', ['fields' => 'names']);
        if (!is_wp_error($genres)) {
            foreach ($genres as $g) $genre_map[$g] = ($genre_map[$g] ?? 0) + 1;
        }
        $studios = wp_get_post_terms($pid, 'anime_studio_tax', ['fields' => 'names']);
        if (is_wp_error($studios) || !$studios) {
            $meta = get_post_meta($pid, 'anime_studios', true);
            $studios = $meta ? array_map('trim', explode(',', $meta)) : [];
        }
        foreach ($studios as $s) if ($s) $studio_map[$s] = ($studio_map[$s] ?? 0) + 1;

        $year = (int) get_post_meta($pid, 'anime_season_year', true);
        if ($year) {
            $bucket = $year < 2000 ? '~1999' : (intval($year/10)*10) . 's';
            $year_map[$bucket] = ($year_map[$bucket] ?? 0) + 1;
        }
    }

    // 評分統計
    $rcount = count($ratings);
    $rsum = 0; $dist = array_fill(1, 10, 0);
    $score_rows = [];
    foreach ($ratings as $r) {
        $s = (float) $r['overall_score'];
        $rsum += $s;
        $bucket = max(1, min(10, (int) ceil($s)));
        $dist[$bucket]++;
        $score_rows[] = ['post_id' => (int)$r['anime_id'], 'score' => $s];
    }
    usort($score_rows, fn($a,$b)=>$b['score']<=>$a['score']);
    $top3    = array_slice($score_rows, 0, 3);
    $bottom3 = array_slice(array_reverse($score_rows), 0, 3);

    // 排序 genre / studio / year
    arsort($genre_map); arsort($studio_map); ksort($year_map);

    $genre_total = array_sum($genre_map) ?: 1;
    $genres_top = [];
    foreach (array_slice($genre_map, 0, 8, true) as $name => $c) {
        $genres_top[] = ['name'=>$name, 'count'=>$c, 'percent'=>round($c / $genre_total * 100, 1)];
    }
    $studios_top = [];
    foreach (array_slice($studio_map, 0, 5, true) as $name => $c) {
        $studios_top[] = ['name'=>$name, 'count'=>$c];
    }
    $years_arr = [];
    foreach ($year_map as $y => $c) $years_arr[] = ['year'=>$y, 'count'=>$c];

    return [
        'counts'     => $counts,
        'watch_time' => [
            'minutes' => $total_min,
            'hours'   => floor($total_min / 60),
            'days'    => round($total_min / 1440, 1),
        ],
        'rating' => [
            'count'        => $rcount,
            'avg'          => $rcount ? round($rsum / $rcount, 2) : 0,
            'distribution' => $dist,
            'top3'         => $top3,
            'bottom3'      => $bottom3,
        ],
        'genres'  => $genres_top,
        'studios' => $studios_top,
        'years'   => $years_arr,
    ];
}
