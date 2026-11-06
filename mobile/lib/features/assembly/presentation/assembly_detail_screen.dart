import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/assembly/assembly_models.dart';
import '../../../data/providers.dart';

class AssemblyDetailScreen extends ConsumerStatefulWidget {
  const AssemblyDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<AssemblyDetailScreen> createState() =>
      _AssemblyDetailScreenState();
}

class _AssemblyDetailScreenState extends ConsumerState<AssemblyDetailScreen> {
  AssemblyDetail? _detail;
  bool _loading = true;
  String? _error;

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
      final detail = await ref.read(assemblyRepositoryProvider).show(widget.id);
      setState(() => _detail = detail);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;

    return Scaffold(
      appBar: AppBar(title: Text(detail?.number ?? 'Assembly Order')),
      body: _loading
          ? const ListSkeleton(rows: 6)
          : _error != null
          ? ListView(
              children: [ErrorCard(message: _error!, onRetry: _load)],
            )
          : _buildDetail(context, detail!),
    );
  }

  Widget _buildDetail(BuildContext context, AssemblyDetail detail) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        AppPageHeader(
          title: detail.number,
          trailing: StatusBadge(
            status: detail.status,
            label: detail.status.toUpperCase(),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      AppInfoRow(label: 'Jenis', value: detail.type ?? '-'),
                      AppInfoRow(
                        label: 'Barang',
                        value: '${detail.itemId ?? '-'}',
                      ),
                      AppInfoRow(label: 'Jumlah', value: '${detail.quantity}'),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              AppSectionTitle(title: 'Komponen (${detail.lines.length})'),
              const SizedBox(height: AppSpacing.md),
              Card(
                child: Column(
                  children: [
                    for (final line in detail.lines)
                      DataListRow(
                        title: 'Item #${line.itemId ?? '-'}',
                        subtitle: line.role,
                        trailing: Text('${line.quantity}'),
                      ),
                    if (detail.lines.isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(AppSpacing.lg),
                        child: Text('Belum ada komponen.'),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
