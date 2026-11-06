import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/auth/auth_repository.dart';
import '../../../data/providers.dart';
import '../../../data/recall/recall_repository.dart';

class RecallListScreen extends ConsumerStatefulWidget {
  const RecallListScreen({super.key});

  @override
  ConsumerState<RecallListScreen> createState() => _RecallListScreenState();
}

class _RecallListScreenState extends ConsumerState<RecallListScreen> {
  final _scroll = ScrollController();
  final List<RecallRow> _rows = [];
  String? _status;
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
          .read(recallRepositoryProvider)
          .list(status: _status, page: _page);

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

  Future<void> _lift(RecallRow row) async {
    setState(() => _busy = true);

    try {
      await ref.read(recallRepositoryProvider).lift(row.id);
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
        ref.watch(authStateProvider).valueOrNull?.user?.can('stock.movement') ??
        false;

    return Scaffold(
      appBar: AppBar(title: const Text('Recall')),
      floatingActionButton: canManage
          ? FloatingActionButton(
              onPressed: () =>
                  context.push('/recalls/create').then((_) => _load()),
              child: const Icon(Icons.add),
            )
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
            child: FilterChipsRow(
              options: const ['active', 'lifted'],
              value: _status,
              onSelected: (value) {
                _status = value;
                _load();
              },
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
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
              title: 'Belum ada recall',
              message: 'Tandai batch/serial ditarik dengan tombol +.',
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

          return Card(
            margin: EdgeInsets.zero,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const AppIconBadge(
                    icon: Icons.report_gmailerrorred_outlined,
                    color: AppColors.error,
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${row.type ?? ''} · ${row.value}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        if (row.reason != null) ...[
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            row.reason!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: AppColors.muted(theme.brightness),
                            ),
                          ),
                        ],
                        const SizedBox(height: AppSpacing.sm),
                        StatusBadge(
                          status: row.status,
                          label: row.status.toUpperCase(),
                        ),
                      ],
                    ),
                  ),
                  if (row.status == 'active' && canManage) ...[
                    const SizedBox(width: AppSpacing.sm),
                    IconButton(
                      onPressed: _busy ? null : () => _lift(row),
                      icon: const Icon(Icons.undo),
                      tooltip: 'Cabut recall',
                    ),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
