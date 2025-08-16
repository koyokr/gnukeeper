<?php
/**
 * GnuKeeper Version Manager
 * 
 * 버전 관리 및 업데이트 체크를 담당하는 클래스
 */

if (!defined('_GNUBOARD_')) exit;

class GK_VersionManager {
    
    const CACHE_FILE = 'version_check.json';
    const CACHE_TIME = 3600; // 1시간
    const GITHUB_API_URL = 'https://api.github.com/repos';
    
    private static $instance = null;
    private $cache_dir;
    
    private function __construct() {
        $this->cache_dir = G5_DATA_PATH . '/cache';
        $this->ensureCacheDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 캐시 디렉토리 확인 및 생성
     */
    private function ensureCacheDirectory() {
        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * GnuKeeper 현재 버전 가져오기
     */
    public function getCurrentVersion() {
        return defined('GK_VERSION') ? GK_VERSION : '0.0.0';
    }
    
    /**
     * Gnuboard5 현재 버전 가져오기
     */
    public function getGnuboardCurrentVersion() {
        return defined('G5_GNUBOARD_VER') ? G5_GNUBOARD_VER : '0.0.0';
    }
    
    /**
     * GitHub에서 최신 릴리스 버전 가져오기
     */
    public function getGithubLatestVersion($repo) {
        $api_url = self::GITHUB_API_URL . "/{$repo}/releases/latest";
        
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
                // v1.0.0 형식에서 버전 번호만 추출
                return ltrim($data['tag_name'], 'v');
            }
        }
        
        return null;
    }
    
    /**
     * Gnuboard5 최신 버전 가져오기
     */
    public function getGnuboardLatestVersion() {
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
    
    /**
     * 버전 비교
     * @return string 'latest', 'outdated', 'newer', 'unknown'
     */
    public function compareVersions($current, $latest) {
        if (!$latest) return 'unknown';
        
        $result = version_compare($current, $latest);
        if ($result < 0) return 'outdated';
        if ($result > 0) return 'newer';
        return 'latest';
    }
    
    /**
     * 캐시된 버전 정보 가져오기
     */
    public function getCachedVersionInfo() {
        $cache_file = $this->cache_dir . '/' . self::CACHE_FILE;
        
        if (file_exists($cache_file)) {
            $data = json_decode(file_get_contents($cache_file), true);
            if ($data && isset($data['timestamp'])) {
                if ((time() - $data['timestamp']) < self::CACHE_TIME) {
                    return $data;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 버전 정보 캐시 저장
     */
    public function saveCachedVersionInfo($data) {
        $cache_file = $this->cache_dir . '/' . self::CACHE_FILE;
        $data['timestamp'] = time();
        return file_put_contents($cache_file, json_encode($data));
    }
    
    /**
     * 캐시 강제 삭제
     */
    public function clearCache() {
        $cache_file = $this->cache_dir . '/' . self::CACHE_FILE;
        if (file_exists($cache_file)) {
            return unlink($cache_file);
        }
        return true;
    }
    
    /**
     * 설정에서 GitHub 레포지토리 정보 가져오기
     */
    public function getGithubRepoFromConfig() {
        return defined('GK_GITHUB_REPO') ? GK_GITHUB_REPO : null;
    }
    
    /**
     * 전체 버전 정보 가져오기 (캐시 활용)
     */
    public function getVersionInfo($github_repo = null, $force_refresh = false) {
        // 강제 새로고침이면 캐시 삭제
        if ($force_refresh) {
            $this->clearCache();
        }
        
        // 캐시 확인
        $cached = $this->getCachedVersionInfo();
        if ($cached && !$force_refresh) {
            return $cached;
        }
        
        // 새로운 정보 가져오기
        $info = array(
            'gk_current' => $this->getCurrentVersion(),
            'g5_current' => $this->getGnuboardCurrentVersion(),
            'gk_latest' => null,
            'g5_latest' => null,
            'gk_status' => 'unknown',
            'g5_status' => 'unknown'
        );
        
        // GitHub 레포지토리 정보가 제공되지 않은 경우 설정에서 가져오기
        if (!$github_repo) {
            $github_repo = $this->getGithubRepoFromConfig();
        }
        
        // GitHub 레포지토리 정보가 있는 경우 GnuKeeper 최신 버전 확인
        if ($github_repo) {
            $info['gk_latest'] = $this->getGithubLatestVersion($github_repo);
            $info['gk_status'] = $this->compareVersions($info['gk_current'], $info['gk_latest']);
            $info['github_repo'] = $github_repo;
        }
        
        // Gnuboard5 최신 버전 확인
        $info['g5_latest'] = $this->getGnuboardLatestVersion();
        $info['g5_status'] = $this->compareVersions($info['g5_current'], $info['g5_latest']);
        
        // 캐시 저장
        $this->saveCachedVersionInfo($info);
        
        return $info;
    }
    
    /**
     * 업데이트 가능 여부 확인
     */
    public function hasUpdate($github_repo = null) {
        $info = $this->getVersionInfo($github_repo);
        return ($info['gk_status'] == 'outdated' || $info['g5_status'] == 'outdated');
    }
}
?>