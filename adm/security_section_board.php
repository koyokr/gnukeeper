<?php
// 게시판 접근 권한 정책 섹션
if (!defined('_GNUBOARD_')) exit;

// 예외 처리된 게시판 목록 가져오기
$exception_sql = "SELECT cf_1, cf_2 FROM {$g5['config_table']}";
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
            'is_exception' => true,
            'is_interest' => false
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
    
    // "비회원 쓰기 및 쓰기 외 권한 관리자 권한" 상태 체크
    if ($board['bo_list_level'] == 10 && $board['bo_read_level'] == 10 && 
        $board['bo_reply_level'] == 10 && $board['bo_comment_level'] == 10 && 
        $board['bo_write_level'] == 1) {
        $is_except_write_admin = true;
    }
    
    // "비회원 읽기 및 비회원 읽기 외 권한 관리자 권한" 상태 체크
    if ($board['bo_list_level'] == 1 && $board['bo_write_level'] == 10 && 
        $board['bo_reply_level'] == 10 && $board['bo_comment_level'] == 10 && 
        $board['bo_read_level'] == 1) {
        $is_except_read_admin = true;
    }
    
    // "모든 권한이 회원 레벨" 상태 체크
    if ($board['bo_list_level'] == $default_member_level && $board['bo_read_level'] == $default_member_level && 
        $board['bo_write_level'] == $default_member_level && $board['bo_reply_level'] == $default_member_level && 
        $board['bo_comment_level'] == $default_member_level) {
        $is_all_member_level = true;
    }
    
    // 비회원 쓰기/읽기 권한 설정은 안전 상태로 처리
    // 비회원 읽기만 허용하는 안전한 패턴 체크 (목록도 포함)
    $is_safe_read_only = ($board['bo_read_level'] == 1 && $board['bo_write_level'] == 10 && 
                         $board['bo_reply_level'] == 10 && $board['bo_comment_level'] == 10) ||
                        (($board['bo_list_level'] == 1 || $board['bo_list_level'] == 10) && 
                         $board['bo_read_level'] == 1 && $board['bo_write_level'] == 10 && 
                         $board['bo_reply_level'] == 10 && $board['bo_comment_level'] == 10);
    
    if ($is_except_write_admin || $is_except_read_admin || $is_safe_read_only) {
        $status = 'safe';
        $icon = '✅';
        $warnings = array();
        
        if ($is_except_write_admin) {
            $warnings[] = '이 게시판은 비회원 쓰기 권한으로 설정되어 있습니다. 목록, 읽기, 답글, 댓글은 관리자 권한이며, 쓰기만 비회원이 가능합니다.';
        } elseif ($is_except_read_admin) {
            $warnings[] = '이 게시판은 비회원 목록/읽기 권한으로 설정되어 있습니다. 쓰기, 답글, 댓글은 관리자 권한이며, 목록과 읽기만 비회원이 가능합니다.';
        } elseif ($is_safe_read_only) {
            $warnings[] = '이 게시판은 비회원 읽기 권한으로 설정되어 있습니다. 쓰기, 답글, 댓글은 관리자 권한이며, 읽기만 비회원이 가능합니다.';
        }
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

<!-- 게시판 접근 권한 정책 -->
<div class="dashboard-section">
    <div class="section-header" onclick="toggleSection('board-section')" style="cursor: pointer;">
        🔐 게시판 접근 권한 정책 <span id="board-toggle" style="float: right; transition: transform 0.3s ease;">▶</span>
    </div>
    <div class="section-content" id="board-section">
        <div class="info-highlight">
            게시판별 접근 권한을 확인하고 비회원 권한으로 인한 보안 위험을 관리합니다.
        </div>
        
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
                                <div style="background: <?php echo $analysis['status'] == 'exception' ? '#fff3cd' : ($analysis['status'] == 'safe' ? '#f0f9f0' : '#fef2f2'); ?>; 
                                           color: <?php echo $analysis['status'] == 'exception' ? '#856404' : ($analysis['status'] == 'safe' ? '#059669' : '#dc2626'); ?>; 
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
                            
                            <div style="margin-top: 10px; display: flex; gap: 3px; flex-wrap: wrap;">
                                <?php if ($analysis['has_guest_permissions']): ?>
                                <button onclick="updateBoardPermissions('<?php echo $board['bo_table']; ?>', 'fix_member_level', <?php echo $analysis['default_member_level']; ?>)" 
                                        style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600; margin-right: 3px;">
                                    모든 접근 권한을 기본 회원 레벨(<?php echo $analysis['default_member_level']; ?>)로 수정
                                </button>
                                <?php endif; ?>
                                
                                <?php if (!$analysis['is_except_write_admin'] && !$analysis['is_exception']): ?>
                                <div class="btn-with-tooltip">
                                    <button onclick="updateBoardPermissions('<?php echo $board['bo_table']; ?>', 'except_write_admin')" 
                                            style="background: #3b82f6; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        비회원 쓰기 및 쓰기 외 권한 관리자 권한으로 수정
                                    </button>
                                    <span class="tooltip-message">문의·상담 등 사용자가 글만 작성할 수 있는 게시판에 적용을 권장합니다.</span>
                                </div>
                                <?php endif; ?>
                                <?php if (!$analysis['is_except_read_admin'] && !$analysis['is_exception']): ?>
                                <div class="btn-with-tooltip">
                                    <button onclick="updateBoardPermissions('<?php echo $board['bo_table']; ?>', 'except_read_admin')" 
                                            style="background: #8b5cf6; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600;">
                                        비회원 읽기 및 비회원 읽기 외 권한 관리자 권한으로 수정
                                    </button>
                                    <span class="tooltip-message">공지사항·알림 등 공지성 게시판에 적용을 권장합니다.</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($analysis['is_exception']): ?>
                                <button onclick="toggleBoardException('<?php echo $board['bo_table']; ?>')" 
                                        style="background: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600; margin-right: 3px;">
                                    예외 처리 해제
                                </button>
                                <?php elseif ($analysis['has_guest_permissions'] && $analysis['status'] != 'safe'): ?>
                                <button onclick="toggleBoardException('<?php echo $board['bo_table']; ?>')" 
                                        style="background: #fd7e14; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600; margin-right: 3px;">
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
                                    ($analysis['status'] == 'exception' ? '예외' : 
                                    '안전'); 
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