<?php
require_once './_common.php';

$g5['title'] = '정규식 스팸 차단 설정';
include_once '../admin.head.php';
?>

<link rel="stylesheet" href="../css/gk_admin.css">

<?php

// 변수명 매핑 (기존 코드 호환성을 위해)
$regex_spam_enabled = gk_get_spam_config('regex_spam_enabled', '0');
$regex_spam_auto_block = gk_get_spam_config('regex_spam_auto_block', '1');
$regex_spam_block_duration = gk_get_spam_config('regex_spam_block_duration', '3600');
$regex_spam_ghost_mode = gk_get_spam_config('regex_spam_ghost_mode', '0');
$regex_spam_check_title = gk_get_spam_config('regex_spam_check_title', '1');
$regex_spam_check_content = gk_get_spam_config('regex_spam_check_content', '1');
$regex_spam_check_comment = gk_get_spam_config('regex_spam_check_comment', '1');
$regex_spam_check_name = gk_get_spam_config('regex_spam_check_name', '0');
$regex_spam_check_email = gk_get_spam_config('regex_spam_check_email', '0');
?>

<div class="local_ov01 local_ov">
    <?php echo $g5['title']; ?>
</div>


    <!-- 정규식 스팸 차단 설정 -->
    <div class="dashboard-section" role="region" aria-labelledby="regex-spam-heading">
        <div class="section-header" 
             role="button" 
             aria-expanded="true" 
             aria-controls="regex-spam-section"
             tabindex="0"
             onclick="toggleSection('regex-spam-section')" 
             onkeydown="handleKeyDown(event, 'regex-spam-section')">
            <span id="regex-spam-heading">🔍 정규식 스팸 차단 설정</span>
            <span id="regex-spam-toggle" aria-hidden="true">▼</span>
        </div>

        <div class="section-content expanded" id="regex-spam-section" role="group" aria-labelledby="regex-spam-heading">
            <div class="info-highlight" role="note" aria-label="기능 설명">
                정규식 패턴을 사용하여 스팸 콘텐츠를 자동으로 탐지하고 차단합니다. 제목, 내용, 댓글 등에서 유해한 키워드를 실시간으로 필터링합니다.
            </div>

            <!-- 정규식 스팸 차단 기본 설정 -->
            <div class="extension-container" role="group" aria-labelledby="regex-spam-status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="regex-spam-status-heading" style="font-weight: bold; font-size: 16px; color: #333;">정규식 스팸 차단</span>
                    <div class="feature-switch <?php echo $regex_spam_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $regex_spam_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="정규식 스팸 차단 기능 토글"
                         tabindex="0"
                         onclick="toggleRegexSpamFeature()"
                         onkeydown="handleSwitchRegexSpamKeyDown(event)"
                         data-enabled="<?php echo $regex_spam_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $regex_spam_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $regex_spam_enabled == '1' ? '✅' : '❌'; ?></span>
                        스팸 차단 <?php echo $regex_spam_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item <?php echo $regex_spam_auto_block == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $regex_spam_auto_block == '1' ? '🚫' : '⚠️'; ?></span>
                        자동 IP 차단 <?php echo $regex_spam_auto_block == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏰</span> 차단 시간: <?php echo number_format($regex_spam_block_duration/60); ?>분
                    </div>
                    <div class="extension-item <?php echo $regex_spam_check_title == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true">📝</span> 제목 검사 <?php echo $regex_spam_check_title == '1' ? 'ON' : 'OFF'; ?>
                    </div>
                    <div class="extension-item <?php echo $regex_spam_check_content == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true">📄</span> 내용 검사 <?php echo $regex_spam_check_content == '1' ? 'ON' : 'OFF'; ?>
                    </div>
                    <div class="extension-item <?php echo $regex_spam_check_comment == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true">💬</span> 댓글 검사 <?php echo $regex_spam_check_comment == '1' ? 'ON' : 'OFF'; ?>
                    </div>
                </div>
            </div>

            <!-- 정규식 스팸 설정 폼 -->
            <?php if ($regex_spam_enabled == '1'): ?>
            <div class="extension-container" role="group" aria-labelledby="regex-spam-form-heading">
                <h4 id="regex-spam-form-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">정규식 스팸 차단 설정 변경</h4>
                <form method="post" action="./security_spam_update.php" role="form" aria-labelledby="regex-spam-form-heading">
                    <input type="hidden" name="action" value="save_regex_spam_config">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: start;" role="group" aria-label="정규식 스팸 차단 설정 입력">
                        
                        <div class="form-group">
                            <label class="form-label" for="regex_spam_block_duration">차단 시간 (분)</label>
                            <input type="number" 
                                   id="regex_spam_block_duration"
                                   name="regex_spam_block_duration" 
                                   value="<?php echo $regex_spam_block_duration/60; ?>"
                                   class="form-input" 
                                   min="5" 
                                   max="1440"
                                   required>
                            <div class="form-help">스팸 탐지로 차단된 IP의 차단 시간입니다.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">검사 대상</label>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                                    <input type="checkbox" name="regex_spam_check_title" value="1" <?php echo $regex_spam_check_title == '1' ? 'checked' : ''; ?>>
                                    제목 검사
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                                    <input type="checkbox" name="regex_spam_check_content" value="1" <?php echo $regex_spam_check_content == '1' ? 'checked' : ''; ?>>
                                    내용 검사
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                                    <input type="checkbox" name="regex_spam_check_comment" value="1" <?php echo $regex_spam_check_comment == '1' ? 'checked' : ''; ?>>
                                    댓글 검사
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                                    <input type="checkbox" name="regex_spam_check_name" value="1" <?php echo $regex_spam_check_name == '1' ? 'checked' : ''; ?>>
                                    이름 검사
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                                    <input type="checkbox" name="regex_spam_check_email" value="1" <?php echo $regex_spam_check_email == '1' ? 'checked' : ''; ?>>
                                    이메일 검사
                                </label>
                            </div>
                            <div class="form-help">정규식 패턴을 검사할 대상을 선택하세요.</div>
                        </div>

                        <div style="display: flex; align-items: end; margin-top: 28px;">
                            <button type="submit" class="btn-primary">설정 저장</button>
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                            <input type="checkbox" name="regex_spam_auto_block" value="1" <?php echo $regex_spam_auto_block == '1' ? 'checked' : ''; ?>>
                            스팸 탐지 시 자동으로 IP 차단
                        </label>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 정규식 스팸 규칙 관리 -->
            <?php if ($regex_spam_enabled == '1'): ?>
            <div class="extension-container" role="group" aria-labelledby="regex-rules-heading">
                <h4 id="regex-rules-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">정규식 스팸 규칙 관리</h4>
                
                <?php
                $regex_rules_sql = "SELECT * FROM " . G5_TABLE_PREFIX . "security_regex_spam ORDER BY srs_priority ASC, srs_id ASC";
                $regex_rules_result = sql_query($regex_rules_sql, false);
                ?>

                <?php if ($regex_rules_result && sql_num_rows($regex_rules_result) > 0): ?>
                    <table class="data-table" role="table" aria-labelledby="regex-rules-heading">
                        <thead>
                            <tr role="row">
                                <th scope="col">규칙명</th>
                                <th scope="col">정규식 패턴</th>
                                <th scope="col">대상</th>
                                <th scope="col">조치</th>
                                <th scope="col">우선순위</th>
                                <th scope="col">매칭수</th>
                                <th scope="col">상태</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($rule = sql_fetch_array($regex_rules_result)): ?>
                                <tr role="row">
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($rule['srs_name']); ?></td>
                                    <td style="font-family: monospace; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($rule['srs_pattern']); ?>">
                                        <?php echo htmlspecialchars($rule['srs_pattern']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(str_replace(',', ', ', $rule['srs_target'])); ?></td>
                                    <td>
                                        <span style="padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; 
                                                     background: <?php 
                                                        echo $rule['srs_action'] == 'block' ? '#ffebee; color: #d32f2f;' : 
                                                             ($rule['srs_action'] == 'ghost' ? '#fff3e0; color: #f57c00;' : '#e8f5e8; color: #388e3c;'); 
                                                     ?>">
                                            <?php 
                                                echo $rule['srs_action'] == 'block' ? '차단' : 
                                                     ($rule['srs_action'] == 'ghost' ? '유령' : '삭제'); 
                                            ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;"><?php echo $rule['srs_priority']; ?></td>
                                    <td style="text-align: center; font-weight: bold; color: #dc3545;">
                                        <?php echo number_format($rule['srs_hit_count']); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span style="padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;
                                                     background: <?php echo $rule['srs_enabled'] ? '#e8f5e8; color: #388e3c;' : '#ffebee; color: #d32f2f;'; ?>">
                                            <?php echo $rule['srs_enabled'] ? '활성' : '비활성'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <div class="empty-icon" aria-hidden="true">📝</div>
                        <h3>정규식 스팸 규칙이 없습니다</h3>
                        <p>아직 등록된 정규식 스팸 규칙이 없습니다.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 스팸 탐지 로그 -->
            <?php if ($regex_spam_enabled == '1'): ?>
            <div class="extension-container" role="group" aria-labelledby="spam-detection-logs-heading">
                <h4 id="spam-detection-logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 스팸 탐지 기록</h4>
                
                <?php
                $spam_logs_sql = "SELECT l.*, r.srs_name 
                                  FROM " . G5_TABLE_PREFIX . "security_regex_spam_log l
                                  LEFT JOIN " . G5_TABLE_PREFIX . "security_regex_spam r ON l.srsl_srs_id = r.srs_id
                                  ORDER BY l.srsl_datetime DESC
                                  LIMIT 20";
                $spam_logs_result = sql_query($spam_logs_sql, false);
                ?>

                <?php if ($spam_logs_result && sql_num_rows($spam_logs_result) > 0): ?>
                    <table class="data-table" role="table" aria-labelledby="spam-detection-logs-heading">
                        <thead>
                            <tr role="row">
                                <th scope="col">IP 주소</th>
                                <th scope="col">규칙명</th>
                                <th scope="col">대상 유형</th>
                                <th scope="col">매칭 텍스트</th>
                                <th scope="col">조치</th>
                                <th scope="col">시간</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = sql_fetch_array($spam_logs_result)): ?>
                                <tr role="row">
                                    <td>
                                        <span class="ip-address"><?php echo htmlspecialchars($log['srsl_ip']); ?></span>
                                    </td>
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($log['srs_name'] ?: '삭제된 규칙'); ?></td>
                                    <td><?php echo htmlspecialchars($log['srsl_target_type']); ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($log['srsl_matched_text']); ?>">
                                        <?php echo htmlspecialchars($log['srsl_matched_text']); ?>
                                    </td>
                                    <td>
                                        <span style="padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;
                                                     background: <?php 
                                                        echo strpos($log['srsl_action_taken'], 'block') !== false ? '#ffebee; color: #d32f2f;' : 
                                                             (strpos($log['srsl_action_taken'], 'ghost') !== false ? '#fff3e0; color: #f57c00;' : '#e8f5e8; color: #388e3c;'); 
                                                     ?>">
                                            <?php 
                                                echo strpos($log['srsl_action_taken'], 'block') !== false ? '차단됨' : 
                                                     (strpos($log['srsl_action_taken'], 'ghost') !== false ? '유령처리' : '삭제됨'); 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <time datetime="<?php echo date('c', strtotime($log['srsl_datetime'])); ?>"><?php echo $log['srsl_datetime']; ?></time>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <div class="empty-icon" aria-hidden="true">🔍</div>
                        <h3>스팸 탐지 기록이 없습니다</h3>
                        <p>아직 정규식 스팸 탐지 기록이 없습니다.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

<script>
// Section toggle functionality
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    const toggle = document.getElementById(sectionId.replace('-section', '-toggle'));
    const header = toggle.closest('.section-header');
    
    const isExpanded = section.style.display !== 'none';
    
    if (isExpanded) {
        section.style.display = 'none';
        toggle.textContent = '▶';
        header.setAttribute('aria-expanded', 'false');
    } else {
        section.style.display = 'block';
        toggle.textContent = '▼';
        header.setAttribute('aria-expanded', 'true');
    }
}

function handleKeyDown(event, sectionId) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleSection(sectionId);
    }
}

function toggleRegexSpamFeature() {
    const toggle = document.querySelector('[onclick="toggleRegexSpamFeature()"]');
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';
    
    const formData = new FormData();
    formData.append('action', 'toggle_regex_spam');
    formData.append('regex_spam_enabled', newState);
    formData.append('ajax', '1');
    
    fetch('./security_spam_update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('success') || data.includes('성공')) {
            toggle.setAttribute('data-enabled', newState);
            if (newState === '1') {
                toggle.classList.add('enabled');
            } else {
                toggle.classList.remove('enabled');
            }
            setTimeout(() => location.reload(), 500);
        } else {
            toggle.setAttribute('aria-checked', currentState === '1' ? 'true' : 'false');
            alert('설정 변경에 실패했습니다: ' + data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toggle.setAttribute('aria-checked', currentState === '1' ? 'true' : 'false');
        alert('설정 변경 중 오류가 발생했습니다.');
    })
    .finally(() => {
        toggle.style.opacity = '1';
        toggle.style.pointerEvents = 'auto';
    });
}

function handleSwitchRegexSpamKeyDown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleRegexSpamFeature();
    }
}
</script>

<?php
include_once '../admin.tail.php';
?>

<script src="./security_spam.js"></script>