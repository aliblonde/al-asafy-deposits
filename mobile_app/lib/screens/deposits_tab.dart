import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';
import 'withdraw_dialog.dart';

class DepositsTab extends StatefulWidget {
  const DepositsTab({super.key});

  @override
  State<DepositsTab> createState() => _DepositsTabState();
}

class _DepositsTabState extends State<DepositsTab> {
  bool _isLoading = true;
  List _deposits = [];
  final _numberFormat = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _fetchDeposits();
  }

  Future<void> _fetchDeposits() async {
    setState(() => _isLoading = true);
    final res = await ApiService.getDeposits();
    setState(() {
      _isLoading = false;
      if (res['success'] == true) {
        _deposits = (res['data'] as List?) ?? [];
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator(color: AppTheme.gold)),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('ودائعي الاستثمارية'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _fetchDeposits),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchDeposits,
        color: AppTheme.gold,
        child: _deposits.isEmpty
            ? const Center(
                child: Text('لا توجد ودائع مسجلة في حسابك حالياً', style: TextStyle(color: AppTheme.textMuted)),
              )
            : ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _deposits.length,
                itemBuilder: (ctx, i) {
                  final d = _deposits[i];
                  final isActive = d['status'] == 'active';
                  final cur = d['currency'] ?? '';
                  final acc = (d['accumulated_profit'] ?? 0).toDouble();

                  return Card(
                    margin: const EdgeInsets.only(bottom: 16),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Header: Deposit ID & Status
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'وديعة #${d['id']}',
                                style: const TextStyle(color: AppTheme.gold, fontSize: 16, fontWeight: FontWeight.bold),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  color: isActive ? AppTheme.success.withOpacity(0.15) : AppTheme.textMuted.withOpacity(0.2),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  d['status_ar'] ?? d['status'],
                                  style: TextStyle(
                                    color: isActive ? AppTheme.success : AppTheme.textMuted,
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const Divider(color: AppTheme.borderGold, height: 20),

                          // Type & Amount
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('نوع الوديعة', style: TextStyle(color: AppTheme.textMuted, fontSize: 11)),
                                  Text(
                                    d['type_name'] ?? '—',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                  ),
                                ],
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  const Text('رأس المال', style: TextStyle(color: AppTheme.textMuted, fontSize: 11)),
                                  Text(
                                    '${_numberFormat.format(d['amount'])} $cur',
                                    style: const TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold, fontSize: 15),
                                  ),
                                ],
                              ),
                            ],
                          ),

                          const SizedBox(height: 12),

                          // Accumulated & Paid Profit
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppTheme.bgBase,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppTheme.borderGold.withOpacity(0.5)),
                            ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('أرباح متراكمة جاهزة', style: TextStyle(color: AppTheme.textMuted, fontSize: 11)),
                                      const SizedBox(height: 2),
                                      Text(
                                        '${_numberFormat.format(acc)} $cur',
                                        style: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.bold, fontSize: 14),
                                      ),
                                    ],
                                  ),
                                ),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('أرباح تم صرفها مسبقاً', style: TextStyle(color: AppTheme.textMuted, fontSize: 11)),
                                      const SizedBox(height: 2),
                                      Text(
                                        '${_numberFormat.format(d['paid_profit'] ?? 0)} $cur',
                                        style: const TextStyle(color: AppTheme.textPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 12),

                          // Dates & Payout frequency
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'دورية الصرف: كل ${d['profit_frequency_months']} أشهر',
                                style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
                              ),
                              if (d['next_payout_date'] != null)
                                Text(
                                  'موعد الصرف القادم: ${d['next_payout_date']}',
                                  style: const TextStyle(color: AppTheme.goldLight, fontSize: 11, fontWeight: FontWeight.bold),
                                ),
                            ],
                          ),

                          // Withdraw Button if accumulated profit available
                          if (isActive && acc > 0) ...[
                            const SizedBox(height: 14),
                            OutlinedButton.icon(
                              style: OutlinedButton.styleFrom(
                                foregroundColor: AppTheme.gold,
                                side: const BorderSide(color: AppTheme.gold),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                              icon: const Icon(Icons.wallet, size: 18),
                              label: const Text('طلب سحب أرباح هذه الوديعة'),
                              onPressed: () {
                                showModalBottomSheet(
                                  context: context,
                                  isScrollControlled: true,
                                  backgroundColor: Colors.transparent,
                                  builder: (_) => WithdrawDialog(
                                    deposits: [d],
                                    onSuccess: _fetchDeposits,
                                  ),
                                );
                              },
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                },
              ),
      ),
    );
  }
}
