<?php
// Input cleaning for stored fields. Encode with htmlspecialchars (or escHtml)
// at output time; these functions do not HTML-encode.

function sanitize_string(string $val, int $maxLength = 500): string {
    $val = trim($val);
    $val = stripslashes($val);
    // Strip ASCII control characters so values cannot inject extra email headers.
    $val = preg_replace('/[\x00-\x1F\x7F]/u', '', $val);
    if (mb_strlen($val) > $maxLength) {
        $val = mb_substr($val, 0, $maxLength);
    }
    return $val;
}

/**
 * Sanitize a value being interpolated into an email header (Subject,
 * From, To, etc.). Strips CR/LF AND colons — the latter blocks attempts
 * to introduce a brand-new header inside an existing one.
 */
function sanitize_header(string $val, int $maxLength = 200): string {
    $val = sanitize_string($val, $maxLength);
    return str_replace([':', '<', '>'], '', $val);
}

/**
 * Escape a value for inclusion in an iCalendar TEXT property
 * (DESCRIPTION, SUMMARY, LOCATION) per RFC 5545 §3.3.11.
 *   \\  →  \\\\
 *   ;   →  \;
 *   ,   →  \,
 *   \n  →  \n
 *   \r  →  (stripped)
 */
function ics_escape(string $val): string {
    $val = str_replace("\r\n", "\n", $val);
    $val = str_replace("\r",   "\n", $val);
    $val = str_replace('\\', '\\\\', $val);
    $val = str_replace(';',   '\\;',  $val);
    $val = str_replace(',',   '\\,',  $val);
    $val = str_replace("\n",  '\\n',  $val);
    return $val;
}

/**
 * Strip secret/PII fields from a user record before serializing it to a
 * client-bound response or HTML page. NEVER echo a raw user record.
 */
function safe_user(array $u): array {
    unset($u['password'], $u['calculator_key']);
    return $u;
}

/**
 * Case-insensitive, whitespace-tolerant email equality. Use everywhere
 * email uniqueness needs to be enforced regardless of how the legacy
 * record was capitalized.
 */
function emails_equal(string $a, string $b): bool {
    return strcasecmp(trim($a), trim($b)) === 0;
}

/**
 * Sanitize string for HTML output (XSS prevention).
 * Use this when echoing values into HTML pages.
 */
function sanitize_html(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize and validate an email address.
 * Returns the sanitized email or empty string if invalid.
 */
function sanitize_email(string $val): string {
    $val = sanitize_string($val, 254); // RFC 5321 max email length
    $val = strtolower($val);
    $val = filter_var($val, FILTER_SANITIZE_EMAIL);
    return filter_var($val, FILTER_VALIDATE_EMAIL) ? $val : '';
}

/**
 * Sanitize a phone number to digits only.
 * Returns a 10-digit string or empty string if invalid.
 */
function sanitize_phone(string $val): string {
    $digits = preg_replace('/\D/', '', $val);
    // Support US numbers: strip leading 1 if 11 digits
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    return (strlen($digits) === 10) ? $digits : '';
}

/**
 * Sanitize an integer value.
 * Returns the integer value, or the default if input is not numeric.
 */
function sanitize_int($val, int $default = 0): int {
    if (is_numeric($val)) {
        return (int) $val;
    }
    return $default;
}

/**
 * Sanitize a float value.
 * Returns the float value, or null if input is empty/non-numeric.
 */
function sanitize_float($val, bool $allowNull = true): ?float {
    if ($val === '' || $val === null) {
        return $allowNull ? null : 0.0;
    }
    if (is_numeric($val)) {
        return (float) $val;
    }
    return $allowNull ? null : 0.0;
}

/**
 * Sanitize a date string (YYYY-MM-DD format).
 * Returns the validated date string or empty string if invalid.
 */
function sanitize_date(string $val): string {
    $val = sanitize_string($val, 10);
    // Validate YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        $parts = explode('-', $val);
        if (checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            return $val;
        }
    }
    return '';
}

/**
 * Sanitize a timestamp/datetime string.
 * Returns the value if parseable by strtotime, or empty string if invalid.
 */
function sanitize_timestamp(string $val): string {
    $val = sanitize_string($val, 30);
    if (strtotime($val) !== false) {
        return $val;
    }
    return '';
}

/**
 * Sanitize an array of strings (e.g., day names).
 * Only keeps values present in the allowed list.
 */
function sanitize_string_array(array $vals, array $allowed): array {
    $result = [];
    foreach ($vals as $v) {
        $clean = strtolower(sanitize_string((string) $v, 20));
        if (in_array($clean, $allowed, true)) {
            $result[] = $clean;
        }
    }
    return array_values(array_unique($result));
}

/**
 * Sanitize an array of integers (e.g., estimate IDs).
 */
function sanitize_int_array(array $vals): array {
    return array_values(array_unique(array_map('intval', $vals)));
}

/**
 * Sanitize a time string (HH:MM format).
 * Returns the validated time or empty string if invalid.
 */
function sanitize_time(string $val): string {
    $val = sanitize_string($val, 5);
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $val)) {
        return $val;
    }
    return '';
}

/**
 * Validate a password meets minimum requirements.
 * Returns true if valid, false otherwise.
 */
function validate_password(string $val): bool {
    return strlen($val) >= 8;
}

/**
 * Sanitize coordinates (lat/lng).
 * Returns the float value clamped to valid geographic range, or null if invalid.
 */
function sanitize_lat($val): ?float {
    if (!is_numeric($val)) return null;
    $f = (float) $val;
    return ($f >= -90 && $f <= 90) ? $f : null;
}

function sanitize_lng($val): ?float {
    if (!is_numeric($val)) return null;
    $f = (float) $val;
    return ($f >= -180 && $f <= 180) ? $f : null;
}

/**
 * Sanitize a coordinate polygon array from estimate data.
 *
 * Accepts two formats:
 *   - Typed (new):  [{ latLngs: [{lat,lng},...], type: 'standard' }, ...]
 *   - Legacy (old): [[{lat,lng},...], ...]
 *
 * Always returns typed format:
 *   [{ latLngs: [{lat,lng},...], type: 'standard|chemical|heavy|negative' }, ...]
 *
 * Legacy polygons default to type 'standard'.
 */
function sanitize_coordinates(array $polygons): array {
    $validTypes = ['standard', 'chemical', 'heavy', 'negative'];
    $clean = [];

    foreach ($polygons as $poly) {
        if (!is_array($poly)) continue;

        // Detect format: typed (has 'latLngs' key) vs legacy (flat array of points)
        if (isset($poly['latLngs'])) {
            // Typed format
            $points = is_array($poly['latLngs']) ? $poly['latLngs'] : [];
            $type   = isset($poly['type']) && in_array(strtolower($poly['type']), $validTypes, true)
                    ? strtolower($poly['type'])
                    : 'standard';
        } else {
            // Legacy flat format — array of {lat, lng} objects
            $points = $poly;
            $type   = 'standard';
        }

        $cleanPoints = [];
        foreach ($points as $point) {
            if (!is_array($point) && !is_object($point)) continue;
            $point = (array) $point;
            $lat = sanitize_lat($point['lat'] ?? null);
            $lng = sanitize_lng($point['lng'] ?? null);
            if ($lat !== null && $lng !== null) {
                $cleanPoints[] = ['lat' => $lat, 'lng' => $lng];
            }
        }

        if (count($cleanPoints) >= 3) {
            $clean[] = ['latLngs' => $cleanPoints, 'type' => $type];
        }
    }
    return $clean;
}
