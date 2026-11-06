import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/providers.dart';
import '../../../data/tracing/traceability_repository.dart';

class TraceabilityScreen extends ConsumerStatefulWidget {
  const TraceabilityScreen({super.key});

  @override
  ConsumerState<TraceabilityScreen> createState() => _TraceabilityScreenState();
}

class _TraceabilityScreenState extends ConsumerState<TraceabilityScreen> {
  final _value = TextEditingController();
  String _type = 'batch';
  TraceResult? _result;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _value.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    final value = _value.text.trim();

    if (value.isEmpty) return;

    setState(() {
      _loading = true;
      _error = null;
      _result = null;
    });

    try {
      final result = await ref
          .read(traceabilityRepositoryProvider)
          .trace(type: _type, value: value);
      setState(() => _result = result);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Lacak Batch / Serial')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            initialValue: _type,
                            decoration: const InputDecoration(
                              labelText: 'Jenis',
                              isDense: true,
                            ),
                            items: const [
                              DropdownMenuItem(
                                value: 'batch',
                                child: Text('Batch'),
                              ),
                              DropdownMenuItem(
                                value: 'serial',
                                child: Text('Serial'),
                              ),
                              DropdownMenuItem(
                                value: 'lot',
                                child: Text('Lot'),
                              ),
                            ],
                            onChanged: (v) =>
                                setState(() => _type = v ?? 'batch'),
                          ),
                        ),
                        const SizedBox(width: AppSpacing.md),
                        Expanded(
                          flex: 2,
                          child: AppTextField(
                            label: 'Nilai',
                            hint: 'Nomor batch/serial',
                            controller: _value,
                            textInputAction: TextInputAction.search,
                            onFieldSubmitted: (_) => _search(),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    AppButton.primary(
                      'Lacak',
                      icon: Icons.search,
                      loading: _loading,
                      onPressed: _search,
                    ),
                  ],
                ),
              ),
            ),
          ),
          Expanded(child: _body()),
        ],
      ),
    );
  }

  Widget _body() {
    if (_loading) return const ListSkeleton(rows: 8);
    if (_error != null) {
      return ListView(
        children: [ErrorCard(message: _error!, onRetry: _search)],
      );
    }

    final result = _result;
    if (result == null) {
      return const EmptyState(
        title: 'Belum ada pencarian',
        message: 'Masukkan nomor batch/serial lalu tekan Lacak.',
      );
    }

    if (result.events.isEmpty) {
      return const EmptyState(
        title: 'Tidak ditemukan',
        message: 'Tidak ada riwayat untuk nilai ini.',
      );
    }

    final summary = result.summary;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Row(
              children: [
                _metric('Masuk', '${summary['in'] ?? 0}', AppColors.emerald),
                _metric('Keluar', '${summary['out'] ?? 0}', AppColors.rose),
                _metric('Saldo', '${summary['balance'] ?? 0}', null),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        SectionHeader(title: 'Riwayat (${result.events.length})'),
        for (final event in result.events)
          Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.md),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AppIconBadge(
                    icon: event.quantityIn > 0
                        ? Icons.south_west
                        : Icons.north_east,
                    color: event.quantityIn > 0
                        ? AppColors.success
                        : AppColors.error,
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                event.referenceNumber ??
                                    event.referenceLabel ??
                                    '-',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                            StatusBadge(
                              status: event.type ?? '',
                              label: (event.type ?? '').toUpperCase(),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          '${event.date ?? ''} · ${event.warehouse ?? '-'} · '
                          '${event.location ?? '-'}',
                          style: AppText.caption,
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          '${event.quantityIn > 0 ? '+${event.quantityIn}' : ''}'
                          '${event.quantityOut > 0 ? ' -${event.quantityOut}' : ''}'
                          '  → saldo ${event.balanceAfter}',
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _metric(String label, String value, Color? color) {
    return Expanded(
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 18,
              color: color,
            ),
          ),
          Text(label, style: AppText.caption),
        ],
      ),
    );
  }
}
