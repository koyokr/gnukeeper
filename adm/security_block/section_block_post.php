<?php
require_once './_common.php';
if (!defined('_GNUBOARD_')) exit;

// AJAX 응답을 위한 헤더 설정
header('Content-Type: text/plain; charset=utf-8');

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($is_admin !== 'super') {
        echo '권한이 없습니다.';
        exit;
    }

    switch ($_POST['action']) {
        case 'add_block':
            $block_ip = trim($_POST['block_ip'] ?? '');
            $block_reason = trim($_POST['block_reason'] ?? '');
            $block_duration = $_POST['block_duration'] ?? 'permanent';
            $block_levels = isset($_POST['block_level']) && is_array($_POST['block_level'])
                          ? implode(',', $_POST['block_level'])
                          : 'access';

            if (empty($block_ip)) {
                echo 'IP 또는 CIDR을 입력해주세요.';
                exit;
            }

            // CIDR 파싱
            $cidr_result = gk_parse_cidr_for_block($block_ip);
            if ($cidr_result === false) {
                echo '올바른 IP 또는 CIDR 형식이 아닙니다.';
                exit;
            }

            list($start_ip, $end_ip) = $cidr_result;

            // 관리자 IP 보호
            $admin_ip_long = sprintf('%u', ip2long($current_admin_ip));
            if ($admin_ip_long >= $start_ip && $admin_ip_long <= $end_ip) {
                echo '관리자 IP는 차단할 수 없습니다.';
                exit;
            }

            $end_datetime = null;
            if ($block_duration === 'temporary') {
                $duration_hours = (int)($_POST['duration_hours'] ?? 24);
                $end_datetime = date('Y-m-d H:i:s', time() + ($duration_hours * 3600));
            }

            $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_block SET
                        sb_ip = '" . sql_escape_string($block_ip) . "',
                        sb_start_ip = {$start_ip},
                        sb_end_ip = {$end_ip},
                        sb_reason = '" . sql_escape_string($block_reason) . "',
                        sb_block_type = 'manual',
                        sb_block_level = '" . sql_escape_string($block_levels) . "',
                        sb_duration = '" . sql_escape_string($block_duration) . "',
                        sb_end_datetime = " . ($end_datetime ? "'{$end_datetime}'" : "NULL") . ",
                        sb_hit_count = 0,
                        sb_status = 'active',
                        sb_datetime = NOW()";

            if (sql_query($sql)) {
                echo 'IP 차단이 추가되었습니다.';
            } else {
                echo '추가 중 오류가 발생했습니다.';
            }
            break;

        case 'delete_block':
            $block_id = (int)($_POST['block_id'] ?? 0);

            if ($block_id > 0) {
                $sql = "DELETE FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_id = " . $block_id;
                if (sql_query($sql)) {
                    echo 'IP 차단이 삭제되었습니다.';
                } else {
                    echo '삭제 중 오류가 발생했습니다.';
                }
            }
            break;

        case 'toggle_block':
            $block_id = (int)($_POST['block_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? 'active';

            if ($block_id > 0 && in_array($new_status, ['active', 'inactive'])) {
                $sql = "UPDATE " . G5_TABLE_PREFIX . "security_ip_block
                        SET sb_status = '" . sql_escape_string($new_status) . "'
                        WHERE sb_id = " . $block_id;

                if (sql_query($sql)) {
                    $status_text = ($new_status === 'active') ? '활성화' : '비활성화';
                    echo "IP 차단이 {$status_text}되었습니다.";
                } else {
                    echo '상태 변경 중 오류가 발생했습니다.';
                }
            }
            break;

        case 'toggle_ip_block_feature':
            $enabled = $_POST['enabled'] ?? '0';
            if (in_array($enabled, ['0', '1'])) {
                gk_set_config('ip_block_enabled', $enabled);
                $status_text = $enabled === '1' ? '활성화' : '비활성화';
                echo "IP 차단 기능이 {$status_text}되었습니다.";
            } else {
                echo '잘못된 요청입니다.';
            }
            break;

        case 'bulk_action':
            $action = $_POST['bulk_action'] ?? '';
            $block_ids = $_POST['block_ids'] ?? [];

            if (!empty($block_ids) && is_array($block_ids) && in_array($action, ['delete', 'activate', 'deactivate'])) {
                $ids = array_map('intval', $block_ids);
                $ids_str = implode(',', $ids);

                switch ($action) {
                    case 'delete':
                        $sql = "DELETE FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_id IN ({$ids_str})";
                        $success_msg = '선택된 IP 차단이 삭제되었습니다.';
                        break;
                    case 'activate':
                        $sql = "UPDATE " . G5_TABLE_PREFIX . "security_ip_block SET sb_status = 'active' WHERE sb_id IN ({$ids_str})";
                        $success_msg = '선택된 IP 차단이 활성화되었습니다.';
                        break;
                    case 'deactivate':
                        $sql = "UPDATE " . G5_TABLE_PREFIX . "security_ip_block SET sb_status = 'inactive' WHERE sb_id IN ({$ids_str})";
                        $success_msg = '선택된 IP 차단이 비활성화되었습니다.';
                        break;
                }

                if (sql_query($sql)) {
                    echo $success_msg;
                } else {
                    echo '일괄 처리 중 오류가 발생했습니다.';
                }
            } else {
                echo '처리할 항목을 선택하고 작업을 선택해주세요.';
            }
            break;
    }
    exit;
}
?>