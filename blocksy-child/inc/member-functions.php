<?php
/**
 * 會員相關 helper：輔助函式 / 點數系統 / 等級系統 / cooldown / 每日登入
 *
 * @package weixiaoacg
 * @subpackage Member
 *
 * 註：此檔案內函式未來會逐步搬到 plugin（anime-sync-pro/includes/）。
 * 目前留 theme 是因為 functions.php 重構期間先集中管理。
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   輔助函式
   ============================================================ */
function weixiaoacg_get_user_level_int(): int {
    if (!is_user_logged_in()) return 0;
    $uid = get_current_user_id();
    $lv  = (int)get_user_meta($uid,'weixiaoacg_user_level',true);
    if (!$lv && function_exists('um_user')) {
        $role = um_user('role');
        $lv = match($role) { 'weixiaoacg_vip'=>3,'weixiaoacg_pro'=>2,default=>1 };
    }
    return $lv ?: 1;
}

function weixiaoacg_get_user_points(int $uid=0): int {
    return (int)get_user_meta($uid?:get_current_user_id(),'weixiaoacg_points',true);
}

if (!function_exists('weixiaoacg_get_news_thumb')) {
    function weixiaoacg_get_news_thumb(int $post_id, string $size='news-thumb'): string {
        if ($url = get_the_post_thumbnail_url($post_id,$size)) return $url;
        $c = get_post($post_id)->post_content ?? '';
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/',$c,$m)) return $m[1];
        return function_exists('get_field') ? (get_field('weixiaoacg_cover_url',$post_id)?:'') : '';
    }
}

if (!function_exists('weixiaoacg_get_anilist'))  { function weixiaoacg_get_anilist($id)     { return null; } }
if (!function_exists('weixiaoacg_get_bangumi'))  { function weixiaoacg_get_bangumi($id,$t=2){ return null; } }

if (!function_exists('weixiaoacg_acf')) {
    function weixiaoacg_acf(string $key, $post_id = false, $default = '') {
        if (!function_exists('get_field')) return $default;
        $v = get_field($key, $post_id);
        return ($v === null || $v === false || $v === '') ? $default : $v;
    }
}

/* ============================================================
   共用判斷：是否為會員中心頁面
   ============================================================ */
if (!function_exists('smacg_is_member_page')) {
    function smacg_is_member_page(): bool {
        if (!function_exists('um_is_core_page')) return is_page_template('page-member.php');
        return um_is_core_page('user')
            || get_query_var('um_user')
            || is_page_template('page-member.php');
    }
}

/* ============================================================
   積分 / 等級系統
   ============================================================ */
function smacg_get_levels(): array {
    return [
        ['min'=>0,    'label'=>'🌱 新手',   'key'=>'newbie'],
        ['min'=>100,  'label'=>'⭐ 動漫迷', 'key'=>'lover'],
        ['min'=>500,  'label'=>'💫 老手',   'key'=>'veteran'],
        ['min'=>2000, 'label'=>'🔥 狂熱者', 'key'=>'fanatic'],
        ['min'=>5000, 'label'=>'👑 大師',   'key'=>'master'],
    ];
}

function smacg_get_user_level(int $uid): array {
    $pts = (int)get_user_meta($uid,'anime_total_points',true);
    $levels = smacg_get_levels(); $cur = $levels[0]; $next = null;
    foreach ($levels as $i => $l) { if ($pts >= $l['min']) { $cur = $l; $next = $levels[$i+1] ?? null; } }
    $pct = 100;
    if ($next) { $r = $next['min']-$cur['min']; $e = $pts-$cur['min']; $pct = $r > 0 ? min(100,round($e/$r*100)) : 100; }
    return ['points'=>$pts,'current'=>$cur,'next'=>$next,'progress_pct'=>$pct];
}

function smacg_add_points(int $uid, int $pts, string $reason=''): void {
    if ($pts <= 0 || !$uid) return;
    update_user_meta($uid,'anime_total_points',(int)get_user_meta($uid,'anime_total_points',true)+$pts);
    $log = json_decode(get_user_meta($uid,'anime_points_log',true)?:'[]',true);
    $log[] = ['points'=>$pts,'reason'=>$reason,'time'=>time()];
    if (count($log) > 100) $log = array_slice($log,-100);
    update_user_meta($uid,'anime_points_log',wp_json_encode($log));
}

function smacg_check_cooldown(int $uid, string $action, int $post_id): bool {
    $key = "smacg_cd_{$action}_{$post_id}";
    if (time()-(int)get_user_meta($uid,$key,true) < DAY_IN_SECONDS) return false;
    update_user_meta($uid,$key,time());
    return true;
}

/* ============================================================
   留言加分
   ============================================================ */
add_action('comment_post', function($cid,$approved) {
    if ($approved !== 1) return;
    $c = get_comment($cid); $uid = (int)$c->user_id;
    if ($uid && smacg_check_cooldown($uid,'comment',(int)$c->comment_post_ID))
        smacg_add_points($uid, SMACG_POINT_COMMENT, "comment:{$c->comment_post_ID}");
}, 10, 2);

/* ============================================================
   每日登入積分（UM hook）
   ============================================================ */
add_action('um_user_login', function($uid) {
    $uid = (int)$uid; if (!$uid) return;
    $today = date('Y-m-d');
    if ((string)get_user_meta($uid,'smacg_last_login_date',true) !== $today) {
        update_user_meta($uid,'smacg_last_login_date',$today);
        smacg_add_points($uid, SMACG_POINT_LOGIN, 'daily_login');
    }
});

/* ============================================================
   watchlist 排序 helper
   ============================================================ */
if (!function_exists('smacg_sort_watchlist')) {
    function smacg_sort_watchlist(array $list, $sort): array {
        usort($list, fn($a, $b) => match ($sort) {
            'title'    => strcmp(get_the_title($a['post_id']), get_the_title($b['post_id'])),
            'progress' => (int)$b['progress'] <=> (int)$a['progress'],
            'year'     => (int) get_post_meta($b['post_id'], 'anime_season_year', true)
                      <=> (int) get_post_meta($a['post_id'], 'anime_season_year', true),
            default    => strcmp($b['updated'] ?? '', $a['updated'] ?? ''),
        });
        return $list;
    }
}
