<?php
/**
 * GnuKeeper IP 차단 관리 클래스
 */

if (!defined('_GNUBOARD_')) exit;

class GK_BlockManager {

    private static $instance = null;
    private static $cache = array();

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
     * 현재 IP 차단 체크 (메인 진입점)
     */
    public static function checkCurrentIP() {
        global $member, $g5_security_block_info, $g5_security_block_levels;

        // 관리자는 차단에서 제외
        if (isset($member) && $member['mb_level'] >= 10) {
            return;
        }

        // 현재 접속자 IP 주소 확인
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // IP 유효성 검사
        if (empty($current_ip) || !filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return;
        }

        // localhost IP는 절대 차단하지 않음 (안전 장치)
        if (self::is_localhost_ip($current_ip)) {
            return;
        }

        // 테이블 존재 여부 및 기능 활성화 확인
        if (!GK_Common::check_tables_exist() || !self::is_enabled()) {
            return;
        }

        // 예외 IP(화이트리스트) 확인 (우선 처리)
        if (self::is_whitelisted($current_ip)) {
            return;
        }

        // IP 차단 확인
        $block_info = self::get_block_info($current_ip);
        if ($block_info) {
            // 차단된 IP는 무조건 접속 차단
            // 차단 로그 기록
            self::log_blocked_access($current_ip, $block_info);

            // 차단 페이지 표시 후 종료
            self::show_blocked_page($block_info, $current_ip);
            exit;
        }
    }

    /**
     * IP 차단 기능 활성화 여부 확인
     */
    public static function is_enabled() {
        static $is_enabled = null;

        if ($is_enabled === null) {
            $value = GK_Common::get_config('ip_block_enabled');
            $is_enabled = ($value === null || $value == '1'); // 기본값 true
        }

        return $is_enabled;
    }

    /**
     * IP가 예외 IP(화이트리스트)에 있는지 확인
     */
    public static function is_whitelisted($ip) {
        // 캐시 확인
        if (isset(self::$cache['whitelist'][$ip])) {
            return self::$cache['whitelist'][$ip];
        }

        $sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_IP_WHITELIST_TABLE . "
                WHERE sw_ip = '" . sql_escape_string($ip) . "'";

        $result = sql_query($sql, false);
        $is_whitelisted = false;

        if ($result && $row = sql_fetch_array($result)) {
            $is_whitelisted = $row['cnt'] > 0;
        }

        // 캐시 저장
        self::$cache['whitelist'][$ip] = $is_whitelisted;

        return $is_whitelisted;
    }

    /**
     * IP 차단 정보 조회
     */
    public static function get_block_info($ip) {
        $ip_long = sprintf('%u', ip2long($ip));

        $sql = "SELECT sb_id, sb_ip, sb_reason, sb_block_type, sb_datetime, sb_hit_count
                FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                WHERE sb_status = 'active'
                  AND {$ip_long} BETWEEN sb_start_ip AND sb_end_ip
                ORDER BY sb_datetime DESC
                LIMIT 1";

        $result = sql_query($sql, false);
        if ($result && $block = sql_fetch_array($result)) {
            // 차단 적중 횟수 증가
            self::increment_hit_count($block['sb_id']);
            return $block;
        }

        return false;
    }

    /**
     * IP 주소 정규화 (x/32를 x로 변환)
     */
    public static function normalize_ip($ip) {
        $ip = trim($ip);

        // IPv4 주소만 처리
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // CIDR 형태인지 확인
            if (strpos($ip, '/') !== false) {
                list($ip_part, $cidr_part) = explode('/', $ip, 2);
                if (filter_var($ip_part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && is_numeric($cidr_part)) {
                    // /32인 경우 단일 IP로 정규화
                    if ($cidr_part == '32') {
                        return $ip_part;
                    }
                    return $ip;
                }
            }
            return false;
        }

        return $ip;
    }

    /**
     * IP 차단 추가
     */
    public static function add_block($ip, $reason = '', $block_type = 'manual') {
        // IP 정규화
        $normalized_ip = self::normalize_ip($ip);
        if (!$normalized_ip) {
            return false;
        }

        // CIDR 범위 계산
        $range = GK_Common::parse_cidr($normalized_ip);
        if (!$range) {
            return false;
        }

        // 중복 확인 (UNIQUE 제약 조건으로 처리됨)
        $sql = "INSERT IGNORE INTO " . GK_SECURITY_IP_BLOCK_TABLE . "
                (sb_ip, sb_start_ip, sb_end_ip, sb_reason, sb_block_type, sb_datetime)
                VALUES (
                    '" . sql_escape_string($normalized_ip) . "',
                    " . $range['start'] . ",
                    " . $range['end'] . ",
                    '" . sql_escape_string($reason) . "',
                    '" . sql_escape_string($block_type) . "',
                    NOW()
                )";

        $result = sql_query($sql, false);

        // INSERT IGNORE가 실패했을 경우 (중복) 기존 데이터 업데이트
        if ($result) {
            // 중복인 경우를 확인하기 위해 해당 범위의 데이터 조회
            $check_sql = "SELECT sb_id FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                         WHERE sb_start_ip = " . $range['start'] . "
                           AND sb_end_ip = " . $range['end'];
            $check_result = sql_query($check_sql, false);

            if ($check_result && sql_num_rows($check_result) > 0) {
                // 기존 데이터가 있으면 업데이트
                $update_sql = "UPDATE " . GK_SECURITY_IP_BLOCK_TABLE . "
                              SET sb_reason = '" . sql_escape_string($reason) . "',
                                  sb_block_type = '" . sql_escape_string($block_type) . "',
                                  sb_status = 'active'
                              WHERE sb_start_ip = " . $range['start'] . "
                                AND sb_end_ip = " . $range['end'];

                $result = sql_query($update_sql, false);
            }
        }

        return $result ? true : false;
    }

    /**
     * IP 차단 해제
     */
    public static function remove_block($ip) {
        // IP 정규화
        $normalized_ip = self::normalize_ip($ip);
        if (!$normalized_ip) {
            return false;
        }

        // CIDR 범위 계산
        $range = GK_Common::parse_cidr($normalized_ip);
        if (!$range) {
            return false;
        }

        $sql = "DELETE FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                WHERE sb_start_ip = " . $range['start'] . "
                  AND sb_end_ip = " . $range['end'];

        return sql_query($sql, false);
    }

    /**
     * 차단 적중 횟수 증가
     */
    private static function increment_hit_count($block_id) {
        $sql = "UPDATE " . GK_SECURITY_IP_BLOCK_TABLE . "
                SET sb_hit_count = sb_hit_count + 1
                WHERE sb_id = " . (int)$block_id;

        sql_query($sql, false);
    }

    /**
     * 차단된 접속 로그 기록
     */
    private static function log_blocked_access($ip, $block_info) {
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $block_reason = $block_info['sb_reason'] ?? '알 수 없음';

        $sql = "INSERT INTO " . GK_SECURITY_IP_LOG_TABLE . "
                (sl_ip, sl_datetime, sl_url, sl_user_agent, sl_block_reason)
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    NOW(),
                    '" . sql_escape_string($url) . "',
                    '" . sql_escape_string($user_agent) . "',
                    '" . sql_escape_string($block_reason) . "'
                )";

        sql_query($sql, false);
    }

    /**
     * 차단 페이지 표시
     */
    private static function show_blocked_page($block_info, $ip) {
        // 차단 사유 준비
        $reason = $block_info['sb_reason'] ?? '보안 정책 위반';
        $block_type = $block_info['sb_block_type'] ?? 'manual';

        // 차단 유형별 메시지
        $type_messages = array(
            'manual' => '관리자에 의해 차단되었습니다',
            'auto_login' => '과도한 로그인 시도로 인해 차단되었습니다',
            'auto_spam' => '스팸 활동 감지로 인해 차단되었습니다',
            'auto_abuse' => '비정상적인 활동 감지로 인해 차단되었습니다',
            'auto_regex' => '정규식 스팸 필터에 의해 차단되었습니다',
            'auto_behavior' => '이상 행위 감지로 인해 차단되었습니다',
            'auto_multiuser' => '다중 계정 사용으로 인해 차단되었습니다',
            'auto_useragent' => 'User-Agent 필터에 의해 차단되었습니다'
        );

        $type_message = isset($type_messages[$block_type]) ? $type_messages[$block_type] : '접근이 차단되었습니다';

        // HTML 출력
        header('HTTP/1.1 403 Forbidden');
        ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>접근 차단</title>
    <style>
        body {
            font-family: 'Malgun Gothic', '맑은 고딕', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .icon {
            font-size: 72px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .message {
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .details {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            font-size: 14px;
            text-align: left;
        }
        .details div {
            margin: 10px 0;
            word-break: break-all;
        }
        .details strong {
            display: inline-block;
            min-width: 80px;
            margin-right: 10px;
        }
        .contact {
            margin-top: 30px;
            font-size: 14px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚫</div>
        <h1>접근이 차단되었습니다</h1>
        <div class="message">
            <?php echo htmlspecialchars($type_message); ?>
        </div>
        <div class="details">
            <div><strong>IP 주소:</strong> <?php echo htmlspecialchars($ip); ?></div>
            <div><strong>차단 사유:</strong> <?php echo htmlspecialchars($reason); ?></div>
            <div><strong>차단 일시:</strong> <?php echo date('Y-m-d H:i:s'); ?></div>
        </div>
        <div class="contact">
            차단 해제를 원하시면 관리자에게 문의해주세요.
        </div>
    </div>
</body>
</html>
        <?php
    }

    /**
     * 해외 IP 차단 체크
     */
    public static function checkForeignIP() {
        global $member;

        // 관리자는 차단에서 제외
        if (isset($member) && $member['mb_level'] >= 10) {
            return;
        }

        // 해외 IP 차단 활성화 확인
        if (GK_Common::get_config('foreign_block_enabled') != '1') {
            return;
        }

        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // IP 유효성 검사
        if (empty($current_ip) || !filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return;
        }

        // localhost IP는 절대 차단하지 않음 (안전 장치)
        if (self::is_localhost_ip($current_ip)) {
            return;
        }

        // 예외 IP(화이트리스트) 확인 (우선 처리)
        if (self::is_whitelisted($current_ip)) {
            return;
        }

        // 사설 IP, 예약 IP, 로컬 IP는 차단에서 제외
        if (self::is_private_or_reserved_ip($current_ip)) {
            return;
        }

        // 알려진 해외 서비스 IP는 차단에서 제외
        if (self::is_known_service_ip($current_ip)) {
            return;
        }

        // 한국 IP 목록 파일 확인
        if (!file_exists(GK_KOREA_IP_FILE)) {
            return;
        }

        // 한국 IP인지 확인
        if (!self::is_korean_ip($current_ip)) {
            // 차단 로그 기록
            self::log_blocked_access($current_ip, array(
                'sb_reason' => '해외 IP 차단',
                'sb_block_type' => 'auto_foreign'
            ));

            // 차단 페이지 표시
            self::show_blocked_page(array(
                'sb_reason' => '해외 접속 차단',
                'sb_block_type' => 'auto_foreign'
            ), $current_ip);
            exit;
        }
    }

    /**
     * localhost 관련 IP인지 확인 (IPv4, IPv6 모두 포함)
     */
    public static function is_localhost_ip($ip) {
        if (empty($ip)) {
            return false;
        }

        // IPv4 localhost 패턴
        $ipv4_localhost_patterns = [
            '127.0.0.1',        // 정확한 localhost
            '0.0.0.0',          // 모든 인터페이스
            '::1',              // IPv6 localhost
            'localhost',        // 호스트명
        ];

        // 정확한 매치 확인
        if (in_array($ip, $ipv4_localhost_patterns)) {
            return true;
        }

        // IPv4 127.x.x.x 범위 확인
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_long = ip2long($ip);
            $localhost_start = ip2long('127.0.0.0');
            $localhost_end = ip2long('127.255.255.255');
            
            if ($ip_long >= $localhost_start && $ip_long <= $localhost_end) {
                return true;
            }
        }

        // IPv6 localhost 패턴 확인
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // ::1 (IPv6 localhost)
            if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
                return true;
            }
            // IPv6 loopback 범위
            if (strpos($ip, '::1') !== false || strpos($ip, '0:0:0:0:0:0:0:1') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 사설 IP, 예약 IP, 로컬 IP인지 확인
     */
    private static function is_private_or_reserved_ip($ip) {
        // localhost IP는 항상 예외 처리
        if (self::is_localhost_ip($ip)) {
            return true;
        }

        // IP 유효성 검사
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true; // 유효하지 않은 IP
        }

        // PHP의 내장 필터를 사용하여 사설 IP와 예약 IP 확인
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true; // 사설 IP 또는 예약 IP
        }

        // 추가로 확인이 필요한 특수 IP 범위들
        $ip_long = ip2long($ip);

        $special_ranges = [
            // 224.0.0.0/4 - Multicast (RFC 3171)
            ['start' => ip2long('224.0.0.0'), 'end' => ip2long('239.255.255.255')],
            // 240.0.0.0/4 - Reserved for Future Use (RFC 1112)
            ['start' => ip2long('240.0.0.0'), 'end' => ip2long('255.255.255.255')],
            // 100.64.0.0/10 - Carrier-grade NAT (RFC 6598)
            ['start' => ip2long('100.64.0.0'), 'end' => ip2long('100.127.255.255')],
            // 198.18.0.0/15 - Benchmark Testing (RFC 2544)
            ['start' => ip2long('198.18.0.0'), 'end' => ip2long('198.19.255.255')],
        ];

        foreach ($special_ranges as $range) {
            if ($ip_long >= $range['start'] && $ip_long <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 알려진 해외 서비스 IP인지 확인
     * (검색 봇, CDN, 클라우드 서비스 등)
     */
    private static function is_known_service_ip($ip) {
        static $service_ranges = null;

        if ($service_ranges === null) {
            $service_ranges = [
                // Google (Googlebot, PageSpeed Insights, etc.)
                ['start' => ip2long('66.249.64.0'), 'end' => ip2long('66.249.95.255')],
                ['start' => ip2long('64.233.160.0'), 'end' => ip2long('64.233.191.255')],
                ['start' => ip2long('72.14.192.0'), 'end' => ip2long('72.14.255.255')],
                ['start' => ip2long('74.125.0.0'), 'end' => ip2long('74.125.255.255')],
                ['start' => ip2long('108.177.8.0'), 'end' => ip2long('108.177.15.255')],
                ['start' => ip2long('172.217.0.0'), 'end' => ip2long('172.217.255.255')],
                ['start' => ip2long('173.194.0.0'), 'end' => ip2long('173.194.255.255')],
                ['start' => ip2long('209.85.128.0'), 'end' => ip2long('209.85.255.255')],
                ['start' => ip2long('216.58.192.0'), 'end' => ip2long('216.58.223.255')],

                // Microsoft Bing (Bingbot)
                ['start' => ip2long('40.77.167.0'), 'end' => ip2long('40.77.167.255')],
                ['start' => ip2long('157.55.39.0'), 'end' => ip2long('157.55.39.255')],
                ['start' => ip2long('207.46.13.0'), 'end' => ip2long('207.46.13.255')],
                ['start' => ip2long('199.30.16.0'), 'end' => ip2long('199.30.31.255')],

                // Cloudflare CDN
                ['start' => ip2long('103.21.244.0'), 'end' => ip2long('103.21.247.255')],
                ['start' => ip2long('103.22.200.0'), 'end' => ip2long('103.22.203.255')],
                ['start' => ip2long('103.31.4.0'), 'end' => ip2long('103.31.7.255')],
                ['start' => ip2long('104.16.0.0'), 'end' => ip2long('104.31.255.255')],
                ['start' => ip2long('108.162.192.0'), 'end' => ip2long('108.162.255.255')],
                ['start' => ip2long('131.0.72.0'), 'end' => ip2long('131.0.75.255')],
                ['start' => ip2long('141.101.64.0'), 'end' => ip2long('141.101.127.255')],
                ['start' => ip2long('162.158.0.0'), 'end' => ip2long('162.159.255.255')],
                ['start' => ip2long('172.64.0.0'), 'end' => ip2long('172.67.255.255')],
                ['start' => ip2long('173.245.48.0'), 'end' => ip2long('173.245.63.255')],
                ['start' => ip2long('188.114.96.0'), 'end' => ip2long('188.114.111.255')],
                ['start' => ip2long('190.93.240.0'), 'end' => ip2long('190.93.255.255')],
                ['start' => ip2long('197.234.240.0'), 'end' => ip2long('197.234.255.255')],
                ['start' => ip2long('198.41.128.0'), 'end' => ip2long('198.41.255.255')],

                // Facebook/Meta
                ['start' => ip2long('31.13.24.0'), 'end' => ip2long('31.13.127.255')],
                ['start' => ip2long('66.220.144.0'), 'end' => ip2long('66.220.159.255')],
                ['start' => ip2long('69.63.176.0'), 'end' => ip2long('69.63.191.255')],
                ['start' => ip2long('69.171.224.0'), 'end' => ip2long('69.171.255.255')],
                ['start' => ip2long('173.252.64.0'), 'end' => ip2long('173.252.127.255')],

                // Amazon Web Services (일부 주요 범위)
                ['start' => ip2long('52.95.0.0'), 'end' => ip2long('52.95.255.255')],
                ['start' => ip2long('54.239.128.0'), 'end' => ip2long('54.239.255.255')],

                // Yahoo/Verizon Media
                ['start' => ip2long('72.30.0.0'), 'end' => ip2long('72.30.255.255')],
                ['start' => ip2long('98.138.0.0'), 'end' => ip2long('98.138.255.255')],

                // Twitter
                ['start' => ip2long('199.16.156.0'), 'end' => ip2long('199.16.159.255')],
                ['start' => ip2long('199.59.148.0'), 'end' => ip2long('199.59.151.255')],
            ];
        }

        $ip_long = ip2long($ip);

        foreach ($service_ranges as $range) {
            if ($ip_long >= $range['start'] && $ip_long <= $range['end']) {
                return true;
            }
        }

        // User-Agent 기반 추가 확인
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (self::is_known_bot_user_agent($user_agent)) {
            return true;
        }

        return false;
    }

    /**
     * 알려진 봇 User-Agent인지 확인
     */
    private static function is_known_bot_user_agent($user_agent) {
        if (empty($user_agent)) {
            return false;
        }

        $bot_signatures = [
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
            'MJ12bot',         // Majestic
            'AhrefsBot',       // Ahrefs
            'SemrushBot',      // Semrush
            'MozBot',          // Moz
            'PageSpeed',       // Google PageSpeed
            'GTmetrix',        // GTmetrix
            'Pingdom',         // Pingdom
            'UptimeRobot',     // Uptime Robot
        ];

        $user_agent_lower = strtolower($user_agent);

        foreach ($bot_signatures as $signature) {
            if (strpos($user_agent_lower, strtolower($signature)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 한국 IP인지 확인
     */
    private static function is_korean_ip($ip) {
        static $korea_ranges = null;

        if ($korea_ranges === null) {
            $korea_ranges = array();
            $lines = file(GK_KOREA_IP_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;

                $parts = explode('/', $line);
                if (count($parts) == 2) {
                    $range = GK_Common::parse_cidr($line);
                    if ($range) {
                        $korea_ranges[] = $range;
                    }
                }
            }
        }

        $ip_long = sprintf('%u', ip2long($ip));

        foreach ($korea_ranges as $range) {
            if ($ip_long >= $range['start'] && $ip_long <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * 그누보드 기본 IP 설정을 GnuKeeper로 동기화
     */
    public static function syncFromGnuboard() {
        global $config;
        
        if (!isset($config)) {
            return;
        }
        
        // 접근차단 IP 동기화
        if (!empty($config['cf_intercept_ip'])) {
            $intercept_ips = explode("\n", trim($config['cf_intercept_ip']));
            foreach ($intercept_ips as $ip) {
                $ip = trim($ip);
                if (empty($ip)) continue;
                
                // + 패턴을 CIDR로 변환
                $normalized_ip = self::convert_gnuboard_pattern_to_cidr($ip);
                if ($normalized_ip) {
                    self::add_block($normalized_ip, '그누보드 기본 차단 설정에서 동기화', 'manual');
                }
            }
        }
        
        // 접근가능 IP 동기화 (예외 IP로 추가)
        if (!empty($config['cf_possible_ip'])) {
            $possible_ips = explode("\n", trim($config['cf_possible_ip']));
            foreach ($possible_ips as $ip) {
                $ip = trim($ip);
                if (empty($ip)) continue;
                
                // + 패턴을 실제 IP로 변환해서 예외 IP에 추가
                $normalized_ip = self::convert_gnuboard_pattern_to_ip($ip);
                if ($normalized_ip) {
                    self::add_whitelist($normalized_ip, '그누보드 접근가능 IP에서 동기화');
                }
            }
        }
    }
    
    /**
     * GnuKeeper 설정을 그누보드 기본 설정에 동기화
     */
    public static function syncToGnuboard() {
        // 수동으로 추가된 차단 IP만 그누보드에 동기화 (자동 차단은 제외)
        $manual_blocks = self::getManualBlocks();
        $intercept_ips = [];
        
        foreach ($manual_blocks as $block) {
            $intercept_ips[] = $block['sb_ip'];
        }
        
        // 예외 IP를 접근가능 IP로 동기화
        $whitelist_ips = self::getWhitelistIPs();
        $possible_ips = [];
        
        foreach ($whitelist_ips as $whitelist) {
            $possible_ips[] = $whitelist['sw_ip'];
        }
        
        // 그누보드 설정 업데이트
        $intercept_ip_str = implode("\n", $intercept_ips);
        $possible_ip_str = implode("\n", $possible_ips);
        
        $sql = "UPDATE " . G5_TABLE_PREFIX . "config SET 
                cf_intercept_ip = '" . sql_escape_string($intercept_ip_str) . "',
                cf_possible_ip = '" . sql_escape_string($possible_ip_str) . "'";
        sql_query($sql);
    }
    
    /**
     * 수동 차단 IP 목록 조회
     */
    private static function getManualBlocks() {
        $sql = "SELECT sb_ip FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                WHERE sb_status = 'active' AND sb_block_type = 'manual'";
        $result = sql_query($sql);
        
        $blocks = [];
        while ($row = sql_fetch_array($result)) {
            $blocks[] = $row;
        }
        
        return $blocks;
    }
    
    /**
     * 예외 IP 목록 조회
     */
    private static function getWhitelistIPs() {
        $sql = "SELECT sw_ip FROM " . G5_TABLE_PREFIX . "security_ip_whitelist";
        $result = sql_query($sql);
        
        $whitelist = [];
        while ($row = sql_fetch_array($result)) {
            $whitelist[] = $row;
        }
        
        return $whitelist;
    }
    
    /**
     * 예외 IP 추가
     */
    private static function add_whitelist($ip, $memo = '') {
        // 중복 체크
        $existing = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_ip = '" . sql_escape_string($ip) . "'");
        if ($existing && $existing['cnt'] > 0) {
            return false;
        }
        
        $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_whitelist (sw_ip, sw_memo, sw_datetime) VALUES (
            '" . sql_escape_string($ip) . "',
            '" . sql_escape_string($memo) . "',
            NOW()
        )";
        
        return sql_query($sql);
    }
    
    /**
     * 그누보드 패턴(+)을 CIDR로 변환
     */
    private static function convert_gnuboard_pattern_to_cidr($pattern) {
        // 123.123.+ -> 123.123.0.0/16
        // 123.123.123.+ -> 123.123.123.0/24
        
        if (strpos($pattern, '+') !== false) {
            $parts = explode('.', $pattern);
            $cidr_prefix = 0;
            $ip_parts = [];
            
            for ($i = 0; $i < 4; $i++) {
                if (isset($parts[$i]) && $parts[$i] !== '+') {
                    $ip_parts[] = $parts[$i];
                    $cidr_prefix += 8;
                } else {
                    $ip_parts[] = '0';
                }
            }
            
            $ip = implode('.', $ip_parts);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip . '/' . $cidr_prefix;
            }
        } else {
            // 일반 IP인 경우
            if (filter_var($pattern, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $pattern;
            }
        }
        
        return null;
    }
    
    /**
     * 그누보드 패턴(+)을 실제 IP로 변환 (첫 번째 IP만)
     */
    private static function convert_gnuboard_pattern_to_ip($pattern) {
        if (strpos($pattern, '+') !== false) {
            // 123.123.+ -> 123.123.0.1 (대표 IP)
            $parts = explode('.', $pattern);
            $ip_parts = [];
            
            for ($i = 0; $i < 4; $i++) {
                if (isset($parts[$i]) && $parts[$i] !== '+') {
                    $ip_parts[] = $parts[$i];
                } else {
                    $ip_parts[] = ($i == 3) ? '1' : '0'; // 마지막 옥텟은 1로 설정
                }
            }
            
            $ip = implode('.', $ip_parts);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        } else {
            // 일반 IP인 경우
            if (filter_var($pattern, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $pattern;
            }
        }
        
        return null;
    }
}