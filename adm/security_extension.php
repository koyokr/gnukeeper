<?php
$sub_menu = '950400';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

// AJAX 요청 처리 (헤더 출력 전에 처리)
if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'remove_extension') {
        $extension = trim($_POST['extension']);
        if (empty($extension)) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }
        
        // 각 필드에서 확장자 제거
        $fields_to_update = array('cf_image_extension', 'cf_flash_extension', 'cf_movie_extension');
        $updates = array();
        
        foreach ($fields_to_update as $field) {
            $sql = "SELECT {$field} FROM {$g5['config_table']}";
            $row = sql_fetch($sql);
            
            if ($row && !empty($row[$field])) {
                $current_extensions = explode('|', $row[$field]);
                $current_extensions = array_map('trim', $current_extensions);
                
                // 확장자 제거
                $new_extensions = array_filter($current_extensions, function($ext) use ($extension) {
                    return strtolower($ext) !== strtolower($extension);
                });
                
                // 변경된 경우에만 업데이트
                if (count($new_extensions) !== count($current_extensions)) {
                    $new_extension_string = implode('|', $new_extensions);
                    $updates[] = "{$field} = '" . sql_escape_string($new_extension_string) . "'";
                }
            }
        }
        
        if (!empty($updates)) {
            $update_sql = "UPDATE {$g5['config_table']} SET " . implode(', ', $updates);
            if (sql_query($update_sql)) {
                echo json_encode(['success' => true, 'message' => '확장자가 삭제되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'message' => '확장자 삭제에 실패했습니다.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => '해당 확장자를 찾을 수 없습니다.']);
        }
        exit;
    }
    
    if ($_POST['action'] == 'fix_board_permissions') {
        $bo_table = trim($_POST['bo_table']);
        $default_level = intval($_POST['default_level']);
        
        if (empty($bo_table) || $default_level < 1) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }
        
        // 게시판 권한 수정 (비회원 권한을 회원 권한으로 변경 - 목록, 읽기, 쓰기, 답글, 댓글 모두)
        $update_sql = "UPDATE {$g5['board_table']} SET 
                       bo_list_level = CASE WHEN bo_list_level <= 1 THEN {$default_level} ELSE bo_list_level END,
                       bo_read_level = CASE WHEN bo_read_level <= 1 THEN {$default_level} ELSE bo_read_level END,
                       bo_write_level = CASE WHEN bo_write_level <= 1 THEN {$default_level} ELSE bo_write_level END,
                       bo_reply_level = CASE WHEN bo_reply_level <= 1 THEN {$default_level} ELSE bo_reply_level END,
                       bo_comment_level = CASE WHEN bo_comment_level <= 1 THEN {$default_level} ELSE bo_comment_level END
                       WHERE bo_table = '" . sql_escape_string($bo_table) . "'";
        
        if (sql_query($update_sql)) {
            echo json_encode(['success' => true, 'message' => '게시판 모든 권한이 기본 회원 권한으로 수정되었습니다.']);
        } else {
            echo json_encode(['success' => false, 'message' => '게시판 권한 수정에 실패했습니다.']);
        }
        exit;
    }
    
    if ($_POST['action'] == 'toggle_board_exception') {
        $bo_table = trim($_POST['bo_table']);
        
        if (empty($bo_table)) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }
        
        // 현재 예외 목록 가져오기
        $config_sql = "SELECT cf_1 FROM {$g5['config_table']}";
        $config_result = sql_fetch($config_sql);
        $current_exceptions = isset($config_result['cf_1']) ? $config_result['cf_1'] : '';
        
        $exception_list = array();
        if (!empty($current_exceptions)) {
            $exception_list = explode('|', $current_exceptions);
            $exception_list = array_map('trim', $exception_list);
            $exception_list = array_filter($exception_list);
        }
        
        // 예외 목록에서 추가/제거
        $is_exception = in_array($bo_table, $exception_list);
        if ($is_exception) {
            // 제거
            $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                return $item !== $bo_table;
            });
            $message = '게시판 예외 처리가 해제되었습니다.';
        } else {
            // 추가
            $exception_list[] = $bo_table;
            $message = '게시판이 예외 처리되었습니다.';
        }
        
        // 저장
        $new_exceptions = implode('|', $exception_list);
        $update_sql = "UPDATE {$g5['config_table']} SET cf_1 = '" . sql_escape_string($new_exceptions) . "'";
        
        if (sql_query($update_sql)) {
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => '예외 처리 설정에 실패했습니다.']);
        }
        exit;
    }
    
    if ($_POST['action'] == 'update_board_permissions') {
        $bo_table = trim($_POST['bo_table']);
        $type = trim($_POST['type']);
        
        if (empty($bo_table) || empty($type)) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }
        
        $update_sql = "";
        
        switch($type) {
            case 'fix_member_level':
                // 모든 접근 권한을 회원 레벨로 설정
                if (isset($_POST['level'])) {
                    $level = (int)$_POST['level'];
                } else {
                    // 기본값으로 실제 회원 기본 레벨 사용
                    $config_sql = "SELECT cf_register_level FROM {$g5['config_table']}";
                    $config_result = sql_fetch($config_sql);
                    $level = isset($config_result['cf_register_level']) ? $config_result['cf_register_level'] : 2;
                }
                $update_sql = "UPDATE {$g5['board_table']} SET 
                               bo_list_level = {$level}, 
                               bo_read_level = {$level}, 
                               bo_write_level = {$level}, 
                               bo_reply_level = {$level}, 
                               bo_comment_level = {$level} 
                               WHERE bo_table = '" . sql_escape_string($bo_table) . "'";
                break;
                
            case 'except_write_admin':
                // 쓰기 권한 외 관리자 권한(10)으로
                $update_sql = "UPDATE {$g5['board_table']} SET 
                               bo_list_level = 10, 
                               bo_read_level = 10, 
                               bo_reply_level = 10, 
                               bo_comment_level = 10 
                               WHERE bo_table = '" . sql_escape_string($bo_table) . "'";
                break;
                
            case 'except_read_admin':
                // 읽기 권한 외 관리자 권한(10)으로
                $update_sql = "UPDATE {$g5['board_table']} SET 
                               bo_list_level = 10, 
                               bo_write_level = 10, 
                               bo_reply_level = 10, 
                               bo_comment_level = 10 
                               WHERE bo_table = '" . sql_escape_string($bo_table) . "'";
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => '알 수 없는 작업 타입입니다.']);
                exit;
        }
        
        if (!empty($update_sql) && sql_query($update_sql)) {
            echo json_encode(['success' => true, 'message' => '게시판 권한이 성공적으로 변경되었습니다.']);
        } else {
            echo json_encode(['success' => false, 'message' => '권한 변경에 실패했습니다.']);
        }
        exit;
    }
    
    if ($_POST['action'] == 'reset_member_permissions') {
        if (!isset($_POST['selected_users']) || !is_array($_POST['selected_users'])) {
            echo json_encode(['success' => false, 'message' => '선택된 사용자가 없습니다.']);
            exit;
        }
        
        $selected_users = $_POST['selected_users'];
        $super_admin_id = 'admin'; // 최고관리자 ID 보호
        $updated_count = 0;
        
        // 기본 회원가입 권한 레벨 가져오기
        $config_sql = "SELECT cf_register_level FROM {$g5['config_table']}";
        $config_result = sql_fetch($config_sql);
        $default_member_level = isset($config_result['cf_register_level']) ? $config_result['cf_register_level'] : 2;
        
        foreach ($selected_users as $mb_id) {
            $mb_id = clean_xss_tags(trim($mb_id));
            if ($mb_id && $mb_id != $super_admin_id) {
                $update_sql = "UPDATE {$g5['member_table']} 
                              SET mb_level = '{$default_member_level}' 
                              WHERE mb_id = '" . sql_escape_string($mb_id) . "' AND mb_id != '{$super_admin_id}'";
                if (sql_query($update_sql)) {
                    $updated_count++;
                }
            }
        }
        
        if ($updated_count > 0) {
            echo json_encode(['success' => true, 'message' => $updated_count . "명의 사용자 권한을 기본 권한(레벨 {$default_member_level})으로 변경했습니다."]);
        } else {
            echo json_encode(['success' => false, 'message' => '권한 변경에 실패했습니다.']);
        }
        exit;
    }
    
    // 업로드 용량 예외 처리
    if ($_POST['action'] == 'toggle_upload_exception') {
        $bo_table = trim($_POST['bo_table']);
        
        if (empty($bo_table)) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }
        
        // 현재 예외 목록 가져오기 (cf_2 필드 사용)
        $config_sql = "SELECT cf_2 FROM {$g5['config_table']}";
        $config_result = sql_fetch($config_sql);
        $current_exceptions = isset($config_result['cf_2']) ? $config_result['cf_2'] : '';
        
        $exception_list = array();
        if (!empty($current_exceptions)) {
            $exception_list = explode('|', $current_exceptions);
            $exception_list = array_map('trim', $exception_list);
            $exception_list = array_filter($exception_list);
        }
        
        // 예외 목록에서 추가/제거
        $is_exception = in_array($bo_table, $exception_list);
        if ($is_exception) {
            // 제거
            $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                return $item !== $bo_table;
            });
            $message = '업로드 용량 예외 처리가 해제되었습니다.';
        } else {
            // 추가
            $exception_list[] = $bo_table;
            $message = '업로드 용량이 예외 처리되었습니다.';
        }
        
        // 저장
        $new_exceptions = implode('|', $exception_list);
        $update_sql = "UPDATE {$g5['config_table']} SET cf_2 = '" . sql_escape_string($new_exceptions) . "'";
        
        if (sql_query($update_sql)) {
            echo json_encode(['success' => true, 'message' => $message, 'is_exception' => !$is_exception]);
        } else {
            echo json_encode(['success' => false, 'message' => '예외 처리 설정에 실패했습니다.']);
        }
        exit;
    }
    
    // 캡차 예외 처리
    if ($_POST['action'] == 'toggle_captcha_exception') {
        $bo_table = trim($_POST['bo_table']);
        
        if (empty($bo_table)) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }
        
        // 현재 예외 목록 가져오기 (cf_4 필드 사용)
        $config_sql = "SELECT cf_4 FROM {$g5['config_table']}";
        $config_result = sql_fetch($config_sql);
        $current_exceptions = isset($config_result['cf_4']) ? $config_result['cf_4'] : '';
        
        $exception_list = array();
        if (!empty($current_exceptions)) {
            $exception_list = explode('|', $current_exceptions);
            $exception_list = array_map('trim', $exception_list);
            $exception_list = array_filter($exception_list);
        }
        
        // 예외 목록에서 추가/제거
        $is_exception = in_array($bo_table, $exception_list);
        if ($is_exception) {
            // 제거
            $exception_list = array_filter($exception_list, function($item) use ($bo_table) {
                return $item !== $bo_table;
            });
            $message = '캡챠 예외 처리가 해제되었습니다.';
        } else {
            // 추가
            $exception_list[] = $bo_table;
            $message = '캡챠가 예외 처리되었습니다.';
        }
        
        // 저장
        $new_exceptions = implode('|', $exception_list);
        $update_sql = "UPDATE {$g5['config_table']} SET cf_4 = '" . sql_escape_string($new_exceptions) . "'";
        
        if (sql_query($update_sql)) {
            echo json_encode(['success' => true, 'message' => $message, 'is_exception' => !$is_exception]);
        } else {
            echo json_encode(['success' => false, 'message' => '예외 처리 설정에 실패했습니다.']);
        }
        exit;
    }
    
    // 업로드 크기를 10MB로 제한
    if ($_POST['action'] == 'limit_upload_10mb') {
        $bo_table = trim($_POST['bo_table']);
        
        if (empty($bo_table)) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }
        
        // 10MB = 10 * 1024 * 1024 bytes
        $limit_10mb = 10 * 1024 * 1024;
        
        $update_sql = "UPDATE {$g5['board_table']} SET bo_upload_size = {$limit_10mb} WHERE bo_table = '" . sql_escape_string($bo_table) . "'";
        
        if (sql_query($update_sql)) {
            echo json_encode(['success' => true, 'message' => '업로드 크기가 10MB로 제한되었습니다.']);
        } else {
            echo json_encode(['success' => false, 'message' => '업로드 크기 제한에 실패했습니다.']);
        }
        exit;
    }
    
    // 게시판에 캡챠 적용
    if ($_POST['action'] == 'apply_captcha') {
        $bo_table = trim($_POST['bo_table']);
        
        if (empty($bo_table)) {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }
        
        $update_sql = "UPDATE {$g5['board_table']} SET bo_use_captcha = 1 WHERE bo_table = '" . sql_escape_string($bo_table) . "'";
        
        if (sql_query($update_sql)) {
            echo json_encode(['success' => true, 'message' => '캡챠가 적용되었습니다.']);
        } else {
            echo json_encode(['success' => false, 'message' => '캡챠 적용에 실패했습니다.']);
        }
        exit;
    }
}

$g5['title'] = '정책관리';
require_once './admin.head.php';

// 확장자 위험도 분석 데이터
$extension_risks = array(
    'high' => array(
        'exe' => '실행 파일 - 악성코드, 바이러스 실행 가능',
        'bat' => '배치 파일 - 시스템 명령어 실행으로 서버 해킹 위험',
        'cmd' => '윈도우 명령 파일 - 시스템 조작 가능',
        'com' => 'DOS 실행 파일 - 구형 악성코드 실행 위험',
        'scr' => '스크린세이버 파일 - 악성코드 은닉 수단으로 활용',
        'pif' => 'DOS 프로그램 정보 파일 - 실행 파일 위장 가능',
        'php' => 'PHP 스크립트 - 웹셸 업로드로 서버 완전 장악 위험',
        'asp' => 'ASP 스크립트 - IIS 서버에서 백도어 생성 가능',
        'aspx' => 'ASP.NET 파일 - .NET 서버에서 원격 제어 위험',
        'jsp' => 'Java Server Pages - 톰캣 서버 해킹 위험',
        'pl' => 'Perl 스크립트 - Unix/Linux 서버 공격 가능',
        'py' => 'Python 스크립트 - 서버 시스템 조작 위험',
        'rb' => 'Ruby 스크립트 - 웹 어플리케이션 공격 가능',
        'cgi' => 'CGI 스크립트 - 웹서버 백도어 생성 위험',
        'sh' => 'Shell 스크립트 - Linux/Unix 시스템 명령 실행',
        'jar' => 'Java 아카이브 - 악성 Java 코드 실행 위험'
    ),
    'medium' => array(
        'js' => 'JavaScript 파일 - XSS 공격, 브라우저 조작 가능',
        'vbs' => 'VBScript 파일 - 윈도우에서 시스템 조작 위험',
        'html' => 'HTML 파일 - XSS 공격, 피싱 페이지 생성 가능',
        'htm' => 'HTML 파일 - 악성 스크립트 포함 위험',
        'xml' => 'XML 파일 - XXE 공격, 외부 엔티티 참조 위험',
        'xsl' => 'XSL 스타일시트 - 스크립트 실행 위험',
        'svg' => 'SVG 벡터 이미지 - 스크립트 실행 및 XSS 공격',
        'hta' => 'HTML Application - 시스템 권한으로 실행 위험',
        'sql' => 'SQL 파일 - 데이터베이스 조작 및 정보 유출',
        'reg' => '레지스트리 파일 - 윈도우 시스템 설정 변경',
        'msi' => '설치 패키지 - 악성 소프트웨어 설치 위험'
    ),
    'low' => array(
        'jpg' => 'JPEG 이미지',
        'jpeg' => 'JPEG 이미지',
        'png' => 'PNG 이미지',
        'gif' => 'GIF 이미지',
        'bmp' => 'BMP 이미지',
        'webp' => 'WebP 이미지',
        'ico' => '아이콘 파일',
        'pdf' => 'PDF 문서',
        'doc' => 'MS Word 문서',
        'docx' => 'MS Word 문서',
        'xls' => 'MS Excel 문서',
        'xlsx' => 'MS Excel 문서',
        'ppt' => 'MS PowerPoint',
        'pptx' => 'MS PowerPoint',
        'txt' => '텍스트 파일',
        'rtf' => 'Rich Text Format',
        'csv' => 'CSV 데이터',
        'mp3' => 'MP3 오디오',
        'wav' => 'WAV 오디오',
        'ogg' => 'OGG 오디오',
        'mp4' => 'MP4 비디오',
        'avi' => 'AVI 비디오',
        'mov' => 'QuickTime 비디오',
        'wmv' => 'Windows Media Video',
        'asx' => 'Windows Media 재생목록',
        'asf' => 'Windows Media 컨테이너',
        'wma' => 'Windows Media Audio',
        'mpg' => 'MPEG 비디오 파일',
        'mpeg' => 'MPEG 비디오 파일',
        'zip' => 'ZIP 압축 파일',
        'rar' => 'RAR 압축 파일',
        '7z' => '7-Zip 압축 파일',
        'tar' => 'TAR 아카이브',
        'gz' => 'GZIP 압축 파일',
        'hwp' => '한글 문서',
        'swf' => 'Flash 파일'
    )
);

// 현재 설정된 확장자 분석
function analyzeCurrentExtensions() {
    global $g5, $extension_risks;
    
    // 모든 업로드 관련 확장자 필드 가져오기
    $sql = "SELECT cf_image_extension, cf_flash_extension, cf_movie_extension FROM {$g5['config_table']}";
    $row = sql_fetch($sql);
    
    $all_extensions = array();
    
    // 각 필드에서 확장자 추출
    $extension_fields = array(
        'cf_image_extension' => isset($row['cf_image_extension']) ? $row['cf_image_extension'] : '', 
        'cf_flash_extension' => isset($row['cf_flash_extension']) ? $row['cf_flash_extension'] : '',
        'cf_movie_extension' => isset($row['cf_movie_extension']) ? $row['cf_movie_extension'] : ''
    );
    
    foreach ($extension_fields as $field_name => $field_value) {
        if (!empty($field_value)) {
            $exts = explode('|', strtolower(trim($field_value)));
            $all_extensions = array_merge($all_extensions, $exts);
        }
    }
    
    // 중복 제거 및 빈 값 제거
    $current_extensions = array_unique(array_filter(array_map('trim', $all_extensions)));
    
    $analysis = array(
        'total' => count($current_extensions),
        'high' => 0,
        'medium' => 0,
        'low' => 0,
        'unknown' => 0,
        'extensions' => array()
    );
    
    foreach ($current_extensions as $ext) {
        $ext = strtolower(trim($ext));
        if (empty($ext)) continue;
        
        $risk_level = 'unknown';
        $description = '알 수 없는 확장자 - 보안 검토 필요';
        
        foreach ($extension_risks as $level => $risks) {
            if (isset($risks[$ext])) {
                $risk_level = $level;
                $description = $risks[$ext];
                break;
            }
        }
        
        $analysis[$risk_level]++;
        $analysis['extensions'][] = array(
            'name' => $ext,
            'risk' => $risk_level,
            'description' => $description
        );
    }
    
    return $analysis;
}

$analysis = analyzeCurrentExtensions();

// 전체 보안 등급 결정
function getOverallSecurityGrade($analysis) {
    if ($analysis['high'] > 0) {
        return array(
            'grade' => '위험',
            'color' => '#dc3545',
            'icon' => '🚨',
            'description' => '매우 위험한 확장자가 허용되어 있습니다!'
        );
    } elseif ($analysis['medium'] > 3) {
        return array(
            'grade' => '주의',
            'color' => '#fd7e14',
            'icon' => '⚠️',
            'description' => '주의가 필요한 확장자가 다수 허용되어 있습니다.'
        );
    } elseif ($analysis['medium'] > 0) {
        return array(
            'grade' => '보통',
            'color' => '#ffc107',
            'icon' => '⚡',
            'description' => '일부 주의 확장자가 허용되어 있습니다.'
        );
    } elseif ($analysis['unknown'] > 0) {
        return array(
            'grade' => '검토',
            'color' => '#6f42c1',
            'icon' => '🔍',
            'description' => '알 수 없는 확장자가 있어 검토가 필요합니다.'
        );
    } else {
        return array(
            'grade' => '안전',
            'color' => '#28a745',
            'icon' => '✅',
            'description' => '안전한 확장자만 허용되어 있습니다.'
        );
    }
}

$security_grade = getOverallSecurityGrade($analysis);
?>

<style>
.security-dashboard {
    margin: 20px 0;
}

.dashboard-section {
    margin-bottom: 30px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    font-weight: bold;
    font-size: 16px;
    color: #333;
}

.section-content {
    padding: 0;
    overflow: hidden;
    transition: max-height 0.5s cubic-bezier(0.4, 0.0, 0.2, 1), 
                opacity 0.3s ease,
                padding 0.5s cubic-bezier(0.4, 0.0, 0.2, 1);
    max-height: 0;
    opacity: 0;
}

.section-content.expanded {
    max-height: 3000px;
    opacity: 1;
    padding: 20px;
}

.dashboard-title {
    color: #333;
    margin-bottom: 20px;
    font-size: 24px;
    font-weight: bold;
}


.extension-container {
    background: rgba(248, 249, 250, 0.7);
    border: 1px solid rgba(222, 226, 230, 0.8);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    backdrop-filter: blur(5px);
}

.extension-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.extension-item {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 15px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    font-weight: bold;
    color: #495057;
}

.extension-item.high {
    background: #ffebee;
    border-color: #ef5350;
    color: #d32f2f;
}

.extension-item.medium {
    background: #fff3e0;
    border-color: #ff9800;
    color: #f57c00;
}

.extension-item.low {
    background: #e8f5e8;
    border-color: #4caf50;
    color: #388e3c;
}

.extension-item.unknown {
    background: #f3e5f5;
    border-color: #9c27b0;
    color: #7b1fa2;
}

.extension-remove {
    margin-left: 8px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    opacity: 0.6;
    transition: all 0.2s ease;
    padding: 0;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.extension-remove:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 0.8);
    transform: scale(1.1);
}


.analysis-result {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.analysis-result.safe {
    background: #f0f9f0;
    border: 1px solid #d4edda;
    color: #155724;
}

.analysis-result.warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.analysis-result.danger {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.analysis-icon {
    font-size: 20px;
    margin-right: 8px;
}

.recommendations {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 15px;
    border-radius: 5px;
}

.recommendations h4 {
    color: #495057;
    margin-bottom: 10px;
    font-size: 16px;
}

.info-highlight {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
    color: #0056b3;
    font-size: 15px;
    font-weight: 500;
}

.info-subtle {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 5px;
    padding: 12px;
    margin-bottom: 15px;
    color: #6c757d;
    font-size: 14px;
    font-weight: normal;
}

.recommendations ul {
    margin: 0;
    padding-left: 20px;
    color: #495057;
}

.recommendations li {
    margin-bottom: 5px;
    line-height: 1.4;
}

/* 툴팁 스타일 */
.tooltip {
    position: absolute;
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 13px;
    font-family: Arial, sans-serif;
    white-space: nowrap;
    z-index: 1000;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    max-width: 300px;
    white-space: normal;
    line-height: 1.4;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.tooltip.show {
    opacity: 1;
}

.tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: rgba(0, 0, 0, 0.9) transparent transparent transparent;
}
</style>

<div class="security-dashboard">
    <h1 class="dashboard-title">정책관리</h1>
    
    <!-- 확장자 정책 관리 -->
    <div class="dashboard-section">
        <div class="section-header" onclick="toggleSection('extension-section')" style="cursor: pointer;">
            📁 확장자 정책 관리 <span id="extension-toggle" style="float: right; transition: transform 0.3s ease;">▶</span>
        </div>
        <div class="section-content" id="extension-section">
            <div class="info-highlight">
                웹에서 업로드 가능한 파일 확장자를 관리하고 보안 위험성을 확인합니다.
            </div>
            
            
            <!-- 허용된 확장자 목록 -->
            <div class="extension-container">
                <div style="margin-bottom: 15px;">
                    <span style="font-weight: bold; font-size: 16px; color: #333;">허용된 확장자 목록</span>
                </div>
                <?php if (empty($analysis['extensions'])): ?>
                <p style="color: #666; padding: 15px; background: #f8f9fa; border-radius: 4px; margin-top: 10px;">
                    허용된 확장자가 없습니다.
                </p>
                <?php else: ?>
                <div class="extension-list">
                    <?php foreach ($analysis['extensions'] as $ext): ?>
                    <div class="extension-item <?php echo $ext['risk']; ?>" data-tooltip="<?php echo htmlspecialchars($ext['description']); ?>">
                        <?php 
                        $icon = '';
                        if ($ext['risk'] == 'high') $icon = '❗';
                        elseif ($ext['risk'] == 'medium') $icon = '⚠️';
                        echo $icon;
                        ?>.<?php echo $ext['name']; ?>
                        <?php if ($is_admin == 'super'): ?>
                        <button class="extension-remove" onclick="removeExtension('<?php echo $ext['name']; ?>')" title="삭제">×</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 보안 위험성 분석 결과 -->
            <?php 
            $analysis_class = 'safe';
            $analysis_icon = '✅';
            $analysis_message = '<div style="background: #e8f5e8; color: #2d5a2d; padding: 8px 12px; border-radius: 6px; font-size: 12px;">✅ 안전합니다! 현재 허용된 확장자 목록에는 알려진 위험한 확장자가 포함되어 있지 않습니다.</div>';
            
            if ($analysis['high'] > 0) {
                $analysis_class = 'danger';
                $analysis_icon = '🚨';
                $analysis_message = '위험! 매우 위험한 확장자가 ' . $analysis['high'] . '개 허용되어 있습니다. 즉시 제거하시기 바랍니다.';
            } elseif ($analysis['medium'] > 3) {
                $analysis_class = 'warning';
                $analysis_icon = '⚠️';
                $analysis_message = '주의! 주의가 필요한 확장자가 ' . $analysis['medium'] . '개 허용되어 있습니다. 검토 후 불필요한 확장자를 제거하세요.';
            } elseif ($analysis['medium'] > 0) {
                $analysis_class = 'warning';
                $analysis_icon = '⚡';
                $analysis_message = '보통! 일부 주의 확장자가 ' . $analysis['medium'] . '개 허용되어 있습니다. 필요시에만 사용하세요.';
            }
            ?>
            <div class="analysis-result <?php echo $analysis_class; ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 15px;">
                <div style="flex: 1;">
                    <?php echo $analysis_message; ?>
                </div>
                <div style="margin-left: 20px; flex-shrink: 0;">
                    <span style="padding: 6px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; color: white;
                                   background: <?php 
                                   echo $analysis_class == 'danger' ? '#dc2626' : 
                                       ($analysis_class == 'warning' ? '#fd7e14' : '#059669'); 
                                   ?>;">
                        <?php 
                        echo $analysis_class == 'danger' ? '위험' : 
                            ($analysis_class == 'warning' ? '주의' : '안전'); 
                        ?>
                    </span>
                </div>
            </div>
            
            <!-- 보안 가이드 -->
            <div class="recommendations">
                <h4>보안 가이드</h4>
                <ul>
                    <li><strong>1.</strong> 실행 파일 확장자(.exe, .bat, .cmd 등)는 절대 허용하지 마세요</li>
                    <li><strong>2.</strong> 스크립트 파일(.js, .vbs, .php 등)은 신중하게 검토 후 허용하세요</li>
                    <li><strong>3.</strong> 업무상 필요한 최소한의 확장자만 허용하는 것이 안전합니다</li>
                    <li><strong>4.</strong> 정기적으로 허용 목록을 검토하고 불필요한 확장자를 제거하세요</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 게시판 접근 권한 정책 -->
    <div class="dashboard-section">
        <div class="section-header" onclick="toggleSection('board-section')" style="cursor: pointer;">
            🔐 게시판 접근 권한 정책 <span id="board-toggle" style="float: right; transition: transform 0.3s ease;">▶</span>
        </div>
        <div class="section-content" id="board-section">
            <div class="info-highlight">
                게시판별 접근 권한을 확인하고 비회원 권한으로 인한 보안 위험을 관리합니다.
            </div>
            
            
            <?php
            // 예외 처리된 게시판 목록 가져오기
            $exception_sql = "SELECT cf_1 FROM {$g5['config_table']}";
            $exception_result = sql_fetch($exception_sql);
            $exception_boards = array();
            if ($exception_result && !empty($exception_result['cf_1'])) {
                $exception_list = explode('|', $exception_result['cf_1']);
                foreach ($exception_list as $ex_board) {
                    if (!empty(trim($ex_board))) {
                        $exception_boards[] = trim($ex_board);
                    }
                }
            }
            
            // 게시판 목록 가져오기
            $board_sql = "SELECT bo_table, bo_subject, bo_list_level, bo_read_level, bo_write_level, bo_reply_level, bo_comment_level FROM {$g5['board_table']} ORDER BY bo_table";
            $board_result = sql_query($board_sql);
            
            $boards = array();
            while ($board = sql_fetch_array($board_result)) {
                $boards[] = $board;
            }
            
            // index.php 내용 가져오기
            $index_content = '';
            $index_file = G5_PATH . '/index.php';
            if (file_exists($index_file)) {
                $index_content = file_get_contents($index_file);
            }
            
            // 회원가입시 기본 권한 가져오기
            $config_sql = "SELECT cf_register_level FROM {$g5['config_table']}";
            $config_result = sql_fetch($config_sql);
            $default_member_level = isset($config_result['cf_register_level']) ? $config_result['cf_register_level'] : 2;
            
            function analyzeBoardSecurity($board, $default_member_level, $exception_boards) {
                $bo_table = $board['bo_table'];
                $status = 'safe';
                $icon = '✅';
                $warnings = array();
                $has_guest_permissions = false;
                $is_exception = in_array($bo_table, $exception_boards);
                
                // 예외 처리된 게시판인 경우
                if ($is_exception) {
                    return array(
                        'status' => 'exception',
                        'icon' => '⚙️',
                        'warnings' => array('이 게시판은 예외 처리되어 보안 검사에서 제외됩니다. 관리자가 의도적으로 설정한 특별한 용도의 게시판입니다.'),
                        'has_guest_permissions' => false,
                        'default_member_level' => $default_member_level,
                        'is_exception' => true
                    );
                }
                
                // 미회원 권한 체크 (레벨 1 = 미회원 접근 가능)
                $dangerous_permissions = array();
                if ($board['bo_list_level'] <= 1) {
                    $dangerous_permissions[] = '목록';
                    $has_guest_permissions = true;
                }
                if ($board['bo_read_level'] <= 1) {
                    $dangerous_permissions[] = '읽기';
                    $has_guest_permissions = true;
                }
                if ($board['bo_write_level'] <= 1) {
                    $dangerous_permissions[] = '쓰기';
                    $has_guest_permissions = true;
                }
                if ($board['bo_reply_level'] <= 1) {
                    $dangerous_permissions[] = '답글';
                    $has_guest_permissions = true;
                }
                if ($board['bo_comment_level'] <= 1) {
                    $dangerous_permissions[] = '댓글';
                    $has_guest_permissions = true;
                }
                
                if (!empty($dangerous_permissions)) {
                    $status = 'danger';
                    $icon = '🚨';
                    $warnings[] = '비회원도 해당 게시판에 ' . implode(', ', $dangerous_permissions) . ' 권한이 있어 스팸/악성 콘텐츠 위험이 높습니다.';
                }
                
                // 추가 권한 상태 분석
                $is_except_write_admin = false;
                $is_except_read_admin = false;
                $is_all_member_level = false;
                
                // "쓰기 권한 외 관리자 권한" 상태 체크
                if ($board['bo_list_level'] == 10 && $board['bo_read_level'] == 10 && 
                    $board['bo_reply_level'] == 10 && $board['bo_comment_level'] == 10 && 
                    $board['bo_write_level'] != 10) {
                    $is_except_write_admin = true;
                }
                
                // "읽기 권한 외 관리자 권한" 상태 체크
                if ($board['bo_list_level'] == 10 && $board['bo_write_level'] == 10 && 
                    $board['bo_reply_level'] == 10 && $board['bo_comment_level'] == 10 && 
                    $board['bo_read_level'] != 10) {
                    $is_except_read_admin = true;
                }
                
                // "모든 권한이 회원 레벨" 상태 체크
                if ($board['bo_list_level'] == $default_member_level && $board['bo_read_level'] == $default_member_level && 
                    $board['bo_write_level'] == $default_member_level && $board['bo_reply_level'] == $default_member_level && 
                    $board['bo_comment_level'] == $default_member_level) {
                    $is_all_member_level = true;
                }
                
                return array(
                    'status' => $status,
                    'icon' => $icon,
                    'warnings' => $warnings,
                    'has_guest_permissions' => $has_guest_permissions,
                    'default_member_level' => $default_member_level,
                    'is_exception' => false,
                    'is_except_write_admin' => $is_except_write_admin,
                    'is_except_read_admin' => $is_except_read_admin,
                    'is_all_member_level' => $is_all_member_level
                );
            }
            ?>
            
            <div class="extension-container">
                <?php if (empty($boards)): ?>
                <p style="color: #666; padding: 15px; background: #f8f9fa; border-radius: 4px; margin-top: 10px;">
                    생성된 게시판이 없습니다.
                </p>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                    <?php foreach ($boards as $board): ?>
                    <?php $analysis = analyzeBoardSecurity($board, $default_member_level, $exception_boards); ?>
                    <div style="background: <?php 
                        echo $analysis['status'] == 'safe' ? '#f0f9f0' : 
                            ($analysis['status'] == 'danger' ? '#fef2f2' : 
                            ($analysis['status'] == 'exception' ? '#fffbeb' : 'white')); 
                        ?>; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                    <span style="font-weight: bold; font-size: 16px; color: #333;"><?php echo $analysis['icon']; ?> <?php echo htmlspecialchars($board['bo_subject']); ?></span>
                                    <span style="background: #f7fafc; color: #718096; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-family: monospace;">
                                        <?php echo $board['bo_table']; ?>
                                    </span>
                                </div>
                                
                                <div style="font-size: 13px; color: #666; margin-bottom: 8px;">
                                    설정된 접근 권한: 
                                    <span style="background: #f1f3f4; color: #5f6368; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin: 0 2px;">목록(<?php echo $board['bo_list_level']; ?>)</span>
                                    <span style="background: #f1f3f4; color: #5f6368; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin: 0 2px;">읽기(<?php echo $board['bo_read_level']; ?>)</span>
                                    <span style="background: #f1f3f4; color: #5f6368; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin: 0 2px;">쓰기(<?php echo $board['bo_write_level']; ?>)</span>
                                    <span style="background: #f1f3f4; color: #5f6368; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin: 0 2px;">답글(<?php echo $board['bo_reply_level']; ?>)</span>
                                    <span style="background: #f1f3f4; color: #5f6368; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin: 0 2px;">댓글(<?php echo $board['bo_comment_level']; ?>)</span>
                                </div>
                                
                                <?php if (!empty($analysis['warnings'])): ?>
                                <div style="margin-top: 8px;">
                                    <?php foreach ($analysis['warnings'] as $warning): ?>
                                    <div style="background: <?php echo $analysis['status'] == 'exception' ? '#fff3cd' : '#fef2f2'; ?>; 
                                               color: <?php echo $analysis['status'] == 'exception' ? '#856404' : '#dc2626'; ?>; 
                                               padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                                        <?php echo $warning; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($analysis['is_all_member_level']): ?>
                                <div style="background: #e8f5e8; color: #2d5a2d; padding: 8px 12px; border-radius: 6px; font-size: 12px; margin-top: 8px;">
                                    이 게시판내 게시물은 회원 간 열람이 허용되어 있습니다.
                                </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap;">
                                    <?php if ($analysis['has_guest_permissions']): ?>
                                    <button onclick="updateBoardPermissions('<?php echo $board['bo_table']; ?>', 'fix_member_level', <?php echo $analysis['default_member_level']; ?>)" 
                                            style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        모든 접근 권한을 기본 회원 레벨(<?php echo $analysis['default_member_level']; ?>)로 수정
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($analysis['is_except_write_admin'] || $analysis['is_except_read_admin']): ?>
                                    <button onclick="updateBoardPermissions('<?php echo $board['bo_table']; ?>', 'fix_member_level', <?php echo $analysis['default_member_level']; ?>)" 
                                            style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        모든 접근 권한을 기본 회원 레벨(<?php echo $analysis['default_member_level']; ?>)로 수정
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (!$analysis['is_except_write_admin']): ?>
                                    <button onclick="updateBoardPermissions('<?php echo $board['bo_table']; ?>', 'except_write_admin')" 
                                            style="background: #d97706; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        쓰기 권한 외 관리자 권한으로 수정
                                    </button>
                                    <?php endif; ?>
                                    <?php if (!$analysis['is_except_read_admin']): ?>
                                    <button onclick="updateBoardPermissions('<?php echo $board['bo_table']; ?>', 'except_read_admin')" 
                                            style="background: #6f42c1; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        읽기 권한 외 관리자 권한으로 수정
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($analysis['is_exception']): ?>
                                    <button onclick="toggleBoardException('<?php echo $board['bo_table']; ?>')" 
                                            style="background: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        예외 처리 해제
                                    </button>
                                    <?php elseif ($analysis['has_guest_permissions']): ?>
                                    <button onclick="toggleBoardException('<?php echo $board['bo_table']; ?>')" 
                                            style="background: #fd7e14; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        예외 처리
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="margin-left: 15px;">
                                <span style="padding: 6px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; color: white;
                                           background: <?php 
                                           echo $analysis['status'] == 'danger' ? '#dc2626' : 
                                               ($analysis['status'] == 'exception' ? '#fd7e14' : '#059669'); 
                                           ?>;">
                                    <?php 
                                    echo $analysis['status'] == 'danger' ? '위험' : 
                                        ($analysis['status'] == 'exception' ? '예외' : '안전'); 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            
            <!-- 보안 가이드 -->
            <div class="recommendations">
                <h4>게시판 보안 가이드</h4>
                <ul>
                    <li><strong>1.</strong> 비회원 권한(레벨 1)은 스팸 위험이 높으므로 신중하게 설정하세요</li>
                    <li><strong>2.</strong> 게시판별 권한은 최소 필요 권한 원칙에 따라 설정하세요</li>
                    <li><strong>3.</strong> 각 게시판별로 제공되는 권한 수정 버튼을 활용하여 빠르게 권한을 변경할 수 있습니다</li>
                    <li><strong>4.</strong> 정기적으로 게시판 설정된 권한을 검토하고 관리하세요</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 관리자급 권한 보유 사용자 관리 -->
    <div class="dashboard-section">
        <div class="section-header" onclick="toggleSection('admin-users-section')" style="cursor: pointer;">
            👥 관리자급 권한 보유 사용자 관리 <span id="admin-users-toggle" style="float: right; transition: transform 0.3s ease;">▶</span>
        </div>
        <div class="section-content" id="admin-users-section">
            <div class="info-highlight">
                관리자와 동일한 권한 레벨(레벨 10)을 가진 일반 사용자를 감지하고 관리합니다.
            </div>
            
            <?php
            // 최고관리자 ID (보호 대상)
            $super_admin_id = 'admin';
            
            // 기본 회원가입 권한 레벨 가져오기
            $config_sql = "SELECT cf_register_level FROM {$g5['config_table']}";
            $config_result = sql_fetch($config_sql);
            $default_member_level = isset($config_result['cf_register_level']) ? $config_result['cf_register_level'] : 2;
            
            // 관리자급 권한을 가진 일반 사용자 검색
            $admin_users_sql = "SELECT mb_id, mb_name, mb_nick, mb_level, mb_datetime, mb_email, mb_login_ip, mb_today_login 
                               FROM {$g5['member_table']} 
                               WHERE mb_level >= 10 
                               AND mb_id != '{$super_admin_id}'
                               ORDER BY mb_level DESC, mb_datetime ASC";
            
            $admin_users_result = sql_query($admin_users_sql);
            $admin_users = array();
            while ($row = sql_fetch_array($admin_users_result)) {
                $admin_users[] = $row;
            }
            ?>
            
            
            <?php if (count($admin_users) > 0): ?>
            <form id="admin-users-form">
                <div class="extension-container">
                    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold; font-size: 16px; color: #333;">
                            <label>
                                <input type="checkbox" id="select-all-admin-users" style="margin-right: 8px;">
                                전체 선택
                            </label>
                        </span>
                        <button type="button" onclick="resetSelectedUserPermissions()" 
                                style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; font-size: 13px; cursor: pointer; font-weight: 600;">
                            선택된 사용자를 기본 권한(<?php echo $default_member_level; ?>)으로 초기화
                        </button>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($admin_users as $user): ?>
                        <?php 
                        $risk_level = '';
                        $risk_color = '';
                        if ($user['mb_level'] >= 10) {
                            $risk_level = '위험';
                            $risk_color = '#dc2626';
                        }
                        
                        $last_login = $user['mb_today_login'] != '0000-00-00 00:00:00' ? 
                                     substr($user['mb_today_login'], 0, 16) : '로그인 기록 없음';
                        ?>
                        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="checkbox" name="admin_users[]" value="<?php echo htmlspecialchars($user['mb_id']); ?>" 
                                                   style="margin-right: 8px;" class="admin-user-checkbox">
                                            <span style="font-weight: bold; font-size: 16px; color: #333;">
                                                <?php echo htmlspecialchars($user['mb_id']); ?>
                                            </span>
                                        </label>
                                        <?php if($user['mb_name']): ?>
                                        <span style="background: #e6f3ff; color: #2563eb; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                            이름: <?php echo htmlspecialchars($user['mb_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if($user['mb_nick']): ?>
                                        <span style="background: #f0f9ff; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                            닉네임: <?php echo htmlspecialchars($user['mb_nick']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="font-size: 13px; color: #666; margin-bottom: 8px;">
                                        <span style="margin-right: 15px;"><strong>권한레벨:</strong> <span style="color: #dc2626; font-weight: bold;"><?php echo $user['mb_level']; ?></span></span>
                                        <span style="margin-right: 15px;"><strong>이메일:</strong> <?php echo htmlspecialchars($user['mb_email']); ?></span>
                                        <span style="margin-right: 15px;"><strong>가입일:</strong> <?php echo substr($user['mb_datetime'], 0, 16); ?></span>
                                        <span style="margin-right: 15px;"><strong>최근로그인:</strong> <?php echo $last_login; ?></span>
                                        <span><strong>로그인IP:</strong> <?php echo htmlspecialchars($user['mb_login_ip']); ?></span>
                                    </div>
                                    
                                    <div style="background: #fee2e2; color: #dc2626; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-top: 8px;">
                                        ⚠️ 이 사용자는 관리자와 동일한 권한을 가지고 있어 보안상 위험할 수 있습니다.
                                    </div>
                                </div>
                                
                                <div style="margin-left: 15px;">
                                    <span style="padding: 6px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $risk_color; ?>;">
                                        <?php echo $risk_level; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                </div>
            </form>
            <?php else: ?>
            <div style="text-align: center; padding: 50px 20px; background: #f0f9f0; border: 1px solid #d4edda; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 10px 0; font-size: 18px; font-weight: bold; color: #28a745;">
                    ✅ 관리자급 권한을 가진 일반 사용자가 없습니다.
                </p>
                <p style="margin: 10px 0; font-size: 16px; color: #155724;">
                    현재 시스템이 안전한 상태입니다.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- 보안 가이드 -->
            <div class="recommendations">
                <h4>관리자 권한 보안 가이드</h4>
                <ul>
                    <li><strong>1.</strong> 레벨 10 이상의 사용자는 시스템 전체에 대한 관리자 권한을 가집니다</li>
                    <li><strong>2.</strong> 정기적으로 관리자급 권한 사용자를 점검하시기 바랍니다</li>
                    <li><strong>3.</strong> 불필요한 관리자 권한은 즉시 일반 회원 권한으로 변경하세요</li>
                    <li><strong>4.</strong> 최고관리자(<?php echo $super_admin_id; ?>) 계정은 보호되어 변경할 수 없습니다</li>
                    <li><strong>5.</strong> 관리자 권한 부여 시 신중하게 검토하고 필요한 경우에만 부여하세요</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 업로드 용량 정책 -->
    <div class="dashboard-section">
        <div class="section-header" onclick="toggleSection('upload-policy-section')" style="cursor: pointer;">
            📁 업로드 용량 정책 <span id="upload-policy-toggle" style="float: right; transition: transform 0.3s ease;">▶</span>
        </div>
        <div class="section-content" id="upload-policy-section">
            <div class="info-highlight">
                업로드 가능한 파일 용량이 과도하게 높아 서버 자원 고갈 위험을 확인하고 관리합니다.
            </div>
            
            <?php
            // 업로드 용량 위험 임계값 (100MB = 104857600 bytes)
            $upload_risk_threshold = 100 * 1024 * 1024; // 100MB
            
            // 업로드 예외 목록 가져오기
            $config_sql = "SELECT cf_2 FROM {$g5['config_table']}";
            $config_result = sql_fetch($config_sql);
            $upload_exceptions = isset($config_result['cf_2']) ? $config_result['cf_2'] : '';
            $upload_exception_list = array();
            if (!empty($upload_exceptions)) {
                $upload_exception_list = explode('|', $upload_exceptions);
                $upload_exception_list = array_map('trim', $upload_exception_list);
                $upload_exception_list = array_filter($upload_exception_list);
            }
            
            // 전체 게시판 업로드 설정 조회
            $upload_boards_sql = "SELECT bo_table, bo_subject, bo_upload_size FROM {$g5['board_table']} WHERE bo_upload_size > 0 ORDER BY bo_upload_size DESC";
            $upload_boards_result = sql_query($upload_boards_sql);
            
            $upload_boards = array();
            $high_risk_count = 0;
            while ($board = sql_fetch_array($upload_boards_result)) {
                $board['is_high_risk'] = $board['bo_upload_size'] >= $upload_risk_threshold;
                $board['is_exception'] = in_array($board['bo_table'], $upload_exception_list);
                
                // 예외 처리된 게시판은 위험도에서 제외
                if ($board['is_high_risk'] && !$board['is_exception']) {
                    $high_risk_count++;
                }
                $upload_boards[] = $board;
            }
            
            // 전체 설정에서 기본 업로드 크기 확인
            $config_sql = "SELECT cf_file_upload_size FROM {$g5['config_table']}";
            $config_result = sql_fetch($config_sql);
            $global_upload_size = $config_result ? $config_result['cf_file_upload_size'] : 0;
            $global_is_high_risk = $global_upload_size >= $upload_risk_threshold;
            ?>
            
            
            <?php if ($global_is_high_risk): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <div style="color: #dc2626; font-weight: bold; margin-bottom: 8px;">⚠️ 전역 업로드 크기 위험</div>
                <div style="color: #666; font-size: 13px;">
                    전체 사이트의 기본 업로드 크기가 <?php echo number_format($global_upload_size / 1024 / 1024, 1); ?>MB로 설정되어 있습니다. 
                    이는 서버 자원 고갈 및 디스크 공간 부족을 야기할 수 있습니다.
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (count($upload_boards) > 0): ?>
            <div class="extension-container">
                <div style="margin-bottom: 15px;">
                    <span style="font-weight: bold; font-size: 16px; color: #333;">게시판별 업로드 크기 설정</span>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($upload_boards as $board): ?>
                    <div style="background: <?php 
                                echo $board['is_exception'] ? '#fffbeb' : 
                                    ($board['is_high_risk'] ? '#fef2f2' : '#f0f9f0'); 
                               ?>; 
                               border: 1px solid #e2e8f0; 
                               border-radius: 8px; padding: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                    <span style="font-weight: bold; font-size: 16px; color: #333;">
                                        <?php if ($board['is_exception']): ?>⚙️<?php elseif ($board['is_high_risk']): ?>🚨<?php else: ?>✅<?php endif; ?> <?php echo htmlspecialchars($board['bo_subject']); ?>
                                    </span>
                                    <span style="background: #f7fafc; color: #718096; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                        <?php echo $board['bo_table']; ?>
                                    </span>
                                </div>
                                
                                <div style="font-size: 13px; color: #666; margin-bottom: 8px; display: flex; align-items: center; gap: 4px;">
                                    업로드 크기: 
                                    <span style="background: #f7fafc; border: 1px solid #e2e8f0; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                        <?php echo number_format($board['bo_upload_size'] / 1024 / 1024, 1); ?>MB ( 바이트: <?php echo number_format($board['bo_upload_size']); ?> bytes)
                                    </span>
                                </div>
                                
                                <?php if ($board['is_exception']): ?>
                                <div style="background: #fff3cd; color: #856404; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                                    이 게시판은 예외 처리되어 보안 검사에서 제외됩니다. 관리자가 의도적으로 설정한 특별한 용도의 게시판입니다.
                                </div>
                                <?php elseif ($board['is_high_risk']): ?>
                                <div style="background: #fee2e2; color: #dc2626; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                                    이 게시판의 업로드 크기가 <?php echo number_format($upload_risk_threshold / 1024 / 1024); ?>MB 이상으로 설정되어 서버 자원 고갈 위험이 있습니다.
                                </div>
                                <?php else: ?>
                                <div style="background: #e8f5e8; color: #0c5460; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                                    이 게시판은 안전한 업로드 크기로 설정되어 있습니다. 서버 자원을 효율적으로 사용하고 있습니다.
                                </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 10px;">
                                    <?php if ($board['is_exception']): ?>
                                    <button onclick="toggleUploadException('<?php echo $board['bo_table']; ?>')" 
                                            style="background: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        예외 처리 해제
                                    </button>
                                    <?php elseif ($board['is_high_risk']): ?>
                                    <button onclick="limitUpload10MB('<?php echo $board['bo_table']; ?>')" 
                                            style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600; margin-right: 6px;">
                                        10MB로 제한
                                    </button>
                                    <button onclick="toggleUploadException('<?php echo $board['bo_table']; ?>')" 
                                            style="background: #fd7e14; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        예외 처리
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="margin-left: 15px;">
                                <span style="padding: 6px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; color: white;
                                           background: <?php 
                                           echo $board['is_exception'] ? '#fd7e14' : 
                                               ($board['is_high_risk'] ? '#dc2626' : '#059669'); 
                                           ?>;">
                                    <?php 
                                    echo $board['is_exception'] ? '예외' : 
                                        ($board['is_high_risk'] ? '위험' : '안전'); 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 50px 20px; background: #f0f9f0; border: 1px solid #d4edda; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 10px 0; font-size: 18px; font-weight: bold; color: #28a745;">
                    ✅ 업로드가 허용된 게시판이 없습니다.
                </p>
                <p style="margin: 10px 0; font-size: 16px; color: #155724;">
                    현재 파일 업로드 위험이 없는 상태입니다.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- 보안 가이드 -->
            <div class="recommendations">
                <h4>업로드 용량 보안 가이드</h4>
                <ul>
                    <li><strong>1.</strong> 업로드 크기는 실제 필요한 용량으로 제한하세요 (일반적으로 10MB 이하 권장)</li>
                    <li><strong>2.</strong> 대용량 파일이 필요한 경우 별도의 파일 서버나 클라우드 스토리지 사용을 고려하세요</li>
                    <li><strong>3.</strong> 서버 디스크 용량과 대역폭을 정기적으로 모니터링하세요</li>
                    <li><strong>4.</strong> 업로드 파일 타입을 제한하여 불필요한 대용량 파일을 차단하세요</li>
                </ul>
            </div>
        </div>
    </div>


    <!-- 캡챠 적용 정책 -->
    <div class="dashboard-section">
        <div class="section-header" onclick="toggleSection('captcha-policy-section')" style="cursor: pointer;">
            🤖 캡챠 적용 정책 <span id="captcha-policy-toggle" style="float: right; transition: transform 0.3s ease;">▶</span>
        </div>
        <div class="section-content" id="captcha-policy-section">
            <div class="info-highlight">
                회원가입 및 주요 기능에서 캡챠 적용 여부를 확인하여 자동화 공격 위험을 관리합니다.
            </div>
            
            <?php
            // 캡차 예외 목록 가져오기
            $config_sql = "SELECT cf_4 FROM {$g5['config_table']}";
            $config_result = sql_fetch($config_sql);
            $captcha_exceptions = isset($config_result['cf_4']) ? $config_result['cf_4'] : '';
            $captcha_exception_list = array();
            if (!empty($captcha_exceptions)) {
                $captcha_exception_list = explode('|', $captcha_exceptions);
                $captcha_exception_list = array_map('trim', $captcha_exception_list);
                $captcha_exception_list = array_filter($captcha_exception_list);
            }
            
            // 전체 캡차 설정 확인
            $captcha_config_sql = "SELECT cf_use_captcha FROM {$g5['config_table']}";
            $captcha_config_result = sql_fetch($captcha_config_sql);
            $global_captcha = $captcha_config_result ? $captcha_config_result['cf_use_captcha'] : 0;
            
            // 캡차가 적용되지 않은 게시판 조회
            $no_captcha_boards_sql = "SELECT bo_table, bo_subject, bo_use_captcha FROM {$g5['board_table']} WHERE bo_use_captcha = 0 ORDER BY bo_subject";
            $no_captcha_boards_result = sql_query($no_captcha_boards_sql);
            
            $no_captcha_boards = array();
            $risk_count = 0;
            while ($board = sql_fetch_array($no_captcha_boards_result)) {
                $board['is_exception'] = in_array($board['bo_table'], $captcha_exception_list);
                if (!$board['is_exception']) {
                    $risk_count++;
                }
                $no_captcha_boards[] = $board;
            }
            
            if (!$global_captcha) {
                $risk_count++; // 전역 캡차가 비활성화된 경우 위험도 증가
            }
            ?>
            
            <?php if (count($no_captcha_boards) > 0): ?>
            <div class="extension-container">
                <div style="margin-bottom: 15px;">
                    <span style="font-weight: bold; font-size: 16px; color: #333;">캡챠가 적용되지 않은 게시판</span>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($no_captcha_boards as $board): ?>
                    <div style="background: <?php echo $board['is_exception'] ? '#fff3cd' : '#fef2f2'; ?>; 
                               border: 1px solid <?php echo $board['is_exception'] ? '#ffeaa7' : '#fecaca'; ?>; 
                               border-radius: 8px; padding: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                    <span style="font-weight: bold; font-size: 16px; color: #333;">
                                        <?php echo htmlspecialchars($board['bo_subject']); ?>
                                    </span>
                                    <span style="background: #f7fafc; color: #718096; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                        <?php echo $board['bo_table']; ?>
                                    </span>
                                </div>
                                
                                <?php if ($board['is_exception']): ?>
                                <div style="background: #fff3cd; color: #856404; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                                    이 게시판은 예외 처리되어 보안 검사에서 제외됩니다. 관리자가 의도적으로 설정한 특별한 용도의 게시판입니다.
                                </div>
                                <?php else: ?>
                                <div style="background: #fee2e2; color: #dc2626; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                                    이 게시판에서는 캡챠 인증 없이 글 작성이 가능하여 스팸 게시글 및 자동화 공격에 취약할 수 있습니다.
                                </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 10px;">
                                    <?php if ($board['is_exception']): ?>
                                    <button onclick="toggleCaptchaException('<?php echo $board['bo_table']; ?>')" 
                                            style="background: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        예외 처리 해제
                                    </button>
                                    <?php else: ?>
                                    <button onclick="applyCaptcha('<?php echo $board['bo_table']; ?>')" 
                                            style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600; margin-right: 6px;">
                                        캡챠 적용
                                    </button>
                                    <button onclick="toggleCaptchaException('<?php echo $board['bo_table']; ?>')" 
                                            style="background: #fd7e14; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        예외 처리
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="margin-left: 15px;">
                                <span style="padding: 6px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; color: white; 
                                           background: <?php echo $board['is_exception'] ? '#fd7e14' : '#dc2626'; ?>;">
                                    <?php echo $board['is_exception'] ? '예외' : '위험'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 50px 20px; background: #f0f9f0; border: 1px solid #d4edda; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 10px 0; font-size: 18px; font-weight: bold; color: #28a745;">
                    ✅ 모든 게시판에 캡챠가 적용되어 있습니다.
                </p>
                <p style="margin: 10px 0; font-size: 16px; color: #155724;">
                    현재 자동화 공격으로부터 보호되고 있습니다.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- 보안 가이드 -->
            <div class="recommendations">
                <h4>캡챠 적용 보안 가이드</h4>
                <ul>
                    <li><strong>1.</strong> 회원가입, 로그인, 게시글 작성 등 주요 기능에는 캡챠를 적용하세요</li>
                    <li><strong>2.</strong> 사용자 경험을 고려하여 적절한 수준의 캡챠를 선택하세요</li>
                    <li><strong>3.</strong> 반복적인 실패 시 캡챠 난이도를 높이는 방식을 고려하세요</li>
                    <li><strong>4.</strong> 정기적으로 스팸 및 자동화 공격 시도를 모니터링하세요</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
let currentTooltip = null;

// 툴팁 기능
function initializeTooltips() {
    const items = document.querySelectorAll('.extension-item[data-tooltip]');
    
    items.forEach(item => {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = item.getAttribute('data-tooltip');
        document.body.appendChild(tooltip);
        
        item.addEventListener('mouseenter', function(e) {
            if (currentTooltip) {
                currentTooltip.classList.remove('show');
            }
            
            currentTooltip = tooltip;
            const rect = item.getBoundingClientRect();
            
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
            
            tooltip.classList.add('show');
        });
        
        item.addEventListener('mouseleave', function() {
            tooltip.classList.remove('show');
            currentTooltip = null;
        });
    });
}

// 확장자 삭제
function removeExtension(extension) {
    if (!confirm(`'${extension}' 확장자를 삭제하시겠습니까?`)) {
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax=1&action=remove_extension&extension=' + encodeURIComponent(extension)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('확장자가 삭제되었습니다.');
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}

// 섹션 토글 함수
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    let toggle;
    
    if (sectionId === 'extension-section') {
        toggle = document.getElementById('extension-toggle');
    } else if (sectionId === 'board-section') {
        toggle = document.getElementById('board-toggle');
    } else if (sectionId === 'admin-users-section') {
        toggle = document.getElementById('admin-users-toggle');
    } else if (sectionId === 'upload-policy-section') {
        toggle = document.getElementById('upload-policy-toggle');
    } else if (sectionId === 'captcha-policy-section') {
        toggle = document.getElementById('captcha-policy-toggle');
    }
    
    if (section.classList.contains('expanded')) {
        // 접기
        section.classList.remove('expanded');
        if (toggle) {
            toggle.textContent = '▶';
            toggle.style.transform = 'rotate(-90deg)';
        }
    } else {
        // 펼치기
        section.classList.add('expanded');
        if (toggle) {
            toggle.textContent = '▼';
            toggle.style.transform = 'rotate(0deg)';
        }
    }
}

// 관리자 사용자 전체 선택/해제
function toggleSelectAllAdminUsers() {
    const selectAll = document.getElementById('select-all-admin-users');
    const checkboxes = document.querySelectorAll('.admin-user-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// 선택된 사용자 권한 초기화
function resetSelectedUserPermissions() {
    const checkboxes = document.querySelectorAll('.admin-user-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('권한을 초기화할 사용자를 선택해주세요.');
        return;
    }
    
    const userIds = Array.from(checkboxes).map(cb => cb.value);
    const confirmMessage = `선택된 ${userIds.length}명의 사용자 권한을 기본 회원 권한으로 변경하시겠습니까?\n\n변경될 사용자: ${userIds.join(', ')}\n\n이 작업은 되돌릴 수 없습니다.`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'reset_member_permissions');
    
    userIds.forEach(userId => {
        formData.append('selected_users[]', userId);
    });
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}

// 게시판 권한 수정 함수
function fixBoardPermissions(bo_table, default_level) {
    if (!confirm(`'${bo_table}' 게시판의 비회원 권한을 기본 회원 권한(레벨 ${default_level})으로 변경하시겠습니까?`)) {
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax=1&action=fix_board_permissions&bo_table=${encodeURIComponent(bo_table)}&default_level=${default_level}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('게시판 권한이 수정되었습니다.');
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}

// 게시판 예외 처리 토글 함수
function toggleBoardException(bo_table) {
    const confirmMessage = `'${bo_table}' 게시판의 예외 처리를 변경하시겠습니까?\n\n예외 처리된 게시판은 보안 검사에서 제외되며, 현재 설정된 권한이 그대로 유지됩니다.`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax=1&action=toggle_board_exception&bo_table=${encodeURIComponent(bo_table)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}

// 개별 게시판 권한 수정 함수
function updateBoardPermissions(bo_table, type, level = null) {
    let confirmMessage = '';
    let description = '';
    
    switch(type) {
        case 'fix_member_level':
            confirmMessage = `'${bo_table}' 게시판의 모든 접근 권한을 회원 레벨(${level})로 변경하시겠습니까?`;
            description = '목록, 읽기, 쓰기, 답글, 댓글 권한을 모두 회원 레벨로 설정합니다.';
            break;
        case 'except_write_admin':
            confirmMessage = `'${bo_table}' 게시판의 쓰기 권한을 제외하고 관리자 권한으로 변경하시겠습니까?`;
            description = '목록, 읽기, 답글, 댓글 권한은 관리자 권한으로, 쓰기 권한은 현재 설정을 유지합니다.';
            break;
        case 'except_read_admin':
            confirmMessage = `'${bo_table}' 게시판의 읽기 권한을 제외하고 관리자 권한으로 변경하시겠습니까?`;
            description = '목록, 쓰기, 답글, 댓글 권한은 관리자 권한으로, 읽기 권한은 현재 설정을 유지합니다.';
            break;
    }
    
    if (!confirm(confirmMessage + '\n\n' + description)) {
        return;
    }
    
    let body = `ajax=1&action=update_board_permissions&bo_table=${encodeURIComponent(bo_table)}&type=${encodeURIComponent(type)}`;
    if (level !== null) {
        body += `&level=${level}`;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('게시판 권한이 성공적으로 변경되었습니다.');
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}

// 업로드 예외 처리 토글 함수
function toggleUploadException(bo_table) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax=1&action=toggle_upload_exception&bo_table=${encodeURIComponent(bo_table)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('업로드 정책 예외 처리가 ' + (data.is_exception ? '설정' : '해제') + '되었습니다.');
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}


// 캡차 예외 처리 토글 함수
function toggleCaptchaException(bo_table) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax=1&action=toggle_captcha_exception&bo_table=${encodeURIComponent(bo_table)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('캡챠 정책 예외 처리가 ' + (data.is_exception ? '설정' : '해제') + '되었습니다.');
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}

// 업로드 크기 10MB로 제한 함수
function limitUpload10MB(bo_table) {
    if (!confirm(`'${bo_table}' 게시판의 업로드 크기를 10MB로 제한하시겠습니까?\n\n현재 설정된 업로드 크기가 10MB(10,485,760 bytes)로 변경됩니다.`)) {
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax=1&action=limit_upload_10mb&bo_table=${encodeURIComponent(bo_table)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}

// 캡챠 적용 함수
function applyCaptcha(bo_table) {
    if (!confirm(`'${bo_table}' 게시판에 캡챠를 적용하시겠습니까?\n\n글 작성 시 캡챠 인증이 필요하게 됩니다.`)) {
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax=1&action=apply_captcha&bo_table=${encodeURIComponent(bo_table)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            saveScrollPosition();
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 중 오류가 발생했습니다.');
    });
}

// 스크롤 위치 저장 함수
function saveScrollPosition() {
    sessionStorage.setItem('scrollPosition', window.pageYOffset);
    sessionStorage.setItem('isAjaxReload', 'true');
}

// 스크롤 위치 복원 함수
function restoreScrollPosition() {
    const savedPosition = sessionStorage.getItem('scrollPosition');
    if (savedPosition) {
        // 페이지 렌더링 완료 후 스크롤 복원
        setTimeout(() => {
            window.scrollTo(0, parseInt(savedPosition));
            sessionStorage.removeItem('scrollPosition');
        }, 100);
    }
}

// 새로고침 감지 (AJAX 요청 후 새로고침 여부 확인)
function isReloadAfterAjax() {
    const isAjaxReload = sessionStorage.getItem('isAjaxReload') === 'true';
    if (isAjaxReload) {
        sessionStorage.removeItem('isAjaxReload');
    }
    return isAjaxReload;
}

// 페이지 로드 시 툴팁 초기화
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
    
    // 관리자 사용자 전체 선택 체크박스 이벤트
    const selectAllCheckbox = document.getElementById('select-all-admin-users');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', toggleSelectAllAdminUsers);
    }
    
    // 스크롤 위치 복원
    restoreScrollPosition();
    
    // 새로고침 후가 아닌 경우에만 자동 펼치기 실행
    const isAfterReload = isReloadAfterAjax();
    
    // 각 섹션을 순차적으로 펼치기 (새로고침 후가 아닌 경우에만)
    const sections = ['extension-section', 'board-section', 'admin-users-section', 'upload-policy-section', 'captcha-policy-section'];
    if (!isAfterReload) {
        // 새로고침 후가 아닌 경우에만 자동 펼치기 애니메이션 실행
        sections.forEach((sectionId, index) => {
            setTimeout(() => {
                const section = document.getElementById(sectionId);
                const toggle = document.getElementById(sectionId.replace('-section', '-toggle'));
                if (section && toggle) {
                    section.classList.add('expanded');
                    toggle.textContent = '▼';
                    toggle.style.transform = 'rotate(0deg)';
                }
            }, 200 + (index * 200));
        });
    } else {
        // 새로고침 후인 경우 즉시 모든 섹션 펼치기 (애니메이션 없이)
        sections.forEach((sectionId) => {
            const section = document.getElementById(sectionId);
            const toggle = document.getElementById(sectionId.replace('-section', '-toggle'));
            if (section && toggle) {
                // 트랜지션 임시 비활성화
                section.style.transition = 'none';
                // 즉시 펼치기
                section.classList.add('expanded');
                toggle.textContent = '▼';
                toggle.style.transform = 'rotate(0deg)';
                // 트랜지션 복원 (다음 상호작용을 위해)
                setTimeout(() => {
                    section.style.transition = '';
                }, 50);
            }
        });
    }
    
    // 스크롤 시 툴팁 숨기기
    window.addEventListener('scroll', function() {
        if (currentTooltip) {
            currentTooltip.classList.remove('show');
            currentTooltip = null;
        }
    });
});
</script>

<?php
require_once './admin.tail.php';
?>