<?php
/**
 * GnuKeeper 공통 유틸리티 클래스
 */

if (!defined('_GNUBOARD_')) exit;

class GK_Common {

    /**
     * CIDR 파싱
     */
    public static function parse_cidr($cidr) {
        // 단일 IP인 경우 /32 자동 추가
        if (!strpos($cidr, '/')) {
            $cidr = $cidr . '/32';
        }

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
    public static function set_config($key, $value) {
        $sql = "INSERT INTO " . GK_SECURITY_CONFIG_TABLE . " (sc_key, sc_value, sc_datetime)
                VALUES ('" . sql_escape_string($key) . "', '" . sql_escape_string($value) . "', NOW())
                ON DUPLICATE KEY UPDATE sc_value = '" . sql_escape_string($value) . "', sc_datetime = NOW()";
        return sql_query($sql);
    }

    /**
     * 설정값 가져오기
     */
    public static function get_config($key = null) {
        static $config = null;

        if ($config === null) {
            $config = array();
            $sql = "SELECT sc_key, sc_value FROM " . GK_SECURITY_CONFIG_TABLE;
            $result = sql_query($sql, false);

            if ($result) {
                while ($row = sql_fetch_array($result)) {
                    $config[$row['sc_key']] = $row['sc_value'];
                }
            }
        }

        if ($key !== null) {
            return isset($config[$key]) ? $config[$key] : null;
        }

        return $config;
    }

    /**
     * 테이블 존재 여부 확인
     */
    public static function check_tables_exist() {
        static $tables_exist = null;

        if ($tables_exist === null) {
            $sql = "SHOW TABLES LIKE '" . GK_SECURITY_CONFIG_TABLE . "'";
            $result = sql_query($sql, false);
            $tables_exist = ($result && sql_num_rows($result) > 0);
        }

        return $tables_exist;
    }
}