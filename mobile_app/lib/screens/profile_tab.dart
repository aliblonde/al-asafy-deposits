import 'package:flutter/material.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';
import 'login_screen.dart';

class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key});

  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  Map<String, dynamic>? _investor;

  @override
  void initState() {
    super.initState();
    _loadInvestor();
  }

  void _loadInvestor() async {
    final data = await ApiService.getInvestorData();
    setState(() => _investor = data);
  }

  void _handleLogout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppTheme.bgCard,
        title: const Text('تسجيل الخروج', style: TextStyle(color: AppTheme.gold)),
        content: const Text('هل أنت متأكد من رغبتك في تسجيل الخروج من التطبيق؟'),
        actions: [
          TextButton(
            child: const Text('إلغاء', style: TextStyle(color: AppTheme.textMuted)),
            onPressed: () => Navigator.of(ctx).pop(false),
          ),
          ElevatedButton(
            child: const Text('خروج'),
            onPressed: () => Navigator.of(ctx).pop(true),
          ),
        ],
      ),
    );

    if (confirm == true) {
      await ApiService.clearAuth();
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  // Change Password Dialog
  void _showChangePasswordDialog() {
    final curPassController = TextEditingController();
    final newPassController = TextEditingController();
    final confPassController = TextEditingController();
    final formKey = GlobalKey<FormState>();
    bool isSaving = false;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDlgState) => AlertDialog(
          backgroundColor: AppTheme.bgCard,
          title: const Text('تغيير كلمة المرور', style: TextStyle(color: AppTheme.gold, fontSize: 18)),
          content: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextFormField(
                    controller: curPassController,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'كلمة المرور الحالية'),
                    validator: (v) => (v == null || v.isEmpty) ? 'مطلوب' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: newPassController,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'كلمة المرور الجديدة (8 محارف على الأقل)'),
                    validator: (v) => (v == null || v.length < 8) ? 'يجب أن لا تقل عن 8 محارف' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: confPassController,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'تأكيد كلمة المرور الجديدة'),
                    validator: (v) => (v != newPassController.text) ? 'غير متطابقة' : null,
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              child: const Text('إلغاء', style: TextStyle(color: AppTheme.textMuted)),
              onPressed: () => Navigator.of(ctx).pop(),
            ),
            ElevatedButton(
              onPressed: isSaving
                  ? null
                  : () async {
                      if (!formKey.currentState!.validate()) return;
                      setDlgState(() => isSaving = true);

                      final res = await ApiService.changePassword(
                        currentPassword: curPassController.text,
                        newPassword: newPassController.text,
                        confirmPassword: confPassController.text,
                      );

                      setDlgState(() => isSaving = false);
                      if (!mounted) return;

                      if (res['success'] == true) {
                        Navigator.of(ctx).pop();
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(res['message']), backgroundColor: AppTheme.success),
                        );
                      } else {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(res['message'] ?? 'فشل التغيير'), backgroundColor: AppTheme.danger),
                        );
                      }
                    },
              child: isSaving ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('حفظ'),
            ),
          ],
        ),
      ),
    );
  }

  // Store Compliance: In-App Account Deletion Dialog (Apple 5.1.1 & Google Play)
  void _showDeleteAccountDialog() {
    final passController = TextEditingController();
    final reasonController = TextEditingController();
    final formKey = GlobalKey<FormState>();
    bool isSubmitting = false;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDlgState) => AlertDialog(
          backgroundColor: AppTheme.bgCard,
          title: const Row(
            children: [
              Icon(Icons.warning_amber_rounded, color: AppTheme.danger),
              SizedBox(width: 8),
              Text('طلب حذف الحساب', style: TextStyle(color: AppTheme.danger, fontSize: 18)),
            ],
          ),
          content: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'تنبيه: سيؤدي هذا الإجراء إلى تسجيل طلب إغلاق الحساب وإنهاء صلاحيات الوصول للتطبيق.',
                    style: TextStyle(color: AppTheme.textMuted, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: passController,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'كلمة المرور الحالية للتأكيد'),
                    validator: (v) => (v == null || v.isEmpty) ? 'يرجى إدخال كلمة المرور' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: reasonController,
                    decoration: const InputDecoration(labelText: 'سبب طلب الحذف (اختياري)'),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              child: const Text('إلغاء', style: TextStyle(color: AppTheme.textMuted)),
              onPressed: () => Navigator.of(ctx).pop(),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger, foregroundColor: Colors.white),
              onPressed: isSubmitting
                  ? null
                  : () async {
                      if (!formKey.currentState!.validate()) return;
                      setDlgState(() => isSubmitting = true);

                      final res = await ApiService.deleteAccount(
                        password: passController.text,
                        reason: reasonController.text.trim(),
                      );

                      setDlgState(() => isSubmitting = false);
                      if (!mounted) return;

                      Navigator.of(ctx).pop();

                      if (res['success'] == true) {
                        showDialog(
                          context: context,
                          builder: (c) => AlertDialog(
                            backgroundColor: AppTheme.bgCard,
                            title: const Text('تم استلام الطلب', style: TextStyle(color: AppTheme.gold)),
                            content: Text(res['data']?['message_ar'] ?? res['message']),
                            actions: [
                              ElevatedButton(
                                child: const Text('حسناً'),
                                onPressed: () async {
                                  await ApiService.clearAuth();
                                  if (!mounted) return;
                                  Navigator.of(c).pushAndRemoveUntil(
                                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                                    (route) => false,
                                  );
                                },
                              ),
                            ],
                          ),
                        );
                      } else {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(res['message'] ?? 'فشل الطلب'), backgroundColor: AppTheme.danger),
                        );
                      }
                    },
              child: isSubmitting ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('تأكيد الحذف'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('الملف الشخصي والإعدادات')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // Profile Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    CircleAvatar(
                      radius: 36,
                      backgroundColor: AppTheme.gold.withOpacity(0.2),
                      child: const Icon(Icons.person, color: AppTheme.gold, size: 40),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      _investor?['full_name'] ?? '—',
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'اسم المستخدم: ${_investor?['username'] ?? '—'}',
                      style: const TextStyle(color: AppTheme.textMuted, fontSize: 13),
                    ),
                    const Divider(color: AppTheme.borderGold, height: 26),
                    _buildInfoRow(Icons.phone_outlined, 'رقم الهاتف', _investor?['phone'] ?? '—'),
                    const SizedBox(height: 10),
                    _buildInfoRow(Icons.location_on_outlined, 'المدينة', _investor?['city'] ?? '—'),
                    if (_investor?['national_id'] != null) ...[
                      const SizedBox(height: 10),
                      _buildInfoRow(Icons.badge_outlined, 'الرقم الوطني / الهوية', _investor?['national_id']),
                    ],
                  ],
                ),
              ),
            ),

            const SizedBox(height: 20),

            // Actions Menu
            Card(
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.lock_reset, color: AppTheme.gold),
                    title: const Text('تغيير كلمة المرور'),
                    trailing: const Icon(Icons.chevron_left, color: AppTheme.textMuted),
                    onTap: _showChangePasswordDialog,
                  ),
                  const Divider(color: AppTheme.borderGold, height: 1),
                  ListTile(
                    leading: const Icon(Icons.privacy_tip_outlined, color: AppTheme.gold),
                    title: const Text('سياسة الخصوصية والأمان'),
                    subtitle: const Text('معتمدة لمطابقة معايير المتاجر العالمية', style: TextStyle(fontSize: 11)),
                    trailing: const Icon(Icons.chevron_left, color: AppTheme.textMuted),
                    onTap: () {
                      showDialog(
                        context: context,
                        builder: (_) => AlertDialog(
                          backgroundColor: AppTheme.bgCard,
                          title: const Text('سياسة الخصوصية وحماية البيانات', style: TextStyle(color: AppTheme.gold, fontSize: 16)),
                          content: const SingleChildScrollView(
                            child: Text(
                              'تلتزم مجموعة العسافي للاستثمارات بحماية خصوصية وأمان بيانات العملاء وفق أعلى معايير التشفير (TLS 1.3) وبما يطابق لوائح متجري Google Play و Apple App Store.\n\nيمكنك مراجعة الوثيقة الكاملة عبر الرابط:\nhttps://alasafygroup.xyz/public/privacy_policy.php',
                              style: TextStyle(fontSize: 13, height: 1.6),
                            ),
                          ),
                          actions: [
                            TextButton(child: const Text('إغلاق', style: TextStyle(color: AppTheme.gold)), onPressed: () => Navigator.of(context).pop()),
                          ],
                        ),
                      );
                    },
                  ),
                  const Divider(color: AppTheme.borderGold, height: 1),
                  ListTile(
                    leading: const Icon(Icons.delete_forever_outlined, color: AppTheme.danger),
                    title: const Text('طلب إغلاق وحذف الحساب', style: TextStyle(color: AppTheme.danger)),
                    trailing: const Icon(Icons.chevron_left, color: AppTheme.danger),
                    onTap: _showDeleteAccountDialog,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Logout Button
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: AppTheme.danger,
                side: const BorderSide(color: AppTheme.danger),
                minimumSize: const Size.fromHeight(50),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              icon: const Icon(Icons.logout),
              label: const Text('تسجيل الخروج من الحساب'),
              onPressed: _handleLogout,
            ),

            const SizedBox(height: 20),
            const Text(
              'الإصدار 1.0.0 (Build 1) — مجموعة العسافي للاستثمارات',
              style: TextStyle(color: AppTheme.textMuted, fontSize: 11),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, color: AppTheme.gold, size: 20),
        const SizedBox(width: 10),
        Text(label, style: const TextStyle(color: AppTheme.textMuted, fontSize: 13)),
        const Spacer(),
        Text(value, style: const TextStyle(color: AppTheme.textPrimary, fontWeight: FontWeight.bold, fontSize: 13)),
      ],
    );
  }
}
