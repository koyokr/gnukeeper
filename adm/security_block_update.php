<?php
$sub_menu = '950300';
require_once './_common.php';
auth_check_menu($auth, $sub_menu, 'w');

// 보안 관리 함수들



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
$current_admin_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// 액션 처리
$action = $_POST['action'] ?? '';
if ($action && isset($is_admin) && $is_admin == 'super') {
    switch ($action) {

        case 'create_tables':
            $result = create_tables();
            alert($result ? '테이블이 성공적으로 생성되었습니다.' : '테이블 생성에 실패했습니다.');
            break;

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
            gk_set_config('ip_block_enabled', $ip_block_enabled);

            // gnuboard5 기본 IP 차단 기능 반대로 설정
            global $g5;
            sql_query("UPDATE {$g5['config_table']} SET cf_intercept_ip = '" . ($ip_block_enabled == '1' ? '0' : '1') . "'");

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('설정이 저장되었습니다. (gnuboard5 기본 IP 차단 기능도 자동 조정됨)');
            break;

        case 'toggle_foreign':
            $ip_foreign_block_enabled = $_POST['ip_foreign_block_enabled'] ?? '0';
            gk_set_config('ip_foreign_block_enabled', $ip_foreign_block_enabled);

            if (isset($_POST['ajax'])) {
                echo 'success';
                exit;
            }
            alert('해외 IP 차단 기능이 ' . ($ip_foreign_block_enabled == '1' ? '활성화' : '비활성화') . '되었습니다.');
            break;
            
        case 'save_foreign_level':
            $foreign_block_level = isset($_POST['foreign_block_level']) && is_array($_POST['foreign_block_level']) 
                                   ? implode(',', $_POST['foreign_block_level']) 
                                   : 'access';
            gk_set_config('ip_foreign_block_level', $foreign_block_level);
            
            alert('해외 IP 차단 수준이 저장되었습니다.');
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

            // 현재 접속자 IP와 같은 경우 확인 메시지 (경고가 아닌 정보성)
            if ($ip == $current_admin_ip) {
                // 관리자 IP를 예외 IP로 추가하는 것은 매우 합리적인 보안 조치임
                // 경고 메시지 제거 - 이는 정상적이고 권장되는 행위
            }

            // CIDR 검사
            if (strpos($ip, '/') !== false) {
                $result = gk_parse_cidr($ip);
                if (!$result) {
                    alert('올바른 CIDR 형식이 아닙니다.');
                    break;
                }
                list($network, $prefix_length) = explode('/', $ip);
                $prefix_length = (int)$prefix_length;
                if ($prefix_length <= 16) {
                    $ip_count = pow(2, 32 - $prefix_length);
                    alert('경고: 매우 광범위한 IP 대역입니다. 이 CIDR은 ' . number_format($ip_count) . '개의 IP를 포함합니다.');
                    break;
                }
            } else {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    alert('올바른 IP 주소가 아닙니다.');
                    break;
                }
            }

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
            $duration = isset($_POST['duration']) ? $_POST['duration'] : 'permanent';
            $end_datetime = isset($_POST['end_datetime']) ? $_POST['end_datetime'] : null;
            $block_level = isset($_POST['block_level']) && is_array($_POST['block_level']) ? implode(',', $_POST['block_level']) : 'access';

            if (!$ip || !$reason) {
                alert('IP 주소와 차단 사유를 입력해주세요.');
                break;
            }

            // IP 주소 정규화 (/32 CIDR을 단일 IP로 변환)
            $ip = normalize_ip($ip);

            // 현재 관리자 IP 확인
            if (strpos($ip, '/') !== false) {
                $result = gk_parse_cidr($ip);
                if ($result) {
                    $admin_ip_long = sprintf('%u', ip2long($current_admin_ip));
                    if ($admin_ip_long >= $result['start'] && $admin_ip_long <= $result['end']) {
                        alert('경고: 현재 접속 중인 관리자 IP(' . $current_admin_ip . ')가 차단 대상에 포함됩니다.');
                        break;
                    }
                }
            } else if ($ip == $current_admin_ip) {
                alert('경고: 현재 접속 중인 관리자 IP(' . $current_admin_ip . ')를 차단할 수 없습니다.');
                break;
            }

            // 예외 IP 확인
            $result = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_ip = '" . sql_escape_string($ip) . "'");
            if ($result['cnt'] > 0) {
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
                $result = gk_parse_cidr($ip);
                if (!$result) {
                    alert('올바른 CIDR 형식이 아닙니다.');
                    break;
                }
                $start_ip = $result['start'];
                $end_ip = $result['end'];
            } else {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    alert('올바른 IP 주소가 아닙니다.');
                    break;
                }
                $start_ip = $end_ip = sprintf('%u', ip2long($ip));
            }

            $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_block SET
                    sb_ip = '" . sql_escape_string($ip) . "',
                    sb_start_ip = " . (int)$start_ip . ",
                    sb_end_ip = " . (int)$end_ip . ",
                    sb_reason = '" . sql_escape_string($reason) . "',
                    sb_block_type = 'manual',
                    sb_block_level = '" . sql_escape_string($block_level) . "',
                    sb_duration = '" . sql_escape_string($duration) . "',
                    sb_end_datetime = " . ($duration == 'temporary' && $end_datetime ? "'" . sql_escape_string($end_datetime) . "'" : 'NULL') . ",
                    sb_datetime = NOW()";

            // 디버깅을 위한 로그 출력
            error_log("SQL Query: " . $sql);
            error_log("Variables - IP: $ip, Reason: $reason, Block Level: $block_level, Duration: $duration");
            
            $result = sql_query($sql);
            if ($result) {
                alert('IP 차단이 추가되었습니다.');
            } else {
                error_log("SQL Query failed: " . $sql);
                alert('IP 차단 추가에 실패했습니다. 관리자에게 문의하세요.');
            }
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