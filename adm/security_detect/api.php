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

// GnuKeeper 플러그인 로드
try {
    require_once G5_PATH . '/plugin/gnukeeper/bootstrap.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'GnuKeeper plugin not found: ' . $e->getMessage()]);
    exit;
}

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
                    // 스팸 콘텐츠 탐지 기능 토글
                    $result = GK_Common::set_config('spam_content_enabled', $enabled ? '1' : '0');
                    $message = $enabled ? '스팸 콘텐츠 탐지 시 자동 차단이 활성화되었습니다.' : '스팸 콘텐츠 탐지 시 자동 차단이 비활성화되었습니다.';
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

        // 스팸 콘텐츠 관련 API
        case 'get_spam_content_logs':
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 10);
            
            // 필터 옵션
            $filters = [];
            if (!empty($_POST['action_filter'])) $filters['action'] = $_POST['action_filter'];
            if (!empty($_POST['score'])) $filters['score'] = $_POST['score'];
            if (!empty($_POST['days'])) $filters['days'] = (int)$_POST['days'];
            
            // 직접 데이터베이스에서 조회 (클래스 의존성 제거)
            $offset = ($page - 1) * $limit;
            
            // WHERE 조건 구성
            $whereConditions = [];
            
            if (!empty($filters['action'])) {
                $action = sql_escape_string($filters['action']);
                $whereConditions[] = "sscl_action_taken = '{$action}'";
            }
            
            if (!empty($filters['score'])) {
                switch ($filters['score']) {
                    case 'high':
                        $whereConditions[] = "sscl_total_score >= 12";
                        break;
                    case 'medium':
                        $whereConditions[] = "sscl_total_score >= 7 AND sscl_total_score <= 11";
                        break;
                    case 'low':
                        $whereConditions[] = "sscl_total_score <= 6";
                        break;
                }
            }
            
            if (!empty($filters['days'])) {
                $days = (int)$filters['days'];
                $whereConditions[] = "sscl_datetime >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
            }
            
            $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
            
            $sql = "SELECT * FROM ".G5_TABLE_PREFIX."security_spam_content_log 
                    {$whereClause}
                    ORDER BY sscl_datetime DESC 
                    LIMIT {$offset}, {$limit}";
            $result = sql_query($sql);
            
            $logs = [];
            while ($row = sql_fetch_array($result)) {
                $row['detected_keywords'] = json_decode($row['sscl_detected_keywords'], true);
                $logs[] = $row;
            }
            
            // 총 개수
            $countSql = "SELECT COUNT(*) as total FROM ".G5_TABLE_PREFIX."security_spam_content_log {$whereClause}";
            $countResult = sql_fetch($countSql);
            $total = (int)$countResult['total'];
            
            $response = [
                'success' => true, 
                'data' => $logs, 
                'total_pages' => ceil($total / $limit),
                'current_page' => $page,
                'total' => $total,
                'debug_info' => [
                    'logs_count' => count($logs),
                    'page' => $page,
                    'limit' => $limit,
                    'filters' => $filters,
                    'sql' => $sql
                ]
            ];
            echo json_encode($response);
            break;
            
        case 'get_spam_keywords':
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 100);
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT * FROM ".G5_TABLE_PREFIX."security_spam_keywords 
                    ORDER BY ssk_category, ssk_score DESC, ssk_keyword 
                    LIMIT {$offset}, {$limit}";
            $result = sql_query($sql);
            
            $keywords = [];
            while ($row = sql_fetch_array($result)) {
                $keywords[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $keywords]);
            break;
            
        case 'add_spam_keyword':
            $category = trim($_POST['category'] ?? '');
            $keyword = trim($_POST['keyword'] ?? '');
            $score = (int)($_POST['score'] ?? 3);
            
            if (empty($category) || empty($keyword)) {
                echo json_encode(['success' => false, 'message' => '카테고리와 키워드를 입력해주세요.']);
                break;
            }
            
            if ($score < 1 || $score > 5) {
                echo json_encode(['success' => false, 'message' => '위험도는 1-5 사이로 설정해주세요.']);
                break;
            }
            
            require_once G5_PATH . '/plugin/gnukeeper/filters/SpamContentFilter.php';
            $spamFilter = new GK_SpamContentFilter();
            $result = $spamFilter->addKeyword($category, $keyword, $score);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => '키워드가 추가되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '키워드 추가에 실패했습니다. 이미 존재하는 키워드일 수 있습니다.']);
            }
            break;
            
        case 'update_keyword_score':
            $keyword = trim($_POST['keyword'] ?? '');
            $score = (int)($_POST['score'] ?? 3);
            
            if (empty($keyword)) {
                echo json_encode(['success' => false, 'message' => '키워드를 지정해주세요.']);
                break;
            }
            
            if ($score < 1 || $score > 5) {
                echo json_encode(['success' => false, 'message' => '위험도는 1-5 사이로 설정해주세요.']);
                break;
            }
            
            require_once G5_PATH . '/plugin/gnukeeper/filters/SpamContentFilter.php';
            $spamFilter = new GK_SpamContentFilter();
            $result = $spamFilter->updateKeywordScore($keyword, $score);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => '키워드 점수가 수정되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '키워드 점수 수정에 실패했습니다.']);
            }
            break;
            
        case 'delete_spam_keyword':
            $keyword = trim($_POST['keyword'] ?? '');
            
            if (empty($keyword)) {
                echo json_encode(['success' => false, 'message' => '키워드를 지정해주세요.']);
                break;
            }
            
            require_once G5_PATH . '/plugin/gnukeeper/filters/SpamContentFilter.php';
            $spamFilter = new GK_SpamContentFilter();
            $result = $spamFilter->removeKeyword($keyword);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => '키워드가 삭제되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '키워드 삭제에 실패했습니다.']);
            }
            break;
            
        case 'block_spam_content_ip':
            $ip = trim($_POST['ip'] ?? '');
            
            if (empty($ip)) {
                echo json_encode(['success' => false, 'message' => 'IP 주소를 지정해주세요.']);
                break;
            }
            
            $blockManager = GK_BlockManager::getInstance();
            $reason = '스팸 콘텐츠 탐지로 인한 수동 차단';
            $result = $blockManager->addBlock($ip, $reason, 'auto_spam');
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => "IP {$ip}가 차단되었습니다."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'IP 차단에 실패했습니다.']);
            }
            break;
            
        case 'delete_spam_content_log':
            $logId = (int)($_POST['log_id'] ?? 0);
            
            if ($logId <= 0) {
                echo json_encode(['success' => false, 'message' => '로그 ID를 지정해주세요.']);
                break;
            }
            
            $sql = "DELETE FROM ".G5_TABLE_PREFIX."security_spam_content_log WHERE sscl_id = {$logId}";
            $result = sql_query($sql);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => '로그가 삭제되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '로그 삭제에 실패했습니다.']);
            }
            break;
            
        case 'delete_all_spam_content_logs':
            $sql = "DELETE FROM ".G5_TABLE_PREFIX."security_spam_content_log";
            $result = sql_query($sql);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => '모든 스팸 탐지 로그가 삭제되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '로그 삭제에 실패했습니다.']);
            }
            break;
            
        case 'reset_spam_keywords':
            require_once G5_PATH . '/plugin/gnukeeper/filters/SpamContentFilter.php';
            $spamFilter = new GK_SpamContentFilter();
            $result = $spamFilter->resetKeywordsToDefault();
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => '스팸 키워드가 기본 설정으로 초기화되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '키워드 초기화에 실패했습니다.']);
            }
            break;
            
        case 'delete_category':
            $category = trim($_POST['category'] ?? '');
            
            if (empty($category)) {
                echo json_encode(['success' => false, 'message' => '카테고리를 지정해주세요.']);
                break;
            }
            
            // 해당 카테고리의 모든 키워드 삭제
            $sql = "DELETE FROM ".G5_TABLE_PREFIX."security_spam_keywords 
                    WHERE ssk_category = '" . sql_escape_string($category) . "'";
            $result = sql_query($sql);
            
            if ($result) {
                $deletedCount = sql_affected_rows();
                echo json_encode([
                    'success' => true, 
                    'message' => "카테고리 \"{$category}\"와 {$deletedCount}개 키워드가 삭제되었습니다."
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '카테고리 삭제에 실패했습니다.']);
            }
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