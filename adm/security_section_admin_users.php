<?php
// 관리자급 권한 보유 사용자 관리 섹션
if (!defined('_GNUBOARD_')) exit;

// 기본 회원가입 권한 레벨 가져오기
$config_sql = "SELECT cf_register_level FROM {$g5['config_table']}";
$config_result = sql_fetch($config_sql);
$default_member_level = isset($config_result['cf_register_level']) ? $config_result['cf_register_level'] : 2;

// 관리자급 권한 보유 사용자 조회 (레벨 10 이상)
$admin_users_sql = "SELECT mb_id, mb_name, mb_nick, mb_level, mb_today_login, mb_login_ip, mb_datetime 
                    FROM {$g5['member_table']} 
                    WHERE mb_level >= 10 
                    ORDER BY mb_level DESC, mb_datetime DESC";
$admin_users_result = sql_query($admin_users_sql);

$admin_users = array();
while ($user = sql_fetch_array($admin_users_result)) {
    $admin_users[] = $user;
}

$admin_user_count = count($admin_users);
$super_admin_count = 0;
$high_level_count = 0;

foreach ($admin_users as $user) {
    if ($user['mb_level'] >= 10) {
        $high_level_count++;
    }
    if ($user['mb_id'] == 'admin') {
        $super_admin_count++;
    }
}
?>

<!-- 관리자급 권한 보유 사용자 관리 -->
<div class="dashboard-section">
    <div class="section-header" onclick="toggleSection('admin-users-section')" style="cursor: pointer;">
        👥 관리자급 권한 보유 사용자 관리 <span id="admin-users-toggle" style="float: right; transition: transform 0.3s ease;">▶</span>
    </div>
    <div class="section-content" id="admin-users-section">
        <div class="info-highlight">
            관리자급 권한(레벨 10 이상)을 보유한 사용자를 확인하고 관리합니다.
        </div>
        
        <?php if ($admin_user_count > 1): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <div style="color: #dc2626; font-weight: bold; margin-bottom: 8px;">⚠️ 관리자급 권한 사용자 발견</div>
            <div style="color: #666; font-size: 13px;">
                현재 관리자급 권한을 보유한 사용자가 <?php echo $admin_user_count; ?>명 있습니다. 
                불필요한 관리자 권한은 보안상 위험할 수 있으므로 정기적으로 검토하시기 바랍니다.
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (count($admin_users) > 0): ?>
        <div class="extension-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div>
                    <span style="font-weight: bold; font-size: 16px; color: #333;">관리자급 권한 보유 사용자 목록</span>
                    <?php if ($high_level_count > 1): ?>
                    <span style="background: #dc2626; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px; font-weight: bold;">
                        위험 <?php echo $high_level_count; ?>명
                    </span>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="font-size: 12px; margin-right: 10px;">
                        <input type="checkbox" id="select-all-admin-users" style="margin-right: 5px;"> 전체 선택
                    </label>
                    <button onclick="resetSelectedUserPermissions()" 
                            style="background: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; font-weight: 600;">
                        선택한 사용자 권한 초기화 (레벨 <?php echo $default_member_level; ?>)
                    </button>
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($admin_users as $user): ?>
                <?php 
                $risk_level = '';
                $risk_color = '';
                if ($user['mb_level'] >= 10) {
                    $risk_level = '위험';
                    $risk_color = '#dc2626';
                }
                
                $last_login = $user['mb_today_login'] != '0000-00-00 00:00:00' ? 
                             substr($user['mb_today_login'], 0, 16) : '로그인 기록 없음';
                
                $join_date = substr($user['mb_datetime'], 0, 16);
                $is_super_admin = ($user['mb_id'] == 'admin');
                ?>
                <div style="background: <?php echo $is_super_admin ? '#e0f2fe' : '#fef2f2'; ?>; 
                           border: 1px solid <?php echo $is_super_admin ? '#0ea5e9' : '#fecaca'; ?>; 
                           border-radius: 8px; padding: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <?php if (!$is_super_admin): ?>
                                <input type="checkbox" class="admin-user-checkbox" value="<?php echo htmlspecialchars($user['mb_id']); ?>" 
                                       style="margin-right: 8px;">
                                <?php endif; ?>
                                <span style="font-weight: bold; font-size: 16px; color: #333;">
                                    <?php echo $is_super_admin ? '👑' : '🚨'; ?> <?php echo htmlspecialchars($user['mb_name']); ?>
                                    <?php if (!empty($user['mb_nick'])): ?>
                                        (<?php echo htmlspecialchars($user['mb_nick']); ?>)
                                    <?php endif; ?>
                                </span>
                                <span style="background: #f7fafc; color: #718096; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo htmlspecialchars($user['mb_id']); ?>
                                </span>
                                <span style="background: <?php echo $is_super_admin ? '#0ea5e9' : '#dc2626'; ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                    레벨 <?php echo $user['mb_level']; ?>
                                </span>
                            </div>
                            
                            <div style="font-size: 13px; color: #666; margin-bottom: 8px;">
                                <span style="margin-right: 15px;"><strong>가입일:</strong> <?php echo $join_date; ?></span>
                                <span style="margin-right: 15px;"><strong>최근로그인:</strong> <?php echo $last_login; ?></span>
                                <span><strong>로그인IP:</strong> <?php echo htmlspecialchars($user['mb_login_ip']); ?></span>
                            </div>
                            
                            <?php if ($is_super_admin): ?>
                            <div style="background: #cffafe; color: #0f766e; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-top: 8px;">
                                👑 이 계정은 최고 관리자로 시스템에서 보호되고 있습니다.
                            </div>
                            <?php else: ?>
                            <div style="background: #fee2e2; color: #dc2626; padding: 6px 10px; border-radius: 4px; font-size: 12px; margin-top: 8px;">
                                ⚠️ 이 사용자는 관리자와 동일한 권한을 가지고 있어 보안상 위험할 수 있습니다.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-left: 15px;">
                            <span style="padding: 6px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; color: white; background: <?php echo $is_super_admin ? '#0ea5e9' : $risk_color; ?>;">
                                <?php echo $is_super_admin ? '최고관리자' : $risk_level; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 50px 20px; background: #f0f9f0; border: 1px solid #d4edda; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 10px 0; font-size: 18px; font-weight: bold; color: #28a745;">
                ✅ 관리자급 권한 사용자가 없습니다.
            </p>
            <p style="margin: 10px 0; font-size: 16px; color: #155724;">
                현재 적절한 권한 관리가 이루어지고 있습니다.
            </p>
        </div>
        <?php endif; ?>
        
        <!-- 보안 가이드 -->
        <div class="recommendations">
            <h4>관리자 권한 보안 가이드</h4>
            <ul>
                <li><strong>1.</strong> 관리자급 권한(레벨 10)은 꼭 필요한 사용자에게만 부여하세요</li>
                <li><strong>2.</strong> 정기적으로 관리자 권한 사용자 목록을 검토하고 불필요한 권한을 제거하세요</li>
                <li><strong>3.</strong> 관리자 계정의 비밀번호는 복잡하게 설정하고 정기적으로 변경하세요</li>
                <li><strong>4.</strong> 관리자 계정의 로그인 기록을 정기적으로 확인하세요</li>
                <li><strong>5.</strong> 'admin' 계정은 시스템에서 자동으로 보호되어 권한 변경이 제한됩니다</li>
            </ul>
        </div>
    </div>
</div>