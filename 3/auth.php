<?php
// Sign-in endpoint. POST application/json.
// Identifier may be an email, a 10-digit phone number, or a username.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/ratelimit.php';
require_once __DIR__ . '/../includes/audit.php';

prezure_session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['success' => false, 'error' => 'Unsupported content type']);
    exit;
}
csrf_require_header();

$ip = client_ip();
rate_limit_or_429('login:ip:' . $ip, 10, 900); // 10 / 15 min / IP

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['identifier']) || empty($input['password'])) {
    echo json_encode(['success' => false, 'error' => 'Please fill in all fields']);
    exit;
}

$identifier = sanitize_string((string)$input['identifier'], 254);
$password   = (string)$input['password']; // never sanitized — passed raw to password_verify

// Normalize phone-like identifiers to 10 digits
$stripped = preg_replace('/\D/', '', $identifier);
if (strlen($stripped) === 10) {
    $identifier = $stripped;
}

// Per-identifier limit (10 attempts / 15 min)
rate_limit_or_429('login:id:' . strtolower($identifier), 10, 900);

$data = read_json_locked(PREZURE_USERS_FILE);
if (!$data || empty($data['users'])) {
    audit_log('login_no_users');
    echo json_encode(['success' => false, 'error' => 'Incorrect credentials']);
    exit;
}

foreach ($data['users'] as $user) {
    $emailMatch = !empty($user['email']) && emails_equal((string)$user['email'], $identifier);
    $phoneMatch = isset($user['phone']) && (string)$user['phone'] === $identifier;
    $usernameMatch = !empty($user['username']) && strcasecmp((string)$user['username'], $identifier) === 0;
    if (($emailMatch || $phoneMatch || $usernameMatch) && password_verify($password, (string)$user['password'])) {
        // Migrate weak hashes if PHP recommends a rehash.
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            with_locked_json(PREZURE_USERS_FILE, function (array $d) use ($user, $password) {
                foreach ($d['users'] as &$u) {
                    if ((int)$u['id'] === (int)$user['id']) {
                        $u['password'] = password_hash($password, PASSWORD_DEFAULT);
                        break;
                    }
                }
                return $d;
            }, ['users' => [], 'next_id' => 1]);
        }

        login_user((int)$user['id']);                    // session_regenerate_id + drop signup state
        audit_log('login_success', ['user_id' => (int)$user['id']]);

        echo json_encode([
            'success'   => true,
            'redirect'  => app_url('4/dashboard.php'),
            'csrfToken' => csrf_token(),
        ]);
        exit;
    }
}

audit_log('login_fail', ['identifier_hash' => substr(hash('sha256', strtolower($identifier)), 0, 12)]);
echo json_encode(['success' => false, 'error' => 'Incorrect credentials']);
