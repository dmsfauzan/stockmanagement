import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';

class TraceEvent {
  const TraceEvent({
    required this.id,
    this.date,
    this.type,
    this.quantityIn = 0,
    this.quantityOut = 0,
    this.balanceAfter = 0,
    this.qualityStatus,
    this.sku,
    this.itemName,
    this.warehouse,
    this.location,
    this.user,
    this.referenceLabel,
    this.referenceNumber,
    this.notes,
  });

  final int id;
  final String? date;
  final String? type;
  final int quantityIn;
  final int quantityOut;
  final int balanceAfter;
  final String? qualityStatus;
  final String? sku;
  final String? itemName;
  final String? warehouse;
  final String? location;
  final String? user;
  final String? referenceLabel;
  final String? referenceNumber;
  final String? notes;

  factory TraceEvent.fromJson(Map<String, dynamic> j) => TraceEvent(
    id: (j['id'] as num?)?.toInt() ?? 0,
    date: j['date'] as String?,
    type: j['transaction_type'] as String?,
    quantityIn: (j['quantity_in'] as num?)?.toInt() ?? 0,
    quantityOut: (j['quantity_out'] as num?)?.toInt() ?? 0,
    balanceAfter: (j['balance_after'] as num?)?.toInt() ?? 0,
    qualityStatus: j['quality_status'] as String?,
    sku: j['sku'] as String?,
    itemName: j['item_name'] as String?,
    warehouse: j['warehouse'] as String?,
    location: j['location'] as String?,
    user: j['user'] as String?,
    referenceLabel: j['reference_label'] as String?,
    referenceNumber: j['reference_number'] as String?,
    notes: j['notes'] as String?,
  );
}

class TraceResult {
  const TraceResult({
    this.type,
    this.value,
    this.events = const [],
    this.summary = const {},
  });

  final String? type;
  final String? value;
  final List<TraceEvent> events;
  final Map<String, dynamic> summary;

  factory TraceResult.fromJson(Map<String, dynamic> j) => TraceResult(
    type: j['type'] as String?,
    value: j['value'] as String?,
    events: ((j['events'] as List?) ?? [])
        .map((e) => TraceEvent.fromJson(e as Map<String, dynamic>))
        .toList(),
    summary: (j['summary'] as Map<String, dynamic>?) ?? const {},
  );
}

class TraceabilityRepository {
  TraceabilityRepository(this._dio);

  final Dio _dio;

  Future<TraceResult> trace({
    required String type,
    required String value,
  }) async {
    try {
      final res = await _dio.get(
        '/traceability/$type/${Uri.encodeComponent(value)}',
      );
      final data =
          ((res.data as Map<String, dynamic>)['data'] ?? {})
              as Map<String, dynamic>;

      return TraceResult.fromJson(data);
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal melacak.');
    }
  }
}
