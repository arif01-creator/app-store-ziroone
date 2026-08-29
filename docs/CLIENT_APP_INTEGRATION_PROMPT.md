# Prompt: integrate a Flutter app with App Update Manager

Copy the block below into a Claude Code session opened on the Flutter app you
want to wire up, fill in the two bracketed values, and send it. It reproduces
the same integration already shipped in Collector 2.0, Member Passbook, and
Dhaka Western Valley Customer Portal — same file layout, same server
contract, same UX — adapted to whatever architecture the target app already
uses.

---

## The prompt

```
Integrate this Flutter app with our self-hosted App Update Manager
(https://play.ziroone.com), the same way it's already done in three sibling
apps on this machine:

  - C:/Users/ziroone/Desktop/Collection_app/collection_app   (Riverpod)
  - C:/Users/ziroone/Desktop/member_app/member_app           (Riverpod, Dio)
  - C:/Users/ziroone/Desktop/GoldenEye/goldeneye-customer-apps (plain StatefulWidget/setState)

Read RELEASE.md in each of those three, and read the App Update Manager
server's own README at C:/Users/ziroone/Desktop/app_management/README.md for
the authoritative API contract (endpoints, request/response shapes, the FCM
payload). Use collection_app's lib/core/update/*, lib/providers/update_provider.dart
and lib/screens/widgets/update_gate.dart as the primary code reference — port
that structure, not just the general idea.

This app's package id / applicationId is: [PACKAGE_ID]
This app's display name (for the update dialog, e.g. "Update required — X"): [APP NAME]

Before writing code, work out and tell me:
  1. What state management this app already uses (Riverpod / Provider / Bloc /
     plain setState / none) — match it. Don't introduce a new state-management
     dependency just for this feature; if the app has nothing, use a plain
     ChangeNotifier singleton the way goldeneye-customer-apps does.
  2. What HTTP client it already uses (http / dio) — match it, and use a
     *separate* client/instance for update calls, not the app's main
     authenticated API client (an update check must work even when the user
     is signed out, and must never send their session token to
     play.ziroone.com).
  3. Whether it already has an i18n/translation system, and if so, add the
     update strings there rather than hardcoding English.
  4. Whether it already has a settings/profile/drawer screen where a manual
     "check for updates" entry makes sense.

Then implement, following the reference apps exactly on these points:
  - lib/core/update/update_config.dart — base URL and API key from
    --dart-define (UPDATE_BASE_URL, default https://play.ziroone.com;
    UPDATE_API_KEY, required, empty by default). Never hardcode the API key
    in a committed file.
  - lib/core/update/update_models.dart, update_exception.dart,
    device_identity.dart (install_uuid + device info + installed version),
    update_api.dart (/register on every launch, /latest for the full release
    details), apk_installer.dart (streamed download to app storage +
    REQUEST_INSTALL_PACKAGES + system installer via open_filex), and
    push_service.dart (FCM, optional, never fatal if Firebase isn't
    configured — data-only "app_update" messages are a hint to re-check
    /latest, not a source of truth).
  - The state machine (checking / available / downloading /
    handedToInstaller / failed) and its force-update-blocks-dismissal rule,
    ported into whatever state-management answer you gave in step 1.
  - update_gate.dart — an overlay wrapping the app's root/home, not a pushed
    route, so a forced update follows the user across navigation and survives
    getting shown twice (launch check + a push landing together).
  - A manual "check for updates" entry point per step 4, using
    surfaceErrors: true so a failed check or "you're up to date" is actually
    shown, unlike the silent launch-time check.
  - Android: conditional `apply(plugin = "com.google.gms.google-services")`
    only when android/app/google-services.json exists (never make the build
    hard-require Firebase). key.properties-based release signing that falls
    back to the debug keystore with a loud `logger.warn` when
    android/key.properties is absent — never a silent fallback.
    REQUEST_INSTALL_PACKAGES + POST_NOTIFICATIONS permissions. If open_filex
    is already a dependency, check its plugin manifest for broad
    READ_MEDIA_* permissions and strip them with tools:node="remove" if nothing
    else in the app needs them.
  - android/key.properties.example, dart_defines.example.json, and .gitignore
    entries for key.properties, google-services.json, and dart_defines.json.
  - A RELEASE.md at the app root, same structure as the three reference
    RELEASE.md files: one-time setup (register the app on play.ziroone.com,
    keystore, Firebase), building a release (version bump, dart-define,
    aapt2 sanity check before upload), what the client does, file map, notes
    on pulling a bad build / devices with no FCM token.

Do NOT:
  - Guess or invent the per-app API key, or a production URL other than
    https://play.ziroone.com — ask me if either is unclear.
  - Register the app on play.ziroone.com yourself or create Firebase
    credentials — I'll do the admin-panel and Firebase-console steps and
    hand you the API key / google-services.json.
  - Build or sign a release APK unless I ask — get the integration compiling
    and analyzing clean (flutter pub get && flutter analyze) and stop there.

Ask me before making a choice that's mine to make: which state-management/
HTTP-client answer you found in steps 1-2 if it's ambiguous, whether to reuse
an existing signing keystore or generate a new one, and whether to include
Firebase/FCM at all in this pass.
```

---

## Why a prompt file and not a code template

The three reference apps stay the source of truth. A template file here would
drift the moment one of them changes (e.g. if `update_api.dart` gets a retry
policy added) and nobody would remember to update this doc too. Pointing at
the live apps means the next integration always copies current behavior, not
a snapshot from whenever this file was written.

## Updating this file

If the pattern changes in a way future integrations should pick up
(different state-management fallback, a new file in `lib/core/update/`, a
changed Android permission), update the prompt above to describe the new
shape — don't just fix it in one app and leave this document describing the
old one.
