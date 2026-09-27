<?php
// File-backed sliding-window rate limiter. One JSON file per bucket.

require_once __DIR__ . '/config.php';

if (!function_exists('client_ip')) {
    function client_ip(): string {
        // Forwarded headers are client-controlled unless a trusted proxy set them.
        $trust = getenv('TRUST_PROXY');
        $trustProxy = $trust === '1' || strcasecmp((string)$trust, 'true') === 0;
        $candidates = $trustProxy
            ? [
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                $_SERVER['HTTP_X_REAL_IP'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]
            : [$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'];
        foreach ($candidates as $c) {
            if ($c === '') continue;
            $first = trim(explode(',', $c)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
        }
        return '0.0.0.0';
    }
}

if (!function_exists('rate_limit_check')) {
    /**
     * Token-bucket / sliding-window check.
     *
     * @param string $key       Bucket key, e.g. "login:1.2.3.4"
     * @param int    $maxHits   Max events allowed per window
     * @param int    $windowSec Window length in seconds
     * @return bool             true = allowed, false = limit exceeded
     */
    function rate_limit_check(string $key, int $maxHits, int $windowSec): bool {
        $dir = PREZURE_RATELIMIT_DIR;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        // Hash the key so it's filesystem-safe.
        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $fp = @fopen($file, 'c+');
        if (!$fp) return true; // fail-open if we can't write the file
        if (!@flock($fp, LOCK_EX)) { fclose($fp); return true; }

        $raw  = stream_get_contents($fp);
        $hits = json_decode($raw, true);
        if (!is_array($hits)) $hits = [];

        $now = time();
        $cutoff = $now - $windowSec;
        $hits = array_values(array_filter($hits, fn($t) => $t >= $cutoff));

        $allowed = count($hits) < $maxHits;
        if ($allowed) $hits[] = $now;

        fseek($fp, 0);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($hits));
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);

        // Opportunistic GC of stale buckets (1% of calls).
        if (mt_rand(1, 100) === 1) rate_limit_gc($dir, 86400);

        return $allowed;
    }
}

if (!function_exists('rate_limit_gc')) {
    function rate_limit_gc(string $dir, int $maxAge): void {
        if (!is_dir($dir)) return;
        $cutoff = time() - $maxAge;
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            if (@filemtime($f) < $cutoff) @unlink($f);
        }
    }
}

if (!function_exists('rate_limit_or_429')) {
    /**
     * Convenience wrapper: enforce the limit and exit 429 on failure.
     */
    function rate_limit_or_429(string $key, int $maxHits, int $windowSec): void {
        if (!rate_limit_check($key, $maxHits, $windowSec)) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $windowSec);
            echo json_encode([
                'success' => false,
                'error'   => 'Too many requests. Please slow down and try again later.'
            ]);
            exit;
        }
    }
}
