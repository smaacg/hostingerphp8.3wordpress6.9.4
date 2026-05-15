<?php
namespace SMACG\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Rank Tier — TFT 段位計算（純函式，無狀態）
 *
 * 分數 → 段位（鐵～大師）
 * 名次 → 高階段位修正（前 50 = 菁英、前 200 = 宗師）
 */
class Rank_Tier {

    /** 段位門檻表：[tier_key, division(IV~I 或 ''), min_score, label, color] */
    const TIERS = [
        [ 'iron',     'IV', 0,    '鐵 IV',     '#6b6b6b' ],
        [ 'iron',     'III', 100, '鐵 III',    '#6b6b6b' ],
        [ 'iron',     'II',  200, '鐵 II',     '#6b6b6b' ],
        [ 'iron',     'I',   300, '鐵 I',      '#6b6b6b' ],
        [ 'bronze',   'IV',  400, '銅 IV',     '#a97142' ],
        [ 'bronze',   'III', 550, '銅 III',    '#a97142' ],
        [ 'bronze',   'II',  700, '銅 II',     '#a97142' ],
        [ 'bronze',   'I',   850, '銅 I',      '#a97142' ],
        [ 'silver',   'IV',  1000,'銀 IV',     '#b8b8b8' ],
        [ 'silver',   'III', 1200,'銀 III',    '#b8b8b8' ],
        [ 'silver',   'II',  1400,'銀 II',     '#b8b8b8' ],
        [ 'silver',   'I',   1600,'銀 I',      '#b8b8b8' ],
        [ 'gold',     'IV',  1800,'金 IV',     '#f0c040' ],
        [ 'gold',     'III', 2050,'金 III',    '#f0c040' ],
        [ 'gold',     'II',  2300,'金 II',     '#f0c040' ],
        [ 'gold',     'I',   2550,'金 I',      '#f0c040' ],
        [ 'platinum', 'IV',  2800,'白金 IV',   '#4ad6c0' ],
        [ 'platinum', 'III', 3100,'白金 III',  '#4ad6c0' ],
        [ 'platinum', 'II',  3400,'白金 II',   '#4ad6c0' ],
        [ 'platinum', 'I',   3700,'白金 I',    '#4ad6c0' ],
        [ 'emerald',  'IV',  4000,'翡翠 IV',   '#28b463' ],
        [ 'emerald',  'III', 4400,'翡翠 III',  '#28b463' ],
        [ 'emerald',  'II',  4800,'翡翠 II',   '#28b463' ],
        [ 'emerald',  'I',   5200,'翡翠 I',    '#28b463' ],
        [ 'diamond',  'IV',  5600,'鑽石 IV',   '#5dade2' ],
        [ 'diamond',  'III', 6100,'鑽石 III',  '#5dade2' ],
        [ 'diamond',  'II',  6600,'鑽石 II',   '#5dade2' ],
        [ 'diamond',  'I',   7100,'鑽石 I',    '#5dade2' ],
        [ 'master',   '',    7600,'大師',      '#bb8fce' ],
    ];

    const ICONS = [
        'iron'         => '🥉',
        'bronze'       => '🟫',
        'silver'       => '⚪',
        'gold'         => '🟡',
        'platinum'     => '🟦',
        'emerald'      => '🟢',
        'diamond'      => '💎',
        'master'       => '👑',
        'grandmaster'  => '🔥',
        'challenger'   => '⚡',
    ];

    /**
     * 由分數計算基礎段位（不考慮名次）
     */
    public static function tier_from_score( $score ) {
        $score = max( 0, (int) $score );
        $found = self::TIERS[0];
        foreach ( self::TIERS as $row ) {
            if ( $score >= $row[2] ) $found = $row;
            else break;
        }
        return [
            'key'      => $found[0],
            'division' => $found[1],
            'min'      => $found[2],
            'label'    => $found[3],
            'color'    => $found[4],
            'icon'     => self::ICONS[ $found[0] ] ?? '🎖️',
        ];
    }

    /**
     * 由分數 + 名次計算最終段位（含菁英 / 宗師 修正）
     *
     * @param int $score   賽季積分
     * @param int $rank    全站名次（1 為第 1 名；0 或負值 = 未上榜）
     * @return array
     */
    public static function tier_from_score_and_rank( $score, $rank = 0 ) {
        $base = self::tier_from_score( $score );

        // 必須達到大師門檻（7600）才有資格進菁英 / 宗師
        if ( $base['key'] !== 'master' || $rank <= 0 ) return $base;

        if ( $rank <= 50 ) {
            return [
                'key'      => 'challenger',
                'division' => '',
                'min'      => 7600,
                'label'    => '菁英',
                'color'    => '#ff6b6b',
                'icon'     => self::ICONS['challenger'],
            ];
        }
        if ( $rank <= 200 ) {
            return [
                'key'      => 'grandmaster',
                'division' => '',
                'min'      => 7600,
                'label'    => '宗師',
                'color'    => '#ff9f43',
                'icon'     => self::ICONS['grandmaster'],
            ];
        }
        return $base;
    }

    /**
     * 計算距離下一階所需分數
     */
    public static function progress_to_next( $score ) {
        $score = max( 0, (int) $score );
        $cur = self::TIERS[0];
        $next = null;
        foreach ( self::TIERS as $i => $row ) {
            if ( $score >= $row[2] ) {
                $cur = $row;
                $next = self::TIERS[ $i + 1 ] ?? null;
            } else {
                break;
            }
        }
        if ( ! $next ) {
            return [
                'is_max'    => true,
                'cur_min'   => $cur[2],
                'next_min'  => $cur[2],
                'to_next'   => 0,
                'percent'   => 100,
                'next_label'=> $cur[3],
            ];
        }
        $span    = max( 1, $next[2] - $cur[2] );
        $in_tier = $score - $cur[2];
        return [
            'is_max'     => false,
            'cur_min'    => $cur[2],
            'next_min'   => $next[2],
            'to_next'    => max( 0, $next[2] - $score ),
            'percent'    => min( 100, (int) round( $in_tier * 100 / $span ) ),
            'next_label' => $next[3],
        ];
    }

    /**
     * 取得當前賽季代碼（依現在月份判斷）
     *   3-5 月 = spring；6-8 = summer；9-11 = fall；12,1,2 = winter
     * 回傳格式：'2026-spring'
     */
    public static function current_season_code( $ts = null ) {
        $ts    = $ts ?: current_time( 'timestamp' );
        $month = (int) date( 'n', $ts );
        $year  = (int) date( 'Y', $ts );

        if ( $month >= 3 && $month <= 5 )   return $year . '-spring';
        if ( $month >= 6 && $month <= 8 )   return $year . '-summer';
        if ( $month >= 9 && $month <= 11 )  return $year . '-fall';
        // 12 月 = 該年冬季；1-2 月 = 前一年冬季
        return ( $month === 12 ? $year : $year - 1 ) . '-winter';
    }

    /**
     * 賽季代碼 → 顯示名稱（'2026-spring' → '2026 春季賽'）
     */
    public static function season_label( $code ) {
        if ( ! preg_match( '/^(\d{4})-(spring|summer|fall|winter)$/', $code, $m ) ) return $code;
        $map = [ 'spring' => '春', 'summer' => '夏', 'fall' => '秋', 'winter' => '冬' ];
        return sprintf( '%s %s季賽', $m[1], $map[ $m[2] ] );
    }

    /**
     * 賽季代碼 → [start_ts, end_ts]
     */
    public static function season_range( $code ) {
        if ( ! preg_match( '/^(\d{4})-(spring|summer|fall|winter)$/', $code, $m ) ) return [ 0, 0 ];
        $y = (int) $m[1];
        switch ( $m[2] ) {
            case 'spring':
                return [ mktime( 0, 0, 0, 3, 1, $y ),       mktime( 23, 59, 59, 5, 31, $y ) ];
            case 'summer':
                return [ mktime( 0, 0, 0, 6, 1, $y ),       mktime( 23, 59, 59, 8, 31, $y ) ];
            case 'fall':
                return [ mktime( 0, 0, 0, 9, 1, $y ),       mktime( 23, 59, 59, 11, 30, $y ) ];
            case 'winter':
                // 冬季 = Y/12/1 ~ (Y+1)/2/28(29)
                $end_day = ( ( ( $y + 1 ) % 4 === 0 && ( $y + 1 ) % 100 !== 0 ) || ( $y + 1 ) % 400 === 0 ) ? 29 : 28;
                return [ mktime( 0, 0, 0, 12, 1, $y ),      mktime( 23, 59, 59, 2, $end_day, $y + 1 ) ];
        }
        return [ 0, 0 ];
    }
}
