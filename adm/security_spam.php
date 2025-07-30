<?php
$sub_menu = '950500';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// 기본 보안 테이블 자동 생성
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

// 스팸 관리 테이블 자동 생성
if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_login_fail LIMIT 1", false)) {
    $sql_file = __DIR__ . '/security_spam.sql';
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
$login_block_enabled = gk_get_spam_config('login_block_enabled', '0');
$login_attempt_limit = gk_get_spam_config('login_attempt_limit', '5');
$login_attempt_window = gk_get_spam_config('login_attempt_window', '300');
$auto_block_duration = gk_get_spam_config('auto_block_duration', '600');
$spam_block_level = gk_get_spam_config('spam_block_level', 'access');

// User-Agent 차단 설정
$useragent_block_enabled = gk_get_spam_config('useragent_block_enabled', '0');
$useragent_block_level = gk_get_spam_config('useragent_block_level', 'access');

// 최근 로그인 실패 통계
$recent_fails_sql = "SELECT COUNT(*) as fail_count
                     FROM " . G5_TABLE_PREFIX . "security_login_fail
                     WHERE slf_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
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
/* CSS Variables for consistent theming */
:root {
    --primary-color: #007bff;
    --primary-hover: #0056b3;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --info-color: #0056b3;
    --light-gray: #f8f9fa;
    --border-color: #ddd;
    --text-color: #333;
    --text-muted: #666;
    --spacing-sm: 8px;
    --spacing-md: 15px;
    --spacing-lg: 20px;
    --border-radius: 5px;
    --font-size-sm: 12px;
    --font-size-base: 14px;
    --font-size-lg: 16px;
    --font-size-xl: 24px;
}

/* Reset and base styles */
* {
    box-sizing: border-box;
}

/* Security Dashboard Layout */
.security-dashboard {
    margin: var(--spacing-lg) 0;
}

.dashboard-title {
    color: var(--text-color);
    margin-bottom: var(--spacing-lg);
    font-size: var(--font-size-xl);
    font-weight: bold;
}

/* Section Components */
.dashboard-section {
    margin-bottom: 30px;
    background: #fff;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.section-header {
    background: var(--light-gray);
    padding: var(--spacing-md) var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
    font-weight: bold;
    font-size: var(--font-size-lg);
    color: var(--text-color);
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background-color 0.2s ease;
}

.section-header:hover {
    background: #e9ecef;
}

.section-header .collapse-icon {
    font-size: var(--font-size-sm);
    margin-left: 10px;
    transition: transform 0.2s ease;
}

.section-header.collapsed .collapse-icon {
    transform: rotate(-90deg);
}

.section-content {
    padding: var(--spacing-md);
    transition: all 0.2s ease;
}

.section-content.collapsed {
    display: none;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.stat-card {
    background: var(--light-gray);
    border: 1px solid #e9ecef;
    border-radius: var(--border-radius);
    padding: var(--spacing-md);
    text-align: center;
}

.stat-number {
    font-size: var(--font-size-xl);
    font-weight: bold;
    color: var(--text-color);
    margin-bottom: 5px;
}

.stat-number.on {
    color: var(--success-color);
}

.stat-number.off {
    color: var(--danger-color);
}

.stat-label {
    font-size: var(--font-size-base);
    color: var(--text-muted);
}

/* Information and Extension Components */
.info-highlight {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: var(--border-radius);
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
    color: var(--info-color);
    font-size: 15px;
    font-weight: 500;
}

.extension-container {
    background: rgba(248, 249, 250, 0.7);
    border: 1px solid rgba(222, 226, 230, 0.8);
    border-radius: 8px;
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
    backdrop-filter: blur(5px);
}

.extension-list {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.extension-item {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: var(--light-gray);
    border: 1px solid #dee2e6;
    border-radius: 15px;
    font-size: 13px;
    font-weight: bold;
    color: #495057;
}

.extension-item.low {
    background: #e8f5e8;
    border-color: #4caf50;
    color: #388e3c;
}

.extension-item.high {
    background: #ffebee;
    border-color: #ef5350;
    color: #d32f2f;
}

/* Feature Toggle Switch */
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

/* Form Components */
.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.form-label {
    font-weight: 600;
    color: #2d3748;
    font-size: var(--font-size-base);
}

.form-input {
    padding: var(--spacing-sm) 10px;
    border: 1px solid var(--border-color);
    border-radius: 3px;
    font-size: var(--font-size-base);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.form-help {
    font-size: var(--font-size-sm);
    color: #718096;
}

.btn-primary {
    background: var(--primary-color);
    border: 1px solid var(--primary-color);
    color: white;
    padding: var(--spacing-sm) 12px;
    border-radius: 3px;
    font-size: var(--font-size-base);
    cursor: pointer;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.btn-primary:hover {
    background: var(--primary-hover);
    border-color: #004085;
}

/* Data Table */
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
    font-size: var(--font-size-base);
}

.data-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #f1f5f9;
    font-size: var(--font-size-base);
    vertical-align: top;
}

.data-table tr:hover {
    background: #f8fafc;
}

.ip-address {
    background: #f7fafc;
    padding: 4px var(--spacing-sm);
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px var(--spacing-lg);
    color: #718096;
}

.empty-icon {
    font-size: 3em;
    margin-bottom: 16px;
    opacity: 0.5;
}

/* Accessibility */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Focus styles for better accessibility */
.section-header:focus,
.feature-switch:focus,
.btn-primary:focus,
.form-input:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .stat-card,
    .extension-container,
    .info-highlight {
        border-width: 2px;
    }
    
    .feature-switch {
        border-width: 3px;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .feature-switch,
    .feature-switch-handle,
    .section-header .collapse-icon,
    .btn-primary {
        transition: none;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        padding: 10px var(--spacing-md);
        font-size: var(--font-size-base);
    }
    
    /* Make form layout stack on mobile */
    .dashboard-section div[style*="grid-template-columns"] {
        display: flex !important;
        flex-direction: column !important;
        gap: var(--spacing-md) !important;
    }
}
</style>

<div class="security-dashboard" role="main" aria-labelledby="page-title">
    <h1 id="page-title" class="dashboard-title">스팸 관리</h1>
    
    <!-- 통계 섹션 -->
    <div class="dashboard-section" role="region" aria-labelledby="stats-heading">
        <div class="section-header" role="button" aria-expanded="true" aria-controls="stats-content" tabindex="0">
            <span id="stats-heading">📊 현황판</span>
        </div>
        <div id="stats-content" class="section-content" role="group" aria-labelledby="stats-heading">
            <div class="stats-grid" role="grid" aria-label="보안 통계 현황">
                <div class="stat-card" role="gridcell">
                    <div class="stat-number" aria-label="<?php echo number_format($recent_fails); ?>건의 로그인 실패"><?php echo number_format($recent_fails); ?>건</div>
                    <div class="stat-label">24시간 로그인 실패</div>
                </div>
                <div class="stat-card" role="gridcell">
                    <div class="stat-number" aria-label="<?php echo number_format($auto_blocks); ?>개의 현재 자동 차단된 IP"><?php echo number_format($auto_blocks); ?>건</div>
                    <div class="stat-label">현재 자동 차단</div>
                </div>
                <div class="stat-card" role="gridcell">
                    <div class="stat-number" aria-label="총 <?php echo number_format($total_auto_blocks); ?>개의 자동 차단"><?php echo number_format($total_auto_blocks); ?>건</div>
                    <div class="stat-label">전체 자동 차단</div>
                </div>
                <div class="stat-card" role="gridcell">
                    <div class="stat-number <?php echo $login_block_enabled == '1' ? 'on' : 'off'; ?>" 
                         aria-label="로그인 차단 기능이 <?php echo $login_block_enabled == '1' ? '활성화됨' : '비활성화됨'; ?>"><?php echo $login_block_enabled == '1' ? 'ON' : 'OFF'; ?></div>
                    <div class="stat-label">로그인 차단</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 로그인 차단 관리 -->
    <div class="dashboard-section" role="region" aria-labelledby="login-heading">
        <div class="section-header" 
             role="button" 
             aria-expanded="true" 
             aria-controls="login-section" 
             tabindex="0"
             onclick="toggleSection('login-section')" 
             onkeydown="handleKeyDown(event, 'login-section')">
            <span id="login-heading">🔐 로그인 차단 설정</span>
            <span id="login-toggle" aria-hidden="true">▼</span>
        </div>

        <div class="section-content" id="login-section" role="group" aria-labelledby="login-heading">
            <div class="info-highlight" role="note" aria-label="기능 설명">
                gnuboard5의 로그인 실패 이벤트를 자동으로 감지하여 IP를 차단합니다.
            </div>

            <div class="extension-container" role="group" aria-labelledby="status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="status-heading" style="font-weight: bold; font-size: 16px; color: #333;">로그인 차단 기능 상태</span>
                    <div class="feature-switch <?php echo $login_block_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $login_block_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="로그인 차단 기능 토글"
                         tabindex="0"
                         onclick="toggleLoginFeature()"
                         onkeydown="handleSwitchKeyDown(event)"
                         data-enabled="<?php echo $login_block_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $login_block_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $login_block_enabled == '1' ? '✅' : '❌'; ?></span>
                        로그인 차단 <?php echo $login_block_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🔢</span> 최대 실패 횟수: <?php echo $login_attempt_limit; ?>회
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏱️</span> 감지 윈도우: <?php echo number_format($login_attempt_window); ?>초
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏰</span> 차단 시간: <?php echo $auto_block_duration == '0' ? '영구' : number_format($auto_block_duration) . '초'; ?>
                    </div>
                </div>
            </div>

        <!-- 로그인 차단 설정 폼 -->
        <div class="extension-container" role="group" aria-labelledby="form-heading">
            <h3 id="form-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">설정 변경</h3>
            <form name="login_config_form" 
                  method="post" 
                  action="./security_spam_update.php" 
                  onsubmit="return validate_form();"
                  role="form"
                  aria-labelledby="form-heading">
                <input type="hidden" name="action" value="save_login_config">

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: start;" role="group" aria-label="로그인 차단 설정 입력">
                    <div class="form-group">
                        <label class="form-label" for="login_attempt_limit">최대 실패 횟수</label>
                        <input type="number" 
                               id="login_attempt_limit"
                               name="login_attempt_limit" 
                               value="<?php echo $login_attempt_limit; ?>"
                               class="form-input" 
                               min="1" 
                               max="50"
                               aria-describedby="limit-help"
                               required>
                        <div id="limit-help" class="form-help">지정된 시간 내에 이 횟수만큼 로그인에 실패하면 IP가 차단됩니다. (기본: 5회)</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login_attempt_window">감지 시간 윈도우 (초)</label>
                        <input type="number" 
                               id="login_attempt_window"
                               name="login_attempt_window" 
                               value="<?php echo $login_attempt_window; ?>"
                               class="form-input" 
                               min="10" 
                               max="86400"
                               aria-describedby="window-help"
                               required>
                        <div id="window-help" class="form-help">이 시간(초) 내의 로그인 실패 횟수를 누적하여 계산합니다. (기본: 300초/5분)</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="auto_block_duration">자동 차단 시간 (초)</label>
                        <input type="number" 
                               id="auto_block_duration"
                               name="auto_block_duration" 
                               value="<?php echo $auto_block_duration; ?>"
                               class="form-input" 
                               min="0" 
                               max="31536000"
                               aria-describedby="duration-help"
                               required>
                        <div id="duration-help" class="form-help">자동 차단된 IP가 이 시간(초) 동안 접근이 제한됩니다. 0이면 영구 차단됩니다. (기본: 600초/10분)</div>
                    </div>

                    <div style="display: flex; align-items: end; margin-top: 28px;">
                        <button type="submit" class="btn-primary" aria-describedby="save-help">설정 저장</button>
                        <div id="save-help" class="sr-only">로그인 차단 설정을 저장합니다</div>
                    </div>
                </div>
            </form>
        </div>

        <!-- 스팸 차단 수준 설정 -->
        <?php if ($login_block_enabled == '1'): ?>
        <div class="extension-container" role="group" aria-labelledby="level-heading">
            <h3 id="level-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">스팸 차단 수준 설정</h3>
            <form method="post" action="./security_spam_update.php" role="form" aria-labelledby="level-heading">
                <input type="hidden" name="action" value="save_spam_level">
                <div style="display: flex; flex-direction: column; gap: 12px;" role="group" aria-label="스팸 차단 수준 선택">
                    <?php
                    $spam_levels = explode(',', $spam_block_level);
                    ?>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="spam_block_level[]" value="access" 
                               <?php echo in_array('access', $spam_levels) ? 'checked' : ''; ?>
                               aria-describedby="access-help"> 
                        <strong>접속 차단</strong> - 사이트 접속 자체를 차단
                        <div id="access-help" class="form-help" style="margin-top: 4px;">로그인 실패 시 IP의 모든 접속을 차단합니다.</div>
                    </label>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="spam_block_level[]" value="login"
                               <?php echo in_array('login', $spam_levels) ? 'checked' : ''; ?>
                               aria-describedby="login-help"> 
                        <strong>로그인 차단</strong> - 로그인만 차단
                        <div id="login-help" class="form-help" style="margin-top: 4px;">로그인 시도만 차단하고 사이트 접속은 허용합니다.</div>
                    </label>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="spam_block_level[]" value="write"
                               <?php echo in_array('write', $spam_levels) ? 'checked' : ''; ?>
                               aria-describedby="write-help"> 
                        <strong>게시글/댓글 작성 차단</strong> - 글쓰기와 댓글 작성 차단
                        <div id="write-help" class="form-help" style="margin-top: 4px;">게시글이나 댓글 작성을 차단합니다.</div>
                    </label>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="spam_block_level[]" value="memo"
                               <?php echo in_array('memo', $spam_levels) ? 'checked' : ''; ?>
                               aria-describedby="memo-help"> 
                        <strong>쪽지 작성 차단</strong> - 쪽지 발송 차단
                        <div id="memo-help" class="form-help" style="margin-top: 4px;">쪽지 발송을 차단합니다.</div>
                    </label>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-primary">차단 수준 저장</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- 최근 로그인 실패 로그 -->
        <div class="extension-container" role="group" aria-labelledby="logs-heading">
            <h3 id="logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 로그인 실패 로그</h3>
            
            <?php
            $logs_sql = "SELECT slf_ip, slf_mb_id, slf_datetime, slf_user_agent
                         FROM " . G5_TABLE_PREFIX . "security_login_fail
                         ORDER BY slf_datetime DESC
                         LIMIT 20";
            $logs_result = sql_query($logs_sql, false);
            ?>

            <?php if ($logs_result && sql_num_rows($logs_result) > 0): ?>
                <table class="data-table" role="table" aria-labelledby="logs-heading" aria-describedby="logs-description">
                    <caption id="logs-description" class="sr-only">최근 20개의 로그인 실패 기록을 보여주는 표입니다.</caption>
                    <thead>
                        <tr role="row">
                            <th scope="col">IP 주소</th>
                            <th scope="col">시도한 ID</th>
                            <th scope="col">실패 시간</th>
                            <th scope="col">사용자 에이전트</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_count = 0; while ($log = sql_fetch_array($logs_result)): $row_count++; ?>
                            <tr role="row">
                                <td>
                                    <span class="ip-address" aria-label="IP 주소: <?php echo htmlspecialchars($log['slf_ip']); ?>"><?php echo htmlspecialchars($log['slf_ip']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['slf_mb_id'] ?: '(없음)'); ?></strong>
                                </td>
                                <td>
                                    <time datetime="<?php echo date('c', strtotime($log['slf_datetime'])); ?>"><?php echo $log['slf_datetime']; ?></time>
                                </td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                    title="사용자 에이전트: <?php echo htmlspecialchars($log['slf_user_agent']); ?>"
                                    aria-label="사용자 에이전트: <?php echo htmlspecialchars($log['slf_user_agent']); ?>">
                                    <?php echo htmlspecialchars($log['slf_user_agent']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="sr-only" aria-live="polite">
                    총 <?php echo $row_count; ?>개의 로그인 실패 기록이 표시되었습니다.
                </div>
            <?php else: ?>
                <div class="empty-state" role="status" aria-live="polite">
                    <div class="empty-icon" aria-hidden="true">📝</div>
                    <h3>로그인 실패 기록이 없습니다</h3>
                    <p>아직 로그인 실패 기록이 없습니다. 시스템이 자동으로 실패 시도를 감지하고 기록합니다.</p>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>

    <!-- User-Agent 차단 설정 -->
    <div class="dashboard-section">
        <div class="section-header" 
             role="button" 
             aria-expanded="true" 
             tabindex="0"
             onclick="toggleSection('useragent-section')" 
             onkeydown="handleKeyDown(event, 'useragent-section')">
            <span id="useragent-heading">🤖 User-Agent 차단 설정</span>
            <span id="useragent-toggle" aria-hidden="true">▼</span>
        </div>

        <div class="section-content" id="useragent-section" role="group" aria-labelledby="useragent-heading">
            <div class="info-highlight" role="note" aria-label="기능 설명">
                의심스러운 User-Agent를 자동으로 감지하여 IP를 차단합니다. 일반적인 브라우저가 아닌 봇이나 스크래퍼를 차단합니다.
            </div>

            <div class="extension-container" role="group" aria-labelledby="useragent-status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="useragent-status-heading" style="font-weight: bold; font-size: 16px; color: #333;">User-Agent 차단 기능 상태</span>
                    <div class="feature-switch <?php echo $useragent_block_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $useragent_block_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="User-Agent 차단 기능 토글"
                         tabindex="0"
                         onclick="toggleUserAgentFeature()"
                         onkeydown="handleSwitchKeyDown(event)"
                         data-enabled="<?php echo $useragent_block_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $useragent_block_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $useragent_block_enabled == '1' ? '✅' : '❌'; ?></span>
                        User-Agent 차단 <?php echo $useragent_block_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🛡️</span> 차단 대상: 의심스러운 봇, 스크래퍼, 비정상 User-Agent
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🔍</span> 허용 대상: 일반 브라우저, 검색엔진 봇 (Google, Bing 등)
                    </div>
                </div>
            </div>

            <!-- User-Agent 차단 수준 설정 -->
            <?php if ($useragent_block_enabled == '1'): ?>
            <div class="extension-container" role="group" aria-labelledby="useragent-level-heading">
                <h4 id="useragent-level-heading" style="margin-bottom: 15px; color: #333;">User-Agent 차단 수준 설정</h4>
                <form method="post" action="./security_spam_update.php" role="form" aria-labelledby="useragent-level-heading">
                    <input type="hidden" name="action" value="save_useragent_level">
                    <div style="display: flex; flex-direction: column; gap: 12px;" role="group" aria-label="User-Agent 차단 수준 선택">
                        <?php
                        $useragent_levels = explode(',', $useragent_block_level);
                        ?>
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="useragent_block_level[]" value="access" 
                                   <?php echo in_array('access', $useragent_levels) ? 'checked' : ''; ?>
                                   aria-describedby="useragent-access-help"> 
                            <strong>접속 차단</strong> - 사이트 접속 자체를 차단
                            <div id="useragent-access-help" class="form-help" style="margin-top: 4px;">의심스러운 User-Agent 감지 시 IP의 모든 접속을 차단합니다.</div>
                        </label>
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="useragent_block_level[]" value="login"
                                   <?php echo in_array('login', $useragent_levels) ? 'checked' : ''; ?>
                                   aria-describedby="useragent-login-help"> 
                            <strong>로그인 차단</strong> - 로그인만 차단
                            <div id="useragent-login-help" class="form-help" style="margin-top: 4px;">로그인 시도만 차단하고 사이트 접속은 허용합니다.</div>
                        </label>
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="useragent_block_level[]" value="write"
                                   <?php echo in_array('write', $useragent_levels) ? 'checked' : ''; ?>
                                   aria-describedby="useragent-write-help"> 
                            <strong>게시글/댓글 작성 차단</strong> - 글쓰기와 댓글 작성 차단
                            <div id="useragent-write-help" class="form-help" style="margin-top: 4px;">게시글이나 댓글 작성을 차단합니다.</div>
                        </label>
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="useragent_block_level[]" value="memo"
                                   <?php echo in_array('memo', $useragent_levels) ? 'checked' : ''; ?>
                                   aria-describedby="useragent-memo-help"> 
                            <strong>쪽지 작성 차단</strong> - 쪽지 발송 차단
                            <div id="useragent-memo-help" class="form-help" style="margin-top: 4px;">쪽지 발송을 차단합니다.</div>
                        </label>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn-primary">차단 수준 저장</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 차단된 User-Agent 목록 -->
            <div class="extension-container" role="group" aria-labelledby="useragent-logs-heading">
                <h4 id="useragent-logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 차단된 User-Agent</h4>
                
                <?php
                $useragent_logs_sql = "SELECT sl_ip, sl_user_agent, sl_datetime, sl_block_reason
                                       FROM " . G5_TABLE_PREFIX . "security_ip_log
                                       WHERE sl_block_reason LIKE '%User-Agent%'
                                       ORDER BY sl_datetime DESC
                                       LIMIT 10";
                $useragent_logs_result = sql_query($useragent_logs_sql, false);
                ?>

                <?php if ($useragent_logs_result && sql_num_rows($useragent_logs_result) > 0): ?>
                    <table class="data-table" role="table" aria-labelledby="useragent-logs-heading">
                        <thead>
                            <tr role="row">
                                <th scope="col">IP 주소</th>
                                <th scope="col">User-Agent</th>
                                <th scope="col">차단 시간</th>
                                <th scope="col">차단 사유</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = sql_fetch_array($useragent_logs_result)): ?>
                                <tr role="row">
                                    <td>
                                        <span class="ip-address"><?php echo htmlspecialchars($log['sl_ip']); ?></span>
                                    </td>
                                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($log['sl_user_agent']); ?>">
                                        <?php echo htmlspecialchars($log['sl_user_agent']); ?>
                                    </td>
                                    <td>
                                        <time datetime="<?php echo date('c', strtotime($log['sl_datetime'])); ?>"><?php echo $log['sl_datetime']; ?></time>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['sl_block_reason']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <div class="empty-icon" aria-hidden="true">🤖</div>
                        <h3>차단된 User-Agent가 없습니다</h3>
                        <p>아직 의심스러운 User-Agent로 인한 차단 기록이 없습니다.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
// 접근성을 고려한 섹션 토글 함수
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    const toggle = document.getElementById(sectionId.replace('-section', '-toggle'));
    const header = toggle.closest('.section-header');
    
    const isExpanded = section.style.display !== 'none';
    
    if (isExpanded) {
        section.style.display = 'none';
        toggle.textContent = '▶';
        header.setAttribute('aria-expanded', 'false');
    } else {
        section.style.display = 'block';
        toggle.textContent = '▼';
        header.setAttribute('aria-expanded', 'true');
    }
}

// 키보드 접근성을 위한 이벤트 핸들러
function handleKeyDown(event, sectionId) {
    // Enter 또는 Space 키로 토글 실행
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleSection(sectionId);
    }
}

// 스위치 컨트롤을 위한 키보드 핸들러
function handleSwitchKeyDown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleLoginFeature();
    }
}

function toggleLoginFeature() {
    const toggle = document.querySelector('.feature-switch');
    const enabled = toggle.dataset.enabled === '1';
    const newState = enabled ? '0' : '1';

    // 접근성을 위한 즉시 상태 업데이트
    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    
    // 시각적 피드백을 위한 로딩 상태 표시
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';

    // AJAX로 설정 변경
    fetch('./security_spam_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_login&login_block_enabled=${newState}&ajax=1`
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('success') || result.includes('성공')) {
            // 성공 시 페이지 새로고침
            location.reload();
        } else {
            // 실패 시 상태 복구
            toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
            toggle.style.opacity = '1';
            toggle.style.pointerEvents = 'auto';
            
            // 접근 가능한 오류 메시지 표시
            const errorMsg = document.createElement('div');
            errorMsg.setAttribute('role', 'alert');
            errorMsg.textContent = '설정 변경에 실패했습니다.';
            errorMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 10px; border-radius: 5px; z-index: 1000;';
            document.body.appendChild(errorMsg);
            
            setTimeout(() => {
                document.body.removeChild(errorMsg);
            }, 5000);
        }
    })
    .catch(error => {
        // 네트워크 오류 시 상태 복구
        toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
        toggle.style.opacity = '1';
        toggle.style.pointerEvents = 'auto';
        
        const errorMsg = document.createElement('div');
        errorMsg.setAttribute('role', 'alert');
        errorMsg.textContent = '네트워크 오류가 발생했습니다: ' + error.message;
        errorMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 10px; border-radius: 5px; z-index: 1000;';
        document.body.appendChild(errorMsg);
        
        setTimeout(() => {
            document.body.removeChild(errorMsg);
        }, 5000);
    });
}


function validate_form() {
    var limit = document.querySelector('input[name="login_attempt_limit"]').value;
    var window_time = document.querySelector('input[name="login_attempt_window"]').value;
    var block_duration = document.querySelector('input[name="auto_block_duration"]').value;

    if (limit < 1 || limit > 50) {
        alert('최대 실패 횟수는 1~50 사이의 값이어야 합니다.');
        return false;
    }

    if (window_time < 10 || window_time > 86400) {
        alert('감지 시간 윈도우는 10초~86400초(24시간) 사이의 값이어야 합니다.');
        return false;
    }

    if (block_duration < 0 || block_duration > 31536000) {
        alert('자동 차단 시간은 0초~31536000초(1년) 사이의 값이어야 합니다.');
        return false;
    }

    return true;
}

function toggleUserAgentFeature() {
    const toggle = document.querySelector('[onclick="toggleUserAgentFeature()"]');
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    // AJAX로 상태 변경
    const formData = new FormData();
    formData.append('action', 'toggle_useragent');
    formData.append('useragent_block_enabled', newState);
    formData.append('ajax', '1');
    
    fetch('./security_spam_update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data === 'success') {
            // 페이지 새로고침으로 UI 전체 업데이트
            location.reload();
        } else {
            alert('설정 변경에 실패했습니다.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('오류가 발생했습니다.');
    });
}

// 페이지 로드 시 바로 표시 (애니메이션 제거)
document.addEventListener('DOMContentLoaded', function() {
    // 애니메이션 없이 바로 표시
    console.log('스팸 관리 페이지 로드 완료');
});
</script>

<?php
include_once('./admin.tail.php');
?>