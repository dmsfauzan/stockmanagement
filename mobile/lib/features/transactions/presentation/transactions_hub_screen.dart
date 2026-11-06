import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/tokens.dart';
import '../../../../core/widgets/common.dart';
import '../../../../data/auth/auth_repository.dart';

class _HubEntry {
  const _HubEntry({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.path,
    required this.permission,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String path;
  final String permission;
}

const _entries = [
  _HubEntry(
    icon: Icons.move_to_inbox_outlined,
    title: 'Barang Masuk',
    subtitle: 'Penerimaan supplier',
    path: '/tx/receipts',
    permission: 'goods_receipt.view',
  ),
  _HubEntry(
    icon: Icons.outbox_outlined,
    title: 'Barang Keluar',
    subtitle: 'Pengeluaran ke customer',
    path: '/tx/issues',
    permission: 'goods_issue.view',
  ),
  _HubEntry(
    icon: Icons.tune_outlined,
    title: 'Adjustment',
    subtitle: 'Koreksi stok',
    path: '/tx/adjustments',
    permission: 'stock.adjustment',
  ),
  _HubEntry(
    icon: Icons.fact_check_outlined,
    title: 'Opname',
    subtitle: 'Stock opname & counting',
    path: '/tx/opnames',
    permission: 'stock_opname.view',
  ),
  _HubEntry(
    icon: Icons.swap_horiz_outlined,
    title: 'Transfer',
    subtitle: 'Antar gudang/lokasi',
    path: '/tx/transfers',
    permission: 'transfer.view',
  ),
  _HubEntry(
    icon: Icons.shopping_cart_checkout_outlined,
    title: 'Purchase Order',
    subtitle: 'Pesanan pembelian ke supplier',
    path: '/tx/purchase-orders',
    permission: 'purchase_order.view',
  ),
  _HubEntry(
    icon: Icons.point_of_sale_outlined,
    title: 'Sales Order',
    subtitle: 'Pesanan penjualan ke customer',
    path: '/tx/sales-orders',
    permission: 'sales_order.view',
  ),
  _HubEntry(
    icon: Icons.assignment_return_outlined,
    title: 'Retur Penjualan',
    subtitle: 'Barang kembali dari customer',
    path: '/tx/customer-returns',
    permission: 'customer_return.view',
  ),
  _HubEntry(
    icon: Icons.keyboard_return_outlined,
    title: 'Retur Pembelian',
    subtitle: 'Barang dikembalikan ke supplier',
    path: '/tx/supplier-returns',
    permission: 'supplier_return.view',
  ),
  _HubEntry(
    icon: Icons.request_quote_outlined,
    title: 'Purchase Requisition',
    subtitle: 'Pengajuan pembelian (PR)',
    path: '/requisitions',
    permission: 'requisition.view',
  ),
  _HubEntry(
    icon: Icons.build_outlined,
    title: 'Perakitan',
    subtitle: 'Assembly / disassembly',
    path: '/assembly-orders',
    permission: 'assembly.view',
  ),
  _HubEntry(
    icon: Icons.checklist_outlined,
    title: 'Picking',
    subtitle: 'Daftar pengambilan barang',
    path: '/pick-lists',
    permission: 'picking.view',
  ),
];

class TransactionsHubScreen extends ConsumerWidget {
  const TransactionsHubScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authStateProvider).valueOrNull?.user;
    final entries = _entries
        .where((e) => user?.can(e.permission) ?? false)
        .toList();

    return Scaffold(
      body: Column(
        children: [
          const AppPageHeader(
            title: 'Transaksi',
            subtitle: 'Kelola dokumen operasional gudang',
          ),
          Expanded(
            child: entries.isEmpty
                ? const Center(
                    child: Padding(
                      padding: EdgeInsets.all(AppSpacing.xl),
                      child: Text(
                        'Tidak ada modul transaksi yang dapat Anda akses.',
                        textAlign: TextAlign.center,
                      ),
                    ),
                  )
                : GridView.builder(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          mainAxisSpacing: AppSpacing.md,
                          crossAxisSpacing: AppSpacing.md,
                          mainAxisExtent: 176,
                        ),
                    itemCount: entries.length,
                    itemBuilder: (context, index) {
                      final entry = entries[index];

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
