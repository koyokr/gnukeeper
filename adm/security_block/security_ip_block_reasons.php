<?php
if (!defined('_GNUBOARD_')) exit;

// IP 차단 사유 관리 클래스
class GK_IPBlockReasons {
    
    // 차단 사유를 저장
    public static function saveReason($ip, $reason, $block_type = 'manual') {
        global $g5;
        
        if (empty($ip) || empty($reason)) {
            return false;
        }
        
        $table = G5_TABLE_PREFIX . 'security_ip_block';
        
        // 테이블 존재 체크
        if (!self::checkTableExists()) {
            return false;
        }
        
        // IP 범위 계산 (단순 IP일 경우)
        $start_ip = $end_ip = self::ipToLong($ip);
        
        // 이미 존재하는지 체크
        $sql = "SELECT sb_id FROM {$table} WHERE sb_ip = '" . sql_escape_string($ip) . "'";
        $existing = sql_fetch($sql);
        
        if ($existing) {
            // 업데이트
            $sql = "UPDATE {$table} SET 
                        sb_reason = '" . sql_escape_string($reason) . "',
                        sb_block_type = '" . sql_escape_string($block_type) . "'
                    WHERE sb_ip = '" . sql_escape_string($ip) . "'";
        } else {
            // 새로 추가
            $sql = "INSERT INTO {$table} SET
                        sb_ip = '" . sql_escape_string($ip) . "',
                        sb_start_ip = {$start_ip},
                        sb_end_ip = {$end_ip},
                        sb_reason = '" . sql_escape_string($reason) . "',
                        sb_block_type = '" . sql_escape_string($block_type) . "',
                        sb_datetime = NOW()";
        }
        
        return sql_query($sql);
    }
    
    // 차단 사유 조회
    public static function getReason($ip) {
        global $g5;
        
        if (!self::checkTableExists()) {
            return '';
        }
        
        $table = G5_TABLE_PREFIX . 'security_ip_block';
        $sql = "SELECT sb_reason FROM {$table} WHERE sb_ip = '" . sql_escape_string($ip) . "'";
        $row = sql_fetch($sql);
        
        return $row ? $row['sb_reason'] : '';
    }
    
    // 차단 정보 조회 (사유 + 등록일)
    public static function getBlockInfo($ip) {
        global $g5;
        
        if (!self::checkTableExists()) {
            return null;
        }
        
        $table = G5_TABLE_PREFIX . 'security_ip_block';
        $sql = "SELECT sb_reason, sb_datetime FROM {$table} WHERE sb_ip = '" . sql_escape_string($ip) . "'";
        $row = sql_fetch($sql);
        
        return $row ? $row : null;
    }
    
    // 모든 차단 사유 조회 (IP를 키로 하는 배열)
    public static function getAllReasons() {
        global $g5;
        
        if (!self::checkTableExists()) {
            return array();
        }
        
        $table = G5_TABLE_PREFIX . 'security_ip_block';
        $sql = "SELECT sb_ip, sb_reason FROM {$table}";
        $result = sql_query($sql);
        
        $reasons = array();
        while ($row = sql_fetch_array($result)) {
            $reasons[$row['sb_ip']] = $row['sb_reason'];
        }
        
        return $reasons;
    }
    
    // IP 차단 삭제 시 사유도 삭제
    public static function deleteReason($ip) {
        global $g5;
        
        if (!self::checkTableExists()) {
            return true; // 테이블이 없으면 성공으로 처리
        }
        
        $table = G5_TABLE_PREFIX . 'security_ip_block';
        $sql = "DELETE FROM {$table} WHERE sb_ip = '" . sql_escape_string($ip) . "'";
        
        return sql_query($sql);
    }
    
    // 테이블 존재 여부 체크
    private static function checkTableExists() {
        static $table_exists = null;
        
        if ($table_exists === null) {
            $table = G5_TABLE_PREFIX . 'security_ip_block';
            $sql = "SHOW TABLES LIKE '{$table}'";
            $result = sql_query($sql, false);
            $table_exists = ($result && sql_num_rows($result) > 0);
        }
        
        return $table_exists;
    }
    
    // IP를 숫자로 변환 (안전 처리)
    private static function ipToLong($ip) {
        // 와일드카드 처리
        if (strpos($ip, '+') !== false) {
            $ip = str_replace('+', '0', $ip); // 임시로 0으로 변환
        }
        
        $long = ip2long($ip);
        if ($long === false) {
            return 0;
        }
        
        return sprintf('%u', $long);
    }
}
?>