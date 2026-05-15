<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Career Jobs — 8 職業 × 4 階稱號（動漫梗）
 *
 * 用法：
 *   Career_Jobs::all()                       → 8 職業完整表
 *   Career_Jobs::milestones()                → 4 個里程碑（Lv 10/30/70/120）
 *   Career_Jobs::career_stage( $level )      → 該等級在第幾轉（0~4）
 *   Career_Jobs::user_job( $uid )            → 玩家當前 job_key
 *   Career_Jobs::set_user_job( $uid, $key )  → 一轉時呼叫
 *   Career_Jobs::user_title( $uid )          → 玩家當前稱號（依等級判轉階）
 *
 * @since 2.0.0 (2026-05-15) 從 theme 重新導入，取代舊 Career_Ajax 4 職業
 */
class Career_Jobs {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    /* ==========================================================
     * 8 職業 × 4 階稱號表
     * ========================================================== */
    public static function all() {
        return [
            'student' => [
                'label'  => '學生',
                'icon'   => '🎓',
                'desc'   => '校園裡的努力家，從懵懂新生晉升為神級學者',
                'titles' => [
                    1 => [ 'name' => '懵懂新生',   'ref' => '我的英雄學院' ],
                    2 => [ 'name' => '優等生',     'ref' => '為美好的世界獻上祝福' ],
                    3 => [ 'name' => '學生會長',   'ref' => '輝夜姬想讓人告白' ],
                    4 => [ 'name' => '神級學者',   'ref' => '文豪 Stray Dogs' ],
                ],
            ],
            'it' => [
                'label'  => '資訊／軟體業',
                'icon'   => '💻',
                'desc'   => '與 bug 共舞的鏈鋸碼農',
                'titles' => [
                    1 => [ 'name' => '程式新手',   'ref' => '無職轉生' ],
                    2 => [ 'name' => '鏈鋸碼農',   'ref' => '鏈鋸人' ],
                    3 => [ 'name' => '咒術工程師', 'ref' => '咒術迴戰' ],
                    4 => [ 'name' => '開發超人',   'ref' => 'SPY×FAMILY' ],
                ],
            ],
            'design' => [
                'label'  => '設計／創作',
                'icon'   => '🎨',
                'desc'   => '色彩與線條的魔法師',
                'titles' => [
                    1 => [ 'name' => '美術新手',   'ref' => '排球少年' ],
                    2 => [ 'name' => '色彩魔法師', 'ref' => '魔法少女小圓' ],
                    3 => [ 'name' => '藝術鬼才',   'ref' => '進擊的巨人' ],
                    4 => [ 'name' => '神之筆者',   'ref' => '哆啦 A 夢' ],
                ],
            ],
            'office' => [
                'label'  => '行政／內勤',
                'icon'   => '💼',
                'desc'   => '社畜生活的辦公室之神',
                'titles' => [
                    1 => [ 'name' => '社畜新血',   'ref' => 'NEW GAME!' ],
                    2 => [ 'name' => '會議達人',   'ref' => '我推的孩子' ],
                    3 => [ 'name' => '部門核心',   'ref' => '半澤直樹' ],
                    4 => [ 'name' => '辦公室之神', 'ref' => '魔法少女小圓' ],
                ],
            ],
            'sales' => [
                'label'  => '業務／行銷',
                'icon'   => '📊',
                'desc'   => '靠嘴吃飯的銷售之神',
                'titles' => [
                    1 => [ 'name' => '業務新兵',   'ref' => '機動戰士鋼彈' ],
                    2 => [ 'name' => '簽單獵人',   'ref' => '獵人' ],
                    3 => [ 'name' => '王牌業務',   'ref' => 'JOJO 的奇妙冒險' ],
                    4 => [ 'name' => '銷售之神',   'ref' => '海賊王' ],
                ],
            ],
            'medical' => [
                'label'  => '醫療／護理',
                'icon'   => '🏥',
                'desc'   => '白衣天使到醫術之神的旅程',
                'titles' => [
                    1 => [ 'name' => '見習醫師',   'ref' => '怪醫黑傑克' ],
                    2 => [ 'name' => '白衣天使',   'ref' => '工作細胞' ],
                    3 => [ 'name' => '主治醫師',   'ref' => '怪醫黑傑克' ],
                    4 => [ 'name' => '醫術之神',   'ref' => 'Dr. STONE' ],
                ],
            ],
            'service' => [
                'label'  => '餐飲／服務業',
                'icon'   => '🍽️',
                'desc'   => '微笑大使到傳奇店長',
                'titles' => [
                    1 => [ 'name' => '服務員',     'ref' => '異世界食堂' ],
                    2 => [ 'name' => '迎賓武士',   'ref' => '銀魂' ],
                    3 => [ 'name' => '微笑大使',   'ref' => 'SPY×FAMILY' ],
                    4 => [ 'name' => '傳奇店長',   'ref' => '中華一番' ],
                ],
            ],
            'freelance' => [
                'label'  => '自由／其他',
                'icon'   => '🌱',
                'desc'   => '人生的逍遙之神',
                'titles' => [
                    1 => [ 'name' => '自由人',     'ref' => '葬送的芙莉蓮' ],
                    2 => [ 'name' => '斜槓達人',   'ref' => '為美好的世界獻上祝福' ],
                    3 => [ 'name' => '人生玩家',   'ref' => 'JOJO 的奇妙冒險' ],
                    4 => [ 'name' => '逍遙之神',   'ref' => '聖哥傳' ],
                ],
            ],
        ];
    }

    /* ==========================================================
     * 4 轉里程碑
     * ========================================================== */
    public static function milestones() {
        return [
            1 => [ 'level' => 10,  'label' => '一轉：解鎖職業', 'icon' => '🎓' ],
            2 => [ 'level' => 30,  'label' => '二轉：職業升階', 'icon' => '⭐' ],
            3 => [ 'level' => 70,  'label' => '三轉：高階稱號', 'icon' => '🌟' ],
            4 => [ 'level' => 120, 'label' => '四轉：究極稱號', 'icon' => '👑' ],
        ];
    }

    /**
     * 由 level 判斷在第幾轉（0~4）
     */
    public static function career_stage( $level ) {
        $level = (int) $level;
        if ( $level >= 120 ) return 4;
        if ( $level >= 70  ) return 3;
        if ( $level >= 30  ) return 2;
        if ( $level >= 10  ) return 1;
        return 0;
    }

    /* ==========================================================
     * 玩家職業（meta：smacg_job_key）
     * ========================================================== */
    public static function user_job( $uid ) {
        return (string) get_user_meta( (int) $uid, 'smacg_job_key', true );
    }

    public static function set_user_job( $uid, $job_key ) {
        $uid     = (int) $uid;
        $job_key = sanitize_key( $job_key );
        $jobs    = self::all();

        if ( $uid <= 0 || ! isset( $jobs[ $job_key ] ) ) return false;

        update_user_meta( $uid, 'smacg_job_key',       $job_key );
        update_user_meta( $uid, 'smacg_job_chosen_at', current_time( 'mysql' ) );

        do_action( 'smacg_user_job_chosen', $uid, $job_key );
        return true;
    }

    /**
     * 取得玩家目前的職業稱號（依當前等級判轉階）
     *
     * @return array|[]  ['job_key','job_label','job_icon','stage','title_name','title_ref']
     */
    public static function user_title( $uid ) {
        $uid = (int) $uid;
        if ( $uid <= 0 ) return [];

        $key = self::user_job( $uid );
        if ( ! $key ) return [];

        $jobs = self::all();
        if ( ! isset( $jobs[ $key ] ) ) return [];

        $info  = Level_System::get_user_level( $uid );
        $stage = max( 1, self::career_stage( (int) $info['level'] ) );
        if ( $stage < 1 ) return [];

        $t = $jobs[ $key ]['titles'][ $stage ];
        return [
            'job_key'    => $key,
            'job_label'  => $jobs[ $key ]['label'],
            'job_icon'   => $jobs[ $key ]['icon'],
            'stage'      => $stage,
            'title_name' => $t['name'],
            'title_ref'  => $t['ref'],
        ];
    }
}
