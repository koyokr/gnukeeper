<?php
/**
 * Security Block API Endpoint
 *
 * 모든 AJAX 요청을 처리하는 통합 API 엔드포인트
 * Plugin의 GK_BlockAdmin 클래스를 사용하여 비즈니스 로직 처리
 */

require_once './_common.php';

// JSON 응답을 위한 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// 관리자 권한 체크
if ($is_admin !== 'super') {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청 방식입니다.']);
    exit;
}

// action 파라미터 확인
$action = $_POST['action'] ?? '';
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'action이 지정되지 않았습니다.']);
    exit;
}

// Plugin Admin 클래스 인스턴스
$blockAdmin = GK_BlockAdmin::getInstance();

try {
    switch ($action) {
        // IP 차단 관련
        case 'add_block':
            $ip = trim((string)($_POST['block_ip'] ?? ''));
            $reason = trim((string)($_POST['block_reason'] ?? ''));
            $result = $blockAdmin->addIPBlock($ip, $reason);
            break;

        case 'remove_block':
            $ip = trim((string)($_POST['block_ip'] ?? ''));
            $result = $blockAdmin->removeIPBlock($ip);
            break;

        case 'get_blocked_ips':
            $ips = $blockAdmin->getBlockedIPs();
            $result = ['success' => true, 'data' => $ips];
            break;

        // 예외 IP 관련
        case 'add_whitelist':
            $ip = trim((string)($_POST['whitelist_ip'] ?? ''));
            $memo = trim((string)($_POST['whitelist_memo'] ?? ''));
            $result = $blockAdmin->addWhitelistIP($ip, $memo);
            break;

        case 'remove_whitelist':
            $id = (int)($_POST['whitelist_id'] ?? 0);
            $result = $blockAdmin->removeWhitelistIP($id);
            break;

        case 'get_whitelist_ips':
            $ips = $blockAdmin->getWhitelistIPs();
            $result = ['success' => true, 'data' => $ips];
            break;

        // 해외 IP 차단 관련
        case 'toggle_foreign_block':
            $enabled = ((string)($_POST['enabled'] ?? '0')) === '1';
            $result = $blockAdmin->toggleForeignBlock($enabled);
            break;

        // 고급 IP 차단 관리 토글
        case 'toggle_gk_block':
            $enabled = ((string)($_POST['enabled'] ?? '0')) === '1';
            $result = $blockAdmin->toggleGKBlock($enabled);
            break;

        // 국가별 차단 관련
        case 'add_country_block':
            $countryCode = trim((string)($_POST['country_code'] ?? ''));
            $countryName = trim((string)($_POST['country_name'] ?? ''));
            $countryFlag = trim((string)($_POST['country_flag'] ?? ''));
            $result = $blockAdmin->addCountryBlock($countryCode, $countryName, $countryFlag);
            break;

        case 'remove_country_block':
            $countryCode = trim((string)($_POST['country_code'] ?? ''));
            $result = $blockAdmin->removeCountryBlock($countryCode);
            break;

        case 'get_blocked_countries':
            $countries = $blockAdmin->getBlockedCountries();
            $result = ['success' => true, 'data' => $countries];
            break;

        default:
            $result = ['success' => false, 'message' => '알 수 없는 action입니다: ' . $action];
            break;
    }

} catch (Exception $e) {
    $result = ['success' => false, 'message' => '처리 중 오류가 발생했습니다: ' . $e->getMessage()];
}

// JSON 응답 출력
echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;