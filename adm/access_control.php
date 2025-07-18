<?php
$sub_menu = '950200';
require_once './_common.php';

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '접근 제어 관리';
require_once './admin.head.php';

// 관련 파일들 정보
$related_files = array(
    'bbs/search.php' => array(),
    'bbs/new.php' => array('new_delete.php'),
    'bbs/faq.php' => array(),
    'bbs/content.php' => array(),
    'bbs/current_connect.php' => array(),
    'bbs/group.php' => array(),
    'bbs/register.php' => array('register_form.php', 'register_form_update.php', 'register_result.php', 'register_email.php'),
    'bbs/password_lost.php' => array('password_lost2.php', 'password_reset.php', 'password_reset_update.php'),
    'bbs/memo.php' => array('memo_delete.php', 'memo_form.php', 'memo_form_update.php', 'memo_view.php'),
    'bbs/profile.php' => array('member_confirm.php', 'member_leave.php', 'point.php'),
    'bbs/board.php' => array('list.php', 'view.php', 'write.php', 'write_update.php', 'delete.php', 'good.php', 'move.php'),
    'bbs/download.php' => array('view_image.php'),
    'bbs/scrap.php' => array('scrap_delete.php', 'scrap_popin.php', 'scrap_popin_update.php'),
    'bbs/poll_result.php' => array('poll_update.php', 'poll_etc_update.php'),
    'bbs/qalist.php' => array('qaview.php', 'qawrite.php', 'qawrite_update.php', 'qadelete.php'),
    'bbs/qadownload.php' => array(),
    'bbs/link.php' => array()
);

// 접근 제어 테이블이 없다면 생성
$table_exists = sql_fetch("SHOW TABLES LIKE 'g5_access_control'");
if (!$table_exists) {
    include_once './create_access_control_table.php';
}

// 접근 제어 설정 불러오기
$sql = "SELECT * FROM g5_access_control ORDER BY ac_category, ac_page";
$result = sql_query($sql);

$access_controls = array();
while ($row = sql_fetch_array($result)) {
    $access_controls[$row['ac_category']][] = $row;
}
?>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f8fafc;
}

.access-control-container {
    margin: 20px 0;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-title {
    color: #1a202c;
    margin-bottom: 20px;
    font-size: 32px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
    text-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.dashboard-subtitle {
    color: #718096;
    font-size: 18px;
    margin-bottom: 30px;
    font-weight: 400;
    line-height: 1.6;
}

.access-section {
    margin-bottom: 40px;
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.access-section:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 24px 32px;
    color: white;
    font-weight: 700;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-content {
    padding: 0;
}

.access-item {
    display: flex;
    align-items: flex-start;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.3s ease;
    position: relative;
}

.access-item:hover {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.access-item:last-child {
    border-bottom: none;
}

.item-info {
    flex: 1;
    margin-right: 20px;
}

.item-name {
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 4px;
    font-size: 22px;
}

.item-path {
    font-size: 16px;
    color: #718096;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    margin-bottom: 4px;
    background: #f7fafc;
    padding: 3px 6px;
    border-radius: 4px;
    display: inline-block;
}

.item-description {
    font-size: 15px;
    color: #a0aec0;
    margin-bottom: 6px;
    line-height: 1.4;
}

.related-files {
    margin-top: 4px;
}

.related-label {
    font-size: 13px;
    color: #e53e3e;
    font-weight: 600;
    margin-bottom: 4px;
    display: block;
}

.related-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.related-file {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
    color: #c53030;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.access-controls {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-width: 160px;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 700;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    min-width: 80px;
    text-align: center;
}

.status-visitor {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

.status-member {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
}

.status-admin {
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
}

.status-off {
    background: linear-gradient(135deg, #a0aec0 0%, #718096 100%);
}

/* 스위치 컨테이너 */
.switch-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

/* 3단계 스위치 (정사각형 드래그 스타일) */
.triple-switch {
    position: relative;
    width: 120px;
    height: 40px;
    background: #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid #cbd5e0;
    overflow: hidden;
    transition: all 0.3s ease;
}

.triple-switch:hover {
    border-color: #a0aec0;
    transform: scale(1.02);
}

.triple-switch-handle {
    position: absolute;
    width: 36px;
    height: 36px;
    background: white;
    border-radius: 6px;
    top: 1px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: 2px solid;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 10px;
    color: white;
}

.triple-switch[data-level="1"] {
    background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
    border-color: #68d391;
}

.triple-switch[data-level="1"] .triple-switch-handle {
    left: 1px;
    border-color: #38a169;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

.triple-switch[data-level="2"] {
    background: linear-gradient(135deg, #bee3f8 0%, #90cdf4 100%);
    border-color: #63b3ed;
}

.triple-switch[data-level="2"] .triple-switch-handle {
    left: 41px;
    border-color: #3182ce;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
}

.triple-switch[data-level="10"] {
    background: linear-gradient(135deg, #fbd38d 0%, #f6ad55 100%);
    border-color: #f6ad55;
}

.triple-switch[data-level="10"] .triple-switch-handle {
    left: 81px;
    border-color: #dd6b20;
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
}

/* ON/OFF 스위치 */
.simple-switch {
    position: relative;
    width: 80px;
    height: 40px;
    background: #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid #cbd5e0;
    transition: all 0.3s ease;
}

.simple-switch:hover {
    border-color: #a0aec0;
    transform: scale(1.02);
}

.simple-switch.on {
    background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
    border-color: #68d391;
}

.simple-switch-handle {
    position: absolute;
    width: 36px;
    height: 36px;
    background: white;
    border-radius: 6px;
    top: 1px;
    left: 1px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: 2px solid #cbd5e0;
}

.simple-switch.on .simple-switch-handle {
    transform: translateX(40px);
    border-color: #38a169;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

/* 관리자/회원 스위치 */
.dual-switch {
    position: relative;
    width: 100px;
    height: 40px;
    background: #bee3f8;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid #63b3ed;
    overflow: hidden;
    transition: all 0.3s ease;
}

.dual-switch:hover {
    transform: scale(1.02);
}

.dual-switch.admin {
    background: linear-gradient(135deg, #fbd38d 0%, #f6ad55 100%);
    border-color: #f6ad55;
}

.dual-switch-handle {
    position: absolute;
    width: 46px;
    height: 36px;
    background: white;
    border-radius: 6px;
    top: 1px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: 2px solid;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    color: white;
}

.dual-switch .dual-switch-handle {
    left: 1px;
    border-color: #3182ce;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
}

.dual-switch.admin .dual-switch-handle {
    left: 51px;
    border-color: #dd6b20;
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
}

.level-labels {
    display: flex;
    justify-content: space-between;
    width: 120px;
    margin-top: 8px;
    font-size: 11px;
    color: #718096;
    font-weight: 600;
}

.dual-labels {
    width: 100px;
}

.warning-notice {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
    border: 1px solid #fc8181;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 30px;
    color: #c53030;
    font-size: 15px;
    font-weight: 500;
}

.control-buttons {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}

.reset-button {
    background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
    border: 2px solid #fc8181;
    color: #c53030;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(252, 129, 129, 0.3);
}

.reset-button:hover {
    background: linear-gradient(135deg, #fc8181 0%, #f56565 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(252, 129, 129, 0.4);
}

.reset-button:active {
    transform: translateY(0);
}

.feature-highlight {
    background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%);
    border: 1px solid #81e6d9;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 30px;
    color: #234e52;
    font-size: 15px;
    font-weight: 500;
}
</style>

<div class="access-control-container">
    <h1 class="dashboard-title">
        🛡️ 접근 제어 관리
    </h1>
    <p class="dashboard-subtitle">
        각 페이지별로 접근 권한을 설정할 수 있습니다. 메인 페이지 차단 시 관련된 모든 파일이 함께 차단됩니다.
    </p>

    <div class="control-buttons">
        <button type="button" class="reset-button" onclick="resetToDefault()">
            🔄 초기설정으로 복원
        </button>
    </div>

    <div class="feature-highlight">
        ✨ <strong>스마트 차단:</strong> 메인 기능을 차단하면 관련된 모든 파일들이 자동으로 함께 차단되어 우회 접근을 완전 차단합니다.
    </div>

    <form id="accessControlForm">
        <?php foreach ($access_controls as $category => $items): ?>
        <div class="access-section">
            <div class="section-header">
                <?php 
                $icons = array(
                    '검색 & 컨텐츠' => '🔍',
                    '회원 관련' => '👤', 
                    '게시판/설문 관련' => '📝'
                );
                echo $icons[$category] ?? '📁';
                ?> <?php echo $category; ?>
            </div>
            <div class="section-content">
                <?php foreach ($items as $item): ?>
                <div class="access-item">
                    <div class="item-info">
                        <div class="item-name"><?php echo $item['ac_name']; ?></div>
                        <div class="item-path"><?php echo $item['ac_page']; ?></div>
                        <div class="item-description"><?php echo $item['ac_description']; ?></div>
                        
                        <?php if (isset($related_files[$item['ac_page']]) && !empty($related_files[$item['ac_page']])): ?>
                        <div class="related-files">
                            <span class="related-label">🔗 함께 차단되는 관련 파일들:</span>
                            <div class="related-list">
                                <?php foreach ($related_files[$item['ac_page']] as $related): ?>
                                <span class="related-file"><?php echo $related; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="access-controls">
                        <div class="switch-container">
                            <span class="status-badge status-<?php 
                                echo $item['ac_level'] == 10 ? 'admin' : 
                                    ($item['ac_level'] == 2 ? 'member' : 
                                    ($item['ac_level'] == 1 ? 'visitor' : 'off')); 
                            ?>" id="status-<?php echo $item['ac_id']; ?>">
                                <?php 
                                echo $item['ac_level'] == 10 ? '관리자만' : 
                                    ($item['ac_level'] == 2 ? '회원 이상' : 
                                    ($item['ac_level'] == 1 ? '모든 사용자' : '접근 차단')); 
                                ?>
                            </span>
                            
                            <?php if (in_array($item['ac_page'], ['bbs/register.php', 'bbs/password_lost.php'])): ?>
                                <!-- ON/OFF 스위치 -->
                                <div class="simple-switch <?php echo $item['ac_level'] > 0 ? 'on' : ''; ?>" 
                                     onclick="toggleSimpleSwitch(<?php echo $item['ac_id']; ?>)"
                                     data-id="<?php echo $item['ac_id']; ?>">
                                    <div class="simple-switch-handle"></div>
                                </div>
                                <div class="level-labels dual-labels">
                                    <span>차단</span>
                                    <span>허용</span>
                                </div>
                                
                            <?php elseif (in_array($item['ac_page'], ['bbs/memo.php', 'bbs/profile.php', 'bbs/point.php', 'bbs/scrap.php', 'bbs/qalist.php', 'bbs/qadownload.php'])): ?>
                                <!-- 관리자/회원 스위치 -->
                                <div class="dual-switch <?php echo $item['ac_level'] == 10 ? 'admin' : ''; ?>" 
                                     onclick="toggleDualSwitch(<?php echo $item['ac_id']; ?>)"
                                     data-id="<?php echo $item['ac_id']; ?>"
                                     data-level="<?php echo $item['ac_level']; ?>">
                                    <div class="dual-switch-handle"></div>
                                </div>
                                <div class="level-labels dual-labels">
                                    <span>회원</span>
                                    <span>관리자</span>
                                </div>
                                
                            <?php else: ?>
                                <!-- 3단계 스위치 -->
                                <div class="triple-switch" 
                                     data-level="<?php echo $item['ac_level']; ?>" 
                                     data-id="<?php echo $item['ac_id']; ?>"
                                     onclick="toggleTripleSwitch(<?php echo $item['ac_id']; ?>)">
                                    <div class="triple-switch-handle"></div>
                                </div>
                                <div class="level-labels">
                                    <span>방문자</span>
                                    <span>회원</span>
                                    <span>관리자</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </form>
</div>

<script>
function toggleTripleSwitch(id) {
    const switchElement = document.querySelector(`[data-id="${id}"].triple-switch`);
    const currentLevel = parseInt(switchElement.getAttribute('data-level'));
    
    let newLevel;
    if (currentLevel === 1) {
        newLevel = 2;
    } else if (currentLevel === 2) {
        newLevel = 10;
    } else {
        newLevel = 1;
    }
    
    switchElement.setAttribute('data-level', newLevel);
    updateStatus(id, newLevel);
    saveAccessControl(id, newLevel);
}

function toggleSimpleSwitch(id) {
    const switchElement = document.querySelector(`[data-id="${id}"].simple-switch`);
    const isOn = switchElement.classList.contains('on');
    
    if (isOn) {
        switchElement.classList.remove('on');
        updateStatus(id, -1);
        saveAccessControl(id, -1);
    } else {
        switchElement.classList.add('on');
        updateStatus(id, 1);
        saveAccessControl(id, 1);
    }
}

function toggleDualSwitch(id) {
    const switchElement = document.querySelector(`[data-id="${id}"].dual-switch`);
    const currentLevel = parseInt(switchElement.getAttribute('data-level'));
    
    let newLevel = currentLevel === 10 ? 2 : 10;
    
    switchElement.setAttribute('data-level', newLevel);
    if (newLevel === 10) {
        switchElement.classList.add('admin');
    } else {
        switchElement.classList.remove('admin');
    }
    
    updateStatus(id, newLevel);
    saveAccessControl(id, newLevel);
}

function updateStatus(id, level) {
    const statusElement = document.getElementById(`status-${id}`);
    
    statusElement.className = 'status-badge status-' + 
        (level == 10 ? 'admin' : 
         level == 2 ? 'member' : 
         level == 1 ? 'visitor' : 'off');
    
    statusElement.textContent = 
        level == 10 ? '관리자만' : 
        level == 2 ? '회원 이상' : 
        level == 1 ? '모든 사용자' : '접근 차단';
}

function saveAccessControl(id, level) {
    fetch('access_control_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&level=${level}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 성공 시 약간의 시각적 피드백
            const statusElement = document.getElementById(`status-${id}`);
            statusElement.style.transform = 'scale(1.1)';
            setTimeout(() => {
                statusElement.style.transform = 'scale(1)';
            }, 200);
        } else {
            alert('설정 저장에 실패했습니다: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('설정 저장 중 오류가 발생했습니다.');
    });
}

function resetToDefault() {
    if (!confirm('정말로 초기설정으로 복원하시겠습니까?\n\n모든 접근 제어 설정이 그누보드 설치 시 기본값으로 돌아갑니다.')) {
        return;
    }
    
    fetch('access_control_reset.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('초기설정으로 복원되었습니다.');
            location.reload(); // 페이지 새로고침
        } else {
            alert('초기설정 복원에 실패했습니다: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('초기설정 복원 중 오류가 발생했습니다.');
    });
}

// 페이지 로드 시 애니메이션
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.access-section');
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        setTimeout(() => {
            section.style.transition = 'all 0.5s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php
require_once './admin.tail.php';
?>