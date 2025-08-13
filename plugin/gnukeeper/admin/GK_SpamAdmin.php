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
     * 로그인 실패 로그 조회 (5회 이상 실패한 계정만)
     */
    public function getLoginFailLogs($page = 1, $limit = 10) {
        $page = max(1, (int)$page);
        $limit = max(1, min(100, (int)$limit));
        $offset = ($page - 1) * $limit;

        // 5회 이상 실패한 계정들의 통계를 먼저 조회 (모든 기간)
        $stats_sql = "SELECT slf_ip, slf_mb_id, COUNT(*) as fail_count, MAX(slf_datetime) as last_fail
                      FROM " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                      GROUP BY slf_ip, slf_mb_id
                      HAVING fail_count >= 5
                      ORDER BY fail_count DESC, last_fail DESC";

        $result = sql_query($stats_sql, false);
        $all_failed_accounts = array();

        if ($result) {
            while ($row = sql_fetch_array($result)) {
                $all_failed_accounts[] = $row;
            }
        }

        $total = count($all_failed_accounts);
        
        // 페이지네이션 적용
        $logs = array_slice($all_failed_accounts, $offset, $limit);
        
        // 각 계정에 대한 상세 정보 추가
        foreach ($logs as &$log) {
            // 최신 User-Agent 조회
            $agent_sql = "SELECT slf_user_agent FROM " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                         WHERE slf_ip = '" . sql_escape_string($log['slf_ip']) . "'
                         AND slf_mb_id = '" . sql_escape_string($log['slf_mb_id']) . "'
                         ORDER BY slf_datetime DESC LIMIT 1";
            
            $agent_result = sql_query($agent_sql, false);
            if ($agent_result && $agent_row = sql_fetch_array($agent_result)) {
                $log['slf_user_agent'] = $agent_row['slf_user_agent'];
            } else {
                $log['slf_user_agent'] = 'Unknown';
            }
            
            // 자동차단 여부 확인
            $block_check_sql = "SELECT sb_reason, sb_block_type FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                               WHERE sb_ip = '" . sql_escape_string($log['slf_ip']) . "'
                               AND sb_status = 'active'
                               AND sb_block_type = 'auto_login'";
            
            $block_result = sql_query($block_check_sql, false);
            if ($block_result && sql_num_rows($block_result) > 0) {
                $log['action_status'] = 'auto_blocked';
            } else {
                $log['action_status'] = 'none';
            }
            
            // 기존 필드명으로 매핑
            $log['slf_datetime'] = $log['last_fail'];
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

        $result = sql_query($sql);
        if ($result) {
            global $g5;
            $affected = 0;
            if (function_exists('mysqli_affected_rows') && G5_MYSQLI_USE) {
                $affected = mysqli_affected_rows($g5['connect_db']);
            } else {
                $affected = mysql_affected_rows($g5['connect_db']);
            }
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

        $result = sql_query($sql);
        if ($result) {
            global $g5;
            $affected = 0;
            if (function_exists('mysqli_affected_rows') && G5_MYSQLI_USE) {
                $affected = mysqli_affected_rows($g5['connect_db']);
            } else {
                $affected = mysql_affected_rows($g5['connect_db']);
            }
            return array('success' => true, 'message' => $affected . '개 기록이 삭제되었습니다.');
        } else {
            return array('success' => false, 'message' => '기록 삭제에 실패했습니다.');
        }
    }

    /**
     * IP를 차단 리스트에 추가
     */
    public function addToBlockList($ip) {
        if (empty($ip)) {
            return array('success' => false, 'message' => 'IP 주소가 비어있습니다.');
        }

        // IP 주소 형식 검증
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return array('success' => false, 'message' => '유효하지 않은 IP 주소입니다.');
        }

        $ip = sql_escape_string($ip);
        
        // 중복 확인
        $check_sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                     WHERE sb_ip = '$ip' AND sb_status = 'active'";
        
        $check_result = sql_query($check_sql, false);
        if ($check_result && $check_row = sql_fetch_array($check_result)) {
            if ($check_row['cnt'] > 0) {
                return array('success' => false, 'message' => '이미 차단 목록에 등록된 IP입니다.');
            }
        }

        // 차단 리스트에 추가
        $sql = "INSERT INTO " . GK_SECURITY_IP_BLOCK_TABLE . "
                (sb_ip, sb_reason, sb_block_type, sb_status, sb_datetime)
                VALUES (
                    '$ip',
                    '의심 IP 수동 차단',
                    'manual',
                    'active',
                    NOW()
                )";

        if (sql_query($sql)) {
            // 그누보드 기본 차단 목록에도 동기화
            if (class_exists('GK_BlockManager')) {
                GK_BlockManager::syncToGnuboard();
            }
            
            return array('success' => true, 'message' => 'IP가 차단 목록에 추가되었습니다.');
        } else {
            return array('success' => false, 'message' => '차단 목록 추가에 실패했습니다.');
        }
    }

    /**
     * 봇 로그 조회 (User-Agent 필터로 차단된 로그)
     */
    public function getBotLogs($page = 1, $limit = 10) {
        $page = max(1, (int)$page);
        $limit = max(1, min(100, (int)$limit));
        $offset = ($page - 1) * $limit;

        // 전체 개수 조회
        $count_sql = "SELECT COUNT(*) as total FROM " . GK_SECURITY_SPAM_LOG_TABLE . " 
                      WHERE sl_reason LIKE '%User-Agent 필터 탐지%'";
        $count_result = sql_query($count_sql, false);
        $total = 0;
        if ($count_result && $count_row = sql_fetch_array($count_result)) {
            $total = (int)$count_row['total'];
        }

        // 페이지별 데이터 조회
        $sql = "SELECT sl_id, sl_ip, sl_reason, sl_url, sl_user_agent, sl_datetime
                FROM " . GK_SECURITY_SPAM_LOG_TABLE . "
                WHERE sl_reason LIKE '%User-Agent 필터 탐지%'
                ORDER BY sl_datetime DESC
                LIMIT {$limit} OFFSET {$offset}";

        $result = sql_query($sql, false);
        $logs = array();

        if ($result) {
            while ($row = sql_fetch_array($result)) {
                // 해당 IP가 차단 목록에 있는지 확인 (모든 차단 타입 포함)
                $block_check_sql = "SELECT sb_reason, sb_block_type FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                                   WHERE sb_ip = '" . sql_escape_string($row['sl_ip']) . "'
                                   AND sb_status = 'active'";
                
                $block_result = sql_query($block_check_sql, false);
                if ($block_result && sql_num_rows($block_result) > 0) {
                    $row['action_status'] = 'auto_blocked';
                } else {
                    $row['action_status'] = 'detected_only';
                }
                
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
     * 봇 탐지 기록 삭제 (ID 기반)
     */
    public function deleteBotLog($log_id) {
        if (empty($log_id)) {
            return array('success' => false, 'message' => '로그 ID가 비어있습니다.');
        }

        $log_id = (int)$log_id;

        try {
            // 해당 ID의 User-Agent 탐지 기록 삭제
            $sql = "DELETE FROM " . GK_SECURITY_SPAM_LOG_TABLE . "
                    WHERE sl_id = $log_id
                    AND sl_reason LIKE '%User-Agent 필터 탐지%'";

            // 삭제하기 전에 먼저 개수 확인
            $count_sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_SPAM_LOG_TABLE . "
                         WHERE sl_id = $log_id
                         AND sl_reason LIKE '%User-Agent 필터 탐지%'";
            
            $count_result = sql_query($count_sql);
            $count = 0;
            if ($count_result && $count_row = sql_fetch_array($count_result)) {
                $count = (int)$count_row['cnt'];
            }
            
            if ($count == 0) {
                return array('success' => false, 'message' => '삭제할 기록이 없습니다.');
            }
            
            $result = sql_query($sql);
            if ($result) {
                return array(
                    'success' => true, 
                    'message' => '봇 탐지 기록이 삭제되었습니다.'
                );
            } else {
                return array('success' => false, 'message' => 'SQL 쿼리 실행에 실패했습니다.');
            }
        } catch (Exception $e) {
            return array('success' => false, 'message' => '오류: ' . $e->getMessage());
        }
    }

    /**
     * 의심 IP의 로그인 실패 기록 삭제
     */
    public function deleteSuspectIP($ip, $mb_id) {
        if (empty($ip)) {
            return array('success' => false, 'message' => 'IP 주소가 비어있습니다.');
        }

        // 테이블 상수가 정의되어 있는지 확인
        if (!defined('GK_SECURITY_LOGIN_FAIL_TABLE')) {
            return array('success' => false, 'message' => '테이블 상수가 정의되지 않았습니다.');
        }

        $ip = sql_escape_string($ip);
        $mb_id = sql_escape_string($mb_id);

        try {
            // 해당 IP와 사용자 ID의 로그인 실패 기록 삭제
            $sql = "DELETE FROM " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                    WHERE slf_ip = '$ip'";
            
            // 사용자 ID가 있고 비어있지 않으면 추가 조건
            if (!empty($mb_id) && $mb_id !== '-') {
                $sql .= " AND slf_mb_id = '$mb_id'";
            }

            // 삭제하기 전에 먼저 개수 확인
            $count_sql = "SELECT COUNT(*) as cnt FROM " . GK_SECURITY_LOGIN_FAIL_TABLE . "
                         WHERE slf_ip = '$ip'";
            
            // 사용자 ID가 있고 비어있지 않으면 추가 조건
            if (!empty($mb_id) && $mb_id !== '-') {
                $count_sql .= " AND slf_mb_id = '$mb_id'";
            }
            
            $count_result = sql_query($count_sql);
            $count = 0;
            if ($count_result && $count_row = sql_fetch_array($count_result)) {
                $count = (int)$count_row['cnt'];
            }
            
            if ($count == 0) {
                return array('success' => false, 'message' => '삭제할 기록이 없습니다.');
            }
            
            $result = sql_query($sql);
            if ($result) {
                return array(
                    'success' => true, 
                    'message' => '해당 기록이 삭제되었습니다.'
                );
            } else {
                return array('success' => false, 'message' => 'SQL 쿼리 실행에 실패했습니다.');
            }
        } catch (Exception $e) {
            return array('success' => false, 'message' => '오류: ' . $e->getMessage());
        }
    }
}