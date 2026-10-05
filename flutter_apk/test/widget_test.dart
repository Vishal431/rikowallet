import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:riko_wallet/design.dart';
import 'package:riko_wallet/screens.dart';
import 'package:riko_wallet/api.dart';

void main() {
  testWidgets(
    'RikoCoin manual withdrawal shows configured method and RC amount',
    (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: rikoTheme(),
          home: DetailScreen(
            page: 'withdraw',
            api: RikoApi(),
            refresh: () async {},
            data: {
              'rewards_only': true,
              'user': {'name': 'Alex', 'push_consent': false},
              'records': [],
              'withdraw_methods': [
                {
                  'id': 1,
                  'name': 'Bank payout',
                  'category': 'Wallet',
                  'payout_currency': 'INR',
                  'minimum': '100',
                  'maximum': '10000',
                  'instructions': 'Enter bank account and IFSC',
                  'image_file': null,
                },
              ],
            },
          ),
        ),
      );
      expect(find.text('Amount · RikoCoin'), findsOneWidget);
      expect(find.text('Wallet · Bank payout'), findsWidgets);
      expect(find.text('Currency'), findsNothing);
      expect(find.text('Review and request withdrawal'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('Unverified earning shows KYC gate and no tasks', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: rikoTheme(),
        home: Scaffold(
          body: WalletContent(
            page: 'earn',
            data: {
              'user': {'name': 'Alex'},
              'earning_locked': true,
              'tasks': [],
            },
            open: (_) {},
            api: RikoApi(),
            refresh: () async {},
            onLogout: () async {},
          ),
        ),
      ),
    );
    expect(find.text('Verify to unlock earning.'), findsOneWidget);
    expect(find.text('Start verification'), findsOneWidget);
    expect(find.text('Find your next little extra'), findsNothing);
  });
  testWidgets('Card preview remains explicitly marked as not issued', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: rikoTheme(),
        home: const Scaffold(
          body: SingleChildScrollView(child: RikoCard(name: 'Alex Morgan')),
        ),
      ),
    );
    expect(find.text('PREPAID · PREVIEW'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
