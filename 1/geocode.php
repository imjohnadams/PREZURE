<?php
// Server-side geocode proxy. Uses MAPS_SERVER_KEY (falls back to MAPS_API_KEY).

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/ratelimit.php';

header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json');

// CORS — restrict to allow-list. Same-origin (no Origin header) always passes.
if (!apply_cors()) {
    http_response_code(403);
    echo json_encode(['error' => 'Origin not allowed.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Per-IP rate limit: 30 lookups / minute is plenty for a customer drawing
// a property; aggressive scraping will trip immediately.
rate_limit_or_429('geocode:' . client_ip(), 30, 60);

if (MAPS_SERVER_KEY === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Geocoding is not configured on the server.']);
    exit;
}

$address = sanitize_string($_GET['address'] ?? '', 500);
if (!$address) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing address parameter.']);
    exit;
}

// ── Cache check ──────────────────────────────────────────────────────────────
// Successful geocodes are stable for months — cache them so repeat lookups
// (same address typed twice, same address from two customers) don't burn
// API quota.
$cacheKey = strtolower(trim($address));
$cacheFile = PREZURE_GEOCODE_CACHE;
$cache = read_json_locked($cacheFile) ?? [];
$now = time();
$cacheTtl = 30 * 86400; // 30 days

if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])
    && ($cache[$cacheKey]['ts'] ?? 0) + $cacheTtl > $now) {
    $hit = $cache[$cacheKey];
    echo json_encode([
        'lat'               => $hit['lat'],
        'lng'               => $hit['lng'],
        'formatted_address' => $hit['formatted_address'],
        'cached'            => true,
    ]);
    exit;
}

$url = 'https://maps.googleapis.com/maps/api/geocode/json'
     . '?address=' . urlencode($address)
     . '&key='     . urlencode(MAPS_SERVER_KEY);

$ctx      = stream_context_create(['http' => ['timeout' => 10]]);
$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Geocoding request failed. Check server connectivity.']);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['status']) || $data['status'] !== 'OK' || empty($data['results'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Address not found.']);
    exit;
}

$result = $data['results'][0];
$out = [
    'lat'               => $result['geometry']['location']['lat'],
    'lng'               => $result['geometry']['location']['lng'],
    'formatted_address' => $result['formatted_address'],
];

// ── Persist to cache ─────────────────────────────────────────────────────────
// Cap cache size at 10k entries to bound disk usage; evict oldest first.
@with_locked_json($cacheFile, function ($cache) use ($cacheKey, $out, $now) {
    if (!is_array($cache)) $cache = [];
    $cache[$cacheKey] = [
        'lat'               => $out['lat'],
        'lng'               => $out['lng'],
        'formatted_address' => $out['formatted_address'],
        'ts'                => $now,
    ];
    if (count($cache) > 10000) {
        uasort($cache, fn($a, $b) => ($a['ts'] ?? 0) <=> ($b['ts'] ?? 0));
        $cache = array_slice($cache, count($cache) - 10000, null, true);
    }
    return $cache;
}, []);

echo json_encode($out);
