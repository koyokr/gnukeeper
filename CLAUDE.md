# GnuKeeper - Gnuboard5 Security Plugin

## Project Overview

Purpose: Develop a comprehensive security plugin for gnuboard5
Concept: Enable general users to easily protect their sites, similar to WordPress security plugins

## Development Rules

### Basic Rules
- NEVER modify existing files: Absolutely no modification of original gnuboard5 files
- When adding new files: Must add exception entries to `.gitignore` (`!filepath`)
- Table prefix: Use G5_TABLE_PREFIX for compatibility
- Constants reference: Refer to `/common.php`, `/config.php`, `/data/dbconfig.php` for gnuboard5 defined constants

### Naming Conventions
- Functions: `gk_` prefix (e.g., `gk_parse_cidr()`, `gk_set_config()`)
- Classes: `GK_` prefix (e.g., `GK_BlockManager`, `GK_SpamDetector`)
- Constants: `GK_` prefix (e.g., `GK_VERSION`, `GK_PLUGIN_PATH`)
- Tables: `g5_security_*` format
- Menu codes: Use 950000 series

### Coding Rules

#### IP Address Handling
- Principle: Use ONLY `$_SERVER['REMOTE_ADDR']`
- Forbidden: HTTP header-based IP extraction (`X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`, etc.)
- Reason: Client-manipulatable headers are not trustworthy for security

#### Performance and Design
- Optimize extend files: Must be lightweight as they load on every page
- Static caching: Cache DB query results with `static` variables
- Early return: Use immediate `return` when conditions are not met
- Function existence check: Use `!function_exists()` to prevent redeclaration

#### Data Validation
- IP validation: Use `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)`
- CIDR parsing: Handle directly with regex + `ip2long()` + bitwise operations
- SQL injection prevention: Use gnuboard5's `sql_escape_string()` function
- XSS prevention: Use `htmlspecialchars()`

## Architecture

### Plugin Structure
- **extend/**: `gnukeeper.extend.php` (single unified hook file)
- **plugin/gnukeeper/**: Core business logic and classes
- **adm/**: Admin interface files

### File Organization
```
├── adm/
│   ├── admin.menu950.php       # Menu registration
│   ├── security_home.php       # Dashboard (950100)
│   ├── security_extension.php  # Policy management (950400)
│   ├── access_control*.php     # Access control files
│   ├── security_card_*.php     # Policy cards
│   ├── security_block/         # Block management
│   └── security_detect/        # Detection management
└── plugin/gnukeeper/
    ├── bootstrap.php, config.php
    ├── core/      # GK_Common, GK_BlockManager, GK_SpamDetector
    ├── filters/   # Regex, UserAgent, Behavior, MultiUser filters
    ├── admin/     # Admin helper classes
    ├── sql/       # Database scripts
    └── data/      # Static data files
```

## Key Features

### 1. Access Control & Blocking
- IP/CIDR block management with whitelist exceptions
- Auto-block on login failures, spam detection
- Referer verification system
- Foreign IP bulk blocking

### 2. Detection & Prevention
- Login brute force protection (5 attempts, 10-min block)
- Regex pattern spam filtering
- User-Agent and behavior pattern detection
- Multi-account monitoring

### 3. Admin Interface
- 950100: Security dashboard (`security_home.php`)
- 950400: Policy management (`security_extension.php`)
- security_block/: Block management interface
- security_detect/: Detection logs and settings

## Database Tables
- `g5_security_config`: Plugin settings
- `g5_security_ip_block`: Blocked IPs
- `g5_security_ip_whitelist`: Exception IPs
- `g5_security_login_fail`: Login attempts
- `g5_security_regex_spam`: Spam patterns
- `g5_security_detect_log`: Detection logs

## Testing & Development

### Test Commands
```bash
# IP blocking test
curl -s http://127.0.0.1/ -w "Status: %{http_code}\n"

# Login failure test (triggers auto-block after 5 attempts)
for i in {1..6}; do curl -d "mb_id=test&mb_password=wrong" -X POST http://127.0.0.1/bbs/login_check.php; done

# Database cleanup
mysql -u gnuuser -p'password' gnuboard -e "DELETE FROM g5_security_ip_block WHERE sb_ip = '127.0.0.1';"
```

### Test Accounts
- Admin: admin/adminpassword
- Test: hacker/hackerpassword

## Critical Guidelines

### Security
- Never trust HTTP headers for IP (`$_SERVER['REMOTE_ADDR']` only)
- Validate all inputs with `sql_escape_string()`, `htmlspecialchars()`
- Admin permission check: `$member['mb_level'] >= 10`
- Graceful DB failure handling

### Performance
- Extend files: Minimal code (loads on EVERY page)
- Static caching for DB queries
- Business logic in plugin classes only
- Single hook file: `/extend/gnukeeper.extend.php`

### UI/UX
- Simple, intuitive interface for general users
- AJAX-only interactions (no page refresh)
- Clear, immediate feedback

### Important Notes
- Test all features with real scenarios
- Use `!function_exists()` to prevent redeclaration
- Keep blocking logic simple (no complexity levels)