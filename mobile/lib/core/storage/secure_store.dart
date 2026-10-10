import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Penyimpanan aman (Android Keystore) untuk token & data sesi.
class SecureStore {
  SecureStore([FlutterSecureStorage? storage])
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth_token';
  static const _userKey = 'auth_user';

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> writeToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<String?> readUser() => _storage.read(key: _userKey);

  Future<void> writeUser(String json) =>
      _storage.write(key: _userKey, value: json);

  Future<void> clear() => _storage.deleteAll();
}
