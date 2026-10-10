import 'package:flutter/material.dart';

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
    return InkWell(
      onTap: () => _pick(context),
      borderRadius: BorderRadius.circular(12),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: required ? '$label *' : label,
          suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
        ),
        child: Text(
          value == null
              ? '—'
              : '${value!.day.toString().padLeft(2, '0')}/'
                    '${value!.month.toString().padLeft(2, '0')}/'
                    '${value!.year}',
        ),
      ),
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
