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
        $ip = $_SERVER['REMOTE_ADDR'];
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $is_blocking_enabled = GK_Common::get_config('behavior_404_enabled') == '1';

        // 항상 404 로그 기록 (OFF 상태에서도)
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

        if ($is_blocking_enabled) {
            // ON 상태에서만 차단 로직 실행
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
        }

        return true;
    }

    /**
     * 레퍼러 체크
     */
    public static function checkReferer($expected_referer) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $actual_referer = $_SERVER['HTTP_REFERER'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_blocking_enabled = GK_Common::get_config('behavior_referer_enabled') == '1';

        // 레퍼러가 예상과 다른 경우
        if (!empty($expected_referer) && $actual_referer != $expected_referer) {
            // 항상 로그 기록 (OFF 상태에서도)
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

            if ($is_blocking_enabled) {
                // ON 상태에서만 차단 처리
                self::auto_block($ip, 'behavior_referer', '비정상적인 레퍼러');
                return false;
            }
        }

        return true;
    }

    /**
     * 비즈니스 로직 기반 Referer 검증
     */
    public static function checkBusinessReferer() {
        // 디버깅 로그 추가
        error_log("checkBusinessReferer() called - " . date('Y-m-d H:i:s'), 3, '/tmp/behavior_debug.log');
        
        $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'];
        $is_blocking_enabled = GK_Common::get_config('behavior_referer_enabled') == '1';
        
        // 상세 정보 로그
        error_log("Script: {$current_script}, Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . ", Referer: {$referer}", 3, '/tmp/behavior_debug.log');
        
        // POST 요청이 아니면 검사하지 않음 (GET은 북마크 등 정상 접근 가능)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }
        
        // Referer 검증이 필요한 페이지 목록
        $referer_required_pages = [
            // 최고 우선순위 - 인증 관련 (즉시 차단)
            '/bbs/login_check.php' => ['priority' => 'HIGH', 'reason' => '로그인 처리'],
            '/bbs/password_check.php' => ['priority' => 'HIGH', 'reason' => '비밀번호 확인'],
            '/bbs/register_form_update.php' => ['priority' => 'HIGH', 'reason' => '회원가입 처리'],
            '/bbs/password_reset_update.php' => ['priority' => 'HIGH', 'reason' => '비밀번호 재설정'],
            
            // 높은 우선순위 - 게시판 작성/수정 (즉시 차단)
            '/bbs/write_update.php' => ['priority' => 'HIGH', 'reason' => '게시글 작성'],
            '/bbs/write_comment_update.php' => ['priority' => 'HIGH', 'reason' => '댓글 작성'],
            '/bbs/qawrite_update.php' => ['priority' => 'HIGH', 'reason' => 'Q&A 작성'],
            '/bbs/memo_form_update.php' => ['priority' => 'HIGH', 'reason' => '쪽지 발송'],
            '/bbs/scrap_popin_update.php' => ['priority' => 'HIGH', 'reason' => '스크랩'],
            '/bbs/good.php' => ['priority' => 'HIGH', 'reason' => '추천'],
            '/bbs/nogood.php' => ['priority' => 'HIGH', 'reason' => '비추천'],
            '/bbs/move_update.php' => ['priority' => 'HIGH', 'reason' => '게시글 이동'],
            
            // 중간 우선순위 - 관리 액션
            '/bbs/member_confirm.php' => ['priority' => 'MEDIUM', 'reason' => '회원정보 수정'],
            '/bbs/register_email_update.php' => ['priority' => 'MEDIUM', 'reason' => '이메일 인증'],
            '/bbs/board_list_update.php' => ['priority' => 'MEDIUM', 'reason' => '게시판 관리'],
            '/bbs/poll_update.php' => ['priority' => 'MEDIUM', 'reason' => '투표 참여'],
            '/bbs/poll_etc_update.php' => ['priority' => 'MEDIUM', 'reason' => '기타 투표'],
            
            // 관리자 전용 (최고 우선순위)
            '/adm/member_form_update.php' => ['priority' => 'HIGH', 'reason' => '회원 관리'],
            '/adm/config_form_update.php' => ['priority' => 'HIGH', 'reason' => '환경설정'],
            '/adm/board_form_update.php' => ['priority' => 'HIGH', 'reason' => '게시판 설정'],
            '/adm/auth_update.php' => ['priority' => 'HIGH', 'reason' => '권한 관리']
        ];
        
        // 현재 페이지가 검증 대상인지 확인
        if (!isset($referer_required_pages[$current_script])) {
            return true; // 검증 대상 아님
        }
        
        $page_info = $referer_required_pages[$current_script];
        
        // Referer 없음 = 의심
        if (empty($referer)) {
            // 로그 기록 (기능 OFF여도 기록)
            self::logSuspiciousReferer($ip, $current_script, '', 'Referer 헤더 없음', $page_info['reason'], $page_info['priority']);
            
            // 활성화 상태에서만 차단 처리
            if ($is_blocking_enabled) {
                // 모든 우선순위에서 즉시 차단 (단순화)
                self::auto_block($ip, 'behavior_referer', $page_info['reason'] . ' - Referer 없음');
                
                // 403 응답 후 종료
                header('HTTP/1.1 403 Forbidden');
                die('Access Denied: Invalid request source');
            }
        }
        
        // 외부 도메인 체크
        if (!empty($referer)) {
            $referer_host = parse_url($referer, PHP_URL_HOST);
            $current_host = $_SERVER['HTTP_HOST'] ?? '';
            
            if ($referer_host !== $current_host && !empty($referer_host)) {
                self::logSuspiciousReferer($ip, $current_script, $referer, '외부 도메인', $page_info['reason'], $page_info['priority']);
                
                if ($is_blocking_enabled) {
                    self::auto_block($ip, 'behavior_referer', $page_info['reason'] . ' - 외부 도메인');
                    header('HTTP/1.1 403 Forbidden');
                    die('Access Denied: External domain not allowed');
                }
            }
        }
        
        return true;
    }
    
    /**
     * Referer 의심 로그 기록
     */
    private static function logSuspiciousReferer($ip, $url, $referer, $issue_type, $reason, $priority) {
        // Referer 로그 테이블에 기록
        $sql = "INSERT INTO " . GK_SECURITY_REFERER_LOG_TABLE . "
                (srl_ip, srl_url, srl_expected_referer, srl_actual_referer, srl_user_agent, srl_datetime)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($url) . "',
                    '" . sql_escape_string('REQUIRED') . "',
                    '" . sql_escape_string($referer) . "',
                    '" . sql_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '') . "',
                    NOW()
                )";
        sql_query($sql);
        
        // 스팸 로그에도 기록 (통합 조회용)
        $log_reason = "비정상 Referer - {$reason} ({$issue_type}) [우선순위: {$priority}]";
        $sql2 = "INSERT INTO " . GK_SECURITY_SPAM_LOG_TABLE . "
                (sl_ip, sl_reason, sl_url, sl_user_agent, sl_datetime)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($log_reason) . "',
                    '" . sql_escape_string($url) . "',
                    '" . sql_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '') . "',
                    NOW()
                )";
        sql_query($sql2);
    }

    /**
     * 차단하지 않고 탐지만 하는 Referer 체크
     */
    public static function checkBusinessRefererWithoutBlock() {
        $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // POST 요청이 아니면 검사하지 않음
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        
        // Referer 검증이 필요한 페이지 목록 (간단 버전)
        $referer_required_pages = [
            '/bbs/login_check.php' => '로그인 처리',
            '/bbs/register_form_update.php' => '회원가입 처리',
            '/bbs/write_update.php' => '게시글 작성',
            '/bbs/write_comment_update.php' => '댓글 작성',
            '/bbs/scrap_popin_update.php' => '스크랩',
        ];
        
        foreach ($referer_required_pages as $page => $reason) {
            if (strpos($current_script, $page) !== false) {
                $issue_type = '';
                
                if (empty($referer)) {
                    $issue_type = 'Referer 없음';
                } else {
                    $parsed_referer = parse_url($referer);
                    $referer_host = $parsed_referer['host'] ?? '';
                    $current_host = $_SERVER['HTTP_HOST'] ?? '';
                    
                    if ($referer_host !== $current_host) {
                        $issue_type = '외부 도메인 Referer';
                    }
                }
                
                if ($issue_type) {
                    // 로그 기록
                    self::log_behavior_detection($ip, $current_script, $issue_type, $referer, $reason);
                    return $issue_type . ' (' . $reason . ')';
                }
            }
        }
        
        return false;
    }

    /**
     * 행동 탐지 로그 기록
     */
    private static function log_behavior_detection($ip, $url, $issue_type, $referer, $reason) {
        // 비정상 행동 로그에 기록
        $sql = "INSERT INTO " . GK_SECURITY_BEHAVIOR_LOG_TABLE . "
                (sbl_ip, sbl_url, sbl_type, sbl_referer, sbl_user_agent, sbl_datetime)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($url) . "',
                    '" . sql_escape_string($issue_type) . "',
                    '" . sql_escape_string($referer) . "',
                    '" . sql_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '') . "',
                    NOW()
                )";
        sql_query($sql);
        
        // 스팸 로그에도 기록
        $log_reason = "비정상 행동 탐지 - {$reason} ({$issue_type})";
        $sql2 = "INSERT INTO " . GK_SECURITY_SPAM_LOG_TABLE . "
                (sl_ip, sl_reason, sl_url, sl_user_agent, sl_datetime)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($log_reason) . "',
                    '" . sql_escape_string($url) . "',
                    '" . sql_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '') . "',
                    NOW()
                )";
        sql_query($sql2);
    }

    /**
     * 자동 차단
     */
    private static function auto_block($ip, $type, $reason) {
        // GK_SpamDetector를 사용하여 IP 차단 추가
        return GK_SpamDetector::auto_block_ip($ip, 'auto_behavior', $reason);
    }
}