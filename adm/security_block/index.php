<?php
$sub_menu = '950300';
require_once './_common.php';

// 관리자 권한 체크
auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '차단 관리';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 공통 보안 CSS 포함
echo '<link rel="stylesheet" href="'.G5_ADMIN_URL.'/css/security_common.css?ver='.G5_CSS_VER.'">';

// Plugin Admin 클래스 사용
$blockAdmin = GK_BlockAdmin::getInstance();
$stats = $blockAdmin->getBlockStats();
?>


<div class="security-dashboard">
    <h1 class="dashboard-title">
        🛡️ 차단 관리
    </h1>
    <p class="dashboard-subtitle">
        IP 차단, 해외 접속 제한, 예외 설정을 통합 관리합니다
    </p>

    <!-- 통계 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['active_blocks_count']); ?></div>
            <div class="stat-label">고급 차단 IP</div>
            <div class="stat-trend trend-up">활성 차단 규칙</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['whitelist_count']); ?></div>
            <div class="stat-label">예외 IP</div>
            <div class="stat-trend">허용된 주소</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['foreign_block_enabled'] == '1' ? 'ON' : 'OFF'; ?></div>
            <div class="stat-label">해외 IP 차단</div>
            <div class="stat-trend <?php echo $stats['foreign_block_enabled'] == '1' ? 'trend-up' : 'trend-down'; ?>">
                <?php echo $stats['foreign_block_enabled'] == '1' ? '활성화됨' : '비활성화됨'; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['today_blocks_count']); ?></div>
            <div class="stat-label">오늘 차단</div>
            <div class="stat-trend">24시간 기준</div>
        </div>
    </div>

    <!-- 고급 IP 차단 관리 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('advanced-ip-card')">
            🚫 고급 IP 차단 관리 <span id="advanced-ip-toggle">▶</span>
        </div>
        <div class="card-content" id="advanced-ip-card">
            <div class="info-highlight">
                수동으로 특정 IP나 IP 대역을 차단하여 사이트 접근을 제한할 수 있습니다.
            </div>

            <!-- 그누보드 설정 동기화 -->
            <div class="sync-section" style="margin: 16px 0; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 600; color: #374151;">
                            🔄 그누보드 기본 IP 설정 동기화
                        </h4>
                        <p style="margin: 0; font-size: 13px; color: #6b7280;">
                            GnuKeeper와 그누보드 기본 IP 설정을 양방향으로 동기화합니다
                        </p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="syncWithGnuboard()">
                        동기화
                    </button>
                </div>
            </div>

            <!-- IP 추가 폼 -->
            <div class="form-section">
                <form id="addBlockForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">IP 주소</label>
                            <input type="text" name="block_ip" class="form-input"
                                   placeholder="192.168.1.100 또는 192.168.1.0/24" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">차단 사유 (선택)</label>
                            <input type="text" name="block_reason" class="form-input"
                                   placeholder="스팸, 악성 행위 등">
                        </div>
                        <button type="submit" class="btn btn-primary">차단 추가</button>
                    </div>
                </form>
            </div>

            <!-- 차단된 IP 목록 -->
            <div id="blockedIPList" class="ip-list">
                <!-- 동적으로 로드됨 -->
            </div>
        </div>
    </div>

    <!-- 해외 IP 차단 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('foreign-ip-card')">
            🌍 해외 IP 차단 <span id="foreign-ip-toggle">▶</span>
        </div>
        <div class="card-content" id="foreign-ip-card">
            <div class="info-highlight">
                한국이 아닌 해외 IP의 접속을 자동으로 차단합니다. 검색엔진 봇과 알려진 서비스는 자동으로 허용됩니다.
            </div>
            <div class="toggle-section">
                <div class="toggle-info">
                    <h3 class="toggle-title">해외 IP 접속 차단</h3>
                    <p class="toggle-desc">한국 외 IP의 사이트 접속을 차단합니다.</p>
                </div>
                <input type="checkbox"
                       id="foreign-block-toggle"
                       class="toggle-input"
                       <?php echo $stats['foreign_block_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleForeignBlock(this)">
                <label for="foreign-block-toggle" class="toggle-switch"></label>
            </div>

            <!-- 해외 IP 차단 상태 메시지 -->
            <div id="foreign-block-status" class="status-message" style="display: none;">
                <!-- 상태 메시지가 여기에 표시됩니다 -->
            </div>

            <!-- 국가별 차단 설정과 허용되는 해외 IP -->
            <div class="sub-card-group">
                <!-- 국가별 차단 설정 -->
                <div class="sub-card">
                    <div class="sub-card-header" onclick="toggleSubCard('country-block-details', this)">
                        국가별 차단 설정 <span class="sub-card-toggle">▶</span>
                    </div>
                    <div class="sub-card-content" id="country-block-details">
                        <div class="info-highlight">
                            특정 국가의 IP를 개별적으로 차단할 수 있습니다.
                        </div>

                        <!-- 국가 선택 -->
                        <div class="country-selection">
                            <h4>🌍 차단할 국가 선택</h4>
                            <div class="country-grid">
                                <button class="country-btn" onclick="addCountryBlock('CN', '🇨🇳', '중국')">🇨🇳 중국</button>
                                <button class="country-btn" onclick="addCountryBlock('RU', '🇷🇺', '러시아')">🇷🇺 러시아</button>
                                <button class="country-btn" onclick="addCountryBlock('US', '🇺🇸', '미국')">🇺🇸 미국</button>
                                <button class="country-btn" onclick="addCountryBlock('JP', '🇯🇵', '일본')">🇯🇵 일본</button>
                                <button class="country-btn" onclick="addCountryBlock('IN', '🇮🇳', '인도')">🇮🇳 인도</button>
                                <button class="country-btn" onclick="addCountryBlock('VN', '🇻🇳', '베트남')">🇻🇳 베트남</button>
                                <button class="country-btn" onclick="addCountryBlock('TH', '🇹🇭', '태국')">🇹🇭 태국</button>
                                <button class="country-btn" onclick="addCountryBlock('PH', '🇵🇭', '필리핀')">🇵🇭 필리핀</button>
                                <button class="country-btn" onclick="addCountryBlock('ID', '🇮🇩', '인도네시아')">🇮🇩 인도네시아</button>
                                <button class="country-btn" onclick="addCountryBlock('MY', '🇲🇾', '말레이시아')">🇲🇾 말레이시아</button>
                                <button class="country-btn" onclick="addCountryBlock('SG', '🇸🇬', '싱가포르')">🇸🇬 싱가포르</button>
                                <button class="country-btn" onclick="addCountryBlock('TR', '🇹🇷', '터키')">🇹🇷 터키</button>
                            </div>
                        </div>

                        <!-- 차단된 국가 목록 -->
                        <div class="blocked-countries-section">
                            <h4>🚫 차단된 국가 목록</h4>
                            <div id="blockedCountriesList" class="ip-list">
                                <!-- 동적으로 로드됨 -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 허용되는 해외 IP -->
                <div class="sub-card">
                    <div class="sub-card-header" onclick="toggleSubCard('foreign-services-details', this)">
                        허용되는 해외 IP <span class="sub-card-toggle">▶</span>
                    </div>
                    <div class="sub-card-content" id="foreign-services-details">
                        <div class="info-highlight">
                            해외 IP 차단이 활성화되어도 다음 서비스들은 자동으로 허용됩니다.
                        </div>

                        <table class="ip-table">
                            <thead>
                                <tr>
                                    <th>서비스 유형</th>
                                    <th>허용되는 서비스</th>
                                    <th>상태</th>
                                    <th>설명</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="ip-address">🔍 검색엔진</div>
                                        <div class="ip-reason">SEO 및 색인화</div>
                                    </td>
                                    <td>
                                        <div class="country-ip-info">
                                            <div class="ip-sample">
                                                <span class="ip-range-tag">Google</span>
                                                <span class="ip-range-tag">Bing</span>
                                                <span class="ip-range-tag">DuckDuckGo</span>
                                                <span class="ip-range-tag">Baidu</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-badge-exception">차단 예외됨</span>
                                    </td>
                                    <td>
                                        <div class="ip-date">사이트 검색 최적화</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="ip-address">☁️ CDN/클라우드</div>
                                        <div class="ip-reason">콘텐츠 전송 네트워크</div>
                                    </td>
                                    <td>
                                        <div class="country-ip-info">
                                            <div class="ip-sample">
                                                <span class="ip-range-tag">Cloudflare</span>
                                                <span class="ip-range-tag">AWS</span>
                                                <span class="ip-range-tag">Azure</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-badge-exception">차단 예외됨</span>
                                    </td>
                                    <td>
                                        <div class="ip-date">성능 및 보안 향상</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="ip-address">📱 소셜미디어</div>
                                        <div class="ip-reason">링크 미리보기</div>
                                    </td>
                                    <td>
                                        <div class="country-ip-info">
                                            <div class="ip-sample">
                                                <span class="ip-range-tag">Facebook</span>
                                                <span class="ip-range-tag">Twitter</span>
                                                <span class="ip-range-tag">LinkedIn</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-badge-exception">차단 예외됨</span>
                                    </td>
                                    <td>
                                        <div class="ip-date">소셜 공유 지원</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="ip-address">📊 분석도구</div>
                                        <div class="ip-reason">사이트 모니터링</div>
                                    </td>
                                    <td>
                                        <div class="country-ip-info">
                                            <div class="ip-sample">
                                                <span class="ip-range-tag">SEO 크롤러</span>
                                                <span class="ip-range-tag">모니터링</span>
                                                <span class="ip-range-tag">Analytics</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-badge-exception">차단 예외됨</span>
                                    </td>
                                    <td>
                                        <div class="ip-date">사이트 분석 및 추적</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 예외 IP 설정 -->
    <div class="card">
        <div class="card-header" onclick="toggleCard('whitelist-ip-card')">
            ✅ 예외 IP 설정 <span id="whitelist-ip-toggle">▶</span>
        </div>
        <div class="card-content" id="whitelist-ip-card">
            <div class="info-highlight">
                예외 IP는 모든 자동 차단 및 수동 차단 기능에서 예외입니다.
            </div>
            <!-- IP 추가 폼 -->
            <div class="form-section">
                <form id="addWhitelistForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">IP 주소</label>
                            <input type="text" name="whitelist_ip" class="form-input"
                                   placeholder="192.168.1.100" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">메모 (선택)</label>
                            <input type="text" name="whitelist_memo" class="form-input"
                                   placeholder="관리자 접속용 등">
                        </div>
                        <button type="submit" class="btn btn-primary">예외 추가</button>
                    </div>
                </form>
            </div>

            <!-- 예외 IP 목록 -->
            <div id="whitelistIPList" class="ip-list">
                <!-- 동적으로 로드됨 -->
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

// IP 차단 관리 함수들
// localhost IP 확인 함수 (JavaScript 버전)
const isLocalhostIP = (ip) => {
    const localhostPatterns = ['127.', '::1', '0.0.0.0', 'localhost'];
    return localhostPatterns.some(pattern => ip.toLowerCase().startsWith(pattern.toLowerCase()));
};

const addIPBlock = async (event) => {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const ip = formData.get('block_ip').trim();
    
    // localhost IP 경고
    if (isLocalhostIP(ip)) {
        if (!confirm('⚠️ 경고: localhost 관련 IP를 차단하려고 합니다.\n\n이 IP는 목록에는 추가되지만 실제로는 차단되지 않습니다.\n(사이트 접근 불가 방지를 위한 안전 장치)\n\n계속하시겠습니까?')) {
            return;
        }
    }

    const result = await apiCall('add_block', {
        block_ip: ip,
        block_reason: formData.get('block_reason') || ''
    });

    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        form.reset();
        loadBlockedIPs();
        updateStats();
    }
};

const removeIPBlock = async (ip) => {
    if (!confirm(`IP ${ip}의 차단을 해제하시겠습니까?`)) return;

    const result = await apiCall('remove_block', { block_ip: ip });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadBlockedIPs();
        updateStats();
    }
};

const loadBlockedIPs = async () => {
    const result = await apiCall('get_blocked_ips');
    const container = document.getElementById('blockedIPList');

    if (result.success && result.data.length > 0) {
        container.innerHTML = `
            <table class="ip-table">
                <thead>
                    <tr>
                        <th>IP 주소</th>
                        <th>차단 사유</th>
                        <th>등록 방식</th>
                        <th>등록일</th>
                        <th>처리 기능</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(ip => `
                        <tr${ip.is_localhost ? ' style="background-color: #fef3c7;"' : ''}>
                            <td>
                                <div class="ip-address">${ip.ip}${ip.is_localhost ? ' <span style="color: #d97706; font-size: 11px; font-weight: 600;">⚠️ LOCALHOST</span>' : ''}</div>
                                ${ip.hit_count > 0 ? `<div class="ip-hits">적중 횟수: ${ip.hit_count}</div>` : ''}
                            </td>
                            <td>
                                <div class="ip-reason">${ip.reason || '사유 없음'}</div>
                            </td>
                            <td>
                                <span class="status-badge status-badge-${ip.is_localhost ? 'exception' : ip.block_type.replace('auto_', '').replace('manual', 'manual')}">
                                    ${ip.is_localhost ? '차단 예외됨' : (ip.block_type_display || '차단됨')}
                                </span>
                            </td>
                            <td>
                                <div class="ip-date">${ip.created_at || '-'}</div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-sm btn-danger" onclick="removeIPBlock('${ip.ip}')">차단 해제</button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🚫</div>
                <p>차단된 IP가 없습니다</p>
            </div>
        `;
    }
};

// 예외 IP 관리 함수들
const addWhitelistIP = async (event) => {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    const result = await apiCall('add_whitelist', {
        whitelist_ip: formData.get('whitelist_ip'),
        whitelist_memo: formData.get('whitelist_memo') || ''
    });

    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        form.reset();
        loadWhitelistIPs();
        updateStats();
    }
};

const removeWhitelistIP = async (id) => {
    if (!confirm('이 예외 IP를 삭제하시겠습니까?')) return;

    const result = await apiCall('remove_whitelist', { whitelist_id: id });
    showToast(result.message, result.success ? 'success' : 'error');

    if (result.success) {
        loadWhitelistIPs();
        updateStats();
    }
};

const loadWhitelistIPs = async () => {
    const result = await apiCall('get_whitelist_ips');
    const container = document.getElementById('whitelistIPList');

    if (result.success && result.data.length > 0) {
        container.innerHTML = `
            <table class="ip-table">
                <thead>
                    <tr>
                        <th>IP 주소</th>
                        <th>메모</th>
                        <th>상태</th>
                        <th>등록일</th>
                        <th>처리 기능</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(ip => `
                        <tr>
                            <td>
                                <div class="ip-address">${ip.sw_ip}</div>
                            </td>
                            <td>
                                <div class="ip-reason">${ip.sw_memo || '메모 없음'}</div>
                            </td>
                            <td>
                                <span class="status-badge allowed">허용됨</span>
                            </td>
                            <td>
                                <div class="ip-date">${ip.created_at || '-'}</div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-sm btn-danger" onclick="removeWhitelistIP('${ip.sw_id}')">삭제</button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">✅</div>
                <p>등록된 예외 IP가 없습니다</p>
            </div>
        `;
    }
};


// 해외 IP 차단 토글 (Cloudflare 스타일)
const toggleForeignBlock = async (checkbox) => {
    const enabled = checkbox.checked;

    const result = await apiCall('toggle_foreign_block', {
        enabled: enabled ? '1' : '0'
    });

    showToast(result.message, result.success ? 'success' : 'error');

    if (!result.success) {
        // 실패 시 체크박스 상태 되돌리기
        checkbox.checked = !enabled;
    } else {
        updateStats();
        updateForeignBlockStatus(enabled);
        // 국가별 차단 목록 상태도 업데이트
        loadBlockedCountries();
    }
};

// 해외 IP 차단 상태 메시지 업데이트
const updateForeignBlockStatus = (enabled) => {
    const statusDiv = document.getElementById('foreign-block-status');
    
    if (enabled) {
        statusDiv.innerHTML = `
            <div class="alert-success-sm">
                해외 IP 차단이 활성화되어 한국 외 IP의 접속이 안전하게 차단되고 있습니다.
            </div>
        `;
        statusDiv.style.display = 'block';
    } else {
        statusDiv.innerHTML = `
            <div class="alert-warning-sm">
                해외 IP 차단이 비활성화되어 모든 해외 IP의 접속이 허용됩니다.
            </div>
        `;
        statusDiv.style.display = 'block';
    }
};

// Sub Card 토글 함수
const toggleSubCard = (contentId, headerElement) => {
    const content = document.getElementById(contentId);
    const toggle = headerElement.querySelector('.sub-card-toggle');
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        content.style.display = 'none';
        content.style.visibility = 'hidden';
        content.style.opacity = '0';
        toggle.textContent = '▶';
        toggle.style.transform = 'rotate(0deg)';
    } else {
        content.classList.add('show');
        content.style.display = 'block';
        content.style.visibility = 'visible';
        content.style.opacity = '1';
        toggle.textContent = '▼';
        toggle.style.transform = 'rotate(90deg)';
        
        // 국가별 차단 설정이 열릴 때 데이터 로드
        if (contentId === 'country-block-details') {
            // DOM이 완전히 준비될 때까지 잠시 대기
            setTimeout(() => loadBlockedCountries(), 50);
        }
    }
};

// 국가 차단 추가
const addCountryBlock = async (countryCode, flag, countryName) => {
    if (!confirm(`${flag} ${countryName}을(를) 차단 목록에 추가하시겠습니까?`)) return;
    
    const result = await apiCall('add_country_block', {
        country_code: countryCode,
        country_name: countryName,
        country_flag: flag
    });
    
    showToast(result.message, result.success ? 'success' : 'error');
    
    if (result.success) {
        loadBlockedCountries();
        updateCountryButtonStates();
        updateStats();
    }
};

// 국가 차단 제거
const removeCountryBlock = async (countryCode, countryName) => {
    if (!confirm(`${countryName} 차단을 해제하시겠습니까?`)) return;
    
    const result = await apiCall('remove_country_block', {
        country_code: countryCode
    });
    
    showToast(result.message, result.success ? 'success' : 'error');
    
    if (result.success) {
        loadBlockedCountries();
        updateCountryButtonStates();
        updateStats();
    }
};

// 차단된 국가 목록 로드
const loadBlockedCountries = async () => {
    const result = await apiCall('get_blocked_countries');
    const container = document.getElementById('blockedCountriesList');
    
    // 컨테이너가 없으면 조용히 종료 (서브카드가 닫힌 상태)
    if (!container) {
        return;
    }
    
    // 해외 IP 차단 토글 상태 확인
    const foreignToggle = document.getElementById('foreign-block-toggle');
    const isForeignBlockEnabled = foreignToggle ? foreignToggle.checked : false;
    
    if (result.success && result.data.length > 0) {
        container.innerHTML = `
            <table class="ip-table">
                <thead>
                    <tr>
                        <th>국가</th>
                        <th>IP 대역 정보</th>
                        <th>차단 상태</th>
                        <th>등록일</th>
                        <th>처리 기능</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(country => `
                        <tr>
                            <td>
                                <div class="ip-address">${country.flag} ${country.name}</div>
                                <div class="ip-reason">국가 코드: ${country.code}</div>
                            </td>
                            <td>
                                <div class="country-ip-info">
                                    ${country.sample_ranges && country.sample_ranges.length > 0 ? 
                                        `<div class="ip-sample">
                                            ${country.sample_ranges.map(range => 
                                                `<span class="ip-range-tag">${range}</span>`
                                            ).join('')}
                                        </div>`
                                        : '<div class="ip-sample-empty">IP 정보 로딩중...</div>'
                                    }
                                </div>
                            </td>
                            <td>
                                <span class="country-status-badge ${isForeignBlockEnabled ? 'active' : 'standby'}">
                                    ${isForeignBlockEnabled ? '차단됨' : '차단 준비중'}
                                </span>
                            </td>
                            <td>
                                <div class="ip-date">${country.created_at || '-'}</div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-sm btn-danger" onclick="removeCountryBlock('${country.code}', '${country.name}')">차단 해제</button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🌍</div>
                <p>차단된 국가가 없습니다</p>
            </div>
        `;
    }
};

// 국가 버튼 상태 업데이트
const updateCountryButtonStates = async () => {
    const result = await apiCall('get_blocked_countries');
    if (result.success) {
        const blockedCountries = result.data.map(country => country.code);
        
        document.querySelectorAll('.country-btn').forEach(btn => {
            const countryCode = btn.getAttribute('onclick').match(/'([^']+)'/)[1];
            if (blockedCountries.includes(countryCode)) {
                btn.classList.add('blocked');
                btn.disabled = true;
                btn.style.opacity = '0.6';
            } else {
                btn.classList.remove('blocked');
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        });
    }
};

// 서비스 정보 토글
const toggleServiceInfo = (button) => {
    const details = document.getElementById('foreign-services-details');
    const isExpanded = details.style.display !== 'none';

    if (isExpanded) {
        details.style.display = 'none';
        button.classList.remove('expanded');
    } else {
        details.style.display = 'block';
        button.classList.add('expanded');
    }
};

// 그누보드 설정 동기화
const syncWithGnuboard = async () => {
    if (!confirm('GnuKeeper와 그누보드 기본 IP 설정을 동기화하시겠습니까?\n\n🔄 양방향 동기화를 수행합니다.\n- GnuKeeper → 그누보드 기본 설정 반영\n- 그누보드 → GnuKeeper 누락된 설정 추가')) return;
    
    const result = await apiCall('sync_with_gnuboard');
    showToast(result.message, result.success ? 'success' : 'error');
    
    if (result.success) {
        loadBlockedIPs();
        loadWhitelistIPs();
        updateStats();
    }
};


// 스크롤 위치 및 카드 상태 저장/복원
const savePageState = () => {
    // 스크롤 위치 저장
    sessionStorage.setItem('security_block_scroll', window.pageYOffset.toString());
    
    // 카드 펼침 상태 저장
    const cardStates = {};
    document.querySelectorAll('.card-content').forEach(content => {
        const cardId = content.id;
        cardStates[cardId] = content.classList.contains('show');
    });
    
    // 서브카드 펼침 상태 저장
    document.querySelectorAll('.sub-card-content').forEach(content => {
        const cardId = content.id;
        cardStates[cardId] = content.classList.contains('show');
    });
    
    sessionStorage.setItem('security_block_cards', JSON.stringify(cardStates));
};

const restorePageState = () => {
    // 스크롤 위치 복원 (지연 실행)
    const savedPosition = sessionStorage.getItem('security_block_scroll');
    if (savedPosition) {
        // DOM이 완전히 렌더링된 후 스크롤 위치 복원
        setTimeout(() => {
            window.scrollTo(0, parseInt(savedPosition));
            sessionStorage.removeItem('security_block_scroll');
        }, 200);
    }
    
    // 카드 상태 복원
    const savedCards = sessionStorage.getItem('security_block_cards');
    if (savedCards) {
        try {
            const cardStates = JSON.parse(savedCards);
            
            // 메인 카드와 서브카드를 분리해서 처리
            Object.entries(cardStates).forEach(([cardId, isOpen]) => {
                const content = document.getElementById(cardId);
                
                if (!content) return;
                
                // 메인 카드 처리
                if (cardId.endsWith('-card')) {
                    const toggleId = cardId.replace('-card', '-toggle');
                    const toggle = document.getElementById(toggleId);
                    
                    if (toggle) {
                        if (isOpen) {
                            content.classList.add('show');
                            toggle.textContent = '▼';
                            toggle.style.transform = 'rotate(90deg)';
                        } else {
                            content.classList.remove('show');
                            toggle.textContent = '▶';
                            toggle.style.transform = 'rotate(0deg)';
                        }
                    }
                }
                
                // 서브카드 처리
                else if (cardId.endsWith('-details')) {
                    const subCardHeader = document.querySelector(`[onclick*="${cardId}"]`);
                    
                    if (subCardHeader && isOpen) {
                        // toggleSubCard와 동일한 로직 사용
                        content.classList.add('show');
                        content.style.display = 'block';
                        content.style.visibility = 'visible';
                        content.style.opacity = '1';
                        
                        const subToggle = subCardHeader.querySelector('.sub-card-toggle');
                        if (subToggle) {
                            subToggle.textContent = '▼';
                            subToggle.style.transform = 'rotate(90deg)';
                        }
                        
                        // 서브카드별 데이터 로딩
                        if (cardId === 'country-block-details') {
                            setTimeout(() => loadBlockedCountries(), 50);
                        }
                    } else if (subCardHeader && !isOpen) {
                        content.classList.remove('show');
                        content.style.display = 'none';
                        content.style.visibility = 'hidden';
                        content.style.opacity = '0';
                        
                        const subToggle = subCardHeader.querySelector('.sub-card-toggle');
                        if (subToggle) {
                            subToggle.textContent = '▶';
                            subToggle.style.transform = 'rotate(0deg)';
                        }
                    }
                }
            });
            
            sessionStorage.removeItem('security_block_cards');
        } catch (e) {
            console.error('카드 상태 복원 중 오류:', e);
        }
    }
};

// 통계 업데이트
const updateStats = () => {
    // 통계는 페이지 새로고침 없이 업데이트하려면 별도 API가 필요
    // 현재는 간단히 페이지 새로고침
    savePageState();
    setTimeout(() => location.reload(), 1000);
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
    loadBlockedIPs();
    loadWhitelistIPs();
    
    // 해외 IP 차단 초기 상태 표시
    const foreignToggle = document.getElementById('foreign-block-toggle');
    if (foreignToggle) {
        updateForeignBlockStatus(foreignToggle.checked);
    }
    
    // 국가 버튼 상태 초기화
    updateCountryButtonStates();

    // 폼 이벤트 리스너 추가
    document.getElementById('addBlockForm').addEventListener('submit', addIPBlock);
    document.getElementById('addWhitelistForm').addEventListener('submit', addWhitelistIP);

    // 저장된 상태가 있는지 확인
    const savedCards = sessionStorage.getItem('security_block_cards');
    
    if (savedCards) {
        // 저장된 상태가 있으면 카드 복원 후 필요한 데이터 로드
        setTimeout(() => {
            restorePageState();
            
            // 데이터 로드는 서브카드 복원 로직에서 처리됨
            
            // 카드 애니메이션 완료 후 스크롤 위치 재확인
            setTimeout(() => {
                const savedPosition = sessionStorage.getItem('security_block_scroll');
                if (savedPosition) {
                    window.scrollTo(0, parseInt(savedPosition));
                    sessionStorage.removeItem('security_block_scroll');
                }
            }, 600);
        }, 100);
    } else {
        // 저장된 상태가 없으면 기본 자동 펼치기 실행
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
    }
});
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>