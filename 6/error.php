<?php
/*
 * Error page for the codes configured in the root .htaccess.
 * Those ErrorDocument paths are fixed to the mount path (see README);
 * links inside this page use app_url().
 */

require_once __DIR__ . '/../includes/config.php';

// ── Detect error code (priority: explicit ?code= → REDIRECT_STATUS → response code) ──
$code = 404;
if (isset($_GET['code']) && ctype_digit((string)$_GET['code'])) {
    $code = (int) $_GET['code'];
} elseif (!empty($_SERVER['REDIRECT_STATUS']) && ctype_digit((string) $_SERVER['REDIRECT_STATUS'])) {
    $code = (int) $_SERVER['REDIRECT_STATUS'];
} elseif (function_exists('http_response_code') && http_response_code() >= 400) {
    $code = http_response_code();
}
if ($code < 400 || $code > 599) {
    $code = 404;
}
http_response_code($code);

$messages = [
    400 => ['title' => 'Bad Request',     'message' => "We couldn't understand that request."],
    401 => ['title' => 'Sign In Required', 'message' => "Please sign in to continue."],
    403 => ['title' => 'Unauthorized',    'message' => "You don't have permission to access this page."],
    404 => ['title' => 'Page Not Found',  'message' => "This page doesn't exist. It may have been moved or the link is incorrect."],
    429 => ['title' => 'Slow Down',       'message' => "Too many requests in a short time. Please wait a moment and try again."],
    500 => ['title' => 'Server Error',    'message' => "Something went wrong on our end. Please try again in a moment."],
];
$fallback = ['title' => 'Something Went Wrong', 'message' => "Something went wrong. Let's get you back on track."];
$err = $messages[$code] ?? $fallback;

$homeUrl   = app_url('2/index.php');
$signinUrl = app_url('3/signin.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars((string) $code) ?> — Prezure</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/app.css'), ENT_QUOTES) ?>">
  <style>
    /* Page-specific styles. Shared reset + variables come from assets/app.css. */
    body {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Error layout ── */
    .error-wrap {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 80px 24px 100px;
    }

    .logo {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.75rem;
      letter-spacing: 2px;
      color: var(--accent);
      margin-bottom: 48px;
    }

    .error-code {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(6rem, 20vw, 12rem);
      line-height: 0.9;
      letter-spacing: 2px;
      color: var(--accent);
      text-shadow: 0 0 60px rgba(246, 220, 75, 0.18);
      margin-bottom: 8px;
    }

    .error-title {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(1.75rem, 4vw, 2.5rem);
      letter-spacing: 1px;
      color: var(--white);
      margin: 16px 0 12px;
    }

    .error-message {
      font-family: 'Outfit', sans-serif;
      font-weight: 300;
      font-size: 1.125rem;
      color: var(--text-muted);
      max-width: 520px;
      margin: 0 auto;
    }

    .error-subtext {
      font-size: 0.95rem;
      color: var(--text-muted);
      opacity: 0.7;
      margin-top: 12px;
    }

    /* ── CTA buttons ── */
    .cta-row {
      display: flex;
      gap: 16px;
      margin-top: 40px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .btn-primary {
      display: inline-block;
      background: var(--accent);
      color: var(--bg-deep);
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 1.125rem;
      padding: 16px 40px;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(246, 220, 75, 0.3);
    }

    .btn-secondary {
      display: inline-block;
      background: transparent;
      color: var(--white);
      font-family: 'Outfit', sans-serif;
      font-weight: 600;
      font-size: 1.125rem;
      padding: 16px 40px;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.15);
      cursor: pointer;
      transition: border-color 0.2s, color 0.2s, transform 0.2s;
    }

    .btn-secondary:hover {
      border-color: var(--accent);
      color: var(--accent);
      transform: translateY(-2px);
    }

    /* ── Footer (matches 2/index.php) ── */
    .site-footer {
      padding: 32px 0;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .footer-inner {
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .site-footer p {
      font-size: 0.85rem;
      color: var(--text-muted);
      font-weight: 300;
    }

    .footer-links {
      display: flex;
      gap: 24px;
    }

    .footer-links a {
      font-size: 0.85rem;
      color: var(--text-muted);
      font-weight: 300;
      transition: color 0.2s;
    }

    .footer-links a:hover {
      color: var(--white);
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      .error-wrap {
        padding: 60px 20px 80px;
      }

      .logo {
        margin-bottom: 32px;
      }

      .error-message {
        font-size: 1rem;
        padding: 0 8px;
      }

      .cta-row {
        flex-direction: column;
        width: 100%;
        align-items: center;
      }

      .btn-primary,
      .btn-secondary {
        width: 100%;
        max-width: 280px;
        text-align: center;
      }

      .footer-inner {
        flex-direction: column;
        gap: 12px;
        text-align: center;
      }
    }
  </style>
</head>
<body>
  <main class="error-wrap">
    <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>" class="logo">PREZURE</a>
    <h1 class="error-code"><?= htmlspecialchars((string) $code) ?></h1>
    <h2 class="error-title"><?= htmlspecialchars($err['title']) ?></h2>
    <p class="error-message"><?= htmlspecialchars($err['message']) ?></p>
    <p class="error-subtext">This isn't your fault — we'll get you back on track.</p>
    <div class="cta-row">
      <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>" class="btn-primary">Go Home</a>
      <a href="<?= htmlspecialchars($signinUrl, ENT_QUOTES) ?>" class="btn-secondary">Sign In</a>
    </div>
  </main>

  <?php include '../2/footer.php'; ?>
</body>
</html>
