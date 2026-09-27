<?php
require_once __DIR__ . '/../includes/sanitize.php';

$failures = 0;

function check(bool $ok, string $message): void {
    global $failures;
    if ($ok) {
        return;
    }
    fwrite(STDERR, "FAIL: {$message}\n");
    $failures++;
}

check(sanitize_string("a\r\nb\n") === 'ab', 'control characters are removed');
check(sanitize_header("Hi:\r\nBcc: x") === 'HiBcc x', 'header metacharacters are removed');
check(sanitize_email('not-an-email') === '', 'invalid email is rejected');
check(sanitize_email('Guest@Mail.com') === 'guest@mail.com', 'email is normalized');
check(sanitize_phone('(555) 555-0100') === '5555550100', '10-digit phone is kept');
check(sanitize_phone('123') === '', 'short phone is rejected');
check(validate_password('#guestPass') === true, 'demo password meets the length rule');
check(validate_password('short') === false, 'short password is rejected');

if ($failures > 0) {
    fwrite(STDERR, "{$failures} failed\n");
    exit(1);
}

echo "ok\n";
