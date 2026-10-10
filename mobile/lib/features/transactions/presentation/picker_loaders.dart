import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/picker.dart';
import '../../../data/providers.dart';

Future<List<PickerOption>> itemOptions(WidgetRef ref, String query) async {
  final page = await ref
      .read(itemRepositoryProvider)
      .list(search: query, perPage: 50);

  return page.items
      .map(
        (e) => PickerOption(
          id: e.id,
          label: '${e.sku} — ${e.name}',
          subtitle: e.unitCode,
        ),
      )
      .toList();
}

Future<List<PickerOption>> unitOptions(WidgetRef ref, String query) async {
  final units = await ref.read(masterRepositoryProvider).units(search: query);

  return units
      .map((e) => PickerOption(id: e.id, label: e.code, subtitle: e.name))
      .toList();
}

Future<List<PickerOption>> supplierOptions(WidgetRef ref, String query) async {
  final suppliers = await ref
      .read(masterRepositoryProvider)
      .suppliers(search: query);

  return suppliers
      .map((e) => PickerOption(id: e.id, label: e.name, subtitle: e.code))
      .toList();
}

Future<List<PickerOption>> customerOptions(WidgetRef ref, String query) async {
  final customers = await ref
      .read(masterRepositoryProvider)
      .customers(search: query);

  return customers
      .map((e) => PickerOption(id: e.id, label: e.name, subtitle: e.code))
      .toList();
}

Future<List<PickerOption>> warehouseOptions(WidgetRef ref, String query) async {
  final warehouses = await ref
      .read(masterRepositoryProvider)
      .warehouses(search: query);

  return warehouses
      .map((e) => PickerOption(id: e.id, label: e.name, subtitle: e.code))
      .toList();
}

Future<List<PickerOption>> locationOptions(
  WidgetRef ref,
  String query, {
  String? warehouseId,
}) async {
  final locations = await ref
      .read(masterRepositoryProvider)
      .locations(search: query, warehouseId: warehouseId);

  return locations
      .map(
        (e) => PickerOption(
          id: e.id,
          label: e.code,
          subtitle: e.name ?? e.warehouse?.name,
        ),
      )
      .toList();
}
