<?php
// Saves a customer booking. The contractor is resolved from calculator_key only;
// a client-supplied contractor id is ignored. Preview PNGs are checked and,
// when GD is available, re-encoded before they are written.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/ratelimit.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
apply_cors();

// ── 0. Method + content-type guard ──────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['success' => false, 'error' => 'Unsupported content type']);
    exit;
}

// ── 0a. Origin/Referer same-site enforcement ────────────────────────────────
// CORS already blocks cross-origin script reads, but an attacker page using a
// crafted `text/plain` form would slip past. Enforce that the request comes
// from our own site for additional belt-and-suspenders.
$ip       = client_ip();
$origin   = $_SERVER['HTTP_ORIGIN']  ?? '';
$referer  = $_SERVER['HTTP_REFERER'] ?? '';
$ourHost  = parse_url(APP_EXT_URL, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '');
$srcHost  = '';
if ($origin) {
    $srcHost = parse_url($origin, PHP_URL_HOST) ?: '';
} elseif ($referer) {
    $srcHost = parse_url($referer, PHP_URL_HOST) ?: '';
}
if ($srcHost !== '' && strcasecmp($srcHost, $ourHost) !== 0) {
    http_response_code(403);
    audit_log('submit_origin_block', ['origin' => $origin, 'referer' => $referer]);
    echo json_encode(['success' => false, 'error' => 'Cross-origin submissions are not allowed.']);
    exit;
}

// ── 0b. Rate limit (per-IP) ─────────────────────────────────────────────────
rate_limit_or_429('submit:ip:' . $ip, 5, 60);          // 5 / minute / IP
rate_limit_or_429('submit:ipday:' . $ip, 50, 86400);   // 50 / day / IP

// ── 1. Read payload ─────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

// Calculator key is REQUIRED — never trust client-supplied contractor_id.
$calcKey = sanitize_string((string)($data['calculator_key'] ?? ''), 32);
if (!preg_match('/^[A-Za-z0-9\-]{4,32}$/', $calcKey)) {
    http_response_code(400);
    audit_log('submit_bad_key', ['len' => strlen($calcKey)]);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid calculator key.']);
    exit;
}

if (!isset($data['customer'], $data['estimate'], $data['booking'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Incomplete payload.']);
    exit;
}

$customer  = is_array($data['customer'])  ? $data['customer']  : [];
$booking   = is_array($data['booking'])   ? $data['booking']   : [];
$estimate  = is_array($data['estimate'])  ? $data['estimate']  : [];
$areas     = is_array($data['areas'] ?? null) ? $data['areas'] : [];
$timestamp = sanitize_timestamp((string)($data['timestamp'] ?? '')) ?: date('c');

// Sanitize customer fields
$customer['name']    = sanitize_string((string)($customer['name'] ?? ''), 100);
$customer['contact'] = sanitize_string((string)($customer['contact'] ?? ''), 254);
$customer['address'] = sanitize_string((string)($customer['address'] ?? ''), 500);

if ($customer['name'] === '' || $customer['contact'] === '' || $customer['address'] === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Customer name, contact, and address are required.']);
    exit;
}

// Sanitize booking fields
$booking['date']        = sanitize_date((string)($booking['date'] ?? ''));
$booking['time']        = sanitize_time((string)($booking['time'] ?? ''));
$booking['displayDate'] = sanitize_string((string)($booking['displayDate'] ?? ''), 100);
$booking['displayTime'] = sanitize_string((string)($booking['displayTime'] ?? ''), 20);

if ($booking['date'] === '' || $booking['time'] === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid date and time are required.']);
    exit;
}

// Sanitize estimate fields
$estimate['total']         = sanitize_float($estimate['total']         ?? 0, false);
$estimate['base_fee']      = sanitize_float($estimate['base_fee']      ?? 0, false);
$estimate['service_total'] = sanitize_float($estimate['service_total'] ?? 0, false);
$estimate['totalSqft']     = sanitize_float($estimate['totalSqft']     ?? 0, false);

// Sanitize coordinates
$data['coordinates'] = sanitize_coordinates($data['coordinates'] ?? []);

// Sanitize map center and zoom
$data['map_center'] = [
    'lat' => sanitize_lat($data['map_center']['lat'] ?? null) ?? 0,
    'lng' => sanitize_lng($data['map_center']['lng'] ?? null) ?? 0,
];
$data['map_zoom'] = sanitize_int($data['map_zoom'] ?? 19);

// Sanitize areas list (used in HTML email + estimates JSON)
$cleanAreas = [];
foreach ($areas as $a) {
    if (!is_array($a)) continue;
    $cleanAreas[] = [
        'label' => sanitize_string((string)($a['label'] ?? ''), 100),
        'sqft'  => sanitize_float($a['sqft'] ?? 0, false),
        'rate'  => sanitize_float($a['rate'] ?? 0, false),
    ];
}
$areas = $cleanAreas;

// ── 2. Look up contractor by calculator_key (NEVER by client ID) ────────────
$usersData = read_json_locked(PREZURE_USERS_FILE);
if (!is_array($usersData) || !isset($usersData['users'])) {
    http_response_code(500);
    audit_log('submit_users_missing');
    echo json_encode(['success' => false, 'error' => 'Data source error.']);
    exit;
}
$contractor = null;
foreach ($usersData['users'] as $c) {
    if (($c['calculator_key'] ?? '') === $calcKey) { $contractor = $c; break; }
}
if (!$contractor) {
    http_response_code(404);
    audit_log('submit_unknown_key', ['key_prefix' => substr($calcKey, 0, 4)]);
    echo json_encode(['success' => false, 'error' => 'Contractor not found.']);
    exit;
}
$contractorId = (int) $contractor['id'];
$toEmail      = (string) ($contractor['email'] ?? '');
$toName       = (string) ($contractor['company_name'] ?? '');

// Per-contractor rate limit (avoid mailbomb of any single contractor).
rate_limit_or_429('submit:contractor:' . $contractorId, 30, 3600);

// ── 3. Token + booking record ───────────────────────────────────────────────
$token = bin2hex(random_bytes(24));    // 48-char hex string

// ── 3a. Validate + persist preview image ────────────────────────────────────
$previewRel = '';
$previewImage = $data['preview_image'] ?? null;
if (is_string($previewImage) && str_starts_with($previewImage, 'data:image/png;base64,')) {
    $b64 = substr($previewImage, strlen('data:image/png;base64,'));
    $decoded = base64_decode($b64, true);
    if ($decoded !== false
        && strlen($decoded) >= 8
        && strlen($decoded) < 1024 * 1024 // <1MB
        && substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n") {
        // Re-encode through GD to strip any embedded payload (steganography,
        // PHP shells in EXIF, PNG-IDAT-encoded JS, etc.). If GD isn't
        // available we still write the validated raw bytes — the magic-header
        // check + the .htaccess php_flag engine off in /1/previews/ keep us
        // safe from execution.
        if (function_exists('imagecreatefromstring') && function_exists('imagepng')) {
            $img = @imagecreatefromstring($decoded);
            if ($img !== false) {
                ob_start();
                imagepng($img, null, 6);
                $clean = ob_get_clean();
                imagedestroy($img);
                if ($clean !== '' && substr($clean, 0, 8) === "\x89PNG\r\n\x1a\n") {
                    $decoded = $clean;
                }
            }
        }
        if (!is_dir(PREZURE_PREVIEWS_DIR)) @mkdir(PREZURE_PREVIEWS_DIR, 0755, true);
        $previewPath = PREZURE_PREVIEWS_DIR . '/' . $token . '.png';
        if (@file_put_contents($previewPath, $decoded) !== false) {
            $previewRel = '1/previews/' . $token . '.png';
        }
    } else {
        audit_log('submit_bad_preview', ['contractor_id' => $contractorId]);
    }
}

// ── 3b. Atomic write to bookings.json ───────────────────────────────────────
$bookingRecord = [
    'token'         => $token,
    'status'        => 'pending',
    'contractor_id' => $contractorId,
    'customer'      => $customer,
    'booking'       => $booking,
    'estimate'      => $estimate,
    'areas'         => $areas,
    'created_at'    => $timestamp,
    'preview_file'  => $previewRel,
];

$ok = with_locked_json(PREZURE_BOOKINGS_FILE, function (array $bookings) use ($token, $bookingRecord) {
    $bookings[$token] = $bookingRecord;
    return $bookings;
}, []);
if (!$ok) {
    http_response_code(500);
    audit_log('submit_bookings_write_fail', ['contractor_id' => $contractorId]);
    echo json_encode(['success' => false, 'error' => 'Could not save booking record.']);
    exit;
}

// ── 4. Append estimate to per-contractor estimates file (atomic) ────────────
$estimateJsonFile = PREZURE_ESTIMATES_DIR . '/' . $contractorId . '.json';
$breakdown = is_array($estimate['breakdown'] ?? null) ? $estimate['breakdown'] : [];
$totalSqft = $estimate['totalSqft']
    ?? (($breakdown['standard']['sqft'] ?? 0)
      + ($breakdown['chemical']['sqft'] ?? 0)
      + ($breakdown['heavy']['sqft']    ?? 0));

$estimateRecord = [
    // id assigned inside the lock to guarantee uniqueness
    'address'          => $customer['address'] ?? '',
    'price'            => (float) ($estimate['total'] ?? 0),
    'sqft'             => (float) $totalSqft,
    'intensity_levels' => [
        'standard' => (float) ($breakdown['standard']['sqft'] ?? 0),
        'chemical' => (float) ($breakdown['chemical']['sqft'] ?? 0),
        'heavy'    => (float) ($breakdown['heavy']['sqft']    ?? 0),
    ],
    'preferred_date'   => $booking['date'] ?? '',
    'preferred_time'   => $booking['time'] ?? '',
    'coordinates'      => $data['coordinates'] ?? [],
    'map_center'       => $data['map_center'] ?? ['lat' => 0.0, 'lng' => 0.0],
    'map_zoom'         => (int) ($data['map_zoom'] ?? 18),
    'created_at'       => $timestamp,
];

$ok = with_locked_json(
    $estimateJsonFile,
    function (array $fileData) use ($contractorId, $estimateRecord) {
        if (!isset($fileData['contractor_id'])) $fileData['contractor_id'] = $contractorId;
        if (!isset($fileData['estimates']) || !is_array($fileData['estimates'])) {
            $fileData['estimates'] = [];
        }
        $maxId = 0;
        foreach ($fileData['estimates'] as $e) {
            if (isset($e['id']) && $e['id'] > $maxId) $maxId = (int)$e['id'];
        }
        $estimateRecord['id'] = $maxId + 1;
        // Newest first, so the dashboard does not have to sort on every view.
        array_unshift($fileData['estimates'], $estimateRecord);
        return $fileData;
    },
    ['contractor_id' => $contractorId, 'estimates' => []]
);
if (!$ok) {
    audit_log('submit_estimate_write_fail', ['contractor_id' => $contractorId]);
    // Booking already saved — keep going so the contractor still gets the email.
}

audit_log('submit_success', [
    'contractor_id' => $contractorId,
    'token'         => substr($token, 0, 8),
]);

// ── 5. Build the HTML email ─────────────────────────────────────────────────
// EVERY interpolation of customer / contractor data MUST go through esc().
$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$baseUrl    = APP_EXT_URL;
$acceptUrl  = $esc($baseUrl . '/1/respond.php?token=' . $token . '&action=accept');
$declineUrl = $esc($baseUrl . '/1/respond.php?token=' . $token . '&action=decline');

$areaRows = '';
foreach ($areas as $a) {
    $label = $esc((string)$a['label']);
    $sqft  = number_format((float)$a['sqft'], 0);
    $cost  = '$' . number_format(((float)$a['sqft']) * ((float)$a['rate']), 2);
    $areaRows .= "
        <tr>
            <td style='padding:10px 16px;border-bottom:1px solid #28314e;color:#ffffff;'>{$label}</td>
            <td style='padding:10px 16px;border-bottom:1px solid #28314e;color:#ffffff;text-align:right;'>{$sqft} sq ft</td>
            <td style='padding:10px 16px;border-bottom:1px solid #28314e;color:#f6dc4b;text-align:right;'>{$cost}</td>
        </tr>";
}

$baseFormatted  = '$' . number_format((float)$estimate['base_fee'],      2);
$totalFormatted = '$' . number_format((float)$estimate['total'],         2);

$safeName        = $esc($customer['name']);
$safeContact     = $esc($customer['contact']);
$safeAddress     = $esc($customer['address']);
$safeCompany     = $esc($toName);
$safeDispDate    = $esc($booking['displayDate']);
$safeDispTime    = $esc($booking['displayTime']);
$safeTimestamp   = $esc($timestamp);
$safeToken       = $esc($token);

// Subject is plain text, not HTML — only need CR/LF safety (already enforced
// by sanitize_string) and a header-safe stripper for ":<>" via sanitize_header.
$subject = sanitize_header(
    'New Booking Request — ' . $customer['name'] . ' — ' . $booking['displayDate']
);

$previewBlock = '';
if ($previewRel) {
    $previewUrl = $esc(rtrim($baseUrl, '/') . '/' . $previewRel);
    $previewBlock = '
      <div style="background:#0e1324;border-radius:8px;overflow:hidden;margin-bottom:20px;padding:14px 16px;">
        <div style="color:#f6dc4b;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:10px;">Service Area Preview</div>
        <img src="' . $previewUrl . '" style="max-width:100%;border-radius:8px;border:1px solid rgba(255,255,255,0.08);display:block;" alt="Service area map" />
      </div>';
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0e1324;font-family:'Outfit',Arial,sans-serif;">
  <div style="max-width:600px;margin:0 auto;background:#161f39;border:1px solid rgba(255,255,255,0.08);border-radius:12px;overflow:hidden;">
    <div style="background:#0e1324;padding:28px 32px;border-bottom:1px solid rgba(255,255,255,0.08);">
      <div style="color:#00e5a0;font-size:13px;letter-spacing:3px;margin-bottom:6px;text-transform:uppercase;font-weight:700;">New Booking Request</div>
      <div style="color:#f6dc4b;font-size:28px;font-weight:700;letter-spacing:1px;">PREZURE</div>
      <div style="color:#ffffff;font-size:13px;margin-top:4px;">Estimate &amp; Scheduling Platform</div>
    </div>
    <div style="padding:28px 32px;">
      <p style="color:#ffffff;font-size:15px;line-height:1.6;margin:0 0 20px;">
        Hi <strong style="color:#f6dc4b;">{$safeCompany}</strong>,<br>
        You have a new booking request from a customer. Please review and confirm or decline below.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#0e1324;border-radius:8px;overflow:hidden;margin-bottom:20px;">
        <tr><td style="padding:14px 16px 6px;color:#f6dc4b;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;" colspan="2">Customer</td></tr>
        <tr><td style="padding:6px 16px;color:rgba(255,255,255,0.5);font-size:13px;width:40%;">Name</td><td style="padding:6px 16px;color:#ffffff;font-size:13px;font-weight:600;">{$safeName}</td></tr>
        <tr><td style="padding:6px 16px;color:rgba(255,255,255,0.5);font-size:13px;">Contact</td><td style="padding:6px 16px;color:#ffffff;font-size:13px;">{$safeContact}</td></tr>
        <tr><td style="padding:6px 16px 14px;color:rgba(255,255,255,0.5);font-size:13px;">Address</td><td style="padding:6px 16px 14px;color:#ffffff;font-size:13px;">{$safeAddress}</td></tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#0e1324;border-radius:8px;overflow:hidden;margin-bottom:20px;">
        <tr><td style="padding:14px 16px 6px;color:#f6dc4b;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;" colspan="2">Requested Appointment</td></tr>
        <tr><td style="padding:6px 16px;color:rgba(255,255,255,0.5);font-size:13px;width:40%;">Date</td><td style="padding:6px 16px;color:#ffffff;font-size:15px;font-weight:700;">{$safeDispDate}</td></tr>
        <tr><td style="padding:6px 16px 14px;color:rgba(255,255,255,0.5);font-size:13px;">Time</td><td style="padding:6px 16px 14px;color:#ffffff;font-size:15px;font-weight:700;">{$safeDispTime}</td></tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#0e1324;border-radius:8px;overflow:hidden;margin-bottom:20px;">
        <tr><td style="padding:14px 16px 8px;color:#f6dc4b;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700;" colspan="3">Estimate Breakdown</td></tr>
        <tr>
          <td style="padding:10px 16px;border-bottom:1px solid #28314e;color:rgba(255,255,255,0.5);font-size:12px;">Service</td>
          <td style="padding:10px 16px;border-bottom:1px solid #28314e;color:rgba(255,255,255,0.5);font-size:12px;text-align:right;">Area</td>
          <td style="padding:10px 16px;border-bottom:1px solid #28314e;color:rgba(255,255,255,0.5);font-size:12px;text-align:right;">Cost</td>
        </tr>
        <tr>
          <td style="padding:10px 16px;border-bottom:1px solid #28314e;color:#ffffff;">Base Fee</td>
          <td style="padding:10px 16px;border-bottom:1px solid #28314e;color:#ffffff;text-align:right;">—</td>
          <td style="padding:10px 16px;border-bottom:1px solid #28314e;color:#f6dc4b;text-align:right;">{$baseFormatted}</td>
        </tr>
        {$areaRows}
        <tr style="background:#28314e;">
          <td style="padding:12px 16px;color:#ffffff;font-weight:700;font-size:15px;" colspan="2">TOTAL</td>
          <td style="padding:12px 16px;color:#f6dc4b;font-weight:700;font-size:20px;text-align:right;">{$totalFormatted}</td>
        </tr>
      </table>
      {$previewBlock}
      <p style="color:rgba(255,255,255,0.6);font-size:12px;margin:0 0 16px;">Click one of the buttons below to respond. Your customer will receive an automatic email based on your selection.</p>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="padding-right:8px;" width="50%">
            <a href="{$acceptUrl}" style="display:block;background:#00e5a0;color:#0e1324;text-align:center;padding:16px;border-radius:8px;font-weight:700;font-size:16px;text-decoration:none;letter-spacing:0.5px;">&#10003; ACCEPT</a>
          </td>
          <td style="padding-left:8px;" width="50%">
            <a href="{$declineUrl}" style="display:block;background:#28314e;color:#ff6b6b;text-align:center;padding:16px;border-radius:8px;font-weight:700;font-size:16px;text-decoration:none;border:1px solid rgba(255,107,107,0.3);letter-spacing:0.5px;">&#10005; DECLINE</a>
          </td>
        </tr>
      </table>
      <p style="color:rgba(255,255,255,0.3);font-size:11px;margin:20px 0 0;text-align:center;">
        Booking ID: {$safeToken}<br>
        Submitted: {$safeTimestamp}
      </p>
    </div>
    <div style="background:#0e1324;padding:16px 32px;border-top:1px solid rgba(255,255,255,0.06);text-align:center;">
      <p style="color:rgba(255,255,255,0.3);font-size:11px;margin:0;">Powered by <strong style="color:#00e5a0;">Prezure</strong> &mdash; Pressure Washing Estimate Platform</p>
    </div>
  </div>
</body>
</html>
HTML;

// ── 6. Send email ──────────────────────────────────────────────────────────
// Headers are built from constants only — no user input touches them.
$fromName = sanitize_header(MAIL_FROM_NAME, 60);
$fromAddr = MAIL_FROM_ADDR;
$replyAddr = MAIL_REPLY_ADDR;

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: {$fromName} <{$fromAddr}>\r\n";
$headers .= "Reply-To: <{$replyAddr}>\r\n";
$headers .= 'X-Mailer: Prezure-PHP/' . phpversion();

$sent = false;
if (MAIL_TRANSPORT === 'smtp' && SMTP_HOST !== '' && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // PHPMailer is available — preferred path. Headers are built by the
    // library so any user-supplied data is properly encoded.
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = SMTP_USER !== '';
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->setFrom(MAIL_FROM_ADDR, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_REPLY_ADDR);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->CharSet = 'UTF-8';
        $sent = $mail->send();
    } catch (Throwable $e) {
        $sent = false;
        audit_log('submit_smtp_fail', ['contractor_id' => $contractorId]);
    }
} else {
    $sent = @mail($toEmail, $subject, $html, $headers);
}

if (!$sent) {
    http_response_code(202);
    audit_log('submit_email_fail', ['contractor_id' => $contractorId]);
    echo json_encode([
        'success' => true,
        'message' => 'Booking saved. Email delivery is currently unavailable; the contractor will see the request in their dashboard.'
    ]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Booking request sent to contractor.']);
exit;
