# GnuKeeper Plugin

GnuBoard5 보안 플러그인의 핵심 로직과 데이터를 관리하는 디렉토리입니다.

## 디렉토리 구조

```
plugin/gnukeeper/
├── bootstrap.php           # 플러그인 초기화
├── config.php             # 경로 상수 및 테이블명 정의
├── core/                  # 핵심 클래스
│   ├── GK_Common.php         # 공통 유틸리티
│   ├── GK_BlockManager.php   # IP 차단 관리
│   └── GK_SpamDetector.php   # 스팸 탐지 엔진
├── filters/               # 필터 모듈
│   ├── RegexFilter.php       # 정규식 스팸 필터
│   ├── UserAgentFilter.php   # User-Agent 필터
│   ├── BehaviorFilter.php    # 이상 행위 탐지
│   └── MultiUserFilter.php   # 다중 계정 탐지
├── sql/                   # SQL 스크립트
│   └── install.sql           # 통합 설치 스크립트
└── data/                  # 데이터 파일
    └── korea_ip_list.txt     # 한국 IP 대역 목록
```

## 주요 기능

- **IP 차단**: 수동/자동 IP 차단 (접속 완전 차단)
- **스팸 탐지**: 로그인 실패, 정규식 패턴, User-Agent 필터링
- **해외 IP 차단**: 한국 IP 목록 기반 해외 접속 차단
- **성능 최적화**: extend 파일 부담을 최소화한 하이브리드 구조

## 설치 및 사용

### 테이블 설치
```bash
# {PREFIX}를 g5_로 치환하여 실행
sed 's/{PREFIX}/g5_/g' sql/install.sql | mysql -u [user] -p [database]
```

### 플러그인 로드
extend 파일에서 자동으로 로드됩니다:
```php
// extend/security_hook.extend.php에서
require_once GK_PLUGIN_PATH . '/bootstrap.php';
```

## 아키텍처

- **extend/**: 단일 훅 파일만 (`security_hook.extend.php`)
- **plugin/gnukeeper/**: 모든 비즈니스 로직
- **adm/**: 관리자 인터페이스

이 구조로 extend의 성능 부담을 최소화하면서도 모든 보안 기능을 제공합니다.