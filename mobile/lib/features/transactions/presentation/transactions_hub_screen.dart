import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/tokens.dart';
import '../../../../core/widgets/common.dart';

class _HubEntry {
  const _HubEntry({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.path,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String path;
}

const _entries = [
  _HubEntry(
    icon: Icons.move_to_inbox_outlined,
    title: 'Barang Masuk',
    subtitle: 'Penerimaan supplier',
    path: '/tx/receipts',
  ),
  _HubEntry(
    icon: Icons.outbox_outlined,
    title: 'Barang Keluar',
    subtitle: 'Pengeluaran ke customer',
    path: '/tx/issues',
  ),
  _HubEntry(
    icon: Icons.tune_outlined,
    title: 'Adjustment',
    subtitle: 'Koreksi stok',
    path: '/tx/adjustments',
  ),
  _HubEntry(
    icon: Icons.fact_check_outlined,
    title: 'Opname',
    subtitle: 'Stock opname & counting',
    path: '/tx/opnames',
  ),
  _HubEntry(
    icon: Icons.swap_horiz_outlined,
    title: 'Transfer',
    subtitle: 'Antar gudang/lokasi',
    path: '/tx/transfers',
  ),
  _HubEntry(
    icon: Icons.shopping_cart_checkout_outlined,
    title: 'Purchase Order',
    subtitle: 'Pesanan pembelian ke supplier',
    path: '/tx/purchase-orders',
  ),
  _HubEntry(
    icon: Icons.point_of_sale_outlined,
    title: 'Sales Order',
    subtitle: 'Pesanan penjualan ke customer',
    path: '/tx/sales-orders',
  ),
  _HubEntry(
    icon: Icons.assignment_return_outlined,
    title: 'Retur Penjualan',
    subtitle: 'Barang kembali dari customer',
    path: '/tx/customer-returns',
  ),
  _HubEntry(
    icon: Icons.keyboard_return_outlined,
    title: 'Retur Pembelian',
    subtitle: 'Barang dikembalikan ke supplier',
    path: '/tx/supplier-returns',
  ),
  _HubEntry(
    icon: Icons.request_quote_outlined,
    title: 'Purchase Requisition',
    subtitle: 'Pengajuan pembelian (PR)',
    path: '/requisitions',
  ),
  _HubEntry(
    icon: Icons.build_outlined,
    title: 'Perakitan',
    subtitle: 'Assembly / disassembly',
    path: '/assembly-orders',
  ),
  _HubEntry(
    icon: Icons.checklist_outlined,
    title: 'Picking',
    subtitle: 'Daftar pengambilan barang',
    path: '/pick-lists',
  ),
];

class TransactionsHubScreen extends StatelessWidget {
  const TransactionsHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          const AppPageHeader(
            title: 'Transaksi',
            subtitle: 'Kelola dokumen operasional gudang',
          ),
          Expanded(
            child: GridView.builder(
              padding: const EdgeInsets.all(AppSpacing.lg),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: AppSpacing.md,
                crossAxisSpacing: AppSpacing.md,
                mainAxisExtent: 176,
              ),
              itemCount: _entries.length,
              itemBuilder: (context, index) {
                final entry = _entries[index];

                return AppStatTile(
                  title: entry.title,
                  subtitle: entry.subtitle,
                  icon: entry.icon,
                  onTap: () => context.push(entry.path),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
