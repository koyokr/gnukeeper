<?php
// 확장자 정책 관리 카드
if (!defined('_GNUBOARD_')) exit;

// 확장자 위험도 분석 데이터
$extension_risks = array(
    'high' => array(
        'exe' => '실행 파일 - 악성코드, 바이러스 실행 가능',
        'bat' => '배치 파일 - 시스템 명령어 실행으로 서버 해킹 위험',
        'cmd' => '윈도우 명령 파일 - 시스템 조작 가능',
        'com' => 'DOS 실행 파일 - 구형 악성코드 실행 위험',
        'scr' => '스크린세이버 파일 - 악성코드 은닉 수단으로 활용',
        'pif' => 'DOS 프로그램 정보 파일 - 실행 파일 위장 가능',
        'php' => 'PHP 스크립트 - 웹셸 업로드로 서버 완전 장악 위험',
        'asp' => 'ASP 스크립트 - IIS 서버에서 백도어 생성 가능',
        'aspx' => 'ASP.NET 파일 - .NET 서버에서 원격 제어 위험',
        'jsp' => 'Java Server Pages - 톰캣 서버 해킹 위험',
        'pl' => 'Perl 스크립트 - Unix/Linux 서버 공격 가능',
        'py' => 'Python 스크립트 - 서버 시스템 조작 위험',
        'rb' => 'Ruby 스크립트 - 웹 어플리케이션 공격 가능',
        'cgi' => 'CGI 스크립트 - 웹서버 백도어 생성 위험',
        'sh' => 'Shell 스크립트 - Linux/Unix 시스템 명령 실행',
        'jar' => 'Java 아카이브 - 악성 Java 코드 실행 위험'
    ),
    'medium' => array(
        'js' => 'JavaScript 파일 - XSS 공격, 브라우저 조작 가능',
        'vbs' => 'VBScript 파일 - 윈도우에서 시스템 조작 위험',
        'html' => 'HTML 파일 - XSS 공격, 피싱 페이지 생성 가능',
        'htm' => 'HTML 파일 - 악성 스크립트 포함 위험',
        'xml' => 'XML 파일 - XXE 공격, 외부 엔티티 참조 위험',
        'xsl' => 'XSL 스타일시트 - 스크립트 실행 위험',
        'svg' => 'SVG 벡터 이미지 - 스크립트 실행 및 XSS 공격',
        'hta' => 'HTML Application - 시스템 권한으로 실행 위험',
        'sql' => 'SQL 파일 - 데이터베이스 조작 및 정보 유출',
        'reg' => '레지스트리 파일 - 윈도우 시스템 설정 변경',
        'msi' => '설치 패키지 - 악성 소프트웨어 설치 위험'
    ),
    'low' => array(
        'jpg' => 'JPEG 이미지',
        'jpeg' => 'JPEG 이미지',
        'png' => 'PNG 이미지',
        'gif' => 'GIF 이미지',
        'bmp' => 'BMP 이미지',
        'webp' => 'WebP 이미지',
        'ico' => '아이콘 파일',
        'pdf' => 'PDF 문서',
        'doc' => 'MS Word 문서',
        'docx' => 'MS Word 문서',
        'xls' => 'MS Excel 문서',
        'xlsx' => 'MS Excel 문서',
        'ppt' => 'MS PowerPoint',
        'pptx' => 'MS PowerPoint',
        'txt' => '텍스트 파일',
        'rtf' => 'Rich Text Format',
        'csv' => 'CSV 데이터',
        'mp3' => 'MP3 오디오',
        'wav' => 'WAV 오디오',
        'ogg' => 'OGG 오디오',
        'mp4' => 'MP4 비디오',
        'avi' => 'AVI 비디오',
        'mov' => 'QuickTime 비디오',
        'wmv' => 'Windows Media Video',
        'asx' => 'Windows Media 재생목록',
        'asf' => 'Windows Media 컨테이너',
        'wma' => 'Windows Media Audio',
        'mpg' => 'MPEG 비디오 파일',
        'mpeg' => 'MPEG 비디오 파일',
        'zip' => 'ZIP 압축 파일',
        'rar' => 'RAR 압축 파일',
        '7z' => '7-Zip 압축 파일',
        'tar' => 'TAR 아카이브',
        'gz' => 'GZIP 압축 파일',
        'hwp' => '한글 문서',
        'swf' => 'Flash 파일'
    )
);

// 현재 설정된 확장자 분석
function analyzeCurrentExtensions() {
    global $g5, $extension_risks;

    // 모든 업로드 관련 확장자 필드 가져오기
    $sql = "SELECT cf_image_extension, cf_flash_extension, cf_movie_extension FROM {$g5['config_table']}";
    $row = sql_fetch($sql);

    $all_extensions = array();

    // 각 필드에서 확장자 추출
    $extension_fields = array(
        'cf_image_extension' => isset($row['cf_image_extension']) ? $row['cf_image_extension'] : '',
        'cf_flash_extension' => isset($row['cf_flash_extension']) ? $row['cf_flash_extension'] : '',
        'cf_movie_extension' => isset($row['cf_movie_extension']) ? $row['cf_movie_extension'] : ''
    );

    foreach ($extension_fields as $field_name => $field_value) {
        if (!empty($field_value)) {
            $exts = explode('|', strtolower(trim($field_value)));
            $all_extensions = array_merge($all_extensions, $exts);
        }
    }

    // 중복 제거 및 빈 값 제거
    $current_extensions = array_unique(array_filter(array_map('trim', $all_extensions)));

    $analysis = array(
        'total' => count($current_extensions),
        'high' => 0,
        'medium' => 0,
        'low' => 0,
        'unknown' => 0,
        'extensions' => array()
    );

    foreach ($current_extensions as $ext) {
        $ext = strtolower(trim($ext));
        if (empty($ext)) continue;

        $risk_level = 'unknown';
        $description = '알 수 없는 확장자 - 보안 검토 필요';

        foreach ($extension_risks as $level => $risks) {
            if (isset($risks[$ext])) {
                $risk_level = $level;
                $description = $risks[$ext];
                break;
            }
        }

        $analysis[$risk_level]++;
        $analysis['extensions'][] = array(
            'name' => $ext,
            'risk' => $risk_level,
            'description' => $description
        );
    }

    return $analysis;
}

$analysis = analyzeCurrentExtensions();

// 전체 보안 등급 결정
function getOverallSecurityGrade($analysis) {
    if ($analysis['high'] > 0) {
        return array(
            'grade' => '위험',
            'color' => '#dc3545',
            'icon' => '🚨',
            'description' => '매우 위험한 확장자가 허용되어 있습니다!'
        );
    } elseif ($analysis['medium'] > 3) {
        return array(
            'grade' => '주의',
            'color' => '#fd7e14',
            'icon' => '⚠️',
            'description' => '주의가 필요한 확장자가 다수 허용되어 있습니다.'
        );
    } elseif ($analysis['medium'] > 0) {
        return array(
            'grade' => '보통',
            'color' => '#ffc107',
            'icon' => '⚡',
            'description' => '일부 주의 확장자가 허용되어 있습니다.'
        );
    } elseif ($analysis['unknown'] > 0) {
        return array(
            'grade' => '검토',
            'color' => '#6f42c1',
            'icon' => '🔍',
            'description' => '알 수 없는 확장자가 있어 검토가 필요합니다.'
        );
    } else {
        return array(
            'grade' => '안전',
            'color' => '#28a745',
            'icon' => '✅',
            'description' => '안전한 확장자만 허용되어 있습니다.'
        );
    }
}

$security_grade = getOverallSecurityGrade($analysis);
?>

<!-- 확장자 정책 관리 -->
<div class="card">
    <div class="card-header" onclick="toggleCard('extension-section')">
        📁 확장자 정책 관리 <span id="extension-toggle">▶</span>
    </div>
    <div class="card-content" id="extension-section">
        <div class="info-highlight">
            웹에서 업로드 가능한 파일 확장자를 관리하고 보안 위험성을 확인합니다.
        </div>

        <!-- 허용된 확장자 목록 -->
        <div class="extension-container">
            <div style="margin-bottom: 15px;">
                <span class="text-title-bold">허용된 확장자 목록</span>
            </div>
            <?php if (empty($analysis['extensions'])): ?>
            <p style="color: #666; padding: 15px; background: #f8f9fa; border-radius: 4px; margin: 10px 20px 20px 20px;">
                허용된 확장자가 없습니다.
            </p>
            <?php else: ?>
            <div class="extension-list">
                <?php foreach ($analysis['extensions'] as $ext): ?>
                <div class="extension-item <?php echo $ext['risk']; ?>" data-tooltip="<?php echo htmlspecialchars($ext['description']); ?>">
                    <?php
                    $icon = '';
                    if ($ext['risk'] == 'high') $icon = '❗';
                    elseif ($ext['risk'] == 'medium') $icon = '⚠️';
                    echo $icon;
                    ?>.<?php echo $ext['name']; ?>
                    <?php if ($is_admin == 'super'): ?>
                    <button class="extension-remove" onclick="removeExtension('<?php echo $ext['name']; ?>')" title="삭제">×</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 보안 위험성 분석 결과 -->
        <?php
        $analysis_class = 'safe';
        $analysis_icon = '✅';
        $analysis_message = '<div style="background: #e8f5e8; color: #2d5a2d; padding: 8px 12px; border-radius: 6px; font-size: 12px;">✅ 안전합니다! 현재 허용된 확장자 목록에는 알려진 위험한 확장자가 포함되어 있지 않습니다.</div>';

        if ($analysis['high'] > 0) {
            $analysis_class = 'danger';
            $analysis_icon = '🚨';
            $analysis_message = '위험! 매우 위험한 확장자가 ' . $analysis['high'] . '개 허용되어 있습니다. 즉시 제거하시기 바랍니다.';
        } elseif ($analysis['medium'] > 3) {
            $analysis_class = 'warning';
            $analysis_icon = '⚠️';
            $analysis_message = '주의! 주의가 필요한 확장자가 ' . $analysis['medium'] . '개 허용되어 있습니다. 검토 후 불필요한 확장자를 제거하세요.';
        } elseif ($analysis['medium'] > 0) {
            $analysis_class = 'warning';
            $analysis_icon = '⚡';
            $analysis_message = '보통 수준입니다. 일부 주의 확장자(' . $analysis['medium'] . '개)가 있으니 필요에 따라 검토하세요.';
        }
        ?>

        <div class="analysis-result <?php echo $analysis_class; ?>">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="flex: 1;">
                    <?php echo $analysis_message; ?>
                </div>
                <div style="margin-left: 20px; flex-shrink: 0;">
                    <span class="badge-primary" style="background: <?php
                                   echo $analysis_class == 'danger' ? '#dc2626' :
                                       ($analysis_class == 'warning' ? '#fd7e14' : '#059669');
                                   ?>;">
                        <?php
                        echo $analysis_class == 'danger' ? '위험' :
                            ($analysis_class == 'warning' ? '주의' : '안전');
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- 보안 가이드 -->
        <div class="recommendations">
            <h4>확장자 보안 가이드</h4>
            <ul>
                <li><strong>1.</strong> 실행 파일 확장자(.exe, .php, .asp 등)는 절대 허용하지 마세요</li>
                <li><strong>2.</strong> 스크립트 파일(.js, .vbs, .html 등)은 신중하게 검토하여 허용하세요</li>
                <li><strong>3.</strong> 이미지, 문서 파일 등 안전한 확장자만 허용하는 것을 권장합니다</li>
                <li><strong>4.</strong> 정기적으로 허용된 확장자 목록을 검토하고 불필요한 확장자를 제거하세요</li>
            </ul>
        </div>
    </div>
</div>

<script>
// 확장자 제거 함수
function removeExtension(extension) {
    if (!confirm('확장자 "' + extension + '"을(를) 제거하시겠습니까?')) {
        return;
    }
    
    // AJAX로 확장자 제거 요청
    fetch('security_extension.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=remove_extension&extension=' + encodeURIComponent(extension)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 성공시 페이지 새로고침
            location.reload();
        } else {
            alert('확장자 제거에 실패했습니다: ' + (data.message || '알 수 없는 오류'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('확장자 제거 중 오류가 발생했습니다.');
    });
}

// 카드 토글 함수 (UI 뒤집힘 방지)
function toggleCard(cardId) {
    const card = document.getElementById(cardId);
    if (!card) return;
    
    const content = card.querySelector('.card-content');
    if (!content) return;
    
    // 현재 상태 확인
    const isCollapsed = content.style.display === 'none' || !content.style.display;
    
    if (isCollapsed) {
        // 펼치기
        content.style.display = 'block';
        card.classList.add('expanded');
        card.classList.remove('collapsed');
    } else {
        // 접기
        content.style.display = 'none';
        card.classList.add('collapsed');
        card.classList.remove('expanded');
    }
}
</script>

<style>
/* UI 뒤집힘 방지 CSS */
.security-card {
    transform: none !important;
    transition: none !important;
}

.security-card.expanded {
    transform: none !important;
}

.security-card.collapsed {
    transform: none !important;
}

/* 확장자 제거 버튼 스타일 - 박스 색상과 조화 */
.extension-remove {
    background: rgba(255, 255, 255, 0.3);
    color: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 11px;
    line-height: 1;
    cursor: pointer;
    margin-left: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    backdrop-filter: blur(2px);
}

.extension-remove:hover {
    background: rgba(255, 255, 255, 0.5);
    color: white;
    border-color: rgba(255, 255, 255, 0.6);
    transform: scale(1.05);
}

/* 위험도별 더 세련된 제거 버튼 */
.extension-item.high .extension-remove {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.3);
}

.extension-item.high .extension-remove:hover {
    background: rgba(255, 255, 255, 0.4);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.extension-item.medium .extension-remove {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.4);
}

.extension-item.medium .extension-remove:hover {
    background: rgba(255, 255, 255, 0.5);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.extension-item.low .extension-remove {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.3);
}

.extension-item.low .extension-remove:hover {
    background: rgba(255, 255, 255, 0.4);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.extension-item.unknown .extension-remove {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.4);
}

.extension-item.unknown .extension-remove:hover {
    background: rgba(255, 255, 255, 0.5);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}
</style>