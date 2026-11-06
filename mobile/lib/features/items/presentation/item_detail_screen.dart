import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/item_visual.dart';
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

      if (!mounted) return;

      setState(() {
        _item = item;
        _balances = balances;
        _movements = movements;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _loading = false);
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
          : _loaded(_item!),
    );
  }

  Widget _loaded(ItemDetail item) {
    final theme = Theme.of(context);
    final category = item.category?.name ?? item.name;

    return Column(
      children: [
        Container(
          width: double.infinity,
          color: AppColors.surface(theme.brightness),
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.md,
            AppSpacing.lg,
            AppSpacing.lg,
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              ItemVisual(label: category, size: 72, radius: 18),
              const SizedBox(width: AppSpacing.lg),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                        height: 1.2,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.sku}${item.barcode != null && item.barcode!.isNotEmpty ? ' · ${item.barcode}' : ''}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.muted(theme.brightness),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Wrap(
                      spacing: AppSpacing.sm,
                      runSpacing: AppSpacing.xs,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        if (item.category?.name != null)
                          _pill(item.category!.name),
                        if (item.unit?.code != null) _pill(item.unit!.code),
                        if (item.status != null)
                          StatusBadge(
                            status: item.status!,
                            label: item.status!.toUpperCase(),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.md,
            AppSpacing.lg,
            AppSpacing.sm,
          ),
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
    );
  }

  Widget _pill(String text) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant(theme.brightness),
        borderRadius: BorderRadius.circular(AppRadius.chip),
        border: Border.all(color: AppColors.border(theme.brightness)),
      ),
      child: Text(
        text,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: theme.textTheme.labelSmall?.copyWith(
          color: AppColors.muted(theme.brightness),
        ),
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
                AppInfoRow(
                  label: 'Kategori',
                  value: item.category?.name ?? '-',
                ),
                AppInfoRow(label: 'Satuan', value: item.unit?.code ?? '-'),
                AppInfoRow(label: 'Brand', value: item.brand ?? '-'),
                AppInfoRow(
                  label: 'Kepemilikan',
                  value: item.ownership == 'consignment'
                      ? 'Konsinyasi — ${item.consignorName ?? '-'}'
                      : 'Milik Sendiri',
                ),
                AppInfoRow(
                  label: 'Min / Max',
                  value: '${item.minimumStock} / ${item.maximumStock}',
                ),
                AppInfoRow(label: 'Deskripsi', value: item.description ?? '-'),
              ],
            ),
          ),
        ),
        if (item.conversions.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.lg),
          const AppSectionTitle(title: 'Konversi Satuan'),
          const SizedBox(height: AppSpacing.md),
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
          const AppSectionTitle(title: 'Bill of Materials'),
          const SizedBox(height: AppSpacing.md),
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
                      Expanded(child: _metric('On Hand', b.onHand)),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(child: _metric('Available', b.available)),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Row(
                    children: [
                      Expanded(child: _metric('Reserved', b.reserved)),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(child: _metric('Karantina', b.quarantine)),
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

  Widget _metric(String label, int value) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(
        vertical: AppSpacing.sm,
        horizontal: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant(theme.brightness),
        borderRadius: BorderRadius.circular(AppRadius.button),
      ),
      child: Column(
        children: [
          Text(
            '$value',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: AppText.caption.copyWith(
              color: AppColors.muted(theme.brightness),
            ),
          ),
        ],
      ),
    );
  }
}
