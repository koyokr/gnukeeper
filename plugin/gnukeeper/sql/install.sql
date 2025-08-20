-- ========================================
-- GnuKeeper Security Plugin Installation SQL
-- gnuboard5 보안 플러그인 통합 설치 스크립트
-- ========================================

-- ========================================
-- 1. IP 차단 관리 테이블
-- ========================================

DROP TABLE IF EXISTS `{PREFIX}security_ip_block`;
CREATE TABLE `{PREFIX}security_ip_block` (
  `sb_id` int(11) NOT NULL AUTO_INCREMENT,
  `sb_ip` varchar(45) NOT NULL DEFAULT '' COMMENT 'IP 주소 또는 CIDR',
  `sb_start_ip` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '시작 IP (숫자형)',
  `sb_end_ip` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '끝 IP (숫자형)',
  `sb_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '차단 사유',
  `sb_block_type` enum('manual','auto_login','auto_spam','auto_abuse','auto_regex','auto_behavior','auto_multiuser','auto_useragent') NOT NULL DEFAULT 'manual' COMMENT '차단 유형',
  `sb_hit_count` int(11) NOT NULL DEFAULT 0 COMMENT '차단 적중 횟수',
  `sb_status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT '차단 상태',
  `sb_datetime` datetime NOT NULL COMMENT '등록일시',
  PRIMARY KEY (`sb_id`),
  UNIQUE KEY `unique_ip_range` (`sb_start_ip`,`sb_end_ip`),
  KEY `idx_ip_range` (`sb_start_ip`,`sb_end_ip`),
  KEY `idx_status` (`sb_status`),
  KEY `idx_datetime` (`sb_datetime`),
  KEY `idx_block_type` (`sb_block_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='IP 차단 관리';

-- ========================================
-- 2. IP 차단 로그
-- ========================================

DROP TABLE IF EXISTS `{PREFIX}security_ip_log`;
CREATE TABLE `{PREFIX}security_ip_log` (
  `sl_id` int(11) NOT NULL AUTO_INCREMENT,
  `sl_ip` varchar(45) NOT NULL DEFAULT '' COMMENT '차단된 IP',
  `sl_datetime` datetime NOT NULL COMMENT '차단 시각',
  `sl_url` varchar(500) DEFAULT NULL COMMENT '요청 URL',
  `sl_user_agent` varchar(500) DEFAULT NULL COMMENT 'User Agent',
  `sl_block_reason` varchar(255) DEFAULT NULL COMMENT '차단 사유',
  PRIMARY KEY (`sl_id`),
  KEY `idx_ip` (`sl_ip`),
  KEY `idx_datetime` (`sl_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='IP 차단 로그';

-- ========================================
-- 3. 예외 IP (화이트리스트)
-- ========================================

DROP TABLE IF EXISTS `{PREFIX}security_ip_whitelist`;
CREATE TABLE `{PREFIX}security_ip_whitelist` (
  `sw_id` int(11) NOT NULL AUTO_INCREMENT,
  `sw_ip` varchar(45) NOT NULL DEFAULT '' COMMENT 'IP 주소 또는 CIDR',
  `sw_memo` varchar(255) DEFAULT NULL COMMENT '메모',
  `sw_datetime` datetime NOT NULL COMMENT '등록일시',
  PRIMARY KEY (`sw_id`),
  UNIQUE KEY `unique_ip` (`sw_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='예외 IP';

-- ========================================
-- 4. 로그인 실패 기록
-- ========================================

DROP TABLE IF EXISTS `{PREFIX}security_login_fail`;
CREATE TABLE `{PREFIX}security_login_fail` (
  `slf_id` int(11) NOT NULL AUTO_INCREMENT,
  `slf_ip` varchar(45) NOT NULL DEFAULT '' COMMENT 'IP 주소',
  `slf_mb_id` varchar(20) NOT NULL DEFAULT '' COMMENT '시도한 회원ID',
  `slf_datetime` datetime NOT NULL COMMENT '실패 시간',
  `slf_url` varchar(500) DEFAULT NULL COMMENT '요청 URL',
  `slf_user_agent` varchar(500) DEFAULT NULL COMMENT 'User Agent',
  PRIMARY KEY (`slf_id`),
  KEY `idx_ip_datetime` (`slf_ip`, `slf_datetime`),
  KEY `idx_datetime` (`slf_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='로그인 실패 기록';

-- ========================================
-- 5. 회원가입 시도 로그
-- ========================================

CREATE TABLE IF NOT EXISTS `{PREFIX}security_register_log` (
  `srl_id` int NOT NULL AUTO_INCREMENT,
  `srl_ip` varchar(45) NOT NULL COMMENT 'IP 주소',
  `srl_mb_id` varchar(100) NOT NULL COMMENT '회원 아이디',
  `srl_mb_email` varchar(255) NOT NULL COMMENT '회원 이메일',
  `srl_user_agent` varchar(500) NOT NULL COMMENT 'User-Agent',
  `srl_datetime` datetime NOT NULL COMMENT '가입 시간',
  `srl_status` enum('success','failed') NOT NULL DEFAULT 'success' COMMENT '가입 상태',
  PRIMARY KEY (`srl_id`),
  KEY `idx_ip_datetime` (`srl_ip`, `srl_datetime`),
  KEY `idx_datetime` (`srl_datetime`),
  KEY `idx_mb_id` (`srl_mb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='회원가입 시도 로그';

-- ========================================
-- 6. 로그인 성공 로그
-- ========================================

CREATE TABLE IF NOT EXISTS `{PREFIX}security_login_success_log` (
  `sls_id` int NOT NULL AUTO_INCREMENT,
  `sls_ip` varchar(45) NOT NULL COMMENT 'IP 주소',
  `sls_mb_id` varchar(100) NOT NULL COMMENT '회원 아이디',
  `sls_user_agent` varchar(500) NOT NULL COMMENT 'User-Agent',
  `sls_datetime` datetime NOT NULL COMMENT '로그인 시간',
  PRIMARY KEY (`sls_id`),
  KEY `idx_ip_datetime` (`sls_ip`, `sls_datetime`),
  KEY `idx_datetime` (`sls_datetime`),
  KEY `idx_mb_id` (`sls_mb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='로그인 성공 로그';

-- ========================================
-- 7. 404 접속 로그
-- ========================================

CREATE TABLE IF NOT EXISTS `{PREFIX}security_404_log` (
  `sl4_id` int NOT NULL AUTO_INCREMENT,
  `sl4_ip` varchar(45) NOT NULL COMMENT 'IP 주소',
  `sl4_url` varchar(500) NOT NULL COMMENT '요청된 URL',
  `sl4_user_agent` varchar(500) NOT NULL COMMENT 'User-Agent',
  `sl4_referer` varchar(500) NOT NULL DEFAULT '' COMMENT 'Referer',
  `sl4_datetime` datetime NOT NULL COMMENT '접속 시간',
  PRIMARY KEY (`sl4_id`),
  KEY `idx_ip_datetime` (`sl4_ip`, `sl4_datetime`),
  KEY `idx_datetime` (`sl4_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='404 접속 로그';

-- ========================================
-- 8. 레퍼러 검증 로그
-- ========================================

CREATE TABLE IF NOT EXISTS `{PREFIX}security_referer_log` (
  `srl_id` int NOT NULL AUTO_INCREMENT,
  `srl_ip` varchar(45) NOT NULL COMMENT 'IP 주소',
  `srl_url` varchar(500) NOT NULL COMMENT '요청된 URL',
  `srl_expected_referer` varchar(500) NOT NULL COMMENT '예상 레퍼러',
  `srl_actual_referer` varchar(500) NOT NULL DEFAULT '' COMMENT '실제 레퍼러',
  `srl_user_agent` varchar(500) NOT NULL COMMENT 'User-Agent',
  `srl_datetime` datetime NOT NULL COMMENT '접속 시간',
  PRIMARY KEY (`srl_id`),
  KEY `idx_ip_datetime` (`srl_ip`, `srl_datetime`),
  KEY `idx_datetime` (`srl_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='레퍼러 검증 로그';

-- ========================================
-- 9. 정규식 기반 스팸 키워드
-- ========================================

CREATE TABLE IF NOT EXISTS `{PREFIX}security_regex_spam` (
  `srs_id` int NOT NULL AUTO_INCREMENT,
  `srs_name` varchar(100) NOT NULL COMMENT '규칙 이름',
  `srs_pattern` varchar(1000) NOT NULL COMMENT '정규식 패턴',
  `srs_action` enum('block','ghost','delete') NOT NULL DEFAULT 'block' COMMENT '차단 행동',
  `srs_target` set('title','content','name','email','comment') NOT NULL DEFAULT 'content' COMMENT '검사 대상',
  `srs_case_sensitive` tinyint(1) NOT NULL DEFAULT 0 COMMENT '대소문자 구분',
  `srs_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성화 여부',
  `srs_hit_count` int NOT NULL DEFAULT 0 COMMENT '매칭 횟수',
  `srs_priority` int NOT NULL DEFAULT 10 COMMENT '우선순위',
  `srs_description` text COMMENT '설명',
  `srs_datetime` datetime NOT NULL COMMENT '등록 시간',
  `srs_update_datetime` datetime DEFAULT NULL COMMENT '수정 시간',
  PRIMARY KEY (`srs_id`),
  KEY `idx_enabled_priority` (`srs_enabled`, `srs_priority`),
  KEY `idx_hit_count` (`srs_hit_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='정규식 기반 스팸 키워드';

-- ========================================
-- 10. 스팸 탐지 로그
-- ========================================

CREATE TABLE IF NOT EXISTS `{PREFIX}security_regex_spam_log` (
  `srsl_id` int NOT NULL AUTO_INCREMENT,
  `srsl_srs_id` int NOT NULL COMMENT '매칭된 규칙 ID',
  `srsl_ip` varchar(45) NOT NULL COMMENT 'IP 주소',
  `srsl_mb_id` varchar(100) DEFAULT NULL COMMENT '회원 아이디',
  `srsl_target_type` enum('board_write','board_comment','member_register','memo') NOT NULL COMMENT '대상 유형',
  `srsl_bo_table` varchar(20) DEFAULT NULL COMMENT '게시판 테이블',
  `srsl_wr_id` int DEFAULT NULL COMMENT '게시글 ID',
  `srsl_matched_text` text COMMENT '매칭된 텍스트',
  `srsl_full_content` longtext COMMENT '전체 내용',
  `srsl_action_taken` enum('blocked','ghosted','deleted','logged') NOT NULL COMMENT '취한 조치',
  `srsl_user_agent` varchar(500) DEFAULT NULL COMMENT 'User-Agent',
  `srsl_datetime` datetime NOT NULL COMMENT '탐지 시간',
  PRIMARY KEY (`srsl_id`),
  KEY `idx_rule_id` (`srsl_srs_id`),
  KEY `idx_ip_datetime` (`srsl_ip`, `srsl_datetime`),
  KEY `idx_datetime` (`srsl_datetime`),
  KEY `idx_target_type` (`srsl_target_type`),
  FOREIGN KEY (`srsl_srs_id`) REFERENCES `{PREFIX}security_regex_spam` (`srs_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='정규식 스팸 탐지 로그';

-- ========================================
-- 11. 보안 설정 테이블
-- ========================================

DROP TABLE IF EXISTS `{PREFIX}security_config`;
CREATE TABLE `{PREFIX}security_config` (
  `sc_key` varchar(50) NOT NULL,
  `sc_value` text,
  `sc_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`sc_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='보안 설정';

-- ========================================
-- 12. 기본 설정값 추가
-- ========================================

-- IP 차단 기능 설정
INSERT IGNORE INTO `{PREFIX}security_config` (`sc_key`, `sc_value`, `sc_datetime`) VALUES
('ip_block_enabled', '1', NOW()),
('disable_gnuboard_ip_block', '1', NOW());

-- 로그인 실패 차단 설정
INSERT IGNORE INTO `{PREFIX}security_config` (`sc_key`, `sc_value`, `sc_datetime`) VALUES
('login_block_enabled', '0', NOW()),
('login_attempt_limit', '5', NOW()),
('login_attempt_window', '300', NOW()),
('auto_block_duration', '600', NOW());

-- User-Agent 차단 설정
INSERT IGNORE INTO `{PREFIX}security_config` (`sc_key`, `sc_value`, `sc_datetime`) VALUES
('useragent_block_enabled', '0', NOW());

-- 다중 계정 설정
INSERT IGNORE INTO `{PREFIX}security_config` (`sc_key`, `sc_value`, `sc_datetime`) VALUES
('multiuser_register_enabled', '0', NOW()),
('multiuser_register_limit', '3', NOW()),
('multiuser_register_window', '86400', NOW()),
('multiuser_register_block_duration', '3600', NOW()),
('multiuser_login_enabled', '0', NOW()),
('multiuser_login_limit', '5', NOW()),
('multiuser_login_window', '86400', NOW()),
('multiuser_login_block_duration', '1800', NOW());

-- 이상 행위 설정
INSERT IGNORE INTO `{PREFIX}security_config` (`sc_key`, `sc_value`, `sc_datetime`) VALUES
('behavior_404_enabled', '0', NOW()),
('behavior_404_limit', '10', NOW()),
('behavior_404_window', '300', NOW()),
('behavior_404_block_duration', '1800', NOW()),
('behavior_referer_enabled', '0', NOW()),
('behavior_referer_block_duration', '3600', NOW());

-- 정규식 스팸 차단 설정
INSERT IGNORE INTO `{PREFIX}security_config` (`sc_key`, `sc_value`, `sc_datetime`) VALUES
('regex_spam_enabled', '0', NOW()),
('regex_spam_auto_block', '1', NOW()),
('regex_spam_block_duration', '3600', NOW()),
('regex_spam_ghost_mode', '0', NOW()),
('regex_spam_check_title', '1', NOW()),
('regex_spam_check_content', '1', NOW()),
('regex_spam_check_comment', '1', NOW()),
('regex_spam_check_name', '0', NOW()),
('regex_spam_check_email', '0', NOW());

-- 스팸 콘텐츠 탐지 설정
INSERT IGNORE INTO `{PREFIX}security_config` (`sc_key`, `sc_value`, `sc_datetime`) VALUES
('spam_content_enabled', '0', NOW()),
('spam_content_auto_block', '1', NOW()),
('spam_content_block_duration', '3600', NOW()),
('spam_content_min_keywords', '3', NOW()),
('spam_content_min_score', '7', NOW()),
('spam_content_high_risk_score', '12', NOW()),
('spam_content_admin_approval', '1', NOW()),
('spam_content_whitelist_admin', '1', NOW()),
('spam_content_whitelist_trusted', '1', NOW());

-- ========================================
-- 13. 기본 정규식 스팸 규칙 추가
-- ========================================

-- ========================================
-- 스팸 콘텐츠 키워드 테이블
-- ========================================

CREATE TABLE IF NOT EXISTS `{PREFIX}security_spam_keywords` (
  `ssk_id` int NOT NULL AUTO_INCREMENT,
  `ssk_category` varchar(50) NOT NULL COMMENT '스팸 카테고리',
  `ssk_keyword` varchar(100) NOT NULL COMMENT '스팸 키워드',
  `ssk_score` int NOT NULL DEFAULT 1 COMMENT '위험 점수 (1-5)',
  `ssk_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성화 여부',
  `ssk_hit_count` int NOT NULL DEFAULT 0 COMMENT '탐지 횟수',
  `ssk_created` datetime NOT NULL COMMENT '등록일시',
  `ssk_updated` datetime DEFAULT NULL COMMENT '수정일시',
  PRIMARY KEY (`ssk_id`),
  UNIQUE KEY `unique_keyword` (`ssk_keyword`, `ssk_category`),
  KEY `idx_category` (`ssk_category`),
  KEY `idx_enabled` (`ssk_enabled`),
  KEY `idx_score` (`ssk_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='스팸 콘텐츠 키워드';

-- ========================================
-- 스팸 콘텐츠 탐지 로그 테이블
-- ========================================

CREATE TABLE IF NOT EXISTS `{PREFIX}security_spam_content_log` (
  `sscl_id` int NOT NULL AUTO_INCREMENT,
  `sscl_ip` varchar(45) NOT NULL COMMENT 'IP 주소',
  `sscl_mb_id` varchar(100) DEFAULT NULL COMMENT '회원 아이디',
  `sscl_bo_table` varchar(20) DEFAULT NULL COMMENT '게시판 테이블',
  `sscl_wr_id` int DEFAULT NULL COMMENT '게시글 ID',
  `sscl_detected_keywords` text COMMENT '탐지된 키워드 (JSON)',
  `sscl_keyword_count` int NOT NULL DEFAULT 0 COMMENT '탐지된 키워드 수',
  `sscl_total_score` int NOT NULL DEFAULT 0 COMMENT '총 위험 점수',
  `sscl_content_sample` text COMMENT '콘텐츠 샘플 (처음 500자)',
  `sscl_action_taken` enum('blocked','pending','auto_blocked') NOT NULL COMMENT '취한 조치',
  `sscl_auto_blocked` tinyint(1) NOT NULL DEFAULT 0 COMMENT '자동 차단 여부',
  `sscl_user_agent` varchar(500) DEFAULT NULL COMMENT 'User-Agent',
  `sscl_datetime` datetime NOT NULL COMMENT '탐지 시간',
  PRIMARY KEY (`sscl_id`),
  KEY `idx_ip_datetime` (`sscl_ip`, `sscl_datetime`),
  KEY `idx_datetime` (`sscl_datetime`),
  KEY `idx_action` (`sscl_action_taken`),
  KEY `idx_auto_blocked` (`sscl_auto_blocked`),
  KEY `idx_score` (`sscl_total_score`),
  KEY `idx_keyword_count` (`sscl_keyword_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='스팸 콘텐츠 탐지 로그';

-- 기존 정규식 스팸 규칙 (유지)
INSERT IGNORE INTO `{PREFIX}security_regex_spam` (`srs_name`, `srs_pattern`, `srs_action`, `srs_target`, `srs_case_sensitive`, `srs_enabled`, `srs_priority`, `srs_description`, `srs_datetime`) VALUES
('카지노 관련 키워드', '(카지노|바카라|포커|슬롯|베팅|토토|배팅)', 'block', 'title,content', 0, 1, 1, '카지노, 도박 관련 키워드 차단', NOW()),
('성인 광고 키워드', '(야동|av|성인|19금|에로|포르노)', 'block', 'title,content', 0, 1, 2, '성인 콘텐츠 관련 키워드 차단', NOW()),
('약물 판매 키워드', '(마약|대마초|필로폰|히로뽕|엑스터시|LSD)', 'block', 'title,content', 0, 1, 3, '불법 약물 관련 키워드 차단', NOW()),
('금융 사기 키워드', '(급전|소액대출|무담보|즉시|대출|빠른돈)', 'ghost', 'title,content', 0, 1, 4, '금융 사기 관련 키워드 유령 처리', NOW()),
('광고성 전화번호', '010-?\d{4}-?\d{4}', 'block', 'content', 0, 1, 5, '전화번호 형태의 광고성 게시물 차단', NOW()),
('URL 스팸', 'https?:\/\/[^\s<>"]+\.(com|net|org|co\.kr|kr)', 'ghost', 'content', 0, 0, 6, 'URL이 포함된 게시물 유령 처리 (비활성화 상태)', NOW()),
('반복 문자 스팸', '(.)\1{9,}', 'block', 'title,content', 0, 1, 7, '동일 문자 10회 이상 반복 차단', NOW()),
('이메일 주소 스팸', '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', 'ghost', 'content', 0, 0, 8, '이메일 주소가 포함된 게시물 유령 처리 (비활성화 상태)', NOW());

-- ========================================
-- 14. 스팸 콘텐츠 키워드 데이터 삽입
-- ========================================

-- 성인/유흥 광고 필터
INSERT IGNORE INTO `{PREFIX}security_spam_keywords` (`ssk_category`, `ssk_keyword`, `ssk_score`, `ssk_enabled`, `ssk_created`) VALUES
('성인/유흥 광고 필터', '출장', 3, 1, NOW()),
('성인/유흥 광고 필터', '안마', 3, 1, NOW()),
('성인/유흥 광고 필터', '마사지', 3, 1, NOW()),
('성인/유흥 광고 필터', '홈타이', 2, 1, NOW()),
('성인/유흥 광고 필터', '오피', 4, 1, NOW()),
('성인/유흥 광고 필터', '룸사롱', 5, 1, NOW()),
('성인/유흥 광고 필터', '풀싸롱', 5, 1, NOW()),
('성인/유흥 광고 필터', '유흥', 4, 1, NOW()),
('성인/유흥 광고 필터', '애인대행', 5, 1, NOW()),
('성인/유흥 광고 필터', '조건만남', 5, 1, NOW()),
('성인/유흥 광고 필터', '도우미', 3, 1, NOW()),
('성인/유흥 광고 필터', '왁싱샵', 2, 1, NOW()),
('성인/유흥 광고 필터', '모텔', 2, 1, NOW()),
('성인/유흥 광고 필터', '티켓', 3, 1, NOW()),
('성인/유흥 광고 필터', '섹파', 5, 1, NOW()),
('성인/유흥 광고 필터', '흥', 2, 1, NOW()),
('성인/유흥 광고 필터', '대딸', 5, 1, NOW()),
('성인/유흥 광고 필터', '빠른문의', 2, 1, NOW()),
('성인/유흥 광고 필터', '텔레', 2, 1, NOW()),
('성인/유흥 광고 필터', '라인', 1, 1, NOW()),
('성인/유흥 광고 필터', '광고', 2, 1, NOW()),
('성인/유흥 광고 필터', '노래방', 2, 1, NOW()),
('성인/유흥 광고 필터', '문의', 1, 1, NOW()),
('성인/유흥 광고 필터', '100%', 1, 1, NOW());

-- 도박/먹튀 필터
INSERT IGNORE INTO `{PREFIX}security_spam_keywords` (`ssk_category`, `ssk_keyword`, `ssk_score`, `ssk_enabled`, `ssk_created`) VALUES
('도박/먹튀 필터', '먹튀', 5, 1, NOW()),
('도박/먹튀 필터', '토토', 4, 1, NOW()),
('도박/먹튀 필터', '폴리스', 3, 1, NOW()),
('도박/먹튀 필터', '우회', 3, 1, NOW()),
('도박/먹튀 필터', '사이트', 2, 1, NOW()),
('도박/먹튀 필터', '픽스터', 3, 1, NOW()),
('도박/먹튀 필터', '승부식', 3, 1, NOW()),
('도박/먹튀 필터', '배당률', 3, 1, NOW()),
('도박/먹튀 필터', '베팅', 4, 1, NOW()),
('도박/먹튀 필터', '카지노', 5, 1, NOW()),
('도박/먹튀 필터', '바카라', 5, 1, NOW()),
('도박/먹튀 필터', '검증', 3, 1, NOW()),
('도박/먹튀 필터', '인증', 2, 1, NOW()),
('도박/먹튀 필터', '보증', 2, 1, NOW()),
('도박/먹튀 필터', '코드', 2, 1, NOW()),
('도박/먹튀 필터', '놀이터', 3, 1, NOW()),
('도박/먹튀 필터', '픽공유', 3, 1, NOW()),
('도박/먹튀 필터', '파워볼', 3, 1, NOW()),
('도박/먹튀 필터', '조작', 4, 1, NOW()),
('도박/먹튀 필터', '자동', 3, 1, NOW()),
('도박/먹튀 필터', '단폴', 3, 1, NOW()),
('도박/먹튀 필터', '스코어링', 2, 1, NOW()),
('도박/먹튀 필터', '라이브배팅', 4, 1, NOW()),
('도박/먹튀 필터', '지인추천', 2, 1, NOW()),
('도박/먹튀 필터', '전용링크', 2, 1, NOW()),
('도박/먹튀 필터', '텔레', 2, 1, NOW()),
('도박/먹튀 필터', '라인', 1, 1, NOW()),
('도박/먹튀 필터', '광고', 2, 1, NOW()),
('도박/먹튀 필터', '100%', 1, 1, NOW());

-- 성기능/의약품 필터
INSERT IGNORE INTO `{PREFIX}security_spam_keywords` (`ssk_category`, `ssk_keyword`, `ssk_score`, `ssk_enabled`, `ssk_created`) VALUES
('성기능/의약품 필터', '비아그라', 5, 1, NOW()),
('성기능/의약품 필터', '발기', 3, 1, NOW()),
('성기능/의약품 필터', '조루', 3, 1, NOW()),
('성기능/의약품 필터', '사정지연', 3, 1, NOW()),
('성기능/의약품 필터', '정력제', 4, 1, NOW()),
('성기능/의약품 필터', '흥분제', 4, 1, NOW()),
('성기능/의약품 필터', '성욕증진', 4, 1, NOW()),
('성기능/의약품 필터', '자위기구', 3, 1, NOW()),
('성기능/의약품 필터', '오나홀', 3, 1, NOW()),
('성기능/의약품 필터', '스웨디시', 2, 1, NOW()),
('성기능/의약품 필터', '남성강화제', 3, 1, NOW()),
('성기능/의약품 필터', '발기부전', 4, 1, NOW()),
('성기능/의약품 필터', '남성기능', 3, 1, NOW()),
('성기능/의약품 필터', '정품', 2, 1, NOW()),
('성기능/의약품 필터', '정품보장', 2, 1, NOW()),
('성기능/의약품 필터', '처방없이', 4, 1, NOW()),
('성기능/의약품 필터', '약국X', 4, 1, NOW()),
('성기능/의약품 필터', '성기능', 3, 1, NOW()),
('성기능/의약품 필터', '약효', 3, 1, NOW()),
('성기능/의약품 필터', '자위용품', 3, 1, NOW()),
('성기능/의약품 필터', '오르가즘', 3, 1, NOW()),
('성기능/의약품 필터', '1알', 2, 1, NOW()),
('성기능/의약품 필터', '온라인', 1, 1, NOW()),
('성기능/의약품 필터', '광고', 2, 1, NOW()),
('성기능/의약품 필터', '텔레', 2, 1, NOW()),
('성기능/의약품 필터', '라인', 1, 1, NOW()),
('성기능/의약품 필터', '100%', 1, 1, NOW());

-- 온라인 환전/도박 필터
INSERT IGNORE INTO `{PREFIX}security_spam_keywords` (`ssk_category`, `ssk_keyword`, `ssk_score`, `ssk_enabled`, `ssk_created`) VALUES
('온라인 환전/도박 필터', '한게임', 3, 1, NOW()),
('온라인 환전/도박 필터', '포커', 3, 1, NOW()),
('온라인 환전/도박 필터', '고포게임', 4, 1, NOW()),
('온라인 환전/도박 필터', '환전', 4, 1, NOW()),
('온라인 환전/도박 필터', '머니', 3, 1, NOW()),
('온라인 환전/도박 필터', '점수머니', 3, 1, NOW()),
('온라인 환전/도박 필터', '머니거래', 3, 1, NOW()),
('온라인 환전/도박 필터', '아이템거래', 3, 1, NOW()),
('온라인 환전/도박 필터', '환전계좌', 4, 1, NOW()),
('온라인 환전/도박 필터', '충전문의', 3, 1, NOW()),
('온라인 환전/도박 필터', '사설환전', 5, 1, NOW()),
('온라인 환전/도박 필터', '슬롯게임', 3, 1, NOW()),
('온라인 환전/도박 필터', '토큰충전', 3, 1, NOW()),
('온라인 환전/도박 필터', '점수거래', 3, 1, NOW()),
('온라인 환전/도박 필터', '계좌이체', 2, 1, NOW()),
('온라인 환전/도박 필터', '빠른환전', 3, 1, NOW()),
('온라인 환전/도박 필터', '다이사', 3, 1, NOW()),
('온라인 환전/도박 필터', '칩충전', 3, 1, NOW()),
('온라인 환전/도박 필터', '칩판매', 3, 1, NOW()),
('온라인 환전/도박 필터', '100%', 1, 1, NOW());

-- ========================================
-- 15. 스팸 콘텐츠 탐지 테스트 데이터
-- ========================================

-- 스팸 콘텐츠 탐지 로그 테스트 데이터
INSERT IGNORE INTO `{PREFIX}security_spam_content_log` 
(`sscl_ip`, `sscl_mb_id`, `sscl_bo_table`, `sscl_wr_id`, `sscl_detected_keywords`, `sscl_keyword_count`, `sscl_total_score`, `sscl_content_sample`, `sscl_action_taken`, `sscl_auto_blocked`, `sscl_user_agent`, `sscl_datetime`) 
VALUES
-- 고위험 스팸 (자동 차단)
('192.168.1.100', 'spammer01', 'free', 123, '[{"keyword":"비아그라","category":"성기능/의약품 필터","score":5},{"keyword":"처방없이","category":"성기능/의약품 필터","score":4},{"keyword":"정품보장","category":"성기능/의약품 필터","score":2},{"keyword":"텔레","category":"성기능/의약품 필터","score":2}]', 4, 13, '비아그라 정품 처방없이 구매가능! 정품보장 100% 텔레그램으로 연락주세요. 안전하고 빠른 배송으로 만족도 최고입니다.', 'auto_blocked', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NOW() - INTERVAL 2 HOUR),

('211.45.123.78', 'casino_bot', 'notice', 456, '[{"keyword":"바카라","category":"도박/먹튀 필터","score":5},{"keyword":"카지노","category":"도박/먹튀 필터","score":5},{"keyword":"베팅","category":"도박/먹튀 필터","score":4}]', 3, 14, '온라인 바카라와 카지노 게임! 실시간 베팅으로 큰 돈을 벌어보세요. 검증된 사이트에서 안전하게 즐기실 수 있습니다.', 'auto_blocked', 1, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', NOW() - INTERVAL 5 HOUR),

-- 중위험 스팸 (관리자 승인 대기)
('172.16.0.50', 'massage_ad', 'community', 789, '[{"keyword":"안마","category":"성인/유흥 광고 필터","score":3},{"keyword":"마사지","category":"성인/유흥 광고 필터","score":3},{"keyword":"출장","category":"성인/유흥 광고 필터","score":3}]', 3, 9, '전문 안마사의 마사지 서비스! 출장 가능하며 깔끔하고 친절한 서비스를 제공합니다. 예약은 전화로 연락주세요.', 'blocked', 0, 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X)', NOW() - INTERVAL 1 DAY),

('10.0.0.25', 'game_seller', 'free', 321, '[{"keyword":"환전","category":"온라인 환전/도박 필터","score":4},{"keyword":"머니","category":"온라인 환전/도박 필터","score":3},{"keyword":"빠른환전","category":"온라인 환전/도박 필터","score":3}]', 3, 10, '게임 머니 환전 서비스! 빠른환전 보장하며 24시간 언제든지 가능합니다. 안전하고 신뢰할 수 있는 업체입니다.', 'blocked', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NOW() - INTERVAL 8 HOUR),

('203.248.15.67', 'adult_spam', 'gallery', 654, '[{"keyword":"오피","category":"성인/유흥 광고 필터","score":4},{"keyword":"도우미","category":"성인/유흥 광고 필터","score":3}]', 2, 7, '프리미엄 오피 서비스 안내드립니다. 고급 도우미들과 함께하는 특별한 시간을 약속드립니다.', 'blocked', 0, 'Mozilla/5.0 (Android 11; Mobile; rv:92.0)', NOW() - INTERVAL 3 HOUR),

-- 저위험 스팸 (관리자 승인 대기)
('158.247.89.12', 'toto_user', 'sports', 987, '[{"keyword":"토토","category":"도박/먹튀 필터","score":4},{"keyword":"검증","category":"도박/먹튀 필터","score":3}]', 2, 7, '안전한 토토 사이트 추천합니다. 철저한 검증을 거친 믿을만한 곳들만 소개해드려요.', 'pending', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:91.0)', NOW() - INTERVAL 6 HOUR),

('123.123.123.100', 'pharmacy_ad', 'health', 111, '[{"keyword":"정력제","category":"성기능/의약품 필터","score":4},{"keyword":"온라인","category":"성기능/의약품 필터","score":1},{"keyword":"정품","category":"성기능/의약품 필터","score":2}]', 3, 7, '천연 정력제 온라인 판매! 정품만 취급하며 부작용 없는 안전한 제품들로 구성되어 있습니다.', 'pending', 0, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6)', NOW() - INTERVAL 12 HOUR),

-- 최근 탐지 사례들
('61.78.45.123', 'recent_spam1', 'free', 1001, '[{"keyword":"룸사롱","category":"성인/유흥 광고 필터","score":5},{"keyword":"유흥","category":"성인/유흥 광고 필터","score":4}]', 2, 9, '고급 룸사롱에서 최고의 유흥을 경험해보세요. VIP 서비스로 특별한 하루를 만들어드립니다.', 'blocked', 0, 'Mozilla/5.0 (Linux; Android 10; SM-G975F)', NOW() - INTERVAL 30 MINUTE),

('77.88.99.111', 'recent_spam2', 'notice', 1002, '[{"keyword":"먹튀","category":"도박/먹튀 필터","score":5},{"keyword":"사이트","category":"도박/먹튀 필터","score":2},{"keyword":"검증","category":"도박/먹튀 필터","score":3}]', 3, 10, '먹튀 없는 안전한 사이트를 찾고 계신가요? 저희가 직접 검증한 업체들만 소개해드립니다.', 'blocked', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0', NOW() - INTERVAL 15 MINUTE),

('155.201.33.44', 'recent_spam3', 'community', 1003, '[{"keyword":"자위기구","category":"성기능/의약품 필터","score":3},{"keyword":"오나홀","category":"성기능/의약품 필터","score":3},{"keyword":"광고","category":"성기능/의약품 필터","score":2}]', 3, 8, '성인용품 자위기구 할인 판매! 오나홀 신상품 입고했습니다. 이벤트 광고 확인하세요!', 'blocked', 0, 'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X)', NOW() - INTERVAL 5 MINUTE);

-- 스팸 키워드 탐지 횟수 업데이트 (실제 탐지가 있었던 것처럼 시뮬레이션)
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 15 WHERE `ssk_keyword` = '비아그라';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 12 WHERE `ssk_keyword` = '바카라';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 11 WHERE `ssk_keyword` = '카지노';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 8 WHERE `ssk_keyword` = '안마';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 7 WHERE `ssk_keyword` = '마사지';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 6 WHERE `ssk_keyword` = '오피';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 5 WHERE `ssk_keyword` = '토토';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 4 WHERE `ssk_keyword` = '환전';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 3 WHERE `ssk_keyword` = '정력제';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 3 WHERE `ssk_keyword` = '텔레';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 2 WHERE `ssk_keyword` = '라인';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 2 WHERE `ssk_keyword` = '광고';
UPDATE `{PREFIX}security_spam_keywords` SET `ssk_hit_count` = 1 WHERE `ssk_keyword` = '100%';