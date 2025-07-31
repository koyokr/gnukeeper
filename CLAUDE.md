# GnuKeeper - Gnuboard5 Security Plugin

## Project Overview

Purpose: Develop a comprehensive security plugin for gnuboard5
Concept: Enable general users to easily protect their sites, similar to WordPress security plugins

Development Approach:
- Admin interface: Add directly to `/adm/` directory
- Security logic: Add to `extend/` directory (auto-loaded)

## Development Rules

### Basic Rules
- NEVER modify existing files: Absolutely no modification of original gnuboard5 files
- When adding new files: Must add exception entries to `.gitignore` (`!filepath`)
- Table prefix: Use G5_TABLE_PREFIX for compatibility
- Constants reference: Refer to `/common.php`, `/config.php`, `/data/dbconfig.php` for gnuboard5 defined constants

### Naming Conventions
- Functions: `gk_` prefix (e.g., `gk_parse_cidr()`, `gk_set_config()`)
- Classes: `GK_` prefix (e.g., `GK_SecurityManager`, `GK_IPBlocker`)
- Constants: `GK_` prefix (e.g., `GK_VERSION`, `GK_PLUGIN_PATH`)
- Tables: `g5_security_*` format
- Menu codes: Use 950000 series

### Coding Rules

#### IP Address Handling
- Principle: Use ONLY `$_SERVER['REMOTE_ADDR']`
- Forbidden: HTTP header-based IP extraction (`X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`, etc.)
- Reason: Client-manipulatable headers are not trustworthy for security

#### Function Design and Performance
- No excessive abstraction: Don't create wrappers for things PHP built-in functions can handle
- Optimize extend files: Must be lightweight as they load on every page
- Static caching: Cache DB query results with `static` variables
- Early return: Use immediate `return` when conditions are not met to prevent unnecessary processing

#### Data Validation
- IP validation: Use `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)`
- CIDR parsing: Handle directly with regex + `ip2long()` + bitwise operations
- SQL injection prevention: Use gnuboard5's `sql_escape_string()` function
- XSS prevention: Use `htmlspecialchars()`

## Key Features

### 1. Block Management
- Manual IP/CIDR block management
- Exception IP (whitelist) management
- Bulk foreign IP blocking
- Auto-block rules (login failures, spam, etc.)
- Auto admin IP protection, CIDR notation support

### 2. Spam Management
- Login brute force blocking
- Consecutive registration attempt limiting
- Keyword filtering and regex pattern matching
- Ghost mode (show spam posts only to author)
- Defaults: 5 max attempts, 5-minute window, 10-minute block

### 3. Access Control
- Page-level access permission control (search, latest posts, member registration, etc.)
- Admin page IP restrictions

### 4. Permission Management
- Enhanced board permission security
- Bulk permission settings and template management

## File Structure

### Admin Interface (/adm/)
- `admin.menu950.php`: Security settings menu definition
- `security_home.php`: Security dashboard (950100)
- `security_block/`: IP block management (950300) - modularized structure
- `security_spam.php`: Spam management (950500)
- `security_spam.sql`: Integrated SQL schema

### Security Logic (/extend/)
- `security_common.extend.php`: Common functions
- `security_block_ip.extend.php`: IP block checking
- `security_detect_spam.extend.php`: Spam detection and blocking
- `security_block_ip_foreign.extend.php`: Foreign IP blocking

### Menu Structure
- 950100: Security dashboard
- 950300: Block management (IP blocking system)
- 950500: Spam management (auto-block settings)

## Gnuboard5 Architecture

### lib/ vs extend/ Differences
- lib/: Function libraries manually loaded when needed (`include_once()`)
- extend/: Extension system auto-loaded by `common.php` (all pages)

### Dependency Relationships
```
common.php (central)
├── extend/*.extend.php auto-loaded
├── lib/*.lib.php loaded when needed
├── config.php included
└── dbconfig.php included
```

### Bootstrap Process
1. Execute common.php
2. Load config.php (basic settings)
3. Load dbconfig.php (DB connection)
4. Auto-load all extend/*.extend.php
5. Manually load lib/*.lib.php when needed
6. Execute page-specific logic

### Core Directories
- `/adm/`: Admin interface
- `/bbs/`: Board system (actively utilize hook system)
- `/extend/`: Auto extension system (performance impact, independent)
- `/lib/`: Function library (manual loading)
- `/data/`: Configuration and cache files

### extend/ File Writing Principles
```php
// extend/security_*.extend.php pattern
if (!defined('_GNUBOARD_')) exit;

// Static caching
static $security_config = null;
if ($security_config === null) {
    $security_config = gk_get_config();
}

// Early return
if (!$security_config['enable']) return;

// Minimal security check
gk_check_ip_block();
```

## Database Structure
- `g5_security_config`: Plugin configuration storage
- `g5_security_ip_block`: Blocked IP list
- `g5_security_ip_whitelist`: Exception IP list
- `g5_security_login_fail`: Login failure log
- `g5_security_spam_log`: Spam detection log

## Security Considerations
- Validate and escape all input values
- Check admin permissions (`$is_admin == 'super'`)
- Use only trustworthy IP sources
- Avoid heavy operations in extend files
- Graceful degradation on DB connection failure

## Development Status
Currently at Phase 2-3: IP blocking, spam prevention, and permission management systems are complete and refactored into a modularized structure. security_block has been improved to an include-based structure referencing the shop_admin pattern.

## Commands and Scripts

### Database Setup
```bash
# Initialize security tables
mysql -u [user] -p [database] < security_spam.sql
```

### Testing Commands
```bash
# Test IP blocking
curl -H "X-Real-IP: 192.168.1.100" http://yoursite.com/
# Test login failure detection
# Attempt login with wrong credentials 5+ times
```

### File Permissions
```bash
# Set proper permissions for extend files
chmod 644 extend/security_*.extend.php
chmod 755 adm/security_*
```

## Workflow Guidelines
- Always test extend files on development environment first
- Use git branching for feature development
- Run security tests before committing
- Update CLAUDE.md when adding new commands or patterns
- Check performance impact of extend modifications

## Important Notes
- extend files are loaded on EVERY page request - keep them lightweight
- Never trust HTTP headers for IP detection in security contexts
- Use gnuboard5's existing functions whenever possible for compatibility
- Static caching is crucial for performance in frequently called functions
- Always provide fallbacks for security functions to prevent site breakage

## UI/UX Guidelines
- **ABSOLUTELY NO PAGE REFRESHES**: All user interactions must be handled via AJAX
- **Dynamic Content Updates**: Use JavaScript to update page elements without reloading
- **AJAX-Only Policy**: Never use form submissions that cause page refreshes
- **Real-time Updates**: Content should update immediately after AJAX operations
- **User Feedback**: Provide instant visual feedback for all user actions
- **Smooth Interactions**: All UI changes should be seamless and without interruption
