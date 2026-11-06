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
          AppPageHeader(
            leading: Container(
              height: 48,
              width: 48,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [AppColors.primary, AppColors.primaryHover],
                ),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Text(
                initial,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 18,
                ),
              ),
            ),
            title: user?.name ?? '-',
            subtitle: user?.email ?? '-',
            child: (user?.roles.isNotEmpty ?? false)
                ? Wrap(
                    spacing: AppSpacing.xs,
                    runSpacing: AppSpacing.xs,
                    children: [
                      for (final role in user!.roles)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.surfaceVariant(theme.brightness),
                            borderRadius: BorderRadius.circular(AppRadius.chip),
                            border: Border.all(
                              color: AppColors.border(theme.brightness),
                            ),
                          ),
                          child: Text(
                            role,
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: AppColors.muted(theme.brightness),
                            ),
                          ),
                        ),
                    ],
                  )
                : null,
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (user?.can('items.view') ?? false)
                  AppMenuGroup(
                    children: [
                      AppMenuItem(
                        title: 'Master Data',
                        subtitle: 'Barang, kategori, supplier, gudang',
                        icon: Icons.folder_outlined,
                        onTap: () => context.push('/master'),
                      ),
                      if (user?.can('stock.quarantine') ?? false)
                        AppMenuItem(
                          title: 'Karantina',
                          subtitle: 'Stok menunggu inspeksi',
                          icon: Icons.verified_outlined,
                          onTap: () => context.push('/quarantine'),
                        ),
                      if (user?.can('stock.movement') ?? false)
                        AppMenuItem(
                          title: 'Lacak Batch/Serial',
                          subtitle: 'Telusuri asal & pergerakan',
                          icon: Icons.timeline_outlined,
                          onTap: () => context.push('/traceability'),
                        ),
                      if (user?.can('stock.movement') ?? false)
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
                    if (user?.can('reports.view') ?? false)
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
                    iconColor: AppColors.error,
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
