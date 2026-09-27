<?php
// Updates the signed-in contractor's settings. Returns the user without the password.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/audit.php';

prezure_session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$userId = require_login('json');

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

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['section'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$section = sanitize_string((string)$input['section'], 20);
$error          = null;
$updatedUser    = null;
$passwordChanged = false;

$ok = with_locked_json(PREZURE_USERS_FILE, function (array $data) use ($userId, $section, $input, &$error, &$updatedUser, &$passwordChanged) {
    if (empty($data['users'])) { $error = 'User data unavailable'; return null; }

    $userIdx = null;
    foreach ($data['users'] as $i => $u) {
        if ((int)$u['id'] === $userId) { $userIdx = $i; break; }
    }
    if ($userIdx === null) { $error = 'User not found'; return null; }
    $user = &$data['users'][$userIdx];

    switch ($section) {
        case 'account': {
            $name        = sanitize_string((string)($input['name']         ?? ''), 100);
            $email       = sanitize_email((string)($input['email']        ?? ''));
            $phone       = sanitize_phone((string)($input['phone']        ?? ''));
            $companyName = sanitize_string((string)($input['company_name'] ?? ''), 150);
            $address     = sanitize_string((string)($input['address']     ?? ''), 300);

            if ($name === '')             { $error = 'Name is required';              return null; }
            if ($email === '')            { $error = 'Please enter a valid email';   return null; }
            if ($phone === '')            { $error = 'Phone number must be 10 digits'; return null; }
            if ($companyName === '')      { $error = 'Company name is required';      return null; }
            if ($address === '')          { $error = 'Address is required';           return null; }

            foreach ($data['users'] as $i => $u) {
                if ($i === $userIdx) continue;
                if (emails_equal((string)$u['email'], $email))                    { $error = 'That email is already registered';        return null; }
                if (($u['phone'] ?? '') === $phone)                               { $error = 'That phone number is already registered'; return null; }
                if (strcasecmp((string)($u['company_name'] ?? ''), $companyName) === 0) { $error = 'That company name is already registered'; return null; }
            }

            $user['name']         = $name;
            $user['email']        = $email;
            $user['phone']        = $phone;
            $user['company_name'] = $companyName;
            $user['address']      = $address;
            break;
        }

        case 'password': {
            $current = (string)($input['current_password'] ?? '');
            $newPass = (string)($input['new_password']    ?? '');
            $confirm = (string)($input['confirm_password'] ?? '');

            if ($current === '')                                 { $error = 'Current password is required';                return null; }
            if (!password_verify($current, (string)$user['password'])) { $error = 'Current password is incorrect';         return null; }
            if (!validate_password($newPass))                    { $error = 'New password must be at least 8 characters'; return null; }
            if ($newPass !== $confirm)                           { $error = 'New passwords do not match';                  return null; }

            $user['password'] = password_hash($newPass, PASSWORD_DEFAULT);
            $passwordChanged  = true;
            break;
        }

        case 'pricing': {
            $standard = sanitize_float($input['pricing_standard'] ?? '', false);
            if ($standard === null || $standard <= 0) { $error = 'Standard price per sqft is required'; return null; }
            $user['pricing_standard'] = $standard;
            $user['pricing_base']     = sanitize_float($input['pricing_base']     ?? '');
            $user['pricing_chemical'] = sanitize_float($input['pricing_chemical'] ?? '');
            $user['pricing_heavy']    = sanitize_float($input['pricing_heavy']    ?? '');
            break;
        }

        case 'availability': {
            $validDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
            $days       = sanitize_string_array($input['available_days']     ?? [], $validDays);
            $hoursStart = sanitize_time((string)($input['available_hours_start'] ?? ''));
            $hoursEnd   = sanitize_time((string)($input['available_hours_end']   ?? ''));

            if (empty($days)) {
                $user['available_days']  = null;
                $user['available_hours'] = null;
            } else {
                $user['available_days'] = $days;
                $hours = [];
                if ($hoursStart !== '' && $hoursEnd !== '') {
                    $startH = (int) substr($hoursStart, 0, 2);
                    $endH   = (int) substr($hoursEnd,   0, 2);
                    if ($startH < $endH) {
                        for ($h = $startH; $h <= $endH; $h++) {
                            $hours[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
                        }
                    }
                }
                $user['available_hours'] = $hours !== [] ? $hours : null;
            }
            break;
        }

        default:
            $error = 'Unknown section';
            return null;
    }

    $updatedUser = $user;
    return $data;
}, ['users' => [], 'next_id' => 1]);

if (!$ok || $error) {
    if (!$error) $error = 'Could not save settings';
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

audit_log('settings_update', ['user_id' => $userId, 'section' => $section]);

if ($passwordChanged) {
    // Per OWASP: rotate the session ID after credential changes.
    session_regenerate_id(true);
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    audit_log('password_change', ['user_id' => $userId]);
}

echo json_encode([
    'success'   => true,
    'user'      => safe_user($updatedUser),
    'csrfToken' => csrf_token(),
]);
