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
                    <h3 class="toggle-title">자동 차단 기능</h3>
                    <p class="toggle-desc">스팸 콘텐츠 탐지 시 자동으로 IP 차단<br>
                    <span class="toggle-desc" style="margin-top: 4px; display: inline-block;">OFF 상태에서도 스팸 탐지 및 로그 기록은 계속 동작합니다.</span></p>
                </div>
                <input type="checkbox"
                       id="regex-spam-toggle"
                       class="toggle-input"
                       <?php echo $stats['spam_content_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'regex_spam')">
                <label for="regex-spam-toggle" class="toggle-switch"></label>
            </div>

            <!-- 스팸 콘텐츠 차단 목록 -->
            <div class="sub-card">
                <div class="sub-card-header" onclick="toggleSubCard('spam-logs-details', this)">
                    스팸 콘텐츠 탐지 로그 <span class="sub-card-toggle">▶</span>
                </div>
                <div class="sub-card-content" id="spam-logs-details">
                    <div id="spam-logs-table" class="ip-list">
                        <div class="empty-state">
                            <div class="empty-state-icon">🔍</div>
                            <p>스팸 콘텐츠 탐지 로그가 없습니다</p>
                            <small>스팸 키워드로 탐지된 콘텐츠가 발견되지 않았습니다.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 스팸 키워드 관리 -->
            <div class="sub-card">
                <div class="sub-card-header" onclick="toggleSubCard('spam-keywords-details', this)">
                    스팸 키워드 관리 
                    <div style="display: inline-flex; align-items: center; gap: 10px; margin-left: auto;">
                        <button class="btn btn-sm btn-secondary" onclick="resetSpamKeywords()" style="font-size: 12px; padding: 4px 8px;" title="기본 시스템 키워드로 초기화">
                            🔄 설정 초기화
                        </button>
                        <span class="sub-card-toggle">▶</span>
                    </div>
                </div>
                <div class="sub-card-content" id="spam-keywords-details">
                    <div class="keyword-management">
                        <div class="keyword-add-section">
                            <h4>새 키워드 추가</h4>
                            <div class="keyword-form">
                                <select id="keyword-category">
                                    <option value="성인/유흥 광고 필터">성인/유흥 광고 필터</option>
                                    <option value="도박/먹튀 필터">도박/먹튀 필터</option>
                                    <option value="성기능/의약품 필터">성기능/의약품 필터</option>
                                    <option value="온라인 환전/도박 필터">온라인 환전/도박 필터</option>
                                    <option value="기타">기타</option>
                                </select>
                                <input type="text" id="keyword-text" placeholder="키워드 입력" maxlength="100">
                                <select id="keyword-score">
                                    <option value="1">위험도 1</option>
                                    <option value="2">위험도 2</option>
                                    <option value="3" selected>위험도 3</option>
                                    <option value="4">위험도 4</option>
                                    <option value="5">위험도 5</option>
                                </select>
                                <button class="btn btn-primary" onclick="addSpamKeyword()">추가</button>
                            </div>
                        </div>
                        
                        <div id="keywords-table" class="keywords-list">
                            <!-- 키워드 목록이 로드됩니다 -->
                        </div>
                    </div>
                </div>
            </div>

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

// 콘텐츠 샘플에서 탐지된 키워드 주변 텍스트 추출
const getContentSampleWithContext = (content, keywords) => {
    if (!content || !keywords.length) return '';
    
    const contentLower = content.toLowerCase();
    let bestMatch = null;
    let bestPos = -1;
    
    // 가장 먼저 나타나는 키워드 찾기
    for (const kw of keywords) {
        const pos = contentLower.indexOf(kw.keyword.toLowerCase());
        if (pos !== -1 && (bestPos === -1 || pos < bestPos)) {
            bestMatch = kw.keyword;
            bestPos = pos;
        }
    }
    
    if (bestMatch && bestPos !== -1) {
        const start = Math.max(0, bestPos - 10);
        const end = Math.min(content.length, bestPos + bestMatch.length + 10);
        const sample = content.substring(start, end);
        
        return (start > 0 ? '...' : '') + sample + (end < content.length ? '...' : '');
    }
    
    return content.substring(0, 100) + (content.length > 100 ? '...' : '');
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

// 다중 사용자 탐지 로그 로드
const loadMultiUserLogs = async (page = 1) => {
    const container = document.getElementById('multiuser-logs-table');
    if (!container) return;

    container.innerHTML = '<div class="loading-spinner">로딩 중...</div>';

    const result = await apiCall('get_multiuser_logs', { page: page, limit: 10 });

    if (result.success && result.logs && result.logs.length > 0) {
        container.innerHTML = `
            <table class="ip-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <button class="btn-delete-all" onclick="deleteAllMultiUserLogs(this)" title="모든 다중 사용자 탐지 기록 삭제">
                                🗑️
                            </button>
                        </th>
                        <th>IP 주소</th>
                        <th>계정 수</th>
                        <th>생성된 계정</th>
                        <th>최초 탐지</th>
                        <th>상태</th>
                        <th>처리 기능</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.logs.map(log => `
                        <tr ${log.smu_blocked == '1' ? 'class="blocked-ip"' : ''}>
                            <td>
                                <button class="btn-delete" onclick="deleteMultiUserLog('${log.smu_ip}', this)" title="이 다중 사용자 탐지 기록 삭제">
                                    ✕
                                </button>
                            </td>
                            <td>
                                <div class="ip-address">${log.smu_ip}</div>
                            </td>
                            <td>
                                <div>
                                    <span class="detection-count ${log.smu_count >= 5 ? 'danger' : log.smu_count >= 3 ? 'warning' : 'normal'}">${log.smu_count}개</span>
                                </div>
                            </td>
                            <td>
                                <div class="behavior-reason" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${log.smu_member_list || '정보 없음'}">
                                    ${log.smu_member_list || '정보 없음'}
                                </div>
                            </td>
                            <td>
                                <div class="log-time">${log.smu_first_detected}</div>
                            </td>
                            <td>
                                <div>
                                    ${log.smu_blocked == '1' 
                                        ? '<span class="action-status blocked">🔒 차단됨</span>' 
                                        : '<span class="action-status detected">👥 탐지됨</span>'}
                                </div>
                            </td>
                            <td>
                                <div class="text-center">
                                    ${log.smu_blocked == '0' 
                                        ? `<button class="btn btn-sm btn-danger" onclick="blockMultiUserIP('${log.smu_ip}', this)">IP 차단</button>`
                                        : '<span class="text-muted">이미 차단됨</span>'}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            ${result.total_pages > 1 ? `
                <div class="pagination">
                    ${page > 1 ? `<button class="btn btn-secondary btn-sm" onclick="loadMultiUserLogs(${page - 1})">이전</button>` : ''}
                    <span class="page-info">페이지 ${page} / ${result.total_pages}</span>
                    ${page < result.total_pages ? `<button class="btn btn-secondary btn-sm" onclick="loadMultiUserLogs(${page + 1})">다음</button>` : ''}
                </div>
            ` : ''}
        `;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <p>다중 계정 의심 IP가 없습니다</p>
                <small>하루에 3개 이상의 계정을 생성한 IP가 발견되지 않았습니다.</small>
            </div>
        `;
    }
};

// 다중 사용자 IP 차단
const blockMultiUserIP = async (ip, button) => {
    if (!confirm(`IP ${ip}를 차단하시겠습니까?`)) return;

    button.disabled = true;
    button.textContent = '처리 중...';

    const result = await apiCall('block_multiuser_ip', { ip: ip });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadMultiUserLogs();
    } else {
        button.disabled = false;
        button.textContent = 'IP 차단';
    }
};

// 다중 사용자 탐지 기록 삭제
const deleteMultiUserLog = async (ip, button) => {
    if (!confirm(`IP ${ip}의 다중 사용자 탐지 기록을 삭제하시겠습니까?`)) return;

    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳';

    const result = await apiCall('delete_multiuser_log', { ip: ip });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        const row = button.closest('tr');
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            row.remove();
            
            const tableBody = row.closest('tbody');
            if (!tableBody.children.length) {
                loadMultiUserLogs();
            }
        }, 300);
    } else {
        button.disabled = false;
        button.innerHTML = originalText;
    }
};

// 모든 다중 사용자 로그 삭제
const deleteAllMultiUserLogs = async (button) => {
    if (!confirm('모든 다중 사용자 탐지 로그를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) return;

    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ 삭제 중...';

    const result = await apiCall('delete_all_multiuser_logs');
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadMultiUserLogs();
    } else {
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
        } else if (targetId === 'multiuser-logs-details' && !headerElement.dataset.loaded) {
            loadMultiUserLogs();
            headerElement.dataset.loaded = 'true';
        } else if (targetId === 'spam-logs-details' && !headerElement.dataset.loaded) {
            loadSpamContentLogs();
            headerElement.dataset.loaded = 'true';
        } else if (targetId === 'spam-keywords-details' && !headerElement.dataset.loaded) {
            loadSpamKeywords();
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

// 스팸 콘텐츠 탐지 로그 로드
const loadSpamContentLogs = async (page = 1, filters = {}) => {
    console.log('Loading spam content logs...');
    const container = document.getElementById('spam-logs-table');
    let result = { success: false, data: [] };
    
    try {
        // API 호출을 직접 fetch로 수행
        const formData = new FormData();
        formData.append('action', 'get_spam_content_logs');
        formData.append('page', page);
        formData.append('limit', 10);
        
        // 필터 파라미터 추가
        Object.keys(filters).forEach(key => {
            formData.append(key, filters[key]);
        });
        
        console.log('Sending API request...');
        const response = await fetch('./api.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('API response status:', response.status);
        result = await response.json();
        console.log('API result:', result);
        console.log('result.success:', result.success);
        console.log('result.data:', result.data);
        console.log('result.data type:', typeof result.data);
        console.log('result.data length:', result.data ? result.data.length : 'undefined');
    } catch (error) {
        console.error('API call failed:', error);
        result = { success: false, data: [], error: error.message };
    }

    if (result.success && result.data && result.data.length > 0) {
        container.innerHTML = `
            <table class="ip-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <button class="btn-delete-all" onclick="deleteAllSpamContentLogs()" title="모든 스팸 콘텐츠 탐지 로그 삭제">
                                🗑️
                            </button>
                        </th>
                        <th>IP 주소</th>
                        <th>작성자 ID</th>
                        <th>게시판 정보</th>
                        <th>탐지 키워드</th>
                        <th>위험 점수</th>
                        <th>콘텐츠 샘플</th>
                        <th>탐지 시간</th>
                        <th>조치</th>
                        <th>처리</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(log => {
                        const keywords = JSON.parse(log.sscl_detected_keywords || '[]');
                        const keywordsByCategory = {};
                        keywords.forEach(k => {
                            if (!keywordsByCategory[k.category]) {
                                keywordsByCategory[k.category] = [];
                            }
                            keywordsByCategory[k.category].push(k.keyword);
                        });
                        
                        const categoryText = Object.keys(keywordsByCategory).map(cat => {
                            const kws = keywordsByCategory[cat].join(', ');
                            return `[${cat}] ${kws}`;
                        }).join(' / ');
                        
                        const contentSample = getContentSampleWithContext(log.sscl_content_sample, keywords);
                        const boardUrl = log.sscl_bo_table && log.sscl_wr_id ? `/bbs/board.php?bo_table=${log.sscl_bo_table}&wr_id=${log.sscl_wr_id}` : '';
                        
                        return `
                        <tr ${log.sscl_auto_blocked == '1' ? 'class="blocked-ip"' : ''}>
                            <td>
                                <button class="btn-delete" onclick="deleteSpamContentLog('${log.sscl_id}', this)" title="이 스팸 탐지 로그 삭제">
                                    ✕
                                </button>
                            </td>
                            <td>
                                <div class="ip-address">${log.sscl_ip}</div>
                            </td>
                            <td>
                                <div class="log-user">${log.sscl_mb_id || '-'}</div>
                            </td>
                            <td>
                                <div class="board-info">
                                    ${log.sscl_bo_table ? 
                                        `<div class="board-name">${log.sscl_bo_table}</div>
                                         <small class="board-link">
                                             ${boardUrl ? `<a href="${boardUrl}" target="_blank" title="게시글 보기">글#${log.sscl_wr_id}</a>` : '게시글 삭제됨'}
                                         </small>` 
                                        : '<span class="text-muted">-</span>'}
                                </div>
                            </td>
                            <td>
                                <div class="keyword-list" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;" title="${categoryText}">
                                    <span class="keyword-count">${log.sscl_keyword_count}개:</span><br>
                                    <small>${categoryText}</small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <span class="score-badge ${log.sscl_total_score >= 12 ? 'danger' : log.sscl_total_score >= 7 ? 'warning' : 'normal'}">${log.sscl_total_score}점</span>
                                </div>
                            </td>
                            <td>
                                <div class="content-sample" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${log.sscl_content_sample || ''}">
                                    ${contentSample}
                                </div>
                            </td>
                            <td>
                                <div class="log-time">${log.sscl_datetime}</div>
                            </td>
                            <td>
                                <div>
                                    ${log.sscl_action_taken === 'auto_blocked' 
                                        ? '<span class="action-status auto-blocked">🔒 자동차단</span>' 
                                        : log.sscl_action_taken === 'blocked' 
                                            ? '<span class="action-status blocked">⚠️ 차단됨</span>'
                                            : log.sscl_action_taken === 'detected'
                                                ? '<span class="action-status detected">🔍 탐지</span>'
                                                : '<span class="action-status pending">⏳ 탐지</span>'}
                                </div>
                            </td>
                            <td>
                                <div class="text-center">
                                    ${log.sscl_auto_blocked != '1' 
                                        ? `<button class="btn btn-sm btn-danger" onclick="blockSpamContentIP('${log.sscl_ip}', this)">IP 차단</button>`
                                        : '<span class="text-muted">차단됨</span>'}
                                </div>
                            </td>
                        </tr>
                    `;}).join('')}
                </tbody>
            </table>
            ${result.total_pages > 1 ? `
                <div class="pagination">
                    ${page > 1 ? `<button class="btn btn-secondary btn-sm" onclick="loadSpamContentLogsWithCurrentFilter(${page - 1})">이전</button>` : ''}
                    <span class="page-info">페이지 ${page} / ${result.total_pages}</span>
                    ${page < result.total_pages ? `<button class="btn btn-secondary btn-sm" onclick="loadSpamContentLogsWithCurrentFilter(${page + 1})">다음</button>` : ''}
                </div>
            ` : ''}
        `;
    } else {
        console.log('Empty state triggered');
        console.log('result.success:', result.success);
        console.log('result.data exists:', !!result.data);
        console.log('result.data length:', result.data ? result.data.length : 'N/A');
        console.log('Full result object:', JSON.stringify(result, null, 2));
        
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <p>스팸 콘텐츠 탐지 로그가 없습니다</p>
                <small>스팸 키워드로 탐지된 콘텐츠가 발견되지 않았습니다.</small>
                <small style="margin-top: 10px; color: #666;">
                    Debug: success=${result.success}, data=${result.data ? 'exists' : 'null'}, 
                    length=${result.data ? result.data.length : 'N/A'}
                </small>
            </div>
        `;
    }
};

// 스팸 키워드 목록 로드
const loadSpamKeywords = async (page = 1) => {
    const result = await apiCall('get_spam_keywords', { page: page, limit: 100 });
    const container = document.getElementById('keywords-table');

    if (result.success && result.data.length > 0) {
        // 카테고리별로 그룹화
        const groupedKeywords = {};
        const availableCategories = new Set();
        
        result.data.forEach(keyword => {
            if (!groupedKeywords[keyword.ssk_category]) {
                groupedKeywords[keyword.ssk_category] = [];
            }
            groupedKeywords[keyword.ssk_category].push(keyword);
            availableCategories.add(keyword.ssk_category);
        });

        // 카테고리 선택 드롭다운 업데이트
        updateCategoryDropdown(availableCategories);

        let html = `
            <div class="keywords-table-header">
                <div class="header-item category-col">항목</div>
                <div class="header-item keyword-col">키워드</div>
                <div class="header-item score-col">위험도</div>
                <div class="header-item actions-col">작업</div>
            </div>
        `;
        
        // 카테고리별로 출력
        Object.keys(groupedKeywords).sort().forEach(category => {
            // 해당 카테고리의 키워드들
            groupedKeywords[category].sort((a, b) => b.ssk_score - a.ssk_score).forEach(keyword => {
                html += `
                    <div class="keyword-item">
                        <span class="keyword-category">${keyword.ssk_category}</span>
                        <span class="keyword-text">${keyword.ssk_keyword}</span>
                        <span class="keyword-score score-${keyword.ssk_score}">${keyword.ssk_score}점</span>
                        <div class="keyword-actions">
                            <button class="btn-keyword-edit" onclick="editKeywordScore('${keyword.ssk_keyword}', ${keyword.ssk_score})">수정</button>
                            <button class="btn-keyword-delete" onclick="deleteKeyword('${keyword.ssk_keyword}')">삭제</button>
                        </div>
                    </div>
                `;
            });
        });

        container.innerHTML = html;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <p>등록된 스팸 키워드가 없습니다</p>
                <small>새 키워드를 추가하여 스팸 탐지를 시작하세요.</small>
            </div>
        `;
    }
};

// 스팸 키워드 추가
const addSpamKeyword = async () => {
    const category = document.getElementById('keyword-category').value;
    const keyword = document.getElementById('keyword-text').value.trim();
    const score = document.getElementById('keyword-score').value;

    if (!keyword) {
        showToast('키워드를 입력하세요.', 'error');
        return;
    }

    const result = await apiCall('add_spam_keyword', {
        category: category,
        keyword: keyword,
        score: score
    });

    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        document.getElementById('keyword-text').value = '';
        loadSpamKeywords();
    }
};

// 키워드 점수 수정
const editKeywordScore = async (keyword, currentScore) => {
    const newScore = prompt(`키워드 "${keyword}"의 새로운 위험도 점수를 입력하세요 (1-5):`, currentScore);
    
    if (newScore === null) return;
    
    const score = parseInt(newScore);
    if (isNaN(score) || score < 1 || score > 5) {
        showToast('1-5 사이의 숫자를 입력하세요.', 'error');
        return;
    }

    const result = await apiCall('update_keyword_score', {
        keyword: keyword,
        score: score
    });

    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadSpamKeywords();
    }
};

// 키워드 삭제
const deleteKeyword = async (keyword) => {
    if (!confirm(`키워드 "${keyword}"를 삭제하시겠습니까?`)) return;

    const result = await apiCall('delete_spam_keyword', { keyword: keyword });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadSpamKeywords();
    }
};

// 스팸 콘텐츠 IP 차단
const blockSpamContentIP = async (ip, button) => {
    if (!confirm(`IP ${ip}를 차단하시겠습니까?`)) return;

    button.disabled = true;
    button.textContent = '처리 중...';

    const result = await apiCall('block_spam_content_ip', { ip: ip });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadSpamContentLogs();
    } else {
        button.disabled = false;
        button.textContent = 'IP 차단';
    }
};

// 스팸 콘텐츠 로그 삭제
const deleteSpamContentLog = async (logId, button) => {
    if (!confirm('이 스팸 탐지 로그를 삭제하시겠습니까?')) return;

    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '⏳';

    const result = await apiCall('delete_spam_content_log', { log_id: logId });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        const row = button.closest('tr');
        row.style.opacity = '0';
        setTimeout(() => {
            row.remove();
        }, 300);
    } else {
        button.disabled = false;
        button.innerHTML = originalText;
    }
};

// 모든 스팸 콘텐츠 로그 삭제
const deleteAllSpamContentLogs = async () => {
    if (!confirm('⚠️ 모든 스팸 콘텐츠 탐지 로그를 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.')) return;
    
    if (!confirm('정말로 모든 스팸 탐지 로그를 삭제하시겠습니까?')) return;

    const result = await apiCall('delete_all_spam_content_logs');
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadSpamContentLogs();
    }
};

// 스팸 키워드 설정 초기화
const resetSpamKeywords = async () => {
    if (!confirm('⚠️ 스팸 키워드를 기본 시스템 설정으로 초기화하시겠습니까?\n\n현재 등록된 모든 키워드가 삭제되고 기본 키워드로 대체됩니다.')) return;
    
    if (!confirm('정말로 초기화하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) return;

    const result = await apiCall('reset_spam_keywords');
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadSpamKeywords();
    }
};

// 현재 필터 설정으로 페이지네이션
const loadSpamContentLogsWithCurrentFilter = (page) => {
    const currentFilters = getCurrentSpamLogFilters();
    loadSpamContentLogs(page, currentFilters);
};

// 현재 필터 설정 가져오기
const getCurrentSpamLogFilters = () => {
    const filters = {};
    
    const actionFilter = document.getElementById('spam-log-filter-action');
    const scoreFilter = document.getElementById('spam-log-filter-score');
    const daysFilter = document.getElementById('spam-log-filter-days');
    
    if (actionFilter && actionFilter.value) filters.action = actionFilter.value;
    if (scoreFilter && scoreFilter.value) filters.score = scoreFilter.value;
    if (daysFilter && daysFilter.value) filters.days = daysFilter.value;
    
    return filters;
};

// 스팸 로그 필터 적용
const applySpamLogFilter = () => {
    const filters = getCurrentSpamLogFilters();
    loadSpamContentLogs(1, filters);
};

// 스팸 로그 필터 초기화
const resetSpamLogFilter = () => {
    const actionFilter = document.getElementById('spam-log-filter-action');
    const scoreFilter = document.getElementById('spam-log-filter-score');
    const daysFilter = document.getElementById('spam-log-filter-days');
    
    if (actionFilter) actionFilter.value = '';
    if (scoreFilter) scoreFilter.value = '';
    if (daysFilter) daysFilter.value = '';
    
    loadSpamContentLogs(1);
};

// 카테고리 드롭다운 업데이트
const updateCategoryDropdown = (availableCategories) => {
    const categorySelect = document.getElementById('keyword-category');
    if (!categorySelect) return;
    
    // 현재 선택된 값 보존
    const currentValue = categorySelect.value;
    
    // 기존 옵션들 저장
    const existingOptions = Array.from(categorySelect.options).map(option => option.value);
    
    // 새로운 카테고리가 있으면 추가
    availableCategories.forEach(category => {
        if (!existingOptions.includes(category)) {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categorySelect.appendChild(option);
        }
    });
    
    // 이전 선택값 복원
    if (currentValue && existingOptions.includes(currentValue)) {
        categorySelect.value = currentValue;
    }
};

// 새 카테고리 추가
const addNewCategory = async () => {
    const categoryName = document.getElementById('new-category-name').value.trim();
    
    if (!categoryName) {
        showToast('카테고리명을 입력해주세요.', 'error');
        return;
    }
    
    if (categoryName.length > 50) {
        showToast('카테고리명은 50자 이하로 입력해주세요.', 'error');
        return;
    }
    
    // 카테고리 선택 드롭다운에 추가
    const categorySelect = document.getElementById('keyword-category');
    const existingOptions = Array.from(categorySelect.options).map(option => option.value);
    
    if (existingOptions.includes(categoryName)) {
        showToast('이미 존재하는 카테고리입니다.', 'error');
        return;
    }
    
    // 새 옵션 추가
    const option = document.createElement('option');
    option.value = categoryName;
    option.textContent = categoryName;
    categorySelect.appendChild(option);
    
    // 새로 추가된 카테고리를 선택
    categorySelect.value = categoryName;
    
    // 입력 필드 초기화
    document.getElementById('new-category-name').value = '';
    
    showToast(`"${categoryName}" 카테고리가 추가되었습니다. 이제 키워드를 추가할 수 있습니다.`, 'success');
};

// 카테고리 삭제
const deleteCategory = async (category) => {
    if (!confirm(`"${category}" 카테고리와 해당 카테고리의 모든 키워드를 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`)) return;
    
    const result = await apiCall('delete_category', { category: category });
    showToast(result.message, result.success ? 'success' : 'error');
    
    if (result.success) {
        // 카테고리 드롭다운에서 제거
        const categorySelect = document.getElementById('keyword-category');
        const optionToRemove = Array.from(categorySelect.options).find(option => option.value === category);
        if (optionToRemove) {
            optionToRemove.remove();
        }
        
        loadSpamKeywords();
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