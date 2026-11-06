import 'package:flutter/material.dart';

import '../theme/tokens.dart';
import 'barcode_scanner.dart';
import 'common.dart';

class PickerOption {
  const PickerOption({required this.id, required this.label, this.subtitle});

  final int id;
  final String label;
  final String? subtitle;
}

/// Satu halaman opsi picker.
class PickerPage {
  const PickerPage({required this.options, this.hasMore = false});

  const PickerPage.single(this.options) : hasMore = false;

  final List<PickerOption> options;
  final bool hasMore;
}

typedef PickerLoader = Future<PickerPage> Function(String query, int page);

/// Bottom sheet pemilih dengan pencarian + paging. Mengembalikan opsi terpilih.
Future<PickerOption?> pickOption(
  BuildContext context, {
  required String title,
  required PickerLoader loader,
}) {
  return showModalBottomSheet<PickerOption>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => _PickerSheet(title: title, loader: loader),
  );
}

class _PickerSheet extends StatefulWidget {
  const _PickerSheet({required this.title, required this.loader});

  final String title;
  final PickerLoader loader;

  @override
  State<_PickerSheet> createState() => _PickerSheetState();
}

class _PickerSheetState extends State<_PickerSheet> {
  final _search = TextEditingController();
  final _scroll = ScrollController();

  List<PickerOption> _options = [];
  int _page = 1;
  bool _hasMore = false;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
    _load('');
  }

  @override
  void dispose() {
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 120 &&
        !_loadingMore &&
        _hasMore) {
      _loadMore();
    }
  }

  Future<void> _load(String query) async {
    setState(() {
      _loading = true;
      _error = null;
      _page = 1;
      _hasMore = false;
    });

    try {
      final page = await widget.loader(query, 1);
      if (mounted) {
        setState(() {
          _options = page.options;
          _hasMore = page.hasMore;
          if (page.hasMore) _page = 2;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadMore() async {
    setState(() => _loadingMore = true);

    try {
      final page = await widget.loader(_search.text, _page);
      if (mounted) {
        setState(() {
          _options = [..._options, ...page.options];
          _hasMore = page.hasMore;
          if (page.hasMore) _page++;
        });
      }
    } catch (_) {
      // Abaikan; pengguna dapat menggulir ulang.
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.7,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
              child: Text(
                widget.title,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Row(
                children: [
                  Expanded(
                    child: AppSearchField(
                      controller: _search,
                      hint: 'Cari…',
                      onSubmitted: _load,
                      onChanged: (_) {},
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  IconButton.filledTonal(
                    icon: const Icon(Icons.qr_code_scanner),
                    tooltip: 'Pindai barcode',
                    onPressed: () async {
                      final code = await showBarcodeScanner(context);
                      if (!context.mounted) return;
                      if (code != null && code.isNotEmpty) {
                        _search.text = code;
                        _load(code);
                      }
                    },
                  ),
                ],
              ),
            ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                  ? Center(child: Text(_error!))
                  : ListView.separated(
                      controller: _scroll,
                      itemCount: _options.length + (_loadingMore ? 1 : 0),
                      separatorBuilder: (context, index) =>
                          const Divider(height: 1),
                      itemBuilder: (context, index) {
                        if (index >= _options.length) {
                          return const Padding(
                            padding: EdgeInsets.all(AppSpacing.lg),
                            child: Center(child: CircularProgressIndicator()),
                          );
                        }

                        final option = _options[index];

                        return ListTile(
                          title: Text(option.label),
                          subtitle: option.subtitle != null
                              ? Text(option.subtitle!)
                              : null,
                          onTap: () => Navigator.pop(context, option),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Baris form yang bisa diketuk untuk memilih.
class PickerTile extends StatelessWidget {
  const PickerTile({
    super.key,
    required this.label,
    required this.value,
    required this.onTap,
    this.required = false,
  });

  final String label;
  final String? value;
  final VoidCallback onTap;
  final bool required;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = AppColors.muted(theme.brightness);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          required ? '$label *' : label,
          style: theme.textTheme.bodySmall?.copyWith(
            fontWeight: FontWeight.w600,
            color: AppColors.text(theme.brightness),
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.input),
          child: InputDecorator(
            decoration: const InputDecoration(
              suffixIcon: Icon(Icons.unfold_more, size: 18),
            ),
            child: Text(
              value ?? '—',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: value == null ? muted : null,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
