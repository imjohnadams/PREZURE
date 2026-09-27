# Prezure

Prezure lets pressure-washing contractors share a link that gives customers an area-based quote and a booking request.

## Overview

A contractor signs up, sets prices and availability, and sends customers to a calculator. The customer enters an address, traces the work area on a satellite map, and gets a quote from the contractor's rates. Submitting the quote downloads a PDF and saves a booking request. The contractor receives an email and accepts or declines from a confirmation page. The customer is emailed when their contact field is an email address.

Accounts, bookings, and estimates are JSON files. The repository has no database and no Composer project.

The marketing page and terms describe a subscription price. Creating an account writes a contractor record in `users.json`.

## Features

- Contractor signup with a generated calculator link
- Dashboard for the public calculator URL, estimate history, account details, password, pricing, and weekly hours
- Customer calculator: address search, polygon tracing, and a quote from a base fee plus per-square-foot rates for standard, chemical, and heavy work
- Exclusion areas that subtract overlapping traced square footage
- Browser-side PDF of the estimate
- Booking stored for the contractor and emailed to them
- Accept or decline on a confirmation page, with a calendar invite attached when an accepted booking has a date and time
- Included guest account for a local demo

## Tech Stack

- **Backend:** PHP 8 and Apache 2.4. Shared code lives in `includes/`.
- **Frontend:** PHP pages and one calculator page, `1/prezure.html`. Shared styles are in `assets/app.css`. The pages load Bebas Neue and Outfit from Google Fonts. The calculator loads the Maps JavaScript API (Places and geometry) and jsPDF from jsDelivr.
- **Data:** JSON files on disk. There is no database.
- **Authentication:** PHP sessions and `password_hash()`. Sign-in, signup, and the dashboard's settings and delete endpoints require an `X-CSRF-Token` header.
- **Email:** PHP `mail()` by default. SMTP is used when `MAIL_TRANSPORT` is `smtp`, `SMTP_HOST` is set, and `PHPMailer\PHPMailer\PHPMailer` is already loadable. This repository does not include PHPMailer. If that class is missing, sending uses `mail()`.
- **Maps:** A browser key for the Maps JavaScript API. Geocoding and Static Maps run through `1/geocode.php` and `1/staticmap.php`, using `MAPS_SERVER_KEY` when it is set and `MAPS_API_KEY` otherwise.

## Architecture

The project root redirects to the marketing page.

| Path | Role |
| --- | --- |
| `2/` | Marketing page |
| `3/` | Sign-in, sign-up, and session endpoints |
| `4/` | Contractor dashboard and settings |
| `1/` | Customer calculator, booking submit, and accept/decline |
| `5/` | Terms and privacy |
| `6/` | Error page |
| `includes/` | Configuration, sessions, sanitizing, rate limits, and the audit log |
| `estimatesJSON/` | One estimate file per contractor id |
| `scripts/prune_orphans.php` | CLI cleanup for records whose contractor no longer exists |

Signup is two requests to `3/register.php`: account details, then pricing. The account is written only after the contractor agrees to the terms. The response includes a calculator key. Sign-in at `3/auth.php` accepts an email, a 10-digit phone number, or a username when the account has one. Signup does not assign a username. The included guest account uses `guest`.

The dashboard link points at `1/prezure.html?key=...`. `1/contractor.php` looks up that key and returns the company name, phone, address, rates, availability, and the Maps script URL. The response leaves out the password and the numeric user id.

The calculator has three steps. The customer enters an address, traces polygons on the map, and reviews the quote. Square footage and price are computed in the browser from the rates returned for that key. `1/submit.php` stores the submitted figures and resolves the contractor from `calculator_key` only. The booking is stored in `bookings.json` under a new token. The estimate is inserted at the front of that contractor's file under `estimatesJSON/`. A PNG preview is written under `1/previews/` when the payload includes one. GD re-encodes that PNG when the extension is present. The same request emails the contractor.

Accept and decline links in that email open `1/respond.php`. A GET shows a confirmation form. The booking status changes only when the form is posted. The customer email is sent when the contact value is an email address. An accepted booking with a parseable date and time attaches an `.ics` invite. If mail delivery fails, the booking is already saved. The estimate is written before that email is sent, and the dashboard lists estimates.

With `PREZURE_DATA_DIR` empty, those files stay in the project tree. A custom data directory uses the same relative layout: `3/users.json`, `1/bookings.json`, `estimatesJSON/`, and `1/previews/`. The app also writes `_audit.log`, `_ratelimit/`, and `_geocode_cache.json` under that directory. Geocoding results are cached for 30 days.

The root `.htaccess` denies direct access to `.json`, `.log`, `.env`, `.md`, and database files, and to dotfiles. `estimatesJSON/`, `includes/`, and `scripts/` are also denied. `1/previews/.htaccess` allows image files and turns the PHP engine off with `php_flag`.

## Getting Started

### Prerequisites

- PHP 8 with the `mbstring` extension. `includes/sanitize.php` calls `mb_strlen` and `mb_substr`.
- Apache 2.4. The `.htaccess` files use `Require`.
- PHP loaded as an Apache module if you want `php_flag engine off` in `1/previews/.htaccess` to apply. That directive is what keeps uploaded previews from being executed as PHP.
- `allow_url_fopen` enabled. `1/geocode.php` and `1/staticmap.php` call Google with `file_get_contents`.
- GD is optional. Without it, preview images are stored after a PNG signature check and are not re-encoded.
- The web server user needs write access to `3/users.json`, `1/bookings.json`, `estimatesJSON/`, and `1/previews/`, and must be able to create `_audit.log`, `_ratelimit/`, and `_geocode_cache.json`. With a custom `PREZURE_DATA_DIR`, those paths are under that directory.

### Installation

1. Put the project in an Apache document root, for example `htdocs/prezure2`.
2. Copy `.env.example` to `.env`.
3. Set `APP_BASE_URL` to the URL path where the app is mounted (`/prezure2`, or blank if it is the site root). When `APP_BASE_URL` is blank, the app takes the first path segment of `SCRIPT_NAME`.
4. In `.htaccess`, set the `ErrorDocument` paths to that same prefix. They are `/prezure2/` in the repository. At the site root they should be `/6/error.php`.
5. Leave `PREZURE_DATA_DIR` blank to use the guest account included in this repository. A custom directory starts empty unless you copy `3/users.json`, `1/bookings.json`, and `estimatesJSON/` into it.
6. Open the site. The project root redirects to the marketing page.

### Environment

Copy `.env.example` to `.env`, or set the same names in the server environment. Values already present in the environment are kept; the `.env` file fills in only what is unset. Do not commit `.env`.

| Variable | Required | Purpose |
| --- | --- | --- |
| `APP_BASE_URL` | Set this to the mount path. Blank auto-detects from `SCRIPT_NAME`. | Internal path prefix used in links. Example: `/prezure2`. |
| `APP_EXT_URL` | Recommended when the site is public. Blank uses the current request. | Canonical URL written into emails. Example: `https://example.com/prezure2`. |
| `MAPS_API_KEY` | Required for the map, address search, and satellite preview. | Browser Maps JavaScript API key. Sign-in and the dashboard work while this is empty. |
| `MAPS_SERVER_KEY` | Optional. | Server-side Geocoding and Static Maps. Falls back to `MAPS_API_KEY`. |
| `PREZURE_DATA_DIR` | Optional. | Directory for users, bookings, estimates, and previews. Blank uses the project tree and the included guest account. |
| `TRUST_PROXY` | Optional. | Set to `1` only behind a trusted proxy that sets `X-Forwarded-For`. Otherwise leave it blank. |
| `MAIL_FROM_ADDR` | Optional. | From address. Defaults to `noreply@example.com`. |
| `MAIL_FROM_NAME` | Optional. | From name. Defaults to `Prezure Estimates`. |
| `MAIL_REPLY_ADDR` | Optional. | Reply-To address. Defaults to `MAIL_FROM_ADDR`. |
| `MAIL_TRANSPORT` | Optional. | `mail` (default) or `smtp`. |
| `SMTP_HOST` | Required for the SMTP path. | SMTP server. Ignored unless PHPMailer is loadable. |
| `SMTP_PORT` | Optional. | Defaults to `587`. |
| `SMTP_USER` | Optional. | SMTP username. Authentication is skipped when this is empty. |
| `SMTP_PASS` | Optional. | SMTP password. |
| `SMTP_SECURE` | Optional. | Defaults to `tls`. |
| `CORS_ALLOWED_ORIGINS` | Optional. | Comma-separated origins for cross-origin calls. Defaults to `APP_EXT_URL`. Requests with no `Origin` header are allowed. |

Create `MAPS_API_KEY` in Google Cloud. Restrict it by HTTP referrer, and allow the Maps JavaScript API. A separate `MAPS_SERVER_KEY` should be restricted by server IP to the Geocoding API and the Static Maps API.

### Guest account

Sign in at `3/signin.php` with:

- Username: `guest` (or email `guest@mail.com`, or phone `5555550100`)
- Password: `#guestPass`

The guest calculator is `1/prezure.html?key=Guest`.

This password is public so the demo can be opened without signing up. Do not deploy these files as a real service without replacing the guest account.

## Usage

Open the site and create an account from the marketing page, or sign in with the guest account. Signup collects name, email, password (at least 8 characters), phone, company, and address, then a standard price per square foot. Base, chemical, and heavy prices are optional. After agreeing to the terms, the confirmation screen shows the calculator link. The same link is on the dashboard home tab.

The dashboard has three tabs:

- **Home** shows the calculator link and recent estimates.
- **Estimates** lists that contractor's estimates and can delete them.
- **Settings** edits account details, password, pricing, and available days and hours.

Share `1/prezure.html?key=` plus the contractor's key. The customer enters an address, traces the work area, and can mark exclusion polygons. The quote uses the contractor's base fee and any rates above zero. If days and hours are set, the customer picks a time before the PDF is generated. Otherwise the PDF is generated without an appointment. Either way, the booking is posted to `1/submit.php`.

The contractor email links to `1/respond.php`. Confirming accept or decline updates the booking. If the customer entered an email address, they are notified. An accepted booking with a date and time includes a calendar file.

To find bookings and estimate files whose contractor id is gone:

```
php scripts/prune_orphans.php
```

That is a dry run. Add `--apply` to move orphaned bookings into `1/bookings.orphaned.json` and rename orphaned estimate files to `estimatesJSON/{id}.orphaned.json`. The script refuses to run over HTTP.

## Testing

```
php tests/sanitize_test.php
```

The script prints `ok` when every check passes and exits with status 1 otherwise. It covers `includes/sanitize.php`: stripping control characters, stripping header metacharacters, rejecting invalid emails, normalizing email case, keeping a 10-digit phone number, rejecting a short phone number, and the 8-character password rule.

## Deployment

Prefer real server environment variables over a committed `.env` file. Set `APP_EXT_URL` to the public HTTPS URL used in emails. Set `PREZURE_DATA_DIR` to a directory outside the web root, using the layout described above, and point `ErrorDocument` in `.htaccess` at the same prefix as `APP_BASE_URL`.

Replace the guest account before using the site as a real service. Configure `MAPS_API_KEY` with your own Google Cloud key. Use `mail` on hosts where PHP `mail()` works, or set `MAIL_TRANSPORT=smtp` and make PHPMailer loadable if you need SMTP.

## License

Released under the MIT License. See [LICENSE](LICENSE).
