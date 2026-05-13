<?php
/**
 * 會員相關 AJAX endpoints + 頭像上傳 + 頭像顯示 filters
 *
 * @package weixiaoacg
 * @subpackage Member
 *
 * Version: 2.2.0 (2026-05-13)
 *  - Batch B: 新增 wp_ajax_smacg_load_more_comments（留言分頁載入）
 *  - v2.2.0: 頭像上傳優化
 *    - 拿掉新帳號 24 小時鎖定（信任使用者）
 *    - 冷卻時間 5 分鐘 → 2 分鐘
 *    - 每日上限 3 次 → 5 次
 *    - 檔案大小 1 MB → 5 MB（前端會以 Cropper.js 壓縮至 ~400 KB 再上傳）
 *    - 新增 error_log() 上傳事件記錄
 *
 * 註：此檔案未來會整檔搬到 plugin（anime-sync-pro/public/）。
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   通用 AJAX：閱讀文章加分
   ============================================================ */
add_action('wp_ajax_smacg_read_article', function() {
    check_ajax_referer('smacg_nonce','nonce');
    $uid = (int)get_current_user_id(); $pid = (int)($_POST['post_id']??0);
    if ($uid && $pid && smacg_check_cooldown($uid,'read',$pid))
        smacg_add_points($uid, SMACG_POINT_READ, "read:{$pid}");
    wp_send_json_success();
});

/* ============================================================
   AJAX：提交詳細評分（5 維）
   ============================================================ */
add_action('wp_ajax_smacg_submit_rating_detail', function() {
    check_ajax_referer('smacg_nonce','nonce');
    $uid = get_current_user_id(); if (!$uid) wp_send_json_error(['msg'=>'請先登入才能評分'],401);
    $pid = (int)($_POST['post_id']??0);
    if (!$pid || get_post_type($pid) !== 'anime') wp_send_json_error(['msg'=>'無效的動漫 ID'],400);
    $keys = ['story','music','animation','voice']; $scores = [];
    foreach ($keys as $k) {
        $v = isset($_POST[$k]) ? (float)$_POST[$k] : null;
        if ($v === null || $v < 1 || $v > 10) wp_send_json_error(['msg'=>"「{$k}」分數無效，應介於 1–10"],400);
        $scores[$k] = round($v,1);
    }
    $avg = round(array_sum($scores)/count($scores),2);
    update_user_meta($uid,"smacg_rating_detail_{$pid}",array_merge($scores,['avg'=>$avg,'time'=>time()]));
    global $wpdb; $mk = "smacg_rating_detail_{$pid}";
    $all = $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key=%s",$mk));
    $tot = array_fill_keys($keys,0); $cnt = 0;
    foreach ($all as $raw) { $r = maybe_unserialize($raw); if (!is_array($r)) continue; foreach ($keys as $k) $tot[$k] += (float)($r[$k]??0); $cnt++; }
    if ($cnt > 0) {
        $s = []; foreach ($keys as $k) $s[$k] = round($tot[$k]/$cnt,1);
        $sa = round(array_sum($s)/count($s),1);
        foreach (['smacg_site_score'=>$sa,'smacg_site_score_story'=>$s['story'],'smacg_site_score_music'=>$s['music'],'smacg_site_score_animation'=>$s['animation'],'smacg_site_score_voice'=>$s['voice'],'smacg_site_score_count'=>$cnt] as $mk2=>$val)
            update_post_meta($pid,$mk2,$val);
        wp_send_json_success(['msg'=>'評分成功，感謝你的評價！','avg'=>$sa]+$s+['count'=>$cnt]);
    }
    wp_send_json_success(['msg'=>'評分成功！','avg'=>$avg]+$scores+['count'=>1]);
});

/* ============================================================
   AJAX：收藏切換
   ============================================================ */
add_action('wp_ajax_weixiaoacg_toggle_favorite', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $pid = (int)($_POST['post_id']??0); $uid = get_current_user_id();
    if (!$pid || !$uid) wp_send_json_error(['msg'=>'無效請求']);
    $favs = get_user_meta($uid,'weixiaoacg_favorites',true)?:[];
    $k = array_search($pid,$favs);
    if ($k !== false) { unset($favs[$k]); $act = 'removed'; } else { $favs[] = $pid; $act = 'added'; }
    update_user_meta($uid,'weixiaoacg_favorites',array_values($favs));
    wp_send_json_success(['action'=>$act,'count'=>count($favs)]);
});

/* ============================================================
   AJAX：進度更新
   ============================================================ */
add_action('wp_ajax_weixiaoacg_update_progress', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $pid = (int)($_POST['post_id']??0); $uid = get_current_user_id();
    if (!$pid || !$uid) wp_send_json_error(['msg'=>'無效請求']);
    $d = ['progress'=>(int)($_POST['progress']??0),'watch_status'=>sanitize_text_field($_POST['watch_status']??''),'updated_at'=>time()];
    update_user_meta($uid,"weixiaoacg_progress_{$pid}",$d);
    wp_send_json_success($d);
});

/* ============================================================
   AJAX：站內搜尋
   ============================================================ */
add_action('wp_ajax_weixiaoacg_search',        'weixiaoacg_ajax_search');
add_action('wp_ajax_nopriv_weixiaoacg_search', 'weixiaoacg_ajax_search');
function weixiaoacg_ajax_search() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $kw   = sanitize_text_field($_POST['query']??$_POST['keyword']??'');
    $type = sanitize_text_field($_POST['type']??'all');
    if (strlen($kw) < 2) wp_send_json_error(['msg'=>'關鍵字太短']);
    $types = match($type) {'anime'=>['anime'],'manga'=>['manga'],'character'=>['character'],'va'=>['voice-actor'],'music'=>['music'],default=>['anime','manga','novel','game','character','voice-actor','post']};
    $q = new WP_Query(['s'=>$kw,'post_type'=>$types,'posts_per_page'=>12,'post_status'=>'publish']);
    $res = [];
    while ($q->have_posts()) { $q->the_post(); $pid = get_the_ID();
        $res[] = [
            'id'       => $pid,
            'title'    => get_the_title(),
            'title_zh' => weixiaoacg_acf('weixiaoacg_title_zh', $pid, get_the_title()),
            'type'     => get_post_type(),
            'url'      => get_permalink(),
            'thumb'    => get_the_post_thumbnail_url($pid,'weixiaoacg-thumb') ?: weixiaoacg_acf('weixiaoacg_cover_url', $pid),
            'score'    => weixiaoacg_acf('weixiaoacg_score_anilist', $pid, 0),
        ];
    }
    wp_reset_postdata(); wp_send_json_success($res);
}

/* ============================================================
   AJAX：簡易評分
   ============================================================ */
add_action('wp_ajax_weixiaoacg_submit_rating', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $pid = (int)($_POST['post_id']??0); $score = (float)($_POST['score']??0); $uid = get_current_user_id();
    if (!$pid || !$uid) wp_send_json_error(['msg'=>'請先登入']);
    if ($score < 1 || $score > 10) wp_send_json_error(['msg'=>'評分範圍 1–10']);
    if (function_exists('yasr_save_visitor_vote')) wp_send_json_success(['msg'=>'評分成功','yasr'=>yasr_save_visitor_vote($pid,$score)]);
    update_user_meta($uid,"weixiaoacg_rating_{$pid}",$score);
    wp_send_json_success(['msg'=>'評分成功']);
});

/* ============================================================
   AJAX：管理員重新同步 Bangumi
   ============================================================ */
add_action('wp_ajax_weixiaoacg_resync_bangumi', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['msg'=>'權限不足']);
    class_exists('Anime_Sync_API_Handler') ? (new Anime_Sync_API_Handler())->ajax_resync_bangumi() : wp_send_json_error(['msg'=>'API Handler 類別未載入']);
});

/* ============================================================
   AJAX：訪客 → AJAX 登入
   ============================================================ */
add_action('wp_ajax_nopriv_weixiaoacg_ajax_login', function() {
    check_ajax_referer('weixiaoacg_nonce','nonce');
    $u = sanitize_user($_POST['log']??''); $p = $_POST['pwd']??'';
    if (!$u || !$p) wp_send_json_error(['msg'=>'請輸入帳號和密碼']);
    $user = wp_signon(['user_login'=>$u,'user_password'=>$p,'remember'=>!empty($_POST['rememberme'])],is_ssl());
    is_wp_error($user) ? wp_send_json_error(['msg'=>'帳號或密碼錯誤，請再試一次']) : wp_send_json_success(['msg'=>'登入成功','redirect'=>home_url('/')]);
});

/* ============================================================
   AJAX：訪客 → AJAX 註冊
   ============================================================ */
add_action('wp_ajax_nopriv_weixiaoacg_ajax_register', function() {
    check_ajax_referer('weixiaoacg_nonce', 'nonce');
    $username = sanitize_user($_POST['user_login'] ?? '');
    $email    = sanitize_email($_POST['user_email'] ?? '');
    $password = $_POST['user_password'] ?? '';
    if (!$username) wp_send_json_error(['msg' => '請輸入使用者名稱']);
    if (!$email || !is_email($email)) wp_send_json_error(['msg' => '請輸入有效的電子郵件']);
    if (!$password) wp_send_json_error(['msg' => '請輸入密碼']);
    if (username_exists($username)) wp_send_json_error(['msg' => '此使用者名稱已被使用']);
    if (email_exists($email)) wp_send_json_error(['msg' => '此電子郵件已被註冊']);
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) wp_send_json_error(['msg' => $user_id->get_error_message()]);
    if (function_exists('um_fetch_user')) {
        update_user_meta($user_id, 'account_status', 'approved');
        (new WP_User($user_id))->set_role('subscriber');
    }
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, false, is_ssl());
    wp_send_json_success(['msg' => '註冊成功！', 'redirect' => home_url('/')]);
});

/* ============================================================
   AJAX：查詢我的評分
   ============================================================ */
add_action('wp_ajax_smacg_get_my_rating', function() {
    $post_id = isset($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0;
    if ($post_id <= 0) wp_send_json_error(['msg' => 'invalid post_id'], 400);
    $uid = get_current_user_id();
    if (!$uid) wp_send_json_error(['msg' => 'not logged in'], 401);
    $detail = get_user_meta($uid, "smacg_rating_detail_{$post_id}", true);
    if (!is_array($detail)) wp_send_json_success(['rated' => false]);
    wp_send_json_success([
        'rated'     => true,
        'story'     => (float) ($detail['story']     ?? 5),
        'music'     => (float) ($detail['music']     ?? 5),
        'animation' => (float) ($detail['animation'] ?? 5),
        'voice'     => (float) ($detail['voice']     ?? 5),
        'avg'       => (float) ($detail['avg']       ?? 5),
    ]);
});

/* ============================================================
   會員中心：member.js / member.css enqueue
   ============================================================ */
add_action('wp_enqueue_scripts', function () {
    if (!smacg_is_member_page()) return;

    $js_path  = get_stylesheet_directory() . '/assets/js/member.js';
    $css_path = get_stylesheet_directory() . '/assets/css/member.css';

    if (file_exists($js_path)) {
        wp_enqueue_script(
            'smacg-member',
            get_stylesheet_directory_uri() . '/assets/js/member.js',
            ['jquery'],
            filemtime($js_path),
            true
        );
        wp_localize_script('smacg-member', 'smacgMember', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smacg_member_nonce'),
        ]);

        // P0-2: 呼叫 /wp-json/weixiaoacg/v1/user-status/ 用
        wp_localize_script('smacg-member', 'wpApiSettings', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    if (file_exists($css_path)) {
        wp_enqueue_style(
            'smacg-member-css',
            get_stylesheet_directory_uri() . '/assets/css/member.css',
            [],
            filemtime($css_path)
        );
    }
}, 20);

/* ============================================================
   會員中心：載入更多清單／評分
   ============================================================ */
add_action('wp_ajax_smacg_member_loadmore', function () {
    check_ajax_referer('smacg_member_nonce', 'nonce');

    $uid = get_current_user_id();
    if (!$uid) wp_send_json_error(['msg' => '請先登入'], 401);

    $type   = sanitize_key($_POST['type']   ?? '');
    $offset = max(0, (int) ($_POST['offset'] ?? 0));
    $limit  = min(50, max(1, (int) ($_POST['limit'] ?? 20)));
    $filter = sanitize_key($_POST['filter'] ?? 'all');
    $sort   = sanitize_key($_POST['sort']   ?? 'updated');
    $search = sanitize_text_field($_POST['search'] ?? '');

    // 這兩個檔案在 functions.php 已 require_once，這裡再保險一次（require_once 不會重複載入）
    require_once get_stylesheet_directory() . '/inc/member-stats.php';
    require_once get_stylesheet_directory() . '/inc/member-render.php';

    if ($type === 'watchlist') {
        $items = smacg_build_watchlist($uid);

        if ($filter === 'favorited') {
            $items = array_filter($items, fn($w) => !empty($w['favorited']));
        } elseif ($filter !== 'all' && $filter !== '') {
            $items = array_filter($items, fn($w) => ($w['status'] ?? '') === $filter);
        }

        if ($search !== '') {
            $q = mb_strtolower($search);
            $items = array_filter($items, fn($w) => str_contains(mb_strtolower(get_the_title($w['post_id'])), $q));
        }
        $items = smacg_sort_watchlist(array_values($items), $sort);

    } elseif ($type === 'ratings') {
        $items = smacg_get_user_ratings($uid);
        if ($search !== '') {
            $q = mb_strtolower($search);
            $items = array_filter($items, fn($r) => str_contains(mb_strtolower(get_the_title($r['anime_id'])), $q));
        }
        usort($items, fn($a, $b) => match ($sort) {
            'score-desc' => (float)$b['overall_score'] <=> (float)$a['overall_score'],
            'score-asc'  => (float)$a['overall_score'] <=> (float)$b['overall_score'],
            default      => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''),
        });
    } else {
        wp_send_json_error(['msg' => '參數錯誤'], 400);
    }

    $items = array_values($items);
    $slice = array_slice($items, $offset, $limit);

    ob_start();
    foreach ($slice as $row) {
        if ($type === 'watchlist') {
            smacg_render_anime_card($row['post_id'], $row);
        } else {
            smacg_render_anime_card((int)$row['anime_id'], ['user_score' => (float)$row['overall_score']]);
        }
    }
    wp_send_json_success([
        'html'     => ob_get_clean(),
        'loaded'   => $offset + count($slice),
        'total'    => count($items),
        'has_more' => ($offset + count($slice)) < count($items),
    ]);
});

/* ============================================================
   會員中心：更新基本資料 AJAX
   ============================================================ */
add_action('wp_ajax_smacg_update_profile', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['msg' => '請先登入']);
    }
    check_ajax_referer('smacg_update_profile', 'nonce');

    $uid     = get_current_user_id();
    $display = sanitize_text_field($_POST['display_name'] ?? '');
    $bio     = sanitize_textarea_field($_POST['description'] ?? '');

    if ($display === '') {
        wp_send_json_error(['msg' => '顯示名稱不可為空']);
    }
    if (mb_strlen($display) > 40 || mb_strlen($bio) > 300) {
        wp_send_json_error(['msg' => '欄位長度超出限制']);
    }

    $r = wp_update_user([
        'ID'           => $uid,
        'display_name' => $display,
        'nickname'     => $display,  // 自動同步暱稱 = 顯示名稱
        'description'  => $bio,
    ]);

    if (is_wp_error($r)) {
        wp_send_json_error(['msg' => $r->get_error_message()]);
    }
    if (function_exists('UM')) UM()->user()->remove_cache($uid);

    wp_send_json_success(['msg' => '已儲存 ✓']);
});

/* ============================================================
   P0-1: 隱私設定 AJAX handler
   ============================================================ */
add_action( 'wp_ajax_smacg_update_privacy', function () {
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'msg' => '請先登入' ] );
    check_ajax_referer( 'smacg_privacy', 'nonce' );
    $uid = get_current_user_id();

    $allowed = [ 'show_email', 'public_profile', 'public_watchlist', 'show_continue_watching' ];
    $current = function_exists( 'smacg_get_user_privacy' )
        ? smacg_get_user_privacy( $uid )
        : [];

    $key = sanitize_key( $_POST['key'] ?? '' );
    if ( ! in_array( $key, $allowed, true ) ) {
        wp_send_json_error( [ 'msg' => '無效參數' ] );
    }
    $current[ $key ] = ! empty( $_POST['value'] ) ? 1 : 0;

    update_user_meta( $uid, 'smacg_privacy', $current );
    wp_send_json_success( [ 'msg' => '已儲存 ✓', 'privacy' => $current ] );
} );

/* ============================================================
   Batch B (v2.1.0)：留言分頁載入
   - 由 smacg_render_comments() 產生的按鈕觸發
   - 每次回傳 SMACG_COMMENT_PAGE_SIZE 筆（預設 20）
   - 回傳純 <li> HTML 片段，前端 append 進 #mc-cmt-list
   ============================================================ */
add_action( 'wp_ajax_smacg_load_more_comments', function () {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'msg' => '請先登入' ], 401 );
    }
    check_ajax_referer( 'smacg_load_more_comments', 'nonce' );

    $uid    = get_current_user_id();
    $offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );

    // 頁面大小：與 render 層一致
    if ( ! defined( 'SMACG_COMMENT_PAGE_SIZE' ) ) {
        define( 'SMACG_COMMENT_PAGE_SIZE', 20 );
    }
    $limit = SMACG_COMMENT_PAGE_SIZE;

    // 總數（供前端更新進度文字）
    $total = (int) get_comments( [
        'user_id' => $uid,
        'status'  => 'approve',
        'count'   => true,
    ] );

    // 取下一批
    $cmts = get_comments( [
        'user_id' => $uid,
        'status'  => 'approve',
        'number'  => $limit,
        'offset'  => $offset,
        'orderby' => 'comment_date',
        'order'   => 'DESC',
    ] );

    ob_start();
    foreach ( $cmts as $c ) {
        printf(
            '<li><a href="%s"><b>%s</b><p>%s</p><small>%s</small></a></li>',
            esc_url( get_comment_link( $c ) ),
            esc_html( get_the_title( $c->comment_post_ID ) ),
            esc_html( wp_trim_words( $c->comment_content, 40 ) ),
            esc_html( mysql2date( 'Y-m-d H:i', $c->comment_date ) )
        );
    }
    $html = ob_get_clean();

    $loaded   = $offset + count( $cmts );
    $has_more = $loaded < $total;

    wp_send_json_success( [
        'html'     => $html,
        'loaded'   => $loaded,
        'total'    => $total,
        'has_more' => $has_more,
    ] );
} );

/* ============================================================
   頭像上傳 AJAX（業界標準版 v2.2.0）
   - 5 MB 上限（前端 Cropper.js 壓縮至 ~400 KB）/ 強制 400x400 / 85% 品質
   - 2 分鐘冷卻 / 每日 5 次（信任使用者，無新帳號鎖）
   - hash 檔名 / getimagesize 真實圖片驗證
   - error_log 事件記錄
   ============================================================ */
add_action('wp_ajax_smacg_upload_avatar', function () {
    check_ajax_referer('smacg_member_nonce', 'nonce');

    $uid = get_current_user_id();
    if (!$uid) wp_send_json_error(['msg' => '請先登入'], 401);

    /* ===== 防濫用 ===== */
    // ① 2 分鐘冷卻（v2.2.0）
    $last = (int) get_user_meta($uid, 'smacg_avatar_last_upload', true);
    if ($last && (time() - $last) < 120) {
        $wait = 120 - (time() - $last);
        wp_send_json_error(['msg' => "更換太頻繁，請於 {$wait} 秒後再試"], 429);
    }

    // ② 每日上限 5 次（v2.2.0）
    $today_key   = 'smacg_avatar_count_' . date('Ymd');
    $today_count = (int) get_user_meta($uid, $today_key, true);
    if ($today_count >= 5) {
        wp_send_json_error(['msg' => '今日更換次數已達上限（5 次），請明天再試'], 429);
    }

    // ③ v2.2.0：拿掉新帳號鎖定（信任使用者）

    /* ===== 檔案驗證 ===== */
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['msg' => '上傳失敗，請重試'], 400);
    }
    $file = $_FILES['avatar'];

    // v2.2.0：5 MB 上限（前端會壓縮，理論上不會逼近）
    if ($file['size'] > 5 * 1024 * 1024) {
        wp_send_json_error(['msg' => '檔案過大（上限 5 MB）'], 400);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $mime = wp_check_filetype($file['name'])['type'] ?? @mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        wp_send_json_error(['msg' => '僅支援 JPG / PNG / WEBP'], 400);
    }

    // ④ 用 getimagesize 驗證是真圖片（防偽造副檔名上傳）
    $info = @getimagesize($file['tmp_name']);
    $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if (!$info || !in_array($info[2], $allowed_types, true)) {
        wp_send_json_error(['msg' => '檔案不是有效的圖片'], 400);
    }

    /* ===== 上傳到媒體庫 ===== */
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // hash 檔名：防猜測 + 自然 cache-bust
    $rename = function ($f) use ($uid) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $f['name'] = 'avatar_' . substr(md5($uid . microtime(true)), 0, 16) . '.' . $ext;
        return $f;
    };
    add_filter('wp_handle_upload_prefilter', $rename);
    $attach_id = media_handle_upload('avatar', 0);
    remove_filter('wp_handle_upload_prefilter', $rename);

    if (is_wp_error($attach_id)) {
        wp_send_json_error(['msg' => $attach_id->get_error_message()], 500);
    }

    /* ===== 強制 resize 400x400 + 85% 品質 ===== */
    $path   = get_attached_file($attach_id);
    $editor = wp_get_image_editor($path);
    if (!is_wp_error($editor)) {
        $editor->resize(400, 400, true); // crop = true，強制方形
        $editor->set_quality(85);
        $editor->save($path);
        wp_update_attachment_metadata(
            $attach_id,
            wp_generate_attachment_metadata($attach_id, $path)
        );
    }

    /* ===== 刪除舊頭像 ===== */
    $old = (int) get_user_meta($uid, 'smacg_avatar_id', true);
    if ($old && $old !== $attach_id) {
        wp_delete_attachment($old, true);
    }

    /* ===== 寫入 meta + 計次 ===== */
    update_user_meta($uid, 'smacg_avatar_id', $attach_id);
    update_user_meta($uid, 'smacg_avatar_last_upload', time());
    update_user_meta($uid, $today_key, $today_count + 1);

    $url = wp_get_attachment_url($attach_id);

    // 同步給 UM
    if (function_exists('um_fetch_user')) {
        update_user_meta($uid, 'profile_photo', basename($url));
        update_user_meta($uid, 'synced_profile_photo', $url);
    }

    /* ===== v2.2.0：error_log 記錄上傳事件 ===== */
    error_log(sprintf(
        '[SMACG Avatar] uid=%d attach_id=%d size=%d mime=%s today_count=%d ip=%s',
        $uid,
        $attach_id,
        (int) $file['size'],
        $mime,
        $today_count + 1,
        $_SERVER['REMOTE_ADDR'] ?? '-'
    ));

    wp_send_json_success([
        'url' => $url,
        'msg' => '頭像已更新',
    ]);
});

/* ============================================================
   頭像顯示 Filters：覆蓋 WP / UM 預設頭像
   ============================================================ */

/* 讓 get_avatar_url 全站優先讀 smacg_avatar_id（含 cache-bust） */
add_filter('get_avatar_url', function ($url, $id_or_email, $args) {
    $uid = 0;
    if (is_numeric($id_or_email)) {
        $uid = (int) $id_or_email;
    } elseif ($id_or_email instanceof WP_User) {
        $uid = (int) $id_or_email->ID;
    } elseif ($id_or_email instanceof WP_Comment) {
        $uid = (int) $id_or_email->user_id;
    } elseif (is_object($id_or_email)) {
        $uid = (int) ($id_or_email->user_id ?? $id_or_email->ID ?? 0);
    } elseif (is_string($id_or_email) && is_email($id_or_email)) {
        $u = get_user_by('email', $id_or_email);
        $uid = $u ? (int) $u->ID : 0;
    }
    if (!$uid) return $url;

    $aid = (int) get_user_meta($uid, 'smacg_avatar_id', true);
    if (!$aid || !wp_attachment_is_image($aid)) return $url;

    $size = isset($args['size']) ? (int) $args['size'] : 96;
    $img  = wp_get_attachment_image_src($aid, [$size, $size]);
    if (!$img) return $url;

    return add_query_arg('v', get_post_modified_time('U', false, $aid), $img[0]);
}, 9999, 3);

/* 攔截整個 get_avatar HTML 輸出，覆蓋 UM 的 um-avatar-default */
add_filter('get_avatar', function ($html, $id_or_email, $size, $default, $alt, $args) {
    $uid = 0;
    if (is_numeric($id_or_email)) {
        $uid = (int) $id_or_email;
    } elseif ($id_or_email instanceof WP_User) {
        $uid = (int) $id_or_email->ID;
    } elseif ($id_or_email instanceof WP_Comment) {
        $uid = (int) $id_or_email->user_id;
    } elseif (is_object($id_or_email)) {
        $uid = (int) ($id_or_email->user_id ?? $id_or_email->ID ?? 0);
    } elseif (is_string($id_or_email) && is_email($id_or_email)) {
        $u = get_user_by('email', $id_or_email);
        $uid = $u ? (int) $u->ID : 0;
    }
    if (!$uid) return $html;

    $aid = (int) get_user_meta($uid, 'smacg_avatar_id', true);
    if (!$aid || !wp_attachment_is_image($aid)) return $html;

    $img = wp_get_attachment_image_src($aid, [$size, $size]);
    if (!$img) return $html;

    $src  = $img[0] . '?v=' . get_post_modified_time('U', false, $aid);
    $size = (int) $size;

    return sprintf(
        '<img src="%s" class="avatar avatar-%d photo" width="%d" height="%d" alt="%s" loading="lazy" />',
        esc_url($src),
        $size,
        $size,
        $size,
        esc_attr($alt ?: '')
    );
}, 9999, 6);

/* ★ 核心：覆蓋 UM 的頭像 URL（這才是 UM 真正讀的 filter） */
add_filter('um_user_avatar_url_filter', function ($avatar_uri, $user_id) {
    $user_id = (int) $user_id;
    if (!$user_id) return $avatar_uri;
    $aid = (int) get_user_meta($user_id, 'smacg_avatar_id', true);
    if ($aid && wp_attachment_is_image($aid)) {
        $img = wp_get_attachment_image_src($aid, 'medium');
        if ($img) {
            return $img[0] . '?v=' . get_post_modified_time('U', false, $aid);
        }
    }
    return $avatar_uri;
}, 9999, 2);

/* 移除 UM 對 <img> 加的 um-avatar-default class（讓圖正常顯示） */
add_filter('get_avatar', function ($html, $id_or_email) {
    $uid = 0;
    if (is_numeric($id_or_email)) $uid = (int) $id_or_email;
    elseif ($id_or_email instanceof WP_User) $uid = $id_or_email->ID;
    elseif ($id_or_email instanceof WP_Comment) $uid = (int) $id_or_email->user_id;
    elseif (is_object($id_or_email)) $uid = (int) ($id_or_email->user_id ?? $id_or_email->ID ?? 0);
    elseif (is_string($id_or_email) && is_email($id_or_email)) {
        $u = get_user_by('email', $id_or_email);
        $uid = $u ? $u->ID : 0;
    }
    if (!$uid) return $html;
    if (!get_user_meta($uid, 'smacg_avatar_id', true)) return $html;

    $html = preg_replace('/\sum-avatar-default/', '', $html);
    $html = preg_replace('/\sonerror="[^"]*"/', '', $html);
    $html = preg_replace('/\sdata-default="[^"]*"/', '', $html);
    return $html;
}, 99999, 2);
