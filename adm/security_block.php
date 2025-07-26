<?php
$sub_menu = '950300';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// 테이블 자동 생성
function gk_create_security_tables() {
    $sql_file = __DIR__ . '/security_block.sql';
    if (!file_exists($sql_file)) {
        return false;
    }
    
    $sql_content = file_get_contents($sql_file);
    if ($sql_content === false) {
        return false;
    }
    
    // {PREFIX}를 실제 테이블 접두사로 치환
    $sql_content = str_replace('{PREFIX}', G5_TABLE_PREFIX, $sql_content);
    
    // SQL 문장을 분리하여 실행
    $statements = explode(';', $sql_content);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        if (!sql_query($statement)) {
            return false;
        }
    }
    
    return true;
}

// 테이블 존재 여부 확인 및 자동 생성
if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_config LIMIT 1", false)) {
    gk_create_security_tables();
}

// 설정 로드
function gk_get_security_config($key, $default = '') {
    $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = '" . sql_escape_string($key) . "'";
    $result = sql_query($sql, false);
    
    if ($result && $row = sql_fetch_array($result)) {
        return $row['sc_value'];
    }
    
    return $default;
}

$ip_block_enabled = gk_get_security_config('ip_block_enabled', '1');

// 통계 정보
$total_blocks_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_status = 'active'";
$total_blocks_result = sql_query($total_blocks_sql, false);
$total_blocks = 0;
if ($total_blocks_result && $row = sql_fetch_array($total_blocks_result)) {
    $total_blocks = $row['cnt'];
}

$auto_blocks_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_status = 'active' AND sb_block_type != 'manual'";
$auto_blocks_result = sql_query($auto_blocks_sql, false);
$auto_blocks = 0;
if ($auto_blocks_result && $row = sql_fetch_array($auto_blocks_result)) {
    $auto_blocks = $row['cnt'];
}

$whitelist_count_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist";
$whitelist_count_result = sql_query($whitelist_count_sql, false);
$whitelist_count = 0;
if ($whitelist_count_result && $row = sql_fetch_array($whitelist_count_result)) {
    $whitelist_count = $row['cnt'];
}

// 페이징
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 차단 목록 조회
$block_sql = "SELECT * FROM " . G5_TABLE_PREFIX . "security_ip_block ORDER BY sb_datetime DESC LIMIT {$offset}, {$per_page}";
$block_result = sql_query($block_sql, false);

// 화이트리스트 조회
$whitelist_sql = "SELECT * FROM " . G5_TABLE_PREFIX . "security_ip_whitelist ORDER BY sw_datetime DESC LIMIT 10";
$whitelist_result = sql_query($whitelist_sql, false);

$g5['title'] = 'IP 차단 관리';
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

.stat-card.blocked::before { --accent-color: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
.stat-card.auto::before { --accent-color: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%); }
.stat-card.whitelist::before { --accent-color: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
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

.section-actions {
    display: flex;
    gap: 10px;
}

.btn-primary {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border: none;
    color: white;
    padding: 8px 16px;
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

.btn-danger {
    background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
    border: none;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3);
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

/* 상태 배지 */
.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
    color: #22543d;
}

.status-inactive {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
    color: #742a2a;
}

.status-expired {
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e0 100%);
    color: #4a5568;
}

.block-type-badge {
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
}

.block-type-manual {
    background: linear-gradient(135deg, #bee3f8 0%, #90cdf4 100%);
    color: #2c5282;
}

.block-type-auto {
    background: linear-gradient(135deg, #fbd38d 0%, #f6ad55 100%);
    color: #744210;
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

.bulk-actions {
    padding: 16px 24px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.checkbox-all {
    width: 16px;
    height: 16px;
}

.btn-small {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
}
</style>

<div class="security-container">
    <h1 class="dashboard-title">
        🛡️ IP 차단 관리
    </h1>
    <p class="dashboard-subtitle">
        IP 주소 및 대역을 차단하여 사이트를 보호하고, 화이트리스트로 신뢰할 수 있는 IP를 관리할 수 있습니다.
    </p>

    <!-- 통계 카드 -->
    <div class="stats-grid">
        <div class="stat-card blocked">
            <span class="stat-icon">🚫</span>
            <span class="stat-number"><?php echo number_format($total_blocks); ?></span>
            <span class="stat-label">차단된 IP</span>
        </div>
        <div class="stat-card auto">
            <span class="stat-icon">⚡</span>
            <span class="stat-number"><?php echo number_format($auto_blocks); ?></span>
            <span class="stat-label">자동 차단</span>
        </div>
        <div class="stat-card whitelist">
            <span class="stat-icon">✅</span>
            <span class="stat-number"><?php echo number_format($whitelist_count); ?></span>
            <span class="stat-label">예외 IP</span>
        </div>
        <div class="stat-card status">
            <span class="stat-icon"><?php echo $ip_block_enabled == '1' ? '🟢' : '🔴'; ?></span>
            <span class="stat-number"><?php echo $ip_block_enabled == '1' ? 'ON' : 'OFF'; ?></span>
            <span class="stat-label">차단 기능</span>
        </div>
    </div>

    <!-- gnuboard 연동 안내 -->
    <div class="integration-notice">
        <div class="integration-content" style="flex: 1;">
            <h4>🔗 gnuboard5 통합 정보</h4>
            <p>
                고급 IP 차단 기능 활성화 시 gnuboard5 기본 차단은 자동으로 비활성화됩니다.<br>
                기존 gnuboard5 파일 수정 없이 안전하게 작동합니다.
            </p>
        </div>
        <div class="toggle-controls">
            <div class="feature-switch <?php echo $ip_block_enabled == '1' ? 'enabled' : ''; ?>" 
                 onclick="toggleFeature()" 
                 data-enabled="<?php echo $ip_block_enabled; ?>">
                <div class="feature-switch-handle"></div>
            </div>
        </div>
    </div>

    <!-- IP 차단 추가 폼 -->
    <div class="security-section">
        <div class="section-header">
            <span>➕ IP 차단 추가</span>
        </div>
        <form method="post" action="./security_block_update.php">
            <input type="hidden" name="action" value="add_block">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">IP 주소 또는 CIDR</label>
                    <input type="text" name="ip" class="form-input" placeholder="예: 192.168.1.100 또는 192.168.1.0/24" required>
                    <div class="form-help">단일 IP 또는 CIDR 표기법으로 IP 대역을 입력하세요</div>
                </div>
                <div class="form-group">
                    <label class="form-label">차단 사유</label>
                    <input type="text" name="reason" class="form-input" placeholder="차단 사유를 입력하세요" required>
                </div>
                <div class="form-group">
                    <label class="form-label">차단 유형</label>
                    <select name="duration" class="form-select">
                        <option value="permanent">영구 차단</option>
                        <option value="temporary">임시 차단</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">차단 종료 시간 (임시 차단시)</label>
                    <input type="datetime-local" name="end_datetime" class="form-input">
                    <div class="form-help">임시 차단 선택시에만 적용됩니다</div>
                </div>
            </div>
            <div style="padding: 0 32px 24px;">
                <button type="submit" class="btn-primary">IP 차단 추가</button>
            </div>
        </form>
    </div>

    <!-- 차단 목록 -->
    <div class="security-section">
        <div class="section-header">
            <span>📋 차단된 IP 목록</span>
            <div class="section-actions">
                <button type="button" class="btn-danger btn-small" onclick="bulkAction('delete')">선택 삭제</button>
                <button type="button" class="btn-primary btn-small" onclick="bulkAction('toggle')">선택 토글</button>
            </div>
        </div>
        
        <?php if ($block_result && sql_num_rows($block_result) > 0): ?>
            <form id="bulk-form">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="checkbox-all" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <th>IP 주소</th>
                            <th>차단 사유</th>
                            <th>유형</th>
                            <th>기간</th>
                            <th>적중</th>
                            <th>상태</th>
                            <th>등록일</th>
                            <th>액션</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = sql_fetch_array($block_result)): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_ids[]" value="<?php echo $row['sb_id']; ?>" class="row-checkbox">
                            </td>
                            <td>
                                <span class="ip-address"><?php echo htmlspecialchars($row['sb_ip']); ?></span>
                                <?php if (strpos($row['sb_ip'], '/') !== false): ?>
                                <small style="color: #718096;">(대역)</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['sb_reason']); ?></td>
                            <td>
                                <span class="block-type-badge block-type-<?php echo $row['sb_block_type'] == 'manual' ? 'manual' : 'auto'; ?>">
                                    <?php
                                    switch($row['sb_block_type']) {
                                        case 'manual': echo '수동'; break;
                                        case 'auto_login': echo '로그인'; break;
                                        case 'auto_spam': echo '스팸'; break;
                                        case 'auto_abuse': echo '악성'; break;
                                        default: echo '자동';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['sb_duration'] == 'permanent'): ?>
                                    <strong>영구</strong>
                                <?php else: ?>
                                    임시<br>
                                    <small style="color: #718096;"><?php echo $row['sb_end_datetime']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo number_format($row['sb_hit_count']); ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo $row['sb_status']; ?>">
                                    <?php
                                    switch($row['sb_status']) {
                                        case 'active': echo '활성'; break;
                                        case 'inactive': echo '비활성'; break;
                                        case 'expired': echo '만료'; break;
                                        default: echo $row['sb_status'];
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo substr($row['sb_datetime'], 0, 16); ?></td>
                            <td>
                                <button type="button" class="btn-primary btn-small" 
                                        onclick="toggleStatus(<?php echo $row['sb_id']; ?>, '<?php echo $row['sb_status']; ?>')">
                                    토글
                                </button>
                                <button type="button" class="btn-danger btn-small" 
                                        onclick="deleteBlock(<?php echo $row['sb_id']; ?>)">
                                    삭제
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </form>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <h3>차단된 IP가 없습니다</h3>
                <p>아직 차단된 IP 주소가 없습니다. 위의 폼을 사용하여 IP를 차단하세요.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- 화이트리스트 관리 -->
    <div class="security-section">
        <div class="section-header">
            <span>✅ 예외 IP 관리</span>
        </div>
        
        <form method="post" action="./security_block_update.php">
            <input type="hidden" name="action" value="add_whitelist">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">예외 IP 주소</label>
                    <input type="text" name="whitelist_ip" class="form-input" placeholder="예: 192.168.1.100" required>
                    <div class="form-help">차단에서 제외할 IP 주소를 입력하세요</div>
                </div>
                <div class="form-group">
                    <label class="form-label">메모</label>
                    <input type="text" name="whitelist_memo" class="form-input" placeholder="메모 (선택사항)">
                </div>
            </div>
            <div style="padding: 0 32px 24px;">
                <button type="submit" class="btn-primary">예외 IP 추가</button>
            </div>
        </form>

        <?php if ($whitelist_result && sql_num_rows($whitelist_result) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>IP 주소</th>
                        <th>메모</th>
                        <th>등록일</th>
                        <th>액션</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = sql_fetch_array($whitelist_result)): ?>
                    <tr>
                        <td>
                            <span class="ip-address"><?php echo htmlspecialchars($row['sw_ip']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($row['sw_memo']); ?></td>
                        <td><?php echo substr($row['sw_datetime'], 0, 16); ?></td>
                        <td>
                            <button type="button" class="btn-danger btn-small" 
                                    onclick="deleteWhitelist(<?php echo $row['sw_id']; ?>)">
                                삭제
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleFeature() {
    const toggle = document.querySelector('.feature-switch');
    const enabled = toggle.dataset.enabled === '1';
    
    // AJAX로 설정 변경
    fetch('./security_block_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save_config&ip_block_enabled=${enabled ? '0' : '1'}&ajax=1`
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

function toggleAllCheckboxes(checkbox) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function bulkAction(action) {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    if (selected.length === 0) {
        alert('선택된 항목이 없습니다.');
        return;
    }
    
    const ids = Array.from(selected).map(cb => cb.value);
    
    if (action === 'delete') {
        if (!confirm(`${ids.length}개 항목을 삭제하시겠습니까?`)) return;
        
        fetch('./security_block_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=bulk_delete&selected_ids=${JSON.stringify(ids)}&ajax=1`
        })
        .then(response => response.text())
        .then(result => {
            if (result.includes('success')) {
                location.reload();
            } else {
                alert('삭제에 실패했습니다.');
            }
        });
    } else if (action === 'toggle') {
        const status = prompt('변경할 상태를 입력하세요 (active/inactive):', 'active');
        if (!status) return;
        
        fetch('./security_block_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=bulk_toggle&selected_ids=${JSON.stringify(ids)}&bulk_status=${status}&ajax=1`
        })
        .then(response => response.text())
        .then(result => {
            if (result.includes('success')) {
                location.reload();
            } else {
                alert('상태 변경에 실패했습니다.');
            }
        });
    }
}

function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    fetch('./security_block_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_status&sb_id=${id}&status=${currentStatus}&ajax=1`
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('success')) {
            location.reload();
        } else {
            alert('상태 변경에 실패했습니다.');
        }
    });
}

function deleteBlock(id) {
    if (!confirm('이 IP 차단을 삭제하시겠습니까?')) return;
    
    fetch('./security_block_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_block&sb_id=${id}&ajax=1`
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('success')) {
            location.reload();
        } else {
            alert('삭제에 실패했습니다.');
        }
    });
}

function deleteWhitelist(id) {
    if (!confirm('이 예외 IP를 삭제하시겠습니까?')) return;
    
    fetch('./security_block_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_whitelist&sw_id=${id}&ajax=1`
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('success')) {
            location.reload();
        } else {
            alert('삭제에 실패했습니다.');
        }
    });
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