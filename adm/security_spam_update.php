<?php
$sub_menu = '950500';
require_once './_common.php';
auth_check_menu($auth, $sub_menu, 'w');

function gk_set_spam_config($key, $value) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_config (sc_key, sc_value) VALUES ('". sql_escape_string($key) ."', '". sql_escape_string($value) ."')
            ON DUPLICATE KEY UPDATE sc_value = '". sql_escape_string($value) ."'";
    return sql_query($sql);
}

$action = $_POST['action'] ?? '';

if ($action && isset($is_admin) && $is_admin == 'super') {
    switch ($action) {
        case 'toggle_login':
            $login_block_enabled = $_POST['login_block_enabled'] ?? '0';
            gk_set_spam_config('login_block_enabled', $login_block_enabled);

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('로그인 차단 기능이 ' . ($login_block_enabled == '1' ? '활성화' : '비활성화') . '되었습니다.');
            break;

        case 'save_login_config':
            $login_attempt_limit = (int)($_POST['login_attempt_limit'] ?? 5);
            $login_attempt_window = (int)($_POST['login_attempt_window'] ?? 300);
            $auto_block_duration = (int)($_POST['auto_block_duration'] ?? 600);

            // 유효성 검사
            if ($login_attempt_limit < 1 || $login_attempt_limit > 50) {
                alert('최대 실패 횟수는 1~50 사이의 값이어야 합니다.');
                break;
            }

            // 설정 저장
            gk_set_spam_config('login_attempt_limit', $login_attempt_limit);
            gk_set_spam_config('login_attempt_window', $login_attempt_window);
            gk_set_spam_config('auto_block_duration', $auto_block_duration);

            alert('로그인 차단 설정이 저장되었습니다.');
            break;

        case 'save_config':
            $login_block_enabled = $_POST['login_block_enabled'] ?? '0';
            $login_attempt_limit = (int)($_POST['login_attempt_limit'] ?? 5);
            $login_attempt_window = (int)($_POST['login_attempt_window'] ?? 300);
            $auto_block_duration = (int)($_POST['auto_block_duration'] ?? 600);

            // 유효성 검사
            if ($login_attempt_limit < 1 || $login_attempt_limit > 50) {
                alert('최대 실패 횟수는 1~50 사이의 값이어야 합니다.');
                break;
            }

            // 설정 저장
            gk_set_spam_config('login_block_enabled', $login_block_enabled);
            gk_set_spam_config('login_attempt_limit', $login_attempt_limit);
            gk_set_spam_config('login_attempt_window', $login_attempt_window);
            gk_set_spam_config('auto_block_duration', $auto_block_duration);

            alert('스팸 차단 설정이 저장되었습니다.');
            break;
            
        case 'save_spam_level':
            $spam_block_level = isset($_POST['spam_block_level']) && is_array($_POST['spam_block_level']) 
                                ? implode(',', $_POST['spam_block_level']) 
                                : 'access';
            gk_set_spam_config('spam_block_level', $spam_block_level);
            
            alert('스팸 차단 수준이 저장되었습니다.');
            break;
            
        case 'toggle_useragent':
            $useragent_block_enabled = $_POST['useragent_block_enabled'] ?? '0';
            gk_set_spam_config('useragent_block_enabled', $useragent_block_enabled);

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('User-Agent 차단 기능이 ' . ($useragent_block_enabled == '1' ? '활성화' : '비활성화') . '되었습니다.');
            break;
            
        case 'save_useragent_level':
            $useragent_block_level = isset($_POST['useragent_block_level']) && is_array($_POST['useragent_block_level']) 
                                     ? implode(',', $_POST['useragent_block_level']) 
                                     : 'access';
            gk_set_spam_config('useragent_block_level', $useragent_block_level);
            
            alert('User-Agent 차단 수준이 저장되었습니다.');
            break;

        default:
            alert('알 수 없는 액션입니다.');
            break;
    }

    // AJAX 요청이 아닐 때만 리다이렉트
    if (!isset($_POST['ajax'])) {
        goto_url('./security_spam.php');
    }
} else if ($action) {
    // 권한 없음
    if (isset($_POST['ajax'])) {
        echo 'error:권한이 없습니다.';
        exit;
    }
    alert('권한이 없습니다.');
    goto_url('./security_spam.php');
}
?>