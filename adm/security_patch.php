<?php
$sub_menu = '950600';
require_once './_common.php';
require_once G5_PATH . '/plugin/gnukeeper/bootstrap.php';

auth_check_menu($auth, $sub_menu, 'r');

$g5['title'] = '패치관리';
require_once './admin.head.php';

// 공통 보안 CSS 포함
echo '<link rel="stylesheet" href="'.G5_ADMIN_URL.'/css/security_common.css?ver='.G5_CSS_VER.'">';

// GitHub API로 최신 버전 정보 가져오기
function get_github_latest_version($repo) {
    $api_url = "https://api.github.com/repos/{$repo}/releases/latest";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GnuKeeper Plugin');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['tag_name'])) {
            // v1.0.0 -> 1.0.0
            return ltrim($data['tag_name'], 'v');
        }
    }
    
    return null;
}

// 버전 비교 함수
function compare_versions($current, $latest) {
    if (!$latest) return 'unknown';
    
    $result = version_compare($current, $latest);
    if ($result < 0) return 'outdated';
    if ($result > 0) return 'newer';
    return 'latest';
}

// Gnuboard5 최신 버전 정보 가져오기
function get_gnuboard_latest_version() {
    // Gnuboard5 GitHub API에서 최신 릴리스 정보 가져오기
    $api_url = 'https://api.github.com/repos/gnuboard/gnuboard5/releases/latest';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GnuKeeper Plugin');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/vnd.github.v3+json'
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['tag_name'])) {
            // v5.6.15 형식에서 버전 번호만 추출
            return ltrim($data['tag_name'], 'v');
        }
    }
    
    return null;
}

// 현재 버전 정보
$gk_current_version = defined('GK_VERSION') ? GK_VERSION : '0.0.0';
$g5_current_version = defined('G5_GNUBOARD_VER') ? G5_GNUBOARD_VER : '0.0.0';

// 최신 버전 정보 (캐시 사용)
$cache_file = G5_DATA_PATH . '/cache/version_check.json';
$cache_time = 3600; // 1시간 캐시

$need_update = true;
$cached_data = null;

if (file_exists($cache_file)) {
    $cached_data = json_decode(file_get_contents($cache_file), true);
    if ($cached_data && isset($cached_data['timestamp']) && (time() - $cached_data['timestamp']) < $cache_time) {
        $need_update = false;
    }
}

if ($need_update) {
    // 설정 파일에서 GitHub 레포지토리 정보 가져오기
    $github_repo = defined('GK_GITHUB_REPO') ? GK_GITHUB_REPO : 'gnsehfvlr/gnuboard5_security';
    
    $gk_latest_version = get_github_latest_version($github_repo);
    $g5_latest_version = get_gnuboard_latest_version();
    
    $cached_data = array(
        'timestamp' => time(),
        'gk_latest' => $gk_latest_version,
        'g5_latest' => $g5_latest_version,
        'github_repo' => $github_repo
    );
    
    // 캐시 디렉토리 생성
    $cache_dir = G5_DATA_PATH . '/cache';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    
    file_put_contents($cache_file, json_encode($cached_data));
} else {
    $gk_latest_version = $cached_data['gk_latest'];
    $g5_latest_version = $cached_data['g5_latest'];
    $github_repo = isset($cached_data['github_repo']) ? $cached_data['github_repo'] : 'gnsehfvlr/gnuboard5_security';
}

// 버전 상태 확인
$gk_status = compare_versions($gk_current_version, $gk_latest_version);
$g5_status = compare_versions($g5_current_version, $g5_latest_version);

?>

<style>
.patch-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 20px;
}

.patch-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.patch-header h1 {
    font-size: 28px;
    margin: 0 0 10px 0;
}

.patch-header p {
    margin: 0;
    opacity: 0.9;
}

.version-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
    margin-bottom: 30px;
}

.version-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
}

.version-card h3 {
    font-size: 20px;
    margin: 0 0 20px 0;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.version-icon {
    width: 30px;
    height: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.version-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding: 15px;
    background: #f9fafb;
    border-radius: 8px;
}

.version-label {
    font-weight: 500;
    color: #6b7280;
}

.version-value {
    font-weight: 600;
    font-size: 18px;
    color: #111827;
}

.version-status {
    margin-top: 20px;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
}

.status-latest {
    background: #d1fae5;
    color: #065f46;
}

.status-outdated {
    background: #fee2e2;
    color: #991b1b;
}

.status-unknown {
    background: #f3f4f6;
    color: #6b7280;
}

.status-newer {
    background: #dbeafe;
    color: #1e40af;
}

.update-button {
    display: block;
    width: 100%;
    margin-top: 15px;
    padding: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
}

.update-button:hover {
    opacity: 0.9;
}

.update-button:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.info-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
}

.info-section h3 {
    font-size: 18px;
    margin: 0 0 15px 0;
    color: #333;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    color: #4b5563;
}

.info-list li:last-child {
    border-bottom: none;
}

</style>

<div class="patch-container">
    <div class="patch-header">
        <h1>패치관리</h1>
        <p>시스템 및 플러그인의 버전을 확인하고 최신 업데이트를 관리합니다.</p>
    </div>

    
    <div class="version-grid">
        <!-- Gnuboard5 버전 카드 -->
        <div class="version-card">
            <h3>
                <div class="version-icon">G5</div>
                gnuboard5
            </h3>
            
            <div class="version-info">
                <span class="version-label">현재 버전</span>
                <span class="version-value"><?php echo $g5_current_version; ?></span>
            </div>
            
            <div class="version-info">
                <span class="version-label">최신 버전</span>
                <span class="version-value">
                    <?php if ($g5_latest_version): ?>
                        <?php echo $g5_latest_version; ?>
                    <?php else: ?>
                        확인 불가
                    <?php endif; ?>
                </span>
            </div>
            
            <?php if ($g5_status == 'latest'): ?>
                <div class="version-status status-latest">
                    ✓ 최신 버전을 사용중입니다
                </div>
            <?php elseif ($g5_status == 'outdated'): ?>
                <div class="version-status status-outdated">
                    ⚠ 새로운 버전이 있습니다
                </div>
                <a href="https://sir.kr/g5_pds" target="_blank" class="update-button">
                    업데이트 페이지로 이동
                </a>
            <?php elseif ($g5_status == 'newer'): ?>
                <div class="version-status status-newer">
                    ℹ 개발 버전을 사용중입니다
                </div>
            <?php else: ?>
                <div class="version-status status-unknown">
                    버전 정보를 확인할 수 없습니다
                </div>
            <?php endif; ?>
        </div>

        <!-- GnuKeeper 버전 카드 -->
        <div class="version-card">
            <h3>
                <div class="version-icon">GK</div>
                gnukeeper
            </h3>
            
            <div class="version-info">
                <span class="version-label">현재 버전</span>
                <span class="version-value"><?php echo $gk_current_version; ?></span>
            </div>
            
            <div class="version-info">
                <span class="version-label">최신 버전</span>
                <span class="version-value">
                    <?php if ($gk_latest_version): ?>
                        <?php echo $gk_latest_version; ?>
                    <?php else: ?>
                        확인 불가
                    <?php endif; ?>
                </span>
            </div>
            
            <?php if ($gk_status == 'latest'): ?>
                <div class="version-status status-latest">
                    ✓ 최신 버전을 사용중입니다
                </div>
            <?php elseif ($gk_status == 'outdated'): ?>
                <div class="version-status status-outdated">
                    ⚠ 새로운 버전이 있습니다
                </div>
                <a href="https://github.com/<?php echo htmlspecialchars($github_repo); ?>/releases/latest" 
                   target="_blank" class="update-button">
                    업데이트 다운로드
                </a>
            <?php elseif ($gk_status == 'newer'): ?>
                <div class="version-status status-newer">
                    ℹ 개발 버전을 사용중입니다
                </div>
            <?php else: ?>
                <div class="version-status status-unknown">
                    버전 정보를 확인할 수 없습니다
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-section">
        <h3>업데이트 안내</h3>
        <ul class="info-list">
            <li>• 업데이트 전 반드시 백업을 진행해주세요.</li>
            <li>• GnuKeeper는 GitHub Release를 통해 배포됩니다.</li>
            <li>• Gnuboard5 업데이트는 공식 사이트를 통해 진행하세요.</li>
            <li>• 버전 정보는 1시간마다 자동으로 갱신됩니다.</li>
        </ul>
    </div>
</div>


<?php
require_once './admin.tail.php';
?>