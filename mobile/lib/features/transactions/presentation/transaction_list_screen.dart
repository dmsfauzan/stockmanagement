import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
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
          .read(transactionRepositoryProvider)
          .list(widget.type, search: _query, status: _status, page: _page);

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
              onSubmitted: (value) {
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
            title: 'Belum ada dokumen',
            message: 'Buat dokumen baru dengan tombol +',
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
            title: row.number,
            subtitle: '${row.date ?? '-'} · ${row.warehouse ?? '-'}',
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
