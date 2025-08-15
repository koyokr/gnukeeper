<?php
/**
 * GnuKeeper 보안 플러그인 - 통합 훅 파일
 *
 * 이 파일은 extend 디렉토리에 위치하여 모든 페이지에서 자동으로 로드됩니다.
 * 실제 로직은 plugin/gnukeeper에 있는 클래스들을 호출합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 차단 문제 해결 완료 - GnuKeeper 정상 동작
// return;

// 강제로 테스트 로그 작성 (파일 시스템에)
file_put_contents('/tmp/gnukeeper_extend_test.log', date('Y-m-d H:i:s') . " - extend file loaded\n", FILE_APPEND);

// 플러그인 경로 정의
if (!defined('GK_PLUGIN_PATH')) {
    define('GK_PLUGIN_PATH', G5_PATH . '/plugin/gnukeeper');
}

// 플러그인이 존재하는지 확인
if (!file_exists(GK_PLUGIN_PATH . '/bootstrap.php')) {
    return;
}

// 플러그인 부트스트랩 로드
require_once GK_PLUGIN_PATH . '/bootstrap.php';

// 간단한 테스트 로그
error_log("GnuKeeper gnukeeper.extend.php loaded at " . date('Y-m-d H:i:s'), 3, '/tmp/gk_test.log');

// 플러그인이 초기화되었는지 확인
if (!gk_is_initialized()) {
    return;
}

// ========================================
// 1. 그누보드 기본 IP 차단에서 localhost 예외 처리
// ========================================
add_event('common_start', 'gk_override_gnuboard_ip_block', 1);
function gk_override_gnuboard_ip_block() {
    global $config;
    
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // localhost IP인 경우 그누보드 차단 설정 임시 무력화
    if (GK_BlockManager::is_localhost_ip($current_ip)) {
        $config['cf_intercept_ip'] = '';
        $config['cf_possible_ip'] = '';
    }
}

// ========================================
// 2. IP 차단 체크 (최우선 실행)
// ========================================
GK_BlockManager::checkCurrentIP();

// ========================================
// 3. 해외 IP 차단 체크
// ========================================
GK_BlockManager::checkForeignIP();

// ========================================
// 4. User-Agent 필터
// ========================================
require_once GK_FILTERS_PATH . '/UserAgentFilter.php';
gk_log("About to call UserAgentFilter::check()");
GK_UserAgentFilter::check();
gk_log("UserAgentFilter::check() completed");

// ========================================
// 5. 스팸 필터 등록
// ========================================

// 정규식 필터 등록
if (gk_get_config('regex_spam_enabled') == '1') {
    require_once GK_FILTERS_PATH . '/RegexFilter.php';
    GK_SpamDetector::registerFilter('regex', array('GK_RegexFilter', 'check'));
}

// 다중 사용자 필터 등록
if (gk_get_config('multiuser_login_enabled') == '1') {
    require_once GK_FILTERS_PATH . '/MultiUserFilter.php';
    GK_SpamDetector::registerFilter('multiuser', array('GK_MultiUserFilter', 'checkMultiLogin'));
}

// 행동 패턴 필터 등록
if (gk_get_config('behavior_404_enabled') == '1' || gk_get_config('behavior_referer_enabled') == '1') {
    require_once GK_FILTERS_PATH . '/BehaviorFilter.php';
}

// 비즈니스 로직 기반 Referer 검증 (항상 실행 - OFF 상태에서도 로그 기록)
require_once GK_FILTERS_PATH . '/BehaviorFilter.php';

// 직접 실행 (이벤트 시스템 문제 우회)
$request_method = $_SERVER['REQUEST_METHOD'] ?? '';
$current_script = $_SERVER['SCRIPT_NAME'] ?? '';

// 디버깅 로그
error_log("Security Hook Debug: Method=$request_method, Script=$current_script", 3, '/tmp/security_hook_debug.log');

if ($request_method === 'POST') {
    error_log("POST request detected, calling checkBusinessReferer()", 3, '/tmp/security_hook_debug.log');
    GK_BehaviorFilter::checkBusinessReferer();
} else {
    error_log("Not a POST request, skipping referer check", 3, '/tmp/security_hook_debug.log');
}

// ========================================
// 6. 회원가입 접근제어 체크
// ========================================
// register_form_update.php에서 신규 회원가입만 선별적으로 차단
if (basename($_SERVER['PHP_SELF']) === 'register_form_update.php') {
    // $w 값 확인 (신규 회원가입: '', 회원정보 수정: 'u')
    $w = '';
    if (isset($_POST['w'])) {
        $w = trim($_POST['w']);
    } elseif (isset($_GET['w'])) {
        $w = trim($_GET['w']);
    }

    // 신규 회원가입 시도인 경우만 차단 검사
    if ($w === '') {
        // 접근제어 테이블에서 회원가입 설정 확인
        $access_sql = "SELECT ac_level FROM g5_access_control WHERE ac_page = 'bbs/register.php'";
        $access_result = sql_query($access_sql, false);

        if ($access_result && $access_row = sql_fetch_array($access_result)) {
            $register_level = (int)$access_row['ac_level'];

            // 회원가입이 차단된 상태라면 (ac_level = -1 또는 0)
            if ($register_level <= 0) {
                alert('회원가입이 일시적으로 중단되었습니다.', G5_URL);
                exit;
            }
        }
    }
    // $w = 'u' (회원정보 수정)인 경우는 통과
}

// ========================================
// 7. 이벤트 훅 등록
// ========================================

// 로그인 성공 체크 훅
add_event('member_login_check', 'gk_login_success_handler', 10, 3);
function gk_login_success_handler($mb, $link, $is_social_login) {
    if (is_array($mb) && isset($mb['mb_id'])) {
        return GK_SpamDetector::checkLoginAttempt($mb['mb_id'], true);
    }
    return true;
}

// 로그인 실패 체크 훅
add_event('password_is_wrong', 'gk_login_fail_handler', 10, 2);
function gk_login_fail_handler($type, $mb) {
    // $type이 'login'이고 POST 데이터에 mb_id가 있으면 로그인 실패로 처리
    if ($type === 'login' && isset($_POST['mb_id'])) {
        $mb_id = $_POST['mb_id'];
        return GK_SpamDetector::checkLoginAttempt($mb_id, false);
    }
    return true;
}

// 회원가입 체크 훅
add_event('member_register_after', 'gk_register_check_handler', 10, 2);
function gk_register_check_handler($mb_id, $mb) {
    $mb_email = $mb['mb_email'] ?? '';
    return GK_SpamDetector::checkRegistration($mb_id, $mb_email);
}

// 게시글 작성 체크 훅
add_event('write_update_before', 'gk_write_check_handler', 10, 5);
function gk_write_check_handler($board, $wr_id, $w, $qstr, $redirect_url) {
    return GK_SpamDetector::checkContent($board, $wr_id);
}

// 댓글 작성 체크 훅
add_event('comment_update_before', 'gk_comment_check_handler', 10, 5);
function gk_comment_check_handler($board, $wr_id, $w, $qstr, $redirect_url) {
    return GK_SpamDetector::checkContent($board, $wr_id);
}

// 404 에러 처리 훅 (필요시 활성화)
if (gk_get_config('behavior_404_enabled') == '1') {
    add_event('tail_sub', 'gk_check_404_handler', 10, 0);
    function gk_check_404_handler() {
        // 404 페이지인 경우에만 실행
        if (http_response_code() == 404) {
            GK_BehaviorFilter::check404();
        }
    }
}

// ========================================
// 8. 전역 함수 정의 (하위 호환성)
// ========================================

// 그누보드 기본 IP 차단 기능 비활성화
if (!function_exists('gk_disable_gnuboard_ip_block')) {
    function gk_disable_gnuboard_ip_block() {
        global $config;

        // 그누보드 기본 IP 차단 설정을 비활성화
        if (isset($config['cf_use_ip_block'])) {
            $config['cf_use_ip_block'] = 0;
        }

        // 그누보드 IP 차단 함수를 무력화
        if (!function_exists('get_ip_block_list')) {
            function get_ip_block_list() {
                return array();
            }
        }

        // 차단 체크 함수를 무력화
        if (!function_exists('is_block_ip')) {
            function is_block_ip($ip) {
                return false;
            }
        }
    }
}

// CIDR 파싱 함수 (하위 호환성)
if (!function_exists('gk_parse_cidr')) {
    function gk_parse_cidr($cidr) {
        return GK_Common::parse_cidr($cidr);
    }
}

// 설정 저장 함수 (하위 호환성)
if (!function_exists('gk_set_config')) {
    function gk_set_config($key, $value) {
        return GK_Common::set_config($key, $value);
    }
}

// ========================================
// 9. 그누보드 기본 IP 차단 기능 비활성화 실행
// ========================================
if (GK_Common::get_config('disable_gnuboard_ip_block') == '1') {
    gk_disable_gnuboard_ip_block();
}