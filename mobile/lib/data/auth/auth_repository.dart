import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_exception.dart';
import '../../core/network/dio_client.dart';
import '../../core/offline/outbox_service.dart';
import '../../core/offline/response_cache.dart';
import '../../core/storage/secure_store.dart';

/// Pengguna yang sedang login (dari /me atau /login).
class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    this.roles = const [],
    this.permissions = const [],
  });

  final int id;
  final String name;
  final String email;
  final List<String> roles;
  final List<String> permissions;

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
    id: (json['id'] as num).toInt(),
    name: ('${json['name'] ?? ''}'),
    email: ('${json['email'] ?? ''}'),
    roles: (json['roles'] as List? ?? []).map((e) => '$e').toList(),
    permissions: (json['permissions'] as List? ?? []).map((e) => '$e').toList(),
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': email,
    'roles': roles,
    'permissions': permissions,
  };

  bool can(String permission) => permissions.contains(permission);
}

/// Status sesi.
class AuthState {
  const AuthState({this.user});

  final AuthUser? user;

  bool get authenticated => user != null;
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) =>
      AuthRepository(ref.watch(dioProvider), ref.watch(secureStoreProvider)),
);

final authStateProvider =
    StateNotifierProvider<AuthNotifier, AsyncValue<AuthState>>(
      (ref) => AuthNotifier(ref),
    );

class AuthNotifier extends StateNotifier<AsyncValue<AuthState>> {
  AuthNotifier(this._ref) : super(const AsyncValue.loading()) {
    restore();
  }

  final Ref _ref;

  String? _pendingTwoFactorChallenge;

  Future<String?> pendingTwoFactorChallenge() async =>
      _pendingTwoFactorChallenge;

  /// Selesaikan langkah 2FA: verifikasi kode, lalu muat profil agar state
  /// auth ikut terbarui (tanpa ini router akan mengembalikan ke /login).
  Future<void> completeTwoFactorChallenge({
    required String challengeToken,
    String? code,
    String? recoveryCode,
  }) async {
    final repo = _ref.read(authRepositoryProvider);

    await repo.challengeTwoFactor(
      challengeToken: challengeToken,
      code: code,
      recoveryCode: recoveryCode,
    );

    final user = await repo.me();

    _pendingTwoFactorChallenge = null;
    state = AsyncValue.data(AuthState(user: user));
  }

  Future<void> restore() async {
    try {
      final repo = _ref.read(authRepositoryProvider);
      final user = await repo.me();
      state = AsyncValue.data(AuthState(user: user));
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        state = const AsyncValue.data(AuthState());
      } else {
        state = AsyncValue.error(e, StackTrace.current);
      }
    } catch (e, st) {
      state = AsyncValue.error(e, st);
    }
  }

  Future<bool> login({required String email, required String password}) async {
    final repo = _ref.read(authRepositoryProvider);

    try {
      final result = await repo.login(email: email, password: password);

      if (result.twoFactorRequired) {
        _pendingTwoFactorChallenge = result.challengeToken;
        state = const AsyncValue.data(AuthState());
        return false;
      }

      state = AsyncValue.data(AuthState(user: result.user));
      return true;
    } on ApiException catch (e, st) {
      state = AsyncValue.error(e, st);
      rethrow;
    }
  }

  Future<void> logout() async {
    try {
      await _ref.read(authRepositoryProvider).logout();
    } catch (_) {
      // Abaikan: bersihkan sesi lokal walau server gagal.
    }

    _pendingTwoFactorChallenge = null;

    // Jangan biarkan antrean/cache milik user sebelumnya dipakai user lain.
    try {
      await _ref.read(outboxServiceProvider).clear();
    } catch (_) {}

    try {
      await _ref.read(responseCacheProvider).clear();
    } catch (_) {}

    _ref.invalidate(outboxCountProvider);
    state = const AsyncValue.data(AuthState());
  }
}

/// Hasil login: token (bila langsung) atau flag 2FA.
class LoginResult {
  const LoginResult({
    this.user,
    this.challengeToken,
    this.twoFactorRequired = false,
  });

  final AuthUser? user;
  final String? challengeToken;
  final bool twoFactorRequired;
}

class AuthRepository {
  AuthRepository(this._dio, this._store);

  final Dio _dio;
  final SecureStore _store;

  Future<LoginResult> login({
    required String email,
    required String password,
  }) async {
    try {
      final res = await _dio.post(
        '/login',
        data: {
          'email': email,
          'password': password,
          'device_name': 'android-flutter',
        },
      );

      final body = res.data as Map<String, dynamic>;
      final data = (body['data'] ?? {}) as Map<String, dynamic>;

      if (data['two_factor_required'] == true) {
        return LoginResult(
          twoFactorRequired: true,
          challengeToken: data['challenge_token'] as String?,
        );
      }

      final token = data['token'] as String;
      final user = AuthUser.fromJson(data['user'] as Map<String, dynamic>);

      await _store.writeToken(token);
      await _store.writeUser(jsonEncode(user.toJson()));

      return LoginResult(user: user);
    } on DioException catch (e) {
      if (e.error is ApiException) throw e.error as ApiException;
      rethrow;
    }
  }

  Future<void> challengeTwoFactor({
    required String challengeToken,
    String? code,
    String? recoveryCode,
  }) async {
    try {
      final res = await _dio.post(
        '/two-factor-challenge',
        data: {
          'challenge_token': challengeToken,
          if (code != null && code.isNotEmpty) 'code': code,
          if (recoveryCode != null && recoveryCode.isNotEmpty)
            'recovery_code': recoveryCode,
          'device_name': 'android-flutter',
        },
      );

      final body = res.data as Map<String, dynamic>;
      final data = (body['data'] ?? {}) as Map<String, dynamic>;
      final user = AuthUser.fromJson(data['user'] as Map<String, dynamic>);

      await _store.writeToken(data['token'] as String);
      await _store.writeUser(jsonEncode(user.toJson()));
    } on DioException catch (e) {
      if (e.error is ApiException) throw e.error as ApiException;
      rethrow;
    }
  }

  Future<AuthUser> me() async {
    try {
      final res = await _dio.get('/me');
      final body = res.data as Map<String, dynamic>;

      return AuthUser.fromJson((body['data'] ?? {}) as Map<String, dynamic>);
    } on DioException catch (e) {
      if (e.error is ApiException) throw e.error as ApiException;
      rethrow;
    }
  }

  Future<void> logout() async {
    try {
      await _dio.post('/logout');
    } finally {
      await _store.clear();
    }
  }
}
