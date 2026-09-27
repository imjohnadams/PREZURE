<?php
// Central configuration: env secrets, base URL, and data file paths.
// Require this file before using APP_* or PREZURE_* constants.

if (defined('PREZURE_CONFIG_LOADED')) return;
define('PREZURE_CONFIG_LOADED', true);

// ── 0. Optional .env loader (for local dev only) ─────────────────────────────
// In production set real environment variables. The loader silently no-ops
// if the file doesn't exist.
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"' \t");
        if ($k !== '' && getenv($k) === false) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
        }
    }
}

// Internal path prefix. /prezure2/3/signin.php → /prezure2.
// A script at the host root (/index.php) has no prefix.
if (!defined('APP_BASE_URL')) {
    $envBase = getenv('APP_BASE_URL');
    if ($envBase !== false && $envBase !== '') {
        define('APP_BASE_URL', rtrim($envBase, '/'));
    } else {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $parts = explode('/', trim($scriptName, '/'));
        $first = $parts[0] ?? '';
        if ($first === '' || str_contains($first, '.')) {
            define('APP_BASE_URL', '');
        } else {
            define('APP_BASE_URL', '/' . $first);
        }
    }
}

// ── 2. External (canonical) URL — used in emails ─────────────────────────────
// Must be HTTPS in production. Falls back to runtime detection.
if (!defined('APP_EXT_URL')) {
    $envExt = getenv('APP_EXT_URL');
    if ($envExt !== false && $envExt !== '') {
        define('APP_EXT_URL', rtrim($envExt, '/'));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('APP_EXT_URL', $scheme . '://' . $host . APP_BASE_URL);
    }
}

// ── 3. Data directory ────────────────────────────────────────────────────────
// In dev keep the existing locations so nothing breaks. In production set
// PREZURE_DATA_DIR to a path OUTSIDE the webroot.
if (!defined('PREZURE_DATA_DIR')) {
    $envData = getenv('PREZURE_DATA_DIR');
    define('PREZURE_DATA_DIR', $envData !== false && $envData !== ''
        ? rtrim($envData, "/\\")
        : __DIR__ . '/..'); // project root → existing folder layout
}

// Resolve concrete file paths. These accept a custom data dir but default to
// the existing webroot-adjacent layout for backwards compatibility.
if (!defined('PREZURE_USERS_FILE')) {
    define('PREZURE_USERS_FILE', PREZURE_DATA_DIR . '/3/users.json');
}
if (!defined('PREZURE_BOOKINGS_FILE')) {
    define('PREZURE_BOOKINGS_FILE', PREZURE_DATA_DIR . '/1/bookings.json');
}
if (!defined('PREZURE_ESTIMATES_DIR')) {
    define('PREZURE_ESTIMATES_DIR', PREZURE_DATA_DIR . '/estimatesJSON');
}
if (!defined('PREZURE_PREVIEWS_DIR')) {
    define('PREZURE_PREVIEWS_DIR', PREZURE_DATA_DIR . '/1/previews');
}
if (!defined('PREZURE_AUDIT_LOG')) {
    define('PREZURE_AUDIT_LOG', PREZURE_DATA_DIR . '/_audit.log');
}
if (!defined('PREZURE_RATELIMIT_DIR')) {
    define('PREZURE_RATELIMIT_DIR', PREZURE_DATA_DIR . '/_ratelimit');
}
if (!defined('PREZURE_GEOCODE_CACHE')) {
    define('PREZURE_GEOCODE_CACHE', PREZURE_DATA_DIR . '/_geocode_cache.json');
}

// ── 4. Google Maps API key ───────────────────────────────────────────────────
// Loaded ONLY from the environment. No source-code fallback — if missing
// the calculator and dashboard fail loudly so the deployer notices.
if (!defined('MAPS_API_KEY')) {
    $key = getenv('MAPS_API_KEY') ?: ($_ENV['MAPS_API_KEY'] ?? '');
    define('MAPS_API_KEY', is_string($key) ? trim($key) : '');
}

// Server-side Geocoding and Static Maps. Falls back to the browser key.
if (!defined('MAPS_SERVER_KEY')) {
    $serverKey = getenv('MAPS_SERVER_KEY') ?: ($_ENV['MAPS_SERVER_KEY'] ?? '');
    $serverKey = is_string($serverKey) ? trim($serverKey) : '';
    define('MAPS_SERVER_KEY', $serverKey !== '' ? $serverKey : MAPS_API_KEY);
}

// ── 5. Email transport ───────────────────────────────────────────────────────
// PHPMailer/SMTP recommended for production; mail() retained as fallback
// for shared hosts that don't have composer.
if (!defined('MAIL_FROM_ADDR'))   define('MAIL_FROM_ADDR',   getenv('MAIL_FROM_ADDR')   ?: 'noreply@example.com');
if (!defined('MAIL_FROM_NAME'))   define('MAIL_FROM_NAME',   getenv('MAIL_FROM_NAME')   ?: 'Prezure Estimates');
if (!defined('MAIL_REPLY_ADDR'))  define('MAIL_REPLY_ADDR',  getenv('MAIL_REPLY_ADDR')  ?: MAIL_FROM_ADDR);
if (!defined('MAIL_TRANSPORT'))   define('MAIL_TRANSPORT',   getenv('MAIL_TRANSPORT')   ?: 'mail'); // 'mail' | 'smtp'
if (!defined('SMTP_HOST'))        define('SMTP_HOST',        getenv('SMTP_HOST')        ?: '');
if (!defined('SMTP_PORT'))        define('SMTP_PORT',  (int)(getenv('SMTP_PORT')        ?: 587));
if (!defined('SMTP_USER'))        define('SMTP_USER',        getenv('SMTP_USER')        ?: '');
if (!defined('SMTP_PASS'))        define('SMTP_PASS',        getenv('SMTP_PASS')        ?: '');
if (!defined('SMTP_SECURE'))      define('SMTP_SECURE',      getenv('SMTP_SECURE')      ?: 'tls');

// ── 6. CORS allow-list ───────────────────────────────────────────────────────
// Comma-separated list of allowed origins; same-origin (no Origin header) is
// always allowed. Defaults to APP_EXT_URL.
if (!defined('CORS_ALLOWED_ORIGINS')) {
    $list = getenv('CORS_ALLOWED_ORIGINS') ?: APP_EXT_URL;
    define('CORS_ALLOWED_ORIGINS', $list);
}

// ── 7. Helpers ───────────────────────────────────────────────────────────────

/**
 * Resolve an internal application URL (e.g. signin page).
 * Always emits a leading slash + APP_BASE_URL.
 */
function app_url(string $path = ''): string {
    return APP_BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Resolve an absolute external URL (used in emails).
 */
function app_ext_url(string $path = ''): string {
    return rtrim(APP_EXT_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Apply CORS headers based on the configured allow-list. Call BEFORE any
 * other response output. Returns true if origin is allowed (or none sent).
 */
function apply_cors(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return true; // same-origin or non-browser request
    $allowed = array_filter(array_map('trim', explode(',', CORS_ALLOWED_ORIGINS)));
    foreach ($allowed as $a) {
        if (strcasecmp($a, $origin) === 0) {
            header('Access-Control-Allow-Origin: ' . $a);
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
            return true;
        }
    }
    // Origin sent but not allowed — do not echo any allow header.
    return false;
}

/**
 * Atomically read a JSON file with a shared lock. Returns null on failure.
 */
function read_json_locked(string $path): ?array {
    if (!file_exists($path)) return null;
    $fp = @fopen($path, 'r');
    if (!$fp) return null;
    @flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Atomically read-modify-write a JSON file with an exclusive lock.
 * The callback receives the decoded data and must return the new array
 * (or null to abort the write).
 *
 * Returns true on success, false on failure.
 */
function with_locked_json(string $path, callable $modifier, array $defaultIfMissing = []): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fp = @fopen($path, 'c+');
    if (!$fp) return false;
    if (!@flock($fp, LOCK_EX)) { fclose($fp); return false; }

    $raw  = stream_get_contents($fp);
    $data = $raw === '' ? $defaultIfMissing : (json_decode($raw, true) ?: $defaultIfMissing);

    try {
        $new = $modifier($data);
    } catch (Throwable $e) {
        @flock($fp, LOCK_UN);
        fclose($fp);
        throw $e;
    }

    if ($new === null) {
        @flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    fseek($fp, 0);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}
