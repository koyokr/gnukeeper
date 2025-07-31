<?php
require_once './_common.php';

$g5['title'] = '다중 계정 차단 설정';
include_once '../admin.head.php';
?>

<link rel="stylesheet" href="../css/gk_admin.css">

<?php

// 변수명 매핑 (기존 코드 호환성을 위해)
$multiuser_register_enabled = gk_get_spam_config('multiuser_register_enabled', '0');
$multiuser_register_limit = gk_get_spam_config('multiuser_register_limit', '3');
$multiuser_register_window = gk_get_spam_config('multiuser_register_window', '86400');
$multiuser_register_block_duration = gk_get_spam_config('multiuser_register_block_duration', '3600');
$multiuser_login_enabled = gk_get_spam_config('multiuser_login_enabled', '0');
$multiuser_login_limit = gk_get_spam_config('multiuser_login_limit', '5');
$multiuser_login_window = gk_get_spam_config('multiuser_login_window', '86400');
$multiuser_login_block_duration = gk_get_spam_config('multiuser_login_block_duration', '1800');
?>

<div class="local_ov01 local_ov">
    <?php echo $g5['title']; ?>
</div>


    <!-- 다중 계정 차단 설정 -->
    <div class="dashboard-section" role="region" aria-labelledby="multiuser-heading">
        <div class="section-header" 
             role="button" 
             aria-expanded="true" 
             aria-controls="multiuser-section"
             tabindex="0"
             onclick="toggleSection('multiuser-section')" 
             onkeydown="handleKeyDown(event, 'multiuser-section')">
            <span id="multiuser-heading">👥 다중 계정 차단 설정</span>
            <span id="multiuser-toggle" aria-hidden="true">▼</span>
        </div>

        <div class="section-content expanded" id="multiuser-section" role="group" aria-labelledby="multiuser-heading">
            <div class="info-highlight" role="note" aria-label="기능 설명">
                하루 내에 같은 IP에서 여러 회원가입이나 다중 로그인을 감지하여 IP를 자동 차단합니다.
            </div>

            <!-- 회원가입 차단 설정 -->
            <div class="extension-container" role="group" aria-labelledby="register-status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="register-status-heading" style="font-weight: bold; font-size: 16px; color: #333;">다중 회원가입 차단</span>
                    <div class="feature-switch <?php echo $multiuser_register_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $multiuser_register_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="회원가입 차단 기능 토글"
                         tabindex="0"
                         onclick="toggleRegisterFeature()"
                         onkeydown="handleSwitchRegisterKeyDown(event)"
                         data-enabled="<?php echo $multiuser_register_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $multiuser_register_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $multiuser_register_enabled == '1' ? '✅' : '❌'; ?></span>
                        회원가입 차단 <?php echo $multiuser_register_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🔢</span> 최대 가입 수: <?php echo $multiuser_register_limit; ?>개
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏱️</span> 감지 윈도우: <?php echo number_format($multiuser_register_window/3600, 1); ?>시간
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏰</span> 차단 시간: <?php echo number_format($multiuser_register_block_duration/60); ?>분
                    </div>
                </div>
            </div>

            <!-- 회원가입 차단 설정 폼 -->
            <?php if ($multiuser_register_enabled == '1'): ?>
            <div class="extension-container" role="group" aria-labelledby="register-form-heading">
                <h4 id="register-form-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">회원가입 차단 설정 변경</h4>
                <form method="post" action="./security_spam_update.php" role="form" aria-labelledby="register-form-heading">
                    <input type="hidden" name="action" value="save_register_config">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: start;" role="group" aria-label="회원가입 차단 설정 입력">
                        <div class="form-group">
                            <label class="form-label" for="multiuser_register_limit">최대 가입 수</label>
                            <input type="number" 
                                   id="multiuser_register_limit"
                                   name="multiuser_register_limit" 
                                   value="<?php echo $multiuser_register_limit; ?>"
                                   class="form-input" 
                                   min="1" 
                                   max="20"
                                   required>
                            <div class="form-help">하루 내에 이 수만큼 가입하면 IP가 차단됩니다.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="multiuser_register_window">감지 시간 윈도우 (시간)</label>
                            <input type="number" 
                                   id="multiuser_register_window"
                                   name="multiuser_register_window" 
                                   value="<?php echo $multiuser_register_window/3600; ?>"
                                   class="form-input" 
                                   min="1" 
                                   max="168"
                                   step="0.1"
                                   required>
                            <div class="form-help">이 시간(시간) 내의 가입 수를 누적하여 계산합니다.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="multiuser_register_block_duration">차단 시간 (분)</label>
                            <input type="number" 
                                   id="multiuser_register_block_duration"
                                   name="multiuser_register_block_duration" 
                                   value="<?php echo $multiuser_register_block_duration/60; ?>"
                                   class="form-input" 
                                   min="5" 
                                   max="1440"
                                   required>
                            <div class="form-help">다중 가입으로 차단된 IP의 차단 시간입니다.</div>
                        </div>

                        <div style="display: flex; align-items: end; margin-top: 28px;">
                            <button type="submit" class="btn-primary">설정 저장</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 로그인 차단 설정 -->
            <div class="extension-container" role="group" aria-labelledby="login-multi-status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="login-multi-status-heading" style="font-weight: bold; font-size: 16px; color: #333;">다중 로그인 차단</span>
                    <div class="feature-switch <?php echo $multiuser_login_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $multiuser_login_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="로그인 차단 기능 토글"
                         tabindex="0"
                         onclick="toggleLoginMultiFeature()"
                         onkeydown="handleSwitchLoginMultiKeyDown(event)"
                         data-enabled="<?php echo $multiuser_login_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $multiuser_login_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $multiuser_login_enabled == '1' ? '✅' : '❌'; ?></span>
                        로그인 차단 <?php echo $multiuser_login_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🔢</span> 최대 로그인 수: <?php echo $multiuser_login_limit; ?>개
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏱️</span> 감지 윈도우: <?php echo number_format($multiuser_login_window/3600, 1); ?>시간
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">⏰</span> 차단 시간: <?php echo number_format($multiuser_login_block_duration/60); ?>분
                    </div>
                </div>
            </div>

            <!-- 로그인 차단 설정 폼 -->
            <?php if ($multiuser_login_enabled == '1'): ?>
            <div class="extension-container" role="group" aria-labelledby="login-multi-form-heading">
                <h4 id="login-multi-form-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">로그인 차단 설정 변경</h4>
                <form method="post" action="./security_spam_update.php" role="form" aria-labelledby="login-multi-form-heading">
                    <input type="hidden" name="action" value="save_login_multi_config">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: start;" role="group" aria-label="로그인 차단 설정 입력">
                        <div class="form-group">
                            <label class="form-label" for="multiuser_login_limit">최대 로그인 수</label>
                            <input type="number" 
                                   id="multiuser_login_limit"
                                   name="multiuser_login_limit" 
                                   value="<?php echo $multiuser_login_limit; ?>"
                                   class="form-input" 
                                   min="2" 
                                   max="50"
                                   required>
                            <div class="form-help">하루 내에 다른 계정으로 이 수만큼 로그인하면 차단됩니다.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="multiuser_login_window">감지 시간 윈도우 (시간)</label>
                            <input type="number" 
                                   id="multiuser_login_window"
                                   name="multiuser_login_window" 
                                   value="<?php echo $multiuser_login_window/3600; ?>"
                                   class="form-input" 
                                   min="1" 
                                   max="168"
                                   step="0.1"
                                   required>
                            <div class="form-help">이 시간(시간) 내의 로그인 수를 누적하여 계산합니다.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="multiuser_login_block_duration">차단 시간 (분)</label>
                            <input type="number" 
                                   id="multiuser_login_block_duration"
                                   name="multiuser_login_block_duration" 
                                   value="<?php echo $multiuser_login_block_duration/60; ?>"
                                   class="form-input" 
                                   min="5" 
                                   max="1440"
                                   required>
                            <div class="form-help">다중 로그인으로 차단된 IP의 차단 시간입니다.</div>
                        </div>

                        <div style="display: flex; align-items: end; margin-top: 28px;">
                            <button type="submit" class="btn-primary">설정 저장</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 회원가입 로그 -->
            <div class="extension-container" role="group" aria-labelledby="register-logs-heading">
                <h4 id="register-logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 회원가입 기록</h4>
                
                <?php
                $register_logs_sql = "SELECT srl_ip, srl_mb_id, srl_mb_email, srl_datetime
                                      FROM " . G5_TABLE_PREFIX . "security_register_log
                                      ORDER BY srl_datetime DESC
                                      LIMIT 20";
                $register_logs_result = sql_query($register_logs_sql, false);
                ?>

                <?php if ($register_logs_result && sql_num_rows($register_logs_result) > 0): ?>
                    <table class="data-table" role="table" aria-labelledby="register-logs-heading">
                        <thead>
                            <tr role="row">
                                <th scope="col">IP 주소</th>
                                <th scope="col">회원 ID</th>
                                <th scope="col">이메일</th>
                                <th scope="col">가입 시간</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = sql_fetch_array($register_logs_result)): ?>
                                <tr role="row">
                                    <td>
                                        <span class="ip-address"><?php echo htmlspecialchars($log['srl_ip']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['srl_mb_id']); ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($log['srl_mb_email']); ?>">
                                        <?php echo htmlspecialchars($log['srl_mb_email']); ?>
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
                        <div class="empty-icon" aria-hidden="true">📝</div>
                        <h3>회원가입 기록이 없습니다</h3>
                        <p>아직 회원가입 기록이 없습니다.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 로그인 로그 -->
            <div class="extension-container" role="group" aria-labelledby="login-success-logs-heading">
                <h4 id="login-success-logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 로그인 성공 기록</h4>
                
                <?php
                $login_logs_sql = "SELECT sls_ip, sls_mb_id, sls_datetime
                                   FROM " . G5_TABLE_PREFIX . "security_login_success_log
                                   ORDER BY sls_datetime DESC
                                   LIMIT 20";
                $login_logs_result = sql_query($login_logs_sql, false);
                ?>

                <?php if ($login_logs_result && sql_num_rows($login_logs_result) > 0): ?>
                    <table class="data-table" role="table" aria-labelledby="login-success-logs-heading">
                        <thead>
                            <tr role="row">
                                <th scope="col">IP 주소</th>
                                <th scope="col">회원 ID</th>
                                <th scope="col">로그인 시간</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = sql_fetch_array($login_logs_result)): ?>
                                <tr role="row">
                                    <td>
                                        <span class="ip-address"><?php echo htmlspecialchars($log['sls_ip']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['sls_mb_id']); ?></td>
                                    <td>
                                        <time datetime="<?php echo date('c', strtotime($log['sls_datetime'])); ?>"><?php echo $log['sls_datetime']; ?></time>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <div class="empty-icon" aria-hidden="true">🔐</div>
                        <h3>로그인 성공 기록이 없습니다</h3>
                        <p>아직 로그인 성공 기록이 없습니다.</p>
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

function toggleRegisterFeature() {
    const toggle = document.querySelector('[onclick="toggleRegisterFeature()"]');
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';
    
    const formData = new FormData();
    formData.append('action', 'toggle_register');
    formData.append('multiuser_register_enabled', newState);
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

function toggleLoginMultiFeature() {
    const toggle = document.querySelector('[onclick="toggleLoginMultiFeature()"]');
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';
    
    const formData = new FormData();
    formData.append('action', 'toggle_login_multi');
    formData.append('multiuser_login_enabled', newState);
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

function handleSwitchRegisterKeyDown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleRegisterFeature();
    }
}

function handleSwitchLoginMultiKeyDown(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleLoginMultiFeature();
    }
}
</script>

<?php
include_once '../admin.tail.php';
?>

<script src="./security_spam.js"></script>