# SMT-Escape 서버 배포 가이드

## 배포 스크립트

`.claude`, `.git`, `_e2e`, `waitfordel` 등 불필요한 파일을 제외하고 서버에 배포하는 스크립트입니다.

## 사용 방법

### Windows 사용자

```cmd
deploy.bat
```

### Git Bash / Linux / Mac 사용자

```bash
bash deploy.sh
```

## 스크립트 동작 순서

1. **서버 백업 생성**
   - `/var/www/html_backup_YYYYMMDD_HHMMSS.tar.gz` 생성
   - 백업 파일은 `/var/www/` 디렉토리에 저장

2. **업로드 권한 설정**
   - 임시로 ubuntu 사용자에게 권한 부여

3. **파일 동기화 (rsync)**
   - 제외 목록:
     - `.claude/` (Claude Code 설정)
     - `.git/`, `.gitignore` (Git 파일)
     - `node_modules/` (Node 모듈)
     - `.vscode/`, `.env.local` (개발 설정)
     - `*.log` (로그 파일)
     - `_e2e/`, `_reports/` (테스트 파일)
     - `waitfordel/` (삭제 대기 파일)
     - `deploy.sh`, `deploy.bat` (배포 스크립트)

4. **웹 서버 권한 복원**
   - 소유자: `www-data:www-data`
   - 디렉토리: 755
   - 파일: 644
   - uploads/backend: 775

## 서버 정보

- **도메인**: www.smt-escape.com
- **IP**: 52.77.238.135
- **사용자**: ubuntu
- **경로**: /var/www/html

## 주의사항

- rsync를 사용하여 `--delete` 옵션이 적용됩니다
- 서버에만 있고 로컬에 없는 파일은 삭제됩니다
- 배포 전 항상 백업이 자동으로 생성됩니다

## 백업 복원 방법

문제 발생 시 백업에서 복원:

```bash
ssh ubuntu@52.77.238.135
cd /var/www
sudo rm -rf html
sudo tar -xzf html_backup_YYYYMMDD_HHMMSS.tar.gz
sudo chown -R www-data:www-data html
```

## 제외 파일 추가

`deploy.sh` 또는 `deploy.bat`에서 `--exclude` 옵션을 추가하세요:

```bash
--exclude='새로운_제외_디렉토리' \
```
