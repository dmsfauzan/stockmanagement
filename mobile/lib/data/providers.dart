import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/network/dio_client.dart';
import 'items/item_repository.dart';
import 'master/master_models.dart';
import 'stock/stock_repository.dart';

final stockRepositoryProvider = Provider<StockRepository>(
  (ref) => StockRepository(ref.watch(dioProvider)),
);
final itemRepositoryProvider = Provider<ItemRepository>(
  (ref) => ItemRepository(ref.watch(dioProvider)),
);
final masterRepositoryProvider = Provider<SimpleListRepository>(
  (ref) => SimpleListRepository(ref.watch(dioProvider)),
);
