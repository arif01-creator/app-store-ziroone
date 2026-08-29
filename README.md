# App Update Manager

A self-hosted Laravel app for privately distributing and updating several independent
Flutter Android apps to your own clients — no Play Store involved.

Each managed app has its own pool of registered devices. When you upload a new APK,
the server immediately queues a push notification to every device that has that app
installed, prompting a one-tap update.

---

## What this is not

- **Not a silent installer.** Android does not permit unattended installs for
  sideloaded apps without device-owner privileges or root. "Automatic update" here
  means: the server pushes the moment a build lands, and the client installs in a
  couple of taps. No WebView tricks, no root APIs.
- **Not multi-tenant.** One admin account, one developer. Clients never log in — the
  only thing they ever see is the public download page.
- **No iOS, no payments, no licence keys.**

---

## Requirements

| Component | Version used here |
|---|---|
| PHP | 8.4 (8.3+ required) |
| Laravel | 13 |
| MySQL | 9.7 (8.0+ fine) |
| Node | 24 (build-time only) |

---

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the database and point `.env` at it:

```sql
CREATE DATABASE app_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app_management
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate
php artisan storage:link
npm run build
```

Create your admin login:

```bash
php artisan app:create-admin
```

Run the queue worker — **the update push will not go out without it**:

```bash
php artisan queue:work
```

---

## PHP upload limits (required for APK uploads)

The app accepts APKs up to `APK_MAX_UPLOAD_KB` (default 204800 KB = 200 MB). PHP
rejects oversized uploads *before* the request reaches Laravel, so `php.ini` must be
raised to match or you will get a confusing empty-request error rather than a
validation message.

```ini
upload_max_filesize = 200M
post_max_size       = 210M   ; must exceed upload_max_filesize
max_execution_time  = 300
memory_limit        = 256M
```

Find your `php.ini` with `php --ini`, edit it, then restart PHP-FPM / your web server.
On Laravel Herd, use **Herd → PHP → open php.ini** for the active version.

If you also front the app with nginx, raise its own cap:

```nginx
client_max_body_size 210M;
```

Lower `APK_MAX_UPLOAD_KB` in `.env` if your host cannot be raised that far.

---

## Firebase Cloud Messaging

Push notifications use [`kreait/laravel-firebase`](https://github.com/kreait/laravel-firebase).

1. Firebase Console → **Project Settings → Service accounts → Generate new private key**.
2. Save the downloaded JSON to `storage/app/firebase/service-account.json`
   (that directory is gitignored — never commit this file).
3. Set the env vars:

```dotenv
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
FIREBASE_PROJECT_ID=your-firebase-project-id
```

The Flutter client must use the **same Firebase project**, and each app's
`package_id` must match the Android app registered in that project.

**Without credentials the app still works** — uploads succeed, devices register, and a
`push_logs` row is still written recording the attempt — but every send is counted as
failed and a warning is logged. This is deliberate: you can run the whole system
locally before wiring up Firebase.

---

## Storage layout

APKs are written to the disk named by `APK_DISK` (default `local`) at the
disk-relative path `apks/{app_id}/{version_code}.apk`. Laravel's `local` disk is
rooted at `storage/app/private`, so on disk that resolves to:

```
storage/app/private/apks/{app_id}/{version_code}.apk
```

Nothing is ever placed under `public/`, and there is no symlink into the APK
directory. The only ways to retrieve a binary are the gated download routes, all of
which stream through `Storage::download()`.

All disk access goes through `App\Services\ApkStorage`, so moving to S3 is a config
change plus an `s3` disk in `config/filesystems.php` — no business logic changes:

```dotenv
APK_DISK=s3
```

App icons are separate: they live on the `public` disk, because they are displayed on
the public download page and are not secret.

---

## Integrating a new Flutter client app

Three apps already integrate with this server (Collector 2.0, Member Passbook,
Dhaka Western Valley Customer Portal) — same file layout under
`lib/core/update/`, same server contract, same update-overlay UX, each adapted
to that app's own state management. To wire up another one, see
[`docs/CLIENT_APP_INTEGRATION_PROMPT.md`](docs/CLIENT_APP_INTEGRATION_PROMPT.md)
for a ready-to-paste prompt that reproduces the pattern.

---

## Admin panel

| Route | Purpose |
|---|---|
| `/dashboard` | Totals, outdated-device count, last 10 check-ins |
| `/apps` | App list with search |
| `/apps/create` | New app — slug auto-fills from the name, API key is minted server-side |
| `/apps/{app}` | The main working screen (below) |
| `/apps/{app}/edit` | Rename, re-slug, change icon/description, pause |

The app screen holds everything for one app:

- **Upload new version** — APK, version name, version code (pre-filled with
  `current max + 1`), release notes, force-update flag.
- **Version history** — size, truncated notes, force badge, push results, and a
  live/pulled toggle.
- **Devices** — client label (click to edit inline), model, Android version,
  installed version, `Outdated` / `Push disabled` badges, last seen. Searchable and
  filterable.
- **Share link** — the public URL plus a QR code and copy button.
- **API key** — masked by default, with the three endpoint URLs for this app.

### Pulling a bad build

Toggle a version off in the history table. It stays in the database (so history and
push logs survive) but is no longer offered by `/latest`, the public page, or the
download endpoint. Its `version_code` remains reserved — a replacement build must use
a higher one, because handsets in the field may already have installed it.

---

## Public download page

`GET /apps/{slug}/download` — no authentication.

Shows the icon, name, latest version name, size, release notes, and a **Download APK**
button that POSTs to a gated route streaming the file from the private disk.

It deliberately exposes **no** admin data: no device list, no package id, no version
history, and **not the API key**. The API key stays server-side and inside the Flutter
binary; the public page uses its own slug-scoped download route instead.

Returns 404 if the app is paused or the slug is unknown.

---

## Public API (for the Flutter clients)

Base: `/api/v1`. Scoped entirely by the per-app `api_key` — no session, no cookies.

### `POST /api/v1/apps/{api_key}/register`

Registration **and** periodic check-in. Call it on **every app launch**, not just first
install — this is how `last_seen_at`, the installed version, and a rotated FCM token
stay current. Upserts on `(app_id, install_uuid)`.

```jsonc
// request
{
  "install_uuid": "f0e1d2c3-…",   // required, client-generated, stable per install
  "fcm_token": "cZx…",             // nullable — omit if the user declined notifications
  "device_model": "Pixel 7",
  "android_version": "14",
  "version_code": 3,               // required, currently installed
  "version_name": "1.4.2"
}
```

```jsonc
// 201 on first registration, 200 on subsequent check-ins
{
  "registered": true,
  "is_new_device": false,
  "update_available": true,
  "latest": { "version_name": "1.5.0", "version_code": 4, "is_force_update": false }
}
```

### `GET /api/v1/apps/{api_key}/latest`

```jsonc
{
  "version_name": "1.5.0",
  "version_code": 4,
  "release_notes": "Fixed the sync bug.",
  "is_force_update": false,
  "file_size": 24117248,
  "download_url": "https://…/api/v1/apps/{api_key}/download/4"
}
```

404 if the app has no active build.

### `GET /api/v1/apps/{api_key}/download/{version_code}`

Streams the APK as `application/vnd.android.package-archive`.

### Status codes

| Code | Meaning |
|---|---|
| 200 / 201 | OK |
| 403 | Bad API key, paused app, or a `version_code` that doesn't belong to this app |
| 404 | No active version, or the file is missing from storage |
| 422 | Validation failed (`errors` keyed by field) |

A wrong `version_code` returns **403 rather than 404** on purpose — probing that
endpoint should not reveal which version codes exist.

### About the API key

It is a shared secret baked into one specific Flutter binary. Its job is to keep the
download URL from being guessable or crawlable — **not** to withstand a determined
attacker who has decompiled your APK. It is deliberately not over-engineered. Just
keep it off any public page.

---

## Push notification payload

`NotifyDevicesOfUpdate` is a queued job. It chunks reachable tokens into batches of
500 (FCM's multicast limit), skips devices already on the new build, and writes one
`push_logs` row per version with targeted / sent / failed counts. Tokens FCM reports
as invalid or unknown are nulled out, so those devices show as **Push disabled** in
the UI rather than silently never receiving anything.

Data payload the client receives:

```jsonc
{
  "type": "app_update",
  "app_slug": "ziroone-crm",
  "package_id": "com.ziroone.crm",
  "version_name": "1.5.0",
  "version_code": "4",
  "is_force_update": "0",
  "download_url": "https://…/api/v1/apps/{api_key}/download/4"
}
```

FCM data values are always strings — parse `version_code` and `is_force_update` on the
Flutter side.

### Devices with no FCM token

They still appear in the device list, flagged **Push disabled**. They will only
discover updates by calling `/latest` themselves on launch. Building that in-app check
UI is out of scope here, but nothing in the schema blocks it — `/latest` already
returns everything such a check needs.

---

## Testing

```bash
php artisan test
```

75 tests cover the three public API endpoints (including the 403 for a bad API key),
the upload flow and its strictly-increasing `version_code` rule, the push job and its
`push_logs` accounting, the public page's data isolation, and the admin screens.

Tests run against in-memory SQLite and fake both the filesystem and FCM, so no
Firebase credentials or MySQL instance is needed to run them.

---

## Schema notes

- `devices` is unique on `(app_id, install_uuid)` — the same handset can hold several
  of your apps and appears once per app.
- `app_versions` is unique on `(app_id, version_code)`.
- "Outdated" means `devices.current_version_code < MAX(app_versions.version_code)`
  among that app's **active** versions.
- `devices.is_active` and `app_versions.is_active` soft-retire rows without losing
  history; nothing in this app hard-deletes a device or a version.
