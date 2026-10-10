import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/tokens.dart';
import '../../../../core/widgets/picker.dart';
import '../../../../data/providers.dart';
import '../../../../data/transactions/transaction_models.dart';
import 'picker_loaders.dart';
import 'txn_line_card.dart';

class TransactionFormCreateScreen extends ConsumerStatefulWidget {
  const TransactionFormCreateScreen({super.key, required this.type});

  final TxnType type;

  @override
  ConsumerState<TransactionFormCreateScreen> createState() =>
      _TransactionFormCreateScreenState();
}

class _TransactionFormCreateScreenState
    extends ConsumerState<TransactionFormCreateScreen> {
  late final List<TxnLineForm> _lines;

  String _supplierId = '';
  String _customerId = '';
  String _warehouseId = '';
  String _warehouseToId = '';
  String _fromLocationId = '';
  String _toLocationId = '';
  String _locationId = '';
  String _reasonOrDestination = '';
  String _notes = '';
  bool _saving = false;
  bool _requiresInspection = false;

  @override
  void initState() {
    super.initState();
    _lines = [TxnLineForm.create()];
  }

  @override
  void dispose() {
    for (final l in _lines) {
      l.dispose();
    }
    super.dispose();
  }

  Future<String?> _selectWarehouse({
    String? current,
    bool isSource = true,
  }) async {
    final option = await pickOption(
      context,
      title: 'Pilih Gudang',
      loader: (q) => warehouseOptions(ref, q),
    );

    return option != null ? '${option.id}' : null;
  }

  Future<String?> _selectLocation({String? warehouseId}) async {
    final option = await pickOption(
      context,
      title: 'Pilih Lokasi',
      loader: (q) => locationOptions(ref, q, warehouseId: warehouseId),
    );

    return option != null ? '${option.id}' : null;
  }

  Future<String?> _selectSupplier() async {
    final option = await pickOption(
      context,
      title: 'Pilih Supplier',
      loader: (q) => supplierOptions(ref, q),
    );

    return option != null ? '${option.id}' : null;
  }

  Future<String?> _selectCustomer() async {
    final option = await pickOption(
      context,
      title: 'Pilih Customer',
      loader: (q) => customerOptions(ref, q),
    );

    return option != null ? '${option.id}' : null;
  }

  Map<String, dynamic> _payload() {
    final items = _lines.map((l) {
      final qty = int.tryParse(l.quantity.trim()) ?? 0;
      final unitId = int.tryParse(l.unitId) ?? 0;
      final locationId = int.tryParse(l.locationId);
      final itemId = int.tryParse(l.itemId);

      return switch (widget.type) {
        TxnType.receipt => {
          'item_id': itemId,
          'quantity': qty,
          'unit_id': unitId > 0 ? unitId : null,
          'unit_cost': double.tryParse(l.price),
          'location_id': locationId,
          'batch_number': l.batch.isNotEmpty ? l.batch : null,
          'expiry_date': l.expiry.isNotEmpty ? l.expiry : null,
          'notes': l.notes.isNotEmpty ? l.notes : null,
        },
        TxnType.issue => {
          'item_id': itemId,
          'quantity': qty,
          'unit_id': unitId > 0 ? unitId : null,
          'location_id': locationId,
          'notes': l.notes.isNotEmpty ? l.notes : null,
        },
        TxnType.adjustment => {
          'item_id': itemId,
          'actual_quantity': qty,
          'notes': l.notes.isNotEmpty ? l.notes : null,
        },
        TxnType.opname => {
          'item_id': itemId,
          'system_quantity': 0,
          'physical_quantity': qty,
          'notes': l.notes.isNotEmpty ? l.notes : null,
        },
        TxnType.transfer => {
          'item_id': itemId,
          'quantity': qty,
          'unit_id': unitId > 0 ? unitId : null,
          'notes': l.notes.isNotEmpty ? l.notes : null,
        },
      };
    }).toList();

    final now = DateTime.now().toIso8601String().substring(0, 10);

    return switch (widget.type) {
      TxnType.receipt => {
        'transaction_date': now,
        'supplier_id': int.tryParse(_supplierId),
        'warehouse_id': int.tryParse(_warehouseId),
        'requires_inspection': _requiresInspection,
        'notes': _notes.isNotEmpty ? _notes : null,
        'items': items,
      },
      TxnType.issue => {
        'transaction_date': now,
        'customer_id': _customerId.isNotEmpty
            ? int.tryParse(_customerId)
            : null,
        'destination': _reasonOrDestination.isNotEmpty
            ? _reasonOrDestination
            : 'Mobile',
        'warehouse_id': int.tryParse(_warehouseId),
        'notes': _notes.isNotEmpty ? _notes : null,
        'items': items,
      },
      TxnType.adjustment => {
        'transaction_date': now,
        'warehouse_id': int.tryParse(_warehouseId),
        'location_id': int.tryParse(_locationId),
        'reason': _reasonOrDestination.isNotEmpty
            ? _reasonOrDestination
            : 'Koreksi mobile',
        'notes': _notes.isNotEmpty ? _notes : null,
        'items': items,
      },
      TxnType.opname => {
        'opname_date': now,
        'warehouse_id': int.tryParse(_warehouseId),
        'location_id': _locationId.isNotEmpty
            ? int.tryParse(_locationId)
            : null,
        'notes': _notes.isNotEmpty ? _notes : null,
        'items': items,
      },
      TxnType.transfer => {
        'transfer_date': now,
        'from_warehouse_id': int.tryParse(_warehouseId),
        'from_location_id': int.tryParse(_fromLocationId),
        'to_warehouse_id': int.tryParse(_warehouseToId),
        'to_location_id': int.tryParse(_toLocationId),
        'notes': _notes.isNotEmpty ? _notes : null,
        'items': items,
      },
    };
  }

  Future<void> _submit() async {
    setState(() => _saving = true);

    try {
      await ref
          .read(transactionRepositoryProvider)
          .create(widget.type, _payload());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Dokumen tersimpan — draf dibuat.')),
      );
      context.pop(true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$e'), backgroundColor: AppColors.rose),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Buat ${widget.type.label}')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          _headerFields(),
          const Divider(height: AppSpacing.xl),
          for (var i = 0; i < _lines.length; i++)
            TxnLineCard(
              index: i,
              type: widget.type,
              form: _lines[i],
              onRemove: () {
                if (_lines.length <= 1) return;
                setState(() => _lines.removeAt(i).dispose());
              },
              onPickItem: () => showItemPicker(context, ref, _lines[i]),
              onPickUnit: () => showUnitPicker(context, ref, _lines[i]),
              onPickLocation: () => showLocationPicker(
                context,
                ref,
                _lines[i],
                warehouseId: _warehouseId,
              ),
            ),
          OutlinedButton.icon(
            onPressed: () => setState(() => _lines.add(TxnLineForm.create())),
            icon: const Icon(Icons.add),
            label: const Text('Tambah baris'),
          ),
          const SizedBox(height: AppSpacing.lg),
          TextField(
            decoration: const InputDecoration(
              labelText: 'Catatan',
              hintText: 'Opsional',
            ),
            onChanged: (v) => _notes = v,
          ),
          const SizedBox(height: AppSpacing.lg),
          FilledButton(
            onPressed: _saving ? null : _submit,
            child: _saving
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Simpan'),
          ),
        ],
      ),
    );
  }

  Widget _headerFields() {
    return Column(
      children: [
        if (widget.type == TxnType.receipt) ...[
          PickerTile(
            label: 'Supplier',
            value: _supplierId.isEmpty ? null : '#$_supplierId',
            required: true,
            onTap: () async =>
                _supplierId = await _selectSupplier() ?? _supplierId,
          ),
          const SizedBox(height: AppSpacing.md),
        ],
        if (widget.type == TxnType.issue) ...[
          PickerTile(
            label: 'Customer (opsional)',
            value: _customerId.isEmpty ? null : '#$_customerId',
            onTap: () async =>
                _customerId = (await _selectCustomer()) ?? _customerId,
          ),
          const SizedBox(height: AppSpacing.md),
          TextField(
            decoration: const InputDecoration(labelText: 'Tujuan *'),
            onChanged: (v) => _reasonOrDestination = v,
          ),
          const SizedBox(height: AppSpacing.md),
        ],
        if (widget.type == TxnType.adjustment) ...[
          TextField(
            decoration: const InputDecoration(
              labelText: 'Alasan penyesuaian *',
            ),
            onChanged: (v) => _reasonOrDestination = v,
          ),
          const SizedBox(height: AppSpacing.md),
        ],
        if (widget.type == TxnType.transfer) ...[
          PickerTile(
            label: 'Gudang asal',
            value: _warehouseId.isEmpty ? null : '#$_warehouseId',
            required: true,
            onTap: () async => _warehouseId =
                await _selectWarehouse(current: _warehouseId, isSource: true) ??
                _warehouseId,
          ),
          const SizedBox(height: AppSpacing.sm),
          PickerTile(
            label: 'Lokasi asal',
            value: _fromLocationId.isEmpty ? null : '#$_fromLocationId',
            required: true,
            onTap: () async => _fromLocationId =
                await _selectLocation(warehouseId: _warehouseId) ??
                _fromLocationId,
          ),
          const SizedBox(height: AppSpacing.md),
          PickerTile(
            label: 'Gudang tujuan',
            value: _warehouseToId.isEmpty ? null : '#$_warehouseToId',
            required: true,
            onTap: () async => _warehouseToId =
                await _selectWarehouse(
                  current: _warehouseToId,
                  isSource: false,
                ) ??
                _warehouseToId,
          ),
          const SizedBox(height: AppSpacing.sm),
          PickerTile(
            label: 'Lokasi tujuan',
            value: _toLocationId.isEmpty ? null : '#$_toLocationId',
            required: true,
            onTap: () async => _toLocationId =
                await _selectLocation(warehouseId: _warehouseToId) ??
                _toLocationId,
          ),
          const SizedBox(height: AppSpacing.md),
        ] else if (widget.type != TxnType.transfer) ...[
          PickerTile(
            label: 'Gudang',
            value: _warehouseId.isEmpty ? null : '#$_warehouseId',
            required: true,
            onTap: () async =>
                _warehouseId = await _selectWarehouse() ?? _warehouseId,
          ),
          const SizedBox(height: AppSpacing.sm),
          if (widget.type != TxnType.receipt)
            PickerTile(
              label: 'Lokasi',
              value: _locationId.isEmpty ? null : '#$_locationId',
              required: widget.type == TxnType.adjustment,
              onTap: () async => _locationId =
                  await _selectLocation(warehouseId: _warehouseId) ??
                  _locationId,
            ),
          const SizedBox(height: AppSpacing.md),
        ],
        if (widget.type == TxnType.receipt)
          CheckboxListTile(
            value: _requiresInspection,
            onChanged: (v) => setState(() => _requiresInspection = v ?? false),
            title: const Text('Perlu inspeksi (karantina)'),
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
          ),
      ],
    );
  }
}
