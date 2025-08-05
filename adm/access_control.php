<?php
$sub_menu = '950200';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '접근 제어 관리';
require_once './admin.head.php';

// 디버깅 정보를 담을 배열
$debug_info = array();
$debug_info['timestamp'] = date('Y-m-d H:i:s');
$debug_info['php_version'] = phpversion();

// MySQL 버전 가져오기 (안전하게)
try {
    $mysql_version_result = sql_fetch("SELECT VERSION() as version");
    $debug_info['mysql_version'] = $mysql_version_result['version'];
} catch (Exception $e) {
    $debug_info['mysql_version'] = 'Unknown';
}

// 파일 존재 여부 체크
$required_files = array(
    'create_access_control_table.php' => false,
    'access_control_update.php' => false,
    'access_control_reset.php' => false
);

foreach ($required_files as $file => $exists) {
    $required_files[$file] = file_exists('./'. $file);
    $debug_info['files'][$file] = $required_files[$file] ? 'EXISTS' : 'MISSING';
}

// 관련 파일들 정보
$related_files = array(
    'search.php' => array(),
    'new.php' => array('new_delete.php'),
    'faq.php' => array(),
    'content.php' => array(),
    'current_connect.php' => array(),
    'group.php' => array(),
    'register.php' => array('register_form.php', 'register_form_update.php', 'register_result.php', 'register_email.php'),
    'password_lost.php' => array('password_lost2.php', 'password_reset.php', 'password_reset_update.php'),
    'memo.php' => array('memo_delete.php', 'memo_form.php', 'memo_form_update.php', 'memo_view.php'),
    'profile.php' => array('member_confirm.php', 'member_leave.php', 'point.php'),
    'board.php' => array('list.php', 'view.php', 'write.php', 'write_update.php', 'delete.php', 'good.php', 'move.php', 'download.php', 'view_image.php', 'link.php'),
    'scrap.php' => array('scrap_delete.php', 'scrap_popin.php', 'scrap_popin_update.php'),
    'poll_result.php' => array('poll_update.php', 'poll_etc_update.php'),
    'qalist.php' => array('qaview.php', 'qawrite.php', 'qawrite_update.php', 'qadelete.php', 'qadownload.php')
);

// 데이터베이스 테이블 존재 여부 체크
$debug_info['database']['connection'] = 'OK';
try {
    $table_check = sql_fetch("SHOW TABLES LIKE 'g5_access_control'");
    $debug_info['database']['table_exists'] = $table_check ? 'YES' : 'NO';
    
    if (!$table_check) {
        $debug_info['database']['table_create_attempted'] = 'NO';
        if (file_exists('./create_access_control_table.php')) {
            include_once './create_access_control_table.php';
            $debug_info['database']['table_create_attempted'] = 'YES';
        }
    }
    
    // 접근 제어 설정 불러오기 시도
    $access_controls = array();
    if ($table_check) {
        // qadownload.php, download.php, link.php 독립 항목 제거 (각각 qalist.php, board.php에 포함되므로)
        sql_query("DELETE FROM g5_access_control WHERE ac_page IN ('qadownload.php', 'bbs/qadownload.php', 'download.php', 'bbs/download.php', 'link.php', 'bbs/link.php')", false);
        
        // 기존 설명 업데이트
        $description_updates = array(
            'search.php' => '사이트 내 모든 게시글과 댓글을 검색할 수 있는 통합 검색 기능입니다. 키워드로 원하는 정보를 빠르게 찾을 수 있습니다.',
            'new.php' => '사이트 전체에서 최근에 작성된 게시글과 댓글을 시간순으로 확인할 수 있는 페이지입니다.',
            'faq.php' => '사용자들이 자주 묻는 질문과 그에 대한 답변을 제공하는 고객지원 페이지입니다.',
            'content.php' => '서비스 소개, 개인정보처리방침, 이용약관 같은 정적 컨텐츠를 보여주는 페이지입니다.',
            'current_connect.php' => '현재 사이트에 접속해 있는 사용자 수와 접속자 정보를 실시간으로 확인할 수 있는 페이지입니다.',
            'group.php' => '게시판 그룹별로 분류된 게시판 목록을 확인하고 접근할 수 있는 페이지입니다.',
            'register.php' => '새로운 계정을 생성하여 사이트 회원으로 가입할 수 있는 페이지입니다. 개인정보 입력 및 약관 동의가 포함됩니다.',
            'password_lost.php' => '로그인 비밀번호를 분실했을 때 이메일이나 휴대폰을 통해 비밀번호를 재설정할 수 있는 페이지입니다.',
            'memo.php' => '다른 회원들과 개인적으로 메시지를 주고받을 수 있는 쪽지 기능 페이지입니다.',
            'profile.php' => '회원의 개인정보, 작성글, 댓글 등을 확인하고 수정할 수 있는 마이페이지입니다.',
            'board.php' => '게시글을 작성하고 조회하며 댓글을 달 수 있는 메인 게시판 기능입니다. 파일 첨부 및 다운로드도 포함됩니다.',
            'scrap.php' => '관심 있는 게시글을 개인 스크랩북에 저장하고 관리할 수 있는 기능입니다.',
            'poll_result.php' => '사이트에서 진행하는 설문조사나 투표에 참여하고 결과를 확인할 수 있는 페이지입니다.',
            'qalist.php' => '질문과 답변 형태의 1:1 문의나 고객지원을 위한 전용 게시판입니다.'
        );
        
        foreach ($description_updates as $page => $description) {
            sql_query("UPDATE g5_access_control SET ac_description = '" . sql_escape_string($description) . "' WHERE ac_page = '{$page}' OR ac_page = 'bbs/{$page}'", false);
        }
        
        $sql = "SELECT * FROM g5_access_control ORDER BY ac_category, ac_page";
        $result = sql_query($sql, false); // 에러 출력 비활성화
        if ($result) {
            $debug_info['database']['query_success'] = 'YES';
            
            $row_count = 0;
            while ($row = sql_fetch_array($result)) {
                // bbs/ 접두사 제거
                $row['ac_page'] = str_replace('bbs/', '', $row['ac_page']);
                $access_controls[$row['ac_category']][] = $row;
                $row_count++;
            }
            $debug_info['database']['rows_loaded'] = $row_count;
        } else {
            $debug_info['database']['query_success'] = 'NO - Query failed';
            $access_controls = create_default_access_controls();
        }
    } else {
        $debug_info['database']['query_success'] = 'NO - Table not found';
        // 테이블이 없을 때 기본 데이터 생성
        $access_controls = create_default_access_controls();
    }
    
} catch (Exception $e) {
    $debug_info['database']['error'] = $e->getMessage();
    $access_controls = create_default_access_controls();
}

// 기본 접근 제어 데이터 생성 함수
function create_default_access_controls() {
    return array(
        '검색 & 컨텐츠' => array(
            array('ac_id' => 1, 'ac_page' => 'search.php', 'ac_name' => '통합 검색', 'ac_description' => '사이트 내 모든 게시글과 댓글을 검색할 수 있는 통합 검색 기능입니다. 키워드로 원하는 정보를 빠르게 찾을 수 있습니다.', 'ac_level' => 1, 'ac_category' => '검색 & 컨텐츠'),
            array('ac_id' => 2, 'ac_page' => 'new.php', 'ac_name' => '최신글 보기', 'ac_description' => '사이트 전체에서 최근에 작성된 게시글과 댓글을 시간순으로 확인할 수 있는 페이지입니다.', 'ac_level' => 1, 'ac_category' => '검색 & 컨텐츠'),
            array('ac_id' => 3, 'ac_page' => 'faq.php', 'ac_name' => 'FAQ 페이지', 'ac_description' => '사용자들이 자주 묻는 질문과 그에 대한 답변을 제공하는 고객지원 페이지입니다.', 'ac_level' => 1, 'ac_category' => '검색 & 컨텐츠'),
            array('ac_id' => 7, 'ac_page' => 'content.php', 'ac_name' => '컨텐츠 보기', 'ac_description' => '서비스 소개, 개인정보처리방침, 이용약관 같은 정적 컨텐츠를 보여주는 페이지입니다.', 'ac_level' => 1, 'ac_category' => '검색 & 컨텐츠'),
            array('ac_id' => 8, 'ac_page' => 'current_connect.php', 'ac_name' => '현재 접속자', 'ac_description' => '현재 사이트에 접속해 있는 사용자 수와 접속자 정보를 실시간으로 확인할 수 있는 페이지입니다.', 'ac_level' => 1, 'ac_category' => '검색 & 컨텐츠'),
            array('ac_id' => 9, 'ac_page' => 'group.php', 'ac_name' => '그룹 페이지', 'ac_description' => '게시판 그룹별로 분류된 게시판 목록을 확인하고 접근할 수 있는 페이지입니다.', 'ac_level' => 1, 'ac_category' => '검색 & 컨텐츠'),
        ),
        '회원 관련' => array(
            array('ac_id' => 4, 'ac_page' => 'register.php', 'ac_name' => '회원가입', 'ac_description' => '새로운 계정을 생성하여 사이트 회원으로 가입할 수 있는 페이지입니다. 개인정보 입력 및 약관 동의가 포함됩니다.', 'ac_level' => 1, 'ac_category' => '회원 관련'),
            array('ac_id' => 5, 'ac_page' => 'password_lost.php', 'ac_name' => '비밀번호 찾기', 'ac_description' => '로그인 비밀번호를 분실했을 때 이메일이나 휴대폰을 통해 비밀번호를 재설정할 수 있는 페이지입니다.', 'ac_level' => 1, 'ac_category' => '회원 관련'),
            array('ac_id' => 10, 'ac_page' => 'memo.php', 'ac_name' => '쪽지함', 'ac_description' => '다른 회원들과 개인적으로 메시지를 주고받을 수 있는 쪽지 기능 페이지입니다.', 'ac_level' => 2, 'ac_category' => '회원 관련'),
            array('ac_id' => 11, 'ac_page' => 'profile.php', 'ac_name' => '회원 프로필', 'ac_description' => '회원의 개인정보, 작성글, 댓글 등을 확인하고 수정할 수 있는 마이페이지입니다.', 'ac_level' => 2, 'ac_category' => '회원 관련'),
        ),
        '게시판/설문 관련' => array(
            array('ac_id' => 6, 'ac_page' => 'board.php', 'ac_name' => '게시판', 'ac_description' => '게시글을 작성하고 조회하며 댓글을 달 수 있는 메인 게시판 기능입니다. 파일 첨부 및 다운로드도 포함됩니다.', 'ac_level' => 1, 'ac_category' => '게시판/설문 관련'),
            array('ac_id' => 12, 'ac_page' => 'scrap.php', 'ac_name' => '스크랩', 'ac_description' => '관심 있는 게시글을 개인 스크랩북에 저장하고 관리할 수 있는 기능입니다.', 'ac_level' => 2, 'ac_category' => '게시판/설문 관련'),
            array('ac_id' => 13, 'ac_page' => 'poll_result.php', 'ac_name' => '투표/설문', 'ac_description' => '사이트에서 진행하는 설문조사나 투표에 참여하고 결과를 확인할 수 있는 페이지입니다.', 'ac_level' => 1, 'ac_category' => '게시판/설문 관련'),
            array('ac_id' => 14, 'ac_page' => 'qalist.php', 'ac_name' => 'Q&A 게시판', 'ac_description' => '질문과 답변 형태의 1:1 문의나 고객지원을 위한 전용 게시판입니다.', 'ac_level' => 2, 'ac_category' => '게시판/설문 관련'),
        )
    );
}
?>

<link rel="stylesheet" href="./security_common.css">
<style>
* {
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f8fafc;
}

.access-control-container {
    margin: 20px 0;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* dashboard-title과 dashboard-subtitle은 security_common.css에서 정의됨 */

.access-section {
    margin-bottom: 30px;
    background: #ffffff;
    border-radius: 5px;
    overflow: hidden;
    border: 1px solid #ddd;
}

/* section-header는 security_common.css에서 정의됨 */

/* 접근제어 페이지 전용 section-content 스타일 */
.access-control-container .section-content {
    padding: 0;
    display: none;
    overflow: hidden;
    transition: all 0.5s cubic-bezier(0.4, 0.0, 0.2, 1);
    max-height: 0;
    opacity: 0;
}

.access-control-container .section-content.expanded {
    display: block;
    max-height: none;
    opacity: 1;
    padding: 0;
    overflow: visible;
}

.access-item {
    display: flex;
    align-items: flex-start;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.3s ease;
    position: relative;
}

.access-item:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.access-item:last-child {
    border-bottom: none;
}

.item-info {
    flex: 1;
    margin-right: 20px;
}

.item-name {
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 4px;
    font-size: 18px;
}

.item-path {
    font-size: 11px;
    color: #ed8936;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    margin: 8px 0;
    background: #fef5e7;
    padding: 6px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 400;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    border: 1px solid #f6ad55;
}

.item-path:hover {
    background: #fed7a7;
    color: #c05621;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(237, 137, 54, 0.3);
}

.item-description {
    font-size: 12px;
    color: #718096;
    margin: 6px 0 12px 0;
    line-height: 1.5;
    font-weight: 500;
}

.related-files {
    margin-top: 4px;
}

.related-label {
    font-size: 11px;
    color: #a0aec0;
    font-weight: 600;
    margin-bottom: 4px;
    display: block;
}

.related-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.related-file {
    background: #f7fafc;
    color: #a0aec0;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 500;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    border: 1px solid #e2e8f0;
}

.related-more {
    background: #edf2f7;
    color: #718096;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    font-style: italic;
}

.access-controls {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-width: 160px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 18px;
    font-size: 12px;
    font-weight: 700;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    min-width: 80px;
    text-align: center;
}

.status-visitor {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

.status-member {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
}

.status-admin {
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
}

.status-off {
    background: linear-gradient(135deg, #a0aec0 0%, #718096 100%);
}

/* 스위치 컨테이너 */
.switch-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

/* 3단계 스위치 (정사각형 드래그 스타일) */
.triple-switch {
    position: relative;
    width: 120px;
    height: 40px;
    background: #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid #cbd5e0;
    overflow: hidden;
    transition: all 0.3s ease;
}

.triple-switch:hover {
    border-color: #a0aec0;
    transform: scale(1.02);
}

.triple-switch-handle {
    position: absolute;
    width: 36px;
    height: 36px;
    background: white;
    border-radius: 6px;
    top: 1px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: 2px solid;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 10px;
    color: white;
}

.triple-switch[data-level="1"] {
    background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
    border-color: #68d391;
}

.triple-switch[data-level="1"] .triple-switch-handle {
    left: 1px;
    border-color: #38a169;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

.triple-switch[data-level="2"] {
    background: linear-gradient(135deg, #bee3f8 0%, #90cdf4 100%);
    border-color: #63b3ed;
}

.triple-switch[data-level="2"] .triple-switch-handle {
    left: 41px;
    border-color: #3182ce;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
}

.triple-switch[data-level="10"] {
    background: linear-gradient(135deg, #fbd38d 0%, #f6ad55 100%);
    border-color: #f6ad55;
}

.triple-switch[data-level="10"] .triple-switch-handle {
    left: 81px;
    border-color: #dd6b20;
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
}

/* ON/OFF 스위치 */
.simple-switch {
    position: relative;
    width: 80px;
    height: 40px;
    background: #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid #cbd5e0;
    transition: all 0.3s ease;
}

.simple-switch:hover {
    border-color: #a0aec0;
    transform: scale(1.02);
}

.simple-switch.on {
    background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
    border-color: #68d391;
}

.simple-switch-handle {
    position: absolute;
    width: 36px;
    height: 36px;
    background: white;
    border-radius: 6px;
    top: 1px;
    left: 1px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: 2px solid #cbd5e0;
}

.simple-switch.on .simple-switch-handle {
    transform: translateX(40px);
    border-color: #38a169;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

/* 관리자/회원 스위치 */
.dual-switch {
    position: relative;
    width: 100px;
    height: 40px;
    background: #bee3f8;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid #63b3ed;
    overflow: hidden;
    transition: all 0.3s ease;
}

.dual-switch:hover {
    transform: scale(1.02);
}

.dual-switch.admin {
    background: linear-gradient(135deg, #fbd38d 0%, #f6ad55 100%);
    border-color: #f6ad55;
}

.dual-switch-handle {
    position: absolute;
    width: 46px;
    height: 36px;
    background: white;
    border-radius: 6px;
    top: 1px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: 2px solid;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    color: white;
}

.dual-switch .dual-switch-handle {
    left: 1px;
    border-color: #3182ce;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
}

.dual-switch.admin .dual-switch-handle {
    left: 51px;
    border-color: #dd6b20;
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
}

.level-labels {
    display: flex;
    justify-content: space-between;
    width: 120px;
    margin-top: 8px;
    font-size: 9px;
    color: #718096;
    font-weight: 600;
}

.dual-labels {
    width: 100px;
}

.warning-notice {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
    border: 1px solid #fc8181;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 30px;
    color: #c53030;
    font-size: 15px;
    font-weight: 500;
}

.control-buttons {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}

.reset-button {
    background: #dc3545;
    border: 1px solid #dc3545;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.reset-button:hover {
    background: #c82333;
    border-color: #c82333;
}

.reset-button:active {
    transform: translateY(0);
}

.feature-highlight {
    background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%);
    border: 1px solid #81e6d9;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 30px;
    color: #234e52;
    font-size: 15px;
    font-weight: 500;
}
</style>

<div class="access-control-container">
    <h1 class="dashboard-title">
        🛡️ 접근 제어 관리
    </h1>
    <p class="dashboard-subtitle">
        각 페이지별로 접근 권한을 설정할 수 있습니다. 메인 페이지 차단 시 관련된 모든 파일이 함께 차단됩니다.
    </p>

    <div class="control-buttons">
        <button type="button" class="reset-button" onclick="resetToDefault()">
            🔄 초기설정으로 복원
        </button>
    </div>

    <div class="feature-highlight">
        ✨ <strong>스마트 차단:</strong> 메인 기능을 차단하면 관련된 모든 파일들이 자동으로 함께 차단되어 우회 접근을 완전 차단합니다.
    </div>


    <form id="accessControlForm">
        <?php foreach ($access_controls as $category => $items): ?>
        <div class="access-section">
            <div class="section-header" onclick="toggleSection('<?php echo str_replace(array(' ', '&', '/'), array('_', '_', '_'), $category); ?>')" style="cursor: pointer;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php 
                    $icons = array(
                        '검색 & 컨텐츠' => '🔍',
                        '회원 관련' => '👤', 
                        '게시판/설문 관련' => '📝'
                    );
                    echo $icons[$category] ?? '📁';
                    ?> <?php echo $category; ?>
                </div>
                <span id="<?php echo str_replace(array(' ', '&', '/'), array('_', '_', '_'), $category); ?>_toggle" style="transition: transform 0.3s ease;">▶</span>
            </div>
            <div class="section-content" id="<?php echo str_replace(array(' ', '&', '/'), array('_', '_', '_'), $category); ?>_section">
                <?php foreach ($items as $item): ?>
                <div class="access-item">
                    <div class="item-info">
                        <div class="item-name"><?php echo $item['ac_name']; ?></div>
                        <div class="item-description"><?php echo $item['ac_description']; ?></div>
                        <a href="<?php echo G5_BBS_URL; ?>/<?php echo $item['ac_page']; ?>" class="item-path" target="_blank">
                            🔗 <?php echo $item['ac_page']; ?>
                        </a>
                        
                        <?php 
                        $current_page = $item['ac_page'];
                        $has_related_files = isset($related_files[$current_page]) && !empty($related_files[$current_page]);
                        if ($has_related_files): 
                        ?>
                        <div class="related-files">
                            <span class="related-label">🔒 함께 차단되는 관련 파일들</span>
                            <div class="related-list">
                                <?php foreach ($related_files[$current_page] as $related): ?>
                                <span class="related-file"><?php echo $related; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="access-controls">
                        <div class="switch-container">
                            <span class="status-badge status-<?php 
                                echo $item['ac_level'] == 10 ? 'admin' : 
                                    ($item['ac_level'] == 2 ? 'member' : 
                                    ($item['ac_level'] == 1 ? 'visitor' : 'off')); 
                            ?>" id="status-<?php echo $item['ac_id']; ?>">
                                <?php 
                                echo $item['ac_level'] == 10 ? '관리자만' : 
                                    ($item['ac_level'] == 2 ? '회원 이상만' : 
                                    ($item['ac_level'] == 1 ? '모든 사용자' : '접근 차단')); 
                                ?>
                            </span>
                            
                            <?php if (in_array($item['ac_page'], ['register.php', 'password_lost.php'])): ?>
                                <!-- ON/OFF 스위치 -->
                                <div class="simple-switch <?php echo $item['ac_level'] > 0 ? 'on' : ''; ?>" 
                                     onclick="toggleSimpleSwitch(<?php echo $item['ac_id']; ?>)"
                                     data-id="<?php echo $item['ac_id']; ?>">
                                    <div class="simple-switch-handle"></div>
                                </div>
                                <div class="level-labels dual-labels">
                                    <span>차단</span>
                                    <span>허용</span>
                                </div>
                                
                            <?php elseif (in_array($item['ac_page'], ['memo.php', 'profile.php', 'point.php', 'scrap.php', 'qalist.php'])): ?>
                                <!-- 관리자/회원 스위치 -->
                                <div class="dual-switch <?php echo $item['ac_level'] == 10 ? 'admin' : ''; ?>" 
                                     onclick="toggleDualSwitch(<?php echo $item['ac_id']; ?>)"
                                     data-id="<?php echo $item['ac_id']; ?>"
                                     data-level="<?php echo $item['ac_level']; ?>">
                                    <div class="dual-switch-handle"></div>
                                </div>
                                <div class="level-labels dual-labels">
                                    <span>회원</span>
                                    <span>관리자</span>
                                </div>
                                
                            <?php else: ?>
                                <!-- 3단계 스위치 -->
                                <div class="triple-switch" 
                                     data-level="<?php echo $item['ac_level']; ?>" 
                                     data-id="<?php echo $item['ac_id']; ?>"
                                     onclick="toggleTripleSwitch(<?php echo $item['ac_id']; ?>)">
                                    <div class="triple-switch-handle"></div>
                                </div>
                                <div class="level-labels">
                                    <span>비회원</span>
                                    <span>회원</span>
                                    <span>관리자</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </form>
</div>

<script>
function toggleTripleSwitch(id) {
    const switchElement = document.querySelector(`[data-id="${id}"].triple-switch`);
    const currentLevel = parseInt(switchElement.getAttribute('data-level'));
    
    let newLevel;
    if (currentLevel === 1) {
        newLevel = 2;
    } else if (currentLevel === 2) {
        newLevel = 10;
    } else {
        newLevel = 1;
    }
    
    switchElement.setAttribute('data-level', newLevel);
    updateStatus(id, newLevel);
    saveAccessControl(id, newLevel);
}

function toggleSimpleSwitch(id) {
    const switchElement = document.querySelector(`[data-id="${id}"].simple-switch`);
    const isOn = switchElement.classList.contains('on');
    
    if (isOn) {
        switchElement.classList.remove('on');
        updateStatus(id, -1);
        saveAccessControl(id, -1);
    } else {
        switchElement.classList.add('on');
        updateStatus(id, 1);
        saveAccessControl(id, 1);
    }
}

function toggleDualSwitch(id) {
    const switchElement = document.querySelector(`[data-id="${id}"].dual-switch`);
    const currentLevel = parseInt(switchElement.getAttribute('data-level'));
    
    let newLevel = currentLevel === 10 ? 2 : 10;
    
    switchElement.setAttribute('data-level', newLevel);
    if (newLevel === 10) {
        switchElement.classList.add('admin');
    } else {
        switchElement.classList.remove('admin');
    }
    
    updateStatus(id, newLevel);
    saveAccessControl(id, newLevel);
}

function updateStatus(id, level) {
    const statusElement = document.getElementById(`status-${id}`);
    
    statusElement.className = 'status-badge status-' + 
        (level == 10 ? 'admin' : 
         level == 2 ? 'member' : 
         level == 1 ? 'visitor' : 'off');
    
    statusElement.textContent = 
        level == 10 ? '관리자만' : 
        level == 2 ? '회원 이상만' : 
        level == 1 ? '모든 사용자' : '접근 차단';
}

function saveAccessControl(id, level) {
    fetch('access_control_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&level=${level}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 성공 시 약간의 시각적 피드백
            const statusElement = document.getElementById(`status-${id}`);
            statusElement.style.transform = 'scale(1.1)';
            setTimeout(() => {
                statusElement.style.transform = 'scale(1)';
            }, 200);
        } else {
            alert('설정 저장에 실패했습니다: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('설정 저장 중 오류가 발생했습니다.');
    });
}

function resetToDefault() {
    if (!confirm('정말로 초기설정으로 복원하시겠습니까?\n\n모든 접근 제어 설정이 그누보드 설치 시 기본값으로 돌아갑니다.')) {
        return;
    }
    
    fetch('access_control_reset.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('초기설정으로 복원되었습니다.');
            location.reload(); // 페이지 새로고침
        } else {
            alert('초기설정 복원에 실패했습니다: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('초기설정 복원 중 오류가 발생했습니다.');
    });
}

// 브라우저 콘솔에 디버깅 정보 출력
function logDebugInfo() {
    const debugInfo = <?php echo json_encode($debug_info, JSON_PRETTY_PRINT); ?>;
    
    console.group('🛠️ 보안 플러그인 디버깅 정보');
    console.log('⏰ 타임스탬프:', debugInfo.timestamp);
    
    console.group('💻 시스템 환경');
    console.log('PHP 버전:', debugInfo.php_version);
    console.log('MySQL 버전:', debugInfo.mysql_version);
    console.groupEnd();
    
    console.group('📁 필수 파일 상태');
    Object.entries(debugInfo.files).forEach(([file, status]) => {
        const emoji = status === 'EXISTS' ? '✅' : '❌';
        console.log(`${emoji} ${file}: ${status}`);
    });
    console.groupEnd();
    
    console.group('🗄️ 데이터베이스 상태');
    console.log('연결 상태:', debugInfo.database.connection);
    console.log('테이블 존재:', debugInfo.database.table_exists);
    console.log('쿼리 성공:', debugInfo.database.query_success);
    if (debugInfo.database.rows_loaded) {
        console.log('로드된 행:', debugInfo.database.rows_loaded + '개');
    }
    if (debugInfo.database.error) {
        console.error('데이터베이스 오류:', debugInfo.database.error);
    }
    console.groupEnd();
    
    console.groupEnd();
    
    // 경고 메시지
    const missingFiles = Object.values(debugInfo.files).filter(status => status === 'MISSING');
    if (missingFiles.length > 0 || debugInfo.database.table_exists === 'NO') {
        console.warn('⚠️ 주의: 일부 기능이 제한될 수 있습니다.');
        if (missingFiles.length > 0) {
            console.warn('- 누락된 파일이 있습니다.');
        }
        if (debugInfo.database.table_exists === 'NO') {
            console.warn('- 데이터베이스 테이블이 없습니다.');
        }
    }
}

// 콘솔 디버깅 토글 기능
let consoleDebugEnabled = true;

function toggleConsoleDebug() {
    consoleDebugEnabled = !consoleDebugEnabled;
    
    if (consoleDebugEnabled) {
        console.log('%c🔍 콘솔 디버깅 활성화', 'color: green; font-weight: bold');
        logDebugInfo();
        localStorage.setItem('accessControlConsoleDebug', 'true');
    } else {
        console.log('%c🔇 콘솔 디버깅 비활성화', 'color: gray; font-weight: bold');
        localStorage.setItem('accessControlConsoleDebug', 'false');
    }
}

// 페이지 로드 시 애니메이션 및 디버깅 정보 출력
document.addEventListener('DOMContentLoaded', function() {
    // 이전 설정 불러오기
    const savedDebugSetting = localStorage.getItem('accessControlConsoleDebug');
    if (savedDebugSetting === 'false') {
        consoleDebugEnabled = false;
    }
    
    // 디버깅 정보 콘솔 출력
    if (consoleDebugEnabled) {
        logDebugInfo();
    }
    
    // 애니메이션
    const sections = document.querySelectorAll('.access-section');
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        setTimeout(() => {
            section.style.transition = 'all 0.5s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
            
            // 각 섹션을 순차적으로 펼치기
            setTimeout(() => {
                const sectionContent = section.querySelector('.section-content');
                const toggle = section.querySelector('[id$="_toggle"]');
                if (sectionContent && toggle) {
                    sectionContent.classList.add('expanded');
                    toggle.textContent = '▼';
                    toggle.style.transform = 'rotate(0deg)';
                }
            }, 500);
        }, index * 100);
    });
});

// 섹션 토글 함수
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId + '_section');
    const toggle = document.getElementById(sectionId + '_toggle');
    
    if (section.classList.contains('expanded')) {
        // 접기
        section.classList.remove('expanded');
        toggle.textContent = '▶';
        toggle.style.transform = 'rotate(-90deg)';
    } else {
        // 펼치기
        section.classList.add('expanded');
        toggle.textContent = '▼';
        toggle.style.transform = 'rotate(0deg)';
    }
}

// 전역 함수로 등록
window.logDebugInfo = logDebugInfo;
window.toggleConsoleDebug = toggleConsoleDebug;
window.toggleSection = toggleSection;
</script>

<?php
require_once './admin.tail.php';
?>