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
  <title>Sign In — Prezure</title>
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
      max-width: 440px;
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

    .auth-card .btn-primary {
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

      .footer-inner {
        flex-direction: column;
        gap: 16px;
        text-align: center;
      }

      .footer-links {
        gap: 20px;
      }
    }
  </style>
</head>
<body>

  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="logo">PREZURE</div>
      <h1>Welcome Back</h1>
      <p class="auth-subtitle">Sign in to your account</p>

      <div class="error-message" id="error" style="display:none"></div>

      <form id="signin-form">
        <div class="form-group">
          <label>Email, phone, or username</label>
          <input type="text" name="identifier" required placeholder="guest">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" required placeholder="Your password">
        </div>
        <button type="submit" class="btn-primary" id="btn-signin">Sign In</button>
      </form>

      <p class="auth-switch">Don't have an account? <a href="signup.php">Sign Up</a></p>
    </div>
  </div>

  <?php include '../2/footer.php'; ?>

  <script>
    document.getElementById('signin-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const errorEl = document.getElementById('error');
      errorEl.style.display = 'none';

      const btn = document.getElementById('btn-signin');
      btn.disabled = true;
      btn.textContent = 'Signing in...';

      try {
        const formData = Object.fromEntries(new FormData(e.target));

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch('auth.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token':  csrf
          },
          body: JSON.stringify(formData)
        });
        const data = await res.json();

        if (data.success) {
          window.location.href = data.redirect;
        } else {
          errorEl.textContent = data.error;
          errorEl.style.display = 'block';
        }
      } catch (err) {
        errorEl.textContent = 'Something went wrong. Please try again.';
        errorEl.style.display = 'block';
      }

      btn.disabled = false;
      btn.textContent = 'Sign In';
    });
  </script>

</body>
</html>
