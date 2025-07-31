<?php
/**
 * 정규식 기반 키워드 스팸 차단 플러그인
 * gnuboard5 extend 파일
 *
 * 정규식 패턴을 사용하여 스팸 콘텐츠를 탐지하고 차단합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 관리자는 차단에서 제외
if (isset($member) && $member['mb_level'] >= 10) {
    return;
}

// 현재 IP 확인
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (empty($current_ip)) {
    return;
}

// IP 유효성 검사
if (!filter_var($current_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return;
}

// 테이블 존재 여부 및 기능 활성화 확인
if (!security_regex_spam_check_tables_exist() || !security_regex_spam_is_enabled()) {
    return;
}

// 예외 IP(화이트리스트) 확인
if (security_regex_spam_is_whitelisted($current_ip)) {
    return;
}

// POST 요청인 경우에만 스팸 검사 수행
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 게시글 작성/수정 훅 등록
    add_event('write_update_after', 'security_regex_spam_check_write', 10, 3);
    
    // 댓글 작성 훅 등록
    add_event('comment_update_after', 'security_regex_spam_check_comment', 10, 3);
    
    // 회원가입 훅 등록
    add_event('member_confirm_before', 'security_regex_spam_check_register', 10, 1);
    
    // 쪽지 작성 훅 등록 (gnuboard5에서 지원하는 경우)
    add_event('memo_form_update_before', 'security_regex_spam_check_memo', 10, 1);
}

/**
 * 테이블 존재 여부 확인
 */
function security_regex_spam_check_tables_exist() {
    static $tables_exist = null;

    if ($tables_exist === null) {
        $sql = "SHOW TABLES LIKE '" . G5_TABLE_PREFIX . "security_regex_spam'";
        $result = sql_query($sql, false);
        $tables_exist = ($result && sql_num_rows($result) > 0);
    }

    return $tables_exist;
}

/**
 * 정규식 스팸 차단 기능 활성화 여부 확인
 */
function security_regex_spam_is_enabled() {
    static $is_enabled = null;

    if ($is_enabled === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'regex_spam_enabled'";
        $result = sql_query($sql, false);

        if ($result && $row = sql_fetch_array($result)) {
            $is_enabled = ($row['sc_value'] == '1');
        } else {
            $is_enabled = false;
        }
    }

    return $is_enabled;
}

/**
 * IP가 예외 IP(화이트리스트)에 있는지 확인
 */
function security_regex_spam_is_whitelisted($ip) {
    $sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist
            WHERE sw_ip = '" . sql_escape_string($ip) . "'";

    $result = sql_query($sql, false);
    if ($result && $row = sql_fetch_array($result)) {
        return $row['cnt'] > 0;
    }

    return false;
}

/**
 * 게시글 스팸 검사
 */
function security_regex_spam_check_write($bo_table, $wr_id, $w) {
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $mb_id = $_POST['mb_id'] ?? '';
    $wr_name = $_POST['wr_name'] ?? '';
    $wr_email = $_POST['wr_email'] ?? '';
    $wr_subject = $_POST['wr_subject'] ?? '';
    $wr_content = $_POST['wr_content'] ?? '';

    // 검사할 데이터 준비
    $check_data = array();
    
    if (security_regex_spam_check_target_enabled('title')) {
        $check_data['title'] = $wr_subject;
    }
    
    if (security_regex_spam_check_target_enabled('content')) {
        $check_data['content'] = $wr_content;
    }
    
    if (security_regex_spam_check_target_enabled('name')) {
        $check_data['name'] = $wr_name;
    }
    
    if (security_regex_spam_check_target_enabled('email')) {
        $check_data['email'] = $wr_email;
    }

    // 스팸 검사 수행
    $spam_result = security_regex_spam_check_content($check_data);
    
    if ($spam_result) {
        security_regex_spam_handle_detection($spam_result, 'board_write', $current_ip, $mb_id, $bo_table, $wr_id, $check_data);
    }
}

/**
 * 댓글 스팸 검사
 */
function security_regex_spam_check_comment($bo_table, $wr_id, $comment_id) {
    if (!security_regex_spam_check_target_enabled('comment')) {
        return;
    }

    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $mb_id = $_POST['mb_id'] ?? '';
    $wr_name = $_POST['wr_name'] ?? '';
    $wr_content = $_POST['wr_content'] ?? '';

    // 검사할 데이터 준비
    $check_data = array(
        'comment' => $wr_content,
        'name' => $wr_name
    );

    // 스팸 검사 수행
    $spam_result = security_regex_spam_check_content($check_data);
    
    if ($spam_result) {
        security_regex_spam_handle_detection($spam_result, 'board_comment', $current_ip, $mb_id, $bo_table, $wr_id, $check_data);
    }
}

/**
 * 회원가입 스팸 검사
 */
function security_regex_spam_check_register($mb) {
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $mb_id = $mb['mb_id'] ?? '';
    $mb_name = $mb['mb_name'] ?? '';
    $mb_email = $mb['mb_email'] ?? '';

    // 검사할 데이터 준비
    $check_data = array();
    
    if (security_regex_spam_check_target_enabled('name')) {
        $check_data['name'] = $mb_name;
    }
    
    if (security_regex_spam_check_target_enabled('email')) {
        $check_data['email'] = $mb_email;
    }

    // 스팸 검사 수행
    $spam_result = security_regex_spam_check_content($check_data);
    
    if ($spam_result) {
        security_regex_spam_handle_detection($spam_result, 'member_register', $current_ip, $mb_id, '', 0, $check_data);
    }
}

/**
 * 쪽지 스팸 검사
 */
function security_regex_spam_check_memo($memo_data) {
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $mb_id = $_POST['mb_id'] ?? '';
    $me_memo = $_POST['me_memo'] ?? '';

    // 검사할 데이터 준비
    $check_data = array(
        'content' => $me_memo
    );

    // 스팸 검사 수행
    $spam_result = security_regex_spam_check_content($check_data);
    
    if ($spam_result) {
        security_regex_spam_handle_detection($spam_result, 'memo', $current_ip, $mb_id, '', 0, $check_data);
    }
}

/**
 * 대상별 검사 활성화 여부 확인
 */
function security_regex_spam_check_target_enabled($target) {
    static $config = null;
    
    if ($config === null) {
        $config = array();
        $targets = ['title', 'content', 'comment', 'name', 'email'];
        
        foreach ($targets as $target_key) {
            $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config 
                    WHERE sc_key = 'regex_spam_check_{$target_key}'";
            $result = sql_query($sql, false);
            
            if ($result && $row = sql_fetch_array($result)) {
                $config[$target_key] = ($row['sc_value'] == '1');
            } else {
                $config[$target_key] = true; // 기본값: 활성화
            }
        }
    }
    
    return $config[$target] ?? true;
}

/**
 * 정규식 스팸 패턴으로 콘텐츠 검사
 */
function security_regex_spam_check_content($check_data) {
    // 활성화된 정규식 규칙 가져오기
    $sql = "SELECT * FROM " . G5_TABLE_PREFIX . "security_regex_spam 
            WHERE srs_enabled = 1 
            ORDER BY srs_priority ASC, srs_id ASC";
    
    $result = sql_query($sql, false);
    if (!$result) {
        return false;
    }

    while ($rule = sql_fetch_array($result)) {
        $pattern = $rule['srs_pattern'];
        $targets = explode(',', $rule['srs_target']);
        $case_sensitive = $rule['srs_case_sensitive'];
        
        // 각 대상에서 패턴 검사
        foreach ($targets as $target) {
            $target = trim($target);
            if (!isset($check_data[$target]) || empty($check_data[$target])) {
                continue;
            }
            
            $content = $check_data[$target];
            $flags = $case_sensitive ? '' : 'i';
            
            // 정규식 패턴 검사
            if (@preg_match('/' . $pattern . '/' . $flags . 'u', $content, $matches)) {
                // 매칭된 경우
                security_regex_spam_update_hit_count($rule['srs_id']);
                
                return array(
                    'rule' => $rule,
                    'target' => $target,
                    'matched_text' => $matches[0] ?? '',
                    'full_content' => $content
                );
            }
        }
    }
    
    return false;
}

/**
 * 정규식 규칙 매칭 횟수 업데이트
 */
function security_regex_spam_update_hit_count($rule_id) {
    $sql = "UPDATE " . G5_TABLE_PREFIX . "security_regex_spam 
            SET srs_hit_count = srs_hit_count + 1 
            WHERE srs_id = " . (int)$rule_id;
    sql_query($sql, false);
}

/**
 * 스팸 탐지 처리
 */
function security_regex_spam_handle_detection($spam_result, $target_type, $ip, $mb_id, $bo_table = '', $wr_id = 0, $full_data = array()) {
    $rule = $spam_result['rule'];
    $action = $rule['srs_action'];
    $matched_text = $spam_result['matched_text'];
    $full_content = json_encode($full_data, JSON_UNESCAPED_UNICODE);
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 로그 기록
    security_regex_spam_log_detection($rule['srs_id'], $ip, $mb_id, $target_type, $bo_table, $wr_id, $matched_text, $full_content, $action, $user_agent);
    
    switch ($action) {
        case 'block':
            // 완전 차단 - 에러 메시지와 함께 중단
            security_regex_spam_show_block_message($rule['srs_name'], $matched_text);
            exit;
            
        case 'ghost':
            // 유령 모드 - 작성자에게만 보임 (구현은 gnuboard5 구조에 따라 조정 필요)
            global $g5_regex_spam_ghost_mode;
            $g5_regex_spam_ghost_mode = true;
            break;
            
        case 'delete':
            // 자동 삭제 - 작성 후 즉시 삭제 (로그만 남김)
            global $g5_regex_spam_auto_delete;
            $g5_regex_spam_auto_delete = true;
            break;
    }
    
    // 자동 IP 차단 설정이 활성화된 경우
    if (security_regex_spam_auto_block_enabled()) {
        security_regex_spam_add_ip_block($ip, $rule['srs_name'], $matched_text);
    }
}

/**
 * 스팸 탐지 로그 기록
 */
function security_regex_spam_log_detection($rule_id, $ip, $mb_id, $target_type, $bo_table, $wr_id, $matched_text, $full_content, $action, $user_agent) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_regex_spam_log SET
                srsl_srs_id = " . (int)$rule_id . ",
                srsl_ip = '" . sql_escape_string($ip) . "',
                srsl_mb_id = '" . sql_escape_string($mb_id) . "',
                srsl_target_type = '" . sql_escape_string($target_type) . "',
                srsl_bo_table = '" . sql_escape_string($bo_table) . "',
                srsl_wr_id = " . (int)$wr_id . ",
                srsl_matched_text = '" . sql_escape_string(substr($matched_text, 0, 1000)) . "',
                srsl_full_content = '" . sql_escape_string($full_content) . "',
                srsl_action_taken = '" . sql_escape_string($action) . "d',
                srsl_user_agent = '" . sql_escape_string(substr($user_agent, 0, 500)) . "',
                srsl_datetime = NOW()";

    sql_query($sql, false);
}

/**
 * 자동 IP 차단 활성화 여부 확인
 */
function security_regex_spam_auto_block_enabled() {
    static $auto_block = null;
    
    if ($auto_block === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'regex_spam_auto_block'";
        $result = sql_query($sql, false);
        
        if ($result && $row = sql_fetch_array($result)) {
            $auto_block = ($row['sc_value'] == '1');
        } else {
            $auto_block = false;
        }
    }
    
    return $auto_block;
}

/**
 * IP 자동 차단 추가
 */
function security_regex_spam_add_ip_block($ip, $rule_name, $matched_text) {
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

    // 차단 기간 가져오기
    $block_duration = security_regex_spam_get_block_duration();
    $end_datetime = date('Y-m-d H:i:s', time() + $block_duration);

    // 차단 사유 생성
    $reason = "정규식 스팸 탐지 [{$rule_name}]: " . substr($matched_text, 0, 100);

    // IP 차단 추가
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_block SET
                sb_ip = '" . sql_escape_string($ip) . "',
                sb_start_ip = {$ip_long},
                sb_end_ip = {$ip_long},
                sb_reason = '" . sql_escape_string($reason) . "',
                sb_block_type = 'auto_regex_spam',
                sb_block_level = 'access',
                sb_duration = 'temporary',
                sb_end_datetime = '{$end_datetime}',
                sb_hit_count = 0,
                sb_status = 'active',
                sb_datetime = NOW()";

    sql_query($sql, false);

    // 추가 로그 기록
    $log_sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_log SET
                    sl_ip = '" . sql_escape_string($ip) . "',
                    sl_datetime = NOW(),
                    sl_action = 'auto_block',
                    sl_reason = '" . sql_escape_string($reason) . "'";

    sql_query($log_sql, false);
}

/**
 * 차단 기간 가져오기
 */
function security_regex_spam_get_block_duration() {
    static $block_duration = null;
    
    if ($block_duration === null) {
        $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = 'regex_spam_block_duration'";
        $result = sql_query($sql, false);
        
        if ($result && $row = sql_fetch_array($result)) {
            $block_duration = (int)$row['sc_value'];
        } else {
            $block_duration = 3600; // 기본값: 1시간
        }
    }
    
    return $block_duration;
}

/**
 * 차단 메시지 표시
 */
function security_regex_spam_show_block_message($rule_name, $matched_text) {
    // HTTP 403 상태 코드 전송
    http_response_code(403);

    $safe_matched = htmlspecialchars(substr($matched_text, 0, 100));
    $safe_rule_name = htmlspecialchars($rule_name);

    echo "<!DOCTYPE html>
<html lang=\"ko\">
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>스팸 콘텐츠 차단</title>
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
        <div class=\"block-icon\">🚫</div>
        <h1 class=\"block-title\">스팸 콘텐츠가 차단되었습니다</h1>

        <div class=\"block-message\">
            작성하신 내용에 스팸으로 분류되는 키워드가 포함되어 게시가 제한되었습니다.
        </div>

        <div class=\"block-info\">
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 규칙:</span> {$safe_rule_name}
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">탐지 내용:</span> {$safe_matched}
            </div>
            <div class=\"block-info-item\">
                <span class=\"block-info-label\">차단 시간:</span> " . date('Y-m-d H:i:s') . "
            </div>
        </div>

        <div class=\"contact-info\">
            <strong>정상적인 게시물이 차단되었나요?</strong><br>
            스팸 필터에 의해 잘못 차단된 경우 사이트 관리자에게 문의해주세요.<br>
            차단된 키워드를 제거하고 다시 시도해보세요.
        </div>
    </div>
</body>
</html>";
}
?>