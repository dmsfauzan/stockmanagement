import 'dart:async';

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
    final theme = Theme.of(context);

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
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
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
              style: AppText.caption.copyWith(
                color: AppColors.muted(theme.brightness),
              ),
            )
          : null,
      trailing:
          trailing ??
          (onTap != null
              ? Icon(
                  Icons.chevron_right,
                  color: AppColors.muted(theme.brightness),
                )
              : null),
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
    final brightness = Theme.of(context).brightness;
    final border = AppColors.border(brightness);

    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant(brightness),
        borderRadius: BorderRadius.circular(AppRadius.button),
        border: Border.all(color: border),
      ),
      child: Row(
        children: [
          for (var i = 0; i < tabs.length; i++)
            Expanded(
              child: GestureDetector(
                onTap: () => onChanged(i),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  decoration: BoxDecoration(
                    color: selected == i
                        ? AppColors.primary
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(AppRadius.button - 2),
                    boxShadow: selected == i
                        ? [
                            BoxShadow(
                              color: AppColors.primary.withValues(alpha: 0.25),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ]
                        : null,
                  ),
                  child: Text(
                    tabs[i],
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: selected == i
                          ? Colors.white
                          : AppColors.muted(brightness),
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

  const AppButton.outline(
    this.label, {
    super.key,
    this.onPressed,
    this.icon,
    this.loading = false,
  }) : variant = AppButtonVariant.outline;

  const AppButton.ghost(
    this.label, {
    super.key,
    this.onPressed,
    this.icon,
    this.loading = false,
  }) : variant = AppButtonVariant.ghost;

  const AppButton.destructive(
    this.label, {
    super.key,
    this.onPressed,
    this.icon,
    this.loading = false,
  }) : variant = AppButtonVariant.destructive;

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final AppButtonVariant variant;

  @override
  Widget build(BuildContext context) {
    final effective = loading ? null : onPressed;
    final spinnerColor =
        variant == AppButtonVariant.primary ||
            variant == AppButtonVariant.destructive
        ? Colors.white
        : AppColors.primary;

    final child = loading
        ? SizedBox(
            height: 20,
            width: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: spinnerColor,
            ),
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

    switch (variant) {
      case AppButtonVariant.secondary:
        return OutlinedButton(onPressed: effective, child: child);
      case AppButtonVariant.outline:
        return OutlinedButton(
          onPressed: effective,
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.text(Theme.of(context).brightness),
            side: BorderSide(
              color: AppColors.border(Theme.of(context).brightness),
            ),
          ),
          child: child,
        );
      case AppButtonVariant.ghost:
        return TextButton(
          onPressed: effective,
          style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48)),
          child: child,
        );
      case AppButtonVariant.destructive:
        return FilledButton(
          onPressed: effective,
          style: FilledButton.styleFrom(backgroundColor: AppColors.error),
          child: child,
        );
      case AppButtonVariant.primary:
        return FilledButton(onPressed: effective, child: child);
    }
  }
}

enum AppButtonVariant { primary, secondary, outline, ghost, destructive }

/// Field berlabel sederhana bergaya Semas.
class AppTextField extends StatelessWidget {
  const AppTextField({
    super.key,
    required this.label,
    this.controller,
    this.hint,
    this.obscure = false,
    this.keyboardType,
    this.prefixIcon,
    this.suffixIcon,
    this.validator,
    this.onChanged,
    this.onFieldSubmitted,
    this.textInputAction,
    this.autofocus = false,
    this.enabled = true,
    this.maxLines = 1,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final bool obscure;
  final TextInputType? keyboardType;
  final IconData? prefixIcon;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onChanged;
  final void Function(String)? onFieldSubmitted;
  final TextInputAction? textInputAction;
  final bool autofocus;
  final bool enabled;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: theme.textTheme.bodySmall?.copyWith(
            fontWeight: FontWeight.w600,
            color: AppColors.text(theme.brightness),
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        TextFormField(
          controller: controller,
          obscureText: obscure,
          keyboardType: keyboardType,
          maxLines: obscure ? 1 : maxLines,
          validator: validator,
          onChanged: onChanged,
          onFieldSubmitted: onFieldSubmitted,
          textInputAction: textInputAction,
          autofocus: autofocus,
          enabled: enabled,
          style: theme.textTheme.bodyMedium,
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: prefixIcon != null
                ? Icon(prefixIcon, size: 20, color: AppColors.slate500)
                : null,
            suffixIcon: suffixIcon,
          ),
        ),
      ],
    );
  }
}

/// Judul bagian + aksi opsional.
class AppSectionTitle extends StatelessWidget {
  const AppSectionTitle({super.key, required this.title, this.action});

  final String title;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
        ),
        ?action,
      ],
    );
  }
}

/// Baris menu: ikon kotak lembut + judul/subjudul + chevron.
class AppMenuItem extends StatelessWidget {
  const AppMenuItem({
    super.key,
    required this.title,
    this.subtitle,
    this.icon,
    this.iconColor = AppColors.primary,
    this.iconBackground,
    this.trailing,
    this.onTap,
  });

  final String title;
  final String? subtitle;
  final IconData? icon;
  final Color iconColor;
  final Color? iconBackground;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.card),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Row(
          children: [
            if (icon != null)
              AppIconBadge(
                icon: icon!,
                color: iconColor,
                background: iconBackground,
                size: 44,
                iconSize: 22,
              ),
            if (icon != null) const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      subtitle!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.muted(theme.brightness),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            trailing ??
                Icon(
                  Icons.chevron_right,
                  color: AppColors.muted(theme.brightness),
                ),
          ],
        ),
      ),
    );
  }
}

/// Kotak ikon lembut (badge) yang konsisten di seluruh aplikasi.
class AppIconBadge extends StatelessWidget {
  const AppIconBadge({
    super.key,
    required this.icon,
    this.color = AppColors.primary,
    this.background,
    this.size = 44,
    this.iconSize = 22,
  });

  final IconData icon;
  final Color color;
  final Color? background;
  final double size;
  final double iconSize;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: size,
      width: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: background ?? color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.button),
      ),
      child: Icon(icon, color: color, size: iconSize),
    );
  }
}

/// Kartu berisi deretan item menu dengan divider otomatis.
class AppMenuGroup extends StatelessWidget {
  const AppMenuGroup({super.key, required this.children, this.clip = true});

  final List<Widget> children;
  final bool clip;

  @override
  Widget build(BuildContext context) {
    final items = <Widget>[];

    for (var i = 0; i < children.length; i++) {
      if (i > 0) items.add(const Divider(height: 1));
      items.add(children[i]);
    }

    return Card(
      clipBehavior: clip ? Clip.antiAlias : Clip.none,
      child: Column(children: items),
    );
  }
}

/// Tile ringkas untuk hub/grid: ikon di atas, judul + subjudul.
class AppStatTile extends StatelessWidget {
  const AppStatTile({
    super.key,
    required this.title,
    required this.icon,
    this.subtitle,
    this.color = AppColors.primary,
    this.onTap,
  });

  final String title;
  final IconData icon;
  final String? subtitle;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AppIconBadge(icon: icon, color: color),
              const SizedBox(height: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        subtitle!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.muted(theme.brightness),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Input pencarian bergaya Semas dengan tombol bersihkan + debounce opsional.
class AppSearchField extends StatefulWidget {
  const AppSearchField({
    super.key,
    this.controller,
    this.hint = 'Cari…',
    this.onChanged,
    this.onSubmitted,
    this.debounce,
  });

  final TextEditingController? controller;
  final String hint;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final Duration? debounce;

  @override
  State<AppSearchField> createState() => _AppSearchFieldState();
}

class _AppSearchFieldState extends State<AppSearchField> {
  late final TextEditingController _controller =
      widget.controller ?? TextEditingController();
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    if (widget.controller == null) _controller.dispose();
    super.dispose();
  }

  void _handle(String value) {
    setState(() {});

    final onChanged = widget.onChanged;
    if (onChanged == null) return;

    if (widget.debounce == null) {
      onChanged(value);
      return;
    }

    _timer?.cancel();
    _timer = Timer(widget.debounce!, () => onChanged(value));
  }

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: _controller,
      textInputAction: TextInputAction.search,
      onChanged: _handle,
      onSubmitted: widget.onSubmitted,
      decoration: InputDecoration(
        hintText: widget.hint,
        prefixIcon: const Icon(Icons.search, size: 20),
        suffixIcon: _controller.text.isEmpty
            ? null
            : IconButton(
                icon: const Icon(Icons.close, size: 18),
                tooltip: 'Bersihkan',
                onPressed: () {
                  _controller.clear();
                  _handle('');
                },
              ),
      ),
    );
  }
}

/// Baris label–nilai untuk layar detail.
class AppInfoRow extends StatelessWidget {
  const AppInfoRow({
    super.key,
    required this.label,
    this.value,
    this.valueWidget,
  });

  final String label;
  final String? value;
  final Widget? valueWidget;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 124,
            child: Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.muted(theme.brightness),
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child:
                valueWidget ??
                Text(
                  value ?? '-',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w500,
                  ),
                ),
          ),
        ],
      ),
    );
  }
}

/// Header halaman putih bersih: judul/subjudul, leading & trailing opsional.
///
/// Pengganti header gelap agar seluruh layar terasa ringan dan konsisten.
class AppPageHeader extends StatelessWidget {
  const AppPageHeader({
    super.key,
    this.title,
    this.subtitle,
    this.leading,
    this.trailing,
    this.child,
  });

  final String? title;
  final String? subtitle;
  final Widget? leading;
  final Widget? trailing;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: AppColors.surface(theme.brightness),
        border: Border(
          bottom: BorderSide(color: AppColors.border(theme.brightness)),
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.md,
            AppSpacing.lg,
            AppSpacing.lg,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  if (leading != null) ...[
                    leading!,
                    const SizedBox(width: AppSpacing.md),
                  ],
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (title != null)
                          Text(
                            title!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w700,
                              height: 1.2,
                            ),
                          ),
                        if (subtitle != null) ...[
                          if (title != null) const SizedBox(height: 2),
                          Text(
                            subtitle!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: AppColors.muted(theme.brightness),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (trailing != null) ...[
                    const SizedBox(width: AppSpacing.sm),
                    trailing!,
                  ],
                ],
              ),
              if (child != null) ...[
                const SizedBox(height: AppSpacing.md),
                child!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}
