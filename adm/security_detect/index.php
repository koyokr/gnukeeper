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
                    <h3 class="toggle-title">로그인 차단 활성화</h3>
                    <p class="toggle-desc">연속 로그인 실패 시 자동 IP 차단 (기본: 5회 실패 시 ㅇ차단)</p>
                </div>
                <input type="checkbox"
                       id="login-block-toggle"
                       class="toggle-input"
                       <?php echo $stats['login_block_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'login_block')">
                <label for="login-block-toggle" class="toggle-switch"></label>
            </div>

            <div class="expandable-section">
                <button type="button" class="expand-btn" onclick="toggleLoginLogs(this)">
                    <span>최근 로그인 실패 로그</span>
                    <span class="expand-icon">▶</span>
                </button>

                <div class="expand-content" id="login-logs-details" style="display: none;">
                    <!-- 동적으로 로드됨 -->
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
                    <p class="toggle-desc">의심스러운 User-Agent 패턴을 차단합니다</p>
                </div>
                <input type="checkbox"
                       id="useragent-block-toggle"
                       class="toggle-input"
                       <?php echo $stats['useragent_block_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'useragent_block')">
                <label for="useragent-block-toggle" class="toggle-switch"></label>
            </div>
        </div>
    </div>

    <!-- 행동 분석 차단 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('behavior-analysis-card')">
            📊 행동 패턴 분석 <span id="behavior-analysis-toggle">▶</span>
        </div>
        <div class="card-content" id="behavior-analysis-card">
            <div class="info-highlight">
                의심스러운 행동 패턴을 탐지하여 자동으로 차단합니다.
            </div>
            <div class="toggle-section">
                <div class="toggle-info">
                    <h3 class="toggle-title">행동 패턴 차단</h3>
                    <p class="toggle-desc">404 스캔, 비정상 Referer 등 악성 행동 패턴을 탐지하여 차단</p>
                </div>
                <input type="checkbox"
                       id="behavior-pattern-toggle"
                       class="toggle-input"
                       <?php echo ($stats['behavior_404_enabled'] == '1' || $stats['behavior_referer_enabled'] == '1') ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'behavior_pattern')">
                <label for="behavior-pattern-toggle" class="toggle-switch"></label>
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
                    <p class="toggle-desc">동일 IP에서 다중 계정 생성 및 동시 로그인을 차단</p>
                </div>
                <input type="checkbox"
                       id="multiuser-protection-toggle"
                       class="toggle-input"
                       <?php echo ($stats['multiuser_register_enabled'] == '1' || $stats['multiuser_login_enabled'] == '1') ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'multiuser_protection')">
                <label for="multiuser-protection-toggle" class="toggle-switch"></label>
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
                    <p class="toggle-desc">사용자 정의 정규식 패턴으로 스팸 콘텐츠 차단</p>
                </div>
                <input type="checkbox"
                       id="regex-spam-toggle"
                       class="toggle-input"
                       <?php echo $stats['regex_spam_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleFeature(this, 'regex_spam')">
                <label for="regex-spam-toggle" class="toggle-switch"></label>
            </div>

            <div class="expandable-section">
                <button type="button" class="expand-btn" onclick="toggleDetectLogs(this)">
                    <span>최근 위협 탐지 로그</span>
                    <span class="expand-icon">▶</span>
                </button>

                <div class="expand-content" id="detect-logs-details" style="display: none;">
                    <!-- 동적으로 로드됨 -->
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

        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: '네트워크 오류가 발생했습니다.' };
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
    const container = document.getElementById('login-logs-details');

    if (result.success && result.data.length > 0) {
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>IP 주소</th>
                        <th>사용자 ID</th>
                        <th>시도 시간</th>
                        <th>User-Agent</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(log => `
                        <tr>
                            <td><span class="ip-address">${log.slf_ip}</span></td>
                            <td><span class="log-user">${log.slf_mb_id || '-'}</span></td>
                            <td><span class="log-time">${log.slf_datetime}</span></td>
                            <td><span class="log-agent" title="${log.slf_user_agent || 'User-Agent 없음'}">${log.slf_user_agent || 'User-Agent 없음'}</span></td>
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
                <p>로그인 실패 기록이 없습니다</p>
            </div>
        `;
    }
};

// 위협 탐지 로그 로드
const loadDetectLogs = async (page = 1) => {
    const result = await apiCall('get_detect_logs', { page: page, limit: 10 });
    const container = document.getElementById('detect-logs-details');

    if (result.success && result.data.length > 0) {
        container.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>IP 주소</th>
                        <th>탐지 사유</th>
                        <th>탐지 시간</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(log => `
                        <tr>
                            <td><span class="ip-address">${log.sl_ip}</span></td>
                            <td><span class="detect-reason">${log.sl_reason}</span></td>
                            <td><span class="log-time">${log.sl_datetime}</span></td>
                            <td><span class="detect-url" title="${log.sl_url || '-'}">${log.sl_url || '-'}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            ${result.total_pages > 1 ? `
                <div class="pagination">
                    ${page > 1 ? `<button class="btn btn-secondary btn-sm" onclick="loadDetectLogs(${page - 1})">이전</button>` : ''}
                    <span class="page-info">페이지 ${page} / ${result.total_pages}</span>
                    ${page < result.total_pages ? `<button class="btn btn-secondary btn-sm" onclick="loadDetectLogs(${page + 1})">다음</button>` : ''}
                </div>
            ` : ''}
        `;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <p>위협 탐지 기록이 없습니다</p>
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

// clearDetectLogs 함수 제거됨

// 로그인 실패 로그 토글
const toggleLoginLogs = (button) => {
    const details = document.getElementById('login-logs-details');
    const isExpanded = details.style.display !== 'none';

    if (isExpanded) {
        details.style.display = 'none';
        button.classList.remove('expanded');
    } else {
        details.style.display = 'block';
        button.classList.add('expanded');
        // 처음 열 때만 로그를 로드
        if (!button.dataset.loaded) {
            loadLoginFailLogs();
            button.dataset.loaded = 'true';
        }
    }
};

// 위협 탐지 로그 토글
const toggleDetectLogs = (button) => {
    const details = document.getElementById('detect-logs-details');
    const isExpanded = details.style.display !== 'none';

    if (isExpanded) {
        details.style.display = 'none';
        button.classList.remove('expanded');
    } else {
        details.style.display = 'block';
        button.classList.add('expanded');
        // 처음 열 때만 로그를 로드
        if (!button.dataset.loaded) {
            loadDetectLogs();
            button.dataset.loaded = 'true';
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

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', () => {
    // 로그는 토글할 때 로드되므로 여기서는 로드하지 않음

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