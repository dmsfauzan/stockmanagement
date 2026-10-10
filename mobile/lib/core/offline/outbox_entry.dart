/// Satu permintaan tulis (POST/PUT/PATCH/DELETE) yang gagal karena jaringan
/// dan disimpan untuk dikirim ulang saat koneksi kembali.
class OutboxEntry {
  const OutboxEntry({
    this.id,
    required this.method,
    required this.url,
    this.data,
    this.idempotencyKey,
    required this.createdAt,
    this.attempts = 0,
    this.nextAttemptAt,
  });

  final int? id;
  final String method;
  final String url;
  final String? data;
  final String? idempotencyKey;
  final String createdAt;
  final int attempts;
  final String? nextAttemptAt;

  factory OutboxEntry.fromMap(Map<String, dynamic> map) => OutboxEntry(
    id: map['id'] as int?,
    method: '${map['method']}',
    url: '${map['url']}',
    data: map['data'] as String?,
    idempotencyKey: map['idempotency_key'] as String?,
    createdAt: '${map['created_at']}',
    attempts: (map['attempts'] as int?) ?? 0,
    nextAttemptAt: map['next_attempt_at'] as String?,
  );

  Map<String, dynamic> toMap() => {
    if (id != null) 'id': id,
    'method': method,
    'url': url,
    'data': data,
    'idempotency_key': idempotencyKey,
    'created_at': createdAt,
    'attempts': attempts,
    'next_attempt_at': nextAttemptAt,
  };
}
