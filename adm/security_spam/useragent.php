<?php
require_once './_common.php';

$g5['title'] = 'User-Agent 차단 설정';
include_once '../admin.head.php';
?>

<link rel="stylesheet" href="../css/gk_admin.css">

<?php

// 변수명 매핑 (기존 코드 호환성을 위해)
$useragent_block_enabled = gk_get_spam_config('useragent_block_enabled', '0');
$useragent_block_level = gk_get_spam_config('useragent_block_level', 'access');
$spam_block_level = gk_get_spam_config('spam_block_level', 'access,login');

// 차단 수준 옵션
$level_options = [
    'access' => ['title' => '접속 차단', 'desc' => 'IP의 모든 접속을 차단', 'icon' => '🚫', 'severity' => 'high', 'exclusive' => true],
    'login' => ['title' => '회원가입/로그인 차단', 'desc' => '로그인 시도 및 회원가입 차단', 'icon' => '🔐', 'severity' => 'medium', 'exclusive' => false],
    'write' => ['title' => '글쓰기/문의/쪽지 차단', 'desc' => '게시글/댓글 작성, 문의, 쪽지 발송 차단', 'icon' => '✍️', 'severity' => 'medium', 'exclusive' => false]
];
?>

<div class="local_ov01 local_ov">
    <?php echo $g5['title']; ?>
</div>


    <!-- User-Agent 차단 설정 -->
    <div class="dashboard-section" role="region" aria-labelledby="useragent-heading">
        <div class="section-header" 
             role="button" 
             aria-expanded="true" 
             aria-controls="useragent-section"
             tabindex="0"
             onclick="toggleSection('useragent-section')" 
             onkeydown="handleKeyDown(event, 'useragent-section')">
            <span id="useragent-heading">🤖 User-Agent 차단 설정</span>
            <span id="useragent-toggle" aria-hidden="true">▼</span>
        </div>

        <div class="section-content expanded" id="useragent-section" role="group" aria-labelledby="useragent-heading">
            <div class="info-highlight" role="note" aria-label="기능 설명">
                의심스러운 User-Agent를 자동으로 감지하여 IP를 차단합니다. 일반적인 브라우저가 아닌 봇이나 스크래퍼를 차단합니다.
            </div>

            <div class="extension-container" role="group" aria-labelledby="useragent-status-heading">
                <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                    <span id="useragent-status-heading" style="font-weight: bold; font-size: 16px; color: #333;">User-Agent 차단 기능 상태</span>
                    <div class="feature-switch <?php echo $useragent_block_enabled == '1' ? 'enabled' : ''; ?>"
                         role="switch"
                         aria-checked="<?php echo $useragent_block_enabled == '1' ? 'true' : 'false'; ?>"
                         aria-label="User-Agent 차단 기능 토글"
                         tabindex="0"
                         onclick="toggleUserAgentFeature()"
                         onkeydown="handleSwitchKeyDown(event)"
                         data-enabled="<?php echo $useragent_block_enabled; ?>">
                        <div class="feature-switch-handle" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="extension-list" role="list" aria-label="현재 설정 상태">
                    <div class="extension-item <?php echo $useragent_block_enabled == '1' ? 'low' : 'high'; ?>" role="listitem">
                        <span aria-hidden="true"><?php echo $useragent_block_enabled == '1' ? '✅' : '❌'; ?></span>
                        User-Agent 차단 <?php echo $useragent_block_enabled == '1' ? '활성화' : '비활성화'; ?>
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🛡️</span> 차단 대상: 의심스러운 봇, 스크래퍼, 비정상 User-Agent
                    </div>
                    <div class="extension-item" role="listitem">
                        <span aria-hidden="true">🔍</span> 허용 대상: 일반 브라우저, 검색엔진 봇 (Google, Bing 등)
                    </div>
                </div>
            </div>

            <!-- User-Agent 차단 수준 설정 -->
            <?php if ($useragent_block_enabled == '1'): ?>
            <div class="extension-container" role="group" aria-labelledby="useragent-level-heading">
                <h4 id="useragent-level-heading" style="margin-bottom: 15px; color: #333;">User-Agent 차단 수준 설정</h4>
                <form method="post" action="./security_spam_update.php" role="form" aria-labelledby="useragent-level-heading">
                    <input type="hidden" name="action" value="save_useragent_level">
                    <div class="block-level-grid" role="group" aria-label="User-Agent 차단 수준 선택">
                        <?php
                        $useragent_levels = explode(',', $useragent_block_level);
                        ?>
                        <?php foreach ($level_options as $level => $info): ?>
                        <div class="block-level-item <?php echo $info['severity']; ?>" role="group" aria-labelledby="ua-<?php echo $level; ?>-label">
                            <div class="block-level-header">
                                <span class="block-level-icon" aria-hidden="true"><?php echo $info['icon']; ?></span>
                                <span id="ua-<?php echo $level; ?>-label" class="block-level-title"><?php echo $info['title']; ?></span>
                                <div class="mini-toggle <?php echo in_array($level, $useragent_levels) ? 'enabled' : ''; ?>"
                                     role="switch"
                                     aria-checked="<?php echo in_array($level, $useragent_levels) ? 'true' : 'false'; ?>"
                                     aria-labelledby="ua-<?php echo $level; ?>-label"
                                     onclick="toggleBlockLevel('useragent_<?php echo $level; ?>', this)"
                                     tabindex="0">
                                    <div class="mini-toggle-handle"></div>
                                    <input type="checkbox" name="useragent_block_level[]" value="<?php echo $level; ?>" 
                                           <?php echo in_array($level, $useragent_levels) ? 'checked' : ''; ?> style="display: none;">
                                </div>
                            </div>
                            <div class="block-level-desc">의심스러운 User-Agent 감지 시 <?php echo $info['desc']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn-primary">차단 수준 저장</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 차단된 User-Agent 목록 -->
            <div class="extension-container" role="group" aria-labelledby="useragent-logs-heading">
                <h4 id="useragent-logs-heading" style="margin: 0 0 15px 0; font-size: 16px; font-weight: bold; color: #333;">최근 차단된 User-Agent</h4>
                
                <?php
                $useragent_logs_sql = "SELECT sl_ip, sl_user_agent, sl_datetime, sl_block_reason
                                       FROM " . G5_TABLE_PREFIX . "security_ip_log
                                       WHERE sl_block_reason LIKE '%User-Agent%'
                                       ORDER BY sl_datetime DESC
                                       LIMIT 10";
                $useragent_logs_result = sql_query($useragent_logs_sql, false);
                ?>

                <?php if ($useragent_logs_result && sql_num_rows($useragent_logs_result) > 0): ?>
                    <table class="data-table" role="table" aria-labelledby="useragent-logs-heading">
                        <thead>
                            <tr role="row">
                                <th scope="col">IP 주소</th>
                                <th scope="col">User-Agent</th>
                                <th scope="col">차단 시간</th>
                                <th scope="col">차단 사유</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = sql_fetch_array($useragent_logs_result)): ?>
                                <tr role="row">
                                    <td>
                                        <span class="ip-address"><?php echo htmlspecialchars($log['sl_ip']); ?></span>
                                    </td>
                                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($log['sl_user_agent']); ?>">
                                        <?php echo htmlspecialchars($log['sl_user_agent']); ?>
                                    </td>
                                    <td>
                                        <time datetime="<?php echo date('c', strtotime($log['sl_datetime'])); ?>"><?php echo $log['sl_datetime']; ?></time>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['sl_block_reason']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <div class="empty-icon" aria-hidden="true">🤖</div>
                        <h3>차단된 User-Agent가 없습니다</h3>
                        <p>아직 의심스러운 User-Agent로 인한 차단 기록이 없습니다.</p>
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
        toggleUserAgentFeature();
    }
}

function toggleUserAgentFeature() {
    const toggle = document.querySelector('[onclick="toggleUserAgentFeature()"]');
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';
    
    // AJAX로 상태 변경
    const formData = new FormData();
    formData.append('action', 'toggle_useragent');
    formData.append('useragent_block_enabled', newState);
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