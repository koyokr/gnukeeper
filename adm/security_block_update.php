<?php
$sub_menu = '950300';
require_once './_common.php';
auth_check_menu($auth, $sub_menu, 'w');

// 보안 관리 함수들


function get_current_admin_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? '';
}

function create_tables() {
    $sql_file = __DIR__ . '/security_block.sql';
    if (!file_exists($sql_file)) {
        return false;
    }
    
    $sql_content = file_get_contents($sql_file);
    if ($sql_content === false) {
        return false;
    }
    
    // {PREFIX}를 실제 테이블 접두사로 치환
    $sql_content = str_replace('{PREFIX}', G5_TABLE_PREFIX, $sql_content);
    
    // SQL 문장을 분리하여 실행
    $statements = explode(';', $sql_content);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        if (!sql_query($statement)) {
            return false;
        }
    }
    
    return true;
}

function set_config($key, $value) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_config (sc_key, sc_value) VALUES ('" . sql_escape_string($key) . "', '" . sql_escape_string($value) . "')
            ON DUPLICATE KEY UPDATE sc_value = '" . sql_escape_string($value) . "'";
    return sql_query($sql);
}

function normalize_ip($ip) {
    // /32 CIDR을 단일 IP로 변환
    if (preg_match('/^([0-9.]+)\/32$/', $ip, $matches)) {
        return $matches[1];
    }
    return $ip;
}

function add_whitelist_ip($ip, $memo) {
    $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_whitelist SET
            sw_ip = '" . sql_escape_string($ip) . "',
            sw_memo = '" . sql_escape_string($memo) . "',
            sw_datetime = NOW()";
    return sql_query($sql);
}

function is_valid_cidr($cidr) {
    if (!preg_match('/^([0-9.]+)\/([0-9]+)$/', $cidr, $matches)) {
        return false;
    }
    
    $ip = $matches[1];
    $prefix_length = (int)$matches[2];
    
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $prefix_length >= 0 && $prefix_length <= 32;
}

function parse_cidr($cidr) {
    list($ip, $prefix_length) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $mask = ~((1 << (32 - $prefix_length)) - 1);
    $start_ip = $ip_long & $mask;
    $end_ip = $start_ip | ~$mask;
    return [$start_ip, $end_ip];
}

function ip_to_long($ip) {
    return sprintf('%u', ip2long($ip));
}

function is_whitelisted_ip($ip) {
    $result = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_ip = '" . sql_escape_string($ip) . "'");
    return $result['cnt'] > 0;
}

function is_valid_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

function bulk_toggle_status($ids, $new_status) {
    if (empty($ids)) return false;
    
    $ids_str = implode(',', array_map('intval', $ids));
    $sql = "UPDATE " . G5_TABLE_PREFIX . "security_ip_block SET sb_status = '" . sql_escape_string($new_status) . "' WHERE sb_id IN ($ids_str)";
    return sql_query($sql);
}

function toggle_block_status($sb_id, $new_status) {
    $sql = "UPDATE " . G5_TABLE_PREFIX . "security_ip_block SET sb_status = '" . sql_escape_string($new_status) . "' WHERE sb_id = " . (int)$sb_id;
    return sql_query($sql);
}

// 초기화
$current_admin_ip = get_current_admin_ip();

// 액션 처리
$action = $_POST['action'] ?? '';
if ($action && isset($is_admin) && $is_admin == 'super') {
    switch ($action) {

        case 'uninstall_tables':
            // 삭제 전용 파일로 리다이렉트
            if (isset($_POST['ajax'])) {
                echo 'error:삭제 작업은 전용 파일에서 처리해주세요.';
                exit;
            }
            header('Location: ./security_block_delete.php');
            exit;
            break;

        case 'save_config':

            $ip_block_enabled = $_POST['ip_block_enabled'] ?? '0';
            set_config('ip_block_enabled', $ip_block_enabled);

            // gnuboard5 기본 IP 차단 기능 반대로 설정
            global $g5;
            sql_query("UPDATE {$g5['config_table']} SET cf_intercept_ip = '" . ($ip_block_enabled == '1' ? '0' : '1') . "'");

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('설정이 저장되었습니다. (gnuboard5 기본 IP 차단 기능도 자동 조정됨)');
            break;

        case 'add_whitelist':

            $ip = trim($_POST['whitelist_ip']);
            $memo = trim($_POST['whitelist_memo']);

            if (!$ip) {
                alert('IP 주소를 입력해주세요.');
                break;
            }

            // IP 주소 정규화 (/32 CIDR을 단일 IP로 변환)
            $ip = normalize_ip($ip);

            alert(add_whitelist_ip($ip, $memo) ? '예외 IP에 추가되었습니다.' : '예외 IP 추가에 실패했습니다.');
            break;

        case 'delete_whitelist':
            // 삭제 전용 파일로 리다이렉트
            if (isset($_POST['ajax'])) {
                echo 'error:삭제 작업은 전용 파일에서 처리해주세요.';
                exit;
            }
            header('Location: ./security_block_delete.php');
            exit;
            break;

        case 'add_block':

            $ip = trim($_POST['ip']);
            $reason = trim($_POST['reason']);
            $duration = $_POST['duration'];
            $end_datetime = $_POST['end_datetime'];

            if (!$ip || !$reason) {
                alert('IP 주소와 차단 사유를 입력해주세요.');
                break;
            }

            // IP 주소 정규화 (/32 CIDR을 단일 IP로 변환)
            $ip = normalize_ip($ip);

            // 현재 관리자 IP 확인
            if (strpos($ip, '/') !== false) {
                if (is_valid_cidr($ip)) {
                    list($start_ip, $end_ip) = parse_cidr($ip);
                    $admin_ip_long = ip_to_long($current_admin_ip);
                    if ($admin_ip_long >= $start_ip && $admin_ip_long <= $end_ip) {
                        alert('경고: 현재 접속 중인 관리자 IP(' . $current_admin_ip . ')가 차단 대상에 포함됩니다.');
                        break;
                    }
                }
            } else if ($ip == $current_admin_ip) {
                alert('경고: 현재 접속 중인 관리자 IP(' . $current_admin_ip . ')를 차단할 수 없습니다.');
                break;
            }

            // 예외 IP 확인
            if (is_whitelisted_ip($ip)) {
                alert('이 IP는 예외 IP에 등록되어 있어 차단할 수 없습니다.');
                break;
            }

            // 중복 차단 확인
            $existing_check = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block
                                       WHERE sb_ip = '" . sql_escape_string($ip) . "' AND sb_status = 'active'");
            if ($existing_check['cnt'] > 0) {
                alert('이미 동일한 IP/CIDR이 차단되어 있습니다.');
                break;
            }

            if (strpos($ip, '/') !== false) {
                if (!is_valid_cidr($ip)) {
                    alert('올바른 CIDR 형식이 아닙니다.');
                    break;
                }
                list($start_ip, $end_ip) = parse_cidr($ip);
            } else {
                if (!is_valid_ip($ip)) {
                    alert('올바른 IP 주소가 아닙니다.');
                    break;
                }
                $start_ip = $end_ip = ip_to_long($ip);
            }

            $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_block SET
                    sb_ip = '" . sql_escape_string($ip) . "',
                    sb_start_ip = " . (int)$start_ip . ",
                    sb_end_ip = " . (int)$end_ip . ",
                    sb_reason = '" . sql_escape_string($reason) . "',
                    sb_block_type = 'manual',
                    sb_duration = '" . sql_escape_string($duration) . "',
                    sb_end_datetime = " . ($duration == 'temporary' && $end_datetime ? "'" . sql_escape_string($end_datetime) . "'" : 'NULL') . ",
                    sb_datetime = NOW()";

            alert(sql_query($sql) ? 'IP 차단이 추가되었습니다.' : 'IP 차단 추가에 실패했습니다.');
            break;

        case 'delete_block':
            // 삭제 전용 파일로 리다이렉트
            if (isset($_POST['ajax'])) {
                echo 'error:삭제 작업은 전용 파일에서 처리해주세요.';
                exit;
            }
            header('Location: ./security_block_delete.php');
            exit;
            break;

        case 'bulk_delete':
            // 삭제 전용 파일로 리다이렉트
            if (isset($_POST['ajax'])) {
                echo 'error:삭제 작업은 전용 파일에서 처리해주세요.';
                exit;
            }
            header('Location: ./security_block_delete.php');
            exit;
            break;

        case 'bulk_toggle':

            try {
                if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
                    $ids = array_filter(array_map('intval', $_POST['selected_ids']));
                    $new_status = $_POST['bulk_status'];

                    if (!empty($ids)) {
                        $result = bulk_toggle_status($ids, $new_status);
                        
                        if (isset($_POST['ajax'])) {
                            if ($result) {
                                $status_text = $new_status == 'active' ? '활성화' : '비활성화';
                                echo 'success:' . count($ids) . '개 항목이 ' . $status_text . '되었습니다.';
                            } else {
                                echo 'error:데이터베이스 오류가 발생했습니다.';
                            }
                            exit;
                        }
                        alert(count($ids) . '개 항목의 상태가 변경되었습니다.');
                    } else {
                        if (isset($_POST['ajax'])) {
                            echo 'error:변경할 항목을 선택해주세요.';
                            exit;
                        }
                        alert('변경할 항목을 선택해주세요.');
                    }
                } else {
                    if (isset($_POST['ajax'])) {
                        echo 'error:변경할 항목을 선택해주세요.';
                        exit;
                    }
                    alert('변경할 항목을 선택해주세요.');
                }
            } catch (Exception $e) {
                if (isset($_POST['ajax'])) {
                    echo 'error:서버 오류: ' . $e->getMessage();
                    exit;
                }
                alert('서버 오류가 발생했습니다.');
            }
            break;

        case 'toggle_status':

            $sb_id = (int)$_POST['sb_id'];
            $new_status = $_POST['status'] == 'active' ? 'inactive' : 'active';
            
            if ($sb_id > 0) {
                $result = toggle_block_status($sb_id, $new_status);
                
                if (isset($_POST['ajax'])) {
                    echo $result ? 'success:상태가 변경되었습니다.' : 'error:상태 변경에 실패했습니다.';
                    exit;
                }
                alert($result ? '상태가 변경되었습니다.' : '상태 변경에 실패했습니다.');
            }
            break;
    }

    // AJAX 요청이 아닐 때만 리다이렉트
    if (!isset($_POST['ajax'])) {
        goto_url('./security_block.php');
    }
} else if ($action) {
    // 권한 없음
    if (isset($_POST['ajax'])) {
        echo 'error:권한이 없습니다.';
        exit;
    }
    alert('권한이 없습니다.');
    goto_url('./security_block.php');
}
?>