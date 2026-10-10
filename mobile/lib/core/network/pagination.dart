/// Metadata paginasi dari envelope `meta`.
class Pagination {
  const Pagination({
    this.currentPage = 1,
    this.perPage = 15,
    this.total = 0,
    this.lastPage = 1,
  });

  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;

  factory Pagination.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const Pagination();

    return Pagination(
      currentPage: (json['current_page'] as num?)?.toInt() ?? 1,
      perPage: (json['per_page'] as num?)?.toInt() ?? 15,
      total: (json['total'] as num?)?.toInt() ?? 0,
      lastPage: (json['last_page'] as num?)?.toInt() ?? 1,
    );
  }

  bool get hasMore => currentPage < lastPage;
}

/// Halaman data generik hasil list endpoint.
class PageResult<T> {
  const PageResult({required this.items, required this.pagination});

  final List<T> items;
  final Pagination pagination;
}
