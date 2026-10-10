import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/config/app_config.dart';
import 'core/router/app_shell.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/presentation/login_screen.dart';
import 'features/auth/presentation/two_factor_challenge_screen.dart';
import 'features/common/soon_screen.dart';
import 'features/dashboard/presentation/dashboard_screen.dart';
import 'features/stock/presentation/stock_list_screen.dart';

void main() {
  runApp(const ProviderScope(child: WsmApp()));
}

/// Router: /login → /dashboard (+ branch Scan/Transaksi/Stok/Lainnya).
final _router = GoRouter(
  initialLocation: '/dashboard',
  redirect: (context, state) {
    return null; // Guard auth ditangani per layar melalui authStateProvider.
  },
  routes: [
    GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
    GoRoute(
      path: '/two-factor/:userId',
      builder: (context, state) => TwoFactorChallengeScreen(
        userId: int.parse(state.pathParameters['userId'] ?? '0'),
      ),
    ),
    StatefulShellRoute.indexedStack(
      builder: (context, state, navigationShell) =>
          AppShell(navigationShell: navigationShell),
      branches: [
        StatefulShellBranch(
          routes: [
            GoRoute(
              path: '/dashboard',
              builder: (context, state) => const DashboardScreen(),
            ),
          ],
        ),
        StatefulShellBranch(
          routes: [
            GoRoute(
              path: '/scan',
              builder: (context, state) => const SoonScreen(
                title: 'Scan',
                note: 'Scan kamera masuk pada Fase 4.',
              ),
            ),
          ],
        ),
        StatefulShellBranch(
          routes: [
            GoRoute(
              path: '/transactions',
              builder: (context, state) => const SoonScreen(
                title: 'Transaksi',
                note: 'Modul transaksi masuk pada Fase 3.',
              ),
            ),
          ],
        ),
        StatefulShellBranch(
          routes: [
            GoRoute(
              path: '/stock',
              builder: (context, state) => const StockListScreen(),
            ),
          ],
        ),
        StatefulShellBranch(
          routes: [
            GoRoute(
              path: '/more',
              builder: (context, state) => const SoonScreen(
                title: 'Lainnya',
                note: 'Profil, label, laporan, dan lainnya masuk pada Fase 3.',
              ),
            ),
          ],
        ),
      ],
    ),
  ],
);

class WsmApp extends ConsumerWidget {
  const WsmApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MaterialApp.router(
      title: AppConfig.appName,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      routerConfig: _router,
      debugShowCheckedModeBanner: false,
    );
  }
}
