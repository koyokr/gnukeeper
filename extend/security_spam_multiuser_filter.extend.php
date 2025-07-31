<?php
/**
 * 다중 계정 차단 보안 플러그인
 * gnuboard5 extend 파일
 *
 * 하루 내에 같은 IP에서 여러 회원가입이나 로그인을 감지하여 자동으로 IP를 차단합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 관리자는 차단에서 제외
if (isset($member) && $member['mb_level'] >= 10) {
    return;
}

// 현재 IP 확인
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (empty($current_ip)) {
    return;
}

// IP 유효성 검사
if (!filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return;
}

// 테이블 존재 여부 확인
if (!security_multiuser_check_tables_exist()) {
    return;
}

// 예외 IP(화이트리스트) 확인
if (security_multiuser_is_whitelisted($current_ip)) {
    return;
}

// 회원가입 완료 후 훅 등록
add_event('member_confirm_after', 'security_multiuser_handle_register', 10, 1);

// 로그인 성공 후 훅 등록  
add_event('login_check_after', 'security_multiuser_handle_login', 10, 1);

/**
 * 테이블 존재 여부 확인
 */
function security_multiuser_check_tables_exist() {
    static $tables_exist = null;

    if ($tables_exist === null) {
        $sql = "SHOW TABLES LIKE '" . G5_TABLE_PREFIX . "security_register_log'";
        $result = sql_query($sql, false);
        $tables_exist = ($result && sql_num_rows($result) > 0);
    }

    return $tables_exist;
}

/**
 * IP가 예외 IP(화이트리스트)에 있는지 확인
 */
function security_multiuser_is_whitelisted($ip) {
    $sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist
            WHERE sw_ip = '" . sql_escape_string($ip) . "'";

    $result = sql_query($sql, false);
    if ($result && $row = sql_fetch_array($result)) {
        return $row['cnt'] > 0;
    }

    return false;
}

/**
 * 회원가입 처리
 */
function security_multiuser_handle_register($mb) {
    // 회원가입 차단 기능이 활성화되어 있는지 확인
    if (!security_multiuser_is_register_enabled()) {
        return;
    }

    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($current_ip)) {
        return;
    }

    // 회원가입 기록
    security_multiuser_record_register($current_ip, $mb['mb_id'], $mb['mb_email'], $user_agent);

    // 차단 조건 확인 및 처리
    security_multiuser_check_register_and_block($current_ip);
}

/**
 * 로그인 성공 처리
 */
function security_multiuser_handle_login($mb) {
    // 로그인 차단 기능이 활성화되어 있는지 확인
    if (!security_multiuser_is_login_enabled()) {
        return;
    }

    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($current_ip)) {
        return;
    }

    // 로그인 성공 기록
    security_multiuser_record_login($current_ip, $mb['mb_id'], $user_agent);

    // 차단 조건 확인 및 처리
    security_multiuser_check_login_and_block($current_ip);
}

/**
 * 회원가입 차단 기능 활성화 여부 확인
 */
function security_multiuser_is_register_enabled() {
    static $is_enabled = null;

    if ($is_enabled === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'multiuser_register_enabled'";
        $result = sql_query($sql, false);

        if ($result && $row = sql_fetch_array($result)) {
            $is_enabled = ($row['sc_value'] == '1');
        } else {
            $is_enabled = false;
        }
    }

    return $is_enabled;
}

/**
 * 로그인 차단 기능 활성화 여부 확인
 */
function security_multiuser_is_login_enabled() {
    static $is_enabled = null;

    if ($is_enabled === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'multiuser_login_enabled'";
        $result = sql_query($sql, false);

        if ($result && $row = sql_fetch_array($result)) {
            $is_enabled = ($row['sc_value'] == '1');
        } else {
            $is_enabled = false;
        }
    }

    return $is_enabled;
}

/**
 * 회원가입 기록
 */
function security_multiuser_record_register($ip, $mb_id, $mb_email, $user_agent) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_register_log SET
                srl_ip = '" . sql_escape_string($ip) . "',
                srl_mb_id = '" . sql_escape_string($mb_id) . "',
                srl_mb_email = '" . sql_escape_string($mb_email) . "',
                srl_user_agent = '" . sql_escape_string(substr($user_agent, 0, 500)) . "',
                srl_datetime = NOW(),
                srl_status = 'success'";

    sql_query($sql, false);
}

/**
 * 로그인 성공 기록
 */
function security_multiuser_record_login($ip, $mb_id, $user_agent) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_login_success_log SET
                sls_ip = '" . sql_escape_string($ip) . "',
                sls_mb_id = '" . sql_escape_string($mb_id) . "',
                sls_user_agent = '" . sql_escape_string(substr($user_agent, 0, 500)) . "',
                sls_datetime = NOW()";

    sql_query($sql, false);
}

/**
 * 회원가입 차단 조건 확인 및 IP 차단 처리
 */
function security_multiuser_check_register_and_block($ip) {
    // 설정값 로드
    $config = security_multiuser_get_config();
    $max_registers = (int)($config['multiuser_register_limit'] ?? 3);
    $time_window = (int)($config['multiuser_register_window'] ?? 86400); // 24시간
    $block_duration = (int)($config['multiuser_register_block_duration'] ?? 3600); // 1시간

    // 지정된 시간 내의 회원가입 횟수 확인
    $since_time = date('Y-m-d H:i:s', time() - $time_window);

    $sql = "SELECT COUNT(DISTINCT srl_mb_id) as register_count
            FROM " . G5_TABLE_PREFIX . "security_register_log
            WHERE srl_ip = '" . sql_escape_string($ip) . "'
              AND srl_datetime >= '{$since_time}'
              AND srl_status = 'success'";

    $result = sql_query($sql, false);
    if (!$result) {
        return;
    }

    $row = sql_fetch_array($result);
    $register_count = (int)$row['register_count'];

    // 최대 가입 횟수를 초과했다면 IP 차단
    if ($register_count >= $max_registers) {
        security_multiuser_add_ip_block($ip, "24시간 내 {$register_count}개 계정 가입", $block_duration, 'auto_multiuser_register');
    }
}

/**
 * 로그인 차단 조건 확인 및 IP 차단 처리
 */
function security_multiuser_check_login_and_block($ip) {
    // 설정값 로드
    $config = security_multiuser_get_config();
    $max_logins = (int)($config['multiuser_login_limit'] ?? 5);
    $time_window = (int)($config['multiuser_login_window'] ?? 86400); // 24시간
    $block_duration = (int)($config['multiuser_login_block_duration'] ?? 1800); // 30분

    // 지정된 시간 내의 로그인 횟수 (서로 다른 계정) 확인
    $since_time = date('Y-m-d H:i:s', time() - $time_window);

    $sql = "SELECT COUNT(DISTINCT sls_mb_id) as login_count
            FROM " . G5_TABLE_PREFIX . "security_login_success_log
            WHERE sls_ip = '" . sql_escape_string($ip) . "'
              AND sls_datetime >= '{$since_time}'";

    $result = sql_query($sql, false);
    if (!$result) {
        return;
    }

    $row = sql_fetch_array($result);
    $login_count = (int)$row['login_count'];

    // 최대 로그인 횟수를 초과했다면 IP 차단
    if ($login_count >= $max_logins) {
        security_multiuser_add_ip_block($ip, "24시간 내 {$login_count}개 계정 로그인", $block_duration, 'auto_multiuser_login');
    }
}

/**
 * 다중 계정 설정 로드
 */
function security_multiuser_get_config() {
    static $config = null;

    if ($config === null) {
        $config = array();

        $sql = "SELECT sc_key, sc_value FROM " . G5_TABLE_PREFIX . "security_config
                WHERE sc_key IN ('multiuser_register_enabled', 'multiuser_register_limit', 'multiuser_register_window', 'multiuser_register_block_duration',
                                'multiuser_login_enabled', 'multiuser_login_limit', 'multiuser_login_window', 'multiuser_login_block_duration')";
        $result = sql_query($sql, false);

        if ($result) {
            while ($row = sql_fetch_array($result)) {
                $config[$row['sc_key']] = $row['sc_value'];
            }
        }

        // 기본값 설정
        if (!isset($config['multiuser_register_enabled'])) $config['multiuser_register_enabled'] = '0';
        if (!isset($config['multiuser_register_limit'])) $config['multiuser_register_limit'] = '3';
        if (!isset($config['multiuser_register_window'])) $config['multiuser_register_window'] = '86400';
        if (!isset($config['multiuser_register_block_duration'])) $config['multiuser_register_block_duration'] = '3600';
        if (!isset($config['multiuser_login_enabled'])) $config['multiuser_login_enabled'] = '0';
        if (!isset($config['multiuser_login_limit'])) $config['multiuser_login_limit'] = '5';
        if (!isset($config['multiuser_login_window'])) $config['multiuser_login_window'] = '86400';
        if (!isset($config['multiuser_login_block_duration'])) $config['multiuser_login_block_duration'] = '1800';
    }

    return $config;
}

/**
 * IP 자동 차단 추가
 */
function security_multiuser_add_ip_block($ip, $reason, $block_duration, $block_type) {
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

    // IP 차단 추가
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_block SET
                sb_ip = '" . sql_escape_string($ip) . "',
                sb_start_ip = {$ip_long},
                sb_end_ip = {$ip_long},
                sb_reason = '" . sql_escape_string($reason) . "',
                sb_block_type = '" . sql_escape_string($block_type) . "',
                sb_block_level = 'access',
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
                    sl_action = 'auto_block',
                    sl_reason = '" . sql_escape_string($reason) . "'";

    sql_query($log_sql, false);
}
?>