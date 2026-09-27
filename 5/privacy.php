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
  <title>Privacy Policy — Prezure</title>
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

    /* Centered content column — menu is fixed-positioned and never pushes this */
    .privacy-layout {
      flex: 1;
      width: 100%;
      max-width: 900px;
      margin: 0 auto;
      padding: 60px 24px 80px;
    }

    /* Floating hamburger toggle — top-left, always visible on desktop */
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

    .tos-section ul {
      list-style: none;
      padding: 0;
    }

    .tos-section ul li {
      color: var(--text-muted);
      font-weight: 300;
      font-size: 0.95rem;
      padding: 6px 0 6px 20px;
      position: relative;
      line-height: 1.7;
    }

    .tos-section ul li::before {
      content: '';
      position: absolute;
      left: 0;
      top: 14px;
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--accent);
    }

    .placeholder-note {
      display: block;
      margin-top: 12px;
      font-size: 0.8rem;
      font-weight: 400;
      color: var(--accent);
      opacity: 0.75;
      font-style: italic;
    }

    /* Collapsible Table of Contents — fixed on the left, overlays empty margin */
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

    /* Hide toggle + menu when viewport is too narrow to fit the menu
       alongside the 900px centered content without overlapping it */
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

<button type="button" class="toc-toggle" id="toc-toggle" aria-label="Toggle table of contents" aria-expanded="false" aria-controls="privacy-toc">
  <span class="toc-toggle-bar"></span>
  <span class="toc-toggle-bar"></span>
  <span class="toc-toggle-bar"></span>
</button>

<aside class="privacy-toc" id="privacy-toc" aria-label="Table of contents">
  <h3>Contents</h3>
  <ol>
    <li><a href="#section-1">Introduction</a></li>
    <li><a href="#section-2">Data Collected — Contractors</a></li>
    <li><a href="#section-3">Data Collected — Customers</a></li>
    <li><a href="#section-4">How Data Is Used</a></li>
    <li><a href="#section-5">Payment Data</a></li>
    <li><a href="#section-6">Cookies</a></li>
    <li><a href="#section-7">Third Party Services</a></li>
    <li><a href="#section-8">Data Storage</a></li>
    <li><a href="#section-9">Data Breach</a></li>
    <li><a href="#section-10">Data Retention &amp; Deletion</a></li>
    <li><a href="#section-11">Data Correction</a></li>
    <li><a href="#section-12">Data Sharing</a></li>
    <li><a href="#section-13">Changes to This Policy</a></li>
    <li><a href="#section-14">Contact</a></li>
  </ol>
</aside>

<main class="privacy-layout">
  <div class="tos-header">
    <div class="logo"><a href="../2/index.php">PREZURE</a></div>
    <h1>Privacy Policy</h1>
    <p class="tos-updated">Last Updated: [INSERT DATE]</p>
  </div>

    <!-- 1. Introduction -->
    <div class="tos-section" id="section-1">
      <h2>1. Introduction</h2>
      <p>This Privacy Policy explains what data Prezure collects, how it is used, how it is stored, and the rights users have over it.</p>
      <p>It covers two groups: contractors who create Prezure accounts, and customers who submit estimates through a contractor's calculator link.</p>
      <p>This policy is governed by the laws of the state of Florida.</p>
    </div>

    <!-- 2. Data Collected — Contractors -->
    <div class="tos-section" id="section-2">
      <h2>2. Data Collected — Contractors</h2>
      <p>When a contractor creates and uses a Prezure account, the following data is collected and stored: name, company name, email, phone number, address, pricing configuration including standard, base, chemical, and heavy rates, available working days and hours, an encrypted password, and a unique calculator key.</p>
      <p>Prezure also stores estimate logs including customer submitted addresses, traced coordinates, square footage, intensity levels, pricing, and preferred dates. A timestamp of the Terms of Service agreement is recorded, and session data is maintained via cookies for functionality only.</p>
    </div>

    <!-- 3. Data Collected — Customers -->
    <div class="tos-section" id="section-3">
      <h2>3. Data Collected — Customers</h2>
      <p>When a customer submits an estimate through a contractor's calculator link, the property address, traced area coordinates, square footage, intensity selections, and preferred date are collected.</p>
      <p>No account is created, no password is collected, and no personal identity information beyond the property address is stored.</p>
    </div>

    <!-- 4. How Data Is Used -->
    <div class="tos-section" id="section-4">
      <h2>4. How Data Is Used</h2>
      <p>Data is used to operate and deliver the Prezure service, to calculate and deliver estimates to contractors, and to manage subscriptions and billing.</p>
      <p>Prezure may also use data to send service notifications, payment alerts, and policy updates by email. Anonymized and aggregated data may be used internally to improve the product, but never in identifiable form.</p>
    </div>

    <!-- 5. Payment Data -->
    <!-- PAYMENT PROCESSOR — insert final provider name and link to their privacy policy here -->
    <div class="tos-section" id="section-5">
      <h2>5. Payment Data</h2>
      <p>Prezure does not store any payment card information directly.</p>
      <p>All payment processing is handled by [INSERT PAYMENT PROCESSOR — Stripe or Square]. Financial data is governed entirely by the processor's own privacy policy.</p>
      <span class="placeholder-note">[PAYMENT PROCESSOR — insert final provider name and link to their privacy policy here]</span>
    </div>

    <!-- 6. Cookies -->
    <div class="tos-section" id="section-6">
      <h2>6. Cookies</h2>
      <p>Prezure uses cookies for session management and site functionality only.</p>
      <p>No tracking cookies, no advertising cookies, and no third party analytics cookies are used.</p>
      <p>Disabling cookies will prevent login functionality from working correctly.</p>
    </div>

    <!-- 7. Third Party Services -->
    <div class="tos-section" id="section-7">
      <h2>7. Third Party Services</h2>
      <p>Google Maps API is used to render map images and coordinate overlays, and address and coordinate data is passed to Google solely for this rendering purpose. [INSERT PAYMENT PROCESSOR] handles all billing and is subject to their own privacy policy.</p>
      <p>No other third party services receive contractor or customer data.</p>
    </div>

    <!-- 8. Data Storage -->
    <div class="tos-section" id="section-8">
      <h2>8. Data Storage</h2>
      <p>All data is currently stored with Amazon Web Services (AWS) in the United States.</p>
      <p>Prezure intends to eventually transition to locally hosted infrastructure — this policy will be updated to reflect that when the transition occurs.</p>
      <p>Reasonable technical and administrative measures are in place to protect stored data from unauthorized access.</p>
    </div>

    <!-- 9. Data Breach -->
    <div class="tos-section" id="section-9">
      <h2>9. Data Breach</h2>
      <p>In the event of a data breach affecting contractor or customer data, Prezure will notify all affected contractors by email as soon as reasonably possible.</p>
      <p>Notification will describe what data was affected and what steps Prezure is taking to address it.</p>
    </div>

    <!-- 10. Data Retention & Deletion -->
    <div class="tos-section" id="section-10">
      <h2>10. Data Retention &amp; Deletion</h2>
      <p>Contractor data is retained for the duration of the active subscription. Suspended accounts retain all data for up to 3 months before permanent deletion. Customer submitted estimate data is retained as part of the contractor's account and is deleted alongside it.</p>
      <p>Data is not deleted upon individual request while an account is active. Contractors wishing to remove their data must cancel their subscription and allow the account to expire.</p>
    </div>

    <!-- 11. Data Correction -->
    <div class="tos-section" id="section-11">
      <h2>11. Data Correction</h2>
      <p>Contractors may edit their own account information directly through the dashboard at any time including name, email, phone, address, and pricing.</p>
      <p>For data that cannot be self-edited — such as billing records, system logs, or stored values that appear incorrect — contractors may contact Prezure directly with supporting documentation to request a correction.</p>
      <p>Prezure will review all correction requests and respond within a reasonable timeframe.</p>
    </div>

    <!-- 12. Data Sharing -->
    <div class="tos-section" id="section-12">
      <h2>12. Data Sharing</h2>
      <p>Prezure does not sell, rent, or share contractor or customer data with any third party under any circumstance.</p>
      <p>The sole exception is if Prezure is legally required to disclose data by a valid law enforcement request or court order — in which case only the minimum legally required data will be disclosed.</p>
    </div>

    <!-- 13. Changes to This Policy -->
    <div class="tos-section" id="section-13">
      <h2>13. Changes to This Policy</h2>
      <p>Prezure may update this Privacy Policy at any time.</p>
      <p>Contractors will be notified by email when material changes are made.</p>
      <p>Continued use of the service following notification constitutes acceptance of the updated policy.</p>
    </div>

    <!-- 14. Contact -->
    <div class="tos-section" id="section-14">
      <h2>14. Contact</h2>
      <p>For data correction requests or any privacy related questions, contractors may contact Prezure at [INSERT CONTACT EMAIL].</p>
    </div>

    <div class="back-to-top">
      <a href="#top">Back to Top</a>
    </div>
</main>

<?php include '../2/footer.php'; ?>

<script>
  (function () {
    var toggle = document.getElementById('toc-toggle');
    var toc = document.getElementById('privacy-toc');
    if (!toggle || !toc) return;

    function setOpen(open) {
      toc.classList.toggle('open', open);
      toggle.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
      setOpen(!toc.classList.contains('open'));
    });

    // Close when a TOC link is clicked so the target section is unobstructed
    toc.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { setOpen(false); });
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && toc.classList.contains('open')) setOpen(false);
    });
  })();
</script>

</body>
</html>
