<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sanitize.php';

prezure_session_start();
$userId = require_login('html');

// ── Read current user under shared lock ──────────────────────────────────────
$usersData = read_json_locked(PREZURE_USERS_FILE);
$currentUser = null;
foreach ((is_array($usersData['users'] ?? null) ? $usersData['users'] : []) as $u) {
    if ((int)$u['id'] === $userId) { $currentUser = $u; break; }
}
if (!$currentUser) {
    logout_user();
    header('Location: ' . app_url('3/signin.php'));
    exit;
}

// ── Compute calculator URL from APP_EXT_URL — no more hardcoded prezure1 ────
$calcUrl = app_ext_url('1/prezure.html?key=' . urlencode((string)$currentUser['calculator_key']));

// ── Profile completeness check (defensive against null arrays) ──────────────
$availDays  = is_array($currentUser['available_days']  ?? null) ? $currentUser['available_days']  : [];
$availHours = is_array($currentUser['available_hours'] ?? null) ? $currentUser['available_hours'] : [];
$incompleteFields = [];
if (empty($availDays))  $incompleteFields[] = 'available days';
if (empty($availHours)) $incompleteFields[] = 'available hours';
$profileIncomplete = count($incompleteFields) > 0;

// ── Safe user object for JS (no password / calculator_key) ──────────────────
$safeUser = safe_user($currentUser);

// CSRF token for fetch headers
$csrfToken = csrf_token();

// Maps API key sourced from environment ONLY — no source-code fallback.
$mapsKey = MAPS_API_KEY;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
  <meta name="app-base-url" content="<?php echo htmlspecialchars(APP_BASE_URL, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
  <title>Dashboard — Prezure</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/app.css'), ENT_QUOTES) ?>">
  <style>
    /* Page-specific styles. Shared reset + variables come from assets/app.css. */
    html { overflow-y: scroll; }
    body {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Header ──────────────────────────────────────────────── */
    .dash-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      max-width: 1120px;
      width: 100%;
      margin: 0 auto;
      padding: 24px 24px 0;
    }

    .logo {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.75rem;
      letter-spacing: 2px;
      color: var(--accent);
    }

    .dash-header-right {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .user-name {
      font-size: 0.9rem;
      color: var(--text-muted);
      font-weight: 400;
    }

    .btn-logout {
      background: none;
      border: 1px solid rgba(255,255,255,0.1);
      color: var(--text-muted);
      font-family: 'Outfit', sans-serif;
      font-size: 0.85rem;
      font-weight: 500;
      padding: 8px 20px;
      border-radius: 6px;
      cursor: pointer;
      transition: border-color 0.2s, color 0.2s;
    }

    .btn-logout:hover {
      border-color: rgba(255,255,255,0.25);
      color: var(--white);
    }

    /* ── Tab Navigation ──────────────────────────────────────── */
    .dash-tabs {
      max-width: 1120px;
      width: 100%;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      gap: 0;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      margin-top: 24px;
    }

    .dash-tab {
      background: none;
      border: none;
      color: var(--text-muted);
      font-family: 'Outfit', sans-serif;
      font-size: 0.95rem;
      font-weight: 500;
      padding: 14px 24px;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      transition: color 0.2s, border-color 0.2s;
    }

    .dash-tab:hover {
      color: var(--white);
    }

    .dash-tab.active {
      color: var(--white);
      border-bottom-color: var(--accent);
    }

    /* ── Tab Content ─────────────────────────────────────────── */
    .dash-content {
      max-width: 1120px;
      width: 100%;
      margin: 0 auto;
      padding: 32px 24px;
      flex: 1;
    }

    .tab-panel {
      display: none;
    }

    .tab-panel.active {
      display: block;
    }

    /* ── Buttons ─────────────────────────────────────────────── */
    .btn-primary {
      display: inline-block;
      background: var(--accent);
      color: var(--bg-deep);
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 1rem;
      padding: 12px 28px;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(246, 220, 75, 0.3);
    }

    .btn-primary:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .btn-secondary {
      display: inline-block;
      background: var(--bg-card);
      color: var(--white);
      font-family: 'Outfit', sans-serif;
      font-weight: 600;
      font-size: 0.9rem;
      padding: 10px 20px;
      border-radius: 8px;
      border: 1px solid rgba(255,255,255,0.08);
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-secondary:hover {
      background: rgba(255,255,255,0.1);
    }

    .btn-danger {
      display: inline-block;
      background: rgba(255,80,80,0.15);
      color: #ff6b6b;
      font-family: 'Outfit', sans-serif;
      font-weight: 600;
      font-size: 0.9rem;
      padding: 10px 20px;
      border-radius: 8px;
      border: 1px solid rgba(255,80,80,0.3);
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-danger:hover {
      background: rgba(255,80,80,0.25);
    }

    .btn-ghost {
      background: none;
      border: 1px solid rgba(255,255,255,0.1);
      color: var(--text-muted);
      font-family: 'Outfit', sans-serif;
      font-weight: 500;
      font-size: 0.9rem;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      transition: border-color 0.2s, color 0.2s;
    }

    .btn-ghost:hover {
      border-color: rgba(255,255,255,0.25);
      color: var(--white);
    }

    /* ── Cards ────────────────────────────────────────────────── */
    .card {
      background: var(--bg-panel);
      border-radius: 16px;
      padding: 28px;
      border: 1px solid rgba(255,255,255,0.06);
    }

    .card-accent {
      border-color: rgba(246,220,75,0.15);
    }

    .card h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.4rem;
      letter-spacing: 1px;
      margin-bottom: 16px;
    }

    /* ── URL Card (Home) ─────────────────────────────────────── */
    .url-display {
      display: flex;
      align-items: center;
      gap: 12px;
      background: var(--bg-input);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 8px;
      padding: 12px 16px;
    }

    .url-display code {
      flex: 1;
      font-family: 'Outfit', monospace;
      font-size: 0.9rem;
      color: var(--accent);
      word-break: break-all;
    }

    .btn-copy {
      background: var(--accent);
      color: var(--bg-deep);
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-family: 'Outfit', sans-serif;
      font-weight: 600;
      font-size: 0.85rem;
      cursor: pointer;
      white-space: nowrap;
      transition: transform 0.2s;
    }

    .btn-copy:hover {
      transform: translateY(-1px);
    }

    /* ── Nudge Card ──────────────────────────────────────────── */
    .nudge-card {
      background: rgba(246,220,75,0.06);
      border: 1px solid rgba(246,220,75,0.2);
      border-radius: 12px;
      padding: 20px 24px;
      margin-top: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .nudge-card p {
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .nudge-card .btn-primary {
      font-size: 0.85rem;
      padding: 10px 20px;
      white-space: nowrap;
    }

    /* ── Estimate Cards ──────────────────────────────────────── */
    .estimates-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 16px;
      margin-top: 16px;
    }

    .estimate-card {
      background: var(--bg-card);
      border-radius: 12px;
      padding: 20px;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      border: 1px solid rgba(255,255,255,0.04);
    }

    .estimate-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.3);
    }

    .estimate-address {
      font-weight: 600;
      font-size: 1rem;
      margin-bottom: 8px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .estimate-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .estimate-price {
      color: var(--accent);
      font-weight: 700;
    }

    /* ── Estimates Tab ───────────────────────────────────────── */
    .estimates-toolbar {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .estimates-toolbar .spacer {
      flex: 1;
    }

    .date-range-panel {
      display: none;
      background: var(--bg-panel);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      gap: 16px;
      align-items: flex-end;
      flex-wrap: wrap;
    }

    .date-range-panel.open {
      display: flex;
    }

    .date-range-panel .form-group {
      flex: 1;
      min-width: 150px;
    }

    .estimates-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .est-row {
      background: var(--bg-card);
      border-radius: 12px;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      gap: 16px;
      border: 1px solid rgba(255,255,255,0.04);
      transition: border-color 0.2s;
    }

    .est-row:hover {
      border-color: rgba(255,255,255,0.1);
    }

    .est-row input[type="checkbox"] {
      width: 18px;
      height: 18px;
      accent-color: var(--accent);
      cursor: pointer;
      flex-shrink: 0;
      display: none;
    }

    .estimates-list.select-mode .est-row input[type="checkbox"] {
      display: block;
    }

    .est-row-body {
      flex: 1;
      min-width: 0;
    }

    .est-row-addr {
      font-weight: 600;
      font-size: 0.95rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .est-row-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-top: 4px;
    }

    .est-row-meta .price {
      color: var(--accent);
      font-weight: 600;
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .badge-standard { background: rgba(30,144,255,0.2); color: #1e90ff; }
    .badge-chemical { background: rgba(255,102,0,0.2); color: #ff6600; }
    .badge-heavy    { background: rgba(204,0,255,0.2); color: #cc00ff; }

    /* ── Estimate Detail View ────────────────────────────────── */
    .est-detail {
      background: var(--bg-panel);
      border-radius: 16px;
      padding: 28px;
      margin-top: 12px;
      border: 1px solid rgba(246,220,75,0.1);
      display: none;
    }

    .est-detail.open {
      display: block;
    }

    .est-detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-top: 16px;
    }

    .est-map-container {
      width: 100%;
      height: 350px;
      border-radius: 12px;
      overflow: hidden;
      background: var(--bg-card);
      position: relative;
    }

    .map-hint {
      position: absolute;
      bottom: 12px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(14,19,36,0.85);
      color: var(--text-muted);
      font-size: 0.8rem;
      padding: 8px 16px;
      border-radius: 8px;
      pointer-events: none;
      z-index: 10;
      white-space: nowrap;
      animation: hintFade 5s ease-in-out forwards;
    }

    @keyframes hintFade {
      0%   { opacity: 1; }
      70%  { opacity: 1; }
      100% { opacity: 0; }
    }

    .map-hint.hidden {
      display: none;
    }

    .est-info-col {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .est-info-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid rgba(255,255,255,0.04);
      font-size: 0.9rem;
    }

    .est-info-row .label {
      color: var(--text-muted);
    }

    .est-info-row .value {
      font-weight: 600;
    }

    .est-detail-close {
      background: none;
      border: none;
      color: var(--text-muted);
      font-size: 1.2rem;
      cursor: pointer;
      float: right;
      padding: 4px 8px;
      transition: color 0.2s;
    }

    .est-detail-close:hover {
      color: var(--white);
    }

    /* ── Settings ────────────────────────────────────────────── */
    .settings-section {
      background: var(--bg-panel);
      border-radius: 16px;
      padding: 32px;
      margin-bottom: 24px;
      border: 1px solid rgba(255,255,255,0.06);
    }

    .settings-section h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.3rem;
      letter-spacing: 1px;
      margin-bottom: 0;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      user-select: none;
      transition: color 0.2s;
    }

    .settings-section h2:hover {
      color: var(--accent);
    }

    .settings-section h2 .chevron {
      width: 20px;
      height: 20px;
      stroke: var(--text-muted);
      transition: transform 0.3s ease;
      flex-shrink: 0;
    }

    .settings-section.collapsed h2 .chevron {
      transform: rotate(-90deg);
    }

    .settings-body {
      overflow: hidden;
      transition: max-height 0.35s ease, opacity 0.25s ease, margin-top 0.35s ease;
      max-height: 800px;
      opacity: 1;
      margin-top: 24px;
    }

    .settings-section.collapsed .settings-body {
      max-height: 0;
      opacity: 0;
      margin-top: 0;
    }

    .form-group {
      margin-bottom: 20px;
      text-align: left;
    }

    .form-group label {
      display: block;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--text-muted);
      margin-bottom: 6px;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 14px 16px;
      background: var(--bg-input);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 8px;
      color: var(--white);
      font-family: 'Outfit', sans-serif;
      font-size: 1rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(246, 220, 75, 0.15);
    }

    .form-group input::placeholder {
      color: rgba(255,255,255,0.25);
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .field-note {
      font-size: 0.8rem;
      color: var(--text-muted);
      font-style: italic;
      margin-top: 4px;
    }

    .form-feedback {
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 0.9rem;
      margin-top: 16px;
      display: none;
    }

    .form-feedback.success {
      background: rgba(78,205,196,0.1);
      border: 1px solid rgba(78,205,196,0.3);
      color: #4ecdc4;
      display: block;
    }

    .form-feedback.error {
      background: rgba(255,80,80,0.1);
      border: 1px solid rgba(255,80,80,0.3);
      color: #ff6b6b;
      display: block;
    }

    /* Day toggles */
    .day-toggles {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .day-toggle {
      padding: 10px 16px;
      border-radius: 8px;
      border: 1px solid rgba(255,255,255,0.08);
      background: var(--bg-card);
      color: var(--text-muted);
      font-family: 'Outfit', sans-serif;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .day-toggle.active {
      background: var(--accent);
      color: var(--bg-deep);
      border-color: var(--accent);
      font-weight: 700;
    }

    /* ── Empty State ─────────────────────────────────────────── */
    .empty-state {
      text-align: center;
      padding: 60px 24px;
    }

    .empty-state svg {
      width: 64px;
      height: 64px;
      stroke: var(--text-muted);
      margin-bottom: 16px;
      opacity: 0.5;
    }

    .empty-state h3 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.3rem;
      margin-bottom: 8px;
    }

    .empty-state p {
      color: var(--text-muted);
      font-size: 0.9rem;
      max-width: 400px;
      margin: 0 auto;
    }

    /* ── Modal ────────────────────────────────────────────────── */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .modal-overlay.open {
      display: flex;
    }

    .modal {
      background: var(--bg-panel);
      border-radius: 16px;
      padding: 36px;
      max-width: 420px;
      width: 100%;
      border: 1px solid rgba(255,80,80,0.2);
    }

    .modal h3 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.3rem;
      margin-bottom: 12px;
    }

    .modal p {
      color: var(--text-muted);
      font-size: 0.9rem;
      margin-bottom: 24px;
    }

    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
    }

    /* ── Mobile Bottom Nav ───────────────────────────────────── */
    .mobile-nav {
      display: none;
    }

    /* ── Footer ──────────────────────────────────────────────── */
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

    /* ── Tablet ──────────────────────────────────────────────── */
    @media (max-width: 1024px) {
      .est-detail-grid {
        grid-template-columns: 1fr;
      }

      .est-map-container {
        height: 280px;
      }

      .form-row {
        grid-template-columns: 1fr;
      }
    }

    /* ── Mobile ──────────────────────────────────────────────── */
    @media (max-width: 767px) {
      .dash-tabs {
        display: none;
      }

      .mobile-nav {
        display: flex;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 100;
        background: var(--bg-panel);
        border-top: 1px solid rgba(255,255,255,0.06);
        justify-content: space-around;
        padding: 8px 0;
      }

      .mobile-nav-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        font-family: 'Outfit', sans-serif;
        font-size: 0.7rem;
        font-weight: 500;
        padding: 8px 12px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        transition: color 0.2s;
      }

      .mobile-nav-btn svg {
        width: 22px;
        height: 22px;
      }

      .mobile-nav-btn.active {
        color: var(--accent);
      }

      .dash-content {
        padding: 24px 16px 100px;
      }

      .dash-header {
        padding: 16px 16px 0;
      }

      .estimates-grid {
        grid-template-columns: 1fr;
      }

      .url-display {
        flex-direction: column;
        align-items: stretch;
      }

      .url-display .btn-copy {
        text-align: center;
      }

      .nudge-card {
        flex-direction: column;
        text-align: center;
      }

      .settings-section {
        padding: 24px 16px;
      }

      .est-row {
        flex-wrap: wrap;
      }

      .footer-inner {
        flex-direction: column;
        gap: 16px;
        text-align: center;
      }

      .footer-links {
        gap: 20px;
      }

      .modal {
        padding: 28px 20px;
      }

      .user-name {
        display: none;
      }
    }
  </style>
</head>
<body>

  <!-- ── Header ──────────────────────────────────────────────── -->
  <header class="dash-header">
    <a href="../2/index.php" class="logo">PREZURE</a>
    <div class="dash-header-right">
      <span class="user-name"><?php echo htmlspecialchars($currentUser['company_name']); ?></span>
      <a href="../3/logout.php" class="btn-logout">Log Out</a>
    </div>
  </header>

  <!-- ── Desktop Tabs ────────────────────────────────────────── -->
  <nav class="dash-tabs">
    <button class="dash-tab active" data-tab="home">Home</button>
    <button class="dash-tab" data-tab="estimates">Estimates</button>
    <button class="dash-tab" data-tab="settings">Settings</button>
  </nav>

  <!-- ── Tab Content ─────────────────────────────────────────── -->
  <main class="dash-content">

    <!-- ════ HOME TAB ════════════════════════════════════════════ -->
    <section id="tab-home" class="tab-panel active">

      <!-- Calculator URL -->
      <div class="card card-accent">
        <h2>Your Calculator Link</h2>
        <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:16px;">Share this link with customers so they can get instant estimates.</p>
        <div class="url-display">
          <code id="calc-url"><?php echo htmlspecialchars($calcUrl); ?></code>
          <button class="btn-copy" id="btn-copy-url" onclick="copyUrl()">Copy</button>
        </div>
      </div>

      <!-- Profile Nudge -->
      <div id="nudge-wrapper" style="<?php echo $profileIncomplete ? '' : 'display:none;'; ?>">
        <div class="nudge-card">
          <p id="nudge-text">Your profile is missing some optional fields (<?php echo htmlspecialchars(implode(', ', $incompleteFields)); ?>). Completing these improves the customer experience.</p>
          <button class="btn-primary" onclick="switchTab('settings')">Complete Profile</button>
        </div>
      </div>

      <!-- Recent Estimates -->
      <div style="margin-top:28px;">
        <h2 style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;letter-spacing:1px;margin-bottom:16px;">Recent Estimates</h2>
        <div id="home-estimates-container">
          <div style="text-align:center;padding:40px 0;color:var(--text-muted);">Loading...</div>
        </div>
      </div>

    </section>

    <!-- ════ ESTIMATES TAB ═══════════════════════════════════════ -->
    <section id="tab-estimates" class="tab-panel">

      <!-- Toolbar -->
      <div class="estimates-toolbar">
        <h2 style="font-family:'Bebas Neue',sans-serif;font-size:1.3rem;letter-spacing:1px;">All Estimates</h2>
        <div class="spacer"></div>
        <button class="btn-secondary" id="btn-select-mode" onclick="toggleSelectMode()">Select</button>
        <button class="btn-secondary" id="btn-select-all" style="display:none;" onclick="selectAllEstimates()">Select All</button>
        <button class="btn-danger" id="btn-delete-selected" style="display:none;" onclick="confirmDeleteSelected()">Delete Selected</button>
        <button class="btn-secondary" id="btn-date-range" onclick="toggleDateRange()">Delete by Date Range</button>
      </div>

      <!-- Date Range Panel -->
      <div class="date-range-panel" id="date-range-panel">
        <div class="form-group">
          <label>Start Date</label>
          <input type="date" id="range-start">
        </div>
        <div class="form-group">
          <label>End Date</label>
          <input type="date" id="range-end">
        </div>
        <button class="btn-danger" onclick="confirmDeleteByRange()">Delete in Range</button>
      </div>

      <!-- Estimates List -->
      <div id="estimates-list-container">
        <div style="text-align:center;padding:40px 0;color:var(--text-muted);">Loading...</div>
      </div>

    </section>

    <!-- ════ SETTINGS TAB ════════════════════════════════════════ -->
    <section id="tab-settings" class="tab-panel">

      <!-- Account Info -->
      <div class="settings-section">
        <h2 onclick="toggleSection(this)">Account Information <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></h2>
        <div class="settings-body">
          <form id="form-account" onsubmit="saveSettings(event,'account')">
            <div class="form-row">
              <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($currentUser['name']); ?>" required>
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($currentUser['phone']); ?>" required>
              </div>
              <div class="form-group">
                <label>Company Name</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($currentUser['company_name']); ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label>Address</label>
              <input type="text" name="address" value="<?php echo htmlspecialchars($currentUser['address']); ?>" required>
            </div>
            <button type="submit" class="btn-primary">Save Changes</button>
            <div class="form-feedback" id="feedback-account"></div>
          </form>
        </div>
      </div>

      <!-- Change Password -->
      <div class="settings-section collapsed">
        <h2 onclick="toggleSection(this)">Change Password <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></h2>
        <div class="settings-body">
          <form id="form-password" onsubmit="saveSettings(event,'password')">
            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" required placeholder="Enter current password">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required placeholder="Min 8 characters" minlength="8">
              </div>
              <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required placeholder="Re-enter new password">
              </div>
            </div>
            <button type="submit" class="btn-primary">Update Password</button>
            <div class="form-feedback" id="feedback-password"></div>
          </form>
        </div>
      </div>

      <!-- Pricing -->
      <div class="settings-section collapsed">
        <h2 onclick="toggleSection(this)">Pricing <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></h2>
        <div class="settings-body">
          <form id="form-pricing" onsubmit="saveSettings(event,'pricing')">
            <div class="form-row">
              <div class="form-group">
                <label>Standard Price / sqft *</label>
                <input type="number" name="pricing_standard" step="0.01" min="0.01" value="<?php echo htmlspecialchars((string)($currentUser['pricing_standard'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
              </div>
              <div class="form-group">
                <label>Base Price</label>
                <input type="number" name="pricing_base" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($currentUser['pricing_base'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional">
                <div class="field-note">Leave blank if you don't charge one</div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Chemical Wash Price / sqft</label>
                <input type="number" name="pricing_chemical" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($currentUser['pricing_chemical'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional">
              </div>
              <div class="form-group">
                <label>Heavy Wash Price / sqft</label>
                <input type="number" name="pricing_heavy" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($currentUser['pricing_heavy'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Optional">
              </div>
            </div>
            <button type="submit" class="btn-primary">Save Pricing</button>
            <div class="form-feedback" id="feedback-pricing"></div>
          </form>
        </div>
      </div>

      <!-- Availability -->
      <div class="settings-section collapsed">
        <h2 onclick="toggleSection(this)">Availability <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></h2>
        <div class="settings-body">
          <form id="form-availability" onsubmit="saveSettings(event,'availability')">
          <div class="form-group">
            <label>Available Days</label>
            <div class="day-toggles" id="day-toggles">
              <?php
              $days = ['monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri','saturday'=>'Sat','sunday'=>'Sun'];
              $activeDays = is_array($currentUser['available_days'] ?? null) ? $currentUser['available_days'] : [];
              foreach ($days as $value => $label):
                $isActive = in_array($value, $activeDays, true) ? ' active' : '';
              ?>
              <button type="button" class="day-toggle<?php echo $isActive; ?>" data-day="<?php echo $value; ?>" onclick="toggleDay(this)"><?php echo $label; ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Start Hour</label>
              <select name="available_hours_start" id="hours-start">
                <option value="">Not set</option>
                <?php
                $hours = $currentUser['available_hours'] ?? [];
                $startHour = !empty($hours) ? $hours[0] : '';
                for ($h = 6; $h <= 22; $h++):
                  $val = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                  $sel = ($val === $startHour) ? ' selected' : '';
                ?>
                <option value="<?php echo $val; ?>"<?php echo $sel; ?>><?php echo date('g:i A', strtotime($val)); ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="form-group">
              <label>End Hour</label>
              <select name="available_hours_end" id="hours-end">
                <option value="">Not set</option>
                <?php
                $endHour = !empty($hours) ? end($hours) : '';
                for ($h = 6; $h <= 22; $h++):
                  $val = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                  $sel = ($val === $endHour) ? ' selected' : '';
                ?>
                <option value="<?php echo $val; ?>"<?php echo $sel; ?>><?php echo date('g:i A', strtotime($val)); ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
          <div class="field-note" style="margin-bottom:16px;">When availability is left empty, the date selection step is hidden from customers on your calculator.</div>
            <button type="submit" class="btn-primary">Save Availability</button>
            <div class="form-feedback" id="feedback-availability"></div>
          </form>
        </div>
      </div>

    </section>

  </main>

  <!-- ── Mobile Bottom Nav ───────────────────────────────────── -->
  <nav class="mobile-nav">
    <button class="mobile-nav-btn active" data-tab="home" onclick="switchTab('home')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Home
    </button>
    <button class="mobile-nav-btn" data-tab="estimates" onclick="switchTab('estimates')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Estimates
    </button>
    <button class="mobile-nav-btn" data-tab="settings" onclick="switchTab('settings')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      Settings
    </button>
  </nav>

  <!-- ── Confirmation Modal ──────────────────────────────────── -->
  <div class="modal-overlay" id="modal-overlay">
    <div class="modal">
      <h3 id="modal-title">Are you sure?</h3>
      <p id="modal-message"></p>
      <div class="modal-actions">
        <button class="btn-ghost" onclick="closeModal()">Cancel</button>
        <button class="btn-danger" id="modal-confirm">Delete</button>
      </div>
    </div>
  </div>

  <?php include '../2/footer.php'; ?>

  <script>
  // ── State ─────────────────────────────────────────────────────
  const USER = <?php echo json_encode($safeUser, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
  const CALC_URL = <?php echo json_encode($calcUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
  let allEstimates = [];
  let mapsLoaded = false;
  let openDetailId = null;
  let selectMode = false;

  // ── CSRF helper for state-changing fetches ─────────────────────
  function csrfHeaders() {
    const tok = document.querySelector('meta[name="csrf-token"]')?.content || '';
    return { 'Content-Type': 'application/json', 'X-CSRF-Token': tok };
  }
  function applyCsrfFromResponse(data) {
    if (data && data.csrfToken) {
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) meta.content = data.csrfToken;
    }
  }

  // Polygon colors matching calculator SERVICE_VISUAL (1/prezure.html line 667-672)
  const POLY_COLORS = {
    standard: { stroke: '#1e90ff', fill: 'rgba(30,144,255,0.35)' },
    chemical: { stroke: '#ff6600', fill: 'rgba(255,102,0,0.35)' },
    heavy:    { stroke: '#cc00ff', fill: 'rgba(204,0,255,0.35)' },
    default:  { stroke: '#1e90ff', fill: 'rgba(30,144,255,0.35)' }
  };

  // ── Tab Switching ─────────────────────────────────────────────
  function switchTab(tab) {
    document.querySelectorAll('.dash-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    document.querySelectorAll('.mobile-nav-btn').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));

    if (tab === 'estimates' && allEstimates.length === 0) {
      loadEstimates();
    }
  }

  document.querySelectorAll('.dash-tab').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
  });

  // ── Copy URL ──────────────────────────────────────────────────
  function copyUrl() {
    navigator.clipboard.writeText(CALC_URL).then(() => {
      const btn = document.getElementById('btn-copy-url');
      btn.textContent = 'Copied!';
      setTimeout(() => btn.textContent = 'Copy', 2000);
    });
  }

  // ── Fetch Estimates ───────────────────────────────────────────
  async function fetchEstimates(limit) {
    const url = 'get_estimates.php' + (limit ? '?limit=' + limit : '');
    const res = await fetch(url);
    const data = await res.json();
    return data.estimates || [];
  }

  async function loadHomeEstimates() {
    const container = document.getElementById('home-estimates-container');
    try {
      const estimates = await fetchEstimates(3);
      if (estimates.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <h3>No Estimates Yet</h3>
            <p>Share your calculator link to start receiving estimates from customers.</p>
            <div style="margin-top:20px;">
              <div class="url-display" style="max-width:500px;margin:0 auto;">
                <code>${escHtml(CALC_URL)}</code>
                <button class="btn-copy" onclick="copyUrl()">Copy</button>
              </div>
            </div>
          </div>`;
        return;
      }

      let html = '<div class="estimates-grid">';
      for (const est of estimates) {
        html += renderEstimateCard(est);
      }
      html += '</div>';
      if (estimates.length >= 3) {
        html += `<div style="text-align:center;margin-top:20px;"><button class="btn-secondary" onclick="switchTab('estimates')">View All Estimates</button></div>`;
      }
      container.innerHTML = html;
    } catch (err) {
      container.innerHTML = `<p style="color:#ff6b6b;text-align:center;">Failed to load estimates.</p>`;
    }
  }

  function renderEstimateCard(est) {
    const pref = formatPreferred(est.preferred_date, est.preferred_time);
    const time = formatTimestamp(est.created_at);
    return `
      <div class="estimate-card" onclick="switchTab('estimates');setTimeout(()=>expandEstimate(${est.id}),300)">
        <div class="estimate-address">${escHtml(est.address)}</div>
        <div class="estimate-meta">
          <span class="estimate-price">$${Number(est.price).toFixed(2)}</span>
          <span>${pref}</span>
          <span>${time}</span>
        </div>
      </div>`;
  }

  // ── Estimates Tab ─────────────────────────────────────────────
  async function loadEstimates() {
    const container = document.getElementById('estimates-list-container');
    try {
      allEstimates = await fetchEstimates();
      renderEstimatesList();
    } catch (err) {
      container.innerHTML = `<p style="color:#ff6b6b;text-align:center;">Failed to load estimates.</p>`;
    }
  }

  function renderEstimatesList() {
    const container = document.getElementById('estimates-list-container');
    if (allEstimates.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <h3>No Estimates Yet</h3>
          <p>Share your calculator link to start receiving estimates from customers.</p>
          <div style="margin-top:20px;">
            <div class="url-display" style="max-width:500px;margin:0 auto;">
              <code>${escHtml(CALC_URL)}</code>
              <button class="btn-copy" onclick="copyUrl()">Copy</button>
            </div>
          </div>
        </div>`;
      document.getElementById('btn-delete-selected').style.display = 'none';
      document.getElementById('btn-date-range').style.display = 'none';
      document.getElementById('btn-select-mode').style.display = 'none';
      document.getElementById('btn-select-all').style.display = 'none';
      return;
    }

    document.getElementById('btn-date-range').style.display = '';
    document.getElementById('btn-select-mode').style.display = '';
    document.getElementById('btn-select-all').style.display = selectMode ? '' : 'none';

    let html = '<div class="estimates-list">';
    for (const est of allEstimates) {
      const pref = formatPreferred(est.preferred_date, est.preferred_time);
      const time = formatTimestamp(est.created_at);
      const il = est.intensity_levels || {};
      const badges = [];
      if (il.standard > 0) badges.push(`<span class="badge badge-standard">${Number(il.standard).toFixed(0)} sqft std</span>`);
      if (il.chemical > 0) badges.push(`<span class="badge badge-chemical">${Number(il.chemical).toFixed(0)} sqft chem</span>`);
      if (il.heavy > 0)    badges.push(`<span class="badge badge-heavy">${Number(il.heavy).toFixed(0)} sqft heavy</span>`);

      html += `
        <div>
          <div class="est-row" id="est-row-${est.id}">
            <input type="checkbox" data-id="${est.id}" onchange="updateDeleteBtn()">
            <div class="est-row-body">
              <div class="est-row-addr">${escHtml(est.address)}</div>
              <div class="est-row-meta">
                <span class="price">$${Number(est.price).toFixed(2)}</span>
                <span>${Number(est.sqft).toFixed(0)} sqft</span>
                ${badges.join('')}
                <span>${pref}</span>
                <span>${time}</span>
              </div>
            </div>
            <button class="btn-secondary" onclick="expandEstimate(${est.id})" style="white-space:nowrap;">View Details</button>
          </div>
          <div class="est-detail" id="est-detail-${est.id}"></div>
        </div>`;
    }
    html += '</div>';
    container.innerHTML = html;
  }

  function toggleSelectMode() {
    selectMode = !selectMode;
    const list = document.querySelector('.estimates-list');
    const btn = document.getElementById('btn-select-mode');
    const btnAll = document.getElementById('btn-select-all');
    if (selectMode) {
      if (list) list.classList.add('select-mode');
      btn.textContent = 'Cancel';
      btn.className = 'btn-ghost';
      btnAll.style.display = '';
    } else {
      if (list) list.classList.remove('select-mode');
      btn.textContent = 'Select';
      btn.className = 'btn-secondary';
      btnAll.style.display = 'none';
      btnAll.textContent = 'Select All';
      // Uncheck all and hide delete button
      document.querySelectorAll('#estimates-list-container input[type="checkbox"]').forEach(cb => cb.checked = false);
      document.getElementById('btn-delete-selected').style.display = 'none';
    }
  }

  function selectAllEstimates() {
    const boxes = document.querySelectorAll('#estimates-list-container input[type="checkbox"]');
    const allChecked = Array.from(boxes).every(cb => cb.checked);
    boxes.forEach(cb => cb.checked = !allChecked);
    document.getElementById('btn-select-all').textContent = allChecked ? 'Select All' : 'Deselect All';
    updateDeleteBtn();
  }

  function updateDeleteBtn() {
    const all = document.querySelectorAll('#estimates-list-container input[type="checkbox"]');
    const checked = document.querySelectorAll('#estimates-list-container input[type="checkbox"]:checked');
    document.getElementById('btn-delete-selected').style.display = checked.length > 0 ? '' : 'none';
    const btnAll = document.getElementById('btn-select-all');
    if (btnAll && selectMode) {
      btnAll.textContent = (all.length > 0 && checked.length === all.length) ? 'Deselect All' : 'Select All';
    }
  }

  // ── Expand Estimate Detail ────────────────────────────────────
  function expandEstimate(id) {
    const detail = document.getElementById('est-detail-' + id);
    if (!detail) return;

    // Toggle off if already open
    if (detail.classList.contains('open')) {
      detail.classList.remove('open');
      detail.innerHTML = '';
      openDetailId = null;
      return;
    }

    // Close any other open detail
    document.querySelectorAll('.est-detail.open').forEach(d => {
      d.classList.remove('open');
      d.innerHTML = '';
    });

    const est = allEstimates.find(e => e.id === id);
    if (!est) return;
    openDetailId = id;

    const il = est.intensity_levels || {};
    const pref = formatPreferred(est.preferred_date, est.preferred_time);

    detail.innerHTML = `
      <button class="est-detail-close" onclick="expandEstimate(${id})">&times;</button>
      <h3 style="font-family:'Bebas Neue',sans-serif;font-size:1.2rem;letter-spacing:1px;margin-bottom:4px;">Estimate Details</h3>
      <div class="est-detail-grid">
        <div class="est-map-container" id="est-map-${id}">
          <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);font-size:0.9rem;">Loading map...</div>
          <div class="map-hint" id="map-hint-${id}">Drag to move &middot; Scroll to zoom</div>
        </div>
        <div class="est-info-col">
          <div class="est-info-row"><span class="label">Address</span><span class="value">${escHtml(est.address)}</span></div>
          <div class="est-info-row"><span class="label">Total Price</span><span class="value" style="color:var(--accent);">$${Number(est.price).toFixed(2)}</span></div>
          <div class="est-info-row"><span class="label">Total Sqft</span><span class="value">${Number(est.sqft).toFixed(0)}</span></div>
          ${il.standard > 0 ? `<div class="est-info-row"><span class="label"><span class="badge badge-standard">Standard</span></span><span class="value">${Number(il.standard).toFixed(0)} sqft</span></div>` : ''}
          ${il.chemical > 0 ? `<div class="est-info-row"><span class="label"><span class="badge badge-chemical">Chemical</span></span><span class="value">${Number(il.chemical).toFixed(0)} sqft</span></div>` : ''}
          ${il.heavy > 0 ? `<div class="est-info-row"><span class="label"><span class="badge badge-heavy">Heavy</span></span><span class="value">${Number(il.heavy).toFixed(0)} sqft</span></div>` : ''}
          <div class="est-info-row"><span class="label">Preferred Date</span><span class="value">${pref}</span></div>
          <div class="est-info-row"><span class="label">Created</span><span class="value">${formatTimestamp(est.created_at)}</span></div>
        </div>
      </div>`;
    detail.classList.add('open');

    // Load map
    loadEstimateMap(est);
  }

  // ── Google Maps ───────────────────────────────────────────────
  // The Maps key is sourced from the environment server-side and
  // restricted by HTTP Referrer in Google Cloud Console. Loader
  // refuses to inject a script tag without a configured key.
  function loadMapsAPI() {
    return new Promise((resolve, reject) => {
      if (mapsLoaded) { resolve(); return; }
      if (window.google && window.google.maps) { mapsLoaded = true; resolve(); return; }
      const key = '<?php echo rawurlencode($mapsKey); ?>';
      if (!key) {
        console.warn('[Prezure] MAPS_API_KEY is not configured — map preview disabled.');
        reject(new Error('Maps API key not configured'));
        return;
      }
      const script = document.createElement('script');
      script.src = 'https://maps.googleapis.com/maps/api/js?key=' + key + '&libraries=geometry&loading=async&callback=_onMapsReady';
      script.async = true;
      script.defer = true;
      window._onMapsReady = () => { mapsLoaded = true; resolve(); };
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  async function loadEstimateMap(est) {
    try {
      await loadMapsAPI();
    } catch (e) {
      document.getElementById('est-map-' + est.id).innerHTML =
        '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#ff6b6b;font-size:0.85rem;">Failed to load Google Maps.</div>';
      return;
    }

    const container = document.getElementById('est-map-' + est.id);
    if (!container) return;
    container.innerHTML = '';

    const center = est.map_center || { lat: 27.9944, lng: -81.7603 };
    const zoom = est.map_zoom || 19;

    const map = new google.maps.Map(container, {
      center: center,
      zoom: zoom,
      mapTypeId: 'satellite',
      tilt: 0,
      disableDefaultUI: true,
      gestureHandling: 'greedy'
    });

    // Re-add the interaction hint on top of the map
    const hint = document.createElement('div');
    hint.className = 'map-hint';
    hint.innerHTML = 'Drag to move &middot; Scroll to zoom';
    container.appendChild(hint);
    const hideHint = () => hint.classList.add('hidden');
    container.addEventListener('mousedown', hideHint, { once: true });
    container.addEventListener('touchstart', hideHint, { once: true });
    container.addEventListener('wheel', hideHint, { once: true });

    // Draw polygons from coordinates
    // Typed format: [{latLngs:[{lat,lng},...], type:'standard'}, ...]
    // Legacy format: [[{lat,lng},...], ...] — rendered as default blue
    const coords = est.coordinates || [];
    for (const poly of coords) {
      let path, colors;
      if (poly.latLngs && Array.isArray(poly.latLngs)) {
        // Typed format (new estimates)
        if (poly.latLngs.length < 3) continue;
        path = poly.latLngs.map(c => ({ lat: c.lat, lng: c.lng }));
        colors = POLY_COLORS[poly.type] || POLY_COLORS.default;
      } else if (Array.isArray(poly) && poly.length >= 3) {
        // Legacy flat format (old estimates)
        path = poly.map(c => ({ lat: c.lat, lng: c.lng }));
        colors = POLY_COLORS.default;
      } else {
        continue;
      }

      new google.maps.Polygon({
        paths: path,
        strokeColor: colors.stroke,
        strokeWeight: 2.5,
        fillColor: colors.stroke,
        fillOpacity: 0.3,
        map: map
      });
    }
  }

  // ── Delete Functions ──────────────────────────────────────────
  function getCheckedIds() {
    return Array.from(document.querySelectorAll('#estimates-list-container input[type="checkbox"]:checked'))
      .map(cb => parseInt(cb.dataset.id));
  }

  function confirmDeleteSelected() {
    const ids = getCheckedIds();
    if (ids.length === 0) return;
    showModal(
      'Delete Estimates',
      `Delete ${ids.length} estimate${ids.length > 1 ? 's' : ''}? This cannot be undone.`,
      'Delete',
      () => deleteEstimates({ action: 'by_ids', ids })
    );
  }

  function toggleDateRange() {
    document.getElementById('date-range-panel').classList.toggle('open');
  }

  function confirmDeleteByRange() {
    const start = document.getElementById('range-start').value;
    const end   = document.getElementById('range-end').value;
    if (!start || !end) return;
    if (start > end) { alert('Start date must be before end date.'); return; }

    // Count estimates in range
    const startTs = new Date(start + 'T00:00:00').getTime();
    const endTs   = new Date(end + 'T23:59:59').getTime();
    const count = allEstimates.filter(e => {
      const t = new Date(e.created_at).getTime();
      return t >= startTs && t <= endTs;
    }).length;

    if (count === 0) { alert('No estimates found in that date range.'); return; }

    showModal(
      'Delete by Date Range',
      `Delete ${count} estimate${count > 1 ? 's' : ''} from ${start} to ${end}? This cannot be undone.`,
      'Delete',
      () => deleteEstimates({ action: 'by_date_range', start, end })
    );
  }

  async function deleteEstimates(payload) {
    closeModal();
    try {
      const res = await fetch('delete_estimates.php', {
        method: 'POST',
        headers: csrfHeaders(),
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });
      const data = await res.json();
      applyCsrfFromResponse(data);
      if (data.success) {
        selectMode = true; toggleSelectMode();
        await loadEstimates();
        loadHomeEstimates();
        document.getElementById('date-range-panel').classList.remove('open');
      } else {
        alert(data.error || 'Failed to delete estimates.');
      }
    } catch (err) {
      alert('Something went wrong. Please try again.');
    }
  }

  // ── Modal ─────────────────────────────────────────────────────
  function showModal(title, message, confirmText, onConfirm) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-message').textContent = message;
    const confirmBtn = document.getElementById('modal-confirm');
    confirmBtn.textContent = confirmText;
    confirmBtn.onclick = onConfirm;
    document.getElementById('modal-overlay').classList.add('open');
  }

  function closeModal() {
    document.getElementById('modal-overlay').classList.remove('open');
  }

  document.getElementById('modal-overlay').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeModal();
  });

  // ── Settings ──────────────────────────────────────────────────
  async function saveSettings(e, section) {
    e.preventDefault();
    const form = e.target;
    const feedback = document.getElementById('feedback-' + section);
    feedback.className = 'form-feedback';
    feedback.style.display = 'none';

    const payload = { section };

    if (section === 'account') {
      payload.name         = form.name.value;
      payload.email        = form.email.value;
      payload.phone        = form.phone.value;
      payload.company_name = form.company_name.value;
      payload.address      = form.address.value;
    } else if (section === 'password') {
      payload.current_password = form.current_password.value;
      payload.new_password     = form.new_password.value;
      payload.confirm_password = form.confirm_password.value;
    } else if (section === 'pricing') {
      payload.pricing_standard = form.pricing_standard.value;
      payload.pricing_base     = form.pricing_base.value;
      payload.pricing_chemical = form.pricing_chemical.value;
      payload.pricing_heavy    = form.pricing_heavy.value;
    } else if (section === 'availability') {
      payload.available_days = Array.from(document.querySelectorAll('.day-toggle.active')).map(b => b.dataset.day);
      payload.available_hours_start = form.available_hours_start.value;
      payload.available_hours_end   = form.available_hours_end.value;
    }

    try {
      const res = await fetch('update_settings.php', {
        method: 'POST',
        headers: csrfHeaders(),
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });
      const data = await res.json();
      applyCsrfFromResponse(data);

      if (data.success) {
        feedback.textContent = 'Saved successfully!';
        feedback.className = 'form-feedback success';
        if (section === 'password') form.reset();
        if (data.user) { Object.assign(USER, data.user); updateNudge(); }
      } else {
        feedback.textContent = data.error || 'Failed to save.';
        feedback.className = 'form-feedback error';
      }
    } catch (err) {
      feedback.textContent = 'Something went wrong. Please try again.';
      feedback.className = 'form-feedback error';
    }

    setTimeout(() => { feedback.style.display = ''; }, 0);
    setTimeout(() => { feedback.className = 'form-feedback'; feedback.style.display = 'none'; }, 5000);
  }

  function toggleSection(h2) {
    h2.closest('.settings-section').classList.toggle('collapsed');
  }

  function toggleDay(btn) {
    btn.classList.toggle('active');
  }

  // ── Profile Nudge ──────────────────────────────────────────────
  function updateNudge() {
    const wrapper = document.getElementById('nudge-wrapper');
    if (!wrapper) return;
    const missing = [];
    if (!USER.available_days || USER.available_days.length === 0)  missing.push('available days');
    if (!USER.available_hours || USER.available_hours.length === 0) missing.push('available hours');

    if (missing.length === 0) {
      wrapper.style.display = 'none';
    } else {
      wrapper.style.display = '';
      document.getElementById('nudge-text').textContent =
        'Your profile is missing some optional fields (' + missing.join(', ') + '). Completing these improves the customer experience.';
    }
  }

  // ── Helpers ───────────────────────────────────────────────────
  function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function formatTime(timeStr) {
    if (!timeStr) return '';
    const [h, m] = timeStr.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    return (h % 12 || 12) + ':' + m.toString().padStart(2, '0') + ' ' + ampm;
  }

  function formatPreferred(date, time) {
    if (!date) return 'No date set';
    const d = formatDate(date);
    const t = formatTime(time);
    return t ? d + ' at ' + t : d;
  }

  function formatTimestamp(ts) {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' at ' +
           d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  }

  // ── Init ──────────────────────────────────────────────────────
  loadHomeEstimates();
  </script>

</body>
</html>
