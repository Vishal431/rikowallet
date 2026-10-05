# RikoWallet Flutter app

Uses the Laravel API in the parent directory. Provider credentials must stay on the server.

## Development

Start Laravel on port 8010, then run `flutter pub get` and `flutter run`.
Android emulator default: `http://10.0.2.2:8010/api`.
For a physical device, supply your reachable server with `--dart-define=API_BASE_URL=http://YOUR_LAN_IP:8010/api`.

Checks: `flutter analyze` and `flutter test`. Review build: `flutter build apk --debug`.
HTTP is allowed only in the Android debug manifest. Release APIs must use HTTPS.

## Release signing

Release builds refuse debug signing. Create the ignored `android/key.properties` locally:

```properties
storeFile=C:/secure/location/rikowallet-upload.jks
storePassword=YOUR_SECRET
keyAlias=YOUR_ALIAS
keyPassword=YOUR_SECRET
```

Securely back up the upload keystore. Never commit signing secrets. Configure the final application identifier before store submission.

```sh
flutter build appbundle --release --dart-define=API_BASE_URL=https://YOUR_DOMAIN/api
```

The existing APK is a debug review build. Provider integrations, physical-device testing and store readiness remain outstanding. See `../docs/DEVELOPMENT-STATUS.md`.
