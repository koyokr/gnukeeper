<?php
$sub_menu = '950300';
require_once './_common.php';
auth_check_menu($auth, $sub_menu, 'r');
$g5['title'] = '차단관리';


function check_security_tables_exist() {
    $tables = ['security_ip_block', 'security_ip_log', 'security_ip_whitelist', 'security_config'];
    foreach ($tables as $table) {
        if (!sql_num_rows(sql_query("SHOW TABLES LIKE '" . G5_TABLE_PREFIX . $table . "'"))) {
            return false;
        }
    }
    return true;
}

function get_security_table_names() {
    return [
        G5_TABLE_PREFIX . 'security_ip_block' => 'IP 차단 관리 테이블',
        G5_TABLE_PREFIX . 'security_ip_log' => 'IP 차단 로그 테이블',
        G5_TABLE_PREFIX . 'security_ip_whitelist' => 'IP 허용 목록 테이블',
        G5_TABLE_PREFIX . 'security_config' => '보안 설정 테이블'
    ];
}

function ip_to_long($ip) {
    $long = ip2long($ip);
    return $long === false ? 0 : sprintf('%u', $long);
}

function is_valid_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function is_valid_cidr($cidr) {
    if (strpos($cidr, '/') === false) return false;
    list($ip, $mask) = explode('/', $cidr, 2);
    return is_valid_ip($ip) && ($mask = (int)$mask) >= 0 && $mask <= 32;
}

function parse_cidr($cidr) {
    list($ip, $mask) = explode('/', $cidr, 2);
    $mask = (int)$mask;
    $start_ip = ip_to_long($ip) & ((0xFFFFFFFF << (32 - $mask)) & 0xFFFFFFFF);
    return [$start_ip, $start_ip | ((1 << (32 - $mask)) - 1)];
}

function get_current_admin_ip() {
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ??
           trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]) ?:
           $_SERVER['HTTP_X_REAL_IP'] ??
           $_SERVER['REMOTE_ADDR'];
}

function is_ip_blocked($ip) {
    $ip_long = ip_to_long($ip);
    $result = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block
                        WHERE sb_status = 'active' AND {$ip_long} BETWEEN sb_start_ip AND sb_end_ip");
    return $result['cnt'] > 0;
}

function get_security_config($key = null) {
    if ($key) {
        $row = sql_fetch("SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = '" . sql_escape_string($key) . "'");
        return $row ? $row['sc_value'] : null;
    }

    $config = [];
    $result = sql_query("SELECT sc_key, sc_value FROM " . G5_TABLE_PREFIX . "security_config");
    while ($row = sql_fetch_array($result)) {
        $config[$row['sc_key']] = $row['sc_value'];
    }
    return $config;
}

function get_block_stats() {
    $prefix = G5_TABLE_PREFIX;
    $queries = [
        'total_blocks' => "SELECT COUNT(*) as cnt FROM {$prefix}security_ip_block WHERE sb_status = 'active'",
        'manual_blocks' => "SELECT COUNT(*) as cnt FROM {$prefix}security_ip_block WHERE sb_status = 'active' AND sb_block_type = 'manual'",
        'auto_blocks' => "SELECT COUNT(*) as cnt FROM {$prefix}security_ip_block WHERE sb_status = 'active' AND sb_block_type != 'manual'",
        'today_blocks' => "SELECT COUNT(*) as cnt FROM {$prefix}security_ip_block WHERE DATE(sb_datetime) = '" . date('Y-m-d') . "'",
        'total_hits' => "SELECT SUM(sb_hit_count) as total FROM {$prefix}security_ip_block WHERE sb_status = 'active'"
    ];

    $stats = [];
    foreach ($queries as $key => $query) {
        $result = sql_fetch($query);
        $stats[$key] = $key === 'total_hits' ? ($result['total'] ?: 0) : $result['cnt'];
    }
    return $stats;
}

function check_dangerous_whitelist() {
    $dangerous_cidrs = [
        '10.0.0.0/8',      // 클래스 A 사설망 전체
        '172.16.0.0/12',   // 클래스 B 사설망 전체
        '192.168.0.0/16'   // 클래스 C 사설망 전체
    ];
    $warnings = [];

    $result = sql_query("SELECT sw_ip FROM " . G5_TABLE_PREFIX . "security_ip_whitelist");
    while ($row = sql_fetch_array($result)) {
        $ip = $row['sw_ip'];
        
        if (strpos($ip, '/') !== false && is_valid_cidr($ip)) {
            list($ip_start, $ip_end) = parse_cidr($ip);
            
            foreach ($dangerous_cidrs as $dangerous_cidr) {
                list($dangerous_start, $dangerous_end) = parse_cidr($dangerous_cidr);
                
                if ($ip_start <= $dangerous_end && $ip_end >= $dangerous_start) {
                    $warnings[] = $ip;
                    break;
                }
            }
        }
    }
    return $warnings;
}

function create_tables() {
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

// 초기화 및 자동 테이블 설치
$tables_exist = check_security_tables_exist();
if (!$tables_exist) {
    $install_result = create_tables();
    if ($install_result) {
        $tables_exist = true;
    }
}
$current_admin_ip = get_current_admin_ip();

// 데이터 조회 - 테이블이 없으면 자동 설치되므로 항상 진행
{
    $security_config = get_security_config();
    $ip_block_enabled = $security_config['ip_block_enabled'] ?? '1';

    // 보안 경고 체크
    $security_warnings = [];

    if (is_ip_blocked($current_admin_ip)) {
        $security_warnings[] = [
            'type' => 'danger',
            'icon' => '🚨',
            'title' => '긴급: 현재 관리자 IP가 차단되어 있습니다!',
            'message' => '현재 접속 IP(' . $current_admin_ip . ')가 차단 목록에 포함되어 있습니다.'
        ];
    }

    // 예외 IP 조회
    $whitelist_result = sql_query("SELECT * FROM " . G5_TABLE_PREFIX . "security_ip_whitelist ORDER BY sw_datetime DESC");
    $whitelist_count = sql_num_rows($whitelist_result);

    // 검색 및 필터링 (POST만 처리)
    $where = [];
    $search_ip = trim($_POST['search_ip'] ?? '');
    $filter_type = $_POST['filter_type'] ?? '';
    $filter_status = $_POST['filter_status'] ?? '';
    $sort_by = $_POST['sort_by'] ?? 'sb_datetime';
    $sort_order = $_POST['sort_order'] ?? 'desc';

    if ($search_ip) {
        // CIDR 형식인지 확인
        if (strpos($search_ip, '/') !== false && is_valid_cidr($search_ip)) {
            // CIDR 범위 검색: 입력한 CIDR 범위와 겹치는 모든 차단 규칙 찾기
            list($search_start, $search_end) = parse_cidr($search_ip);
            // 인덱스 활용을 위해 범위 조건을 우선 배치
            $where[] = "(sb_start_ip <= {$search_end} AND sb_end_ip >= {$search_start})";
        } else {
            // 일반 IP 또는 문자열 검색
            if (is_valid_ip($search_ip)) {
                // 유효한 IP인 경우: 인덱스를 활용한 범위 검색을 우선 시도
                $ip_long = ip_to_long($search_ip);
                $where[] = "({$ip_long} BETWEEN sb_start_ip AND sb_end_ip OR sb_ip LIKE '%" . sql_escape_string($search_ip) . "%')";
            } else {
                // 문자열 검색 (부분 매칭)
                $where[] = "sb_ip LIKE '%" . sql_escape_string($search_ip) . "%'";
            }
        }
    }
    if ($filter_type) $where[] = "sb_block_type = '" . sql_escape_string($filter_type) . "'";
    if ($filter_status) $where[] = "sb_status = '" . sql_escape_string($filter_status) . "'";

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // 페이징 (POST만 처리)
    $page = max(1, (int)($_POST['page'] ?? 1));
    $per_page = 25;
    $offset = ($page - 1) * $per_page;

    // 전체 개수 및 목록 조회 (인덱스 활용 최적화)
    $total_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block {$where_sql}")['cnt'];
    $total_pages = ceil($total_count / $per_page);

    // 차단 목록 전체 통계 (검색 조건 무관)
    $block_list_stats = sql_fetch("SELECT
        COUNT(*) as total_blocks,
        COUNT(CASE WHEN sb_status = 'active' THEN 1 END) as active_blocks
        FROM " . G5_TABLE_PREFIX . "security_ip_block");

    // 정렬 처리
    $sort_columns = [
        'datetime' => 'sb_datetime',
        'ip' => 'sb_ip',
        'type' => 'sb_block_type',
        'status' => 'sb_status',
        'hit_count' => 'sb_hit_count'
    ];

    $sort_column = $sort_columns[$sort_by] ?? 'sb_datetime';
    $sort_direction = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
    $order_clause = "ORDER BY {$sort_column} {$sort_direction}";

    $list_result = sql_query("SELECT * FROM " . G5_TABLE_PREFIX . "security_ip_block {$where_sql} {$order_clause} LIMIT {$offset}, {$per_page}");

    $block_stats = get_block_stats();

}

// AJAX 요청 처리
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    // AJAX 요청인 경우 테이블 부분만 반환    
    // 테이블 HTML만 출력
    ?>
    <!-- 일괄 처리 폼 -->
    <form id="bulkForm" method="post">
        <input type="hidden" name="action" id="bulkAction">
        <input type="hidden" name="bulk_status" id="bulkStatus">

        <!-- 검색 결과 정보 -->
        <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; font-size: 14px; color: #495057;">
            총 <?php echo number_format($total_count); ?>개 검색됨
            <?php if ($total_pages > 1): ?>
            (<?php echo $page; ?>/<?php echo $total_pages; ?> 페이지, 페이지당 <?php echo $per_page; ?>개)
            <?php endif; ?>
        </div>

        <?php if ($is_admin == 'super'): ?>
        <div class="actions gap-sm" style="margin-bottom: 20px; flex-wrap: wrap;">
            <button type="button" onclick="document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = true)" class="btn btn-sm btn-warning">전체 선택</button>
            <button type="button" onclick="document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false)" class="btn btn-sm btn-warning">선택 해제</button>
            <button type="button" onclick="bulkAction('delete')" class="btn btn-sm btn-danger">선택 삭제</button>
            <button type="button" onclick="bulkAction('active')" class="btn btn-sm btn-success">선택 활성화</button>
            <button type="button" onclick="bulkAction('inactive')" class="btn btn-sm btn-secondary">선택 비활성화</button>
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
                </tr>
            </thead>
            <tbody>
                <?php if ($list_result && sql_num_rows($list_result) > 0): ?>
                    <?php while ($row = sql_fetch_array($list_result)): ?>
                    <tr>
                        <?php if ($is_admin == 'super'): ?>
                        <td><input type="checkbox" name="selected_ids[]" value="<?php echo $row['sb_id']; ?>" class="row-checkbox"></td>
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
                                임시<br><small><?php echo $row['sb_end_datetime']; ?></small>
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
                                    default: echo $row['sb_status'];
                                }
                                ?>
                            </span>
                        </td>
                        <td><?php echo substr($row['sb_datetime'], 2, 14); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $is_admin == 'super' ? '8' : '7'; ?>" style="text-align: center; padding: 40px; color: #666;">
                            검색 결과가 없습니다.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <!-- 페이징 -->
    <div class="pagination">
        <?php if ($total_pages == 1): ?>
        <span class="page-info">1/1</span>
        <?php else: ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="#" onclick="loadPage(<?php echo $i; ?>); return false;"
               class="<?php echo $i == $page ? 'current' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <span class="page-info">(<?php echo $page; ?>/<?php echo $total_pages; ?>)</span>
        <?php endif; ?>
    </div>
    <?php
    exit; // AJAX 응답 후 종료
}

// AJAX 요청이 아닐 때만 헤더 포함
if (!isset($_POST['ajax'])) {
    require_once './admin.head.php';
}

// 액션 처리가 필요한 경우 update 파일로 처리
if (isset($_POST['action'])) {
    require_once './security_block_update.php';
    exit; // update 파일에서 처리 완료
}

// 데이터는 이미 위에서 로드됨
?>

<style>
.security-dashboard { margin: 20px 0; }
.dashboard-section { margin-bottom: 30px; background: #fff; border: 1px solid #ddd; border-radius: 5px; overflow: hidden; }
.section-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #ddd; font-weight: bold; font-size: 16px; color: #333; display: flex; justify-content: space-between; align-items: center; }
.section-content { padding: 20px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 5px; padding: 15px; text-align: center; }
.stat-number { font-size: 24px; font-weight: bold; color: #dc3545; margin-bottom: 5px; }
.stat-label { font-size: 14px; color: #666; }
.install-notice { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
.install-notice h3 { color: #856404; margin-bottom: 15px; }
.install-notice p { color: #856404; margin-bottom: 15px; }
.table-preview { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin: 15px 0; }
.table-preview h4 { color: #495057; margin-bottom: 10px; font-size: 16px; }
.table-preview ul { margin: 0; padding-left: 20px; }
.table-preview li { color: #6c757d; margin-bottom: 5px; }
.form-group { flex: 1; display: flex; flex-direction: column; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; font-size: 14px; }
.form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; height: 36px; box-sizing: border-box; }
.btn { padding: 8px 16px !important; border: none; border-radius: 4px; cursor: pointer; font-size: 14px !important; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; vertical-align: top; line-height: 20px !important; height: 36px !important; box-sizing: border-box; white-space: nowrap; transition: all 0.2s ease; }
.btn-primary { background: #007bff; color: white; border: 1px solid #007bff; }
.btn-primary:hover { background: #0056b3; border-color: #0056b3; }
.btn-danger { background: #dc3545; color: white; border: 1px solid #dc3545; }
.btn-danger:hover { background: #c82333; border-color: #c82333; }
.btn-warning { background: #ffc107; color: #212529; border: 1px solid #ffc107; }
.btn-warning:hover { background: #e0a800; border-color: #e0a800; }
.btn-success { background: #28a745; color: white; border: 1px solid #28a745; }
.btn-success:hover { background: #218838; border-color: #218838; }
.btn-secondary { background: #6c757d; color: white; border: 1px solid #6c757d; }
.btn-secondary:hover { background: #5a6268; border-color: #5a6268; }
.btn-sm { padding: 4px 12px !important; font-size: 12px !important; height: 28px !important; line-height: 16px !important; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.section-layout { display: flex; justify-content: space-between; align-items: center; gap: 20px; }
.section-layout .content { flex: 1; min-width: 0; }
.section-layout .actions { flex-shrink: 0; display: flex; gap: 8px; align-items: center; }
.perfect-form-layout { display: grid; grid-template-columns: 2fr 4fr 200px; gap: 15px; align-items: end; margin-bottom: 20px; }
@media (max-width: 768px) { .perfect-form-layout { grid-template-columns: 1fr; gap: 15px; } .button-column { width: 100% !important; min-width: auto !important; max-width: none !important; justify-content: center; } }
.ip-input-column { min-width: 200px; flex: 1; }
.middle-inputs-column { display: flex; gap: 15px; flex: 1; min-width: 0; }
.middle-inputs-column .form-group { flex: 1; min-width: 120px; }
.button-column { width: 200px !important; min-width: 200px !important; max-width: 200px !important; display: flex; gap: 8px; justify-content: center; align-items: flex-end; padding-top: 24px; }
.button-column .btn { flex: none !important; width: 96px !important; min-width: 96px !important; max-width: 96px !important; }
.button-column .btn:only-child { width: 200px !important; min-width: 200px !important; max-width: 200px !important; }
.block-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.block-table th, .block-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
.block-table th { background: #f8f9fa; font-weight: bold; color: #333; }
.block-table tr:hover { background: #f8f9fa; }
.status-active { color: #dc3545; font-weight: bold; }
.status-inactive { color: #6c757d; }
.block-type-manual { background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
.block-type-auto { background: #fff3e0; color: #f57c00; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
.pagination { text-align: center; margin-top: 20px; }
.pagination a { display: inline-block; padding: 8px 12px; margin: 0 2px; border: 1px solid #ddd; text-decoration: none; color: #333; }
.pagination a.current { background: #007bff; color: white; border-color: #007bff; }
.pagination .page-info { display: inline-block; padding: 8px 12px; margin: 0 2px; color: #666; font-size: 14px; }
.dashboard-title { color: #333; margin-bottom: 20px; font-size: 24px; font-weight: bold; }
.switch-container { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.simple-switch { position: relative; width: 80px; height: 40px; background: #e2e8f0; border-radius: 8px; cursor: pointer; border: 2px solid #cbd5e0; transition: all 0.3s ease; }
.simple-switch:hover { border-color: #a0aec0; transform: scale(1.02); }
.simple-switch.on { background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%); border-color: #68d391; }
.simple-switch-handle { position: absolute; width: 36px; height: 36px; background: white; border-radius: 6px; top: 1px; left: 1px; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 4px 12px rgba(0,0,0,0.15); border: 2px solid #cbd5e0; }
.simple-switch.on .simple-switch-handle { transform: translateX(40px); border-color: #38a169; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); }
.level-labels { display: flex; justify-content: space-between; width: 80px; margin-top: 8px; font-size: 11px; color: #718096; font-weight: 600; }
.dual-labels { width: 80px; }
.plugin-info { background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
.plugin-info h4 { color: #0056b3; margin-bottom: 10px; }
.plugin-info ul { margin: 0; padding-left: 20px; color: #495057; }
.current-prefix-info { background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin: 15px 0; }
.current-prefix-info h4 { color: #0056b3; margin-bottom: 10px; font-size: 16px; }
.current-prefix-info p { margin: 0; color: #495057; }
.current-prefix-info code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; border: 1px solid #dee2e6; }
.info-box { margin-bottom: 20px; border-radius: 5px; padding: 15px; font-size: 14px; line-height: 1.4; }
.info-box.danger { background: #f8d7da; border-left: 5px solid #dc3545; color: #721c24; }
.info-box.warning { background: #fff3cd; border-left: 5px solid #ffc107; color: #856404; }
.info-box.success { background: #e8f5e8; border-left: 4px solid #28a745; color: #155724; }
.info-box.info { background: #e7f3ff; border: 1px solid #b3d9ff; border-left: 4px solid #007bff; color: #0056b3; }
.info-box h4 { margin: 0 0 10px 0; font-size: 16px; font-weight: bold; }
.info-box p { margin: 0; }
.info-box .icon { font-size: 18px; margin-right: 8px; }

/* 제어 버튼 섹션 */
.control-buttons-section {
    background: #ffffff;
    border-radius: 16px;
    margin-bottom: 30px;
    padding: 24px 32px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
}

.control-group {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.control-btn {
    padding: 12px 20px;
    border: 2px solid;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    text-decoration: none;
}

.control-btn.primary {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    border-color: #38a169;
    color: white;
}

.control-btn.primary:hover {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(56, 161, 105, 0.4);
}

.control-btn.secondary {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border-color: #3182ce;
    color: white;
}

.control-btn.secondary:hover {
    background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(49, 130, 206, 0.4);
}

.control-btn.danger {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
    border-color: #fc8181;
    color: #c53030;
}

.control-btn.danger:hover {
    background: linear-gradient(135deg, #fc8181 0%, #f56565 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(252, 129, 129, 0.4);
}

.control-btn:active {
    transform: translateY(0);
}

.control-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 12px 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px solid #e9ecef;
}

/* 반응형 레이아웃 */
@media (max-width: 768px) {
    .control-buttons-section > div {
        flex-direction: column !important;
        gap: 15px !important;
    }
    
    .gnuboard-notice {
        order: 2;
    }
    
    .control-group {
        order: 1;
        justify-content: center !important;
        flex-wrap: wrap;
    }
    
    .control-btn {
        font-size: 14px !important;
        padding: 10px 16px !important;
    }
}
</style>

<script>
function toggleEndDateTime() {
    const duration = document.querySelector('select[name="duration"]').value;
    const endDateTime = document.getElementById('endDateTime');

    if (duration === 'temporary') {
        endDateTime.disabled = false;
        endDateTime.required = true;
    } else {
        endDateTime.disabled = true;
        endDateTime.required = false;
        endDateTime.value = '';
    }
}

function toggleMainSwitchRealtime() {
    const buttonElement = event.target;
    const isCurrentlyEnabled = buttonElement.classList.contains('primary');
    const newValue = isCurrentlyEnabled ? '0' : '1';

    // 버튼 비활성화
    buttonElement.disabled = true;
    const originalText = buttonElement.textContent;
    buttonElement.textContent = isCurrentlyEnabled ? '비활성화 중...' : '활성화 중...';

    fetch('./security_block_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save_config&ip_block_enabled=${newValue}&ajax=1`
    })
    .then(response => response.text())
    .then(data => {
        console.log('응답:', data); // 디버그 로그
        if (data.startsWith('error:')) {
            alert(data.substring(6));
            buttonElement.textContent = originalText;
            buttonElement.disabled = false;
            return;
        }

        // 성공 시 페이지 새로고침
        alert(isCurrentlyEnabled ? '고급 IP 차단 기능이 비활성화되었습니다.' : '고급 IP 차단 기능이 활성화되었습니다.');
        location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('설정 저장 중 오류가 발생했습니다.');
        buttonElement.textContent = originalText;
        buttonElement.disabled = false;
    });
}

// 유틸리티 함수들
const utils = {
    confirmDelete: (id) => {
        if (confirm('정말 삭제하시겠습니까?')) {
            utils.submitForm('delete_block', {sb_id: id}, './security_block_delete.php');
        }
    },

    toggleStatus: (id, currentStatus) => {
        utils.submitForm('toggle_status', {sb_id: id, status: currentStatus});
    },

    submitAction: (action) => {
        utils.submitForm(action);
    },

    submitForm: (action, data = {}, endpoint = './security_block_update.php') => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = endpoint;
        form.innerHTML = `<input type="hidden" name="action" value="${action}">` +
                        Object.entries(data).map(([k,v]) => `<input type="hidden" name="${k}" value="${v}">`).join('');
        document.body.appendChild(form);
        form.submit();
    },

    confirmTableDelete: () => {
        const tableList = [
            '<?php echo G5_TABLE_PREFIX; ?>security_ip_block',
            '<?php echo G5_TABLE_PREFIX; ?>security_ip_log',
            '<?php echo G5_TABLE_PREFIX; ?>security_ip_whitelist',
            '<?php echo G5_TABLE_PREFIX; ?>security_config'
        ];

        if (confirm('IP 차단 관리 기능과 다음 테이블들이 완전히 제거됩니다:\n\n' +
                   tableList.join('\n') +
                   '\n\n⚠️ 모든 차단 데이터와 설정이 삭제됩니다.\n정말 제거하시겠습니까?')) {
            utils.submitForm('uninstall_tables', {}, './security_block_delete.php');
        }
    },

    confirmDeleteWhitelist: (id) => {
        if (confirm('예외 IP에서 삭제하시겠습니까?')) {
            utils.submitForm('delete_whitelist', {sw_id: id}, './security_block_delete.php');
        }
    }
};

// IP 검증 및 경고 시스템
function checkCurrentIPBlock() {
    const ipInput = document.getElementById('blockIpInput');
    const warning = document.getElementById('ipWarning');
    const addBtn = document.getElementById('addBlockBtn');
    const currentAdminIP = '<?php echo $current_admin_ip; ?>';

    const inputIP = ipInput.value.trim();

    if (!inputIP) {
        warning.style.display = 'none';
        addBtn.disabled = false;
        addBtn.classList.remove('btn-secondary');
        addBtn.classList.add('btn-primary');
        addBtn.textContent = '차단 추가';
        return;
    }

    let isCurrentIP = false;

    if (inputIP.indexOf('/') > -1) {
        if (isValidCIDR(inputIP)) {
            isCurrentIP = isIPInCIDR(currentAdminIP, inputIP);
        }
    } else {
        isCurrentIP = (inputIP === currentAdminIP);
    }

    if (isCurrentIP) {
        warning.style.display = 'block';
        addBtn.disabled = true;
        addBtn.classList.remove('btn-primary');
        addBtn.classList.add('btn-secondary');
        addBtn.textContent = '차단 불가';
    } else {
        warning.style.display = 'none';
        addBtn.disabled = false;
        addBtn.classList.remove('btn-secondary');
        addBtn.classList.add('btn-primary');
        addBtn.textContent = '차단 추가';
    }
}

// IP 유틸리티
function isValidIP(ip) {
    return /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(ip);
}

function isValidCIDR(cidr) {
    const parts = cidr.split('/');
    if (parts.length !== 2) return false;
    const mask = parseInt(parts[1]);
    return isValidIP(parts[0]) && mask >= 0 && mask <= 32;
}

function ipToLong(ip) {
    const parts = ip.split('.');
    return (parseInt(parts[0]) << 24) + (parseInt(parts[1]) << 16) + (parseInt(parts[2]) << 8) + parseInt(parts[3]);
}

function isIPInCIDR(ip, cidr) {
    const parts = cidr.split('/');
    const mask = parseInt(parts[1]);
    const ipLong = ipToLong(ip);
    const cidrLong = ipToLong(parts[0]);
    const maskLong = (0xFFFFFFFF << (32 - mask)) & 0xFFFFFFFF;
    return (ipLong & maskLong) === (cidrLong & maskLong);
}

// 일괄 처리
function toggleAllCheckboxes(masterCheckbox) {
    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
        checkbox.checked = masterCheckbox.checked;
    });
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(checkbox => checkbox.value);
}

// 통합된 일괄 액션 함수
function bulkAction(action) {
    const selectedIds = getSelectedIds();
    if (selectedIds.length === 0) {
        const actionText = action === 'delete' ? '삭제할' : '상태를 변경할';
        alert(actionText + ' 항목을 선택해주세요.');
        return;
    }

    let confirmMessage, endpoint, formData;
    
    if (action === 'delete') {
        confirmMessage = `선택된 ${selectedIds.length}개 항목을 삭제하시겠습니까?`;
        endpoint = './security_block_delete.php';
        formData = new FormData();
        formData.append('action', 'bulk_delete');
        formData.append('ajax', '1');
        selectedIds.forEach(id => formData.append('selected_ids[]', id));
    } else {
        const statusText = action === 'active' ? '활성화' : '비활성화';
        confirmMessage = `선택된 ${selectedIds.length}개 항목을 ${statusText} 하시겠습니까?`;
        endpoint = './security_block_update.php';
        formData = new FormData();
        formData.append('action', 'bulk_toggle');
        formData.append('bulk_status', action);
        formData.append('ajax', '1');
        selectedIds.forEach(id => formData.append('selected_ids[]', id));
    }

    if (confirm(confirmMessage)) {
        fetch(endpoint, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            console.log(`${action} 응답:`, data);
            if (data.startsWith('success:')) {
                alert(data.substring(8));
                refreshTable();
            } else if (data.startsWith('error:')) {
                alert(data.substring(6));
            } else {
                console.error('예상하지 못한 응답:', data);
                alert('처리 중 오류가 발생했습니다: ' + data.substring(0, 100));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('처리 중 오류가 발생했습니다.');
        });
    }
}

// 테이블 갱신 함수
function refreshTable() {
    const form = document.getElementById('searchForm');
    const formData = new FormData(form);
    
    fetch('./security_block.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        updateTableContent(html);
        
        // 체크박스 선택 해제
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        const masterCheckbox = document.getElementById('checkAll');
        if (masterCheckbox) masterCheckbox.checked = false;
    })
    .catch(error => {
        console.error('테이블 갱신 오류:', error);
        alert('테이블 갱신 중 오류가 발생했습니다.');
    });
}

// AJAX 검색
function searchBlocks(event) {
    event.preventDefault();

    const form = document.getElementById('searchForm');
    const formData = new FormData(form);

    // 로딩 표시
    const submitBtn = document.querySelector('button[onclick="searchBlocks(event)"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '검색중...';
    submitBtn.disabled = true;

    fetch('./security_block.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        console.log('검색 응답 받음:', html.length > 0 ? '데이터 있음' : '빈 응답'); // 디버깅
        updateTableContent(html);
    })
    .catch(error => {
        console.error('검색 오류:', error);
        alert('검색 중 오류가 발생했습니다.');
    })
    .finally(() => {
        // 로딩 상태 복원
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function resetSearch() {
    const form = document.getElementById('searchForm');
    form.reset();

    // 로딩 표시
    const resetBtn = document.querySelector('button[onclick="resetSearch()"]');
    const originalText = resetBtn.textContent;
    resetBtn.textContent = '초기화중...';
    resetBtn.disabled = true;

    fetch('./security_block_list.php', {
        method: 'POST',
        body: new FormData() // 빈 폼 데이터
    })
    .then(response => response.text())
    .then(html => {
        updateTableContent(html);
    })
    .catch(error => {
        console.error('초기화 오류:', error);
        alert('초기화 중 오류가 발생했습니다.');
    })
    .finally(() => {
        // 로딩 상태 복원
        resetBtn.textContent = originalText;
        resetBtn.disabled = false;
    });
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

// 페이징 AJAX 처리
function loadPage(pageNum) {
    const form = document.getElementById('searchForm');
    const formData = new FormData(form);
    formData.set('page', pageNum);

    fetch('./security_block.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        updateTableContent(html);
    })
    .catch(error => {
        console.error('페이지 로딩 오류:', error);
        alert('페이지 로딩 중 오류가 발생했습니다.');
    });
}

// 테이블 콘텐츠 업데이트 함수
function updateTableContent(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    // 새로운 테이블과 페이징 찾기
    const newBulkForm = doc.querySelector('#bulkForm');
    const newPagination = doc.querySelector('.pagination');

    // 기존 테이블과 페이징 제거
    const oldBulkForm = document.querySelector('#bulkForm');
    const oldPagination = document.querySelector('.pagination');

    if (oldBulkForm) oldBulkForm.remove();
    if (oldPagination) oldPagination.remove();

    // 새로운 콘텐츠를 검색 폼 뒤에 추가
    const searchForm = document.getElementById('searchForm');
    if (newBulkForm) {
        searchForm.parentNode.appendChild(newBulkForm);
    }
    if (newPagination) {
        searchForm.parentNode.appendChild(newPagination);
    }
}

// 페이징 링크에 AJAX 이벤트 추가 (이제 onclick으로 처리되므로 불필요)
function addPaginationEvents() {
    // 페이징 링크는 이제 onclick으로 직접 처리되므로 별도 이벤트 추가 불필요
}

// DOM 로드 완료 시 초기 설정
document.addEventListener('DOMContentLoaded', function() {
    // 검색 폼에서 Enter 키 처리
    document.getElementById('searchForm').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchBlocks(e);
        }
    });
});

// 함수 별칭 (이전 버전과의 호환성)
const confirmDelete = utils.confirmDelete;
const toggleStatus = utils.toggleStatus;
const submitAction = utils.submitAction;
const confirmTableDelete = utils.confirmTableDelete;
const confirmDeleteWhitelist = utils.confirmDeleteWhitelist;
const bulkDelete = () => bulkAction('delete');
const bulkToggle = (status) => bulkAction(status);
</script>

<div class="security-dashboard">
    <div class="section-layout" style="margin-bottom: 20px;">
        <div class="content">
            <h1 class="dashboard-title" style="margin: 0;">IP 차단 관리</h1>
        </div>
    </div>

    <!-- 보안 경고 표시 -->
    <?php foreach ($security_warnings as $warning): ?>
    <div class="info-box <?php echo $warning['type']; ?>">
        <h4>
            <span class="icon"><?php echo $warning['icon']; ?></span>
            <?php echo htmlspecialchars($warning['title']); ?>
        </h4>
        <p><?php echo htmlspecialchars($warning['message']); ?></p>
    </div>
    <?php endforeach; ?>


    <!-- gnuboard 연동 안내 및 제어 버튼 -->
    <div class="control-buttons-section">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <!-- 왼쪽: gnuboard 연동 안내 -->
            <div class="gnuboard-notice" style="flex: 1;">
                <div style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 8px; padding: 16px;">
                    <h4 style="margin: 0 0 8px 0; color: #0056b3; font-size: 16px;">🔄 gnuboard5 기본 IP 차단 기능 연동</h4>
                    <p style="margin: 0; color: #495057; font-size: 14px; line-height: 1.4;">고급 IP 차단 기능이 활성화되면 gnuboard5의 기본 IP 차단 기능이 자동으로 비활성화됩니다. 고급 IP 차단 기능을 비활성화하면 기본 IP 차단 기능이 다시 활성화됩니다.</p>
                </div>
            </div>
            
            <!-- 오른쪽: 제어 버튼들 -->
            <div class="control-group" style="display: flex; gap: 12px; flex-shrink: 0;">
                <?php if ($is_admin == 'super'): ?>
                <button type="button" class="control-btn <?php echo $ip_block_enabled ? 'primary' : 'secondary'; ?>" 
                        onclick="toggleMainSwitchRealtime()">
                    <?php echo $ip_block_enabled ? '🛡️ 고급 IP 차단 기능 비활성화' : '🛡️ 고급 IP 차단 기능 활성화'; ?>
                </button>
                
                <button type="button" class="control-btn danger" onclick="confirmTableDelete()">
                    🗑️ 데이터 초기화
                </button>
                <?php else: ?>
                <div class="control-info">
                    <strong>고급 IP 차단:</strong> <?php echo $ip_block_enabled ? '<span style="color: #28a745;">활성화됨</span>' : '<span style="color: #dc3545;">비활성화됨</span>'; ?>
                    <small style="display: block; color: #666; margin-top: 4px;">설정 변경은 최고관리자만 가능합니다</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($ip_block_enabled): ?>
    <!-- 통계 현황 -->
    <div class="dashboard-section">
        <div class="section-header">📊 차단 현황</div>
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


    <!-- 검색 및 목록 -->
    <div class="dashboard-section">
        <div class="section-header">🔍 차단 IP 목록 (<?php echo number_format($block_list_stats['total_blocks']); ?>개)</div>
        <div class="section-content">
            <form id="searchForm" method="post" class="perfect-form-layout" onsubmit="return false;">
                <div class="ip-input-column">
                    <div class="form-group">
                        <label>IP/CIDR</label>
                        <input type="text" name="search_ip" value="<?php echo htmlspecialchars($search_ip); ?>" placeholder="예: 192.168.1.100 또는 192.168.0.0/24">
                    </div>
                </div>

                <div class="middle-inputs-column">
                    <div class="form-group">
                        <label>유형</label>
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
                        <label>정렬</label>
                        <select name="sort_by">
                            <option value="sb_ip" <?php echo $sort_by == 'sb_ip' ? 'selected' : ''; ?>>IP/CIDR</option>
                            <option value="sb_block_type" <?php echo $sort_by == 'sb_block_type' ? 'selected' : ''; ?>>유형</option>
                            <option value="sb_hit_count" <?php echo $sort_by == 'sb_hit_count' ? 'selected' : ''; ?>>적중</option>
                            <option value="sb_status" <?php echo $sort_by == 'sb_status' ? 'selected' : ''; ?>>상태</option>
                            <option value="sb_datetime" <?php echo $sort_by == 'sb_datetime' ? 'selected' : ''; ?>>등록일</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>순서</label>
                        <select name="sort_order">
                            <option value="desc" <?php echo $sort_order == 'desc' ? 'selected' : ''; ?>>내림차순</option>
                            <option value="asc" <?php echo $sort_order == 'asc' ? 'selected' : ''; ?>>오름차순</option>
                        </select>
                    </div>
                </div>

                <div class="button-column">
                    <button type="button" onclick="searchBlocks(event)" class="btn btn-primary">검색</button>
                    <button type="button" onclick="resetSearch()" class="btn btn-warning">초기화</button>
                </div>
            </form>

            <?php if ($tables_exist && isset($list_result)): ?>
                <!-- 일괄 처리 폼 -->
                <form id="bulkForm" method="post">
                    <input type="hidden" name="action" id="bulkAction">
                    <input type="hidden" name="bulk_status" id="bulkStatus">

                    <!-- 검색 결과 정보 -->
                    <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; font-size: 14px; color: #495057;">
                        총 <?php echo number_format($total_count); ?>개 검색됨
                        <?php if ($total_pages > 1): ?>
                        (<?php echo $page; ?>/<?php echo $total_pages; ?> 페이지, 페이지당 <?php echo $per_page; ?>개)
                        <?php endif; ?>
                    </div>

                    <?php if ($is_admin == 'super'): ?>
                    <div class="actions gap-sm" style="margin-bottom: 20px; flex-wrap: wrap;">
                        <button type="button" onclick="document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = true)" class="btn btn-sm btn-warning">전체 선택</button>
                        <button type="button" onclick="document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false)" class="btn btn-sm btn-warning">선택 해제</button>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($list_result && sql_num_rows($list_result) > 0): ?>
                                <?php while ($row = sql_fetch_array($list_result)): ?>
                                <tr>
                                    <?php if ($is_admin == 'super'): ?>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?php echo $row['sb_id']; ?>" class="row-checkbox"></td>
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
                                            임시<br><small><?php echo $row['sb_end_datetime']; ?></small>
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
                                                default: echo $row['sb_status'];
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo substr($row['sb_datetime'], 2, 14); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $is_admin == 'super' ? '8' : '7'; ?>" style="text-align: center; padding: 40px; color: #666;">
                                        검색 결과가 없습니다.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>

                <!-- 페이징 -->
                <div class="pagination">
                    <?php if ($total_pages == 1): ?>
                    <span class="page-info">1/1</span>
                    <?php else: ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="#" onclick="loadPage(<?php echo $i; ?>); return false;"
                           class="<?php echo $i == $page ? 'current' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        <span class="page-info">(<?php echo $page; ?>/<?php echo $total_pages; ?>)</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <p style="text-align: center; color: #666; padding: 40px;">데이터를 불러올 수 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_admin == 'super'): ?>
    <!-- 수동 IP 차단 -->
    <div class="dashboard-section">
        <div class="section-header">➕ 차단 IP 추가 (수동)</div>
        <div class="section-content">
            <form method="post" action="./security_block_update.php" class="perfect-form-layout">
                <input type="hidden" name="action" value="add_block">

                <div class="ip-input-column">
                    <div class="form-group">
                        <label>IP/CIDR</label>
                        <input type="text" name="ip" id="blockIpInput" placeholder="예: 192.168.1.100 또는 192.168.1.0/24" required onkeyup="checkCurrentIPBlock()" onchange="checkCurrentIPBlock()">
                    </div>
                </div>

                <div class="middle-inputs-column">
                    <div class="form-group">
                        <label>메모</label>
                        <input type="text" name="reason" placeholder="스팸, 무차별 로그인 시도 등" required>
                    </div>
                    <div class="form-group">
                        <label>기간</label>
                        <select name="duration" onchange="toggleEndDateTime()">
                            <option value="permanent">영구</option>
                            <option value="temporary">임시</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>종료 일시</label>
                        <input type="datetime-local" name="end_datetime" id="endDateTime" disabled>
                    </div>
                </div>

                <div class="button-column">
                    <button type="submit" id="addBlockBtn" class="btn btn-primary">차단 추가</button>
                </div>
            </form>

            <!-- 현재 IP 차단 경고 -->
            <div id="ipWarning" style="display: none; margin-top: 15px; padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24; font-size: 14px;">
                ⚠️ <strong>경고:</strong> 현재 접속 중인 관리자 IP를 차단할 수 없습니다. 관리자 페이지 접속이 불가능해집니다.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 예외 IP 관리 -->
    <div class="dashboard-section">
        <div class="section-header">🛡️ 예외 IP 설정 (<?php echo $whitelist_count; ?>개)</div>
        <div class="section-content">
            <div class="info-box info">
                <p>등록된 IP는 모든 차단에서 예외 처리됩니다.</p>
            </div>

            <?php 
            $dangerous_whitelist = check_dangerous_whitelist();
            if (!empty($dangerous_whitelist)): 
            ?>
            <div class="info-box warning">
                <p><strong>⚠️ 너무 광범위한 예외 IP 발견:</strong> <?php echo implode(', ', $dangerous_whitelist); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($is_admin == 'super'): ?>
            <form method="post" action="./security_block_update.php" class="perfect-form-layout">
                <input type="hidden" name="action" value="add_whitelist">

                <div class="ip-input-column">
                    <div class="form-group">
                        <label>IP/CIDR</label>
                        <input type="text" name="whitelist_ip" value="<?php echo $current_admin_ip; ?>" required>
                    </div>
                </div>

                <div class="middle-inputs-column">
                    <div class="form-group">
                        <label>메모</label>
                        <input type="text" name="whitelist_memo" placeholder="현재 접속한 관리자 IP">
                    </div>
                </div>

                <div class="button-column">
                    <button type="submit" class="btn btn-success">예외 추가</button>
                </div>
            </form>
            <?php endif; ?>

            <!-- 예외 IP 목록 -->
            <?php if ($whitelist_count > 0): ?>
            <table class="block-table">
                <thead>
                    <tr>
                        <th>IP/CIDR</th>
                        <th>메모</th>
                        <th>등록일</th>
                        <?php if ($is_admin == 'super'): ?><th>관리</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = sql_fetch_array($whitelist_result)): ?>
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
            <p style="text-align: center; color: #666; padding: 20px;">등록된 예외 IP가 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php 
// AJAX 요청이 아닐 때만 푸터 포함
if (!isset($_POST['ajax'])) {
    require_once './admin.tail.php'; 
}
?>