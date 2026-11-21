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
    // Default select first active deposit with accumulated profit if available
    for (var d in widget.deposits) {
      if ((d['accumulated_profit'] ?? 0) > 0) {
        _selectedDepositId = d['id'];
        _selectedDeposit = d;
        break;
      }
    }
  }

  void _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedDepositId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يرجى اختيار الوديعة أولاً'), backgroundColor: AppTheme.danger),
      );
      return;
    }

    final amount = double.tryParse(_amountController.text) ?? 0;
    final available = (_selectedDeposit?['available_to_withdraw'] ?? _selectedDeposit?['accumulated_profit'] ?? 0).toDouble();

    if (amount <= 0 || amount > available) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('المبلغ المطلوب غير متاح في الوديعة (${_numberFormat.format(available)})'),
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
                  final cur = d['currency'] ?? '';
                  final acc = (d['accumulated_profit'] ?? 0).toDouble();
                  return DropdownMenuItem<int>(
                    value: d['id'],
                    child: Text(
                      'وديعة #${d['id']} (${_numberFormat.format(d['amount'])} $cur) — متاح: ${_numberFormat.format(acc)}',
                      style: const TextStyle(fontSize: 12),
                    ),
                  );
                }).toList(),
                onChanged: (val) {
                  setState(() {
                    _selectedDepositId = val;
                    _selectedDeposit = widget.deposits.firstWhere((e) => e['id'] == val, orElse: () => null);
                  });
                },
              ),

              const SizedBox(height: 16),

              // Amount
              TextFormField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  labelText: 'المبلغ المطلوب سحبه',
                  prefixIcon: const Icon(Icons.attach_money, color: AppTheme.gold),
                  suffixText: _selectedDeposit?['currency'] ?? '',
                  suffixStyle: const TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold),
                ),
                validator: (v) {
                  if (v == null || v.isEmpty) return 'يرجى إدخال المبلغ';
                  final val = double.tryParse(v);
                  if (val == null || val <= 0) return 'المبلغ غير صالح';
                  return null;
                },
              ),

              const SizedBox(height: 16),

              // Note
              TextFormField(
                controller: _noteController,
                decoration: const InputDecoration(
                  labelText: 'ملاحظة (اختياري)',
                  prefixIcon: Icon(Icons.note_alt_outlined, color: AppTheme.gold),
                ),
              ),

              const SizedBox(height: 24),

              ElevatedButton(
                onPressed: _isSubmitting ? null : _handleSubmit,
                child: _isSubmitting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(color: Colors.black, strokeWidth: 2),
                      )
                    : const Text('تأكيد وإرسال الطلب للإدارة'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
