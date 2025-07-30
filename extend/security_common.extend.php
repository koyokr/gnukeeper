<?php
/**
 * GnuKeeper 보안 플러그인 공통 함수
 */

if (!defined('_GNUBOARD_')) exit;

/**
 * CIDR 파싱
 */
function gk_parse_cidr($cidr) {
    if (!preg_match('/^([0-9.]+)\/([0-9]+)$/', $cidr, $matches)) {
        return false;
    }
    
    $ip = $matches[1];
    $prefix = (int)$matches[2];
    
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $prefix < 0 || $prefix > 32) {
        return false;
    }
    
    $ip_long = ip2long($ip);
    $mask = ~((1 << (32 - $prefix)) - 1);
    $start = $ip_long & $mask;
    $end = $start | ~$mask;
    
    return [
        'start' => sprintf('%u', $start),
        'end' => sprintf('%u', $end)
    ];
}

/**
 * 설정값 저장
 */
function gk_set_config($key, $value) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_config (sc_key, sc_value) VALUES ('" . sql_escape_string($key) . "', '" . sql_escape_string($value) . "')
            ON DUPLICATE KEY UPDATE sc_value = '" . sql_escape_string($value) . "'";
    return sql_query($sql);
}
?>