import 'package:flutter/material.dart';

import 'api.dart';
import 'design.dart';
import 'screens.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const RikoApp());
}

class RikoApp extends StatelessWidget {
  const RikoApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'RikoWallet',
    debugShowCheckedModeBanner: false,
    theme: rikoTheme(),
    home: const RikoRoot(),
  );
}

class RikoRoot extends StatefulWidget {
  const RikoRoot({super.key});
  @override
  State<RikoRoot> createState() => _RikoRootState();
}

class _RikoRootState extends State<RikoRoot> {
  final api = RikoApi();
  Map<String, dynamic>? data;
  bool loading = true;
  String? error;
  int selected = 0;
  @override
  void initState() {
    super.initState();
    init();
  }

  Future<void> init() async {
    try {
      await api.restore();
      if (api.token != null) await refresh();
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> refresh() async {
    try {
      final value = await api.call('wallet');
      if (mounted) {
        setState(() {
          data = value;
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
      rethrow;
    }
  }

  Future<void> signedIn() async {
    setState(() => loading = true);
    try {
      await refresh();
    } catch (_) {}
    if (mounted) setState(() => loading = false);
  }

  void open(String page) {
    Navigator.of(context)
        .push(
          MaterialPageRoute(
            builder: (_) => DetailScreen(
              page: page,
              api: api,
              data: data!,
              refresh: refresh,
            ),
          ),
        )
        .then((_) async {
          try {
            await refresh();
          } catch (_) {}
        });
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator(color: purple)),
      );
    }
    if (api.token == null) return LoginScreen(api: api, onSuccess: signedIn);
    if (data == null) {
      return Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(30),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const RikoLogo(),
                const SizedBox(height: 30),
                HintBox(error ?? 'Loading your wallet…', error: true),
                ElevatedButton(
                  onPressed: signedIn,
                  child: const Text('Try again'),
                ),
                TextButton(
                  onPressed: () async {
                    await api.logout();
                    setState(() => data = null);
                  },
                  child: const Text('Sign out'),
                ),
              ],
            ),
          ),
        ),
      );
    }
    final pages = [
      'home',
      'pay',
      'cards',
      if (data!['earning_enabled'] == true) 'earn',
      'profile',
    ];
    if (selected >= pages.length) selected = 0;
    final body = WalletContent(
      page: pages[selected],
      data: data!,
      open: open,
      api: api,
      refresh: refresh,
      onLogout: () async {
        await api.logout();
        if (mounted) {
          setState(() {
            data = null;
            selected = 0;
          });
        }
      },
    );
    return Scaffold(
      extendBody: true,
      appBar: AppBar(
        title: const RikoLogo(),
        centerTitle: false,
        titleSpacing: 24,
        actions: [
          IconButton(
            onPressed: () => open('activity'),
            icon: const Icon(Icons.notifications_none_rounded),
          ),
          Padding(
            padding: const EdgeInsets.only(right: 22),
            child: CircleAvatar(
              radius: 17,
              backgroundColor: blush,
              child: Text(
                data!['user']['name'].toString().substring(0, 1),
                style: const TextStyle(color: muted, fontSize: 13),
              ),
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        color: purple,
        onRefresh: refresh,
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 720),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(24, 16, 24, 115),
              children: [if (error != null) HintBox(error!, error: true), body],
            ),
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(20, 0, 20, 12),
        child: Center(
          heightFactor: 1,
          child: Container(
            constraints: const BoxConstraints(maxWidth: 470),
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: deep,
              borderRadius: BorderRadius.circular(42),
              boxShadow: [
                BoxShadow(
                  color: deep.withValues(alpha: .12),
                  blurRadius: 24,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: List.generate(pages.length, (i) {
                final icons = {
                  'home': Icons.home_outlined,
                  'pay': Icons.near_me_outlined,
                  'cards': Icons.credit_card_rounded,
                  'earn': Icons.card_giftcard_rounded,
                  'profile': Icons.person_outline_rounded,
                };
                final labels = {
                  'home': 'Home',
                  'pay': 'Pay',
                  'cards': 'Cards',
                  'earn': 'Earn',
                  'profile': 'Profile',
                };
                final active = i == selected;
                return Semantics(
                  selected: active,
                  label: labels[pages[i]],
                  button: true,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(30),
                    onTap: () => setState(() => selected = i),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      padding: EdgeInsets.fromLTRB(4, 4, active ? 13 : 4, 4),
                      decoration: BoxDecoration(
                        color: active ? Colors.white : Colors.transparent,
                        borderRadius: BorderRadius.circular(32),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 38,
                            height: 38,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: active
                                  ? null
                                  : Colors.white.withValues(alpha: .09),
                              gradient: active
                                  ? const LinearGradient(
                                      colors: [
                                        Color(0xffbca0ed),
                                        Color(0xfff4cbaa),
                                      ],
                                    )
                                  : null,
                            ),
                            child: Icon(
                              icons[pages[i]],
                              size: 22,
                              color: Colors.white,
                            ),
                          ),
                          if (active) ...[
                            const SizedBox(width: 7),
                            Text(
                              labels[pages[i]]!,
                              style: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w600,
                                color: deep,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                );
              }),
            ),
          ),
        ),
      ),
    );
  }
}

class LoginScreen extends StatefulWidget {
  final RikoApi api;
  final Future<void> Function() onSuccess;
  const LoginScreen({super.key, required this.api, required this.onSuccess});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final email = TextEditingController(),
      password = TextEditingController(),
      name = TextEditingController(),
      confirm = TextEditingController();
  bool register = false, busy = false;
  String country = 'IN';
  String? error;
  @override
  void dispose() {
    email.dispose();
    password.dispose();
    name.dispose();
    confirm.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      if (register) {
        await widget.api.register({
          'name': name.text,
          'email': email.text.trim(),
          'password': password.text,
          'password_confirmation': confirm.text,
          'country': country,
        });
      } else {
        await widget.api.login(email.text.trim(), password.text);
      }
      await widget.onSuccess();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    }
    if (mounted) setState(() => busy = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 500),
          child: ListView(
            padding: const EdgeInsets.all(28),
            children: [
              const SizedBox(height: 30),
              const RikoLogo(size: 35),
              const SizedBox(height: 55),
              Container(
                width: 70,
                height: 70,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: lilac,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: const Icon(
                  Icons.auto_awesome_outlined,
                  color: purple,
                  size: 32,
                ),
              ),
              const SizedBox(height: 25),
              Text(
                register
                    ? 'Your world\nstarts here.'
                    : 'A little simpler.\nA lot more you.',
                style: Theme.of(context).textTheme.headlineLarge,
              ),
              const SizedBox(height: 12),
              Text(
                register
                    ? 'Create your RikoWallet account.'
                    : 'Welcome back to your personal wallet.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 30),
              if (error != null) HintBox(error!, error: true),
              if (register) ...[
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Full name'),
                ),
                const SizedBox(height: 15),
              ],
              TextField(
                controller: email,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
                decoration: const InputDecoration(
                  labelText: 'Email address',
                  prefixIcon: Icon(Icons.mail_outline_rounded, size: 20),
                ),
              ),
              const SizedBox(height: 15),
              TextField(
                controller: password,
                obscureText: true,
                autofillHints: const [AutofillHints.password],
                decoration: InputDecoration(
                  labelText: register
                      ? 'Password · 12+ characters'
                      : 'Password',
                  prefixIcon: const Icon(Icons.lock_outline_rounded, size: 20),
                ),
              ),
              const SizedBox(height: 15),
              if (register) ...[
                TextField(
                  controller: confirm,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Confirm password',
                  ),
                ),
                const SizedBox(height: 15),
                DropdownButtonFormField<String>(
                  initialValue: country,
                  decoration: const InputDecoration(
                    labelText: 'Country of residence',
                  ),
                  items: const [
                    DropdownMenuItem(value: 'IN', child: Text('India')),
                    DropdownMenuItem(value: 'US', child: Text('United States')),
                    DropdownMenuItem(value: 'BD', child: Text('Bangladesh')),
                    DropdownMenuItem(
                      value: 'GB',
                      child: Text('United Kingdom'),
                    ),
                    DropdownMenuItem(
                      value: 'AE',
                      child: Text('United Arab Emirates'),
                    ),
                  ],
                  onChanged: (v) => country = v!,
                ),
                const SizedBox(height: 15),
              ],
              const SizedBox(height: 10),
              ElevatedButton(
                onPressed: busy ? null : submit,
                child: busy
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Text(register ? 'Create account' : 'Sign in'),
              ),
              const SizedBox(height: 18),
              TextButton(
                onPressed: busy
                    ? null
                    : () => setState(() {
                        register = !register;
                        error = null;
                      }),
                child: Text(
                  register
                      ? 'Already have an account? Sign in'
                      : 'New here? Create an account',
                  style: const TextStyle(color: purple, fontSize: 12),
                ),
              ),
              const SizedBox(height: 30),
              const Text(
                'Your money. Your world. All together.',
                textAlign: TextAlign.center,
                style: TextStyle(color: muted, fontSize: 11),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
