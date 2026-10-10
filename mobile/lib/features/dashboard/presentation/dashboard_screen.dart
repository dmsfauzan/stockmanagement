import 'package:dio/dio.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/dio_client.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/stat_card.dart';
import '../../../core/widgets/status_badge.dart';

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
                color: AppColors.sky,
                width: 6,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(3),
                ),
              ),
              BarChartRodData(
                toY: qtyOut,
                color: AppColors.rose,
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

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stock = ref.watch(dashboardStockProvider);
    final lowAsync = ref.watch(lowStockProvider);
    final turnoverAsync = ref.watch(turnoverProvider);
    final chartAsync = ref.watch(movementChartProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Dashboard')),
      body: RefreshIndicator(
        onRefresh: () => _reload(ref),
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: [
            Row(
              children: [
                Expanded(
                  child: StatCard(
                    label: 'Items (PR 15D)',
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
            const SectionHeader(title: 'Stock Movement'),
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
                  loading: () => const SizedBox(height: 120, child: Skeleton()),
                  error: (e, _) => ErrorCard(
                    message: '$e',
                    onRetry: () => ref.invalidate(movementChartProvider),
                  ),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            SectionHeader(
              title: 'Low Stock (Top 5)',
              action: TextButton(
                onPressed: () => _reload(ref),
                child: const Text('Lihat semua'),
              ),
            ),
            lowAsync.when(
              data: (items) {
                if (items.isEmpty) {
                  return const EmptyState(title: 'Tidak ada low stock');
                }
                return Card(
                  child: Column(
                    children: [
                      for (final row in items)
                        DataListRow(
                          title:
                              '${row['sku'] ?? ''} — ${row['item_name'] ?? ''}',
                          subtitle:
                              '${row['warehouse'] ?? '-'} · ${row['location'] ?? '-'}',
                          trailing: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                '${row['on_hand'] ?? 0}',
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              const StatusBadge(
                                status: 'low_stock',
                                label: 'LOW',
                              ),
                            ],
                          ),
                          onTap: () {},
                        ),
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
    );
  }
}
