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
        case 'toggle_foreign_block':
            $enabled = isset($_POST['foreign_block_enabled']) ? '1' : '0';
            gk_set_config('foreign_block_enabled', $enabled);

            $status_text = $enabled === '1' ? '활성화' : '비활성화';
            echo "해외 IP 차단이 {$status_text}되었습니다.";
            break;

        case 'toggle_foreign_block_feature':
            $enabled = $_POST['enabled'] ?? '0';
            if (in_array($enabled, ['0', '1'])) {
                gk_set_config('foreign_block_enabled', $enabled);
                $status_text = $enabled === '1' ? '활성화' : '비활성화';
                echo "해외 IP 차단 기능이 {$status_text}되었습니다.";
            } else {
                echo '잘못된 요청입니다.';
            }
            break;

        case 'save_foreign_config':
            $block_levels = isset($_POST['foreign_block_level']) && is_array($_POST['foreign_block_level'])
                          ? implode(',', $_POST['foreign_block_level'])
                          : 'access';

            gk_set_config('foreign_block_level', $block_levels);

            echo '해외 IP 차단 설정이 저장되었습니다.';
            break;
    }
    exit;
}
?>