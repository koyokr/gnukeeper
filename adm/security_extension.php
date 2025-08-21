<?php
$sub_menu = '950400';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// AJAX 요청 처리 (HTML 출력 전에 처리)
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // AJAX 요청에서도 관리자 권한 재확인
    if (!isset($member) || $member['mb_level'] < 10) {
        echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다.']);
        exit;
    }

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
            
        case 'remove_extension':
            // 확장자 제거 기능
            $extension = isset($_POST['extension']) ? trim($_POST['extension']) : '';
            
            if (empty($extension)) {
                echo json_encode(['success' => false, 'message' => '제거할 확장자가 지정되지 않았습니다.']);
                break;
            }
            
            try {
                // 현재 설정된 모든 확장자 필드에서 제거
                $extension_fields = ['cf_image_extension', 'cf_flash_extension', 'cf_movie_extension'];
                $updated = false;
                
                foreach ($extension_fields as $field) {
                    $sql = "SELECT {$field} FROM {$g5['config_table']} WHERE cf_id = 1";
                    $result = sql_fetch($sql);
                    
                    if ($result && !empty($result[$field])) {
                        $extensions = explode('|', $result[$field]);
                        $original_count = count($extensions);
                        
                        // 대소문자 구분 없이 제거
                        $extensions = array_filter($extensions, function($ext) use ($extension) {
                            return strtolower(trim($ext)) !== strtolower($extension);
                        });
                        
                        if (count($extensions) < $original_count) {
                            $new_value = implode('|', $extensions);
                            $update_sql = "UPDATE {$g5['config_table']} SET {$field} = '{$new_value}' WHERE cf_id = 1";
                            sql_query($update_sql);
                            $updated = true;
                        }
                    }
                }
                
                if ($updated) {
                    echo json_encode(['success' => true, 'message' => "확장자 '{$extension}'이(가) 제거되었습니다."]);
                } else {
                    echo json_encode(['success' => false, 'message' => "확장자 '{$extension}'을(를) 찾을 수 없습니다."]);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '확장자 제거 중 오류가 발생했습니다: ' . $e->getMessage()]);
            }
            break;
    }
    exit;
}

$g5['title'] = '정책관리';
require_once './admin.head.php';
?>

<link rel="stylesheet" href="./css/security_common.css">

<!-- 강력한 회전 방지 CSS -->
<style>
/* 모든 토글 관련 요소의 회전 방지 */
.card-header, .card-header *, 
.security-card, .security-card *,
.card-toggle, .card-toggle *,
.toggle-btn, .toggle-btn * {
    transform: none !important;
    transition: none !important;
    animation: none !important;
}

/* 카드 전체 회전 방지 */
.security-card.expanded,
.security-card.collapsed,
.security-card:hover {
    transform: none !important;
    rotate: none !important;
}

/* 토글 버튼 회전 방지 */
.card-header .toggle-btn,
.card-header .card-toggle,
.card-header span[onclick] {
    transform: none !important;
    rotate: none !important;
}
</style>

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
// 스크롤 위치 및 카드 상태 저장/복원
const savePageState = () => {
    // 스크롤 위치 저장
    sessionStorage.setItem('security_policy_scroll', window.pageYOffset.toString());
    
    // 카드 펼침 상태 저장
    const cardStates = {};
    document.querySelectorAll('.card-content').forEach(content => {
        const cardId = content.id;
        cardStates[cardId] = content.classList.contains('show');
    });
    
    sessionStorage.setItem('security_policy_cards', JSON.stringify(cardStates));
};

const restorePageState = () => {
    // 스크롤 위치 복원 (지연 실행)
    const savedPosition = sessionStorage.getItem('security_policy_scroll');
    if (savedPosition) {
        // DOM이 완전히 렌더링된 후 스크롤 위치 복원
        setTimeout(() => {
            window.scrollTo(0, parseInt(savedPosition));
            sessionStorage.removeItem('security_policy_scroll');
        }, 200);
    }
    
    // 카드 상태 복원
    const savedCards = sessionStorage.getItem('security_policy_cards');
    if (savedCards) {
        try {
            const cardStates = JSON.parse(savedCards);
            
            Object.entries(cardStates).forEach(([cardId, isOpen]) => {
                const content = document.getElementById(cardId);
                
                // 다양한 카드 ID 패턴 지원
                let toggleId;
                if (cardId.endsWith('-card')) {
                    toggleId = cardId.replace('-card', '-toggle');
                } else if (cardId.endsWith('-section')) {
                    toggleId = cardId.replace('-section', '-toggle');
                } else {
                    toggleId = cardId + '-toggle';
                }
                
                const toggle = document.getElementById(toggleId);
                
                if (content && toggle) {
                    if (isOpen) {
                        content.classList.add('show');
                        toggle.textContent = '▼';
                        // transform 제거 (회전 방지)
                        toggle.style.transform = 'none';
                    } else {
                        content.classList.remove('show');
                        toggle.textContent = '▶';
                        // transform 제거 (회전 방지)
        toggle.style.transform = 'none';
                    }
                }
            });
            
            sessionStorage.removeItem('security_policy_cards');
        } catch (e) {
            console.error('카드 상태 복원 중 오류:', e);
        }
    }
};

function toggleCard(cardId) {
    const content = document.getElementById(cardId);
    const toggle = document.getElementById(cardId.replace('-card', '-toggle'));

    if (content.classList.contains('show')) {
        content.classList.remove('show');
        // transform 제거 (회전 방지)
        toggle.style.transform = 'none';
    } else {
        content.classList.add('show');
        // transform 제거 (회전 방지)
        toggle.style.transform = 'none';
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
            savePageState();
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
            savePageState();
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
            savePageState();
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
            savePageState();
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

    // 저장된 상태가 있는지 확인
    const savedCards = sessionStorage.getItem('security_policy_cards');
    
    if (savedCards) {
        // 저장된 상태가 있으면 카드 복원
        setTimeout(() => {
            restorePageState();
            
            // 카드 애니메이션 완료 후 스크롤 위치 재확인
            setTimeout(() => {
                const savedPosition = sessionStorage.getItem('security_policy_scroll');
                if (savedPosition) {
                    window.scrollTo(0, parseInt(savedPosition));
                    sessionStorage.removeItem('security_policy_scroll');
                }
            }, 600);
        }, 100);
    } else {
        // 저장된 상태가 없으면 기본 자동 펼치기 실행
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
                        // transform 제거 (회전 방지)
                        toggle.style.transform = 'none';
                    }
                }, 500);
            }, index * 100);
        });
    }
});
</script>

<?php
require_once './admin.tail.php';
?>