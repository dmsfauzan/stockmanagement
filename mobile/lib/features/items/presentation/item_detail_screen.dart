import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/items/item_repository.dart';
import '../../../data/providers.dart';
import '../../../data/stock/stock_repository.dart';

class ItemDetailScreen extends ConsumerStatefulWidget {
  const ItemDetailScreen({super.key, required this.itemId});

  final int itemId;

  @override
  ConsumerState<ItemDetailScreen> createState() => _ItemDetailScreenState();
}

class _ItemDetailScreenState extends ConsumerState<ItemDetailScreen> {
  int _tab = 0;
  ItemDetail? _item;
  List<StockRow> _balances = [];
  List<StockMovement> _movements = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final item = await ref.read(itemRepositoryProvider).show(widget.itemId);
      final balances =
          (await ref
                  .read(stockRepositoryProvider)
                  .list(search: item.sku, perPage: 100))
              .items
              .where((r) => r.itemId == widget.itemId)
              .toList();
      final movements = await ref
          .read(stockRepositoryProvider)
          .movements(itemId: widget.itemId);

      setState(() {
        _item = item;
        _balances = balances;
        _movements = movements;
      });
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_item?.name ?? 'Detail Barang')),
      body: _loading
          ? const ListSkeleton(rows: 6)
          : _error != null
          ? ListView(
              children: [ErrorCard(message: _error!, onRetry: _load)],
            )
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: SegmentedTabs(
                    tabs: const ['Info', 'Inventory', 'Movement', 'Summary'],
                    selected: _tab,
                    onChanged: (i) => setState(() => _tab = i),
                  ),
                ),
                Expanded(
                  child: IndexedStack(
                    index: _tab,
                    children: [_info(), _inventory(), _movement(), _summary()],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _info() {
    final item = _item!;

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        0,
        AppSpacing.lg,
        AppSpacing.xl,
      ),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.name,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  '${item.sku}${item.barcode != null ? ' · ${item.barcode}' : ''}',
                  style: AppText.caption,
                ),
                const SizedBox(height: AppSpacing.md),
                _row('Kategori', item.category?.name ?? '-'),
                _row('Satuan', item.unit?.code ?? '-'),
                _row('Brand', item.brand ?? '-'),
                _row(
                  'Kepemilikan',
                  item.ownership == 'consignment'
                      ? 'Konsinyasi — ${item.consignorName ?? '-'}'
                      : 'Milik Sendiri',
                ),
                _row(
                  'Min / Max',
                  '${item.minimumStock} / ${item.maximumStock}',
                ),
                _row('Status', item.status ?? '-'),
                _row('Deskripsi', item.description ?? '-'),
              ],
            ),
          ),
        ),
        if (item.conversions.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.lg),
          const SectionHeader(title: 'Konversi Satuan'),
          Card(
            child: Column(
              children: [
                for (final c in item.conversions)
                  DataListRow(
                    title: c.unitName ?? c.unitCode,
                    trailing: Text('× ${c.factor}'),
                  ),
              ],
            ),
          ),
        ],
        if (item.bom.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.lg),
          const SectionHeader(title: 'Bill of Materials'),
          Card(
            child: Column(
              children: [
                for (final b in item.bom)
                  DataListRow(title: b.sku, trailing: Text('× ${b.quantity}')),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _inventory() {
    if (_balances.isEmpty) {
      return const EmptyState(
        title: 'Belum ada stok',
        message: 'Tidak ada saldo stok untuk barang ini.',
      );
    }

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        for (final b in _balances)
          Card(
            margin: const EdgeInsets.only(bottom: AppSpacing.md),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${b.warehouse ?? '-'} · ${b.location ?? '-'}',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Row(
                    children: [
                      _metric('On Hand', b.onHand),
                      _metric('Reserved', b.reserved),
                      _metric('Karantina', b.quarantine),
                      _metric('Available', b.available),
                    ],
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _movement() {
    if (_movements.isEmpty) {
      return const EmptyState(
        title: 'Belum ada movement',
        message: 'Belum ada pergerakan stok.',
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(AppSpacing.lg),
      itemCount: _movements.length,
      separatorBuilder: (context, index) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final m = _movements[index];

        return DataListRow(
          title: '${m.warehouse ?? '-'} · ${m.location ?? '-'}',
          subtitle: '${m.createdAt ?? ''} · ${m.reference ?? ''}',
          badge: StatusBadge(status: m.type, label: m.type.toUpperCase()),
          trailing: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (m.quantityIn > 0)
                Text(
                  '+${m.quantityIn}',
                  style: const TextStyle(
                    color: AppColors.emerald,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              if (m.quantityOut > 0)
                Text(
                  '-${m.quantityOut}',
                  style: const TextStyle(
                    color: AppColors.rose,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              Text(
                'bal ${m.balanceAfter}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _summary() {
    final onHand = _balances.fold<int>(0, (sum, b) => sum + b.onHand);
    final reserved = _balances.fold<int>(0, (sum, b) => sum + b.reserved);
    final quarantine = _balances.fold<int>(0, (sum, b) => sum + b.quarantine);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        Row(
          children: [
            Expanded(child: _summaryCard('Total On Hand', onHand)),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: _summaryCard(
                'Available',
                onHand - reserved - quarantine,
                hint: 'Reserved $reserved',
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        Row(
          children: [
            Expanded(child: _summaryCard('Reserved', reserved)),
            const SizedBox(width: AppSpacing.md),
            Expanded(child: _summaryCard('Karantina', quarantine)),
          ],
        ),
      ],
    );
  }

  Widget _summaryCard(String label, int value, {String? hint}) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label.toUpperCase(),
              style: AppText.overline.copyWith(
                color: AppColors.muted(Theme.of(context).brightness),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text('$value', style: Theme.of(context).textTheme.headlineSmall),
            if (hint != null) Text(hint, style: AppText.caption),
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 110, child: Text(label, style: AppText.caption)),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }

  Widget _metric(String label, int value) {
    return Expanded(
      child: Column(
        children: [
          Text(
            '$value',
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
          ),
          Text(label, style: AppText.caption, textAlign: TextAlign.center),
        ],
      ),
    );
  }
}
