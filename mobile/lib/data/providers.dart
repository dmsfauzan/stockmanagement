import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/network/dio_client.dart';
import 'assembly/assembly_repository.dart';
import 'items/item_repository.dart';
import 'master/master_models.dart';
import 'picking/picking_repository.dart';
import 'quality/quality_repository.dart';
import 'recall/recall_repository.dart';
import 'requisitions/requisition_repository.dart';
import 'stock/stock_repository.dart';
import 'tools/tools_repository.dart';
import 'tracing/traceability_repository.dart';
import 'transactions/transaction_repository.dart';

final stockRepositoryProvider = Provider<StockRepository>(
  (ref) => StockRepository(ref.watch(dioProvider)),
);
final itemRepositoryProvider = Provider<ItemRepository>(
  (ref) => ItemRepository(ref.watch(dioProvider)),
);
final masterRepositoryProvider = Provider<SimpleListRepository>(
  (ref) => SimpleListRepository(ref.watch(dioProvider)),
);
final transactionRepositoryProvider = Provider<TransactionRepository>(
  (ref) => TransactionRepository(ref.watch(dioProvider)),
);
final requisitionRepositoryProvider = Provider<RequisitionRepository>(
  (ref) => RequisitionRepository(ref.watch(dioProvider)),
);
final assemblyRepositoryProvider = Provider<AssemblyRepository>(
  (ref) => AssemblyRepository(ref.watch(dioProvider)),
);
final pickingRepositoryProvider = Provider<PickingRepository>(
  (ref) => PickingRepository(ref.watch(dioProvider)),
);
final qualityRepositoryProvider = Provider<QualityRepository>(
  (ref) => QualityRepository(ref.watch(dioProvider)),
);
final traceabilityRepositoryProvider = Provider<TraceabilityRepository>(
  (ref) => TraceabilityRepository(ref.watch(dioProvider)),
);
final recallRepositoryProvider = Provider<RecallRepository>(
  (ref) => RecallRepository(ref.watch(dioProvider)),
);
final toolsRepositoryProvider = Provider<ToolsRepository>(
  (ref) => ToolsRepository(ref.watch(dioProvider)),
);
