import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/utils/format.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../data/auth/auth_repository.dart';
import '../../../data/providers.dart';
import '../../../data/requisitions/requisition_models.dart';

class RequisitionListScreen extends ConsumerStatefulWidget {
  const RequisitionListScreen({super.key});

  @override
  ConsumerState<RequisitionListScreen> createState() =>
      _RequisitionListScreenState();
}

class _RequisitionListScreenState extends ConsumerState<RequisitionListScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();

  final List<RequisitionRow> _rows = [];
  Timer? _debounce;
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
          .read(requisitionRepositoryProvider)
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
    final canCreate =
        ref
            .watch(authStateProvider)
            .valueOrNull
            ?.user
            ?.can('requisition.create') ??
        false;

    return Scaffold(
      appBar: AppBar(title: const Text('Purchase Requisition')),
      floatingActionButton: canCreate
          ? FloatingActionButton(
              onPressed: () =>
                  context.push('/requisitions/create').then((_) => _load()),
              child: const Icon(Icons.add),
            )
          : null,
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
              hint: 'Cari nomor…',
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
              options: RequisitionWorkflow.steps,
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
              title: 'Belum ada requisition',
              message: 'Buat pengajuan baru dengan tombol +',
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
            child: InkWell(
              onTap: () =>
                  context.push('/requisitions/${row.id}').then((_) => _load()),
              borderRadius: BorderRadius.circular(AppRadius.card),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const AppIconBadge(icon: Icons.assignment_outlined),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            row.number,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          Text(
                            '${formatDate(row.date)} · ${row.warehouse ?? '-'}'
                            '${row.poNumber != null ? ' · PO ${row.poNumber}' : ''}',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: AppColors.muted(theme.brightness),
                            ),
                          ),
                          const SizedBox(height: AppSpacing.sm),
                          StatusBadge(
                            status: row.status,
                            label: row.status.toUpperCase(),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Icon(
                      Icons.chevron_right,
                      color: AppColors.muted(theme.brightness),
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
