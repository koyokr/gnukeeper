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
        case 'add_whitelist':
            $whitelist_ip = trim($_POST['whitelist_ip'] ?? '');
            $whitelist_memo = trim($_POST['whitelist_memo'] ?? '');

            if (empty($whitelist_ip)) {
                echo 'IP를 입력해주세요.';
                exit;
            }

            if (!filter_var($whitelist_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                echo '올바른 IP 형식이 아닙니다.';
                exit;
            }

            // 중복 확인
            $check_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_ip = '" . sql_escape_string($whitelist_ip) . "'";
            $check_result = sql_query($check_sql, false);
            if ($check_result && $row = sql_fetch_array($check_result)) {
                if ($row['cnt'] > 0) {
                    echo '이미 등록된 IP입니다.';
                    exit;
                }
            }

            $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_whitelist SET
                        sw_ip = '" . sql_escape_string($whitelist_ip) . "',
                        sw_memo = '" . sql_escape_string($whitelist_memo) . "',
                        sw_datetime = NOW()";

            if (sql_query($sql)) {
                echo '예외 IP가 추가되었습니다.';
            } else {
                echo '추가 중 오류가 발생했습니다.';
            }
            break;

        case 'delete_whitelist':
            $whitelist_id = (int)($_POST['whitelist_id'] ?? 0);

            if ($whitelist_id > 0) {
                $sql = "DELETE FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_id = " . $whitelist_id;
                if (sql_query($sql)) {
                    echo '예외 IP가 삭제되었습니다.';
                } else {
                    echo '삭제 중 오류가 발생했습니다.';
                }
            }
            break;
    }
    exit;
}
?>