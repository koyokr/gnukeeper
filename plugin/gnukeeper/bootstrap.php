<?php
/**
 * GnuKeeper Plugin Bootstrap
 *
 * 플러그인 초기화 파일
 * extend에서 이 파일을 포함하여 플러그인을 활성화합니다.
 */

if (!defined('_GNUBOARD_')) exit;

// 플러그인 설정 파일 로드
require_once __DIR__ . '/config.php';

/**
 * 클래스 오토로더
 * GK_ 접두사를 가진 클래스를 자동으로 로드합니다.
 */
spl_autoload_register(function($class) {
    // GK_ 접두사가 없으면 처리하지 않음
    if (strpos($class, 'GK_') !== 0) {
        return;
    }

    // 우선 순위: admin -> core -> filters 순으로 검색
    $search_paths = [
        GK_ADMIN_PATH,
        GK_CORE_PATH,
        GK_FILTERS_PATH
    ];

    foreach ($search_paths as $path) {
        $file = $path . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * 필터 로더 함수
 * 필요한 필터를 동적으로 로드합니다.
 */
function gk_load_filter($filter_name) {
    $file = GK_FILTERS_PATH . '/' . $filter_name . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
}

/**
 * 플러그인 초기화 체크
 */
function gk_is_initialized() {
    static $initialized = null;

    if ($initialized === null) {
        // 필요한 테이블이 존재하는지 확인
        $sql = "SHOW TABLES LIKE '" . GK_SECURITY_CONFIG_TABLE . "'";
        $result = sql_query($sql, false);
        $initialized = ($result && sql_num_rows($result) > 0);
    }

    return $initialized;
}

/**
 * 플러그인 설정 로드
 * 캐싱을 통해 성능 최적화
 */
if (!function_exists('gk_get_config')) {
    function gk_get_config($key = null) {
        static $config = null;

        if ($config === null) {
            $config = array();

            // 플러그인이 초기화되지 않았으면 기본값 반환
            if (!gk_is_initialized()) {
                return $key ? null : array();
            }

            // DB에서 설정 로드
            $sql = "SELECT sc_key, sc_value FROM " . GK_SECURITY_CONFIG_TABLE;
            $result = sql_query($sql, false);

            if ($result) {
                while ($row = sql_fetch_array($result)) {
                    $config[$row['sc_key']] = $row['sc_value'];
                }
            }
        }

        // 특정 키 요청시
        if ($key !== null) {
            return isset($config[$key]) ? $config[$key] : null;
        }

        return $config;
    }
}

/**
 * 플러그인 설정 저장
 */
if (!function_exists('gk_set_config')) {
    function gk_set_config($key, $value) {
        if (!gk_is_initialized()) {
            return false;
        }

        $key = sql_escape_string($key);
        $value = sql_escape_string($value);
        $datetime = G5_TIME_YMDHIS;

        $sql = "INSERT INTO " . GK_SECURITY_CONFIG_TABLE . "
                (sc_key, sc_value, sc_datetime) VALUES ('$key', '$value', '$datetime')
                ON DUPLICATE KEY UPDATE sc_value = '$value', sc_datetime = '$datetime'";

        return sql_query($sql);
    }
}

/**
 * 디버그 로그 함수
 */
if (!function_exists('gk_log')) {
    function gk_log($message, $level = 'info') {
        // 디버그 모드가 활성화된 경우에만 로그 작성
        $debug_enabled = defined('GK_DEBUG') && GK_DEBUG;
        if (!$debug_enabled) {
            return;
        }

        $log_file = G5_DATA_PATH . '/gnukeeper_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;

        error_log($log_entry, 3, $log_file);
    }
}

/**
 * 플러그인 초기화 완료 후 그누보드 설정 동기화
 * 최초 한 번만 실행되도록 플래그로 제어
 */
if (gk_is_initialized()) {
    $sync_completed = gk_get_config('initial_sync_completed');
    if (!$sync_completed && class_exists('GK_BlockManager')) {
        try {
            // 그누보드 설정을 GnuKeeper로 동기화
            GK_BlockManager::syncFromGnuboard();
            
            // 동기화 완료 플래그 설정
            gk_set_config('initial_sync_completed', '1');
            
            gk_log('Initial sync from Gnuboard completed successfully');
        } catch (Exception $e) {
            gk_log('Initial sync failed: ' . $e->getMessage(), 'error');
        }
    }
}