<?php
require_once './_common.php';

if ($is_admin != 'super') {
    alert('최고관리자만 접근 가능합니다.');
}

// 디버깅 정보 출력 (브라우저에서 확인 가능)
echo '<div style="background:#f0f8ff; border:1px solid #0066cc; padding:15px; margin:10px; font-family:monospace; font-size:12px;">';
echo '<h3 style="color:#0066cc; margin-top:0;">🔧 테이블 생성 진행 상황</h3>';
echo '<p>시작 시간: ' . date('Y-m-d H:i:s') . '</p>';

// 현재 테이블 존재 여부 먼저 확인
try {
    $existing_table = sql_fetch("SHOW TABLES LIKE 'g5_access_control'");
    if ($existing_table) {
        echo '<p style="color:orange;">⚠️ 기존 테이블이 이미 존재합니다. 기존 데이터를 보존합니다.</p>';
    } else {
        echo '<p style="color:blue;">ℹ️ 새로운 테이블을 생성합니다.</p>';
    }
} catch (Exception $e) {
    echo '<p style="color:red;">❌ 테이블 확인 중 오류: ' . $e->getMessage() . '</p>';
}

// 접근 제어 설정 테이블 생성
$sql = "CREATE TABLE IF NOT EXISTS g5_access_control (
    ac_id INT(11) NOT NULL AUTO_INCREMENT,
    ac_page VARCHAR(100) NOT NULL COMMENT '페이지 경로',
    ac_name VARCHAR(100) NOT NULL COMMENT '페이지 이름',
    ac_level INT(2) NOT NULL DEFAULT 1 COMMENT '접근 권한 레벨 (1:방문자, 2:회원, 10:관리자, -1:차단)',
    ac_category VARCHAR(50) NOT NULL COMMENT '카테고리',
    ac_description TEXT COMMENT '설명',
    ac_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ac_id),
    UNIQUE KEY unique_page (ac_page)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='접근 제어 설정'";

// 테이블 생성 실행
try {
    sql_query($sql);
    echo '<p style="color:green;">✅ 테이블 생성/확인 완료</p>';
} catch (Exception $e) {
    echo '<p style="color:red;">❌ 테이블 생성 실패: ' . $e->getMessage() . '</p>';
    echo '</div>';
    exit;
}

// 기본 설정 데이터 삽입
echo '<p>기본 데이터 삽입 시작...</p>';
$default_settings = array(
    // 검색 & 컨텐츠
    array('bbs/search.php', '통합 검색', 1, '검색 & 컨텐츠', '사이트 전체 검색 기능'),
    array('bbs/new.php', '최신글 보기', 1, '검색 & 컨텐츠', '최신 게시글 목록'),
    array('bbs/faq.php', 'FAQ 페이지', 1, '검색 & 컨텐츠', '자주 묻는 질문'),
    array('bbs/content.php', '컨텐츠 보기', 1, '검색 & 컨텐츠', '정적 컨텐츠 페이지'),
    array('bbs/current_connect.php', '현재 접속자', 1, '검색 & 컨텐츠', '현재 접속 중인 사용자 목록'),
    array('bbs/group.php', '그룹', 1, '검색 & 컨텐츠', '게시판 그룹 페이지'),

    // 회원 관련
    array('bbs/register.php', '회원가입', 1, '회원 관련', '신규 회원 가입'),
    array('bbs/password_lost.php', '비밀번호 찾기', 1, '회원 관련', '비밀번호 찾기/재설정'),
    array('bbs/memo.php', '쪽지', 2, '회원 관련', '회원간 쪽지 기능'),
    array('bbs/profile.php', '회원 프로필', 2, '회원 관련', '회원 프로필 보기'),

    // 게시판/설문 관련
    array('bbs/board.php', '게시판', 1, '게시판/설문 관련', '게시판 목록 및 상세보기'),
    array('bbs/download.php', '파일 다운로드', 1, '게시판/설문 관련', '첨부파일 다운로드'),
    array('bbs/scrap.php', '스크랩', 2, '게시판/설문 관련', '게시글 스크랩 기능'),
    array('bbs/poll_result.php', '투표', 1, '게시판/설문 관련', '설문조사 및 투표'),
    array('bbs/qalist.php', 'QA', 2, '게시판/설문 관련', '1:1 문의'),
    array('bbs/qadownload.php', 'QA 파일 다운로드', 2, '게시판/설문 관련', 'QA 첨부파일 다운로드'),
    array('bbs/link.php', '링크', 1, '게시판/설문 관련', '링크 페이지')
);

$inserted_count = 0;
$skipped_count = 0;

foreach ($default_settings as $setting) {
    try {
        // 먼저 해당 페이지가 이미 있는지 확인
        $check_sql = "SELECT COUNT(*) as cnt FROM g5_access_control WHERE ac_page = '{$setting[0]}'";
        $check_result = sql_fetch($check_sql);

        if ($check_result['cnt'] == 0) {
            $sql = "INSERT INTO g5_access_control (ac_page, ac_name, ac_level, ac_category, ac_description)
                    VALUES ('{$setting[0]}', '{$setting[1]}', {$setting[2]}, '{$setting[3]}', '{$setting[4]}')";
            sql_query($sql);
            $inserted_count++;
            echo '<span style="color:green;">✓</span> ' . $setting[1] . ' 추가<br>';
        } else {
            $skipped_count++;
            echo '<span style="color:orange;">⚪</span> ' . $setting[1] . ' 이미 존재함<br>';
        }
    } catch (Exception $e) {
        echo '<span style="color:red;">✗</span> ' . $setting[1] . ' 오류: ' . $e->getMessage() . '<br>';
    }
}

echo '<hr>';
echo '<p style="color:green;"><strong>완료!</strong></p>';
echo '<p>• 신규 추가: ' . $inserted_count . '개</p>';
echo '<p>• 기존 보존: ' . $skipped_count . '개</p>';
echo '<p>완료 시간: ' . date('Y-m-d H:i:s') . '</p>';
echo '</div>';

echo '<div style="background:#d4edda; border:1px solid #c3e6cb; padding:15px; margin:10px; border-radius:5px;">';
echo '<h4 style="color:#155724; margin-top:0;">🎉 설치 완료</h4>';
echo '<p style="color:#155724;">접근 제어 기능이 성공적으로 설치되었습니다!</p>';
echo '<p><a href="access_control.php" style="background:#007bff; color:white; padding:8px 15px; text-decoration:none; border-radius:4px;">접근 제어 관리로 이동</a></p>';
echo '</div>';
?>