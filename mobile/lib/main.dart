import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/auth/session_listener.dart';
import 'core/config/app_config.dart';
import 'core/offline/offline_sync.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/theme_mode.dart';

void main() {
  runApp(const ProviderScope(child: WsmApp()));
}

class WsmApp extends ConsumerWidget {
  const WsmApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return OfflineSync(
      child: MaterialApp.router(
        title: AppConfig.appName,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        themeMode: ref.watch(themeModeProvider),
        routerConfig: ref.watch(routerProvider),
        builder: (context, child) =>
            SessionListener(child: child ?? const SizedBox.shrink()),
        debugShowCheckedModeBanner: false,
      ),
    );
  }
}
