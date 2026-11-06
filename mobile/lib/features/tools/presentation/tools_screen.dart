import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/tokens.dart';
import '../../../core/widgets/common.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/picker.dart';
import '../../../data/master/master_models.dart';
import '../../../data/providers.dart';
import '../../transactions/presentation/picker_loaders.dart';

class ToolsScreen extends ConsumerStatefulWidget {
  const ToolsScreen({super.key});

  @override
  ConsumerState<ToolsScreen> createState() => _ToolsScreenState();
}

class _ToolsScreenState extends ConsumerState<ToolsScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          AppHeaderBlob(
            child: Row(
              children: [
                IconButton(
                  onPressed: () => Navigator.of(context).maybePop(),
                  icon: const Icon(Icons.arrow_back, color: Colors.white),
                  tooltip: 'Kembali',
                  visualDensity: VisualDensity.compact,
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 36,
                    minHeight: 36,
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                const Expanded(
                  child: Text(
                    'Alat',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: SegmentedTabs(
                    tabs: const ['Konversi Kurs', 'Saran Put-Away'],
                    selected: _tab,
                    onChanged: (i) => setState(() => _tab = i),
                  ),
                ),
                Expanded(
                  child: IndexedStack(
                    index: _tab,
                    children: const [_CurrencyTab(), _PutAwayTab()],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CurrencyTab extends ConsumerStatefulWidget {
  const _CurrencyTab();

  @override
  ConsumerState<_CurrencyTab> createState() => _CurrencyTabState();
}

class _CurrencyTabState extends ConsumerState<_CurrencyTab> {
  final _amount = TextEditingController(text: '1');
  List<CurrencyMeta> _currencies = [];
  String? _from;
  String? _to;
  Map<String, dynamic>? _result;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadCurrencies();
  }

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  Future<void> _loadCurrencies() async {
    try {
      final result = await ref.read(masterRepositoryProvider).currencies();
      if (mounted) {
        setState(() {
          _currencies = result.rows;
          _from ??= _currencies.isNotEmpty ? _currencies.first.code : null;
          _to ??= _currencies.isNotEmpty ? _currencies.first.code : null;
        });
      }
    } catch (_) {
      // Biarkan kosong bila gagal.
    }
  }

  Future<void> _convert() async {
    if (_from == null || _to == null) return;

    setState(() {
      _loading = true;
      _error = null;
      _result = null;
    });

    try {
      final result = await ref
          .read(toolsRepositoryProvider)
          .convert(
            amount: double.tryParse(_amount.text.trim()) ?? 0,
            from: _from!,
            to: _to!,
          );
      setState(() => _result = result);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              children: [
                AppTextField(
                  label: 'Jumlah',
                  controller: _amount,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                Row(
                  children: [
                    Expanded(
                      child: _currencyDropdown('Dari', _from, (v) => _from = v),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: _currencyDropdown('Ke', _to, (v) => _to = v),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton.primary(
          'Konversi',
          icon: Icons.swap_horiz,
          loading: _loading,
          onPressed: _convert,
        ),
        if (_error != null) ...[
          const SizedBox(height: AppSpacing.lg),
          ErrorCard(message: _error!),
        ],
        if (_result != null) ...[
          const SizedBox(height: AppSpacing.lg),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                children: [
                  for (final entry in _result!.entries)
                    if (entry.value is! List)
                      AppInfoRow(
                        label: entry.key.replaceAll('_', ' '),
                        value: '${entry.value}',
                      ),
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }

  Widget _currencyDropdown(
    String label,
    String? value,
    ValueChanged<String?> onChanged,
  ) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      decoration: InputDecoration(labelText: label, isDense: true),
      items: [
        for (final c in _currencies)
          DropdownMenuItem(value: c.code, child: Text(c.code)),
      ],
      onChanged: onChanged,
    );
  }
}

class _PutAwayTab extends ConsumerStatefulWidget {
  const _PutAwayTab();

  @override
  ConsumerState<_PutAwayTab> createState() => _PutAwayTabState();
}

class _PutAwayTabState extends ConsumerState<_PutAwayTab> {
  final _quantity = TextEditingController(text: '1');
  String _warehouseId = '';
  String _warehouseLabel = '';
  String _itemId = '';
  String _itemLabel = '';
  dynamic _suggestion;
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _pickWarehouse() async {
    final option = await pickOption(
      context,
      title: 'Pilih Gudang',
      loader: (q, p) => warehouseOptions(ref, q),
    );
    if (option != null) {
      setState(() {
        _warehouseId = '${option.id}';
        _warehouseLabel = option.label;
      });
    }
  }

  Future<void> _pickItem() async {
    final option = await pickOption(
      context,
      title: 'Pilih Barang',
      loader: (q, p) => itemOptions(ref, q, p),
    );
    if (option != null) {
      setState(() {
        _itemId = '${option.id}';
        _itemLabel = option.label;
      });
    }
  }

  Future<void> _run() async {
    if (_warehouseId.isEmpty || _itemId.isEmpty) {
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _suggestion = null;
    });

    try {
      final result = await ref
          .read(toolsRepositoryProvider)
          .putAwaySuggestion(
            warehouseId: int.tryParse(_warehouseId) ?? 0,
            itemId: int.tryParse(_itemId) ?? 0,
            quantity: int.tryParse(_quantity.text.trim()) ?? 1,
          );
      setState(() => _suggestion = result);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              children: [
                PickerTile(
                  label: 'Gudang',
                  value: _warehouseLabel.isEmpty ? null : _warehouseLabel,
                  required: true,
                  onTap: _pickWarehouse,
                ),
                const SizedBox(height: AppSpacing.md),
                PickerTile(
                  label: 'Barang',
                  value: _itemLabel.isEmpty ? null : _itemLabel,
                  required: true,
                  onTap: _pickItem,
                ),
                const SizedBox(height: AppSpacing.md),
                AppTextField(
                  label: 'Jumlah',
                  controller: _quantity,
                  keyboardType: TextInputType.number,
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        AppButton.primary(
          'Cari Lokasi',
          icon: Icons.place_outlined,
          loading: _loading,
          onPressed: _run,
        ),
        if (_error != null) ...[
          const SizedBox(height: AppSpacing.lg),
          ErrorCard(message: _error!),
        ],
        if (_suggestion != null) ...[
          const SizedBox(height: AppSpacing.lg),
          const SectionHeader(title: 'Saran'),
          _suggestionCard(_suggestion),
        ],
      ],
    );
  }

  Widget _suggestionCard(dynamic data) {
    if (data is List) {
      if (data.isEmpty) {
        return const EmptyState(title: 'Tidak ada saran');
      }
      return Column(children: [for (final item in data) _kvCard(item)]);
    }

    return _kvCard(data);
  }

  Widget _kvCard(dynamic item) {
    if (item is! Map) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Text('$item'),
        ),
      );
    }

    return Card(
      margin: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          children: [
            for (final entry in item.entries)
              if (entry.value is! List)
                AppInfoRow(
                  label: '${entry.key}'.replaceAll('_', ' '),
                  value: '${entry.value}',
                ),
          ],
        ),
      ),
    );
  }
}
