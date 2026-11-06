import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/offline/outbox_service.dart';
import '../../../core/theme/theme_mode.dart';
import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../data/auth/auth_repository.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  Future<void> _showFailed(BuildContext context, WidgetRef ref) async {
    final entries = await ref.read(outboxServiceProvider).failedEntries();

    if (!context.mounted) return;

    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Antrean gagal'),
        content: SizedBox(
          width: double.maxFinite,
          child: entries.isEmpty
              ? const Text('Tidak ada antrean gagal.')
              : ListView.separated(
                  shrinkWrap: true,
                  itemCount: entries.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (_, index) => ListTile(
                    dense: true,
                    title: Text(
                      '${entries[index].method} ${entries[index].url}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    subtitle: Text(entries[index].createdAt),
                  ),
                ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Tutup'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authStateProvider).valueOrNull?.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Pengaturan')),
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.lg),
        children: [
          const AppSectionTitle(title: 'Akun'),
          const SizedBox(height: AppSpacing.sm),
          AppMenuGroup(
            children: [
              AppMenuItem(
                title: 'Nama',
                subtitle: user?.name ?? '-',
                trailing: const SizedBox.shrink(),
              ),
              AppMenuItem(
                title: 'Email',
                subtitle: user?.email ?? '-',
                trailing: const SizedBox.shrink(),
              ),
              if ((user?.roles ?? []).isNotEmpty)
                AppMenuItem(
                  title: 'Peran',
                  subtitle: user!.roles.join(', '),
                  trailing: const SizedBox.shrink(),
                ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle(title: 'Tampilan'),
          const SizedBox(height: AppSpacing.sm),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: SegmentedButton<ThemeMode>(
                segments: const [
                  ButtonSegment(
                    value: ThemeMode.system,
                    label: Text('Sistem'),
                    icon: Icon(Icons.brightness_auto),
                  ),
                  ButtonSegment(
                    value: ThemeMode.light,
                    label: Text('Terang'),
                    icon: Icon(Icons.light_mode),
                  ),
                  ButtonSegment(
                    value: ThemeMode.dark,
                    label: Text('Gelap'),
                    icon: Icon(Icons.dark_mode),
                  ),
                ],
                selected: {ref.watch(themeModeProvider)},
                onSelectionChanged: (selection) => ref
                    .read(themeModeProvider.notifier)
                    .setMode(selection.first),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle(title: 'Aplikasi'),
          const SizedBox(height: AppSpacing.sm),
          AppMenuGroup(
            children: [
              const AppMenuItem(
                title: 'Versi',
                subtitle: '1.0.0',
                icon: Icons.info_outline,
                trailing: SizedBox.shrink(),
              ),
              AppMenuItem(
                title: 'API Base URL',
                subtitle: AppConfig.apiBaseUrl,
                icon: Icons.link,
                trailing: const SizedBox.shrink(),
              ),
              const AppMenuItem(
                title: 'Bahasa',
                subtitle: 'Indonesia',
                icon: Icons.language_outlined,
                trailing: SizedBox.shrink(),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle(title: 'Sinkronisasi Offline'),
          const SizedBox(height: AppSpacing.sm),
          AppMenuGroup(
            children: [
              AppMenuItem(
                title: 'Menunggu dikirim',
                subtitle: (ref.watch(outboxCountProvider).valueOrNull ?? 0) == 0
                    ? 'Tidak ada antrean'
                    : '${ref.watch(outboxCountProvider).valueOrNull} dokumen',
                icon: Icons.cloud_upload_outlined,
                trailing: const SizedBox.shrink(),
              ),
              AppMenuItem(
                title: 'Kirim sekarang',
                icon: Icons.sync,
                onTap: () async {
                  final result = await ref.read(outboxServiceProvider).flush();
                  ref.invalidate(outboxCountProvider);
                  ref.invalidate(outboxFailedCountProvider);
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
              AppMenuItem(
                title: 'Kosongkan antrean',
                icon: Icons.delete_outline,
                iconColor: AppColors.error,
                onTap: () async {
                  await ref.read(outboxServiceProvider).clear();
                  ref.invalidate(outboxCountProvider);
                  ref.invalidate(outboxFailedCountProvider);
                },
              ),
              if ((ref.watch(outboxFailedCountProvider).valueOrNull ?? 0) >
                  0) ...[
                AppMenuItem(
                  title: 'Gagal dikirim',
                  subtitle:
                      '${ref.watch(outboxFailedCountProvider).valueOrNull} dokumen ditolak server',
                  icon: Icons.error_outline,
                  iconColor: AppColors.error,
                  onTap: () => _showFailed(context, ref),
                ),
                AppMenuItem(
                  title: 'Hapus antrean gagal',
                  icon: Icons.delete_forever_outlined,
                  iconColor: AppColors.error,
                  onTap: () async {
                    await ref.read(outboxServiceProvider).discardFailed();
                    ref.invalidate(outboxFailedCountProvider);
                  },
                ),
              ],
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Card(
            child: AppMenuItem(
              title: 'Keluar',
              icon: Icons.logout,
              iconColor: AppColors.error,
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
