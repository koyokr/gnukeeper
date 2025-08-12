<?php
/**
 * GnuKeeper - IP 차단 관리자 클래스
 *
 * 관리자 페이지에서 사용되는 IP 차단 관련 비즈니스 로직을 담당
 */

if (!defined('_GNUBOARD_')) exit;

class GK_BlockAdmin
{
    /** @var self|null */
    private static $instance = null;

    /** @var string */
    private $current_admin_ip;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->current_admin_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * 차단 통계 데이터 가져오기
     */
    public function getBlockStats(): array
    {
        global $config;

        // 고급 IP 차단 관리에서 차단한 IP 수만 계산 (그누보드 기본 IP 차단 제외)
        $gk_blocks_result = sql_fetch("SELECT COUNT(*) as cnt FROM " . GK_SECURITY_IP_BLOCK_TABLE . " WHERE sb_status = 'active'");
        $active_blocks_count = $gk_blocks_result ? (int)($gk_blocks_result['cnt'] ?? 0) : 0;

        // 예외 IP는 별도 테이블에서 관리
        $whitelist_result = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist");
        $whitelist_count = $whitelist_result ? (int)($whitelist_result['cnt'] ?? 0) : 0;

        // 오늘 고급 IP 차단 관리에서 차단된 IP 수
        $today_blocks_result = sql_fetch("SELECT COUNT(*) as cnt FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
            WHERE sb_status = 'active' AND DATE(sb_datetime) = CURDATE()");
        $today_blocks_count = $today_blocks_result ? (int)($today_blocks_result['cnt'] ?? 0) : 0;

        // 해외 IP 차단 설정값 로드
        $foreign_block_enabled = gk_get_config('foreign_block_enabled', '0');

        // 고급 IP 차단 관리 활성화 여부
        $gk_block_enabled = gk_get_config('gk_ip_block_enabled', '1');

        return [
            'active_blocks_count' => $active_blocks_count,
            'whitelist_count' => $whitelist_count,
            'today_blocks_count' => $today_blocks_count,
            'foreign_block_enabled' => $foreign_block_enabled,
            'gk_block_enabled' => $gk_block_enabled
        ];
    }

    /**
     * GnuKeeper IP 차단 기능 활성화 여부 확인
     */
    public function isGKBlockEnabled(): bool
    {
        static $enabled = null;
        if ($enabled === null) {
            $enabled = gk_get_config('gk_ip_block_enabled', '1') === '1';
        }
        return $enabled;
    }

    /**
     * GnuKeeper IP 차단 기능 토글
     */
    public function toggleGKBlock(bool $enabled): array
    {
        global $config;

        if (gk_set_config('gk_ip_block_enabled', $enabled ? '1' : '0')) {
            if ($enabled) {
                // GnuKeeper 차단 활성화 시 그누보드 기본 차단 비활성화
                $sql = "UPDATE " . G5_TABLE_PREFIX . "config SET cf_intercept_ip = ''";
                sql_query($sql);
                $message = 'GnuKeeper IP 차단이 활성화되었습니다. (그누보드 기본 차단 비활성화)';
            } else {
                $message = 'GnuKeeper IP 차단이 비활성화되었습니다.';
            }
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => '설정 변경에 실패했습니다.'];
        }
    }

    /**
     * CIDR 표기법을 IP 범위로 변환
     */
    private function parseCIDR($cidr): ?array
    {
        if (strpos($cidr, '/') === false) {
            // 단일 IP인 경우 /32 추가
            $cidr = $cidr . '/32';
        }

        list($ip, $prefix) = explode('/', $cidr);

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $prefix = intval($prefix);
        if ($prefix < 0 || $prefix > 32) {
            return null;
        }

        $ip_long = ip2long($ip);
        $mask = -1 << (32 - $prefix);
        $start_ip = $ip_long & $mask;
        $end_ip = $start_ip | (~$mask & 0xFFFFFFFF);

        return [
            'original' => $cidr,
            'start_ip' => sprintf('%u', $start_ip),
            'end_ip' => sprintf('%u', $end_ip),
            'prefix' => $prefix
        ];
    }

    /**
     * IP/CIDR 정규화 (중복 방지용)
     */
    private function normalizeIP($ip): string
    {
        if (strpos($ip, '/') === false) {
            return $ip . '/32';
        }

        list($ip_part, $prefix) = explode('/', $ip);
        $prefix = intval($prefix);

        if ($prefix === 32) {
            return $ip_part . '/32';
        }

        return $ip;
    }

    /**
     * IP 차단 추가 (CIDR 지원, 자동/수동 구분)
     */
    public function addIPBlock($ip, $reason = '', $block_type = 'manual'): array
    {
        if (empty($ip)) {
            return ['success' => false, 'message' => 'IP 주소를 입력해주세요.'];
        }

        // 관리자 IP 보호
        if ($this->isAdminIPProtected($ip)) {
            return ['success' => false, 'message' => '관리자 IP는 차단할 수 없습니다.'];
        }

        // GK_BlockManager를 사용하여 IP 차단 추가
        $result = GK_BlockManager::add_block($ip, $reason, $block_type);

        if ($result) {
            $block_type_names = [
                'manual' => '수동',
                'auto_login' => '자동(로그인)',
                'auto_spam' => '자동(스팸)',
                'auto_abuse' => '자동(악성행위)',
                'auto_regex' => '자동(정규식)',
                'auto_behavior' => '자동(행위)',
                'auto_multiuser' => '자동(다중계정)',
                'auto_useragent' => '자동(User-Agent)'
            ];
            $type_name = $block_type_names[$block_type] ?? '수동';

            // 수동 차단인 경우 그누보드 설정도 동기화
            if ($block_type === 'manual') {
                GK_BlockManager::syncToGnuboard();
            }

            return ['success' => true, 'message' => "IP 차단이 추가되었습니다. ({$type_name})"];
        } else {
            return ['success' => false, 'message' => 'IP 차단 추가에 실패했습니다.'];
        }
    }

    /**
     * 스팸 탐지 시스템용 자동 IP 차단
     */
    public function addAutoIPBlock($ip, $reason, $block_type = 'auto_spam'): array
    {
        return $this->addIPBlock($ip, $reason, $block_type);
    }

    /**
     * IP 차단 제거
     */
    public function removeIPBlock($ip): array
    {
        if (empty($ip)) {
            return ['success' => false, 'message' => 'IP 주소를 입력해주세요.'];
        }

        // GK_BlockManager를 사용하여 IP 차단 제거
        $result = GK_BlockManager::remove_block($ip);

        if ($result) {
            // 그누보드 설정도 동기화
            GK_BlockManager::syncToGnuboard();
            
            return ['success' => true, 'message' => 'IP 차단이 제거되었습니다.'];
        } else {
            return ['success' => false, 'message' => '해당 IP는 차단 목록에 없습니다.'];
        }
    }

    /**
     * 현재 차단된 IP 목록 가져오기
     */
    public function getBlockedIPs(): array
    {
        $result = [];

        // 차단 유형 이름 매핑
        $block_type_names = [
            'manual' => '수동',
            'auto_login' => '자동(로그인)',
            'auto_spam' => '자동(스팸)',
            'auto_abuse' => '자동(악성행위)',
            'auto_regex' => '자동(정규식)',
            'auto_behavior' => '자동(행위)',
            'auto_multiuser' => '자동(다중계정)',
            'auto_useragent' => '자동(User-Agent)'
        ];

        // 차단된 IP 목록 (security_ip_block 테이블)
        $sql = "SELECT sb_ip, sb_reason, sb_block_type, sb_hit_count, sb_datetime
                FROM " . GK_SECURITY_IP_BLOCK_TABLE . "
                WHERE sb_status = 'active'
                ORDER BY sb_datetime DESC";
        $query_result = sql_query($sql);

        if ($query_result) {
            while ($row = sql_fetch_array($query_result)) {
                $block_type_display = $block_type_names[$row['sb_block_type']] ?? $row['sb_block_type'];
                $is_auto = strpos($row['sb_block_type'], 'auto_') === 0;

                $result[] = [
                    'ip' => $row['sb_ip'],
                    'reason' => $row['sb_reason'] ?: '사유 없음',
                    'block_type' => $row['sb_block_type'],
                    'block_type_display' => $block_type_display,
                    'is_auto' => $is_auto,
                    'hit_count' => (int)$row['sb_hit_count'],
                    'created_at' => $row['sb_datetime'],
                    'is_localhost' => GK_BlockManager::is_localhost_ip($row['sb_ip'])
                ];
            }
        }

        return $result;
    }

    /**
     * 예외 IP 추가
     */
    public function addWhitelistIP($ip, $memo = ''): array
    {
        if (empty($ip)) {
            return ['success' => false, 'message' => 'IP 주소를 입력해주세요.'];
        }

        // 중복 체크
        $existing = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_ip = '" . sql_real_escape_string($ip) . "'");
        if ($existing && $existing['cnt'] > 0) {
            return ['success' => false, 'message' => '이미 등록된 예외 IP입니다.'];
        }

        $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_ip_whitelist (sw_ip, sw_memo, sw_datetime) VALUES (
            '" . sql_real_escape_string($ip) . "',
            '" . sql_real_escape_string($memo) . "',
            NOW()
        )";

        $result = sql_query($sql);

        if ($result) {
            // 그누보드 설정도 동기화
            GK_BlockManager::syncToGnuboard();
            
            return ['success' => true, 'message' => '예외 IP가 추가되었습니다.'];
        } else {
            return ['success' => false, 'message' => '예외 IP 추가에 실패했습니다.'];
        }
    }

    /**
     * 예외 IP 제거
     */
    public function removeWhitelistIP($id): array
    {
        if (empty($id) || !is_numeric($id)) {
            return ['success' => false, 'message' => '잘못된 요청입니다.'];
        }

        $sql = "DELETE FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_id = " . intval($id);
        $result = sql_query($sql);

        if ($result) {
            // 그누보드 설정도 동기화
            GK_BlockManager::syncToGnuboard();
            
            return ['success' => true, 'message' => '예외 IP가 제거되었습니다.'];
        } else {
            return ['success' => false, 'message' => '예외 IP 제거에 실패했습니다.'];
        }
    }

    /**
     * 예외 IP 목록 가져오기
     */
    public function getWhitelistIPs(): array
    {
        $sql = "SELECT * FROM " . G5_TABLE_PREFIX . "security_ip_whitelist ORDER BY sw_datetime DESC";
        $result = sql_query($sql);

        $whitelist = [];
        while ($row = sql_fetch_array($result)) {
            $whitelist[] = $row;
        }

        return $whitelist;
    }

    /**
     * 해외 IP 차단 설정 토글
     */
    public function toggleForeignBlock(bool $enabled): array
    {
        $enabledStr = $enabled ? '1' : '0';

        if (gk_set_config('foreign_block_enabled', $enabledStr)) {
            $message = $enabled ? '해외 IP 차단이 활성화되었습니다.' : '해외 IP 차단이 비활성화되었습니다.';
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => '설정 변경에 실패했습니다.'];
        }
    }

    /**
     * 관리자 IP 보호 확인
     */
    private function isAdminIPProtected($ip): bool
    {
        if ($ip === $this->current_admin_ip) {
            return true;
        }

        // 와일드카드 체크 (그누보드 방식)
        if (strpos($ip, '+') !== false) {
            $pattern = str_replace(".", "\.", $ip);
            $pattern = str_replace("+", "[0-9\.]+", $pattern);
            $pat = "/^{$pattern}$/";
            if (preg_match($pat, $this->current_admin_ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 차단 사유 저장
     */
    private function saveBlockReason($ip, $reason): void
    {
        // 간단한 key-value 형태로 config 테이블에 저장
        $key = 'block_reason_' . md5($ip);
        gk_set_config($key, $reason);
    }

    /**
     * 차단 사유 가져오기
     */
    private function getBlockReason($ip): string
    {
        $key = 'block_reason_' . md5($ip);
        return gk_get_config($key, '');
    }

    /**
     * 차단 사유 제거
     */
    private function removeBlockReason($ip): void
    {
        $key = 'block_reason_' . md5($ip);
        gk_set_config($key, null); // null로 설정하여 제거
    }

    /**
     * 국가별 차단 추가
     */
    public function addCountryBlock($countryCode, $countryName, $countryFlag): array
    {
        if (empty($countryCode) || empty($countryName)) {
            return ['success' => false, 'message' => '국가 코드와 국가명을 입력해주세요.'];
        }

        // 중복 체크
        $existing = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_country_block WHERE cb_country_code = '" . sql_real_escape_string($countryCode) . "'");
        if ($existing && $existing['cnt'] > 0) {
            return ['success' => false, 'message' => '이미 차단된 국가입니다.'];
        }

        // 테이블이 없으면 생성
        $this->createCountryBlockTable();

        $sql = "INSERT INTO " . G5_TABLE_PREFIX . "security_country_block (cb_country_code, cb_country_name, cb_country_flag, cb_datetime) VALUES (
            '" . sql_real_escape_string($countryCode) . "',
            '" . sql_real_escape_string($countryName) . "',
            '" . sql_real_escape_string($countryFlag) . "',
            NOW()
        )";

        if (sql_query($sql)) {
            return ['success' => true, 'message' => $countryFlag . ' ' . $countryName . ' 국가가 차단되었습니다.'];
        } else {
            return ['success' => false, 'message' => '국가 차단 추가에 실패했습니다.'];
        }
    }

    /**
     * 국가별 차단 제거
     */
    public function removeCountryBlock($countryCode): array
    {
        if (empty($countryCode)) {
            return ['success' => false, 'message' => '국가 코드를 입력해주세요.'];
        }

        $sql = "DELETE FROM " . G5_TABLE_PREFIX . "security_country_block WHERE cb_country_code = '" . sql_real_escape_string($countryCode) . "'";
        $result = sql_query($sql);

        if ($result) {
            return ['success' => true, 'message' => '국가 차단이 해제되었습니다.'];
        } else {
            return ['success' => false, 'message' => '해당 국가는 차단 목록에 없습니다.'];
        }
    }

    /**
     * 차단된 국가 목록 가져오기
     */
    public function getBlockedCountries(): array
    {
        // 테이블이 없으면 빈 배열 반환
        if (!$this->tableExists(G5_TABLE_PREFIX . 'security_country_block')) {
            return [];
        }

        $sql = "SELECT * FROM " . G5_TABLE_PREFIX . "security_country_block ORDER BY cb_datetime DESC";
        $result = sql_query($sql);

        $countries = [];
        while ($row = sql_fetch_array($result)) {
            $countryCode = $row['cb_country_code'];
            $ipInfo = $this->getCountryIPInfo($countryCode);
            
            $countries[] = [
                'code' => $countryCode,
                'name' => $row['cb_country_name'],
                'flag' => $row['cb_country_flag'],
                'created_at' => $row['cb_datetime'],
                'ip_ranges_count' => $ipInfo['count'],
                'sample_ranges' => $ipInfo['samples']
            ];
        }

        return $countries;
    }

    /**
     * 국가별 차단 테이블 생성
     */
    private function createCountryBlockTable(): void
    {
        if ($this->tableExists(G5_TABLE_PREFIX . 'security_country_block')) {
            return;
        }

        $sql = "CREATE TABLE " . G5_TABLE_PREFIX . "security_country_block (
            cb_id int(11) NOT NULL AUTO_INCREMENT,
            cb_country_code varchar(2) NOT NULL,
            cb_country_name varchar(100) NOT NULL,
            cb_country_flag varchar(10) NOT NULL,
            cb_datetime datetime NOT NULL,
            PRIMARY KEY (cb_id),
            UNIQUE KEY cb_country_code (cb_country_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        sql_query($sql);
    }

    /**
     * 테이블 존재 여부 확인
     */
    private function tableExists($tableName): bool
    {
        $sql = "SHOW TABLES LIKE '" . sql_real_escape_string($tableName) . "'";
        $result = sql_query($sql);
        return sql_num_rows($result) > 0;
    }

    /**
     * 국가별 IP 정보 가져오기
     */
    private function getCountryIPInfo($countryCode): array
    {
        // 국가별 대표적인 IP 대역 정보 (실제로는 GeoIP 데이터베이스나 외부 API를 사용)
        $countryIPRanges = [
            'CN' => [
                'count' => 8540,
                'samples' => ['1.0.1.0/24', '1.0.2.0/23', '1.0.8.0/21', '1.0.32.0/19', '1.1.0.0/16', '14.0.12.0/22']
            ],
            'RU' => [
                'count' => 6420,
                'samples' => ['5.8.8.0/21', '5.18.96.0/19', '5.34.176.0/21', '5.39.0.0/16', '5.44.160.0/19']
            ],
            'US' => [
                'count' => 12850,
                'samples' => ['3.0.0.0/15', '4.0.0.0/9', '8.0.0.0/7', '11.0.0.0/8', '12.0.0.0/6', '16.0.0.0/4']
            ],
            'JP' => [
                'count' => 3240,
                'samples' => ['1.0.16.0/20', '1.1.64.0/18', '14.8.0.0/13', '27.84.0.0/14', '27.88.0.0/13']
            ],
            'IN' => [
                'count' => 4680,
                'samples' => ['14.96.0.0/11', '14.128.0.0/12', '14.192.0.0/12', '27.34.0.0/15', '27.50.0.0/15']
            ],
            'VN' => [
                'count' => 1850,
                'samples' => ['14.160.0.0/11', '27.68.0.0/14', '27.72.0.0/13', '42.112.0.0/13', '42.118.0.0/15']
            ],
            'TH' => [
                'count' => 2120,
                'samples' => ['1.46.0.0/15', '14.207.0.0/16', '27.130.0.0/15', '49.48.0.0/12', '58.8.0.0/13']
            ],
            'PH' => [
                'count' => 1640,
                'samples' => ['14.192.212.0/22', '27.109.0.0/16', '49.144.0.0/13', '110.54.128.0/17', '112.198.0.0/15']
            ],
            'ID' => [
                'count' => 2380,
                'samples' => ['36.64.0.0/11', '36.66.0.0/15', '36.68.0.0/14', '103.10.0.0/15', '103.23.0.0/16']
            ],
            'MY' => [
                'count' => 1290,
                'samples' => ['14.192.128.0/18', '27.109.32.0/19', '103.10.28.0/22', '103.12.160.0/19', '115.164.0.0/14']
            ],
            'SG' => [
                'count' => 890,
                'samples' => ['8.128.0.0/9', '27.125.128.0/17', '103.1.60.0/22', '103.16.60.0/22', '116.88.0.0/13']
            ],
            'TR' => [
                'count' => 3120,
                'samples' => ['5.2.64.0/18', '31.7.0.0/16', '31.145.0.0/16', '46.1.0.0/16', '78.160.0.0/11']
            ]
        ];

        return $countryIPRanges[$countryCode] ?? ['count' => 0, 'samples' => []];
    }
}