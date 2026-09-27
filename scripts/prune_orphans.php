<?php
// Moves bookings and estimate files whose contractor no longer exists.
// Dry-run unless --apply is passed. Orphans are renamed, not deleted.
// CLI only.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit("This script is CLI-only.\n");
}

require_once __DIR__ . '/../includes/config.php';

$apply  = in_array('--apply', $argv ?? [], true);
$verb   = $apply ? 'PRUNE' : 'DRY-RUN';

echo "[$verb] Loading users from " . PREZURE_USERS_FILE . "\n";
$users = read_json_locked(PREZURE_USERS_FILE);
if (!$users || !isset($users['users']) || !is_array($users['users'])) {
    fwrite(STDERR, "ERROR: users.json missing or malformed.\n");
    exit(1);
}

$validIds = [];
foreach ($users['users'] as $u) {
    if (isset($u['id'])) $validIds[(int)$u['id']] = true;
}
echo "[$verb] " . count($validIds) . " active contractor IDs.\n";

// ── Bookings ─────────────────────────────────────────────────────────────────
$bookingsFile = PREZURE_BOOKINGS_FILE;
$bookings = read_json_locked($bookingsFile) ?? [];
$kept = $orphaned = [];
foreach ($bookings as $token => $booking) {
    $cid = (int)($booking['contractor_id'] ?? 0);
    if ($cid > 0 && isset($validIds[$cid])) {
        $kept[$token] = $booking;
    } else {
        $orphaned[$token] = $booking;
    }
}
echo "[$verb] Bookings: " . count($kept) . " kept, " . count($orphaned) . " orphaned.\n";

if ($apply && $orphaned) {
    $orphanFile = preg_replace('/\.json$/', '.orphaned.json', $bookingsFile);
    @file_put_contents($orphanFile,
        json_encode($orphaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX);
    with_locked_json($bookingsFile, fn($_) => $kept, []);
    echo "[$verb] Wrote orphans to $orphanFile\n";
}

// ── Estimate files ───────────────────────────────────────────────────────────
$estDir = PREZURE_ESTIMATES_DIR;
if (!is_dir($estDir)) {
    echo "[$verb] No estimatesJSON directory at $estDir; skipping.\n";
    exit(0);
}
foreach (glob($estDir . '/*.json') ?: [] as $file) {
    $base = basename($file, '.json');
    if (!ctype_digit($base)) {
        echo "[$verb] Skipping non-numeric file $file\n";
        continue;
    }
    $cid = (int)$base;
    if (!isset($validIds[$cid])) {
        echo "[$verb] Orphan file $file (contractor_id $cid no longer exists).\n";
        if ($apply) {
            $newName = $estDir . '/' . $cid . '.orphaned.json';
            @rename($file, $newName);
            echo "[$verb] Renamed to $newName\n";
        }
    }
}

echo "[$verb] Done.\n";
