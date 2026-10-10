import 'package:flutter/material.dart';

import '../theme/tokens.dart';

class PickerOption {
  const PickerOption({required this.id, required this.label, this.subtitle});

  final int id;
  final String label;
  final String? subtitle;
}

/// Bottom sheet pemilih dengan pencarian. Mengembalikan opsi terpilih.
Future<PickerOption?> pickOption(
  BuildContext context, {
  required String title,
  required Future<List<PickerOption>> Function(String query) loader,
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
  final Future<List<PickerOption>> Function(String query) loader;

  @override
  State<_PickerSheet> createState() => _PickerSheetState();
}

class _PickerSheetState extends State<_PickerSheet> {
  final _search = TextEditingController();
  List<PickerOption> _options = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load('');
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load(String query) async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final options = await widget.loader(query);
      if (mounted) setState(() => _options = options);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _loading = false);
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
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: TextField(
                controller: _search,
                autofocus: true,
                textInputAction: TextInputAction.search,
                decoration: const InputDecoration(
                  hintText: 'Cari…',
                  prefixIcon: Icon(Icons.search),
                ),
                onSubmitted: _load,
              ),
            ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                  ? Center(child: Text(_error!))
                  : ListView.separated(
                      itemCount: _options.length,
                      separatorBuilder: (context, index) =>
                          const Divider(height: 1),
                      itemBuilder: (context, index) {
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
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.button),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: required ? '$label *' : label,
          suffixIcon: const Icon(Icons.unfold_more, size: 18),
        ),
        child: Text(
          value ?? '—',
          style: value == null
              ? TextStyle(color: AppColors.muted(Theme.of(context).brightness))
              : null,
        ),
      ),
    );
  }
}
