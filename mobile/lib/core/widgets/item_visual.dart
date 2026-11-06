import 'package:flutter/material.dart';

/// Visual barang: blok warna lembut + ikon yang diturunkan dari kategori/nama.
///
/// Karena API item belum menyediakan foto, tiap barang diberi "warna" dan
/// ikon yang deterministik (stabil per label) agar daftar terasa hidup.
class ItemVisual extends StatelessWidget {
  const ItemVisual({
    super.key,
    required this.label,
    this.size = 48,
    this.radius = 14,
  });

  final String? label;
  final double size;
  final double radius;

  static const List<Color> _palette = [
    Color(0xFF2563EB),
    Color(0xFF0EA5E9),
    Color(0xFF14B8A6),
    Color(0xFF22C55E),
    Color(0xFFF59E0B),
    Color(0xFFEF4444),
    Color(0xFF8B5CF6),
    Color(0xFFEC4899),
    Color(0xFF64748B),
  ];

  static IconData iconFor(String? label) {
    final k = (label ?? '').toLowerCase();

    if (k.contains('elek') ||
        k.contains('electronic') ||
        k.contains('komputer')) {
      return Icons.memory;
    }
    if (k.contains('makan') || k.contains('food') || k.contains('snack')) {
      return Icons.restaurant;
    }
    if (k.contains('minum') || k.contains('drink') || k.contains('air')) {
      return Icons.local_drink;
    }
    if (k.contains('obat') || k.contains('medic') || k.contains('farm')) {
      return Icons.medication;
    }
    if (k.contains('pakaian') || k.contains('cloth') || k.contains('fashion')) {
      return Icons.checkroom;
    }
    if (k.contains('alat') || k.contains('tool') || k.contains('hardware')) {
      return Icons.handyman;
    }
    if (k.contains('kertas') || k.contains('station') || k.contains('atk')) {
      return Icons.edit_note;
    }
    if (k.contains('kemas') || k.contains('pack') || k.contains('box')) {
      return Icons.inventory_2;
    }
    if (k.contains('kimia') || k.contains('chemical')) {
      return Icons.science;
    }
    if (k.contains('spare') || k.contains('part') || k.contains('mesin')) {
      return Icons.settings;
    }
    if (k.contains('kabel') || k.contains('cable') || k.contains('wire')) {
      return Icons.cable;
    }

    return Icons.inventory_2_outlined;
  }

  static Color colorFor(String? label) {
    final key = (label ?? '').trim();

    if (key.isEmpty) return _palette.first;

    // Hash deterministik (stabil lintas versi Dart/run).
    var hash = 0;
    for (final unit in key.codeUnits) {
      hash = (hash * 31 + unit) & 0x7fffffff;
    }

    return _palette[hash % _palette.length];
  }

  @override
  Widget build(BuildContext context) {
    final color = colorFor(label);

    return Container(
      height: size,
      width: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            color.withValues(alpha: 0.18),
            color.withValues(alpha: 0.06),
          ],
        ),
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: color.withValues(alpha: 0.20)),
      ),
      child: Icon(iconFor(label), color: color, size: size * 0.48),
    );
  }
}
