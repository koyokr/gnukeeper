# GnuKeeper - Gnuboard5 보안 플러그인

## 프로젝트 개요

**목적**: gnuboard5를 위한 종합적인 보안 플러그인 개발
**컨셉**: WordPress의 보안 플러그인처럼 일반 사용자가 쉽게 사이트를 보호할 수 있도록 함

**개발 방식**:
- 관리자 인터페이스: `/adm/` 디렉토리에 직접 추가
- 보안 로직: `extend/` 디렉토리에 추가 (자동 로드)
- DB 연결 정보: `data/dbconfig.php` 참고

## 개발 규칙

### 기본 규칙
- **기존 파일 수정 금지**: gnuboard5 원본 파일 절대 수정하지 않음
- **새 파일 추가 시**: 반드시 `.gitignore`에 예외 항목 추가 (`!파일경로`)
- **의존성 규칙**: extend/ 파일은 adm/ 파일에 의존하지 않음
- **테이블 접두사**: G5_TABLE_PREFIX 사용하여 호환성 확보

### 네이밍 규칙
- **함수**: `gk_` 접두사 (예: `gk_parse_cidr()`, `gk_set_config()`)
- **클래스**: `GK_` 접두사 (예: `GK_SecurityManager`, `GK_IPBlocker`)
- **상수**: `GK_` 접두사 (예: `GK_VERSION`, `GK_PLUGIN_PATH`)
- **테이블**: `g5_security_*` 형식
- **메뉴 코드**: 950000번대 사용

### 코딩 규칙

#### IP 주소 처리
- **원칙**: `$_SERVER['REMOTE_ADDR']` 만 사용
- **금지**: HTTP 헤더 기반 IP 추출 (`X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP` 등)
- **이유**: 클라이언트가 조작 가능한 헤더는 보안상 신뢰할 수 없음
- **예외**: 없음 (웹서버/인프라 레벨에서 처리)

#### 함수 설계
- **과도한 추상화 금지**: PHP 내장 함수로 처리 가능한 것은 wrapper 만들지 않음
- **공통 함수 최소화**: 정말 필요한 것만 `extend/security_common.extend.php`에 작성
- **중복 코드 방지**: 같은 로직이 3개 이상 파일에서 반복되면 공통화
- **불필요한 wrapper 제거**: `is_valid_ip()` 대신 `filter_var()` 직접 사용

#### 데이터 검증
- **IP 검증**: `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)` 사용
- **CIDR 파싱**: 정규식 + `ip2long()` + 비트 연산으로 직접 처리
- **SQL 인젝션 방지**: gnuboard5의 `sql_escape_string()` 함수 사용
- **XSS 방지**: `htmlspecialchars()` 사용

#### 성능 고려사항
- **extend 파일 최적화**: 모든 페이지에서 로드되므로 가벼워야 함
- **정적 캐싱**: `static` 변수로 DB 조회 결과 캐싱
- **조기 반환**: 조건 불만족 시 즉시 `return`으로 불필요한 처리 방지
- **DB 쿼리 최소화**: 한 번 조회한 설정값은 메모리에 캐싱

## 주요 기능 설계

### 1. 접근 제어 (Access Control)
**목적**: 페이지별 접근 권한 제어

**페이지 접근 권한 설정**:
- 검색, 최신글, FAQ, 회원가입, 게시판 등 주요 페이지별 권한 제어
- 권한 레벨: 관리자/회원/방문자/On-Off
- 대상: `/bbs/search.php`, `/bbs/new.php`, `/bbs/register.php`, `/bbs/board.php` 등

**관리자 페이지 IP 제한**:
- 허용 IP 목록 관리
- CIDR 표기법 지원
- 여러 IP 대역 설정 가능

### 2. 차단 관리 (Block Management)
**목적**: IP 기반 접근 차단 시스템

**기능**:
- IP/CIDR 수동 차단 관리
- 예외 IP(화이트리스트) 관리  
- 해외 IP 일괄 차단
- 자동 차단 규칙 (로그인 실패, 스팸 등)

**특징**:
- 로그인된 관리자 계정 자동 예외
- CIDR 표기법 지원 (예: 192.168.1.0/24)
- 임시/영구 차단 지원
- 차단 사유 및 로그 기록

### 3. 권한 관리 (Permission Management)
**목적**: 게시판 권한 보안 강화

**게시판 권한 보안 강화**:
- 비회원 읽기 권한(권한1) 문제 식별 및 수정
- 목록보기, 글읽기, 글쓰기, 댓글쓰기, 업로드 등 권한별 제어

**일괄 권한 설정**:
- 전체 게시판 권한 템플릿 적용
- 권한 변경 이력 관리
- 권한 템플릿 저장/불러오기

### 4. 스팸 관리 (Spam Management)
**목적**: 자동화된 공격 차단

**다회 시도 차단**:
- 로그인 브루트포스 차단
- 연속 등록 시도 제한
- 404 페이지 접속 모니터링
- 계정별/IP별 차단 설정

**스팸 방지**:
- 키워드 필터링
- 정규식 기반 패턴 매칭
- 고스트 모드 (스팸 글을 작성자에게만 표시)

**사후 관리**:
- 스팸/의심 글 목록
- 일괄 삭제 기능
- 오탐 복구 기능

**설정**:
- 최대 시도 횟수 (기본: 5회)
- 시간 윈도우 (기본: 5분)
- 차단 기간 (기본: 10분)

### 5. 시스템 진단 (System Diagnostics)
**목적**: 보안 취약점 자동 탐지

**보안 파일 검사**:
- 삭제되지 않은 install.php 확인
- .git 디렉토리 노출 검사
- 임시 파일/백업 파일 탐지

**웹서버 설정 진단**:
- .htaccess 보안 설정 확인
- 디렉토리 접근 제한 검증
- 파일 업로드 제한 점검

**버전 관리**:
- gnuboard5 버전 확인
- 업데이트 알림
- 핵심 파일 변경 사항 탐지

### 6. 모니터링 및 분석 (Monitoring & Analytics)
**목적**: 보안 현황 종합 관리

**스팸 통계**:
- 차단된 스팸 수 통계
- 지역별/시간별 분석
- 트렌드 분석

**보안 로그**:
- 보안 이벤트 로그 기록
- 접근 시도 기록
- 이상 행동 패턴 분석

## 데이터베이스 구조

### 설정 테이블
- `g5_security_config`: 플러그인 설정 저장

### 차단 관리 테이블
- `g5_security_ip_block`: 차단 IP 목록
- `g5_security_ip_whitelist`: 예외 IP 목록
- `g5_security_ip_log`: 차단 접근 로그

### 스팸 관리 테이블
- `g5_security_login_fail`: 로그인 실패 로그
- `g5_security_spam_log`: 스팸 탐지 로그

### 통계 테이블
- `g5_security_stats`: 일별 보안 통계
- `g5_security_events`: 보안 이벤트 로그

## 파일 구조

### 관리자 인터페이스 (/adm/)
- `admin.menu950.php`: 보안설정 메뉴 정의
- `security_home.php`: 보안 대시보드 (950100)
- `security_access.php`: 접근제어 관리 (950200)
- `security_block.php`: IP 차단 관리 (950300)
- `security_permission.php`: 권한 관리 (950400)
- `security_spam.php`: 스팸 관리 (950500)
- `security_*.sql`: 각 기능별 테이블 생성 SQL

### 보안 로직 (/extend/)
- `security_common.extend.php`: 공통 함수
- `security_block_ip.extend.php`: IP 차단 검사
- `security_detect_spam.extend.php`: 스팸 탐지 및 차단
- `security_access_control.extend.php`: 페이지 접근 제어
- `security_block_ip_foreign.extend.php`: 해외 IP 차단

### 리소스 파일 (/adm/)
- `security_block_ip_kr.txt`: 국내 IP 대역 목록

## 메뉴 구조
- 950100: 보안 대시보드 (HOME)
- 950200: 접근제어 (페이지 권한 관리)
- 950300: 차단관리 (IP 차단 시스템)
- 950400: 권한관리 (게시판 권한 설정)
- 950500: 스팸관리 (자동 차단 설정)

## 개발 단계
1. **Phase 1**: 관리자 인터페이스 기본 구조 및 메뉴 시스템
2. **Phase 2**: 기본 IP 차단 및 접근제어 기능
3. **Phase 3**: 스팸 방지 및 권한 관리 시스템
4. **Phase 4**: 시스템 진단 및 보안 검사 기능
5. **Phase 5**: 모니터링 및 통계 분석 기능
6. **Phase 6**: 테스트 및 최적화

## 보안 고려사항

### 일반 보안
- 모든 입력값 검증 및 이스케이프 처리
- 관리자 권한 체크 (`$is_admin == 'super'`)
- CSRF 방지를 위한 토큰 검증

### IP 보안
- 신뢰할 수 있는 IP 소스만 사용
- 관리자 IP 자동 예외 처리
- IP 차단 시 HTTP 403 상태 코드 반환

### 성능 보안
- extend 파일에서 무거운 작업 지양
- DB 연결 실패 시 graceful degradation
- 차단 페이지는 최소한의 리소스만 사용

## 테스트 가이드라인

### 기능 테스트
- 각 차단 기능별 정상 작동 확인
- 관리자 IP 보호 기능 검증
- 차단 해제 및 만료 기능 테스트

### 성능 테스트  
- 대량 IP 목록에서의 검색 성능
- extend 파일 로딩 시간 측정
- 메모리 사용량 모니터링

### 보안 테스트
- SQL 인젝션 시도 차단 확인
- XSS 방지 기능 검증
- 우회 시도 차단 테스트

## 기술적 고려사항
- gnuboard5의 기존 플러그인 시스템 활용
- `extend/`와 `plugin/` 디렉토리 구조 준수
- 기존 코드 수정 없이 기능 확장
- 다양한 버전 호환성 확보
- 다른 플러그인과의 충돌 방지