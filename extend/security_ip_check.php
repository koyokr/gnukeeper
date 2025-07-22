<?php
/**
 * IP 차단 실제 적용 스크립트
 * gnuboard5 보안 플러그인
 * 
 * 이 파일은 gnuboard5의 extend 디렉토리에 위치하여 
 * 모든 페이지에서 자동으로 로드되어 IP 차단을 실행합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// IP 차단 클래스가 로드되지 않았다면 로드
if (!class_exists('SecurityIPBlocker')) {
    require_once(G5_EXTEND_PATH . '/security_ip_blocker.php');
}

// 현재 접속자 IP 주소 확인
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// 프록시나 로드밸런서 뒤에 있는 경우 실제 IP 확인
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $current_ip = trim($forwarded_ips[0]);
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $current_ip = $_SERVER['HTTP_X_REAL_IP'];
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $current_ip = $_SERVER['HTTP_CLIENT_IP'];
}

// 테이블 존재 여부 확인
$tables_exist = false;
$table_check_sql = "SHOW TABLES LIKE '" . G5_TABLE_PREFIX . "security_config'";
$table_result = sql_query($table_check_sql, false);
if ($table_result && sql_num_rows($table_result) > 0) {
    $tables_exist = true;
}

// 테이블이 존재하지 않으면 차단 기능 비활성화
if (!$tables_exist) {
    return;
}

// IP 차단 기능이 활성화되어 있는지 확인
$config_sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'ip_block_enabled'";
$config_result = sql_query($config_sql, false);
$ip_block_enabled = true; // 기본값

if ($config_result && $config_row = sql_fetch_array($config_result)) {
    $ip_block_enabled = ($config_row['sc_value'] == '1');
}

// IP 차단 기능이 비활성화된 경우 종료
if (!$ip_block_enabled) {
    return;
}

// 관리자 계정 확인 (최우선 예외)
if (isset($member) && $member['mb_level'] >= 10) {
    // 관리자는 IP 차단에서 예외
    return;
}

// 화이트리스트 확인 (우선 처리)
$ip_long = sprintf('%u', ip2long($current_ip));
$whitelist_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist 
                  WHERE {$ip_long} BETWEEN sw_start_ip AND sw_end_ip";
$whitelist_result = sql_query($whitelist_sql, false);

if ($whitelist_result && $whitelist_row = sql_fetch_array($whitelist_result)) {
    if ($whitelist_row['cnt'] > 0) {
        // 화이트리스트에 있으면 차단하지 않음
        return;
    }
}

// IP 차단 확인
$blocker = SecurityIPBlocker::getInstance();
$block_info = $blocker->isBlocked($current_ip);

if ($block_info) {
    // 차단된 IP에 대한 접근 로그 기록
    $current_page = $_SERVER['REQUEST_URI'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $blocker->logBlockedAccess(
        $current_ip, 
        $block_info['id'], 
        'page_access', 
        array(
            'page' => $current_page,
            'user_agent' => $user_agent,
            'reason' => $block_info['reason']
        )
    );
    
    // 차단 페이지 표시
    showBlockedPage($block_info, $current_ip);
    exit;
}

/**
 * 차단 페이지 표시 함수
 */
function showBlockedPage($block_info, $ip) {
    // HTTP 403 상태 코드 전송
    http_response_code(403);
    
    // 차단 유형에 따른 메시지 설정
    $block_types = array(
        'manual' => '수동 차단',
        'auto_login' => '로그인 시도 제한',
        'auto_spam' => '스팸 행위',
        'auto_abuse' => '악성 행위'
    );
    
    $block_type_name = $block_types[$block_info['block_type']] ?? '자동 차단';
    $reason = htmlspecialchars($block_info['reason']);
    
    // 차단 종료 시간 표시 (임시 차단인 경우)
    $end_message = '';
    if ($block_info['duration'] == 'temporary' && $block_info['end_datetime']) {
        $end_time = strtotime($block_info['end_datetime']);
        $remaining = $end_time - time();
        
        if ($remaining > 0) {
            $hours = floor($remaining / 3600);
            $minutes = floor(($remaining % 3600) / 60);
            $end_message = "<p style=\"color: #666; margin-top: 15px;\">
                차단 해제까지: {$hours}시간 {$minutes}분 남음
            </p>";
        }
    }
    
    // 차단 페이지 HTML 출력
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
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .block-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            margin: 20px;
        }
        .block-icon {
            font-size: 4em;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .block-title {
            color: #dc3545;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .block-message {
            color: #333;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .block-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .block-info-item {
            margin: 8px 0;
            color: #555;
        }
        .block-info-label {
            font-weight: bold;
            color: #333;
        }
        .contact-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
            color: #0056b3;
        }
        .ip-address {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid #dee2e6;
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
                <span class=\"block-info-label\">차단된 IP:</span> 
                <span class=\"ip-address\">{$ip}</span>
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 유형:</span> {$block_type_name}
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 사유:</span> {$reason}
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 일시:</span> " . 
                date('Y-m-d H:i:s', strtotime($block_info['datetime'] ?? 'now')) . "
            </div>
        </div>
        
        {$end_message}
        
        <div class=\"contact-info\">
            <strong>정당한 이유로 차단된 경우:</strong><br>
            사이트 관리자에게 문의하여 차단 해제를 요청하실 수 있습니다.
        </div>
    </div>
</body>
</html>";
}
?>