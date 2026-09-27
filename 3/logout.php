<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';

prezure_session_start();
if (!empty($_SESSION['user_id'])) {
    audit_log('logout', ['user_id' => (int)$_SESSION['user_id']]);
}
logout_user();
header('Location: ' . app_url('3/signin.php'));
exit;
