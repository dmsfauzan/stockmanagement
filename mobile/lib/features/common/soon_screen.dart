import 'package:flutter/material.dart';

import '../../core/theme/tokens.dart';
import '../../core/widgets/common.dart';

/// Layar "segera hadir" untuk modul yang belum diimplementasi.
class SoonScreen extends StatelessWidget {
  const SoonScreen({super.key, required this.title, this.note});

  final String title;
  final String? note;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const AppIconBadge(
                icon: Icons.construction_outlined,
                size: 72,
                iconSize: 34,
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                title,
                textAlign: TextAlign.center,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                note ?? 'Modul ini akan tersedia pada fase berikutnya.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: AppColors.muted(theme.brightness),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
