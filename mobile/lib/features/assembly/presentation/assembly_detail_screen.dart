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
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        detail.number,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                    ),
                    StatusBadge(
                      status: detail.status,
                      label: detail.status.toUpperCase(),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.sm),
                _row('Jenis', detail.type ?? '-'),
                _row('Barang', '${detail.itemId ?? '-'}'),
                _row('Jumlah', '${detail.quantity}'),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        SectionHeader(title: 'Komponen (${detail.lines.length})'),
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
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 120, child: Text(label, style: AppText.caption)),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
