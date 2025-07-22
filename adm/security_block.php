<?php
$sub_menu = '950300';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '차단관리';
require_once './admin.head.php';

// 테이블 관리 클래스
class SecurityTableManager {
    
    public static function checkTablesExist($prefix = 'g5_') {
        $tables = array(
            $prefix . 'security_ip_block',
            $prefix . 'security_ip_log',
            $prefix . 'security_ip_whitelist', 
            $prefix . 'security_config'
        );
        
        foreach ($tables as $table) {
            $sql = "SHOW TABLES LIKE '{$table}'";
            $result = sql_query($sql);
            if (sql_num_rows($result) == 0) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function getTableNames($prefix = 'g5_') {
        return array(
            $prefix . 'security_ip_block' => 'IP 차단 관리 테이블',
            $prefix . 'security_ip_log' => 'IP 차단 로그 테이블',
            $prefix . 'security_ip_whitelist' => 'IP 화이트리스트 테이블',
            $prefix . 'security_config' => '보안 설정 테이블'
        );
    }
    
    public static function createTables($prefix = 'g5_') {
        $sql_file = G5_ADMIN_PATH . '/security_ip_block.sql';
        
        if (!file_exists($sql_file)) {
            return false;
        }
        
        $sql_content = file_get_contents($sql_file);
        $sql_content = str_replace('{PREFIX}', $prefix, $sql_content);
        $queries = explode(';', $sql_content);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query) && !preg_match('/^\s*--/', $query)) {
                sql_query($query);
            }
        }
        
        return true;
    }
    
    public static function dropTables($prefix = 'g5_') {
        $tables = array(
            $prefix . 'security_ip_log',
            $prefix . 'security_ip_whitelist',
            $prefix . 'security_ip_block', 
            $prefix . 'security_config'
        );
        
        foreach ($tables as $table) {
            sql_query("DROP TABLE IF EXISTS {$table}");
        }
        
        return true;
    }
}

// IP 차단 관련 함수들
function ip_to_long_safe($ip) {
    $long = ip2long($ip);
    if ($long === false) return 0;
    return sprintf('%u', $long);
}

function is_valid_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function is_valid_cidr($cidr) {
    if (strpos($cidr, '/') === false) return false;
    list($ip, $mask) = explode('/', $cidr, 2);
    
    if (!is_valid_ip($ip)) return false;
    
    $mask = (int)$mask;
    if ($mask < 0 || $mask > 32) return false;
    
    return true;
}

function parse_cidr($cidr) {
    list($ip, $mask) = explode('/', $cidr, 2);
    $mask = (int)$mask;
    
    $start_ip = ip_to_long_safe($ip) & ((0xFFFFFFFF << (32 - $mask)) & 0xFFFFFFFF);
    $end_ip = $start_ip | ((1 << (32 - $mask)) - 1);
    
    return array($start_ip, $end_ip);
}

// 현재 설정된 테이블 접두사 확인 (기존 gnuboard5와 동일)
function detectCurrentPrefix() {
    // 1. 상수로 정의된 값 확인 (가장 확실)
    if (defined('G5_TABLE_PREFIX')) {
        return G5_TABLE_PREFIX;
    }
    
    // 2. 기존 gnuboard5 테이블에서 역추적
    $common_tables = ['config', 'member', 'board'];
    
    foreach ($common_tables as $table) {
        $sql = "SHOW TABLES LIKE '%{$table}'";
        $result = sql_query($sql, false);
        
        if ($result && sql_num_rows($result) > 0) {
            while ($row = sql_fetch_array($result)) {
                $table_name = array_values($row)[0];
                // gnuboard5 기본 테이블 패턴 매칭
                if (preg_match('/^(.+)' . preg_quote($table) . '$/', $table_name, $matches)) {
                    return $matches[1];
                }
            }
        }
    }
    
    // 3. 기본값
    return 'g5_';
}

$current_prefix = detectCurrentPrefix();

// 보안 설정 관리 함수들
function getSecurityConfig($key = null) {
    global $current_prefix;
    
    if ($key) {
        $sql = "SELECT sc_value FROM {$current_prefix}security_config WHERE sc_key = '" . sql_escape_string($key) . "'";
        $row = sql_fetch($sql);
        return $row ? $row['sc_value'] : null;
    } else {
        $config = array();
        $result = sql_query("SELECT sc_key, sc_value FROM {$current_prefix}security_config");
        while ($row = sql_fetch_array($result)) {
            $config[$row['sc_key']] = $row['sc_value'];
        }
        return $config;
    }
}

function setSecurityConfig($key, $value) {
    global $current_prefix;
    
    $sql = "INSERT INTO {$current_prefix}security_config SET 
                sc_key = '" . sql_escape_string($key) . "',
                sc_value = '" . sql_escape_string($value) . "',
                sc_datetime = NOW()
            ON DUPLICATE KEY UPDATE 
                sc_value = '" . sql_escape_string($value) . "'";
    
    return sql_query($sql);
}

// 화이트리스트 관리 함수들
function isWhitelistedIP($ip) {
    global $current_prefix;
    
    $ip_long = ip_to_long_safe($ip);
    $sql = "SELECT COUNT(*) as cnt FROM {$current_prefix}security_ip_whitelist 
            WHERE {$ip_long} BETWEEN sw_start_ip AND sw_end_ip";
    $row = sql_fetch($sql);
    return $row['cnt'] > 0;
}

function addWhitelistIP($ip, $memo = '') {
    global $current_prefix;
    
    $start_ip = $end_ip = 0;
    
    if (strpos($ip, '/') !== false) {
        // CIDR
        if (!is_valid_cidr($ip)) {
            return false;
        }
        list($start_ip, $end_ip) = parse_cidr($ip);
    } else {
        // 단일 IP
        if (!is_valid_ip($ip)) {
            return false;
        }
        $start_ip = $end_ip = ip_to_long_safe($ip);
    }
    
    $sql = "INSERT INTO {$current_prefix}security_ip_whitelist SET
                sw_ip = '" . sql_escape_string($ip) . "',
                sw_start_ip = " . (int)$start_ip . ",
                sw_end_ip = " . (int)$end_ip . ",
                sw_memo = '" . sql_escape_string($memo) . "',
                sw_datetime = NOW()";
    
    return sql_query($sql);
}

// 테이블 존재 확인
$tables_exist = SecurityTableManager::checkTablesExist($current_prefix);

// 액션 처리
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action && $is_admin == 'super') {
    switch ($action) {
        case 'install_tables':
            if (!$tables_exist) {
                if (SecurityTableManager::createTables($current_prefix)) {
                    alert('테이블이 성공적으로 설치되었습니다.');
                } else {
                    alert('테이블 설치에 실패했습니다.');
                }
            }
            break;
            
        case 'uninstall_tables':
            if ($tables_exist) {
                if (SecurityTableManager::dropTables($current_prefix)) {
                    alert('테이블이 성공적으로 삭제되었습니다.');
                } else {
                    alert('테이블 삭제에 실패했습니다.');
                }
            }
            break;
            
        case 'save_config':
            if (!$tables_exist) {
                alert('먼저 테이블을 설치해주세요.');
                break;
            }
            
            $ip_block_enabled = isset($_POST['ip_block_enabled']) ? '1' : '0';
            setSecurityConfig('ip_block_enabled', $ip_block_enabled);
            
            // gnuboard5 기본 IP 차단 기능 반대로 설정
            $original_intercept = $ip_block_enabled == '1' ? '0' : '1';
            
            // config 테이블 업데이트
            $config_sql = "UPDATE {$g5['config_table']} SET cf_intercept_ip = '{$original_intercept}'";
            sql_query($config_sql);
            
            alert('설정이 저장되었습니다. (gnuboard5 기본 IP 차단 기능도 자동 조정됨)');
            break;
            
        case 'add_whitelist':
            if (!$tables_exist) {
                alert('먼저 테이블을 설치해주세요.');
                break;
            }
            
            $ip = trim($_POST['whitelist_ip']);
            $memo = trim($_POST['whitelist_memo']);
            
            if (!$ip) {
                alert('IP 주소를 입력해주세요.');
                break;
            }
            
            if (addWhitelistIP($ip, $memo)) {
                alert('화이트리스트에 추가되었습니다.');
            } else {
                alert('화이트리스트 추가에 실패했습니다.');
            }
            break;
            
        case 'delete_whitelist':
            $sw_id = (int)$_POST['sw_id'];
            if ($sw_id > 0) {
                sql_query("DELETE FROM {$current_prefix}security_ip_whitelist WHERE sw_id = " . (int)$sw_id);
                alert('화이트리스트에서 삭제되었습니다.');
            }
            break;
            
        case 'add_block':
            if (!$tables_exist) {
                alert('먼저 테이블을 설치해주세요.');
                break;
            }
            
            $ip = trim($_POST['ip']);
            $reason = trim($_POST['reason']);
            $duration = $_POST['duration'];
            $end_datetime = $_POST['end_datetime'];
            
            if (!$ip || !$reason) {
                alert('IP 주소와 차단 사유를 입력해주세요.');
                break;
            }
            
            // 화이트리스트 확인
            if (isWhitelistedIP($ip)) {
                alert('이 IP는 화이트리스트에 등록되어 있어 차단할 수 없습니다.');
                break;
            }
            
            $start_ip = $end_ip = 0;
            
            if (strpos($ip, '/') !== false) {
                // CIDR
                if (!is_valid_cidr($ip)) {
                    alert('올바른 CIDR 형식이 아닙니다.');
                    break;
                }
                list($start_ip, $end_ip) = parse_cidr($ip);
            } else {
                // 단일 IP
                if (!is_valid_ip($ip)) {
                    alert('올바른 IP 주소가 아닙니다.');
                    break;
                }
                $start_ip = $end_ip = ip_to_long_safe($ip);
            }
            
            $sql = "INSERT INTO {$current_prefix}security_ip_block SET
                        sb_ip = '" . sql_escape_string($ip) . "',
                        sb_start_ip = " . (int)$start_ip . ",
                        sb_end_ip = " . (int)$end_ip . ",
                        sb_reason = '" . sql_escape_string($reason) . "',
                        sb_block_type = 'manual',
                        sb_duration = '" . sql_escape_string($duration) . "',
                        sb_end_datetime = " . ($duration == 'temporary' && $end_datetime ? "'" . sql_escape_string($end_datetime) . "'" : 'NULL') . ",
                        sb_datetime = NOW()";
            
            if (sql_query($sql)) {
                alert('IP 차단이 추가되었습니다.');
            } else {
                alert('IP 차단 추가에 실패했습니다.');
            }
            break;
            
        case 'delete_block':
            $sb_id = (int)$_POST['sb_id'];
            if ($sb_id > 0) {
                sql_query("DELETE FROM {$current_prefix}security_ip_block WHERE sb_id = {$sb_id}");
                alert('IP 차단이 삭제되었습니다.');
            }
            break;
            
        case 'bulk_delete':
            if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
                $ids = array_map('intval', $_POST['selected_ids']);
                $ids = array_filter($ids, function($id) { return $id > 0; });
                
                if (!empty($ids)) {
                    $id_list = implode(',', $ids);
                    sql_query("DELETE FROM {$current_prefix}security_ip_block WHERE sb_id IN ({$id_list})");
                    alert(count($ids) . '개의 IP 차단이 삭제되었습니다.');
                } else {
                    alert('삭제할 항목을 선택해주세요.');
                }
            } else {
                alert('삭제할 항목을 선택해주세요.');
            }
            break;
            
        case 'bulk_toggle':
            if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
                $ids = array_map('intval', $_POST['selected_ids']);
                $ids = array_filter($ids, function($id) { return $id > 0; });
                $new_status = $_POST['bulk_status'] == 'active' ? 'inactive' : 'active';
                
                if (!empty($ids)) {
                    $id_list = implode(',', $ids);
                    sql_query("UPDATE {$current_prefix}security_ip_block SET sb_status = '" . sql_escape_string($new_status) . "' WHERE sb_id IN ({$id_list})");
                    alert(count($ids) . '개 항목의 상태가 변경되었습니다.');
                } else {
                    alert('변경할 항목을 선택해주세요.');
                }
            } else {
                alert('변경할 항목을 선택해주세요.');
            }
            break;
            
        case 'toggle_status':
            $sb_id = (int)$_POST['sb_id'];
            $status = $_POST['status'] == 'active' ? 'inactive' : 'active';
            if ($sb_id > 0) {
                sql_query("UPDATE {$current_prefix}security_ip_block SET sb_status = '" . sql_escape_string($status) . "' WHERE sb_id = " . (int)$sb_id);
            }
            break;
    }
    
    goto_url('./security_block.php');
}

// 테이블이 존재할 때만 데이터 조회
if ($tables_exist) {
    // 보안 설정 로드
    $security_config = getSecurityConfig();
    $ip_block_enabled = isset($security_config['ip_block_enabled']) ? $security_config['ip_block_enabled'] : '1';
    
    // 화이트리스트 조회
    $whitelist_sql = "SELECT * FROM {$current_prefix}security_ip_whitelist ORDER BY sw_datetime DESC";
    $whitelist_result = sql_query($whitelist_sql);
    $whitelist_count = sql_num_rows($whitelist_result);
    // 검색 및 필터링
    $where = array();
    $search_ip = isset($_GET['search_ip']) ? trim($_GET['search_ip']) : '';
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
    $filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

    if ($search_ip) {
        $where[] = "sb_ip LIKE '%" . sql_escape_string($search_ip) . "%'";
    }
    if ($filter_type) {
        $where[] = "sb_block_type = '" . sql_escape_string($filter_type) . "'";
    }
    if ($filter_status) {
        $where[] = "sb_status = '" . sql_escape_string($filter_status) . "'";
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // 페이징
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // 전체 개수
    $total_sql = "SELECT COUNT(*) as cnt FROM {$current_prefix}security_ip_block {$where_sql}";
    $total_result = sql_fetch($total_sql);
    $total_count = $total_result['cnt'];
    $total_pages = ceil($total_count / $per_page);

    // 목록 조회
    $list_sql = "SELECT * FROM {$current_prefix}security_ip_block {$where_sql} 
                 ORDER BY sb_datetime DESC 
                 LIMIT {$offset}, {$per_page}";
    $list_result = sql_query($list_sql);

    // 통계 조회
    function get_block_stats() {
        global $current_prefix;
        $stats = array();
        
        $result = sql_fetch("SELECT COUNT(*) as cnt FROM {$current_prefix}security_ip_block WHERE sb_status = 'active'");
        $stats['total_blocks'] = $result['cnt'];
        
        $result = sql_fetch("SELECT COUNT(*) as cnt FROM {$current_prefix}security_ip_block WHERE sb_status = 'active' AND sb_block_type = 'manual'");
        $stats['manual_blocks'] = $result['cnt'];
        
        $result = sql_fetch("SELECT COUNT(*) as cnt FROM {$current_prefix}security_ip_block WHERE sb_status = 'active' AND sb_block_type != 'manual'");
        $stats['auto_blocks'] = $result['cnt'];
        
        $today = date('Y-m-d');
        $result = sql_fetch("SELECT COUNT(*) as cnt FROM {$current_prefix}security_ip_block WHERE DATE(sb_datetime) = '{$today}'");
        $stats['today_blocks'] = $result['cnt'];
        
        $result = sql_fetch("SELECT SUM(sb_hit_count) as total FROM {$current_prefix}security_ip_block WHERE sb_status = 'active'");
        $stats['total_hits'] = $result['total'] ?: 0;
        
        return $stats;
    }

    $block_stats = get_block_stats();
}
?>

<style>
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
    justify-content: space-between;
    align-items: center;
}

.section-content {
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    color: #dc3545;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #666;
}

.install-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.install-notice h3 {
    color: #856404;
    margin-bottom: 15px;
}

.install-notice p {
    color: #856404;
    margin-bottom: 15px;
}

.table-preview {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 15px;
    margin: 15px 0;
}

.table-preview h4 {
    color: #495057;
    margin-bottom: 10px;
    font-size: 16px;
}

.table-preview ul {
    margin: 0;
    padding-left: 20px;
}

.table-preview li {
    color: #6c757d;
    margin-bottom: 5px;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    align-items: end;
}

.form-group {
    flex: 1;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary { background: #007bff; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-warning { background: #ffc107; color: #212529; }
.btn-success { background: #28a745; color: white; }
.btn-sm { padding: 4px 8px; font-size: 12px; }

.search-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    align-items: end;
}

.search-filters .form-group {
    min-width: 150px;
}

.block-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.block-table th,
.block-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.block-table th {
    background: #f8f9fa;
    font-weight: bold;
    color: #333;
}

.block-table tr:hover {
    background: #f8f9fa;
}

.status-active { color: #dc3545; font-weight: bold; }
.status-inactive { color: #6c757d; }

.block-type-manual {
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.block-type-auto {
    background: #fff3e0;
    color: #f57c00;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.pagination {
    text-align: center;
    margin-top: 20px;
}

.pagination a {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    border: 1px solid #ddd;
    text-decoration: none;
    color: #333;
}

.pagination a.current {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.dashboard-title {
    color: #333;
    margin-bottom: 20px;
    font-size: 24px;
    font-weight: bold;
}

.end-datetime-group {
    display: none;
}

.end-datetime-group.show {
    display: block;
}

.plugin-info {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
}

.plugin-info h4 {
    color: #0056b3;
    margin-bottom: 10px;
}

.plugin-info ul {
    margin: 0;
    padding-left: 20px;
    color: #495057;
}

.current-prefix-info {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 5px;
    padding: 15px;
    margin: 15px 0;
}

.current-prefix-info h4 {
    color: #0056b3;
    margin-bottom: 10px;
    font-size: 16px;
}

.current-prefix-info p {
    margin: 0;
    color: #495057;
}

.current-prefix-info code {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    border: 1px solid #dee2e6;
}
</style>

<script>
function toggleEndDateTime() {
    const duration = document.querySelector('select[name="duration"]').value;
    const endGroup = document.querySelector('.end-datetime-group');
    
    if (duration === 'temporary') {
        endGroup.classList.add('show');
    } else {
        endGroup.classList.remove('show');
    }
}

function confirmDelete(id) {
    if (confirm('정말 삭제하시겠습니까?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_block">' +
                        '<input type="hidden" name="sb_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleStatus(id, currentStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="toggle_status">' +
                    '<input type="hidden" name="sb_id" value="' + id + '">' +
                    '<input type="hidden" name="status" value="' + currentStatus + '">';
    document.body.appendChild(form);
    form.submit();
}

function submitAction(action) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="' + action + '">';
    document.body.appendChild(form);
    form.submit();
}

function confirmTableDelete() {
    const tableList = [
        '<?php echo $current_prefix; ?>security_ip_block',
        '<?php echo $current_prefix; ?>security_ip_log',
        '<?php echo $current_prefix; ?>security_ip_whitelist',
        '<?php echo $current_prefix; ?>security_config'
    ];
    
    const message = 'IP 차단 관리 기능과 다음 테이블들이 완전히 제거됩니다:\n\n' + 
                   tableList.join('\n') + 
                   '\n\n⚠️ 모든 차단 데이터와 설정이 삭제됩니다.\n정말 제거하시겠습니까?\n이 작업은 되돌릴 수 없습니다.';
    
    if (confirm(message)) {
        submitAction('uninstall_tables');
    }
}

function confirmDeleteWhitelist(id) {
    if (confirm('화이트리스트에서 삭제하시겠습니까?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_whitelist">' +
                        '<input type="hidden" name="sw_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// 일괄 처리 관련 함수들
function toggleAllCheckboxes(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = masterCheckbox.checked;
    });
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const masterCheckbox = document.getElementById('checkAll');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    masterCheckbox.checked = true;
}

function selectNone() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const masterCheckbox = document.getElementById('checkAll');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    masterCheckbox.checked = false;
}

function getSelectedIds() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = [];
    checkboxes.forEach(checkbox => {
        ids.push(checkbox.value);
    });
    return ids;
}

function bulkDelete() {
    const selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert('삭제할 항목을 선택해주세요.');
        return;
    }
    
    if (confirm(`선택된 ${selectedIds.length}개 항목을 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`)) {
        document.getElementById('bulkAction').value = 'bulk_delete';
        document.getElementById('bulkForm').submit();
    }
}

function bulkToggle(status) {
    const selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        alert('상태를 변경할 항목을 선택해주세요.');
        return;
    }
    
    const statusText = status === 'active' ? '활성화' : '비활성화';
    if (confirm(`선택된 ${selectedIds.length}개 항목을 ${statusText} 하시겠습니까?`)) {
        document.getElementById('bulkAction').value = 'bulk_toggle';
        document.getElementById('bulkStatus').value = status;
        document.getElementById('bulkForm').submit();
    }
}

function toggleSettingInfo() {
    const info = document.getElementById('settingInfo');
    const button = event.target;
    
    if (info.style.display === 'none') {
        info.style.display = 'block';
        button.innerHTML = '🔼 접기';
    } else {
        info.style.display = 'none';
        button.innerHTML = 'ℹ️ 상세 정보';
    }
}
</script>

<div class="security-dashboard">
    <h1 class="dashboard-title">IP 차단 관리</h1>
    
    <?php if (!$tables_exist): ?>
    <!-- 플러그인 정보 -->
    <div class="plugin-info">
        <h4>🔒 보안 플러그인 - IP 차단 기능</h4>
        <ul>
            <li><strong>독립적인 테이블 관리</strong>: 기존 gnuboard5 테이블과 완전히 분리되어 생성/관리됩니다</li>
            <li><strong>안전한 제거</strong>: 플러그인 제거 시 해당 테이블만 삭제되어 기존 데이터에 영향 없습니다</li>
            <li><strong>접두사 통일성</strong>: 기존 사이트의 테이블 접두사와 동일하게 설정할 수 있습니다</li>
        </ul>
    </div>
    
    <!-- 테이블 설치 안내 -->
    <div class="install-notice">
        <h3>⚠️ 테이블 설치 필요</h3>
        <p>IP 차단 기능을 사용하려면 먼저 데이터베이스 테이블을 설치해야 합니다.</p>
        
        <div class="current-prefix-info">
            <h4>🔧 현재 설정된 테이블 접두사</h4>
            <p><strong><code><?php echo $current_prefix; ?></code></strong> (dbconfig.php에서 자동 감지)</p>
        </div>
        
        <div class="table-preview">
            <h4>📋 생성될 테이블 목록</h4>
            <ul>
                <?php 
                $preview_tables = SecurityTableManager::getTableNames($current_prefix);
                foreach ($preview_tables as $table_name => $description):
                ?>
                <li><code><?php echo $table_name; ?></code> - <?php echo $description; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if ($is_admin == 'super'): ?>
        <button onclick="submitAction('install_tables')" class="btn btn-primary">테이블 설치</button>
        <?php else: ?>
        <p style="color: #dc3545;">최고관리자만 테이블을 설치할 수 있습니다.</p>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- 0. 기본 설정 -->
    <div class="dashboard-section">
        <div class="section-header">
            ⚙️ 기본 설정
            <button type="button" onclick="toggleSettingInfo()" class="btn btn-sm" style="background: #6c757d; color: white; font-size: 12px; padding: 4px 8px;">
                ℹ️ 상세 정보
            </button>
        </div>
        <div class="section-content">
            <?php if ($is_admin == 'super'): ?>
            <form method="post">
                <input type="hidden" name="action" value="save_config">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: bold; font-size: 16px;">
                        <input type="checkbox" name="ip_block_enabled" value="1" <?php echo $ip_block_enabled ? 'checked' : ''; ?>>
                        고급 IP 차단 기능 사용
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm">적용</button>
                </div>
                
                <div style="background: #e7f3ff; padding: 12px; border-radius: 5px; border-left: 4px solid #007bff;">
                    <p style="margin: 0; color: #0056b3; font-size: 14px;">
                        <strong>✨ 체크 시:</strong> 고급 IP 차단 기능 활성화 + gnuboard5 기본 차단 기능 비활성화<br>
                        <strong>🔒 해제 시:</strong> 고급 IP 차단 기능 비활성화 + gnuboard5 기본 차단 기능 활성화
                    </p>
                </div>
            </form>
            <?php else: ?>
            <div style="padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <p style="margin: 0;"><strong>고급 IP 차단:</strong> <?php echo $ip_block_enabled ? '<span style="color: #28a745;">활성화됨</span>' : '<span style="color: #dc3545;">비활성화됨</span>'; ?></p>
                <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">설정 변경은 최고관리자만 가능합니다</p>
            </div>
            <?php endif; ?>
            
            <!-- 접을 수 있는 상세 정보 -->
            <div id="settingInfo" style="display: none; margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #6c757d;">
                <h4 style="margin: 0 0 10px 0; color: #495057;">📋 기능 비교</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <h5 style="color: #28a745; margin: 0 0 8px 0;">✨ 고급 IP 차단 기능</h5>
                        <ul style="margin: 0; color: #495057; font-size: 14px; padding-left: 18px;">
                            <li>CIDR 대역 차단 (예: 192.168.1.0/24)</li>
                            <li>임시 차단 (자동 해제)</li>
                            <li>자동 차단 (로그인 실패, 스팸 감지)</li>
                            <li>화이트리스트 기능</li>
                            <li>관리자 계정 예외 처리</li>
                            <li>상세한 차단 로그</li>
                        </ul>
                    </div>
                    <div>
                        <h5 style="color: #6c757d; margin: 0 0 8px 0;">🔒 gnuboard5 기본 차단</h5>
                        <ul style="margin: 0; color: #495057; font-size: 14px; padding-left: 18px;">
                            <li>단순 IP 차단</li>
                            <li>영구 차단만 지원</li>
                            <li>수동 관리만 가능</li>
                            <li>기본적인 로그</li>
                        </ul>
                    </div>
                </div>
                <p style="margin: 15px 0 0 0; color: #6c757d; font-size: 13px;">
                    💡 <strong>권장:</strong> 고급 기능이 필요하면 "고급 IP 차단 기능 사용"을 체크하세요. 두 기능이 동시에 작동하지 않도록 자동 조정됩니다.
                </p>
            </div>
        </div>
    </div>

    <!-- 0-1. 화이트리스트 관리 -->
    <div class="dashboard-section">
        <div class="section-header">
            🛡️ IP 화이트리스트 (<?php echo $whitelist_count; ?>개)
        </div>
        <div class="section-content">
            <div style="background: #e7f3ff; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #007bff;">
                <p style="margin: 0; color: #0056b3; font-size: 14px;">
                    <strong>🛡️ 화이트리스트:</strong> 등록된 IP는 모든 차단에서 예외 처리됩니다. 관리자 계정은 자동으로 예외 처리되므로 별도 등록 불필요.
                </p>
            </div>
            
            <?php if ($is_admin == 'super'): ?>
            <form method="post" class="form-row" style="margin-bottom: 20px;">
                <input type="hidden" name="action" value="add_whitelist">
                <div class="form-group">
                    <label>IP 주소 또는 CIDR</label>
                    <input type="text" name="whitelist_ip" placeholder="예: 192.168.1.100 또는 192.168.1.0/24" required>
                </div>
                <div class="form-group">
                    <label>메모</label>
                    <input type="text" name="whitelist_memo" placeholder="관리자 IP, 서버 IP 등">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-success">화이트리스트 추가</button>
                </div>
            </form>
            <?php endif; ?>
            
            <!-- 화이트리스트 목록 -->
            <?php if ($whitelist_count > 0): ?>
            <table class="block-table">
                <thead>
                    <tr>
                        <th>IP/CIDR</th>
                        <th>메모</th>
                        <th>등록일</th>
                        <?php if ($is_admin == 'super'): ?>
                        <th>관리</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    sql_data_seek($whitelist_result, 0); // 결과셋을 처음으로 되돌림
                    while ($row = sql_fetch_array($whitelist_result)):
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($row['sw_ip']); ?></strong>
                            <?php if (strpos($row['sw_ip'], '/') !== false): ?>
                            <span style="font-size: 12px; color: #666;">(대역)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['sw_memo']); ?></td>
                        <td><?php echo substr($row['sw_datetime'], 2, 14); ?></td>
                        <?php if ($is_admin == 'super'): ?>
                        <td>
                            <button onclick="confirmDeleteWhitelist(<?php echo $row['sw_id']; ?>)" class="btn btn-sm btn-danger">삭제</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; color: #666; padding: 20px;">등록된 화이트리스트가 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 1. 통계 현황 -->
    <div class="dashboard-section">
        <div class="section-header">
            📊 차단 현황
            <?php if ($is_admin == 'super'): ?>
            <button onclick="confirmTableDelete()" class="btn btn-danger btn-sm">IP 차단 관리 기능 제거</button>
            <?php endif; ?>
        </div>
        <div class="section-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($block_stats['total_blocks']); ?>개</div>
                    <div class="stat-label">활성 차단 규칙</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($block_stats['manual_blocks']); ?>개</div>
                    <div class="stat-label">수동 차단</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($block_stats['auto_blocks']); ?>개</div>
                    <div class="stat-label">자동 차단</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($block_stats['today_blocks']); ?>개</div>
                    <div class="stat-label">오늘 추가된 차단</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($block_stats['total_hits']); ?>회</div>
                    <div class="stat-label">총 차단 적중</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_admin == 'super'): ?>
    <!-- 2. 새 차단 추가 -->
    <div class="dashboard-section">
        <div class="section-header">
            ➕ 새 IP 차단 추가
        </div>
        <div class="section-content">
            <form method="post">
                <input type="hidden" name="action" value="add_block">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>IP 주소 또는 CIDR</label>
                        <input type="text" name="ip" placeholder="예: 192.168.1.100 또는 192.168.1.0/24" required>
                    </div>
                    <div class="form-group">
                        <label>차단 기간</label>
                        <select name="duration" onchange="toggleEndDateTime()">
                            <option value="permanent">영구</option>
                            <option value="temporary">임시</option>
                        </select>
                    </div>
                    <div class="form-group end-datetime-group">
                        <label>종료 일시</label>
                        <input type="datetime-local" name="end_datetime">
                    </div>
                    <div class="form-group">
                        <label>차단 사유</label>
                        <input type="text" name="reason" placeholder="스팸, 무차별 로그인 시도 등" required>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">차단 추가</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 3. 검색 및 목록 -->
    <div class="dashboard-section">
        <div class="section-header">
            🔍 차단 목록
        </div>
        <div class="section-content">
            <form method="get" class="search-filters">
                <div class="form-group">
                    <label>IP 검색</label>
                    <input type="text" name="search_ip" value="<?php echo htmlspecialchars($search_ip); ?>" placeholder="IP 주소 입력">
                </div>
                <div class="form-group">
                    <label>차단 유형</label>
                    <select name="filter_type">
                        <option value="">전체</option>
                        <option value="manual" <?php echo $filter_type == 'manual' ? 'selected' : ''; ?>>수동</option>
                        <option value="auto_login" <?php echo $filter_type == 'auto_login' ? 'selected' : ''; ?>>로그인 제한</option>
                        <option value="auto_spam" <?php echo $filter_type == 'auto_spam' ? 'selected' : ''; ?>>스팸 차단</option>
                        <option value="auto_abuse" <?php echo $filter_type == 'auto_abuse' ? 'selected' : ''; ?>>악성 행위</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>상태</label>
                    <select name="filter_status">
                        <option value="">전체</option>
                        <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>활성</option>
                        <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>비활성</option>
                        <option value="expired" <?php echo $filter_status == 'expired' ? 'selected' : ''; ?>>만료</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">검색</button>
                    <a href="./security_block.php" class="btn btn-warning">초기화</a>
                </div>
            </form>

            <!-- 일괄 처리 폼 -->
            <form id="bulkForm" method="post">
                <input type="hidden" name="action" id="bulkAction">
                <input type="hidden" name="bulk_status" id="bulkStatus">
                
                <?php if ($is_admin == 'super'): ?>
                <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
                    <button type="button" onclick="selectAll()" class="btn btn-sm btn-warning">전체 선택</button>
                    <button type="button" onclick="selectNone()" class="btn btn-sm btn-warning">선택 해제</button>
                    <button type="button" onclick="bulkDelete()" class="btn btn-sm btn-danger">선택 삭제</button>
                    <button type="button" onclick="bulkToggle('active')" class="btn btn-sm btn-success">선택 활성화</button>
                    <button type="button" onclick="bulkToggle('inactive')" class="btn btn-sm btn-secondary">선택 비활성화</button>
                </div>
                <?php endif; ?>
                
                <!-- 차단 목록 테이블 -->
                <table class="block-table">
                    <thead>
                        <tr>
                            <?php if ($is_admin == 'super'): ?>
                            <th style="width: 40px;">
                                <input type="checkbox" id="checkAll" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <?php endif; ?>
                            <th>IP/CIDR</th>
                        <th>차단 사유</th>
                        <th>유형</th>
                        <th>기간</th>
                        <th>적중</th>
                        <th>상태</th>
                        <th>등록일</th>
                        <?php if ($is_admin == 'super'): ?>
                        <th>관리</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = sql_fetch_array($list_result)): ?>
                    <tr>
                        <?php if ($is_admin == 'super'): ?>
                        <td>
                            <input type="checkbox" name="selected_ids[]" value="<?php echo $row['sb_id']; ?>" class="row-checkbox">
                        </td>
                        <?php endif; ?>
                        <td>
                            <strong><?php echo htmlspecialchars($row['sb_ip']); ?></strong>
                            <?php if (strpos($row['sb_ip'], '/') !== false): ?>
                            <span style="font-size: 12px; color: #666;">(대역)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['sb_reason']); ?></td>
                        <td>
                            <span class="block-type-<?php echo $row['sb_block_type'] == 'manual' ? 'manual' : 'auto'; ?>">
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
                                영구
                            <?php else: ?>
                                임시<br>
                                <small><?php echo $row['sb_end_datetime']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($row['sb_hit_count']); ?>회</td>
                        <td>
                            <span class="status-<?php echo $row['sb_status']; ?>">
                                <?php 
                                switch($row['sb_status']) {
                                    case 'active': echo '활성'; break;
                                    case 'inactive': echo '비활성'; break;
                                    case 'expired': echo '만료'; break;
                                }
                                ?>
                            </span>
                        </td>
                        <td><?php echo substr($row['sb_datetime'], 2, 14); ?></td>
                        <?php if ($is_admin == 'super'): ?>
                        <td>
                            <button onclick="toggleStatus(<?php echo $row['sb_id']; ?>, '<?php echo $row['sb_status']; ?>')" 
                                    class="btn btn-sm <?php echo $row['sb_status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                <?php echo $row['sb_status'] == 'active' ? '비활성' : '활성'; ?>
                            </button>
                            <button onclick="confirmDelete(<?php echo $row['sb_id']; ?>)" class="btn btn-sm btn-danger">삭제</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </form>

            <!-- 페이징 -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $query_string = $_SERVER['QUERY_STRING'];
                $query_string = preg_replace('/&?page=\d+/', '', $query_string);
                $query_string = $query_string ? '&' . $query_string : '';
                
                for ($i = 1; $i <= $total_pages; $i++):
                ?>
                <a href="?page=<?php echo $i . $query_string; ?>" 
                   class="<?php echo $i == $page ? 'current' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
require_once './admin.tail.php';
?>