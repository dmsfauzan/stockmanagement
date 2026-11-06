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
      version: 3,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE outbox (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            method TEXT NOT NULL,
            url TEXT NOT NULL,
            data TEXT,
            idempotency_key TEXT,
            created_at TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            next_attempt_at TEXT,
            failed INTEGER NOT NULL DEFAULT 0
          )
        ''');
      },
      onUpgrade: (db, oldVersion, newVersion) async {
        if (oldVersion < 2) {
          await db.execute(
            'ALTER TABLE outbox ADD COLUMN attempts INTEGER NOT NULL DEFAULT 0',
          );
          await db.execute(
            'ALTER TABLE outbox ADD COLUMN next_attempt_at TEXT',
          );
        }
        if (oldVersion < 3) {
          await db.execute(
            'ALTER TABLE outbox ADD COLUMN failed INTEGER NOT NULL DEFAULT 0',
          );
        }
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

  /// Entri yang siap dicoba (belum menunggu backoff & belum gagal permanen).
  Future<List<OutboxEntry>> due() async {
    final db = await _open();
    final now = DateTime.now().toIso8601String();
    final rows = await db.query(
      'outbox',
      where: '(next_attempt_at IS NULL OR next_attempt_at <= ?) AND failed = 0',
      whereArgs: [now],
      orderBy: 'id ASC',
    );

    return rows.map(OutboxEntry.fromMap).toList();
  }

  Future<int> count() async {
    final db = await _open();
    final result = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM outbox WHERE failed = 0',
    );

    return (result.first['c'] as int?) ?? 0;
  }

  Future<int> failedCount() async {
    final db = await _open();
    final result = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM outbox WHERE failed = 1',
    );

    return (result.first['c'] as int?) ?? 0;
  }

  Future<void> markAttempt(int id, int attempts, String? nextAttemptAt) async {
    final db = await _open();
    await db.update(
      'outbox',
      {'attempts': attempts, 'next_attempt_at': nextAttemptAt},
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  Future<void> remove(int id) async {
    final db = await _open();
    await db.delete('outbox', where: 'id = ?', whereArgs: [id]);
  }

  /// Tandai entri gagal permanen (4xx) agar tetap terlihat, tidak dicoba lagi.
  Future<void> markFailed(int id) async {
    final db = await _open();
    await db.update('outbox', {'failed': 1}, where: 'id = ?', whereArgs: [id]);
  }

  /// Hapus semua entri yang gagal permanen.
  Future<void> discardFailed() async {
    final db = await _open();
    await db.delete('outbox', where: 'failed = 1');
  }

  Future<void> clear() async {
    final db = await _open();
    await db.delete('outbox');
  }
}
