<?php
$menu['menu950'] = array(
    array('950000', '보안설정', G5_ADMIN_URL . '/security_home.php',   'security'),
    array('950100', 'HOME', G5_ADMIN_URL . '/security_home.php',   'security_home'),
    array('950200', '접근제어', G5_ADMIN_URL . '/access_control.php',   'security_access'),
    array('950300', '차단관리', G5_ADMIN_URL . '/member_list.php?sst=mb_intercept_date&sod=desc&sfl=mb_intercept_date&stx=1',   'security_block'),
    array('950400', '권한관리', G5_ADMIN_URL . '/auth_list.php',   'security_auth'),
    array('950500', '스팸관리', G5_ADMIN_URL . '/mail_list.php',   'security_spam'),
);