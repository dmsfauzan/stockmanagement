import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/picker.dart';
import '../../../data/transactions/transaction_models.dart';
import 'picker_loaders.dart';

/// Satu baris item pada form transaksi. Validasi dasar memastikan item, unit,
/// lokasi (untuk GR/GI/Transfer), serta jumlah wajib diisi.
class TxnLineForm {
  TxnLineForm({
    required this.itemIdController,
    required this.searchController,
    required this.unitIdController,
    required this.locationIdController,
    required this.quantityController,
    required this.priceController,
    required this.batchController,
    required this.expiryController,
    required this.reasonController,
    required this.notesController,
  });

  final TextEditingController itemIdController;
  final TextEditingController searchController;
  final TextEditingController unitIdController;
  final TextEditingController locationIdController;
  final TextEditingController quantityController;
  final TextEditingController priceController;
  final TextEditingController batchController;
  final TextEditingController expiryController;
  final TextEditingController reasonController;
  final TextEditingController notesController;

  String get itemId => itemIdController.text.trim();
  String get unitId => unitIdController.text.trim();
  String get locationId => locationIdController.text.trim();
  String get quantity => quantityController.text.trim();
  String get price => priceController.text.trim();
  String get batch => batchController.text.trim();
  String get expiry => expiryController.text.trim();
  String get reason => reasonController.text.trim();
  String get notes => notesController.text.trim();

  bool validateForType(
    TxnType type,
    BuildContext context,
    WidgetRef ref,
    int index,
  ) {
    final errors = <String, String>{};

    if (itemId.isEmpty) {
      errors['items.$index.item_id'] = 'Barang wajib diisi.';
    }

    if (quantity.isEmpty || (int.tryParse(quantity) ?? 0) <= 0) {
      errors['items.$index.quantity'] = 'Jumlah harus lebih dari 0.';
    }

    if ((type == TxnType.receipt ||
            type == TxnType.issue ||
            type == TxnType.transfer) &&
        unitId.isEmpty) {
      errors['items.$index.unit_id'] = 'Satuan wajib diisi.';
    }

    if ((type == TxnType.receipt || type == TxnType.issue) &&
        locationId.isEmpty) {
      errors['items.$index.location_id'] = 'Lokasi wajib diisi.';
    }

    if (errors.isNotEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(errors.values.join('\n')),
          backgroundColor: AppColors.rose,
        ),
      );

      return false;
    }

    return true;
  }

  void dispose() {
    itemIdController.dispose();
    searchController.dispose();
    unitIdController.dispose();
    locationIdController.dispose();
    quantityController.dispose();
    priceController.dispose();
    batchController.dispose();
    expiryController.dispose();
    reasonController.dispose();
    notesController.dispose();
  }

  static TxnLineForm create() => TxnLineForm(
    itemIdController: TextEditingController(),
    searchController: TextEditingController(),
    unitIdController: TextEditingController(),
    locationIdController: TextEditingController(),
    quantityController: TextEditingController(text: '1'),
    priceController: TextEditingController(text: '0'),
    batchController: TextEditingController(),
    expiryController: TextEditingController(),
    reasonController: TextEditingController(),
    notesController: TextEditingController(),
  );
}

/// Kartu untuk satu baris item pada form transaksi.
///
/// Tidak menggunakan `_TransferLike` dummy type — cek langsung pada [type].
class TxnLineCard extends StatelessWidget {
  const TxnLineCard({
    super.key,
    required this.index,
    required this.type,
    required this.form,
    required this.onRemove,
    required this.onPickItem,
    required this.onPickUnit,
    required this.onPickLocation,
  });

  final int index;
  final TxnType type;
  final TxnLineForm form;
  final VoidCallback onRemove;
  final Future<void> Function() onPickItem;
  final Future<void> Function() onPickUnit;
  final Future<void> Function() onPickLocation;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  'Baris #${index + 1}',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const Spacer(),
                IconButton(
                  onPressed: onRemove,
                  icon: const Icon(Icons.close, size: 18),
                  tooltip: 'Hapus baris',
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            PickerTile(
              label: 'Barang',
              value: form.searchController.text.isNotEmpty
                  ? form.searchController.text
                  : null,
              required: true,
              onTap: () async => onPickItem(),
            ),
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: form.quantityController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Qty *',
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: PickerTile(
                    label: 'Satuan',
                    value: form.unitIdController.text.isNotEmpty
                        ? '#${form.unitIdController.text}'
                        : null,
                    required: true,
                    onTap: () => onPickUnit(),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            if (type == TxnType.receipt ||
                type == TxnType.issue ||
                type == TxnType.transfer) ...[
              PickerTile(
                label: 'Lokasi',
                value: form.locationIdController.text.isNotEmpty
                    ? '#${form.locationIdController.text}'
                    : null,
                required: true,
                onTap: () => onPickLocation(),
              ),
              const SizedBox(height: AppSpacing.md),
            ],
            if (type == TxnType.receipt) ...[
              TextField(
                controller: form.priceController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(
                  labelText: 'Harga satuan',
                  isDense: true,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: form.batchController,
                decoration: const InputDecoration(
                  labelText: 'Batch number',
                  isDense: true,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: form.expiryController,
                decoration: const InputDecoration(
                  labelText: 'Expiry (YYYY-MM-DD)',
                  isDense: true,
                ),
              ),
            ],
            TextField(
              controller: form.notesController,
              decoration: const InputDecoration(
                labelText: 'Catatan',
                isDense: true,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Adapter untuk memuat opsi unit/satuan sebagai PickerOption.
Future<void> showUnitPicker(
  BuildContext context,
  WidgetRef ref,
  TxnLineForm form,
) async {
  final option = await pickOption(
    context,
    title: 'Pilih Satuan',
    loader: (q) => unitOptions(ref, q),
  );
  if (option != null) {
    form.unitIdController.text = '${option.id}';
    form.searchController.text = option.label;
  }
}

Future<void> showLocationPicker(
  BuildContext context,
  WidgetRef ref,
  TxnLineForm form, {
  String? warehouseId,
}) async {
  final option = await pickOption(
    context,
    title: 'Pilih Lokasi',
    loader: (q) => locationOptions(ref, q, warehouseId: warehouseId),
  );
  if (option != null) form.locationIdController.text = '${option.id}';
}

Future<void> showItemPicker(
  BuildContext context,
  WidgetRef ref,
  TxnLineForm form,
) async {
  final option = await pickOption(
    context,
    title: 'Pilih Barang',
    loader: (q) => itemOptions(ref, q),
  );
  if (option != null) {
    form.itemIdController.text = '${option.id}';
    form.searchController.text = option.label;
  }
}
