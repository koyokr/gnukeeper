<?php
require_once './_common.php';

$g5['title'] = '로그인 차단 설정';
include_once '../admin.head.php';
?>

<link rel="stylesheet" href="../css/gk_admin.css">

<?php

// 변수명 매핑 (기존 코드 호환성을 위해)
$login_attempt_limit = $login_fail_limit;
$login_attempt_window = $login_fail_window;
$auto_block_duration = $login_block_duration * 60; // 분을 초로 변환
$spam_block_level = gk_get_spam_config('spam_block_level', 'access,login');
?>

<div class="local_ov01 local_ov">
    <?php echo $g5['title']; ?>
</div>


    <!-- 로그인 차단 관리 -->
    <div class="dashboard-section" role="region" aria-labelledby="login-heading">
        <div class="section-header" 
             role="button" 
             aria-expanded="true" 
             aria-controls="login-section" 
             tabindex="0"
             onclick="toggleSection('login-section')" 
             onkeydown="handleKeyDown(event, 'login-section')">
            <span id="login-heading">🔐 로그인 차단 설정</span>
            <span id="login-toggle" aria-hidden="true">▼</span>
        </div>

        <div class="section-content expanded" id="login-section" role="group" aria-labelledby="login-heading">
            <div class="info-highlight" role="note" aria-label="기능 설명">
                gnuboard5의 로그인 실패 이벤트를 자동으로 감지하여 IP를 차단합니다.
            </div>

            <div class="extension-container" role="group" aria-labelledby="status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="status-heading" style="font-weight: bold; font-size: 16px; color: #333;">로그인 차단 기능 상태</span>
                    <div class="feature-switch <?php echo $login_block_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $login_block_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="로그인 차단 기능 토글"
                         tabindex="0"
                         onclick="toggleLoginFeature()"
                         onkeydown="handleSwitchKeyDown(event)"
                         data-enabled="<?php echo $login_block_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $login_block_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $login_block_enabled == '1' ? '✅' : '❌'; ?></span>
                        로그인 차단 <?php echo $login_block_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🔢</span> 최대 실패 횟수: <?php echo $login_attempt_limit; ?>회
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏱️</span> 감지 윈도우: <?php echo number_format($login_attempt_window); ?>분
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏰</span> 차단 시간: <?php echo $auto_block_duration == '0' ? '영구' : number_format($auto_block_duration / 60) . '분'; ?>
                    </div>
                </div>
            </div>

        <!-- 로그인 차단 설정 폼 -->
        <div class="extension-container" role="group" aria-labelledby="form-heading">
            <h3 id="form-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">설정 변경</h3>
            <form name="login_config_form" 
                  method="post" 
                  action="./security_spam_update.php" 
                  onsubmit="return validate_form();"
                  role="form"
                  aria-labelledby="form-heading">
                <input type="hidden" name="action" value="save_login_config">

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: start;" role="group" aria-label="로그인 차단 설정 입력">
                    <div class="form-group">
                        <label class="form-label" for="login_fail_limit">최대 실패 횟수</label>
                        <input type="number" 
                               id="login_fail_limit"
                               name="login_fail_limit" 
                               value="<?php echo $login_fail_limit; ?>"
                               class="form-input" 
                               min="1" 
                               max="50"
                               aria-describedby="limit-help"
                               required>
                        <div id="limit-help" class="form-help">지정된 시간 내에 이 횟수만큼 로그인에 실패하면 IP가 차단됩니다. (기본: 5회)</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login_fail_window">감지 시간 윈도우 (분)</label>
                        <input type="number" 
                               id="login_fail_window"
                               name="login_fail_window" 
                               value="<?php echo $login_fail_window; ?>"
                               class="form-input" 
                               min="1" 
                               max="1440"
                               aria-describedby="window-help"
                               required>
                        <div id="window-help" class="form-help">이 시간(분) 내의 로그인 실패 횟수를 누적하여 계산합니다. (기본: 5분)</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login_block_duration">자동 차단 시간 (분)</label>
                        <input type="number" 
                               id="login_block_duration"
                               name="login_block_duration" 
                               value="<?php echo $login_block_duration; ?>"
                               class="form-input" 
                               min="0" 
                               max="525600"
                               aria-describedby="duration-help"
                               required>
                        <div id="duration-help" class="form-help">자동 차단된 IP가 이 시간(분) 동안 접근이 제한됩니다. 0이면 영구 차단됩니다. (기본: 10분)</div>
                    </div>

                    <div style="display: flex; align-items: end; margin-top: 28px;">
                        <button type="submit" class="btn-primary" aria-describedby="save-help">설정 저장</button>
                        <div id="save-help" class="sr-only">로그인 차단 설정을 저장합니다</div>
                    </div>
                </div>
            </form>
        </div>

        <!-- 스팸 차단 수준 설정 -->
        <?php if ($login_block_enabled == '1'): ?>
        <div class="extension-container" role="group" aria-labelledby="level-heading">
            <h3 id="level-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">스팸 차단 수준 설정</h3>
            <form method="post" action="./security_spam_update.php" role="form" aria-labelledby="level-heading">
                <input type="hidden" name="action" value="save_spam_level">
                <div class="block-level-grid" role="group" aria-label="스팸 차단 수준 선택">
                    <?php
                    $spam_levels = explode(',', $spam_block_level);
                    $level_options = [
                        'access' => ['title' => '접속 차단', 'desc' => 'IP의 모든 접속을 차단', 'icon' => '🚫', 'severity' => 'high', 'exclusive' => true],
                        'login' => ['title' => '회원가입/로그인 차단', 'desc' => '로그인 시도 및 회원가입 차단', 'icon' => '🔐', 'severity' => 'medium', 'exclusive' => false],
                        'write' => ['title' => '글쓰기/문의/쪽지 차단', 'desc' => '게시글/댓글 작성, 문의, 쪽지 발송 차단', 'icon' => '✍️', 'severity' => 'medium', 'exclusive' => false]
                    ];
                    ?>
                    <?php foreach ($level_options as $level => $info): ?>
                    <div class="block-level-item <?php echo $info['severity']; ?>" role="group" aria-labelledby="<?php echo $level; ?>-label">
                        <div class="block-level-header">
                            <span class="block-level-icon" aria-hidden="true"><?php echo $info['icon']; ?></span>
                            <span id="<?php echo $level; ?>-label" class="block-level-title"><?php echo $info['title']; ?></span>
                            <div class="mini-toggle <?php echo in_array($level, $spam_levels) ? 'enabled' : ''; ?>"
                                 role="switch"
                                 aria-checked="<?php echo in_array($level, $spam_levels) ? 'true' : 'false'; ?>"
                                 aria-labelledby="<?php echo $level; ?>-label"
                                 onclick="toggleBlockLevel('<?php echo $level; ?>', this)"
                                 tabindex="0">
                                <div class="mini-toggle-handle"></div>
                                <input type="checkbox" name="spam_block_level[]" value="<?php echo $level; ?>" 
                                       <?php echo in_array($level, $spam_levels) ? 'checked' : ''; ?> style="display: none;">
                            </div>
                        </div>
                        <div class="block-level-desc"><?php echo $info['desc']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-primary">차단 수준 저장</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- 최근 로그인 실패 로그 -->
        <div class="extension-container" role="group" aria-labelledby="logs-heading">
            <h3 id="logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 로그인 실패 로그</h3>
            
            <?php
            $logs_sql = "SELECT slf_ip, slf_mb_id, slf_datetime, slf_user_agent
                         FROM " . G5_TABLE_PREFIX . "security_login_fail
                         ORDER BY slf_datetime DESC
                         LIMIT 20";
            $logs_result = sql_query($logs_sql, false);
            ?>

            <?php if ($logs_result && sql_num_rows($logs_result) > 0): ?>
                <table class="data-table" role="table" aria-labelledby="logs-heading" aria-describedby="logs-description">
                    <caption id="logs-description" class="sr-only">최근 20개의 로그인 실패 기록을 보여주는 표입니다.</caption>
                    <thead>
                        <tr role="row">
                            <th scope="col">IP 주소</th>
                            <th scope="col">시도한 ID</th>
                            <th scope="col">실패 시간</th>
                            <th scope="col">사용자 에이전트</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_count = 0; while ($log = sql_fetch_array($logs_result)): $row_count++; ?>
                            <tr role="row">
                                <td>
                                    <span class="ip-address" aria-label="IP 주소: <?php echo htmlspecialchars($log['slf_ip']); ?>"><?php echo htmlspecialchars($log['slf_ip']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['slf_mb_id'] ?: '(없음)'); ?></strong>
                                </td>
                                <td>
                                    <time datetime="<?php echo date('c', strtotime($log['slf_datetime'])); ?>"><?php echo $log['slf_datetime']; ?></time>
                                </td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                    title="사용자 에이전트: <?php echo htmlspecialchars($log['slf_user_agent']); ?>"
                                    aria-label="사용자 에이전트: <?php echo htmlspecialchars($log['slf_user_agent']); ?>">
                                    <?php echo htmlspecialchars($log['slf_user_agent']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="sr-only" aria-live="polite">
                    총 <?php echo $row_count; ?>개의 로그인 실패 기록이 표시되었습니다.
                </div>
            <?php else: ?>
                <div class="empty-state" role="status" aria-live="polite">
                    <div class="empty-icon" aria-hidden="true">📝</div>
                    <h3>로그인 실패 기록이 없습니다</h3>
                    <p>아직 로그인 실패 기록이 없습니다. 시스템이 자동으로 실패 시도를 감지하고 기록합니다.</p>
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

function handleSwitchKeyDown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleLoginFeature();
    }
}

function toggleLoginFeature() {
    const toggle = document.querySelector('.feature-switch');
    const enabled = toggle.dataset.enabled === '1';
    const newState = enabled ? '0' : '1';

    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';

    fetch('./security_spam_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_login&login_block_enabled=${newState}&ajax=1`
    })
    .then(response => response.text())
    .then(result => {
        if (result.includes('success') || result.includes('성공')) {
            toggle.dataset.enabled = newState;
            if (newState === '1') {
                toggle.classList.add('enabled');
            } else {
                toggle.classList.remove('enabled');
            }
            setTimeout(() => location.reload(), 500);
        } else {
            toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
            alert('설정 변경에 실패했습니다: ' + result);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toggle.setAttribute('aria-checked', enabled ? 'true' : 'false');
        alert('설정 변경 중 오류가 발생했습니다.');
    })
    .finally(() => {
        toggle.style.opacity = '1';
        toggle.style.pointerEvents = 'auto';
    });
}

function validate_form() {
    var limit = document.querySelector('input[name="login_fail_limit"]').value;
    var window_time = document.querySelector('input[name="login_fail_window"]').value;
    var block_duration = document.querySelector('input[name="login_block_duration"]').value;

    if (limit < 1 || limit > 50) {
        alert('최대 실패 횟수는 1~50 사이의 값이어야 합니다.');
        return false;
    }

    if (window_time < 1 || window_time > 1440) {
        alert('감지 시간 윈도우는 1분~1440분(24시간) 사이의 값이어야 합니다.');
        return false;
    }

    if (block_duration < 0 || block_duration > 525600) {
        alert('자동 차단 시간은 0분~525600분(1년) 사이의 값이어야 합니다.');
        return false;
    }

    return true;
}

function toggleBlockLevel(levelId, toggleElement) {
    const checkbox = toggleElement.querySelector('input[type="checkbox"]');
    const isCurrentlyEnabled = toggleElement.classList.contains('enabled');
    
    if (isCurrentlyEnabled) {
        // 토글 해제
        toggleElement.classList.remove('enabled');
        toggleElement.setAttribute('aria-checked', 'false');
        checkbox.checked = false;
    } else {
        // 토글 활성화
        toggleElement.classList.add('enabled');
        toggleElement.setAttribute('aria-checked', 'true');
        checkbox.checked = true;
        
        // 접속 차단이 활성화되면 다른 모든 차단을 해제
        if (levelId === 'access') {
            disableOtherLevels(['login', 'write']);
        }
        // 다른 차단이 활성화되면 접속 차단을 해제
        else if (levelId === 'login' || levelId === 'write') {
            disableOtherLevels(['access']);
        }
    }
    
    toggleElement.style.transform = 'scale(0.95)';
    setTimeout(() => {
        toggleElement.style.transform = 'scale(1)';
    }, 150);
}

function disableOtherLevels(levelIds) {
    levelIds.forEach(levelId => {
        const otherToggle = document.querySelector(`input[value="${levelId}"]`).closest('.mini-toggle');
        const otherCheckbox = otherToggle.querySelector('input[type="checkbox"]');
        
        if (otherToggle.classList.contains('enabled')) {
            otherToggle.classList.remove('enabled');
            otherToggle.setAttribute('aria-checked', 'false');
            otherCheckbox.checked = false;
        }
    });
}
</script>

<?php
include_once '../admin.tail.php';
?>

<script src="./security_spam.js"></script>