import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../data/auth/auth_repository.dart';

class TwoFactorChallengeScreen extends ConsumerStatefulWidget {
  const TwoFactorChallengeScreen({super.key, required this.userId});

  final int userId;

  @override
  ConsumerState<TwoFactorChallengeScreen> createState() =>
      _TwoFactorChallengeScreenState();
}

class _TwoFactorChallengeScreenState
    extends ConsumerState<TwoFactorChallengeScreen> {
  final _code = TextEditingController();
  final _recovery = TextEditingController();
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    final repo = ref.read(authRepositoryProvider);
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await repo.challengeTwoFactor(
        userId: widget.userId,
        code: _code.text.trim().isEmpty ? null : _code.text.trim(),
        recoveryCode: _recovery.text.trim().isEmpty
            ? null
            : _recovery.text.trim(),
      );

      if (!mounted) return;
      context.go('/dashboard');
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Gagal verifikasi 2FA.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _code.dispose();
    _recovery.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Two-Factor Challenge')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const Text(
            'Masukkan kode TOTP dari aplikasi authenticator atau recovery code.',
          ),
          const SizedBox(height: 16),
          if (_error != null) ...[
            Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
            const SizedBox(height: 12),
          ],
          TextField(
            controller: _code,
            autofocus: true,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Kode TOTP',
              hintText: '123456',
            ),
          ),
          const SizedBox(height: 12),
          const Center(child: Text('— atau —')),
          const SizedBox(height: 12),
          TextField(
            controller: _recovery,
            decoration: const InputDecoration(labelText: 'Recovery code'),
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: _loading ? null : _submit,
            child: _loading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Verifikasi'),
          ),
        ],
      ),
    );
  }
}
