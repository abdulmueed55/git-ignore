# FGLA College — mobile app

Two ways to have the college system on a phone:

| | Android | iPhone |
|---|---|---|
| **App (APK)** | `FGLA-College-1.0.apk` — install directly | — |
| **Installable web app (PWA)** | Chrome → ⋮ → *Add to Home screen* / *Install app* | Safari → Share → *Add to Home Screen* |

Both open the same college server, so everything (student portal, staff ERP, admission form) is always up to date — no app update needed when the system changes.

## The Android app (`mobile/android`)

A small native app (≈90 KB) around the college portal:

- First screen: **Student** or **Teacher / Staff**, plus *Apply for admission*, *Admission status* and *College website*. The choice is remembered; Back from the portal's first page returns to it.
- Only the college server opens inside the app (HTTPS only); other links (WhatsApp, maps, other sites) open in the phone's browser.
- Admission documents can be uploaded from the gallery / files; CSV and PDF downloads go to *Downloads*.
- No internet → a clear "No internet connection / Try again" screen.
- **Server**: default `https://fgla-erp-demo.onrender.com`; change it on the first screen (*Server · Change*) or build with your own address (below).

> Voice attendance: Android WebView has no built-in speech recognition, so in the app the teacher types the sentence — or taps the **mic on the Google keyboard** (voice typing) and speaks; the AI then marks the register as usual. Class alerts appear as pop-ups while the app is open (phone push notifications would need Firebase — a later step).

### Install the APK (Roman Urdu)

1. `FGLA-College-1.0.apk` phone par bhejein (WhatsApp / USB / Google Drive).
2. File kholein → Android poochega *"Install unknown apps"* → us app (Files / Chrome / WhatsApp) ko **Allow** karein.
3. **Install** → **Open**. Pehli screen par Student ya Teacher chunein.
4. Server badalna ho to neeche *Server · Change* → apne college ka `https://` address.

### Build it yourself

Needs JDK 17+ and the Android SDK (or Android Studio).

```bash
cd mobile/android
./gradlew assembleRelease -PserverUrl=https://erp.fglacollege.edu.pk
# → app/build/outputs/apk/release/app-release.apk
```

**Before the Play Store or wide distribution**, sign with the college's own key (keep it safe — every future update must use the same key):

```bash
keytool -genkeypair -v -keystore fgla-release.jks -alias fgla -keyalg RSA -keysize 4096 -validity 10000
```

and add a `signingConfigs.release` block in `app/build.gradle` that reads the keystore path and passwords from environment variables (never commit the `.jks` or its passwords). The APK built here is signed with a development key, which is fine for installing and testing.
