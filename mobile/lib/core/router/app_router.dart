import 'package:flutter/foundation.dart' as foundation;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/auth/auth_repository.dart';
import '../../data/master/master_models.dart';
import '../../data/providers.dart';
import '../../data/transactions/transaction_models.dart';
import '../../features/assembly/presentation/assembly_detail_screen.dart';
import '../../features/assembly/presentation/assembly_form_screen.dart';
import '../../features/assembly/presentation/assembly_list_screen.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/two_factor_challenge_screen.dart';
import '../../features/common/splash_screen.dart';
import '../../features/common/soon_screen.dart';
import '../../features/dashboard/presentation/dashboard_screen.dart';
import '../../features/items/presentation/item_detail_screen.dart';
import '../../features/master/presentation/master_screens.dart';
import '../../features/more/presentation/more_screen.dart';
import '../../features/picking/presentation/picking_detail_screen.dart';
import '../../features/picking/presentation/picking_list_screen.dart';
import '../../features/quality/presentation/quarantine_screen.dart';
import '../../features/recall/presentation/recall_form_screen.dart';
import '../../features/recall/presentation/recall_list_screen.dart';
import '../../features/reports/presentation/reports_screen.dart';
import '../../features/requisitions/presentation/requisition_detail_screen.dart';
import '../../features/requisitions/presentation/requisition_form_screen.dart';
import '../../features/requisitions/presentation/requisition_list_screen.dart';
import '../../features/scan/presentation/scan_screen.dart';
import '../../features/settings/presentation/settings_screen.dart';
import '../../features/stock/presentation/stock_list_screen.dart';
import '../../features/tools/presentation/tools_screen.dart';
import '../../features/tracing/presentation/traceability_screen.dart';
import '../../features/transactions/presentation/transaction_detail_screen.dart';
import '../../features/transactions/presentation/transaction_form_screen.dart';
import '../../features/transactions/presentation/transaction_list_screen.dart';
import '../../features/transactions/presentation/transactions_hub_screen.dart';
import 'app_shell.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = foundation.ValueNotifier<int>(0);
  ref.listen(authStateProvider, (_, _) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/dashboard',
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authStateProvider);
      final location = state.matchedLocation;
      final isLogin = location == '/login';
      final isSplash = location == '/splash';
      final inAuthFlow = isLogin || location.startsWith('/two-factor');

      if (auth.isLoading) return isSplash ? null : '/splash';

      final authenticated = auth.valueOrNull?.authenticated ?? false;

      if (!authenticated) return inAuthFlow ? null : '/login';
      if (isLogin || isSplash) return '/dashboard';

      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(
        path: '/two-factor',
        builder: (context, state) => TwoFactorChallengeScreen(
          challengeToken: (state.extra as String?) ?? '',
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
                builder: (context, state) => const ScanScreen(),
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
                  future: (ref) =>
                      ref.read(masterRepositoryProvider).suppliers(),
                  titleOf: (c) => c.name,
                  subtitleOf: (c) => '${c.code} · ${c.status ?? '-'}',
                ),
              ),
              GoRoute(
                path: '/master/customers',
                builder: (context, state) => SimpleListScreen<Customer>(
                  title: 'Customer',
                  future: (ref) =>
                      ref.read(masterRepositoryProvider).customers(),
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
                  future: (ref) =>
                      ref.read(masterRepositoryProvider).locations(),
                  titleOf: (c) => c.code,
                  subtitleOf: (c) => c.name ?? c.warehouse?.name,
                ),
              ),
              GoRoute(
                path: '/master/currencies',
                builder: (context, state) => const CurrenciesScreen(),
              ),
              GoRoute(
                path: '/reports',
                builder: (context, state) => const ReportsIndexScreen(),
              ),
              GoRoute(
                path: '/reports/:key',
                builder: (context, state) =>
                    ReportScreen(reportKey: state.pathParameters['key'] ?? ''),
              ),
              GoRoute(
                path: '/settings',
                builder: (context, state) => const SettingsScreen(),
              ),
              GoRoute(
                path: '/quarantine',
                builder: (context, state) => const QuarantineScreen(),
              ),
              GoRoute(
                path: '/traceability',
                builder: (context, state) => const TraceabilityScreen(),
              ),
              GoRoute(
                path: '/recalls',
                builder: (context, state) => const RecallListScreen(),
              ),
              GoRoute(
                path: '/recalls/create',
                builder: (context, state) => const RecallFormScreen(),
              ),
              GoRoute(
                path: '/tools',
                builder: (context, state) => const ToolsScreen(),
              ),
              GoRoute(
                path: '/requisitions',
                builder: (context, state) => const RequisitionListScreen(),
              ),
              GoRoute(
                path: '/requisitions/create',
                builder: (context, state) => const RequisitionFormScreen(),
              ),
              GoRoute(
                path: '/requisitions/:id',
                builder: (context, state) => RequisitionDetailScreen(
                  id: int.parse(state.pathParameters['id'] ?? '0'),
                ),
              ),
              GoRoute(
                path: '/assembly-orders',
                builder: (context, state) => const AssemblyListScreen(),
              ),
              GoRoute(
                path: '/assembly-orders/create',
                builder: (context, state) => const AssemblyFormScreen(),
              ),
              GoRoute(
                path: '/assembly-orders/:id',
                builder: (context, state) => AssemblyDetailScreen(
                  id: int.parse(state.pathParameters['id'] ?? '0'),
                ),
              ),
              GoRoute(
                path: '/pick-lists',
                builder: (context, state) => const PickingListScreen(),
              ),
              GoRoute(
                path: '/pick-lists/:id',
                builder: (context, state) => PickingDetailScreen(
                  id: int.parse(state.pathParameters['id'] ?? '0'),
                ),
              ),
            ],
          ),
        ],
      ),
    ],
  );
});
