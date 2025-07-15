<?php
$sub_menu = '950100';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '보안설정 HOME';
require_once './admin.head.php';

// 보안 통계 데이터 조회
function get_security_stats() {
    global $g5;
    
    $stats = array();
    
    // 차단된 스팸 시도 (예: 차단된 회원 수)
    $sql = "SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_intercept_date != ''";
    $result = sql_fetch($sql);
    $stats['blocked_spam'] = $result['cnt'];
    
    // 차단된 공격 시도 (예: 실패한 로그인 시도 - 가상 데이터)
    $stats['blocked_attacks'] = 47; // 실제 구현시 로그 테이블에서 조회
    
    // 블랙리스트 IP (가상 데이터)
    $stats['blacklist_ips'] = 23; // 실제 구현시 IP 차단 테이블에서 조회
    
    // 오늘 차단된 스팸
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_intercept_date LIKE '{$today}%'";
    $result = sql_fetch($sql);
    $stats['today_blocked_spam'] = $result['cnt'];
    
    // 오늘 차단된 공격시도 (가상 데이터)
    $stats['today_blocked_attacks'] = 12; // 실제 구현시 로그 테이블에서 조회
    
    return $stats;
}

// 시스템 정보 조회
function get_system_info() {
    global $g5;
    
    $info = array();
    $info['plugin_status'] = '정상 작동중';
    $info['plugin_last_update'] = '2025년 08월 14일';
    $info['plugin_version'] = 'v1.0.0';
    $info['gnuboard_last_update'] = '2025년 06월 10일';
    $info['gnuboard_version'] = 'v15.2.0';
    
    return $info;
}

// 최근 로그 조회 (가상 데이터)
function get_recent_logs() {
    $logs = array(
        array(
            'time' => '2025.08.15 14:24:10',
            'ip' => '192.168.1.100',
            'action' => '스팸 댓글 시도',
            'status' => '차단됨'
        ),
        array(
            'time' => '2025.08.15 13:15:33',
            'ip' => '8.8.4.4',
            'action' => '무차별 로그인 시도',
            'status' => '차단됨'
        ),
        array(
            'time' => '2025.08.15 12:45:22',
            'ip' => '203.123.45.67',
            'action' => 'SQL 인젝션 시도',
            'status' => '차단됨'
        ),
        array(
            'time' => '2025.08.15 11:30:15',
            'ip' => '172.16.0.50',
            'action' => '스팸 게시글 작성',
            'status' => '차단됨'
        ),
        array(
            'time' => '2025.08.15 10:22:44',
            'ip' => '10.0.0.100',
            'action' => '파일 업로드 공격',
            'status' => '차단됨'
        )
    );
    
    return $logs;
}

$security_stats = get_security_stats();
$system_info = get_system_info();
$recent_logs = get_recent_logs();
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
}

.section-content {
    padding: 20px;
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
    color: #dc3545;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #666;
}

.system-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.info-label {
    font-weight: bold;
    color: #333;
}

.info-value {
    color: #666;
}

.status-normal {
    color: #28a745;
    font-weight: bold;
}

.logs-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.logs-table th,
.logs-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.logs-table th {
    background: #f8f9fa;
    font-weight: bold;
    color: #333;
}

.logs-table tr:hover {
    background: #f8f9fa;
}

.status-blocked {
    color: #dc3545;
    font-weight: bold;
}

.dashboard-title {
    color: #333;
    margin-bottom: 20px;
    font-size: 24px;
    font-weight: bold;
}
</style>

<div class="security-dashboard">
    <h1 class="dashboard-title">보안설정 대시보드</h1>
    
    <!-- 1. 현황판 -->
    <div class="dashboard-section">
        <div class="section-header">
            📊 현황판
        </div>
        <div class="section-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($security_stats['blocked_spam']); ?>건</div>
                    <div class="stat-label">차단된 스팸 시도</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($security_stats['blocked_attacks']); ?>건</div>
                    <div class="stat-label">차단된 공격 시도</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($security_stats['blacklist_ips']); ?>건</div>
                    <div class="stat-label">블랙리스트 IP</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($security_stats['today_blocked_spam']); ?>건</div>
                    <div class="stat-label">오늘 차단된 스팸</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($security_stats['today_blocked_attacks']); ?>건</div>
                    <div class="stat-label">오늘 차단된 공격시도</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. 시스템 상태 -->
    <div class="dashboard-section">
        <div class="section-header">
            ⚙️ 시스템 상태
        </div>
        <div class="section-content">
            <div class="system-info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">시스템 상태</span>
                        <span class="info-value status-normal">정상</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">플러그인 상태</span>
                        <span class="info-value status-normal"><?php echo $system_info['plugin_status']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">플러그인 마지막 업데이트</span>
                        <span class="info-value"><?php echo $system_info['plugin_last_update']; ?></span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">플러그인 버전</span>
                        <span class="info-value"><?php echo $system_info['plugin_version']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">그누보드 마지막 업데이트</span>
                        <span class="info-value"><?php echo $system_info['gnuboard_last_update']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">그누보드 버전</span>
                        <span class="info-value"><?php echo $system_info['gnuboard_version']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. 최근 로그 -->
    <div class="dashboard-section">
        <div class="section-header">
            📋 최근 로그
        </div>
        <div class="section-content">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>시간</th>
                        <th>IP 주소</th>
                        <th>동작</th>
                        <th>상태</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                    <tr>
                        <td><?php echo $log['time']; ?></td>
                        <td><?php echo $log['ip']; ?></td>
                        <td><?php echo $log['action']; ?></td>
                        <td><span class="status-blocked"><?php echo $log['status']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once './admin.tail.php';
?>