import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/theme/tokens.dart';
import '../../../data/providers.dart';

/// Tab Scan: memindai barcode/QR, lalu mencari barang berdasarkan kode
/// (SKU/barcode) dan membuka detailnya.
class ScanScreen extends ConsumerStatefulWidget {
  const ScanScreen({super.key});

  @override
  ConsumerState<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends ConsumerState<ScanScreen> {
  final _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );

  bool _busy = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy) return;

    final code = capture.barcodes
        .map((b) => b.rawValue)
        .whereType<String>()
        .firstWhere((v) => v.trim().isNotEmpty, orElse: () => '');

    if (code.isEmpty) return;

    setState(() => _busy = true);
    await _controller.stop();

    try {
      final result = await ref
          .read(itemRepositoryProvider)
          .list(search: code, perPage: 10);

      final items = result.items;

      if (!mounted) return;

      if (items.isEmpty) {
        _snack('Barang "$code" tidak ditemukan.');
      } else if (items.length == 1) {
        await context.push('/item/${items.first.id}');
      } else {
        await _showChoices(code, items);
      }
    } catch (e) {
      if (mounted) _snack('$e');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        await _controller.start();
      }
    }
  }

  Future<void> _showChoices(String code, List items) async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => ListView(
        shrinkWrap: true,
        children: [
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Text(
              'Hasil untuk "$code"',
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          for (final item in items)
            ListTile(
              title: Text('${item.sku} — ${item.name}'),
              onTap: () {
                Navigator.pop(context);
                context.push('/item/${item.id}');
              },
            ),
        ],
      ),
    );
  }

  void _snack(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Scan'),
        actions: [
          IconButton(
            onPressed: () => _controller.toggleTorch(),
            icon: const Icon(Icons.flash_on),
            tooltip: 'Senter',
          ),
          IconButton(
            onPressed: () => _controller.switchCamera(),
            icon: const Icon(Icons.cameraswitch),
            tooltip: 'Ganti kamera',
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),
          Align(
            alignment: Alignment.bottomCenter,
            child: Container(
              width: double.infinity,
              color: Colors.black54,
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Text(
                _busy
                    ? 'Mencari barang…'
                    : 'Arahkan kamera ke barcode / QR barang.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
