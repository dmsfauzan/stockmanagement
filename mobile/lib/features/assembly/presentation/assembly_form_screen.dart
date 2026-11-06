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

class AssemblyFormScreen extends ConsumerStatefulWidget {
  const AssemblyFormScreen({super.key});

  @override
  ConsumerState<AssemblyFormScreen> createState() => _AssemblyFormScreenState();
}

class _AssemblyFormScreenState extends ConsumerState<AssemblyFormScreen> {
  String _type = 'assembly';
  String _itemId = '';
  String _itemLabel = '';
  String _warehouseId = '';
  String _warehouseLabel = '';
  String _locationId = '';
  String _locationLabel = '';
  final _quantity = TextEditingController(text: '1');
  String _notes = '';
  bool _saving = false;
  DateTime _assemblyDate = DateTime.now();

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _pickItem() async {
    final option = await pickOption(
      context,
      title: 'Pilih Barang',
      loader: (q, p) => itemOptions(ref, q, p),
    );

    if (option != null) {
      setState(() {
        _itemId = '${option.id}';
        _itemLabel = option.label;
      });
    }
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

  Future<void> _pickLocation() async {
    final option = await pickOption(
      context,
      title: 'Pilih Lokasi',
      loader: (q, p) => locationOptions(ref, q, warehouseId: _warehouseId),
    );

    if (option != null) {
      setState(() {
        _locationId = '${option.id}';
        _locationLabel = option.label;
      });
    }
  }

  Future<void> _submit() async {
    if (_itemId.isEmpty || _warehouseId.isEmpty || _locationId.isEmpty) {
      _snack('Barang, gudang, dan lokasi wajib dipilih.', error: true);
      return;
    }

    setState(() => _saving = true);

    try {
      await ref.read(assemblyRepositoryProvider).create({
        'type': _type,
        'assembly_date': _assemblyDate.toIso8601String().substring(0, 10),
        'item_id': int.tryParse(_itemId),
        'quantity': int.tryParse(_quantity.text.trim()) ?? 1,
        'warehouse_id': int.tryParse(_warehouseId),
        'location_id': int.tryParse(_locationId),
        'notes': _notes.isNotEmpty ? _notes : null,
      });
      if (!mounted) return;
      _snack('Assembly order dibuat & diposting.');
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
      appBar: AppBar(title: const Text('Buat Perakitan')),
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
                      DropdownMenuItem(
                        value: 'assembly',
                        child: Text('Perakitan'),
                      ),
                      DropdownMenuItem(
                        value: 'disassembly',
                        child: Text('Pembongkaran'),
                      ),
                    ],
                    onChanged: (v) => setState(() => _type = v ?? 'assembly'),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  DateField(
                    label: 'Tanggal perakitan',
                    value: _assemblyDate,
                    required: true,
                    onChanged: (d) => setState(() => _assemblyDate = d),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  PickerTile(
                    label: 'Barang (ber-BOM)',
                    value: _itemLabel.isEmpty ? null : _itemLabel,
                    required: true,
                    onTap: _pickItem,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  AppTextField(
                    label: 'Jumlah *',
                    controller: _quantity,
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  PickerTile(
                    label: 'Gudang',
                    value: _warehouseLabel.isEmpty ? null : _warehouseLabel,
                    required: true,
                    onTap: _pickWarehouse,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  PickerTile(
                    label: 'Lokasi',
                    value: _locationLabel.isEmpty ? null : _locationLabel,
                    required: true,
                    onTap: _pickLocation,
                  ),
                ],
              ),
            ),
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
            'Simpan & Posting',
            onPressed: _saving ? null : _submit,
            loading: _saving,
          ),
        ],
      ),
    );
  }
}
