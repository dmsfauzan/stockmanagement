import 'package:flutter/material.dart';

import '../theme/tokens.dart';
import 'common.dart';

class SectionHeader extends StatelessWidget {
  const SectionHeader({super.key, required this.title, this.action});

  final String title;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Row(
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
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.title,
    this.message,
    this.icon = Icons.inbox_outlined,
  });

  final String title;
  final String? message;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AppIconBadge(
              icon: icon,
              color: AppColors.primary,
              size: 64,
              iconSize: 30,
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              title,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w600,
              ),
              textAlign: TextAlign.center,
            ),
            if (message != null) ...[
              const SizedBox(height: AppSpacing.xs),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: AppColors.muted(theme.brightness),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class ErrorCard extends StatelessWidget {
  const ErrorCard({super.key, required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const AppIconBadge(
                  icon: Icons.error_outline,
                  color: AppColors.error,
                  size: 36,
                  iconSize: 20,
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Text(
                    message,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ],
            ),
            if (onRetry != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: onRetry,
                  child: const Text('Coba lagi'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Kotak abu dengan animasi shimmer untuk status memuat.
class Skeleton extends StatefulWidget {
  const Skeleton({super.key, this.height = 16, this.width, this.radius = 8});

  final double height;
  final double? width;
  final double radius;

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final brightness = Theme.of(context).brightness;
    final base = AppColors.border(brightness);
    final highlight = AppColors.surface(brightness);

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = _controller.value;

        return Container(
          height: widget.height,
          width: widget.width,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(widget.radius),
            gradient: LinearGradient(
              begin: Alignment(-1.0 + 2 * t, 0),
              end: Alignment(1.0 + 2 * t, 0),
              colors: [base, highlight, base],
              stops: const [0.35, 0.5, 0.65],
            ),
          ),
        );
      },
    );
  }
}

/// Skeleton daftar sederhana.
class ListSkeleton extends StatelessWidget {
  const ListSkeleton({super.key, this.rows = 6});

  final int rows;

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.all(AppSpacing.lg),
      itemCount: rows,
      separatorBuilder: (context, index) =>
          const SizedBox(height: AppSpacing.md),
      itemBuilder: (context, index) => const Row(
        children: [
          Expanded(child: Skeleton(height: 14)),
          SizedBox(width: AppSpacing.lg),
          Skeleton(height: 14, width: 48),
        ],
      ),
    );
  }
}
