import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/utils/format.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/master/master_models.dart';
import '../../../data/providers.dart';
import '../../../data/transactions/transaction_models.dart';

class TransactionListScreen extends ConsumerStatefulWidget {
  const TransactionListScreen({super.key, required this.type});

  final TxnType type;

  @override
  ConsumerState<TransactionListScreen> createState() =>
      _TransactionListScreenState();
}

class _TransactionListScreenState extends ConsumerState<TransactionListScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();

  final List<TxnListRow> _rows = [];
  List<Warehouse> _warehouses = [];
  List<Supplier> _suppliers = [];
  List<Customer> _customers = [];

  Timer? _debounce;
  String _query = '';
  String? _status;
  String? _warehouseId;
  String? _supplierId;
  String? _customerId;
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
    _loadFilters();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _loadFilters() async {
    try {
      final master = ref.read(masterRepositoryProvider);
      final warehouses = await master.warehouses();

      var suppliers = <Supplier>[];
      var customers = <Customer>[];

      if (widget.type == TxnType.purchaseOrder) {
        suppliers = await master.suppliers();
      }
      if (widget.type == TxnType.salesOrder) {
        customers = await master.customers();
      }

      if (mounted) {
        setState(() {
          _warehouses = warehouses;
          _suppliers = suppliers;
          _customers = customers;
        });
      }
    } catch (_) {
      // Filter opsional; abaikan bila gagal.
    }
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      _query = value.trim();
      _load();
    });
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
          .read(transactionRepositoryProvider)
          .list(
            widget.type,
            search: _query,
            status: _status,
            warehouseId: _warehouseId,
            supplierId: _supplierId,
            customerId: _customerId,
            page: _page,
          );

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
      appBar: AppBar(title: Text(widget.type.label)),
      floatingActionButton: FloatingActionButton(
        onPressed: () => context.push('/tx/${widget.type.key}/create'),
        child: const Icon(Icons.add),
      ),
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
                hintText: 'Cari nomor…',
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: _onSearchChanged,
              onSubmitted: (value) {
                _debounce?.cancel();
                _query = value.trim();
                _load();
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
            child: FilterChipsRow(
              options: TxnWorkflow.of(widget.type).steps,
              value: _status,
              onSelected: (value) {
                _status = value;
                _load();
              },
            ),
          ),
          _filterBar(),
          const SizedBox(height: AppSpacing.sm),
          Expanded(child: _body()),
        ],
      ),
    );
  }

  Widget _filterBar() {
    final fields = <Widget>[];
    final isReturn =
        widget.type == TxnType.customerReturn ||
        widget.type == TxnType.supplierReturn;

    if (_warehouses.isNotEmpty && !isReturn) {
      fields.add(
        _dropdown(
          label: 'Gudang',
          value: _warehouseId,
          options: [for (final w in _warehouses) MapEntry('${w.id}', w.name)],
          onChanged: (value) {
            _warehouseId = value;
            _load();
          },
        ),
      );
    }

    if (_suppliers.isNotEmpty) {
      fields.add(
        _dropdown(
          label: 'Supplier',
          value: _supplierId,
          options: [for (final s in _suppliers) MapEntry('${s.id}', s.name)],
          onChanged: (value) {
            _supplierId = value;
            _load();
          },
        ),
      );
    }

    if (_customers.isNotEmpty) {
      fields.add(
        _dropdown(
          label: 'Customer',
          value: _customerId,
          options: [for (final c in _customers) MapEntry('${c.id}', c.name)],
          onChanged: (value) {
            _customerId = value;
            _load();
          },
        ),
      );
    }

    if (fields.isEmpty) return const SizedBox.shrink();

    return SizedBox(
      height: 72,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.sm,
        ),
        itemCount: fields.length,
        separatorBuilder: (context, index) =>
            const SizedBox(width: AppSpacing.md),
        itemBuilder: (context, index) =>
            SizedBox(width: 180, child: fields[index]),
      ),
    );
  }

  Widget _dropdown({
    required String label,
    required String? value,
    required List<MapEntry<String, String>> options,
    required ValueChanged<String?> onChanged,
  }) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      isDense: true,
      decoration: InputDecoration(labelText: label, isDense: true),
      items: [
        const DropdownMenuItem(value: null, child: Text('Semua')),
        for (final o in options)
          DropdownMenuItem(value: o.key, child: Text(o.value)),
      ],
      onChanged: onChanged,
    );
  }

  Widget _body() {
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
              title: 'Belum ada dokumen',
              message: 'Buat dokumen baru dengan tombol +',
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _load(),
      child: ListView.separated(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
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
            title: row.number,
            subtitle: '${formatDate(row.date)} · ${row.warehouse ?? '-'}',
            badge: StatusBadge(
              status: row.status,
              label: row.status.toUpperCase(),
            ),
            onTap: () => context
                .push('/tx/${widget.type.key}/${row.id}')
                .then((_) => _load()),
          );
        },
      ),
    );
  }
}
