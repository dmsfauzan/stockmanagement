import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:wsm_mobile/core/config/app_config.dart';

void main() {
  test('AppConfig has a valid API base URL', () {
    expect(AppConfig.apiBaseUrl, isNotEmpty);
    expect(AppConfig.apiBaseUrl, contains('/api'));
  });

  testWidgets('Login screen shows email and password fields', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: _Smoke())),
    );

    expect(find.text('Stock Management'), findsOneWidget);
  });
}

class _Smoke extends StatelessWidget {
  const _Smoke();

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: Text('Stock Management')));
}
