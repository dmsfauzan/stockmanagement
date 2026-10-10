import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/workflow_stepper.dart';
import '../../../data/auth/auth_repository.dart';
import '../../../data/providers.dart';
import '../../../data/transactions/transaction_models.dart';

String? _permissionFor(TxnType type, String verb) => switch (type) {
  TxnType.receipt => switch (verb) {
    'submit' => 'goods_receipt.submit',
    'approve' || 'reject' => 'goods_receipt.approve',
    'post' => 'goods_receipt.post',
    _ => null,
  },
  TxnType.issue => switch (verb) {
    'submit' => 'goods_issue.submit',
    'approve' || 'reject' => 'goods_issue.approve',
    'post' => 'goods_issue.post',
    _ => null,
  },
  TxnType.adjustment => switch (verb) {
    'submit' => 'stock.adjustment',
    'approve' || 'reject' || 'post' => 'stock.adjustment.approve',
    _ => null,
  },
  TxnType.opname => switch (verb) {
    'start' => 'stock_opname.create',
    'submit' => 'stock_opname.submit',
    'approve' || 'reject' => 'stock_opname.approve',
    _ => null,
  },
  TxnType.transfer => switch (verb) {
    'request' => 'transfer.create',
    'approve' || 'reject' || 'dispatch' => 'transfer.approve',
    'receive' || 'complete' => 'transfer.receive',
    _ => null,
  },
};

const _verbLabels = {
  'submit': 'Ajukan',
  'approve': 'Setujui',
  'reject': 'Tolak',
  'post': 'Posting',
  'start': 'Mulai',
  'request': 'Minta',
  'dispatch': 'Kirim',
  'receive': 'Terima',
  'complete': 'Selesaikan',
};

class TransactionDetailScreen extends ConsumerStatefulWidget {
  const TransactionDetailScreen({
    super.key,
    required this.type,
    required this.id,
  });

  final TxnType type;
  final int id;

  @override
  ConsumerState<TransactionDetailScreen> createState() =>
      _TransactionDetailScreenState();
}

class _TransactionDetailScreenState
    extends ConsumerState<TransactionDetailScreen> {
  TxnDetail? _detail;
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
      final detail = await ref
          .read(transactionRepositoryProvider)
          .show(widget.type, widget.id);
      setState(() => _detail = detail);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _run(String verb) async {
    String? reason;

    if (verb == 'reject') {
      reason = await _askReason();
      if (reason == null) return;
    }

    setState(() => _busy = true);

    try {
      await ref
          .read(transactionRepositoryProvider)
          .action(widget.type, widget.id, verb, reason: reason);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${_verbLabels[verb] ?? verb} berhasil.')),
      );
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$e'), backgroundColor: AppColors.rose),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<String?> _askReason() async {
    final controller = TextEditingController();

    return showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Alasan penolakan'),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(hintText: 'Tulis alasan…'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: const Text('Tolak'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;

    return Scaffold(
      appBar: AppBar(title: Text(detail?.number ?? widget.type.label)),
      body: _loading
          ? const ListSkeleton(rows: 6)
          : _error != null
          ? ListView(
              children: [ErrorCard(message: _error!, onRetry: _load)],
            )
          : _buildDetail(context, detail!),
      bottomNavigationBar: (_busy || detail == null)
          ? null
          : _actionsBar(context, detail),
    );
  }

  Widget _buildDetail(BuildContext context, TxnDetail detail) {
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
                if (detail.date != null) _row('Tanggal', detail.date!),
                if (detail.party != null)
                  _row(detail.partyLabel ?? 'Pihak', detail.party!),
                if (detail.warehouse != null)
                  _row('Warehouse', detail.warehouse!),
                if (detail.destination != null)
                  _row('Tujuan', detail.destination!),
                if (detail.reason != null)
                  _row('Alasan/Lokasi', detail.reason!),
                if (detail.requiresInspection)
                  _row('Inspeksi', 'Perlu inspeksi (karantina)'),
                if (detail.notes != null && detail.notes!.isNotEmpty)
                  _row('Catatan', detail.notes!),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        const SectionHeader(title: 'Alur'),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: WorkflowStepper(
              steps: TxnWorkflow.of(widget.type).steps,
              currentStatus: detail.status,
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        SectionHeader(title: 'Item (${detail.lines.length})'),
        Card(
          child: Column(
            children: [
              for (final line in detail.lines) _lineTile(line),
              if (detail.lines.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(AppSpacing.lg),
                  child: Text('Belum ada item.'),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _lineTile(TxnLineItem line) {
    final trailing = StringBuffer();
    if (line.difference != null) {
      trailing.write('${line.difference! >= 0 ? '+' : ''}${line.difference}');
    } else {
      trailing.write('${line.quantity}');
    }
    if (line.unitCode != null) trailing.write(' ${line.unitCode}');

    final subtitle = [
      if (line.locationCode != null) line.locationCode,
      if (line.batchNumber != null && line.batchNumber!.isNotEmpty)
        'batch ${line.batchNumber}',
      if (line.systemQuantity != null) 'sys ${line.systemQuantity}',
    ].whereType<String>().join(' · ');

    return DataListRow(
      title: '${line.sku ?? ''} — ${line.itemName ?? ''}',
      subtitle: subtitle.isEmpty ? null : subtitle,
      trailing: Text(
        trailing.toString(),
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
    );
  }

  Widget _actionsBar(BuildContext context, TxnDetail detail) {
    final user = ref.watch(authStateProvider).valueOrNull?.user;
    final verbs =
        TxnWorkflow.of(widget.type).actions[detail.status] ?? const [];

    final allowed = verbs.where((v) {
      final permission = _permissionFor(widget.type, v);
      return permission == null || (user?.can(permission) ?? false);
    }).toList();

    if (allowed.isEmpty) return const SizedBox.shrink();

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Row(
          children: [
            for (final verb in allowed) ...[
              Expanded(
                child: verb == 'reject'
                    ? OutlinedButton(
                        onPressed: () => _run(verb),
                        child: Text(_verbLabels[verb] ?? verb),
                      )
                    : FilledButton(
                        onPressed: () => _run(verb),
                        child: Text(_verbLabels[verb] ?? verb),
                      ),
              ),
              if (verb != allowed.last) const SizedBox(width: AppSpacing.sm),
            ],
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 110, child: Text(label, style: AppText.caption)),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
