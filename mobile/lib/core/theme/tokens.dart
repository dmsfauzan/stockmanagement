import 'package:flutter/material.dart';

/// Design tokens aplikasi mobile — mengadopsi palet & tipografi "Semas"
/// (biru #0066FF + aksen emas #F7B006, font Poppins).
class AppColors {
  // Brand (Semas)
  static const primary = Color(0xFF0066FF);
  static const primaryHover = Color(0xFF0052CC);
  static const primarySoft = Color(0xFFE6F0FF);
  static const secondary = Color(0xFFFF2D87);
  static const gold = Color(0xFFF7B006);
  static const goldSoft = Color(0xFFFEE8BA);
  static const darkBase = Color(0xFF1E2128);

  // Alias lama (dipertahankan agar layar lain ikut ter-recolor).
  static const indigo = primary;
  static const indigoHover = primaryHover;
  static const indigoSoft = primarySoft;

  // Neutral (Semas)
  static const slate50 = Color(0xFFF8F9FE);
  static const slate100 = Color(0xFFF1F4F8);
  static const slate200 = Color(0xFFE5E5EA);
  static const slate400 = Color(0xFFA1A1AA);
  static const slate500 = Color(0xFF717171);
  static const slate700 = Color(0xFF3F3F46);
  static const slate800 = Color(0xFF2B2F38);
  static const slate900 = Color(0xFF1E2128);

  // Status
  static const success = Color(0xFF10B981);
  static const warning = Color(0xFFF59E0B);
  static const error = Color(0xFFEF4444);
  static const info = Color(0xFF0066FF);
  static const emerald = success;
  static const amber = warning;
  static const rose = error;
  static const sky = info;
  static const teal = Color(0xFF14B8A6);

  static Color surface(Brightness b) =>
      b == Brightness.light ? Colors.white : darkBase;
  static Color surfaceVariant(Brightness b) =>
      b == Brightness.light ? const Color(0xFFF0F2F5) : slate800;
  static Color background(Brightness b) =>
      b == Brightness.light ? slate50 : const Color(0xFF12141A);
  static Color border(Brightness b) =>
      b == Brightness.light ? slate200 : slate700;
  static Color text(Brightness b) =>
      b == Brightness.light ? const Color(0xFF0A0A0F) : slate100;
  static Color muted(Brightness b) =>
      b == Brightness.light ? slate500 : slate400;
}

class AppRadius {
  static const card = 16.0;
  static const button = 12.0;
  static const input = 12.0;
  static const chip = 999.0;
  static const header = 48.0;
}

class AppSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 24.0;
}

class AppText {
  static const family = 'Poppins';

  static const display = TextStyle(
    fontSize: 24,
    fontWeight: FontWeight.w700,
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
    'normal': AppColors.success,
    'good': AppColors.success,
    'active': AppColors.success,
    'posted': AppColors.success,
    'completed': AppColors.success,
    'fulfilled': AppColors.success,
    'incoming': AppColors.success,
    'approved': AppColors.primary,
    'low': AppColors.warning,
    'low_stock': AppColors.warning,
    'pending': AppColors.warning,
    'submitted': AppColors.warning,
    'requested': AppColors.warning,
    'draft': AppColors.slate500,
    'inactive': AppColors.slate500,
    'cancelled': AppColors.slate500,
    'closed': AppColors.slate500,
    'out': AppColors.error,
    'out_of_stock': AppColors.error,
    'rejected': AppColors.error,
    'expired': AppColors.error,
    'recalled': AppColors.error,
    'over': AppColors.info,
    'overstock': AppColors.info,
    'in_transit': AppColors.info,
    'expiring_30': AppColors.warning,
    'expiring_90': AppColors.info,
    'quarantine': AppColors.warning,
    'picking': AppColors.info,
    'picked': AppColors.teal,
    'packed': AppColors.primary,
    'short': AppColors.warning,
    'partial': AppColors.warning,
    'assembly': AppColors.primary,
    'disassembly': AppColors.warning,
  };

  static Color tone(String status) =>
      _tone[status.toLowerCase()] ?? AppColors.slate500;
}
