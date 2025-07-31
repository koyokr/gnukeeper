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
  `sb_block_level` varchar(50) NOT NULL DEFAULT 'access' COMMENT '차단 레벨 (access,login,write,memo 조합)',
  `sb_duration` enum('permanent','temporary') NOT NULL DEFAULT 'permanent',
  `sb_end_datetime` datetime NULL COMMENT '차단 종료일시',
  `sb_hit_count` int(11) NOT NULL DEFAULT 0 COMMENT '차단 적중 횟수',
  `sb_status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `sb_datetime` datetime NOT NULL COMMENT '등록일시',
  PRIMARY KEY (`sb_id`),
  KEY `idx_ip_range` (`sb_start_ip`,`sb_end_ip`),
  KEY `idx_status` (`sb_status`),
  KEY `idx_datetime` (`sb_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='IP 차단 관리';

-- --------------------------------------------------------

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

-- --------------------------------------------------------

DROP TABLE IF EXISTS `{PREFIX}security_ip_whitelist`;
CREATE TABLE `{PREFIX}security_ip_whitelist` (
  `sw_id` int(11) NOT NULL AUTO_INCREMENT,
  `sw_ip` varchar(45) NOT NULL DEFAULT '' COMMENT 'IP 주소 또는 CIDR',
  `sw_memo` varchar(255) DEFAULT NULL COMMENT '메모',
  `sw_datetime` datetime NOT NULL COMMENT '등록일시',
  PRIMARY KEY (`sw_id`),
  UNIQUE KEY `unique_ip` (`sw_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='예외 IP';

-- --------------------------------------------------------

DROP TABLE IF EXISTS `{PREFIX}security_config`;
CREATE TABLE `{PREFIX}security_config` (
  `sc_key` varchar(50) NOT NULL,
  `sc_value` text,
  PRIMARY KEY (`sc_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='보안 설정';

