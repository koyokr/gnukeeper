<?php
/**
 * User-Agent 기반 스팸 필터 플러그인
 * gnuboard5 extend 파일
 *
 * 의심스러운 User-Agent를 감지하여 자동으로 IP를 차단합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 관리자는 차단에서 제외
if (isset($member) && $member['mb_level'] >= 10) {
    return;
}

// 현재 IP 및 User-Agent 확인
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (empty($current_ip) || empty($user_agent)) {
    return;
}

// IP 유효성 검사
if (!filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return;
}

// 테이블 존재 여부 및 기능 활성화 확인
if (!security_useragent_check_tables_exist() || !security_useragent_is_enabled()) {
    return;
}

// 예외 IP(화이트리스트) 확인
if (security_useragent_is_whitelisted($current_ip)) {
    return;
}

// User-Agent 검증
if (security_useragent_is_suspicious($user_agent)) {
    // 차단 수준 확인
    $block_level = security_useragent_get_block_level();
    $block_levels = explode(',', $block_level);
    
    // 자동 차단 추가
    security_useragent_add_ip_block($current_ip, $user_agent, $block_level);
    
    // 접속 차단인 경우 즉시 차단 페이지 표시
    if (in_array('access', $block_levels)) {
        security_useragent_show_blocked_page($user_agent, $current_ip);
        exit;
    }
    
    // 그 외 차단 수준은 전역 변수로 설정
    global $g5_useragent_block_levels;
    $g5_useragent_block_levels = $block_levels;
}

/**
 * 테이블 존재 여부 확인
 */
function security_useragent_check_tables_exist() {
    static $tables_exist = null;

    if ($tables_exist === null) {
        $sql = "SHOW TABLES LIKE '" . G5_TABLE_PREFIX . "security_config'";
        $result = sql_query($sql, false);
        $tables_exist = ($result && sql_num_rows($result) > 0);
    }

    return $tables_exist;
}

/**
 * User-Agent 차단 기능 활성화 여부 확인
 */
function security_useragent_is_enabled() {
    static $is_enabled = null;

    if ($is_enabled === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'useragent_block_enabled'";
        $result = sql_query($sql, false);

        if ($result && $row = sql_fetch_array($result)) {
            $is_enabled = ($row['sc_value'] == '1');
        } else {
            $is_enabled = false; // 기본값: 비활성화
        }
    }

    return $is_enabled;
}

/**
 * IP가 예외 IP(화이트리스트)에 있는지 확인
 */
function security_useragent_is_whitelisted($ip) {
    $sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist
            WHERE sw_ip = '" . sql_escape_string($ip) . "'";

    $result = sql_query($sql, false);
    if ($result && $row = sql_fetch_array($result)) {
        return $row['cnt'] > 0;
    }

    return false;
}

/**
 * User-Agent 차단 수준 가져오기
 */
function security_useragent_get_block_level() {
    static $block_level = null;
    
    if ($block_level === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'useragent_block_level'";
        $result = sql_query($sql, false);
        
        if ($result && $row = sql_fetch_array($result)) {
            $block_level = $row['sc_value'];
        } else {
            $block_level = 'access'; // 기본값: 접속 차단
        }
    }
    
    return $block_level;
}

/**
 * 의심스러운 User-Agent인지 검사
 */
function security_useragent_is_suspicious($user_agent) {
    // 빈 User-Agent는 의심스러움
    if (empty(trim($user_agent))) {
        return true;
    }
    
    // 검색 봇 사칭 검증
    if (security_useragent_is_bot_impersonation($user_agent)) {
        return true; // 봇 사칭으로 판단
    }
    
    // 브라우저 사칭 검증
    if (security_useragent_is_browser_impersonation($user_agent)) {
        return true; // 브라우저 사칭으로 판단
    }
    
    // 허용할 봇들 (검색엔진, 소셜미디어 등) - 사칭 검증을 통과한 것만
    $allowed_bots = [
        'Googlebot',
        'Bingbot', 
        'Slurp',           // Yahoo
        'DuckDuckBot',     // DuckDuckGo
        'Baiduspider',     // Baidu
        'YandexBot',       // Yandex
        'facebookexternalhit', // Facebook
        'Twitterbot',      // Twitter
        'LinkedInBot',     // LinkedIn
        'WhatsApp',        // WhatsApp
        'Applebot',        // Apple
        'ia_archiver',     // Alexa
        'SeznamBot',       // Seznam
        'NaverBot',        // Naver
        'DaumSearch',      // Daum
        'coccocbot',       // Coccoc
        'SiteAuditBot',    // SiteAudit
        'UptimeRobot',     // Uptime monitoring
        'pingdom',         // Pingdom monitoring
    ];
    
    // 허용된 봇인지 확인 (이미 사칭 검증을 통과함)
    foreach ($allowed_bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            return false;
        }
    }
    
    // 일반적인 브라우저 패턴 확인
    $browser_patterns = [
        '/Mozilla.*Chrome/i',
        '/Mozilla.*Firefox/i',
        '/Mozilla.*Safari/i',
        '/Mozilla.*Edge/i',
        '/Opera/i',
        '/Mozilla.*MSIE/i',
        '/Mozilla.*Trident/i',  // IE 11+
        '/Mobile.*Safari/i',     // 모바일 Safari
        '/Android.*Chrome/i',    // 안드로이드 Chrome
        '/iPhone.*Safari/i',     // iPhone Safari
        '/iPad.*Safari/i',       // iPad Safari
    ];
    
    foreach ($browser_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return false; // 정상 브라우저로 판단
        }
    }
    
    // 의심스러운 패턴들
    $suspicious_patterns = [
        '/^curl/i',
        '/^wget/i',
        '/^python/i',
        '/^php/i',
        '/^java/i',
        '/^perl/i',
        '/^ruby/i',
        '/^go-http-client/i',
        '/^node/i',
        '/^axios/i',
        '/^requests/i',
        '/^scrapy/i',
        '/^selenium/i',
        '/^phantomjs/i',
        '/^headlesschrome/i',
        '/^bot/i',
        '/^crawler/i',
        '/^spider/i',
        '/^scraper/i',
        '/^harvest/i',
        '/^extract/i',
        '/^libwww/i',
        '/^lwp/i',
        '/masscan/i',
        '/nmap/i',
        '/sqlmap/i',
        '/nikto/i',
        '/acunetix/i',
        '/^-$/i',              // 단순히 '-'인 경우
        '/^\s*$/i',            // 공백만 있는 경우
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return true;
        }
    }
    
    // User-Agent가 너무 짧은 경우 (10자 미만)
    if (strlen(trim($user_agent)) < 10) {
        return true;
    }
    
    // User-Agent에 일반적이지 않은 문자가 많은 경우
    if (preg_match_all('/[^a-zA-Z0-9\s\.\-_\/\(\)\[\];:,]/', $user_agent) > 5) {
        return true;
    }
    
    return false; // 정상으로 판단
}

/**
 * 검색 봇 사칭인지 검증
 */
function security_useragent_is_bot_impersonation($user_agent) {
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Googlebot 사칭 검증
    if (stripos($user_agent, 'Googlebot') !== false) {
        return !security_useragent_verify_googlebot($current_ip, $user_agent);
    }
    
    // Bingbot 사칭 검증
    if (stripos($user_agent, 'bingbot') !== false) {
        return !security_useragent_verify_bingbot($current_ip);
    }
    
    // Yahoo Slurp 사칭 검증
    if (stripos($user_agent, 'Slurp') !== false) {
        return !security_useragent_verify_yahoo_slurp($current_ip);
    }
    
    return false; // 특별한 검증이 필요한 봇이 아님
}

/**
 * 브라우저 사칭인지 검증
 */
function security_useragent_is_browser_impersonation($user_agent) {
    // 일반적인 브라우저 패턴이 있는지 확인
    $browser_patterns = [
        '/Mozilla.*Chrome/i',
        '/Mozilla.*Firefox/i', 
        '/Mozilla.*Safari/i',
        '/Mozilla.*Edge/i',
    ];
    
    $has_browser_pattern = false;
    foreach ($browser_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            $has_browser_pattern = true;
            break;
        }
    }
    
    if (!$has_browser_pattern) {
        return false; // 브라우저 패턴이 없으면 사칭 검증 불필요
    }
    
    // 브라우저 패턴이 있지만 실제로는 자동화 도구인 경우들
    $automation_indicators = [
        '/python/i',
        '/curl/i', 
        '/wget/i',
        '/scrapy/i',
        '/selenium/i',
        '/phantomjs/i',
        '/headless/i',
        '/automation/i',
        '/bot/i',
        '/crawler/i',
        '/spider/i',
    ];
    
    foreach ($automation_indicators as $indicator) {
        if (preg_match($indicator, $user_agent)) {
            return true; // 브라우저 사칭으로 판단
        }
    }
    
    // Accept 헤더 검증 (실제 브라우저는 복잡한 Accept 헤더를 보냄)
    $accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (empty($accept_header) || $accept_header === '*/*' || strlen($accept_header) < 20) {
        return true; // 간단한 Accept 헤더는 사칭 가능성 높음
    }
    
    // Accept-Language 헤더 검증
    $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (empty($accept_language)) {
        return true; // 브라우저는 보통 Accept-Language 헤더를 보냄
    }
    
    return false; // 정상 브라우저로 판단
}

/**
 * Googlebot 검증 (DNS 역조회)
 */
function security_useragent_verify_googlebot($ip, $user_agent) {
    // Googlebot User-Agent 패턴 검증
    if (!preg_match('/Googlebot\/[0-9.]+/', $user_agent)) {
        return false; // 잘못된 Googlebot User-Agent 형식
    }
    
    // DNS 역조회로 호스트명 확인
    $hostname = gethostbyaddr($ip);
    
    // 구글 도메인인지 확인
    if (!$hostname || $hostname === $ip) {
        return false; // 역조회 실패
    }
    
    // 구글 크롤러 도메인 패턴 확인
    $google_patterns = [
        '/\.googlebot\.com$/',
        '/\.google\.com$/',
        '/\.googleapis\.com$/',
    ];
    
    foreach ($google_patterns as $pattern) {
        if (preg_match($pattern, $hostname)) {
            // 정방향 조회로 재검증
            $resolved_ip = gethostbyname($hostname);
            return ($resolved_ip === $ip);
        }
    }
    
    return false; // 구글 도메인이 아님
}

/**
 * Bingbot 검증
 */
function security_useragent_verify_bingbot($ip) {
    $hostname = gethostbyaddr($ip);
    
    if (!$hostname || $hostname === $ip) {
        return false;
    }
    
    // Bing 크롤러 도메인 패턴
    if (preg_match('/\.search\.msn\.com$/', $hostname)) {
        $resolved_ip = gethostbyname($hostname);
        return ($resolved_ip === $ip);
    }
    
    return false;
}

/**
 * Yahoo Slurp 검증
 */
function security_useragent_verify_yahoo_slurp($ip) {
    $hostname = gethostbyaddr($ip);
    
    if (!$hostname || $hostname === $ip) {
        return false;
    }
    
    // Yahoo 크롤러 도메인 패턴
    $yahoo_patterns = [
        '/\.crawl\.yahoo\.net$/',
        '/\.yahoo\.com$/',
    ];
    
    foreach ($yahoo_patterns as $pattern) {
        if (preg_match($pattern, $hostname)) {
            $resolved_ip = gethostbyname($hostname);
            return ($resolved_ip === $ip);
        }
    }
    
    return false;
}

/**
 * IP 자동 차단 추가
 */
function security_useragent_add_ip_block($ip, $user_agent, $block_level) {
    // 이미 차단된 IP인지 확인
    $ip_long = sprintf('%u', ip2long($ip));

    $existing_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block
                     WHERE sb_status = 'active'
                       AND {$ip_long} BETWEEN sb_start_ip AND sb_end_ip";

    $existing_result = sql_query($existing_sql, false);
    if ($existing_result) {
        $existing_row = sql_fetch_array($existing_result);
        if ($existing_row['cnt'] > 0) {
            return; // 이미 차단된 IP
        }
    }

    // 차단 사유 생성
    $short_ua = substr($user_agent, 0, 100);
    $reason = "의심스러운 User-Agent 감지: " . $short_ua;

    // IP 차단 추가 (영구 차단)
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_block SET
                sb_ip = '" . sql_escape_string($ip) . "',
                sb_start_ip = {$ip_long},
                sb_end_ip = {$ip_long},
                sb_reason = '" . sql_escape_string($reason) . "',
                sb_block_type = 'auto_abuse',
                sb_block_level = '" . sql_escape_string($block_level) . "',
                sb_duration = 'permanent',
                sb_hit_count = 0,
                sb_status = 'active',
                sb_datetime = NOW()";

    sql_query($sql, false);

    // 로그 기록
    $log_sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_log SET
                    sl_ip = '" . sql_escape_string($ip) . "',
                    sl_datetime = NOW(),
                    sl_url = '" . sql_escape_string($_SERVER['REQUEST_URI'] ?? '/') . "',
                    sl_user_agent = '" . sql_escape_string($user_agent) . "',
                    sl_block_reason = 'User-Agent 자동 차단: " . sql_escape_string($short_ua) . "'";

    sql_query($log_sql, false);
}

/**
 * 차단 페이지 표시
 */
function security_useragent_show_blocked_page($user_agent, $ip) {
    // HTTP 403 상태 코드 전송
    http_response_code(403);

    $short_ua = htmlspecialchars(substr($user_agent, 0, 200));

    echo "<!DOCTYPE html>
<html lang=\"ko\">
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>User-Agent 차단</title>
    <style>
        body {
            font-family: 'Malgun Gothic', '맑은 고딕', sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .block-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .block-icon {
            font-size: 3em;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .block-title {
            color: #dc3545;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .block-message {
            color: #555;
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 25px;
        }
        .block-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
            border-left: 4px solid #dc3545;
        }
        .block-info-item {
            margin: 8px 0;
            color: #555;
            font-size: 14px;
            word-break: break-all;
        }
        .block-info-label {
            font-weight: bold;
            color: #333;
            display: inline-block;
            width: 100px;
        }
        .contact-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
            color: #0056b3;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class=\"block-container\">
        <div class=\"block-icon\">🤖</div>
        <h1 class=\"block-title\">의심스러운 접근이 차단되었습니다</h1>

        <div class=\"block-message\">
            귀하의 User-Agent가 의심스러운 봇이나 자동화 도구로 판단되어 접근이 제한되었습니다.
        </div>

        <div class=\"block-info\">
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 IP:</span> {$ip}
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">User-Agent:</span> {$short_ua}
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 시간:</span> " . date('Y-m-d H:i:s') . "
            </div>
        </div>

        <div class=\"contact-info\">
            <strong>정상적인 브라우저를 사용 중이신가요?</strong><br>
            일반적인 웹 브라우저(Chrome, Firefox, Safari, Edge 등)를 사용해주세요.<br>
            문제가 지속되면 사이트 관리자에게 문의해주세요.
        </div>
    </div>
</body>
</html>";
}

// User-Agent 차단 수준별 처리 (전역 변수 설정된 경우)
if (isset($g5_useragent_block_levels)) {
    // 로그인 페이지에서 차단
    if (in_array('login', $g5_useragent_block_levels) && basename($_SERVER['SCRIPT_NAME']) == 'login_check.php') {
        alert('의심스러운 User-Agent로 인해 로그인이 제한됩니다.');
        exit;
    }
    
    // 게시글/댓글 작성 페이지에서 차단
    if (in_array('write', $g5_useragent_block_levels)) {
        $write_pages = ['write.php', 'write_update.php', 'write_comment_update.php'];
        if (in_array(basename($_SERVER['SCRIPT_NAME']), $write_pages)) {
            alert('의심스러운 User-Agent로 인해 게시글/댓글 작성이 제한됩니다.');
            exit;
        }
    }
    
    // 쪽지 작성 페이지에서 차단
    if (in_array('memo', $g5_useragent_block_levels)) {
        $memo_pages = ['memo_form.php', 'memo_form_update.php'];
        if (in_array(basename($_SERVER['SCRIPT_NAME']), $memo_pages)) {
            alert('의심스러운 User-Agent로 인해 쪽지 작성이 제한됩니다.');
            exit;
        }
    }
}
?>