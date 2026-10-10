import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/picker.dart';
import '../../../data/providers.dart';

Future<PickerPage> itemOptions(
  WidgetRef ref,
  String query, [
  int page = 1,
]) async {
  final result = await ref
      .read(itemRepositoryProvider)
      .list(search: query, page: page, perPage: 30);

  return PickerPage(
    options: result.items
        .map(
          (e) => PickerOption(
            id: e.id,
            label: '${e.sku} — ${e.name}',
            subtitle: e.unitCode,
          ),
        )
        .toList(),
    hasMore: result.pagination.hasMore,
  );
}

Future<PickerPage> unitOptions(WidgetRef ref, String query) async {
  final units = await ref.read(masterRepositoryProvider).units(search: query);

  return PickerPage.single(
    units
        .map((e) => PickerOption(id: e.id, label: e.code, subtitle: e.name))
        .toList(),
  );
}

Future<PickerPage> supplierOptions(WidgetRef ref, String query) async {
  final suppliers = await ref
      .read(masterRepositoryProvider)
      .suppliers(search: query);

  return PickerPage.single(
    suppliers
        .map((e) => PickerOption(id: e.id, label: e.name, subtitle: e.code))
        .toList(),
  );
}

Future<PickerPage> customerOptions(WidgetRef ref, String query) async {
  final customers = await ref
      .read(masterRepositoryProvider)
      .customers(search: query);

  return PickerPage.single(
    customers
        .map((e) => PickerOption(id: e.id, label: e.name, subtitle: e.code))
        .toList(),
  );
}

Future<PickerPage> warehouseOptions(WidgetRef ref, String query) async {
  final warehouses = await ref
      .read(masterRepositoryProvider)
      .warehouses(search: query);

  return PickerPage.single(
    warehouses
        .map((e) => PickerOption(id: e.id, label: e.name, subtitle: e.code))
        .toList(),
  );
}

Future<PickerPage> locationOptions(
  WidgetRef ref,
  String query, {
  String? warehouseId,
}) async {
  final locations = await ref
      .read(masterRepositoryProvider)
      .locations(search: query, warehouseId: warehouseId);

  return PickerPage.single(
    locations
        .map(
          (e) => PickerOption(
            id: e.id,
            label: e.code,
            subtitle: e.name ?? e.warehouse?.name,
          ),
        )
        .toList(),
  );
}
