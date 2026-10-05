import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:riko_wallet/api.dart';
import 'package:riko_wallet/design.dart';
import 'package:riko_wallet/screens.dart';

void main() {
  testWidgets('wallet fits a narrow phone and exports a review render', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final boundaryKey = GlobalKey();
    final data = <String, dynamic>{
      'user': {
        'name': 'Alex Morgan',
        'email': 'alex@example.test',
        'wallet_id': '5826149037',
      },
      'sandbox': true,
      'earning_enabled': true,
      'balances': [
        for (final a in ['USD', 'INR', 'EUR', 'BTC', 'TRX', 'USDT', 'RC'])
          {
            'asset': a,
            'balance': a == 'USD'
                ? '1250'
                : a == 'BTC'
                ? '0.0034'
                : '250',
          },
      ],
      'activity': [],
    };
    await tester.pumpWidget(
      MaterialApp(
        theme: rikoTheme(),
        home: RepaintBoundary(
          key: boundaryKey,
          child: Scaffold(
            appBar: AppBar(title: const RikoLogo(), centerTitle: false),
            body: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: WalletContent(
                page: 'home',
                data: data,
                open: (_) {},
                api: RikoApi(),
                refresh: () async {},
                onLogout: () async {},
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    final boundary =
        boundaryKey.currentContext!.findRenderObject()!
            as RenderRepaintBoundary;
    await tester.runAsync(() async {
      final image = await boundary.toImage(pixelRatio: 2);
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      final file = File('../design/implementation/flutter-wallet-render.png');
      await file.parent.create(recursive: true);
      await file.writeAsBytes(bytes!.buffer.asUint8List());
    });
  });
}
