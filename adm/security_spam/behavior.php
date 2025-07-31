<?php
require_once './_common.php';

$g5['title'] = '이상 행위 차단 설정';
include_once '../admin.head.php';
?>

<link rel="stylesheet" href="../css/gk_admin.css">

<?php

// 변수명 매핑 (기존 코드 호환성을 위해)
$behavior_404_enabled = gk_get_spam_config('behavior_404_enabled', '0');
$behavior_404_limit = gk_get_spam_config('behavior_404_limit', '10');
$behavior_404_window = gk_get_spam_config('behavior_404_window', '300');
$behavior_404_block_duration = gk_get_spam_config('behavior_404_block_duration', '1800');
$behavior_referer_enabled = gk_get_spam_config('behavior_referer_enabled', '0');
$behavior_referer_block_duration = gk_get_spam_config('behavior_referer_block_duration', '3600');
?>

<div class="local_ov01 local_ov">
    <?php echo $g5['title']; ?>
</div>


    <!-- 이상 행위 차단 설정 -->
    <div class="dashboard-section" role="region" aria-labelledby="behavior-heading">
        <div class="section-header" 
             role="button" 
             aria-expanded="true" 
             aria-controls="behavior-section"
             tabindex="0"
             onclick="toggleSection('behavior-section')" 
             onkeydown="handleKeyDown(event, 'behavior-section')">
            <span id="behavior-heading">🚨 이상 행위 차단 설정</span>
            <span id="behavior-toggle" aria-hidden="true">▼</span>
        </div>

        <div class="section-content expanded" id="behavior-section" role="group" aria-labelledby="behavior-heading">
            <div class="info-highlight" role="note" aria-label="기능 설명">
                404 페이지 과다 접속과 비정상 레퍼러를 감지하여 IP를 자동 차단합니다.
            </div>

            <!-- 404 차단 설정 -->
            <div class="extension-container" role="group" aria-labelledby="404-status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="404-status-heading" style="font-weight: bold; font-size: 16px; color: #333;">404 페이지 과다 접속 차단</span>
                    <div class="feature-switch <?php echo $behavior_404_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $behavior_404_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="404 차단 기능 토글"
                         tabindex="0"
                         onclick="toggle404Feature()"
                         onkeydown="handleSwitch404KeyDown(event)"
                         data-enabled="<?php echo $behavior_404_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $behavior_404_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $behavior_404_enabled == '1' ? '✅' : '❌'; ?></span>
                        404 차단 <?php echo $behavior_404_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🔢</span> 최대 접속 횟수: <?php echo $behavior_404_limit; ?>회
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏱️</span> 감지 윈도우: <?php echo number_format($behavior_404_window); ?>초
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏰</span> 차단 시간: <?php echo number_format($behavior_404_block_duration); ?>초
                    </div>
                </div>
            </div>

            <!-- 404 차단 설정 폼 -->
            <?php if ($behavior_404_enabled == '1'): ?>
            <div class="extension-container" role="group" aria-labelledby="404-form-heading">
                <h4 id="404-form-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">404 차단 설정 변경</h4>
                <form method="post" action="./security_spam_update.php" role="form" aria-labelledby="404-form-heading">
                    <input type="hidden" name="action" value="save_404_config">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: start;" role="group" aria-label="404 차단 설정 입력">
                        <div class="form-group">
                            <label class="form-label" for="behavior_404_limit">최대 접속 횟수</label>
                            <input type="number" 
                                   id="behavior_404_limit"
                                   name="behavior_404_limit" 
                                   value="<?php echo $behavior_404_limit; ?>"
                                   class="form-input" 
                                   min="1" 
                                   max="100"
                                   required>
                            <div class="form-help">지정된 시간 내에 이 횟수만큼 404 페이지에 접속하면 IP가 차단됩니다.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="behavior_404_window">감지 시간 윈도우 (초)</label>
                            <input type="number" 
                                   id="behavior_404_window"
                                   name="behavior_404_window" 
                                   value="<?php echo $behavior_404_window; ?>"
                                   class="form-input" 
                                   min="60" 
                                   max="86400"
                                   required>
                            <div class="form-help">이 시간(초) 내의 404 접속 횟수를 누적하여 계산합니다.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="behavior_404_block_duration">차단 시간 (초)</label>
                            <input type="number" 
                                   id="behavior_404_block_duration"
                                   name="behavior_404_block_duration" 
                                   value="<?php echo $behavior_404_block_duration; ?>"
                                   class="form-input" 
                                   min="300" 
                                   max="86400"
                                   required>
                            <div class="form-help">404 과다 접속으로 차단된 IP의 차단 시간입니다.</div>
                        </div>

                        <div style="display: flex; align-items: end; margin-top: 28px;">
                            <button type="submit" class="btn-primary">설정 저장</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 레퍼러 검증 설정 -->
            <div class="extension-container" role="group" aria-labelledby="referer-status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="referer-status-heading" style="font-weight: bold; font-size: 16px; color: #333;">비정상 레퍼러 차단</span>
                    <div class="feature-switch <?php echo $behavior_referer_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $behavior_referer_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="레퍼러 검증 기능 토글"
                         tabindex="0"
                         onclick="toggleRefererFeature()"
                         onkeydown="handleSwitchRefererKeyDown(event)"
                         data-enabled="<?php echo $behavior_referer_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $behavior_referer_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $behavior_referer_enabled == '1' ? '✅' : '❌'; ?></span>
                        레퍼러 검증 <?php echo $behavior_referer_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🎯</span> 검증 대상: login_check.php, register_form_update.php 등
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏰</span> 차단 시간: <?php echo number_format($behavior_referer_block_duration); ?>초
                    </div>
                </div>
            </div>

            <!-- 404 접속 로그 -->
            <div class="extension-container" role="group" aria-labelledby="404-logs-heading">
                <h4 id="404-logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 404 접속 기록</h4>
                
                <?php
                $logs_404_sql = "SELECT sl4_ip, sl4_url, sl4_datetime, sl4_user_agent
                                 FROM " . G5_TABLE_PREFIX . "security_404_log
                                 ORDER BY sl4_datetime DESC
                                 LIMIT 20";
                $logs_404_result = sql_query($logs_404_sql, false);
                ?>

                <?php if ($logs_404_result && sql_num_rows($logs_404_result) > 0): ?>
                    <table class="data-table" role="table" aria-labelledby="404-logs-heading">
                        <thead>
                            <tr role="row">
                                <th scope="col">IP 주소</th>
                                <th scope="col">요청 URL</th>
                                <th scope="col">접속 시간</th>
                                <th scope="col">User-Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = sql_fetch_array($logs_404_result)): ?>
                                <tr role="row">
                                    <td>
                                        <span class="ip-address"><?php echo htmlspecialchars($log['sl4_ip']); ?></span>
                                    </td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($log['sl4_url']); ?>">
                                        <?php echo htmlspecialchars($log['sl4_url']); ?>
                                    </td>
                                    <td>
                                        <time datetime="<?php echo date('c', strtotime($log['sl4_datetime'])); ?>"><?php echo $log['sl4_datetime']; ?></time>
                                    </td>
                                    <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($log['sl4_user_agent']); ?>">
                                        <?php echo htmlspecialchars($log['sl4_user_agent']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <div class="empty-icon" aria-hidden="true">📋</div>
                        <h3>404 접속 기록이 없습니다</h3>
                        <p>아직 404 페이지 접속 기록이 없습니다.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 레퍼러 검증 로그 -->
            <div class="extension-container" role="group" aria-labelledby="referer-logs-heading">
                <h4 id="referer-logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 레퍼러 검증 기록</h4>
                
                <?php
                $referer_logs_sql = "SELECT srl_ip, srl_url, srl_expected_referer, srl_actual_referer, srl_datetime
                                     FROM " . G5_TABLE_PREFIX . "security_referer_log
                                     ORDER BY srl_datetime DESC
                                     LIMIT 10";
                $referer_logs_result = sql_query($referer_logs_sql, false);
                ?>

                <?php if ($referer_logs_result && sql_num_rows($referer_logs_result) > 0): ?>
                    <table class="data-table" role="table" aria-labelledby="referer-logs-heading">
                        <thead>
                            <tr role="row">
                                <th scope="col">IP 주소</th>
                                <th scope="col">요청 URL</th>
                                <th scope="col">예상 레퍼러</th>
                                <th scope="col">실제 레퍼러</th>
                                <th scope="col">시간</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = sql_fetch_array($referer_logs_result)): ?>
                                <tr role="row">
                                    <td>
                                        <span class="ip-address"><?php echo htmlspecialchars($log['srl_ip']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars(basename($log['srl_url'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['srl_expected_referer']); ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($log['srl_actual_referer']); ?>">
                                        <?php echo htmlspecialchars($log['srl_actual_referer'] ?: '(없음)'); ?>
                                    </td>
                                    <td>
                                        <time datetime="<?php echo date('c', strtotime($log['srl_datetime'])); ?>"><?php echo $log['srl_datetime']; ?></time>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <div class="empty-icon" aria-hidden="true">🔗</div>
                        <h3>레퍼러 검증 기록이 없습니다</h3>
                        <p>아직 비정상 레퍼러 감지 기록이 없습니다.</p>
                    </div>
                <?php endif; ?>
            </div>
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

function toggle404Feature() {
    const toggle = document.querySelector('[onclick="toggle404Feature()"]');
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';
    
    const formData = new FormData();
    formData.append('action', 'toggle_404');
    formData.append('behavior_404_enabled', newState);
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

function toggleRefererFeature() {
    const toggle = document.querySelector('[onclick="toggleRefererFeature()"]');
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';
    
    const formData = new FormData();
    formData.append('action', 'toggle_referer');
    formData.append('behavior_referer_enabled', newState);
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

function handleSwitch404KeyDown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggle404Feature();
    }
}

function handleSwitchRefererKeyDown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleRefererFeature();
    }
}
</script>

<?php
include_once '../admin.tail.php';
?>

<script src="./security_spam.js"></script>