<?php
// Returns the signed-in contractor's estimates, newest first.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sanitize.php';

prezure_session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$userId = require_login('json');

$estimatesFile = PREZURE_ESTIMATES_DIR . '/' . $userId . '.json';
$data = read_json_locked($estimatesFile);
$estimates = is_array($data['estimates'] ?? null) ? $data['estimates'] : [];

// Newest-first. submit.php now array_unshifts so this is usually a no-op,
// but historical files will still be reordered correctly.
usort($estimates, fn($a, $b) =>
    (int)strtotime((string)($b['created_at'] ?? ''))
  <=> (int)strtotime((string)($a['created_at'] ?? ''))
);

$limit = sanitize_int($_GET['limit'] ?? 0);
if ($limit > 0) {
    $limit     = min($limit, 1000);
    $estimates = array_slice($estimates, 0, $limit);
}

echo json_encode(['estimates' => $estimates]);
