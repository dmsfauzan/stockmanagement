import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/utils/format.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/workflow_stepper.dart';
import '../../../data/auth/auth_repository.dart';
import '../../../data/providers.dart';
import '../../../data/requisitions/requisition_models.dart';
import '../../transactions/presentation/picker_loaders.dart';
import '../../../core/widgets/picker.dart';

const _verbLabels = {
  'submit': 'Ajukan',
  'approve': 'Setujui',
  'reject': 'Tolak',
  'convert': 'Jadikan PO',
};

String? _permissionFor(String verb) => switch (verb) {
  'submit' => 'requisition.submit',
  'approve' || 'reject' => 'requisition.approve',
  'convert' => 'requisition.convert',
  _ => null,
};

class RequisitionDetailScreen extends ConsumerStatefulWidget {
  const RequisitionDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<RequisitionDetailScreen> createState() =>
      _RequisitionDetailScreenState();
}

class _RequisitionDetailScreenState
    extends ConsumerState<RequisitionDetailScreen> {
  RequisitionDetail? _detail;
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
          .read(requisitionRepositoryProvider)
          .show(widget.id);
      setState(() => _detail = detail);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  Future<void> _run(String verb) async {
    if (verb == 'convert') {
      await _convert();
      return;
    }

    String? reason;

    if (verb == 'reject') {
      reason = await _askReason();
      if (reason == null) return;
    }

    setState(() => _busy = true);

    try {
      await ref
          .read(requisitionRepositoryProvider)
          .action(widget.id, verb, reason: reason);
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

  Future<void> _convert() async {
    final option = await pickOption(
      context,
      title: 'Pilih Supplier (opsional)',
      loader: (q, p) => supplierOptions(ref, q),
    );

    if (!mounted) return;
    setState(() => _busy = true);

    try {
      final po = await ref
          .read(requisitionRepositoryProvider)
          .convert(widget.id, supplierId: option?.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(po != null ? 'PO $po dibuat.' : 'PO berhasil dibuat.'),
        ),
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

    try {
      return await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          scrollable: true,
          title: const Text('Alasan penolakan'),
          content: AppTextField(
            label: 'Alasan',
            controller: controller,
            maxLines: 3,
            hint: 'Tulis alasan…',
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
    } finally {
      controller.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;

    return Scaffold(
      appBar: AppBar(title: Text(detail?.number ?? 'Requisition')),
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

  Widget _buildDetail(BuildContext context, RequisitionDetail detail) {
    final info = <Widget>[
      if (detail.requestDate != null)
        AppInfoRow(
          label: 'Tgl permintaan',
          value: formatDate(detail.requestDate),
        ),
      if (detail.requiredDate != null)
        AppInfoRow(label: 'Dibutuhkan', value: formatDate(detail.requiredDate)),
      if (detail.warehouse != null)
        AppInfoRow(label: 'Gudang', value: detail.warehouse),
      if (detail.notes != null && detail.notes!.isNotEmpty)
        AppInfoRow(label: 'Catatan', value: detail.notes),
    ];

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
              if (info.isNotEmpty)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: info,
                    ),
                  ),
                ),
              const SizedBox(height: AppSpacing.lg),
              const AppSectionTitle(title: 'Alur'),
              const SizedBox(height: AppSpacing.md),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: WorkflowStepper(
                    steps: RequisitionWorkflow.steps,
                    currentStatus: detail.status,
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              AppSectionTitle(title: 'Item (${detail.lines.length})'),
              const SizedBox(height: AppSpacing.md),
              Card(
                child: Column(
                  children: [
                    for (final line in detail.lines)
                      DataListRow(
                        title: '${line.sku ?? ''} — ${line.itemName ?? ''}',
                        trailing: Text(
                          '${line.quantity}'
                          '${line.estimatedPrice != null ? ' · ${line.estimatedPrice}' : ''}',
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ),
                    if (detail.lines.isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(AppSpacing.lg),
                        child: Text('Belum ada item.'),
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

  Widget _actionsBar(BuildContext context, RequisitionDetail detail) {
    final user = ref.watch(authStateProvider).valueOrNull?.user;
    final verbs = RequisitionWorkflow.actions[detail.status] ?? const [];

    final allowed = verbs.where((v) {
      final permission = _permissionFor(v);
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
                    ? AppButton.outline(
                        _verbLabels[verb] ?? verb,
                        onPressed: () => _run(verb),
                      )
                    : AppButton.primary(
                        _verbLabels[verb] ?? verb,
                        onPressed: () => _run(verb),
                      ),
              ),
              if (verb != allowed.last) const SizedBox(width: AppSpacing.sm),
            ],
          ],
        ),
      ),
    );
  }
}
