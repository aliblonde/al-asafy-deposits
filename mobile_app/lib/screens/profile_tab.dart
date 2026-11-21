import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../constants/theme.dart';
import '../constants/localization.dart';
import '../services/api_service.dart';
import 'login_screen.dart';

class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key});

  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  Map<String, dynamic>? _investor;
  bool _biometricsEnabled = false;
  bool _privacyEnabled = true;

  @override
  void initState() {
    super.initState();
    _loadInvestor();
    _loadBiometricsStatus();
    _loadPrivacyStatus();
  }

  void _loadPrivacyStatus() async {
    final prefs = await SharedPreferences.getInstance();
    final enabled = prefs.getBool('app_privacy_secure') ?? true;
    if (mounted) setState(() => _privacyEnabled = enabled);
  }

  void _togglePrivacyProtection(bool value) async {
    setState(() => _privacyEnabled = value);
    try {
      const channel = MethodChannel('com.alasafygroup.alasafy_investor/security');
      await channel.invokeMethod('setSecure', {'secure': value});
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('app_privacy_secure', value);
    } catch (_) {}
  }

  void _loadInvestor() async {
    final data = await ApiService.getInvestorData();
    if (mounted) setState(() => _investor = data);
  }

  void _loadBiometricsStatus() async {
    final enabled = await ApiService.isBiometricsEnabled();
    if (mounted) setState(() => _biometricsEnabled = enabled);
  }

  void _toggleBiometrics(bool value) async {
    if (value) {
      final creds = await ApiService.getBiometricCredentials();
      String? passwordToSave;

      if (creds == null) {
        // Prompt user to enter their password to bind it with biometrics
        final pwdController = TextEditingController();
        final formKey = GlobalKey<FormState>();
        if (!mounted) return;
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            backgroundColor: AppTheme.bgCard,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: const BorderSide(color: AppTheme.gold, width: 1.5),
            ),
            title: const Row(
              children: [
                Icon(Icons.fingerprint, color: AppTheme.gold),
                SizedBox(width: 8),
                Text('تفعيل الدخول بالبصمة', style: TextStyle(color: AppTheme.gold, fontSize: 16)),
              ],
            ),
            content: Form(
              key: formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('يرجى إدخال كلمة المرور الحالية لربطها بالبصمة وتسجيل الدخول السريع:'),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: pwdController,
                    obscureText: true,
                    validator: (v) => (v == null || v.isEmpty) ? 'كلمة المرور مطلوبة' : null,
                    decoration: const InputDecoration(
                      labelText: 'كلمة المرور',
                      prefixIcon: Icon(Icons.lock_outline, color: AppTheme.gold),
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('إلغاء')),
              ElevatedButton(
                onPressed: () {
                  if (formKey.currentState!.validate()) {
                    Navigator.of(ctx).pop(true);
                  }
                },
                child: const Text('تأكيد'),
              ),
            ],
          ),
        );

        if (confirmed != true || pwdController.text.isEmpty) return;
        passwordToSave = pwdController.text;
      }

      final localAuth = LocalAuthentication();
      try {
        final canCheck = await localAuth.canCheckBiometrics || await localAuth.isDeviceSupported();
        if (!canCheck) {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('جهازك لا يدعم المصادقة بالبصمة'), backgroundColor: AppTheme.danger),
          );
          return;
        }

        final authenticated = await localAuth.authenticate(
          localizedReason: 'يرجى تأكيد البصمة لتفعيل تسجيل الدخول السريع',
          options: const AuthenticationOptions(biometricOnly: false, stickyAuth: true),
        );

        if (authenticated) {
          if (passwordToSave != null) {
            final u = _investor?['username'] ?? '';
            await ApiService.saveBiometricCredentials(u, passwordToSave);
          } else {
            await ApiService.setBiometricsEnabled(true);
          }
          if (!mounted) return;
          setState(() => _biometricsEnabled = true);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('تم تفعيل الدخول بالبصمة بنجاح'), backgroundColor: AppTheme.success),
          );
        }
      } catch (e) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تعذر التحقق من البصمة'), backgroundColor: AppTheme.danger),
        );
      }
    } else {
      await ApiService.setBiometricsEnabled(false);
      if (mounted) setState(() => _biometricsEnabled = false);
    }
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

                      Navigator.of(ctx).pop();

                      if (res['success'] == true) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(res['message'] ?? 'تم تغيير كلمة المرور بنجاح'), backgroundColor: AppTheme.success),
                        );
                      } else {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(res['message'] ?? 'فشل التغيير'), backgroundColor: AppTheme.danger),
                        );
                      }
                    },
              child: isSaving ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('حفظ التغييرات'),
            ),
          ],
        ),
      ),
    );
  }

  // Delete Account Dialog
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
              Text('طلب إغلاق وحذف الحساب', style: TextStyle(color: AppTheme.danger, fontSize: 16)),
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
                    'تنبيه: سيتم إرسال طلب إغلاق الحساب إلى الإدارة للتدقيق وتصفية أي ودائع مستحقة.',
                    style: TextStyle(fontSize: 12, color: AppTheme.textMuted, height: 1.5),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: passController,
                    obscureText: true,
                    decoration: const InputDecoration(labelText: 'أدخل كلمة المرور لتأكيد الطلب'),
                    validator: (v) => (v == null || v.isEmpty) ? 'كلمة المرور مطلوبة' : null,
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
                      backgroundColor: AppTheme.gold.withValues(alpha: 0.2),
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

            const SizedBox(height: 16),

            // Settings & Preferences Card
            Card(
              child: Column(
                children: [
                  // Dark / Light Theme Toggle
                  ValueListenableBuilder<ThemeMode>(
                    valueListenable: AppTheme.themeModeNotifier,
                    builder: (ctx, mode, _) {
                      final isDark = mode == ThemeMode.dark;
                      return SwitchListTile(
                        secondary: Icon(isDark ? Icons.dark_mode_outlined : Icons.light_mode_outlined, color: AppTheme.gold),
                        title: const Text('المظهر الليلي (Dark Mode)'),
                        subtitle: Text(isDark ? 'المظهر الليلي الفاخر مفعل' : 'المظهر النهاري مفعل', style: const TextStyle(fontSize: 11)),
                        value: isDark,
                        activeColor: AppTheme.gold,
                        onChanged: (_) => AppTheme.toggleTheme(),
                      );
                    },
                  ),
                  const Divider(color: AppTheme.borderGold, height: 1),

                  // Biometrics Toggle
                  SwitchListTile(
                    secondary: const Icon(Icons.fingerprint, color: AppTheme.gold),
                    title: const Text('الدخول بالبصمة (Face ID / بصمة الإصبع)'),
                    subtitle: Text(_biometricsEnabled ? 'الدخول السريع مفعل' : 'غير مفعل', style: const TextStyle(fontSize: 11)),
                    value: _biometricsEnabled,
                    activeColor: AppTheme.gold,
                    onChanged: _toggleBiometrics,
                  ),
                  const Divider(color: AppTheme.borderGold, height: 1),

                  // WhatsApp Direct Support
                  ListTile(
                    leading: const Icon(Icons.support_agent, color: Color(0xFF25D366)),
                    title: const Text('خدمة العملاء والدعم الفني (واتساب)'),
                    subtitle: const Text('07731111861 — رد مباشر ومساعدتك في حسابك', style: TextStyle(fontSize: 11)),
                    trailing: const Icon(Icons.chevron_left, color: AppTheme.textMuted),
                    onTap: ApiService.openWhatsAppSupport,
                  ),
                  const Divider(color: AppTheme.borderGold, height: 1),

                  // Language Switcher Tile
                  ValueListenableBuilder<Locale>(
                    valueListenable: AppLocale.localeNotifier,
                    builder: (ctx, locale, _) {
                      final isAr = locale.languageCode == 'ar';
                      return ListTile(
                        leading: const Icon(Icons.language, color: AppTheme.gold),
                        title: const Text('اللغة (Language)'),
                        subtitle: Text(isAr ? 'العربية (Arabic)' : 'English (الإنجليزية)', style: const TextStyle(fontSize: 11)),
                        trailing: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppTheme.gold.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.gold, width: 1),
                          ),
                          child: Text(isAr ? 'EN' : 'عربي', style: const TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold, fontSize: 12)),
                        ),
                        onTap: AppLocale.toggleLanguage,
                      );
                    },
                  ),
                  const Divider(color: AppTheme.borderGold, height: 1),

                  // Privacy Protection (FLAG_SECURE) Toggle
                  SwitchListTile(
                    secondary: const Icon(Icons.security, color: AppTheme.gold),
                    title: const Text('حماية الخصوصية (إخفاء الشاشة)'),
                    subtitle: Text(_privacyEnabled ? 'حظر لقطات الشاشة وإخفاء البيانات في قائمة التطبيقات' : 'غير مفعل', style: const TextStyle(fontSize: 11)),
                    value: _privacyEnabled,
                    activeColor: AppTheme.gold,
                    onChanged: _togglePrivacyProtection,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Account Security & Legal Card
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
              'الإصدار 1.0.0 — مجموعة العسافي للاستثمارات',
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
