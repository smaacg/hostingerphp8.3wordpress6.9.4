<?php
/**
 * 檔案名稱: admin/pages/import-tool.php
 * 安全修正：所有第三方資料插入 DOM 一律經過 escHtml() 或 .text()
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cn_converter    = new Anime_Sync_CN_Converter();
$converter_stats = $cn_converter->get_stats();
?>

<div class="wrap anime-sync-import-tool">
    <h1>匯入動畫工具</h1>

    <div class="notice notice-info inline" style="margin:15px 0 10px;border-left-color:#2271b1;">
        <p>
            <strong>🔍 繁簡轉換器狀態：</strong>
            詞條總數 <code><?php echo number_format( (int)( $converter_stats['dict_entry_count'] ?? 0 ) ); ?></code> 條 |
            <?php if ( ! empty( $converter_stats['dict_file_exists'] ) ) : ?>
                <span style="color:green;font-weight:bold;">✓ 運作正常</span>
                | <small>測試：「脚本」→「<?php echo esc_html( $cn_converter->convert( '脚本' ) ); ?>」</small>
            <?php else : ?>
                <span style="color:red;font-weight:bold;">❌ 字典載入失敗</span>
            <?php endif; ?>
            | <small>模式：<code><?php echo esc_html( $converter_stats['mode'] ?? 'unknown' ); ?></code></small>
        </p>
    </div>

    <h2 class="nav-tab-wrapper" style="margin-bottom:0;">
        <a href="#single"  class="nav-tab nav-tab-active" data-tab="single">📥 單筆匯入</a>
        <a href="#season"  class="nav-tab" data-tab="season">📅 季度批次匯入</a>
        <a href="#batch"   class="nav-tab" data-tab="batch">📋 ID 清單匯入</a>
        <a href="#series"  class="nav-tab" data-tab="series">🔗 系列分析匯入</a>
        <a href="#ranking" class="nav-tab" data-tab="ranking">🏆 人氣排行匯入</a>
    </h2>

    <!-- TAB 1：單筆匯入 -->
    <div id="tab-single" class="anime-sync-tab-content" style="display:block;">
        <div class="asc-card" style="max-width:680px;margin-top:20px;">
            <h3>透過 AniList ID 匯入</h3>
            <table class="form-table">
                <tr>
                    <th><label for="single-anilist-id">AniList ID</label></th>
                    <td>
                        <input type="number" id="single-anilist-id" class="regular-text" placeholder="例如: 1535">
                        <p class="description">請輸入作品在 AniList 網址結尾的數字。</p>
                    </td>
                </tr>
                <tr>
                    <th>選項</th>
                    <td><label><input type="checkbox" id="single-force-update"> 強制接管卡住的同步鎖（一般情況不需勾選）</label></td>
                </tr>
            </table>
            <p><button type="button" id="btn-single-import" class="button button-primary button-large">開始匯入</button></p>
            <!-- ★ 結果區塊：只用 textContent 填寫，不接受 HTML -->
            <div id="single-import-result" style="margin-top:20px;padding:15px;display:none;border-radius:4px;"></div>
        </div>
    </div>

    <!-- TAB 2：季度批次匯入 -->
    <div id="tab-season" class="anime-sync-tab-content" style="display:none;">
        <div class="asc-card" style="margin-top:20px;">
            <h3>按季度篩選</h3>
            <div style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px;">
                <div>
                    <label>年份</label><br>
                    <select id="season-year-select">
                        <?php for ( $y = date('Y')+1; $y >= 2000; $y-- ) : ?>
                            <option value="<?php echo esc_attr( $y ); ?>"<?php selected( date('Y'), $y ); ?>><?php echo esc_html( $y ); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label>季節</label><br>
                    <select id="season-select">
                        <option value="WINTER">冬季 (1–3月)</option>
                        <option value="SPRING">春季 (4–6月)</option>
                        <option value="SUMMER">夏季 (7–9月)</option>
                        <option value="FALL">秋季 (10–12月)</option>
                    </select>
                </div>
                <div>
                    <button type="button" id="btn-season-query" class="button">第一步：查詢季度清單</button>
                    <span id="season-query-spinner" style="display:none;margin-left:8px;">⏳ 查詢中，可能需要 10–30 秒…</span>
                </div>
            </div>
            <div id="season-preview" style="display:none;">
                <p id="season-preview-summary" class="description" style="font-size:13px;"></p>
                <div id="season-format-filter" style="margin-bottom:12px;padding:10px 12px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                    <strong style="margin-right:4px;">篩選格式：</strong>
                    <label><input type="checkbox" class="format-filter-check" value="TV" checked> TV</label>
                    <label><input type="checkbox" class="format-filter-check" value="MOVIE" checked> MOVIE</label>
                    <label><input type="checkbox" class="format-filter-check" value="OVA" checked> OVA</label>
                    <label><input type="checkbox" class="format-filter-check" value="ONA" checked> ONA</label>
                    <label><input type="checkbox" class="format-filter-check" value="SPECIAL" checked> SPECIAL</label>
                    <label><input type="checkbox" class="format-filter-check" value="MUSIC"> MUSIC</label>
                    <button type="button" id="btn-apply-format-filter" class="button button-small" style="margin-left:8px;">套用篩選</button>
                    <span id="season-filter-count" style="color:#666;font-size:12px;"></span>
                </div>
                <div class="asc-table-wrap">
                    <table class="wp-list-table widefat fixed striped asc-season-table">
                        <thead><tr>
                            <th style="width:36px;"><input id="season-select-all" type="checkbox" checked></th>
                            <th style="width:70px;">ID</th>
                            <th>名稱 (Romaji)</th>
                            <th style="width:80px;">格式</th>
                            <th style="width:60px;">集數</th>
                            <th style="width:90px;">人氣</th>
                            <th style="width:90px;">狀態</th>
                            <th style="width:80px;">站內狀態</th>
                        </tr></thead>
                        <tbody id="season-anime-tbody"></tbody>
                    </table>
                </div>
                <div style="margin-top:15px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="button" id="btn-season-import" class="button button-primary">第二步：開始批次匯入選中項</button>
                    <button type="button" id="btn-season-stop" class="button" style="display:none;color:red;">停止匯入</button>
                    <span id="season-throttle-notice" style="display:none;color:#d97706;font-weight:bold;"></span>
                </div>
                <div id="season-progress-wrap" style="margin-top:20px;display:none;">
                    <div style="background:#eee;height:20px;border-radius:10px;overflow:hidden;">
                        <div id="season-progress-bar" style="background:#2271b1;width:0%;height:100%;transition:width .3s;"></div>
                    </div>
                    <p id="season-progress-text" style="text-align:center;font-weight:bold;"></p>
                    <div id="season-import-log" class="asc-log-box"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3：ID 清單批次匯入 -->
    <div id="tab-batch" class="anime-sync-tab-content" style="display:none;">
        <div class="asc-card" style="max-width:680px;margin-top:20px;">
            <h3>大量 ID 清單匯入</h3>
            <p class="description">請輸入 AniList ID，用換行或逗號隔開。</p>
            <textarea id="batch-id-list" rows="8" class="large-text" placeholder="例如:&#10;1535&#10;21,20&#10;16498"></textarea>
            <p id="batch-id-count" style="color:#666;">0 個 ID</p>
            <p><label><input type="checkbox" id="batch-force-update"> 強制接管卡住的同步鎖（一般情況不需勾選）</label></p>
            <p>
                <button type="button" id="btn-batch-import" class="button button-primary">開始批次匯入</button>
                <button type="button" id="btn-batch-stop" class="button" style="display:none;color:red;">停止</button>
                <span id="batch-throttle-notice" style="display:none;color:#d97706;font-weight:bold;margin-left:10px;"></span>
            </p>
            <div id="batch-progress-wrap" style="margin-top:20px;display:none;">
                <div style="background:#eee;height:20px;border-radius:10px;overflow:hidden;">
                    <div id="batch-progress-bar" style="background:#2271b1;width:0%;height:100%;"></div>
                </div>
                <p id="batch-progress-text" style="text-align:center;font-weight:bold;"></p>
                <div id="batch-import-log" class="asc-log-box"></div>
            </div>
        </div>
    </div>

    <!-- TAB 4：系列分析匯入 -->
    <div id="tab-series" class="anime-sync-tab-content" style="display:none;">
        <div class="asc-card" style="margin-top:20px;">
            <h3>🔗 系列分析與匯入</h3>
            <p class="description">輸入任意一部作品的 AniList ID，系統將自動追溯前作、列出完整系列，並標記哪些已匯入。</p>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:20px;">
                <input type="number" id="series-anilist-id" class="regular-text" placeholder="輸入任意一部的 AniList ID，例如 20958">
                <button type="button" id="btn-analyze-series" class="button button-primary">🔍 分析系列</button>
                <span id="series-analyze-spinner" style="display:none;">⏳ 分析中，正在遞迴追溯前作…</span>
            </div>
            <div id="series-result" style="display:none;">
                <div id="series-info" style="background:#f0f7ff;border:1px solid #b8d4f5;border-radius:4px;padding:12px;margin-bottom:15px;"></div>
                <div class="asc-table-wrap">
                    <table class="wp-list-table widefat fixed striped asc-series-table">
                        <thead><tr>
                            <th style="width:36px;"><input id="series-select-all" type="checkbox" checked></th>
                            <th style="width:70px;">ID</th>
                            <th>作品名稱</th>
                            <th style="width:80px;">格式</th>
                            <th style="width:70px;">年份</th>
                            <th style="width:100px;">關聯類型</th>
                            <th style="width:90px;">站內狀態</th>
                        </tr></thead>
                        <tbody id="series-tbody"></tbody>
                    </table>
                </div>
                <div style="margin-top:15px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="button" id="btn-series-import" class="button button-primary">📥 匯入選中作品並歸入系列</button>
                    <button type="button" id="btn-series-stop" class="button" style="display:none;color:red;">停止</button>
                    <span id="series-throttle-notice" style="display:none;color:#d97706;font-weight:bold;"></span>
                </div>
                <div id="series-progress-wrap" style="margin-top:20px;display:none;">
                    <div style="background:#eee;height:20px;border-radius:10px;overflow:hidden;">
                        <div id="series-progress-bar" style="background:#2271b1;width:0%;height:100%;transition:width .3s;"></div>
                    </div>
                    <p id="series-progress-text" style="text-align:center;font-weight:bold;"></p>
                    <div id="series-import-log" class="asc-log-box"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 5：人氣排行匯入 -->
    <div id="tab-ranking" class="anime-sync-tab-content" style="display:none;">
        <div class="asc-card" style="margin-top:20px;">
            <h3>🏆 AniList 人氣排行匯入</h3>
            <p class="description">依 AniList 人氣排行載入，每次 50 部，標記已匯入狀態，未匯入預設勾選。</p>
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:15px;flex-wrap:wrap;">
                <button type="button" id="btn-ranking-load" class="button">📄 載入排行（第 <span id="ranking-page-num">1</span> 頁）</button>
                <button type="button" id="btn-ranking-more" class="button" style="display:none;">➕ 載入更多 50 部</button>
                <span id="ranking-load-spinner" style="display:none;">⏳ 載入中…</span>
            </div>
            <div id="ranking-preview" style="display:none;">
                <p id="ranking-preview-summary" class="description"></p>
                <div class="asc-table-wrap">
                    <table class="wp-list-table widefat fixed striped asc-ranking-table">
                        <thead><tr>
                            <th style="width:36px;"><input id="ranking-select-all" type="checkbox" checked></th>
                            <th style="width:40px;">#</th>
                            <th style="width:60px;">封面</th>
                            <th>作品名稱</th>
                            <th style="width:80px;">格式</th>
                            <th style="width:60px;">集數</th>
                            <th style="width:90px;">人氣</th>
                            <th style="width:90px;">站內狀態</th>
                        </tr></thead>
                        <tbody id="ranking-tbody"></tbody>
                    </table>
                </div>
                <div style="margin-top:15px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="button" id="btn-ranking-import" class="button button-primary" style="display:none;">📥 匯入選中作品</button>
                    <button type="button" id="btn-ranking-stop" class="button" style="display:none;color:red;">停止</button>
                    <span id="ranking-throttle-notice" style="display:none;color:#d97706;font-weight:bold;"></span>
                </div>
                <div id="ranking-progress-wrap" style="margin-top:20px;display:none;">
                    <div style="background:#eee;height:20px;border-radius:10px;overflow:hidden;">
                        <div id="ranking-progress-bar" style="background:#2271b1;width:0%;height:100%;transition:width .3s;"></div>
                    </div>
                    <p id="ranking-progress-text" style="text-align:center;font-weight:bold;"></p>
                    <div id="ranking-import-log" class="asc-log-box"></div>
                </div>
            </div>
        </div>
    </div>

</div><!-- .anime-sync-import-tool -->

<style>
.anime-sync-import-tool { max-width:none !important; }
#wpcontent { padding-right:20px; }
.asc-card { background:#fff; border:1px solid #ccd0d4; padding:20px 24px; box-shadow:0 1px 1px rgba(0,0,0,.04); border-radius:4px; margin-bottom:20px; }
.asc-table-wrap { overflow-x:auto; margin-bottom:10px; border:1px solid #ddd; }
.asc-table-wrap table { width:100%; min-width:600px; }
.asc-season-table  th:nth-child(3),
.asc-series-table  th:nth-child(3),
.asc-ranking-table th:nth-child(4) { width:auto; min-width:200px; }
.asc-cover-thumb { width:36px; height:50px; object-fit:cover; border-radius:2px; display:block; }
.asc-log-box { background:#111; color:#0f0; padding:10px; height:220px; overflow-y:auto; font-family:monospace; font-size:12px; margin-top:10px; border-radius:5px; }
.log-success  { color:#0f0; }
.log-warning  { color:#f90; font-weight:bold; }
.log-error    { color:#f33; }
.log-skip     { color:#aaa; }
.log-info     { color:#3df; border-bottom:1px solid #333; padding-bottom:2px; margin-bottom:5px; }
.log-throttle { color:#ff0; font-weight:bold; }
.status-imported { color:#46b450; font-weight:bold; }
.status-new      { color:#2271b1; }
#single-import-result.success { background:#edfaef; border:1px solid #46b450; color:#235926; }
#single-import-result.warning { background:#fff8e5; border:1px solid #d97706; color:#7a4b00; }
#single-import-result.error   { background:#fcf0f1; border:1px solid #dc3232; color:#a42821; }
#season-anime-tbody tr.format-hidden { display:none; }
</style>

