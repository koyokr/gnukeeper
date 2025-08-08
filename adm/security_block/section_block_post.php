<?php
require_once './_common.php';
if (!defined('_GNUBOARD_')) exit;

// JSON 응답을 위한 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($is_admin !== 'super') {
        echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
        exit;
    }
    
    $current_admin_ip = $_SERVER['REMOTE_ADDR'];

    switch ($_POST['action']) {
        case 'add_block':
            $block_ip = trim($_POST['block_ip'] ?? '');
            $block_reason = trim($_POST['block_reason'] ?? '');

            if (empty($block_ip)) {
                echo json_encode(['success' => false, 'message' => 'IP 주소를 입력해주세요.']);
                exit;
            }

            // 관리자 IP 보호
            if ($block_ip === $current_admin_ip || strpos($block_ip, '*') !== false) {
                // 와일드카드 체크
                $pattern = str_replace('*', '([0-9\.]*)', $block_ip);
                if (preg_match('/' . $pattern . '/', $current_admin_ip)) {
                    echo json_encode(['success' => false, 'message' => '관리자 IP는 차단할 수 없습니다.']);
                    exit;
                }
            }

            // 현재 그누보드 차단 목록 가져오기
            $current_blocks = isset($config['cf_intercept_ip']) ? $config['cf_intercept_ip'] : '';
            $blocked_ips = array_filter(array_map('trim', explode("\n", $current_blocks)));
            
            // 중복 체크
            if (in_array($block_ip, $blocked_ips)) {
                echo json_encode(['success' => false, 'message' => '이미 차단된 IP입니다.']);
                exit;
            }
            
            // 새 IP 추가
            $blocked_ips[] = $block_ip;
            $new_block_list = implode("\n", $blocked_ips);
            
            // 그누보드 설정 업데이트
            $sql = "UPDATE {$g5['config_table']} SET cf_intercept_ip = '" . sql_escape_string($new_block_list) . "'";
            if (sql_query($sql)) {
                echo json_encode(['success' => true, 'message' => 'IP가 차단되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '차단 설정 저장에 실패했습니다.']);
            }
            break;
            
        case 'remove_block':
            $remove_ip = trim($_POST['ip'] ?? '');
            
            if (empty($remove_ip)) {
                echo json_encode(['success' => false, 'message' => '삭제할 IP를 지정해주세요.']);
                exit;
            }
            
            // 현재 그누보드 차단 목록 가져오기
            $current_blocks = isset($config['cf_intercept_ip']) ? $config['cf_intercept_ip'] : '';
            $blocked_ips = array_filter(array_map('trim', explode("\n", $current_blocks)));
            
            // IP 제거
            $blocked_ips = array_filter($blocked_ips, function($ip) use ($remove_ip) {
                return $ip !== $remove_ip;
            });
            
            $new_block_list = implode("\n", $blocked_ips);
            
            // 그누보드 설정 업데이트
            $sql = "UPDATE {$g5['config_table']} SET cf_intercept_ip = '" . sql_escape_string($new_block_list) . "'";
            if (sql_query($sql)) {
                echo json_encode(['success' => true, 'message' => 'IP 차단이 해제되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '차단 해제에 실패했습니다.']);
            }
            break;
            
        case 'clear_all':
            // 모든 차단 해제
            $sql = "UPDATE {$g5['config_table']} SET cf_intercept_ip = ''";
            if (sql_query($sql)) {
                echo json_encode(['success' => true, 'message' => '모든 IP 차단이 해제되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '전체 차단 해제에 실패했습니다.']);
            }
            break;
            
        case 'update_blocks':
            $new_blocks = trim($_POST['blocks'] ?? '');
            
            // 관리자 IP 보호 체크
            $admin_ip = $_SERVER['REMOTE_ADDR'];
            $new_block_list = array_filter(array_map('trim', explode("\n", $new_blocks)));
            
            foreach ($new_block_list as $ip) {
                if ($ip === $admin_ip || strpos($ip, '*') !== false) {
                    $pattern = str_replace('*', '([0-9\.]*)', $ip);
                    if (preg_match('/' . $pattern . '/', $admin_ip)) {
                        echo json_encode(['success' => false, 'message' => '관리자 IP가 포함된 차단 규칙이 있습니다.']);
                        exit;
                    }
                }
            }
            
            // 그누보드 설정 업데이트
            $sql = "UPDATE {$g5['config_table']} SET cf_intercept_ip = '" . sql_escape_string($new_blocks) . "'";
            if (sql_query($sql)) {
                echo json_encode(['success' => true, 'message' => 'IP 차단 목록이 업데이트되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '업데이트에 실패했습니다.']);
            }
            break;
    }
    exit;
}
?>