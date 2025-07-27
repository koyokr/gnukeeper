<?php
/**
 * 회원가입 선별적 접근제어
 * gnuboard5 보안 플러그인
 * 
 * register_form_update.php에서 신규 회원가입($w='')만 선별적으로 차단
 * 회원정보 수정($w='u')은 허용
 */

if (!defined('_GNUBOARD_')) exit;

// register_form_update.php에서만 실행
if (basename($_SERVER['PHP_SELF']) === 'register_form_update.php') {
    
    // $w 값 확인 (신규 회원가입: '', 회원정보 수정: 'u')
    $w = '';
    if (isset($_POST['w'])) {
        $w = trim($_POST['w']);
    } elseif (isset($_GET['w'])) {
        $w = trim($_GET['w']);
    }
    
    // 신규 회원가입 시도인 경우만 차단 검사
    if ($w === '') {
        
        // 접근제어 테이블에서 회원가입 설정 확인
        $access_sql = "SELECT ac_level FROM g5_access_control WHERE ac_page = 'bbs/register.php'";
        $access_result = sql_query($access_sql, false);
        
        if ($access_result && $access_row = sql_fetch_array($access_result)) {
            $register_level = (int)$access_row['ac_level'];
            
            // 회원가입이 차단된 상태라면 (ac_level = -1 또는 0)
            if ($register_level <= 0) {
                alert('회원가입이 일시적으로 중단되었습니다.', G5_URL);
                exit;
            }
        }
    }
    
    // $w = 'u' (회원정보 수정)인 경우는 통과
    // 다른 모든 경우도 통과
}
?>