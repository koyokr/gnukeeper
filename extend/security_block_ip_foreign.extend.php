<?php
/**
 * 해외 IP 차단 보안 플러그인
 * gnuboard5 extend 파일
 *
 * adm/security_block_ip_kr.txt 파일을 메모리에 로드하여 해외 IP를 차단합니다.
 * 고급 차단 IP 기능과 독립적으로 작동하며, 해당 DB를 사용하지 않습니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 관리자는 해외 IP 차단에서 제외
if (isset($member) && $member['mb_level'] >= 10) {
    return;
}

// 현재 IP가 해외 IP인지 확인하고 차단 수준 적용
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!empty($current_ip) && filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    // 해외 IP 차단 기능이 활성화되어 있고 해외 IP인 경우
    if (gk_is_foreign_ip_block_enabled() && !gk_is_korea_ip($current_ip) && !gk_is_private_or_reserved_ip($current_ip)) {
        // 차단 수준 확인
        $block_level = gk_get_foreign_block_level();
        $block_levels = explode(',', $block_level);

        // 전역 변수로 해외 IP 차단 정보 설정
        global $g5_foreign_block_levels;
        $g5_foreign_block_levels = $block_levels;

        // 접속 차단인 경우 즉시 차단
        if (in_array('access', $block_levels)) {
            gk_show_foreign_blocked_page('해외 IP는 접속이 제한됩니다.');
        }
    }
}

// 회원가입 완료 후 이벤트 훅 등록
add_event('member_confirm_after', 'gk_check_foreign_ip_register', 10);

// 로그인 시도 후 이벤트 훅 등록 (추가 보안)
add_event('login_check_after', 'gk_check_foreign_ip_login', 10);

/**
 * 국내 IP 대역 데이터를 메모리에 로드
 */
function gk_load_korea_ip_ranges() {
    static $ip_ranges = null;

    if ($ip_ranges === null) {
        $ip_ranges = [];
        $file_path = __DIR__ . '/../adm/security_block_ip_kr.txt';

        if (file_exists($file_path)) {
            $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $cidr = trim($line);
                if (empty($cidr) || strpos($cidr, '#') === 0) {
                    continue; // 빈 줄이나 주석 건너뛰기
                }

                $range = gk_cidr_to_range($cidr);
                if ($range) {
                    $ip_ranges[] = $range;
                }
            }
        }
    }

    return $ip_ranges;
}

/**
 * CIDR을 IP 범위로 변환
 */
function gk_cidr_to_range($cidr) {
    if (!preg_match('/^([0-9.]+)\/([0-9]+)$/', $cidr, $matches)) {
        return false;
    }

    $ip = $matches[1];
    $prefix_length = (int)$matches[2];

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    $ip_long = ip2long($ip);
    $mask = ~((1 << (32 - $prefix_length)) - 1);
    $start_ip = $ip_long & $mask;
    $end_ip = $start_ip | ~$mask;

    return [
        'start_ip' => sprintf('%u', $start_ip),
        'end_ip' => sprintf('%u', $end_ip),
        'cidr' => $cidr
    ];
}

/**
 * 사설 IP 및 예약 IP인지 확인
 */
function gk_is_private_or_reserved_ip($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return true; // 잘못된 IP는 사설로 처리
    }

    $ip_long = ip2long($ip);

    // 사설 IP 대역
    $private_ranges = [
        ['start' => ip2long('10.0.0.0'), 'end' => ip2long('10.255.255.255')],      // 10.0.0.0/8
        ['start' => ip2long('172.16.0.0'), 'end' => ip2long('172.31.255.255')],   // 172.16.0.0/12
        ['start' => ip2long('192.168.0.0'), 'end' => ip2long('192.168.255.255')], // 192.168.0.0/16
        ['start' => ip2long('127.0.0.0'), 'end' => ip2long('127.255.255.255')],   // 127.0.0.0/8 (loopback)
        ['start' => ip2long('169.254.0.0'), 'end' => ip2long('169.254.255.255')], // 169.254.0.0/16 (link-local)
        ['start' => ip2long('224.0.0.0'), 'end' => ip2long('239.255.255.255')],   // 224.0.0.0/4 (multicast)
        ['start' => ip2long('240.0.0.0'), 'end' => ip2long('255.255.255.255')],   // 240.0.0.0/4 (reserved)
    ];

    foreach ($private_ranges as $range) {
        if ($ip_long >= $range['start'] && $ip_long <= $range['end']) {
            return true;
        }
    }

    return false;
}

/**
 * 사설 IP 종류 반환 (UI 표시용)
 */
function gk_get_private_ip_type($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return '잘못된 IP';
    }

    $ip_long = ip2long($ip);

    // 사설 IP 대역별 분류
    if ($ip_long >= ip2long('10.0.0.0') && $ip_long <= ip2long('10.255.255.255')) {
        return '🏠 사설 (10.x.x.x)';
    }
    if ($ip_long >= ip2long('172.16.0.0') && $ip_long <= ip2long('172.31.255.255')) {
        return '🏠 사설 (172.16-31.x.x)';
    }
    if ($ip_long >= ip2long('192.168.0.0') && $ip_long <= ip2long('192.168.255.255')) {
        return '🏠 사설 (192.168.x.x)';
    }
    if ($ip_long >= ip2long('127.0.0.0') && $ip_long <= ip2long('127.255.255.255')) {
        return '🔁 루프백 (127.x.x.x)';
    }
    if ($ip_long >= ip2long('169.254.0.0') && $ip_long <= ip2long('169.254.255.255')) {
        return '🔗 링크로컬 (169.254.x.x)';
    }
    if ($ip_long >= ip2long('224.0.0.0') && $ip_long <= ip2long('239.255.255.255')) {
        return '📡 멀티캐스트 (224-239.x.x.x)';
    }
    if ($ip_long >= ip2long('240.0.0.0') && $ip_long <= ip2long('255.255.255.255')) {
        return '⚠️ 예약대역 (240-255.x.x.x)';
    }

    return null; // 공인 IP
}

/**
 * IP가 예외 IP(화이트리스트)에 있는지 확인
 */
function gk_is_whitelisted_ip($ip) {
    // 테이블이 존재하지 않으면 false 반환
    if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_ip_whitelist LIMIT 1", false)) {
        return false;
    }

    $sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist
            WHERE sw_ip = '" . sql_escape_string($ip) . "'";

    $result = sql_query($sql, false);
    if ($result && $row = sql_fetch_array($result)) {
        return $row['cnt'] > 0;
    }

    return false;
}

/**
 * IP가 국내인지 확인 (메모리 기반)
 */
function gk_is_korea_ip($ip) {
    static $cache = [];

    // 캐시 확인
    if (isset($cache[$ip])) {
        return $cache[$ip];
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $cache[$ip] = false;
        return false;
    }

    // 사설 IP나 예약 IP는 국내로 처리 (차단하지 않음)
    if (gk_is_private_or_reserved_ip($ip)) {
        $cache[$ip] = true;
        return true;
    }

    $ip_long = sprintf('%u', ip2long($ip));
    $ip_ranges = gk_load_korea_ip_ranges();

    // 로드된 IP 범위와 비교
    foreach ($ip_ranges as $range) {
        if ($ip_long >= $range['start_ip'] && $ip_long <= $range['end_ip']) {
            $cache[$ip] = true;
            return true;
        }
    }

    $cache[$ip] = false;
    return false;
}

/**
 * 클라이언트 IP 주소 확인
 */
function gk_get_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * 해외 IP 차단 기능 활성화 여부 확인
 */
function gk_is_foreign_ip_block_enabled() {
    static $is_enabled = null;

    if ($is_enabled === null) {
        // 테이블이 존재하지 않으면 기본값 반환
        if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_config LIMIT 1", false)) {
            $is_enabled = false;
            return $is_enabled;
        }

        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'ip_foreign_block_enabled'";
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
 * 해외 IP 차단 여부 확인 (정적 관리, 룰 생성 없음)
 */
function gk_should_block_foreign_ip($ip) {
    // 해외 IP 차단 기능이 비활성화되면 false
    if (!gk_is_foreign_ip_block_enabled()) {
        return false;
    }

    // 예외 IP(화이트리스트)에 있으면 차단하지 않음
    if (gk_is_whitelisted_ip($ip)) {
        return false;
    }

    // 사설 IP나 예약 IP는 차단하지 않음
    if (gk_is_private_or_reserved_ip($ip)) {
        return false;
    }

    // 국내 IP인지 확인 (해외 IP면 차단)
    return !gk_is_korea_ip($ip);
}

/**
 * 해외 IP 관련 액션 로그 기록
 */
function gk_log_foreign_ip_action($ip, $action, $details) {
    // 테이블이 존재하지 않으면 무시
    if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_ip_log LIMIT 1", false)) {
        return false;
    }

    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_log SET
                sl_ip = '" . sql_escape_string($ip) . "',
                sl_datetime = NOW(),
                sl_url = '" . sql_escape_string($_SERVER['REQUEST_URI'] ?? '') . "',
                sl_user_agent = '" . sql_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '') . "',
                sl_block_reason = '" . sql_escape_string("[{$action}] {$details}") . "'";

    return sql_query($sql, false);
}

/**
 * 국내 IP 대역 통계 (메모리 기반)
 */
function gk_get_korea_ip_stats() {
    $ip_ranges = gk_load_korea_ip_ranges();
    $file_path = __DIR__ . '/../adm/security_block_ip_kr.txt';

    $last_updated = 'Unknown';
    if (file_exists($file_path)) {
        $last_updated = date('Y-m-d H:i:s', filemtime($file_path));
    }

    return [
        'total_ranges' => count($ip_ranges),
        'last_updated' => $last_updated,
        'file_exists' => file_exists($file_path)
    ];
}


/**
 * 해외 IP 차단 페이지 표시 (security_block_ip.extend.php와 다른 스타일)
 */
function gk_show_foreign_blocked_page($message) {
    // HTTP 403 상태 코드 전송
    http_response_code(403);

    echo "<!DOCTYPE html>
<html lang=\"ko\">
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>해외 접속 제한</title>
    <style>
        body {
            font-family: 'Malgun Gothic', '맑은 고딕', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .block-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        .block-title {
            color: #2d3748;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .block-message {
            color: #718096;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .home-link {
            display: inline-block;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .home-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
        }
    </style>
</head>
<body>
    <div class=\"block-container\">
        <div class=\"block-icon\">🌍</div>
        <h1 class=\"block-title\">해외 접속이 제한되었습니다</h1>

        <div class=\"block-message\">
            " . htmlspecialchars($message) . "<br><br>
            현재 사이트는 국내에서만 이용 가능합니다.<br>
            문의사항이 있으시면 관리자에게 연락해주세요.
        </div>

        <a href=\"" . G5_URL . "\" class=\"home-link\">🏠 메인페이지로 돌아가기</a>
    </div>
</body>
</html>";

    exit;
}

/**
 * 회원가입 시 해외 IP 체크
 */
function gk_check_foreign_ip_register($mb_id) {
    // 현재 IP 주소 확인
    $current_ip = gk_get_client_ip();
    if (empty($current_ip)) {
        return;
    }

    // 해외 IP 차단 여부 확인
    if (gk_should_block_foreign_ip($current_ip)) {
        // 해외 IP 차단 로그 기록
        gk_log_foreign_ip_action($current_ip, 'BLOCKED', "해외 IP 회원가입 차단 (회원ID: {$mb_id})");

        // 차단 페이지 표시
        gk_show_foreign_blocked_page('해외 IP에서의 회원가입은 제한됩니다.');
    } else {
        // 국내 IP 또는 예외 IP 접근 로그
        gk_log_foreign_ip_action($current_ip, 'ALLOWED', "회원가입 허용: {$mb_id}");
    }
}

/**
 * 로그인 시 해외 IP 체크 (로그만 기록, 차단하지 않음)
 */
function gk_check_foreign_ip_login($mb_id) {
    // 관리자는 제외 (전역 $member 사용)
    global $member;
    if (isset($member['mb_level']) && $member['mb_level'] >= 10) {
        return;
    }

    // 현재 IP 주소 확인
    $current_ip = gk_get_client_ip();
    if (empty($current_ip)) {
        return;
    }

    // 해외 IP 로그인 시도 로그 기록 (차단하지는 않음)
    if (gk_is_foreign_ip_block_enabled() && !gk_is_korea_ip($current_ip)) {
        gk_log_foreign_ip_action($current_ip, 'LOGIN_ATTEMPT', "해외 IP 로그인 시도: {$mb_id}");
    }
}

/**
 * 해외 IP 차단 수준 가져오기
 */
function gk_get_foreign_block_level() {
    static $block_level = null;

    if ($block_level === null) {
        // 테이블이 존재하지 않으면 기본값 반환
        if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_config LIMIT 1", false)) {
            $block_level = 'access';
            return $block_level;
        }

        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'ip_foreign_block_level'";
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
 * 해외 IP 로그인 차단 여부 확인
 */
function gk_is_foreign_login_blocked() {
    global $g5_foreign_block_levels;
    return isset($g5_foreign_block_levels) && in_array('login', $g5_foreign_block_levels);
}

/**
 * 해외 IP 게시글/댓글 작성 차단 여부 확인
 */
function gk_is_foreign_write_blocked() {
    global $g5_foreign_block_levels;
    return isset($g5_foreign_block_levels) && in_array('write', $g5_foreign_block_levels);
}

/**
 * 해외 IP 쪽지 작성 차단 여부 확인 (글쓰기 차단에 포함됨)
 */
function gk_is_foreign_memo_blocked() {
    global $g5_foreign_block_levels;
    return isset($g5_foreign_block_levels) && (in_array('write', $g5_foreign_block_levels) || in_array('memo', $g5_foreign_block_levels));
}

// 해외 IP 차단 수준별 처리
if (isset($g5_foreign_block_levels)) {
    // 회원가입/로그인 페이지에서 차단
    if (gk_is_foreign_login_blocked()) {
        $login_register_pages = [
            'login_check.php',        // 로그인 처리
            'register.php',           // 회원가입 폼
            'register_form_update.php', // 회원가입 처리
            'register_result.php'     // 회원가입 완료
        ];
        if (in_array(basename($_SERVER['SCRIPT_NAME']), $login_register_pages)) {
            alert('해외 IP는 로그인/회원가입이 제한됩니다.');
            exit;
        }
    }

    // 글쓰기/문의/쪽지 작성 페이지에서 차단
    if (gk_is_foreign_write_blocked()) {
        $write_pages = [
            // 게시글/댓글 작성
            'write.php', 'write_update.php', 'write_comment_update.php',
            // 쪽지 작성
            'memo_form.php', 'memo_form_update.php',
            // 문의 작성 (qa)
            'qa_write.php', 'qa_write_update.php'
        ];
        if (in_array(basename($_SERVER['SCRIPT_NAME']), $write_pages)) {
            alert('해외 IP는 글쓰기/문의/쪽지 작성이 제한됩니다.');
            exit;
        }
    }

    // 쪽지 작성 페이지에서 차단 (하위 호환성)
    if (gk_is_foreign_memo_blocked()) {
        $memo_pages = ['memo_form.php', 'memo_form_update.php'];
        if (in_array(basename($_SERVER['SCRIPT_NAME']), $memo_pages)) {
            alert('해외 IP는 글쓰기/문의/쪽지 작성이 제한됩니다.');
            exit;
        }
    }
}
?>