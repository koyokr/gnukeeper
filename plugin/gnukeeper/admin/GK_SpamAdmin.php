<?php
/**
 * GnuKeeper 스팸 관리 어드민 클래스
 */

if (!defined('_GNUBOARD_')) exit;

class GK_SpamAdmin {

    private static $instance = null;

    /**
     * 싱글톤 인스턴스 반환
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 스팸 관리 통계 조회
     */
    public function getSpamStats() {
        global $g5;

        $stats = array(
            'login_fail_count' => 0,
            'blocked_ip_count' => 0,
            'spam_detected_count' => 0,
            'active_features_count' => 0,
            'login_block_enabled' => GK_Common::get_config('login_block_enabled') ?? '1',
            'useragent_block_enabled' => GK_Common::get_config('useragent_block_enabled') ?? '0',
            'behavior_404_enabled' => GK_Common::get_config('behavior_404_enabled') ?? '0',
            'behavior_referer_enabled' => GK_Common::get_config('behavior_referer_enabled') ?? '0',
            'multiuser_register_enabled' => GK_Common::get_config('multiuser_register_enabled') ?? '0',
            'multiuser_login_enabled' => GK_Common::get_config('multiuser_login_enabled') ?? '0',
            'regex_spam_enabled' => GK_Common::get_config('regex_spam_enabled') ?? '0'
        );

        // 24시간 로그인 실패 횟수
        $login_fail_sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                          WHERE slf_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = sql_query($login_fail_sql, false);
        if ($result && $row = sql_fetch_array($result)) {
            $stats['login_fail_count'] = $row['cnt'];
        }

        // 현재 차단된 IP 수
        $blocked_ip_sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                          WHERE sb_status = 'active'";
        $result = sql_query($blocked_ip_sql, false);
        if ($result && $row = sql_fetch_array($result)) {
            $stats['blocked_ip_count'] = $row['cnt'];
        }

        // 24시간 스팸 탐지 횟수
        $spam_log_sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_SPAM_LOG_TABLE . "
                        WHERE sl_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = sql_query($spam_log_sql, false);
        if ($result && $row = sql_fetch_array($result)) {
            $stats['spam_detected_count'] = $row['cnt'];
        }

        // 활성화된 기능 수 계산
        $active_count = 0;
        $features = [
            'login_block_enabled', 'useragent_block_enabled', 'behavior_404_enabled',
            'behavior_referer_enabled', 'multiuser_register_enabled', 'multiuser_login_enabled',
            'regex_spam_enabled'
        ];

        foreach ($features as $feature) {
            if ($stats[$feature] == '1') {
                $active_count++;
            }
        }
        $stats['active_features_count'] = $active_count;

        return $stats;
    }

    /**
     * 로그인 차단 설정 토글
     */
    public function toggleLoginBlock($enabled) {
        return GK_Common::set_config('login_block_enabled', $enabled ? '1' : '0');
    }

    /**
     * User-Agent 차단 설정 토글
     */
    public function toggleUserAgentBlock($enabled) {
        return GK_Common::set_config('useragent_block_enabled', $enabled ? '1' : '0');
    }

    /**
     * 404 행동 차단 설정 토글
     */
    public function toggleBehavior404($enabled) {
        return GK_Common::set_config('behavior_404_enabled', $enabled ? '1' : '0');
    }

    /**
     * Referer 행동 차단 설정 토글
     */
    public function toggleBehaviorReferer($enabled) {
        return GK_Common::set_config('behavior_referer_enabled', $enabled ? '1' : '0');
    }

    /**
     * 다중 사용자 가입 차단 설정 토글
     */
    public function toggleMultiUserRegister($enabled) {
        return GK_Common::set_config('multiuser_register_enabled', $enabled ? '1' : '0');
    }

    /**
     * 다중 사용자 로그인 차단 설정 토글
     */
    public function toggleMultiUserLogin($enabled) {
        return GK_Common::set_config('multiuser_login_enabled', $enabled ? '1' : '0');
    }

    /**
     * 정규식 스팸 차단 설정 토글
     */
    public function toggleRegexSpam($enabled) {
        return GK_Common::set_config('regex_spam_enabled', $enabled ? '1' : '0');
    }

    /**
     * 로그인 실패 로그 조회
     */
    public function getLoginFailLogs($page = 1, $limit = 10) {
        $page = max(1, (int)$page);
        $limit = max(1, min(100, (int)$limit));
        $offset = ($page - 1) * $limit;

        // 전체 개수 조회
        $count_sql = "SELECT COUNT(*) as total FROM " . GK_SECURITY_LOGIN_FAIL_TABLE;
        $count_result = sql_query($count_sql, false);
        $total = 0;
        if ($count_result && $count_row = sql_fetch_array($count_result)) {
            $total = (int)$count_row['total'];
        }

        // 페이지별 데이터 조회
        $sql = "SELECT slf_ip, slf_datetime, slf_mb_id, slf_user_agent
                FROM " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                ORDER BY slf_datetime DESC
                LIMIT {$limit} OFFSET {$offset}";

        $result = sql_query($sql, false);
        $logs = array();

        if ($result) {
            while ($row = sql_fetch_array($result)) {
                $logs[] = $row;
            }
        }

        return [
            'data' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * 스팸 탐지 로그 조회
     */
    public function getSpamLogs($page = 1, $limit = 10) {
        $page = max(1, (int)$page);
        $limit = max(1, min(100, (int)$limit));
        $offset = ($page - 1) * $limit;

        // 전체 개수 조회
        $count_sql = "SELECT COUNT(*) as total FROM " . GK_SECURITY_SPAM_LOG_TABLE;
        $count_result = sql_query($count_sql, false);
        $total = 0;
        if ($count_result && $count_row = sql_fetch_array($count_result)) {
            $total = (int)$count_row['total'];
        }

        // 페이지별 데이터 조회
        $sql = "SELECT sl_ip, sl_datetime, sl_reason, sl_url, sl_user_agent
                FROM " . GK_SECURITY_SPAM_LOG_TABLE . "
                ORDER BY sl_datetime DESC
                LIMIT {$limit} OFFSET {$offset}";

        $result = sql_query($sql, false);
        $logs = array();

        if ($result) {
            while ($row = sql_fetch_array($result)) {
                $logs[] = $row;
            }
        }

        return [
            'data' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * 차단된 IP 해제
     */
    public function unblockIP($ip) {
        if (empty($ip)) {
            return array('success' => false, 'message' => 'IP 주소가 비어있습니다.');
        }

        $ip = sql_escape_string($ip);
        $sql = "DELETE FROM " . GK_SECURITY_IP_BLOCK_TABLE . " WHERE sb_ip = '$ip'";

        if (sql_query($sql)) {
            return array('success' => true, 'message' => 'IP 차단이 해제되었습니다.');
        } else {
            return array('success' => false, 'message' => '차단 해제에 실패했습니다.');
        }
    }

    /**
     * 로그인 실패 기록 삭제
     */
    public function clearLoginFailLogs($days = 30) {
        $sql = "DELETE FROM " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                WHERE slf_datetime < DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)";

        if (sql_query($sql)) {
            $affected = sql_affected_rows();
            return array('success' => true, 'message' => $affected . '개 기록이 삭제되었습니다.');
        } else {
            return array('success' => false, 'message' => '기록 삭제에 실패했습니다.');
        }
    }

    /**
     * 스팸 탐지 기록 삭제
     */
    public function clearSpamLogs($days = 30) {
        $sql = "DELETE FROM " . GK_SECURITY_SPAM_LOG_TABLE . "
                WHERE sl_datetime < DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)";

        if (sql_query($sql)) {
            $affected = sql_affected_rows();
            return array('success' => true, 'message' => $affected . '개 기록이 삭제되었습니다.');
        } else {
            return array('success' => false, 'message' => '기록 삭제에 실패했습니다.');
        }
    }
}