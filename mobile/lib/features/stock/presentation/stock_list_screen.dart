import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/item_visual.dart';
import '../../../core/widgets/status_badge.dart' show StatusBadge;
import '../../../data/master/master_models.dart';
import '../../../data/providers.dart';
import '../../../data/stock/stock_repository.dart';

class StockListScreen extends ConsumerStatefulWidget {
  const StockListScreen({super.key});

  @override
  ConsumerState<StockListScreen> createState() => _StockListScreenState();
}

class _StockListScreenState extends ConsumerState<StockListScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();

  final List<StockRow> _rows = [];
  List<Warehouse> _warehouses = [];
  Timer? _debounce;
  String _query = '';
  String? _status;
  String? _warehouseId;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  static const _statusOptions = {
    '': 'Semua',
    'normal': 'Normal',
    'low': 'Low',
    'out': 'Out',
    'over': 'Over',
  };

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _load();
    _loadWarehouses();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      _query = value.trim();
      _load();
    });
  }

  Future<void> _loadWarehouses() async {
    try {
      final list = await ref.read(masterRepositoryProvider).warehouses();
      if (mounted) setState(() => _warehouses = list);
    } catch (_) {
      // Abaikan; filter warehouse opsional.
    }
  }

  void _onScroll() {
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200 &&
        !_loadingMore &&
        _hasMore) {
      _load(more: true);
    }
  }

  Future<void> _load({bool more = false}) async {
    setState(() {
      if (more) {
        _loadingMore = true;
      } else {
        _loading = true;
        _error = null;
        _page = 1;
        _hasMore = true;
      }
    });

    try {
      final result = await ref
          .read(stockRepositoryProvider)
          .list(
            search: _query,
            status: _status,
            warehouseId: _warehouseId,
            page: _page,
          );

      if (!mounted) return;

      setState(() {
        if (more) {
          _rows.addAll(result.items);
        } else {
          _rows
            ..clear()
            ..addAll(result.items);
        }
        _hasMore = result.pagination.hasMore;
        if (_hasMore) _page++;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = '$e');
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _loadingMore = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Stok')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.md,
              AppSpacing.lg,
              AppSpacing.sm,
            ),
            child: AppSearchField(
              controller: _search,
              hint: 'Cari SKU / nama barang…',
              onChanged: _onSearchChanged,
              onSubmitted: (value) {
                _debounce?.cancel();
                _query = value.trim();
                _load();
              },
            ),
          ),
          SizedBox(
            height: 40,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
              itemCount: _statusOptions.length,
              separatorBuilder: (context, index) =>
                  const SizedBox(width: AppSpacing.sm),
              itemBuilder: (context, index) {
                final entry = _statusOptions.entries.elementAt(index);
                final selected = (_status ?? '') == entry.key;

                return ChoiceChip(
                  label: Text(entry.value),
                  selected: selected,
                  onSelected: (_) {
                    _status = entry.key.isEmpty ? null : entry.key;
                    _load();
                  },
                );
              },
            ),
          ),
          if (_warehouses.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.lg,
                vertical: AppSpacing.sm,
              ),
              child: DropdownButtonFormField<String>(
                initialValue: _warehouseId,
                isDense: true,
                decoration: const InputDecoration(
                  labelText: 'Warehouse',
                  isDense: true,
                ),
                items: [
                  const DropdownMenuItem(
                    value: null,
                    child: Text('Semua warehouse'),
                  ),
                  for (final w in _warehouses)
                    DropdownMenuItem(value: '${w.id}', child: Text(w.name)),
                ],
                onChanged: (value) {
                  _warehouseId = value;
                  _load();
                },
              ),
            ),
          Expanded(child: _buildBody()),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) return const ListSkeleton(rows: 8);
    if (_error != null) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [ErrorCard(message: _error!, onRetry: _load)],
        ),
      );
    }
    if (_rows.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 80),
            EmptyState(
              title: 'Tidak ada data stok',
              message: 'Coba ubah pencarian / filter.',
            ),
          ],
        ),
      );
    }

    final theme = Theme.of(context);

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: ListView.separated(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg,
          AppSpacing.sm,
          AppSpacing.lg,
          AppSpacing.xl,
        ),
        itemCount: _rows.length + (_loadingMore ? 1 : 0),
        separatorBuilder: (context, index) =>
            const SizedBox(height: AppSpacing.sm),
        itemBuilder: (context, index) {
          if (index >= _rows.length) {
            return const Padding(
              padding: EdgeInsets.all(AppSpacing.lg),
              child: Center(child: CircularProgressIndicator()),
            );
          }

          final row = _rows[index];

          return Card(
            margin: EdgeInsets.zero,
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: row.itemId > 0
                  ? () => context.push('/item/${row.itemId}')
                  : null,
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    ItemVisual(label: row.itemName, size: 52),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            row.itemName,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            row.sku,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: AppColors.muted(theme.brightness),
                              letterSpacing: 0.2,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Row(
                            children: [
                              Icon(
                                Icons.place_outlined,
                                size: 13,
                                color: AppColors.muted(theme.brightness),
                              ),
                              const SizedBox(width: 4),
                              Flexible(
                                child: Text(
                                  '${row.warehouse ?? '-'} · ${row.location ?? '-'}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: AppColors.muted(theme.brightness),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${row.onHand}',
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                            height: 1.1,
                          ),
                        ),
                        Text(
                          'avail ${row.available}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.muted(theme.brightness),
                          ),
                        ),
                        if (row.status != null) ...[
                          const SizedBox(height: 4),
                          StatusBadge(
                            status: row.status!,
                            label: row.status!.toUpperCase(),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
