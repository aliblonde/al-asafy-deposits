import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';

class TransactionsTab extends StatefulWidget {
  const TransactionsTab({super.key});

  @override
  State<TransactionsTab> createState() => _TransactionsTabState();
}

class _TransactionsTabState extends State<TransactionsTab> {
  bool _isLoading = true;
  List _transactions = [];
  String _selectedCurrency = '';
  final _numberFormat = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _fetchTransactions();
  }

  Future<void> _fetchTransactions() async {
    setState(() => _isLoading = true);
    final res = await ApiService.getTransactions(currency: _selectedCurrency);
    setState(() {
      _isLoading = false;
      if (res['success'] == true) {
        _transactions = (res['data']?['transactions'] as List?) ?? [];
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('كشف الحساب المالي'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchTransactions),
        ],
      ),
      body: Column(
        children: [
          // Currency Filter chips
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                _buildFilterChip('الكل', ''),
                const SizedBox(width: 8),
                _buildFilterChip('دولار (\$)', 'USD'),
                const SizedBox(width: 8),
                _buildFilterChip('دينار (IQD)', 'IQD'),
              ],
            ),
          ),

          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.gold))
                : RefreshIndicator(
                    onRefresh: _fetchTransactions,
                    color: AppTheme.gold,
                    child: _transactions.isEmpty
                        ? const Center(
                            child: Text('لا توجد حركات مالية مسجلة', style: TextStyle(color: AppTheme.textMuted)),
                          )
                        : ListView.builder(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            itemCount: _transactions.length,
                            itemBuilder: (ctx, i) {
                              final tx = _transactions[i];
                              final isCredit = tx['direction'] == 'credit';

                              return Card(
                                margin: const EdgeInsets.only(bottom: 12),
                                child: Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      // Direction Icon
                                      Container(
                                        padding: const EdgeInsets.all(10),
                                        decoration: BoxDecoration(
                                          shape: BoxShape.circle,
                                          color: isCredit ? AppTheme.success.withOpacity(0.15) : AppTheme.danger.withOpacity(0.15),
                                        ),
                                        child: Icon(
                                          isCredit ? Icons.arrow_downward : Icons.arrow_upward,
                                          color: isCredit ? AppTheme.success : AppTheme.danger,
                                          size: 20,
                                        ),
                                      ),
                                      const SizedBox(width: 14),

                                      // Details
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              tx['type_ar'] ?? tx['type'] ?? 'حركة مالية',
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              'إيصال: ${tx['receipt_no'] ?? '—'}',
                                              style: const TextStyle(color: AppTheme.textMuted, fontSize: 11, fontFamily: 'monospace'),
                                            ),
                                            if (tx['note'] != null && tx['note'].toString().isNotEmpty) ...[
                                              const SizedBox(height: 2),
                                              Text(
                                                tx['note'],
                                                style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
                                              ),
                                            ],
                                            const SizedBox(height: 4),
                                            Text(
                                              tx['date'] ?? '',
                                              style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
                                            ),
                                          ],
                                        ),
                                      ),

                                      // Amount
                                      Text(
                                        '${isCredit ? '+' : '-'}${_numberFormat.format(tx['amount'])} ${tx['currency']}',
                                        style: TextStyle(
                                          color: isCredit ? AppTheme.success : AppTheme.gold,
                                          fontWeight: FontWeight.bold,
                                          fontSize: 14,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String label, String value) {
    final isSelected = _selectedCurrency == value;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      selectedColor: AppTheme.gold,
      backgroundColor: AppTheme.bgSurface,
      labelStyle: TextStyle(
        color: isSelected ? Colors.black : AppTheme.textPrimary,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        fontSize: 12,
      ),
      onSelected: (_) {
        setState(() => _selectedCurrency = value);
        _fetchTransactions();
      },
    );
  }
}
