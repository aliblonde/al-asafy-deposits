import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';

class WithdrawDialog extends StatefulWidget {
  final List deposits;
  final VoidCallback onSuccess;

  const WithdrawDialog({
    super.key,
    required this.deposits,
    required this.onSuccess,
  });

  @override
  State<WithdrawDialog> createState() => _WithdrawDialogState();
}

class _WithdrawDialogState extends State<WithdrawDialog> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _noteController = TextEditingController();
  final _numberFormat = NumberFormat('#,##0.00');

  int? _selectedDepositId;
  Map<String, dynamic>? _selectedDeposit;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    if (widget.deposits.isNotEmpty) {
      // Pick first deposit with positive available balance, or fallback to first deposit
      Map<String, dynamic>? candidate;
      for (var d in widget.deposits) {
        final avail = _calcAvailable(d);
        if (avail > 0) {
          candidate = Map<String, dynamic>.from(d);
          break;
        }
      }
      candidate ??= Map<String, dynamic>.from(widget.deposits.first);
      _selectedDepositId = candidate['id'];
      _selectedDeposit = candidate;
    }
  }

  double _calcAvailable(Map<String, dynamic>? d) {
    if (d == null) return 0.0;
    if (d.containsKey('available_to_withdraw')) {
      return (d['available_to_withdraw'] ?? 0).toDouble();
    }
    final acc = (d['accumulated_profit'] ?? 0).toDouble();
    final pending = (d['pending_withdrawal'] ?? 0).toDouble();
    return (acc - pending).clamp(0.0, double.infinity);
  }

  void _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedDepositId == null || _selectedDeposit == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يرجى اختيار الوديعة أولاً'), backgroundColor: AppTheme.danger),
      );
      return;
    }

    final amount = double.tryParse(_amountController.text) ?? 0;
    final available = _calcAvailable(_selectedDeposit);

    if (amount <= 0 || amount > available) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('المبلغ المطلوب غير متاح للسحب (${_numberFormat.format(available)} ${_selectedDeposit?['currency'] ?? ''})'),
          backgroundColor: AppTheme.danger,
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    final res = await ApiService.submitWithdrawRequest(
      depositId: _selectedDepositId!,
      amount: amount,
      note: _noteController.text.trim(),
    );

    setState(() => _isSubmitting = false);

    if (!mounted) return;

    if (res['success'] == true) {
      Navigator.of(context).pop();
      widget.onSuccess();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'تم إرسال طلب السحب بنجاح وهو قيد الاعتماد'),
          backgroundColor: AppTheme.success,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'فشل إرسال الطلب'), backgroundColor: AppTheme.danger),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final cur = _selectedDeposit?['currency'] ?? '';
    final acc = (_selectedDeposit?['accumulated_profit'] ?? 0).toDouble();
    final pending = (_selectedDeposit?['pending_withdrawal'] ?? 0).toDouble();
    final available = _calcAvailable(_selectedDeposit);

    return Container(
      padding: EdgeInsets.only(
        top: 24,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      decoration: const BoxDecoration(
        color: AppTheme.bgCard,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        border: Border(top: BorderSide(color: AppTheme.gold, width: 2)),
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'طلب صرف أرباح وديعة',
                    style: TextStyle(color: AppTheme.gold, fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: AppTheme.textMuted),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              const Divider(color: AppTheme.borderGold),
              const SizedBox(height: 12),

              // Deposit Selector
              const Text('اختر الوديعة المستهدفة', style: TextStyle(color: AppTheme.textMuted, fontSize: 13)),
              const SizedBox(height: 6),
              DropdownButtonFormField<int>(
                value: _selectedDepositId,
                dropdownColor: AppTheme.bgCard,
                decoration: const InputDecoration(prefixIcon: Icon(Icons.account_balance, color: AppTheme.gold)),
                items: widget.deposits.map<DropdownMenuItem<int>>((d) {
                  final dCur = d['currency'] ?? '';
                  final dAvail = _calcAvailable(Map<String, dynamic>.from(d));
                  return DropdownMenuItem<int>(
                    value: d['id'],
                    child: Text(
                      'وديعة #${d['id']} (${_numberFormat.format(d['amount'])} $dCur) — متاح: ${_numberFormat.format(dAvail)}',
                      style: const TextStyle(fontSize: 12),
                    ),
                  );
                }).toList(),
                onChanged: (val) {
                  setState(() {
                    _selectedDepositId = val;
                    final found = widget.deposits.firstWhere((e) => e['id'] == val, orElse: () => null);
                    _selectedDeposit = found != null ? Map<String, dynamic>.from(found) : null;
                  });
                },
              ),

              const SizedBox(height: 12),

              // Breakdown Container
              if (_selectedDeposit != null) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.bgBase,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: available > 0 ? AppTheme.borderGold : AppTheme.danger.withValues(alpha: 0.5),
                    ),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('إجمالي الأرباح المتراكمة:', style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
                          Text('${_numberFormat.format(acc)} $cur', style: const TextStyle(color: AppTheme.textPrimary, fontSize: 12)),
                        ],
                      ),
                      if (pending > 0) ...[
                        const SizedBox(height: 6),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('طلبات سحب قيد المراجعة:', style: TextStyle(color: AppTheme.warning, fontSize: 12)),
                            Text('- ${_numberFormat.format(pending)} $cur', style: const TextStyle(color: AppTheme.warning, fontSize: 12, fontWeight: FontWeight.bold)),
                          ],
                        ),
                      ],
                      const Divider(color: AppTheme.borderGold, height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('الصافي المتاح للسحب الآن:', style: TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold, fontSize: 13)),
                          Text(
                            '${_numberFormat.format(available)} $cur',
                            style: TextStyle(
                              color: available > 0 ? AppTheme.success : AppTheme.danger,
                              fontWeight: FontWeight.bold,
                              fontSize: 14,
                            ),
                          ),
                        ],
                      ),
                      if (available <= 0 && pending > 0) ...[
                        const SizedBox(height: 10),
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: AppTheme.warning.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Row(
                            children: [
                              Icon(Icons.info_outline, color: AppTheme.warning, size: 16),
                              SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'كامل أرباح هذه الوديعة محجوزة بطلب سحب معلق بانتظار اعتماد الإدارة.',
                                  style: TextStyle(color: AppTheme.warning, fontSize: 11),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // Amount
              TextFormField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                enabled: available > 0,
                decoration: InputDecoration(
                  labelText: 'المبلغ المطلوب سحبه',
                  prefixIcon: const Icon(Icons.attach_money, color: AppTheme.gold),
                  suffixText: cur,
                  suffixStyle: const TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold),
                  hintText: available > 0 ? 'الحد الأقصى ${_numberFormat.format(available)}' : 'لا يوجد رصيد متاح حالياً',
                ),
                validator: (v) {
                  if (available <= 0) return 'لا يوجد رصيد متاح للسحب من هذه الوديعة';
                  if (v == null || v.isEmpty) return 'يرجى إدخال المبلغ';
                  final val = double.tryParse(v);
                  if (val == null || val <= 0) return 'المبلغ غير صالح';
                  if (val > available) return 'المبلغ يتجاوز الرصيد المتاح (${_numberFormat.format(available)})';
                  return null;
                },
              ),

              const SizedBox(height: 16),

              // Note
              TextFormField(
                controller: _noteController,
                enabled: available > 0,
                decoration: const InputDecoration(
                  labelText: 'ملاحظة (اختياري)',
                  prefixIcon: Icon(Icons.note_alt_outlined, color: AppTheme.gold),
                ),
              ),

              const SizedBox(height: 24),

              ElevatedButton(
                onPressed: (_isSubmitting || available <= 0) ? null : _handleSubmit,
                child: _isSubmitting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(color: Colors.black, strokeWidth: 2),
                      )
                    : Text(available > 0 ? 'تأكيد وإرسال الطلب للإدارة' : 'الرصيد محجوز بطلب معلق'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
