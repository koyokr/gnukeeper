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
            
        case 'toggle_404':
            $behavior_404_enabled = $_POST['behavior_404_enabled'] ?? '0';
            gk_set_spam_config('behavior_404_enabled', $behavior_404_enabled);

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('404 차단 기능이 ' . ($behavior_404_enabled == '1' ? '활성화' : '비활성화') . '되었습니다.');
            break;
            
        case 'toggle_referer':
            $behavior_referer_enabled = $_POST['behavior_referer_enabled'] ?? '0';
            gk_set_spam_config('behavior_referer_enabled', $behavior_referer_enabled);

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('레퍼러 검증 기능이 ' . ($behavior_referer_enabled == '1' ? '활성화' : '비활성화') . '되었습니다.');
            break;
            
        case 'save_404_config':
            $behavior_404_limit = (int)($_POST['behavior_404_limit'] ?? 10);
            $behavior_404_window = (int)($_POST['behavior_404_window'] ?? 300);
            $behavior_404_block_duration = (int)($_POST['behavior_404_block_duration'] ?? 1800);

            // 유효성 검사
            if ($behavior_404_limit < 1 || $behavior_404_limit > 100) {
                alert('최대 접속 횟수는 1~100 사이의 값이어야 합니다.');
                break;
            }
            
            if ($behavior_404_window < 60 || $behavior_404_window > 86400) {
                alert('감지 시간 윈도우는 60초~86400초 사이의 값이어야 합니다.');
                break;
            }
            
            if ($behavior_404_block_duration < 300 || $behavior_404_block_duration > 86400) {
                alert('차단 시간은 300초~86400초 사이의 값이어야 합니다.');
                break;
            }

            // 설정 저장
            gk_set_spam_config('behavior_404_limit', $behavior_404_limit);
            gk_set_spam_config('behavior_404_window', $behavior_404_window);
            gk_set_spam_config('behavior_404_block_duration', $behavior_404_block_duration);

            alert('404 차단 설정이 저장되었습니다.');
            break;
            
        case 'toggle_register':
            $multiuser_register_enabled = $_POST['multiuser_register_enabled'] ?? '0';
            gk_set_spam_config('multiuser_register_enabled', $multiuser_register_enabled);

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('회원가입 차단 기능이 ' . ($multiuser_register_enabled == '1' ? '활성화' : '비활성화') . '되었습니다.');
            break;
            
        case 'toggle_login_multi':
            $multiuser_login_enabled = $_POST['multiuser_login_enabled'] ?? '0';
            gk_set_spam_config('multiuser_login_enabled', $multiuser_login_enabled);

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('로그인 차단 기능이 ' . ($multiuser_login_enabled == '1' ? '활성화' : '비활성화') . '되었습니다.');
            break;
            
        case 'save_register_config':
            $multiuser_register_limit = (int)($_POST['multiuser_register_limit'] ?? 3);
            $multiuser_register_window = (int)($_POST['multiuser_register_window'] ?? 24) * 3600; // 시간을 초로 변환
            $multiuser_register_block_duration = (int)($_POST['multiuser_register_block_duration'] ?? 60) * 60; // 분을 초로 변환

            // 유효성 검사
            if ($multiuser_register_limit < 1 || $multiuser_register_limit > 20) {
                alert('최대 가입 수는 1~20 사이의 값이어야 합니다.');
                break;
            }
            
            if ($multiuser_register_window < 3600 || $multiuser_register_window > 604800) { // 1시간~1주일
                alert('감지 시간 윈도우는 1~168시간 사이의 값이어야 합니다.');
                break;
            }
            
            if ($multiuser_register_block_duration < 300 || $multiuser_register_block_duration > 86400) { // 5분~24시간
                alert('차단 시간은 5~1440분 사이의 값이어야 합니다.');
                break;
            }

            // 설정 저장
            gk_set_spam_config('multiuser_register_limit', $multiuser_register_limit);
            gk_set_spam_config('multiuser_register_window', $multiuser_register_window);
            gk_set_spam_config('multiuser_register_block_duration', $multiuser_register_block_duration);

            alert('회원가입 차단 설정이 저장되었습니다.');
            break;
            
        case 'save_login_multi_config':
            $multiuser_login_limit = (int)($_POST['multiuser_login_limit'] ?? 5);
            $multiuser_login_window = (int)($_POST['multiuser_login_window'] ?? 24) * 3600; // 시간을 초로 변환
            $multiuser_login_block_duration = (int)($_POST['multiuser_login_block_duration'] ?? 30) * 60; // 분을 초로 변환

            // 유효성 검사
            if ($multiuser_login_limit < 2 || $multiuser_login_limit > 50) {
                alert('최대 로그인 수는 2~50 사이의 값이어야 합니다.');
                break;
            }
            
            if ($multiuser_login_window < 3600 || $multiuser_login_window > 604800) { // 1시간~1주일
                alert('감지 시간 윈도우는 1~168시간 사이의 값이어야 합니다.');
                break;
            }
            
            if ($multiuser_login_block_duration < 300 || $multiuser_login_block_duration > 86400) { // 5분~24시간
                alert('차단 시간은 5~1440분 사이의 값이어야 합니다.');
                break;
            }

            // 설정 저장
            gk_set_spam_config('multiuser_login_limit', $multiuser_login_limit);
            gk_set_spam_config('multiuser_login_window', $multiuser_login_window);
            gk_set_spam_config('multiuser_login_block_duration', $multiuser_login_block_duration);

            alert('로그인 차단 설정이 저장되었습니다.');
            break;

        case 'toggle_regex_spam':
            $regex_spam_enabled = $_POST['regex_spam_enabled'] ?? '0';
            gk_set_spam_config('regex_spam_enabled', $regex_spam_enabled);

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('정규식 스팸 차단 기능이 ' . ($regex_spam_enabled == '1' ? '활성화' : '비활성화') . '되었습니다.');
            break;

        case 'save_regex_spam_config':
            $regex_spam_block_duration = (int)($_POST['regex_spam_block_duration'] ?? 60) * 60; // 분을 초로 변환
            $regex_spam_auto_block = isset($_POST['regex_spam_auto_block']) ? '1' : '0';
            
            // 검사 대상 설정
            $regex_spam_check_title = isset($_POST['regex_spam_check_title']) ? '1' : '0';
            $regex_spam_check_content = isset($_POST['regex_spam_check_content']) ? '1' : '0';
            $regex_spam_check_comment = isset($_POST['regex_spam_check_comment']) ? '1' : '0';
            $regex_spam_check_name = isset($_POST['regex_spam_check_name']) ? '1' : '0';
            $regex_spam_check_email = isset($_POST['regex_spam_check_email']) ? '1' : '0';

            // 유효성 검사
            if ($regex_spam_block_duration < 300 || $regex_spam_block_duration > 86400) { // 5분~24시간
                alert('차단 시간은 5~1440분 사이의 값이어야 합니다.');
                break;
            }

            // 설정 저장
            gk_set_spam_config('regex_spam_block_duration', $regex_spam_block_duration);
            gk_set_spam_config('regex_spam_auto_block', $regex_spam_auto_block);
            gk_set_spam_config('regex_spam_check_title', $regex_spam_check_title);
            gk_set_spam_config('regex_spam_check_content', $regex_spam_check_content);
            gk_set_spam_config('regex_spam_check_comment', $regex_spam_check_comment);
            gk_set_spam_config('regex_spam_check_name', $regex_spam_check_name);
            gk_set_spam_config('regex_spam_check_email', $regex_spam_check_email);

            alert('정규식 스팸 차단 설정이 저장되었습니다.');
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