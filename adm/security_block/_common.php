<?php
$sub_menu = '950300';
require_once '../_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// 관리자 권한 변수 설정
$is_admin = isset($member['mb_level']) && $member['mb_level'] >= 10 ? 'super' : '';

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
    $alter_sql = "ALTER TABLE " . G5_TABLE_PREFIX . "security_ip_block 
                  ADD COLUMN sb_block_level varchar(20) NOT NULL DEFAULT 'access' COMMENT '차단 수준' 
                  AFTER sb_block_type";
    sql_query($alter_sql, false);
}

// IP 차단 검사 함수
function gk_is_ip_blocked($ip) {
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    $ip_long = sprintf('%u', ip2long($ip));
    
    $sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block 
            WHERE sb_status = 'active' 
              AND (sb_duration = 'permanent' OR sb_end_datetime > NOW())
              AND {$ip_long} BETWEEN sb_start_ip AND sb_end_ip";
              
    $result = sql_query($sql, false);
    if ($result && $row = sql_fetch_array($result)) {
        return $row['cnt'] > 0;
    }
    
    return false;
}

// CIDR 파싱 함수 (extend/security_common.extend.php에서 제공)
function gk_parse_cidr_for_block($cidr) {
    if (strpos($cidr, '/') === false) {
        // 단일 IP인 경우
        $ip = trim($cidr);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_long = sprintf('%u', ip2long($ip));
            return array($ip_long, $ip_long);
        }
        return false;
    }
    
    list($ip, $prefix) = explode('/', $cidr);
    $ip = trim($ip);
    $prefix = (int)trim($prefix);
    
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $prefix < 0 || $prefix > 32) {
        return false;
    }
    
    $ip_long = sprintf('%u', ip2long($ip));
    $mask = 0xFFFFFFFF << (32 - $prefix);
    $mask = $mask & 0xFFFFFFFF;
    
    $start_ip = $ip_long & $mask;
    $end_ip = $start_ip | (~$mask & 0xFFFFFFFF);
    
    return array($start_ip, $end_ip);
}

// 설정값 가져오기 함수
function gk_get_config($key, $default = '') {
    static $config_cache = array();
    
    if (!isset($config_cache[$key])) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = '" . sql_escape_string($key) . "'";
        $result = sql_query($sql, false);
        
        if ($result && $row = sql_fetch_array($result)) {
            $config_cache[$key] = $row['sc_value'];
        } else {
            $config_cache[$key] = $default;
        }
    }
    
    return $config_cache[$key];
}

// gk_set_config 함수는 extend/security_common.extend.php에서 제공됩니다.
?>