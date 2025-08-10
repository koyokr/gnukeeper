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
        // User-Agent 차단 활성화 확인
        if (GK_Common::get_config('useragent_block_enabled') != '1') {
            return true;
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // User-Agent가 없는 경우
        if (empty($user_agent)) {
            return self::handle_block('Empty User-Agent');
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
                return self::handle_block($user_agent);
            }
        }

        return true;
    }

    /**
     * 차단 처리
     */
    private static function handle_block($user_agent) {
        $ip = $_SERVER['REMOTE_ADDR'];

        // 로그 기록
        gk_log("Blocked User-Agent: {$user_agent} from IP: {$ip}");

        // 자동 IP 차단 추가
        $reason = 'User-Agent 필터 차단: ' . substr($user_agent, 0, 100);
        GK_SpamDetector::auto_block_ip($ip, 'auto_useragent', $reason);

        // 무조건 접속 차단
        header('HTTP/1.1 403 Forbidden');
        die('Access Denied');
    }
}