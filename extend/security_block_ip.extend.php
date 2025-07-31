<?php
/**
 * IP 차단 보안 플러그인
 * gnuboard5 extend 파일
 *
 * 이 파일은 모든 페이지에서 자동으로 로드되어 IP 차단을 실행합니다.
 */

if (!defined('_GNUBOARD_')) exit;

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

// 테이블 존재 여부 및 기능 활성화 확인
if (!security_check_tables_exist() || !security_is_enabled()) {
    return;
}

// 예외 IP(화이트리스트) 확인 (우선 처리)
if (security_is_whitelisted($current_ip)) {
    return;
}

// IP 차단 확인
$block_info = security_get_block_info($current_ip);
if ($block_info) {
    // 차단 수준 확인
    $block_levels = explode(',', $block_info['sb_block_level']);
    
    // 접속 차단인 경우 즉시 차단 페이지 표시
    if (in_array('access', $block_levels)) {
        // 차단 로그 기록
        security_log_blocked_access($current_ip, $block_info);
        
        // 차단 페이지 표시 후 종료
        security_show_blocked_page($block_info, $current_ip);
        exit;
    }
    
    // 그 외 차단 수준은 전역 변수로 설정 (다른 페이지에서 확인)
    global $g5_security_block_info;
    $g5_security_block_info = $block_info;
    $g5_security_block_levels = $block_levels;
}

/**
 * 테이블 존재 여부 확인
 */
function security_check_tables_exist() {
    static $tables_exist = null;

    if ($tables_exist === null) {
        $sql = "SHOW TABLES LIKE '" . G5_TABLE_PREFIX . "security_config'";
        $result = sql_query($sql, false);
        $tables_exist = ($result && sql_num_rows($result) > 0);
    }

    return $tables_exist;
}

/**
 * IP 차단 기능 활성화 여부 확인
 */
function security_is_enabled() {
    static $is_enabled = null;

    if ($is_enabled === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'ip_block_enabled'";
        $result = sql_query($sql, false);

        if ($result && $row = sql_fetch_array($result)) {
            $is_enabled = ($row['sc_value'] == '1');
        } else {
            $is_enabled = true; // 기본값
        }
    }

    return $is_enabled;
}

/**
 * IP가 예외 IP(화이트리스트)에 있는지 확인
 */
function security_is_whitelisted($ip) {
    $sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist
            WHERE sw_ip = '" . sql_escape_string($ip) . "'";

    $result = sql_query($sql, false);
    if ($result && $row = sql_fetch_array($result)) {
        return $row['cnt'] > 0;
    }

    return false;
}

/**
 * IP 차단 정보 조회
 */
function security_get_block_info($ip) {
    $ip_long = sprintf('%u', ip2long($ip));

    $sql = "SELECT sb_id, sb_ip, sb_reason, sb_block_type, sb_block_level, sb_duration,
                   sb_end_datetime, sb_datetime, sb_hit_count
            FROM " . G5_TABLE_PREFIX . "security_ip_block
            WHERE sb_status = 'active'
              AND {$ip_long} BETWEEN sb_start_ip AND sb_end_ip
            ORDER BY sb_datetime DESC
            LIMIT 1";

    $result = sql_query($sql, false);
    if ($result && $block = sql_fetch_array($result)) {
        // 임시 차단의 경우 만료 시간 확인
        if ($block['sb_duration'] == 'temporary' &&
            $block['sb_end_datetime'] &&
            strtotime($block['sb_end_datetime']) < time()) {

            // 만료된 차단 규칙 상태 업데이트
            security_expire_block($block['sb_id']);
            return false;
        }

        // 차단 적중 횟수 증가
        security_increment_hit_count($block['sb_id']);

        return $block;
    }

    return false;
}

/**
 * 만료된 차단 규칙 상태 업데이트
 */
function security_expire_block($block_id) {
    $sql = "UPDATE " . G5_TABLE_PREFIX . "security_ip_block
            SET sb_status = 'expired'
            WHERE sb_id = " . (int)$block_id;

    sql_query($sql, false);
}

/**
 * 차단 적중 횟수 증가
 */
function security_increment_hit_count($block_id) {
    $sql = "UPDATE " . G5_TABLE_PREFIX . "security_ip_block
            SET sb_hit_count = sb_hit_count + 1
            WHERE sb_id = " . (int)$block_id;

    sql_query($sql, false);
}

/**
 * 차단된 IP 접근 로그 기록
 */
function security_log_blocked_access($ip, $block_info) {
    $current_page = $_SERVER['REQUEST_URI'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_log SET
                sl_ip = '" . sql_escape_string($ip) . "',
                sl_datetime = NOW(),
                sl_url = '" . sql_escape_string($current_page) . "',
                sl_user_agent = '" . sql_escape_string($user_agent) . "',
                sl_block_reason = '" . sql_escape_string($block_info['sb_reason']) . "'";

    sql_query($sql, false);
}

/**
 * 차단 페이지 표시
 */
function security_show_blocked_page($block_info, $ip) {
    // HTTP 403 상태 코드 전송
    http_response_code(403);

    // 차단 유형 이름
    $block_types = array(
        'manual' => '수동 차단',
        'auto_login' => '로그인 시도 제한',
        'auto_spam' => '스팸 행위',
        'auto_abuse' => '악성 행위'
    );

    $block_type_name = $block_types[$block_info['sb_block_type']] ?? '자동 차단';
    $reason = htmlspecialchars($block_info['sb_reason']);
    $blocked_time = date('Y-m-d H:i:s', strtotime($block_info['sb_datetime']));

    // 차단 종료 시간 (임시 차단인 경우)
    $end_message = '';
    if ($block_info['sb_duration'] == 'temporary' && $block_info['sb_end_datetime']) {
        $end_time = strtotime($block_info['sb_end_datetime']);
        $remaining = $end_time - time();

        if ($remaining > 0) {
            $hours = floor($remaining / 3600);
            $minutes = floor(($remaining % 3600) / 60);
            $end_message = "<p style=\"color: #666; margin-top: 15px; font-size: 14px;\">
                차단 해제까지: {$hours}시간 {$minutes}분 남음
            </p>";
        }
    }

    echo "<!DOCTYPE html>
<html lang=\"ko\">
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>접근이 차단되었습니다</title>
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
        }
        .block-info-label {
            font-weight: bold;
            color: #333;
            display: inline-block;
            width: 80px;
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
        .ip-address {
            font-family: monospace;
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class=\"block-container\">
        <div class=\"block-icon\">🚫</div>
        <h1 class=\"block-title\">접근이 차단되었습니다</h1>

        <div class=\"block-message\">
            귀하의 IP 주소는 보안상의 이유로 이 사이트에 대한 접근이 제한되었습니다.
        </div>

        <div class=\"block-info\">
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 IP:</span>
                <span class=\"ip-address\">{$ip}</span>
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 유형:</span> {$block_type_name}
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 사유:</span> {$reason}
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 일시:</span> {$blocked_time}
            </div>
        </div>

        {$end_message}

        <div class=\"contact-info\">
            <strong>정당한 이유로 차단된 경우</strong><br>
            사이트 관리자에게 문의하여 차단 해제를 요청하실 수 있습니다.
        </div>
    </div>
</body>
</html>";
}

/**
 * 로그인 차단 여부 확인
 */
function gk_is_login_blocked() {
    global $g5_security_block_levels;
    return isset($g5_security_block_levels) && in_array('login', $g5_security_block_levels);
}

/**
 * 게시글/댓글 작성 차단 여부 확인
 */
function gk_is_write_blocked() {
    global $g5_security_block_levels;
    return isset($g5_security_block_levels) && in_array('write', $g5_security_block_levels);
}

/**
 * 쪽지 작성 차단 여부 확인 (글쓰기 차단에 포함됨)
 */
function gk_is_memo_blocked() {
    global $g5_security_block_levels;
    return isset($g5_security_block_levels) && (in_array('write', $g5_security_block_levels) || in_array('memo', $g5_security_block_levels));
}

/**
 * 차단 정보 가져오기
 */
function gk_get_block_info() {
    global $g5_security_block_info;
    return isset($g5_security_block_info) ? $g5_security_block_info : null;
}

// gnuboard5 회원가입/로그인 페이지에서 차단 처리
if (gk_is_login_blocked()) {
    $login_register_pages = [
        'login_check.php',        // 로그인 처리
        'register.php',           // 회원가입 폼
        'register_form_update.php', // 회원가입 처리
        'register_result.php'     // 회원가입 완료
    ];
    if (in_array(basename($_SERVER['SCRIPT_NAME']), $login_register_pages)) {
        alert('귀하의 IP는 로그인/회원가입이 차단되었습니다.\\n차단 사유: ' . gk_get_block_info()['sb_reason']);
        exit;
    }
}

// 글쓰기/문의/쪽지 작성 페이지에서 차단 처리
if (gk_is_write_blocked()) {
    $write_pages = [
        // 게시글/댓글 작성
        'write.php', 'write_update.php', 'write_comment_update.php',
        // 쪽지 작성
        'memo_form.php', 'memo_form_update.php',
        // 문의 작성 (qa)
        'qa_write.php', 'qa_write_update.php'
    ];
    if (in_array(basename($_SERVER['SCRIPT_NAME']), $write_pages)) {
        alert('귀하의 IP는 글쓰기/문의/쪽지 작성이 차단되었습니다.\\n차단 사유: ' . gk_get_block_info()['sb_reason']);
        exit;
    }
}

// 쪽지 작성 페이지에서 차단 처리 (하위 호환성)
if (gk_is_memo_blocked()) {
    $memo_pages = ['memo_form.php', 'memo_form_update.php'];
    if (in_array(basename($_SERVER['SCRIPT_NAME']), $memo_pages)) {
        alert('귀하의 IP는 글쓰기/문의/쪽지 작성이 차단되었습니다.\\n차단 사유: ' . gk_get_block_info()['sb_reason']);
        exit;
    }
}
?>