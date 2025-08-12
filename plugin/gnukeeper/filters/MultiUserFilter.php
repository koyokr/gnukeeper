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
        // 이미 GK_SpamDetector::checkRegistration()에서 처리
        // 필요시 추가 로직 구현
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