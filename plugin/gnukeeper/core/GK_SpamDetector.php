<?php
/**
 * GnuKeeper 스팸 탐지 클래스
 */

if (!defined('_GNUBOARD_')) exit;

class GK_SpamDetector {

    private static $instance = null;
    private static $filters = array();

    /**
     * 싱글톤 인스턴스 반환
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 필터 등록
     */
    public static function registerFilter($name, $callback) {
        self::$filters[$name] = $callback;
    }

    /**
     * 콘텐츠 체크 (게시글/댓글 작성시 호출)
     */
    public static function checkContent($board, $wr_id) {
        // 스팸 탐지 활성화 확인
        if (!self::is_enabled()) {
            return true;
        }

        // 등록된 필터들 실행
        foreach (self::$filters as $name => $callback) {
            if (is_callable($callback)) {
                $result = call_user_func($callback, $board, $wr_id);
                if ($result === false) {
                    // 스팸으로 탐지됨
                    self::log_spam_detection($name, $board, $wr_id);
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 로그인 시도 체크
     */
    public static function checkLoginAttempt($mb_id, $result) {
        global $g5;

        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $url = $_SERVER['REQUEST_URI'] ?? '';

        if (!$result) {
            // 로그인 실패 기록 (항상 기록)
            $sql = "INSERT INTO " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                    (slf_ip, slf_mb_id, slf_datetime, slf_url, slf_user_agent)
                    VALUES (
                        '" . sql_escape_string($ip) . "',
                        '" . sql_escape_string($mb_id) . "',
                        NOW(),
                        '" . sql_escape_string($url) . "',
                        '" . sql_escape_string($user_agent) . "'
                    )";
            sql_query($sql);

            // 로그인 실패 차단이 활성화된 경우에만 차단 처리
            if (GK_Common::get_config('login_block_enabled') == '1') {
                // 실패 횟수 확인
                $attempt_limit = (int)GK_Common::get_config('login_attempt_limit') ?: 5;
                $attempt_window = (int)GK_Common::get_config('login_attempt_window') ?: 300;

                $sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                        WHERE slf_ip = '" . sql_escape_string($ip) . "'
                          AND slf_datetime >= DATE_SUB(NOW(), INTERVAL {$attempt_window} SECOND)";

                $result = sql_query($sql);
                if ($result && $row = sql_fetch_array($result)) {
                    if ($row['cnt'] >= $attempt_limit) {
                        // 자동 차단 추가
                        self::auto_block_ip($ip, 'auto_login', '과도한 로그인 시도');
                        return false;
                    }
                }
            }
        } else {
            // 로그인 성공 기록
            $sql = "INSERT INTO " . GK_SECURITY_LOGIN_SUCCESS_LOG_TABLE . "
                    (sls_ip, sls_mb_id, sls_user_agent, sls_datetime)
                    VALUES (
                        '" . sql_escape_string($ip) . "',
                        '" . sql_escape_string($mb_id) . "',
                        '" . sql_escape_string($user_agent) . "',
                        NOW()
                    )";
            sql_query($sql);
        }

        return true;
    }

    /**
     * 회원가입 체크
     */
    public static function checkRegistration($mb_id, $mb_email) {
        // 다중 계정 차단 활성화 확인
        if (GK_Common::get_config('multiuser_register_enabled') != '1') {
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // 회원가입 시도 기록
        $sql = "INSERT INTO " . GK_SECURITY_REGISTER_LOG_TABLE . "
                (srl_ip, srl_mb_id, srl_mb_email, srl_user_agent, srl_datetime, srl_status)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($mb_id) . "',
                    '" . sql_escape_string($mb_email) . "',
                    '" . sql_escape_string($user_agent) . "',
                    NOW(),
                    'success'
                )";
        sql_query($sql);

        // 동일 IP 가입 횟수 확인
        $register_limit = (int)GK_Common::get_config('multiuser_register_limit') ?: 3;
        $register_window = (int)GK_Common::get_config('multiuser_register_window') ?: 86400;

        $sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_REGISTER_LOG_TABLE . "
                WHERE srl_ip = '" . sql_escape_string($ip) . "'
                  AND srl_datetime >= DATE_SUB(NOW(), INTERVAL {$register_window} SECOND)
                  AND srl_status = 'success'";

        $result = sql_query($sql);
        if ($result && $row = sql_fetch_array($result)) {
            if ($row['cnt'] >= $register_limit) {
                // 자동 차단 추가
                self::auto_block_ip($ip, 'auto_spam', '다중 계정 생성 시도');
                return false;
            }
        }

        return true;
    }

    /**
     * IP 자동 차단
     */
    public static function auto_block_ip($ip, $block_type, $reason) {
        // GK_BlockManager를 사용하여 IP 차단 추가
        return GK_BlockManager::add_block($ip, $reason, $block_type);
    }

    /**
     * 스팸 탐지 로그 기록
     */
    private static function log_spam_detection($filter_name, $board, $wr_id) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // 로그 기록 (필요시 구현)
        gk_log("Spam detected by {$filter_name} filter: Board={$board['bo_table']}, WR_ID={$wr_id}, IP={$ip}");
    }

    /**
     * 스팸 탐지 활성화 여부 확인
     */
    private static function is_enabled() {
        // 각 필터별 활성화 상태 확인
        return true; // 기본적으로 활성화
    }
}