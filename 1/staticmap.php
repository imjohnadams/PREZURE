<?php
// Server-side Google Static Maps proxy. Uses MAPS_SERVER_KEY.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/ratelimit.php';

header('X-Content-Type-Options: nosniff');

if (!apply_cors()) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Origin not allowed.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Per-IP rate limit: 60 tiles per minute is enough for an interactive
// session (zoom + pan) but blocks abusive automation.
rate_limit_or_429('staticmap:' . client_ip(), 60, 60);

if (MAPS_SERVER_KEY === '') {
    http_response_code(503);
    header('Content-Type: text/plain');
    exit('Static map service is not configured.');
}

$lat    = sanitize_float($_GET['lat'] ?? '', false);
$lng    = sanitize_float($_GET['lng'] ?? '', false);
$zoom   = sanitize_int($_GET['zoom'] ?? 19);
$width  = min(sanitize_int($_GET['width']  ?? 640), 640);
$height = min(sanitize_int($_GET['height'] ?? 480), 640);

if ($lat == 0 && $lng == 0)    { http_response_code(400); exit('Missing coordinates'); }
if ($zoom < 1 || $zoom > 21)   { http_response_code(400); exit('Invalid zoom'); }
if ($width < 32 || $height < 32) { http_response_code(400); exit('Invalid size'); }

$url = 'https://maps.googleapis.com/maps/api/staticmap'
     . '?center=' . $lat . ',' . $lng
     . '&zoom='   . $zoom
     . '&size='   . $width . 'x' . $height
     . '&maptype=satellite'
     . '&key='    . urlencode(MAPS_SERVER_KEY);

$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$img = @file_get_contents($url, false, $ctx);
if ($img === false) { http_response_code(502); exit('Failed to fetch map image'); }

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
echo $img;
