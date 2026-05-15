<?php
/**
 * YourAnimes 串流連結爬蟲
 *
 * 從 YourAnimes 動畫頁抓取台灣串流平台連結，
 * 自動填入 ACF 欄位並勾選對應 checkbox。
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_YourAnimes_Fetcher {

    const CACHE_TTL       = 7 * DAY_IN_SECONDS; // 7 天快取
    const RATE_LIMIT_MIN  = 2;                   // 最小延遲秒數
    const RATE_LIMIT_MAX  = 5;                   // 最大延遲秒數
    const TRANSIENT_PREFIX = 'asp_youranimes_';

    public function __construct() {
        // 開關檢查：明確設為 false 才停用
        if ( defined( 'ANIME_YOURANIMES_ENABLED' ) && ! ANIME_YOURANIMES_ENABLED ) {
            return;
        }

        // 在 ACF 欄位下方加同步按鈕
        add_action( 'acf/render_field/name=anime_youranimes_url', array( $this, 'render_sync_button' ), 20 );

        // AJAX 處理
        add_action( 'wp_ajax_asp_sync_youranimes', array( $this, 'ajax_sync' ) );

        // 載入後台 JS
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * 平台對應表：host 關鍵字 => ACF key
     */
    private function platform_map() {
        return array(
            'ani.gamer.com.tw'    => 'bahamut',
            'hamivideo.hinet.net' => 'hami',
            'myvideo.net.tw'      => 'myvideo',
            'linetv.tw'           => 'linetv',
            'video.friday.tw'     => 'friday',
            'ofiii.com'           => 'ofiii',
            'catchplay.com'       => 'catchplay',
            'netflix.com'         => 'netflix',
            'disneyplus.com'      => 'disney',
            'litv.tv'             => 'litv',
            'iq.com'              => 'iqiyi',
            'tw.myrenta.com'      => 'renta',
            'bilibili.tv'         => 'bilibili',
            'bilibili.com'        => 'bilibili',
        );
    }

    /**
     * YouTube 頻道 alt 關鍵字對應表
     * key = ACF 欄位 key, value = alt 文字中可能出現的關鍵字陣列
     */
    private function youtube_alt_map() {
        return array(
            'muse'         => array( 'Muse', '木棉花' ),
            'ani_one'      => array( 'Ani-One', 'AniOne', 'Ani One' ),
            'mighty'       => array( '曼迪', 'Mighty' ),
            'tropicsanime' => array( '回歸線', 'Tropics' ),
            'ani_mi'       => array( 'Ani-Mi', '動漫迷' ),
            'anipass'      => array( 'AniPASS', '車庫' ),
        );
    }

    /**
     * 渲染同步按鈕（在 ACF URL 欄位下方）
     */
    public function render_sync_button( $field ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            global $post;
            $post_id = isset( $post->ID ) ? $post->ID : 0;
        }
        ?>
        <div class="asp-youranimes-sync-wrap" style="margin-top:10px;">
            <button type="button" class="button button-primary asp-youranimes-sync-btn"
                    data-post-id="<?php echo esc_attr( $post_id ); ?>"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'asp_sync_youranimes_' . $post_id ) ); ?>">
                🌐 同步 YourAnimes 串流連結
            </button>
            <span class="asp-youranimes-sync-status" style="margin-left:10px;color:#666;"></span>
        </div>
        <?php
    }

    /**
     * 載入後台 JS
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }
        if ( get_post_type() !== 'anime' ) {
            return;
        }
        $script = "
        jQuery(document).on('click', '.asp-youranimes-sync-btn', function(e){
            e.preventDefault();
            var \$btn = jQuery(this);
            var \$status = \$btn.siblings('.asp-youranimes-sync-status');
            var postId = \$btn.data('post-id');
            var nonce = \$btn.data('nonce');
            var url = jQuery('input[name=\"acf[field_anime_youranimes_url]\"]').val();

            if (!url) {
                \$status.css('color','#d63638').text('⚠️ 請先填入 YourAnimes 網址');
                return;
            }

            \$btn.prop('disabled', true);
            \$status.css('color','#666').text('⏳ 同步中，請稍候 2-5 秒...');

            jQuery.post(ajaxurl, {
                action: 'asp_sync_youranimes',
                post_id: postId,
                nonce: nonce,
                url: url
            }, function(res){
                if (res.success) {
                    \$status.css('color','#00a32a').text('✅ ' + res.data.message);
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    \$status.css('color','#d63638').text('❌ ' + (res.data && res.data.message ? res.data.message : '同步失敗'));
                    \$btn.prop('disabled', false);
                }
            }).fail(function(){
                \$status.css('color','#d63638').text('❌ 網路錯誤，請稍後再試');
                \$btn.prop('disabled', false);
            });
        });
        ";
        wp_add_inline_script( 'jquery', $script );
    }

    /**
     * AJAX 處理：同步 YourAnimes 串流
     */
    public function ajax_sync() {
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        $url     = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'asp_sync_youranimes_' . $post_id ) ) {
            wp_send_json_error( array( 'message' => '驗證失敗' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => '權限不足' ) );
        }

        if ( empty( $url ) || ! preg_match( '#^https?://(www\.)?youranimes\.tw/animes/\d+#i', $url ) ) {
            wp_send_json_error( array( 'message' => '網址格式錯誤，請使用 https://youranimes.tw/animes/XXXX 格式' ) );
        }

        // 正規化 URL：確保有 /onair 後綴
        $url = $this->normalize_url( $url );

        // 抓取頁面
        $html = $this->fetch_page( $url );
        if ( is_wp_error( $html ) ) {
            error_log( '[YourAnimes Fetcher] Fetch failed: ' . $html->get_error_message() . ' URL: ' . $url );
            wp_send_json_error( array( 'message' => '抓取失敗：' . $html->get_error_message() ) );
        }

        // 解析串流連結
        $streams = $this->parse_streams( $html );
        if ( empty( $streams ) ) {
            error_log( '[YourAnimes Fetcher] No platforms found. URL: ' . $url );
            wp_send_json_error( array( 'message' => '未找到任何串流平台，請手動填入（此頁可能為動態載入或舊資料）' ) );
        }

        // 寫入 ACF
        $updated = $this->write_to_acf( $post_id, $streams );

        wp_send_json_success( array(
            'message'  => sprintf( '同步完成！已更新 %d 個平台', count( $updated ) ),
            'updated'  => $updated,
        ) );
    }

    /**
     * URL 正規化
     */
    private function normalize_url( $url ) {
        // 移除可能的 query string 與 fragment
        $url = preg_replace( '#[\?\#].*$#', '', $url );
        // 移除尾端斜線
        $url = rtrim( $url, '/' );
        // 確保以 /onair 結尾
        if ( ! preg_match( '#/onair$#', $url ) ) {
            // 移除其他可能的後綴（/news, /stats 等）
            $url = preg_replace( '#/(news|stats|episodes|comments)$#', '', $url );
            $url .= '/onair';
        }
        return $url;
    }

    /**
     * 抓取頁面（含快取與 Chrome UA 偽裝）
     */
    private function fetch_page( $url ) {
        $cache_key = self::TRANSIENT_PREFIX . md5( $url );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        // 隨機延遲 2-5 秒
        $delay = wp_rand( self::RATE_LIMIT_MIN, self::RATE_LIMIT_MAX );
        sleep( $delay );

        $args = array(
            'timeout'     => 15,
            'redirection' => 3,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'headers'     => array(
                'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language'           => 'zh-TW,zh;q=0.9,en;q=0.8',
                'Accept-Encoding'           => 'gzip, deflate, br',
                'Referer'                   => 'https://www.google.com/',
                'Sec-Fetch-Dest'            => 'document',
                'Sec-Fetch-Mode'            => 'navigate',
                'Sec-Fetch-Site'            => 'cross-site',
                'Sec-Fetch-User'            => '?1',
                'Upgrade-Insecure-Requests' => '1',
            ),
        );

        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'http_error', 'HTTP ' . $code );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return new WP_Error( 'empty_body', '回應內容為空' );
        }

        set_transient( $cache_key, $body, self::CACHE_TTL );
        return $body;
    }

    /**
     * 解析串流連結
     * 全頁掃描所有 <a href>，靠 host 與 YouTube alt 判斷
     * （YourAnimes 為 Next.js CSR/SSR，無傳統 h2/h3 標題可定位區塊）
     */
    private function parse_streams( $html ) {
        $streams = array();

        $platform_map = $this->platform_map();
        $youtube_map  = $this->youtube_alt_map();

        // Ani-One 中文官方優先
        $ani_one_chinese = null;
        $ani_one_first   = null;

        // 全頁掃描所有 <a href="...">...</a>
        if ( ! preg_match_all( '#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#si', $html, $matches, PREG_SET_ORDER ) ) {
            return $streams;
        }

        foreach ( $matches as $match ) {
            $href  = $match[1];
            $inner = $match[2];

            // 解析 host
            $host = parse_url( $href, PHP_URL_HOST );
            if ( ! $host ) {
                continue;
            }
            $host = strtolower( $host );
            $host = preg_replace( '#^www\.#', '', $host );

            // YouTube 連結：靠 alt 判斷頻道
            if ( strpos( $host, 'youtube.com' ) !== false || strpos( $host, 'youtu.be' ) !== false ) {
                $alt = '';
                if ( preg_match( '#alt=["\']([^"\']+)["\']#i', $inner, $am ) ) {
                    $alt = $am[1];
                }
                if ( empty( $alt ) ) {
                    $alt = wp_strip_all_tags( $inner );
                }
                if ( empty( $alt ) ) {
                    continue;
                }

                foreach ( $youtube_map as $acf_key => $keywords ) {
                    foreach ( $keywords as $kw ) {
                        if ( stripos( $alt, $kw ) !== false ) {
                            // Ani-One 特殊處理：優先中文官方
                            if ( $acf_key === 'ani_one' ) {
                                if ( stripos( $alt, '中文官方' ) !== false || stripos( $alt, '中文' ) !== false ) {
                                    $ani_one_chinese = $href;
                                } elseif ( $ani_one_first === null ) {
                                    $ani_one_first = $href;
                                }
                                break 2;
                            }

                            // 其他 YouTube：取第一個出現的
                            if ( ! isset( $streams[ $acf_key ] ) ) {
                                $streams[ $acf_key ] = $href;
                            }
                            break 2;
                        }
                    }
                }
                continue;
            }

            // 一般平台：靠 host 判斷
            foreach ( $platform_map as $needle => $acf_key ) {
                if ( strpos( $host, $needle ) !== false ) {
                    if ( ! isset( $streams[ $acf_key ] ) ) {
                        $streams[ $acf_key ] = $href;
                    }
                    break;
                }
            }
        }

        // Ani-One 最終決定：優先中文官方，否則第一個
        if ( $ani_one_chinese ) {
            $streams['ani_one'] = $ani_one_chinese;
        } elseif ( $ani_one_first ) {
            $streams['ani_one'] = $ani_one_first;
        }

        return $streams;
    }


    /**
     * 寫入 ACF 欄位（覆蓋既有 URL 並自動勾選 checkbox）
     */
    private function write_to_acf( $post_id, $streams ) {
        $updated = array();

        // 取得現有的勾選狀態
        $checked = get_post_meta( $post_id, 'anime_tw_streaming', true );
        if ( ! is_array( $checked ) ) {
            $checked = array();
        }

        foreach ( $streams as $acf_key => $url ) {
            // 寫入 URL 欄位
            update_post_meta( $post_id, 'anime_tw_streaming_url_' . $acf_key, $url );

            // 加入勾選清單（去重）
            if ( ! in_array( $acf_key, $checked, true ) ) {
                $checked[] = $acf_key;
            }

            $updated[] = $acf_key;
        }

        // 更新 checkbox 群組
        update_post_meta( $post_id, 'anime_tw_streaming', $checked );

        return $updated;
    }
}
