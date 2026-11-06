import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../data/auth/auth_repository.dart';

class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final auth = ref.watch(authStateProvider);
    final user = auth.valueOrNull?.user;
    final initial = (user?.name ?? '?').trim().isEmpty
        ? '?'
        : user!.name.trim().substring(0, 1).toUpperCase();

    return Scaffold(
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          AppHeaderBlob(
            child: Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: AppColors.primary,
                  child: Text(
                    initial,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 22,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.lg),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user?.name ?? '-',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        user?.email ?? '-',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 12,
                        ),
                      ),
                      if ((user?.roles ?? []).isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.sm),
                        Wrap(
                          spacing: AppSpacing.xs,
                          children: [
                            for (final role in user!.roles)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.16),
                                  borderRadius: BorderRadius.circular(
                                    AppRadius.chip,
                                  ),
                                ),
                                child: Text(
                                  role,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AppMenuGroup(
                  children: [
                    AppMenuItem(
                      title: 'Master Data',
                      subtitle: 'Barang, kategori, supplier, gudang',
                      icon: Icons.folder_outlined,
                      onTap: () => context.push('/master'),
                    ),
                    AppMenuItem(
                      title: 'Karantina',
                      subtitle: 'Stok menunggu inspeksi',
                      icon: Icons.verified_outlined,
                      onTap: () => context.push('/quarantine'),
                    ),
                    AppMenuItem(
                      title: 'Lacak Batch/Serial',
                      subtitle: 'Telusuri asal & pergerakan',
                      icon: Icons.timeline_outlined,
                      onTap: () => context.push('/traceability'),
                    ),
                    AppMenuItem(
                      title: 'Recall',
                      subtitle: 'Tarik batch bermasalah',
                      icon: Icons.report_gmailerrorred_outlined,
                      onTap: () => context.push('/recalls'),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.lg),
                AppMenuGroup(
                  children: [
                    AppMenuItem(
                      title: 'Alat',
                      subtitle: 'Cetak label, utilitas gudang',
                      icon: Icons.handyman_outlined,
                      onTap: () => context.push('/tools'),
                    ),
                    AppMenuItem(
                      title: 'Laporan',
                      subtitle: 'Stok, valuasi, jurnal, dsb.',
                      icon: Icons.bar_chart_outlined,
                      onTap: () => context.push('/reports'),
                    ),
                    AppMenuItem(
                      title: 'Pengaturan',
                      subtitle: 'Tema, sinkronisasi offline',
                      icon: Icons.settings_outlined,
                      onTap: () => context.push('/settings'),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.lg),
                Card(
                  child: AppMenuItem(
                    title: 'Keluar',
                    icon: Icons.logout,
                    trailing: const SizedBox.shrink(),
                    onTap: () async {
                      await ref.read(authStateProvider.notifier).logout();
                      if (context.mounted) context.go('/login');
                    },
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                Center(
                  child: Text(
                    'API: ${AppConfig.apiBaseUrl}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.muted(theme.brightness),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
