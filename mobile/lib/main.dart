import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'core/config/app_config.dart';
import 'core/router/app_shell.dart';
import 'core/theme/app_theme.dart';
import 'data/master/master_models.dart';
import 'data/providers.dart';
import 'data/transactions/transaction_models.dart';
import 'features/auth/presentation/login_screen.dart';
import 'features/auth/presentation/two_factor_challenge_screen.dart';
import 'features/common/soon_screen.dart';
import 'features/dashboard/presentation/dashboard_screen.dart';
import 'features/transactions/presentation/transaction_detail_screen.dart';
import 'features/transactions/presentation/transaction_form_screen.dart';
import 'features/transactions/presentation/transaction_list_screen.dart';
import 'features/transactions/presentation/transactions_hub_screen.dart';
import 'features/items/presentation/item_detail_screen.dart';
import 'features/master/presentation/master_screens.dart';
import 'features/more/presentation/more_screen.dart';
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
              builder: (context, state) => const TransactionsHubScreen(),
            ),
            GoRoute(
              path: '/tx/:type',
              builder: (context, state) {
                final type = txnTypeFromKey(state.pathParameters['type']);

                if (type == null) {
                  return const SoonScreen(
                    title: 'Transaksi',
                    note: 'Tipe dokumen tidak dikenal.',
                  );
                }

                return TransactionListScreen(type: type);
              },
            ),
            GoRoute(
              path: '/tx/:type/create',
              builder: (context, state) {
                final type = txnTypeFromKey(state.pathParameters['type']);

                if (type == null) {
                  return const SoonScreen(
                    title: 'Transaksi',
                    note: 'Tipe dokumen tidak dikenal.',
                  );
                }

                return TransactionFormCreateScreen(type: type);
              },
            ),
            GoRoute(
              path: '/tx/:type/:id',
              builder: (context, state) {
                final type = txnTypeFromKey(state.pathParameters['type']);
                final id = int.tryParse(state.pathParameters['id'] ?? '');

                if (type == null || id == null) {
                  return const SoonScreen(
                    title: 'Transaksi',
                    note: 'Modul ini segera hadir.',
                  );
                }

                return TransactionDetailScreen(type: type, id: id);
              },
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
              builder: (context, state) => const MoreScreen(),
            ),
            GoRoute(
              path: '/item/:id',
              builder: (context, state) => ItemDetailScreen(
                itemId: int.parse(state.pathParameters['id'] ?? '0'),
              ),
            ),
            GoRoute(
              path: '/master',
              builder: (context, state) => const MasterIndexScreen(),
            ),
            GoRoute(
              path: '/master/items',
              builder: (context, state) => const ItemsScreen(),
            ),
            GoRoute(
              path: '/master/categories',
              builder: (context, state) => SimpleListScreen<Category>(
                title: 'Kategori',
                future: (ref) =>
                    ref.read(masterRepositoryProvider).categories(),
                titleOf: (c) => '${c.name} — ${c.code}',
                subtitleOf: (c) => c.status,
              ),
            ),
            GoRoute(
              path: '/master/units',
              builder: (context, state) => SimpleListScreen<Unit>(
                title: 'Satuan',
                future: (ref) => ref.read(masterRepositoryProvider).units(),
                titleOf: (c) => '${c.name} — ${c.code}',
              ),
            ),
            GoRoute(
              path: '/master/suppliers',
              builder: (context, state) => SimpleListScreen<Supplier>(
                title: 'Supplier',
                future: (ref) => ref.read(masterRepositoryProvider).suppliers(),
                titleOf: (c) => c.name,
                subtitleOf: (c) => '${c.code} · ${c.status ?? '-'}',
              ),
            ),
            GoRoute(
              path: '/master/customers',
              builder: (context, state) => SimpleListScreen<Customer>(
                title: 'Customer',
                future: (ref) => ref.read(masterRepositoryProvider).customers(),
                titleOf: (c) => c.name,
                subtitleOf: (c) => '${c.code} · ${c.type ?? '-'}',
              ),
            ),
            GoRoute(
              path: '/master/warehouses',
              builder: (context, state) => SimpleListScreen<Warehouse>(
                title: 'Gudang',
                future: (ref) =>
                    ref.read(masterRepositoryProvider).warehouses(),
                titleOf: (c) => c.name,
                subtitleOf: (c) => c.code,
              ),
            ),
            GoRoute(
              path: '/master/locations',
              builder: (context, state) => SimpleListScreen<Location>(
                title: 'Lokasi',
                future: (ref) => ref.read(masterRepositoryProvider).locations(),
                titleOf: (c) => c.code,
                subtitleOf: (c) => c.name ?? c.warehouse?.name,
              ),
            ),
            GoRoute(
              path: '/master/currencies',
              builder: (context, state) => const CurrenciesScreen(),
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
