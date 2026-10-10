class PickListRow {
  const PickListRow({
    required this.id,
    required this.number,
    this.warehouse,
    this.goodsIssue,
    required this.status,
  });

  final int id;
  final String number;
  final String? warehouse;
  final String? goodsIssue;
  final String status;

  factory PickListRow.fromJson(Map<String, dynamic> j) => PickListRow(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    warehouse:
        (j['warehouse'] as Map<String, dynamic>?)?['name'] as String? ??
        j['warehouse'] as String?,
    goodsIssue:
        (j['goods_issue'] as Map<String, dynamic>?)?['number'] as String? ??
        j['goods_issue'] as String?,
    status: '${j['status'] ?? ''}',
  );
}

class PickLine {
  const PickLine({
    required this.id,
    this.itemId,
    this.sku,
    this.location,
    this.quantity = 0,
    this.pickedQuantity = 0,
    required this.status,
  });

  final int id;
  final int? itemId;
  final String? sku;
  final String? location;
  final int quantity;
  final int pickedQuantity;
  final String status;

  factory PickLine.fromJson(Map<String, dynamic> j) => PickLine(
    id: (j['id'] as num).toInt(),
    itemId: (j['item_id'] as num?)?.toInt(),
    sku: j['sku'] as String?,
    location: j['location'] as String?,
    quantity: (j['quantity'] as num?)?.toInt() ?? 0,
    pickedQuantity: (j['picked_quantity'] as num?)?.toInt() ?? 0,
    status: '${j['status'] ?? ''}',
  );
}

class PickListDetail {
  const PickListDetail({
    required this.id,
    required this.number,
    this.warehouse,
    required this.status,
    this.lines = const [],
  });

  final int id;
  final String number;
  final String? warehouse;
  final String status;
  final List<PickLine> lines;

  factory PickListDetail.fromJson(Map<String, dynamic> j) => PickListDetail(
    id: (j['id'] as num).toInt(),
    number: '${j['number'] ?? ''}',
    warehouse:
        (j['warehouse'] as Map<String, dynamic>?)?['name'] as String? ??
        j['warehouse'] as String?,
    status: '${j['status'] ?? ''}',
    lines: ((j['items'] as List?) ?? [])
        .map((e) => PickLine.fromJson(e as Map<String, dynamic>))
        .toList(),
  );
}
