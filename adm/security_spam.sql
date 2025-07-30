-- --------------------------------------------------------
--
-- 스팸 관리 테이블 생성 스크립트
-- gnuboard5 보안 플러그인용
--

-- 로그인 실패 기록 테이블
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