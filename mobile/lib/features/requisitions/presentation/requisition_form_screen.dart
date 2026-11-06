import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/utils/form_errors.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/date_field.dart';
import '../../../core/widgets/picker.dart';
import '../../../data/providers.dart';
import '../../transactions/presentation/picker_loaders.dart';

class _PrLine {
  final itemId = TextEditingController();
  final quantity = TextEditingController(text: '1');
  final estimatedPrice = TextEditingController(text: '0');

  String itemLabel = '';
  String unitId = '';
  String unitLabel = '';

  void dispose() {
    itemId.dispose();
    quantity.dispose();
    estimatedPrice.dispose();
  }
}

class RequisitionFormScreen extends ConsumerStatefulWidget {
  const RequisitionFormScreen({super.key});

  @override
  ConsumerState<RequisitionFormScreen> createState() =>
      _RequisitionFormScreenState();
}

class _RequisitionFormScreenState extends ConsumerState<RequisitionFormScreen> {
  late final List<_PrLine> _lines = [_PrLine()];

  String _warehouseId = '';
  String _warehouseLabel = '';
  String _notes = '';
  bool _submit = false;
  bool _saving = false;
  DateTime _requestDate = DateTime.now();
  DateTime? _requiredDate;

  @override
  void dispose() {
    for (final l in _lines) {
      l.dispose();
    }
    super.dispose();
  }

  Future<void> _pickWarehouse() async {
    final option = await pickOption(
      context,
      title: 'Pilih Gudang',
      loader: (q, p) => warehouseOptions(ref, q),
    );

    if (option != null) {
      setState(() {
        _warehouseId = '${option.id}';
        _warehouseLabel = option.label;
      });
    }
  }

  Future<void> _pickItem(_PrLine line) async {
    final option = await pickOption(
      context,
      title: 'Pilih Barang',
      loader: (q, p) => itemOptions(ref, q, p),
    );

    if (option != null) {
      setState(() {
        line.itemId.text = '${option.id}';
        line.itemLabel = option.label;
      });
    }
  }

  Future<void> _pickUnit(_PrLine line) async {
    final option = await pickOption(
      context,
      title: 'Pilih Satuan',
      loader: (q, p) => unitOptions(ref, q),
    );

    if (option != null) {
      setState(() {
        line.unitId = '${option.id}';
        line.unitLabel = option.label;
      });
    }
  }

  Future<void> _save() async {
    if (_warehouseId.isEmpty) {
      _snack('Gudang wajib dipilih.', error: true);
      return;
    }

    final items = <Map<String, dynamic>>[];

    for (var i = 0; i < _lines.length; i++) {
      final line = _lines[i];
      final itemId = int.tryParse(line.itemId.text.trim());
      final qty = int.tryParse(line.quantity.text.trim()) ?? 0;

      if (itemId == null || qty <= 0) {
        _snack('Baris #${i + 1}: barang & jumlah wajib valid.', error: true);
        return;
      }

      items.add({
        'item_id': itemId,
        'quantity': qty,
        'unit_id': int.tryParse(line.unitId),
        'estimated_price':
            double.tryParse(line.estimatedPrice.text.trim()) ?? 0,
      });
    }

    setState(() => _saving = true);

    try {
      await ref.read(requisitionRepositoryProvider).create({
        'request_date': _requestDate.toIso8601String().substring(0, 10),
        if (_requiredDate != null)
          'required_date': _requiredDate!.toIso8601String().substring(0, 10),
        'warehouse_id': int.tryParse(_warehouseId),
        'notes': _notes.isNotEmpty ? _notes : null,
        'submit': _submit,
        'items': items,
      });
      if (!mounted) return;
      _snack('Requisition tersimpan.');
      context.pop(true);
    } catch (e) {
      _snack(describeApiError(e), error: !isQueuedError(e));
    } finally {
      if (mounted) setState(() => _saving = false);
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
    return Scaffold(
      appBar: AppBar(title: const Text('Buat Requisition')),
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
                  DateField(
                    label: 'Tanggal permintaan',
                    value: _requestDate,
                    required: true,
                    onChanged: (d) => setState(() => _requestDate = d),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  DateField(
                    label: 'Dibutuhkan pada',
                    value: _requiredDate,
                    firstDate: _requestDate,
                    onChanged: (d) => setState(() => _requiredDate = d),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  PickerTile(
                    label: 'Gudang',
                    value: _warehouseLabel.isEmpty ? null : _warehouseLabel,
                    required: true,
                    onTap: _pickWarehouse,
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  CheckboxListTile(
                    value: _submit,
                    onChanged: (v) => setState(() => _submit = v ?? false),
                    title: const Text('Langsung ajukan (submit)'),
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          const AppSectionTitle(title: 'Baris'),
          const SizedBox(height: AppSpacing.sm),
          for (var i = 0; i < _lines.length; i++)
            Card(
              margin: const EdgeInsets.only(bottom: AppSpacing.md),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Text(
                          'Baris #${i + 1}',
                          style: Theme.of(context).textTheme.titleSmall,
                        ),
                        const Spacer(),
                        IconButton(
                          onPressed: _lines.length <= 1
                              ? null
                              : () => setState(
                                  () => _lines.removeAt(i).dispose(),
                                ),
                          icon: const Icon(Icons.close, size: 18),
                        ),
                      ],
                    ),
                    PickerTile(
                      label: 'Barang',
                      value: _lines[i].itemLabel.isEmpty
                          ? null
                          : _lines[i].itemLabel,
                      required: true,
                      onTap: () => _pickItem(_lines[i]),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Row(
                      children: [
                        Expanded(
                          child: AppTextField(
                            label: 'Qty *',
                            controller: _lines[i].quantity,
                            keyboardType: TextInputType.number,
                          ),
                        ),
                        const SizedBox(width: AppSpacing.md),
                        Expanded(
                          child: PickerTile(
                            label: 'Satuan',
                            value: _lines[i].unitLabel.isEmpty
                                ? null
                                : _lines[i].unitLabel,
                            onTap: () => _pickUnit(_lines[i]),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                    AppTextField(
                      label: 'Estimasi harga',
                      controller: _lines[i].estimatedPrice,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          AppButton.secondary(
            'Tambah baris',
            onPressed: () => setState(() => _lines.add(_PrLine())),
            icon: Icons.add,
          ),
          const SizedBox(height: AppSpacing.xl),
          const AppSectionTitle(title: 'Catatan'),
          const SizedBox(height: AppSpacing.sm),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: TextField(
                decoration: const InputDecoration(
                  labelText: 'Catatan',
                  hintText: 'Opsional',
                ),
                onChanged: (v) => _notes = v,
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          AppButton.primary(
            'Simpan',
            onPressed: _saving ? null : _save,
            loading: _saving,
          ),
        ],
      ),
    );
  }
}
