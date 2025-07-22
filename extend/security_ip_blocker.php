<?php
/**
 * IP 차단 관리 클래스
 * gnuboard5 보안 플러그인
 * 
 * 이 클래스는 IP 차단 로직을 처리하며, 성능 최적화를 위해 캐싱을 사용합니다.
 */

class SecurityIPBlocker {
    
    private $cache_time = 300; // 5분 캐시
    private static $instance = null;
    private $blocked_ips_cache = null;
    private $cache_updated = 0;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * IP 주소가 차단되어 있는지 확인
     * @param string $ip 확인할 IP 주소
     * @return array|false 차단 정보 배열 또는 false
     */
    public function isBlocked($ip) {
        if (!$this->isValidIP($ip)) {
            return false;
        }
        
        // 관리자 계정 확인 (최우선 예외)
        if ($this->isAdminUser()) {
            return false;
        }
        
        // 화이트리스트 확인
        if ($this->isWhitelisted($ip)) {
            return false;
        }
        
        $this->loadBlockedIPs();
        
        $ip_long = $this->ipToLong($ip);
        
        foreach ($this->blocked_ips_cache as $block) {
            if ($ip_long >= $block['start_ip'] && $ip_long <= $block['end_ip']) {
                // 임시 차단의 경우 만료 시간 확인
                if ($block['duration'] == 'temporary' && 
                    $block['end_datetime'] && 
                    strtotime($block['end_datetime']) < time()) {
                    
                    // 만료된 차단 규칙 상태 업데이트
                    $this->expireBlock($block['id']);
                    continue;
                }
                
                // 차단 적중 횟수 증가
                $this->incrementHitCount($block['id'], $ip);
                
                return $block;
            }
        }
        
        return false;
    }
    
    /**
     * 로그인 시도 제한 확인 및 자동 차단
     * @param string $ip 확인할 IP 주소
     * @param string $username 로그인 시도 사용자명
     * @return bool 차단 여부
     */
    public function checkLoginAttempt($ip, $username = '') {
        global $g5;
        
        // 관리자는 로그인 시도 제한에서 제외
        if ($this->isAdminUser()) {
            return false;
        }
        
        // 기존 차단 확인
        if ($this->isBlocked($ip)) {
            return true;
        }
        
        // 로그인 시도 제한 설정 확인
        $config = $this->getConfig();
        $attempt_limit = $config['login_attempt_limit'] ?: 5;
        $time_window = $config['login_attempt_window'] ?: 300;
        $block_duration = $config['auto_block_duration'] ?: 3600;
        
        // 최근 로그인 실패 횟수 확인
        $since_time = date('Y-m-d H:i:s', time() - $time_window);
        
        // 로그인 로그 테이블이 있다면 사용, 없다면 간단한 파일 기반 로깅
        $fail_count = $this->getLoginFailCount($ip, $since_time);
        
        if ($fail_count >= $attempt_limit) {
            // 자동 차단 추가
            $end_time = date('Y-m-d H:i:s', time() + $block_duration);
            $this->addAutoBlock($ip, 'auto_login', 
                              "로그인 {$attempt_limit}회 실패", 
                              $end_time);
            return true;
        }
        
        return false;
    }
    
    /**
     * 스팸 활동 감지 및 자동 차단
     * @param string $ip IP 주소
     * @param string $action 행동 유형 (post, comment 등)
     * @param array $data 추가 데이터
     * @return bool 차단 여부
     */
    public function checkSpamActivity($ip, $action, $data = array()) {
        // 관리자는 스팸 검사에서 제외
        if ($this->isAdminUser()) {
            return false;
        }
        
        // 기존 차단 확인
        if ($this->isBlocked($ip)) {
            return true;
        }
        
        // 스팸 패턴 검사 로직
        $is_spam = false;
        
        // 1. 연속 게시글 작성 검사
        if ($action == 'post') {
            $recent_posts = $this->getRecentPostCount($ip, 60); // 1분 내
            if ($recent_posts >= 3) {
                $is_spam = true;
                $reason = "1분 내 {$recent_posts}개 게시글 작성";
            }
        }
        
        // 2. 동일 내용 반복 검사
        if (isset($data['content']) && $data['content']) {
            $duplicate_count = $this->getDuplicateContentCount($ip, $data['content']);
            if ($duplicate_count >= 2) {
                $is_spam = true;
                $reason = "동일 내용 {$duplicate_count}회 반복";
            }
        }
        
        // 3. 스팸 키워드 검사
        if (isset($data['content']) && $this->containsSpamKeywords($data['content'])) {
            $is_spam = true;
            $reason = "스팸 키워드 포함";
        }
        
        if ($is_spam) {
            $block_duration = $this->getConfig()['auto_block_duration'] ?: 3600;
            $end_time = date('Y-m-d H:i:s', time() + $block_duration);
            $this->addAutoBlock($ip, 'auto_spam', $reason, $end_time);
            return true;
        }
        
        return false;
    }
    
    /**
     * 차단된 IP에 대한 로그 기록
     * @param string $ip IP 주소
     * @param int $block_id 차단 규칙 ID
     * @param string $action 시도한 행동
     * @param array $details 상세 정보
     */
    public function logBlockedAccess($ip, $block_id, $action, $details = array()) {
        global $g5;
        
        $reason = $details['reason'] ?? '차단된 IP 접근';
        
        $sql = "INSERT INTO g5_security_ip_log SET
                    sl_ip = '{$ip}',
                    sl_block_id = " . (int)$block_id . ",
                    sl_action = '{$action}',
                    sl_reason = '" . addslashes($reason) . "',
                    sl_datetime = NOW()";
        
        sql_query($sql);
    }
    
    /**
     * 차단 목록을 캐시에서 로드
     */
    private function loadBlockedIPs() {
        if ($this->blocked_ips_cache !== null && 
            (time() - $this->cache_updated) < $this->cache_time) {
            return;
        }
        
        global $g5;
        
        $sql = "SELECT sb_id as id, sb_ip as ip, 
                       sb_start_ip as start_ip, sb_end_ip as end_ip,
                       sb_reason as reason, sb_block_type as block_type,
                       sb_duration as duration, sb_end_datetime as end_datetime,
                       sb_hit_count as hit_count
                FROM g5_security_ip_block 
                WHERE sb_status = 'active'
                ORDER BY sb_start_ip";
        
        $result = sql_query($sql);
        $this->blocked_ips_cache = array();
        
        while ($row = sql_fetch_array($result)) {
            $this->blocked_ips_cache[] = $row;
        }
        
        $this->cache_updated = time();
    }
    
    /**
     * IP 주소를 숫자로 변환 (IPv4만 지원)
     */
    private function ipToLong($ip) {
        $long = ip2long($ip);
        return $long === false ? 0 : sprintf('%u', $long);
    }
    
    /**
     * 유효한 IP 주소인지 확인
     */
    private function isValidIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
    
    /**
     * 관리자 계정인지 확인
     */
    private function isAdminUser() {
        global $member;
        return isset($member) && $member['mb_level'] >= 10;
    }
    
    /**
     * IP가 화이트리스트에 있는지 확인
     */
    private function isWhitelisted($ip) {
        $ip_long = $this->ipToLong($ip);
        
        $sql = "SELECT COUNT(*) as cnt FROM g5_security_ip_whitelist 
                WHERE {$ip_long} BETWEEN sw_start_ip AND sw_end_ip";
        
        $result = sql_query($sql, false);
        if ($result && $row = sql_fetch_array($result)) {
            return $row['cnt'] > 0;
        }
        
        return false;
    }
    
    /**
     * 차단 적중 횟수 증가
     */
    private function incrementHitCount($block_id, $ip) {
        global $g5;
        
        $sql = "UPDATE g5_security_ip_block SET 
                    sb_hit_count = sb_hit_count + 1
                WHERE sb_id = " . (int)$block_id;
        
        sql_query($sql);
    }
    
    /**
     * 만료된 차단 규칙 상태 업데이트
     */
    private function expireBlock($block_id) {
        global $g5;
        
        $sql = "UPDATE g5_security_ip_block SET sb_status = 'expired' 
                WHERE sb_id = " . (int)$block_id;
        
        sql_query($sql);
        
        // 캐시 무효화
        $this->blocked_ips_cache = null;
    }
    
    /**
     * 자동 차단 추가
     */
    private function addAutoBlock($ip, $type, $reason, $end_datetime = null) {
        // 관리자는 자동 차단에서 제외
        if ($this->isAdminUser()) {
            return false;
        }
        
        // 화이트리스트 확인 - 화이트리스트에 있으면 차단하지 않음
        if ($this->isWhitelisted($ip)) {
            return false;
        }
        
        $ip_long = $this->ipToLong($ip);
        $duration = $end_datetime ? 'temporary' : 'permanent';
        $end_sql = $end_datetime ? "'{$end_datetime}'" : 'NULL';
        
        $sql = "INSERT INTO g5_security_ip_block SET
                    sb_ip = '{$ip}',
                    sb_start_ip = {$ip_long},
                    sb_end_ip = {$ip_long},
                    sb_reason = '" . addslashes($reason) . "',
                    sb_block_type = '{$type}',
                    sb_duration = '{$duration}',
                    sb_end_datetime = {$end_sql},
                    sb_datetime = NOW()";
        
        sql_query($sql);
        
        // 캐시 무효화
        $this->blocked_ips_cache = null;
    }
    
    /**
     * 보안 설정 로드
     */
    private function getConfig() {
        global $g5;
        static $config = null;
        
        if ($config === null) {
            $config = array();
            $result = sql_query("SELECT sc_key, sc_value FROM g5_security_config");
            while ($row = sql_fetch_array($result)) {
                $config[$row['sc_key']] = $row['sc_value'];
            }
        }
        
        return $config;
    }
    
    /**
     * 로그인 실패 횟수 조회 (간단한 구현)
     */
    private function getLoginFailCount($ip, $since_time) {
        // 실제 구현에서는 로그인 로그 테이블을 사용
        // 여기서는 임시로 세션이나 파일 기반으로 구현
        
        $log_file = G5_DATA_PATH . '/login_fails.log';
        if (!file_exists($log_file)) {
            return 0;
        }
        
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = 0;
        $since_timestamp = strtotime($since_time);
        
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $log_ip = $parts[0];
                $log_time = strtotime($parts[1]);
                
                if ($log_ip == $ip && $log_time >= $since_timestamp) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * 최근 게시글 수 조회
     */
    private function getRecentPostCount($ip, $seconds) {
        global $g5;
        
        $since_time = date('Y-m-d H:i:s', time() - $seconds);
        
        // 모든 게시판의 게시글 확인
        $sql = "SELECT COUNT(*) as cnt FROM g5_write_prefix 
                WHERE wr_ip = '{$ip}' AND wr_datetime >= '{$since_time}'";
        
        // 실제로는 동적으로 모든 게시판 테이블을 확인해야 함
        // 여기서는 간단한 구현
        return 0;
    }
    
    /**
     * 중복 내용 개수 조회
     */
    private function getDuplicateContentCount($ip, $content) {
        // 내용의 해시값으로 중복 검사
        $content_hash = md5($content);
        
        // 실제 구현에서는 최근 게시글/댓글에서 동일한 해시값 찾기
        return 0;
    }
    
    /**
     * 스팸 키워드 포함 여부 확인
     */
    private function containsSpamKeywords($content) {
        $spam_keywords = array(
            '바카라', '카지노', '토토', '먹튀', '온라인게임',
            'viagra', 'cialis', 'casino', 'poker', 'gambling'
        );
        
        $content_lower = strtolower($content);
        
        foreach ($spam_keywords as $keyword) {
            if (strpos($content_lower, strtolower($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 로그인 실패 기록
     */
    public function recordLoginFail($ip) {
        $log_file = G5_DATA_PATH . '/login_fails.log';
        $log_entry = $ip . '|' . date('Y-m-d H:i:s') . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // 오래된 로그 정리 (24시간 이상)
        $this->cleanupLoginFailLog();
    }
    
    /**
     * 로그인 실패 로그 정리
     */
    private function cleanupLoginFailLog() {
        $log_file = G5_DATA_PATH . '/login_fails.log';
        if (!file_exists($log_file)) {
            return;
        }
        
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoff_time = time() - 86400; // 24시간
        $new_lines = array();
        
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $log_time = strtotime($parts[1]);
                if ($log_time >= $cutoff_time) {
                    $new_lines[] = $line;
                }
            }
        }
        
        file_put_contents($log_file, implode("\n", $new_lines) . "\n");
    }
}

// 전역 함수로 간편하게 사용할 수 있도록
function security_check_ip($ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    $blocker = SecurityIPBlocker::getInstance();
    return $blocker->isBlocked($ip);
}

function security_check_login_attempt($ip = null, $username = '') {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    $blocker = SecurityIPBlocker::getInstance();
    return $blocker->checkLoginAttempt($ip, $username);
}

function security_check_spam($action, $data = array(), $ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    $blocker = SecurityIPBlocker::getInstance();
    return $blocker->checkSpamActivity($ip, $action, $data);
}

function security_record_login_fail($ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    $blocker = SecurityIPBlocker::getInstance();
    $blocker->recordLoginFail($ip);
}
?>