<?php
require_once './_common.php';

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

            <div class="toggle-section">
                <div class="toggle-info">
                    <h3 class="toggle-title">고급 IP 차단 기능</h3>
                    <p class="toggle-desc">수동 IP 차단 및 자동 차단 규칙을 활성화합니다.</p>
                </div>
                <input type="checkbox"
                       id="gk-block-toggle"
                       class="toggle-input"
                       <?php echo $stats['gk_block_enabled'] == '1' ? 'checked' : ''; ?>
                       onchange="toggleGKBlock(this)">
                <label for="gk-block-toggle" class="toggle-switch"></label>
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

            <div class="expandable-section">
                <button type="button" class="expand-btn" onclick="toggleServiceInfo(this)">
                    <span>허용되는 해외 IP</span>
                    <span class="expand-icon">▶</span>
                </button>

                <div class="expand-content" id="foreign-services-details" style="display: none;">
                    <div class="service-grid">
                        <div class="service-category">
                            <h4>🔍 검색엔진</h4>
                            <div class="service-tags">
                                <span>Google</span>
                                <span>Bing</span>
                                <span>DuckDuckGo</span>
                                <span>Baidu</span>
                            </div>
                        </div>
                        <div class="service-category">
                            <h4>☁️ CDN/클라우드</h4>
                            <div class="service-tags">
                                <span>Cloudflare</span>
                                <span>AWS</span>
                            </div>
                        </div>
                        <div class="service-category">
                            <h4>📱 소셜미디어</h4>
                            <div class="service-tags">
                                <span>Facebook</span>
                                <span>Twitter</span>
                                <span>LinkedIn</span>
                            </div>
                        </div>
                        <div class="service-category">
                            <h4>📊 분석도구</h4>
                            <div class="service-tags">
                                <span>SEO 크롤러</span>
                                <span>모니터링</span>
                            </div>
                        </div>
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
const addIPBlock = async (event) => {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    const result = await apiCall('add_block', {
        block_ip: formData.get('block_ip'),
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
                        <th>상태</th>
                        <th>등록일</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    ${result.data.map(ip => `
                        <tr>
                            <td>
                                <div class="ip-address">${ip.ip}</div>
                                ${ip.hit_count > 0 ? `<div class="ip-hits">적중 횟수: ${ip.hit_count}</div>` : ''}
                            </td>
                            <td>
                                <div class="ip-reason">${ip.reason || '사유 없음'}</div>
                            </td>
                            <td>
                                <span class="status-badge ${ip.is_auto ? 'auto-blocked' : 'manual-blocked'}">
                                    ${ip.block_type_display || '차단됨'}
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
                        <th></th>
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

// 고급 IP 차단 관리 토글
const toggleGKBlock = async (checkbox) => {
    const enabled = checkbox.checked;

    const result = await apiCall('toggle_gk_block', {
        enabled: enabled ? '1' : '0'
    });

    showToast(result.message, result.success ? 'success' : 'error');

    if (!result.success) {
        // 실패 시 체크박스 상태 되돌리기
        checkbox.checked = !enabled;
    } else {
        updateStats();
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

// 통계 업데이트
const updateStats = () => {
    // 통계는 페이지 새로고침 없이 업데이트하려면 별도 API가 필요
    // 현재는 간단히 페이지 새로고침
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

    // 폼 이벤트 리스너 추가
    document.getElementById('addBlockForm').addEventListener('submit', addIPBlock);
    document.getElementById('addWhitelistForm').addEventListener('submit', addWhitelistIP);

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