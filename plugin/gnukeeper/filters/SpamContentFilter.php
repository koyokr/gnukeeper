<?php
if (!defined('_GNUBOARD_')) exit;

class GK_SpamContentFilter {
    private $config;
    private $blockManager;
    private static $keywords_cache = null;
    
    public function __construct() {
        $this->config = GK_Common::get_config();
        $this->blockManager = GK_BlockManager::getInstance();
    }
    
    public function detectSpam($content, $title = '', $options = []) {
        // 스팸 탐지는 항상 실행 (자동차단만 설정에 따라 결정)
        
        // 관리자 또는 신뢰 사용자 예외 처리
        if ($this->isExemptUser($options)) {
            return false;
        }
        
        // 키워드 캐싱
        if (self::$keywords_cache === null) {
            self::$keywords_cache = $this->loadSpamKeywords();
        }
        
        // 콘텐츠 정규화
        $normalizedContent = $this->normalizeContent($content);
        $normalizedTitle = $this->normalizeContent($title);
        $fullText = $normalizedTitle . ' ' . $normalizedContent;
        
        // 스팸 키워드 탐지
        $detectedKeywords = $this->findSpamKeywords($fullText);
        
        if (empty($detectedKeywords)) {
            return false;
        }
        
        // 점수 계산
        $keywordCount = count($detectedKeywords);
        $totalScore = array_sum(array_column($detectedKeywords, 'score'));
        
        // 탐지 기준 확인
        $minKeywords = (int)($this->config['spam_content_min_keywords'] ?? 3);
        $minScore = (int)($this->config['spam_content_min_score'] ?? 7);
        $highRiskScore = (int)($this->config['spam_content_high_risk_score'] ?? 12);
        
        // 스팸 판정
        $isSpam = ($keywordCount >= $minKeywords) || ($totalScore >= $minScore);
        
        if (!$isSpam) {
            return false;
        }
        
        // 조치 결정 (설정 상태에 따라 결정)
        $action = 'detected'; // 기본: 탐지됨 (조치 없음)
        $autoBlock = false;
        
        // 자동 차단 기능이 활성화된 경우에만 실제 차단 조치
        if ($this->config['spam_content_enabled'] == '1') {
            if ($totalScore >= $highRiskScore) {
                $action = 'auto_blocked';
                $autoBlock = true;
            } elseif ($keywordCount >= $minKeywords || $totalScore >= $minScore) {
                $action = 'blocked';
            }
        }
        
        // 로그 기록
        $logData = [
            'detected_keywords' => $detectedKeywords,
            'keyword_count' => $keywordCount,
            'total_score' => $totalScore,
            'content_sample' => substr($content, 0, 500),
            'action_taken' => $action,
            'auto_blocked' => $autoBlock,
            'options' => $options
        ];
        
        $this->logSpamDetection($logData);
        
        // 자동 차단 처리 (설정에 따라 결정)
        if ($autoBlock && $this->config['spam_content_enabled'] == '1') {
            $this->autoBlockIP($options);
        }
        
        return [
            'spam' => true,
            'action' => $action,
            'keyword_count' => $keywordCount,
            'total_score' => $totalScore,
            'detected_keywords' => $detectedKeywords,
            'auto_blocked' => $autoBlock
        ];
    }
    
    private function loadSpamKeywords() {
        global $g5;
        
        $sql = "SELECT ssk_category, ssk_keyword, ssk_score 
                FROM ".G5_TABLE_PREFIX."security_spam_keywords 
                WHERE ssk_enabled = 1 
                ORDER BY ssk_score DESC, ssk_keyword";
        
        $result = sql_query($sql);
        $keywords = [];
        
        while ($row = sql_fetch_array($result)) {
            $keywords[] = [
                'category' => $row['ssk_category'],
                'keyword' => $row['ssk_keyword'],
                'score' => (int)$row['ssk_score']
            ];
        }
        
        return $keywords;
    }
    
    private function normalizeContent($content) {
        if (empty($content)) return '';
        
        // HTML 태그 제거
        $content = strip_tags($content);
        
        // 공백 정규화
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 특수문자로 단어 분할 방지용 정규화
        $normalizations = [
            '/([가-힣])\s*[.\-_\s]*([가-힣])/u' => '$1$2',  // 한글 사이 특수문자 제거
            '/([a-zA-Z])\s*[.\-_\s]*([a-zA-Z])/u' => '$1$2', // 영문 사이 특수문자 제거
            '/ㅂ\s*ㅣ\s*아\s*그\s*라/u' => '비아그라',
            '/맛\s*사\s*지/u' => '마사지',
            '/t\.?e\.?l\.?e/i' => 'tele',
        ];
        
        foreach ($normalizations as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        return trim($content);
    }
    
    private function findSpamKeywords($text) {
        $detectedKeywords = [];
        $textLower = strtolower($text);
        
        foreach (self::$keywords_cache as $keywordData) {
            $keyword = strtolower($keywordData['keyword']);
            
            // 키워드 검색
            if (strpos($textLower, $keyword) !== false) {
                $detectedKeywords[] = [
                    'keyword' => $keywordData['keyword'],
                    'category' => $keywordData['category'],
                    'score' => $keywordData['score']
                ];
                
                // 탐지 횟수 증가
                $this->incrementKeywordHitCount($keywordData['keyword']);
            }
        }
        
        return $detectedKeywords;
    }
    
    private function incrementKeywordHitCount($keyword) {
        global $g5;
        
        $keyword = sql_escape_string($keyword);
        $sql = "UPDATE ".G5_TABLE_PREFIX."security_spam_keywords 
                SET ssk_hit_count = ssk_hit_count + 1 
                WHERE ssk_keyword = '{$keyword}'";
        sql_query($sql);
    }
    
    private function isExemptUser($options) {
        global $member;
        
        // 관리자 예외
        if ($this->config['spam_content_whitelist_admin'] == '1' && 
            isset($member['mb_level']) && $member['mb_level'] >= 10) {
            return true;
        }
        
        // 신뢰 사용자 예외 (레벨 8 이상)
        if ($this->config['spam_content_whitelist_trusted'] == '1' && 
            isset($member['mb_level']) && $member['mb_level'] >= 8) {
            return true;
        }
        
        return false;
    }
    
    private function logSpamDetection($logData) {
        global $g5, $member;
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $mb_id = isset($member['mb_id']) ? $member['mb_id'] : null;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
        $bo_table = isset($logData['options']['bo_table']) ? $logData['options']['bo_table'] : null;
        $wr_id = isset($logData['options']['wr_id']) ? (int)$logData['options']['wr_id'] : null;
        
        $detected_keywords_json = json_encode($logData['detected_keywords'], JSON_UNESCAPED_UNICODE);
        
        $sql = "INSERT INTO ".G5_TABLE_PREFIX."security_spam_content_log 
                (sscl_ip, sscl_mb_id, sscl_bo_table, sscl_wr_id, sscl_detected_keywords, 
                 sscl_keyword_count, sscl_total_score, sscl_content_sample, sscl_action_taken, 
                 sscl_auto_blocked, sscl_user_agent, sscl_datetime) 
                VALUES (
                    '" . sql_escape_string($ip) . "',
                    " . ($mb_id ? "'" . sql_escape_string($mb_id) . "'" : 'NULL') . ",
                    " . ($bo_table ? "'" . sql_escape_string($bo_table) . "'" : 'NULL') . ",
                    " . ($wr_id ? $wr_id : 'NULL') . ",
                    '" . sql_escape_string($detected_keywords_json) . "',
                    {$logData['keyword_count']},
                    {$logData['total_score']},
                    '" . sql_escape_string($logData['content_sample']) . "',
                    '" . sql_escape_string($logData['action_taken']) . "',
                    " . ($logData['auto_blocked'] ? '1' : '0') . ",
                    " . ($user_agent ? "'" . sql_escape_string($user_agent) . "'" : 'NULL') . ",
                    NOW()
                )";
        
        sql_query($sql);
    }
    
    private function autoBlockIP($options) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $reason = '스팸 콘텐츠 자동 차단 (고위험 점수)';
        $duration = (int)($this->config['spam_content_block_duration'] ?? 3600);
        
        GK_BlockManager::add_block($ip, $reason, 'auto_spam');
    }
    
    public function getSpamStats() {
        global $g5;
        
        $stats = [];
        
        // 24시간 내 스팸 탐지 건수
        $sql = "SELECT COUNT(*) as count FROM ".G5_TABLE_PREFIX."security_spam_content_log 
                WHERE sscl_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = sql_fetch($sql);
        $stats['spam_detected_24h'] = (int)$result['count'];
        
        // 자동 차단된 IP 수
        $sql = "SELECT COUNT(DISTINCT sscl_ip) as count FROM ".G5_TABLE_PREFIX."security_spam_content_log 
                WHERE sscl_auto_blocked = 1 AND sscl_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = sql_fetch($sql);
        $stats['auto_blocked_ips'] = (int)$result['count'];
        
        // 가장 많이 탐지된 키워드 Top 5
        $sql = "SELECT ssk_keyword, ssk_hit_count FROM ".G5_TABLE_PREFIX."security_spam_keywords 
                WHERE ssk_enabled = 1 ORDER BY ssk_hit_count DESC LIMIT 5";
        $result = sql_query($sql);
        $stats['top_keywords'] = [];
        while ($row = sql_fetch_array($result)) {
            $stats['top_keywords'][] = $row;
        }
        
        return $stats;
    }
    
    public function getSpamContentLogs($page = 1, $limit = 10, $filters = []) {
        global $g5;
        
        $offset = ($page - 1) * $limit;
        
        // WHERE 조건 구성
        $whereConditions = [];
        
        // 조치 필터
        if (!empty($filters['action'])) {
            $action = sql_escape_string($filters['action']);
            $whereConditions[] = "sscl_action_taken = '{$action}'";
        }
        
        // 위험점수 필터
        if (!empty($filters['score'])) {
            switch ($filters['score']) {
                case 'high':
                    $whereConditions[] = "sscl_total_score >= 12";
                    break;
                case 'medium':
                    $whereConditions[] = "sscl_total_score >= 7 AND sscl_total_score <= 11";
                    break;
                case 'low':
                    $whereConditions[] = "sscl_total_score <= 6";
                    break;
            }
        }
        
        // 기간 필터
        if (!empty($filters['days'])) {
            $days = (int)$filters['days'];
            $whereConditions[] = "sscl_datetime >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        }
        
        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
        
        $sql = "SELECT * FROM ".G5_TABLE_PREFIX."security_spam_content_log 
                {$whereClause}
                ORDER BY sscl_datetime DESC 
                LIMIT {$offset}, {$limit}";
        $result = sql_query($sql);
        
        $logs = [];
        while ($row = sql_fetch_array($result)) {
            $row['detected_keywords'] = json_decode($row['sscl_detected_keywords'], true);
            $logs[] = $row;
        }
        
        // 총 개수 (필터 적용)
        $countSql = "SELECT COUNT(*) as total FROM ".G5_TABLE_PREFIX."security_spam_content_log {$whereClause}";
        $countResult = sql_fetch($countSql);
        $total = (int)$countResult['total'];
        
        return [
            'logs' => $logs,
            'total' => $total,
            'total_pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    public function clearKeywordCache() {
        self::$keywords_cache = null;
    }
    
    public function updateKeywordScore($keyword, $score) {
        global $g5;
        
        $sql = "UPDATE ".G5_TABLE_PREFIX."security_spam_keywords 
                SET ssk_score = " . (int)$score . ", ssk_updated = NOW() 
                WHERE ssk_keyword = '" . sql_escape_string($keyword) . "'";
        
        $result = sql_query($sql);
        if ($result) {
            $this->clearKeywordCache();
        }
        
        return $result;
    }
    
    public function addKeyword($category, $keyword, $score = 3) {
        global $g5;
        
        $sql = "INSERT INTO ".G5_TABLE_PREFIX."security_spam_keywords 
                (ssk_category, ssk_keyword, ssk_score, ssk_enabled, ssk_created) 
                VALUES (
                    '" . sql_escape_string($category) . "',
                    '" . sql_escape_string($keyword) . "',
                    " . (int)$score . ",
                    1,
                    NOW()
                )";
        
        $result = sql_query($sql);
        if ($result) {
            $this->clearKeywordCache();
        }
        
        return $result;
    }
    
    public function removeKeyword($keyword) {
        global $g5;
        
        $sql = "DELETE FROM ".G5_TABLE_PREFIX."security_spam_keywords 
                WHERE ssk_keyword = '" . sql_escape_string($keyword) . "'";
        
        $result = sql_query($sql);
        if ($result) {
            $this->clearKeywordCache();
        }
        
        return $result;
    }
    
    public function resetKeywordsToDefault() {
        global $g5;
        
        // 기본 스팸 키워드 데이터
        $defaultKeywords = [
            // 성인/유흥 광고 필터
            ['성인/유흥 광고 필터', '출장', 3],
            ['성인/유흥 광고 필터', '안마', 3],
            ['성인/유흥 광고 필터', '마사지', 3],
            ['성인/유흥 광고 필터', '홈타이', 2],
            ['성인/유흥 광고 필터', '오피', 4],
            ['성인/유흥 광고 필터', '룸사롱', 5],
            ['성인/유흥 광고 필터', '풀싸롱', 5],
            ['성인/유흥 광고 필터', '유흥', 4],
            ['성인/유흥 광고 필터', '애인대행', 5],
            ['성인/유흥 광고 필터', '조건만남', 5],
            ['성인/유흥 광고 필터', '도우미', 3],
            ['성인/유흥 광고 필터', '왁싱샵', 2],
            ['성인/유흥 광고 필터', '모텔', 2],
            ['성인/유흥 광고 필터', '티켓', 3],
            ['성인/유흥 광고 필터', '섹파', 5],
            ['성인/유흥 광고 필터', '흥', 2],
            ['성인/유흥 광고 필터', '대딸', 5],
            ['성인/유흥 광고 필터', '빠른문의', 2],
            ['성인/유흥 광고 필터', '텔레', 2],
            ['성인/유흥 광고 필터', '라인', 1],
            ['성인/유흥 광고 필터', '광고', 2],
            ['성인/유흥 광고 필터', '노래방', 2],
            ['성인/유흥 광고 필터', '문의', 1],
            ['성인/유흥 광고 필터', '100%', 1],
            
            // 도박/먹튀 필터
            ['도박/먹튀 필터', '먹튀', 5],
            ['도박/먹튀 필터', '토토', 4],
            ['도박/먹튀 필터', '폴리스', 3],
            ['도박/먹튀 필터', '우회', 3],
            ['도박/먹튀 필터', '사이트', 2],
            ['도박/먹튀 필터', '픽스터', 3],
            ['도박/먹튀 필터', '승부식', 3],
            ['도박/먹튀 필터', '배당률', 3],
            ['도박/먹튀 필터', '베팅', 4],
            ['도박/먹튀 필터', '카지노', 5],
            ['도박/먹튀 필터', '바카라', 5],
            ['도박/먹튀 필터', '검증', 3],
            ['도박/먹튀 필터', '인증', 2],
            ['도박/먹튀 필터', '보증', 2],
            ['도박/먹튀 필터', '코드', 2],
            ['도박/먹튀 필터', '놀이터', 3],
            ['도박/먹튀 필터', '픽공유', 3],
            ['도박/먹튀 필터', '파워볼', 3],
            ['도박/먹튀 필터', '조작', 4],
            ['도박/먹튀 필터', '자동', 3],
            ['도박/먹튀 필터', '단폴', 3],
            ['도박/먹튀 필터', '스코어링', 2],
            ['도박/먹튀 필터', '라이브배팅', 4],
            ['도박/먹튀 필터', '지인추천', 2],
            ['도박/먹튀 필터', '전용링크', 2],
            ['도박/먹튀 필터', '텔레', 2],
            ['도박/먹튀 필터', '라인', 1],
            ['도박/먹튀 필터', '광고', 2],
            ['도박/먹튀 필터', '100%', 1],
            
            // 성기능/의약품 필터
            ['성기능/의약품 필터', '비아그라', 5],
            ['성기능/의약품 필터', '발기', 3],
            ['성기능/의약품 필터', '조루', 3],
            ['성기능/의약품 필터', '사정지연', 3],
            ['성기능/의약품 필터', '정력제', 4],
            ['성기능/의약품 필터', '흥분제', 4],
            ['성기능/의약품 필터', '성욕증진', 4],
            ['성기능/의약품 필터', '자위기구', 3],
            ['성기능/의약품 필터', '오나홀', 3],
            ['성기능/의약품 필터', '스웨디시', 2],
            ['성기능/의약품 필터', '남성강화제', 3],
            ['성기능/의약품 필터', '발기부전', 4],
            ['성기능/의약품 필터', '남성기능', 3],
            ['성기능/의약품 필터', '정품', 2],
            ['성기능/의약품 필터', '정품보장', 2],
            ['성기능/의약품 필터', '처방없이', 4],
            ['성기능/의약품 필터', '약국X', 4],
            ['성기능/의약품 필터', '성기능', 3],
            ['성기능/의약품 필터', '약효', 3],
            ['성기능/의약품 필터', '자위용품', 3],
            ['성기능/의약품 필터', '오르가즘', 3],
            ['성기능/의약품 필터', '1알', 2],
            ['성기능/의약품 필터', '온라인', 1],
            ['성기능/의약품 필터', '광고', 2],
            ['성기능/의약품 필터', '텔레', 2],
            ['성기능/의약품 필터', '라인', 1],
            ['성기능/의약품 필터', '100%', 1],
            
            // 온라인 환전/도박 필터
            ['온라인 환전/도박 필터', '한게임', 3],
            ['온라인 환전/도박 필터', '포커', 3],
            ['온라인 환전/도박 필터', '고포게임', 4],
            ['온라인 환전/도박 필터', '환전', 4],
            ['온라인 환전/도박 필터', '머니', 3],
            ['온라인 환전/도박 필터', '점수머니', 3],
            ['온라인 환전/도박 필터', '머니거래', 3],
            ['온라인 환전/도박 필터', '아이템거래', 3],
            ['온라인 환전/도박 필터', '환전계좌', 4],
            ['온라인 환전/도박 필터', '충전문의', 3],
            ['온라인 환전/도박 필터', '사설환전', 5],
            ['온라인 환전/도박 필터', '슬롯게임', 3],
            ['온라인 환전/도박 필터', '토큰충전', 3],
            ['온라인 환전/도박 필터', '점수거래', 3],
            ['온라인 환전/도박 필터', '계좌이체', 2],
            ['온라인 환전/도박 필터', '빠른환전', 3],
            ['온라인 환전/도박 필터', '다이사', 3],
            ['온라인 환전/도박 필터', '칩충전', 3],
            ['온라인 환전/도박 필터', '칩판매', 3],
            ['온라인 환전/도박 필터', '100%', 1]
        ];
        
        try {
            // 트랜잭션 시작
            sql_query("START TRANSACTION");
            
            // 기존 키워드 모두 삭제
            $deleteSql = "DELETE FROM ".G5_TABLE_PREFIX."security_spam_keywords";
            if (!sql_query($deleteSql)) {
                sql_query("ROLLBACK");
                return false;
            }
            
            // 기본 키워드 삽입
            foreach ($defaultKeywords as $keywordData) {
                list($category, $keyword, $score) = $keywordData;
                
                $insertSql = "INSERT INTO ".G5_TABLE_PREFIX."security_spam_keywords 
                              (ssk_category, ssk_keyword, ssk_score, ssk_enabled, ssk_created) 
                              VALUES (
                                  '" . sql_escape_string($category) . "',
                                  '" . sql_escape_string($keyword) . "',
                                  " . (int)$score . ",
                                  1,
                                  NOW()
                              )";
                
                if (!sql_query($insertSql)) {
                    sql_query("ROLLBACK");
                    return false;
                }
            }
            
            // 트랜잭션 커밋
            sql_query("COMMIT");
            
            // 캐시 초기화
            $this->clearKeywordCache();
            
            return true;
            
        } catch (Exception $e) {
            sql_query("ROLLBACK");
            error_log('SpamContentFilter resetKeywordsToDefault error: ' . $e->getMessage());
            return false;
        }
    }
}

// 전역 함수로 쉽게 사용 가능하도록
if (!function_exists('gk_detect_spam_content')) {
    function gk_detect_spam_content($content, $title = '', $options = []) {
        static $detector = null;
        if ($detector === null) {
            $detector = new GK_SpamContentFilter();
        }
        return $detector->detectSpam($content, $title, $options);
    }
}
?>