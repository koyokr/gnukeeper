# 🛡️ GnuKeeper - Gnuboard5 보안 플러그인

**그누보드5를 위한 종합 보안 솔루션**

GnuKeeper는 그누보드5 사이트 운영자가 쉽게 사용할 수 있는 WordPress 스타일의 종합 보안 플러그인입니다. 복잡한 설정 없이도 사이트를 각종 보안 위협으로부터 보호할 수 있습니다.

---

## ✨ 주요 기능 (Features)

### 1. 🏠 종합 보안 대시보드 (HOME)
- **실시간 보안 점수 계산** (100점 만점 체계)
- **13개 보안 항목** 실시간 모니터링 및 상태 표시
- **차단 통계 현황** - 스팸/공격 차단 건수 실시간 집계
- **최근 보안 로그** 실시간 표시 및 위험 알림

### 2. 🔐 접근 제어 시스템 (접근제어)
- **페이지별 세밀한 접근 권한** 설정
- 회원가입, 게시판, 쪽지, 프로필 등 **모든 관련 페이지 일괄 반영**
- 웹사이트 목적에 맞춘 **맞춤형 접근 정책** 구성

### 3. 🚫 지능형 차단 관리 (차단관리)
- **IP/CIDR 기반** 정밀 차단 시스템 (IPv4/IPv6 지원)
- **해외 IP 자동 차단** - 국가별 세밀한 제어
- **화이트리스트** 예외 설정으로 안전한 접근 보장
- **자동 차단 해제** 기능으로 운영 편의성 제공

### 4. 📋 정책 관리 및 점검 (정책관리)
- **위험 게시판 탐지** - 보안 정책이 미흡한 게시판 자동 발견
- **캡챠 설정 현황** 모니터링 및 권장사항 제공
- **위험 확장자 관리** - php, asp, exe, sh 등 실행 파일 업로드 차단
- **업로드 용량 제한** - 20MB 초과 위험 판정 및 자동 조치
- **관리자 권한 모니터링** - 레벨 10 이상 사용자 관리

### 5. 🤖 AI 기반 스팸 탐지 시스템 (탐지관리)
- **정규식 패턴** 스팸 콘텐츠 자동 탐지 및 차단
- **로그인 보안** - 5회 실패 시 10분 자동 IP 차단
- **악성 봇 차단** - User-Agent 패턴 분석으로 봇 접근 방지
- **비정상 행위 탐지** - 404 에러 과다, 비정상 Referer 검증
- **다중 계정 탐지** - 동일 IP 다중 회원가입 모니터링

---

## 📦 설치 방법 (Installation)

### 1. 파일 업로드
```bash
# 다운로드한 파일을 그누보드5 루트 디렉토리에 압축 해제
/var/www/html/gnuboard/
├── plugin/gnukeeper/     # 플러그인 핵심 파일
├── extend/gnukeeper.extend.php    # 보안 훅
└── adm/security_*.php    # 관리자 인터페이스
```

### 2. 데이터베이스 초기화
```sql
# 필요한 보안 테이블이 자동으로 생성됩니다
- g5_security_config
- g5_security_ip_block
- g5_security_ip_whitelist
- g5_security_login_fail
- g5_security_detect_log
```

### 3. 활성화
1. 관리자 페이지 접속
2. **보안관리 > HOME** 메뉴 확인
3. 기본 보안 정책 설정 완료

---

## 🚀 사용 방법 (Usage)

### 관리자 메뉴 구조
- **950100 - HOME**: 보안 대시보드 및 실시간 모니터링
- **950200 - 접근제어**: 페이지별 접근 권한 설정
- **950300 - 차단관리**: IP 차단 및 해외 IP 관리
- **950400 - 정책관리**: 보안 정책 점검 및 설정
- **950500 - 탐지관리**: 스팸 탐지 로그 및 설정

### 추천 초기 설정
```php
// 기본 보안 설정 (안전 수준)
로그인 실패 차단: 활성화 (5회/10분)
해외 IP 차단: 선택적 활성화
스팸 콘텐츠 탐지: 활성화
위험 확장자 차단: php, asp, exe, sh 등
```

### 고급 설정 옵션
- **정규식 스팸 필터**: 맞춤형 스팸 패턴 등록
- **화이트리스트**: 신뢰할 수 있는 IP 예외 설정
- **탐지 로그**: 상세한 보안 이벤트 분석

---

## ⚙️ 시스템 요구사항 (Requirements)

### 필수 환경
- **PHP**: 7.4 이상
- **그누보드5**: 5.4 이상
- **MySQL/MariaDB**: 5.7 이상
- **웹서버**: Apache 2.4+ 또는 Nginx 1.16+

### 권장 환경
- **PHP**: 8.0 이상
- **메모리**: 128MB 이상
- **디스크 공간**: 10MB 이상

### 추가 확장 모듈
- `php-filter` (IP 유효성 검증)
- `php-json` (설정 데이터 처리)
- `php-curl` (외부 API 연동 시)

---

## 🎯 기대 효과 및 활용 분야 (Expected Effects & Use Cases)

### 보안 강화 효과
- **점수 기반 스팸 차단** - 각 키워드에 대한 점수 기반 스팸 탐지 
- **무단 접근 방지** - IP 기반 접근 제어
- **취약한 정책에 대한 관리 효율성 향상** - 위협으로 발생될 수 있는 정책에 대해 위험성을 판단 및 대응 기능 제공

### 주요 활용 분야
- **커뮤니티 사이트**: 스팸 게시글 및 댓글 차단
- **쇼핑몰**: 악성 봇 접근 차단 및 보안 강화
- **기업 사이트**: 해외 IP 차단 및 접근 제어
- **개인 블로그**: 간편한 보안 설정으로 안전한 운영

---

## 🤝 기여하기 (Contribution)

### 이슈 신고
- [GitHub Issues](https://github.com/gnsehfvlr/gnuboard5_security/issues)에서 버그 신고 및 기능 요청
- 상세한 환경 정보와 재현 과정 포함 요청

### 개발 참여
```bash
git clone https://github.com/gnsehfvlr/gnuboard5_security.git
cd gnuboard5_security
# 개발 후 Pull Request 제출
```

### 개발 가이드라인
- **코딩 규칙**: 그누보드5 표준 준수
- **보안 원칙**: `$_SERVER['REMOTE_ADDR']`만 사용
- **성능 최적화**: 캐싱 및 정적 변수 활용

---

## 📄 라이선스 (License)

이 프로젝트는 **MIT License** 하에 배포됩니다.

```
MIT License

Copyright (c) 2024 GnuKeeper Team

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 🔧 개발자 릴리스 가이드 (Release Guide)

1. `plugin/gnukeeper/config.php`에서 `GK_VERSION` 업데이트
2. 변경사항 커밋
3. 태그 생성: `git tag 1.0.1` 
4. 태그 푸시: `git push origin 1.0.1`

---

## 📞 지원 및 문의 (Support)

- **GitHub**: [https://github.com/gnsehfvlr/gnuboard5_security](https://github.com/gnsehfvlr/gnuboard5_security)
- **버그 신고**: Issues 탭에서 신고해주세요
- **기능 요청**: Discussions 탭에서 제안해주세요

---

