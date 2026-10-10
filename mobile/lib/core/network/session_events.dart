import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Sinyal bahwa token/sesi tidak lagi valid (mis. HTTP 401 di tengah sesi).
/// Root aplikasi mendengarkan ini untuk logout + arahkan ke halaman masuk.
final sessionExpiredProvider = StateProvider<int>((ref) => 0);
