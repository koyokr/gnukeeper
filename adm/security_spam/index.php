<?php
require_once './_common.php';

$g5['title'] = '스팸 관리 대시보드';
include_once '../admin.head.php';
?>

<link rel="stylesheet" href="../css/gk_admin.css">

<!-- 전체 통계 개요 -->
<div class="overview-stats">
    <h2>📊 스팸 방어 현황</h2>
    <div class="overview-grid">
        <div class="overview-item">
            <div class="overview-number"><?php echo number_format($login_fail_count); ?></div>
            <div class="overview-label">로그인 실패 (24시간)</div>
        </div>
        <div class="overview-item">
            <div class="overview-number"><?php echo number_format($blocked_ip_count); ?></div>
            <div class="overview-label">현재 차단된 IP</div>
        </div>
        <div class="overview-item">
            <div class="overview-number"><?php echo number_format($spam_detected_count); ?></div>
            <div class="overview-label">스팸 탐지 (24시간)</div>
        </div>
        <div class="overview-item">
            <div class="overview-number">
                <?php
                $active_features = 0;
                if ($login_block_enabled == '1') $active_features++;
                if ($useragent_block_enabled == '1') $active_features++;
                if ($behavior_404_enabled == '1') $active_features++;
                if ($behavior_referer_enabled == '1') $active_features++;
                if ($multiuser_register_enabled == '1') $active_features++;
                if ($multiuser_login_enabled == '1') $active_features++;
                if ($regex_spam_enabled == '1') $active_features++;
                echo $active_features;
                ?>
            </div>
            <div class="overview-label">활성화된 보안 기능</div>
        </div>
    </div>
</div>

<!-- 기능별 관리 섹션 -->
<div class="dashboard-grid">
    <!-- 로그인 차단 -->
    <div class="dashboard-card">
        <h3>
            <span class="icon">🔐</span>
            로그인 차단 설정
            <span class="status-indicator <?php echo $login_block_enabled == '1' ? 'status-active' : 'status-inactive'; ?>"></span>
        </h3>
        <div class="description">
            로그인 실패 시도를 자동으로 감지하여 IP를 차단합니다.
        </div>
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo $login_fail_limit; ?></div>
                <div class="stat-label">최대 실패 횟수</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $login_fail_window; ?>분</div>
                <div class="stat-label">감지 윈도우</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $login_block_duration == 0 ? '영구' : $login_block_duration . '분'; ?></div>
                <div class="stat-label">차단 시간</div>
            </div>
        </div>
        <div class="actions">
            <a href="./login.php" class="btn-link">설정 관리</a>
        </div>
    </div>

    <!-- User-Agent 차단 -->
    <div class="dashboard-card">
        <h3>
            <span class="icon">🤖</span>
            User-Agent 차단
            <span class="status-indicator <?php echo $useragent_block_enabled == '1' ? 'status-active' : 'status-inactive'; ?>"></span>
        </h3>
        <div class="description">
            의심스러운 User-Agent를 감지하여 봇과 스크래퍼를 차단합니다.
        </div>
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo strtoupper($useragent_block_level); ?></div>
                <div class="stat-label">차단 수준</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">
                    <?php
                    $ua_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_memo LIKE '%User-Agent%' AND sb_status = 'active'")['cnt'] ?? 0;
                    echo number_format($ua_count);
                    ?>
                </div>
                <div class="stat-label">차단된 봇</div>
            </div>
        </div>
        <div class="actions">
            <a href="./useragent.php" class="btn-link">설정 관리</a>
        </div>
    </div>

    <!-- 이상 행위 차단 -->
    <div class="dashboard-card">
        <h3>
            <span class="icon">🚨</span>
            이상 행위 차단
            <span class="status-indicator <?php echo ($behavior_404_enabled == '1' || $behavior_referer_enabled == '1') ? 'status-active' : 'status-inactive'; ?>"></span>
        </h3>
        <div class="description">
            404 페이지 과도한 접근 및 잘못된 레퍼러를 감지하여 차단합니다.
        </div>
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo $behavior_404_limit; ?></div>
                <div class="stat-label">404 한계</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $behavior_404_window; ?>분</div>
                <div class="stat-label">감지 시간</div>
            </div>
        </div>
        <div class="actions">
            <a href="./behavior.php" class="btn-link">설정 관리</a>
        </div>
    </div>

    <!-- 다중 계정 차단 -->
    <div class="dashboard-card">
        <h3>
            <span class="icon">👥</span>
            다중 계정 차단
            <span class="status-indicator <?php echo ($multiuser_register_enabled == '1' || $multiuser_login_enabled == '1') ? 'status-active' : 'status-inactive'; ?>"></span>
        </h3>
        <div class="description">
            같은 IP에서의 다중 회원가입 및 로그인을 감지하여 차단합니다.
        </div>
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo $multiuser_register_limit; ?></div>
                <div class="stat-label">가입 한계</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $multiuser_login_limit; ?></div>
                <div class="stat-label">로그인 한계</div>
            </div>
        </div>
        <div class="actions">
            <a href="./multiuser.php" class="btn-link">설정 관리</a>
        </div>
    </div>

    <!-- 정규식 스팸 차단 -->
    <div class="dashboard-card">
        <h3>
            <span class="icon">🔍</span>
            정규식 스팸 차단
            <span class="status-indicator <?php echo $regex_spam_enabled == '1' ? 'status-active' : 'status-inactive'; ?>"></span>
        </h3>
        <div class="description">
            정규식 패턴을 사용하여 스팸 콘텐츠를 실시간으로 필터링합니다.
        </div>
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value">
                    <?php
                    $regex_rules_count = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_regex_spam WHERE srs_status = 'active'")['cnt'] ?? 0;
                    echo number_format($regex_rules_count);
                    ?>
                </div>
                <div class="stat-label">활성 규칙</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo strtoupper($regex_spam_action); ?></div>
                <div class="stat-label">처리 방식</div>
            </div>
        </div>
        <div class="actions">
            <a href="./regex.php" class="btn-link">설정 관리</a>
        </div>
    </div>

    <!-- 통합 설정 -->
    <div class="dashboard-card">
        <h3>
            <span class="icon">⚙️</span>
            통합 설정 업데이트
        </h3>
        <div class="description">
            모든 스팸 방어 설정을 일괄적으로 업데이트하고 관리합니다.
        </div>
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value">
                    <?php
                    $total_settings = sql_fetch("SELECT COUNT(*) as cnt FROM " . G5_TABLE_PREFIX . "security_config WHERE sc_key LIKE '%_enabled'")['cnt'] ?? 0;
                    echo $total_settings;
                    ?>
                </div>
                <div class="stat-label">총 설정 항목</div>
            </div>
        </div>
        <div class="actions">
            <a href="./security_spam_update.php" class="btn-link">업데이트 스크립트</a>
        </div>
    </div>
</div>

<!-- 최근 활동 요약 -->
<div style="background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 30px;">
    <h3 style="margin: 0 0 20px 0; font-size: 20px; color: #333;">📈 최근 보안 활동</h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <!-- 최근 로그인 실패 -->
        <div>
            <h4 style="margin: 0 0 10px 0; color: #666;">최근 로그인 실패</h4>
            <?php
            $recent_login_fails = sql_query("SELECT slf_ip, slf_datetime FROM " . G5_TABLE_PREFIX . "security_login_fail ORDER BY slf_datetime DESC LIMIT 5", false);
            if ($recent_login_fails && sql_num_rows($recent_login_fails) > 0):
            ?>
                <ul style="margin: 0; padding: 0; list-style: none;">
                    <?php while ($fail = sql_fetch_array($recent_login_fails)): ?>
                        <li style="padding: 5px 0; border-bottom: 1px solid #eee; font-size: 13px;">
                            <span style="font-family: monospace; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">
                                <?php echo htmlspecialchars($fail['slf_ip']); ?>
                            </span>
                            <span style="color: #666; margin-left: 10px;">
                                <?php echo date('m/d H:i', strtotime($fail['slf_datetime'])); ?>
                            </span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p style="color: #999; font-size: 13px;">최근 로그인 실패가 없습니다.</p>
            <?php endif; ?>
        </div>

        <!-- 최근 차단된 IP -->
        <div>
            <h4 style="margin: 0 0 10px 0; color: #666;">최근 차단된 IP</h4>
            <?php
            $recent_blocks = sql_query("SELECT sb_ip, sb_datetime FROM " . G5_TABLE_PREFIX . "security_ip_block WHERE sb_status = 'active' ORDER BY sb_datetime DESC LIMIT 5", false);
            if ($recent_blocks && sql_num_rows($recent_blocks) > 0):
            ?>
                <ul style="margin: 0; padding: 0; list-style: none;">
                    <?php while ($block = sql_fetch_array($recent_blocks)): ?>
                        <li style="padding: 5px 0; border-bottom: 1px solid #eee; font-size: 13px;">
                            <span style="font-family: monospace; background: #ffebee; color: #c62828; padding: 2px 6px; border-radius: 3px;">
                                <?php echo htmlspecialchars($block['sb_ip']); ?>
                            </span>
                            <span style="color: #666; margin-left: 10px;">
                                <?php echo date('m/d H:i', strtotime($block['sb_datetime'])); ?>
                            </span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p style="color: #999; font-size: 13px;">최근 차단된 IP가 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="./security_spam.js"></script>

<?php
include_once '../admin.tail.php';
?>