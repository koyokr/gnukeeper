<?php
/**
 * 스팸 차단 보안 플러그인
 * gnuboard5 extend 파일
 * 
 * 로그인 실패 시도를 감지하여 자동으로 IP를 차단합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 로그인 실패 이벤트 훅 등록
add_event('password_is_wrong', 'security_handle_login_fail', 10);

/**
 * 로그인 실패 처리 함수
 */
function security_handle_login_fail($type, $mb) {
    // 로그인 실패가 아니면 무시
    if ($type !== 'login') {
        return;
    }
    
    // 테이블이 존재하지 않으면 무시
    if (!security_spam_check_tables_exist()) {
        return;
    }
    
    // 스팸 차단 기능이 비활성화되면 무시
    if (!security_spam_is_enabled()) {
        return;
    }
    
    // 현재 IP 주소 확인
    $current_ip = security_spam_get_client_ip();
    if (empty($current_ip)) {
        return;
    }
    
    // 관리자 IP는 차단하지 않음
    if (security_spam_is_admin_ip($current_ip)) {
        return;
    }
    
    // 화이트리스트에 있으면 차단하지 않음
    if (security_spam_is_whitelisted($current_ip)) {
        return;
    }
    
    // 로그인 실패 기록
    security_spam_record_login_fail($current_ip, $mb['mb_id'] ?? '');
    
    // 차단 조건 확인 및 처리
    security_spam_check_and_block($current_ip);
}

/**
 * 테이블 존재 여부 확인
 */
function security_spam_check_tables_exist() {
    static $tables_exist = null;
    
    if ($tables_exist === null) {
        $sql = "SHOW TABLES LIKE '" . G5_TABLE_PREFIX . "security_config'";
        $result = sql_query($sql, false);
        $tables_exist = ($result && sql_num_rows($result) > 0);
    }
    
    return $tables_exist;
}

/**
 * 스팸 차단 기능 활성화 여부 확인
 */
function security_spam_is_enabled() {
    static $is_enabled = null;
    
    if ($is_enabled === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'login_block_enabled'";
        $result = sql_query($sql, false);
        
        if ($result && $row = sql_fetch_array($result)) {
            $is_enabled = ($row['sc_value'] == '1');
        } else {
            $is_enabled = true; // 기본값: 활성화
        }
    }
    
    return $is_enabled;
}

/**
 * 클라이언트 IP 주소 확인 (프록시 고려)
 */
function security_spam_get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($forwarded_ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    // IP 유효성 검사
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return '';
    }
    
    return $ip;
}

/**
 * 관리자 IP인지 확인
 */
function security_spam_is_admin_ip($ip) {
    global $member;
    
    // 현재 세션이 관리자 레벨인지 확인
    if (isset($member) && $member['mb_level'] >= 10) {
        return true;
    }
    
    return false;
}

/**
 * IP가 화이트리스트에 있는지 확인
 */
function security_spam_is_whitelisted($ip) {
    $sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist 
            WHERE sw_ip = '" . sql_escape_string($ip) . "'";
    
    $result = sql_query($sql, false);
    if ($result && $row = sql_fetch_array($result)) {
        return $row['cnt'] > 0;
    }
    
    return false;
}

/**
 * 로그인 실패 기록
 */
function security_spam_record_login_fail($ip, $mb_id = '') {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_log SET
                sl_ip = '" . sql_escape_string($ip) . "',
                sl_datetime = NOW(),
                sl_url = '" . sql_escape_string($_SERVER['REQUEST_URI'] ?? '/bbs/login_check.php') . "',
                sl_user_agent = '" . sql_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '') . "',
                sl_block_reason = '" . sql_escape_string("로그인 실패 (ID: {$mb_id})") . "'";
    
    sql_query($sql, false);
}

/**
 * 차단 조건 확인 및 IP 차단 처리
 */
function security_spam_check_and_block($ip) {
    // 설정값 로드
    $config = gk_spam_get_config();
    $max_attempts = (int)($config['login_attempt_limit'] ?? 5);
    $time_window = (int)($config['login_attempt_window'] ?? 300); // 5분
    $block_duration = (int)($config['auto_block_duration'] ?? 600); // 10분
    
    // 지정된 시간 내의 로그인 실패 횟수 확인
    $since_time = date('Y-m-d H:i:s', time() - $time_window);
    
    $sql = "SELECT COUNT(*) as fail_count 
            FROM " . G5_TABLE_PREFIX . "security_ip_log 
            WHERE sl_ip = '" . sql_escape_string($ip) . "' 
              AND sl_datetime >= '{$since_time}'
              AND sl_block_reason LIKE '로그인 실패%'";
    
    $result = sql_query($sql, false);
    if (!$result) {
        return;
    }
    
    $row = sql_fetch_array($result);
    $fail_count = (int)$row['fail_count'];
    
    // 최대 시도 횟수를 초과했다면 IP 차단
    if ($fail_count >= $max_attempts) {
        security_spam_add_ip_block($ip, $fail_count, $block_duration);
    }
}

/**
 * 스팸 차단 설정 로드
 */
function gk_spam_get_config() {
    static $config = null;
    
    if ($config === null) {
        $config = array();
        
        $sql = "SELECT sc_key, sc_value FROM " . G5_TABLE_PREFIX . "security_config 
                WHERE sc_key IN ('login_attempt_limit', 'login_attempt_window', 'auto_block_duration', 'login_block_enabled')";
        $result = sql_query($sql, false);
        
        if ($result) {
            while ($row = sql_fetch_array($result)) {
                $config[$row['sc_key']] = $row['sc_value'];
            }
        }
        
        // 기본값 설정
        if (!isset($config['login_attempt_limit'])) $config['login_attempt_limit'] = '5';
        if (!isset($config['login_attempt_window'])) $config['login_attempt_window'] = '300';
        if (!isset($config['auto_block_duration'])) $config['auto_block_duration'] = '600';
        if (!isset($config['login_block_enabled'])) $config['login_block_enabled'] = '1';
    }
    
    return $config;
}

/**
 * IP 자동 차단 추가
 */
function security_spam_add_ip_block($ip, $fail_count, $block_duration) {
    // 이미 차단된 IP인지 확인
    $ip_long = sprintf('%u', ip2long($ip));
    
    $existing_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block 
                     WHERE sb_status = 'active' 
                       AND {$ip_long} BETWEEN sb_start_ip AND sb_end_ip";
    
    $existing_result = sql_query($existing_sql, false);
    if ($existing_result) {
        $existing_row = sql_fetch_array($existing_result);
        if ($existing_row['cnt'] > 0) {
            return; // 이미 차단된 IP
        }
    }
    
    // 차단 종료 시간 계산
    $end_datetime = date('Y-m-d H:i:s', time() + $block_duration);
    $reason = "로그인 {$fail_count}회 실패 (자동 차단)";
    
    // IP 차단 추가
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_block SET
                sb_ip = '" . sql_escape_string($ip) . "',
                sb_start_ip = {$ip_long},
                sb_end_ip = {$ip_long},
                sb_reason = '" . sql_escape_string($reason) . "',
                sb_block_type = 'auto_login',
                sb_duration = 'temporary',
                sb_end_datetime = '{$end_datetime}',
                sb_hit_count = 0,
                sb_status = 'active',
                sb_datetime = NOW()";
    
    sql_query($sql, false);
    
    // 추가 로그 기록
    $log_sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_log SET
                    sl_ip = '" . sql_escape_string($ip) . "',
                    sl_datetime = NOW(),
                    sl_url = '/auto-block',
                    sl_user_agent = 'System',
                    sl_block_reason = '" . sql_escape_string($reason) . "'";
    
    sql_query($log_sql, false);
}
?>