<?php
// Deletes estimates owned by the signed-in contractor.

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
if (!is_array($input) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$action        = sanitize_string((string)$input['action'], 20);
$estimatesFile = PREZURE_ESTIMATES_DIR . '/' . $userId . '.json';
if (!file_exists($estimatesFile)) {
    echo json_encode(['success' => false, 'error' => 'No estimates file found']);
    exit;
}

$deletedCount = 0;
$err          = null;

$ok = with_locked_json($estimatesFile, function (array $data) use ($action, $input, &$deletedCount, &$err) {
    if (!isset($data['estimates']) || !is_array($data['estimates'])) {
        $err = 'Invalid estimates data';
        return null;
    }

    $original = count($data['estimates']);

    if ($action === 'by_ids') {
        $ids = sanitize_int_array(is_array($input['ids'] ?? null) ? $input['ids'] : []);
        if (empty($ids)) {
            $err = 'No IDs provided';
            return null;
        }
        // Hard cap — prevents an authenticated user from sending millions of IDs.
        if (count($ids) > 1000) {
            $err = 'Too many IDs in a single request (max 1000)';
            return null;
        }
        $data['estimates'] = array_values(array_filter(
            $data['estimates'],
            fn($e) => !in_array((int)($e['id'] ?? 0), $ids, true)
        ));
    } elseif ($action === 'by_date_range') {
        $startDate = sanitize_date((string)($input['start'] ?? ''));
        $endDate   = sanitize_date((string)($input['end']   ?? ''));
        if ($startDate === '' || $endDate === '') {
            $err = 'Invalid date range';
            return null;
        }
        $start    = strtotime($startDate);
        $endOfDay = strtotime($endDate . ' 23:59:59');
        $data['estimates'] = array_values(array_filter($data['estimates'], function ($e) use ($start, $endOfDay) {
            $t = strtotime((string)($e['created_at'] ?? ''));
            return $t === false || $t < $start || $t > $endOfDay;
        }));
    } else {
        $err = 'Unknown action';
        return null;
    }

    $deletedCount = $original - count($data['estimates']);
    return $data;
}, []);

if (!$ok || $err) {
    if (!$err) $err = 'Could not delete estimates';
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}

audit_log('estimate_delete', [
    'user_id' => $userId,
    'action'  => $action,
    'count'   => $deletedCount,
]);

echo json_encode(['success' => true, 'deleted' => $deletedCount]);
