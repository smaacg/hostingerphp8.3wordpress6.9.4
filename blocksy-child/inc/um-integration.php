<?php
/**
 * Ultimate Member 整合：enqueue / template_include / 中文字典 / JS 翻譯 /
 * 登入後跳轉 / template 防護 (single.php → page.php) /
 * /user/ 404 自動重導
 *
 * @package weixiaoacg
 * @subpackage UM
 *
 * Changelog:
 * - v1.1.0 (2026-05-13)：
 *   新增 smacg_um_user_404_redirect()
 *   攔截 /user/{找不到的 username}/ → 自動 302 重導到會員中心 (/mc/)，
 *   徹底解決 "We are sorry. We cannot find any users..." 英文錯誤頁。
 *   priority 5，比 UM 自己的 profile 渲染 hook 更早。
 */
defined( 'ABSPATH' ) || exit;

/* ============================================================
   UM 資源 enqueue（user / account / page-member 才載）
   ============================================================ */
add_action('wp_enqueue_scripts', function() {
    if (!function_exists('um_is_core_page')) return;
    $is_um = um_is_core_page('user') || get_query_var('um_user') || um_is_core_page('account');
    $is_mt = is_page_template('page-member.php');
    if ($is_um || $is_mt) {
        foreach (['um_scripts','um_profile','um_account','um_crop','um_modal','um_fileupload'] as $s) wp_enqueue_script($s);
        foreach (['um_styles','um_profile','um_account','um_crop','um_misc','um_modal','um_fileupload'] as $s) wp_enqueue_style($s);
    }
}, 20);

/* UM /user/xxx/ 改用自訂模板 page-member.php */
add_filter('template_include', function($tpl) {
    if (!function_exists('um_is_core_page')) return $tpl;
    if (um_is_core_page('user') || get_query_var('um_user')) {
        $c = weixiaoacg_THEME_DIR.'/page-member.php';
        if (file_exists($c)) return $c;
    }
    return $tpl;
}, 99);

/* user 頁面拔掉 UM 預設 style（保留 account 頁的） */
add_action('wp_enqueue_scripts', function() {
    if (!function_exists('um_is_core_page')) return;
    if ((um_is_core_page('user') || get_query_var('um_user')) && !um_is_core_page('account')) {
        wp_dequeue_style('um_styles');
        wp_dequeue_style('um_responsive');
        wp_dequeue_style('um_icons');
    }
}, 99);

/* 移除 UM 後台 admin notices */
add_action('admin_notices', function() {
    $s = get_current_screen();
    if ($s && (strpos($s->id,'um_')!==false || strpos($s->id,'ultimate-member')!==false || $s->id==='toplevel_page_um-options'))
        remove_all_actions('admin_notices');
}, 1);

add_filter( 'um_login_allow_nonce_verification', '__return_false' );

/* ============================================================
   v1.1.0：/user/ 找不到使用者 → 自動重導
   ------------------------------------------------------------
   觸發條件：
     1) 當前是 UM user 頁（um_is_core_page('user') 或 query_var um_user 存在）
     2) 但 URL 中的 profile slug 找不到對應使用者
   行為：
     - 已登入 → 302 重導到 /mc/（自家會員中心）
     - 未登入 → 302 重導到首頁
   priority 5，比 UM 內部 profile 渲染（通常 priority 10+）更早觸發。
   ============================================================ */
if ( ! function_exists( 'smacg_um_user_404_redirect' ) ) {
    function smacg_um_user_404_redirect() {

        // 後台、AJAX、REST、Cron 一律跳過
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;

        // UM 沒啟用就不處理
        if ( ! function_exists( 'um_is_core_page' ) ) return;

        // 不是 UM user 頁就不處理（/login/、/register/ 等不影響）
        $is_user_page = um_is_core_page( 'user' ) || get_query_var( 'um_user' );
        if ( ! $is_user_page ) return;

        /**
         * 偵測「找不到使用者」的條件：
         *   1) URL 帶了 profile slug 但 UM 沒解析出有效 user
         *   2) 或 query_var um_user 存在但對應不到 user_id
         */
        $um_user_slug = get_query_var( 'um_user' );
        $needs_redirect = false;

        if ( $um_user_slug ) {
            // 嘗試各種方式解析 username → user
            $u = get_user_by( 'slug',  $um_user_slug );
            if ( ! $u ) $u = get_user_by( 'login', $um_user_slug );
            if ( ! $u ) $u = get_user_by( 'email', str_replace( '-', '.', $um_user_slug ) );

            // UM 可能用 meta_key 'um_user_profile_url_slug_{base}' 儲存自訂 slug
            if ( ! $u ) {
                $users = get_users( array(
                    'meta_key'    => 'um_user_profile_url_slug_user_login',
                    'meta_value'  => $um_user_slug,
                    'number'      => 1,
                    'count_total' => false,
                ) );
                if ( ! empty( $users ) ) $u = $users[0];
            }
            if ( ! $u ) {
                $users = get_users( array(
                    'meta_key'    => 'um_user_profile_url_slug_username',
                    'meta_value'  => $um_user_slug,
                    'number'      => 1,
                    'count_total' => false,
                ) );
                if ( ! empty( $users ) ) $u = $users[0];
            }

            if ( ! $u ) $needs_redirect = true;
        } else {
            // 是 user core page 但 query_var 完全空 → 也視為無效
            $needs_redirect = true;
        }

        if ( ! $needs_redirect ) return;

        // 決定目的地
        $target = '';
        if ( is_user_logged_in() && function_exists( 'smacg_get_member_center_url' ) ) {
            $target = smacg_get_member_center_url();
        }
        if ( ! $target ) {
            $target = home_url( '/' );
        }

        // 加 query 參數方便除錯（可選，未來不需要可拿掉）
        $target = add_query_arg( 'from', 'um-404', $target );

        wp_safe_redirect( $target, 302 );
        exit;
    }
    add_action( 'template_redirect', 'smacg_um_user_404_redirect', 5 );
}

/* ============================================================
   UM 中文化字典
   ============================================================ */
add_filter('gettext', function($t,$o,$d) {
    if ($d !== 'ultimate-member') return $t;
    static $map = [
        'An error has been encountered. Probably page was cached. Please try again.' => '發生錯誤，頁面可能已被快取，請重新整理後再試一次。',
        'Username or E-mail'=>'使用者名稱或電子郵件','Username or Email' =>'使用者名稱或電子郵件',
        'Password'=>'密碼','Keep me signed in'=>'保持登入狀態',
        'Sign Up'=>'註冊','Forgot your password?'=>'忘記密碼？',
        'Log In'=>'登入','Login'=>'登入',
        'The username you entered is incorrect'=>'使用者名稱輸入有誤',
        'The email you entered is incorrect'   =>'電子郵件輸入有誤',
        'The password you entered is incorrect'=>'密碼輸入有誤',
        'Invalid username or email'=>'使用者名稱或電子郵件有誤',
        'Invalid username'=>'無效的使用者名稱',
        'Invalid email address'=>'無效的電子郵件',
        'Incorrect password'=>'密碼不正確',
        'This account has been blocked'=>'此帳號已被封鎖',
        'This account is awaiting approval'=>'此帳號正在等待審核',
        'Your account has not been activated yet'=>'你的帳號尚未啟用',
        'A user could not be found with this email address'=>'找不到使用此電子郵件的使用者',
        'Your account was updated successfully.'=>'你的帳號已成功更新。',
        'Your account has been updated successfully.'=>'你的帳號已成功更新。',
        'Changes saved successfully.'=>'變更已成功儲存。',
        'Username'=>'使用者名稱','E-mail'=>'電子郵件','Email'=>'電子郵件',
        'Confirm Password'=>'確認密碼','Already have an account?'=>'已有帳號？','Register'=>'註冊',
        'Your %s must contain at least %d characters'=>'你的%s至少需要 %d 個字元',
        'Your %s must contain at least one uppercase letter'=>'你的%s至少需要一個大寫字母',
        'Your %s must contain at least one lowercase letter'=>'你的%s至少需要一個小寫字母',
        'Your %s must contain at least one number'=>'你的%s至少需要一個數字',
        'Your %s must contain at least one special character'=>'你的%s至少需要一個特殊符號',
        'Your password must contain at least %d characters' =>'你的密碼至少需要 %d 個字元',
        'Your password must contain at least %d characters.'=>'你的密碼至少需要 %d 個字元。',
        'Your password must contain at least one capital letter'=>'你的密碼至少需要一個大寫字母',
        'Your password must contain at least one capital letter.'=>'你的密碼至少需要一個大寫字母。',
        'Your password must contain at least one uppercase letter'=>'你的密碼至少需要一個大寫字母',
        'Your password must contain at least one lowercase letter'=>'你的密碼至少需要一個小寫字母',
        'Your password must contain at least one number'=>'你的密碼至少需要一個數字',
        'Your password must contain at least one special character'=>'你的密碼至少需要一個特殊符號',
        'Your username must contain at least %d characters'=>'你的使用者名稱至少需要 %d 個字元',
        'Your username must contain at least 3 characters'=>'你的使用者名稱至少需要 3 個字元',
        'password'=>'密碼','username'=>'使用者名稱','Password strength'=>'密碼強度',
        'Very Weak'=>'非常弱','Weak'=>'弱','Medium'=>'中等','Strong'=>'強','Very Strong'=>'非常強',
        'Mismatch'=>'密碼不一致','Please enter your password again'=>'請再次輸入密碼',
        'Passwords do not match'=>'兩次輸入的密碼不一致',
        'Password is too short'=>'密碼太短','Password is too weak'=>'密碼強度不足',
        'Forgot Password'=>'忘記密碼','Reset Password'=>'重設密碼',
        'Send Reset Link'=>'發送重設連結','Back to login'=>'返回登入','Back to Login'=>'返回登入',
        'About'=>'關於','Posts'=>'文章','Comments'=>'留言','Friends'=>'朋友',
        'Photos'=>'相片','Videos'=>'影片','Groups'=>'群組','Forums'=>'論壇',
        'Change your cover photo'=>'更換封面照片','Upload a cover photo'=>'上傳封面照片',
        'Remove cover photo'=>'移除封面照片','Change your profile photo'=>'更換個人頭像',
        'Upload a profile photo'=>'上傳個人頭像','Remove profile photo'=>'移除個人頭像',
        '( max: %s/MB )'=>'（最大：%s MB）','( max: %s MB )'=>'（最大：%s MB）',
        'Drop image here or click to upload'=>'拖曳圖片至此或點擊上傳',
        'Drop file here or click to upload'=>'拖曳檔案至此或點擊上傳',
        'Change Photo'=>'更換照片','Upload Photo'=>'上傳照片',
        'Tell us a bit about yourself...'=>'介紹一下你自己…',
        'Tell us a bit about yourself…'=>'介紹一下你自己…',
        'Biography'=>'個人簡介','No biography yet.'=>'尚未填寫個人簡介。',
        'Edit my profile'=>'編輯個人資料','Edit Profile'=>'編輯個人資料',
        'First Name'=>'名字','Last Name'=>'姓氏','Display Name'=>'顯示名稱',
        'No posts found.'=>'尚無文章。','No comments found.'=>'尚無留言。',
        'My Bookmarks'=>'我的書籤','Report'=>'檢舉','Block'=>'封鎖','Unblock'=>'取消封鎖',
        'Message'=>'訊息','Follow'=>'追蹤','Unfollow'=>'取消追蹤','Privacy'=>'隱私權',
        'Update Privacy Settings'=>'更新隱私設定','Profile Privacy'=>'個人資料隱私',
        'Who can view my profile?'=>'誰可以查看我的個人資料？',
        'All visitors'=>'全部使用者','All members'=>'所有會員',
        'Logged in users'=>'已登入的使用者','Only me'=>'只有我自己',
        'Show my last login?'=>'顯示我的最後登入時間？','Show last login'=>'顯示最後登入時間',
        'Download your data'=>'下載你的資料','Request Data Export'=>'請求資料匯出',
        'Export Data'=>'匯出資料','Erase your data'=>'清除你的資料',
        'Delete Account'=>'刪除帳號','Delete my account'=>'刪除我的帳號',
        'Current Password'=>'目前密碼','New Password'=>'新密碼','Confirm New Password'=>'確認新密碼',
        'Change Password'=>'更改密碼','Update Password'=>'更新密碼',
        'Submit'=>'送出','Save Changes'=>'儲存變更','Save'=>'儲存','Update'=>'更新',
        'Update account'=>'更新帳號','Upload'=>'上傳','Remove'=>'移除','Crop'=>'裁切',
        'Apply'=>'套用','Cancel'=>'取消','Yes'=>'是','No'=>'否',
        'General'=>'一般','Account'=>'帳號','Profile'=>'個人資料','Delete'=>'刪除帳號',
        'Member Since'=>'加入時間','Role'=>'角色','Logout'=>'登出','Log Out'=>'登出',
        'Hide my profile from directory'=>'在目錄中隱藏我的個人資料',
        'Enter your current password to confirm a new export of your personal data.'=>'請輸入目前的密碼以確認匯出你的個人資料。',
        'Request data'=>'請求資料',
        'Erase of your data'=>'刪除你的資料',
        'Enter your current password to confirm the erasure of your personal data.'=>'請輸入目前的密碼以確認刪除你的個人資料。',
        'Are you sure you want to delete your account?'=>'你確定要刪除你的帳號嗎？',
        'This will erase all of your account data from the site.'=>'這將會清除你在本站的所有帳號資料。',
        'To delete your account enter your password below.'=>'請在下方輸入密碼以確認刪除帳號。',
        'Are you sure you want to delete your account? This will erase all of your account data from the site. To delete your account enter your password below.'=>'你確定要刪除你的帳號嗎？這將會清除你在本站的所有帳號資料。請在下方輸入密碼以確認刪除帳號。',
        'Are you sure you want to delete your account? This will erase all of your account data from the site. To delete your account, click on the button below.'=>'你確定要刪除你的帳號嗎？這將會清除你在本站的所有帳號資料。請點擊下方按鈕以確認刪除帳號。',
        'Upload photo'=>'上傳頭像','Change photo'=>'更換頭像','Remove photo'=>'移除頭像',
        'Change cover'=>'更換封面','Remove cover'=>'移除封面',
        'Update Account'=>'更新帳號','Update Privacy'=>'更新隱私設定',
        'Avoid indexing my profile by search engines'=>'避免搜尋引擎索引我的個人資料',
        'View profile'=>'查看個人資料','Are you sure?'=>'你確定嗎？',
    ];
    return $map[$o] ?? $t;
}, 10, 3);

add_filter('gettext_with_context', function($t,$o,$ctx,$d) {
    if ($d !== 'ultimate-member') return $t;
    static $m = ['Your %s must contain at least %d characters'=>'你的%s至少需要 %d 個字元'];
    return $m[$o] ?? $t;
}, 10, 4);

/* ============================================================
   UM JS 端錯誤訊息中文化
   ============================================================ */
add_action('wp_footer', function() { ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var s = {
            'An error has been encountered. Probably page was cached. Please try again.': '發生錯誤，頁面可能已被快取，請重新整理後再試一次。',
            'Your password must contain at least one capital letter'   :'你的密碼至少需要一個大寫字母',
            'Your password must contain at least one uppercase letter' :'你的密碼至少需要一個大寫字母',
            'Your password must contain at least one lowercase letter' :'你的密碼至少需要一個小寫字母',
            'Your password must contain at least one number'           :'你的密碼至少需要一個數字',
            'Your password must contain at least one special character':'你的密碼至少需要一個特殊符號',
            'The username you entered is incorrect':'使用者名稱輸入有誤',
            'The password you entered is incorrect':'密碼輸入有誤',
            'Passwords do not match'               :'兩次輸入的密碼不一致',
            'Password is too short'                :'密碼太短'
        };
        function fix(el) {
            Object.keys(s).forEach(function(en) {
                if (el.textContent.indexOf(en) !== -1) el.textContent = el.textContent.replace(en, s[en]);
            });
        }
        new MutationObserver(function(ms) {
            ms.forEach(function(m) {
                m.addedNodes.forEach(function(n) {
                    if (n.nodeType !== 1) return;
                    n.querySelectorAll('.um-field-error,.um-notice,.um-error,.um-form-message').forEach(fix);
                    if (n.classList && (
                        n.classList.contains('um-field-error') || n.classList.contains('um-notice') ||
                        n.classList.contains('um-error')       || n.classList.contains('um-form-message')
                    )) fix(n);
                });
            });
        }).observe(document.body, {childList: true, subtree: true});
        document.querySelectorAll('.um-profile-note,.um-empty-profile,[class*="um-profile"]').forEach(function(el) {
            if (el.innerHTML.indexOf('Your profile is looking a little empty') !== -1)
                el.innerHTML = el.innerHTML.replace(
                    /Your profile is looking a little empty\. Why not <a([^>]*)>add<\/a> some information!/g,
                    '你的個人頁面看起來空空的。來<a$1>新增一些資料</a>吧！'
                );
        });
    });
    </script>
<?php }, 999);

/* ============================================================
   UM 登入後跳轉
   ============================================================ */
add_action( 'um_on_login_before_redirect', function( $user_id ) {
    um_fetch_user( $user_id );
    if ( um_user( 'after_login' ) === 'redirect_profile' ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
}, 5 );

/* UM 帳號更新後跳回個人頁 */
add_action( 'um_after_user_account_updated', function( $user_id, $args ) {
    if ( ! empty( $_POST['um_account_tab'] ) && $_POST['um_account_tab'] === 'general' ) {
        um_fetch_user( $user_id );
        wp_safe_redirect( um_user_profile_url() );
        exit;
    }
}, 10, 2 );

/* /account/ 強制 editing 模式 */
add_action( 'um_account_page_load', function() {
    if ( function_exists( 'UM' ) ) UM()->fields()->editing = true;
}, 1 );

/* ============================================================
   防止 single.php 套用到 Page / UM / wpForo 等非 post 頁面
   優先級 999，比上方優先級 99 的 UM user → page-member.php 晚跑，
   page-member.php 已選中時不會被覆蓋
   ============================================================ */
add_filter( 'template_include', function( $template ) {
    // 只在 WP 選了 single.php 時介入
    if ( basename( $template ) !== 'single.php' ) return $template;

    // 真．單篇文章：post type=post 且不是 Page
    $is_real_single = is_singular( 'post' ) && ! is_page();

    // UM 動態頁
    if ( function_exists( 'um_is_core_page' ) && (
            um_is_core_page( 'user' )     || get_query_var( 'um_user' ) ||
            um_is_core_page( 'account' )  || um_is_core_page( 'login' ) ||
            um_is_core_page( 'register' ) || um_is_core_page( 'password-reset' ) ||
            um_is_core_page( 'logout' )   || um_is_core_page( 'members' )
       ) ) {
        $is_real_single = false;
    }

    // wpForo
    if ( function_exists( 'is_wpforo_page' ) && is_wpforo_page() ) {
        $is_real_single = false;
    }

    if ( ! $is_real_single ) {
        // 改用父主題的 page.php
        $page_tpl = get_template_directory() . '/page.php';
        if ( file_exists( $page_tpl ) ) return $page_tpl;
        // 父主題沒 page.php 才退而求其次走 index.php
        $idx_tpl = get_template_directory() . '/index.php';
        if ( file_exists( $idx_tpl ) ) return $idx_tpl;
    }

    return $template;
}, 999 );
