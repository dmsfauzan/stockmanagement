import 'package:flutter/material.dart';

import '../theme/tokens.dart';

/// Field tanggal dengan pemilih (date picker). Nilai ditampilkan `dd/MM/yyyy`.
class DateField extends StatelessWidget {
  const DateField({
    super.key,
    required this.label,
    required this.value,
    required this.onChanged,
    this.required = false,
    this.firstDate,
  });

  final String label;
  final DateTime? value;
  final ValueChanged<DateTime> onChanged;
  final bool required;
  final DateTime? firstDate;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = AppColors.muted(theme.brightness);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          required ? '$label *' : label,
          style: theme.textTheme.bodySmall?.copyWith(
            fontWeight: FontWeight.w600,
            color: AppColors.text(theme.brightness),
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        InkWell(
          onTap: () => _pick(context),
          borderRadius: BorderRadius.circular(AppRadius.input),
          child: InputDecorator(
            decoration: const InputDecoration(
              suffixIcon: Icon(Icons.calendar_today_outlined, size: 18),
            ),
            child: Text(
              value == null
                  ? '—'
                  : '${value!.day.toString().padLeft(2, '0')}/'
                        '${value!.month.toString().padLeft(2, '0')}/'
                        '${value!.year}',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: value == null ? muted : null,
              ),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _pick(BuildContext context) async {
    final base = value ?? DateTime.now();

    final picked = await showDatePicker(
      context: context,
      initialDate: base,
      firstDate: firstDate ?? DateTime(2020),
      lastDate: DateTime(2100),
    );

    if (picked != null) onChanged(picked);
  }
}
