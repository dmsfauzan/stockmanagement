import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../data/providers.dart';
import '../../../data/recall/recall_repository.dart';

class RecallFormScreen extends ConsumerStatefulWidget {
  const RecallFormScreen({super.key});

  @override
  ConsumerState<RecallFormScreen> createState() => _RecallFormScreenState();
}

class _RecallFormScreenState extends ConsumerState<RecallFormScreen> {
  final _value = TextEditingController();
  final _reason = TextEditingController();
  String _type = 'batch';
  RecallImpact? _impact;
  bool _checking = false;
  bool _saving = false;

  @override
  void dispose() {
    _value.dispose();
    _reason.dispose();
    super.dispose();
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

  Future<void> _check() async {
    final value = _value.text.trim();
    if (value.isEmpty) {
      _snack('Nilai wajib diisi.', error: true);
      return;
    }

    setState(() {
      _checking = true;
      _impact = null;
    });

    try {
      final impact = await ref
          .read(recallRepositoryProvider)
          .impact(type: _type, value: value);
      setState(() => _impact = impact);
    } catch (e) {
      _snack('$e', error: true);
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<void> _save() async {
    final value = _value.text.trim();
    final reason = _reason.text.trim();

    if (value.isEmpty || reason.length < 3) {
      _snack('Nilai dan alasan (min 3 karakter) wajib diisi.', error: true);
      return;
    }

    setState(() => _saving = true);

    try {
      await ref
          .read(recallRepositoryProvider)
          .create(type: _type, value: value, reason: reason);
      if (!mounted) return;
      _snack('Recall ditandai aktif.');
      context.pop(true);
    } catch (e) {
      _snack('$e', error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final impact = _impact;

    return Scaffold(
      appBar: AppBar(title: const Text('Tandai Recall')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          const AppSectionTitle(title: 'Informasi'),
          const SizedBox(height: AppSpacing.sm),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: _type,
                    decoration: const InputDecoration(labelText: 'Jenis *'),
                    items: const [
                      DropdownMenuItem(value: 'batch', child: Text('Batch')),
                      DropdownMenuItem(value: 'serial', child: Text('Serial')),
                    ],
                    onChanged: (v) => setState(() => _type = v ?? 'batch'),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  AppTextField(label: 'Nilai *', controller: _value),
                  const SizedBox(height: AppSpacing.md),
                  AppTextField(
                    label: 'Alasan *',
                    controller: _reason,
                    maxLines: 2,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          AppButton.secondary(
            _checking ? 'Memeriksa…' : 'Cek dampak',
            onPressed: _checking ? null : _check,
            icon: Icons.search,
          ),
          if (impact != null) ...[
            const SizedBox(height: AppSpacing.xl),
            const AppSectionTitle(title: 'Dampak'),
            const SizedBox(height: AppSpacing.sm),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('On hand: ${impact.onHand}'),
                    Text('Stok terdampak: ${impact.stock.length}'),
                    Text('Dokumen terkait: ${impact.documents.length}'),
                    if (impact.activeRecall != null)
                      Padding(
                        padding: const EdgeInsets.only(top: AppSpacing.sm),
                        child: Text(
                          'Sudah ada recall aktif.',
                          style: TextStyle(color: AppColors.error),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.xl),
          AppButton.primary(
            'Tandai Recall',
            onPressed: _saving ? null : _save,
            loading: _saving,
          ),
        ],
      ),
    );
  }
}
