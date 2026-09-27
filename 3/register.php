<?php
// Multi-step signup. POST application/json.
// Step 1 stashes a password hash in the session. Step 2 writes the account.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/ratelimit.php';

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

// Per-IP signup rate limit (defends against automated registration abuse).
rate_limit_or_429('signup:ip:' . client_ip(), 5, 3600);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['step'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$step = sanitize_int($input['step']);

function generateCalculatorKey(array $existingKeys): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    do {
        $key = '';
        for ($i = 0; $i < 24; $i++) {
            $key .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } while (in_array($key, $existingKeys, true));
    return $key;
}

// =============================================================================
// Step 1 — validate + dupe-check, stash in session
// =============================================================================
if ($step === 1) {
    $required = ['name', 'email', 'password', 'phone', 'company_name', 'address'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            exit;
        }
    }

    $name        = sanitize_string((string)$input['name'], 100);
    $email       = sanitize_email((string)$input['email']);
    $password    = (string)$input['password']; // raw — passed to password_hash
    $phone       = sanitize_phone((string)$input['phone']);
    $companyName = sanitize_string((string)$input['company_name'], 150);
    $address     = sanitize_string((string)$input['address'], 300);

    if ($email === '')                      { echo json_encode(['success' => false, 'error' => 'Please enter a valid email address']); exit; }
    if (!validate_password($password))      { echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']); exit; }
    if ($phone === '')                      { echo json_encode(['success' => false, 'error' => 'Phone number must be 10 digits']); exit; }
    if ($name === '' || $companyName === '' || $address === '') {
        echo json_encode(['success' => false, 'error' => 'All fields are required']); exit;
    }

    $data = read_json_locked(PREZURE_USERS_FILE) ?? ['users' => [], 'next_id' => 1];
    foreach ($data['users'] as $user) {
        if (emails_equal((string)$user['email'], $email)) {
            echo json_encode(['success' => false, 'error' => 'That email is already registered']); exit;
        }
        if (($user['phone'] ?? '') === $phone) {
            echo json_encode(['success' => false, 'error' => 'That phone number is already registered']); exit;
        }
        if (strcasecmp((string)($user['company_name'] ?? ''), $companyName) === 0) {
            echo json_encode(['success' => false, 'error' => 'That company name is already registered']); exit;
        }
    }

    $_SESSION['signup'] = [
        'name'          => $name,
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'phone'         => $phone,
        'company_name'  => $companyName,
        'address'       => $address,
    ];

    echo json_encode(['success' => true, 'step' => 2, 'csrfToken' => csrf_token()]);
    exit;
}

// =============================================================================
// Step 2 — finalize account creation
// =============================================================================
if ($step === 2) {
    if (empty($_SESSION['signup']) || empty($_SESSION['signup']['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Session expired. Please start over.']);
        exit;
    }
    if (empty($input['tos_agreed']) || $input['tos_agreed'] !== true) {
        echo json_encode(['success' => false, 'error' => 'You must agree to the Terms of Service']);
        exit;
    }

    $pricingStandard = sanitize_float($input['pricing_standard'] ?? '', false);
    if ($pricingStandard <= 0) {
        echo json_encode(['success' => false, 'error' => 'Standard price per sqft is required']);
        exit;
    }

    $signup          = $_SESSION['signup'];
    $pricingBase     = sanitize_float($input['pricing_base']     ?? '');
    $pricingChemical = sanitize_float($input['pricing_chemical'] ?? '');
    $pricingHeavy    = sanitize_float($input['pricing_heavy']    ?? '');

    $createdUser = null;
    $errMsg      = null;

    $ok = with_locked_json(PREZURE_USERS_FILE, function (array $data) use ($signup, $pricingStandard, $pricingBase, $pricingChemical, $pricingHeavy, &$createdUser, &$errMsg) {
        if (!isset($data['users']))   $data['users']   = [];
        if (!isset($data['next_id'])) $data['next_id'] = 1;

        foreach ($data['users'] as $user) {
            if (emails_equal((string)$user['email'], $signup['email'])) {
                $errMsg = 'That email is already registered';     return null;
            }
            if (($user['phone'] ?? '') === $signup['phone']) {
                $errMsg = 'That phone number is already registered'; return null;
            }
            if (strcasecmp((string)($user['company_name'] ?? ''), $signup['company_name']) === 0) {
                $errMsg = 'That company name is already registered'; return null;
            }
        }

        $existingKeys = array_column($data['users'], 'calculator_key');
        $newUser = [
            'id'               => $data['next_id'],
            'name'             => $signup['name'],
            'email'            => $signup['email'],
            'password'         => $signup['password_hash'],
            'phone'            => $signup['phone'],
            'company_name'     => $signup['company_name'],
            'address'          => $signup['address'],
            'calculator_key'   => generateCalculatorKey($existingKeys),
            'pricing_standard' => $pricingStandard,
            'pricing_base'     => $pricingBase,
            'pricing_chemical' => $pricingChemical,
            'pricing_heavy'    => $pricingHeavy,
            'available_days'   => null,
            'available_hours'  => null,
            'created_at'       => date('c'),
            'tos_agreed_at'    => date('c'),
        ];

        $data['users'][] = $newUser;
        $data['next_id']++;
        $createdUser = $newUser;
        return $data;
    }, ['users' => [], 'next_id' => 1]);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $errMsg ?? 'Could not create account']);
        exit;
    }

    // Pre-create the per-contractor estimates file so submit.php has a target.
    if (!is_dir(PREZURE_ESTIMATES_DIR)) @mkdir(PREZURE_ESTIMATES_DIR, 0755, true);
    $estimatesFile = PREZURE_ESTIMATES_DIR . '/' . $createdUser['id'] . '.json';
    if (!file_exists($estimatesFile)) {
        @file_put_contents($estimatesFile, json_encode([
            'contractor_id' => $createdUser['id'],
            'estimates'     => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Drop the in-progress signup data and rotate the session id.
    unset($_SESSION['signup']);
    login_user((int)$createdUser['id']);
    audit_log('signup_success', ['user_id' => (int)$createdUser['id']]);

    echo json_encode([
        'success'        => true,
        'step'           => 3,
        'calculator_key' => $createdUser['calculator_key'],
        'company_name'   => $createdUser['company_name'],
        'csrfToken'      => csrf_token(),
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid step']);
