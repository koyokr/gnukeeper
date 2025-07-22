-- --------------------------------------------------------
--
-- IP 차단 관리 테이블 생성 스크립트
-- gnuboard5 보안 플러그인용
--

DROP TABLE IF EXISTS `{PREFIX}security_ip_block`;
CREATE TABLE `{PREFIX}security_ip_block` (
  `sb_id` int(11) NOT NULL AUTO_INCREMENT,
  `sb_ip` varchar(45) NOT NULL DEFAULT '' COMMENT 'IP 주소 또는 CIDR',
  `sb_start_ip` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '시작 IP (숫자형)',
  `sb_end_ip` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '끝 IP (숫자형)',
  `sb_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '차단 사유',
  `sb_block_type` enum('manual','auto_login','auto_spam','auto_abuse') NOT NULL DEFAULT 'manual',
  `sb_duration` enum('permanent','temporary') NOT NULL DEFAULT 'permanent',
  `sb_end_datetime` datetime NULL COMMENT '차단 종료일시',
  `sb_hit_count` int(11) NOT NULL DEFAULT 0 COMMENT '차단 적중 횟수',
  `sb_status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `sb_datetime` datetime NOT NULL COMMENT '등록일시',
  PRIMARY KEY (`sb_id`),
  KEY `idx_ip_range` (`sb_start_ip`,`sb_end_ip`),
  KEY `idx_status` (`sb_status`),
  KEY `idx_datetime` (`sb_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='IP 차단 관리';

-- --------------------------------------------------------

DROP TABLE IF EXISTS `{PREFIX}security_ip_log`;
CREATE TABLE `{PREFIX}security_ip_log` (
  `sl_id` int(11) NOT NULL AUTO_INCREMENT,
  `sl_ip` varchar(45) NOT NULL DEFAULT '',
  `sl_block_id` int(11) DEFAULT NULL,
  `sl_action` varchar(50) NOT NULL DEFAULT '',
  `sl_reason` varchar(255) NOT NULL DEFAULT '',
  `sl_datetime` datetime NOT NULL,
  PRIMARY KEY (`sl_id`),
  KEY `idx_ip` (`sl_ip`),
  KEY `idx_block_id` (`sl_block_id`),
  KEY `idx_datetime` (`sl_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='IP 차단 로그';

-- --------------------------------------------------------

DROP TABLE IF EXISTS `{PREFIX}security_ip_whitelist`;
CREATE TABLE `{PREFIX}security_ip_whitelist` (
  `sw_id` int(11) NOT NULL AUTO_INCREMENT,
  `sw_ip` varchar(45) NOT NULL DEFAULT '' COMMENT 'IP 주소 또는 CIDR',
  `sw_start_ip` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '시작 IP (숫자형)',
  `sw_end_ip` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '끝 IP (숫자형)',
  `sw_memo` varchar(255) NOT NULL DEFAULT '' COMMENT '메모',
  `sw_datetime` datetime NOT NULL COMMENT '등록일시',
  PRIMARY KEY (`sw_id`),
  KEY `idx_ip_range` (`sw_start_ip`,`sw_end_ip`),
  KEY `idx_datetime` (`sw_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='IP 화이트리스트';

-- --------------------------------------------------------

DROP TABLE IF EXISTS `{PREFIX}security_config`;
CREATE TABLE `{PREFIX}security_config` (
  `sc_id` int(11) NOT NULL AUTO_INCREMENT,
  `sc_key` varchar(100) NOT NULL DEFAULT '',
  `sc_value` text,
  `sc_description` varchar(255) DEFAULT NULL,
  `sc_datetime` datetime NOT NULL,
  PRIMARY KEY (`sc_id`),
  UNIQUE KEY `idx_key` (`sc_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='보안 설정';

-- --------------------------------------------------------
--
-- 기본 설정값 삽입
--

INSERT INTO `{PREFIX}security_config` (`sc_key`, `sc_value`, `sc_description`, `sc_datetime`) VALUES
('ip_block_enabled', '1', 'IP 차단 기능 활성화', NOW()),
('login_attempt_limit', '5', '로그인 시도 제한 횟수', NOW()),
('login_attempt_window', '300', '로그인 시도 제한 시간(초)', NOW()),
('auto_block_duration', '3600', '자동 차단 기간(초)', NOW());