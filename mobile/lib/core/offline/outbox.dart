import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import 'outbox_entry.dart';

/// Penyimpanan antrean offline (SQLite).
class Outbox {
  Database? _db;

  Future<Database> _open() async {
    if (_db != null) return _db!;

    final path = p.join(await getDatabasesPath(), 'wsm_offline.db');

    _db = await openDatabase(
      path,
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE outbox (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            method TEXT NOT NULL,
            url TEXT NOT NULL,
            data TEXT,
            idempotency_key TEXT,
            created_at TEXT NOT NULL
          )
        ''');
      },
    );

    return _db!;
  }

  Future<int> add(OutboxEntry entry) async {
    final db = await _open();

    return db.insert('outbox', entry.toMap()..remove('id'));
  }

  Future<List<OutboxEntry>> all() async {
    final db = await _open();
    final rows = await db.query('outbox', orderBy: 'id ASC');

    return rows.map(OutboxEntry.fromMap).toList();
  }

  Future<int> count() async {
    final db = await _open();
    final result = await db.rawQuery('SELECT COUNT(*) AS c FROM outbox');

    return (result.first['c'] as int?) ?? 0;
  }

  Future<void> remove(int id) async {
    final db = await _open();
    await db.delete('outbox', where: 'id = ?', whereArgs: [id]);
  }

  Future<void> clear() async {
    final db = await _open();
    await db.delete('outbox');
  }
}
