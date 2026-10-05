import 'package:flutter/material.dart';

const ink = Color(0xff251137),
    muted = Color(0xff93869f),
    purple = Color(0xffa079e5),
    deep = Color(0xff24064b),
    lilac = Color(0xfff3edff),
    blush = Color(0xfffff0f7),
    blue = Color(0xffedf8ff),
    butter = Color(0xfffff6de),
    line = Color(0xffeee8f3);
ThemeData rikoTheme() => ThemeData(
  useMaterial3: true,
  scaffoldBackgroundColor: Colors.white,
  colorScheme: ColorScheme.fromSeed(
    seedColor: purple,
    primary: deep,
    surface: Colors.white,
  ),
  textTheme: const TextTheme(
    headlineLarge: TextStyle(
      fontSize: 32,
      fontWeight: FontWeight.w600,
      letterSpacing: -1.3,
      color: ink,
    ),
    headlineMedium: TextStyle(
      fontSize: 26,
      fontWeight: FontWeight.w600,
      letterSpacing: -.9,
      color: ink,
    ),
    titleLarge: TextStyle(
      fontSize: 20,
      fontWeight: FontWeight.w600,
      letterSpacing: -.6,
      color: ink,
    ),
    titleMedium: TextStyle(
      fontSize: 15,
      fontWeight: FontWeight.w600,
      color: ink,
    ),
    bodyMedium: TextStyle(fontSize: 13, height: 1.5, color: ink),
    bodySmall: TextStyle(fontSize: 11, height: 1.5, color: muted),
  ),
  inputDecorationTheme: InputDecorationTheme(
    filled: true,
    fillColor: Colors.white,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(15),
      borderSide: const BorderSide(color: line),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(15),
      borderSide: const BorderSide(color: line),
    ),
  ),
  elevatedButtonTheme: ElevatedButtonThemeData(
    style: ElevatedButton.styleFrom(
      backgroundColor: deep,
      foregroundColor: Colors.white,
      elevation: 0,
      minimumSize: const Size(double.infinity, 49),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
      textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
    ),
  ),
  appBarTheme: const AppBarTheme(
    backgroundColor: Colors.white,
    surfaceTintColor: Colors.transparent,
    foregroundColor: ink,
    elevation: 0,
    centerTitle: true,
    titleTextStyle: TextStyle(
      fontSize: 15,
      fontWeight: FontWeight.w600,
      color: ink,
    ),
  ),
);

class RikoLogo extends StatelessWidget {
  final double size;
  const RikoLogo({super.key, this.size = 29});
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: size,
        height: size + 2,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          gradient: const LinearGradient(
            colors: [Color(0xffbe96f8), Color(0xff8150d7), Color(0xffd9afe2)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Text(
          'R',
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w800,
            fontSize: size * .8,
          ),
        ),
      ),
      const SizedBox(width: 8),
      Text(
        'RikoWallet',
        style: TextStyle(
          fontSize: size * .68,
          fontWeight: FontWeight.w700,
          letterSpacing: -.8,
          color: ink,
        ),
      ),
    ],
  );
}

class SoftPanel extends StatelessWidget {
  final Widget child;
  final Color color;
  final EdgeInsets padding;
  const SoftPanel({
    super.key,
    required this.child,
    this.color = Colors.white,
    this.padding = const EdgeInsets.all(22),
  });
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: padding,
    decoration: BoxDecoration(
      color: color,
      border: Border.all(color: color == Colors.white ? line : color),
      borderRadius: BorderRadius.circular(24),
    ),
    child: child,
  );
}

class TileIcon extends StatelessWidget {
  final IconData icon;
  final Color color;
  final double size;
  const TileIcon(this.icon, {super.key, this.color = lilac, this.size = 43});
  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(size * .31),
    ),
    child: Icon(icon, color: purple, size: size * .5),
  );
}

class SectionTitle extends StatelessWidget {
  final String title;
  final String? action;
  final VoidCallback? onTap;
  const SectionTitle(this.title, {super.key, this.action, this.onTap});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 26, bottom: 16),
    child: Row(
      children: [
        Expanded(
          child: Text(title, style: Theme.of(context).textTheme.titleLarge),
        ),
        if (action != null)
          TextButton(
            onPressed: onTap,
            child: Text(
              action!,
              style: const TextStyle(color: purple, fontSize: 11),
            ),
          ),
      ],
    ),
  );
}

class HintBox extends StatelessWidget {
  final String text;
  final bool error;
  const HintBox(this.text, {super.key, this.error = false});
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(15),
    margin: const EdgeInsets.symmetric(vertical: 12),
    decoration: BoxDecoration(
      color: error ? blush : lilac,
      borderRadius: BorderRadius.circular(15),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          error ? Icons.info_outline_rounded : Icons.shield_outlined,
          size: 18,
          color: purple,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            style: TextStyle(
              fontSize: 11,
              color: error ? const Color(0xffad547b) : muted,
            ),
          ),
        ),
      ],
    ),
  );
}

class RikoCard extends StatelessWidget {
  final String name;
  const RikoCard({super.key, required this.name});
  @override
  Widget build(BuildContext context) => AspectRatio(
    aspectRatio: 1.72,
    child: Container(
      padding: const EdgeInsets.all(23),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          colors: [Color(0xff34205b), Color(0xff67418e)],
          begin: Alignment.bottomLeft,
          end: Alignment.topRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'RikoWallet',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                'PREPAID · PREVIEW',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: .65),
                  fontSize: 8,
                  letterSpacing: 1,
                ),
              ),
            ],
          ),
          const Spacer(),
          Container(
            width: 30,
            height: 23,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(5),
              gradient: const LinearGradient(
                colors: [Color(0xffb59a79), Color(0xffead5b5)],
              ),
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            '••••   ••••   ••••   4821',
            style: TextStyle(
              color: Colors.white,
              fontSize: 17,
              letterSpacing: 2,
            ),
          ),
          const Spacer(),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                name.toUpperCase(),
                style: const TextStyle(
                  color: Colors.white70,
                  fontSize: 9,
                  letterSpacing: 1,
                ),
              ),
              const Text(
                'R.',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.w700,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

class EmptyState extends StatelessWidget {
  final String title, description;
  final IconData icon;
  const EmptyState(
    this.title,
    this.description, {
    super.key,
    this.icon = Icons.inbox_outlined,
  });
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(30),
    child: Column(
      children: [
        TileIcon(icon, size: 58),
        const SizedBox(height: 18),
        Text(
          title,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 8),
        Text(
          description,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ],
    ),
  );
}

class StatusPill extends StatelessWidget {
  final String text;
  const StatusPill(this.text, {super.key});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: ['approved', 'credited', 'completed', 'active'].contains(text)
          ? const Color(0xffe8f6ee)
          : lilac,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      text.replaceAll('_', ' '),
      style: const TextStyle(fontSize: 9, color: muted),
    ),
  );
}
