<?php
$sub_menu = '950300';
require_once './_common.php';
auth_check_menu($auth, $sub_menu, 'w');

// 보안 관리 함수들


function delete_block($sb_id) {
    $sql = "DELETE FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_id = " . (int)$sb_id;
    return sql_query($sql);
}

function bulk_delete_blocks($ids) {
    if (empty($ids)) return false;
    
    $ids_str = implode(',', array_map('intval', $ids));
    $sql = "DELETE FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_id IN ($ids_str)";
    return sql_query($sql);
}

function delete_whitelist($sw_id) {
    $sql = "DELETE FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_id = " . (int)$sw_id;
    return sql_query($sql);
}

function drop_tables() {
    $tables = [
        G5_TABLE_PREFIX . 'security_ip_block',
        G5_TABLE_PREFIX . 'security_ip_whitelist',
        G5_TABLE_PREFIX . 'security_ip_log',
        G5_TABLE_PREFIX . 'security_config'
    ];
    
    foreach ($tables as $table) {
        if (!sql_query("DROP TABLE IF EXISTS `$table`")) {
            return false;
        }
    }
    return true;
}


// 삭제 액션 처리
$action = $_POST['action'] ?? '';
if ($action && isset($is_admin) && $is_admin == 'super') {
    switch ($action) {
        case 'delete_block':

            $sb_id = (int)$_POST['sb_id'];
            $result = delete_block($sb_id);
            
            if (isset($_POST['ajax'])) {
                echo $result ? 'success:IP 차단이 삭제되었습니다.' : 'error:IP 차단 삭제에 실패했습니다.';
                exit;
            }
            alert($result ? 'IP 차단이 삭제되었습니다.' : 'IP 차단 삭제에 실패했습니다.');
            break;

        case 'bulk_delete':

            try {
                if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
                    $ids = array_filter(array_map('intval', $_POST['selected_ids']));
                    if (!empty($ids)) {
                        $result = bulk_delete_blocks($ids);
                        
                        if (isset($_POST['ajax'])) {
                            if ($result) {
                                echo 'success:' . count($ids) . '개의 IP 차단이 삭제되었습니다.';
                            } else {
                                echo 'error:데이터베이스 오류가 발생했습니다.';
                            }
                            exit;
                        }
                        alert($result ? count($ids) . '개의 IP 차단이 삭제되었습니다.' : '삭제 중 오류가 발생했습니다.');
                    } else {
                        if (isset($_POST['ajax'])) {
                            echo 'error:삭제할 항목을 선택해주세요.';
                            exit;
                        }
                        alert('삭제할 항목을 선택해주세요.');
                    }
                } else {
                    if (isset($_POST['ajax'])) {
                        echo 'error:삭제할 항목을 선택해주세요.';
                        exit;
                    }
                    alert('삭제할 항목을 선택해주세요.');
                }
            } catch (Exception $e) {
                if (isset($_POST['ajax'])) {
                    echo 'error:서버 오류: ' . $e->getMessage();
                    exit;
                }
                alert('서버 오류가 발생했습니다.');
            }
            break;

        case 'delete_whitelist':

            $sw_id = (int)$_POST['sw_id'];
            $result = delete_whitelist($sw_id);
            
            if (isset($_POST['ajax'])) {
                echo $result ? 'success:예외 IP에서 삭제되었습니다.' : 'error:예외 IP 삭제에 실패했습니다.';
                exit;
            }
            alert($result ? '예외 IP에서 삭제되었습니다.' : '예외 IP 삭제에 실패했습니다.');
            break;

        case 'uninstall_tables':
            $result = drop_tables();
            
            if (isset($_POST['ajax'])) {
                echo $result ? 'success:테이블이 성공적으로 삭제되었습니다.' : 'error:테이블 삭제에 실패했습니다.';
                exit;
            }
            alert($result ? '테이블이 성공적으로 삭제되었습니다.' : '테이블 삭제에 실패했습니다.');
            break;

        default:
            if (isset($_POST['ajax'])) {
                echo 'error:알 수 없는 액션입니다.';
                exit;
            }
            alert('알 수 없는 액션입니다.');
            break;
    }

    // AJAX 요청이 아닐 때만 리다이렉트
    if (!isset($_POST['ajax'])) {
        goto_url('./security_block.php');
    }
} else {
    // 권한 없음
    if (isset($_POST['ajax'])) {
        echo 'error:권한이 없습니다.';
        exit;
    }
    alert('권한이 없습니다.');
    goto_url('./security_block.php');
}
?>