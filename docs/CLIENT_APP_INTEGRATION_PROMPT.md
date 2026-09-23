# Prompt: integrate a Flutter app with App Update Manager

Copy the whole block under **The prompt** into any AI coding agent (Claude
Code, Cursor, Copilot, and so on) opened on the Flutter app you want to wire
up. Fill in the bracketed values at the top, then send it. If you leave them
blank, the agent is told to ask you for them.

The prompt contains everything the integration needs: the server contract,
every client file with reference code, the state machine, the UI, the Android
build changes, the release doc, a checklist of what **you** have to do or
provide (PART 10), and a closing developer guide (PART 11). The agent fills
that guide in with the app's real values and prints it at the end: Firebase
setup and the version/build-number steps for every release. The agent does not need access to this repo or to any other
app. It is the pattern already running in
production in three apps (Collector 2.0, Member Passbook, and Dhaka Western
Valley Customer Portal). The shared behavior is written down once here, and the
prompt explains how to adapt it to the target app's state management and HTTP
client.

---

## The prompt

````text
Integrate this Flutter Android app with our self-hosted App Update Manager
(https://play.ziroone.com). Builds are distributed by that server instead of
the Play Store. The app has to check in with it, find out whether a newer APK
exists, download that APK itself, and hand it to Android's package installer.
An FCM push tells the app the moment a new build is uploaded.

This app's package id / applicationId: [PACKAGE_ID]
This app's display name:                [APP NAME]
Who uses it (for comments/copy, e.g. "officer", "member", "customer"): [USER NOUN]
(If any of these is still in [BRACKETS], ask me for it before starting. For
the package id, suggest the current applicationId from android/app/build.gradle.)

Everything you need is in this prompt. You do not need access to the update
server's source code or to any other app. Follow the prompt closely. It
describes a pattern that already runs in production in several apps. Port its
structure and behavior, and change only what this app's architecture forces
you to change (state management, HTTP client, i18n, theming).

How to work through it:
  Step 1  Do PART 0 (investigation) and report your findings to me. In the
          same message, show me the PART 10 checklist (what I must do or
          provide) so I can start on my side in parallel. Wait for my
          answers to the questions PART 0 says to ask.
  Step 2  Implement PARTS 2–8.
  Step 3  Verify against PART 9. Then give me the final summary, including
          the PART 10 checklist again with each item marked done, still
          needed from me, or not applicable.
  Step 4  End your final message with the DEVELOPER GUIDE from PART 11
          (Firebase setup + version/build number procedure), filled in with
          this app's real values: package id, file paths, current pubspec
          version, and the exact build command. Also make sure the same
          content is in RELEASE.md, so it stays with the repo.

======================================================================
PART 0 — INVESTIGATE FIRST, THEN REPORT BACK BEFORE WRITING CODE
======================================================================

Work out these points and tell me what you found:

  1. State management already in use: Riverpod / Provider / Bloc / GetX /
     plain setState / none. Match it. Do NOT add a state-management package
     just for this feature. If the app has none (or only setState), use a
     plain ChangeNotifier singleton (PART 4 gives it in full).
  2. HTTP client already in use: package:http or dio. Match it. Update calls
     must use a SEPARATE client/instance, never the app's main authenticated
     API client. Reasons: the update check must work while the user is
     signed out; the user's session/bearer token must never be sent to
     play.ziroone.com; and a 401/403 from the update server must never
     trigger the main client's "sign the user out" interceptor.
  3. Whether the app already has an i18n/translation system. If it does, add
     the update strings there (keys in PART 6) instead of hardcoding English.
  4. Where a manual "Check for updates" entry fits: a settings, profile, or
     about screen, or a drawer.
  5. How the root widget is built: does MaterialApp already use `builder:`?
     Does `home:` switch declaratively on auth state, or does the app
     navigate with push/pushReplacement from a splash screen? This decides
     where UpdateGate goes (PART 5).
  6. Whether the app already has a SharedPreferences wrapper or other local
     storage service, and whether logout calls `prefs.clear()` or wipes
     storage. The install UUID must SURVIVE logout (PART 3.4).
  7. Whether it already has an app exception class (for example
     ApiException). If it does, you may reuse it. If not, create
     UpdateException (PART 3.3).
  8. Which of these are already dependencies: http/dio, shared_preferences,
     permission_handler, path_provider, device_info_plus,
     package_info_plus, open_filex, firebase_core, firebase_messaging.
  9. The Android Gradle files: Kotlin DSL (build.gradle.kts) or Groovy
     (build.gradle), the current applicationId and namespace, and whether
     release signing is already configured.

Ask me before deciding anything that is mine to decide. That includes an
ambiguous answer to point 1 or 2, whether to reuse an existing signing
keystore or generate a new one, whether to change the applicationId if it
is still com.example.*, and whether to include Firebase/FCM in this pass.

======================================================================
PART 1 — THE SERVER CONTRACT (authoritative)
======================================================================

Base: {UPDATE_BASE_URL}/api/v1/apps/{api_key}/...
Authentication is the per-app api_key in the PATH. There are no headers,
cookies, or sessions. Always send `Accept: application/json`.

1) POST /api/v1/apps/{api_key}/register
   Registration AND periodic check-in. Call it on EVERY launch (and
   whenever the FCM token rotates), not only on first install. It is how the
   server keeps last_seen_at, the installed version, and the FCM token
   current. The server upserts on (app, install_uuid).

   Request JSON:
     install_uuid     string, REQUIRED, max 64. Client-generated, stable per install.
     fcm_token        string|null, max 4096. Null/omitted if there is no Firebase or permission was declined.
     device_model     string|null, max 255.  e.g. "samsung SM-A155F"
     android_version  string|null, max 50.   e.g. "14"
     version_code     int, REQUIRED, >= 1.   The INSTALLED Android versionCode.
     version_name     string|null, max 50.   e.g. "1.4.2"

   Response: 201 on the first registration, 200 on later check-ins:
     {
       "registered": true,
       "is_new_device": false,
       "update_available": true,
       "latest": { "version_name": "1.5.0", "version_code": 4, "is_force_update": false }
                 // or null if no active build exists
     }
   `latest` here is only a summary with NO download_url. To act on an update,
   call /latest.

2) GET /api/v1/apps/{api_key}/latest
   200:
     {
       "version_name": "1.5.0",
       "version_code": 4,
       "release_notes": "Fixed the sync bug.",     // may be null
       "is_force_update": false,
       "file_size": 24117248,                        // bytes, may be null
       "download_url": "https://play.ziroone.com/api/v1/apps/{api_key}/download/4"
     }
   404 { "message": "..." } means the app has no active build yet. That is a
   NORMAL state (a freshly created app), not an error. Treat it as "no update".

3) GET /api/v1/apps/{api_key}/download/{version_code}
   Streams the APK as application/vnd.android.package-archive, with
   Content-Length.

Status codes:
   200/201  OK
   403      Bad API key, app paused, or a version_code that doesn't belong to
            this app (deliberately 403, not 404)
   404      No active version (on /latest), or file missing from storage
   422      Validation failed: { "message": "...", "errors": { field: [...] } }
Error bodies are JSON with a "message" field. Show that message when the user
explicitly asked for the check.

FCM push, sent when a build is uploaded. It includes a notification
(title/body) plus a DATA payload. ALL data values are STRINGS:
   {
     "type": "app_update",
     "app_slug": "my-app",
     "package_id": "com.example.app",
     "version_name": "1.5.0",
     "version_code": "4",          // string!
     "is_force_update": "0",       // "1" or "0"
     "download_url": "https://.../download/4"
   }
The client treats a push only as a HINT to re-run the normal check. It never
trusts the version numbers in the payload. /latest is the single source of
truth, because a build can be pulled between the push and the tap.

Server-side facts the client relies on:
 - version_code must strictly increase per app. The server rejects an upload
   whose code is not higher than the previous max.
 - "Update available" means latest.version_code > installed version_code.
 - Pulling a bad build = the admin toggles it inactive. /latest then returns
   the previous active build (or 404), so clients stop being offered it.
 - The server nulls FCM tokens that FCM reports as invalid. The device then
   shows as "Push disabled" until the next check-in sends a valid token.

======================================================================
PART 2 — FILE LAYOUT
======================================================================

Create these files. Adjust the directory names only if the app has a clearly
different convention, and keep the files together.

  lib/core/update/update_config.dart     base URL + API key from --dart-define
  lib/core/update/update_exception.dart  error type (skip if reusing the app's own)
  lib/core/update/update_models.dart     AppRelease, CheckInResult, tolerant parsers
  lib/core/update/device_identity.dart   install_uuid + device info + installed version
  lib/core/update/update_api.dart        POST /register, GET /latest
  lib/core/update/apk_installer.dart     permission, streamed download, open installer
  lib/core/update/push_service.dart      Firebase init, token, app_update pushes (optional, never fatal)
  <state file>                           UpdateController + UpdateState (location per the app's convention:
                                         lib/core/update/update_controller.dart for ChangeNotifier,
                                         lib/providers/update_provider.dart for Riverpod, etc.)
  <widgets dir>/update_gate.dart         overlay wrapping the whole app
  + manual "Check for updates" entry in the settings/profile screen
  + main.dart: PushService.initialize() before runApp
  + Android: gradle, manifest, key.properties.example
  + dart_defines.example.json, .gitignore entries, RELEASE.md

Dependencies to add if missing (use current stable versions):
  http (or dio, whichever the app uses), shared_preferences,
  permission_handler, path_provider, device_info_plus, package_info_plus,
  open_filex, firebase_core, firebase_messaging

======================================================================
PART 3 — CORE FILES (reference implementation; port faithfully)
======================================================================

The code below is the production implementation, using package:http and a
standalone UpdateException. If the app uses dio, see PART 3.8 for the exact
differences. Keep the doc comments. They record WHY each decision was made,
and the next maintainer needs them.

---------------------------------------------------------------
3.1  update_config.dart
---------------------------------------------------------------
```dart
/// Where this build looks for its own updates.
///
/// Builds are distributed by our self-hosted App Update Manager rather than
/// the Play Store, so the app has to ask the server whether a newer APK
/// exists and then install it itself.
class UpdateConfig {
  UpdateConfig._();

  /// The App Update Manager instance serving this app's builds.
  static const String baseUrl = String.fromEnvironment(
    'UPDATE_BASE_URL',
    defaultValue: 'https://play.ziroone.com',
  );

  /// The per-app key minted by the server when the app was registered there.
  ///
  /// Passed at build time (`--dart-define=UPDATE_API_KEY=...`) so the secret
  /// never lands in version control. It ends up in the binary either way —
  /// its job is to keep the download URL unguessable, not to survive
  /// decompilation.
  static const String apiKey = String.fromEnvironment('UPDATE_API_KEY');

  /// False when the APK was built without `--dart-define=UPDATE_API_KEY`.
  /// Every entry point checks this and degrades to "updates unavailable"
  /// rather than firing doomed requests at the server on each launch.
  static bool get isConfigured => apiKey.isNotEmpty;

  static Uri endpoint(String path) =>
      Uri.parse('$baseUrl/api/v1/apps/$apiKey/$path');
}
```
Never hardcode the API key in any committed file.

---------------------------------------------------------------
3.2  update_models.dart
---------------------------------------------------------------
```dart
/// The build the server says clients should be running, as returned by
/// `GET /api/v1/apps/{api_key}/latest`.
class AppRelease {
  const AppRelease({
    required this.versionName,
    required this.versionCode,
    required this.releaseNotes,
    required this.isForceUpdate,
    required this.fileSize,
    required this.downloadUrl,
  });

  final String versionName;
  final int versionCode;
  final String? releaseNotes;
  final bool isForceUpdate;

  /// Bytes, or null when the server did not record a size.
  final int? fileSize;
  final String downloadUrl;

  factory AppRelease.fromJson(Map<String, dynamic> json) => AppRelease(
        versionName: (json['version_name'] ?? '').toString(),
        versionCode: _asInt(json['version_code']) ?? 0,
        releaseNotes: json['release_notes']?.toString(),
        isForceUpdate: _asBool(json['is_force_update']),
        fileSize: _asInt(json['file_size']),
        downloadUrl: (json['download_url'] ?? '').toString(),
      );

  bool isNewerThan(int installedVersionCode) => versionCode > installedVersionCode;
}

/// Reply to `POST /api/v1/apps/{api_key}/register`. Its `latest` block is a
/// summary only — it carries no download URL, so acting on
/// [updateAvailable] means a follow-up call to `/latest`.
class CheckInResult {
  const CheckInResult({required this.isNewDevice, required this.updateAvailable});

  final bool isNewDevice;
  final bool updateAvailable;

  factory CheckInResult.fromJson(Map<String, dynamic> json) => CheckInResult(
        isNewDevice: _asBool(json['is_new_device']),
        updateAvailable: _asBool(json['update_available']),
      );
}

/// FCM delivers every data value as a string, so `version_code` arrives as
/// "4" and `is_force_update` as "0" — hence the tolerant parsing here and in
/// [_asBool]. The same helpers cover the JSON API, where they are real types.
int? _asInt(Object? value) => switch (value) {
      int v => v,
      num v => v.toInt(),
      String v => int.tryParse(v),
      _ => null,
    };

bool _asBool(Object? value) => switch (value) {
      bool v => v,
      num v => v != 0,
      String v => v == '1' || v.toLowerCase() == 'true',
      _ => false,
    };
```
(If the app's Dart SDK is below 3.0, rewrite the switch expressions as
if/else chains. The behavior must stay identical.)

---------------------------------------------------------------
3.3  update_exception.dart  (skip only if reusing an existing ApiException
     that already has message / statusCode / isNetworkError)
---------------------------------------------------------------
```dart
class UpdateException implements Exception {
  UpdateException(this.message, {this.statusCode, this.isNetworkError = false});

  final String message;
  final int? statusCode;

  /// True when the request never reached the server (offline, DNS, timeout).
  final bool isNetworkError;

  @override
  String toString() => message;
}
```

---------------------------------------------------------------
3.4  device_identity.dart
---------------------------------------------------------------
Rules:
 - install_uuid: a random UUID v4, generated once and stored in
   SharedPreferences under the key 'update_install_uuid'. It must survive
   logout. If the app's logout calls prefs.clear() or wipes storage, change
   that code to preserve this key (or store the UUID somewhere logout doesn't
   touch). A UUID that changes produces duplicate device rows on the server
   for one handset.
 - If the app has a storage wrapper, add get/setInstallUuid to it instead of
   calling SharedPreferences directly. Keep the same key name.
 - versionCode comes from PackageInfo.buildNumber, parsed defensively.
 - Cache the result for the life of the process.

```dart
import 'dart:io';
import 'dart:math';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Everything `POST /register` needs to describe this handset and the build
/// currently sitting on it.
class DeviceIdentity {
  const DeviceIdentity({
    required this.installUuid,
    required this.deviceModel,
    required this.androidVersion,
    required this.versionCode,
    required this.versionName,
  });

  final String installUuid;
  final String? deviceModel;
  final String? androidVersion;

  /// The installed build. This is what the server compares against the
  /// latest active version to decide `update_available`.
  final int versionCode;
  final String versionName;

  static const _kInstallUuid = 'update_install_uuid';
  static DeviceIdentity? _cached;

  /// Cached after the first call — the platform channels behind
  /// device_info_plus and package_info_plus are not free, and none of these
  /// values can change while the process is alive.
  static Future<DeviceIdentity> load() async {
    final cached = _cached;
    if (cached != null) return cached;

    final packageInfo = await PackageInfo.fromPlatform();

    String? model;
    String? androidVersion;
    if (Platform.isAndroid) {
      final android = await DeviceInfoPlugin().androidInfo;
      model = '${android.manufacturer} ${android.model}'.trim();
      androidVersion = android.version.release;
    }

    final identity = DeviceIdentity(
      installUuid: await _installUuid(),
      deviceModel: model,
      androidVersion: androidVersion,
      // buildNumber is Android's versionCode. It comes back as a string and
      // can carry a suffix on some build setups, so parse defensively rather
      // than trusting it — a bad parse here would make every check-in claim
      // version 0 and pull an update on every launch.
      versionCode: int.tryParse(packageInfo.buildNumber.split('.').first) ?? 0,
      versionName: packageInfo.version,
    );

    _cached = identity;
    return identity;
  }

  /// Identifies this install to the update server. Generated once and kept
  /// across logouts — the server upserts devices on (app, install_uuid), so a
  /// value that changed would fill the device list with duplicates.
  static Future<String> _installUuid() async {
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString(_kInstallUuid);
    if (existing != null && existing.isNotEmpty) return existing;

    final generated = _uuidV4();
    await prefs.setString(_kInstallUuid, generated);
    return generated;
  }

  /// Random UUID v4. Hand-rolled rather than pulling in a package for one
  /// call — the value only has to be unique per install, and Random.secure()
  /// is a stronger source than anything that requirement needs.
  static String _uuidV4() {
    final rng = Random.secure();
    final bytes = List<int>.generate(16, (_) => rng.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
    bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant 1

    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }
}
```

---------------------------------------------------------------
3.5  update_api.dart  (package:http version)
---------------------------------------------------------------
```dart
import 'dart:convert';

import 'package:http/http.dart' as http;

import 'device_identity.dart';
import 'update_config.dart';
import 'update_exception.dart';
import 'update_models.dart';

/// Talks to the App Update Manager's public API. Separate from the app's own
/// API client because it hits a different host and authenticates with the
/// per-app key in the path rather than the user's bearer token — a user who
/// is logged out still needs to be able to take an update, and their token
/// must never be sent to the update server.
class UpdateApi {
  const UpdateApi();

  static const _timeout = Duration(seconds: 20);

  /// Registration *and* periodic check-in — call on every launch, not just
  /// first install. This is what keeps last_seen_at, the installed version
  /// and the FCM token current on the server.
  Future<CheckInResult> checkIn({String? fcmToken}) async {
    final device = await DeviceIdentity.load();

    final res = await _send(
      () => http.post(
        UpdateConfig.endpoint('register'),
        headers: const {'Accept': 'application/json', 'Content-Type': 'application/json'},
        body: jsonEncode({
          'install_uuid': device.installUuid,
          'fcm_token': fcmToken,
          'device_model': device.deviceModel,
          'android_version': device.androidVersion,
          'version_code': device.versionCode,
          'version_name': device.versionName,
        }),
      ),
    );

    return CheckInResult.fromJson(res);
  }

  /// The build clients should be running. Returns null when the app has no
  /// active build yet (the server answers 404), which is a normal state for a
  /// freshly created app rather than an error worth surfacing.
  Future<AppRelease?> latest() async {
    try {
      return AppRelease.fromJson(await _send(
        () => http.get(
          UpdateConfig.endpoint('latest'),
          headers: const {'Accept': 'application/json'},
        ),
      ));
    } on UpdateException catch (e) {
      if (e.statusCode == 404) return null;
      rethrow;
    }
  }

  Future<Map<String, dynamic>> _send(Future<http.Response> Function() request) async {
    if (!UpdateConfig.isConfigured) {
      throw UpdateException('This build has no update server key.');
    }

    http.Response res;
    try {
      res = await request().timeout(_timeout);
    } catch (_) {
      throw UpdateException('Cannot reach the update server.', isNetworkError: true);
    }

    Map<String, dynamic> data = {};
    try {
      final decoded = jsonDecode(res.body);
      if (decoded is Map) data = Map<String, dynamic>.from(decoded);
    } catch (_) {
      // Non-JSON body (an HTML error page from a proxy, say) — fall through
      // with {} and let the status check below produce the message.
    }

    if (res.statusCode < 200 || res.statusCode >= 300) {
      throw UpdateException(
        (data['message'] ?? 'Update server error (${res.statusCode})').toString(),
        statusCode: res.statusCode,
      );
    }
    return data;
  }
}
```

---------------------------------------------------------------
3.6  apk_installer.dart  (package:http version)
---------------------------------------------------------------
Non-negotiable behaviors:
 - Request REQUEST_INSTALL_PACKAGES ("install unknown apps") through
   permission_handler. On Android 8+ this opens a Settings page, not a
   dialog.
 - STREAM the download to disk (getApplicationSupportDirectory()/updates).
   Never buffer the whole APK in memory, because cheap handsets get
   OOM-killed.
 - Write to "<code>.apk.part" and rename to "<code>.apk" only after the
   received byte count matches Content-Length (or file_size). A truncated
   download must never sit at the final path.
 - Delete old APKs from the updates dir before each download.
 - Rewrite download_url's host to UpdateConfig.baseUrl's host if they
   differ. The server builds that URL from its own APP_URL, which can be
   stale or misconfigured.
 - Open the system installer with open_filex, MIME
   application/vnd.android.package-archive. "Success" only means Android
   accepted the file. Android never reports whether the install finished.

```dart
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';

import 'update_config.dart';
import 'update_exception.dart';
import 'update_models.dart';

/// Download progress, 0.0–1.0, or null while the server has not told us how
/// big the file is (no Content-Length and no recorded file_size).
typedef DownloadProgress = void Function(double? fraction, int receivedBytes);

/// Fetches an APK from the update server and hands it to Android's package
/// installer.
///
/// Android does not permit unattended installs for sideloaded apps, so this
/// gets as far as the system install screen and the user taps through the
/// last two steps. Anything claiming to do better needs device-owner
/// privileges or root.
class ApkInstaller {
  const ApkInstaller();

  /// Android 8+ gates the install intent behind a per-app "install unknown
  /// apps" toggle. Requesting it opens the Settings page for this app;
  /// there is no in-place dialog, so the user leaves and comes back.
  Future<bool> ensureInstallPermission() async {
    if (!Platform.isAndroid) return false;
    if (await Permission.requestInstallPackages.isGranted) return true;
    return (await Permission.requestInstallPackages.request()).isGranted;
  }

  /// Streams the APK to app-private storage and returns the file.
  ///
  /// Streamed rather than buffered because these builds run to tens of MB and
  /// users' handsets are cheap — holding the whole binary in memory
  /// alongside the running app is how you get an OOM kill mid-update.
  Future<File> download(AppRelease release, {DownloadProgress? onProgress}) async {
    final dir = Directory('${(await getApplicationSupportDirectory()).path}/updates');
    await dir.create(recursive: true);
    await _clearStaleApks(dir);

    // Partial file under its own name, renamed only once the byte count
    // checks out — an interrupted download must never be left sitting at the
    // final path where the next attempt would hand it to the installer.
    final target = File('${dir.path}/${release.versionCode}.apk');
    final partial = File('${target.path}.part');

    final client = http.Client();
    try {
      final res = await client
          .send(http.Request('GET', _resolveDownloadUrl(release)))
          .timeout(const Duration(seconds: 60));

      if (res.statusCode != 200) {
        throw UpdateException(
          'The update server refused the download (${res.statusCode}).',
          statusCode: res.statusCode,
        );
      }

      final total = res.contentLength ?? release.fileSize;
      var received = 0;
      final sink = partial.openWrite();
      try {
        await for (final chunk in res.stream) {
          sink.add(chunk);
          received += chunk.length;
          onProgress?.call(
            total == null || total <= 0 ? null : (received / total).clamp(0.0, 1.0),
            received,
          );
        }
        await sink.flush();
      } finally {
        await sink.close();
      }

      // A truncated body is the common failure on a flaky mobile connection,
      // and it arrives looking like success. Catching it here turns a
      // baffling "package appears to be invalid" from the system installer
      // into a retryable error.
      if (total != null && total > 0 && received != total) {
        await partial.delete();
        throw UpdateException('The download was cut short. Try again on a better connection.');
      }

      if (await target.exists()) await target.delete();
      return await partial.rename(target.path);
    } on UpdateException {
      rethrow;
    } catch (_) {
      if (await partial.exists()) await partial.delete();
      throw UpdateException('Cannot reach the update server.', isNetworkError: true);
    } finally {
      client.close();
    }
  }

  /// Opens the system installer for [apk]. Returns an error message on
  /// failure, or null once the installer has been handed the file — success
  /// here means "Android took it", not "the user completed the install".
  Future<String?> install(File apk) async {
    final result = await OpenFilex.open(
      apk.path,
      type: 'application/vnd.android.package-archive',
    );
    return result.type == ResultType.done ? null : result.message;
  }

  /// Old APKs are dead weight — each is tens of MB on a handset that is
  /// usually short of space, and nothing ever reads them again.
  Future<void> _clearStaleApks(Directory dir) async {
    try {
      await for (final entry in dir.list()) {
        if (entry is File && entry.path.contains('.apk')) {
          await entry.delete();
        }
      }
    } catch (_) {
      // Best-effort cleanup; a locked or vanished file must not abort an
      // update the user is waiting on.
    }
  }

  /// The server builds `download_url` from its own `APP_URL`. If that setting
  /// is stale the URL points at a host we cannot reach, so keep the path but
  /// send it to the host this build is actually configured for.
  Uri _resolveDownloadUrl(AppRelease release) {
    final base = Uri.parse(UpdateConfig.baseUrl);
    final raw = Uri.tryParse(release.downloadUrl);

    if (raw == null || !raw.hasScheme || raw.host.isEmpty) {
      return UpdateConfig.endpoint('download/${release.versionCode}');
    }
    if (raw.host == base.host) return raw;
    return raw.replace(scheme: base.scheme, host: base.host, port: base.port);
  }
}
```

---------------------------------------------------------------
3.7  push_service.dart
---------------------------------------------------------------
FCM is OPTIONAL and must NEVER be fatal. A build with no
android/app/google-services.json must still run: initialize() logs and
returns, token() returns null, and the launch-time check is the only update
path. Handle all three arrival paths: foreground (onMessage), tapped from
background (onMessageOpenedApp), and tapped from a cold start
(getInitialMessage). Expose token rotations so they reach the server.

If the app ALREADY uses Firebase/FCM for other messages, do not initialize
Firebase twice or replace its existing handlers. Add the `type ==
'app_update'` branch to the existing listeners and forward it to the same
stream.

```dart
import 'dart:async';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

/// Firebase Cloud Messaging, used for one thing only: telling handsets that a
/// new build has landed, the moment it is uploaded, instead of waiting for the
/// user to next restart the app.
///
/// Every part of this is optional by design. A build with no
/// `android/app/google-services.json` still runs — [initialize] records the
/// failure, [token] returns null, and the launch-time check in
/// UpdateController remains the only path to an update. That is the same
/// stance the server takes: uploads and registrations work whether or not
/// Firebase credentials are configured.
class PushService {
  PushService._();

  static bool _available = false;

  /// True once Firebase started and this platform can receive pushes.
  static bool get isAvailable => _available;

  /// Data-message pushes of `type: app_update`, from all three arrival
  /// paths — foreground, notification tapped from background, and
  /// notification tapped from a cold start.
  ///
  /// The payload itself is deliberately ignored downstream: it is a hint that
  /// something changed, and the client re-asks `/latest` for the truth rather
  /// than trusting version numbers that arrived over the wire.
  static Stream<void> get updatePushes => _updatePushes.stream;
  static final _updatePushes = StreamController<void>.broadcast();

  /// Fires when FCM rotates this device's token, so the new one can be sent
  /// up on the next check-in. Without this, a rotated token leaves the device
  /// permanently unreachable — the server would keep pushing to the dead one.
  static Stream<String> get tokenRefreshes =>
      _available ? FirebaseMessaging.instance.onTokenRefresh : const Stream.empty();

  /// Safe to call unconditionally at startup; never throws.
  static Future<void> initialize() async {
    if (!Platform.isAndroid) return;

    try {
      await Firebase.initializeApp();
      _available = true;
    } catch (e) {
      // Almost always a missing or mismatched google-services.json. Not fatal:
      // the app runs, it just cannot be told about updates out of band.
      debugPrint('PushService: Firebase unavailable, update pushes disabled ($e)');
      return;
    }

    try {
      // Android 13+ needs the runtime notification permission. Declining it
      // costs the user only the instant prompt — the server still sends,
      // and the launch check still finds the build.
      await FirebaseMessaging.instance.requestPermission();

      FirebaseMessaging.onMessage.listen(_handle);
      FirebaseMessaging.onMessageOpenedApp.listen(_handle);

      // Opened from a notification while the app was not running. Fetched
      // once, since it stays set for the life of the process.
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) _handle(initial);
    } catch (e) {
      debugPrint('PushService: could not attach message handlers ($e)');
    }
  }

  /// Null when Firebase is unavailable or the token is not yet issued, in
  /// which case the device registers without one and shows as "Push disabled"
  /// in the admin panel.
  static Future<String?> token() async {
    if (!_available) return null;
    try {
      return await FirebaseMessaging.instance.getToken();
    } catch (e) {
      debugPrint('PushService: could not read FCM token ($e)');
      return null;
    }
  }

  static void _handle(RemoteMessage message) {
    if (message.data['type'] == 'app_update') _updatePushes.add(null);
  }
}
```

In main.dart:
```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // ...existing init...
  // Never throws — a build without Firebase config simply has no update
  // pushes, and UpdateGate's launch-time check remains the only path.
  await PushService.initialize();
  runApp(...);
}
```
Note that `updatePushes` is a broadcast stream. A cold-start message is added
before the controller subscribes, so the controller would miss it. That is
fine, because the gate runs a launch check right after the first frame
anyway.

---------------------------------------------------------------
3.8  If the app uses DIO instead of package:http
---------------------------------------------------------------
UpdateApi: create a private `Dio` in the constructor. Never reuse the app's
Dio, which carries auth interceptors and a different baseUrl.
```dart
UpdateApi()
    : _dio = Dio(BaseOptions(
        headers: const {'Accept': 'application/json', 'Content-Type': 'application/json'},
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 20),
        // Non-2xx is handled explicitly — /latest answering 404 is a normal
        // state, not an exception worth unwinding for.
        validateStatus: (_) => true,
      ));
```
 - checkIn: `_dio.post(UpdateConfig.endpoint('register').toString(), data: {...map...})`
 - latest: on status 404 return null; otherwise parse `res.data` (already
   decoded; guard with `body is Map`).
 - Any thrown DioException (no response) → UpdateException('Cannot reach
   the update server.', isNetworkError: true).
 - Non-2xx → UpdateException(data['message'] ?? 'Update server error (N)',
   statusCode: N).
ApkInstaller: use a private Dio with connectTimeout 30s and receiveTimeout
10 minutes (large files over slow mobile data). Use
`_dio.download(url, partialPath, onReceiveProgress: (received, total) {...})`.
Dio reports total = -1 when there is no Content-Length, so fall back to
release.fileSize and report null progress if neither is known. On
DioException: delete the .part file and throw UpdateException, using
"refused the download (status)" when e.response != null and "download
failed, try again on a better connection" when it is null. After a
successful download, verify the byte count and do the .part → .apk rename
exactly as in the http version.

======================================================================
PART 4 — STATE MACHINE (UpdateController + UpdateState)
======================================================================

Stages:
  idle               nothing to show (no newer build, or an optional one was dismissed)
  checking           check-in + /latest in flight
  available          newer build found → show offer
  downloading        progress 0.0–1.0, or null = indeterminate
  handedToInstaller  system installer opened (Android never reports the outcome)
  failed             error; if release != null the offer stays up with "Try again"

Rules. Every one of them must be preserved whatever the state-management
flavor:
 1. checkForUpdate({bool surfaceErrors = false}):
    - return immediately if !UpdateConfig.isConfigured or !Platform.isAndroid
    - return if a check is already running (bool _checking guard, because a
      push can arrive during the launch check)
    - return if currently downloading (a push must never reset a download
      and strand the partial file)
    - ALWAYS check in first (so the launch is recorded even when there is
      no update), passing await PushService.token(); THEN call /latest;
      THEN compare with DeviceIdentity.versionCode
    - no release, or not newer → idle
    - newer → available(release)
    - on error: surfaceErrors ? failed(error) : idle. The launch check and
      push-triggered checks are SILENT. An unreachable update server must
      never interrupt the user.
 2. downloadAndInstall(): ensure the install permission, or fail with 'Allow
    "install unknown apps" for this app, then try again.'. Then downloading
    with progress, then install(). The installer returning an error →
    failed. Success → handedToInstaller. Keep `release` in state across
    every transition so "Try again" works.
 3. dismiss(): a no-op when release.isForceUpdate. Otherwise → idle
    (postponed until the next launch or push).
 4. isBlocking = release.isForceUpdate && stage not in {idle, checking}.
    A FAILED forced update is still blocking, but the Try again button is
    always present, so the user is never stuck without a way out.
 5. Subscribe to PushService.updatePushes → checkForUpdate(). Subscribe to
    PushService.tokenRefreshes → _api.checkIn(fcmToken: token), with errors
    swallowed and logged via debugPrint.
 6. copyWith deliberately resets `progress` and `error` to the passed value
    (null if omitted). Keep that. It prevents a stale error or progress
    value from leaking into the next stage.

Reference: ChangeNotifier singleton (use this when the app has no
state-management package, or uses package:provider, in which case you may
also expose it with ChangeNotifierProvider.value):

```dart
import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';

import 'apk_installer.dart';
import 'device_identity.dart';
import 'push_service.dart';
import 'update_api.dart';
import 'update_config.dart';
import 'update_exception.dart';
import 'update_models.dart';

enum UpdateStage {
  /// Nothing to show. Either no newer build exists, or the user dismissed
  /// an optional one.
  idle,
  checking,
  available,
  downloading,

  /// The APK is on disk and the system installer has been opened. Android
  /// never tells us how that ended, so this is as far as the state machine
  /// can honestly go.
  handedToInstaller,
  failed,
}

class UpdateState {
  const UpdateState({
    this.stage = UpdateStage.idle,
    this.release,
    this.progress,
    this.receivedBytes = 0,
    this.error,
  });

  final UpdateStage stage;
  final AppRelease? release;

  /// 0.0–1.0, or null when the size is unknown and the bar must be
  /// indeterminate.
  final double? progress;
  final int receivedBytes;
  final String? error;

  /// A force update blocks the app until it is taken. Only true while there
  /// really is a newer build to take.
  bool get isBlocking =>
      (release?.isForceUpdate ?? false) &&
      stage != UpdateStage.idle &&
      stage != UpdateStage.checking;

  bool get isBusy => stage == UpdateStage.checking || stage == UpdateStage.downloading;

  UpdateState copyWith({
    UpdateStage? stage,
    AppRelease? release,
    double? progress,
    int? receivedBytes,
    String? error,
  }) =>
      UpdateState(
        stage: stage ?? this.stage,
        release: release ?? this.release,
        progress: progress,
        receivedBytes: receivedBytes ?? this.receivedBytes,
        error: error,
      );
}

/// Drives the whole self-update cycle: check in with the update server, offer
/// the new build, download it, hand it to Android's installer.
///
/// A plain [ChangeNotifier] singleton so the update feature stays
/// self-contained without pulling a state-management dependency into the
/// whole app.
class UpdateController extends ChangeNotifier {
  UpdateController._() {
    _listenForPushes();
  }

  static final UpdateController instance = UpdateController._();

  final _api = const UpdateApi();
  final _installer = const ApkInstaller();

  UpdateState _state = const UpdateState();
  UpdateState get state => _state;

  StreamSubscription<void>? _pushSub;
  StreamSubscription<String>? _tokenSub;

  /// Guards against two checks racing — a push landing while the launch
  /// check is still in flight would otherwise run the whole flow twice.
  bool _checking = false;

  void _set(UpdateState next) {
    _state = next;
    notifyListeners();
  }

  @override
  void dispose() {
    _pushSub?.cancel();
    _tokenSub?.cancel();
    super.dispose();
  }

  void _listenForPushes() {
    _pushSub = PushService.updatePushes.listen((_) => checkForUpdate());

    // A rotated token is only useful to the server, so push it up with an
    // ordinary check-in rather than inventing a second endpoint for it.
    _tokenSub = PushService.tokenRefreshes.listen((token) async {
      try {
        await _api.checkIn(fcmToken: token);
      } catch (e) {
        debugPrint('UpdateController: token refresh check-in failed ($e)');
      }
    });
  }

  /// Called on launch and whenever a push arrives.
  ///
  /// Silent by default: an update server that is unreachable is not the
  /// user's problem and must not interrupt the app, so failures leave the
  /// UI at [UpdateStage.idle]. The manual "check for updates" entry point
  /// passes `surfaceErrors: true` because there the user asked and
  /// deserves an answer either way.
  Future<void> checkForUpdate({bool surfaceErrors = false}) async {
    if (!UpdateConfig.isConfigured || !Platform.isAndroid) return;
    if (_checking) return;

    // Never interrupt a download in progress — a push arriving mid-download
    // would otherwise reset the state and strand the partial file.
    if (_state.stage == UpdateStage.downloading) return;

    _checking = true;
    _set(_state.copyWith(stage: UpdateStage.checking, release: _state.release));

    try {
      // Check in first so this launch is recorded even when no update
      // exists; that is what keeps last_seen_at and the installed version
      // honest in the admin panel.
      await _api.checkIn(fcmToken: await PushService.token());

      final release = await _api.latest();
      final device = await DeviceIdentity.load();

      if (release == null || !release.isNewerThan(device.versionCode)) {
        _set(const UpdateState());
        return;
      }
      _set(UpdateState(stage: UpdateStage.available, release: release));
    } on UpdateException catch (e) {
      _set(surfaceErrors
          ? UpdateState(stage: UpdateStage.failed, error: e.message)
          : const UpdateState());
    } catch (e) {
      _set(surfaceErrors
          ? UpdateState(stage: UpdateStage.failed, error: e.toString())
          : const UpdateState());
    } finally {
      _checking = false;
    }
  }

  /// Downloads the offered build and opens the system installer.
  ///
  /// Returns a message to show the user when something went wrong before
  /// the installer opened, or null on success.
  Future<String?> downloadAndInstall() async {
    final release = _state.release;
    if (release == null || _state.stage == UpdateStage.downloading) return null;

    if (!await _installer.ensureInstallPermission()) {
      const message = 'Allow "install unknown apps" for this app, then try again.';
      _set(_state.copyWith(stage: UpdateStage.failed, release: release, error: message));
      return message;
    }

    _set(_state.copyWith(stage: UpdateStage.downloading, release: release, progress: 0));

    try {
      final apk = await _installer.download(
        release,
        onProgress: (fraction, received) {
          _set(_state.copyWith(
            stage: UpdateStage.downloading,
            release: release,
            progress: fraction,
            receivedBytes: received,
          ));
        },
      );

      final failure = await _installer.install(apk);
      if (failure != null) {
        _set(_state.copyWith(stage: UpdateStage.failed, release: release, error: failure));
        return failure;
      }

      _set(_state.copyWith(stage: UpdateStage.handedToInstaller, release: release));
      return null;
    } on UpdateException catch (e) {
      _set(_state.copyWith(stage: UpdateStage.failed, release: release, error: e.message));
      return e.message;
    } catch (e) {
      _set(_state.copyWith(stage: UpdateStage.failed, release: release, error: e.toString()));
      return e.toString();
    }
  }

  /// Postpone an optional update until the next launch or push. Forced
  /// updates ignore this — the whole point is that they cannot be waved away.
  void dismiss() {
    if (_state.release?.isForceUpdate ?? false) return;
    _set(const UpdateState());
  }
}
```

Porting to other state management. The logic stays identical and only the
container changes:
 - Riverpod: `class UpdateController extends StateNotifier<UpdateState>`
   (or Notifier<UpdateState> if the app uses the Riverpod 2+ Notifier API).
   Replace `_set(x)` with `state = x`. Subscribe to push streams in the
   constructor (or build()) and cancel them in dispose (or ref.onDispose).
   In the onProgress callback, add `if (!mounted) return;` before writing
   state. Put it at lib/providers/update_provider.dart as
   `final updateProvider = StateNotifierProvider<UpdateController,
   UpdateState>((ref) => UpdateController());`. It must be a single
   app-wide provider: no autoDispose, no family.
 - Bloc/Cubit: `class UpdateCubit extends Cubit<UpdateState>` with
   `emit(x)` and an `if (isClosed) return;` guard in onProgress. Provide
   it once above MaterialApp.
 - GetX: a GetxController with an Rx<UpdateState>, registered permanently.
   Or simply use the ChangeNotifier version.

======================================================================
PART 5 — UpdateGate (the overlay) + placement
======================================================================

UpdateGate wraps the ENTIRE app and renders the update sheet as a Stack
overlay driven by state. It is NOT a pushed route and NOT showDialog, for
two reasons. A forced update has to survive any navigation the user
attempts. And a state-driven overlay cannot show itself twice when the
launch check and a push land together, which showDialog-from-a-listener
does.

Placement:
 - DEFAULT: `MaterialApp(builder: (context, child) => UpdateGate(child: child!))`.
   The builder wraps the Navigator itself, so the overlay survives every
   push/pushReplacement. If the app already has a `builder:` (for example a
   MediaQuery text-scale clamp), compose them: wrap the existing result, or
   nest UpdateGate inside it.
 - ALTERNATIVE: wrap `home:` ONLY IF home switches screens declaratively
   (for example `home: UpdateGate(child: switch (auth.status) {...})`) and
   the app never replaces the home route. Otherwise a pushReplacement from a
   splash screen tears down the gate.
 - The gate must cover the signed-out screens too (splash/login). A build
   can be pulled and replaced while someone is sitting on the login screen.

Behavior:
 - initState: run `checkForUpdate()` in addPostFrameCallback, so the silent
   check never delays the first screen appearing.
 - Show the sheet when stage ∈ {available, downloading, handedToInstaller},
   or stage == failed AND release != null. A bare failed CHECK (no release)
   is never shown in the overlay; the manual check entry reports it.
 - Scrim: full-screen semi-opaque Material (e.g. Color(0xB30F172A)). Tapping
   the scrim calls dismiss() only when !isBlocking. The card swallows its
   own taps (inner GestureDetector(onTap: () {})) so a stray tap during a
   download never lands on the screen underneath.
 - Card, maxWidth 420, centered, padded:
     header: icon (priority_high for forced in error color,
             system_update otherwise in primary color) + title
             "Update required" / "Update available"
     subtitle: "<versionName>  ·  <X.X> MB" (size omitted if null/0)
     forced only: hint "This update must be installed before you can carry on."
     release notes if non-empty: "What's new" + text, scrollable, max height 160
     body by stage:
       available          → [Update now] (+ [Later] if dismissible)
       downloading        → LinearProgressIndicator(value: progress) (null =
                            indeterminate), then "Downloading...  NN%"
       handedToInstaller  → "The install screen is open. Follow the steps
                            there to finish." + [Try again] (reopens the
                            installer; useful when the user backed out) + [Later]
                            if dismissible
       failed             → error text in error color + [Try again] + [Later]
                            if dismissible
     All primary buttons call downloadAndInstall(). "Later" is a muted
     TextButton that calls dismiss().
 - Use the app's own theme tokens and design system (colors, spacing,
   radii, button styles). If it has none, use Theme.of(context).colorScheme.
   Never hardcode a new palette.
 - Strings come from the app's i18n system (PART 6), if it has one.

Reference structure (ChangeNotifier flavor; for Riverpod use a
ConsumerStatefulWidget + ref.watch(updateProvider) + ref.read(updateProvider.notifier)):

```dart
class UpdateGate extends StatefulWidget {
  const UpdateGate({super.key, required this.child});
  final Widget child;

  @override
  State<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends State<UpdateGate> {
  @override
  void initState() {
    super.initState();
    // After the first frame: the check is silent and must not delay or
    // block the first screen appearing.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      UpdateController.instance.checkForUpdate();
    });
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: UpdateController.instance,
      builder: (context, _) {
        final update = UpdateController.instance.state;
        final showSheet = switch (update.stage) {
          UpdateStage.available ||
          UpdateStage.downloading ||
          UpdateStage.handedToInstaller => true,
          // A failure is only worth an overlay when there is still an update
          // to retry. A bare failed check belongs wherever the user asked
          // for it (the manual "check for updates" entry).
          UpdateStage.failed => update.release != null,
          _ => false,
        };
        return Stack(
          children: [
            widget.child,
            if (showSheet)
              Positioned.fill(
                child: _UpdateSheet(state: update, dismissible: !update.isBlocking),
              ),
          ],
        );
      },
    );
  }
}

// _UpdateSheet: Material(color: scrim) > GestureDetector(onTap: dismissible ? dismiss : null)
//   > Center > GestureDetector(onTap: () {}) > Padding > ConstrainedBox(maxWidth: 420)
//   > Container(card) > Column(mainAxisSize.min, crossAxisAlignment.stretch)
//   with the header / subtitle / hint / notes / stage body described above.
// Size helper:
//   static String _size(int? bytes) =>
//       (bytes == null || bytes <= 0) ? '' : '  ·  ${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
```
Because MaterialApp.builder sits above the Navigator, the sheet must not rely
on Navigator, ScaffoldMessenger, or showDialog. It is plain widgets, which is
what makes it safe there. If it needs Directionality/Theme, those are
available inside MaterialApp.builder.

======================================================================
PART 6 — MANUAL "CHECK FOR UPDATES" + STRINGS
======================================================================

Put the entry in the screen chosen in PART 0.4. Show the installed version
(from DeviceIdentity.load() → versionName (+versionCode if useful)) and a
button:
 - label "Check for updates", and "Checking..." while stage == checking
   (button disabled then)
 - on press:
     if (!UpdateConfig.isConfigured) → show "This build has no update server configured." as an error toast/snackbar; return
     await controller.checkForUpdate(surfaceErrors: true); if (!mounted) return;
     switch (state.stage):
       idle                        → toast "You are on the latest version."
       failed when release == null → error toast with state.error
       otherwise                   → nothing (UpdateGate is already showing the offer)
Use the app's existing toast/snackbar helper.

i18n keys. Add them to every language the app supports and translate them
properly. Use the app's key-naming style (snake_case or camelCase):
  check_for_updates        Check for updates
  update_checking          Checking...
  update_up_to_date        You are on the latest version.
  update_available         Update available
  update_required          Update required
  update_required_hint     This update must be installed before you can carry on.
  update_whats_new         What's new
  update_now               Update now
  update_later             Later
  update_retry             Try again
  update_downloading       Downloading...
  update_installer_opened  The install screen is open. Follow the steps there to finish.
  update_unavailable       This build has no update server configured.
  update_check_failed      Could not check for updates.   (fallback when error is empty)
(Error messages produced inside UpdateApi/ApkInstaller stay English unless the
app already localizes exception messages.)

======================================================================
PART 7 — ANDROID
======================================================================

7.1 android/app/src/main/AndroidManifest.xml (add xmlns:tools to <manifest>):
```xml
<uses-permission android:name="android.permission.INTERNET"/>

<!-- In-app updates. Builds come from our own play.ziroone.com rather than
     the Play Store, so the app downloads its own APK and hands it to the
     system installer. POST_NOTIFICATIONS is for the FCM "new build
     available" push on Android 13+. -->
<uses-permission android:name="android.permission.REQUEST_INSTALL_PACKAGES"/>
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>

<!-- open_filex declares broad media-read permissions for its general
     file-opening use case. We only ever hand it our own APK from app
     storage, so strip them from the merged manifest. -->
<uses-permission android:name="android.permission.READ_MEDIA_IMAGES" tools:node="remove"/>
<uses-permission android:name="android.permission.READ_MEDIA_VIDEO" tools:node="remove"/>
<uses-permission android:name="android.permission.READ_MEDIA_AUDIO" tools:node="remove"/>
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" tools:node="remove"/>
```
Before adding the tools:node="remove" lines, CHECK whether anything else in
the app (image_picker, file_picker, gallery features) needs those
permissions. Strip only the ones nothing else uses, and tell me which you
kept.

7.2 Google Services plugin. It is applied CONDITIONALLY so the build never
hard-requires Firebase.
  settings.gradle(.kts) plugins block:
    id("com.google.gms.google-services") version "4.4.x" apply false
  app/build.gradle(.kts), after the plugins block:
```kotlin
// Firebase Cloud Messaging, applied only once the config file is present.
// The plugin hard-fails the build when google-services.json is missing, and
// that file is per-Firebase-project rather than per-checkout — gating it here
// keeps the app buildable before FCM is wired up, in which case the client
// falls back to checking for updates on launch. See PushService.
if (file("google-services.json").exists()) {
    apply(plugin = "com.google.gms.google-services")
}
```
  (For Groovy: `if (file("google-services.json").exists()) { apply plugin: "com.google.gms.google-services" }`)

7.3 Release signing from key.properties, with a LOUD fallback to debug keys
(never a silent one). Android refuses to install an update signed with a
different key than the installed build.
```kotlin
import java.util.Properties

// Release signing lives outside version control. Copy key.properties.example
// to android/key.properties and point it at your own keystore — see RELEASE.md.
//
// This must never silently fall back to the debug keystore: Android refuses
// to install an update signed with a different key than the installed build,
// so a debug-signed release shipped to handsets can only ever be replaced by
// builds from the same machine's ~/.android/debug.keystore.
val keystorePropertiesFile = rootProject.file("key.properties")
val keystoreProperties = Properties().apply {
    if (keystorePropertiesFile.exists()) {
        keystorePropertiesFile.inputStream().use { load(it) }
    }
}
val hasReleaseKeystore = keystoreProperties.getProperty("storeFile") != null

android {
    // ...namespace / applicationId = "[PACKAGE_ID]" ...
    // versionCode = flutter.versionCode, versionName = flutter.versionName

    signingConfigs {
        if (hasReleaseKeystore) {
            create("release") {
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
                storeFile = rootProject.file(keystoreProperties.getProperty("storeFile"))
                storePassword = keystoreProperties.getProperty("storePassword")
            }
        }
    }

    buildTypes {
        release {
            if (hasReleaseKeystore) {
                signingConfig = signingConfigs.getByName("release")
            } else {
                // Debug keys, so `flutter run --release` still works locally.
                // Builds distributed through play.ziroone.com must be signed
                // with the real keystore or handsets cannot update in place.
                signingConfig = signingConfigs.getByName("debug")
                logger.warn(
                    "WARNING: android/key.properties not found — this release APK is signed " +
                        "with DEBUG keys and must not be uploaded to play.ziroone.com.",
                )
            }
        }
    }
}
```
If release signing already exists, adapt it to this shape rather than adding
a second mechanism.

7.4 If applicationId is still com.example.*, ask me before changing it. If
it changes, both namespace and applicationId change, the Kotlin
MainActivity package directory may need to move, and handsets treat it as a
different app (old test builds stay installed side by side). Record that in
RELEASE.md.

7.5 android/key.properties.example:
```properties
# Copy to android/key.properties (gitignored) and fill in your own values.
#
# Generate the keystore once, and keep it somewhere safe and backed up — if it
# is lost, no future build can update an installed app; every user would have
# to uninstall and reinstall by hand.
#
#   keytool -genkey -v -keystore C:/Users/<you>/<app-alias>.jks \
#     -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 \
#     -alias <app-alias>
#
# storeFile is resolved relative to the android/ directory, so an absolute
# path is usually clearest.

storePassword=
keyPassword=
keyAlias=<app-alias>
storeFile=
```

7.6 dart_defines.example.json (committed) at the app root:
```json
{
  "UPDATE_API_KEY": "paste-the-api-key-from-play.ziroone.com-here",
  "UPDATE_BASE_URL": "https://play.ziroone.com"
}
```

7.7 .gitignore additions:
```
# Release signing — never commit the keystore or its passwords.
/android/key.properties
*.jks
*.keystore

# Firebase client config (per Firebase project).
/android/app/google-services.json

# Build-time update-server key (see dart_defines.example.json).
/dart_defines.json
```

======================================================================
PART 8 — RELEASE.md (at the app root)
======================================================================

Write RELEASE.md titled "Releasing [APP NAME] through play.ziroone.com", with
these sections:

1. Intro: builds come from our App Update Manager, not the Play Store. The
   app checks in on every launch, gets an FCM push when an APK is
   uploaded, downloads it itself, and hands it to Android's installer.
   Package id: `[PACKAGE_ID]`.
   Note that this is a separate app on the server, with its own app record,
   API key, device pool, and Firebase Android app. Only the signing keystore
   may be shared with sibling apps.
2. One-time setup
   2.1 Register on play.ziroone.com: Admin panel → Apps → New app, package
       id = [PACKAGE_ID]. The server mints the API key (shown masked on the
       app screen).
   2.2 Release keystore: the keytool command, "generate once, back it up,
       never lose it". Copy key.properties.example → key.properties.
       Without it the build falls back to DEBUG keys with a warning, and such
       an APK must never be uploaded. (A keystore from another of our apps
       may be reused, because signing keys are per-developer.)
   2.3 Firebase (optional): the SAME Firebase project as the server. Add
       app → Android with package name [PACKAGE_ID], download
       google-services.json to android/app/. Never reuse another app's
       file, because the package names must match exactly. Without it the
       app still builds, and users only discover updates on their next
       launch.
3. Building a release
   - Bump `version:` in pubspec.yaml. The number after `+` is the
     versionCode, and it must strictly increase. The server rejects
     anything not higher than the last upload.
   - The table of dart-defines: UPDATE_API_KEY (required; without it the
     app never calls out) and UPDATE_BASE_URL (default
     https://play.ziroone.com).
   - `flutter build apk --release --dart-define-from-file=dart_defines.json`
     (or inline `--dart-define=UPDATE_API_KEY=...`). The same flags work
     for `flutter run`, VS Code launch.json "toolArgs", and Android Studio
     "Additional run args".
   - Output: build/app/outputs/flutter-apk/app-release.apk
   - SANITY CHECK before uploading:
       "<ANDROID_SDK>/build-tools/<ver>/aapt2.exe" dump badging build/app/outputs/flutter-apk/app-release.apk | head -1
     It prints `package: name='...' versionCode='N' versionName='X.Y.Z'`.
     Enter EXACTLY those values in the upload form. A mismatch makes the
     app offer the same update on every launch, because the installed build
     never reports the version code the server expects.
   - Upload in the admin panel: "Upload new version" (version name, code,
     release notes, force-update flag). The push goes out when the server's
     queue worker picks it up.
4. What the client does: a table of triggers (every launch / FCM push /
   manual check) and their behavior, the overlay UX, forced vs optional
   updates, the permission flow, and why the last state is "the install
   screen is open".
5. File map: a table of every file created in this integration and its
   role.
6. Notes: pulling a bad build (toggle it off; its version_code stays
   reserved, so the replacement must be higher); devices with no FCM token
   (they show as "Push disabled" and pick up updates on the next launch);
   an applicationId change, if one happened.

======================================================================
PART 9 — CONSTRAINTS AND DONE-CRITERIA
======================================================================

Do NOT:
 - Guess or invent the API key, or use any production URL other than
   https://play.ziroone.com. Ask if either is unclear.
 - Register the app on play.ziroone.com or create Firebase projects,
   credentials, or google-services.json yourself. I do the admin-panel and
   Firebase-console steps and give you the results.
 - Generate a keystore, or build or sign a release APK, unless I ask.
 - Send the user's auth token to the update server, or route update calls
   through the app's main API client.
 - Make a failed or unreachable update check visible anywhere except the
   manual check.
 - Add a state-management library the app doesn't already use.
 - Trust FCM payload values for the update decision.

Done means:
 - `flutter pub get` and `flutter analyze` pass with no new issues.
 - A debug run WITHOUT --dart-define and WITHOUT google-services.json
   starts normally. No crash, no update calls, and the manual check shows
   "no update server configured".
 - Give me a short summary: files added/changed, the answers from PART 0,
   which manifest permissions you stripped or kept, and the exact next
   steps for me (register the app, keystore, Firebase, first build command).

Manual test plan to include in your summary (I'll run it):
 1. Upload build N to the server. Install build N on a phone and launch.
    The device appears in the admin panel with the correct version.
 2. Upload N+1 (optional). With the app open, the push arrives and the
    overlay appears. "Later" dismisses it. Relaunching shows it again.
 3. "Update now" → permission prompt → progress bar → system installer →
    installs over the top without "App not installed" (same signing key).
 4. Upload N+2 marked force-update. The overlay can't be dismissed and
    follows every navigation, including logout.
 5. Airplane mode + manual check → an error toast. Airplane mode + launch →
    nothing is shown.

======================================================================
PART 10 — WHAT THE USER MUST DO OR PROVIDE (show this to me, verbatim
          structure, at the start and again in the final summary)
======================================================================

You (the agent) cannot do the items below. They need my accounts, secrets,
or decisions. Present them as a numbered checklist, with what each one is
for, where to do it, what to hand back to you, and whether it blocks your
work.

A. Decisions (answer before or during implementation)
   A1. Package id / applicationId, app display name, and user noun (top of
       this prompt). If the app is still com.example.*, decide whether to
       rename it. Renaming means installed test builds won't update in
       place.
   A2. Anything ambiguous you found in PART 0 (state management, HTTP
       client, where the "Check for updates" entry goes).
   A3. Include Firebase/FCM push in this pass? Yes = instant update
       notifications. No = updates are found only on app launch or by the
       manual check. The code supports both, and Firebase can be added
       later just by dropping in the file.
   A4. Signing keystore: reuse an existing one of mine (I give you its
       path and alias, and put the passwords in key.properties myself), or
       generate a new one (I run the keytool command, or explicitly ask you
       to).
   A5. Which open_filex media permissions to strip, if another feature
       might need them.

B. Admin panel on https://play.ziroone.com (only I can do this)
   B1. Log in → Apps → New app. Name = [APP NAME], package id =
       [PACKAGE_ID] (must match applicationId exactly), plus an optional
       icon and description.
   B2. Copy the app's API key from the app screen (it is masked by
       default; reveal it). Put it into dart_defines.json myself, or give
       it to the agent to place there. Never commit it.
       → Not blocking for the code. The app runs without it but never
         checks for updates.

C. Firebase console (only if A3 = yes)
   C1. Open the SAME Firebase project the update server uses
       (project id: myappmanage-2c50a). A different project cannot receive
       the server's pushes.
   C2. Add app → Android, package name = [PACKAGE_ID]. The SHA-1 is not
       needed for FCM.
   C3. Download google-services.json and place it at
       android/app/google-services.json (gitignored). Never copy another
       app's file, because the package name inside must match.
       → Not blocking. Without it the build still succeeds and push is
         simply off.

D. Signing (before the FIRST release that goes to real devices)
   D1. Create or locate the release keystore and back it up somewhere
       safe. If it is lost, installed apps can never be updated again, and
       every user has to uninstall and reinstall.
   D2. Copy android/key.properties.example → android/key.properties and
       fill in storePassword, keyPassword, keyAlias, and storeFile.
       → Without it, release builds are debug-signed and must NOT be
         uploaded.

E. First release (after the integration is merged)
   E1. Bump `version:` in pubspec.yaml (e.g. 1.0.0+1). The +N is the
       versionCode, and every upload needs a higher one.
   E2. flutter build apk --release --dart-define-from-file=dart_defines.json
   E3. Run the aapt2 dump badging check (see RELEASE.md) and note the
       exact versionCode/versionName.
   E4. Admin panel → the app → Upload new version, with exactly those
       values, release notes, and the force-update flag if needed.
   E5. Install that first APK on devices manually (download page or share
       link/QR in the admin panel). Every build after that arrives through
       the in-app updater.
   E6. Make sure the server's queue worker is running, or pushes won't go
       out. (This is server ops, not part of this app.)

F. Test on a real device: run the PART 9 manual test plan.

======================================================================
PART 11 — DEVELOPER GUIDE (print at the end of your final message, and
          keep the same content in RELEASE.md)
======================================================================

After the integration is complete, finish your final message with the guide
below. Replace every <placeholder> with this app's real values (package id,
the current `version:` line from pubspec.yaml, the real paths). Keep the
structure and headings so every app's guide looks the same. Mark any step
already done during this session as "(already done)".

--------------------------------------------------------------------
DEVELOPER GUIDE — <APP NAME> (<PACKAGE_ID>)
--------------------------------------------------------------------

## 1. Firebase setup (instant update notifications)

Firebase is optional. Without it the app still builds and runs, and users
find updates on the next app launch or with "Check for updates". With it,
users get a notification the moment a new APK is uploaded.

1. Open https://console.firebase.google.com and select the project
   **myappmanage-2c50a**. It must be this project, the same one the update
   server sends from. A different project cannot receive its pushes.
2. Project overview → **Add app** → **Android**.
   - Android package name: `<PACKAGE_ID>`. It must match `applicationId` in
     android/app/build.gradle(.kts) exactly.
   - App nickname: `<APP NAME>` (optional).
   - Debug signing certificate SHA-1: leave empty (FCM doesn't need it).
3. Click **Register app** → **Download google-services.json**.
4. Put the file at `android/app/google-services.json`. Open it and check
   that `"package_name": "<PACKAGE_ID>"` appears in it. Never reuse the
   file from another app.
5. Skip Firebase's "Add Firebase SDK" Gradle steps. This project already
   applies the Google Services plugin automatically whenever that file
   exists (see android/app/build.gradle(.kts)).
6. The file is gitignored. Keep a copy somewhere safe, or download it
   again from the console when needed.
7. Rebuild: `flutter clean && flutter build apk --release --dart-define-from-file=dart_defines.json`
8. Verify:
   - Install and open the app. Allow notifications when Android 13+ asks.
   - Admin panel → the app → **Devices**: this device should NOT show
     "Push disabled".
   - Upload a test build with a higher version code. A notification
     should arrive within seconds, and tapping it opens the update
     overlay.
   - If "Push disabled" still shows: check that the package name in
     google-services.json matches, that the Firebase project is the right
     one, that notification permission was granted, and that `flutter
     logs` does not show "PushService: Firebase unavailable". On the
     server, check that the queue worker is running and that
     FIREBASE_CREDENTIALS points to a service account from the same
     project.

To turn push OFF again: delete android/app/google-services.json and
rebuild. No code change is needed.

## 2. Version and build number: every release

The version lives in ONE place, in pubspec.yaml:

    version: <MAJOR>.<MINOR>.<PATCH>+<BUILD>
    current: <paste current version line>

| Part             | Android name  | Shown to users | Rule                                   |
|------------------|---------------|----------------|----------------------------------------|
| 1.2.0 (before +) | versionName   | Yes            | Human-readable; follow semver loosely  |
| 5 (after +)      | versionCode   | No             | Integer; MUST go up by at least 1 on   |
|                  |               |                | EVERY upload, never reused             |

How to bump:
  - Bug fix:         1.2.0+5  →  1.2.1+6
  - New feature:     1.2.1+6  →  1.3.0+7
  - Big change:      1.3.0+7  →  2.0.0+8
  - Rebuild of the same version (e.g. the last upload was broken or
    pulled):         1.3.0+7  →  1.3.0+8
The build number NEVER resets when the version name changes. It only goes
up.

Release steps:
  1. Edit `version:` in pubspec.yaml.
  2. Build:
       flutter build apk --release --dart-define-from-file=dart_defines.json
     (dart_defines.json holds UPDATE_API_KEY. Without it the build won't
     check for updates.)
  3. Confirm what's actually inside the APK:
       "<ANDROID_SDK>/build-tools/<version>/aapt2.exe" dump badging build/app/outputs/flutter-apk/app-release.apk | head -1
     → package: name='<PACKAGE_ID>' versionCode='<BUILD>' versionName='<MAJOR.MINOR.PATCH>'
     The name must be <PACKAGE_ID>. Make sure the build was signed with the
     release keystore: the Gradle output must NOT show the "DEBUG keys"
     warning.
  4. Admin panel → the app → **Upload new version**:
       APK file        build/app/outputs/flutter-apk/app-release.apk
       Version name    exactly the versionName from step 3
       Version code    exactly the versionCode from step 3 (the form
                       pre-fills max+1; overwrite it if it differs)
       Release notes   what changed. Shown in the update dialog and the
                       notification.
       Force update    tick only if old builds must stop being used (users
                       can't dismiss the dialog)
  5. Save. Devices on older builds get a push (if Firebase is set up) or
     see the update on their next launch.

Common mistakes:
  - The form's version code doesn't match the APK's versionCode → the app
    offers the same update on every launch forever. Fix it by uploading a
    new build with a higher code and matching values.
  - Forgot to bump +BUILD → the server rejects the upload, because the
    code must be higher than the last one.
  - APK signed with debug keys, or with a different keystore → Android
    shows "App not installed" when updating. Always build with
    android/key.properties present.
  - Built without --dart-define-from-file → that build can never find
    updates by itself. Users would have to install the next build by hand.
  - A bad build went out → turn it off in the admin panel's version
    history. Fix it, then upload with a HIGHER build number. The bad
    build's number stays used.
--------------------------------------------------------------------
````

---

## Keeping this prompt current

This file is now the single description of the integration pattern. It no
longer points at the sibling apps as the source of truth, so it only stays
correct if it is updated whenever the pattern changes. Examples: a new file
in `lib/core/update/`, a retry policy added to `UpdateApi`, a changed
Android permission, a new field in the `/register` or `/latest` contract, or
a change to the FCM data payload in `NotifyDevicesOfUpdate`.

When one of those changes lands in the server or in any client app, update
the relevant PART above in the same change. A server contract change (PART 1)
has to be made here and in the README's "Public API" section together.
