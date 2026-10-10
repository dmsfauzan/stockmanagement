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
    final auth = ref.watch(authStateProvider);
    final user = auth.valueOrNull?.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Lainnya')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 24,
                    backgroundColor: AppColors.indigoSoft,
                    child: Text(
                      (user?.name ?? '?').substring(0, 1).toUpperCase(),
                      style: const TextStyle(
                        color: AppColors.indigo,
                        fontWeight: FontWeight.bold,
                        fontSize: 20,
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
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        Text(user?.email ?? '-', style: AppText.caption),
                        if ((user?.roles ?? []).isNotEmpty) ...[
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            user!.roles.join(', '),
                            style: AppText.caption.copyWith(
                              color: AppColors.indigo,
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
          const SizedBox(height: AppSpacing.lg),
          Card(
            child: Column(
              children: [
                DataListRow(
                  title: 'Master Data',
                  leading: const Icon(Icons.folder_outlined),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/master'),
                ),
                const Divider(height: 1),
                DataListRow(
                  title: 'Laporan',
                  leading: const Icon(Icons.bar_chart_outlined),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/reports'),
                ),
                const Divider(height: 1),
                DataListRow(
                  title: 'Pengaturan',
                  leading: const Icon(Icons.settings_outlined),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/settings'),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Card(
            child: DataListRow(
              title: 'Keluar',
              leading: const Icon(Icons.logout, color: AppColors.rose),
              onTap: () async {
                await ref.read(authStateProvider.notifier).logout();
                if (context.mounted) context.go('/login');
              },
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Center(
            child: Text('API: ${AppConfig.apiBaseUrl}', style: AppText.caption),
          ),
        ],
      ),
    );
  }
}
