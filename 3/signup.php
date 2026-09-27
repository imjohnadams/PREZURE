<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
prezure_session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . app_url('4/dashboard.php'));
    exit;
}
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
  <meta name="app-base-url" content="<?php echo htmlspecialchars(APP_BASE_URL, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
  <title>Sign Up — Prezure</title>
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
      width: 100%;
      text-align: center;
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

    /* Auth layout */
    .auth-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 60px 24px;
    }

    .auth-card {
      background: var(--bg-panel);
      border-radius: 20px;
      padding: 48px 40px;
      width: 100%;
      max-width: 520px;
      border: 1px solid rgba(246, 220, 75, 0.15);
      text-align: center;
    }

    .logo {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.75rem;
      letter-spacing: 2px;
      color: var(--accent);
      margin-bottom: 8px;
    }

    .auth-card h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 2rem;
      margin-bottom: 8px;
    }

    .auth-subtitle {
      color: var(--text-muted);
      font-weight: 300;
      margin-bottom: 32px;
    }

    /* Progress bar */
    .progress-bar {
      display: flex;
      align-items: flex-start;
      justify-content: center;
      margin-bottom: 36px;
    }

    .progress-step {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }

    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.1rem;
      background: var(--bg-card);
      color: var(--text-muted);
      border: 2px solid rgba(255,255,255,0.1);
      transition: all 0.3s;
    }

    .progress-step.active .step-circle {
      background: var(--accent);
      color: var(--bg-deep);
      border-color: var(--accent);
    }

    .progress-step.completed .step-circle {
      background: var(--accent);
      color: var(--bg-deep);
      border-color: var(--accent);
    }

    .progress-step span {
      font-size: 0.75rem;
      color: var(--text-muted);
      font-weight: 300;
    }

    .progress-step.active span,
    .progress-step.completed span {
      color: var(--white);
      font-weight: 500;
    }

    .progress-line {
      width: 48px;
      height: 2px;
      background: rgba(255,255,255,0.1);
      margin: 19px 12px 0;
      transition: background 0.3s;
    }

    .progress-line.filled {
      background: var(--accent);
    }

    /* Form */
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

    .form-group input {
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

    .form-group input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(246, 220, 75, 0.15);
    }

    .form-group input::placeholder {
      color: rgba(255,255,255,0.25);
    }

    .field-note {
      display: block;
      font-size: 0.8rem;
      color: var(--text-muted);
      font-weight: 300;
      margin-top: 6px;
    }

    /* Error */
    .error-message {
      background: rgba(255, 80, 80, 0.1);
      border: 1px solid rgba(255, 80, 80, 0.3);
      border-radius: 8px;
      padding: 12px 16px;
      color: #ff6b6b;
      font-size: 0.9rem;
      margin-bottom: 20px;
      text-align: left;
    }

    /* Step visibility */
    .step-form { display: none; }
    .step-form.active { display: block; }

    /* Step 3 — Confirmation */
    .success-icon {
      width: 72px;
      height: 72px;
      margin: 0 auto 24px;
      background: rgba(246, 220, 75, 0.15);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .success-icon svg {
      width: 36px;
      height: 36px;
      color: var(--accent);
    }

    .step-form h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.75rem;
      margin-bottom: 8px;
    }

    .calculator-url-box {
      display: flex;
      align-items: center;
      background: var(--bg-input);
      border: 1px solid rgba(246, 220, 75, 0.3);
      border-radius: 8px;
      padding: 12px 16px;
      margin: 24px 0;
      gap: 12px;
    }

    .calculator-url-box code {
      flex: 1;
      font-size: 0.85rem;
      color: var(--accent);
      word-break: break-all;
      text-align: left;
      font-family: 'Outfit', sans-serif;
    }

    .btn-copy {
      background: var(--accent);
      color: var(--bg-deep);
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.8rem;
      cursor: pointer;
      white-space: nowrap;
      font-family: 'Outfit', sans-serif;
      transition: transform 0.2s;
    }

    .btn-copy:hover {
      transform: translateY(-1px);
    }

    .step-form .btn-primary {
      margin-top: 8px;
    }

    /* Switch link */
    .auth-switch {
      margin-top: 24px;
      font-size: 0.9rem;
      color: var(--text-muted);
      font-weight: 300;
    }

    .auth-switch a {
      color: var(--accent);
      font-weight: 600;
    }

    .auth-switch a:hover {
      text-decoration: underline;
    }

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

    .footer-links a:hover {
      color: var(--white);
    }

    /* ToS Modal */
    .tos-overlay {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: rgba(0, 0, 0, 0.7);
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .tos-overlay.active {
      display: flex;
    }

    .tos-modal {
      background: var(--bg-panel);
      border: 1px solid rgba(246, 220, 75, 0.15);
      border-radius: 20px;
      padding: 40px 32px;
      max-width: 480px;
      width: 100%;
      text-align: center;
    }

    .tos-modal h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.5rem;
      margin-bottom: 16px;
    }

    .tos-modal p {
      color: var(--text-muted);
      font-weight: 300;
      font-size: 0.95rem;
      line-height: 1.7;
      margin-bottom: 24px;
    }

    .tos-modal a.tos-link {
      color: var(--accent);
      font-weight: 600;
      transition: opacity 0.2s;
    }

    .tos-modal a.tos-link:hover { opacity: 0.7; }

    .tos-checkbox-row {
      display: flex;
      align-items: center;
      gap: 12px;
      text-align: left;
      margin-bottom: 24px;
      cursor: pointer;
    }

    .tos-checkbox-row input[type="checkbox"] {
      appearance: none;
      -webkit-appearance: none;
      width: 22px;
      height: 22px;
      min-width: 22px;
      border: 2px solid rgba(255, 255, 255, 0.15);
      border-radius: 4px;
      background: var(--bg-input);
      cursor: pointer;
      position: relative;
      transition: border-color 0.2s, background 0.2s;
    }

    .tos-checkbox-row input[type="checkbox"]:checked {
      background: var(--accent);
      border-color: var(--accent);
    }

    .tos-checkbox-row input[type="checkbox"]:checked::after {
      content: '';
      position: absolute;
      left: 6px;
      top: 2px;
      width: 6px;
      height: 12px;
      border: solid var(--bg-deep);
      border-width: 0 2.5px 2.5px 0;
      transform: rotate(45deg);
    }

    .tos-checkbox-row label {
      font-size: 0.9rem;
      color: var(--white);
      font-weight: 400;
      cursor: pointer;
    }

    .tos-error {
      display: none;
      background: rgba(255, 80, 80, 0.1);
      border: 1px solid rgba(255, 80, 80, 0.3);
      border-radius: 8px;
      padding: 10px 14px;
      color: #ff6b6b;
      font-size: 0.85rem;
      margin-bottom: 20px;
      text-align: left;
    }

    .tos-buttons {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .btn-tos-confirm {
      display: inline-block;
      background: var(--accent);
      color: var(--bg-deep);
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 1rem;
      padding: 14px 32px;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      width: 100%;
      text-align: center;
    }

    .btn-tos-confirm:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(246, 220, 75, 0.3);
    }

    .btn-tos-confirm:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .btn-tos-back {
      background: transparent;
      color: var(--text-muted);
      font-family: 'Outfit', sans-serif;
      font-weight: 500;
      font-size: 0.95rem;
      padding: 12px 32px;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      cursor: pointer;
      transition: color 0.2s, border-color 0.2s;
      width: 100%;
    }

    .btn-tos-back:hover {
      color: var(--white);
      border-color: rgba(255, 255, 255, 0.25);
    }

    /* Tablet */
    @media (max-width: 1024px) {
      .auth-card {
        padding: 40px 32px;
      }
    }

    /* Mobile */
    @media (max-width: 767px) {
      .auth-wrapper {
        padding: 40px 16px;
      }

      .auth-card {
        padding: 32px 20px;
      }

      .btn-primary {
        font-size: 1rem;
        padding: 14px 32px;
      }

      .progress-line {
        width: 24px;
        margin: 19px 8px 0;
      }

      .step-circle {
        width: 36px;
        height: 36px;
        font-size: 1rem;
      }

      .progress-line {
        margin-top: 17px;
      }

      .footer-inner {
        flex-direction: column;
        gap: 16px;
        text-align: center;
      }

      .footer-links {
        gap: 20px;
      }

      .tos-overlay { padding: 16px; }
      .tos-modal { padding: 28px 20px; }
    }
  </style>
</head>
<body>

  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="logo">PREZURE</div>
      <h1>Create Your Account</h1>
      <p class="auth-subtitle">Get your personalized calculator in minutes</p>

      <!-- Progress Indicator -->
      <div class="progress-bar">
        <div class="progress-step active" data-step="1">
          <div class="step-circle">1</div>
          <span>Account</span>
        </div>
        <div class="progress-line" id="line-1"></div>
        <div class="progress-step" data-step="2">
          <div class="step-circle">2</div>
          <span>Pricing</span>
        </div>
        <div class="progress-line" id="line-2"></div>
        <div class="progress-step" data-step="3">
          <div class="step-circle">3</div>
          <span>You're In</span>
        </div>
      </div>

      <div class="error-message" id="error" style="display:none"></div>

      <!-- Step 1: Account Setup -->
      <form id="step-1" class="step-form active">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" required placeholder="John Doe">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required placeholder="john@example.com">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" required minlength="8" placeholder="Minimum 8 characters">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="tel" name="phone" required placeholder="123-456-7890" maxlength="14" pattern="[\d\-]{10,14}">
        </div>
        <div class="form-group">
          <label>Company Name</label>
          <input type="text" name="company_name" required placeholder="Your business name">
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" required placeholder="123 Main St, City, State">
        </div>
        <button type="submit" class="btn-primary" id="btn-step1">Continue</button>
      </form>

      <!-- Step 2: Pricing -->
      <form id="step-2" class="step-form">
        <div class="form-group">
          <label>Standard Price (per sqft) *</label>
          <input type="number" name="pricing_standard" step="0.01" min="0.01" required placeholder="0.20">
        </div>
        <div class="form-group">
          <label>Base Price (flat fee per job)</label>
          <input type="number" name="pricing_base" step="0.01" min="0" placeholder="50.00">
          <span class="field-note">Leave blank if you don't charge one</span>
        </div>
        <div class="form-group">
          <label>Chemical Wash Price (per sqft)</label>
          <input type="number" name="pricing_chemical" step="0.01" min="0" placeholder="0.25">
        </div>
        <div class="form-group">
          <label>Heavy Wash Price (per sqft)</label>
          <input type="number" name="pricing_heavy" step="0.01" min="0" placeholder="0.30">
        </div>
        <button type="submit" class="btn-primary" id="btn-step2">Create My Account</button>
      </form>

      <!-- Step 3: Confirmation -->
      <div id="step-3" class="step-form">
        <div class="success-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </div>
        <h2>You're In, <span id="user-company"></span>!</h2>
        <p class="auth-subtitle" style="margin-bottom:16px">Here's your personalized calculator link:</p>
        <div class="calculator-url-box">
          <code id="calc-url"></code>
          <button class="btn-copy" id="copy-btn">Copy</button>
        </div>
        <p class="field-note" style="text-align:center;margin-bottom:24px">Share this link with customers so they can get instant quotes.</p>
        <a href="../4/dashboard.php" class="btn-primary">Go to Dashboard</a>
      </div>

      <p class="auth-switch" id="signin-link">Already have an account? <a href="signin.php">Sign In</a></p>
    </div>
  </div>

  <!-- ToS Confirmation Modal -->
  <div class="tos-overlay" id="tos-overlay">
    <div class="tos-modal">
      <h2>Terms of Service</h2>
      <p>By creating your Prezure account you agree to our <a href="../5/tos.php" target="_blank" rel="noopener" class="tos-link">Terms of Service</a> and <a href="../5/privacy.php" target="_blank" rel="noopener" class="tos-link">Privacy Policy</a>. Please read and confirm before continuing.</p>
      <div class="tos-error" id="tos-error">You must agree to the Terms of Service and Privacy Policy to create an account.</div>
      <label class="tos-checkbox-row">
        <input type="checkbox" id="tos-checkbox">
        <span>I have read and agree to the Terms of Service and Privacy Policy</span>
      </label>
      <div class="tos-buttons">
        <button type="button" class="btn-tos-confirm" id="tos-confirm" disabled>Create My Account</button>
        <button type="button" class="btn-tos-back" id="tos-back">Go Back</button>
      </div>
    </div>
  </div>

  <?php include '../2/footer.php'; ?>

  <script>
    function showError(msg) {
      const el = document.getElementById('error');
      el.textContent = msg;
      el.style.display = 'block';
    }

    function clearError() {
      const el = document.getElementById('error');
      el.style.display = 'none';
      el.textContent = '';
    }

    function showStep(step) {
      clearError();
      document.querySelectorAll('.step-form').forEach(f => f.classList.remove('active'));
      document.getElementById('step-' + step).classList.add('active');

      document.querySelectorAll('.progress-step').forEach(s => {
        const n = parseInt(s.dataset.step);
        s.classList.remove('active', 'completed');
        if (n === step) s.classList.add('active');
        if (n < step) s.classList.add('completed');
      });

      if (step >= 2) document.getElementById('line-1').classList.add('filled');
      if (step >= 3) document.getElementById('line-2').classList.add('filled');

      if (step === 3) {
        document.getElementById('signin-link').style.display = 'none';
      }
    }

    // Step 1
    document.getElementById('step-1').addEventListener('submit', async (e) => {
      e.preventDefault();
      clearError();
      const btn = document.getElementById('btn-step1');
      btn.disabled = true;
      btn.textContent = 'Checking...';

      try {
        const formData = Object.fromEntries(new FormData(e.target));
        formData.step = 1;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch('register.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token':  csrf
          },
          body: JSON.stringify(formData)
        });
        const data = await res.json();

        if (data.success) {
          if (data.csrfToken) {
            document.querySelector('meta[name="csrf-token"]').content = data.csrfToken;
          }
          showStep(2);
        } else {
          showError(data.error);
        }
      } catch (err) {
        showError('Something went wrong. Please try again.');
      }

      btn.disabled = false;
      btn.textContent = 'Continue';
    });

    // Step 2 — intercept submit, show ToS modal
    let pendingFormData = null;

    document.getElementById('step-2').addEventListener('submit', (e) => {
      e.preventDefault();
      clearError();

      const formData = Object.fromEntries(new FormData(e.target));
      formData.step = 2;
      formData.pricing_standard = parseFloat(formData.pricing_standard);
      formData.pricing_base = formData.pricing_base ? parseFloat(formData.pricing_base) : null;
      formData.pricing_chemical = formData.pricing_chemical ? parseFloat(formData.pricing_chemical) : null;
      formData.pricing_heavy = formData.pricing_heavy ? parseFloat(formData.pricing_heavy) : null;

      pendingFormData = formData;

      // Reset modal state
      document.getElementById('tos-checkbox').checked = false;
      document.getElementById('tos-confirm').disabled = true;
      document.getElementById('tos-error').style.display = 'none';
      document.getElementById('tos-overlay').classList.add('active');
    });

    // Checkbox toggles confirm button
    document.getElementById('tos-checkbox').addEventListener('change', (e) => {
      document.getElementById('tos-confirm').disabled = !e.target.checked;
      if (e.target.checked) {
        document.getElementById('tos-error').style.display = 'none';
      }
    });

    // Go Back — close modal, preserve form data
    document.getElementById('tos-back').addEventListener('click', () => {
      document.getElementById('tos-overlay').classList.remove('active');
    });

    // Confirm — validate checkbox, submit to register.php
    document.getElementById('tos-confirm').addEventListener('click', async () => {
      if (!document.getElementById('tos-checkbox').checked) {
        document.getElementById('tos-error').style.display = 'block';
        return;
      }

      const confirmBtn = document.getElementById('tos-confirm');
      const step2Btn = document.getElementById('btn-step2');
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Creating account...';

      pendingFormData.tos_agreed = true;

      try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch('register.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token':  csrf
          },
          body: JSON.stringify(pendingFormData)
        });
        const data = await res.json();

        if (data.success) {
          document.getElementById('tos-overlay').classList.remove('active');
          document.getElementById('user-company').textContent = data.company_name;
          const appBase = document.querySelector('meta[name="app-base-url"]')?.content || '';
          const baseUrl = window.location.origin + appBase + '/1/prezure.html';
          document.getElementById('calc-url').textContent = baseUrl + '?key=' + data.calculator_key;
          if (data.csrfToken) {
            document.querySelector('meta[name="csrf-token"]').content = data.csrfToken;
          }
          showStep(3);
        } else {
          document.getElementById('tos-overlay').classList.remove('active');
          showError(data.error);
        }
      } catch (err) {
        document.getElementById('tos-overlay').classList.remove('active');
        showError('Something went wrong. Please try again.');
      }

      confirmBtn.disabled = false;
      confirmBtn.textContent = 'Create My Account';
      step2Btn.disabled = false;
      step2Btn.textContent = 'Create My Account';
    });

    // Copy URL
    document.getElementById('copy-btn').addEventListener('click', () => {
      const url = document.getElementById('calc-url').textContent;
      navigator.clipboard.writeText(url).then(() => {
        const btn = document.getElementById('copy-btn');
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
      });
    });
  </script>

</body>
</html>
