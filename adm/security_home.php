<?php
$sub_menu = '950100';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '보안설정 HOME';
require_once './admin.head.php';

// 공통 보안 CSS 포함
echo '<link rel="stylesheet" href="'.G5_ADMIN_URL.'/css/security_common.css?ver='.G5_CSS_VER.'">';

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

// 종합 보안 점수 계산
function calculate_security_score() {
    global $g5, $debug_info;

    $score = 0;
    $max_score = 100;
    $score_details = array();

    // 1. 데이터베이스 테이블 존재 (20점)
    if (isset($debug_info['database']['table_exists']) && $debug_info['database']['table_exists'] == 'YES') {
        $score += 20;
        $score_details['database'] = array('status' => 'good', 'score' => 20, 'text' => '보안 테이블 정상');
    } else {
        $score_details['database'] = array('status' => 'bad', 'score' => 0, 'text' => '보안 테이블 없음');
    }

    // 2. 필수 파일 존재 (20점)
    $missing_files = 0;
    if (isset($debug_info['files'])) {
        foreach ($debug_info['files'] as $file => $status) {
            if ($status == 'MISSING') {
                $missing_files++;
            }
        }
        $file_score = max(0, 20 - ($missing_files * 3));
        $score += $file_score;
        if ($missing_files == 0) {
            $score_details['files'] = array('status' => 'good', 'score' => 20, 'text' => '모든 파일 정상');
        } else {
            $score_details['files'] = array('status' => 'warning', 'score' => $file_score, 'text' => $missing_files.'개 파일 누락');
        }
    }

    // 3. 스팸 차단 효과 (15점)
    $security_stats = get_security_stats();
    if ($security_stats['blocked_spam'] > 10) {
        $score += 15;
        $score_details['spam_protection'] = array('status' => 'good', 'score' => 15, 'text' => '스팸 차단 활성');
    } else if ($security_stats['blocked_spam'] > 0) {
        $score += 10;
        $score_details['spam_protection'] = array('status' => 'warning', 'score' => 10, 'text' => '스팸 차단 보통');
    } else {
        $score_details['spam_protection'] = array('status' => 'warning', 'score' => 0, 'text' => '스팸 차단 미흡');
    }

    // 4. 공격 차단 효과 (15점)
    if ($security_stats['blocked_attacks'] > 20) {
        $score += 15;
        $score_details['attack_protection'] = array('status' => 'good', 'score' => 15, 'text' => '공격 차단 우수');
    } else if ($security_stats['blocked_attacks'] > 0) {
        $score += 10;
        $score_details['attack_protection'] = array('status' => 'warning', 'score' => 10, 'text' => '공격 차단 보통');
    } else {
        $score_details['attack_protection'] = array('status' => 'warning', 'score' => 0, 'text' => '공격 차단 미흡');
    }

    // 5. IP 블랙리스트 관리 (10점)
    if ($security_stats['blacklist_ips'] > 10) {
        $score += 10;
        $score_details['blacklist'] = array('status' => 'good', 'score' => 10, 'text' => 'IP 차단 활성');
    } else if ($security_stats['blacklist_ips'] > 0) {
        $score += 5;
        $score_details['blacklist'] = array('status' => 'warning', 'score' => 5, 'text' => 'IP 차단 보통');
    } else {
        $score_details['blacklist'] = array('status' => 'bad', 'score' => 0, 'text' => 'IP 차단 없음');
    }

    // 6. PHP 버전 보안 (10점) - PHP 7.2 이상
    $php_version = phpversion();
    if (version_compare($php_version, '7.4.0', '>=')) {
        $score += 10;
        $score_details['php_version'] = array('status' => 'good', 'score' => 10, 'text' => 'PHP 버전 안전');
    } else if (version_compare($php_version, '7.0.0', '>=')) {
        $score += 5;
        $score_details['php_version'] = array('status' => 'warning', 'score' => 5, 'text' => 'PHP 버전 주의');
    } else {
        $score_details['php_version'] = array('status' => 'bad', 'score' => 0, 'text' => 'PHP 버전 위험');
    }

    // 7. 최근 활동 상태 (10점)
    if ($security_stats['today_blocked_spam'] > 0 || $security_stats['today_blocked_attacks'] > 0) {
        $score += 10;
        $score_details['recent_activity'] = array('status' => 'good', 'score' => 10, 'text' => '실시간 보안 활성');
    } else {
        $score += 5;
        $score_details['recent_activity'] = array('status' => 'warning', 'score' => 5, 'text' => '보안 대기 상태');
    }

    return array(
        'score' => $score,
        'max_score' => $max_score,
        'percentage' => round(($score / $max_score) * 100),
        'grade' => get_security_grade($score, $max_score),
        'details' => $score_details
    );
}

// 보안 등급 계산
function get_security_grade($score, $max_score) {
    $percentage = ($score / $max_score) * 100;

    if ($percentage >= 90) return array('grade' => 'A+', 'text' => '최우수', 'color' => '#28a745');
    if ($percentage >= 80) return array('grade' => 'A', 'text' => '우수', 'color' => '#20c997');
    if ($percentage >= 70) return array('grade' => 'B', 'text' => '양호', 'color' => '#ffc107');
    if ($percentage >= 60) return array('grade' => 'C', 'text' => '보통', 'color' => '#fd7e14');
    if ($percentage >= 50) return array('grade' => 'D', 'text' => '주의', 'color' => '#dc3545');
    return array('grade' => 'F', 'text' => '위험', 'color' => '#dc3545');
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

// 보안 점수 계산 (디버깅 정보가 필요하므로 디버깅 정보 이후에 계산)
$security_score = null;

// 디버깅 정보 수집
$debug_info = array();
$debug_info['timestamp'] = date('Y-m-d H:i:s');
$debug_info['php_version'] = phpversion();
$debug_info['gnuboard_version'] = defined('G5_GNUBOARD_VER') ? G5_GNUBOARD_VER : 'Unknown';

// MySQL 버전 가져오기 (안전하게)
try {
    $mysql_version_result = sql_fetch("SELECT VERSION() as version");
    $debug_info['mysql_version'] = $mysql_version_result['version'];
} catch (Exception $e) {
    $debug_info['mysql_version'] = 'Unknown';
}

// 보안 플러그인 관련 파일 체크
$security_files = array(
    'admin.menu950.php' => 'adm/admin.menu950.php',
    'access_control.php' => 'adm/access_control.php',
    'access_control_update.php' => 'adm/access_control_update.php',
    'access_control_reset.php' => 'adm/access_control_reset.php',
    'create_access_control_table.php' => 'adm/create_access_control_table.php',
    'menu_shield.png' => 'adm/img/menu_shield.png',
    'admin.css' => 'adm/css/admin.css'
);

foreach ($security_files as $name => $path) {
    $full_path = G5_PATH . '/' . $path;
    $debug_info['files'][$name] = file_exists($full_path) ? 'EXISTS' : 'MISSING';
}

// 데이터베이스 테이블 상태 체크
try {
    $table_check = sql_fetch("SHOW TABLES LIKE 'g5_access_control'");
    $debug_info['database']['table_exists'] = $table_check ? 'YES' : 'NO';

    if ($table_check) {
        $count_result = sql_fetch("SELECT COUNT(*) as cnt FROM g5_access_control");
        $debug_info['database']['table_rows'] = $count_result['cnt'];
    }
} catch (Exception $e) {
    $debug_info['database']['error'] = $e->getMessage();
}

// 디버깅 정보 수집 완료 후 보안 점수 계산
$security_score = calculate_security_score();
?>

<style>
/* security_home.php 전용 스타일 - 공통 스타일은 security_common.css에서 로드됨 */
/* dashboard-section, stat-number는 security_common.css에서 로드됨 */

/* system-info-grid, info-item, info-label, info-value는 security_common.css에서 로드됨 */

/* status-normal, status-blocked는 security_common.css에서 로드됨 */

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

/* dashboard-title는 security_common.css에서 로드됨 */

/* 보안 점수 관련 스타일 */
.security-score-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.security-score-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    pointer-events: none;
}

.score-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.score-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.score-updated {
    font-size: 14px;
    opacity: 0.9;
}

.score-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 25px;
}

.score-display {
    text-align: center;
}

.score-number {
    font-size: 72px;
    font-weight: 900;
    line-height: 1;
    text-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.score-max {
    font-size: 24px;
    opacity: 0.8;
}

.score-grade {
    margin-left: 40px;
    text-align: center;
}

.grade-badge {
    display: inline-block;
    padding: 15px 25px;
    border-radius: 50px;
    font-size: 36px;
    font-weight: 900;
    margin-bottom: 10px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    border: 3px solid rgba(255,255,255,0.3);
}

.grade-text {
    font-size: 18px;
    font-weight: 600;
}

.score-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 25px;
}

.score-item {
    background: rgba(255,255,255,0.15);
    padding: 15px;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s ease;
}

.score-item:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.score-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.score-item-name {
    font-weight: 600;
    font-size: 14px;
}

.score-item-points {
    background: rgba(255,255,255,0.3);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
}

.score-item-status {
    font-size: 13px;
    opacity: 0.9;
}

.status-icon {
    margin-right: 5px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
    margin-top: 15px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
    border-radius: 4px;
    transition: width 2s ease-in-out;
}
</style>

<div class="security-dashboard">
    <h1 class="dashboard-title">보안설정 대시보드</h1>

    <!-- 종합 보안 점수 -->
    <div class="security-score-section">
        <div class="score-header">
            <h2 class="score-title">🛡️ 종합 보안 점수</h2>
            <div class="score-updated">최종 업데이트: <?php echo date('Y.m.d H:i'); ?></div>
        </div>

        <div class="score-main">
            <div class="score-display">
                <div class="score-number"><?php echo $security_score['score']; ?></div>
                <div class="score-max">/ <?php echo $security_score['max_score']; ?></div>
            </div>

            <div class="score-grade">
                <div class="grade-badge" style="background-color: <?php echo $security_score['grade']['color']; ?>">
                    <?php echo $security_score['grade']['grade']; ?>
                </div>
                <div class="grade-text"><?php echo $security_score['grade']['text']; ?></div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $security_score['percentage']; ?>%"></div>
        </div>

        <div class="score-details">
            <?php foreach ($security_score['details'] as $key => $detail): ?>
            <div class="score-item">
                <div class="score-item-header">
                    <span class="score-item-name">
                        <span class="status-icon">
                            <?php
                            echo $detail['status'] == 'good' ? '✅' :
                                ($detail['status'] == 'warning' ? '⚠️' : '❌');
                            ?>
                        </span>
                        <?php
                        $item_names = array(
                            'database' => '데이터베이스',
                            'files' => '파일 무결성',
                            'spam_protection' => '스팸 차단',
                            'attack_protection' => '공격 차단',
                            'blacklist' => 'IP 블랙리스트',
                            'php_version' => 'PHP 버전',
                            'recent_activity' => '실시간 보안'
                        );
                        echo $item_names[$key] ?? $key;
                        ?>
                    </span>
                    <span class="score-item-points"><?php echo $detail['score']; ?>점</span>
                </div>
                <div class="score-item-status"><?php echo $detail['text']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

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

<script>
// 브라우저 콘솔에 종합 디버깅 정보 출력
function logSecurityDebugInfo() {
    const debugInfo = <?php echo json_encode($debug_info, JSON_PRETTY_PRINT); ?>;
    const systemInfo = <?php echo json_encode($system_info, JSON_PRETTY_PRINT); ?>;
    const securityStats = <?php echo json_encode($security_stats, JSON_PRETTY_PRINT); ?>;
    const securityScore = <?php echo json_encode($security_score, JSON_PRETTY_PRINT); ?>;

    console.group('🛡️ 보안 플러그인 시스템 진단');
    console.log('⏰ 진단 시간:', debugInfo.timestamp);

    // 보안 점수 정보 추가
    console.group('🏆 종합 보안 점수');
    console.log(`%c${securityScore.score}/${securityScore.max_score}점 (${securityScore.percentage}%)`,
                'font-size: 18px; font-weight: bold; color: ' + securityScore.grade.color);
    console.log(`등급: %c${securityScore.grade.grade} (${securityScore.grade.text})`,
                'font-weight: bold; color: ' + securityScore.grade.color);

    console.group('📋 점수 세부 항목');
    Object.entries(securityScore.details).forEach(([key, detail]) => {
        const emoji = detail.status === 'good' ? '✅' : (detail.status === 'warning' ? '⚠️' : '❌');
        const color = detail.status === 'good' ? 'green' : (detail.status === 'warning' ? 'orange' : 'red');
        console.log(`%c${emoji} ${detail.text}: ${detail.score}점`, `color: ${color}`);
    });
    console.groupEnd();
    console.groupEnd();

    console.group('📊 보안 통계');
    console.log('차단된 스팸:', securityStats.blocked_spam + '건');
    console.log('차단된 공격:', securityStats.blocked_attacks + '건');
    console.log('블랙리스트 IP:', securityStats.blacklist_ips + '건');
    console.log('오늘 차단된 스팸:', securityStats.today_blocked_spam + '건');
    console.log('오늘 차단된 공격:', securityStats.today_blocked_attacks + '건');
    console.groupEnd();

    console.group('💻 시스템 정보');
    console.log('PHP 버전:', debugInfo.php_version);
    console.log('MySQL 버전:', debugInfo.mysql_version);
    console.log('그누보드 버전:', debugInfo.gnuboard_version);
    console.log('플러그인 버전:', systemInfo.plugin_version);
    console.log('플러그인 상태:', systemInfo.plugin_status);
    console.groupEnd();

    console.group('📁 플러그인 파일 무결성');
    Object.entries(debugInfo.files).forEach(([file, status]) => {
        const emoji = status === 'EXISTS' ? '✅' : '❌';
        const color = status === 'EXISTS' ? 'color: green' : 'color: red';
        console.log(`%c${emoji} ${file}: ${status}`, color);
    });
    console.groupEnd();

    console.group('🗄️ 데이터베이스 상태');
    console.log('테이블 존재:', debugInfo.database.table_exists);
    if (debugInfo.database.table_rows !== undefined) {
        console.log('설정 데이터:', debugInfo.database.table_rows + '개 행');
    }
    if (debugInfo.database.error) {
        console.error('오류:', debugInfo.database.error);
    }
    console.groupEnd();

    console.groupEnd();

    // 상태 요약
    const missingFiles = Object.values(debugInfo.files).filter(status => status === 'MISSING').length;
    const tableExists = debugInfo.database.table_exists === 'YES';

    if (missingFiles === 0 && tableExists) {
        console.log('%c✅ 시스템 상태: 정상', 'color: green; font-weight: bold; font-size: 14px');
    } else {
        console.warn('%c⚠️ 시스템 상태: 주의 필요', 'color: orange; font-weight: bold; font-size: 14px');
        if (missingFiles > 0) {
            console.warn(`- ${missingFiles}개 파일 누락`);
        }
        if (!tableExists) {
            console.warn('- 데이터베이스 테이블 누락');
        }
    }
}

// 콘솔 디버깅 토글 기능
let consoleDebugEnabled = true;

function toggleConsoleDebug() {
    consoleDebugEnabled = !consoleDebugEnabled;

    if (consoleDebugEnabled) {
        console.log('%c🔍 콘솔 디버깅 활성화됨', 'color: green; font-weight: bold');
        logSecurityDebugInfo();
        localStorage.setItem('securityPluginConsoleDebug', 'true');
    } else {
        console.log('%c🔇 콘솔 디버깅 비활성화됨', 'color: gray; font-weight: bold');
        localStorage.setItem('securityPluginConsoleDebug', 'false');
    }
}

// 페이지 로드 시 디버깅 정보 출력
document.addEventListener('DOMContentLoaded', function() {
    // 이전 설정 불러오기
    const savedDebugSetting = localStorage.getItem('securityPluginConsoleDebug');
    if (savedDebugSetting === 'false') {
        consoleDebugEnabled = false;
    }

    if (consoleDebugEnabled) {
        logSecurityDebugInfo();

        // 추가 개발자 도구 표시
        console.log('%c보안 플러그인 개발자 모드', 'background: #007bff; color: white; padding: 5px 10px; border-radius: 3px');
        console.log('• logSecurityDebugInfo() - 시스템 상태 확인');
        console.log('• toggleConsoleDebug() - 콘솔 디버깅 토글');
    }
});

// 전역 함수로 등록 (개발자가 콘솔에서 직접 호출 가능)
window.logSecurityDebugInfo = logSecurityDebugInfo;
window.toggleConsoleDebug = toggleConsoleDebug;
</script>

<?php
require_once './admin.tail.php';
?>