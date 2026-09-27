<?php
// Contractor accept/decline.
// GET only shows a confirmation form. POST with _confirm=1 updates the booking.
// Email clients that prefetch links must not be able to change status.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/ratelimit.php';
require_once __DIR__ . '/../includes/audit.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=UTF-8');

// Per-IP rate limit deters automated token-guessing. Generous on GET because
// legitimate clicks come from email and may be retried on flaky networks.
rate_limit_or_429('respond:' . client_ip(), 30, 60);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$src    = $method === 'POST' ? $_POST : $_GET;

$token  = sanitize_string((string)($src['token']  ?? ''), 48);
$action = sanitize_string((string)($src['action'] ?? ''), 10);

if (!preg_match('/^[a-f0-9]{48}$/', $token) || !in_array($action, ['accept', 'decline'], true)) {
    _page('Invalid Link', 'This link is invalid or has expired.', false);
    exit;
}

// ── Load booking ─────────────────────────────────────────────────────────────
$bookings = read_json_locked(PREZURE_BOOKINGS_FILE);
if (!is_array($bookings) || !isset($bookings[$token])) {
    _page('Not Found', 'No booking found for this token.', false);
    exit;
}
$booking = $bookings[$token];

if (($booking['status'] ?? '') !== 'pending') {
    $already = htmlspecialchars(ucfirst((string)$booking['status']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    _page('Already Responded', "This booking was already <strong>{$already}</strong>. No changes made.", true);
    exit;
}

// =============================================================================
// GET — render confirmation page (no mutation)
// =============================================================================
if ($method !== 'POST') {
    _confirm_page($token, $action, $booking);
    exit;
}

// =============================================================================
// POST — apply the response
// =============================================================================
if (($_POST['_confirm'] ?? '') !== '1') {
    _page('Invalid Submission', 'Form submission was incomplete.', false);
    exit;
}

// ── Load contractor + customer ───────────────────────────────────────────────
$usersData = read_json_locked(PREZURE_USERS_FILE);
$contractor = null;
foreach (($usersData['users'] ?? []) as $c) {
    if ((int)$c['id'] === (int)$booking['contractor_id']) { $contractor = $c; break; }
}
if (!$contractor) {
    audit_log('respond_no_contractor', ['token' => substr($token, 0, 8)]);
    _page('Error', 'Contractor record not found.', false);
    exit;
}

$customer        = is_array($booking['customer'] ?? null) ? $booking['customer'] : [];
$bookingDate     = is_array($booking['booking']  ?? null) ? $booking['booking']  : [];
$estimate        = is_array($booking['estimate'] ?? null) ? $booking['estimate'] : [];
$areas           = is_array($booking['areas']    ?? null) ? $booking['areas']    : [];
$contractorName  = (string)($contractor['company_name'] ?? '');
$contractorPhone = (string)($contractor['phone']        ?? '');
$customerContact = (string)($customer['contact']        ?? '');
$customerEmail   = '';
$customerPhone   = '';
if (filter_var($customerContact, FILTER_VALIDATE_EMAIL)) {
    $customerEmail = $customerContact;
} else {
    $customerPhone = $customerContact;
}

// ── Atomic status update ─────────────────────────────────────────────────────
$newStatus  = ($action === 'accept') ? 'accepted' : 'declined';
$respondedAt = date('c');

$updated = with_locked_json(PREZURE_BOOKINGS_FILE, function (array $all) use ($token, $newStatus, $respondedAt) {
    if (!isset($all[$token])) return null;            // race: deleted in between
    if ($all[$token]['status'] !== 'pending') return null; // race: someone else clicked first
    $all[$token]['status']       = $newStatus;
    $all[$token]['responded_at'] = $respondedAt;
    return $all;
}, []);

if (!$updated) {
    _page('Already Responded', 'This booking was responded to in the meantime.', true);
    exit;
}

audit_log('respond_' . $newStatus, [
    'token'         => substr($token, 0, 8),
    'contractor_id' => (int)$booking['contractor_id'],
]);

// ── Build customer email + ICS ───────────────────────────────────────────────
$totalFmt = '$' . number_format((float)($estimate['total'] ?? 0), 2);

$ics = null;
if ($action === 'accept') {
    try {
        $dtStart = new DateTime(($bookingDate['date'] ?? '') . 'T' . ($bookingDate['time'] ?? '00:00') . ':00');
        $dtEnd   = clone $dtStart;
        $dtEnd->modify('+2 hours');
        $icsStart = $dtStart->format('Ymd\THis');
        $icsEnd   = $dtEnd->format('Ymd\THis');
        $icsUID   = $token . '@prezure.app';
        $icsStamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');

        $areasList = [];
        foreach ($areas as $a) {
            $label = (string)($a['label'] ?? '');
            $sqft  = (float)($a['sqft'] ?? 0);
            $areasList[] = $label . ' (' . number_format($sqft, 0) . ' sq ft)';
        }
        $areasStr = implode(', ', $areasList);

        // Per-line escaping per RFC 5545. Each value must be escaped
        // separately because the joined string already contains commas /
        // semicolons that are valid as part of the literal.
        $sumLine  = 'Pressure Washing — ' . $contractorName;
        $descLine = 'Service at ' . (string)($customer['address'] ?? '') . "\n"
                  . 'Areas: ' . $areasStr . "\n"
                  . 'Total: ' . $totalFmt . "\n"
                  . 'Contact: ' . $contractorPhone;
        $locLine  = (string)($customer['address'] ?? '');

        $ics = "BEGIN:VCALENDAR\r\n"
             . "VERSION:2.0\r\n"
             . "PRODID:-//Prezure//Estimate//EN\r\n"
             . "CALSCALE:GREGORIAN\r\n"
             . "METHOD:REQUEST\r\n"
             . "BEGIN:VEVENT\r\n"
             . "UID:" . ics_escape($icsUID) . "\r\n"
             . "DTSTAMP:{$icsStamp}\r\n"
             . "DTSTART:{$icsStart}\r\n"
             . "DTEND:{$icsEnd}\r\n"
             . "SUMMARY:"     . ics_escape($sumLine)  . "\r\n"
             . "DESCRIPTION:" . ics_escape($descLine) . "\r\n"
             . "LOCATION:"    . ics_escape($locLine)  . "\r\n"
             . "STATUS:CONFIRMED\r\n"
             . "END:VEVENT\r\n"
             . "END:VCALENDAR\r\n";
    } catch (Throwable $e) {
        $ics = null;
    }
}

$subject = sanitize_header(
    $action === 'accept'
        ? '✓ Booking Confirmed — ' . $contractorName . ' — ' . (string)($bookingDate['displayDate'] ?? '')
        : 'Booking Update from ' . $contractorName,
    180
);
$html = _buildCustomerEmail(
    $action === 'accept' ? 'confirmed' : 'declined',
    $customer, $bookingDate, $estimate, $areas,
    $contractorName, $contractorPhone,
    $ics,
    (string)($booking['preview_file'] ?? '')
);

if ($customerEmail) {
    $fromName = sanitize_header(MAIL_FROM_NAME, 60);

    $sentToCustomer = false;
    if (MAIL_TRANSPORT === 'smtp' && SMTP_HOST !== '' && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->Port       = SMTP_PORT;
            $mail->SMTPAuth   = SMTP_USER !== '';
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->setFrom(MAIL_FROM_ADDR, $contractorName . ' via Prezure');
            $mail->addReplyTo(MAIL_REPLY_ADDR);
            $mail->addAddress($customerEmail, (string)($customer['name'] ?? ''));
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->CharSet = 'UTF-8';
            if ($ics) $mail->addStringAttachment($ics, 'booking.ics', 'base64', 'text/calendar');
            $sentToCustomer = $mail->send();
        } catch (Throwable $e) {
            audit_log('respond_smtp_fail', ['token' => substr($token, 0, 8)]);
        }
    } else {
        // mail() path. Headers are constants only.
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= 'From: ' . sanitize_header($contractorName . ' via Prezure', 60)
                  . ' <' . MAIL_FROM_ADDR . ">\r\n";
        $headers .= 'Reply-To: <' . MAIL_REPLY_ADDR . ">\r\n";

        if ($ics) {
            $boundary = md5(uniqid('', true));
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $body  = "--{$boundary}\r\n"
                   . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                   . "{$html}\r\n"
                   . "--{$boundary}\r\n"
                   . "Content-Type: text/calendar; method=REQUEST; name=\"booking.ics\"\r\n"
                   . "Content-Disposition: attachment; filename=\"booking.ics\"\r\n\r\n"
                   . "{$ics}\r\n"
                   . "--{$boundary}--";
        } else {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body = $html;
        }
        $sentToCustomer = @mail($customerEmail, $subject, $body, $headers);
    }
    if (!$sentToCustomer) {
        audit_log('respond_email_fail', ['token' => substr($token, 0, 8)]);
    }
}

// ── Confirmation page to contractor ──────────────────────────────────────────
$statusWord = $action === 'accept' ? 'Confirmed' : 'Declined';
$customerName = htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$dispDate = htmlspecialchars((string)($bookingDate['displayDate'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$dispTime = htmlspecialchars((string)($bookingDate['displayTime'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

$msg = $action === 'accept'
    ? "Booking confirmed for <strong>{$customerName}</strong> on <strong>{$dispDate} at {$dispTime}</strong>."
      . ($customerEmail ? ' A confirmation email with a calendar invite has been sent to the customer.' : ' No customer email was on file — follow up directly.')
    : 'Booking declined. ' . ($customerEmail ? 'The customer has been notified.' : 'No customer email on file.');

_page("Booking {$statusWord}", $msg, true);
exit;


// =============================================================================
// HELPERS
// =============================================================================

/**
 * Render the GET-stage confirmation page. Has the only POST form to mutate.
 */
function _confirm_page(string $token, string $action, array $booking): void
{
    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $customer    = is_array($booking['customer'] ?? null) ? $booking['customer'] : [];
    $bookingDate = is_array($booking['booking']  ?? null) ? $booking['booking']  : [];
    $estimate    = is_array($booking['estimate'] ?? null) ? $booking['estimate'] : [];

    $isAccept = ($action === 'accept');
    $color    = $isAccept ? '#00e5a0' : '#ff6b6b';
    $verb     = $isAccept ? 'CONFIRM ACCEPT' : 'CONFIRM DECLINE';
    $hint     = $isAccept
        ? 'You are about to accept this booking. The customer will receive a confirmation email and calendar invite.'
        : 'You are about to decline this booking. The customer will be notified.';

    $totalFmt = '$' . number_format((float)($estimate['total'] ?? 0), 2);
    $name     = $esc((string)($customer['name']    ?? ''));
    $address  = $esc((string)($customer['address'] ?? ''));
    $dispDate = $esc((string)($bookingDate['displayDate'] ?? ''));
    $dispTime = $esc((string)($bookingDate['displayTime'] ?? ''));
    $tokenEsc = $esc($token);
    $actionEsc= $esc($action);
    $formUrl  = $esc(app_url('1/respond.php'));

    echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Prezure — Confirm Response</title>
<style>
body{margin:0;background:#0e1324;font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;}
.box{background:#161f39;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:32px;max-width:520px;width:100%;}
h1{color:{$color};font-size:24px;margin:0 0 12px;letter-spacing:1px;}
p{color:#fff;font-size:14px;line-height:1.6;margin:0 0 14px;}
table{width:100%;border-collapse:collapse;margin:14px 0;background:#0e1324;border-radius:8px;overflow:hidden;}
td{padding:8px 14px;color:#fff;font-size:13px;}
td.k{color:rgba(255,255,255,0.5);width:38%;}
.btn{display:inline-block;background:{$color};color:#0e1324;padding:14px 28px;border-radius:8px;font-weight:700;text-decoration:none;letter-spacing:0.5px;font-size:14px;border:none;cursor:pointer;font-family:inherit;}
.cancel{background:#28314e;color:#fff;margin-left:8px;}
.sub{color:rgba(255,255,255,0.4);font-size:12px;margin-top:18px;}
</style></head><body>
<div class="box">
  <h1>{$verb}</h1>
  <p>{$hint}</p>
  <table>
    <tr><td class="k">Customer</td><td>{$name}</td></tr>
    <tr><td class="k">Address</td><td>{$address}</td></tr>
    <tr><td class="k">Date</td><td>{$dispDate}</td></tr>
    <tr><td class="k">Time</td><td>{$dispTime}</td></tr>
    <tr><td class="k">Total</td><td><strong>{$totalFmt}</strong></td></tr>
  </table>
  <form method="POST" action="{$formUrl}" autocomplete="off">
    <input type="hidden" name="token"  value="{$tokenEsc}">
    <input type="hidden" name="action" value="{$actionEsc}">
    <input type="hidden" name="_confirm" value="1">
    <button type="submit" class="btn">{$verb}</button>
    <a href="javascript:window.close()" class="btn cancel">Cancel</a>
  </form>
  <p class="sub">Powered by Prezure</p>
</div>
</body></html>
HTML;
}

function _buildCustomerEmail(string $status, array $customer, array $booking,
                              array $estimate, array $areas, string $contName,
                              string $contPhone, ?string $ics = null,
                              string $previewFile = ''): string
{
    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $isAccepted  = $status === 'confirmed';
    $accentColor = $isAccepted ? '#00e5a0' : '#ff6b6b';
    $statusLabel = $isAccepted ? '&#10003; CONFIRMED' : '&#10005; NOT AVAILABLE';
    $totalFmt    = '$' . number_format((float)($estimate['total'] ?? 0), 2);

    $areaRows = '';
    foreach ($areas as $a) {
        $label = $esc((string)($a['label'] ?? ''));
        $sqft  = number_format((float)($a['sqft'] ?? 0), 0);
        $cost  = '$' . number_format(((float)($a['sqft'] ?? 0)) * ((float)($a['rate'] ?? 0)), 2);
        $areaRows .= "<tr>
            <td style='padding:8px 14px;border-bottom:1px solid #28314e;color:#fff;'>{$label}</td>
            <td style='padding:8px 14px;border-bottom:1px solid #28314e;color:#fff;text-align:right;'>{$sqft} sq ft</td>
            <td style='padding:8px 14px;border-bottom:1px solid #28314e;color:#f6dc4b;text-align:right;'>{$cost}</td>
          </tr>";
    }

    $previewBlock = '';
    if ($previewFile !== '') {
        $previewUrl = $esc(rtrim(APP_EXT_URL, '/') . '/' . ltrim($previewFile, '/'));
        $previewBlock = "
    <div style='background:#0e1324;border-radius:8px;overflow:hidden;margin-bottom:10px;padding:12px 14px;'>
      <div style='color:#f6dc4b;font-size:10px;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:8px;'>Service Area</div>
      <img src='{$previewUrl}' style='max-width:100%;border-radius:8px;border:1px solid rgba(255,255,255,0.08);display:block;' alt='Service area map' />
    </div>";
    }

    $calNote = $isAccepted && $ics
        ? "<p style='color:rgba(255,255,255,0.6);font-size:12px;margin-top:14px;'>A calendar invite (.ics) is attached &mdash; open it to add this appointment to your calendar.</p>"
        : '';

    $bodyText = $isAccepted
        ? 'Your service appointment has been confirmed. ' . $esc($contName) . ' will arrive at the address below.'
        : $esc($contName) . ' is not available for the requested time. Please contact them directly to reschedule.';

    $name        = $esc((string)($customer['name']    ?? ''));
    $address     = $esc((string)($customer['address'] ?? ''));
    $dispDate    = $esc((string)($booking['displayDate'] ?? ''));
    $dispTime    = $esc((string)($booking['displayTime'] ?? ''));
    $contNameEsc = $esc($contName);
    $contPhoneEsc= $esc($contPhone);

    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0e1324;font-family:Arial,sans-serif;">
<div style="max-width:580px;margin:0 auto;background:#161f39;border-radius:12px;overflow:hidden;">
  <div style="background:#0e1324;padding:24px 28px;border-bottom:1px solid rgba(255,255,255,0.08);">
    <div style="color:{$accentColor};font-size:22px;font-weight:700;letter-spacing:2px;">{$statusLabel}</div>
    <div style="color:#f6dc4b;font-size:13px;margin-top:4px;">from {$contNameEsc}</div>
  </div>
  <div style="padding:24px 28px;">
    <p style="color:#fff;font-size:14px;margin:0 0 16px;">Hi <strong style='color:#f6dc4b;'>{$name}</strong>, {$bodyText}</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0e1324;border-radius:8px;margin-bottom:16px;">
      <tr><td style="padding:12px 14px 4px;color:#f6dc4b;font-size:10px;letter-spacing:2px;text-transform:uppercase;font-weight:700;" colspan="2">Appointment</td></tr>
      <tr><td style="padding:5px 14px;color:rgba(255,255,255,0.5);font-size:12px;width:38%;">Date</td><td style="padding:5px 14px;color:#fff;font-size:14px;font-weight:700;">{$dispDate}</td></tr>
      <tr><td style="padding:5px 14px 12px;color:rgba(255,255,255,0.5);font-size:12px;">Time</td><td style="padding:5px 14px 12px;color:#fff;font-size:14px;font-weight:700;">{$dispTime}</td></tr>
      <tr><td style="padding:5px 14px 12px;color:rgba(255,255,255,0.5);font-size:12px;">Address</td><td style="padding:5px 14px 12px;color:#fff;font-size:13px;">{$address}</td></tr>
      <tr><td style="padding:5px 14px 12px;color:rgba(255,255,255,0.5);font-size:12px;">Contact</td><td style="padding:5px 14px 12px;color:#fff;font-size:13px;">{$contPhoneEsc}</td></tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0e1324;border-radius:8px;margin-bottom:10px;">
      <tr><td colspan="3" style="padding:12px 14px 6px;color:#f6dc4b;font-size:10px;letter-spacing:2px;text-transform:uppercase;font-weight:700;">Estimate</td></tr>
      {$areaRows}
      <tr style="background:#28314e;"><td style="padding:10px 14px;color:#fff;font-weight:700;" colspan="2">TOTAL</td><td style="padding:10px 14px;color:#f6dc4b;font-weight:700;font-size:18px;text-align:right;">{$totalFmt}</td></tr>
    </table>
    {$previewBlock}
    {$calNote}
  </div>
  <div style="background:#0e1324;padding:12px 28px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
    <p style="color:rgba(255,255,255,0.3);font-size:11px;margin:0;">Powered by <strong style="color:#00e5a0;">Prezure</strong></p>
  </div>
</div>
</body></html>
HTML;
}

function _page(string $title, string $body, bool $ok): void
{
    $color = $ok ? '#00e5a0' : '#ff6b6b';
    $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // $body is built ONLY from server-side strings + already-escaped values.
    echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Prezure — {$titleEsc}</title>
<meta name="robots" content="noindex,nofollow">
<style>body{margin:0;background:#0e1324;font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;}
.box{background:#161f39;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:40px;max-width:480px;width:90%;text-align:center;}
h1{color:{$color};font-size:28px;margin:0 0 12px;letter-spacing:1px;}
p{color:#fff;font-size:14px;line-height:1.7;margin:0;}
.sub{color:rgba(255,255,255,0.4);font-size:12px;margin-top:12px;}</style>
</head><body>
<div class="box">
  <h1>{$titleEsc}</h1>
  <p>{$body}</p>
  <p class="sub">Powered by Prezure</p>
</div>
</body></html>
HTML;
}
