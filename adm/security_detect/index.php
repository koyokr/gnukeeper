<?php
require_once './_common.php';

$g5['title'] = '탐지 관리';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 공통 보안 CSS 포함
echo '<link rel="stylesheet" href="'.G5_ADMIN_URL.'/css/security_common.css?ver='.G5_CSS_VER.'">';

// Plugin Admin 클래스 사용
$detectAdmin = GK_SpamAdmin::getInstance();
$stats = $detectAdmin->getSpamStats();
?>


<div class="security-dashboard">
    <!-- Header -->
    <h1 class="dashboard-title">🔍 보안 탐지 관리</h1>
    <p class="dashboard-subtitle">자동 위협 탐지, 로그인 차단, 행동 분석을 통한 종합적인 보안 탐지 시스템</p>

    <!-- 통계 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['login_fail_count']); ?></div>
            <div class="stat-label">로그인 실패</div>
            <div class="stat-trend">24시간 기준</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['blocked_ip_count']); ?></div>
            <div class="stat-label">차단된 IP</div>
            <div class="stat-trend">현재 활성</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['spam_detected_count']); ?></div>
            <div class="stat-label">위협 탐지</div>
            <div class="stat-trend">24시간 기준</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['active_features_count']; ?></div>
            <div class="stat-label">활성 보안 기능</div>
            <div class="stat-trend">7개 기능 중</div>
        </div>
    </div>

    <!-- 로그인 차단 관리 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('login-threat-card')">
            🔐 로그인 위협 탐지 <span id="login-threat-toggle">▶</span>
        </div>
        <div class="card-content" id="login-threat-card">
            <div class="info-highlight">
                과도한 로그인 시도를 탐지하고 자동으로 IP를 차단합니다.
            </div>
            <div class="toggle-section">
                <div class="toggle-info">
                    <h3 class="toggle-title">로그인 위협 자동 차단</h3>
                    <p class="toggle-desc">연속 로그인 실패 시 자동 IP 차단 (기본: 5회 실패 시 차단)<br>
                    <span class="toggle-desc" style="margin-top: 4px; display: inline-block;">로그인 위협 탐지가 OFF 상태여도 위험한 IP 목록을 확인할 수 있습니다.</span></p>
                </div>
                <input type="checkbox"
                       id="login-block-toggle"
                       class="toggle-input"
                       <?php echo $stats['login_block_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'login_block')">
                <label for="login-block-toggle" class="toggle-switch"></label>
            </div>

            <!-- 의심 IP 목록 -->
            <div class="sub-card">
                <div class="sub-card-header" onclick="toggleSubCard('login-logs-details', this)">
                    5회 이상 실패한 의심 IP <span class="sub-card-toggle">▶</span>
                </div>
                <div class="sub-card-content" id="login-logs-details">
                    <div id="login-logs-table" class="ip-list">
                        <!-- 동적으로 로드됨 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User-Agent 차단 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('bot-detection-card')">
            🤖 악성 봇 탐지 <span id="bot-detection-toggle">▶</span>
        </div>
        <div class="card-content" id="bot-detection-card">
            <div class="info-highlight">
                알려진 악성 봇과 스크래퍼의 User-Agent를 탐지하여 차단합니다.
            </div>
            <div class="toggle-section">
                <div class="toggle-info">
                    <h3 class="toggle-title">User-Agent 필터링</h3>
                    <p class="toggle-desc">의심스러운 User-Agent 패턴을 차단합니다<br>
                    <span class="toggle-desc" style="margin-top: 4px; display: inline-block;">User-Agent 필터링이 OFF 상태여도 차단된 봇 목록을 확인할 수 있습니다.</span></p>
                </div>
                <input type="checkbox"
                       id="useragent-block-toggle"
                       class="toggle-input"
                       <?php echo $stats['useragent_block_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'useragent_block')">
                <label for="useragent-block-toggle" class="toggle-switch"></label>
            </div>

            <!-- 의심 봇 목록 -->
            <div class="sub-card">
                <div class="sub-card-header" onclick="toggleSubCard('bot-logs-details', this)">
                    악성 봇 차단 목록 <span class="sub-card-toggle">▶</span>
                </div>
                <div class="sub-card-content" id="bot-logs-details">
                    <div id="bot-logs-table" class="ip-list">
                        <div class="empty-state">
                            <div class="empty-state-icon">🤖</div>
                            <p>차단된 악성 봇이 없습니다</p>
                            <small>User-Agent 필터링으로 차단된 봇이 발견되지 않았습니다.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 행동 분석 차단 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('behavior-analysis-card')">
            📊 비정상 행동 탐지 <span id="behavior-analysis-toggle">▶</span>
        </div>
        <div class="card-content" id="behavior-analysis-card">
            <div class="info-highlight">
                의심스러운 행동 패턴을 탐지하여 자동으로 차단합니다.
            </div>
            <div class="toggle-section">
                <div class="toggle-info">
                    <h3 class="toggle-title">행동 패턴 차단</h3>
                    <p class="toggle-desc">404 스캔, 비정상 Referer 등 악성 행동 패턴을 탐지하여 차단<br>
                    <span class="toggle-desc" style="margin-top: 4px; display: inline-block;">행동 패턴 차단이 OFF 상태여도 차단된 IP 목록을 확인할 수 있습니다.</span></p>
                </div>
                <input type="checkbox"
                       id="behavior-pattern-toggle"
                       class="toggle-input"
                       <?php echo ($stats['behavior_404_enabled'] == '1' || $stats['behavior_referer_enabled'] == '1') ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'behavior_pattern')">
                <label for="behavior-pattern-toggle" class="toggle-switch"></label>
            </div>

            <!-- 비정상 행동 탐지 목록 -->
            <div class="sub-card">
                <div class="sub-card-header" onclick="toggleSubCard('behavior-logs-details', this)">
                    비정상 행동 차단 IP <span class="sub-card-toggle">▶</span>
                </div>
                <div class="sub-card-content" id="behavior-logs-details">
                    <div id="behavior-logs-table" class="ip-list">
                        <div class="empty-state">
                            <div class="empty-state-icon">📊</div>
                            <p>비정상 행동으로 차단된 IP가 없습니다</p>
                            <small>404 스캔이나 비정상 Referer로 차단된 IP가 발견되지 않았습니다.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 다중 사용자 차단 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('multiuser-detection-card')">
            👥 다중 사용자 탐지 <span id="multiuser-detection-toggle">▶</span>
        </div>
        <div class="card-content" id="multiuser-detection-card">
            <div class="info-highlight">
                동일 IP에서의 과도한 계정 생성 및 로그인을 탐지하여 차단합니다.
            </div>
            <div class="toggle-section">
                <div class="toggle-info">
                    <h3 class="toggle-title">다중 사용자 차단</h3>
                    <p class="toggle-desc">동일 IP에서 다중 계정 생성 및 동시 로그인을 차단<br>
                    <span class="toggle-desc" style="margin-top: 4px; display: inline-block;">다중 사용자 차단이 OFF 상태여도 의심 IP 목록을 확인할 수 있습니다.</span></p>
                </div>
                <input type="checkbox"
                       id="multiuser-protection-toggle"
                       class="toggle-input"
                       <?php echo ($stats['multiuser_register_enabled'] == '1' || $stats['multiuser_login_enabled'] == '1') ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'multiuser_protection')">
                <label for="multiuser-protection-toggle" class="toggle-switch"></label>
            </div>

            <!-- 다중 사용자 의심 목록 -->
            <div class="sub-card">
                <div class="sub-card-header" onclick="toggleSubCard('multiuser-logs-details', this)">
                    다중 계정 의심 IP <span class="sub-card-toggle">▶</span>
                </div>
                <div class="sub-card-content" id="multiuser-logs-details">
                    <div id="multiuser-logs-table" class="ip-list">
                        <div class="empty-state">
                            <div class="empty-state-icon">👥</div>
                            <p>다중 계정 의심 IP가 없습니다</p>
                            <small>동일 IP에서 다중 계정을 생성한 의심스러운 활동이 발견되지 않았습니다.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 정규식 스팸 차단 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('spam-content-card')">
            🔍 스팸 콘텐츠 탐지 <span id="spam-content-toggle">▶</span>
        </div>
        <div class="card-content" id="spam-content-card">
            <div class="info-highlight">
                정규식 패턴을 이용한 고급 악성 콘텐츠 탐지 및 필터링을 수행합니다.
            </div>
            <div class="toggle-section">
                <div class="toggle-info">
                    <h3 class="toggle-title">정규식 필터링</h3>
                    <p class="toggle-desc">사용자 정의 정규식 패턴으로 스팸 콘텐츠 차단<br>
                    <span class="toggle-desc" style="margin-top: 4px; display: inline-block;">정규식 필터링이 OFF 상태여도 차단된 스팸 IP 목록을 확인할 수 있습니다.</span></p>
                </div>
                <input type="checkbox"
                       id="regex-spam-toggle"
                       class="toggle-input"
                       <?php echo $stats['regex_spam_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'regex_spam')">
                <label for="regex-spam-toggle" class="toggle-switch"></label>
            </div>

            <!-- 스팸 콘텐츠 차단 목록 -->
            <div class="sub-card">
                <div class="sub-card-header" onclick="toggleSubCard('spam-logs-details', this)">
                    스팸 콘텐츠 차단 IP <span class="sub-card-toggle">▶</span>
                </div>
                <div class="sub-card-content" id="spam-logs-details">
                    <div id="spam-logs-table" class="ip-list">
                        <div class="empty-state">
                            <div class="empty-state-icon">🔍</div>
                            <p>스팸 콘텐츠로 차단된 IP가 없습니다</p>
                            <small>정규식 필터링으로 차단된 스팸 IP가 발견되지 않았습니다.</small>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// 유틸리티 함수들
const showToast = (message, type = 'info') => {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
};

const apiCall = async (action, data = {}) => {
    try {
        const formData = new FormData();
        formData.append('action', action);
        Object.entries(data).forEach(([key, value]) => {
            formData.append(key, value);
        });

        const response = await fetch('./api.php', {
            method: 'POST',
            body: formData
        });

        // 응답 상태 코드 확인
        if (!response.ok) {
            console.error(`HTTP Error: ${response.status} ${response.statusText}`);
            
            // 403 (권한 없음) 에러 특별 처리
            if (response.status === 403) {
                return { success: false, message: '관리자 권한이 필요합니다. 다시 로그인해주세요.' };
            }
            
            // 500 에러의 경우 응답 텍스트를 확인
            if (response.status === 500) {
                const responseText = await response.text();
                console.error('500 Error Response:', responseText);
                return { success: false, message: '서버 오류가 발생했습니다. 콘솔을 확인해주세요.' };
            }
            
            return { success: false, message: `HTTP ${response.status}: ${response.statusText}` };
        }

        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API Error:', error);
        
        // JSON 파싱 에러인 경우
        if (error.name === 'SyntaxError' && error.message.includes('JSON')) {
            return { success: false, message: '서버 응답을 처리할 수 없습니다. 관리자에게 문의하세요.' };
        }
        
        return { success: false, message: '네트워크 오류가 발생했습니다: ' + error.message };
    }
};

// 기능 토글
const toggleFeature = async (checkbox, feature) => {
    const enabled = checkbox.checked;

    const result = await apiCall('toggle_feature', {
        feature: feature,
        enabled: enabled ? '1' : '0'
    });

    showToast(result.message, result.success ? 'success' : 'error');

    if (!result.success) {
        // 실패 시 체크박스 상태 되돌리기
        checkbox.checked = !enabled;
    }
};

// 로그인 실패 로그 로드
const loadLoginFailLogs = async (page = 1) => {
    const result = await apiCall('get_login_fail_logs', { page: page, limit: 10 });
    const container = document.getElementById('login-logs-table');

    if (result.success && result.data.length > 0) {
        container.innerHTML = `
            <table class="ip-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <button class="btn-delete-all" onclick="deleteAllLoginFail()" title="모든 로그인 실패 기록 삭제">
                                🗑️
                            </button>
                        </th>
                        <th>IP 주소</th>
                        <th>사용자 ID</th>
                        <th>실패 횟수</th>
                        <th>최근 시도</th>
                        <th>대응 내용</th>
                        <th>처리 기능</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(log => `
                        <tr ${log.fail_count >= 10 ? 'class="high-risk"' : log.fail_count >= 7 ? 'class="medium-risk"' : ''}>
                            <td>
                                <button class="btn-delete" onclick="deleteSuspectIP('${log.slf_ip}', '${log.slf_mb_id}', this)" title="이 의심 IP 기록 삭제">
                                    ✕
                                </button>
                            </td>
                            <td>
                                <div class="ip-address">${log.slf_ip}</div>
                            </td>
                            <td>
                                <div class="log-user">${log.slf_mb_id || '-'}</div>
                            </td>
                            <td>
                                <div>
                                    <span class="fail-count ${log.fail_count >= 10 ? 'danger' : log.fail_count >= 7 ? 'warning' : 'normal'}">${log.fail_count}회</span>
                                </div>
                            </td>
                            <td>
                                <div class="log-time">${log.slf_datetime}</div>
                            </td>
                            <td>
                                <div>
                                    ${log.action_status === 'auto_blocked' 
                                        ? '<span class="action-status auto-blocked">🔒 자동차단</span>' 
                                        : '<span class="action-status none">-</span>'}
                                </div>
                            </td>
                            <td>
                                <div class="text-center">
                                    ${log.action_status !== 'auto_blocked' 
                                        ? `<button class="btn btn-sm btn-danger" onclick="addToBlockList('${log.slf_ip}', this)">IP 차단</button>`
                                        : '<span class="text-muted">이미 차단됨</span>'}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            ${result.total_pages > 1 ? `
                <div class="pagination">
                    ${page > 1 ? `<button class="btn btn-secondary btn-sm" onclick="loadLoginFailLogs(${page - 1})">이전</button>` : ''}
                    <span class="page-info">페이지 ${page} / ${result.total_pages}</span>
                    ${page < result.total_pages ? `<button class="btn btn-secondary btn-sm" onclick="loadLoginFailLogs(${page + 1})">다음</button>` : ''}
                </div>
            ` : ''}
        `;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🔐</div>
                <p>5회 이상 실패한 IP가 없습니다</p>
                <small>로그인을 5회 이상 실패한 의심스러운 IP가 발견되지 않았습니다.</small>
            </div>
        `;
    }
};

// 비정상 행동 탐지 로그 로드
const loadBehaviorLogs = async (page = 1) => {
    const result = await apiCall('get_behavior_logs', { page: page, limit: 10 });
    const container = document.getElementById('behavior-logs-table');

    if (result.success && result.data.length > 0) {
        container.innerHTML = `
            <table class="ip-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <button class="btn-delete-all" onclick="deleteAllBehaviorLogs()" title="모든 비정상 행동 탐지 기록 삭제">
                                🗑️
                            </button>
                        </th>
                        <th>IP 주소</th>
                        <th>탐지 사유</th>
                        <th>탐지 횟수</th>
                        <th>최근 탐지</th>
                        <th>상태</th>
                        <th>처리 기능</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(log => `
                        <tr ${log.action_status === 'blocked' ? 'class="blocked-ip"' : ''}>
                            <td>
                                <button class="btn-delete" onclick="deleteBehaviorLog('${log.sb_ip}', this)" title="이 비정상 행동 탐지 기록 삭제">
                                    ✕
                                </button>
                            </td>
                            <td>
                                <div class="ip-address">${log.sb_ip}</div>
                            </td>
                            <td>
                                <div class="behavior-reason" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${log.last_activity_reason}">
                                    ${log.last_activity_reason}
                                </div>
                            </td>
                            <td>
                                <div>
                                    <span class="detection-count ${log.block_count >= 10 ? 'danger' : log.block_count >= 5 ? 'warning' : 'normal'}">${log.block_count || log.detection_count}회</span>
                                </div>
                            </td>
                            <td>
                                <div class="log-time">${log.last_activity_time}</div>
                            </td>
                            <td>
                                <div>
                                    ${log.action_status === 'blocked' 
                                        ? '<span class="action-status blocked">🔒 차단됨</span>' 
                                        : '<span class="action-status detected">⚠️ 탐지됨</span>'}
                                </div>
                            </td>
                            <td>
                                <div class="text-center">
                                    ${log.action_status !== 'blocked' 
                                        ? `<button class="btn btn-sm btn-danger" onclick="addToBlockList('${log.sb_ip}', this)">IP 차단</button>`
                                        : '<span class="text-muted">이미 차단됨</span>'}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            ${result.total_pages > 1 ? `
                <div class="pagination">
                    ${page > 1 ? `<button class="btn btn-secondary btn-sm" onclick="loadBehaviorLogs(${page - 1})">이전</button>` : ''}
                    <span class="page-info">페이지 ${page} / ${result.total_pages}</span>
                    ${page < result.total_pages ? `<button class="btn btn-secondary btn-sm" onclick="loadBehaviorLogs(${page + 1})">다음</button>` : ''}
                </div>
            ` : ''}
        `;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📊</div>
                <p>비정상 행동으로 차단된 IP가 없습니다</p>
                <small>404 스캔이나 비정상 Referer로 차단된 IP가 발견되지 않았습니다.</small>
            </div>
        `;
    }
};

// 비정상 행동 탐지 기록 삭제
const deleteBehaviorLog = async (ip, button) => {
    if (!confirm(`IP ${ip}의 모든 비정상 행동 탐지 기록을 삭제하시겠습니까?`)) return;

    // 버튼 비활성화
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳';

    const result = await apiCall('delete_behavior_log', { 
        ip: ip 
    });
    
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        // 성공시 해당 행 제거
        const row = button.closest('tr');
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            row.remove();
            
            // 테이블이 비어있으면 다시 로드
            const tableBody = row.closest('tbody');
            if (!tableBody.children.length) {
                loadBehaviorLogs();
            }
        }, 300);
    } else {
        // 실패시 버튼 복구
        button.disabled = false;
        button.innerHTML = originalText;
    }
};

// 봇 로그 로드 (User-Agent 필터로 차단된 로그)
const loadBotLogs = async (page = 1) => {
    const result = await apiCall('get_bot_logs', { page: page, limit: 10 });
    const container = document.getElementById('bot-logs-table');

    if (result.success && result.data.length > 0) {
        container.innerHTML = `
            <table class="ip-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <button class="btn-delete-all" onclick="deleteAllBotLogs()" title="모든 봇 탐지 기록 삭제">
                                🗑️
                            </button>
                        </th>
                        <th>IP 주소</th>
                        <th>User-Agent</th>
                        <th>탐지 시간</th>
                        <th>요청 URL</th>
                        <th>처리 기능</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(log => `
                        <tr>
                            <td>
                                <button class="btn-delete" onclick="deleteBotLog('${log.sl_id}', '${log.sl_ip}', this)" title="이 봇 탐지 기록 삭제">
                                    ✕
                                </button>
                            </td>
                            <td>
                                <div class="ip-address">${log.sl_ip}</div>
                            </td>
                            <td>
                                <div class="user-agent" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${log.sl_user_agent}">
                                    ${log.sl_user_agent}
                                </div>
                            </td>
                            <td>
                                <div class="log-time">${log.sl_datetime}</div>
                            </td>
                            <td>
                                <div class="request-url" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${log.sl_url}">
                                    ${log.sl_url || '-'}
                                </div>
                            </td>
                            <td>
                                <div>
                                    ${log.action_status === 'auto_blocked' 
                                        ? '<span class="btn btn-sm btn-success" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724; cursor: default; white-space: nowrap;">✓ IP 차단 완료</span>' 
                                        : `<button class="btn btn-sm btn-danger" onclick="addToBlockList('${log.sl_ip}', this)">IP 차단</button>`}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            ${result.total_pages > 1 ? `
                <div class="pagination">
                    ${page > 1 ? `<button class="btn btn-secondary btn-sm" onclick="loadBotLogs(${page - 1})">이전</button>` : ''}
                    <span class="page-info">페이지 ${page} / ${result.total_pages}</span>
                    ${page < result.total_pages ? `<button class="btn btn-secondary btn-sm" onclick="loadBotLogs(${page + 1})">다음</button>` : ''}
                </div>
            ` : ''}
        `;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🤖</div>
                <p>차단된 악성 봇이 없습니다</p>
                <small>User-Agent 필터링으로 차단된 봇이 발견되지 않았습니다.</small>
            </div>
        `;
    }
};


// IP 차단 해제
const unblockIP = async (ip) => {
    if (!confirm(`IP ${ip}의 차단을 해제하시겠습니까?`)) return;

    const result = await apiCall('unblock_ip', { ip: ip });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadLoginFailLogs();
    }
};

// IP를 차단 리스트에 추가
const addToBlockList = async (ip, button) => {
    if (!confirm(`IP ${ip}를 차단 리스트에 추가하시겠습니까?`)) return;

    // 버튼 비활성화
    button.disabled = true;
    button.textContent = '처리 중...';

    const result = await apiCall('add_to_block_list', { ip: ip });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        // 성공시 봇 로그 리로드
        loadBotLogs();
    } else {
        // 실패시 버튼 복구
        button.disabled = false;
        button.textContent = 'IP 차단';
    }
};

// 봇 탐지 기록 삭제
const deleteBotLog = async (log_id, ip, button) => {
    if (!confirm(`IP ${ip}의 User-Agent 탐지 기록을 삭제하시겠습니까?`)) return;

    // 버튼 비활성화
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳';

    const result = await apiCall('delete_bot_log', { 
        log_id: log_id
    });
    
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        // 성공시 해당 행 제거
        const row = button.closest('tr');
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            row.remove();
            
            // 테이블이 비어있으면 다시 로드
            const tableBody = row.closest('tbody');
            if (!tableBody.children.length) {
                loadBotLogs();
            }
        }, 300);
    } else {
        // 실패시 버튼 복구
        button.disabled = false;
        button.innerHTML = originalText;
    }
};

// 의심 IP 기록 삭제
const deleteSuspectIP = async (ip, mb_id, button) => {
    // mb_id가 '-'면 빈 문자열로 처리
    const userId = (mb_id === '-') ? '' : mb_id;
    const userDisplay = userId || '알 수 없음';
    
    if (!confirm(`IP ${ip} (사용자: ${userDisplay})의 로그인 실패 기록을 모두 삭제하시겠습니까?`)) return;

    // 버튼 비활성화
    button.disabled = true;
    button.textContent = '삭제중...';

    try {
        const result = await apiCall('delete_suspect_ip', { 
            ip: ip, 
            mb_id: userId 
        });

        showToast(result.message, result.success ? 'success' : 'error');

        if (result.success) {
            // 해당 행 제거 전에 tbody 참조 먼저 저장
            const tableBody = button.closest('tbody');
            const tableRow = button.closest('tr');
            
            if (tableRow) {
                tableRow.remove();
                
                // 테이블이 비어있으면 전체 새로고침
                if (tableBody && tableBody.children.length === 0) {
                    loadLoginFailLogs();
                }
            }
        } else {
            // 실패시 버튼 복구
            button.disabled = false;
            button.textContent = '✕';
        }
    } catch (error) {
        console.error('Delete error:', error);
        showToast('삭제 중 오류가 발생했습니다.', 'error');
        
        // 오류시 버튼 복구
        button.disabled = false;
        button.textContent = '✕';
    }
};

// Sub-card 토글 함수 (차단관리와 동일)
const toggleSubCard = (targetId, headerElement) => {
    const targetContent = document.getElementById(targetId);
    const toggle = headerElement.querySelector('.sub-card-toggle');
    
    if (targetContent.classList.contains('show')) {
        targetContent.classList.remove('show');
        toggle.textContent = '▶';
        toggle.style.transform = 'rotate(0deg)';
    } else {
        targetContent.classList.add('show');
        toggle.textContent = '▼';
        toggle.style.transform = 'rotate(90deg)';
        
        // 처음 열 때만 로그를 로드
        if (targetId === 'login-logs-details' && !headerElement.dataset.loaded) {
            loadLoginFailLogs();
            headerElement.dataset.loaded = 'true';
        } else if (targetId === 'bot-logs-details' && !headerElement.dataset.loaded) {
            loadBotLogs();
            headerElement.dataset.loaded = 'true';
        } else if (targetId === 'behavior-logs-details' && !headerElement.dataset.loaded) {
            loadBehaviorLogs();
            headerElement.dataset.loaded = 'true';
        }
    }
};


// 카드 토글 함수 (security_extension.php와 동일)
function toggleCard(cardId) {
    const content = document.getElementById(cardId);
    const toggle = document.getElementById(cardId.replace('-card', '-toggle'));

    if (content.classList.contains('show')) {
        content.classList.remove('show');
        toggle.style.transform = 'rotate(0deg)';
    } else {
        content.classList.add('show');
        toggle.style.transform = 'rotate(90deg)';
    }
}

// 모든 로그인 실패 기록 삭제
const deleteAllLoginFail = async () => {
    if (!confirm('⚠️ 모든 로그인 실패 기록을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.')) return;
    
    // 이중 확인
    if (!confirm('정말로 모든 로그인 실패 기록을 삭제하시겠습니까?\n\n삭제 후에는 복구할 수 없습니다.')) return;

    const result = await apiCall('delete_all_login_fail');
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        // 성공시 테이블 다시 로드
        loadLoginFailLogs();
    }
};

// 모든 비정상 행동 탐지 기록 삭제
const deleteAllBehaviorLogs = async () => {
    if (!confirm('⚠️ 모든 비정상 행동 탐지 기록을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.')) return;
    
    // 이중 확인
    if (!confirm('정말로 모든 비정상 행동 탐지 기록을 삭제하시겠습니까?\n\n삭제 후에는 복구할 수 없습니다.')) return;

    const result = await apiCall('delete_all_behavior_logs');
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        // 성공시 테이블 다시 로드
        loadBehaviorLogs();
    }
};

// 모든 봇 탐지 기록 삭제
const deleteAllBotLogs = async () => {
    if (!confirm('⚠️ 모든 악성 봇 탐지 기록을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.')) return;
    
    // 이중 확인
    if (!confirm('정말로 모든 봇 탐지 기록을 삭제하시겠습니까?\n\n삭제 후에는 복구할 수 없습니다.')) return;

    const result = await apiCall('delete_all_bot_logs');
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        // 성공시 테이블 다시 로드
        loadBotLogs();
    }
};

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', () => {
    // 의심 IP 목록은 토글할 때 로드됨

    // security_extension과 동일한 자동 카드 펼치기 기능
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';

            // 각 카드를 순차적으로 펼치기
            setTimeout(() => {
                const cardContent = card.querySelector('.card-content');
                const toggle = card.querySelector('[id$="-toggle"]');
                if (cardContent && toggle) {
                    cardContent.classList.add('show');
                    toggle.textContent = '▼';
                    toggle.style.transform = 'rotate(90deg)';
                }
            }, 500);
        }, index * 100);
    });
});
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>