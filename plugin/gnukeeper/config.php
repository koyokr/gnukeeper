<?php
/**
 * GnuKeeper Plugin Configuration
 *
 * GnuKeeper 플러그인 설정 파일
 * 플러그인 경로 및 기본 설정을 정의합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 디버그 모드 활성화 (테스트용)
if (!defined('GK_DEBUG')) {
    define('GK_DEBUG', true);
}

// 플러그인 기본 경로
if (!defined('GK_PLUGIN_PATH')) {
    define('GK_PLUGIN_PATH', G5_PATH . '/plugin/gnukeeper');
}
if (!defined('GK_PLUGIN_URL')) {
    define('GK_PLUGIN_URL', G5_URL . '/plugin/gnukeeper');
}

// 플러그인 하위 디렉토리
define('GK_SQL_PATH', GK_PLUGIN_PATH . '/sql');
define('GK_DATA_PATH', GK_PLUGIN_PATH . '/data');
define('GK_CORE_PATH', GK_PLUGIN_PATH . '/core');
define('GK_FILTERS_PATH', GK_PLUGIN_PATH . '/filters');
define('GK_HOOKS_PATH', GK_PLUGIN_PATH . '/hooks');
define('GK_ADMIN_PATH', GK_PLUGIN_PATH . '/admin');

// 주요 파일 경로
define('GK_INSTALL_SQL', GK_SQL_PATH . '/install.sql');
define('GK_KOREA_IP_FILE', GK_DATA_PATH . '/korea_ip_list.txt');

// 테이블명 정의 (G5_TABLE_PREFIX 사용)
define('GK_SECURITY_CONFIG_TABLE', G5_TABLE_PREFIX . 'security_config');
define('GK_SECURITY_IP_BLOCK_TABLE', G5_TABLE_PREFIX . 'security_ip_block');
define('GK_SECURITY_IP_LOG_TABLE', G5_TABLE_PREFIX . 'security_ip_log');
define('GK_SECURITY_IP_WHITELIST_TABLE', G5_TABLE_PREFIX . 'security_ip_whitelist');
define('GK_SECURITY_LOGIN_FAIL_TABLE', G5_TABLE_PREFIX . 'security_login_fail');
define('GK_SECURITY_REGISTER_LOG_TABLE', G5_TABLE_PREFIX . 'security_register_log');
define('GK_SECURITY_LOGIN_SUCCESS_LOG_TABLE', G5_TABLE_PREFIX . 'security_login_success_log');
define('GK_SECURITY_404_LOG_TABLE', G5_TABLE_PREFIX . 'security_404_log');
define('GK_SECURITY_REFERER_LOG_TABLE', G5_TABLE_PREFIX . 'security_referer_log');
define('GK_SECURITY_REGEX_SPAM_TABLE', G5_TABLE_PREFIX . 'security_regex_spam');
define('GK_SECURITY_REGEX_SPAM_LOG_TABLE', G5_TABLE_PREFIX . 'security_regex_spam_log');
define('GK_SECURITY_SPAM_LOG_TABLE', G5_TABLE_PREFIX . 'security_spam_log');

// 플러그인 버전
define('GK_VERSION', '0.1.0');

// GitHub 레포지토리 정보 (버전 체크용)
define('GK_GITHUB_REPO', 'koyokr/gnukeeper');

// 플러그인 활성화 상태 (기본값)
if (!defined('GK_ENABLED')) {
    define('GK_ENABLED', true);
}

/**
 * 플러그인 경로 헬퍼 함수
 */
function gk_get_path($type = '') {
    switch($type) {
        case 'sql':
            return GK_SQL_PATH;
        case 'data':
            return GK_DATA_PATH;
        case 'core':
            return GK_CORE_PATH;
        case 'filters':
            return GK_FILTERS_PATH;
        case 'hooks':
            return GK_HOOKS_PATH;
        default:
            return GK_PLUGIN_PATH;
    }
}

/**
 * 플러그인 URL 헬퍼 함수
 */
function gk_get_url($path = '') {
    return GK_PLUGIN_URL . ($path ? '/' . ltrim($path, '/') : '');
}