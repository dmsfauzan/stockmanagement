import 'package:dio/dio.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/pagination.dart';

class ItemRef {
  const ItemRef({required this.id, required this.code, required this.name});

  final int id;
  final String code;
  final String name;
}

class ItemConversion {
  const ItemConversion({
    required this.unitCode,
    this.unitName,
    required this.factor,
  });

  final String unitCode;
  final String? unitName;
  final double factor;

  factory ItemConversion.fromJson(Map<String, dynamic> j) => ItemConversion(
    unitCode: '${j['unit_code'] ?? ''}',
    unitName: j['unit_name'] as String?,
    factor: (j['factor'] as num?)?.toDouble() ?? 1,
  );
}

class ItemBom {
  const ItemBom({required this.sku, required this.quantity});

  final String sku;
  final int quantity;

  factory ItemBom.fromJson(Map<String, dynamic> j) => ItemBom(
    sku: '${j['sku'] ?? ''}',
    quantity: (j['quantity'] as num?)?.toInt() ?? 0,
  );
}

class ItemListRow {
  const ItemListRow({
    required this.id,
    required this.sku,
    required this.name,
    this.categoryName,
    this.unitCode,
    this.status,
    this.ownership,
  });

  final int id;
  final String sku;
  final String name;
  final String? categoryName;
  final String? unitCode;
  final String? status;
  final String? ownership;

  factory ItemListRow.fromJson(Map<String, dynamic> j) => ItemListRow(
    id: (j['id'] as num).toInt(),
    sku: '${j['sku'] ?? ''}',
    name: '${j['name'] ?? ''}',
    categoryName: (j['category'] as Map<String, dynamic>?)?['name'] as String?,
    unitCode: (j['unit'] as Map<String, dynamic>?)?['code'] as String?,
    status: j['status'] as String?,
    ownership: j['ownership'] as String?,
  );
}

class ItemDetail {
  const ItemDetail({
    required this.id,
    required this.sku,
    required this.name,
    this.barcode,
    this.brand,
    this.description,
    this.category,
    this.unit,
    this.consignorName,
    this.minimumStock = 0,
    this.maximumStock = 0,
    this.cost,
    this.status,
    this.ownership = 'owned',
    this.conversions = const [],
    this.bom = const [],
  });

  final int id;
  final String sku;
  final String name;
  final String? barcode;
  final String? brand;
  final String? description;
  final ItemRef? category;
  final ItemRef? unit;
  final String? consignorName;
  final int minimumStock;
  final int maximumStock;
  final dynamic cost;
  final String? status;
  final String ownership;
  final List<ItemConversion> conversions;
  final List<ItemBom> bom;

  factory ItemDetail.fromJson(Map<String, dynamic> j) {
    ItemRef? ref(Map<String, dynamic>? m) => m == null
        ? null
        : ItemRef(
            id: (m['id'] as num).toInt(),
            code: '${m['code'] ?? ''}',
            name: '${m['name'] ?? ''}',
          );

    return ItemDetail(
      id: (j['id'] as num).toInt(),
      sku: '${j['sku'] ?? ''}',
      name: '${j['name'] ?? ''}',
      barcode: j['barcode'] as String?,
      brand: j['brand'] as String?,
      description: j['description'] as String?,
      category: ref(j['category'] as Map<String, dynamic>?),
      unit: ref(j['unit'] as Map<String, dynamic>?),
      consignorName:
          (j['consignor'] as Map<String, dynamic>?)?['name'] as String?,
      minimumStock: (j['minimum_stock'] as num?)?.toInt() ?? 0,
      maximumStock: (j['maximum_stock'] as num?)?.toInt() ?? 0,
      cost: j['cost'],
      status: j['status'] as String?,
      ownership: '${j['ownership'] ?? 'owned'}',
      conversions: ((j['conversions'] as List?) ?? [])
          .map((e) => ItemConversion.fromJson(e as Map<String, dynamic>))
          .toList(),
      bom: ((j['bom'] as List?) ?? [])
          .map((e) => ItemBom.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

class ItemRepository {
  ItemRepository(this._dio);

  final Dio _dio;

  Future<PageResult<ItemListRow>> list({
    String? search,
    String? categoryId,
    String? status,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final res = await _dio.get(
        '/items',
        queryParameters: {
          'page': page,
          'per_page': perPage,
          if (search != null && search.isNotEmpty) 'search': search,
          if (categoryId != null && categoryId.isNotEmpty)
            'category_id': categoryId,
          if (status != null && status.isNotEmpty) 'status': status,
        },
      );

      final body = res.data as Map<String, dynamic>;

      return PageResult(
        items: (body['data'] as List? ?? [])
            .map((e) => ItemListRow.fromJson(e as Map<String, dynamic>))
            .toList(),
        pagination: Pagination.fromJson(body['meta'] as Map<String, dynamic>?),
      );
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat barang.');
    }
  }

  Future<ItemDetail> show(int id) async {
    try {
      final res = await _dio.get(
        '/items/$id',
        queryParameters: const {'include': 'conversions,bom'},
      );
      final body = res.data as Map<String, dynamic>;

      return ItemDetail.fromJson((body['data'] ?? {}) as Map<String, dynamic>);
    } on DioException catch (e) {
      throw e.error is ApiException
          ? e.error as ApiException
          : ApiException('Gagal memuat detail barang.');
    }
  }
}
