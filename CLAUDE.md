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

## Architecture (Current State)

### Hybrid Plugin Structure
- **extend/**: Single hook file only (`security_hook.extend.php`)
- **plugin/gnukeeper/**: All business logic and classes
- **adm/**: Admin interface files (unchanged location)

### File Structure
```
plugin/gnukeeper/
├── bootstrap.php          # Plugin initialization
├── config.php            # Path constants and table names
├── core/                 # Core classes
│   ├── GK_Common.php        # Common utilities
│   ├── GK_BlockManager.php  # IP blocking logic
│   └── GK_SpamDetector.php  # Spam detection engine
├── filters/              # Filter modules
│   ├── RegexFilter.php      # Regex spam filter
│   ├── UserAgentFilter.php  # User-Agent filter
│   ├── BehaviorFilter.php   # Behavior pattern detection
│   └── MultiUserFilter.php  # Multi-account detection
├── sql/                  # SQL scripts
│   └── install.sql          # Unified installation script
└── data/                 # Data files
    └── korea_ip_list.txt    # Korean IP ranges
```

## Key Features (Simplified Design)

### 1. IP Blocking
- **Simple Rule**: Block = Complete access denial (no level variations)
- Manual IP/CIDR block management
- Exception IP (whitelist) management
- Auto-block rules (login failures, spam, etc.)
- Bulk foreign IP blocking

### 2. Spam Management
- Login brute force blocking
- Regex pattern matching
- User-Agent filtering
- Multi-account detection
- Defaults: 5 max attempts, 5-minute window, 10-minute block

### 3. Admin Interface
- 950100: Security dashboard
- 950300: Block management
- 950500: Spam management

## Database Structure (Simplified)
- `g5_security_config`: Plugin configuration
- `g5_security_ip_block`: Blocked IP list (no sb_block_level field)
- `g5_security_ip_whitelist`: Exception IP list
- `g5_security_login_fail`: Login failure log
- `g5_security_regex_spam`: Spam detection rules

## Development Workflow & Testing

### Critical Development Process
1. **Code Development**: Implement features with proper error handling
2. **Individual Testing**: Test each function with various inputs
3. **Integration Testing**: Test plugin interaction with gnuboard5
4. **Security Testing**: Test IP blocking, spam detection with real scenarios
5. **Performance Testing**: Monitor extend file load impact
6. **User Experience Testing**: Verify admin interface usability

### Testing Commands
```bash
# Test IP blocking (use 127.0.0.1 for IPv4 testing)
curl -s http://127.0.0.1/ -w "Status: %{http_code}\n"

# Test User-Agent filtering
curl -H "User-Agent: curl/7.68.0" http://127.0.0.1/

# Test login failure blocking
for i in {1..6}; do curl -d "mb_id=test&mb_password=wrong" -X POST http://127.0.0.1/bbs/login_check.php; done

# Database cleanup after tests
mysql -u gnuuser -p'password' gnuboard -e "DELETE FROM g5_security_ip_block WHERE sb_ip = '127.0.0.1';"
```

### Testing Accounts
- Admin: id=admin, pw=adminpassword
- Test user: id=hacker, pw=hackerpassword
- Use separate cookie files for different test scenarios

## Security Considerations
- Validate and escape all input values
- Check admin permissions (`$member['mb_level'] >= 10`)
- Use only trustworthy IP sources
- Graceful degradation on DB connection failure
- Always test blocking functions before deployment

## Performance Guidelines
- extend files load on EVERY page - keep minimal
- Use static caching for database queries
- Single hook file pattern: `/extend/security_hook.extend.php` only
- Business logic in plugin classes, not extend files
- Test performance impact after any extend file changes

## UI/UX Guidelines (Customer-Focused)
- **Simplicity First**: General users should understand all options intuitively
- **No Complex Configurations**: Avoid technical jargon and multiple sub-options
- **Clear Feedback**: Show immediate results of actions
- **AJAX-Only Interactions**: No page refreshes for user actions

## Important Notes
- **Testing is Critical**: Every feature must be tested with real scenarios before deployment
- Function existence check prevents redeclaration errors
- Never trust HTTP headers for IP detection in security contexts
- Plugin structure optimizes performance while maintaining functionality
- Simplified blocking logic (no levels) improves user experience