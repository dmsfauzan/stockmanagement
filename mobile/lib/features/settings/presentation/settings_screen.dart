import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../data/auth/auth_repository.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authStateProvider).valueOrNull?.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Pengaturan')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          const SectionHeader(title: 'Akun'),
          Card(
            child: Column(
              children: [
                DataListRow(title: 'Nama', subtitle: user?.name ?? '-'),
                const Divider(height: 1),
                DataListRow(title: 'Email', subtitle: user?.email ?? '-'),
                if ((user?.roles ?? []).isNotEmpty) ...[
                  const Divider(height: 1),
                  DataListRow(title: 'Peran', subtitle: user!.roles.join(', ')),
                ],
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          const SectionHeader(title: 'Aplikasi'),
          Card(
            child: Column(
              children: [
                const DataListRow(
                  title: 'Versi',
                  subtitle: '1.0.0',
                  leading: Icon(Icons.info_outline),
                ),
                const Divider(height: 1),
                DataListRow(
                  title: 'API Base URL',
                  subtitle: AppConfig.apiBaseUrl,
                  leading: const Icon(Icons.link),
                ),
                const Divider(height: 1),
                const DataListRow(
                  title: 'Bahasa',
                  subtitle: 'Indonesia',
                  leading: Icon(Icons.language_outlined),
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
        ],
      ),
    );
  }
}
