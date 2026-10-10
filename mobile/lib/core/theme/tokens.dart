import 'package:flutter/material.dart';

/// Design tokens aplikasi mobile (selaras web: indigo + slate).
class AppColors {
  // Brand
  static const indigo = Color(0xFF4F46E5);
  static const indigoHover = Color(0xFF4338CA);
  static const indigoSoft = Color(0xFFEEF2FF);

  // Slate
  static const slate50 = Color(0xFFF8FAFC);
  static const slate100 = Color(0xFFF1F5F9);
  static const slate200 = Color(0xFFE2E8F0);
  static const slate400 = Color(0xFF94A3B8);
  static const slate500 = Color(0xFF64748B);
  static const slate700 = Color(0xFF334155);
  static const slate800 = Color(0xFF1E293B);
  static const slate900 = Color(0xFF0F172A);

  // Status
  static const emerald = Color(0xFF059669);
  static const amber = Color(0xFFD97706);
  static const rose = Color(0xFFE11D48);
  static const sky = Color(0xFF0284C7);
  static const teal = Color(0xFF0D9488);

  static Color surface(Brightness b) =>
      b == Brightness.light ? Colors.white : slate800;
  static Color background(Brightness b) =>
      b == Brightness.light ? slate100 : slate900;
  static Color border(Brightness b) =>
      b == Brightness.light ? slate200 : slate700;
  static Color text(Brightness b) =>
      b == Brightness.light ? slate900 : slate100;
  static Color muted(Brightness b) =>
      b == Brightness.light ? slate500 : slate400;
}

class AppRadius {
  static const card = 14.0;
  static const button = 12.0;
  static const chip = 999.0;
}

class AppSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 24.0;
}

class AppText {
  static const family = 'Inter';

  static const display = TextStyle(
    fontSize: 24,
    fontWeight: FontWeight.w600,
    height: 1.2,
  );
  static const title = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w600,
    height: 1.25,
  );
  static const subtitle = TextStyle(fontSize: 16, fontWeight: FontWeight.w500);
  static const body = TextStyle(fontSize: 14);
  static const caption = TextStyle(fontSize: 12, color: AppColors.slate500);
  static const overline = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w600,
    letterSpacing: 0.6,
  );
}

/// Peta warna status → (bg, fg) untuk [StatusBadge].
class StatusColors {
  static const Map<String, Color> _tone = {
    'normal': AppColors.emerald,
    'good': AppColors.emerald,
    'active': AppColors.emerald,
    'posted': AppColors.emerald,
    'completed': AppColors.emerald,
    'fulfilled': AppColors.emerald,
    'incoming': AppColors.emerald,
    'approved': Color(0xFF2563EB),
    'low': AppColors.amber,
    'low_stock': AppColors.amber,
    'pending': AppColors.amber,
    'submitted': AppColors.amber,
    'requested': AppColors.amber,
    'draft': AppColors.slate500,
    'inactive': AppColors.slate500,
    'cancelled': AppColors.slate500,
    'closed': AppColors.slate500,
    'out': AppColors.rose,
    'out_of_stock': AppColors.rose,
    'rejected': AppColors.rose,
    'expired': AppColors.rose,
    'recalled': AppColors.rose,
    'over': AppColors.sky,
    'overstock': AppColors.sky,
    'in_transit': AppColors.sky,
    'expiring_30': AppColors.amber,
    'expiring_90': AppColors.sky,
    'quarantine': AppColors.amber,
    'picking': AppColors.sky,
    'picked': AppColors.teal,
    'packed': AppColors.indigo,
    'short': AppColors.amber,
    'partial': AppColors.amber,
    'assembly': AppColors.indigo,
    'disassembly': AppColors.amber,
  };

  static Color tone(String status) =>
      _tone[status.toLowerCase()] ?? AppColors.slate500;
}
