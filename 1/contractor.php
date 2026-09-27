<?php
// Public contractor lookup by calculator key.
// Never returns the password or the numeric user id. The Maps script URL
// may contain MAPS_API_KEY; that key is meant for the browser.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/ratelimit.php';

header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json');

if (!apply_cors()) {
    http_response_code(403);
    echo json_encode(['error' => 'Origin not allowed.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Per-IP rate limit: a calculator load is one request — no legitimate caller
// hits this more than a few times per minute.
rate_limit_or_429('contractor:' . client_ip(), 30, 60);

// ── Validate request key ──────────────────────────────────────────────────────
$key = sanitize_string($_GET['key'] ?? '', 32);
if ($key === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing key parameter.']);
    exit;
}
if (!preg_match('/^[A-Za-z0-9\-]{4,32}$/', $key)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid key format.']);
    exit;
}

// Per-key rate limit: even if the IP rotates, a single key can't be probed
// faster than 15 RPS.
rate_limit_or_429('contractor:key:' . $key, 30, 60);

// ── Look up contractor ───────────────────────────────────────────────────────
$usersData = read_json_locked(PREZURE_USERS_FILE);
if (!is_array($usersData) || !isset($usersData['users']) || !is_array($usersData['users'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Data source error.']);
    exit;
}

$row = null;
foreach ($usersData['users'] as $c) {
    if (($c['calculator_key'] ?? '') === $key) { $row = $c; break; }
}
if ($row === null) {
    http_response_code(404);
    echo json_encode(['error' => 'No contractor found for that key.']);
    exit;
}

// ── Build the safe response ──────────────────────────────────────────────────
$raw_rates = [
    'standard' => (float)($row['pricing_standard'] ?? 0),
    'chemical' => (float)($row['pricing_chemical'] ?? 0),
    'heavy'    => (float)($row['pricing_heavy']    ?? 0),
];
$rates = [];
foreach ($raw_rates as $type => $val) {
    $v = (float) $val;
    if ($v > 0) $rates[$type] = $v;
}
if (empty($rates)) {
    http_response_code(422);
    echo json_encode(['error' => 'No active rates configured.']);
    exit;
}

$availability = [
    'days'              => array_values(array_filter((array)($row['available_days']  ?? []), 'is_string')),
    'time_slots'        => array_values(array_filter((array)($row['available_hours'] ?? []), 'is_string')),
    'slot_duration_min' => (int)($row['slot_duration_min'] ?? 60),
];

// ── Build the Maps script URL (key embedded server-side, never echoed alone) ─
$maps_url = '';
if (MAPS_API_KEY !== '') {
    $maps_url = 'https://maps.googleapis.com/maps/api/js'
              . '?key='       . urlencode(MAPS_API_KEY)
              . '&libraries=' . 'places,geometry'
              . '&callback='  . '_mapsReady'
              . '&loading='   . 'async';
}

// company_id stays internal. Client uses calculator_key for submission;
// submit.php re-derives company_id from that key server-side.
echo json_encode([
    'company_name' => (string) ($row['company_name'] ?? ''),
    'phone'        => (string) ($row['phone']        ?? ''),
    'address'      => (string) ($row['address']      ?? ''),
    'pricing' => [
        'base_price' => (float) ($row['pricing_base'] ?? 0),
        'rate_type'  => 'area',
        'rates'      => $rates,
    ],
    'availability' => $availability,
    // maps_url is opaque to JS — it shoves it into a <script src> and never
    // reads the embedded key.
    'maps_url'     => $maps_url,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
