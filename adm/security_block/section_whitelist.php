<?php
if (!defined('_GNUBOARD_')) {
    require_once './_common.php';
}

// 현재 접속 IP 가져오기
$current_admin_ip = $_SERVER['REMOTE_ADDR'];

// 화이트리스트 조회
$whitelist_sql = "SELECT * FROM " . G5_TABLE_PREFIX . "security_ip_whitelist ORDER BY sw_datetime DESC LIMIT 50";
$whitelist_result = sql_query($whitelist_sql, false);

// 예외 IP 통계
$whitelist_count_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_whitelist";
$whitelist_count_result = sql_query($whitelist_count_sql, false);
$whitelist_count = 0;
if ($whitelist_count_result && $row = sql_fetch_array($whitelist_count_result)) {
    $whitelist_count = $row['cnt'];
}
?>

<div class="info-highlight">
    여기에 등록된 IP는 모든 차단 규칙에서 제외됩니다.
</div>

<div class="extension-container">
    <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
        <span style="font-weight: bold; font-size: 16px; color: #333;">예외 IP 기능 상태</span>
        <div style="color: #28a745; font-weight: bold;">활성화됨</div>
    </div>
    <div class="extension-list">
        <div class="extension-item low">
            ✅ 예외 IP 기능 활성화
        </div>
        <div class="extension-item low">
            🔢 등록된 IP: <?php echo number_format($whitelist_count); ?>개
        </div>
    </div>
</div>

<div class="extension-container">
    <form method="post" action="./section_whitelist_post.php">
        <input type="hidden" name="action" value="add_whitelist">
        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: start;">
            <div class="form-group">
                <label class="form-label">IP 주소</label>
                <input type="text" name="whitelist_ip" class="form-input" value="<?php echo htmlspecialchars($current_admin_ip); ?>" required>
                <div class="form-help">차단에서 제외할 IP를 입력하세요</div>
            </div>
            <div class="form-group">
                <label class="form-label">메모</label>
                <input type="text" name="whitelist_memo" class="form-input" placeholder="설명을 입력하세요 (선택사항)">
                <div class="form-help">관리를 위한 설명을 입력하세요</div>
            </div>
            <div style="display: flex; align-items: end; margin-top: 28px;">
                <button type="submit" class="btn-primary">예외 IP 추가</button>
            </div>
        </div>
    </form>
</div>

<?php if ($whitelist_result && sql_num_rows($whitelist_result) > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>IP 주소</th>
            <th>메모</th>
            <th>등록일</th>
            <th>액션</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = sql_fetch_array($whitelist_result)): ?>
        <tr>
            <td>
                <span class="ip-address"><?php echo htmlspecialchars($row['sw_ip']); ?></span>
                <?php if ($row['sw_ip'] === $current_admin_ip): ?>
                    <span class="current-ip-badge">현재 IP</span>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['sw_memo']); ?></td>
            <td><?php echo substr($row['sw_datetime'], 0, 16); ?></td>
            <td>
                <button type="button" class="btn-danger" onclick="deleteWhitelist(<?php echo $row['sw_id']; ?>)">
                    삭제
                </button>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
<div class="empty-state">
    <p><strong>등록된 예외 IP가 없습니다</strong></p>
    <p>관리자 IP를 예외 목록에 추가하여 실수로 차단되는 것을 방지하세요.</p>
</div>
<?php endif; ?>