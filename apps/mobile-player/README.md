# DigSignage Mobile Player

One Expo / React Native codebase that ships as a fullscreen signage player on:

- **iPhone and iPad** (iOS / iPadOS)
- **Android phones and Android tablets**

Like the Windows player in `../windows-player`, the app is a kiosk shell around the
web player at `{server}/player`. Pairing, manifest sync, schedules, offline asset
caching, heartbeats, proof-of-play and remote commands (Reverb + polling) all
come from the web player, so every platform behaves the same.

## What the native shell adds

| Feature | How |
| --- | --- |
| Fullscreen | Status bar hidden, Android navigation bar hidden (immersive), iPad `UIRequiresFullScreen` |
| Always on | `expo-keep-awake` keeps the screen from sleeping |
| Any orientation | Rotation unlocked on phones and tablets; content follows the screen's orientation |
| Native bridge | Injects `window.digsignagePlayer` (`platform: ios/android`, `version`, `captureScreenshot`, `restart`) so dashboard **Take screenshot** and **Restart player** commands work |
| Sync on resume | When the app returns to the foreground after 30s or more, it makes the player resync right away |
| Self-healing | Offline screen retries every 15s; WebView crashes reload automatically |
| Autoplay | Inline video autoplays without a tap |

Hidden settings: **tap the top-left corner 5 times** to change the server,
return to the player, or unpair the display.

## Setup

```bash
cd apps/mobile-player
npm install
npm run typecheck
```

## One build for Apple and Android

Builds run on Expo Application Services (EAS), so you can build the iOS app from
Windows without a Mac.

```bash
npm install -g eas-cli
eas login
eas init                 # first time only, links the project to your Expo account
npm run build:all        # eas build --platform all --profile production
```

This produces an `.ipa` (App Store / TestFlight) and an `.aab` (Google Play) from
the same commit. Other profiles:

- `npm run build:preview` makes an installable Android `.apk` plus an iOS ad-hoc
  build for testing on your own devices.
- `eas build --profile development --platform all` makes an Android `.apk` plus an
  iOS **simulator** build.
- `npm run submit:all` uploads the production builds to App Store Connect and Google Play.

Requirements: an Apple Developer account for iOS and a Google Play Console
account for Play Store distribution. EAS manages signing credentials.

## Local development

```bash
npm run android          # expo run:android (Android Studio / device)
npm run ios              # expo run:ios (macOS + Xcode only)
```

The app uses native modules (WebView, view-shot), so use a development/native build.
Expo Go isn't supported.

## Pairing a device

1. Open the app and enter the server URL (e.g. `https://signage.example.com`).
2. The player shows a pairing code.
3. In the dashboard, go to **Screens → Pair** and enter the code.
4. Playback starts in fullscreen and stays in sync with the dashboard.

## Notes

- **Use HTTPS in production.** The web player's offline asset cache (Cache API)
  needs a secure context. Plain `http://` works on the LAN for testing, and the
  app allows cleartext traffic for that. Apple may ask about
  `NSAllowsArbitraryLoads` during review. If every server uses HTTPS, remove it
  from `app.json`.
- Bundle IDs are `com.digsignage.player` for both stores. Change them in `app.json`
  before the first store upload if you need different identifiers.
- For unattended kiosks, pair this with Guided Access (iPad) or screen pinning /
  a device-owner kiosk launcher (Android).
