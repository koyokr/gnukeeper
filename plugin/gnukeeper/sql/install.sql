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

-- ========================================
-- 13. 기본 정규식 스팸 규칙 추가
-- ========================================

INSERT IGNORE INTO `{PREFIX}security_regex_spam` (`srs_name`, `srs_pattern`, `srs_action`, `srs_target`, `srs_case_sensitive`, `srs_enabled`, `srs_priority`, `srs_description`, `srs_datetime`) VALUES
('카지노 관련 키워드', '(카지노|바카라|포커|슬롯|베팅|토토|배팅)', 'block', 'title,content', 0, 1, 1, '카지노, 도박 관련 키워드 차단', NOW()),
('성인 광고 키워드', '(야동|av|성인|19금|에로|포르노)', 'block', 'title,content', 0, 1, 2, '성인 콘텐츠 관련 키워드 차단', NOW()),
('약물 판매 키워드', '(마약|대마초|필로폰|히로뽕|엑스터시|LSD)', 'block', 'title,content', 0, 1, 3, '불법 약물 관련 키워드 차단', NOW()),
('금융 사기 키워드', '(급전|소액대출|무담보|즉시|대출|빠른돈)', 'ghost', 'title,content', 0, 1, 4, '금융 사기 관련 키워드 유령 처리', NOW()),
('광고성 전화번호', '010-?\d{4}-?\d{4}', 'block', 'content', 0, 1, 5, '전화번호 형태의 광고성 게시물 차단', NOW()),
('URL 스팸', 'https?:\/\/[^\s<>"]+\.(com|net|org|co\.kr|kr)', 'ghost', 'content', 0, 0, 6, 'URL이 포함된 게시물 유령 처리 (비활성화 상태)', NOW()),
('반복 문자 스팸', '(.)\1{9,}', 'block', 'title,content', 0, 1, 7, '동일 문자 10회 이상 반복 차단', NOW()),
('이메일 주소 스팸', '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', 'ghost', 'content', 0, 0, 8, '이메일 주소가 포함된 게시물 유령 처리 (비활성화 상태)', NOW());