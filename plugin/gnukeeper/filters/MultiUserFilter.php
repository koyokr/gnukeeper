<?php
/**
 * GnuKeeper 다중 계정 탐지 필터
 */

if (!defined('_GNUBOARD_')) exit;

class GK_MultiUserFilter {

    /**
     * 다중 로그인 체크
     */
    public static function checkMultiLogin($mb_id) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $is_blocking_enabled = GK_Common::get_config('multiuser_login_enabled') == '1';
        
        // 항상 로그인 성공 로그 기록 (OFF 상태에서도)
        $sql = "INSERT INTO " . GK_SECURITY_LOGIN_SUCCESS_LOG_TABLE . "
                (sls_ip, sls_mb_id, sls_datetime)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($mb_id) . "',
                    NOW()
                )";
        sql_query($sql);

        if ($is_blocking_enabled) {
            // ON 상태에서만 차단 로직 실행
            $limit = (int)GK_Common::get_config('multiuser_login_limit') ?: 5;
            $window = (int)GK_Common::get_config('multiuser_login_window') ?: 86400;

            // 동일 IP에서 다른 계정으로 로그인한 횟수 확인
            $sql = "SELECT COUNT(DISTINCT sls_mb_id) as cnt
                    FROM " . GK_SECURITY_LOGIN_SUCCESS_LOG_TABLE . "
                    WHERE sls_ip = '" . sql_escape_string($ip) . "'
                      AND sls_datetime >= DATE_SUB(NOW(), INTERVAL {$window} SECOND)";

            $result = sql_query($sql);
            if ($result && $row = sql_fetch_array($result)) {
                if ($row['cnt'] >= $limit) {
                    // 자동 차단
                    self::auto_block($ip, 'multiuser_login', '다중 계정 로그인 시도');
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 다중 회원가입 체크
     */
    public static function checkMultiRegister($mb_id, $mb_email) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $today = date('Y-m-d');
        
        // 오늘 날짜의 해당 IP 등록 기록 조회
        $check_sql = "SELECT * FROM g5_security_multiuser_log 
                     WHERE smu_ip = '" . sql_escape_string($ip) . "' 
                     AND smu_date = '$today'";
        
        $result = sql_query($check_sql);
        
        if ($result && $row = sql_fetch_array($result)) {
            // 기존 기록이 있으면 업데이트
            $current_count = $row['smu_count'] + 1;
            $member_list = $row['smu_member_list'];
            
            // 새로운 회원 ID를 목록 맨 앞에 추가 (최신 순서)
            if (empty($member_list)) {
                $new_member_list = $mb_id;
            } else {
                $new_member_list = $mb_id . ', ' . $member_list;
            }
            
            $update_sql = "UPDATE g5_security_multiuser_log SET 
                          smu_count = $current_count,
                          smu_member_list = '" . sql_escape_string($new_member_list) . "',
                          smu_last_detected = NOW()
                          WHERE smu_id = " . (int)$row['smu_id'];
            
            sql_query($update_sql);
            
            // 3개 이상이면 차단 여부 확인
            if ($current_count >= 3) {
                $is_blocking_enabled = GK_Common::get_config('multiuser_register_enabled') == '1';
                if ($is_blocking_enabled) {
                    // 자동 차단
                    self::auto_block($ip, 'multiuser_register', "하루에 {$current_count}개 계정 생성");
                }
            }
            
        } else {
            // 새로운 기록 생성
            $insert_sql = "INSERT INTO g5_security_multiuser_log 
                          (smu_ip, smu_date, smu_count, smu_member_list, smu_first_detected, smu_last_detected, smu_blocked)
                          VALUES (
                              '" . sql_escape_string($ip) . "',
                              '$today',
                              1,
                              '" . sql_escape_string($mb_id) . "',
                              NOW(),
                              NOW(),
                              0
                          )";
            
            sql_query($insert_sql);
        }
        
        return true;
    }

    /**
     * 자동 차단
     */
    private static function auto_block($ip, $type, $reason) {
        // GK_SpamDetector를 사용하여 IP 차단 추가
        return GK_SpamDetector::auto_block_ip($ip, 'auto_multiuser', $reason);
    }
}