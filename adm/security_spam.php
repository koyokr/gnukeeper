<?php
$sub_menu = '950500';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// 테이블 자동 생성
if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_config LIMIT 1", false)) {
    $sql_file = __DIR__ . '/security_block.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        $sql_content = str_replace('{PREFIX}', G5_TABLE_PREFIX, $sql_content);
        $statements = explode(';', $sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && strpos($statement, '--') !== 0) {
                sql_query($statement, false);
            }
        }
    }
}

// 현재 설정 로드
function gk_get_spam_config($key, $default = '') {
    $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = '" . sql_escape_string($key) . "'";
    $result = sql_query($sql, false);
    
    if ($result && $row = sql_fetch_array($result)) {
        return $row['sc_value'];
    }
    
    return $default;
}

// 설정값들
$login_block_enabled = gk_get_spam_config('login_block_enabled', '1');
$login_attempt_limit = gk_get_spam_config('login_attempt_limit', '5');
$login_attempt_window = gk_get_spam_config('login_attempt_window', '300');
$auto_block_duration = gk_get_spam_config('auto_block_duration', '600');

// 최근 로그인 실패 통계
$recent_fails_sql = "SELECT COUNT(*) as fail_count 
                     FROM " . G5_TABLE_PREFIX . "security_ip_log 
                     WHERE sl_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                       AND sl_block_reason LIKE '로그인 실패%'";
$recent_fails_result = sql_query($recent_fails_sql, false);
$recent_fails = 0;
if ($recent_fails_result && $row = sql_fetch_array($recent_fails_result)) {
    $recent_fails = $row['fail_count'];
}

// 자동 차단된 IP 수
$auto_blocks_sql = "SELECT COUNT(*) as block_count 
                    FROM " . G5_TABLE_PREFIX . "security_ip_block 
                    WHERE sb_block_type = 'auto_login' 
                      AND sb_status = 'active'";
$auto_blocks_result = sql_query($auto_blocks_sql, false);
$auto_blocks = 0;
if ($auto_blocks_result && $row = sql_fetch_array($auto_blocks_result)) {
    $auto_blocks = $row['block_count'];
}

// 전체 자동 차단 통계
$total_auto_blocks_sql = "SELECT COUNT(*) as total_count 
                          FROM " . G5_TABLE_PREFIX . "security_ip_block 
                          WHERE sb_block_type IN ('auto_login', 'auto_spam', 'auto_abuse')";
$total_auto_blocks_result = sql_query($total_auto_blocks_sql, false);
$total_auto_blocks = 0;
if ($total_auto_blocks_result && $row = sql_fetch_array($total_auto_blocks_result)) {
    $total_auto_blocks = $row['total_count'];
}

$g5['title'] = '스팸 관리';
include_once('./admin.head.php');
?>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f8fafc;
}

.security-container {
    margin: 20px 0;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-title {
    color: #1a202c;
    margin-bottom: 20px;
    font-size: 32px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
    text-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.dashboard-subtitle {
    color: #718096;
    font-size: 18px;
    margin-bottom: 30px;
    font-weight: 400;
    line-height: 1.6;
}

/* 통계 카드 */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--accent-color);
}

.stat-card.fails::before { --accent-color: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
.stat-card.blocks::before { --accent-color: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%); }
.stat-card.total::before { --accent-color: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%); }
.stat-card.status::before { --accent-color: linear-gradient(135deg, #007bff 0%, #0056b3 100%); }

.stat-icon {
    font-size: 2.5em;
    margin-bottom: 12px;
    display: block;
}

.stat-number {
    font-size: 2.2em;
    font-weight: 800;
    color: #1a202c;
    margin-bottom: 8px;
    display: block;
}

.stat-label {
    color: #718096;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* gnuboard 연동 안내 */
.integration-notice {
    background: linear-gradient(135deg, #e8f4fd 0%, #d4edda 100%);
    border: 1px solid #bee5eb;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.integration-content h4 {
    margin: 0 0 8px 0;
    color: #0c5460;
    font-size: 18px;
    font-weight: 700;
}

.integration-content p {
    margin: 0;
    color: #055160;
    font-size: 14px;
    line-height: 1.5;
}

.toggle-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.feature-switch {
    position: relative;
    width: 60px;
    height: 30px;
    background: #e2e8f0;
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #cbd5e0;
}

.feature-switch.enabled {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    border-color: #38a169;
}

.feature-switch-handle {
    position: absolute;
    width: 26px;
    height: 26px;
    background: white;
    border-radius: 13px;
    top: 1px;
    left: 1px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.feature-switch.enabled .feature-switch-handle {
    transform: translateX(30px);
}

/* 섹션 스타일 */
.security-section {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    margin-bottom: 30px;
}

.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 24px 32px;
    color: white;
    font-weight: 700;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* 폼 스타일 */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    padding: 24px 32px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label {
    font-weight: 600;
    color: #2d3748;
    font-size: 14px;
}

.form-input {
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.form-select {
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    transition: all 0.3s ease;
}

.form-help {
    font-size: 12px;
    color: #718096;
}

.form-radio-group {
    display: flex;
    gap: 20px;
    margin-top: 8px;
}

.form-radio-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-radio {
    width: 18px;
    height: 18px;
}

.btn-primary {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border: none;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
}

/* 테이블 스타일 */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #f7fafc;
    padding: 16px 12px;
    text-align: left;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
    font-size: 14px;
}

.data-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    vertical-align: top;
}

.data-table tr:hover {
    background: #f8fafc;
}

.ip-address {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    background: #f7fafc;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #718096;
}

.empty-icon {
    font-size: 3em;
    margin-bottom: 16px;
    opacity: 0.5;
}
</style>

<div class="security-container">
    <h1 class="dashboard-title">
        🛡️ 스팸 관리
    </h1>
    <p class="dashboard-subtitle">
        로그인 실패 시도를 감지하여 자동으로 IP를 차단하는 기능을 관리합니다. 브루트포스 공격으로부터 사이트를 보호할 수 있습니다.
    </p>

    <!-- 통계 카드 -->
    <div class="stats-grid">
        <div class="stat-card fails">
            <span class="stat-icon">🚫</span>
            <span class="stat-number"><?php echo number_format($recent_fails); ?></span>
            <span class="stat-label">24시간 로그인 실패</span>
        </div>
        <div class="stat-card blocks">
            <span class="stat-icon">⚡</span>
            <span class="stat-number"><?php echo number_format($auto_blocks); ?></span>
            <span class="stat-label">현재 자동 차단</span>
        </div>
        <div class="stat-card total">
            <span class="stat-icon">📊</span>
            <span class="stat-number"><?php echo number_format($total_auto_blocks); ?></span>
            <span class="stat-label">전체 자동 차단</span>
        </div>
        <div class="stat-card status">
            <span class="stat-icon"><?php echo $login_block_enabled == '1' ? '🟢' : '🔴'; ?></span>
            <span class="stat-number"><?php echo $login_block_enabled == '1' ? 'ON' : 'OFF'; ?></span>
            <span class="stat-label">로그인 차단</span>
        </div>
    </div>

    <!-- gnuboard 연동 안내 -->
    <div class="integration-notice">
        <div class="integration-content" style="flex: 1;">
            <h4>🔗 gnuboard5 통합 정보</h4>
            <p>
                이 기능은 gnuboard5의 로그인 실패 이벤트를 자동으로 감지하여 IP를 차단합니다.<br>
                기존 gnuboard5 파일 수정 없이 안전하게 작동합니다.
            </p>
        </div>
        <div class="toggle-controls">
            <div class="feature-switch <?php echo $login_block_enabled == '1' ? 'enabled' : ''; ?>" 
                 onclick="toggleSpamFeature()" 
                 data-enabled="<?php echo $login_block_enabled; ?>">
                <div class="feature-switch-handle"></div>
            </div>
        </div>
    </div>

    <!-- 설정 폼 -->
    <div class="security-section">
        <div class="section-header">
            <span>⚙️ 스팸 차단 설정</span>
        </div>
        
        <form name="spam_config_form" method="post" action="./security_spam_update.php" onsubmit="return validate_form();">
            <input type="hidden" name="action" value="save_config">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">로그인 차단 기능</label>
                    <div class="form-radio-group">
                        <div class="form-radio-item">
                            <input type="radio" name="login_block_enabled" value="1" id="login_block_enabled_1" 
                                   <?php echo ($login_block_enabled == '1') ? 'checked' : ''; ?> class="form-radio">
                            <label for="login_block_enabled_1">활성화</label>
                        </div>
                        <div class="form-radio-item">
                            <input type="radio" name="login_block_enabled" value="0" id="login_block_enabled_0" 
                                   <?php echo ($login_block_enabled == '0') ? 'checked' : ''; ?> class="form-radio">
                            <label for="login_block_enabled_0">비활성화</label>
                        </div>
                    </div>
                    <div class="form-help">로그인 실패 시도 감지 및 자동 차단 기능을 활성화/비활성화합니다.</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">최대 실패 횟수</label>
                    <input type="number" name="login_attempt_limit" value="<?php echo $login_attempt_limit; ?>" 
                           class="form-input" min="1" max="50" style="width: 120px;">
                    <div class="form-help">지정된 시간 내에 이 횟수만큼 로그인에 실패하면 IP가 차단됩니다. (기본: 5회)</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">감지 시간 윈도우</label>
                    <select name="login_attempt_window" class="form-select">
                        <option value="60" <?php echo ($login_attempt_window == '60') ? 'selected' : ''; ?>>1분</option>
                        <option value="300" <?php echo ($login_attempt_window == '300') ? 'selected' : ''; ?>>5분</option>
                        <option value="600" <?php echo ($login_attempt_window == '600') ? 'selected' : ''; ?>>10분</option>
                        <option value="1800" <?php echo ($login_attempt_window == '1800') ? 'selected' : ''; ?>>30분</option>
                        <option value="3600" <?php echo ($login_attempt_window == '3600') ? 'selected' : ''; ?>>1시간</option>
                    </select>
                    <div class="form-help">이 시간 내의 로그인 실패 횟수를 누적하여 계산합니다. (기본: 5분)</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">자동 차단 시간</label>
                    <select name="auto_block_duration" class="form-select">
                        <option value="300" <?php echo ($auto_block_duration == '300') ? 'selected' : ''; ?>>5분</option>
                        <option value="600" <?php echo ($auto_block_duration == '600') ? 'selected' : ''; ?>>10분</option>
                        <option value="1800" <?php echo ($auto_block_duration == '1800') ? 'selected' : ''; ?>>30분</option>
                        <option value="3600" <?php echo ($auto_block_duration == '3600') ? 'selected' : ''; ?>>1시간</option>
                        <option value="7200" <?php echo ($auto_block_duration == '7200') ? 'selected' : ''; ?>>2시간</option>
                        <option value="21600" <?php echo ($auto_block_duration == '21600') ? 'selected' : ''; ?>>6시간</option>
                        <option value="86400" <?php echo ($auto_block_duration == '86400') ? 'selected' : ''; ?>>24시간</option>
                    </select>
                    <div class="form-help">자동 차단된 IP가 이 시간 동안 접근이 제한됩니다. (기본: 10분)</div>
                </div>
            </div>
            
            <div style="padding: 0 32px 24px;">
                <button type="submit" class="btn-primary">설정 저장</button>
            </div>
        </form>
    </div>

    <!-- 최근 로그인 실패 로그 -->
    <div class="security-section">
        <div class="section-header">
            <span>📋 최근 로그인 실패 로그</span>
        </div>

        <?php
        $logs_sql = "SELECT sl_ip, sl_datetime, sl_user_agent, sl_block_reason
                     FROM " . G5_TABLE_PREFIX . "security_ip_log 
                     WHERE sl_block_reason LIKE '로그인 실패%'
                     ORDER BY sl_datetime DESC 
                     LIMIT 20";
        $logs_result = sql_query($logs_sql, false);
        ?>

        <?php if ($logs_result && sql_num_rows($logs_result) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>IP 주소</th>
                        <th>실패 시간</th>
                        <th>사용자 에이전트</th>
                        <th>상세 정보</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = sql_fetch_array($logs_result)): ?>
                        <tr>
                            <td>
                                <span class="ip-address"><?php echo htmlspecialchars($log['sl_ip']); ?></span>
                            </td>
                            <td><?php echo $log['sl_datetime']; ?></td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                title="<?php echo htmlspecialchars($log['sl_user_agent']); ?>">
                                <?php echo htmlspecialchars($log['sl_user_agent']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['sl_block_reason']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <h3>로그인 실패 기록이 없습니다</h3>
                <p>아직 로그인 실패 기록이 없습니다. 시스템이 자동으로 실패 시도를 감지하고 기록합니다.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSpamFeature() {
    const toggle = document.querySelector('.feature-switch');
    const enabled = toggle.dataset.enabled === '1';
    
    // AJAX로 설정 변경
    fetch('./security_spam_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_feature&login_block_enabled=${enabled ? '0' : '1'}&ajax=1`
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('success') || result.includes('성공')) {
            // UI 업데이트
            toggle.classList.toggle('enabled');
            toggle.dataset.enabled = enabled ? '0' : '1';
            
            // 통계 카드 업데이트
            const statusCard = document.querySelector('.stat-card.status .stat-icon');
            const statusNumber = document.querySelector('.stat-card.status .stat-number');
            
            if (enabled) {
                statusCard.textContent = '🔴';
                statusNumber.textContent = 'OFF';
            } else {
                statusCard.textContent = '🟢';
                statusNumber.textContent = 'ON';
            }
        } else {
            alert('설정 변경에 실패했습니다.');
        }
    })
    .catch(error => {
        alert('오류가 발생했습니다: ' + error);
    });
}

function validate_form() {
    var limit = document.querySelector('input[name="login_attempt_limit"]').value;
    
    if (limit < 1 || limit > 50) {
        alert('최대 실패 횟수는 1~50 사이의 값이어야 합니다.');
        return false;
    }
    
    return true;
}

// 페이지 로드 애니메이션
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card, .security-section');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php
include_once('./admin.tail.php');
?>