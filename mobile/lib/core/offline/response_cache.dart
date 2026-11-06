import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// Cache respons GET (agar bisa dibaca saat offline).
class ResponseCache {
  Database? _db;

  /// Umur maksimum cache; data lebih tua dianggap tidak ada.
  static const Duration ttl = Duration(minutes: 30);

  Future<Database> _open() async {
    if (_db != null) return _db!;

    final path = p.join(await getDatabasesPath(), 'wsm_cache.db');

    _db = await openDatabase(
      path,
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE cache (
            key TEXT PRIMARY KEY,
            body TEXT NOT NULL,
            created_at TEXT NOT NULL
          )
        ''');
      },
    );

    return _db!;
  }

  Future<void> put(String key, String body) async {
    final db = await _open();
    await db.insert('cache', {
      'key': key,
      'body': body,
      'created_at': DateTime.now().toIso8601String(),
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> get(String key) async {
    final db = await _open();
    final rows = await db.query(
      'cache',
      where: 'key = ?',
      whereArgs: [key],
      limit: 1,
    );

    if (rows.isEmpty) return null;

    final createdAt = DateTime.tryParse(
      (rows.first['created_at'] as String?) ?? '',
    );

    if (createdAt == null || DateTime.now().difference(createdAt) > ttl) {
      await db.delete('cache', where: 'key = ?', whereArgs: [key]);

      return null;
    }

    return rows.first['body'] as String?;
  }

  /// Hapus seluruh cache (dipanggil saat logout).
  Future<void> clear() async {
    final db = await _open();
    await db.delete('cache');
  }
}

final responseCacheProvider = Provider<ResponseCache>((ref) => ResponseCache());
