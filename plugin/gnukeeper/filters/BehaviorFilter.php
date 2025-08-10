<?php
/**
 * GnuKeeper 이상 행위 탐지 필터
 */

if (!defined('_GNUBOARD_')) exit;

class GK_BehaviorFilter {

    /**
     * 404 에러 체크
     */
    public static function check404() {
        // 404 탐지 활성화 확인
        if (GK_Common::get_config('behavior_404_enabled') != '1') {
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        // 404 로그 기록
        $sql = "INSERT INTO " . GK_SECURITY_404_LOG_TABLE . "
                (sl4_ip, sl4_url, sl4_user_agent, sl4_referer, sl4_datetime)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($url) . "',
                    '" . sql_escape_string($user_agent) . "',
                    '" . sql_escape_string($referer) . "',
                    NOW()
                )";
        sql_query($sql);

        // 404 횟수 확인
        $limit = (int)GK_Common::get_config('behavior_404_limit') ?: 10;
        $window = (int)GK_Common::get_config('behavior_404_window') ?: 300;

        $sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_404_LOG_TABLE . "
                WHERE sl4_ip = '" . sql_escape_string($ip) . "'
                  AND sl4_datetime >= DATE_SUB(NOW(), INTERVAL {$window} SECOND)";

        $result = sql_query($sql);
        if ($result && $row = sql_fetch_array($result)) {
            if ($row['cnt'] >= $limit) {
                // 자동 차단
                self::auto_block($ip, 'behavior_404', '과도한 404 에러 발생');
                return false;
            }
        }

        return true;
    }

    /**
     * 레퍼러 체크
     */
    public static function checkReferer($expected_referer) {
        // 레퍼러 검증 활성화 확인
        if (GK_Common::get_config('behavior_referer_enabled') != '1') {
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $actual_referer = $_SERVER['HTTP_REFERER'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // 레퍼러가 예상과 다른 경우
        if (!empty($expected_referer) && $actual_referer != $expected_referer) {
            // 로그 기록
            $sql = "INSERT INTO " . GK_SECURITY_REFERER_LOG_TABLE . "
                    (srl_ip, srl_url, srl_expected_referer, srl_actual_referer, srl_user_agent, srl_datetime)
                    VALUES (
                        '" . sql_escape_string($ip) . "',
                        '" . sql_escape_string($url) . "',
                        '" . sql_escape_string($expected_referer) . "',
                        '" . sql_escape_string($actual_referer) . "',
                        '" . sql_escape_string($user_agent) . "',
                        NOW()
                    )";
            sql_query($sql);

            // 차단 처리
            self::auto_block($ip, 'behavior_referer', '비정상적인 레퍼러');
            return false;
        }

        return true;
    }

    /**
     * 자동 차단
     */
    private static function auto_block($ip, $type, $reason) {
        // GK_SpamDetector를 사용하여 IP 차단 추가
        return GK_SpamDetector::auto_block_ip($ip, 'auto_behavior', $reason);
    }
}