import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/auth/auth_repository.dart';
import '../network/session_events.dart';
import '../router/app_router.dart';

/// Mendengarkan sinyal sesi kedaluwarsa (HTTP 401) lalu logout & arahkan ke
/// halaman masuk. Dipasang di bawah `MaterialApp` agar punya akses navigasi.
class SessionListener extends ConsumerStatefulWidget {
  const SessionListener({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<SessionListener> createState() => _SessionListenerState();
}

class _SessionListenerState extends ConsumerState<SessionListener> {
  @override
  Widget build(BuildContext context) {
    ref.listen(sessionExpiredProvider, (previous, next) {
      if (next != previous) _handle();
    });

    return widget.child;
  }

  Future<void> _handle() async {
    final wasAuthenticated =
        ref.read(authStateProvider).valueOrNull?.authenticated ?? false;

    await ref.read(authStateProvider.notifier).logout();
    if (!mounted) return;

    ref.read(routerProvider).go('/login');

    if (wasAuthenticated) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sesi berakhir — silakan masuk kembali.')),
      );
    }
  }
}
