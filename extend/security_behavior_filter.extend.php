<?php
/**
 * 이상 행위 차단 보안 플러그인
 * gnuboard5 extend 파일
 *
 * 404 페이지 과다 접속과 비정상 레퍼러를 감지하여 자동으로 IP를 차단합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 관리자는 차단에서 제외
if (isset($member) && $member['mb_level'] >= 10) {
    return;
}

// 현재 IP 및 요청 정보 확인
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

if (empty($current_ip)) {
    return;
}

// IP 유효성 검사
if (!filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return;
}

// 테이블 존재 여부 확인
if (!security_behavior_check_tables_exist()) {
    return;
}

// 예외 IP(화이트리스트) 확인
if (security_behavior_is_whitelisted($current_ip)) {
    return;
}

// 404 에러 감지 및 처리
if (http_response_code() === 404 || 
    (isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS'] == '404')) {
    security_behavior_handle_404($current_ip, $request_uri, $user_agent, $referer);
}

// 레퍼러 검증 (특정 페이지에 대해)
security_behavior_check_referer($current_ip, $request_uri, $user_agent, $referer);

/**
 * 테이블 존재 여부 확인
 */
function security_behavior_check_tables_exist() {
    static $tables_exist = null;

    if ($tables_exist === null) {
        $sql = "SHOW TABLES LIKE '" . G5_TABLE_PREFIX . "security_404_log'";
        $result = sql_query($sql, false);
        $tables_exist = ($result && sql_num_rows($result) > 0);
    }

    return $tables_exist;
}

/**
 * IP가 예외 IP(화이트리스트)에 있는지 확인
 */
function security_behavior_is_whitelisted($ip) {
    $sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist
            WHERE sw_ip = '" . sql_escape_string($ip) . "'";

    $result = sql_query($sql, false);
    if ($result && $row = sql_fetch_array($result)) {
        return $row['cnt'] > 0;
    }

    return false;
}

/**
 * 404 접속 처리
 */
function security_behavior_handle_404($ip, $url, $user_agent, $referer) {
    // 404 차단 기능이 활성화되어 있는지 확인
    if (!security_behavior_is_404_enabled()) {
        return;
    }

    // 404 접속 기록
    security_behavior_record_404($ip, $url, $user_agent, $referer);

    // 차단 조건 확인 및 처리
    security_behavior_check_404_and_block($ip);
}

/**
 * 404 차단 기능 활성화 여부 확인
 */
function security_behavior_is_404_enabled() {
    static $is_enabled = null;

    if ($is_enabled === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'behavior_404_enabled'";
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
 * 404 접속 기록
 */
function security_behavior_record_404($ip, $url, $user_agent, $referer) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_404_log SET
                sl4_ip = '" . sql_escape_string($ip) . "',
                sl4_url = '" . sql_escape_string(substr($url, 0, 500)) . "',
                sl4_user_agent = '" . sql_escape_string(substr($user_agent, 0, 500)) . "',
                sl4_referer = '" . sql_escape_string(substr($referer, 0, 500)) . "',
                sl4_datetime = NOW()";

    sql_query($sql, false);
}

/**
 * 404 차단 조건 확인 및 IP 차단 처리
 */
function security_behavior_check_404_and_block($ip) {
    // 설정값 로드
    $config = security_behavior_get_config();
    $max_attempts = (int)($config['behavior_404_limit'] ?? 10);
    $time_window = (int)($config['behavior_404_window'] ?? 300); // 5분
    $block_duration = (int)($config['behavior_404_block_duration'] ?? 1800); // 30분

    // 지정된 시간 내의 404 접속 횟수 확인
    $since_time = date('Y-m-d H:i:s', time() - $time_window);

    $sql = "SELECT COUNT(*) as access_count
            FROM " . G5_TABLE_PREFIX . "security_404_log
            WHERE sl4_ip = '" . sql_escape_string($ip) . "'
              AND sl4_datetime >= '{$since_time}'";

    $result = sql_query($sql, false);
    if (!$result) {
        return;
    }

    $row = sql_fetch_array($result);
    $access_count = (int)$row['access_count'];

    // 최대 시도 횟수를 초과했다면 IP 차단
    if ($access_count >= $max_attempts) {
        security_behavior_add_ip_block($ip, "404 페이지 {$access_count}회 접속", $block_duration, 'auto_404');
    }
}

/**
 * 레퍼러 검증
 */
function security_behavior_check_referer($ip, $url, $user_agent, $referer) {
    // 레퍼러 검증 기능이 활성화되어 있는지 확인
    if (!security_behavior_is_referer_enabled()) {
        return;
    }

    $script_name = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $suspicious = false;
    $expected_referer = '';

    // 로그인 체크 페이지
    if ($script_name == 'login_check.php') {
        $expected_referer = 'login.php';
        if (empty($referer) || strpos($referer, 'login.php') === false) {
            $suspicious = true;
        }
    }
    // 회원가입 처리 페이지
    elseif ($script_name == 'register_form_update.php') {
        $expected_referer = 'register_form.php';
        if (empty($referer) || strpos($referer, 'register') === false) {
            $suspicious = true;
        }
    }
    // 글쓰기 처리 페이지
    elseif ($script_name == 'write_update.php') {
        $expected_referer = 'write.php';
        if (empty($referer) || strpos($referer, 'write.php') === false) {
            $suspicious = true;
        }
    }
    // 댓글 처리 페이지
    elseif ($script_name == 'write_comment_update.php') {
        $expected_referer = 'view.php';
        if (empty($referer) || strpos($referer, 'view.php') === false) {
            $suspicious = true;
        }
    }

    if ($suspicious) {
        // 레퍼러 이상 기록
        security_behavior_record_referer($ip, $url, $expected_referer, $referer, $user_agent);
        
        // 즉시 차단
        $block_duration = (int)(security_behavior_get_config()['behavior_referer_block_duration'] ?? 3600);
        security_behavior_add_ip_block($ip, "비정상 레퍼러: {$script_name}", $block_duration, 'auto_referer');
    }
}

/**
 * 레퍼러 차단 기능 활성화 여부 확인
 */
function security_behavior_is_referer_enabled() {
    static $is_enabled = null;

    if ($is_enabled === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'behavior_referer_enabled'";
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
 * 레퍼러 이상 기록
 */
function security_behavior_record_referer($ip, $url, $expected_referer, $actual_referer, $user_agent) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_referer_log SET
                srl_ip = '" . sql_escape_string($ip) . "',
                srl_url = '" . sql_escape_string(substr($url, 0, 500)) . "',
                srl_expected_referer = '" . sql_escape_string($expected_referer) . "',
                srl_actual_referer = '" . sql_escape_string(substr($actual_referer, 0, 500)) . "',
                srl_user_agent = '" . sql_escape_string(substr($user_agent, 0, 500)) . "',
                srl_datetime = NOW()";

    sql_query($sql, false);
}

/**
 * 이상 행위 설정 로드
 */
function security_behavior_get_config() {
    static $config = null;

    if ($config === null) {
        $config = array();

        $sql = "SELECT sc_key, sc_value FROM " . G5_TABLE_PREFIX . "security_config
                WHERE sc_key IN ('behavior_404_enabled', 'behavior_404_limit', 'behavior_404_window', 'behavior_404_block_duration',
                                'behavior_referer_enabled', 'behavior_referer_block_duration')";
        $result = sql_query($sql, false);

        if ($result) {
            while ($row = sql_fetch_array($result)) {
                $config[$row['sc_key']] = $row['sc_value'];
            }
        }

        // 기본값 설정
        if (!isset($config['behavior_404_enabled'])) $config['behavior_404_enabled'] = '0';
        if (!isset($config['behavior_404_limit'])) $config['behavior_404_limit'] = '10';
        if (!isset($config['behavior_404_window'])) $config['behavior_404_window'] = '300';
        if (!isset($config['behavior_404_block_duration'])) $config['behavior_404_block_duration'] = '1800';
        if (!isset($config['behavior_referer_enabled'])) $config['behavior_referer_enabled'] = '0';
        if (!isset($config['behavior_referer_block_duration'])) $config['behavior_referer_block_duration'] = '3600';
    }

    return $config;
}

/**
 * IP 자동 차단 추가
 */
function security_behavior_add_ip_block($ip, $reason, $block_duration, $block_type) {
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