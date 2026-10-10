import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/tokens.dart';
import '../../../../core/widgets/common.dart';
import '../../../../data/transactions/transaction_models.dart';

class TransactionsHubScreen extends StatelessWidget {
  const TransactionsHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    const modules = [
      (
        TxnType.receipt,
        Icons.move_to_inbox_outlined,
        'Barang Masuk',
        'Penerimaan supplier',
      ),
      (
        TxnType.issue,
        Icons.outbox_outlined,
        'Barang Keluar',
        'Pengeluaran ke customer',
      ),
      (TxnType.adjustment, Icons.tune_outlined, 'Adjustment', 'Koreksi stok'),
      (
        TxnType.opname,
        Icons.fact_check_outlined,
        'Opname',
        'Stock opname & counting',
      ),
      (
        TxnType.transfer,
        Icons.swap_horiz_outlined,
        'Transfer',
        'Antar gudang/lokasi',
      ),
    ];

    return Scaffold(
      appBar: AppBar(title: const Text('Transaksi')),
      body: ListView.separated(
        padding: const EdgeInsets.all(AppSpacing.lg),
        itemCount: modules.length,
        separatorBuilder: (context, index) =>
            const SizedBox(height: AppSpacing.sm),
        itemBuilder: (context, index) {
          final m = modules[index];

          return Card(
            child: DataListRow(
              title: m.$3,
              subtitle: m.$4,
              leading: Icon(m.$2),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/tx/${m.$1.key}'),
            ),
          );
        },
      ),
    );
  }
}
