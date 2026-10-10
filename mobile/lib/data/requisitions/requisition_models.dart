/// Baris daftar Purchase Requisition (PR).
class RequisitionRow {
  const RequisitionRow({
    required this.id,
    required this.number,
    this.date,
    this.warehouse,
    required this.status,
    this.poNumber,
  });

  final int id;
  final String number;
  final String? date;
  final String? warehouse;
  final String status;
  final String? poNumber;

  factory RequisitionRow.fromJson(Map<String, dynamic> j) => RequisitionRow(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    date: j['request_date'] as String?,
    warehouse:
        (j['warehouse'] as Map<String, dynamic>?)?['name'] as String? ??
        j['warehouse'] as String?,
    status: '${j['status'] ?? ''}',
    poNumber: j['purchase_order'] as String?,
  );
}

class RequisitionLine {
  const RequisitionLine({
    required this.itemId,
    this.sku,
    this.itemName,
    this.quantity = 0,
    this.estimatedPrice,
  });

  final int itemId;
  final String? sku;
  final String? itemName;
  final int quantity;
  final dynamic estimatedPrice;

  factory RequisitionLine.fromJson(Map<String, dynamic> j) => RequisitionLine(
    itemId: (j['item_id'] as num?)?.toInt() ?? 0,
    sku: j['sku'] as String?,
    itemName: j['item_name'] as String?,
    quantity: (j['quantity'] as num?)?.toInt() ?? 0,
    estimatedPrice: j['estimated_price'],
  );
}

class RequisitionDetail {
  const RequisitionDetail({
    required this.id,
    required this.number,
    required this.status,
    this.requestDate,
    this.requiredDate,
    this.warehouse,
    this.notes,
    this.lines = const [],
  });

  final int id;
  final String number;
  final String status;
  final String? requestDate;
  final String? requiredDate;
  final String? warehouse;
  final String? notes;
  final List<RequisitionLine> lines;

  factory RequisitionDetail.fromJson(Map<String, dynamic> j) =>
      RequisitionDetail(
        id: (j['id'] as num).toInt(),
        number: '${j['number'] ?? ''}',
        status: '${j['status'] ?? ''}',
        requestDate: j['request_date'] as String?,
        requiredDate: j['required_date'] as String?,
        warehouse:
            (j['warehouse'] as Map<String, dynamic>?)?['name'] as String? ??
            j['warehouse'] as String?,
        notes: j['notes'] as String?,
        lines: ((j['items'] as List?) ?? [])
            .map((e) => RequisitionLine.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class RequisitionWorkflow {
  const RequisitionWorkflow();

  static const steps = ['draft', 'submitted', 'approved', 'rejected'];

  static Map<String, List<String>> get actions => const {
    'draft': ['submit'],
    'submitted': ['approve', 'reject'],
    'approved': ['convert'],
  };
}
