<?php require_once __DIR__ . '/../includes/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prezure — Instant Quotes for Pressure Washing Contractors</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/app.css'), ENT_QUOTES) ?>">
  <style>
    /* Page-specific styles. Shared reset + variables come from assets/app.css. */

    /* ── Buttons ── */
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

    /* ── Navigation ── */
    .nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 100;
      padding: 20px 0;
      transition: background 0.3s, padding 0.3s;
    }

    .nav.scrolled {
      background: rgba(14, 19, 36, 0.95);
      backdrop-filter: blur(12px);
      padding: 12px 0;
    }

    .nav-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .logo {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.75rem;
      letter-spacing: 2px;
      color: var(--accent);
    }

    .nav .btn-primary {
      font-size: 0.875rem;
      padding: 10px 24px;
    }

    /* ── Hero ── */
    .hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 120px 24px 80px;
    }

    .hero-content { max-width: 720px; }

    .hero h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(3rem, 8vw, 5.5rem);
      line-height: 1.05;
      letter-spacing: 1px;
      margin-bottom: 24px;
    }

    .hero h1 .highlight {
      color: var(--accent);
    }

    .hero p {
      font-size: 1.25rem;
      color: var(--text-muted);
      font-weight: 300;
      max-width: 540px;
      margin: 0 auto 40px;
    }

    /* ── Problem ── */
    .problem {
      padding: 100px 0;
      background: var(--bg-panel);
    }

    .problem-inner {
      max-width: 680px;
      margin: 0 auto;
      text-align: center;
    }

    .problem .section-label {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1rem;
      letter-spacing: 3px;
      color: var(--accent);
      text-transform: uppercase;
      margin-bottom: 16px;
    }

    .problem h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2rem, 5vw, 3rem);
      line-height: 1.1;
      margin-bottom: 28px;
    }

    .problem p {
      font-size: 1.125rem;
      color: var(--text-muted);
      font-weight: 300;
      line-height: 1.8;
    }

    .problem p + p {
      margin-top: 20px;
    }

    /* ── How It Works ── */
    .how-it-works {
      padding: 100px 0;
    }

    .how-it-works .section-header {
      text-align: center;
      margin-bottom: 64px;
    }

    .how-it-works .section-label {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1rem;
      letter-spacing: 3px;
      color: var(--accent);
      text-transform: uppercase;
      margin-bottom: 16px;
    }

    .how-it-works h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2rem, 5vw, 3rem);
      line-height: 1.1;
    }

    .steps {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 32px;
    }

    .step {
      background: var(--bg-panel);
      border-radius: 16px;
      padding: 40px 32px;
      text-align: center;
      position: relative;
      transition: transform 0.2s;
    }

    .step:hover {
      transform: translateY(-4px);
    }

    .step-number {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 56px;
      height: 56px;
      background: var(--accent);
      color: var(--bg-deep);
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1.5rem;
      border-radius: 50%;
      margin-bottom: 24px;
    }

    .step-icon {
      width: 48px;
      height: 48px;
      margin: 0 auto 20px;
      color: var(--accent);
    }

    .step h3 {
      font-family: 'Outfit', sans-serif;
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 12px;
    }

    .step p {
      font-size: 0.95rem;
      color: var(--text-muted);
      font-weight: 300;
      line-height: 1.7;
    }

    /* ── Demo ── */
    .demo {
      padding: 100px 0;
      background: var(--bg-panel);
    }

    .demo .section-header {
      text-align: center;
      margin-bottom: 48px;
    }

    .demo .section-label {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1rem;
      letter-spacing: 3px;
      color: var(--accent);
      text-transform: uppercase;
      margin-bottom: 16px;
    }

    .demo h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2rem, 5vw, 3rem);
      line-height: 1.1;
    }

    .demo-placeholder {
      max-width: 800px;
      margin: 0 auto;
      aspect-ratio: 16/9;
      background: var(--bg-card);
      border: 2px dashed rgba(246, 220, 75, 0.3);
      border-radius: 16px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px;
    }

    .demo-placeholder svg {
      width: 64px;
      height: 64px;
      color: var(--accent);
      opacity: 0.6;
    }

    .demo-placeholder span {
      color: var(--text-muted);
      font-size: 0.95rem;
      font-weight: 300;
    }

    /* ── Pricing ── */
    .pricing {
      padding: 100px 0;
    }

    .pricing .section-header {
      text-align: center;
      margin-bottom: 48px;
    }

    .pricing .section-label {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 1rem;
      letter-spacing: 3px;
      color: var(--accent);
      text-transform: uppercase;
      margin-bottom: 16px;
    }

    .pricing h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2rem, 5vw, 3rem);
      line-height: 1.1;
    }

    .pricing-card {
      max-width: 480px;
      margin: 0 auto;
      background: var(--bg-panel);
      border-radius: 20px;
      padding: 48px 40px;
      text-align: center;
      border: 1px solid rgba(246, 220, 75, 0.15);
    }

    .pricing-intro {
      font-size: 1rem;
      color: var(--accent);
      font-weight: 600;
      margin-bottom: 8px;
    }

    .pricing-amount {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 4.5rem;
      line-height: 1;
      margin-bottom: 4px;
    }

    .pricing-amount span {
      font-family: 'Outfit', sans-serif;
      font-size: 1.125rem;
      font-weight: 300;
      color: var(--text-muted);
    }

    .pricing-then {
      font-size: 1rem;
      color: var(--text-muted);
      font-weight: 300;
      margin-bottom: 32px;
    }

    .pricing-features {
      list-style: none;
      text-align: left;
      margin-bottom: 36px;
    }

    .pricing-features li {
      padding: 10px 0;
      font-size: 0.95rem;
      color: var(--text-muted);
      font-weight: 300;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .pricing-features li svg {
      width: 20px;
      height: 20px;
      color: var(--accent);
      flex-shrink: 0;
    }

    .pricing-card .btn-primary {
      width: 100%;
    }

    .pricing-note {
      margin-top: 20px;
      font-size: 0.85rem;
      color: var(--text-muted);
      font-weight: 300;
    }

    /* ── Footer CTA ── */
    .footer-cta {
      padding: 100px 0;
      background: var(--bg-panel);
      text-align: center;
    }

    .footer-cta h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(2rem, 5vw, 3.5rem);
      line-height: 1.1;
      margin-bottom: 20px;
    }

    .footer-cta p {
      font-size: 1.125rem;
      color: var(--text-muted);
      font-weight: 300;
      margin-bottom: 36px;
      max-width: 480px;
      margin-left: auto;
      margin-right: auto;
    }

    /* ── Site Footer ── */
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

    /* ── Tablet (768–1024px) ── */
    @media (max-width: 1024px) {
      .steps {
        gap: 24px;
      }

      .step {
        padding: 32px 24px;
      }

      .pricing-card {
        padding: 40px 32px;
      }
    }

    /* ── Mobile (< 768px) ── */
    @media (max-width: 767px) {
      .hero {
        min-height: auto;
        padding: 140px 20px 60px;
      }

      .hero p {
        font-size: 1.1rem;
      }

      .btn-primary {
        font-size: 1rem;
        padding: 14px 32px;
        width: 100%;
        text-align: center;
      }

      .nav .btn-primary {
        width: auto;
        padding: 8px 18px;
        font-size: 0.8rem;
      }

      .problem,
      .how-it-works,
      .demo,
      .pricing,
      .footer-cta {
        padding: 72px 0;
      }

      .steps {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .step {
        padding: 32px 24px;
      }

      .pricing-card {
        padding: 36px 24px;
      }

      .pricing-amount {
        font-size: 3.5rem;
      }

      .footer-inner {
        flex-direction: column;
        gap: 16px;
        text-align: center;
      }

      .footer-links {
        gap: 20px;
      }

      .demo-placeholder {
        aspect-ratio: 4/3;
      }
    }

    /* ── Animations ── */
    .fade-up {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.6s ease, transform 0.6s ease;
    }

    .fade-up.visible {
      opacity: 1;
      transform: translateY(0);
    }
  </style>
</head>
<body>

  <!-- Nav -->
  <nav class="nav" id="nav">
    <div class="container nav-inner">
      <div class="logo">PREZURE</div>
      <a href="../4/dashboard.php" class="btn-primary">Dashboard</a>
    </div>
  </nav>

  <!-- Hero -->
  <section class="hero">
    <div class="hero-content">
      <h1>Stop Chasing Quotes.<br><span class="highlight">Start Closing Jobs.</span></h1>
      <p>Give your customers a link, let them trace the area and pick the cleaning level. You get a clean estimate in your inbox. No driving out. No wasted time.</p>
      <a href="../3/signup.php" class="btn-primary">Get Started for $15</a>
    </div>
  </section>

  <!-- The Problem -->
  <section class="problem">
    <div class="container">
      <div class="problem-inner fade-up">
        <div class="section-label">The Problem</div>
        <h2>Quoting Shouldn't Be a Second Job</h2>
        <p>You drive 30 minutes to look at a driveway. You eyeball the square footage. You text the customer a number. They ghost you. Rinse and repeat.</p>
        <p>Half your week is spent giving free estimates to people who were never going to book. Every quote you chase is time you're not washing, and not getting paid.</p>
        <p>There's a better way.</p>
      </div>
    </div>
  </section>

  <!-- How It Works -->
  <section class="how-it-works">
    <div class="container">
      <div class="section-header fade-up">
        <div class="section-label">How It Works</div>
        <h2>Three Steps.</h2>
      </div>
      <div class="steps">
        <div class="step fade-up">
          <div class="step-number">1</div>
          <svg class="step-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
          </svg>
          <h3>Share Your Link</h3>
          <p> Send your personalized Prezure link to any customer via text, DM, or your website. That's it on your end.</p>
        </div>
        <div class="step fade-up">
          <div class="step-number">2</div>
          <svg class="step-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
          </svg>
          <h3>Customer Traces the Job</h3>
          <p>They outline the area on a map, choose the cleaning intensity, and get an instant price based on your rates.</p>
        </div>
        <div class="step fade-up">
          <div class="step-number">3</div>
          <svg class="step-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <path d="m22 4-10 8L2 4"/>
          </svg>
          <h3>You Get the Estimate</h3>
          <p>A clean summary hits your inbox. Job details, area size, price, and customer info. Ready to book or follow up.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- See It In Action -->
  <section class="demo">
    <div class="container">
      <div class="section-header fade-up">
        <div class="section-label">See It In Action</div>
        <h2>Watch How Fast It Works</h2>
      </div>
      <div class="demo-placeholder fade-up">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="5 3 19 12 5 21 5 3"/>
        </svg>
        <span>Demo video coming soon</span>
      </div>
    </div>
  </section>

  <!-- Pricing -->
  <section class="pricing">
    <div class="container">
      <div class="section-header fade-up">
        <div class="section-label">Pricing</div>
        <h2>Simple. Honest. One Plan.</h2>
      </div>
      <div class="pricing-card fade-up">
        <div class="pricing-intro">Start for just</div>
        <div class="pricing-amount">$15 <span>/ first month</span></div>
        <div class="pricing-then">Then $49/month. That's it. Forever.</div>
        <ul class="pricing-features">
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Your own personalized calculator link
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Unlimited customer estimates
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Email summaries for every quote
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            No tiers. No addons. No hidden fees.
          </li>
        </ul>
        <a href="../3/signup.php" class="btn-primary">Get Started for $15</a>
        <div class="pricing-note">The price today is the price in five years.</div>
      </div>
    </div>
  </section>

  <!-- Footer CTA -->
  <section class="footer-cta">
    <div class="container fade-up">
      <h2>Ready to Quote Smarter?</h2>
      <p>Stop losing hours on estimates that go nowhere. Start closing jobs from your couch.</p>
      <a href="../3/signup.php" class="btn-primary">Get Started for $15</a>
    </div>
  </section>

  <!-- Footer -->
  <?php include 'footer.php'; ?>

  <script>
    // Sticky nav background on scroll
    const nav = document.getElementById('nav');
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 40);
    });

    // Fade-up on scroll
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
        }
      });
    }, { threshold: 0.15 });

    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
  </script>

</body>
</html>