<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
prezure_session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terms of Service — Prezure</title>
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

    /* Centered content column */
    .privacy-layout {
      flex: 1;
      width: 100%;
      max-width: 900px;
      margin: 0 auto;
      padding: 60px 24px 80px;
    }

    /* Floating hamburger toggle */
    .toc-toggle {
      position: fixed;
      top: 24px;
      left: 24px;
      width: 46px;
      height: 46px;
      background: var(--bg-panel);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 10px;
      cursor: pointer;
      z-index: 100;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 5px;
      padding: 0;
      transition: background 0.2s, border-color 0.2s;
    }

    .toc-toggle:hover {
      background: var(--bg-card);
      border-color: rgba(246, 220, 75, 0.3);
    }

    .toc-toggle-bar {
      display: block;
      width: 20px;
      height: 2px;
      background: var(--accent);
      border-radius: 2px;
      transition: transform 0.3s ease, opacity 0.2s ease;
      transform-origin: center;
    }

    .toc-toggle.open .toc-toggle-bar:nth-child(1) {
      transform: translateY(7px) rotate(45deg);
    }

    .toc-toggle.open .toc-toggle-bar:nth-child(2) {
      opacity: 0;
    }

    .toc-toggle.open .toc-toggle-bar:nth-child(3) {
      transform: translateY(-7px) rotate(-45deg);
    }

    .tos-header {
      text-align: center;
      margin-bottom: 48px;
    }

    .logo {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.75rem;
      letter-spacing: 2px;
      color: var(--accent);
      margin-bottom: 16px;
    }

    .logo a:hover { opacity: 0.8; transition: opacity 0.2s; }

    .tos-header h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 2.5rem;
      letter-spacing: 1px;
      margin-bottom: 12px;
    }

    .tos-updated {
      font-size: 0.9rem;
      color: var(--text-muted);
      font-weight: 300;
    }

    .tos-section {
      background: var(--bg-panel);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 12px;
      padding: 32px;
      margin-bottom: 24px;
      scroll-margin-top: 24px;
    }

    .tos-section h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.5rem;
      letter-spacing: 1px;
      color: var(--accent);
      margin-bottom: 16px;
    }

    .tos-section p {
      color: var(--text-muted);
      font-weight: 300;
      font-size: 0.95rem;
      margin-bottom: 12px;
      line-height: 1.7;
    }

    .tos-section p:last-child { margin-bottom: 0; }

    /* Collapsible Table of Contents */
    .privacy-toc {
      position: fixed;
      top: 84px;
      left: 24px;
      width: 220px;
      max-height: calc(100vh - 110px);
      overflow-y: auto;
      background: var(--bg-panel);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      padding: 24px;
      z-index: 90;
      transform: translateX(calc(-100% - 32px));
      opacity: 0;
      pointer-events: none;
      transition: transform 0.35s ease, opacity 0.25s ease;
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
    }

    .privacy-toc.open {
      transform: translateX(0);
      opacity: 1;
      pointer-events: auto;
    }

    .privacy-toc h3 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.1rem;
      letter-spacing: 1px;
      color: var(--accent);
      margin-bottom: 12px;
    }

    .privacy-toc ol {
      list-style: none;
      counter-reset: toc;
    }

    .privacy-toc ol li {
      counter-increment: toc;
      padding: 6px 0;
      font-size: 0.85rem;
      line-height: 1.4;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .privacy-toc ol li:last-child { border-bottom: none; }

    .privacy-toc ol li a {
      color: var(--text-muted);
      font-weight: 300;
      transition: color 0.2s;
      display: block;
    }

    .privacy-toc ol li a::before {
      content: counter(toc) ". ";
      color: var(--accent);
      font-weight: 500;
    }

    .privacy-toc ol li a:hover { color: var(--accent); }

    .back-to-top {
      text-align: center;
      margin-top: 16px;
      margin-bottom: 40px;
    }

    .back-to-top a {
      color: var(--accent);
      font-weight: 500;
      font-size: 0.9rem;
      transition: opacity 0.2s;
    }

    .back-to-top a:hover { opacity: 0.7; }

    /* Footer */
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

    .footer-links a:hover { color: var(--white); }

    /* Hide toggle + menu on narrow viewports */
    @media (max-width: 1399px) {
      .toc-toggle,
      .privacy-toc { display: none; }
    }

    /* Tablet */
    @media (max-width: 768px) {
      .privacy-layout { padding: 40px 20px 60px; }
      .tos-header h1 { font-size: 2rem; }
      .tos-section { padding: 24px 20px; }
      .footer-inner {
        flex-direction: column;
        gap: 16px;
        text-align: center;
      }
    }

    /* Mobile */
    @media (max-width: 480px) {
      .privacy-layout { padding: 32px 16px 48px; }
      .tos-header h1 { font-size: 1.75rem; }
      .tos-section h2 { font-size: 1.3rem; }
      .tos-section { padding: 20px 16px; border-radius: 10px; }
    }
  </style>
</head>
<body id="top">

<button type="button" class="toc-toggle" id="toc-toggle" aria-label="Toggle table of contents" aria-expanded="false" aria-controls="tos-toc">
  <span class="toc-toggle-bar"></span>
  <span class="toc-toggle-bar"></span>
  <span class="toc-toggle-bar"></span>
</button>

<aside class="privacy-toc" id="tos-toc" aria-label="Table of contents">
  <h3>Contents</h3>
  <ol>
    <li><a href="#section-1">Acceptance of Terms</a></li>
    <li><a href="#section-2">The Service</a></li>
    <li><a href="#section-3">Subscription & Billing</a></li>
    <li><a href="#section-4">Cancellation & Suspension</a></li>
    <li><a href="#section-5">Account Rules</a></li>
    <li><a href="#section-6">Termination</a></li>
    <li><a href="#section-7">Data & Ownership</a></li>
    <li><a href="#section-8">Customer Link Misuse</a></li>
    <li><a href="#section-9">Liability</a></li>
    <li><a href="#section-10">Changes to Terms</a></li>
    <li><a href="#section-11">Governing Law</a></li>
  </ol>
</aside>

<main class="privacy-layout">
  <div class="tos-header">
    <div class="logo"><a href="../2/index.php">PREZURE</a></div>
    <h1>Terms of Service</h1>
    <p class="tos-updated">Last Updated: [INSERT DATE]</p>
  </div>

  <!-- 1. Acceptance of Terms -->
  <div class="tos-section" id="section-1">
    <h2>1. Acceptance of Terms</h2>
    <p>By creating a Prezure account and checking the confirmation box at signup, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.</p>
    <p>These terms must be accepted before your account can be created. Continued use of the platform constitutes ongoing agreement with these terms.</p>
  </div>

  <!-- 2. The Service -->
  <div class="tos-section" id="section-2">
    <h2>2. The Service</h2>
    <p>Prezure provides a calculator tool designed for pressure washing estimation. The tool is intended to assist contractors in generating estimates for their customers.</p>
    <p>Prezure is not responsible for the outcome of any estimate between a contractor and their customer. The contractor is fully responsible for their customer's experience, including the accuracy of any estimates provided.</p>
    <p>In the event of downtime, Prezure will make best efforts to maintain service availability via a failover mirror site. However, no uptime is guaranteed, and no liability is assumed for service interruptions of any kind.</p>
  </div>

  <!-- 3. Subscription & Billing -->
  <div class="tos-section" id="section-3">
    <h2>3. Subscription & Billing</h2>
    <p>The subscription fee is $15 for the first month and $49 per month thereafter. The monthly price is permanently locked for existing customers and will never increase for the duration of the account.</p>
    <p>If a payment fails, a 3-day grace period is provided before the account is suspended. Refunds are available within 7 days of initial signup only, and no exceptions are made after that period.</p>
  </div>

  <!-- 4. Cancellation & Suspension -->
  <div class="tos-section" id="section-4">
    <h2>4. Cancellation & Suspension</h2>
    <p>Cancellation takes effect at the end of the current billing period, and access to the platform continues until that date. Suspended accounts due to non-payment retain all data for up to 3 months.</p>
    <p>Full account and data restoration, including the original calculator key, is available upon payment within that 3-month window. After 3 months of non-payment, the account and all associated data are permanently deleted with no possibility of recovery.</p>
  </div>

  <!-- 5. Account Rules -->
  <div class="tos-section" id="section-5">
    <h2>5. Account Rules</h2>
    <p>One account is permitted per registered company, and multiple team members of the registered company may access the account. Use of the account by members of any company other than the one registered to the account is grounds for immediate termination and permanent data deletion.</p>
    <p>Sharing or reselling the calculator key to any other individual or business is strictly prohibited and is grounds for immediate termination with no refund.</p>
  </div>

  <!-- 6. Termination -->
  <div class="tos-section" id="section-6">
    <h2>6. Termination</h2>
    <p>Prezure reserves the right to terminate any account at any time for any reason.</p>
    <p>Termination for key sharing or account misuse is immediate, with no refund issued.</p>
    <p>Contractors may dispute a termination by contacting Prezure to request a review. All decisions following review are final.</p>
  </div>

  <!-- 7. Data & Ownership -->
  <div class="tos-section" id="section-7">
    <h2>7. Data & Ownership</h2>
    <p>All data generated through the Prezure platform is owned by Prezure.</p>
    <p>Contractors may view and manage their estimates within their dashboard during an active subscription.</p>
    <p>Prezure may use anonymized and aggregated data internally for product improvement purposes. Data is never sold or shared with third parties.</p>
  </div>

  <!-- 8. Customer Link Misuse -->
  <div class="tos-section" id="section-8">
    <h2>8. Customer Link Misuse</h2>
    <p>The contractor is fully responsible for any misuse of their calculator link by their customers.</p>
    <p>Spam submissions, bot traffic, or any abuse originating from a contractor's link is the contractor's liability.</p>
  </div>

  <!-- 9. Liability -->
  <div class="tos-section" id="section-9">
    <h2>9. Liability</h2>
    <p>Prezure is not liable for any lost business, revenue, or damages resulting from downtime, estimate disputes, or service interruptions of any kind.</p>
    <p>The contractor assumes full responsibility for all estimates generated through their account and all communications with their customers.</p>
  </div>

  <!-- 10. Changes to Terms -->
  <div class="tos-section" id="section-10">
    <h2>10. Changes to Terms</h2>
    <p>Prezure may update these Terms of Service at any time.</p>
    <p>Contractors will be notified by email of any changes prior to them taking effect. Continued use of the service following notification constitutes acceptance of the updated terms.</p>
  </div>

  <!-- 11. Governing Law -->
  <div class="tos-section" id="section-11">
    <h2>11. Governing Law</h2>
    <p>These Terms of Service are governed by the laws of the state of Florida.</p>
    <p>Any disputes arising from these terms shall be handled under Florida jurisdiction.</p>
  </div>

  <div class="back-to-top">
    <a href="#top">Back to Top</a>
  </div>
</main>

<?php include '../2/footer.php'; ?>

<script>
  (function () {
    var toggle = document.getElementById('toc-toggle');
    var toc = document.getElementById('tos-toc');
    if (!toggle || !toc) return;

    function setOpen(open) {
      toc.classList.toggle('open', open);
      toggle.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
      setOpen(!toc.classList.contains('open'));
    });

    toc.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { setOpen(false); });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && toc.classList.contains('open')) setOpen(false);
    });
  })();
</script>

</body>
</html>
