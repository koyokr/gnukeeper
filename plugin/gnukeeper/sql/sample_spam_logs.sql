-- 스팸 콘텐츠 탐지 로그 예시 데이터
INSERT INTO g5_security_spam_content_log 
(sscl_ip, sscl_mb_id, sscl_bo_table, sscl_wr_id, sscl_detected_keywords, sscl_keyword_count, sscl_total_score, sscl_content_sample, sscl_action_taken, sscl_auto_blocked, sscl_user_agent, sscl_datetime) 
VALUES 
-- 고위험 자동차단 케이스
('192.168.1.100', 'spammer1', 'free', 1234, '[{"keyword":"바카라","category":"도박/먹튀 필터","score":5},{"keyword":"먹튀","category":"도박/먹튀 필터","score":5},{"keyword":"토토","category":"도박/먹튀 필터","score":4}]', 3, 14, '안전한 바카라 사이트 추천! 먹튀 없는 토토 사이트에서 베팅하세요. 100% 보장합니다.', 'auto_blocked', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', NOW() - INTERVAL 2 HOUR),

-- 성인 광고 차단 케이스
('203.245.1.50', 'advertiser2', 'notice', 5678, '[{"keyword":"마사지","category":"성인/유흥 광고 필터","score":3},{"keyword":"출장","category":"성인/유흥 광고 필터","score":3},{"keyword":"텔레","category":"성인/유흥 광고 필터","score":2}]', 3, 8, '서울 강남 출장 마사지 서비스입니다. 텔레그램으로 문의 주세요. 24시간 가능합니다.', 'blocked', 0, 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15', NOW() - INTERVAL 5 HOUR),

-- 의약품 스팸 차단 케이스
('121.78.45.123', NULL, 'board1', 9012, '[{"keyword":"비아그라","category":"성기능/의약품 필터","score":5},{"keyword":"정력제","category":"성기능/의약품 필터","score":4}]', 2, 9, '처방전 없이 비아그라 구입 가능합니다. 천연 정력제도 함께 판매중이에요. 정품 보장합니다.', 'blocked', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0', NOW() - INTERVAL 1 DAY),

-- 환전 스팸 차단 케이스
('175.223.67.89', 'gameuser', 'game', 3456, '[{"keyword":"환전","category":"온라인 환전/도박 필터","score":4},{"keyword":"머니","category":"온라인 환전/도박 필터","score":3},{"keyword":"충전문의","category":"온라인 환전/도박 필터","score":3}]', 3, 10, '한게임 포커 머니 환전해드립니다. 충전문의는 카톡으로 주세요. 빠른 처리 가능합니다.', 'blocked', 0, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', NOW() - INTERVAL 3 HOUR),

-- 성인 서비스 광고 차단 케이스
('58.141.92.156', 'spam_test', 'community', 7890, '[{"keyword":"조건만남","category":"성인/유흥 광고 필터","score":5},{"keyword":"애인대행","category":"성인/유흥 광고 필터","score":5}]', 2, 10, '조건만남 원하시는 분 연락주세요. 애인대행 서비스도 가능합니다. 깔끔하고 예쁜 분들만...', 'blocked', 0, 'Mozilla/5.0 (Android 12; Mobile; rv:102.0) Gecko/102.0 Firefox/102.0', NOW() - INTERVAL 8 HOUR),

-- 카지노 광고 자동차단 케이스
('14.63.178.201', NULL, 'qna', 1122, '[{"keyword":"카지노","category":"도박/먹튀 필터","score":5},{"keyword":"베팅","category":"도박/먹튀 필터","score":4},{"keyword":"검증","category":"도박/먹튀 필터","score":3}]', 3, 12, '검증된 카지노 사이트에서 안전하게 베팅하세요. 신규 가입시 보너스 지급! 먹튀 걱정 없습니다.', 'auto_blocked', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Edge/120.0', NOW() - INTERVAL 30 MINUTE),

-- 저위험 대기 케이스
('110.45.67.89', 'normaluser', 'talk', 2468, '[{"keyword":"광고","category":"성인/유흥 광고 필터","score":2},{"keyword":"문의","category":"성인/유흥 광고 필터","score":1}]', 2, 3, '제품 광고 문의드립니다. 정상적인 비즈니스 제안입니다.', 'pending', 0, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/121.0', NOW() - INTERVAL 1 HOUR);