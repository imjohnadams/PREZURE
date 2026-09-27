<?php
// Append-only audit log. One JSON object per line. Not shown to users.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ratelimit.php'; // for client_ip()

if (!function_exists('audit_log')) {
    function audit_log(string $event, array $context = []): void {
        $line = json_encode([
            'ts'      => gmdate('c'),
            'event'   => $event,
            'ip'      => client_ip(),
            'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            'session' => substr(session_id() ?: '', 0, 12), // truncated, only for correlation
            'ctx'     => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) return;

        $path = PREZURE_AUDIT_LOG;
        $dir  = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $fp = @fopen($path, 'a');
        if (!$fp) return;
        if (@flock($fp, LOCK_EX)) {
            fwrite($fp, $line . "\n");
            fflush($fp);
            @flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}
