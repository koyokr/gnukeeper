<?php
$sub_menu = '950300';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// 현재 접속 IP 가져오기
$current_admin_ip = $_SERVER['REMOTE_ADDR'] ?? '';


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

// sb_block_level 컬럼 존재 여부 확인 및 추가
$check_column = sql_query("SHOW COLUMNS FROM " . G5_TABLE_PREFIX . "security_ip_block LIKE 'sb_block_level'", false);
if ($check_column && sql_num_rows($check_column) == 0) {
    // 컬럼이 없으면 추가
    sql_query("ALTER TABLE " . G5_TABLE_PREFIX . "security_ip_block 
               ADD COLUMN sb_block_level varchar(50) NOT NULL DEFAULT 'access' 
               COMMENT '차단 레벨 (access,login,write,memo 조합)' 
               AFTER sb_block_type", false);
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
$ip_foreign_block_enabled = gk_get_security_config('ip_foreign_block_enabled', '0');
$ip_foreign_block_level = gk_get_security_config('ip_foreign_block_level', 'access');

// 해외 IP 차단 통계 (security_block_ip_foreign.extend.php에서 함수들 로드)
if (function_exists('gk_get_korea_ip_stats')) {
    $korea_ip_stats = gk_get_korea_ip_stats();
    $korea_ip_count = $korea_ip_stats['total_ranges'];
} else {
    $korea_ip_stats = ['file_exists' => false, 'last_updated' => 'Unknown'];
    $korea_ip_count = 0;
}


// 국내 IP 확인 함수 (extend 파일에서 로드되지 않은 경우 기본 구현)
if (!function_exists('gk_is_korea_ip')) {
    function gk_is_korea_ip($ip) {
        // IP 유효성 검사 후 기본값 반환
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        return false; // extend 파일이 로드되지 않으면 기본적으로 해외로 처리
    }
}

// 사설 IP 종류 확인 함수 (extend 파일에서 로드되지 않은 경우 기본 구현)
if (!function_exists('gk_get_private_ip_type')) {
    function gk_get_private_ip_type($ip) {
        // IP 유효성 검사 후 기본값 반환
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }
        return null; // extend 파일이 로드되지 않으면 null 반환
    }
}

if (!function_exists('gk_is_private_or_reserved_ip')) {
    function gk_is_private_or_reserved_ip($ip) {
        // IP 유효성 검사 후 기본값 반환
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        return false; // extend 파일이 로드되지 않으면 기본적으로 공인 IP로 처리
    }
}

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

$g5['title'] = '차단 관리';
include_once('./admin.head.php');
?>

<style>
/* security_home.php, access_control.php, security_extension.php 스타일 기반 */
.security-dashboard {
    margin: 20px 0;
}

.dashboard-section {
    margin-bottom: 30px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    font-weight: bold;
    font-size: 16px;
    color: #333;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
}

.section-header:hover {
    background: #e9ecef;
}

.section-content {
    padding: 15px;
}

.dashboard-title {
    color: #333;
    margin-bottom: 20px;
    font-size: 24px;
    font-weight: bold;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 5px;
    padding: 15px;
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.stat-number.on {
    color: #28a745;
}

.stat-number.off {
    color: #dc3545;
}

.stat-label {
    font-size: 14px;
    color: #666;
}

.stats-container {
    background: rgba(248, 249, 250, 0.7);
    border: 1px solid rgba(222, 226, 230, 0.8);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    backdrop-filter: blur(5px);
}

.stats-container .stats-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.stats-container .stat-card {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 15px;
    font-size: 13px;
    font-weight: bold;
    color: #495057;
}

.stats-container .stat-icon {
    margin-right: 5px;
    font-size: 1em;
}

.stats-container .stat-number {
    margin-right: 5px;
    font-weight: bold;
}

.stats-container .stat-label {
    font-size: 13px;
    color: #666;
}

.extension-container {
    background: rgba(248, 249, 250, 0.7);
    border: 1px solid rgba(222, 226, 230, 0.8);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    backdrop-filter: blur(5px);
}

.extension-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.extension-item {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 15px;
    font-size: 13px;
    font-weight: bold;
    color: #495057;
}

.extension-item.high {
    background: #ffebee;
    border-color: #ef5350;
    color: #d32f2f;
}

.extension-item.medium {
    background: #fff3e0;
    border-color: #ff9800;
    color: #f57c00;
}

.extension-item.low {
    background: #e8f5e8;
    border-color: #4caf50;
    color: #388e3c;
}

.extension-item.unknown {
    background: #f3e5f5;
    border-color: #9c27b0;
    color: #7b1fa2;
}

.info-highlight {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
    color: #0056b3;
    font-size: 15px;
    font-weight: 500;
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

.collapse-icon {
    font-size: 12px;
    margin-left: 10px;
    transition: transform 0.2s ease;
}

.section-header.collapsed .collapse-icon {
    transform: rotate(-90deg);
}

.section-content.collapsed {
    display: none;
}

.section-actions {
    display: flex;
    gap: 10px;
}


.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    padding: 0;
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
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-size: 14px;
}

.form-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.form-help {
    font-size: 12px;
    color: #718096;
}

.btn-primary {
    background: #007bff;
    border: 1px solid #007bff;
    color: white;
    padding: 8px 12px;
    border-radius: 3px;
    font-size: 14px;
    cursor: pointer;
}

.btn-primary:hover {
    background: #0056b3;
    border-color: #004085;
}

.btn-danger {
    background: #dc3545;
    border: 1px solid #dc3545;
    color: white;
    padding: 8px 12px;
    border-radius: 3px;
    font-size: 14px;
    cursor: pointer;
}

.btn-danger:hover {
    background: #c82333;
    border-color: #bd2130;
}

.btn-small {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
}

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

.checkbox-all {
    width: 16px;
    height: 16px;
}

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
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
.btn-danger:focus,
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
    .section-content,
    .btn-primary,
    .btn-danger {
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

<div class="security-dashboard">
    <h1 class="dashboard-title">차단 관리</h1>

    <!-- 현황판 -->
    <div class="dashboard-section">
        <div class="section-header">
            📊 현황판
        </div>
        <div class="section-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($total_blocks); ?>건</div>
                    <div class="stat-label">차단된 IP 시도</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($auto_blocks); ?>건</div>
                    <div class="stat-label">자동 차단 시도</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($whitelist_count); ?>건</div>
                    <div class="stat-label">예외 IP</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number <?php echo $ip_block_enabled == '1' ? 'on' : 'off'; ?>"><?php echo $ip_block_enabled == '1' ? 'ON' : 'OFF'; ?></div>
                    <div class="stat-label">고급 IP 차단</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number <?php echo $ip_foreign_block_enabled == '1' ? 'on' : 'off'; ?>"><?php echo $ip_foreign_block_enabled == '1' ? 'ON' : 'OFF'; ?></div>
                    <div class="stat-label">해외 IP 차단</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 예외 IP 관리 -->
    <div class="dashboard-section">
        <div class="section-header" onclick="toggleSection('whitelist-section')" style="cursor: pointer;">
            ✅ 예외 IP 설정 <span id="whitelist-toggle" style="float: right;">▼</span>
        </div>

        <div class="section-content" id="whitelist-section">
        <div class="extension-container">
            <form method="post" action="./security_block_update.php">
                <input type="hidden" name="action" value="add_whitelist">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: start;">
                    <div class="form-group">
                        <label class="form-label">IP</label>
                        <input type="text" name="whitelist_ip" class="form-input" value="<?php echo htmlspecialchars($current_admin_ip); ?>" required>
                        <div class="form-help">차단에서 제외할 IP를 입력하세요</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">메모</label>
                        <input type="text" name="whitelist_memo" class="form-input" placeholder="메모 (선택사항)">
                    </div>
                    <div style="display: flex; align-items: end; margin-top: 28px;">
                        <button type="submit" class="btn-primary">예외 IP 추가</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($whitelist_result && sql_num_rows($whitelist_result) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>IP</th>
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
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <h3>예외 IP가 없습니다</h3>
                <p>아직 예외 IP가 없습니다.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- 차단 관리 -->
    <div class="dashboard-section">
        <div class="section-header" onclick="toggleSection('block-section')" style="cursor: pointer;">
            📋 고급 차단 IP 설정 <span id="block-toggle" style="float: right;">▼</span>
        </div>

        <div class="section-content" id="block-section">
        <!-- gnuboard5 통합 안내 -->
        <div class="info-highlight">
            IP 차단 기능은 gnuboard5의 기본 IP 차단 기능을 대체합니다. 스팸 차단 기능과 연동됩니다.
        </div>

        <!-- IP 차단 추가 폼 -->
        <div class="extension-container">
            <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-weight: bold; font-size: 16px; color: #333;">IP 차단 기능 상태</span>
                <div class="feature-switch <?php echo $ip_block_enabled == '1' ? 'enabled' : ''; ?>"
                     onclick="toggleFeature()"
                     data-enabled="<?php echo $ip_block_enabled; ?>">
                    <div class="feature-switch-handle"></div>
                </div>
            </div>
            <div class="extension-list">
                <div class="extension-item <?php echo $ip_block_enabled == '1' ? 'low' : 'high'; ?>">
                    <?php echo $ip_block_enabled == '1' ? '✅' : '❌'; ?>
                    IP 차단 <?php echo $ip_block_enabled == '1' ? '활성화' : '비활성화'; ?>
                </div>
            </div>
        </div>

        <div class="extension-container">
            <form method="post" action="./security_block_update.php">
                <input type="hidden" name="action" value="add_block">
                <input type="hidden" name="duration" value="permanent">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: start;">
                    <div class="form-group">
                        <label class="form-label">IP/CIDR</label>
                        <input type="text" name="ip" class="form-input" placeholder="예: 192.168.1.100 또는 192.168.1.0/24" required>
                        <div class="form-help">단일 IP 또는 CIDR 표기법으로 IP 대역을 입력하세요</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">차단 사유</label>
                        <input type="text" name="reason" class="form-input" placeholder="차단 사유를 입력하세요" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">차단 수준</label>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <label style="font-weight: normal;">
                                <input type="checkbox" name="block_level[]" value="access" checked> 접속 차단
                            </label>
                            <label style="font-weight: normal;">
                                <input type="checkbox" name="block_level[]" value="login"> 로그인 차단
                            </label>
                            <label style="font-weight: normal;">
                                <input type="checkbox" name="block_level[]" value="write"> 게시글/댓글 작성 차단
                            </label>
                            <label style="font-weight: normal;">
                                <input type="checkbox" name="block_level[]" value="memo"> 쪽지 작성 차단
                            </label>
                        </div>
                    </div>
                    <div style="display: flex; align-items: end; margin-top: 28px;">
                        <button type="submit" class="btn-primary">차단 IP 추가</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($block_result && sql_num_rows($block_result) > 0): ?>
            <form id="bulk-form">
            <table class="data-table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="checkbox-all" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <th>IP/CIDR</th>
                            <th>차단 사유</th>
                            <th>차단 수준</th>
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
                                <?php
                                $block_levels = explode(',', $row['sb_block_level'] ?? 'access');
                                $level_display = [];
                                foreach ($block_levels as $level) {
                                    $level = trim($level);
                                    switch($level) {
                                        case 'access': $level_display[] = '접속'; break;
                                        case 'login': $level_display[] = '로그인'; break;
                                        case 'write': $level_display[] = '작성'; break;
                                        case 'memo': $level_display[] = '쪽지'; break;
                                    }
                                }
                                echo implode(', ', $level_display);
                                ?>
                            </td>
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
                                <?php if ($row['sb_status'] == 'inactive'): ?>
                                    <button type="button" class="btn-primary btn-small"
                                            onclick="toggleStatus(<?php echo $row['sb_id']; ?>, '<?php echo $row['sb_status']; ?>')">
                                        활성화
                                    </button>
                                <?php endif; ?>
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
    </div>


    <!-- 해외 IP 차단 설정 -->
    <div class="dashboard-section">
        <div class="section-header" onclick="toggleSection('foreign-section')" style="cursor: pointer;">
            🌍 해외 IP 차단 설정 <span id="foreign-toggle" style="float: right;">▼</span>
        </div>
        <div class="section-content" id="foreign-section">
            <div class="info-highlight">
                해외 IP를 차단하는 기능입니다.
            </div>

            <div class="extension-container">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-weight: bold; font-size: 16px; color: #333;">해외 IP 차단 기능 상태</span>
                    <div class="feature-switch <?php echo $ip_foreign_block_enabled == '1' ? 'enabled' : ''; ?>"
                         onclick="toggleForeignFeature()"
                         data-enabled="<?php echo $ip_foreign_block_enabled; ?>">
                        <div class="feature-switch-handle"></div>
                    </div>
                </div>
                <div class="extension-list">
                    <div class="extension-item <?php echo $ip_foreign_block_enabled == '1' ? 'low' : 'high'; ?>">
                        <?php echo $ip_foreign_block_enabled == '1' ? '✅' : '❌'; ?>
                        해외 IP 차단 <?php echo $ip_foreign_block_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item <?php echo $korea_ip_stats['file_exists'] ? 'low' : 'high'; ?>">
                        <?php echo $korea_ip_stats['file_exists'] ? '✅' : '❌'; ?>
                        <?php echo $korea_ip_stats['file_exists'] ? '파일 로드됨' : '파일 없음'; ?>
                    </div>
                    <div class="extension-item">
                        📊 <?php echo number_format($korea_ip_count); ?>개 IP 대역
                    </div>
                    <?php if ($korea_ip_stats['file_exists']): ?>
                    <div class="extension-item">
                        📅 <?php echo $korea_ip_stats['last_updated']; ?>
                    </div>
                    <?php endif; ?>
                    <?php
                    $current_ip = gk_get_client_ip();
                    if ($current_ip):
                        $is_private = gk_is_private_or_reserved_ip($current_ip);
                        $private_type = gk_get_private_ip_type($current_ip);

                        if ($is_private && $private_type):
                    ?>
                    <div class="extension-item">
                        🏠 <?php echo $current_ip; ?> (<?php echo $private_type; ?>)
                    </div>
                    <?php elseif ($korea_ip_stats['file_exists']):
                        $is_korea = gk_is_korea_ip($current_ip);
                    ?>
                    <div class="extension-item <?php echo $is_korea ? 'low' : 'high'; ?>">
                        <?php echo $is_korea ? '🇰🇷' : '🌍'; ?> <?php echo $current_ip; ?> (<?php echo $is_korea ? '국내' : '해외'; ?>)
                    </div>
                    <?php else: ?>
                    <div class="extension-item unknown">
                        ❓ <?php echo $current_ip; ?> (판단 불가)
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($ip_foreign_block_enabled == '1'): ?>
            <div class="extension-container" style="margin-top: 20px;">
                <h4 style="margin-bottom: 15px; color: #333;">해외 IP 차단 수준 설정</h4>
                <form method="post" action="./security_block_update.php" id="foreign-level-form">
                    <input type="hidden" name="action" value="save_foreign_level">
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php
                        $foreign_levels = explode(',', $ip_foreign_block_level);
                        ?>
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="foreign_block_level[]" value="access" 
                                   <?php echo in_array('access', $foreign_levels) ? 'checked' : ''; ?>> 
                            <strong>접속 차단</strong> - 사이트 접속 자체를 차단
                        </label>
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="foreign_block_level[]" value="login"
                                   <?php echo in_array('login', $foreign_levels) ? 'checked' : ''; ?>> 
                            <strong>로그인 차단</strong> - 로그인만 차단
                        </label>
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="foreign_block_level[]" value="write"
                                   <?php echo in_array('write', $foreign_levels) ? 'checked' : ''; ?>> 
                            <strong>게시글/댓글 작성 차단</strong> - 글쓰기와 댓글 작성 차단
                        </label>
                        <label style="font-weight: normal;">
                            <input type="checkbox" name="foreign_block_level[]" value="memo"
                                   <?php echo in_array('memo', $foreign_levels) ? 'checked' : ''; ?>> 
                            <strong>쪽지 작성 차단</strong> - 쪽지 발송 차단
                        </label>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn-primary">차단 수준 저장</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
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
            location.reload();
        } else {
            alert('설정 변경에 실패했습니다.');
        }
    })
    .catch(error => {
        alert('오류가 발생했습니다: ' + error);
    });
}

function toggleForeignFeature() {
    const toggle = document.querySelector('#foreign-section .feature-switch');
    const enabled = toggle.dataset.enabled === '1';

    // AJAX로 설정 변경
    fetch('./security_block_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_foreign&ip_foreign_block_enabled=${enabled ? '0' : '1'}&ajax=1`
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('success') || result.includes('성공')) {
            // UI 업데이트
            toggle.classList.toggle('enabled');
            toggle.dataset.enabled = enabled ? '0' : '1';

            location.reload();
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

// 섹션 토글 함수
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    const toggle = document.getElementById(sectionId.replace('-section', '-toggle'));

    if (section.style.display === 'none') {
        section.style.display = 'block';
        toggle.textContent = '▼';
    } else {
        section.style.display = 'none';
        toggle.textContent = '▶';
    }
}

// 페이지 로드 시 바로 표시 (애니메이션 제거)
document.addEventListener('DOMContentLoaded', function() {
    // 애니메이션 없이 바로 표시
    console.log('보안 관리 페이지 로드 완료');
});
</script>

<?php
include_once('./admin.tail.php');
?>