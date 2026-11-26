import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:local_auth/local_auth.dart';
import 'package:url_launcher/url_launcher.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';
import 'withdraw_dialog.dart';

class WalletTab extends StatefulWidget {
  const WalletTab({super.key});

  @override
  State<WalletTab> createState() => _WalletTabState();
}

class _WalletTabState extends State<WalletTab> {
  bool _isLoading = true;
  Map<String, dynamic>? _walletData;
  String _selectedCurrency = 'USD';
  bool _hideBalance = false;
  final NumberFormat _fmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _fetchWallet();
  }

  Future<void> _fetchWallet() async {
    setState(() => _isLoading = true);
    final res = await ApiService.getWalletData();
    if (mounted) {
      setState(() {
        _isLoading = false;
        if (res['success'] == true && res['data'] != null) {
          _walletData = res['data'] as Map<String, dynamic>;
        }
      });
    }
  }

  void _openTopupSheet() {
    final channels = (_walletData?['company_channels'] as List<dynamic>?) ?? [];
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _WalletTopupSheet(
        channels: channels,
        defaultCurrency: _selectedCurrency,
        onSuccess: () {
          Navigator.of(ctx).pop();
          _fetchWallet();
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('تم إرسال طلب الشحن وإشعار التحويل بنجاح! سيتم تدقيقه واعتماد الرصيد فوراً.'),
              backgroundColor: AppTheme.success,
            ),
          );
        },
      ),
    );
  }

  void _openWithdrawDialog() {
    showDialog(
      context: context,
      builder: (_) => WithdrawDialog(
        deposits: const [],
        onSuccess: _fetchWallet,
      ),
    );
  }

  void _openTransferSheet(double availableBalance) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _WalletTransferSheet(
        availableBalance: availableBalance,
        currency: _selectedCurrency,
        onSuccess: () {
          Navigator.of(ctx).pop();
          _fetchWallet();
        },
      ),
    );
  }

  void _showMyQrDialog(String walletId) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppTheme.bgCard,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: const BorderSide(color: AppTheme.borderGold, width: 1.5),
        ),
        title: const Row(
          children: [
            Icon(Icons.qr_code_2, color: AppTheme.gold, size: 28),
            SizedBox(width: 8),
            Text('رمز محفظتي الرقمية', style: TextStyle(color: AppTheme.gold, fontSize: 16)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                children: [
                  Icon(Icons.qr_code_scanner, size: 140, color: Colors.grey.shade900),
                  const SizedBox(height: 8),
                  Text(
                    walletId,
                    style: const TextStyle(
                      fontFamily: 'monospace',
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                      color: Colors.black,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'شارك رقم محفظتك مع المستثمرين الآخرين لتلقي الحوالات الفورية مباشرة.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: AppTheme.textMuted),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: AppTheme.gold,
                side: const BorderSide(color: AppTheme.gold),
              ),
              icon: const Icon(Icons.copy, size: 16),
              label: Text('نسخ رقم المحفظة: $walletId'),
              onPressed: () {
                Clipboard.setData(ClipboardData(text: walletId));
                Navigator.of(ctx).pop();
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('تم نسخ $walletId'), backgroundColor: AppTheme.gold),
                );
              },
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('إغلاق', style: TextStyle(color: AppTheme.textMuted)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(color: AppTheme.gold),
        ),
      );
    }

    final totalAvailMap = (_walletData?['total_available'] as Map<String, dynamic>?) ?? {};
    final totalAvail = (totalAvailMap[_selectedCurrency] as num?)?.toDouble() ?? 0.0;

    final capitalMap = (_walletData?['invested_capital'] as Map<String, dynamic>?) ?? {};
    final investedCapital = (capitalMap[_selectedCurrency] as num?)?.toDouble() ?? 0.0;

    final directBalMap = (_walletData?['direct_wallet_balance'] as Map<String, dynamic>?) ?? {};
    final directBal = (directBalMap[_selectedCurrency] as num?)?.toDouble() ?? 0.0;

    final profitsMap = (_walletData?['available_profits'] as Map<String, dynamic>?) ?? {};
    final profitsBal = (profitsMap[_selectedCurrency] as num?)?.toDouble() ?? 0.0;

    final walletId = _walletData?['wallet_id'] ?? 'W-000000';
    final topupList = (_walletData?['topup_requests'] as List<dynamic>?) ?? [];
    final channels = (_walletData?['company_channels'] as List<dynamic>?) ?? [];

    return Scaffold(
      appBar: AppBar(
        title: const Text('المحفظة الرقمية (AL-ASAFY)'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: AppTheme.gold),
            tooltip: 'تحديث البيانات',
            onPressed: _fetchWallet,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchWallet,
        color: AppTheme.gold,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Luxury Digital Card
              _buildDigitalWalletCard(walletId, totalAvail, directBal, profitsBal),

              const SizedBox(height: 14),

              // 2. Protected Capital Banner
              _buildCapitalNotice(investedCapital),

              const SizedBox(height: 18),

              // 3. Quick Action Buttons (2x2 Grid)
              _buildActionButtons(totalAvail),

              const SizedBox(height: 24),

              // 4. Company Payment Channels (Where to transfer)
              if (channels.isNotEmpty) ...[
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'حسابات وقنوات التحويل للشركة',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.gold),
                    ),
                    Text(
                      'اضغط للنسخ',
                      style: TextStyle(fontSize: 11, color: Colors.white.withValues(alpha: 0.6)),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                ...channels.map((ch) => _buildChannelTile(ch as Map<String, dynamic>)),
                const SizedBox(height: 20),
              ],

              // 5. Recent Top-up Requests & Ledger
              const Text(
                'سجل طلبات شحن المحفظة',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.gold),
              ),
              const SizedBox(height: 10),
              if (topupList.isEmpty)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(24.0),
                    child: Center(
                      child: Column(
                        children: [
                          Icon(Icons.receipt_long_outlined, size: 40, color: Colors.white.withValues(alpha: 0.3)),
                          const SizedBox(height: 8),
                          const Text(
                            'لا توجد طلبات شحن سابقة',
                            style: TextStyle(color: AppTheme.textMuted, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  ),
                )
              else
                ...topupList.map((req) => _buildTopupItem(req as Map<String, dynamic>)),
            ],
          ),
        ),
      ),
    );
  }

  // Digital Wallet Card Widget
  Widget _buildDigitalWalletCard(String walletId, double totalAvail, double directBal, double profitsBal) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: const LinearGradient(
          colors: [
            Color(0xFF262C36),
            Color(0xFF13171F),
            Color(0xFF0B0E13),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(color: AppTheme.gold.withValues(alpha: 0.6), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: AppTheme.gold.withValues(alpha: 0.15),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top row: Brand & Currency toggle
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(color: AppTheme.gold, width: 1),
                    ),
                    child: Image.asset('assets/images/app_logo.png', fit: BoxFit.contain),
                  ),
                  const SizedBox(width: 10),
                  const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'مجموعة العسافي (AL-ASAFY)',
                        style: TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                      Text(
                        'بطاقة المحفظة الرقمية',
                        style: TextStyle(color: AppTheme.textMuted, fontSize: 10),
                      ),
                    ],
                  ),
                ],
              ),
              // Currency Toggle
              Container(
                decoration: BoxDecoration(
                  color: AppTheme.bgBase,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppTheme.gold.withValues(alpha: 0.4)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _buildCurBtn('USD', 'دولار'),
                    _buildCurBtn('IQD', 'دينار'),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 24),

          // Balance Label with Hide/Show Eye
          Row(
            children: [
              Text(
                'الرصيد المتاح للتصرف والسحب',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 12),
              ),
              const SizedBox(width: 6),
              GestureDetector(
                onTap: () => setState(() => _hideBalance = !_hideBalance),
                child: Icon(
                  _hideBalance ? Icons.visibility_off : Icons.visibility,
                  size: 16,
                  color: AppTheme.gold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),

          // Balance Display
          Text(
            _hideBalance ? '••••••••' : '${_fmt.format(totalAvail)} $_selectedCurrency',
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.bold,
              color: AppTheme.gold,
              letterSpacing: 0.5,
            ),
          ),
          const SizedBox(height: 6),

          // Detail breakdown
          Text(
            _hideBalance
                ? '••••'
                : 'الأرباح المستحقة: ${_fmt.format(profitsBal)} | الرصيد المشحون: ${_fmt.format(directBal)}',
            style: TextStyle(color: Colors.white.withValues(alpha: 0.6), fontSize: 11),
          ),

          const SizedBox(height: 18),
          const Divider(color: AppTheme.borderGold, height: 1),
          const SizedBox(height: 12),

          // Bottom card row: Wallet ID and Status
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              GestureDetector(
                onTap: () => _showMyQrDialog(walletId),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppTheme.gold.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppTheme.gold.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.qr_code, color: AppTheme.gold, size: 18),
                      const SizedBox(width: 6),
                      Text(
                        walletId,
                        style: const TextStyle(
                          fontFamily: 'monospace',
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 4),
                      const Icon(Icons.touch_app, size: 12, color: AppTheme.gold),
                    ],
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                decoration: BoxDecoration(
                  color: AppTheme.success.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppTheme.success, width: 0.8),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.check_circle, color: AppTheme.success, size: 12),
                    SizedBox(width: 4),
                    Text(
                      'محفظة معتمدة',
                      style: TextStyle(color: AppTheme.success, fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildCurBtn(String code, String label) {
    final isSelected = _selectedCurrency == code;
    return GestureDetector(
      onTap: () => setState(() => _selectedCurrency = code),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.gold : Colors.transparent,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: isSelected ? Colors.black : Colors.white70,
            fontSize: 11,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }

  // Capital Notice (Frozen/Protected in contracts)
  Widget _buildCapitalNotice(double capital) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppTheme.bgCard,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.borderGold.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          const Icon(Icons.shield_outlined, color: AppTheme.gold, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: RichText(
              text: TextSpan(
                style: const TextStyle(fontSize: 12, color: AppTheme.textPrimary, fontFamily: 'Tajawal'),
                children: [
                  const TextSpan(text: 'رأس المال المستثمر: '),
                  TextSpan(
                    text: _hideBalance ? '••••' : '${_fmt.format(capital)} $_selectedCurrency',
                    style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.gold),
                  ),
                  const TextSpan(
                    text: ' (مجمّد ومحمي في عقود الودائع النشطة)',
                    style: TextStyle(color: AppTheme.textMuted, fontSize: 11),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // Quick Action Buttons (2x2 Grid)
  Widget _buildActionButtons(double availableBalance) {
    return Column(
      children: [
        // Row 1: Top-up & Withdraw
        Row(
          children: [
            Expanded(
              child: ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.gold,
                  foregroundColor: Colors.black,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: const Icon(Icons.add_card, size: 18),
                label: const Text('شحن المحفظة', style: TextStyle(fontWeight: FontWeight.bold)),
                onPressed: _openTopupSheet,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.gold,
                  side: const BorderSide(color: AppTheme.gold),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: const Icon(Icons.arrow_upward, size: 18),
                label: const Text('سحب الأرباح', style: TextStyle(fontWeight: FontWeight.bold)),
                onPressed: _openWithdrawDialog,
              ),
            ),
          ],
        ),

        const SizedBox(height: 10),

        // Row 2: P2P Transfer & New Investment (Coming Soon)
        Row(
          children: [
            Expanded(
              child: ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1E2836),
                  foregroundColor: AppTheme.gold,
                  side: const BorderSide(color: AppTheme.gold, width: 1),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: const Icon(Icons.swap_horiz, size: 20, color: AppTheme.gold),
                label: const Text('تحويل لمستثمر', style: TextStyle(fontWeight: FontWeight.bold)),
                onPressed: () => _openTransferSheet(availableBalance),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppTheme.textMuted,
                        side: BorderSide(color: Colors.white.withValues(alpha: 0.2)),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      icon: const Icon(Icons.diamond_outlined, size: 18),
                      label: const Text('استثمار جديد', style: TextStyle(fontSize: 12)),
                      onPressed: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text('ميزة فتح استثمار جديد من المحفظة ستتاح قريباً بعد اعتماد الآلية من الإدارة.'),
                            backgroundColor: AppTheme.gold,
                          ),
                        );
                      },
                    ),
                  ),
                  Positioned(
                    top: -6,
                    right: 4,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppTheme.danger,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Text(
                        'قريباً',
                        style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ],
    );
  }

  // Company Payment Channel Card
  Widget _buildChannelTile(Map<String, dynamic> ch) {
    final accNo = ch['account_number'] ?? '';
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: AppTheme.gold.withValues(alpha: 0.15),
          child: Icon(_getChannelIcon(ch['type']), color: AppTheme.gold, size: 20),
        ),
        title: Text(ch['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 2),
            Text(ch['account_title'] ?? '', style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
            const SizedBox(height: 2),
            Text(
              accNo,
              style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.bold, color: Colors.white, fontSize: 12),
            ),
          ],
        ),
        trailing: IconButton(
          icon: const Icon(Icons.copy, size: 18, color: AppTheme.gold),
          tooltip: 'نسخ رقم الحساب',
          onPressed: () {
            Clipboard.setData(ClipboardData(text: accNo));
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('تم نسخ: $accNo'), backgroundColor: AppTheme.gold, duration: const Duration(seconds: 2)),
            );
          },
        ),
      ),
    );
  }

  IconData _getChannelIcon(dynamic type) {
    switch (type) {
      case 'bank':
        return Icons.account_balance;
      case 'wallet':
        return Icons.phone_android;
      case 'exchange':
        return Icons.currency_exchange;
      default:
        return Icons.payment;
    }
  }

  // Topup Item Widget
  Widget _buildTopupItem(Map<String, dynamic> req) {
    final status = req['status'] ?? 'pending';
    final amount = (req['amount'] as num?)?.toDouble() ?? 0.0;
    final cur = req['currency'] ?? 'USD';
    final date = req['created_at'] ?? '';

    Color statusColor;
    String statusText;
    IconData statusIcon;

    if (status == 'approved') {
      statusColor = AppTheme.success;
      statusText = 'معتمد ومضاف';
      statusIcon = Icons.check_circle_outline;
    } else if (status == 'rejected') {
      statusColor = AppTheme.danger;
      statusText = 'مرفوض';
      statusIcon = Icons.cancel_outlined;
    } else {
      statusColor = AppTheme.warning;
      statusText = 'قيد المراجعة';
      statusIcon = Icons.hourglass_top;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: statusColor.withValues(alpha: 0.15),
          child: Icon(statusIcon, color: statusColor, size: 22),
        ),
        title: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              '+${_fmt.format(amount)} $cur',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Colors.white),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                color: statusColor.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: statusColor, width: 0.8),
              ),
              child: Text(
                statusText,
                style: TextStyle(color: statusColor, fontSize: 11, fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text('طريقة التحويل: ${req['payment_method'] ?? '—'}', style: const TextStyle(fontSize: 11)),
            if (req['transfer_reference'] != null && req['transfer_reference'].toString().isNotEmpty)
              Text('المرجع: ${req['transfer_reference']}', style: const TextStyle(fontSize: 11, fontFamily: 'monospace')),
            Text(date, style: const TextStyle(fontSize: 10, color: AppTheme.textMuted)),
          ],
        ),
      ),
    );
  }
}

// -------------------------------------------------------------
// Bottom Sheet for Top-up with Receipt Image Upload
// -------------------------------------------------------------
class _WalletTopupSheet extends StatefulWidget {
  final List<dynamic> channels;
  final String defaultCurrency;
  final VoidCallback onSuccess;

  const _WalletTopupSheet({
    required this.channels,
    required this.defaultCurrency,
    required this.onSuccess,
  });

  @override
  State<_WalletTopupSheet> createState() => _WalletTopupSheetState();
}

class _WalletTopupSheetState extends State<_WalletTopupSheet> {
  final _formKey = GlobalKey<FormState>();
  final _amountCtrl = TextEditingController();
  final _refCtrl = TextEditingController();
  final _noteCtrl = TextEditingController();

  late String _currency;
  String? _selectedMethod;
  File? _receiptFile;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _currency = widget.defaultCurrency;
    if (widget.channels.isNotEmpty) {
      _selectedMethod = widget.channels.first['name'] as String?;
    } else {
      _selectedMethod = 'تحويل بنكي FIB';
    }
  }

  Future<void> _pickReceipt(ImageSource source) async {
    try {
      final picker = ImagePicker();
      final picked = await picker.pickImage(source: source, maxWidth: 1600, maxHeight: 1600, imageQuality: 85);
      if (picked != null) {
        setState(() => _receiptFile = File(picked.path));
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('تعذر اختيار الصورة: $e'), backgroundColor: AppTheme.danger),
      );
    }
  }

  void _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final amt = double.tryParse(_amountCtrl.text.trim()) ?? 0;
    if (amt <= 0) return;

    setState(() => _isSubmitting = true);

    String? base64Img;
    if (_receiptFile != null) {
      final bytes = await _receiptFile!.readAsBytes();
      base64Img = 'data:image/jpeg;base64,${base64Encode(bytes)}';
    }

    final res = await ApiService.submitWalletTopup(
      amount: amt,
      currency: _currency,
      paymentMethod: _selectedMethod ?? 'تحويل بنكي',
      transferReference: _refCtrl.text.trim(),
      receiptBase64: base64Img,
      note: _noteCtrl.text.trim(),
    );

    setState(() => _isSubmitting = false);

    if (res['success'] == true) {
      widget.onSuccess();
    } else {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'فشل إرسال الطلب'), backgroundColor: AppTheme.danger),
      );
    }
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _refCtrl.dispose();
    _noteCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.only(
        top: 20,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      decoration: const BoxDecoration(
        color: AppTheme.bgCard,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'شحن المحفظة (إرسال إشعار تحويل)',
                    style: TextStyle(color: AppTheme.gold, fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white70),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              const Divider(color: AppTheme.borderGold),
              const SizedBox(height: 8),

              // Method Dropdown
              const Text('طريقة التحويل المستخدمة:', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
              const SizedBox(height: 6),
              DropdownButtonFormField<String>(
                value: _selectedMethod,
                decoration: const InputDecoration(contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10)),
                items: widget.channels.map((ch) {
                  final name = ch['name'] as String;
                  return DropdownMenuItem(value: name, child: Text(name, style: const TextStyle(fontSize: 13)));
                }).toList(),
                onChanged: (v) => setState(() => _selectedMethod = v),
              ),

              const SizedBox(height: 12),

              // Amount & Currency Row
              Row(
                children: [
                  Expanded(
                    flex: 2,
                    child: TextFormField(
                      controller: _amountCtrl,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(
                        labelText: 'المبلغ المحوّل *',
                        prefixIcon: Icon(Icons.attach_money, color: AppTheme.gold),
                      ),
                      validator: (v) {
                        if (v == null || v.trim().isEmpty) return 'المبلغ مطلوب';
                        final val = double.tryParse(v.trim());
                        if (val == null || val <= 0) return 'مبلغ غير صالح';
                        return null;
                      },
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 1,
                    child: DropdownButtonFormField<String>(
                      value: _currency,
                      decoration: const InputDecoration(contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 10)),
                      items: const [
                        DropdownMenuItem(value: 'USD', child: Text('USD')),
                        DropdownMenuItem(value: 'IQD', child: Text('IQD')),
                      ],
                      onChanged: (v) => setState(() => _currency = v ?? 'USD'),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 12),

              // Transfer Reference
              TextFormField(
                controller: _refCtrl,
                decoration: const InputDecoration(
                  labelText: 'رقم مرجع الحوالة / العملية (اختياري)',
                  prefixIcon: Icon(Icons.tag, color: AppTheme.gold),
                ),
              ),

              const SizedBox(height: 12),

              // Receipt Image Picker
              const Text('صورة وصل / إشعار التحويل:', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
              const SizedBox(height: 6),
              if (_receiptFile != null)
                Stack(
                  alignment: Alignment.topRight,
                  children: [
                    Container(
                      height: 120,
                      width: double.infinity,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppTheme.gold),
                        image: DecorationImage(
                          image: FileImage(_receiptFile!),
                          fit: BoxFit.cover,
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const CircleAvatar(
                        radius: 14,
                        backgroundColor: AppTheme.danger,
                        child: Icon(Icons.close, size: 16, color: Colors.white),
                      ),
                      onPressed: () => setState(() => _receiptFile = null),
                    ),
                  ],
                )
              else
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppTheme.gold,
                          side: const BorderSide(color: AppTheme.borderGold),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                        ),
                        icon: const Icon(Icons.camera_alt, size: 18),
                        label: const Text('الكاميرا', style: TextStyle(fontSize: 12)),
                        onPressed: () => _pickReceipt(ImageSource.camera),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton.icon(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppTheme.gold,
                          side: const BorderSide(color: AppTheme.borderGold),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                        ),
                        icon: const Icon(Icons.photo_library, size: 18),
                        label: const Text('المعرض', style: TextStyle(fontSize: 12)),
                        onPressed: () => _pickReceipt(ImageSource.gallery),
                      ),
                    ),
                  ],
                ),

              const SizedBox(height: 16),

              // Submit Button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.gold,
                    foregroundColor: Colors.black,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: _isSubmitting ? null : _submit,
                  child: _isSubmitting
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                      : const Text('تأكيد وإرسال طلب الشحن', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// -------------------------------------------------------------
// Bottom Sheet for P2P Investor-to-Investor Transfer
// -------------------------------------------------------------
class _WalletTransferSheet extends StatefulWidget {
  final double availableBalance;
  final String currency;
  final VoidCallback onSuccess;

  const _WalletTransferSheet({
    required this.availableBalance,
    required this.currency,
    required this.onSuccess,
  });

  @override
  State<_WalletTransferSheet> createState() => _WalletTransferSheetState();
}

class _WalletTransferSheetState extends State<_WalletTransferSheet> {
  final _searchCtrl = TextEditingController();
  final _amountCtrl = TextEditingController();
  final _noteCtrl = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  bool _isSearching = false;
  Map<String, dynamic>? _verifiedRecipient;
  String? _lookupError;

  bool _isSubmitting = false;

  void _searchRecipient() async {
    final q = _searchCtrl.text.trim();
    if (q.isEmpty) return;

    setState(() {
      _isSearching = true;
      _lookupError = null;
      _verifiedRecipient = null;
    });

    final res = await ApiService.lookupInvestor(q);

    if (!mounted) return;
    setState(() {
      _isSearching = false;
      if (res['success'] == true && res['data'] != null) {
        _verifiedRecipient = res['data'] as Map<String, dynamic>;
        _lookupError = null;
      } else {
        _lookupError = res['message'] ?? 'لم يتم العثور على المستثمر';
        _verifiedRecipient = null;
      }
    });
  }

  void _submitTransfer() async {
    if (_verifiedRecipient == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يرجى التحقق من المستلم أولاً.'), backgroundColor: AppTheme.warning),
      );
      return;
    }

    if (!_formKey.currentState!.validate()) return;
    final amt = double.tryParse(_amountCtrl.text.trim()) ?? 0;
    if (amt <= 0) return;

    if (amt > widget.availableBalance) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('المبلغ المطلوب ($amt) يتجاوز رصيدك المتاح (${widget.availableBalance} ${widget.currency}).'),
          backgroundColor: AppTheme.danger,
        ),
      );
      return;
    }

    // Biometric Security Confirmation
    final localAuth = LocalAuthentication();
    try {
      final canCheck = await localAuth.canCheckBiometrics || await localAuth.isDeviceSupported();
      if (canCheck) {
        final auth = await localAuth.authenticate(
          localizedReason: 'يرجى تأكيد البصمة لإتمام الحوالة المالية إلى ${_verifiedRecipient!['full_name']}',
          options: const AuthenticationOptions(biometricOnly: false, stickyAuth: true),
        );
        if (!auth) return;
      }
    } catch (_) {}

    setState(() => _isSubmitting = true);

    final res = await ApiService.transferToInvestor(
      receiverId: _verifiedRecipient!['recipient_id'] as int,
      amount: amt,
      currency: widget.currency,
      note: _noteCtrl.text.trim(),
    );

    setState(() => _isSubmitting = false);

    if (res['success'] == true) {
      if (!mounted) return;
      final transferData = res['data'] as Map<String, dynamic>?;
      final trfNo = transferData?['transfer_no'] ?? '';
      final recName = transferData?['receiver_name'] ?? _verifiedRecipient!['full_name'];

      // Show Success Receipt Dialog
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (c) => AlertDialog(
          backgroundColor: AppTheme.bgCard,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
            side: const BorderSide(color: AppTheme.success, width: 1.5),
          ),
          title: const Row(
            children: [
              Icon(Icons.check_circle, color: AppTheme.success, size: 28),
              SizedBox(width: 8),
              Text('تم التحويل بنجاح', style: TextStyle(color: AppTheme.success, fontSize: 17)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('تم إرسال مبلغ $amt ${widget.currency} بنجاح إلى:'),
              const SizedBox(height: 6),
              Text(recName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.gold)),
              const SizedBox(height: 8),
              Text('رقم الحوالة: $trfNo', style: const TextStyle(fontFamily: 'monospace', fontSize: 12, color: AppTheme.textMuted)),
              const Divider(color: AppTheme.borderGold, height: 20),
              const Text('تم خصم المبلغ من رصيدك المتاح وإضافته لمحفظة المستلم فوراً.', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
            ],
          ),
          actions: [
            OutlinedButton.icon(
              icon: const Icon(Icons.share, size: 16),
              label: const Text('مشاركة الإشعار'),
              onPressed: () {
                final text = 'إشعار تحويل مالي عبر مجموعة العسافي\nالمبلغ: $amt ${widget.currency}\nالمستلم: $recName\nرقم الحوالة: $trfNo';
                final url = Uri.parse('https://wa.me/?text=${Uri.encodeComponent(text)}');
                launchUrl(url, mode: LaunchMode.externalApplication);
              },
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.gold, foregroundColor: Colors.black),
              child: const Text('إغلاق'),
              onPressed: () {
                Navigator.of(c).pop();
                widget.onSuccess();
              },
            ),
          ],
        ),
      );
    } else {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'فشل التحويل'), backgroundColor: AppTheme.danger),
      );
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _amountCtrl.dispose();
    _noteCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.only(
        top: 20,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      decoration: const BoxDecoration(
        color: AppTheme.bgCard,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'تحويل رصيد لمستثمر آخر',
                    style: TextStyle(color: AppTheme.gold, fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white70),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              const Divider(color: AppTheme.borderGold),

              // Available Balance notice
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: AppTheme.gold.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppTheme.gold.withValues(alpha: 0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.account_balance_wallet, size: 16, color: AppTheme.gold),
                    const SizedBox(width: 8),
                    Text(
                      'رصيدك المتاح للتحويل: ${widget.availableBalance.toStringAsFixed(2)} ${widget.currency}',
                      style: const TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold, fontSize: 12),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 14),

              // Search Recipient input
              const Text('رقم محفظة أو اسم مستخدم المستلم:', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _searchCtrl,
                      decoration: const InputDecoration(
                        hintText: 'مثال: W-000105 أو اسم المستخدم',
                        prefixIcon: Icon(Icons.search, color: AppTheme.gold),
                      ),
                      onFieldSubmitted: (_) => _searchRecipient(),
                    ),
                  ),
                  const SizedBox(width: 8),
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.gold,
                      foregroundColor: Colors.black,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    ),
                    onPressed: _isSearching ? null : _searchRecipient,
                    child: _isSearching
                        ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                        : const Text('تحقق', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ],
              ),

              // Lookup Result Banner
              if (_verifiedRecipient != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.success.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppTheme.success),
                  ),
                  child: Row(
                    children: [
                      const CircleAvatar(
                        backgroundColor: AppTheme.success,
                        radius: 18,
                        child: Icon(Icons.check, color: Colors.white, size: 20),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _verifiedRecipient!['full_name'],
                              style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 14),
                            ),
                            Text(
                              'رقم المحفظة: ${_verifiedRecipient!['wallet_id']} | اسم المستخدم: ${_verifiedRecipient!['username']}',
                              style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              if (_lookupError != null) ...[
                const SizedBox(height: 8),
                Text(_lookupError!, style: const TextStyle(color: AppTheme.danger, fontSize: 12)),
              ],

              const SizedBox(height: 14),

              // Amount to Transfer
              TextFormField(
                controller: _amountCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  labelText: 'المبلغ المراد تحويله (${widget.currency}) *',
                  prefixIcon: const Icon(Icons.payments_outlined, color: AppTheme.gold),
                ),
                validator: (v) {
                  if (v == null || v.trim().isEmpty) return 'المبلغ مطلوب';
                  final val = double.tryParse(v.trim());
                  if (val == null || val <= 0) return 'مبلغ غير صالح';
                  if (val > widget.availableBalance) return 'المبلغ يتجاوز رصيدك المتاح';
                  return null;
                },
              ),

              const SizedBox(height: 12),

              // Note
              TextFormField(
                controller: _noteCtrl,
                decoration: const InputDecoration(
                  labelText: 'ملاحظة أو سبب الحوالة (اختياري)',
                  prefixIcon: Icon(Icons.notes, color: AppTheme.gold),
                ),
              ),

              const SizedBox(height: 20),

              // Submit Button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.gold,
                    foregroundColor: Colors.black,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  icon: const Icon(Icons.lock_outline, size: 18),
                  label: _isSubmitting
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                      : const Text('تأكيد التحويل بالبصمة', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                  onPressed: (_isSubmitting || _verifiedRecipient == null) ? null : _submitTransfer,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
