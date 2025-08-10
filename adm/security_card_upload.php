<?php
// 업로드 용량 정책 카드
if (!defined('_GNUBOARD_')) exit;

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

// 전체 기본 업로드 크기 확인
$global_config_sql = "SELECT cf_upload_size FROM {$g5['config_table']}";
$global_config_result = sql_fetch($global_config_sql);
$global_upload_size = isset($global_config_result['cf_upload_size']) ? $global_config_result['cf_upload_size'] : 0;
$global_is_high_risk = $global_upload_size >= $upload_risk_threshold;

// 모든 게시판 업로드 크기 조회
$upload_boards_sql = "SELECT bo_table, bo_subject, bo_upload_size FROM {$g5['board_table']} WHERE bo_use_file_upload = 1 ORDER BY bo_table";
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

// 전체 위험 카운트
$total_risk_items = $high_risk_count;
if ($global_is_high_risk) {
    $total_risk_items++;
}
?>

<!-- 업로드 용량 정책 -->
<div class="card">
    <div class="card-header" onclick="toggleCard('upload-policy-section')">
        📁 업로드 용량 정책 <span id="upload-policy-toggle">▶</span>
    </div>
    <div class="card-content" id="upload-policy-section">
        <div class="info-highlight">
            업로드 가능한 파일 용량이 과도하게 높아 서버 자원 고갈 위험을 확인하고 관리합니다.
        </div>

        <?php if ($global_is_high_risk): ?>
        <div class="warning-container">
            <div style="color: #dc2626; font-weight: bold; margin-bottom: 8px;">⚠️ 전역 업로드 크기 위험</div>
            <div class="text-muted-sm">
                전체 사이트의 기본 업로드 크기가 <?php echo number_format($global_upload_size / 1024 / 1024, 1); ?>MB로 설정되어 있습니다.
                이는 서버 자원 고갈 및 디스크 공간 부족을 야기할 수 있습니다.
            </div>
        </div>
        <?php endif; ?>

        <?php if (count($upload_boards) > 0): ?>
        <div class="extension-container">
            <div style="margin-bottom: 15px;">
                <span class="text-title-bold">게시판별 업로드 용량 정책 현황</span>
                <?php if ($high_risk_count > 0): ?>
                <span class="badge-danger">
                    위험 <?php echo $high_risk_count; ?>개
                </span>
                <?php endif; ?>
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
                                <span class="text-title-bold">
                                    <?php if ($board['is_exception']): ?>⚙️<?php elseif ($board['is_high_risk']): ?>🚨<?php else: ?>✅<?php endif; ?> <?php echo htmlspecialchars($board['bo_subject']); ?>
                                </span>
                                <span class="badge-gray">
                                    <?php echo $board['bo_table']; ?>
                                </span>
                                <span class="badge-info">
                                    <?php echo number_format($board['bo_upload_size'] / 1024 / 1024, 1); ?>MB
                                </span>
                            </div>

                            <?php if ($board['is_exception']): ?>
                            <div class="alert-warning-sm">
                                이 게시판은 예외 처리되어 보안 검사에서 제외됩니다. 관리자가 의도적으로 설정한 특별한 용도의 게시판입니다.
                            </div>
                            <?php elseif ($board['is_high_risk']): ?>
                            <div class="alert-danger-sm">
                                이 게시판의 업로드 크기가 <?php echo number_format($upload_risk_threshold / 1024 / 1024); ?>MB 이상으로 설정되어 서버 자원 고갈 위험이 있습니다.
                            </div>
                            <?php else: ?>
                            <div class="alert-success-sm">
                                이 게시판은 안전한 업로드 크기로 설정되어 있습니다. 서버 자원을 효율적으로 사용하고 있습니다.
                            </div>
                            <?php endif; ?>

                            <div style="margin-top: 10px;">
                                <?php if ($board['is_exception']): ?>
                                <button onclick="toggleUploadException('<?php echo $board['bo_table']; ?>')"
                                        class="btn-secondary-sm">
                                    예외 처리 해제
                                </button>
                                <?php elseif ($board['is_high_risk']): ?>
                                <button onclick="limitUpload10MB('<?php echo $board['bo_table']; ?>')"
                                        class="btn-success-sm" style="margin-right: 6px;">
                                    10MB로 제한
                                </button>
                                <button onclick="toggleUploadException('<?php echo $board['bo_table']; ?>')"
                                        class="btn-warning-sm">
                                    예외 처리
                                </button>
                                <?php else: ?>
                                <!-- 안전한 게시판은 버튼 없음 -->
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="margin-left: 15px;">
                            <span class="badge-primary" style="background: <?php
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
        <div class="success-container center-container" style="padding: 50px 20px; margin: 0 20px 20px 20px;">
            <p class="success-message-lg">
                ✅ 업로드가 허용된 게시판이 없습니다.
            </p>
            <p class="success-message-md">
                현재 파일 업로드 위험이 없는 상태입니다.
            </p>
        </div>
        <?php endif; ?>

        <!-- 보안 가이드 -->
        <div class="recommendations">
            <h4>업로드 용량 보안 가이드</h4>
            <ul>
                <li><strong>1.</strong> 게시판별 업로드 크기는 필요에 따라 최소한으로 설정하세요</li>
                <li><strong>2.</strong> 일반적으로 이미지 게시판은 5-10MB, 자료실은 50MB 이하를 권장합니다</li>
                <li><strong>3.</strong> 100MB 이상의 대용량 업로드는 서버 자원 부족을 야기할 수 있습니다</li>
                <li><strong>4.</strong> 정기적으로 디스크 사용량을 모니터링하고 불필요한 파일을 정리하세요</li>
            </ul>
        </div>
    </div>
</div>