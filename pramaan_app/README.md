# PRAMAN — Android app (Flutter)

A native Flutter app of the **PRAMAN** property-verification demo of the **Kota Development Authority**.
It is a full port of `../html/index.html`. It has the same demo data, the same four panels, the same screens and the same verification workflow, rebuilt with Flutter widgets. Everything runs on the phone: there is no server, and it works offline.

> **Demo only.** All data lives on the phone (SharedPreferences, like the web demo's localStorage).
> Demo password for every account: **`Praman@123`**.

## Build the APK

Requirements: [Flutter SDK](https://docs.flutter.dev/get-started/install) (stable) with the Android toolchain set up.
`flutter doctor` must show **Android toolchain** without errors. Install Android Studio, or the Android command-line tools, and accept the licenses with `flutter doctor --android-licenses`.

```powershell
cd parman_app
.\build_apk.ps1          # Windows
```

```bash
cd parman_app
./build_apk.sh           # macOS / Linux
```

The script:
1. runs `flutter create` once to generate the `android/` folder for your Flutter version;
2. copies `branding/android/res` into it (KDA launcher icon, adaptive icon, splash) and sets the app name to **PRAMAN**;
3. runs `flutter pub get` and `flutter build apk --release`;
4. copies the result to **`dist/PRAMAN-demo.apk`**.

Copy the APK to an Android phone (Android 5.0+) and install it. You may need to allow "Install unknown apps" for the file manager.

To run on a connected phone or emulator during development, run the script once (so that `android/` exists), then:

```bash
flutter run
```

## What is inside

| Area | Screens |
|---|---|
| Landing | White header with the KDA emblem, hero, 4-photo slider (wipe transition, Ken Burns zoom, thumbnails with an autoplay bar, swipe), animated **Sign in** popup with the demo accounts |
| Super Admin | Dashboard (stat cards + charts), Role Master, User Master (login as user, unlock, reset password), Scheme / Sector / Zone / Ward / Assembly / Plot Type / Status masters, Activity & Performance Insights |
| Data Entry | Dashboard, Scheme Master (sectors + role–user mapping), Property Register, Add / Edit Property (scheme → sector → ward, Yes/No confirmation), property timeline |
| OIC | Dashboard, Properties – Verification (bulk / single send, re-send with reason, per-section verification bar), property status with every round |
| Role User | Dashboard, My Tasks (tabs), task decision (No Objection / Objection, Engineering site measurement + encroachment, Sale vacancy check, attachments) |
| Everyone | Notifications (bell with badge and sound, full page), Change Password (live rules), Reset demo data, Logout |

The native extras are: Android back button navigation, an animated splash, the KDA app icon, a file picker for attachments, and PDFs that open in the phone's PDF viewer.

## Project layout

```
lib/
  main.dart            app start, splash, back button, root view
  app/                 database (db.dart = js/db.js), state + router (store.dart, routes.dart),
                       validation, formatting, theme, Font Awesome icons (fa.dart, generated)
  ui/                  shared widgets, forms, lists, charts, layout (shell), landing page,
                       attachments, property timeline
  pages/               one file per panel area (admin, masters, schemes, properties, oic, tasks,
                       dashboards, insights, notifications, account)
assets/                fonts (Inter, Poppins, Font Awesome 6 solid), KDA emblem, slider photos
branding/android/res   launcher icons + splash copied into android/ by the build script
```

## Credits

- Slider photos: Wikimedia Commons, **CC BY-SA 4.0**. Kishore Sagar by Kushagra140, Garh Palace by Shrikant Bansod, Chambal River Front by Imakanksha, Seven Wonders Park by Arvind Sunda07. Long-press a photo in the app to see its credit.
- Fonts: Inter and Poppins (SIL Open Font License). Icons: Font Awesome Free 6.5.2 (SIL OFL 1.1).
