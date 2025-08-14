<?php
// 에러 리포팅 설정 (프로덕션용)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);

try {
    require_once './_common.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load common file: ' . $e->getMessage()]);
    exit;
}

// 권한 체크
if (!isset($member) || $member['mb_level'] < 10) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다.']);
    exit;
}

// API 요청만 처리
if (!isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// JSON 응답 헤더 설정
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// 클래스 존재 확인
if (!class_exists('GK_SpamAdmin')) {
    echo json_encode(['success' => false, 'message' => 'GK_SpamAdmin class not found']);
    exit;
}

$spamAdmin = GK_SpamAdmin::getInstance();
$action = $_POST['action'];

try {
    switch ($action) {
        case 'toggle_feature':
            $feature = $_POST['feature'] ?? '';
            $enabled = ($_POST['enabled'] ?? '0') === '1';

            $result = false;
            $message = '';

            switch ($feature) {
                case 'login_block':
                    $result = $spamAdmin->toggleLoginBlock($enabled);
                    $message = $enabled ? '로그인 차단이 활성화되었습니다.' : '로그인 차단이 비활성화되었습니다.';
                    break;
                case 'useragent_block':
                    $result = $spamAdmin->toggleUserAgentBlock($enabled);
                    $message = $enabled ? 'User-Agent 차단이 활성화되었습니다.' : 'User-Agent 차단이 비활성화되었습니다.';
                    break;
                case 'behavior_pattern':
                    // 행동 패턴 차단 (404 + Referer 통합)
                    $result1 = $spamAdmin->toggleBehavior404($enabled);
                    $result2 = $spamAdmin->toggleBehaviorReferer($enabled);
                    $result = $result1 && $result2;
                    $message = $enabled ? '행동 패턴 차단이 활성화되었습니다.' : '행동 패턴 차단이 비활성화되었습니다.';
                    break;
                case 'multiuser_protection':
                    // 다중 사용자 보호 (가입 + 로그인 통합)
                    $result1 = $spamAdmin->toggleMultiUserRegister($enabled);
                    $result2 = $spamAdmin->toggleMultiUserLogin($enabled);
                    $result = $result1 && $result2;
                    $message = $enabled ? '다중 사용자 차단이 활성화되었습니다.' : '다중 사용자 차단이 비활성화되었습니다.';
                    break;
                case 'regex_spam':
                    $result = $spamAdmin->toggleRegexSpam($enabled);
                    $message = $enabled ? '정규식 스팸 차단이 활성화되었습니다.' : '정규식 스팸 차단이 비활성화되었습니다.';
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => '알 수 없는 기능입니다.']);
                    exit;
            }

            if ($result) {
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => '설정 변경에 실패했습니다.']);
            }
            break;

        case 'get_login_fail_logs':
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 10);
            $result = $spamAdmin->getLoginFailLogs($page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'get_detect_logs':
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 10);
            $result = $spamAdmin->getSpamLogs($page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'unblock_ip':
            $ip = $_POST['ip'] ?? '';
            $result = $spamAdmin->unblockIP($ip);
            echo json_encode($result);
            break;

        case 'clear_login_fail_logs':
            $days = (int)($_POST['days'] ?? 30);
            $result = $spamAdmin->clearLoginFailLogs($days);
            echo json_encode($result);
            break;

        case 'clear_detect_logs':
            $days = (int)($_POST['days'] ?? 30);
            $result = $spamAdmin->clearSpamLogs($days);
            echo json_encode($result);
            break;

        case 'add_to_block_list':
            $ip = $_POST['ip'] ?? '';
            $result = $spamAdmin->addToBlockList($ip);
            echo json_encode($result);
            break;

        case 'delete_suspect_ip':
            $ip = $_POST['ip'] ?? '';
            $mb_id = $_POST['mb_id'] ?? '';
            
            if (empty($ip)) {
                echo json_encode(['success' => false, 'message' => 'IP 주소가 필요합니다.']);
                break;
            }
            
            $result = $spamAdmin->deleteSuspectIP($ip, $mb_id);
            echo json_encode($result);
            break;

        case 'get_bot_logs':
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 10);
            $result = $spamAdmin->getBotLogs($page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'delete_bot_log':
            $log_id = $_POST['log_id'] ?? '';
            
            if (empty($log_id)) {
                echo json_encode(['success' => false, 'message' => '로그 ID가 필요합니다.']);
                break;
            }
            
            $result = $spamAdmin->deleteBotLog($log_id);
            echo json_encode($result);
            break;

        case 'get_behavior_logs':
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 10);
            $result = $spamAdmin->getBehaviorLogs($page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'delete_behavior_log':
            $log_id = $_POST['log_id'] ?? '';
            $ip = $_POST['ip'] ?? '';
            
            if (empty($log_id) && empty($ip)) {
                echo json_encode(['success' => false, 'message' => '로그 ID 또는 IP 주소가 필요합니다.']);
                break;
            }
            
            $result = $spamAdmin->deleteBehaviorLog($log_id, $ip);
            echo json_encode($result);
            break;

        case 'delete_all_login_fail':
            $result = $spamAdmin->deleteAllLoginFail();
            echo json_encode($result);
            break;

        case 'delete_all_behavior_logs':
            $result = $spamAdmin->deleteAllBehaviorLogs();
            echo json_encode($result);
            break;

        case 'delete_all_bot_logs':
            $result = $spamAdmin->deleteAllBotLogs();
            echo json_encode($result);
            break;

        case 'get_multiuser_logs':
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 10);
            $result = $spamAdmin->getMultiUserLogs($page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'delete_multiuser_log':
            $log_id = $_POST['log_id'] ?? '';
            $ip = $_POST['ip'] ?? '';
            
            if (empty($log_id) && empty($ip)) {
                echo json_encode(['success' => false, 'message' => '로그 ID 또는 IP 주소가 필요합니다.']);
                break;
            }
            
            $result = $spamAdmin->deleteMultiUserLog($log_id, $ip);
            echo json_encode($result);
            break;

        case 'delete_all_multiuser_logs':
            $result = $spamAdmin->deleteAllMultiUserLogs();
            echo json_encode($result);
            break;

        case 'block_multiuser_ip':
            $ip = $_POST['ip'] ?? '';
            
            if (empty($ip)) {
                echo json_encode(['success' => false, 'message' => 'IP 주소가 필요합니다.']);
                break;
            }
            
            $result = $spamAdmin->blockMultiUserIP($ip);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '지원하지 않는 액션입니다.']);
            break;
    }

} catch (Exception $e) {
    error_log('GnuKeeper Detect API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '서버 오류가 발생했습니다: ' . $e->getMessage(),
        'error_type' => 'exception',
        'error_line' => $e->getLine(),
        'error_file' => basename($e->getFile())
    ]);
} catch (Error $e) {
    error_log('GnuKeeper Detect API Fatal Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage(),
        'error_type' => 'fatal_error',
        'error_line' => $e->getLine(),
        'error_file' => basename($e->getFile())
    ]);
}
?>