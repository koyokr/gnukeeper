<?php
/**
 * GnuKeeper User-Agent 기반 필터
 */

if (!defined('_GNUBOARD_')) exit;

class GK_UserAgentFilter {

    // 차단할 User-Agent 패턴
    private static $blocked_patterns = array(
        '/bot/i',
        '/crawler/i',
        '/spider/i',
        '/scraper/i',
        '/curl/i',
        '/wget/i',
        '/python/i',
        '/java/i',
        '/perl/i',
        '/ruby/i'
    );

    // 허용할 봇 (검색엔진 등)
    private static $allowed_bots = array(
        'Googlebot',
        'bingbot',
        'Yeti', // Naver
        'Daum',
        'facebookexternalhit',
        'kakaotalk'
    );

    /**
     * User-Agent 체크
     */
    public static function check() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_blocking_enabled = GK_Common::get_config('useragent_block_enabled') == '1';
        
        // 디버깅 로그
        gk_log("UserAgentFilter::check() called - UA: {$user_agent}, blocking_enabled: " . ($is_blocking_enabled ? 'YES' : 'NO'));

        // User-Agent가 없는 경우
        if (empty($user_agent)) {
            return self::handle_detection('Empty User-Agent', $is_blocking_enabled);
        }

        // 허용된 봇인지 확인
        foreach (self::$allowed_bots as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                return true;
            }
        }

        // 차단 패턴 확인
        foreach (self::$blocked_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return self::handle_detection($user_agent, $is_blocking_enabled);
            }
        }

        return true;
    }

    /**
     * 탐지 처리 (차단 여부에 따라 다르게 동작)
     */
    private static function handle_detection($user_agent, $is_blocking_enabled) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $reason = 'User-Agent 필터 탐지: ' . substr($user_agent, 0, 100);

        // 항상 데이터베이스에 로그 기록 (OFF 상태에서도)
        $sql = "INSERT INTO " . GK_SECURITY_SPAM_LOG_TABLE . "
                (sl_ip, sl_reason, sl_url, sl_user_agent, sl_datetime)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($reason) . "',
                    '" . sql_escape_string($url) . "',
                    '" . sql_escape_string($user_agent) . "',
                    NOW()
                )";
        sql_query($sql);

        // 파일 로그도 기록
        gk_log("Detected User-Agent: {$user_agent} from IP: {$ip}");

        if ($is_blocking_enabled) {
            // ON 상태: 로그 + 차단 리스트 추가 + 접속 차단
            GK_SpamDetector::auto_block_ip($ip, 'auto_useragent', $reason);
            header('HTTP/1.1 403 Forbidden');
            die('Access Denied');
        } else {
            // OFF 상태: 로그만 기록, 접속은 허용
            return true;
        }
    }
}