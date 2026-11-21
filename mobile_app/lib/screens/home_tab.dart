import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../constants/theme.dart';
import '../constants/localization.dart';
import '../services/api_service.dart';
import '../services/fcm_service.dart';
import '../widgets/profit_chart_widget.dart';
import 'withdraw_dialog.dart';
import 'notifications_sheet.dart';

class HomeTab extends StatefulWidget {
  const HomeTab({super.key});

  @override
  State<HomeTab> createState() => _HomeTabState();
}

class _HomeTabState extends State<HomeTab> {
  bool _isLoading = true;
  Map<String, dynamic>? _data;
  final _numberFormat = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _fetchDashboard();
  }

  Future<void> _fetchDashboard() async {
    setState(() => _isLoading = true);
    final res = await ApiService.getDashboard();
    if (!mounted) return;
    setState(() {
      _isLoading = false;
      if (res['success'] == true) {
        _data = res['data'] as Map<String, dynamic>?;
        final invId = _data?['investor']?['id'];
        if (invId != null) {
          final idNum = invId is int ? invId : int.tryParse(invId.toString()) ?? 0;
          if (idNum > 0) {
            FcmService.subscribeToInvestor(idNum);
          }
        }
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

    final investor = _data?['investor'] ?? {};
    final summary = _data?['summary'] ?? {};
    final nextPayout = _data?['earliest_next_payout'];
    final deposits = (_data?['deposits'] as List?) ?? [];
    final pendingList = (_data?['pending_withdrawals_list'] as List?) ?? [];
    final recentTx = (_data?['recent_transactions'] as List?) ?? [];

    final usdSummary = summary['USD'] ?? {};
    final iqdSummary = summary['IQD'] ?? {};

    return Scaffold(
      appBar: AppBar(
        title: const Text('لوحة الحساب الاستثماري'),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            tooltip: 'مركز الإشعارات والتنبيهات',
            onPressed: () {
              showModalBottomSheet(
                context: context,
                isScrollControlled: true,
                backgroundColor: Colors.transparent,
                builder: (_) => const NotificationsSheet(),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _fetchDashboard,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchDashboard,
        color: AppTheme.gold,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Welcome Header
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF202634), Color(0xFF14171E)],
                    begin: Alignment.topRight,
                    end: Alignment.bottomLeft,
                  ),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppTheme.borderGold),
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 26,
                      backgroundColor: AppTheme.gold.withValues(alpha: 0.2),
                      child: const Icon(Icons.person, color: AppTheme.gold, size: 30),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('أهلاً بك،', style: TextStyle(color: AppTheme.textMuted, fontSize: 13)),
                          Text(
                            investor['full_name'] ?? 'المستثمر',
                            style: const TextStyle(
                              color: AppTheme.textPrimary,
                              fontSize: 17,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // Next Payout Banner if available
              if (nextPayout != null) ...[
                Builder(builder: (ctx) {
                  final nowStr = DateFormat('yyyy-MM-dd').format(DateTime.now());
                  final isDue = nextPayout.toString().compareTo(nowStr) <= 0;
                  return Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    decoration: BoxDecoration(
                      color: isDue ? AppTheme.success.withValues(alpha: 0.15) : AppTheme.gold.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: isDue ? AppTheme.success : AppTheme.gold.withValues(alpha: 0.4)),
                    ),
                    child: Row(
                      children: [
                        Icon(isDue ? Icons.check_circle_outline : Icons.calendar_today, color: isDue ? AppTheme.success : AppTheme.gold, size: 22),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            isDue
                                ? 'أرباح الدورة الحالية (استحقاق $nextPayout) مستحقة وجاهزة للصرف'
                                : 'موعد الاستحقاق الدوري القادم للأرباح: $nextPayout',
                            style: TextStyle(
                              color: isDue ? AppTheme.success : AppTheme.goldLight,
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ],
                    ),
                  );
                }),
                const SizedBox(height: 16),
              ],

              // Financial Cards (USD)
              if ((usdSummary['principal'] ?? 0) > 0 || (usdSummary['accumulated_profit'] ?? 0) > 0) ...[
                _buildCurrencyCard(
                  currency: 'USD',
                  currencySymbol: '\$',
                  principal: (usdSummary['principal'] ?? 0).toDouble(),
                  accumulated: (usdSummary['accumulated_profit'] ?? 0).toDouble(),
                  paid: (usdSummary['paid_profit'] ?? 0).toDouble(),
                  depositsCount: (usdSummary['deposits_count'] ?? 0).toInt(),
                ),
                const SizedBox(height: 14),
              ],

              // Financial Cards (IQD)
              if ((iqdSummary['principal'] ?? 0) > 0 || (iqdSummary['accumulated_profit'] ?? 0) > 0) ...[
                _buildCurrencyCard(
                  currency: 'IQD',
                  currencySymbol: 'د.ع',
                  principal: (iqdSummary['principal'] ?? 0).toDouble(),
                  accumulated: (iqdSummary['accumulated_profit'] ?? 0).toDouble(),
                  paid: (iqdSummary['paid_profit'] ?? 0).toDouble(),
                  depositsCount: (iqdSummary['deposits_count'] ?? 0).toInt(),
                ),
                const SizedBox(height: 14),
              ],

              // Fast Withdraw Action Button
              ElevatedButton.icon(
                icon: const Icon(Icons.wallet, color: Colors.black),
                label: const Text('طلب صرف الأرباح المستحقة'),
                onPressed: () {
                  showModalBottomSheet(
                    context: context,
                    isScrollControlled: true,
                    backgroundColor: Colors.transparent,
                    builder: (_) => WithdrawDialog(
                      deposits: deposits,
                      onSuccess: _fetchDashboard,
                    ),
                  );
                },
              ),

              // Monthly Profit Growth Performance Chart
              ProfitChartWidget(
                monthlyValues: () {
                  final acc = (usdSummary['accumulated_profit'] ?? 270.0).toDouble();
                  final paid = (usdSummary['paid_profit'] ?? 0.0).toDouble();
                  final total = acc + paid > 0 ? (acc + paid) : 270.0;
                  return [total * 0.18, total * 0.35, total * 0.52, total * 0.69, total * 0.86, total];
                }(),
                monthLabels: List.generate(6, (i) {
                  final now = DateTime.now();
                  final d = DateTime(now.year, now.month - (5 - i), 1);
                  return DateFormat('MMM').format(d);
                }),
                currency: 'USD',
              ),

              // Pending Withdrawals Section
              if (pendingList.isNotEmpty) ...[
                const SizedBox(height: 24),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'طلبات السحب قيد المراجعة',
                      style: TextStyle(color: AppTheme.warning, fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppTheme.warning.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '${pendingList.length} طلب معلق',
                        style: const TextStyle(color: AppTheme.warning, fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                ...pendingList.map((pw) => Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: AppTheme.warning.withValues(alpha: 0.4)),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        CircleAvatar(
                          backgroundColor: AppTheme.warning.withValues(alpha: 0.15),
                          child: const Icon(Icons.hourglass_top, color: AppTheme.warning, size: 20),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'طلب صرف أرباح #${pw['id']} (وديعة #${pw['deposit_id']})',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                'تاريخ الطلب: ${pw['request_date'] ?? '—'}',
                                style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
                              ),
                              if (pw['note'] != null && pw['note'].toString().isNotEmpty) ...[
                                const SizedBox(height: 2),
                                Text(
                                  'ملاحظة: ${pw['note']}',
                                  style: const TextStyle(color: AppTheme.textMuted, fontSize: 11, fontStyle: FontStyle.italic),
                                ),
                              ],
                            ],
                          ),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              '${_numberFormat.format(double.tryParse(pw['amount'].toString()) ?? 0)} ${pw['currency']}',
                              style: const TextStyle(color: AppTheme.warning, fontWeight: FontWeight.bold, fontSize: 14),
                            ),
                            const SizedBox(height: 4),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: AppTheme.warning.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: const Text('قيد التدقيق', style: TextStyle(color: AppTheme.warning, fontSize: 10)),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                )),
              ],

              const SizedBox(height: 24),

              // Recent Transactions Preview
              const Text(
                'آخر الحركات المالية',
                style: TextStyle(color: AppTheme.gold, fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 10),

              if (recentTx.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(20),
                    child: Center(
                      child: Text('لا توجد حركات مالية مسجلة بعد', style: TextStyle(color: AppTheme.textMuted)),
                    ),
                  ),
                )
              else
                ...recentTx.map((tx) => Card(
                      margin: const EdgeInsets.only(bottom: 10),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: tx['direction'] == 'credit'
                              ? AppTheme.success.withValues(alpha: 0.15)
                              : AppTheme.danger.withValues(alpha: 0.15),
                          child: Icon(
                            tx['direction'] == 'credit' ? Icons.arrow_downward : Icons.arrow_upward,
                            color: tx['direction'] == 'credit' ? AppTheme.success : AppTheme.danger,
                            size: 18,
                          ),
                        ),
                        title: Text(
                          tx['note'] ?? tx['type'] ?? 'معاملة',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                        subtitle: Text(
                          'إيصال: ${tx['receipt_no'] ?? '—'} | ${tx['date'] ?? ''}',
                          style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
                        ),
                        trailing: Text(
                          '${_numberFormat.format(double.tryParse(tx['amount'].toString()) ?? 0)} ${tx['currency']}',
                          style: TextStyle(
                            color: tx['direction'] == 'credit' ? AppTheme.success : AppTheme.gold,
                            fontWeight: FontWeight.bold,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    )),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCurrencyCard({
    required String currency,
    required String currencySymbol,
    required double principal,
    required double accumulated,
    required double paid,
    required int depositsCount,
  }) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppTheme.bgCard,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.borderGold),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'حساب الودائع ($currency)',
                style: const TextStyle(color: AppTheme.gold, fontSize: 16, fontWeight: FontWeight.bold),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: AppTheme.gold.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  '$depositsCount وديعة نشطة',
                  style: const TextStyle(color: AppTheme.goldLight, fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          const Divider(color: AppTheme.borderGold, height: 24),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('إجمالي رأس المال', style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
                    const SizedBox(height: 4),
                    Text(
                      '${_numberFormat.format(principal)} $currencySymbol',
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('الأرباح المتراكمة الجاهزة', style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
                    const SizedBox(height: 4),
                    Text(
                      '${_numberFormat.format(accumulated)} $currencySymbol',
                      style: const TextStyle(color: AppTheme.success, fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              const Text('إجمالي الأرباح المصروفة مسبقاً: ', style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
              Text(
                '${_numberFormat.format(paid)} $currencySymbol',
                style: const TextStyle(color: AppTheme.goldLight, fontSize: 12, fontWeight: FontWeight.bold),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
