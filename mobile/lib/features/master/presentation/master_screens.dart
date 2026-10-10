import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/items/item_repository.dart';
import '../../../data/master/master_models.dart';
import '../../../data/providers.dart';

class _MasterTab {
  const _MasterTab({
    required this.title,
    required this.icon,
    required this.path,
  });

  final String title;
  final IconData icon;
  final String path;
}

const _tabs = [
  _MasterTab(
    title: 'Barang',
    icon: Icons.inventory_2_outlined,
    path: '/master/items',
  ),
  _MasterTab(
    title: 'Kategori',
    icon: Icons.category_outlined,
    path: '/master/categories',
  ),
  _MasterTab(
    title: 'Satuan',
    icon: Icons.straighten_outlined,
    path: '/master/units',
  ),
  _MasterTab(
    title: 'Supplier',
    icon: Icons.factory_outlined,
    path: '/master/suppliers',
  ),
  _MasterTab(
    title: 'Customer',
    icon: Icons.store_outlined,
    path: '/master/customers',
  ),
  _MasterTab(
    title: 'Gudang',
    icon: Icons.warehouse_outlined,
    path: '/master/warehouses',
  ),
  _MasterTab(
    title: 'Lokasi',
    icon: Icons.place_outlined,
    path: '/master/locations',
  ),
  _MasterTab(
    title: 'Mata Uang',
    icon: Icons.attach_money,
    path: '/master/currencies',
  ),
];

class MasterIndexScreen extends StatelessWidget {
  const MasterIndexScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Master Data')),
      body: ListView.separated(
        padding: const EdgeInsets.all(AppSpacing.lg),
        itemCount: _tabs.length,
        separatorBuilder: (context, index) =>
            const SizedBox(height: AppSpacing.sm),
        itemBuilder: (context, index) {
          final tab = _tabs[index];

          return Card(
            child: DataListRow(
              title: tab.title,
              leading: Icon(tab.icon),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push(tab.path),
            ),
          );
        },
      ),
    );
  }
}

class ItemsScreen extends ConsumerStatefulWidget {
  const ItemsScreen({super.key});

  @override
  ConsumerState<ItemsScreen> createState() => _ItemsScreenState();
}

class _ItemsScreenState extends ConsumerState<ItemsScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();

  final List<ItemListRow> _rows = [];
  String _query = '';
  String? _status;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    _scroll.dispose();
    super.dispose();
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
          .read(itemRepositoryProvider)
          .list(search: _query, status: _status, page: _page);

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
      setState(() => _error = '$e');
    } finally {
      setState(() {
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Barang')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.md,
              AppSpacing.lg,
              AppSpacing.sm,
            ),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              decoration: const InputDecoration(
                hintText: 'Cari SKU / nama…',
                prefixIcon: Icon(Icons.search),
              ),
              onSubmitted: (value) {
                _query = value.trim();
                _load();
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
            child: FilterChipsRow(
              options: const ['active', 'inactive'],
              value: _status,
              onSelected: (value) {
                _status = value;
                _load();
              },
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Expanded(child: _body()),
        ],
      ),
    );
  }

  Widget _body() {
    if (_loading) return const ListSkeleton(rows: 8);
    if (_error != null) {
      return ListView(
        children: [ErrorCard(message: _error!, onRetry: _load)],
      );
    }
    if (_rows.isEmpty) {
      return ListView(
        children: const [
          SizedBox(height: 80),
          EmptyState(
            title: 'Tidak ada barang',
            message: 'Coba ubah pencarian / filter.',
          ),
        ],
      );
    }

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: ListView.separated(
        controller: _scroll,
        itemCount: _rows.length + (_loadingMore ? 1 : 0),
        separatorBuilder: (context, index) => const Divider(height: 1),
        itemBuilder: (context, index) {
          if (index >= _rows.length) {
            return const Padding(
              padding: EdgeInsets.all(AppSpacing.lg),
              child: Center(child: CircularProgressIndicator()),
            );
          }

          final row = _rows[index];

          return DataListRow(
            title: '${row.sku} — ${row.name}',
            subtitle: '${row.categoryName ?? '-'} · ${row.unitCode ?? '-'}',
            badge: row.ownership == 'consignment'
                ? const StatusBadge(status: 'quarantine', label: 'KONSINYASI')
                : (row.status != null
                      ? StatusBadge(
                          status: row.status!,
                          label: (row.status ?? '').toUpperCase(),
                        )
                      : null),
            onTap: () => context.push('/item/${row.id}'),
          );
        },
      ),
    );
  }
}

class SimpleListScreen<T> extends ConsumerWidget {
  const SimpleListScreen({
    super.key,
    required this.title,
    required this.future,
    required this.titleOf,
    this.subtitleOf,
  });

  final String title;
  final Future<List<T>> Function(WidgetRef ref) future;
  final String Function(T) titleOf;
  final String? Function(T)? subtitleOf;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: FutureBuilder<List<T>>(
        future: future(ref),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const ListSkeleton(rows: 8);
          }

          if (snapshot.hasError) {
            return ListView(
              children: [ErrorCard(message: '${snapshot.error}')],
            );
          }

          final items = snapshot.data ?? [];

          if (items.isEmpty) {
            return const EmptyState(title: 'Tidak ada data');
          }

          return ListView.separated(
            itemCount: items.length,
            separatorBuilder: (context, index) => const Divider(height: 1),
            itemBuilder: (context, index) {
              final item = items[index];
              final sub = subtitleOf?.call(item);

              return DataListRow(title: titleOf(item), subtitle: sub);
            },
          );
        },
      ),
    );
  }
}

class CurrenciesScreen extends ConsumerWidget {
  const CurrenciesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return SimpleListScreen<CurrencyMeta>(
      title: 'Mata Uang',
      future: (ref) async {
        final result = await ref.read(masterRepositoryProvider).currencies();

        return result.rows;
      },
      titleOf: (c) =>
          '${c.code}${c.isBase == true ? ' (Dasar)' : ''} — ${c.name}',
      subtitleOf: (c) => c.symbol,
    );
  }
}
