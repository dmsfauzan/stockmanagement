import 'package:dio/dio.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/dio_client.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/item_visual.dart';
import '../../../core/widgets/stat_card.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/auth/auth_repository.dart';

final dashboardStockProvider =
    FutureProvider.autoDispose<({int items15d, int lowCount})>((ref) async {
      final dio = ref.watch(dioProvider);

      try {
        final res = await dio.get<List<dynamic>>(
          '/stock',
          queryParameters: const {'search': '', 'per_page': 1},
        );
        final meta =
            ((res.data as Map<String, dynamic>)['meta'] ?? {})
                as Map<String, dynamic>;
        final total = (meta['total'] as num?)?.toInt() ?? 0;

        return (items15d: total, lowCount: 0);
      } on DioException catch (e) {
        throw e.error is ApiException
            ? e.error as ApiException
            : ApiException('Gagal memuat dashboard.');
      }
    });

final lowStockProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>(
  (ref) async {
    final dio = ref.watch(dioProvider);

    try {
      final res = await dio.get(
        '/stock/low',
        queryParameters: const {'per_page': 5},
      );
      final body = res.data as Map<String, dynamic>;

      return ((body['data'] as List?) ?? []).cast<Map<String, dynamic>>();
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat low stock.');
    }
  },
);

final turnoverProvider = FutureProvider.autoDispose<Map<String, dynamic>>((
  ref,
) async {
  final dio = ref.watch(dioProvider);

  try {
    final res = await dio.get('/reports/turnover');
    final body = res.data as Map<String, dynamic>;

    return (body['data'] ?? {}) as Map<String, dynamic>;
  } on DioException catch (e) {
    throw e.error is ApiException
        ? e.error as ApiException
        : ApiException('Gagal memuat turnover.');
  }
});

final movementChartProvider =
    FutureProvider.autoDispose<List<BarChartGroupData>>((ref) async {
      final dio = ref.watch(dioProvider);

      try {
        final res = await dio.get(
          '/reports/movement',
          queryParameters: const {'per_page': 7},
        );
        final body = res.data as Map<String, dynamic>;
        final items = ((body['data'] as List?) ?? []).reversed.toList();

        return List.generate(items.length, (i) {
          final row = items[i] as Map<String, dynamic>;
          final qtyIn = (row['quantity_in'] as num?)?.toDouble() ?? 0;
          final qtyOut = (row['quantity_out'] as num?)?.toDouble() ?? 0;

          return BarChartGroupData(
            x: i,
            barRods: [
              BarChartRodData(
                toY: qtyIn,
                color: AppColors.primary,
                width: 6,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(3),
                ),
              ),
              BarChartRodData(
                toY: qtyOut,
                color: AppColors.gold,
                width: 6,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(3),
                ),
              ),
            ],
          );
        });
      } on DioException catch (e) {
        throw e.error is ApiException
            ? e.error as ApiException
            : ApiException('Gagal memuat chart.');
      }
    });

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  Future<void> _reload(WidgetRef ref) async {
    ref.invalidate(dashboardStockProvider);
    ref.invalidate(lowStockProvider);
    ref.invalidate(turnoverProvider);
    ref.invalidate(movementChartProvider);
  }

  String _initials(String? name) {
    final trimmed = (name ?? '').trim();

    if (trimmed.isEmpty) return 'WS';

    final parts = trimmed.split(RegExp(r'\s+'));

    if (parts.length == 1) {
      return parts.first.substring(0, 1).toUpperCase();
    }

    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stock = ref.watch(dashboardStockProvider);
    final lowAsync = ref.watch(lowStockProvider);
    final turnoverAsync = ref.watch(turnoverProvider);
    final chartAsync = ref.watch(movementChartProvider);
    final user = ref.watch(authStateProvider).valueOrNull?.user;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () => _reload(ref),
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            AppPageHeader(
              leading: Container(
                height: 48,
                width: 48,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [AppColors.primary, AppColors.primaryHover],
                  ),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Text(
                  _initials(user?.name),
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
              ),
              title: user?.name ?? 'Pengguna',
              subtitle: 'Selamat datang kembali',
              trailing: IconButton.filledTonal(
                onPressed: () => _reload(ref),
                icon: const Icon(Icons.refresh),
                tooltip: 'Muat ulang',
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          label: 'Total Baris Stok',
                          value: stock.when(
                            data: (s) => '${s.items15d}',
                            loading: () => '…',
                            error: (e, _) => '–',
                          ),
                          icon: Icons.inventory_2_outlined,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: StatCard(
                          label: 'Turnover',
                          value: turnoverAsync.when(
                            data: (t) => '${t['turnover'] ?? 0}x',
                            loading: () => '…',
                            error: (e, _) => '–',
                          ),
                          icon: Icons.autorenew,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  const AppSectionTitle(title: 'Stock Movement'),
                  const SizedBox(height: AppSpacing.md),
                  Card(
                    child: Container(
                      height: 180,
                      padding: const EdgeInsets.all(AppSpacing.md),
                      child: chartAsync.when(
                        data: (groups) {
                          if (groups.isEmpty) {
                            return const EmptyState(
                              title: 'Belum ada movement',
                              message: 'Belum ada pergerakan stok.',
                            );
                          }
                          return BarChart(
                            BarChartData(
                              barGroups: groups,
                              titlesData: const FlTitlesData(show: false),
                              borderData: FlBorderData(show: false),
                              gridData: const FlGridData(show: false),
                            ),
                          );
                        },
                        loading: () =>
                            const SizedBox(height: 120, child: Skeleton()),
                        error: (e, _) => ErrorCard(
                          message: '$e',
                          onRetry: () => ref.invalidate(movementChartProvider),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AppSectionTitle(
                    title: 'Low Stock (Top 5)',
                    action: TextButton(
                      onPressed: () => context.go('/stock'),
                      child: const Text('Lihat semua'),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  lowAsync.when(
                    data: (items) {
                      if (items.isEmpty) {
                        return const EmptyState(title: 'Tidak ada low stock');
                      }
                      return Card(
                        clipBehavior: Clip.antiAlias,
                        child: Column(
                          children: [
                            for (var i = 0; i < items.length; i++) ...[
                              if (i > 0) const Divider(height: 1),
                              _LowStockTile(
                                row: items[i],
                                onTap: (items[i]['item_id'] as num?) != null
                                    ? () => context.push(
                                        '/item/${items[i]['item_id']}',
                                      )
                                    : null,
                              ),
                            ],
                          ],
                        ),
                      );
                    },
                    loading: () => const ListSkeleton(rows: 4),
                    error: (e, _) => ErrorCard(
                      message: '$e',
                      onRetry: () => ref.invalidate(lowStockProvider),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LowStockTile extends StatelessWidget {
  const _LowStockTile({required this.row, this.onTap});

  final Map<String, dynamic> row;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final name = '${row['item_name'] ?? ''}';
    final sku = '${row['sku'] ?? ''}';
    final onHand = '${row['on_hand'] ?? 0}';

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Row(
          children: [
            ItemVisual(label: name, size: 44),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '$sku · ${row['warehouse'] ?? '-'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.muted(theme.brightness),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  onHand,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const StatusBadge(status: 'low_stock', label: 'LOW'),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
