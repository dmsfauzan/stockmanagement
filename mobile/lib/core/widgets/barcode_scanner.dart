import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

/// Buka kamera untuk memindai satu barcode/QR, kembalikan nilainya (atau null).
Future<String?> showBarcodeScanner(BuildContext context) {
  return Navigator.of(
    context,
  ).push<String>(MaterialPageRoute(builder: (_) => const _BarcodeScanPage()));
}

class _BarcodeScanPage extends StatefulWidget {
  const _BarcodeScanPage();

  @override
  State<_BarcodeScanPage> createState() => _BarcodeScanPageState();
}

class _BarcodeScanPageState extends State<_BarcodeScanPage> {
  final _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );

  bool _done = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_done) return;

    final code = capture.barcodes
        .map((b) => b.rawValue)
        .whereType<String>()
        .firstWhere((v) => v.trim().isNotEmpty, orElse: () => '');

    if (code.isEmpty) return;

    _done = true;
    Navigator.of(context).pop(code);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pindai Barcode')),
      body: MobileScanner(controller: _controller, onDetect: _onDetect),
    );
  }
}
