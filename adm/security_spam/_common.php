<?php
$sub_menu = '950500';
require_once '../_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// 관리자 권한 변수 설정
$is_admin = isset($member['mb_level']) && $member['mb_level'] >= 10 ? 'super' : '';

// 기본 보안 테이블 자동 생성
if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_config LIMIT 1", false)) {
    $sql_file = __DIR__ . '/../security_block/security_block.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        $sql_content = str_replace('{PREFIX}', G5_TABLE_PREFIX, $sql_content);
        $statements = explode(';', $sql_content);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && strpos($statement, '--') !== 0) {
                sql_query($statement, false);
            }
        }
    }
}

// 스팸 관리 테이블 자동 생성
if (!sql_query("SELECT 1 FROM " . G5_TABLE_PREFIX . "security_login_fail LIMIT 1", false)) {
    $sql_file = __DIR__ . '/security_spam.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        $sql_content = str_replace('{PREFIX}', G5_TABLE_PREFIX, $sql_content);
        $statements = explode(';', $sql_content);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && strpos($statement, '--') !== 0) {
                sql_query($statement, false);
            }
        }
    }
}

// 현재 설정 로드
function gk_get_spam_config($key, $default = '') {
    $sql = "SELECT sc_value FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key = '" . sql_escape_string($key) . "'";
    $result = sql_query($sql, false);

    if ($result && $row = sql_fetch_array($result)) {
        return $row['sc_value'];
    }

    return $default;
}

// 로그인 차단 설정
$login_block_enabled = gk_get_spam_config('login_block_enabled', '0');
$login_fail_limit = gk_get_spam_config('login_fail_limit', '5');
$login_fail_window = gk_get_spam_config('login_fail_window', '5');
$login_block_duration = gk_get_spam_config('login_block_duration', '10');

// User-Agent 차단 설정
$useragent_block_enabled = gk_get_spam_config('useragent_block_enabled', '0');
$useragent_block_level = gk_get_spam_config('useragent_block_level', 'strict');

// 이상 행위 차단 설정
$behavior_404_enabled = gk_get_spam_config('behavior_404_enabled', '0');
$behavior_404_limit = gk_get_spam_config('behavior_404_limit', '10');
$behavior_404_window = gk_get_spam_config('behavior_404_window', '5');
$behavior_404_duration = gk_get_spam_config('behavior_404_duration', '60');

$behavior_referer_enabled = gk_get_spam_config('behavior_referer_enabled', '0');

// 다중 계정 차단 설정
$multiuser_register_enabled = gk_get_spam_config('multiuser_register_enabled', '0');
$multiuser_register_limit = gk_get_spam_config('multiuser_register_limit', '3');
$multiuser_register_duration = gk_get_spam_config('multiuser_register_duration', '60');

$multiuser_login_enabled = gk_get_spam_config('multiuser_login_enabled', '0');
$multiuser_login_limit = gk_get_spam_config('multiuser_login_limit', '5');
$multiuser_login_window = gk_get_spam_config('multiuser_login_window', '1440');
$multiuser_login_duration = gk_get_spam_config('multiuser_login_duration', '60');

// 정규식 스팸 차단 설정
$regex_spam_enabled = gk_get_spam_config('regex_spam_enabled', '0');
$regex_spam_action = gk_get_spam_config('regex_spam_action', 'ghost');
$regex_spam_block_duration = gk_get_spam_config('regex_spam_block_duration', '60');

// 통계 데이터 로드
$login_fail_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_login_fail WHERE slf_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['cnt'] ?? 0;
$blocked_ip_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_status = 'active' AND (sb_duration = 'permanent' OR sb_end_datetime > NOW())")['cnt'] ?? 0;
$spam_detected_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_spam_log WHERE ssl_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['cnt'] ?? 0;
?>