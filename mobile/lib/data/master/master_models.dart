import 'package:dio/dio.dart';

class Category {
  const Category({
    required this.id,
    required this.code,
    required this.name,
    this.status,
  });

  final int id;
  final String code;
  final String name;
  final String? status;

  factory Category.fromJson(Map<String, dynamic> j) => Category(
    id: (j['id'] as num).toInt(),
    code: '${j['code'] ?? ''}',
    name: '${j['name'] ?? ''}',
    status: j['status'] as String?,
  );
}

class Unit {
  const Unit({required this.id, required this.code, required this.name});

  final int id;
  final String code;
  final String name;

  factory Unit.fromJson(Map<String, dynamic> j) => Unit(
    id: (j['id'] as num).toInt(),
    code: j['code'] as String? ?? '',
    name: j['name'] as String? ?? '',
  );
}

class Supplier {
  const Supplier({
    required this.id,
    required this.code,
    required this.name,
    this.phone,
    this.email,
    this.status,
  });

  final int id;
  final String code;
  final String name;
  final String? phone;
  final String? email;
  final String? status;

  factory Supplier.fromJson(Map<String, dynamic> j) => Supplier(
    id: (j['id'] as num).toInt(),
    code: '${j['code'] ?? ''}',
    name: '${j['name'] ?? ''}',
    phone: j['phone'] as String?,
    email: j['email'] as String?,
    status: j['status'] as String?,
  );
}

class Customer {
  const Customer({
    required this.id,
    required this.code,
    required this.name,
    this.type,
    this.status,
  });

  final int id;
  final String code;
  final String name;
  final String? type;
  final String? status;

  factory Customer.fromJson(Map<String, dynamic> j) => Customer(
    id: (j['id'] as num).toInt(),
    code: '${j['code'] ?? ''}',
    name: '${j['name'] ?? ''}',
    type: j['type'] as String?,
    status: j['status'] as String?,
  );
}

class Warehouse {
  const Warehouse({
    required this.id,
    required this.code,
    required this.name,
    this.address,
    this.status,
  });

  final int id;
  final String code;
  final String name;
  final String? address;
  final String? status;

  factory Warehouse.fromJson(Map<String, dynamic> j) => Warehouse(
    id: (j['id'] as num).toInt(),
    code: '${j['code'] ?? ''}',
    name: '${j['name'] ?? ''}',
    address: j['address'] as String?,
    status: j['status'] as String?,
  );
}

class Location {
  const Location({
    required this.id,
    required this.code,
    this.name,
    this.warehouse,
  });

  final int id;
  final String code;
  final String? name;
  final WarehouseMeta? warehouse;

  factory Location.fromJson(Map<String, dynamic> j) => Location(
    id: (j['id'] as num).toInt(),
    code: '${j['code'] ?? ''}',
    name: j['name'] as String?,
    warehouse: j['warehouse'] != null
        ? WarehouseMeta.fromJson(j['warehouse'] as Map<String, dynamic>)
        : null,
  );
}

class WarehouseMeta {
  const WarehouseMeta({
    required this.id,
    required this.code,
    required this.name,
  });

  final int id;
  final String code;
  final String name;

  factory WarehouseMeta.fromJson(Map<String, dynamic> j) => WarehouseMeta(
    id: (j['id'] as num).toInt(),
    code: '${j['code'] ?? ''}',
    name: '${j['name'] ?? ''}',
  );
}

class CurrencyMeta {
  const CurrencyMeta({
    required this.code,
    required this.name,
    this.symbol,
    this.isBase,
    this.rateToBase,
  });

  final String code;
  final String name;
  final String? symbol;
  final bool? isBase;
  final double? rateToBase;

  factory CurrencyMeta.fromJson(Map<String, dynamic> j) => CurrencyMeta(
    code: '${j['code'] ?? ''}',
    name: '${j['name'] ?? ''}',
    symbol: j['symbol'] as String?,
    isBase: j['is_base'] as bool?,
    rateToBase: (j['rate_to_base'] as num?)?.toDouble(),
  );
}

class SimpleListRepository {
  SimpleListRepository(this._dio);

  final Dio _dio;

  Future<List<Category>> categories({String? search}) async {
    final res = await _dio.get(
      '/categories',
      queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );
    final body = res.data as Map<String, dynamic>;

    return ((body['data'] as List?) ?? [])
        .map((e) => Category.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<Unit>> units({String? search}) async {
    final res = await _dio.get(
      '/units',
      queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );
    final body = res.data as Map<String, dynamic>;

    return ((body['data'] as List?) ?? [])
        .map((e) => Unit.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<Supplier>> suppliers({String? search}) async {
    final res = await _dio.get(
      '/suppliers',
      queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );
    final body = res.data as Map<String, dynamic>;

    return ((body['data'] as List?) ?? [])
        .map((e) => Supplier.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<Customer>> customers({String? search}) async {
    final res = await _dio.get(
      '/customers',
      queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );
    final body = res.data as Map<String, dynamic>;

    return ((body['data'] as List?) ?? [])
        .map((e) => Customer.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<Warehouse>> warehouses({String? search}) async {
    final res = await _dio.get(
      '/warehouses',
      queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );
    final body = res.data as Map<String, dynamic>;

    return ((body['data'] as List?) ?? [])
        .map((e) => Warehouse.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<Location>> locations({
    String? search,
    String? warehouseId,
  }) async {
    final res = await _dio.get(
      '/locations',
      queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
        if (warehouseId != null && warehouseId.isNotEmpty)
          'warehouse_id': warehouseId,
      },
    );
    final body = res.data as Map<String, dynamic>;

    return ((body['data'] as List?) ?? [])
        .map((e) => Location.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<({String base, List<CurrencyMeta> rows})> currencies() async {
    final res = await _dio.get('/currencies');
    final body = res.data as Map<String, dynamic>;
    final data = (body['data'] ?? {}) as Map<String, dynamic>;
    final base = data['base'] as String? ?? 'IDR';
    final rows = ((data['currencies'] as List?) ?? [])
        .map((e) => CurrencyMeta.fromJson(e as Map<String, dynamic>))
        .toList();

    return (base: base, rows: rows);
  }
}
