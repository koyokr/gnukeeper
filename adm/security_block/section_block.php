<?php
if (!defined('_GNUBOARD_')) {
    require_once './_common.php';
}

// 현재 접속 IP 가져오기
$current_admin_ip = $_SERVER['REMOTE_ADDR'];

// 그누보드 기본 IP 차단 설정 가져오기
$blocked_ips_raw = isset($config['cf_intercept_ip']) ? $config['cf_intercept_ip'] : '';
$blocked_ips = array_filter(array_map('trim', explode("\n", $blocked_ips_raw)));

?>

<!-- gnuboard5 기본 IP 차단 관리 -->
<div class="info-highlight">
    그누보드 기본 IP 차단 설정을 관리합니다. 여기에 등록된 IP는 전체 사이트 접속이 차단됩니다.
</div>


<!-- IP 차단 추가 폼 -->
<div class="extension-container">
    <div style="margin-bottom: 15px;">
        <span style="font-weight: bold; font-size: 16px; color: #333;">IP 차단 추가</span>
    </div>
    <form method="post" action="./section_block_post.php">
        <input type="hidden" name="action" value="add_block">
        <div style="display: grid; grid-template-columns: 2fr 3fr auto; gap: 15px; align-items: start;">
            <div class="form-group">
                <label class="form-label">차단할 IP 주소</label>
                <input type="text" name="block_ip" class="form-input" placeholder="예: 192.168.1.100 또는 192.168.1.*" required>
                <div class="form-help">와일드카드(*) 사용 가능: 192.168.1.*</div>
            </div>
            <div class="form-group">
                <label class="form-label">차단 사유 (선택사항)</label>
                <input type="text" name="block_reason" class="form-input" placeholder="스팸, 악성 행위 등">
                <div class="form-help" style="opacity: 0; height: 16px;">.</div>
            </div>
            <div style="margin-top: 24px;">
                <button type="submit" class="btn-primary" style="height: 40px;">차단 추가</button>
            </div>
        </div>
    </form>
</div>

<!-- 현재 차단된 IP 목록 -->
<div class="extension-container">
    <div style="margin-bottom: 15px;">
        <span style="font-weight: bold; font-size: 16px; color: #333;">현재 차단된 IP 목록</span>
        <span style="background: #dc2626; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px; font-weight: bold;">
            <?php echo count($blocked_ips); ?>개
        </span>
    </div>
    
    <?php if (empty($blocked_ips)): ?>
    <div style="text-align: center; padding: 50px 20px; background: #f0f9f0; border: 1px solid #d4edda; border-radius: 5px; margin: 0 20px 20px 20px;">
        <p style="margin: 10px 0; font-size: 18px; font-weight: bold; color: #28a745;">
            ✅ 차단된 IP가 없습니다.
        </p>
        <p style="margin: 10px 0; font-size: 16px; color: #155724;">
            현재 모든 IP에서 사이트 접속이 가능합니다.
        </p>
    </div>
    <?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>차단된 IP 주소</th>
            <th>차단 사유</th>
            <th>액션</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($blocked_ips as $index => $ip): ?>
        <tr>
            <td>
                <span class="ip-address"><?php echo htmlspecialchars($ip); ?></span>
            </td>
            <td>-</td>
            <td>
                <button type="button" class="btn-danger" onclick="removeBlockedIP('<?php echo htmlspecialchars($ip); ?>')">
                    삭제
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
    <?php endif; ?>
</div>


<script>
// 차단 IP 추가
document.querySelector('form[action="./section_block_post.php"]').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('./section_block_post.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 처리 중 오류가 발생했습니다.');
    });
});

// 차단 IP 삭제
function removeBlockedIP(ip) {
    if (!confirm('정말로 이 IP의 차단을 해제하시겠습니까?\n\nIP: ' + ip)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove_block');
    formData.append('ip', ip);
    
    fetch('./section_block_post.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('요청 처리 중 오류가 발생했습니다.');
    });
}

</script>
