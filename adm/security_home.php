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

    // 차단된 스팸 시도 (스팸 정규식 로그)
    $sql = "SELECT COUNT(*) as cnt FROM g5_security_regex_spam_log";
    $result = sql_fetch($sql);
    $stats['blocked_spam'] = $result['cnt'];

    // 차단된 공격 시도 (IP 차단 로그 + 로그인 실패)
    $sql = "SELECT COUNT(*) as cnt FROM g5_security_ip_log";
    $result = sql_fetch($sql);
    $login_fail_sql = "SELECT COUNT(*) as cnt FROM g5_security_login_fail";
    $login_fail_result = sql_fetch($login_fail_sql);
    $stats['blocked_attacks'] = $result['cnt'] + $login_fail_result['cnt'];

    // 블랙리스트 IP (실제 차단된 IP 수)
    $sql = "SELECT COUNT(*) as cnt FROM g5_security_ip_block";
    $result = sql_fetch($sql);
    $stats['blacklist_ips'] = $result['cnt'];

    // 오늘 차단된 스팸
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as cnt FROM g5_security_regex_spam_log WHERE DATE(srsl_datetime) = '{$today}'";
    $result = sql_fetch($sql);
    $stats['today_blocked_spam'] = $result['cnt'];

    // 오늘 차단된 공격시도
    $sql = "SELECT COUNT(*) as cnt FROM g5_security_ip_log WHERE DATE(sl_datetime) = '{$today}'";
    $result = sql_fetch($sql);
    $login_today_sql = "SELECT COUNT(*) as cnt FROM g5_security_login_fail WHERE DATE(slf_datetime) = '{$today}'";
    $login_today_result = sql_fetch($login_today_sql);
    $stats['today_blocked_attacks'] = $result['cnt'] + $login_today_result['cnt'];

    return $stats;
}

// 버전 비교 함수 (JavaScript에서도 사용)
function compare_versions($current, $latest) {
    if (!$latest) return 'unknown';
    
    $result = version_compare($current, $latest);
    if ($result < 0) return 'outdated';
    if ($result > 0) return 'newer';
    return 'latest';
}

// 시스템 정보 조회
function get_system_info() {
    $info = array();
    
    // 현재 버전 정보 (JavaScript가 사용할 수 있도록)
    $gk_current_version = defined('GK_VERSION') ? GK_VERSION : '0.0.0';
    $g5_current_version = defined('G5_GNUBOARD_VER') ? G5_GNUBOARD_VER : '0.0.0';
    $github_repo = defined('GK_GITHUB_REPO') ? GK_GITHUB_REPO : 'gnsehfvlr/gnuboard5_security';

    $info['plugin_status'] = '정상 작동중';
    $info['plugin_version'] = $gk_current_version;
    $info['gnuboard_version'] = $g5_current_version;
    $info['github_repo'] = $github_repo;

    return $info;
}

// 종합 보안 점수 계산
function calculate_security_score() {
    global $debug_info;

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
        foreach ($debug_info['files'] as $status) {
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

// 최근 로그 조회 (실제 데이터)
function get_recent_logs() {
    $logs = array();

    // IP 차단 로그
    $sql = "SELECT sl_ip, sl_datetime, sl_block_reason, sl_url 
            FROM g5_security_ip_log 
            ORDER BY sl_datetime DESC 
            LIMIT 3";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $logs[] = array(
            'time' => date('Y.m.d H:i:s', strtotime($row['sl_datetime'])),
            'ip' => $row['sl_ip'],
            'action' => $row['sl_block_reason'] ?: 'IP 차단',
            'status' => '차단됨'
        );
    }

    // 로그인 실패 로그
    $sql = "SELECT slf_ip, slf_datetime, slf_mb_id 
            FROM g5_security_login_fail 
            ORDER BY slf_datetime DESC 
            LIMIT 2";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $logs[] = array(
            'time' => date('Y.m.d H:i:s', strtotime($row['slf_datetime'])),
            'ip' => $row['slf_ip'],
            'action' => '로그인 실패 (ID: ' . htmlspecialchars($row['slf_mb_id']) . ')',
            'status' => '차단됨'
        );
    }

    // 스팸 정규식 로그
    $sql = "SELECT srsl_ip, srsl_datetime, srsl_matched_pattern 
            FROM g5_security_regex_spam_log 
            ORDER BY srsl_datetime DESC 
            LIMIT 2";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $logs[] = array(
            'time' => date('Y.m.d H:i:s', strtotime($row['srsl_datetime'])),
            'ip' => $row['srsl_ip'],
            'action' => '스팸 패턴 감지: ' . htmlspecialchars(substr($row['srsl_matched_pattern'], 0, 20)) . '...',
            'status' => '차단됨'
        );
    }

    // 시간 순으로 정렬
    usort($logs, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    // 최대 5개까지만 반환
    return array_slice($logs, 0, 5);
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

/* 버전 정보 카드 스타일 */
.version-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
    margin-bottom: 15px;
}

.version-card h4 {
    font-size: 16px;
    margin: 0 0 15px 0;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.version-icon {
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.version-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 10px;
    background: #f9fafb;
    border-radius: 6px;
}

.version-label {
    font-weight: 500;
    color: #6b7280;
    font-size: 14px;
}

.version-value {
    font-weight: 600;
    color: #111827;
    font-size: 14px;
}

.version-status {
    margin-top: 15px;
    padding: 10px;
    border-radius: 6px;
    text-align: center;
    font-weight: 500;
    font-size: 14px;
}

.status-latest {
    background: #d1fae5;
    color: #065f46;
}

.status-outdated {
    background: #fee2e2;
    color: #991b1b;
}

.status-unknown {
    background: #f3f4f6;
    color: #6b7280;
}

.status-newer {
    background: #dbeafe;
    color: #1e40af;
}

.update-button {
    display: block;
    width: 100%;
    margin-top: 10px;
    padding: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
}

.update-button:hover {
    opacity: 0.9;
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
            <!-- 버전 관리 카드들 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-top: 20px;">
                <!-- GnuKeeper 버전 카드 -->
                <div class="version-card">
                    <h4>
                        <div class="version-icon">GK</div>
                        그누키퍼
                    </h4>
                    
                    <div class="version-info-row">
                        <span class="version-label">현재 버전</span>
                        <span class="version-value"><?php echo $system_info['plugin_version']; ?></span>
                    </div>
                    
                    <div class="version-info-row">
                        <span class="version-label">최신 버전</span>
                        <span class="version-value" id="gk-latest-version">확인 중...</span>
                    </div>
                    
                    <div class="version-status status-unknown" id="gk-version-status">
                        버전 정보를 확인하는 중...
                    </div>
                </div>

                <!-- Gnuboard5 버전 카드 -->
                <div class="version-card">
                    <h4>
                        <div class="version-icon">G5</div>
                        그누보드5
                    </h4>
                    
                    <div class="version-info-row">
                        <span class="version-label">현재 버전</span>
                        <span class="version-value"><?php echo $system_info['gnuboard_version']; ?></span>
                    </div>
                    
                    <div class="version-info-row">
                        <span class="version-label">최신 버전</span>
                        <span class="version-value" id="g5-latest-version">확인 중...</span>
                    </div>
                    
                    <div class="version-status status-unknown" id="g5-version-status">
                        버전 정보를 확인하는 중...
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

// JavaScript 버전 비교 함수
function compareVersions(current, latest) {
    if (!latest) return 'unknown';
    
    // 버전 문자열을 숫자 배열로 변환
    const currentParts = current.split('.').map(Number);
    const latestParts = latest.split('.').map(Number);
    
    const maxLength = Math.max(currentParts.length, latestParts.length);
    
    for (let i = 0; i < maxLength; i++) {
        const currentPart = currentParts[i] || 0;
        const latestPart = latestParts[i] || 0;
        
        if (currentPart < latestPart) return 'outdated';
        if (currentPart > latestPart) return 'newer';
    }
    
    return 'latest';
}

// GitHub API에서 최신 버전 정보 가져오기 (클라이언트 사이드)
async function fetchLatestVersions() {
    const systemInfo = <?php echo json_encode($system_info); ?>;
    let gkStatus = 'unknown';
    let g5Status = 'unknown';
    
    try {
        // GnuKeeper 플러그인 버전 확인
        const gkResponse = await fetch(`https://api.github.com/repos/${systemInfo.github_repo}/releases/latest`);
        if (gkResponse.ok) {
            const gkData = await gkResponse.json();
            const gkLatestVersion = gkData.tag_name || null;
            
            document.getElementById('gk-latest-version').textContent = gkLatestVersion || '확인 불가';
            
            gkStatus = compareVersions(systemInfo.plugin_version, gkLatestVersion);
            updateVersionStatus('gk', gkStatus, systemInfo.github_repo);
        } else {
            document.getElementById('gk-latest-version').textContent = '확인 불가';
            updateVersionStatus('gk', 'unknown');
        }
        
        // Gnuboard5 버전 확인
        const g5Response = await fetch('https://api.github.com/repos/gnuboard/gnuboard5/releases/latest');
        if (g5Response.ok) {
            const g5Data = await g5Response.json();
            const g5LatestVersion = g5Data.tag_name ? g5Data.tag_name.replace(/^v/, '') : null;
            
            document.getElementById('g5-latest-version').textContent = g5LatestVersion || '확인 불가';
            
            g5Status = compareVersions(systemInfo.gnuboard_version, g5LatestVersion);
            updateVersionStatus('g5', g5Status);
        } else {
            document.getElementById('g5-latest-version').textContent = '확인 불가';
            updateVersionStatus('g5', 'unknown');
        }
        
    } catch (error) {
        console.error('Version check failed:', error);
        document.getElementById('gk-latest-version').textContent = '확인 실패';
        document.getElementById('g5-latest-version').textContent = '확인 실패';
        updateVersionStatus('gk', 'unknown');
        updateVersionStatus('g5', 'unknown');
    }
}

// 버전 상태 UI 업데이트
function updateVersionStatus(type, status, githubRepo = null) {
    const statusElement = document.getElementById(`${type}-version-status`);
    
    // 기존 업데이트 버튼 제거
    const existingButton = statusElement.parentNode.querySelector('.update-button');
    if (existingButton) {
        existingButton.remove();
    }
    
    statusElement.className = 'version-status';
    
    switch (status) {
        case 'latest':
            statusElement.classList.add('status-latest');
            statusElement.textContent = '✓ 최신 버전을 사용중입니다';
            break;
        case 'outdated':
            statusElement.classList.add('status-outdated');
            statusElement.textContent = '⚠ 새로운 버전이 있습니다';
            
            // 업데이트 버튼 추가
            const updateButton = document.createElement('a');
            updateButton.className = 'update-button';
            updateButton.target = '_blank';
            
            if (type === 'gk' && githubRepo) {
                updateButton.href = `https://github.com/${githubRepo}/releases/latest`;
                updateButton.textContent = '업데이트 다운로드';
            } else if (type === 'g5') {
                updateButton.href = 'https://sir.kr/g5_pds';
                updateButton.textContent = '업데이트 페이지로 이동';
            }
            
            statusElement.parentNode.appendChild(updateButton);
            break;
        case 'newer':
            statusElement.classList.add('status-newer');
            statusElement.textContent = 'ℹ 개발 버전을 사용중입니다';
            break;
        default:
            statusElement.classList.add('status-unknown');
            statusElement.textContent = '버전 정보를 확인할 수 없습니다';
            break;
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
        console.log('• fetchLatestVersions() - 최신 버전 확인');
    }
    
    // 클라이언트 사이드 버전 확인 실행
    fetchLatestVersions();
});

// 전역 함수로 등록 (개발자가 콘솔에서 직접 호출 가능)
window.logSecurityDebugInfo = logSecurityDebugInfo;
window.toggleConsoleDebug = toggleConsoleDebug;
window.fetchLatestVersions = fetchLatestVersions;
</script>

<?php
require_once './admin.tail.php';
?>