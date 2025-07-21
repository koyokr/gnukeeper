<?php
require_once './_common.php';

header('Content-Type: application/json');

if ($is_admin != 'super') {
    echo json_encode(['success' => false, 'message' => '최고관리자만 접근 가능합니다.']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$level = isset($_POST['level']) ? (int)$_POST['level'] : 1;

if (!$id) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

// 레벨 값 검증
if (!in_array($level, [-1, 1, 2, 10])) {
    echo json_encode(['success' => false, 'message' => '잘못된 권한 레벨입니다.']);
    exit;
}

$sql = "UPDATE g5_access_control SET ac_level = {$level} WHERE ac_id = {$id}";
$result = sql_query($sql);

if ($result) {
    echo json_encode(['success' => true, 'message' => '설정이 저장되었습니다.']);
} else {
    echo json_encode(['success' => false, 'message' => '데이터베이스 업데이트에 실패했습니다.']);
}
?>