<?php
if (!defined('_GNUBOARD_')) {
    require_once './_common.php';
}

// 현재 설정값 가져오기
$foreign_enabled = gk_get_config('foreign_block_enabled', '0');
$foreign_level = gk_get_config('foreign_block_level', 'access');

// 해외 IP 차단 통계
$foreign_block_count_sql = "SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_block_type = 'auto_foreign'";
$foreign_block_count_result = sql_query($foreign_block_count_sql, false);
$foreign_block_count = 0;
if ($foreign_block_count_result && $row = sql_fetch_array($foreign_block_count_result)) {
    $foreign_block_count = $row['cnt'];
}

// korea_ip_list.txt 파일 로드 상태 확인
$korea_ip_file = __DIR__ . '/korea_ip_list.txt';
$korea_ip_loaded = false;
$korea_ip_count = 0;
if (file_exists($korea_ip_file)) {
    $korea_ip_lines = file($korea_ip_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($korea_ip_lines !== false) {
        $korea_ip_count = count($korea_ip_lines);
        $korea_ip_loaded = true;
    }
}
?>

<!-- gnuboard5 통합 안내 -->
<div class="info-highlight">
    해외 IP를 차단하는 기능입니다.
</div>

<!-- 해외 IP 차단 설정 -->
<div class="extension-container">
    <div style="margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
        <span style="font-weight: bold; font-size: 16px; color: #333;">해외 IP 차단 기능 상태</span>
        <div class="feature-switch <?php echo $foreign_enabled === '1' ? 'enabled' : ''; ?>"
             onclick="toggleForeignBlockFeature()"
             data-enabled="<?php echo $foreign_enabled; ?>">
            <div class="feature-switch-handle"></div>
        </div>
    </div>
    <div class="extension-list">
        <div class="extension-item <?php echo $foreign_enabled === '1' ? 'low' : 'high'; ?>">
            <?php echo $foreign_enabled === '1' ? '✅' : '❌'; ?>
            해외 IP 차단 <?php echo $foreign_enabled === '1' ? '활성화' : '비활성화'; ?>
        </div>
        <div class="extension-item low">
            🌏 차단된 해외 IP: <?php echo number_format($foreign_block_count); ?>개
        </div>
        <div class="extension-item <?php echo $korea_ip_loaded ? 'low' : 'high'; ?>">
            <?php echo $korea_ip_loaded ? '📋' : '⚠️'; ?>
            한국 IP 목록: <?php echo $korea_ip_loaded ? number_format($korea_ip_count) . '개 로드됨' : '로드 실패'; ?>
        </div>
    </div>
</div>

<div class="extension-container">
    <form method="post" action="./section_foreign_post.php">
        <input type="hidden" name="action" value="save_foreign_config">
        <div style="display: grid; grid-template-columns: 1fr auto; gap: 15px; align-items: start;">
            <div class="form-group">
                <label class="form-label">차단 수준</label>
                <div style="display: flex; gap: 15px;">
                    <?php $foreign_levels = explode(',', $foreign_level); ?>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="foreign_block_level[]" value="access" <?php echo in_array('access', $foreign_levels) ? 'checked' : ''; ?>> 접속 차단
                    </label>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="foreign_block_level[]" value="login" <?php echo in_array('login', $foreign_levels) ? 'checked' : ''; ?>> 로그인/회원가입 차단
                    </label>
                    <label style="font-weight: normal;">
                        <input type="checkbox" name="foreign_block_level[]" value="content" <?php echo in_array('content', $foreign_levels) ? 'checked' : ''; ?>> 게시글/댓글/문의/쪽지 차단
                    </label>
                </div>
            </div>
            <div style="display: flex; align-items: end; margin-top: 28px;">
                <button type="submit" class="btn-primary">설정 저장</button>
            </div>
        </div>
    </form>
</div>