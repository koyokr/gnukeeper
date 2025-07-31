/**
 * Security Spam Module JavaScript
 * Handles dynamic interactions without page refreshes
 */

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeSecuritySpam();
});

/**
 * Initialize Security Spam functionality
 */
function initializeSecuritySpam() {
    setupFormHandlers();
    setupToggleHandlers();
    setupKeyboardHandlers();
}

/**
 * Section toggle functionality
 */
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    const toggle = document.getElementById(sectionId.replace('-section', '-toggle'));
    const header = toggle?.closest('.section-header');
    
    if (!section || !toggle) return;
    
    const isExpanded = section.style.display !== 'none';
    
    if (isExpanded) {
        section.style.display = 'none';
        toggle.textContent = '▶';
        header?.setAttribute('aria-expanded', 'false');
    } else {
        section.style.display = 'block';
        toggle.textContent = '▼';
        header?.setAttribute('aria-expanded', 'true');
    }
}

/**
 * Setup form handlers for AJAX submission
 */
function setupFormHandlers() {
    // Setup all forms with action pointing to security_spam_update.php
    const forms = document.querySelectorAll('form[action="./security_spam_update.php"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleSpamForm(this);
        });
    });
}

/**
 * Setup toggle handlers
 */
function setupToggleHandlers() {
    // Block level toggles
    const blockToggles = document.querySelectorAll('.mini-toggle');
    blockToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.querySelector('input[type="checkbox"]');
            if (input) {
                const levelId = input.value;
                toggleBlockLevel(levelId, this);
            }
        });
    });
}

/**
 * Setup keyboard handlers for accessibility
 */
function setupKeyboardHandlers() {
    // Section headers
    const sectionHeaders = document.querySelectorAll('.section-header[role="button"]');
    sectionHeaders.forEach(header => {
        header.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const sectionId = this.getAttribute('aria-controls');
                if (sectionId) {
                    toggleSection(sectionId);
                }
            }
        });
    });
    
    // Feature switches
    const featureSwitches = document.querySelectorAll('.feature-switch[role="switch"]');
    featureSwitches.forEach(switchEl => {
        switchEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
}

/**
 * Handle spam configuration forms
 */
async function handleSpamForm(form) {
    const formData = new FormData(form);
    const action = formData.get('action');
    
    try {
        showLoading('설정을 저장하는 중...');
        
        const response = await fetch('./security_spam_update.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.text();
        
        hideLoading();
        
        if (result.includes('성공') || result.includes('저장되었습니다')) {
            showSuccess('설정이 성공적으로 저장되었습니다.');
            
            // Update UI without refresh based on action
            await updateUIAfterSave(action, formData);
            
        } else {
            showError('설정 저장에 실패했습니다: ' + extractErrorMessage(result));
        }
        
    } catch (error) {
        hideLoading();
        showError('오류가 발생했습니다: ' + error.message);
    }
}

/**
 * Update UI after successful save
 */
async function updateUIAfterSave(action, formData) {
    switch (action) {
        case 'save_login_config':
            updateLoginConfig(formData);
            break;
        case 'save_spam_level':
            updateSpamLevel(formData);
            break;
        case 'save_404_config':
            update404Config(formData);
            break;
        case 'save_register_config':
            updateRegisterConfig(formData);
            break;
        case 'save_login_multi_config':
            updateLoginMultiConfig(formData);
            break;
        case 'save_regex_spam_config':
            updateRegexSpamConfig(formData);
            break;
        case 'save_useragent_level':
            updateUserAgentLevel(formData);
            break;
        default:
            // For other actions, do a gentle refresh
            setTimeout(() => location.reload(), 1000);
    }
}

/**
 * Feature toggle functions
 */
async function toggleLoginFeature() {
    const toggle = document.querySelector('.feature-switch[data-enabled]');
    if (!toggle) return;
    
    const enabled = toggle.dataset.enabled === '1';
    const newState = enabled ? '0' : '1';
    
    await toggleFeature('toggle_login', 'login_block_enabled', newState, toggle);
}

async function toggle404Feature() {
    const toggle = document.querySelector('[onclick="toggle404Feature()"]');
    if (!toggle) return;
    
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    await toggleFeature('toggle_404', 'behavior_404_enabled', newState, toggle);
}

async function toggleRefererFeature() {
    const toggle = document.querySelector('[onclick="toggleRefererFeature()"]');
    if (!toggle) return;
    
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    await toggleFeature('toggle_referer', 'behavior_referer_enabled', newState, toggle);
}

async function toggleRegisterFeature() {
    const toggle = document.querySelector('[onclick="toggleRegisterFeature()"]');
    if (!toggle) return;
    
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    await toggleFeature('toggle_register', 'multiuser_register_enabled', newState, toggle);
}

async function toggleLoginMultiFeature() {
    const toggle = document.querySelector('[onclick="toggleLoginMultiFeature()"]');
    if (!toggle) return;
    
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    await toggleFeature('toggle_login_multi', 'multiuser_login_enabled', newState, toggle);
}

async function toggleRegexSpamFeature() {
    const toggle = document.querySelector('[onclick="toggleRegexSpamFeature()"]');
    if (!toggle) return;
    
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    await toggleFeature('toggle_regex_spam', 'regex_spam_enabled', newState, toggle);
}

async function toggleUserAgentFeature() {
    const toggle = document.querySelector('[onclick="toggleUserAgentFeature()"]');
    if (!toggle) return;
    
    const currentState = toggle.getAttribute('data-enabled');
    const newState = currentState === '1' ? '0' : '1';
    
    await toggleFeature('toggle_useragent', 'useragent_block_enabled', newState, toggle);
}

/**
 * Generic feature toggle handler
 */
async function toggleFeature(action, paramName, newState, toggle) {
    if (!toggle) return;
    
    toggle.setAttribute('aria-checked', newState === '1' ? 'true' : 'false');
    toggle.style.opacity = '0.6';
    toggle.style.pointerEvents = 'none';
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append(paramName, newState);
    formData.append('ajax', '1');
    
    try {
        const response = await fetch('./security_spam_update.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.text();
        
        if (data.includes('success') || data.includes('성공')) {
            toggle.setAttribute('data-enabled', newState);
            if (newState === '1') {
                toggle.classList.add('enabled');
            } else {
                toggle.classList.remove('enabled');
            }
            
            showSuccess(`기능이 성공적으로 ${newState === '1' ? '활성화' : '비활성화'}되었습니다.`);
            
            // Update related UI elements
            updateFeatureUI(action, newState);
            
        } else {
            toggle.setAttribute('aria-checked', toggle.dataset.enabled === '1' ? 'true' : 'false');
            showError('설정 변경에 실패했습니다: ' + data);
        }
    } catch (error) {
        console.error('Error:', error);
        toggle.setAttribute('aria-checked', toggle.dataset.enabled === '1' ? 'true' : 'false');
        showError('설정 변경 중 오류가 발생했습니다.');
    } finally {
        toggle.style.opacity = '1';
        toggle.style.pointerEvents = 'auto';
    }
}

/**
 * Block level toggle functionality
 */
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
    
    // Visual feedback
    toggleElement.style.transform = 'scale(0.95)';
    setTimeout(() => {
        toggleElement.style.transform = 'scale(1)';
    }, 150);
}

/**
 * Disable other block levels (for exclusivity)
 */
function disableOtherLevels(levelIds) {
    levelIds.forEach(levelId => {
        const otherInput = document.querySelector(`input[value="${levelId}"]`);
        if (!otherInput) return;
        
        const otherToggle = otherInput.closest('.mini-toggle');
        const otherCheckbox = otherToggle?.querySelector('input[type="checkbox"]');
        
        if (otherToggle?.classList.contains('enabled')) {
            otherToggle.classList.remove('enabled');
            otherToggle.setAttribute('aria-checked', 'false');
            if (otherCheckbox) {
                otherCheckbox.checked = false;
            }
        }
    });
}

/**
 * Form validation
 */
function validate_form() {
    const limit = document.querySelector('input[name="login_fail_limit"]')?.value;
    const windowTime = document.querySelector('input[name="login_fail_window"]')?.value;
    const blockDuration = document.querySelector('input[name="login_block_duration"]')?.value;

    if (limit && (limit < 1 || limit > 50)) {
        showError('최대 실패 횟수는 1~50 사이의 값이어야 합니다.');
        return false;
    }

    if (windowTime && (windowTime < 1 || windowTime > 1440)) {
        showError('감지 시간 윈도우는 1분~1440분(24시간) 사이의 값이어야 합니다.');
        return false;
    }

    if (blockDuration && (blockDuration < 0 || blockDuration > 525600)) {
        showError('자동 차단 시간은 0분~525600분(1년) 사이의 값이어야 합니다.');
        return false;
    }

    return true;
}

/**
 * Update UI functions (called after successful saves)
 */
function updateLoginConfig(formData) {
    // Update displayed values in extension items
    const limit = formData.get('login_fail_limit');
    const window = formData.get('login_fail_window');
    const duration = formData.get('login_block_duration');
    
    updateExtensionItem('최대 실패 횟수', `${limit}회`);
    updateExtensionItem('감지 윈도우', `${window}분`);
    updateExtensionItem('차단 시간', duration === '0' ? '영구' : `${duration}분`);
}

function updateSpamLevel(formData) {
    // Update block level displays
    const levels = formData.getAll('spam_block_level[]');
    
    // Update each level indicator
    ['access', 'login', 'write'].forEach(level => {
        const isEnabled = levels.includes(level);
        updateLevelIndicator(level, isEnabled);
    });
}

function update404Config(formData) {
    const limit = formData.get('behavior_404_limit');
    const window = formData.get('behavior_404_window');
    const duration = formData.get('behavior_404_block_duration');
    
    updateExtensionItem('최대 접속 횟수', `${limit}회`);
    updateExtensionItem('감지 윈도우', `${window}초`);
    updateExtensionItem('차단 시간', `${duration}초`);
}

function updateRegisterConfig(formData) {
    const limit = formData.get('multiuser_register_limit');
    const window = formData.get('multiuser_register_window');
    const duration = formData.get('multiuser_register_block_duration');
    
    updateExtensionItem('최대 가입 수', `${limit}개`);
    updateExtensionItem('감지 윈도우', `${window}시간`);
    updateExtensionItem('차단 시간', `${duration}분`);
}

function updateLoginMultiConfig(formData) {
    const limit = formData.get('multiuser_login_limit');
    const window = formData.get('multiuser_login_window');
    const duration = formData.get('multiuser_login_block_duration');
    
    updateExtensionItem('최대 로그인 수', `${limit}개`);
    updateExtensionItem('감지 윈도우', `${window}시간`);
    updateExtensionItem('차단 시간', `${duration}분`);
}

function updateRegexSpamConfig(formData) {
    const duration = formData.get('regex_spam_block_duration');
    updateExtensionItem('차단 시간', `${duration}분`);
}

function updateUserAgentLevel(formData) {
    const levels = formData.getAll('useragent_block_level[]');
    
    ['access', 'login', 'write'].forEach(level => {
        const isEnabled = levels.includes(level);
        updateLevelIndicator(`ua-${level}`, isEnabled);
    });
}

/**
 * Helper functions
 */
function updateExtensionItem(label, value) {
    const items = document.querySelectorAll('.extension-item');
    items.forEach(item => {
        if (item.textContent.includes(label)) {
            const parts = item.innerHTML.split(':');
            if (parts.length > 1) {
                item.innerHTML = parts[0] + ': ' + value;
            }
        }
    });
}

function updateLevelIndicator(level, isEnabled) {
    const toggle = document.querySelector(`input[value="${level}"]`)?.closest('.mini-toggle');
    if (toggle) {
        if (isEnabled) {
            toggle.classList.add('enabled');
            toggle.setAttribute('aria-checked', 'true');
        } else {
            toggle.classList.remove('enabled');
            toggle.setAttribute('aria-checked', 'false');
        }
    }
}

function updateFeatureUI(action, newState) {
    // Update related extension items based on the feature toggle
    const isEnabled = newState === '1';
    
    // Find and update status items
    const statusItems = document.querySelectorAll('.extension-item');
    statusItems.forEach(item => {
        if (item.textContent.includes('활성화') || item.textContent.includes('비활성화')) {
            const icon = item.querySelector('span[aria-hidden="true"]');
            if (icon) {
                icon.textContent = isEnabled ? '✅' : '❌';
            }
            
            // Update text
            const text = item.textContent;
            if (text.includes('활성화') || text.includes('비활성화')) {
                item.innerHTML = item.innerHTML.replace(
                    /(활성화|비활성화)/,
                    isEnabled ? '활성화' : '비활성화'
                );
            }
            
            // Update color class
            item.classList.remove('low', 'high');
            item.classList.add(isEnabled ? 'low' : 'high');
        }
    });
}

/**
 * Message display functions (similar to security_block.js)
 */
function showLoading(message) {
    hideAllMessages();
    
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'gk-loading';
    loadingDiv.className = 'gk-message gk-loading';
    loadingDiv.innerHTML = `
        <div class="gk-message-content">
            <div class="gk-spinner"></div>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(loadingDiv);
    
    if (!document.getElementById('gk-message-styles')) {
        addMessageStyles();
    }
}

function showSuccess(message) {
    hideAllMessages();
    
    const successDiv = document.createElement('div');
    successDiv.id = 'gk-success';
    successDiv.className = 'gk-message gk-success';
    successDiv.innerHTML = `
        <div class="gk-message-content">
            <span>✅ ${message}</span>
            <button onclick="hideAllMessages()" class="gk-close">×</button>
        </div>
    `;
    
    document.body.appendChild(successDiv);
    
    if (!document.getElementById('gk-message-styles')) {
        addMessageStyles();
    }
    
    setTimeout(hideAllMessages, 3000);
}

function showError(message) {
    hideAllMessages();
    
    const errorDiv = document.createElement('div');
    errorDiv.id = 'gk-error';
    errorDiv.className = 'gk-message gk-error';
    errorDiv.innerHTML = `
        <div class="gk-message-content">
            <span>❌ ${message}</span>
            <button onclick="hideAllMessages()" class="gk-close">×</button>
        </div>
    `;
    
    document.body.appendChild(errorDiv);
    
    if (!document.getElementById('gk-message-styles')) {
        addMessageStyles();
    }
}

function hideLoading() {
    const loading = document.getElementById('gk-loading');
    if (loading) {
        loading.remove();
    }
}

function hideAllMessages() {
    const messages = document.querySelectorAll('.gk-message');
    messages.forEach(msg => msg.remove());
}

function addMessageStyles() {
    const styles = document.createElement('style');
    styles.id = 'gk-message-styles';
    styles.textContent = `
        .gk-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }
        
        .gk-message-content {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 500;
        }
        
        .gk-loading {
            background: #007cba;
            color: white;
        }
        
        .gk-success {
            background: #28a745;
            color: white;
        }
        
        .gk-error {
            background: #dc3545;
            color: white;
        }
        
        .gk-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        .gk-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            margin-left: 10px;
            padding: 0;
            width: 20px;
            height: 20px;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    
    document.head.appendChild(styles);
}

function extractErrorMessage(response) {
    const alertMatch = response.match(/alert\(['"]([^'"]+)['"]\)/);
    if (alertMatch) {
        return alertMatch[1];
    }
    return '알 수 없는 오류가 발생했습니다.';
}

// Export functions to global scope for HTML onclick handlers
window.toggleSection = toggleSection;
window.toggleLoginFeature = toggleLoginFeature;
window.toggle404Feature = toggle404Feature;
window.toggleRefererFeature = toggleRefererFeature;
window.toggleRegisterFeature = toggleRegisterFeature;
window.toggleLoginMultiFeature = toggleLoginMultiFeature;
window.toggleRegexSpamFeature = toggleRegexSpamFeature;
window.toggleUserAgentFeature = toggleUserAgentFeature;
window.toggleBlockLevel = toggleBlockLevel;
window.validate_form = validate_form;
window.hideAllMessages = hideAllMessages;