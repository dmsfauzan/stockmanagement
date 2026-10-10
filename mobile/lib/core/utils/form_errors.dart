import '../network/api_exception.dart';

/// Ubah error API menjadi pesan yang ramah form.
///
/// Error validasi Laravel (`errors`) dipetakan ke label baris/field, mis.
/// `items.0.quantity` → `Baris 1 · quantity: Jumlah harus lebih dari 0.`
String describeApiError(Object error) {
  if (error is ApiException) {
    final errors = error.errors;

    if (errors != null && errors.isNotEmpty) {
      return errors.entries
          .map((e) => '${_humanizeKey(e.key)}: ${_message(e.value)}')
          .join('\n');
    }

    return error.message;
  }

  return '$error';
}

/// `true` bila error berasal dari antrean offline (bukan kegagalan final).
bool isQueuedError(Object error) => error is ApiException && error.queued;

String _message(dynamic value) {
  if (value is List) return value.map((e) => '$e').join(', ');

  return '$value';
}

String _humanizeKey(String key) {
  final match = RegExp(r'^([a-z_]+)\.(\d+)\.([a-z_]+)$').firstMatch(key);

  if (match != null) {
    final index = int.tryParse(match.group(2)!) ?? 0;

    return 'Baris ${index + 1} · ${_label(match.group(3)!)}';
  }

  return _label(key);
}

String _label(String key) => key.replaceAll('_', ' ');
