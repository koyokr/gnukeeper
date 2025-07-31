<?php
require_once './_common.php';

$g5['title'] = '차단 관리';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 차단 통계 데이터 계산
$active_blocks_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_status = 'active'")['cnt'] ?? 0;
$expired_blocks_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_status = 'expired'")['cnt'] ?? 0;
$whitelist_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist WHERE sw_status = 'active'")['cnt'] ?? 0;
$today_blocks_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE DATE(sb_datetime) = CURDATE()")['cnt'] ?? 0;

// 해외 IP 차단 설정값 로드
$foreign_block_enabled = gk_get_config('foreign_block_enabled', '0');
?>

<link rel="stylesheet" href="../css/gk_admin.css">

<!-- 전체 통계 개요 -->
<div class="overview-stats">
    <h2>🛡️ 차단 관리 현황</h2>
    <div class="overview-grid">
        <div class="overview-item">
            <div class="overview-number"><?php echo number_format($active_blocks_count); ?></div>
            <div class="overview-label">활성 차단 IP</div>
        </div>
        <div class="overview-item">
            <div class="overview-number"><?php echo number_format($whitelist_count); ?></div>
            <div class="overview-label">예외 IP</div>
        </div>
        <div class="overview-item">
            <div class="overview-number"><?php echo number_format($today_blocks_count); ?></div>
            <div class="overview-label">오늘 차단 (24시간)</div>
        </div>
        <div class="overview-item">
            <div class="overview-number"><?php echo $foreign_block_enabled == '1' ? 'ON' : 'OFF'; ?></div>
            <div class="overview-label">해외 IP 차단</div>
        </div>
    </div>
</div>


<!-- 예외 IP 관리 섹션 -->
<div class="dashboard-section">
    <div class="section-header" onclick="toggleSection('whitelist-section')">
        ✅ 예외 IP 설정 <span id="whitelist-toggle">▼</span>
    </div>
    <div class="section-content expanded" id="whitelist-section">
        <?php include './section_whitelist.php'; ?>
    </div>
</div>

<!-- IP 차단 관리 섹션 -->
<div class="dashboard-section">
    <div class="section-header" onclick="toggleSection('block-section')">
        🚫 IP 차단 관리 <span id="block-toggle">▼</span>
    </div>
    <div class="section-content expanded" id="block-section">
        <?php include './section_block.php'; ?>
    </div>
</div>

<!-- 해외 IP 차단 섹션 -->
<div class="dashboard-section">
    <div class="section-header" onclick="toggleSection('foreign-section')">
        🌍 해외 IP 차단 <span id="foreign-toggle">▼</span>
    </div>
    <div class="section-content expanded" id="foreign-section">
        <?php include './section_foreign.php'; ?>
    </div>
</div>


<script>
// Security Block JavaScript - Optimized
const $ = id => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);
const showMsg = (msg, type='info') => {
    const div = document.createElement('div');
    div.style.cssText = `position:fixed;top:20px;right:20px;z-index:9999;padding:15px 20px;border-radius:5px;background:${type==='error'?'#dc3545':type==='success'?'#28a745':'#007bff'};color:white;box-shadow:0 2px 8px rgba(0,0,0,0.2)`;
    div.innerHTML = `${msg} <button onclick="this.parentNode.remove()" style="background:none;border:none;color:white;margin-left:10px;cursor:pointer">×</button>`;
    document.body.appendChild(div);
    if(type==='success') setTimeout(()=>div.remove(), 3000);
};
const ajax = async (url, data) => {
    const form = new FormData();
    Object.entries(data).forEach(([k,v]) => form.append(k,v));
    try {
        const res = await fetch(url, {method:'POST', body:form});
        const text = await res.text();
        return {ok: res.ok, text};
    } catch(e) {
        return {ok: false, error: e.message};
    }
};
const toggleSection = id => {
    const section = $(id);
    const toggle = $(id.replace('-section', '-toggle'));
    if(!section || !toggle) return;

    if (section.style.display === 'none' || section.classList.contains('collapsed')) {
        section.style.display = 'block';
        section.classList.remove('collapsed');
        toggle.textContent = '▼';
    } else {
        section.style.display = 'none';
        section.classList.add('collapsed');
        toggle.textContent = '▶';
    }
};
const refreshSection = async (sectionId, url) => {
    try {
        const res = await fetch(url);
        if (!res.ok) throw new Error('Network response was not ok');

        const html = await res.text();
        const temp = document.createElement('div');
        temp.innerHTML = html;

        const section = $(sectionId);
        if(section) {
            // 전체 내용을 바꿀 것이 아니라 내부만 바꿔서 이벤트 리스너 보존
            section.innerHTML = temp.innerHTML;

            // 새로 로드된 콘텐츠에 이벤트 리스너 재등록
            updateSelectedCount();
        }
    } catch(e) {
        console.error('Section refresh failed:', e);
        showMsg('섹션 새로고침 실패', 'error');
    }
};
const deleteBlock = async id => {
    if(!confirm('이 IP 차단을 삭제하시겠습니까?')) return;

    try {
        const {ok, text} = await ajax('./section_block_post.php', {action:'delete_block', block_id:id});
        const isSuccess = ok && text.includes('삭제');
        showMsg(isSuccess ? 'IP 차단이 삭제되었습니다' : '삭제에 실패했습니다', isSuccess ? 'success' : 'error');
        if(isSuccess) {
            await refreshSection('block-section', './section_block.php');
        }
    } catch(error) {
        console.error('Delete error:', error);
        showMsg('삭제 중 오류가 발생했습니다', 'error');
    }
};
const toggleBlock = async (id, status) => {
    const {ok, text} = await ajax('./section_block_post.php', {action:'toggle_block', block_id:id, new_status:status});
    const statusText = status==='active'?'활성화':'비활성화';
    showMsg(ok && text.includes(statusText) ? `${statusText}되었습니다` : '처리 실패', ok && text.includes(statusText) ? 'success' : 'error');
    if(ok && text.includes(statusText)) await refreshSection('block-section', './section_block.php');
};
const deleteWhitelist = async id => {
    if(!confirm('이 예외 IP를 삭제하시겠습니까?')) return;

    try {
        const {ok, text} = await ajax('./section_whitelist_post.php', {action:'delete_whitelist', whitelist_id:id});
        const isSuccess = ok && text.includes('삭제');
        showMsg(isSuccess ? '예외 IP가 삭제되었습니다' : '삭제에 실패했습니다', isSuccess ? 'success' : 'error');
        if(isSuccess) {
            await refreshSection('whitelist-section', './section_whitelist.php');
        }
    } catch(error) {
        console.error('Delete whitelist error:', error);
        showMsg('삭제 중 오류가 발생했습니다', 'error');
    }
};
const toggleDurationInput = sel => {
    const input = sel.parentNode.querySelector('.duration-input');
    if(input) {
        input.style.display = sel.value === 'temporary' ? 'inline-block' : 'none';
        input.required = sel.value === 'temporary';
    }
};
const updateSelectedCount = () => {
    const checked = $$('.block-checkbox:checked').length;
    const total = $$('.block-checkbox').length;
    const selectAll = $('selectAll');
    if($('selectedCount')) $('selectedCount').textContent = checked;
    if(selectAll) {
        selectAll.indeterminate = checked > 0 && checked < total;
        selectAll.checked = checked === total && total > 0;
    }
};
const toggleAllCheckboxes = cb => {
    $$('.block-checkbox').forEach(c => c.checked = cb.checked);
    updateSelectedCount();
};
const confirmBulkAction = () => {
    const checked = $$('.block-checkbox:checked');
    const action = document.querySelector('select[name="bulk_action"]')?.value;
    if(!checked.length) return alert('처리할 항목을 선택해주세요.'), false;
    if(!action) return alert('작업을 선택해주세요.'), false;
    const actionText = {activate:'활성화', deactivate:'비활성화', delete:'삭제'}[action] || action;
    return confirm(`선택된 ${checked.length}개 항목을 ${actionText}하시겠습니까?`);
};
document.addEventListener('DOMContentLoaded', () => {
    // Section 초기 상태 설정 (기본적으로 모든 섹션 표시)
    ['whitelist-section', 'block-section', 'foreign-section'].forEach(id => {
        const section = $(id);
        const toggle = $(id.replace('-section', '-toggle'));
        if(section && toggle) {
            section.style.display = 'block';
            section.classList.remove('collapsed');
            toggle.textContent = '▼';
        }
    });
    // Form handlers with AJAX (STRICT NO REFRESH POLICY)
    document.addEventListener('submit', async e => {
        // 모든 form submit 이벤트를 차단하고 AJAX로 처리
        e.preventDefault();
        e.stopPropagation();

        if(!e.target.matches('form')) return;

        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        try {
            const action = form.getAttribute('action') || form.action;
            console.log('Form action:', action); // 디버깅용
            const {ok, text} = await ajax(action, data);
            console.log('AJAX Response:', {ok, text}); // 디버깅용
            const isSuccess = ok && (text.includes('성공') || text.includes('추가') || text.includes('저장') || text.includes('활성화') || text.includes('비활성화') || text.includes('삭제') || text.includes('되었습니다'));
            showMsg(isSuccess ? '처리되었습니다' : text || '처리 실패', isSuccess ? 'success' : 'error');

            if(isSuccess) {
                const actionFile = action.split('/').pop();
                if(actionFile.includes('block')) await refreshSection('block-section', './section_block.php');
                else if(actionFile.includes('whitelist')) await refreshSection('whitelist-section', './section_whitelist.php');
                else if(actionFile.includes('foreign')) await refreshSection('foreign-section', './section_foreign.php');

                // form 초기화 (입력 필드 지우기)
                if(form.reset) {
                    form.reset();
                }
            }
        } catch(error) {
            console.error('AJAX Error:', error);
            showMsg('AJAX 요청 중 오류가 발생했습니다', 'error');
        }

        return false; // 절대 새로고침 방지
    });
    // Event delegation for dynamic content
    document.addEventListener('change', async e => {
        if(e.target.matches('select[name="block_duration"]')) toggleDurationInput(e.target);
        if(e.target.matches('.block-checkbox, #selectAll')) updateSelectedCount();
        if(e.target.matches('input[name="foreign_block_enabled"]')) {
            e.preventDefault();
            const enabled = e.target.checked ? '1' : '0';
            const {ok, text} = await ajax('./section_foreign_post.php', {action:'toggle_foreign_block', enabled});
            showMsg(ok ? '설정이 저장되었습니다' : '설정 저장 실패', ok ? 'success' : 'error');
            if(ok) await refreshSection('foreign-section', './section_foreign.php');
        }
    });
    updateSelectedCount();
});
window.toggleSection = toggleSection;
window.deleteBlock = deleteBlock;
window.toggleBlock = toggleBlock;
window.deleteWhitelist = deleteWhitelist;
window.toggleDurationInput = toggleDurationInput;
window.toggleAllCheckboxes = toggleAllCheckboxes;
window.confirmBulkAction = confirmBulkAction;
window.toggleIPBlockFeature = async () => {
    const toggle = document.querySelector('.feature-switch[data-enabled]');
    const enabled = toggle.dataset.enabled === '1';
    const {ok, text} = await ajax('./section_block_post.php', {action:'toggle_ip_block_feature', enabled: enabled ? '0' : '1'});
    showMsg(ok ? (enabled ? 'IP 차단 기능이 비활성화되었습니다' : 'IP 차단 기능이 활성화되었습니다') : '설정 변경 실패', ok ? 'success' : 'error');
    if(ok) await refreshSection('block-section', './section_block.php');
};
window.toggleForeignBlockFeature = async () => {
    const toggle = document.querySelector('#foreign-section .feature-switch[data-enabled]');
    if (!toggle) {
        console.error('Foreign block toggle not found');
        return;
    }
    const enabled = toggle.dataset.enabled === '1';
    const {ok, text} = await ajax('./section_foreign_post.php', {action:'toggle_foreign_block_feature', enabled: enabled ? '0' : '1'});
    showMsg(ok ? (enabled ? '해외 IP 차단 기능이 비활성화되었습니다' : '해외 IP 차단 기능이 활성화되었습니다') : '설정 변경 실패', ok ? 'success' : 'error');
    if(ok) {
        // UI 업데이트
        toggle.classList.toggle('enabled');
        toggle.dataset.enabled = enabled ? '0' : '1';
        await refreshSection('foreign-section', './section_foreign.php');
    }
};
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');
?>