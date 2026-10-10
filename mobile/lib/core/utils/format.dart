import 'package:intl/intl.dart';

final _date = DateFormat('dd/MM/yyyy');
final _dateTime = DateFormat('dd/MM/yyyy HH:mm');
final _number = NumberFormat.decimalPattern('id_ID');

/// Format tanggal dari string ISO menjadi `dd/MM/yyyy`.
String formatDate(String? iso, {String fallback = '-'}) {
  if (iso == null || iso.isEmpty) return fallback;

  final parsed = DateTime.tryParse(iso);

  return parsed == null ? iso : _date.format(parsed.toLocal());
}

String formatDateTime(String? iso, {String fallback = '-'}) {
  if (iso == null || iso.isEmpty) return fallback;

  final parsed = DateTime.tryParse(iso);

  return parsed == null ? iso : _dateTime.format(parsed.toLocal());
}

/// Format angka dengan pemisah ribuan (locale id).
String formatNumber(num? value, {String fallback = '-'}) {
  if (value == null) return fallback;

  return _number.format(value);
}

String formatCurrency(num? value, {String prefix = 'Rp '}) {
  if (value == null) return '-';

  return '$prefix${_number.format(value)}';
}
