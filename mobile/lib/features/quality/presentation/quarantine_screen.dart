import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../data/auth/auth_repository.dart';
import '../../../data/providers.dart';
import '../../../data/quality/quality_repository.dart';

class QuarantineScreen extends ConsumerStatefulWidget {
  const QuarantineScreen({super.key});

  @override
  ConsumerState<QuarantineScreen> createState() => _QuarantineScreenState();
}

class _QuarantineScreenState extends ConsumerState<QuarantineScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();

  final List<QuarantineRow> _rows = [];
  Timer? _debounce;
  String _query = '';
  int _page = 1;
  bool _hasMore = true;
  bool _loading = true;
  bool _loadingMore = false;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _load();
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
          .read(qualityRepositoryProvider)
          .quarantine(search: _query, page: _page);

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

  Future<void> _act(QuarantineRow row, bool release) async {
    final qty = TextEditingController(text: '${row.quarantine}');
    final reason = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(release ? 'Rilis karantina' : 'Tolak karantina'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('${row.sku ?? ''} — ${row.itemName ?? ''}'),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: qty,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Jumlah'),
            ),
            const SizedBox(height: AppSpacing.sm),
            TextField(
              controller: reason,
              decoration: const InputDecoration(labelText: 'Alasan (opsional)'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(release ? 'Rilis' : 'Tolak'),
          ),
        ],
      ),
    );

    if (ok != true) return;

    setState(() => _busy = true);

    try {
      final repo = ref.read(qualityRepositoryProvider);
      final quantity = int.tryParse(qty.text.trim()) ?? row.quarantine;

      if (release) {
        await repo.release(row.id, quantity: quantity, reason: reason.text);
      } else {
        await repo.reject(row.id, quantity: quantity, reason: reason.text);
      }
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$e'), backgroundColor: AppColors.rose),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final canManage =
        ref
            .watch(authStateProvider)
            .valueOrNull
            ?.user
            ?.can('stock.quarantine') ??
        false;

    return Scaffold(
      appBar: AppBar(title: const Text('Karantina')),
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
              hint: 'Cari SKU / nama…',
              onChanged: _onSearchChanged,
              onSubmitted: (value) {
                _debounce?.cancel();
                _query = value.trim();
                _load();
              },
            ),
          ),
          Expanded(child: _body(canManage)),
        ],
      ),
    );
  }

  Widget _body(bool canManage) {
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
              title: 'Tidak ada stok karantina',
              message: 'Semua stok bersih.',
            ),
          ],
        ),
      );
    }

    final theme = Theme.of(context);

    return RefreshIndicator(
      onRefresh: _load,
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
          final quantity = Text(
            'Karantina ${row.quarantine}',
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          );

          return Card(
            margin: EdgeInsets.zero,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const AppIconBadge(
                    icon: Icons.health_and_safety_outlined,
                    color: AppColors.warning,
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${row.sku ?? ''} — ${row.itemName ?? ''}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          '${row.warehouse ?? '-'} · ${row.location ?? '-'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.muted(theme.brightness),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  if (canManage)
                    PopupMenuButton<bool>(
                      enabled: !_busy,
                      onSelected: (release) => _act(row, release),
                      itemBuilder: (context) => const [
                        PopupMenuItem(value: true, child: Text('Rilis')),
                        PopupMenuItem(value: false, child: Text('Tolak')),
                      ],
                      child: quantity,
                    )
                  else
                    quantity,
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
