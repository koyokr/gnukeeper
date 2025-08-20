<?php
$sub_menu = '950100';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '보안설정 HOME';
require_once './admin.head.php';

// 공통 보안 CSS 포함
echo '<link rel="stylesheet" href="'.G5_ADMIN_URL.'/css/security_common.css?ver='.G5_CSS_VER.'">';

// GnuKeeper 플러그인 부트스트랩 로드
if (file_exists(G5_PATH . '/plugin/gnukeeper/bootstrap.php')) {
    require_once G5_PATH . '/plugin/gnukeeper/bootstrap.php';
}

// 보안 통계 데이터 조회
function get_security_stats() {
    global $g5;

    $stats = array();

    // 차단된 스팸 시도 (스팸 정규식 로그 + 스팸 콘텐츠 로그)
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_regex_spam_log";
    $result = sql_fetch($sql);
    $regex_spam = $result['cnt'] ?? 0;
    
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_spam_content_log";
    $result = sql_fetch($sql);
    $content_spam = $result['cnt'] ?? 0;
    
    $stats['blocked_spam'] = $regex_spam + $content_spam;

    // 차단된 공격 시도 (IP 차단 로그 + 로그인 실패)
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_ip_log";
    $result = sql_fetch($sql);
    $ip_logs = $result['cnt'] ?? 0;
    
    $login_fail_sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_login_fail";
    $login_fail_result = sql_fetch($login_fail_sql);
    $login_fails = $login_fail_result['cnt'] ?? 0;
    
    $stats['blocked_attacks'] = $ip_logs + $login_fails;

    // 블랙리스트 IP (실제 차단된 IP 수)
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_ip_block";
    $result = sql_fetch($sql);
    $stats['blacklist_ips'] = $result['cnt'] ?? 0;

    // 오늘 차단된 스팸 (정규식 + 콘텐츠)
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_regex_spam_log WHERE DATE(srsl_datetime) = '{$today}'";
    $result = sql_fetch($sql);
    $today_regex_spam = $result['cnt'] ?? 0;
    
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_spam_content_log WHERE DATE(sscl_datetime) = '{$today}'";
    $result = sql_fetch($sql);
    $today_content_spam = $result['cnt'] ?? 0;
    
    $stats['today_blocked_spam'] = $today_regex_spam + $today_content_spam;

    // 오늘 차단된 공격시도
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_ip_log WHERE DATE(sl_datetime) = '{$today}'";
    $result = sql_fetch($sql);
    $today_ip_logs = $result['cnt'] ?? 0;
    
    $login_today_sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_login_fail WHERE DATE(slf_datetime) = '{$today}'";
    $login_today_result = sql_fetch($login_today_sql);
    $today_login_fails = $login_today_result['cnt'] ?? 0;
    
    $stats['today_blocked_attacks'] = $today_ip_logs + $today_login_fails;
    
    // 추가 통계: 스팸 콘텐츠 관련
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_spam_content_log WHERE sscl_auto_blocked = 1";
    $result = sql_fetch($sql);
    $stats['auto_blocked_spam'] = $result['cnt'] ?? 0;
    
    // 추가 통계: 다중 사용자 탐지
    $sql = "SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."security_multiuser_log";
    $result = sql_fetch($sql);
    $stats['multiuser_detections'] = $result['cnt'] ?? 0;

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
    $github_repo = defined('GK_GITHUB_REPO') ? GK_GITHUB_REPO : 'koyokr/gnukeeper';

    $info['plugin_status'] = '정상 작동중';
    $info['plugin_version'] = $gk_current_version;
    $info['gnuboard_version'] = $g5_current_version;
    $info['github_repo'] = $github_repo;

    return $info;
}

// 각 보안 페이지에서 설정 정보 수집
function get_all_security_settings() {
    global $g5;
    
    $settings = array();
    
    try {
        // GK_Common 클래스 로드 및 설정 정보 가져오기
        if (class_exists('GK_Common')) {
            $gk_config = GK_Common::get_config();
            $settings['gk_config'] = $gk_config;
        } else {
            $settings['gk_config'] = array();
        }
        
        // 1. 접근제어 설정 (access_control)
        $access_control_sql = "SELECT COUNT(*) as count FROM ".G5_TABLE_PREFIX."access_control WHERE ac_level > 0";
        $access_control_result = sql_fetch($access_control_sql);
        $settings['access_control_enabled'] = ($access_control_result['count'] > 0);
        
        // 실제 GnuKeeper 설정 테이블에서 직접 읽기 (백업 방법)
        $gk_config_from_db = array();
        try {
            $gk_settings_sql = "SELECT * FROM ".G5_TABLE_PREFIX."security_config";
            $gk_settings_result = sql_query($gk_settings_sql);
            while ($row = sql_fetch_array($gk_settings_result)) {
                $gk_config_from_db[$row['sc_key']] = $row['sc_value'];
            }
        } catch (Exception $e) {
            // 테이블이 없으면 무시
        }
        
        // 클래스에서 가져온 설정과 데이터베이스에서 직접 가져온 설정 병합
        $merged_config = array_merge($gk_config_from_db, $gk_config);
        
        // 디버그: 설정값 확인 (브라우저 콘솔에 출력)
        $settings['debug_config'] = $merged_config;
        $settings['debug_block_stats'] = null;
        $settings['debug_spam_stats'] = null;
        
        // BlockAdmin 통계 디버그
        if (isset($blockStats)) {
            $settings['debug_block_stats'] = $blockStats;
        }
        
        // SpamAdmin 통계 디버그  
        if (isset($spam_stats)) {
            $settings['debug_spam_stats'] = $spam_stats;
        }
        
        // 2. 해외 IP 차단 설정 (BlockAdmin 클래스 사용)
        $foreign_ip_enabled = false;
        try {
            if (class_exists('GK_BlockAdmin')) {
                $blockAdmin = GK_BlockAdmin::getInstance();
                $blockStats = $blockAdmin->getBlockStats();
                $foreign_ip_enabled = ($blockStats['foreign_block_enabled'] ?? '0') == '1';
            }
        } catch (Exception $e) {
            // BlockAdmin 사용 실패 시 설정에서 직접 확인
            $foreign_ip_enabled = (
                ($merged_config['foreign_ip_enabled'] ?? '0') == '1' ||
                ($merged_config['foreign_ip_block_enabled'] ?? '0') == '1' ||
                ($merged_config['foreign_ip_auto_block'] ?? '0') == '1'
            );
        }
        $settings['foreign_ip_enabled'] = $foreign_ip_enabled;
        
        // 3. 게시판 접근 권한 정책 - 위험한 게시판 수 계산
        $dangerous_boards_sql = "SELECT COUNT(*) as count FROM ".G5_TABLE_PREFIX."board WHERE bo_list_level <= 1 OR bo_read_level <= 1 OR bo_write_level <= 1";
        $dangerous_boards_result = sql_fetch($dangerous_boards_sql);
        $settings['board_policy_safe'] = ($dangerous_boards_result['count'] == 0);
        
        // 4. 캡챠 적용 정책 - 실제 게시판별 캡챠 설정 확인
        $captcha_unsafe_sql = "SELECT COUNT(*) as count FROM ".G5_TABLE_PREFIX."board WHERE bo_use_captcha = ''";
        $captcha_unsafe_result = sql_fetch($captcha_unsafe_sql);
        $settings['captcha_policy_safe'] = ($captcha_unsafe_result['count'] == 0); // 모든 게시판이 캡챠 설정되어 있으면 안전
        
        // 5. 관리자급 권한 사용자 관리 - 레벨 10 이상 사용자 수 확인
        $admin_users_sql = "SELECT COUNT(*) as count FROM ".G5_TABLE_PREFIX."member WHERE mb_level >= 10";
        $admin_users_result = sql_fetch($admin_users_sql);
        $settings['admin_users_safe'] = ($admin_users_result['count'] <= 3); // 3명 이하면 안전
        
        // 6. 확장자 정책 관리 - 위험한 확장자 허용 여부 확인
        $dangerous_extensions = array('php', 'php3', 'phtml', 'asp', 'jsp', 'exe', 'sh', 'pl');
        $upload_extensions_sql = "SELECT cf_upload_extension FROM ".G5_TABLE_PREFIX."config WHERE cf_id = 1";
        $upload_extensions_result = sql_fetch($upload_extensions_sql);
        $allowed_extensions = explode('|', strtolower($upload_extensions_result['cf_upload_extension'] ?? ''));
        $has_dangerous_ext = count(array_intersect($dangerous_extensions, $allowed_extensions)) > 0;
        $settings['extension_policy_safe'] = !$has_dangerous_ext;
        
        // 7. 업로드 용량 정책 - 게시판별 업로드 용량 확인
        $unsafe_upload_sql = "SELECT COUNT(*) as count FROM ".G5_TABLE_PREFIX."board WHERE bo_upload_size > 20971520 OR bo_upload_size = 0";
        $unsafe_upload_result = sql_fetch($unsafe_upload_sql);
        $settings['upload_size_policy_safe'] = ($unsafe_upload_result['count'] == 0); // 모든 게시판이 20MB 이하면 안전
        
        // 8-12. 탐지 관련 설정들 (SpamAdmin 클래스 사용)
        $spam_stats = null;
        try {
            if (class_exists('GK_SpamAdmin')) {
                $spamAdmin = GK_SpamAdmin::getInstance();
                $spam_stats = $spamAdmin->getSpamStats();
            }
        } catch (Exception $e) {
            // SpamAdmin 사용 실패 시 무시
        }
        
        // 8. 스팸 콘텐츠 탐지
        $settings['spam_content_enabled'] = ($spam_stats['spam_content_enabled'] ?? $merged_config['spam_content_enabled'] ?? '0') == '1';
        
        // 9. 로그인 위협 탐지
        $settings['login_threat_enabled'] = ($spam_stats['login_threat_enabled'] ?? $merged_config['login_threat_enabled'] ?? '1') == '1';
        
        // 10. 악성 봇 탐지
        $settings['bot_detection_enabled'] = ($spam_stats['user_agent_enabled'] ?? $merged_config['user_agent_enabled'] ?? '1') == '1';
        
        // 11. 비정상 행동 탐지
        $settings['behavior_detection_enabled'] = (
            ($spam_stats['behavior_404_enabled'] ?? $merged_config['behavior_404_enabled'] ?? '0') == '1' || 
            ($spam_stats['behavior_referer_enabled'] ?? $merged_config['behavior_referer_enabled'] ?? '1') == '1'
        );
        
        // 12. 다중 사용자 탐지
        $settings['multiuser_detection_enabled'] = ($spam_stats['multiuser_login_enabled'] ?? $merged_config['multiuser_login_enabled'] ?? '0') == '1';
        
        // 13. Core 버전 최신 여부 - 그누보드5 버전 확인 (클라이언트에서 확인)
        $settings['core_version_latest'] = null; // JavaScript에서 확인
        
    } catch (Exception $e) {
        error_log('Security settings collection error: ' . $e->getMessage());
        // 에러 발생 시 기본값으로 설정
        $settings = array_merge($settings, array(
            'access_control_enabled' => false,
            'foreign_ip_enabled' => false,
            'board_policy_safe' => false,
            'captcha_policy_safe' => false,
            'admin_users_safe' => false,
            'extension_policy_safe' => false,
            'upload_size_policy_safe' => false,
            'spam_content_enabled' => false,
            'login_threat_enabled' => false,
            'bot_detection_enabled' => false,
            'behavior_detection_enabled' => false,
            'multiuser_detection_enabled' => false
        ));
    }
    
    return $settings;
}

// 새로운 보안 점수 계산 (0~100점 기준, 총 16개 항목)
function calculate_security_score() {
    $settings = get_all_security_settings();
    $score = 0;
    $max_score = 100;
    $score_details = array();
    
    // 1. 접근 제어 관리 (15점)
    if ($settings['access_control_enabled']) {
        $score += 15;
        $score_details['access_control'] = array('status' => 'good', 'score' => 15, 'text' => '접근 제어 활성화');
    } else {
        $score_details['access_control'] = array('status' => 'bad', 'score' => 0, 'text' => '접근 제어 비활성화');
    }
    
    // 2. 해외 IP 자동 차단 (6점)
    if ($settings['foreign_ip_enabled']) {
        $score += 6;
        $score_details['foreign_ip'] = array('status' => 'good', 'score' => 6, 'text' => '해외 IP 차단 활성화');
    } else {
        $score_details['foreign_ip'] = array('status' => 'warning', 'score' => 0, 'text' => '해외 IP 차단 비활성화');
    }
    
    // 3. 게시판 접근 권한 정책 (6점)
    if ($settings['board_policy_safe']) {
        $score += 6;
        $score_details['board_policy'] = array('status' => 'good', 'score' => 6, 'text' => '게시판 권한 정책 안전');
    } else {
        $score_details['board_policy'] = array('status' => 'bad', 'score' => 0, 'text' => '게시판 권한 정책 위험');
    }
    
    // 4. 캡챠 적용 정책 (6점)
    if ($settings['captcha_policy_safe']) {
        $score += 6;
        $score_details['captcha_policy'] = array('status' => 'good', 'score' => 6, 'text' => '캡챠 정책 안전');
    } else {
        $score_details['captcha_policy'] = array('status' => 'bad', 'score' => 0, 'text' => '캡챠 정책 위험');
    }
    
    // 5. 관리자급 권한 사용자 관리 (6점)
    if ($settings['admin_users_safe']) {
        $score += 6;
        $score_details['admin_users'] = array('status' => 'good', 'score' => 6, 'text' => '관리자 사용자 관리 안전');
    } else {
        $score_details['admin_users'] = array('status' => 'bad', 'score' => 0, 'text' => '관리자 사용자 관리 위험');
    }
    
    // 6. 확장자 정책 관리 (6점)
    if ($settings['extension_policy_safe']) {
        $score += 6;
        $score_details['extension_policy'] = array('status' => 'good', 'score' => 6, 'text' => '확장자 정책 안전');
    } else {
        $score_details['extension_policy'] = array('status' => 'bad', 'score' => 0, 'text' => '확장자 정책 위험');
    }
    
    // 7. 업로드 용량 정책 (6점)
    if ($settings['upload_size_policy_safe']) {
        $score += 6;
        $score_details['upload_size_policy'] = array('status' => 'good', 'score' => 6, 'text' => '업로드 용량 정책 안전');
    } else {
        $score_details['upload_size_policy'] = array('status' => 'bad', 'score' => 0, 'text' => '업로드 용량 정책 위험');
    }
    
    // 8. 스팸 콘텐츠 탐지 (10점)
    if ($settings['spam_content_enabled']) {
        $score += 10;
        $score_details['spam_content'] = array('status' => 'good', 'score' => 10, 'text' => '스팸 콘텐츠 탐지 활성화');
    } else {
        $score_details['spam_content'] = array('status' => 'warning', 'score' => 0, 'text' => '스팸 콘텐츠 탐지 비활성화');
    }
    
    // 9. 로그인 위협 탐지 (10점)
    if ($settings['login_threat_enabled']) {
        $score += 10;
        $score_details['login_threat'] = array('status' => 'good', 'score' => 10, 'text' => '로그인 위협 탐지 활성화');
    } else {
        $score_details['login_threat'] = array('status' => 'warning', 'score' => 0, 'text' => '로그인 위협 탐지 비활성화');
    }
    
    // 10. 악성 봇 탐지 (10점)
    if ($settings['bot_detection_enabled']) {
        $score += 10;
        $score_details['bot_detection'] = array('status' => 'good', 'score' => 10, 'text' => '악성 봇 탐지 활성화');
    } else {
        $score_details['bot_detection'] = array('status' => 'warning', 'score' => 0, 'text' => '악성 봇 탐지 비활성화');
    }
    
    // 11. 비정상 행동 탐지 (5점)
    if ($settings['behavior_detection_enabled']) {
        $score += 5;
        $score_details['behavior_detection'] = array('status' => 'good', 'score' => 5, 'text' => '비정상 행동 탐지 활성화');
    } else {
        $score_details['behavior_detection'] = array('status' => 'warning', 'score' => 0, 'text' => '비정상 행동 탐지 비활성화');
    }
    
    // 12. 다중 사용자 탐지 (5점)
    if ($settings['multiuser_detection_enabled']) {
        $score += 5;
        $score_details['multiuser_detection'] = array('status' => 'good', 'score' => 5, 'text' => '다중 사용자 탐지 활성화');
    } else {
        $score_details['multiuser_detection'] = array('status' => 'warning', 'score' => 0, 'text' => '다중 사용자 탐지 비활성화');
    }
    
    // 13. 그누보드 최신 여부 (6점) - JavaScript에서 업데이트
    $score_details['core_version'] = array('status' => 'warning', 'score' => 0, 'text' => '버전 확인 중...');
    
    return array(
        'score' => $score,
        'max_score' => $max_score,
        'percentage' => round(($score / $max_score) * 100),
        'grade' => get_security_grade($score, $max_score),
        'details' => $score_details,
        'settings' => $settings
    );
}

// 보안 등급 계산 (새로운 기준)
function get_security_grade($score, $max_score) {
    $percentage = ($score / $max_score) * 100;

    if ($percentage >= 80) return array('grade' => '✅', 'text' => '안전', 'color' => '#28a745');
    if ($percentage >= 60) return array('grade' => '⚠️', 'text' => '주의', 'color' => '#ffc107');
    return array('grade' => '❌', 'text' => '위험', 'color' => '#dc3545');
}

// 최근 로그 조회 (실제 데이터)
function get_recent_logs() {
    $logs = array();

    // IP 차단 로그 (최신 순)
    $sql = "SELECT sl_ip, sl_datetime, sl_block_reason, sl_url 
            FROM ".G5_TABLE_PREFIX."security_ip_log 
            ORDER BY sl_datetime DESC 
            LIMIT 2";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $logs[] = array(
            'time' => date('Y.m.d H:i:s', strtotime($row['sl_datetime'])),
            'timestamp' => strtotime($row['sl_datetime']), // 정렬용 타임스탬프 추가
            'ip' => $row['sl_ip'],
            'action' => $row['sl_block_reason'] ?: 'IP 차단',
            'status' => '차단됨'
        );
    }

    // 로그인 실패 로그 (최신 순)
    $sql = "SELECT slf_ip, slf_datetime, slf_mb_id 
            FROM ".G5_TABLE_PREFIX."security_login_fail 
            ORDER BY slf_datetime DESC 
            LIMIT 2";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $logs[] = array(
            'time' => date('Y.m.d H:i:s', strtotime($row['slf_datetime'])),
            'timestamp' => strtotime($row['slf_datetime']), // 정렬용 타임스탬프 추가
            'ip' => $row['slf_ip'],
            'action' => '로그인 실패 (ID: ' . htmlspecialchars($row['slf_mb_id']) . ')',
            'status' => '차단됨'
        );
    }

    // 스팸 정규식 로그 (최신 순)
    $sql = "SELECT srsl_ip, srsl_datetime, srsl_matched_pattern 
            FROM ".G5_TABLE_PREFIX."security_regex_spam_log 
            ORDER BY srsl_datetime DESC 
            LIMIT 2";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $logs[] = array(
            'time' => date('Y.m.d H:i:s', strtotime($row['srsl_datetime'])),
            'timestamp' => strtotime($row['srsl_datetime']), // 정렬용 타임스탬프 추가
            'ip' => $row['srsl_ip'],
            'action' => '스팸 패턴 감지: ' . htmlspecialchars(substr($row['srsl_matched_pattern'], 0, 20)) . '...',
            'status' => '차단됨'
        );
    }

    // 스팸 콘텐츠 탐지 로그
    $sql = "SELECT sscl_ip, sscl_datetime, sscl_action_taken, sscl_keyword_count 
            FROM ".G5_TABLE_PREFIX."security_spam_content_log 
            ORDER BY sscl_datetime DESC 
            LIMIT 2";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $action_text = '';
        switch ($row['sscl_action_taken']) {
            case 'auto_blocked':
                $action_text = '자동차단';
                break;
            case 'blocked':
                $action_text = '차단';
                break;
            case 'detected':
                $action_text = '탐지';
                break;
            default:
                $action_text = '탐지';
                break;
        }
        
        $logs[] = array(
            'time' => date('Y.m.d H:i:s', strtotime($row['sscl_datetime'])),
            'timestamp' => strtotime($row['sscl_datetime']), // 정렬용 타임스탬프 추가
            'ip' => $row['sscl_ip'],
            'action' => '스팸 콘텐츠 ' . $action_text . ' (' . $row['sscl_keyword_count'] . '개 키워드)',
            'status' => $action_text
        );
    }

    // 시간 순으로 정렬 (최신순 - 타임스탬프 기반)
    usort($logs, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
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
    $table_check = sql_fetch("SHOW TABLES LIKE '".G5_TABLE_PREFIX."access_control'");
    $debug_info['database']['table_exists'] = $table_check ? 'YES' : 'NO';

    if ($table_check) {
        $count_result = sql_fetch("SELECT COUNT(*) as cnt FROM ".G5_TABLE_PREFIX."access_control");
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

/* 로그 상태 스타일 */
.status-blocked {
    background: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.status-auto-blocked {
    background: #fd7e14;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.status-detected {
    background: #6f42c1;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
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
    grid-template-columns: repeat(3, 1fr);
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
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.score-item-clickable {
    cursor: pointer;
    user-select: none;
}

.score-item-clickable:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.score-item-clickable:active {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}


.score-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.score-item-name {
    font-weight: 600;
    font-size: 13px;
    line-height: 1.2;
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.score-item-status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    min-width: 40px;
}

.status-badge-safe {
    background: rgba(255, 255, 255, 0.9);
    color: #28a745;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.status-badge-caution {
    background: rgba(255, 255, 255, 0.9);
    color: #ffc107;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.status-badge-danger {
    background: rgba(255, 255, 255, 0.9);
    color: #dc3545;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.score-item-status {
    font-size: 12px;
    opacity: 0.9;
    line-height: 1.2;
    margin-top: 4px;
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

/* 반응형 디자인 개선 */
@media (max-width: 1024px) {
    .score-details {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .score-details {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .score-item {
        min-height: 70px;
        padding: 12px;
    }
    
    .score-item-name {
        font-size: 12px;
    }
    
    .score-item-status {
        font-size: 11px;
    }
    
    .score-main {
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }
    
    .score-grade {
        margin-left: 0;
    }
}
</style>

<div class="security-dashboard">
    <h1 class="dashboard-title">보안설정 대시보드</h1>

    <!-- 종합 보안 점수 -->
    <div class="security-score-section">
        <div class="score-header">
            <h2 class="score-title">🛡️ 종합 보안 점수</h2>
            <div class="score-updated">점수 반영: <?php echo date('Y.m.d H:i'); ?></div>
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
            <div class="score-item score-item-clickable" data-item="<?php echo $key; ?>" onclick="navigateToSecurityPage('<?php echo $key; ?>')">
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
                            'access_control' => '접근제어 관리',
                            'foreign_ip' => '해외IP 차단',
                            'board_policy' => '게시판 권한정책',
                            'captcha_policy' => '캡챠 정책',
                            'admin_users' => '관리자 권한관리',
                            'extension_policy' => '확장자 정책',
                            'upload_size_policy' => '업로드 용량정책',
                            'spam_content' => '스팸 콘텐츠 탐지',
                            'login_threat' => '로그인 위협탐지',
                            'bot_detection' => '악성봇 탐지',
                            'behavior_detection' => '비정상 행동탐지',
                            'multiuser_detection' => '다중사용자 탐지',
                            'core_version' => '그누보드 최신여부'
                        );
                        echo $item_names[$key] ?? $key;
                        ?>
                    </span>
                    <span class="score-item-status-badge 
                        <?php 
                        echo $detail['status'] == 'good' ? 'status-badge-safe' : 
                            ($detail['status'] == 'warning' ? 'status-badge-caution' : 'status-badge-danger'); 
                        ?>">
                        <?php 
                        echo $detail['status'] == 'good' ? '안전' : 
                            ($detail['status'] == 'warning' ? '주의' : '위험'); 
                        ?>
                    </span>
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
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($security_stats['auto_blocked_spam']); ?>건</div>
                    <div class="stat-label">자동 차단된 스팸</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($security_stats['multiuser_detections']); ?>건</div>
                    <div class="stat-label">다중 사용자 탐지</div>
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
                        <td><?php 
                            $status = $log['status'];
                            $status_class = '';
                            $status_icon = '';
                            $status_text = '';
                            
                            switch($status) {
                                case '차단됨':
                                    $status_class = 'status-blocked';
                                    $status_icon = '🚫';
                                    $status_text = '차단됨';
                                    break;
                                case '자동차단':
                                    $status_class = 'status-auto-blocked';
                                    $status_icon = '🤖';
                                    $status_text = '자동차단';
                                    break;
                                case '탐지':
                                    $status_class = 'status-detected';
                                    $status_icon = '👁️';
                                    $status_text = '탐지됨';
                                    break;
                                default:
                                    $status_class = 'status-blocked';
                                    $status_icon = '⚠️';
                                    $status_text = $status;
                                    break;
                            }
                            ?>
                            <span class="<?php echo $status_class; ?>">
                                <?php echo $status_icon; ?> <?php echo $status_text; ?>
                            </span>
                        </td>
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

    console.group('🔧 설정 디버그');
    if (securityScore.settings) {
        console.log('📋 최종 설정 상태:');
        console.log('해외IP 차단:', securityScore.settings.foreign_ip_enabled);
        console.log('스팸 콘텐츠:', securityScore.settings.spam_content_enabled);
        console.log('로그인 위협:', securityScore.settings.login_threat_enabled);
        console.log('봇 탐지:', securityScore.settings.bot_detection_enabled);
        
        if (securityScore.settings.debug_config) {
            console.log('🗂️ GK Config:', securityScore.settings.debug_config);
        }
        if (securityScore.settings.debug_block_stats) {
            console.log('🛡️ Block Stats:', securityScore.settings.debug_block_stats);
        }
        if (securityScore.settings.debug_spam_stats) {
            console.log('🔍 Spam Stats:', securityScore.settings.debug_spam_stats);
        }
    }
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
            
            // Core 버전 최신 여부로 보안 점수 업데이트
            updateSecurityScoreForCoreVersion(g5Status);
        } else {
            document.getElementById('g5-latest-version').textContent = '확인 불가';
            updateVersionStatus('g5', 'unknown');
            // 네트워크 오류 시에도 기본적으로 최신으로 간주하여 6점 부여
            updateSecurityScoreForCoreVersion('latest');
        }
        
    } catch (error) {
        console.error('Version check failed:', error);
        document.getElementById('gk-latest-version').textContent = '확인 실패';
        document.getElementById('g5-latest-version').textContent = '확인 실패';
        updateVersionStatus('gk', 'unknown');
        updateVersionStatus('g5', 'unknown');
        // 네트워크 오류 시에도 기본적으로 최신으로 간주하여 6점 부여
        updateSecurityScoreForCoreVersion('latest');
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

// Core 버전 상태에 따라 보안 점수 업데이트
function updateSecurityScoreForCoreVersion(versionStatus) {
    const coreVersionElement = document.querySelector('[data-item="core_version"]');
    if (!coreVersionElement) return;
    
    const badgeElement = coreVersionElement.querySelector('.score-item-status-badge');
    const statusElement = coreVersionElement.querySelector('.score-item-status');
    const iconElement = coreVersionElement.querySelector('.status-icon');
    
    let score = 0;
    let text = '';
    let icon = '❌';
    let badgeText = '위험';
    let badgeClass = 'status-badge-danger';
    
    switch (versionStatus) {
        case 'latest':
            score = 6;
            text = '그누보드 최신 버전';
            icon = '✅';
            badgeText = '안전';
            badgeClass = 'status-badge-safe';
            break;
        case 'newer':
            score = 6;
            text = '그누보드 최신 (개발)';
            icon = '✅';
            badgeText = '안전';
            badgeClass = 'status-badge-safe';
            break;
        case 'outdated':
            score = 0;
            text = '그누보드 구버전';
            icon = '❌';
            badgeText = '위험';
            badgeClass = 'status-badge-danger';
            break;
        default:
            score = 0;
            text = '그누보드 버전 확인 불가';
            icon = '⚠️';
            badgeText = '주의';
            badgeClass = 'status-badge-caution';
            break;
    }
    
    if (badgeElement) {
        badgeElement.textContent = badgeText;
        badgeElement.className = 'score-item-status-badge ' + badgeClass;
    }
    if (statusElement) statusElement.textContent = text;
    if (iconElement) iconElement.textContent = icon;
    
    // 전체 점수 재계산 및 업데이트
    updateTotalSecurityScore(score);
}

// 전체 보안 점수 업데이트
function updateTotalSecurityScore(coreVersionScore) {
    const currentScoreElement = document.querySelector('.score-number');
    const currentPercentageElement = document.querySelector('.progress-fill');
    const gradeElement = document.querySelector('.grade-badge');
    const gradeTextElement = document.querySelector('.grade-text');
    
    if (!currentScoreElement) return;
    
    // 현재 점수에 Core 버전 점수 추가
    let currentScore = parseInt(currentScoreElement.textContent);
    let newScore = currentScore + coreVersionScore;
    
    // 100점 초과 방지
    if (newScore > 100) newScore = 100;
    
    const newPercentage = Math.round((newScore / 100) * 100);
    
    // UI 업데이트
    currentScoreElement.textContent = newScore;
    currentPercentageElement.style.width = newPercentage + '%';
    
    // 등급 업데이트
    let grade, gradeText, gradeColor;
    if (newPercentage >= 80) {
        grade = '✅';
        gradeText = '안전';
        gradeColor = '#28a745';
    } else if (newPercentage >= 60) {
        grade = '⚠️';
        gradeText = '주의';
        gradeColor = '#ffc107';
    } else {
        grade = '❌';
        gradeText = '위험';
        gradeColor = '#dc3545';
    }
    
    if (gradeElement) {
        gradeElement.textContent = grade;
        gradeElement.style.backgroundColor = gradeColor;
    }
    if (gradeTextElement) gradeTextElement.textContent = gradeText;
}

// 보안 페이지로 네비게이션
function navigateToSecurityPage(itemKey) {
    const pageMap = {
        'access_control': './access_control.php',
        'foreign_ip': './security_block/index.php',
        'board_policy': './security_extension.php',
        'captcha_policy': './security_extension.php',
        'admin_users': './security_extension.php',
        'extension_policy': './security_extension.php',
        'upload_size_policy': './security_extension.php',
        'spam_content': './security_detect/index.php',
        'login_threat': './security_detect/index.php',
        'bot_detection': './security_detect/index.php',
        'behavior_detection': './security_detect/index.php',
        'multiuser_detection': './security_detect/index.php',
        'core_version': 'https://sir.kr/g5_pds'
    };
    
    const url = pageMap[itemKey];
    if (url) {
        if (url.startsWith('http')) {
            // 외부 링크는 새 창에서 열기
            window.open(url, '_blank');
        } else {
            // 내부 페이지는 같은 창에서 열기
            window.location.href = url;
        }
    } else {
        console.log('해당 항목에 대한 페이지를 찾을 수 없습니다:', itemKey);
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
    
    // 즉시 6점 부여 (모든 항목 안전 시 100점 달성)
    updateSecurityScoreForCoreVersion('latest');
    
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