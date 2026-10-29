#!/usr/bin/env bash
# PRAMAN — builds the demo APK on macOS / Linux (same steps as build_apk.ps1).
# Needs: Flutter SDK (stable) with the Android toolchain ready (`flutter doctor`).
set -euo pipefail
cd "$(dirname "$0")"

command -v flutter >/dev/null 2>&1 || { echo "flutter was not found in PATH — install Flutter and run 'flutter doctor'."; exit 1; }

if [ ! -d android ]; then
  echo "1/5  flutter create — generating the Android project files..."
  flutter create --org com.kda.praman --project-name parman_app --platforms android .
else
  echo "1/5  android/ already exists — skipping flutter create"
fi

echo "2/5  branding — KDA launcher icon, splash screen and app name..."
cp -R branding/android/res/. android/app/src/main/res/
manifest=android/app/src/main/AndroidManifest.xml
sed -E 's/android:label="[^"]*"/android:label="PRAMAN"/' "$manifest" > "$manifest.tmp" && mv "$manifest.tmp" "$manifest"

echo "3/5  flutter pub get..."
flutter pub get

echo "4/5  flutter build apk --release..."
flutter build apk --release --no-tree-shake-icons

echo "5/5  copying the APK to dist/"
mkdir -p dist
cp build/app/outputs/flutter-apk/app-release.apk dist/PRAMAN-demo.apk
echo "Done: $(pwd)/dist/PRAMAN-demo.apk"
