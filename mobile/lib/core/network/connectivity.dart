import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// `true` bila perangkat memiliki koneksi jaringan (online).
final connectivityProvider = StreamProvider<bool>((ref) async* {
  final connectivity = Connectivity();

  final initial = await connectivity.checkConnectivity();
  yield _isOnline(initial);

  yield* connectivity.onConnectivityChanged.map(_isOnline);
});

bool _isOnline(List<ConnectivityResult> results) =>
    results.any((r) => r != ConnectivityResult.none);
