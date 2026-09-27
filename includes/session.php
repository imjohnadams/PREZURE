<?php
// Session cookie settings and CSRF helpers.
// require_login() returns the signed-in user id.

require_once __DIR__ . '/config.php';

if (!function_exists('prezure_session_start')) {
    function prezure_session_start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        // Use HTTPS-only cookies whenever the request is HTTPS. In dev (HTTP)
        // we fall back to non-secure so local testing still works.
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => APP_BASE_URL === '' ? '/' : APP_BASE_URL,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();

        // Idle timeout: 4 hours since last activity
        $now = time();
        $idleLimit = 4 * 60 * 60;
        if (isset($_SESSION['_last_seen']) && ($now - (int)$_SESSION['_last_seen']) > $idleLimit) {
            $_SESSION = [];
            session_destroy();
            session_start();
        }
        $_SESSION['_last_seen'] = $now;

        // First-touch fingerprint to deter cross-session token replay.
        $fp = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!isset($_SESSION['_fp'])) {
            $_SESSION['_fp'] = $fp;
        } elseif (!hash_equals($_SESSION['_fp'], $fp)) {
            // Session being used from a different UA/IP — start fresh.
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_fp']        = $fp;
            $_SESSION['_last_seen'] = $now;
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) prezure_session_start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(string $tok): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) prezure_session_start();
        $expected = $_SESSION['_csrf'] ?? '';
        return $expected !== '' && hash_equals($expected, $tok);
    }
}

if (!function_exists('csrf_require_header')) {
    /**
     * Read X-CSRF-Token from the request and verify. Exits with 403 JSON on
     * failure. Use at the top of every state-changing JSON endpoint.
     */
    function csrf_require_header(): void {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $tok = '';
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'X-CSRF-Token') === 0) { $tok = $v; break; }
        }
        if ($tok === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $tok = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        if (!csrf_verify($tok)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token. Refresh the page and try again.']);
            exit;
        }
    }
}

if (!function_exists('require_login')) {
    /**
     * Ensure a signed-in user. Returns the integer user_id from the session.
     * For HTML pages, redirects to signin. For JSON endpoints, returns 401.
     */
    function require_login(string $mode = 'html'): int {
        if (session_status() !== PHP_SESSION_ACTIVE) prezure_session_start();
        if (empty($_SESSION['user_id'])) {
            if ($mode === 'json') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . app_url('3/signin.php'));
            exit;
        }
        return (int) $_SESSION['user_id'];
    }
}

if (!function_exists('require_json_post')) {
    /**
     * Reject anything that isn't POST application/json, returning the parsed
     * JSON body. Stops the simplest cross-origin form CSRF vectors before
     * the CSRF token is even consulted.
     */
    function require_json_post(): array {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') === false) {
            http_response_code(415);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unsupported content type']);
            exit;
        }
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
            exit;
        }
        return $data;
    }
}

if (!function_exists('login_user')) {
    /**
     * Centralized login: regenerate the session id, set user_id, drop any
     * leftover signup state. Always call this immediately after a credential
     * match — prevents session fixation.
     */
    function login_user(int $userId): void {
        if (session_status() !== PHP_SESSION_ACTIVE) prezure_session_start();
        $oldFp = $_SESSION['_fp'] ?? null;
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['user_id']    = $userId;
        $_SESSION['_csrf']      = bin2hex(random_bytes(32));
        $_SESSION['_last_seen'] = time();
        if ($oldFp) $_SESSION['_fp'] = $oldFp;
    }
}

if (!function_exists('logout_user')) {
    function logout_user(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) prezure_session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}
