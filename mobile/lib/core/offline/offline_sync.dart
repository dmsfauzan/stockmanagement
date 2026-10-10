import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/connectivity.dart';
import 'outbox_service.dart';

/// Menjalankan sinkronisasi antrean offline saat mulai & saat koneksi kembali.
class OfflineSync extends ConsumerStatefulWidget {
  const OfflineSync({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<OfflineSync> createState() => _OfflineSyncState();
}

class _OfflineSyncState extends ConsumerState<OfflineSync> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _flush());
  }

  Future<void> _flush() async {
    try {
      final result = await ref.read(outboxServiceProvider).flush();
      if (result.sent > 0) ref.invalidate(outboxCountProvider);
    } catch (_) {
      // Diamkan; akan dicoba lagi saat koneksi berubah.
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(connectivityProvider, (previous, next) {
      if (next.valueOrNull == true && previous?.valueOrNull != true) {
        _flush();
      }
    });

    return widget.child;
  }
}
