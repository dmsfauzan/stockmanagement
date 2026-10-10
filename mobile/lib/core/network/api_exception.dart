/// Exception tunggal untuk semua kegagalan API, membawa pesan ramah + status.
class ApiException implements Exception {
  ApiException(
    this.message, {
    this.statusCode,
    this.errors,
    this.queued = false,
  });

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  /// `true` bila request tulis disimpan ke antrean offline (bukan gagal final).
  final bool queued;

  bool get isUnauthorized => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isNotFound => statusCode == 404;
  bool get isConflict => statusCode == 409;
  bool get isValidation => statusCode == 422;
  bool get isRateLimited => statusCode == 429;
  bool get isNetwork => statusCode == null;

  @override
  String toString() => message;
}
