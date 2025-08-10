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

<link rel="stylesheet" href="./css/security_common.css">

<div class="security-dashboard">
    <h1 class="dashboard-title">
        🛡️ 정책 관리
    </h1>
    <p class="dashboard-subtitle">
        사이트 보안 정책을 통합 관리합니다
    </p>

    <?php
    // 각 카드를 원하는 순서로 include
    include_once 'security_card_board.php';      // 게시판 접근 권한
    include_once 'security_card_captcha.php';    // 캡챠 적용 정책
    include_once 'security_card_admin_users.php'; // 관리자급 권한
    include_once 'security_card_extension.php';   // 확장자 정책
    include_once 'security_card_upload.php';      // 업로드 용량
    ?>
</div>

<script>
function toggleCard(cardId) {
    const content = document.getElementById(cardId);
    const toggle = document.getElementById(cardId.replace('-card', '-toggle'));

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

// 전체 선택 기능 및 자동 섹션 펼치기
document.addEventListener('DOMContentLoaded', function() {
    // 전체 선택 기능
    const selectAllCheckbox = document.getElementById('select-all-admin-users');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.admin-user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // 접근제어와 동일한 자동 카드 펼치기 기능
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';

            // 각 카드를 순차적으로 펼치기
            setTimeout(() => {
                const cardContent = card.querySelector('.card-content');
                const toggle = card.querySelector('[id$="-toggle"]');
                if (cardContent && toggle) {
                    cardContent.classList.add('show');
                    toggle.textContent = '▼';
                    toggle.style.transform = 'rotate(90deg)';
                }
            }, 500);
        }, index * 100);
    });
});
</script>

<?php
require_once './admin.tail.php';
?>