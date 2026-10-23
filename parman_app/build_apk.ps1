# PRAMAN — builds the demo APK on Windows.
# Needs: Flutter SDK (stable channel) with the Android toolchain ready — `flutter doctor` must show
# "Android toolchain" without errors (Android Studio or the Android command-line tools + accepted licenses).
# Run from PowerShell:   .\build_apk.ps1
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
    throw "flutter was not found in PATH. Install Flutter (https://docs.flutter.dev/get-started/install/windows) and run 'flutter doctor'."
}

if (-not (Test-Path 'android')) {
    Write-Host '1/5  flutter create — generating the Android project files...'
    flutter create --org com.kda.praman --project-name parman_app --platforms android .
    if ($LASTEXITCODE -ne 0) { throw 'flutter create failed' }
} else {
    Write-Host '1/5  android/ already exists — skipping flutter create'
}

Write-Host '2/5  branding — KDA launcher icon, splash screen and app name...'
Copy-Item -Recurse -Force 'branding/android/res/*' 'android/app/src/main/res/'
$manifest = 'android/app/src/main/AndroidManifest.xml'
$xml = [IO.File]::ReadAllText($manifest)
$xml = $xml -replace 'android:label="[^"]*"', 'android:label="PRAMAN"'
if ($xml -notmatch 'android.intent.action.VIEW' -and $xml -match '</queries>') {
    $view = "    <intent>`r`n            <action android:name=`"android.intent.action.VIEW`" />`r`n            <data android:scheme=`"https`" />`r`n        </intent>`r`n    </queries>"
    $xml = $xml -replace '</queries>', $view
}
[IO.File]::WriteAllText($manifest, $xml, (New-Object System.Text.UTF8Encoding $false))

Write-Host '3/5  flutter pub get...'
flutter pub get
if ($LASTEXITCODE -ne 0) { throw 'flutter pub get failed' }

Write-Host '4/5  flutter build apk --release (first build downloads Gradle files — takes a few minutes)...'
flutter build apk --release --no-tree-shake-icons
if ($LASTEXITCODE -ne 0) { throw 'flutter build apk failed' }

Write-Host '5/5  copying the APK to dist\'
New-Item -ItemType Directory -Force 'dist' | Out-Null
Copy-Item -Force 'build/app/outputs/flutter-apk/app-release.apk' 'dist/PRAMAN-demo.apk'
Write-Host ''
Write-Host "Done: $PSScriptRoot\dist\PRAMAN-demo.apk" -ForegroundColor Green
Write-Host 'Copy it to an Android phone and install it (allow "Install unknown apps" for the file manager).'
