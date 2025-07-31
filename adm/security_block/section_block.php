<?php
if (!defined('_GNUBOARD_')) {
    require_once './_common.php';
}

// 현재 접속 IP 가져오기
$current_admin_ip = $_SERVER['REMOTE_ADDR'];

// 현재 설정값 가져오기
$ip_block_enabled = gk_get_config('ip_block_enabled', '1');

// 차단 목록 조회
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$block_sql = "SELECT * FROM " . G5_TABLE_PREFIX . "security_ip_block ORDER BY sb_datetime DESC LIMIT {$offset}, {$per_page}";
$block_result = sql_query($block_sql, false);

// 통계 계산
$total_blocks = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_status = 'active'")['cnt'] ?? 0;
$auto_blocks = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_status = 'active' AND sb_block_type != 'manual'")['cnt'] ?? 0;

?>

<!-- gnuboard5 통합 안내 -->
<div class="info-highlight">
    IP 차단 기능은 gnuboard5의 기본 IP 차단 기능을 대체합니다. 스팸 차단 기능과 연동됩니다.
</div>

<!-- IP 차단 추가 폼 -->
<div class="extension-container">
    <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
        <span style="font-weight: bold; font-size: 16px; color: #333;">IP 차단 기능 상태</span>
        <div class="feature-switch <?php echo $ip_block_enabled == '1' ? 'enabled' : ''; ?>"
             onclick="toggleIPBlockFeature()"
             data-enabled="<?php echo $ip_block_enabled; ?>">
            <div class="feature-switch-handle"></div>
        </div>
    </div>
    <div class="extension-list">
        <div class="extension-item <?php echo $ip_block_enabled == '1' ? 'low' : 'high'; ?>">
            <?php echo $ip_block_enabled == '1' ? '✅' : '❌'; ?>
            IP 차단 <?php echo $ip_block_enabled == '1' ? '활성화' : '비활성화'; ?>
        </div>
    </div>
</div>

<div class="extension-container">
    <form method="post" action="./section_block_post.php">
        <input type="hidden" name="action" value="add_block">
        <input type="hidden" name="duration" value="permanent">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: start;">
            <div class="form-group">
                <label class="form-label">IP/CIDR</label>
                <input type="text" name="block_ip" class="form-input" placeholder="예: 192.168.1.100 또는 192.168.1.0/24" required>
                <div class="form-help">단일 IP 또는 CIDR 표기법으로 IP 대역을 입력하세요</div>
            </div>
            <div class="form-group">
                <label class="form-label">차단 사유</label>
                <input type="text" name="block_reason" class="form-input" placeholder="차단 사유를 입력하세요" required>
            </div>
            <div class="form-group">
                <label class="form-label">차단 수준</label>
                <div style="display: flex; gap: 15px;">
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="block_level[]" value="access" checked> 접속 차단
                    </label>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="block_level[]" value="login"> 로그인/회원가입 차단
                    </label>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="block_level[]" value="content"> 게시글/댓글/문의/쪽지 차단
                    </label>
                </div>
            </div>
            <div style="display: flex; align-items: end; margin-top: 28px;">
                <button type="submit" class="btn-primary">차단 IP 추가</button>
            </div>
        </div>
    </form>
</div>

<?php if ($block_result && sql_num_rows($block_result) > 0): ?>
    <form id="bulk-form">
    <table class="data-table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" class="checkbox-all" onchange="toggleAllCheckboxes(this)">
                    </th>
                    <th>IP/CIDR</th>
                    <th>차단 사유</th>
                    <th>차단 수준</th>
                    <th>유형</th>
                    <th>기간</th>
                    <th>적중</th>
                    <th>상태</th>
                    <th>등록일</th>
                    <th>액션</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = sql_fetch_array($block_result)): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="selected_ids[]" value="<?php echo $row['sb_id']; ?>" class="row-checkbox">
                    </td>
                    <td>
                        <span class="ip-address"><?php echo htmlspecialchars($row['sb_ip']); ?></span>
                        <?php if (strpos($row['sb_ip'], '/') !== false): ?>
                        <small style="color: #718096;">(대역)</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['sb_reason']); ?></td>
                    <td>
                        <?php
                        $block_levels = explode(',', $row['sb_block_level'] ?? 'access');
                        $level_display = [];
                        foreach ($block_levels as $level) {
                            $level = trim($level);
                            switch($level) {
                                case 'access': $level_display[] = '접속'; break;
                                case 'login': $level_display[] = '로그인/회원가입'; break;
                                case 'content': $level_display[] = '게시글/댓글/문의/쪽지'; break;
                                // 기존 데이터 호환성을 위한 처리
                                case 'write': $level_display[] = '작성'; break;
                                case 'memo': $level_display[] = '쪽지'; break;
                            }
                        }
                        echo implode(', ', $level_display);
                        ?>
                    </td>
                    <td>
                        <span class="block-type-badge block-type-<?php echo $row['sb_block_type'] == 'manual' ? 'manual' : 'auto'; ?>">
                            <?php
                            switch($row['sb_block_type']) {
                                case 'manual': echo '수동'; break;
                                case 'auto_login': echo '로그인'; break;
                                case 'auto_spam': echo '스팸'; break;
                                case 'auto_abuse': echo '악성'; break;
                                default: echo '자동';
                            }
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['sb_duration'] == 'permanent'): ?>
                            <strong>영구</strong>
                        <?php else: ?>
                            임시<br>
                            <small style="color: #718096;"><?php echo $row['sb_end_datetime']; ?></small>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo number_format($row['sb_hit_count']); ?></strong></td>
                    <td>
                        <span class="status-badge status-<?php echo $row['sb_status']; ?>">
                            <?php
                            switch($row['sb_status']) {
                                case 'active': echo '활성'; break;
                                case 'inactive': echo '비활성'; break;
                                case 'expired': echo '만료'; break;
                                default: echo $row['sb_status'];
                            }
                            ?>
                        </span>
                    </td>
                    <td><?php echo substr($row['sb_datetime'], 0, 16); ?></td>
                    <td>
                        <?php if ($row['sb_status'] == 'inactive'): ?>
                            <button type="button" class="btn-primary btn-small"
                                    onclick="toggleBlock(<?php echo $row['sb_id']; ?>, '<?php echo $row['sb_status']; ?>')">
                                활성화
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn-danger btn-small"
                                onclick="deleteBlock(<?php echo $row['sb_id']; ?>)">
                            삭제
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
    </table>
    </form>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📝</div>
        <h3>차단된 IP가 없습니다</h3>
        <p>아직 차단된 IP 주소가 없습니다. 위의 폼을 사용하여 IP를 차단하세요.</p>
    </div>
<?php endif; ?>
