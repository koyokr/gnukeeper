<?php
require_once './_common.php';

header('Content-Type: application/json');

if ($is_admin != 'super') {
    echo json_encode(['success' => false, 'message' => '최고관리자만 접근 가능합니다.']);
    exit;
}

try {
    // 기존 설정 모두 삭제
    $sql = "TRUNCATE TABLE g5_access_control";
    sql_query($sql);

    // 초기 기본 설정 삽입
    $default_settings = array(
        // 검색 & 컨텐츠 - 기본적으로 모든 사용자 허용
        array('bbs/search.php', '통합 검색', 1, '검색 & 컨텐츠', '사이트 전체 검색 기능'),
        array('bbs/new.php', '최신글 보기', 1, '검색 & 컨텐츠', '최신 게시글 목록'),
        array('bbs/faq.php', 'FAQ 페이지', 1, '검색 & 컨텐츠', '자주 묻는 질문'),
        array('bbs/content.php', '컨텐츠 보기', 1, '검색 & 컨텐츠', '정적 컨텐츠 페이지'),
        array('bbs/current_connect.php', '현재 접속자', 1, '검색 & 컨텐츠', '현재 접속 중인 사용자 목록'),
        array('bbs/group.php', '그룹', 1, '검색 & 컨텐츠', '게시판 그룹 페이지'),

        // 회원 관련 - 회원가입/비밀번호찾기는 허용, 나머지는 회원만
        array('bbs/register.php', '회원가입', 1, '회원 관련', '신규 회원 가입'),
        array('bbs/password_lost.php', '비밀번호 찾기', 1, '회원 관련', '비밀번호 찾기/재설정'),
        array('bbs/memo.php', '쪽지', 2, '회원 관련', '회원간 쪽지 기능'),
        array('bbs/profile.php', '회원 프로필', 2, '회원 관련', '회원 프로필 보기'),

        // 게시판/설문 관련 - 기본적으로 모든 사용자 허용, 일부만 회원 전용
        array('bbs/board.php', '게시판', 1, '게시판/설문 관련', '게시판 목록 및 상세보기'),
        array('bbs/download.php', '파일 다운로드', 1, '게시판/설문 관련', '첨부파일 다운로드'),
        array('bbs/scrap.php', '스크랩', 2, '게시판/설문 관련', '게시글 스크랩 기능'),
        array('bbs/poll_result.php', '투표', 1, '게시판/설문 관련', '설문조사 및 투표'),
        array('bbs/qalist.php', 'QA', 2, '게시판/설문 관련', '1:1 문의'),
        array('bbs/qadownload.php', 'QA 파일 다운로드', 2, '게시판/설문 관련', 'QA 첨부파일 다운로드'),
        array('bbs/link.php', '링크', 1, '게시판/설문 관련', '링크 페이지')
    );

    foreach ($default_settings as $setting) {
        $sql = "INSERT INTO g5_access_control (ac_page, ac_name, ac_level, ac_category, ac_description)
                VALUES ('{$setting[0]}', '{$setting[1]}', {$setting[2]}, '{$setting[3]}', '{$setting[4]}')";
        sql_query($sql);
    }

    echo json_encode(['success' => true, 'message' => '초기설정으로 복원되었습니다.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '복원 중 오류가 발생했습니다: ' . $e->getMessage()]);
}
?>