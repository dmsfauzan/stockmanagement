import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/offline/outbox_service.dart';
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
          const SectionHeader(title: 'Sinkronisasi Offline'),
          Card(
            child: Column(
              children: [
                DataListRow(
                  title: 'Menunggu dikirim',
                  subtitle:
                      (ref.watch(outboxCountProvider).valueOrNull ?? 0) == 0
                      ? 'Tidak ada antrean'
                      : '${ref.watch(outboxCountProvider).valueOrNull} dokumen',
                  leading: const Icon(Icons.cloud_upload_outlined),
                ),
                const Divider(height: 1),
                DataListRow(
                  title: 'Kirim sekarang',
                  leading: const Icon(Icons.sync),
                  onTap: () async {
                    final result = await ref
                        .read(outboxServiceProvider)
                        .flush();
                    ref.invalidate(outboxCountProvider);
                    if (!context.mounted) return;
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          'Terkirim ${result.sent}, gagal ${result.failed}, '
                          'sisa ${result.remaining}.',
                        ),
                      ),
                    );
                  },
                ),
                const Divider(height: 1),
                DataListRow(
                  title: 'Kosongkan antrean',
                  leading: const Icon(
                    Icons.delete_outline,
                    color: AppColors.rose,
                  ),
                  onTap: () async {
                    await ref.read(outboxServiceProvider).clear();
                    ref.invalidate(outboxCountProvider);
                  },
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
