<?php
$sub_menu = '950400';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// AJAX 요청 처리 (HTML 출력 전에 처리)
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    $bo_table = isset($_POST['bo_table']) ? $_POST['bo_table'] : '';
    
    switch ($action) {
        case 'fix_member_level':
            // 모든 권한을 회원 레벨로 설정
            if ($bo_table) {
                $level = isset($_POST['level']) ? intval($_POST['level']) : 2;
                $update_sql = "UPDATE {$g5['board_table']} SET 
                              bo_list_level = $level,
                              bo_read_level = $level, 
                              bo_write_level = $level, 
                              bo_reply_level = $level, 
                              bo_comment_level = $level, 
                              bo_link_level = $level, 
                              bo_upload_level = $level, 
                              bo_download_level = $level, 
                              bo_html_level = $level 
                              WHERE bo_table = '$bo_table'";
                sql_query($update_sql);
                
                // 예외 목록에서 제거
                $config_sql = "SELECT cf_1 FROM {$g5['config_table']}";
                $config_result = sql_fetch($config_sql);
                $exceptions = isset($config_result['cf_1']) ? $config_result['cf_1'] : '';
                $exception_list = explode('|', $exceptions);
                $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                    return trim($item) !== $bo_table;
                });
                $new_exceptions = implode('|', $exception_list);
                
                $update_config_sql = "UPDATE {$g5['config_table']} SET cf_1 = '$new_exceptions'";
                sql_query($update_config_sql);
                
                echo json_encode(['success' => true, 'message' => '모든 권한이 회원 레벨(' . $level . ')로 수정되었습니다.']);
            }
            break;
            
        case 'except_read_admin':
            // 읽기 외 권한을 관리자로 설정하고 읽기 권한은 비회원(1)로 설정
            if ($bo_table) {
                $update_sql = "UPDATE {$g5['board_table']} SET 
                              bo_list_level = 1,
                              bo_read_level = 1, 
                              bo_write_level = 10, 
                              bo_reply_level = 10, 
                              bo_comment_level = 10, 
                              bo_link_level = 10, 
                              bo_upload_level = 10, 
                              bo_download_level = 10, 
                              bo_html_level = 10 
                              WHERE bo_table = '$bo_table'";
                sql_query($update_sql);
                
                // 예외 목록에서 제거
                $config_sql = "SELECT cf_1 FROM {$g5['config_table']}";
                $config_result = sql_fetch($config_sql);
                $exceptions = isset($config_result['cf_1']) ? $config_result['cf_1'] : '';
                $exception_list = explode('|', $exceptions);
                $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                    return trim($item) !== $bo_table;
                });
                $new_exceptions = implode('|', $exception_list);
                
                $update_config_sql = "UPDATE {$g5['config_table']} SET cf_1 = '$new_exceptions'";
                sql_query($update_config_sql);
                
                echo json_encode(['success' => true, 'message' => '비회원 읽기 및 비회원 읽기 외 권한 관리자 권한으로 수정되었습니다.']);
            }
            break;
            
        case 'except_write_admin':
            // 쓰기는 비회원(1)으로, 나머지 권한은 관리자(10)로 설정
            if ($bo_table) {
                $update_sql = "UPDATE {$g5['board_table']} SET 
                              bo_list_level = 10,
                              bo_read_level = 10, 
                              bo_write_level = 1, 
                              bo_reply_level = 10, 
                              bo_comment_level = 10, 
                              bo_link_level = 10, 
                              bo_upload_level = 10, 
                              bo_download_level = 10, 
                              bo_html_level = 10 
                              WHERE bo_table = '$bo_table'";
                sql_query($update_sql);
                
                // 예외 목록에서 제거
                $config_sql = "SELECT cf_1 FROM {$g5['config_table']}";
                $config_result = sql_fetch($config_sql);
                $exceptions = isset($config_result['cf_1']) ? $config_result['cf_1'] : '';
                $exception_list = explode('|', $exceptions);
                $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                    return trim($item) !== $bo_table;
                });
                $new_exceptions = implode('|', $exception_list);
                
                $update_config_sql = "UPDATE {$g5['config_table']} SET cf_1 = '$new_exceptions'";
                sql_query($update_config_sql);
                
                echo json_encode(['success' => true, 'message' => '비회원 쓰기 권한으로 설정되었습니다. (쓰기: 비회원, 나머지: 관리자)']);
            }
            break;
            
        case 'except_board':
            if ($bo_table) {
                try {
                    // 예외 목록 토글 (추가/제거)
                    $config_sql = "SELECT cf_1 FROM {$g5['config_table']}";
                    $config_result = sql_fetch($config_sql);
                    $exceptions = isset($config_result['cf_1']) ? $config_result['cf_1'] : '';
                    
                    $exception_list = array_filter(explode('|', $exceptions));
                    
                    if (in_array($bo_table, $exception_list)) {
                        // 예외 목록에서 제거
                        $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                            return trim($item) !== $bo_table;
                        });
                        $message = '예외 처리가 해제되었습니다.';
                    } else {
                        // 예외 목록에 추가
                        $exception_list[] = $bo_table;
                        $message = '게시판이 예외 처리되었습니다.';
                    }
                    
                    $new_exceptions = implode('|', $exception_list);
                    $update_sql = "UPDATE {$g5['config_table']} SET cf_1 = '$new_exceptions'";
                    $result = sql_query($update_sql);
                    
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => $message]);
                    } else {
                        echo json_encode(['success' => false, 'message' => '데이터베이스 업데이트 실패']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '게시판 정보가 없습니다.']);
            }
            break;
            
        case 'remove_exception':
            if ($bo_table) {
                // 예외 목록에서 제거
                $config_sql = "SELECT cf_1 FROM {$g5['config_table']}";
                $config_result = sql_fetch($config_sql);
                $exceptions = isset($config_result['cf_1']) ? $config_result['cf_1'] : '';
                
                $exception_list = explode('|', $exceptions);
                $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                    return trim($item) !== $bo_table;
                });
                $new_exceptions = implode('|', $exception_list);
                
                $update_sql = "UPDATE {$g5['config_table']} SET cf_1 = '$new_exceptions'";
                sql_query($update_sql);
                
                echo json_encode(['success' => true, 'message' => '예외 처리가 해제되었습니다.']);
            }
            break;
            
        case 'toggle_captcha_exception':
            if ($bo_table) {
                // 캡챠 예외 목록 토글
                $config_sql = "SELECT cf_3 FROM {$g5['config_table']}";
                $config_result = sql_fetch($config_sql);
                $captcha_exceptions = isset($config_result['cf_3']) ? $config_result['cf_3'] : '';
                
                $exception_list = array_filter(explode('|', $captcha_exceptions));
                
                if (in_array($bo_table, $exception_list)) {
                    // 제거
                    $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                        return trim($item) !== $bo_table;
                    });
                    $message = '캡챠 예외 처리가 해제되었습니다.';
                } else {
                    // 추가
                    $exception_list[] = $bo_table;
                    $message = '캡챠 예외 처리되었습니다.';
                }
                
                $new_exceptions = implode('|', $exception_list);
                $update_sql = "UPDATE {$g5['config_table']} SET cf_3 = '$new_exceptions'";
                sql_query($update_sql);
                
                echo json_encode(['success' => true, 'message' => $message]);
            }
            break;
            
        case 'enable_captcha':
            if ($bo_table) {
                $update_sql = "UPDATE {$g5['board_table']} SET bo_use_captcha = 1 WHERE bo_table = '$bo_table'";
                sql_query($update_sql);
                echo json_encode(['success' => true, 'message' => '캡챠가 활성화되었습니다.']);
            }
            break;
            
        case 'reset_user_permissions':
            $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : array();
            if (!empty($user_ids) && is_array($user_ids)) {
                $success_count = 0;
                $config_sql = "SELECT cf_register_level FROM {$g5['config_table']}";
                $config_result = sql_fetch($config_sql);
                $default_level = isset($config_result['cf_register_level']) ? $config_result['cf_register_level'] : 2;
                
                foreach ($user_ids as $user_id) {
                    // admin 계정은 건드리지 않음
                    if ($user_id !== 'admin') {
                        $update_sql = "UPDATE {$g5['member_table']} SET mb_level = $default_level WHERE mb_id = '" . sql_real_escape_string($user_id) . "'";
                        if (sql_query($update_sql)) {
                            $success_count++;
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'message' => $success_count . '명의 사용자 권한이 레벨 ' . $default_level . '로 초기화되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '선택된 사용자가 없습니다.']);
            }
            break;
            
        case 'limit_upload_10mb':
            if ($bo_table) {
                $limit_size = 10 * 1024 * 1024; // 10MB
                $update_sql = "UPDATE {$g5['board_table']} SET bo_upload_size = $limit_size WHERE bo_table = '$bo_table'";
                sql_query($update_sql);
                echo json_encode(['success' => true, 'message' => '업로드 크기가 10MB로 제한되었습니다.']);
            }
            break;
            
        case 'toggle_upload_exception':
            if ($bo_table) {
                // 업로드 예외 목록 토글
                $config_sql = "SELECT cf_2 FROM {$g5['config_table']}";
                $config_result = sql_fetch($config_sql);
                $upload_exceptions = isset($config_result['cf_2']) ? $config_result['cf_2'] : '';
                
                $exception_list = array_filter(explode('|', $upload_exceptions));
                
                if (in_array($bo_table, $exception_list)) {
                    // 제거
                    $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                        return trim($item) !== $bo_table;
                    });
                    $message = '업로드 예외 처리가 해제되었습니다.';
                } else {
                    // 추가
                    $exception_list[] = $bo_table;
                    $message = '업로드 예외 처리되었습니다.';
                }
                
                $new_exceptions = implode('|', $exception_list);
                $update_sql = "UPDATE {$g5['config_table']} SET cf_2 = '$new_exceptions'";
                sql_query($update_sql);
                
                echo json_encode(['success' => true, 'message' => $message]);
            }
            break;
    }
    exit;
}

$g5['title'] = '정책관리';
require_once './admin.head.php';
?>

<style>
.dashboard-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
    color: white;
    padding: 18px 25px;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-header:hover {
    background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%);
}

.section-content {
    padding: 25px;
    display: none;
}

.section-content.show {
    display: block;
}

.info-highlight {
    background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
    color: #374151;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    font-weight: 500;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border: 1px solid #d1d5db;
}

.boards-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.board-item {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
}

.board-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.board-item.safe {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-color: #bae6fd;
}

.board-item.danger {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-color: #fecaca;
}

.board-item.exception {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border-color: #fde68a;
}

.board-actions {
    display: flex;
    gap: 3px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.recommendations {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-top: 25px;
}

.recommendations h4 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 16px;
}

.recommendations ul {
    margin: 0;
    padding-left: 20px;
    color: #6c757d;
}

.recommendations li {
    margin-bottom: 8px;
    line-height: 1.5;
}

.board-item [data-tooltip] {
    position: relative;
    cursor: help;
}

.board-item [data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    margin-bottom: 5px;
}

.board-item [data-tooltip]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #333;
    z-index: 1000;
}

.extension-container {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.extension-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 15px;
}

.extension-item {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    position: relative;
    cursor: help;
}

.extension-item.high {
    background: #dc3545;
    color: white;
}

.extension-item.medium {
    background: #fd7e14;
    color: white;
}

.extension-item.low {
    background: #28a745;
    color: white;
}

.extension-item.unknown {
    background: #6f42c1;
    color: white;
}

.extension-remove {
    background: rgba(255,255,255,0.3);
    border: none;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    margin-left: 6px;
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.analysis-result {
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.analysis-result.safe {
    background: #d1f2eb;
    border: 1px solid #a7e5d4;
}

.analysis-result.warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
}

.analysis-result.danger {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

/* 툴팁 스타일 */
.btn-with-tooltip {
    position: relative;
    display: inline-block;
    margin-right: 3px;
}

.btn-with-tooltip .tooltip-message {
    visibility: hidden;
    background-color: #333;
    color: white;
    text-align: center;
    border-radius: 6px;
    padding: 8px 12px;
    position: absolute;
    z-index: 1000;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    width: 200px;
    font-size: 12px;
    line-height: 1.4;
    opacity: 0;
    transition: opacity 0.3s;
}

.btn-with-tooltip .tooltip-message::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #333 transparent transparent transparent;
}

.btn-with-tooltip:hover .tooltip-message {
    visibility: visible;
    opacity: 1;
}
</style>

<!-- 보안 설정 -->
<div style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); color: #1f2937; padding: 30px; border-radius: 12px; margin-bottom: 30px; text-align: center; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1); border: 1px solid #e5e7eb;">
    <h1 style="margin: 0; font-size: 32px; font-weight: 700; color: #374151;">🛡️ 정책 관리</h1>
    <p style="margin: 10px 0 0 0; font-size: 16px; color: #6b7280;">사이트 보안 정책을 통합 관리합니다</p>
</div>

<?php 
// 각 섹션을 원하는 순서로 include
include_once 'security_section_board.php';      // 게시판 접근 권한
include_once 'security_section_captcha.php';    // 캡챠 적용 정책  
include_once 'security_section_admin_users.php'; // 관리자급 권한
include_once 'security_section_extension.php';   // 확장자 정책
include_once 'security_section_upload.php';      // 업로드 용량
?>

<script>
function toggleSection(sectionId) {
    const content = document.getElementById(sectionId);
    const toggle = document.getElementById(sectionId.replace('-section', '-toggle'));
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        toggle.style.transform = 'rotate(0deg)';
    } else {
        content.classList.add('show');
        toggle.style.transform = 'rotate(90deg)';
    }
}

function updateBoardSecurity(action, boTable) {
    if (!confirm('이 작업을 실행하시겠습니까?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('bo_table', boTable);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('오류가 발생했습니다: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 처리 중 오류가 발생했습니다.');
    });
}

// 게시판 권한 업데이트 함수
function updateBoardPermissions(boTable, action, level) {
    let confirmMessage = '';
    let actionName = '';
    
    switch(action) {
        case 'fix_member_level':
            confirmMessage = `게시판 "${boTable}"의 모든 권한을 회원 레벨(${level})로 변경하시겠습니까?`;
            actionName = 'fix_member_level';
            break;
        case 'except_write_admin':
            confirmMessage = `게시판 "${boTable}"을 비회원 쓰기 권한으로 설정하시겠습니까?\n(쓰기: 비회원, 나머지: 관리자)`;
            actionName = 'except_write_admin';
            break;
        case 'except_read_admin':
            confirmMessage = `게시판 "${boTable}"을 비회원 읽기 권한으로 설정하시겠습니까?\n(목록/읽기: 비회원, 나머지: 관리자)`;
            actionName = 'except_read_admin';
            break;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', actionName);
    formData.append('bo_table', boTable);
    if (level) {
        formData.append('level', level);
    }
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data); // 디버깅용
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('오류가 발생했습니다: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error details:', error);
        alert('요청 처리 중 오류가 발생했습니다: ' + error.message);
    });
}

// 게시판 예외 처리 토글
function toggleBoardException(boTable) {
    if (!confirm(`게시판 "${boTable}"의 예외 처리 상태를 변경하시겠습니까?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'except_board');
    formData.append('bo_table', boTable);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data); // 디버깅용
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('오류가 발생했습니다: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error details:', error);
        alert('요청 처리 중 오류가 발생했습니다: ' + error.message);
    });
}

function toggleCaptchaException(boTable) {
    updateBoardSecurity('toggle_captcha_exception', boTable);
}

function enableCaptcha(boTable) {
    updateBoardSecurity('enable_captcha', boTable);
}

function applyCaptcha(boTable) {
    updateBoardSecurity('enable_captcha', boTable);
}

function resetSelectedUserPermissions() {
    const checkboxes = document.querySelectorAll('.admin-user-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('권한을 초기화할 사용자를 선택해주세요.');
        return;
    }
    
    if (!confirm('선택한 ' + checkboxes.length + '명의 사용자 권한을 일반 회원 권한으로 초기화하시겠습니까?')) {
        return;
    }
    
    const userIds = Array.from(checkboxes).map(cb => cb.value);
    
    const formData = new FormData();
    formData.append('action', 'reset_user_permissions');
    formData.append('user_ids', JSON.stringify(userIds));
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('오류가 발생했습니다: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 처리 중 오류가 발생했습니다.');
    });
}

function limitUpload10MB(boTable) {
    updateBoardSecurity('limit_upload_10mb', boTable);
}

function toggleUploadException(boTable) {
    updateBoardSecurity('toggle_upload_exception', boTable);
}

// 전체 선택 기능
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-admin-users');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.admin-user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
});
</script>

<?php
require_once './admin.tail.php';
?>