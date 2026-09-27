<?php
// Direct-access guard: footer is a partial; refuse to serve it standalone.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}
?>
<footer class="site-footer">
  <div class="footer-inner">
    <p>&copy; Prezure 2026</p>
    <nav class="footer-links">
      <a href="../5/tos.php">Terms of Service</a>
      <a href="../5/privacy.php">Privacy Policy</a>
    </nav>
  </div>
</footer>