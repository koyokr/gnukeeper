<?php
require_once './_common.php';

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

        default:
            echo json_encode(['success' => false, 'message' => '지원하지 않는 액션입니다.']);
            break;
    }

} catch (Exception $e) {
    error_log('GnuKeeper Detect API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '서버 오류가 발생했습니다: ' . $e->getMessage()
    ]);
}
?>