-- 스팸 콘텐츠 탐지 테스트 데이터

-- 스팸 콘텐츠 탐지 로그 테스트 데이터
INSERT INTO `g5_security_spam_content_log` 
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
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 15 WHERE `ssk_keyword` = '비아그라';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 12 WHERE `ssk_keyword` = '바카라';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 11 WHERE `ssk_keyword` = '카지노';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 8 WHERE `ssk_keyword` = '안마';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 7 WHERE `ssk_keyword` = '마사지';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 6 WHERE `ssk_keyword` = '오피';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 5 WHERE `ssk_keyword` = '토토';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 4 WHERE `ssk_keyword` = '환전';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 3 WHERE `ssk_keyword` = '정력제';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 3 WHERE `ssk_keyword` = '텔레';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 2 WHERE `ssk_keyword` = '라인';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 2 WHERE `ssk_keyword` = '광고';
UPDATE `g5_security_spam_keywords` SET `ssk_hit_count` = 1 WHERE `ssk_keyword` = '100%';