<?php
// 캡챠 적용 정책 섹션
if (!defined('_GNUBOARD_')) exit;

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

// 모든 게시판 캡챠 정책 현황 조회
$captcha_boards_sql = "SELECT bo_table, bo_subject, bo_use_captcha, bo_list_level, bo_read_level, bo_write_level, bo_reply_level, bo_comment_level FROM {$g5['board_table']} ORDER BY bo_table";
$captcha_boards_result = sql_query($captcha_boards_sql);

$captcha_boards = array();
$risk_count = 0;
while ($board = sql_fetch_array($captcha_boards_result)) {
    $board['is_exception'] = in_array($board['bo_table'], $captcha_exception_list);
    $board['has_captcha'] = $board['bo_use_captcha'] == 1;
    
    // 캡챠가 적용되지 않고 예외처리도 되지 않은 경우만 위험으로 카운트
    if (!$board['has_captcha'] && !$board['is_exception']) {
        $risk_count++;
    }
    $captcha_boards[] = $board;
}

if (!$global_captcha) {
    $risk_count++; // 전역 캡차가 비활성화된 경우 위험도 증가
}
?>

<!-- 캡챠 적용 정책 -->
<div class="dashboard-section">
    <div class="section-header" onclick="toggleSection('captcha-policy-section')" style="cursor: pointer;">
        🤖 캡챠 적용 정책 <span id="captcha-policy-toggle" style="float: right; transition: transform 0.3s ease;">▶</span>
    </div>
    <div class="section-content" id="captcha-policy-section">
        <div class="info-highlight">
            회원가입 및 주요 기능에서 캡챠 적용 여부를 확인하여 자동화 공격 위험을 관리합니다.
        </div>
        
        <?php if (count($captcha_boards) > 0): ?>
        <div class="extension-container">
            <div style="margin-bottom: 15px;">
                <span style="font-weight: bold; font-size: 16px; color: #333;">게시판별 캡챠 적용 정책 현황</span>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($captcha_boards as $board): ?>
                <?php 
                // 캡챠 섹션에서는 캡챠 적용 여부만 판단
                $captcha_status = $board['is_exception'] ? 'exception' : ($board['has_captcha'] ? 'safe' : 'danger');
                ?>
                <div style="background: <?php 
                            echo $board['is_exception'] ? '#fffbeb' : 
                                ($captcha_status == 'safe' ? '#f0f9f0' : '#fef2f2'); 
                           ?>; 
                           border: 1px solid #e2e8f0; 
                           border-radius: 8px; padding: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <span style="font-weight: bold; font-size: 16px; color: #333;">
                                    <?php if ($board['is_exception']): ?>⚙️<?php elseif ($captcha_status == 'safe'): ?>✅<?php else: ?>🚨<?php endif; ?> <?php echo htmlspecialchars($board['bo_subject']); ?>
                                </span>
                                <span style="background: #f7fafc; color: #718096; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo $board['bo_table']; ?>
                                </span>
                            </div>
                            
                            <?php if ($captcha_status == 'exception'): ?>
                            <div style="background: #fff3cd; color: #856404; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                                이 게시판은 예외 처리되어 캡챠 검사에서 제외됩니다. 관리자가 의도적으로 설정한 특별한 용도의 게시판입니다.
                            </div>
                            <?php elseif ($captcha_status == 'safe'): ?>
                            <div style="background: #e8f5e8; color: #0c5460; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                                이 게시판은 캡챠 인증이 적용되어 있어 스팸 및 자동화 공격으로부터 보호되고 있습니다.
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
                                <?php elseif ($board['has_captcha']): ?>
                                <!-- 캡챠가 적용된 안전한 게시판은 버튼 없음 -->
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
                                       background: <?php 
                                       echo $captcha_status == 'exception' ? '#fd7e14' : 
                                           ($captcha_status == 'safe' ? '#059669' : '#dc2626'); 
                                       ?>;">
                                <?php 
                                echo $captcha_status == 'exception' ? '예외' : 
                                    ($captcha_status == 'safe' ? '안전' : '위험'); 
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