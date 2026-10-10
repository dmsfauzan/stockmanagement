import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/dio_client.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';

class ReportDef {
  const ReportDef({required this.key, required this.title, required this.icon});

  final String key;
  final String title;
  final IconData icon;

  String get path => '/reports/$key';
}

const reportDefs = [
  ReportDef(
    key: 'stock',
    title: 'Laporan Stok',
    icon: Icons.inventory_2_outlined,
  ),
  ReportDef(
    key: 'valuation',
    title: 'Valuasi Persediaan',
    icon: Icons.payments_outlined,
  ),
  ReportDef(
    key: 'warehouse-comparison',
    title: 'Perbandingan Gudang',
    icon: Icons.warehouse_outlined,
  ),
  ReportDef(
    key: 'incoming',
    title: 'Barang Masuk',
    icon: Icons.move_to_inbox_outlined,
  ),
  ReportDef(
    key: 'outgoing',
    title: 'Barang Keluar',
    icon: Icons.outbox_outlined,
  ),
  ReportDef(key: 'movement', title: 'Pergerakan Stok', icon: Icons.swap_vert),
  ReportDef(
    key: 'journal',
    title: 'Jurnal / Ledger',
    icon: Icons.receipt_long_outlined,
  ),
  ReportDef(key: 'cogs', title: 'HPP (COGS)', icon: Icons.calculate_outlined),
  ReportDef(
    key: 'expiry',
    title: 'Kedaluwarsa',
    icon: Icons.event_busy_outlined,
  ),
  ReportDef(
    key: 'aging',
    title: 'Aging Stok',
    icon: Icons.hourglass_bottom_outlined,
  ),
  ReportDef(
    key: 'abc',
    title: 'Klasifikasi ABC',
    icon: Icons.leaderboard_outlined,
  ),
  ReportDef(key: 'turnover', title: 'Turnover', icon: Icons.autorenew),
  ReportDef(
    key: 'slow-moving',
    title: 'Slow Moving',
    icon: Icons.trending_down,
  ),
  ReportDef(
    key: 'replenishment',
    title: 'Replenishment',
    icon: Icons.add_shopping_cart_outlined,
  ),
  ReportDef(
    key: 'forecast',
    title: 'Prakiraan Permintaan',
    icon: Icons.query_stats_outlined,
  ),
  ReportDef(
    key: 'capacity',
    title: 'Kapasitas Gudang',
    icon: Icons.straighten_outlined,
  ),
  ReportDef(key: 'opname', title: 'Opname', icon: Icons.fact_check_outlined),
  ReportDef(key: 'adjustment', title: 'Adjustment', icon: Icons.tune_outlined),
  ReportDef(
    key: 'transfer',
    title: 'Transfer',
    icon: Icons.swap_horiz_outlined,
  ),
  ReportDef(
    key: 'returns',
    title: 'Retur',
    icon: Icons.assignment_return_outlined,
  ),
];

class ReportsIndexScreen extends StatelessWidget {
  const ReportsIndexScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Laporan')),
      body: ListView.separated(
        padding: const EdgeInsets.all(AppSpacing.lg),
        itemCount: reportDefs.length,
        separatorBuilder: (context, index) =>
            const SizedBox(height: AppSpacing.sm),
        itemBuilder: (context, index) {
          final def = reportDefs[index];

          return Card(
            child: DataListRow(
              title: def.title,
              leading: Icon(def.icon),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push(def.path),
            ),
          );
        },
      ),
    );
  }
}

class ReportScreen extends ConsumerStatefulWidget {
  const ReportScreen({super.key, required this.reportKey});

  final String reportKey;

  @override
  ConsumerState<ReportScreen> createState() => _ReportScreenState();
}

class _ReportScreenState extends ConsumerState<ReportScreen> {
  Map<String, dynamic>? _body;
  bool _loading = true;
  String? _error;
  String? _dateFrom;
  String? _dateTo;

  ReportDef get _def => reportDefs.firstWhere(
    (d) => d.key == widget.reportKey,
    orElse: () => ReportDef(
      key: widget.reportKey,
      title: widget.reportKey,
      icon: Icons.bar_chart_outlined,
    ),
  );

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await ref
          .read(dioProvider)
          .get(
            '/reports/${widget.reportKey}',
            queryParameters: {
              'per_page': 50,
              if (_dateFrom != null) 'date_from': _dateFrom,
              if (_dateTo != null) 'date_to': _dateTo,
            },
          );
      setState(() => _body = res.data as Map<String, dynamic>);
    } catch (e) {
      setState(() => _error = e is ApiException ? e.message : '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _pickDate({required bool from}) async {
    final initial = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );

    if (picked == null) return;

    final value = picked.toIso8601String().substring(0, 10);
    setState(() {
      if (from) {
        _dateFrom = value;
      } else {
        _dateTo = value;
      }
    });
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_def.title)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.lg,
              vertical: AppSpacing.sm,
            ),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickDate(from: true),
                    icon: const Icon(Icons.date_range, size: 18),
                    label: Text(_dateFrom ?? 'Dari tanggal'),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickDate(from: false),
                    icon: const Icon(Icons.date_range, size: 18),
                    label: Text(_dateTo ?? 'Sampai tanggal'),
                  ),
                ),
              ],
            ),
          ),
          Expanded(child: _content()),
        ],
      ),
    );
  }

  Widget _content() {
    if (_loading) return const ListSkeleton(rows: 8);
    if (_error != null) {
      return ListView(
        children: [ErrorCard(message: _error!, onRetry: _load)],
      );
    }

    final data = _body?['data'];

    if (data is List) {
      if (data.isEmpty) {
        return const EmptyState(title: 'Tidak ada data laporan');
      }

      return RefreshIndicator(
        onRefresh: _load,
        child: ListView.separated(
          padding: const EdgeInsets.all(AppSpacing.lg),
          itemCount: data.length,
          separatorBuilder: (context, index) =>
              const SizedBox(height: AppSpacing.sm),
          itemBuilder: (context, index) => _mapCard(data[index]),
        ),
      );
    }

    if (data is Map) {
      final rows = data['rows'];
      if (rows is List) {
        return RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            children: [
              _summaryCard(data, skip: const {'rows'}),
              for (final row in rows) _mapCard(row),
            ],
          ),
        );
      }

      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: [_summaryCard(data)],
        ),
      );
    }

    return const EmptyState(title: 'Format laporan tidak dikenal');
  }

  Widget _summaryCard(Map data, {Set<String> skip = const {}}) {
    final entries = data.entries
        .where((e) => !skip.contains(e.key) && e.value is! List)
        .toList();

    if (entries.isEmpty) return const SizedBox.shrink();

    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          children: [for (final e in entries) _kv('${e.key}', e.value)],
        ),
      ),
    );
  }

  Widget _mapCard(dynamic item) {
    if (item is! Map) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Text('$item'),
        ),
      );
    }

    final entries = item.entries.where((e) => e.value is! List).toList();

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [for (final e in entries) _kv('${e.key}', e.value)],
        ),
      ),
    );
  }

  Widget _kv(String label, dynamic value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 130,
            child: Text(label.replaceAll('_', ' '), style: AppText.caption),
          ),
          Expanded(
            child: Text(
              value == null ? '-' : '$value',
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}
