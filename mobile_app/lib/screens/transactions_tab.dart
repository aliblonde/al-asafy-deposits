import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';
import '../services/pdf_service.dart';

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
    if (!mounted) return;
    setState(() {
      _isLoading = false;
      if (res['success'] == true) {
        _transactions = (res['data']?['transactions'] as List?) ?? [];
      }
    });
  }

  Future<void> _exportPdf() async {
    if (_transactions.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا توجد حركات لتصديرها'), backgroundColor: AppTheme.warning),
      );
      return;
    }
    final investor = await ApiService.getInvestorData() ?? {};
    await PdfService.generateAndShareStatement(
      investor: investor,
      transactions: _transactions,
      currencyFilter: _selectedCurrency,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('كشف الحساب المالي'),
        actions: [
          IconButton(
            icon: const Icon(Icons.picture_as_pdf),
            tooltip: 'تصدير كشف حساب PDF',
            onPressed: _exportPdf,
          ),
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
                : _transactions.isEmpty
                    ? const Center(
                        child: Text('لا توجد حركات مالية مطابقة للفلتر المحدد', style: TextStyle(color: AppTheme.textMuted)),
                      )
                    : RefreshIndicator(
                        onRefresh: _fetchTransactions,
                        color: AppTheme.gold,
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          itemCount: _transactions.length,
                          itemBuilder: (ctx, i) {
                            final tx = _transactions[i];
                            final isCredit = tx['direction'] == 'credit';
                            final amount = double.tryParse(tx['amount'].toString()) ?? 0;

                            return Card(
                              margin: const EdgeInsets.only(bottom: 12),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Row(
                                  children: [
                                    CircleAvatar(
                                      radius: 20,
                                      backgroundColor: isCredit
                                          ? AppTheme.success.withValues(alpha: 0.15)
                                          : AppTheme.danger.withValues(alpha: 0.15),
                                      child: Icon(
                                        isCredit ? Icons.arrow_downward : Icons.arrow_upward,
                                        color: isCredit ? AppTheme.success : AppTheme.danger,
                                        size: 20,
                                      ),
                                    ),
                                    const SizedBox(width: 14),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            tx['note'] ?? tx['type'] ?? 'معاملة مالية',
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            'رقم الوصل: ${tx['receipt_no'] ?? '—'}',
                                            style: const TextStyle(color: AppTheme.goldLight, fontSize: 12),
                                          ),
                                          Text(
                                            tx['date'] ?? '',
                                            style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Column(
                                      crossAxisAlignment: CrossAxisAlignment.end,
                                      children: [
                                        Text(
                                          (isCredit ? '+ ' : '- ') + _numberFormat.format(amount),
                                          style: TextStyle(
                                            color: isCredit ? AppTheme.success : AppTheme.danger,
                                            fontWeight: FontWeight.bold,
                                            fontSize: 16,
                                          ),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          tx['currency'] ?? '',
                                          style: const TextStyle(color: AppTheme.textMuted, fontSize: 12, fontWeight: FontWeight.bold),
                                        ),
                                      ],
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
      backgroundColor: AppTheme.bgCard,
      labelStyle: TextStyle(
        color: isSelected ? Colors.black : AppTheme.textPrimary,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        fontSize: 12,
      ),
      onSelected: (_) {
        setState(() {
          _selectedCurrency = value;
          _fetchTransactions();
        });
      },
    );
  }
}
