import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/auth/auth_repository.dart';
import '../../../data/picking/picking_models.dart';
import '../../../data/providers.dart';

class PickingDetailScreen extends ConsumerStatefulWidget {
  const PickingDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<PickingDetailScreen> createState() =>
      _PickingDetailScreenState();
}

class _PickingDetailScreenState extends ConsumerState<PickingDetailScreen> {
  PickListDetail? _detail;
  bool _loading = true;
  bool _busy = false;
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
      final detail = await ref.read(pickingRepositoryProvider).show(widget.id);
      setState(() => _detail = detail);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _confirm(PickLine line) async {
    final qty = TextEditingController(text: '${line.quantity}');
    final batch = TextEditingController();
    final serial = TextEditingController();

    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Konfirmasi ${line.sku ?? ''}'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: qty,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Jumlah diambil'),
            ),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: batch,
              decoration: const InputDecoration(labelText: 'Batch (opsional)'),
            ),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: serial,
              decoration: const InputDecoration(labelText: 'Serial (opsional)'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Simpan'),
          ),
        ],
      ),
    );

    if (result != true) return;

    setState(() => _busy = true);

    try {
      await ref
          .read(pickingRepositoryProvider)
          .confirm(
            widget.id,
            line.id,
            pickedQuantity: int.tryParse(qty.text.trim()) ?? line.quantity,
            batchNumber: batch.text.trim(),
            serialNumber: serial.text.trim(),
          );
      await _load();
    } catch (e) {
      _snack('$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _run(String verb) async {
    setState(() => _busy = true);

    try {
      final repo = ref.read(pickingRepositoryProvider);
      if (verb == 'complete') {
        await repo.complete(widget.id);
      } else {
        await repo.pack(widget.id);
      }
      await _load();
    } catch (e) {
      _snack('$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? AppColors.rose : null,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;

    return Scaffold(
      appBar: AppBar(title: Text(detail?.number ?? 'Pick List')),
      body: _loading
          ? const ListSkeleton(rows: 6)
          : _error != null
          ? ListView(
              children: [ErrorCard(message: _error!, onRetry: _load)],
            )
          : _buildDetail(context, detail!),
      bottomNavigationBar: (detail == null || _busy)
          ? null
          : _actionsBar(context, detail),
    );
  }

  Widget _buildDetail(BuildContext context, PickListDetail detail) {
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
                if (detail.warehouse != null)
                  Padding(
                    padding: const EdgeInsets.only(top: AppSpacing.sm),
                    child: Text(detail.warehouse!, style: AppText.caption),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        SectionHeader(title: 'Item (${detail.lines.length})'),
        Card(
          child: Column(
            children: [
              for (final line in detail.lines)
                DataListRow(
                  title: '${line.sku ?? ''} · ${line.location ?? '-'}',
                  subtitle: 'Diambil ${line.pickedQuantity}/${line.quantity}',
                  badge: StatusBadge(
                    status: line.status,
                    label: line.status.toUpperCase(),
                  ),
                  trailing:
                      (line.status == 'pending' || line.status == 'picking')
                      ? IconButton(
                          onPressed: _busy ? null : () => _confirm(line),
                          icon: const Icon(Icons.check_circle_outline),
                          tooltip: 'Konfirmasi',
                        )
                      : null,
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _actionsBar(BuildContext context, PickListDetail detail) {
    final user = ref.watch(authStateProvider).valueOrNull?.user;
    final canPick = user?.can('picking.pick') ?? false;
    final canPack = user?.can('picking.pack') ?? false;

    final buttons = <Widget>[];

    if ((detail.status == 'pending' || detail.status == 'picking') && canPick) {
      buttons.add(
        Expanded(
          child: FilledButton(
            onPressed: () => _run('complete'),
            child: const Text('Selesaikan picking'),
          ),
        ),
      );
    }

    if (detail.status == 'picked' && canPack) {
      buttons.add(
        Expanded(
          child: FilledButton(
            onPressed: () => _run('pack'),
            child: const Text('Pack'),
          ),
        ),
      );
    }

    if (buttons.isEmpty) return const SizedBox.shrink();

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Row(
          children: [
            for (var i = 0; i < buttons.length; i++) ...[
              if (i > 0) const SizedBox(width: AppSpacing.sm),
              buttons[i],
            ],
          ],
        ),
      ),
    );
  }
}
