import 'package:flutter/material.dart';

import '../theme/tokens.dart';

class DataListRow extends StatelessWidget {
  const DataListRow({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
    this.badge,
    this.onTap,
    this.leading,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget? badge;
  final Widget? leading;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.lg,
        vertical: AppSpacing.xs,
      ),
      leading: leading,
      title: Row(
        children: [
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
          ),
          if (badge != null) ...[const SizedBox(width: AppSpacing.sm), badge!],
        ],
      ),
      subtitle: subtitle != null
          ? Text(
              subtitle!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppText.caption,
            )
          : null,
      trailing: trailing,
    );
  }
}

class SegmentedTabs extends StatelessWidget {
  const SegmentedTabs({
    super.key,
    required this.tabs,
    required this.selected,
    required this.onChanged,
  });

  final List<String> tabs;
  final int selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final border = AppColors.border(Theme.of(context).brightness);

    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.surface(Theme.of(context).brightness),
        borderRadius: BorderRadius.circular(AppRadius.button),
        border: Border.all(color: border),
      ),
      child: Row(
        children: [
          for (var i = 0; i < tabs.length; i++)
            Expanded(
              child: GestureDetector(
                onTap: () => onChanged(i),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  decoration: BoxDecoration(
                    color: selected == i
                        ? AppColors.indigo
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(AppRadius.button - 2),
                  ),
                  child: Text(
                    tabs[i],
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: selected == i
                          ? Colors.white
                          : AppColors.muted(Theme.of(context).brightness),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class FilterChipsRow extends StatelessWidget {
  const FilterChipsRow({
    super.key,
    required this.options,
    required this.value,
    required this.onSelected,
  });

  final List<String> options;
  final String? value;
  final ValueChanged<String?> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 40,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: options.length,
        separatorBuilder: (context, index) =>
            const SizedBox(width: AppSpacing.sm),
        itemBuilder: (context, index) {
          final option = options[index];
          final selected = option == value;

          return ChoiceChip(
            label: Text(option),
            selected: selected,
            onSelected: (_) => onSelected(selected ? null : option),
          );
        },
      ),
    );
  }
}

class AppButton extends StatelessWidget {
  const AppButton.primary(
    this.label, {
    super.key,
    this.onPressed,
    this.icon,
    this.loading = false,
  }) : variant = AppButtonVariant.primary;

  const AppButton.secondary(
    this.label, {
    super.key,
    this.onPressed,
    this.icon,
    this.loading = false,
  }) : variant = AppButtonVariant.secondary;

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final AppButtonVariant variant;

  @override
  Widget build(BuildContext context) {
    final child = loading
        ? const SizedBox(
            height: 18,
            width: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        : Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 18),
                const SizedBox(width: AppSpacing.sm),
              ],
              Text(label),
            ],
          );

    final effective = loading ? null : onPressed;

    if (variant == AppButtonVariant.secondary) {
      return OutlinedButton(
        onPressed: effective,
        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
        child: child,
      );
    }

    return FilledButton(onPressed: effective, child: child);
  }
}

enum AppButtonVariant { primary, secondary }
