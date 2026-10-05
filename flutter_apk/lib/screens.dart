import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:file_picker/file_picker.dart';

import 'api.dart';
import 'design.dart';

const assets = ['USD', 'INR', 'EUR'];
const signs = {
  'USD': '\$',
  'INR': '₹',
  'EUR': '€',
  'BTC': '₿',
  'TRX': 'T',
  'USDT': '₮',
  'RC': '◇',
};
String balance(Map<String, dynamic> data, String asset) {
  final rows = (data['balances'] as List).where((e) => e['asset'] == asset);
  final value = rows.isEmpty ? '0' : rows.first['balance'].toString();
  final n = double.tryParse(value) ?? 0;
  return n.toStringAsFixed(['BTC'].contains(asset) ? 6 : 2);
}

Widget title(BuildContext c, String text, String sub) => Column(
  crossAxisAlignment: CrossAxisAlignment.start,
  children: [
    Text(text, style: Theme.of(c).textTheme.headlineLarge),
    const SizedBox(height: 9),
    Text(sub, style: Theme.of(c).textTheme.bodySmall),
    const SizedBox(height: 26),
  ],
);

class WalletContent extends StatelessWidget {
  final String page;
  final Map<String, dynamic> data;
  final void Function(String) open;
  final RikoApi api;
  final Future<void> Function() refresh;
  final Future<void> Function() onLogout;
  const WalletContent({
    super.key,
    required this.page,
    required this.data,
    required this.open,
    required this.api,
    required this.refresh,
    required this.onLogout,
  });
  Widget shortcut(String name, IconData icon, String route, Color color) =>
      Expanded(
        child: InkWell(
          onTap: () => open(route),
          borderRadius: BorderRadius.circular(18),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Column(
              children: [
                TileIcon(icon, color: color, size: 47),
                const SizedBox(height: 9),
                Text(name, style: const TextStyle(fontSize: 10, color: ink)),
              ],
            ),
          ),
        ),
      );
  Widget feature(
    BuildContext c,
    String text,
    String sub,
    IconData icon,
    Color color,
    String route,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 13),
    child: InkWell(
      onTap: () => open(route),
      borderRadius: BorderRadius.circular(22),
      child: SoftPanel(
        color: color,
        padding: const EdgeInsets.all(19),
        child: Row(
          children: [
            TileIcon(icon, color: Colors.white.withValues(alpha: .7)),
            const SizedBox(width: 15),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(text, style: Theme.of(c).textTheme.titleMedium),
                  const SizedBox(height: 4),
                  Text(sub, style: Theme.of(c).textTheme.bodySmall),
                ],
              ),
            ),
            const Icon(Icons.arrow_forward_rounded, size: 18, color: purple),
          ],
        ),
      ),
    ),
  );
  Widget assetGrid() => LayoutBuilder(
    builder: (context, cs) {
      final width = (cs.maxWidth - 12) / 2;
      return Wrap(
        spacing: 12,
        runSpacing: 12,
        children: (data['balances'] as List)
            .map(
              (b) => SizedBox(
                width: width,
                child: SoftPanel(
                  padding: const EdgeInsets.all(15),
                  color: b['asset'] == 'RC' ? blush : const Color(0xfffaf8fe),
                  child: Row(
                    children: [
                      Container(
                        width: 32,
                        height: 32,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(11),
                        ),
                        child: Text(
                          signs[b['asset']] ?? '•',
                          style: const TextStyle(color: purple, fontSize: 19),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              b['asset'] == 'RC' ? 'RikoCoin' : b['asset'],
                              style: const TextStyle(
                                color: muted,
                                fontSize: 10,
                              ),
                            ),
                            const SizedBox(height: 4),
                            FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: Alignment.centerLeft,
                              child: Text(
                                balance(data, b['asset']),
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            )
            .toList(),
      );
    },
  );
  @override
  Widget build(BuildContext context) {
    final user = data['user'] as Map<String, dynamic>;
    if (data['rewards_only'] == true && ['home', 'pay'].contains(page)) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          title(context, 'Your RikoCoin.', 'Earn rewards. Choose your payout.'),
          SoftPanel(
            color: lilac,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Available RikoCoin'),
                const SizedBox(height: 12),
                Text(
                  '${balance(data, 'RC')} RC',
                  style: Theme.of(context).textTheme.headlineLarge,
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          feature(
            context,
            'Withdraw RikoCoin',
            'Review rates, fees and manual payout options.',
            Icons.north_east,
            blue,
            'withdraw',
          ),
          if (data['earning_enabled'] == true)
            feature(
              context,
              'Earn & refer',
              'Tasks, rewards and your referral community.',
              Icons.card_giftcard,
              blush,
              'earn',
            ),
          feature(
            context,
            'Cards · Coming soon',
            'Virtual and physical RikoWallet cards.',
            Icons.credit_card,
            butter,
            'cards',
          ),
          const SectionTitle('Recent activity'),
          feature(
            context,
            'Activity',
            'Review your wallet history.',
            Icons.history,
            lilac,
            'activity',
          ),
        ],
      );
    }
    switch (page) {
      case 'home':
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            title(
              context,
              'Good morning,\n${user['name'].toString().split(' ').first} ✨',
              'A little overview of your world.',
            ),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(25),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(26),
                gradient: const LinearGradient(
                  colors: [Color(0xffefe3ff), Color(0xfffff0e6)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Available USD balance',
                    style: TextStyle(fontSize: 12, color: muted),
                  ),
                  const SizedBox(height: 12),
                  FittedBox(
                    child: Text(
                      '\$ ${balance(data, 'USD')}',
                      style: const TextStyle(
                        fontSize: 40,
                        fontWeight: FontWeight.w600,
                        letterSpacing: -2,
                        color: ink,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    data['sandbox'] == true
                        ? 'Sandbox balance · no real funds'
                        : 'Your currencies, held separately.',
                    style: const TextStyle(color: muted, fontSize: 10),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 15),
            Row(
              children: [
                shortcut('Deposit', Icons.south_rounded, 'deposit', lilac),
                shortcut('Send', Icons.north_east_rounded, 'send', blue),
                shortcut('Swap', Icons.swap_horiz_rounded, 'swap', blush),
                shortcut('Withdraw', Icons.north_rounded, 'withdraw', butter),
              ],
            ),
            const SectionTitle('Your currencies'),
            assetGrid(),
            SectionTitle(
              'Your everyday card',
              action: 'Explore',
              onTap: () => open('cards'),
            ),
            RikoCard(name: user['name']),
            const SizedBox(height: 22),
            if (data['earning_enabled'] == true)
              feature(
                context,
                'Make time rewarding',
                'Your next RikoCoin starts here.',
                Icons.card_giftcard_rounded,
                butter,
                'earn',
              ),
            feature(
              context,
              'A little help, whenever.',
              'Talk to the Riko support team.',
              Icons.chat_bubble_outline_rounded,
              blue,
              'support',
            ),
            SectionTitle(
              'Recent activity',
              action: 'View all',
              onTap: () => open('activity'),
            ),
            ActivityList(items: (data['activity'] as List).take(3).toList()),
          ],
        );
      case 'pay':
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            title(
              context,
              'A little closer.',
              'Send, receive and request money in one place.',
            ),
            feature(
              context,
              'Send money',
              'Pay with a RikoWallet ID.',
              Icons.near_me_outlined,
              lilac,
              'send',
            ),
            feature(
              context,
              'Receive money',
              'Share your personal wallet ID.',
              Icons.south_west_rounded,
              blue,
              'receive',
            ),
            feature(
              context,
              'Request a payment',
              'Make shared payments simple.',
              Icons.call_received_rounded,
              butter,
              'request',
            ),
            const SectionTitle('Made for your business'),
            feature(
              context,
              'Payment links',
              'Create one link to get paid.',
              Icons.link_rounded,
              blush,
              'link',
            ),
            feature(
              context,
              'Invoices',
              'Keep every client payment organised.',
              Icons.description_outlined,
              lilac,
              'invoice',
            ),
            feature(
              context,
              'Subscriptions',
              'Manage recurring payment drafts.',
              Icons.autorenew_rounded,
              blue,
              'subscription',
            ),
          ],
        );
      case 'cards':
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            title(
              context,
              'Cards, coming soon.',
              'Your next everyday companion.',
            ),
            RikoCard(name: user['name']),
            const SizedBox(height: 20),
            const HintBox(
              'Virtual and physical cards are coming soon. Applications and card loading are not open yet. This is a design preview.',
            ),
            const SizedBox(height: 20),
            const FilledButton(onPressed: null, child: Text('Coming soon')),
          ],
        );
      case 'earn':
        if (data['earning_locked'] == true) {
          return Column(
            children: [
              const SizedBox(height: 35),
              const TileIcon(Icons.verified_user_outlined, size: 90),
              const SizedBox(height: 25),
              Text(
                'Verify to unlock earning.',
                style: Theme.of(context).textTheme.headlineMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 14),
              const Text(
                'Complete identity verification to open tasks and claim rewards.',
                textAlign: TextAlign.center,
                style: TextStyle(color: muted),
              ),
              const SizedBox(height: 25),
              ElevatedButton(
                onPressed: () => open('kyc'),
                child: const Text('Start verification'),
              ),
            ],
          );
        }
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            title(
              context,
              'Little moments.\nMore possibilities.',
              'Discover tasks. Collect RikoCoin.',
            ),
            SoftPanel(
              color: lilac,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Your RikoCoin balance',
                    style: TextStyle(color: muted, fontSize: 12),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    '${balance(data, 'RC')} RC',
                    style: const TextStyle(
                      fontSize: 33,
                      fontWeight: FontWeight.w600,
                      letterSpacing: -1,
                    ),
                  ),
                  const SizedBox(height: 17),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => open('referrals'),
                          child: const Text('My referrals'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => open(
                            data['rewards_only'] == true
                                ? 'withdraw'
                                : 'redeem',
                          ),
                          child: const Text('Redeem'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            if (data['policy']['contest'] == true)
              feature(
                context,
                'Referral contest is live',
                'Find your place on the leaderboard.',
                Icons.emoji_events_outlined,
                butter,
                'contest',
              ),
            const SectionTitle('Find your next little extra'),
            ...(data['tasks'] as List).map(
              (t) => Padding(
                padding: const EdgeInsets.only(bottom: 13),
                child: SoftPanel(
                  color: t['category'] == 'survey'
                      ? blue
                      : t['category'] == 'web_surf'
                      ? blush
                      : lilac,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          TileIcon(
                            t['category'].toString().contains('app')
                                ? Icons.phone_android_rounded
                                : Icons.language_rounded,
                            color: Colors.white,
                          ),
                          const Spacer(),
                          Text(
                            '+${double.parse(t['reward'].toString()).toStringAsFixed(0)} RC',
                            style: const TextStyle(
                              color: purple,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 15),
                      Text(
                        t['title'],
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 5),
                      Text(
                        t['category'].toString().replaceAll('_', ' '),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                      const SizedBox(height: 17),
                      OutlinedButton(
                        onPressed: () => open('task:${t['id']}'),
                        child: const Text('View task'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SectionTitle('Earning history'),
            if ((data['sessions'] as List).isEmpty)
              const EmptyState(
                'Your next chapter starts here.',
                'Verified task rewards will appear here.',
                icon: Icons.card_giftcard_outlined,
              ),
            ...(data['sessions'] as List).map(
              (s) => ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('Task #${s['task_id']}'),
                subtitle: Text('${s['reward']} RC'),
                trailing: StatusPill(s['status']),
              ),
            ),
          ],
        );
      case 'profile':
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            title(
              context,
              'Your personal space.',
              'Account, preferences and a little peace of mind.',
            ),
            SoftPanel(
              color: lilac,
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 32,
                    backgroundColor: blush,
                    child: Text(
                      user['name'].toString().substring(0, 1),
                      style: const TextStyle(fontSize: 25, color: purple),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    user['name'],
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 5),
                  Text(
                    user['email'],
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 15),
                  Text(
                    'ID ${user['wallet_id']}',
                    style: const TextStyle(letterSpacing: 2, color: purple),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 22),
            feature(
              context,
              'Identity verification',
              user['kyc_status'].toString().replaceAll('_', ' '),
              Icons.verified_user_outlined,
              lilac,
              'kyc',
            ),
            feature(
              context,
              'Your wallet activity',
              'Every movement, accounted for.',
              Icons.receipt_long_outlined,
              blue,
              'activity',
            ),
            feature(
              context,
              'Contact support',
              'A real conversation, in English.',
              Icons.chat_bubble_outline_rounded,
              blush,
              'support',
            ),
            feature(
              context,
              'Notification preferences',
              'Choose what you hear from us.',
              Icons.notifications_none_rounded,
              butter,
              'preferences',
            ),
            const SizedBox(height: 10),
            OutlinedButton(
              onPressed: onLogout,
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.logout_rounded, size: 17),
                  SizedBox(width: 10),
                  Text('Sign out'),
                ],
              ),
            ),
          ],
        );
      default:
        return const SizedBox.shrink();
    }
  }
}

class ActivityList extends StatelessWidget {
  final List items;
  const ActivityList({super.key, required this.items});
  @override
  Widget build(BuildContext context) => items.isEmpty
      ? const EmptyState(
          'A fresh start.',
          'Your wallet activity will appear here.',
        )
      : Column(
          children: items
              .map(
                (op) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Row(
                    children: [
                      TileIcon(
                        op['asset'] == 'RC'
                            ? Icons.card_giftcard_outlined
                            : Icons.swap_horiz_rounded,
                        size: 39,
                      ),
                      const SizedBox(width: 13),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              op['type'].toString().replaceAll('_', ' '),
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              op['created_at'].toString().substring(0, 10),
                              style: const TextStyle(
                                fontSize: 10,
                                color: muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            '${double.tryParse(op['amount'].toString())?.toStringAsFixed(op['asset'] == 'BTC' ? 6 : 2)} ${op['asset']}',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 5),
                          StatusPill(op['status']),
                        ],
                      ),
                    ],
                  ),
                ),
              )
              .toList(),
        );
}

class DetailScreen extends StatefulWidget {
  final String page;
  final RikoApi api;
  final Map<String, dynamic> data;
  final Future<void> Function() refresh;
  const DetailScreen({
    super.key,
    required this.page,
    required this.api,
    required this.data,
    required this.refresh,
  });
  @override
  State<DetailScreen> createState() => _DetailScreenState();
}

class _DetailScreenState extends State<DetailScreen> {
  late Map<String, dynamic> data;
  final fields = <String, TextEditingController>{};
  String asset = 'USD', to = 'EUR';
  String? method, error, message;
  bool busy = false, consent = false;
  Map<String, dynamic>? quote;
  Map<String, dynamic>? feeQuote;
  String feeMessage = 'Enter an amount to see fees and net credit.';
  Timer? feeTimer;
  int feeVersion = 0;
  String key = RikoApi.key();
  TextEditingController field(String name) =>
      fields.putIfAbsent(name, () => TextEditingController());
  @override
  void initState() {
    super.initState();
    data = widget.data;
    consent = data['user']['push_consent'] == true;
    field('legal_name').text = data['user']['name'];
    field('amount').addListener(refreshFees);
  }

  void refreshFees() {
    if (!['deposit', 'withdraw'].contains(widget.page)) return;
    feeTimer?.cancel();
    final version = ++feeVersion;
    setState(() {
      feeQuote = null;
      feeMessage = field('amount').text.isEmpty
          ? 'Enter an amount to see fees and net credit.'
          : 'Calculating fees…';
    });
    if (field('amount').text.isEmpty) return;
    feeTimer = Timer(const Duration(milliseconds: 450), () async {
      try {
        final response = await widget.api.call('actions/fee-quote', {
          'direction': widget.page,
          'method_id': method,
          'asset': asset,
          'amount': field('amount').text,
        });
        if (!mounted || version != feeVersion) return;
        setState(() {
          feeQuote = Map<String, dynamic>.from(response['result']);
        });
      } catch (e) {
        if (mounted && version == feeVersion) {
          setState(() => feeMessage = e.toString());
        }
      }
    });
  }

  Widget feeBreakdown() {
    final q = feeQuote;
    if (q == null) return HintBox(feeMessage);
    final rows = <String, dynamic>{
      if (q['source_amount'] != null) 'RikoCoin held': q['source_amount'],
      if (q['rate'] != null) 'Rate per RC': q['rate'],
      q['source_amount'] != null
              ? 'Converted amount'
              : (widget.page == 'deposit' ? 'You pay' : 'Wallet deduction'):
          q['gross'],
      'Processing fee': q['processing_fee'],
      'Platform fee': q['platform_fee'],
      'Tax on fees (${q['tax_percent']}%)': q['tax'],
      'Total fees': q['total_fee'],
      widget.page == 'deposit' ? 'Net wallet credit' : 'Approximate payout':
          q['net'],
    };
    return SoftPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Estimated breakdown',
            style: TextStyle(fontWeight: FontWeight.w600),
          ),
          ...rows.entries.map(
            (r) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 7),
              child: Text(
                '${r.key}: ${r.value} ${r.key == 'RikoCoin held' ? 'RC' : q['asset']}',
              ),
            ),
          ),
          Text(q['message'], style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }

  @override
  void dispose() {
    feeTimer?.cancel();
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> reload() async {
    final next = await widget.api.call('wallet');
    if (mounted) setState(() => data = next);
    await widget.refresh();
  }

  Future<void> action(String action, Map<String, dynamic> payload) async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
      message = null;
    });
    try {
      final response = await widget.api.call('actions/$action', payload);
      if (action == 'swap-quote') {
        quote = Map<String, dynamic>.from(response['result']);
      } else if (action == 'task-start') {
        message =
            'Task started. Pairing code: ${response['result']['pairing_code']}';
      } else {
        message = response['result']?['message'] ?? 'Saved successfully.';
        if (action == 'manual-withdraw') {
          feeQuote = null;
          field('amount').clear();
        }
        key = RikoApi.key();
        if (action == 'support') field('body').clear();
      }
      await reload();
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() => busy = false);
  }

  Future<void> upload() async {
    final picked = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
    );
    if (picked.isEmpty) return;
    final file = picked.single;
    if ((await file.length() ?? 0) > 5 * 1024 * 1024) {
      setState(() => error = 'Choose a file smaller than 5 MB.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.uploadKyc(
        field('legal_name').text,
        file.name,
        await file.readAsBytes(),
      );
      await reload();
      message = 'Document submitted for review.';
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() => busy = false);
  }

  Widget input(
    String name,
    String label, {
    bool secret = false,
    bool numeric = false,
    int lines = 1,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 17),
    child: TextField(
      controller: field(name),
      obscureText: secret,
      maxLines: lines,
      keyboardType: numeric
          ? const TextInputType.numberWithOptions(decimal: true)
          : null,
      decoration: InputDecoration(labelText: label),
    ),
  );
  Widget currency({bool output = false}) => Padding(
    padding: const EdgeInsets.only(bottom: 17),
    child: DropdownButtonFormField<String>(
      initialValue: output ? to : asset,
      decoration: InputDecoration(
        labelText: output ? 'To currency' : 'Currency',
      ),
      items: assets
          .map((a) => DropdownMenuItem(value: a, child: Text(a)))
          .toList(),
      onChanged: (v) => setState(() {
        if (output) {
          to = v!;
        } else {
          asset = v!;
        }
        refreshFees();
      }),
    ),
  );
  Widget button(String label, VoidCallback run) => Padding(
    padding: const EdgeInsets.only(top: 6),
    child: ElevatedButton(
      onPressed: busy ? null : run,
      child: busy
          ? const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: Colors.white,
              ),
            )
          : Text(label),
    ),
  );
  void open(String page) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => DetailScreen(
          page: page,
          api: widget.api,
          data: data,
          refresh: widget.refresh,
        ),
      ),
    );
  }

  Future<bool> confirm(String text) async =>
      await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('Review your payment'),
          content: Text(text),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('Go back'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('Confirm'),
            ),
          ],
        ),
      ) ??
      false;
  @override
  Widget build(BuildContext context) {
    final p = widget.page;
    List<Widget> content = [];
    if (['cards', 'earn', 'pay', 'profile', 'home'].contains(p)) {
      content = [
        WalletContent(
          page: p,
          data: data,
          open: open,
          api: widget.api,
          refresh: reload,
          onLogout: () async {
            await widget.api.logout();
            if (context.mounted) Navigator.popUntil(context, (r) => r.isFirst);
          },
        ),
      ];
    } else if (p == 'send') {
      content = [
        title(
          context,
          'Send a little\npossibility.',
          'Pay another RikoWallet user.',
        ),
        input('wallet_id', 'Recipient wallet ID', numeric: true),
        currency(),
        input('amount', 'Amount', numeric: true),
        const HintBox(
          'Internal payment · Platform fee: 0. Confirm the recipient and amount before sending.',
        ),
        input('password', 'Confirm your password', secret: true),
        button('Review & send', () async {
          if (await confirm(
            'Send ${field('amount').text} $asset to wallet ${field('wallet_id').text}?',
          )) {
            await action('send', {
              'wallet_id': field('wallet_id').text,
              'asset': asset,
              'amount': field('amount').text,
              'password': field('password').text,
              'key': key,
            });
          }
        }),
      ];
    } else if (p == 'receive') {
      content = [
        title(
          context,
          'Your wallet.\nYour identity.',
          'Share this ID to receive internal payments.',
        ),
        SoftPanel(
          color: lilac,
          child: Column(
            children: [
              const TileIcon(Icons.account_balance_wallet_outlined, size: 80),
              const SizedBox(height: 25),
              const Text(
                'YOUR RIKOWALLET ID',
                style: TextStyle(fontSize: 10, letterSpacing: 2, color: muted),
              ),
              const SizedBox(height: 15),
              SelectableText(
                data['user']['wallet_id'],
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 3,
                ),
              ),
              const SizedBox(height: 25),
              button(
                'Copy wallet ID',
                () => Clipboard.setData(
                  ClipboardData(text: data['user']['wallet_id']),
                ),
              ),
            ],
          ),
        ),
        const HintBox(
          'This is a public payment identifier. It is not your password or an earning pairing code.',
        ),
      ];
    } else if (p == 'withdraw' && data['rewards_only'] == true) {
      final methods = data['withdraw_methods'] as List;
      method ??= methods.isEmpty ? null : methods.first['id'].toString();
      final selected = methods
          .where((m) => m['id'].toString() == method)
          .firstOrNull;
      content = [
        title(
          context,
          'Withdraw RikoCoin.',
          'Manual payouts with clear fees and rates.',
        ),
        if (methods.isEmpty)
          const HintBox(
            'No withdrawal methods have been enabled for your country.',
          ),
        DropdownButtonFormField<String>(
          initialValue: method,
          isExpanded: true,
          decoration: const InputDecoration(
            labelText: 'Payout method / category',
          ),
          items: methods
              .map(
                (m) => DropdownMenuItem<String>(
                  value: m['id'].toString(),
                  child: Text(
                    '${m['category']} · ${m['name']}',
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              )
              .toList(),
          onChanged: (v) {
            setState(() => method = v);
            refreshFees();
          },
        ),
        if (selected != null) ...[
          const SizedBox(height: 18),
          if (selected['image_file'] != null)
            Align(
              alignment: Alignment.centerLeft,
              child: CachedNetworkImage(
                imageUrl: Uri.parse(RikoApi.baseUrl)
                    .resolve('/method-image/${selected['image_file']}')
                    .toString(),
                width: 72,
                height: 72,
                fit: BoxFit.contain,
                errorWidget: (context, url, error) =>
                    const Icon(Icons.account_balance_wallet_outlined),
              ),
            ),
          HintBox(
            '${selected['instructions']}\nMinimum: ${selected['minimum']} RC · Maximum: ${selected['maximum']} RC\nPayout currency: ${selected['payout_currency']}',
          ),
        ],
        input('amount', 'Amount · RikoCoin', numeric: true),
        input('destination', 'Recipient account / wallet details', lines: 3),
        input('password', 'Confirm password', secret: true),
        feeBreakdown(),
        if (feeQuote != null)
          button('Review and request withdrawal', () async {
            final q = feeQuote;
            if (q == null) return;
            if (await confirm(
              'Hold ${q['source_amount']} RC for an approximate payout of ${q['net']} ${q['asset']}? Total fees: ${q['total_fee']} ${q['asset']}.',
            )) {
              await action('manual-withdraw', {
                'quote_token': q['quote_token'],
                'destination': field('destination').text,
                'password': field('password').text,
                'key': key,
              });
            }
          }),
        const SectionTitle('Withdrawal requests'),
        ...(data['records'] as List)
            .where((r) => r['kind'] == 'manual_withdrawal')
            .map(
              (r) => HintBox(
                '${r['amount']} RC · ${r['status']}\nApprox. ${r['data']['quote']['net']} ${r['data']['quote']['asset']}',
              ),
            ),
      ];
    } else if (data['rewards_only'] == true &&
        [
          'deposit',
          'swap',
          'redeem',
          'send',
          'request',
          'receive',
          'invoice',
          'link',
          'subscription',
        ].contains(p)) {
      content = [
        title(
          context,
          'Coming later.',
          'Start with RikoCoin earning and manual withdrawals.',
        ),
        button('Withdraw RikoCoin', () => open('withdraw')),
      ];
    } else if (p == 'deposit' || p == 'withdraw') {
      final methods =
          data[p == 'deposit' ? 'deposit_methods' : 'withdraw_methods'] as List;
      method ??= methods.isEmpty ? null : methods.first['id'].toString();
      content = [
        title(
          context,
          p == 'deposit' ? 'Add a little\npossibility.' : 'Move money out.',
          'Methods available for your account country.',
        ),
        DropdownButtonFormField<String>(
          initialValue: method,
          decoration: const InputDecoration(labelText: 'Payment method'),
          items: methods
              .map(
                (m) => DropdownMenuItem(
                  value: m['id'].toString(),
                  child: Text(m['name']),
                ),
              )
              .toList(),
          onChanged: (v) {
            setState(() => method = v);
            refreshFees();
          },
        ),
        const SizedBox(height: 17),
        currency(),
        input('amount', 'Amount', numeric: true),
        if (p == 'withdraw') input('destination', 'Bank account details'),
        feeBreakdown(),
        const HintBox(
          'Provider setup is pending. No real deposit address is issued and no funds can move.',
        ),
        button(
          'Check availability',
          () => action(p, {
            'method_id': method,
            'asset': asset,
            'amount': field('amount').text,
            'destination': field('destination').text,
          }),
        ),
      ];
    } else if (p == 'swap') {
      content = [
        title(
          context,
          'A change of\ncurrency.',
          'Clear fees. One simple quote.',
        ),
        currency(),
        currency(output: true),
        input('amount', 'Amount to exchange', numeric: true),
        HintBox(
          'Platform fee: ${data['policy']['swap_fee_percent']}%. Sandbox exchange rates are illustrative.',
        ),
        button(
          'Get a quote',
          () => action('swap-quote', {
            'from': asset,
            'to': to,
            'amount': field('amount').text,
          }),
        ),
        if (quote != null) ...[
          const SectionTitle('Review your quote'),
          SoftPanel(
            color: lilac,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('You pay: ${quote!['input']} ${quote!['from']}'),
                const SizedBox(height: 12),
                Text('Fee: ${quote!['fee']} ${quote!['from']}'),
                const SizedBox(height: 12),
                Text('You receive: ${quote!['output']} ${quote!['to']}'),
                const SizedBox(height: 12),
                const Text(
                  'Expires after 60 seconds.',
                  style: TextStyle(color: muted, fontSize: 11),
                ),
              ],
            ),
          ),
          const SizedBox(height: 17),
          input('password', 'Confirm password', secret: true),
          button(
            'Confirm swap',
            () => action('swap-confirm', {
              'quote_id': quote!['id'],
              'password': field('password').text,
            }),
          ),
        ],
      ];
    } else if (p == 'redeem') {
      content = [
        title(
          context,
          'Little rewards.\nReal possibilities.',
          'Redeem RikoCoin into your USD wallet.',
        ),
        SoftPanel(
          color: butter,
          child: Text(
            '${balance(data, 'RC')} RC available',
            style: Theme.of(context).textTheme.headlineMedium,
          ),
        ),
        const SizedBox(height: 25),
        input('amount', 'RikoCoin to redeem', numeric: true),
        HintBox('Current rate: 1 RC = ${data['policy']['rc_usd_rate']} USD.'),
        button(
          'Redeem to USD',
          () => action('redeem', {'amount': field('amount').text, 'key': key}),
        ),
      ];
    } else if (p == 'referrals') {
      final refs = data['referrals'] as List;
      final pol = data['policy'];
      final rule = pol['referral_mode'] == 'C'
          ? 'Earn ${pol['referral_percent']}% of your direct referrals’ approved task rewards. They keep their full reward.'
          : pol['referral_mode'] == 'A'
          ? 'Your friend receives ${pol['newcomer_bonus']} RC and you receive ${pol['referrer_bonus']} RC after qualification.'
          : pol['referral_mode'] == 'B'
          ? 'Your friend receives ${pol['newcomer_bonus']} RC after qualification.'
          : 'Referral rewards are paused.';
      content = [
        title(
          context,
          'Better together.',
          'Invite your people. Share possibilities.',
        ),
        SoftPanel(
          color: lilac,
          child: Column(
            children: [
              const Text(
                'YOUR REFERRAL CODE',
                style: TextStyle(color: muted, fontSize: 10, letterSpacing: 2),
              ),
              const SizedBox(height: 14),
              Text(
                data['user']['wallet_id'],
                style: const TextStyle(
                  fontSize: 26,
                  letterSpacing: 3,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 17),
              button(
                'Copy invitation code',
                () => Clipboard.setData(
                  ClipboardData(text: data['user']['wallet_id']),
                ),
              ),
            ],
          ),
        ),
        HintBox(rule),
        const Text(
          'KYC may be required before rewards are released.',
          style: TextStyle(fontSize: 11, color: muted),
        ),
        if (data['user']['referrer_id'] == null) ...[
          const SectionTitle('Have a referral code?'),
          input('wallet_id', 'Referrer wallet ID', numeric: true),
          button(
            'Apply referral code',
            () => action('referral', {'wallet_id': field('wallet_id').text}),
          ),
        ],
        SectionTitle('Your people (${refs.length})'),
        if (refs.isEmpty)
          const EmptyState(
            'A shared code is a start.',
            'Your referrals and their activity will appear here.',
            icon: Icons.people_outline_rounded,
          ),
        ...refs.map(
          (r) => ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const TileIcon(Icons.person_outline_rounded),
            title: Text(r['name']),
            subtitle: Text('${r['commission']} RC commission'),
            trailing: StatusPill(r['active'] == true ? 'active' : 'inactive'),
          ),
        ),
        button(
          'Notify eligible inactive referrals',
          () => action('notify', {}),
        ),
        const HintBox(
          'Only opted-in inactive referrals are eligible. A seven-day cooldown applies. Push delivery is not connected.',
        ),
      ];
    } else if (p == 'contest') {
      final leaders = data['leaderboard'] as List;
      content = [
        title(
          context,
          'Your next\nwinning moment.',
          'Monthly referral contest.',
        ),
        const SoftPanel(
          color: butter,
          child: Column(
            children: [
              TileIcon(
                Icons.emoji_events_outlined,
                color: Colors.white,
                size: 85,
              ),
              SizedBox(height: 18),
              Text(
                'Grow together. Rise together.',
                style: TextStyle(fontSize: 21, fontWeight: FontWeight.w600),
              ),
              SizedBox(height: 8),
              Text(
                'Ranked by approved direct-referral task commission.',
                textAlign: TextAlign.center,
                style: TextStyle(color: muted, fontSize: 11),
              ),
            ],
          ),
        ),
        const SectionTitle('This month’s leaderboard'),
        if (leaders.isEmpty)
          const EmptyState(
            'A new season awaits.',
            'Qualifying referral earnings will appear here.',
          ),
        ...leaders.asMap().entries.map(
          (e) => ListTile(
            leading: Text(
              '#${e.key + 1}',
              style: const TextStyle(
                color: purple,
                fontWeight: FontWeight.w700,
              ),
            ),
            title: Text('••••${e.value['wallet_id'].toString().substring(6)}'),
            trailing: Text('${e.value['score']} RC'),
          ),
        ),
      ];
    } else if (p == 'kyc') {
      final status = data['user']['kyc_status'];
      content = [
        title(
          context,
          'A little more\nabout you.',
          'Identity verification keeps your wallet protected.',
        ),
        StatusPill(status),
        const SizedBox(height: 25),
        if (status == 'approved')
          const EmptyState(
            'You’re verified.',
            'Eligible features are now available. Country and provider rules still apply.',
            icon: Icons.verified_user_outlined,
          )
        else if (status == 'pending')
          const EmptyState(
            'We’re reviewing your details.',
            'Your identity documents were submitted. Check back here for the decision.',
            icon: Icons.hourglass_top_rounded,
          )
        else ...[
          input('legal_name', 'Full legal name'),
          const HintBox(
            'Choose a JPG, PNG or PDF under 5 MB. For sandbox testing, use a synthetic document.',
          ),
          button('Choose document & submit', upload),
        ],
      ];
    } else if (p == 'support') {
      final messages = data['messages'] as List;
      content = [
        title(
          context,
          'Let’s talk.',
          'A real conversation with the Riko support team.',
        ),
        const HintBox(
          'Please write in English. Never send passwords or private keys.',
        ),
        if (messages.isEmpty)
          const EmptyState(
            'What can we help with?',
            'Ask about your account, payments or rewards.',
            icon: Icons.chat_bubble_outline_rounded,
          ),
        ...messages.map(
          (m) => Align(
            alignment: m['sender_id'] == m['user_id']
                ? Alignment.centerRight
                : Alignment.centerLeft,
            child: Container(
              constraints: const BoxConstraints(maxWidth: 280),
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(15),
              decoration: BoxDecoration(
                color: m['sender_id'] == m['user_id'] ? lilac : blue,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Text(m['body']),
            ),
          ),
        ),
        const SizedBox(height: 15),
        input('body', 'Your message in English', lines: 3),
        button(
          'Send message',
          () => action('support', {'body': field('body').text}),
        ),
      ];
    } else if (p == 'activity') {
      content = [
        title(
          context,
          'Every little\nmovement.',
          'A clear history of your wallet.',
        ),
        ActivityList(items: data['activity'] as List),
      ];
    } else if (p == 'preferences') {
      content = [
        title(
          context,
          'A little more\ncontrol.',
          'Choose the updates you want.',
        ),
        SwitchListTile.adaptive(
          contentPadding: EdgeInsets.zero,
          title: const Text('Referral reminders'),
          subtitle: const Text('Occasional messages from your referrer.'),
          value: consent,
          onChanged: (v) => setState(() => consent = v),
        ),
        button(
          'Save preferences',
          () => action('preferences', {'push_consent': consent}),
        ),
      ];
    } else if (['request', 'link', 'invoice', 'subscription'].contains(p)) {
      final records = (data['records'] as List).where((r) => r['kind'] == p);
      content = [
        title(context, 'Create a $p.', 'A simpler way to organise payments.'),
        input('title', 'Title or client reference'),
        if (p == 'request')
          input('wallet_id', 'Recipient wallet ID', numeric: true),
        currency(),
        input('amount', 'Amount', numeric: true),
        if (p == 'subscription')
          const HintBox(
            'Saved as a draft. Charging requires payer consent and billing integration.',
          ),
        button(
          'Create $p',
          () => action(p, {
            'title': field('title').text,
            'asset': asset,
            'amount': field('amount').text,
            if (p == 'request') 'wallet_id': field('wallet_id').text,
          }),
        ),
        const SectionTitle('Recent records'),
        ...records.map(
          (r) => ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(r['data']['title']),
            subtitle: Text('${r['amount']} ${r['asset']}'),
            trailing: StatusPill(r['status']),
          ),
        ),
      ];
    } else if (p.startsWith('task:')) {
      final task = (data['tasks'] as List).firstWhere(
        (t) => t['id'].toString() == p.split(':')[1],
      );
      content = [
        title(
          context,
          task['title'],
          '${task['category'].toString().replaceAll('_', ' ')} · verified partner evidence required',
        ),
        SoftPanel(
          color: lilac,
          child: Text(
            '+${task['reward']} RC',
            style: Theme.of(context).textTheme.headlineMedium,
          ),
        ),
        const SectionTitle('How it works'),
        Text(task['instructions']),
        const SizedBox(height: 15),
        Text(
          task['package_name'] ?? '',
          style: const TextStyle(color: muted, fontSize: 11),
        ),
        const HintBox(
          'The server confirms completion. Opening an app or waiting on a timer cannot claim a reward.',
        ),
        button(
          'Start & get pairing code',
          () => action('task-start', {
            'task_id': task['id'],
            'device': kIsWeb
                ? 'desktop'
                : defaultTargetPlatform == TargetPlatform.iOS
                ? 'ios'
                : 'android',
          }),
        ),
      ];
    } else if (p == 'card-apply' || p == 'card-load') {
      content = [
        title(
          context,
          p == 'card-load' ? 'Add to your card.' : 'Your new companion.',
          'Cards are coming soon.',
        ),
        RikoCard(name: data['user']['name']),
        const HintBox(
          'The card issuer integration is not connected. No card or fee will be created.',
        ),
        const FilledButton(onPressed: null, child: Text('Coming soon')),
      ];
    }
    return Scaffold(
      appBar: AppBar(
        title: Text(
          p.startsWith('task:')
              ? 'Task details'
              : p.replaceAll('-', ' ').toUpperCase(),
        ),
        actions: [
          IconButton(
            onPressed: () => open('support'),
            icon: const Icon(Icons.help_outline_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 650),
            child: ListView(
              padding: const EdgeInsets.all(24),
              children: [
                if (error != null) HintBox(error!, error: true),
                if (message != null) HintBox(message!),
                ...content,
                const SizedBox(height: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
