import 'package:flutter/material.dart';
import 'package:local_auth/local_auth.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';
import 'main_nav_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _isLoading = false;
  bool _hasBiometrics = false;

  @override
  void initState() {
    super.initState();
    _checkBiometrics();
  }

  Future<void> _checkBiometrics() async {
    final enabled = await ApiService.isBiometricsEnabled();
    final creds = await ApiService.getBiometricCredentials();
    if (enabled && creds != null) {
      if (mounted) setState(() => _hasBiometrics = true);
      // Auto-trigger biometric prompt on startup if enabled
      _handleBiometricLogin();
    }
  }

  Future<void> _handleBiometricLogin() async {
    final localAuth = LocalAuthentication();
    try {
      final canCheck = await localAuth.canCheckBiometrics || await localAuth.isDeviceSupported();
      if (!canCheck) return;

      final authenticated = await localAuth.authenticate(
        localizedReason: 'تأكيد البصمة لتسجيل الدخول إلى حسابك الاستثماري',
        options: const AuthenticationOptions(biometricOnly: false, stickyAuth: true),
      );

      if (authenticated) {
        final creds = await ApiService.getBiometricCredentials();
        if (creds != null) {
          setState(() => _isLoading = true);
          final res = await ApiService.login(creds['username']!, creds['password']!);
          if (!mounted) return;
          setState(() => _isLoading = false);
          if (res['success'] == true) {
            Navigator.of(context).pushReplacement(
              MaterialPageRoute(builder: (_) => const MainNavScreen()),
            );
          }
        }
      }
    } catch (_) {}
  }

  void _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    final u = _usernameController.text.trim();
    final p = _passwordController.text;

    final res = await ApiService.login(u, p);

    setState(() => _isLoading = false);

    if (!mounted) return;

    if (res['success'] == true) {
      final bioEnabled = await ApiService.isBiometricsEnabled();
      if (bioEnabled) {
        await ApiService.saveBiometricCredentials(u, p);
      }
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const MainNavScreen()),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'فشل تسجيل الدخول'),
          backgroundColor: AppTheme.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 20),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // App Brand Logo / Icon
                  Center(
                    child: Container(
                      width: 90,
                      height: 90,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: AppTheme.gold, width: 2),
                        gradient: const RadialGradient(
                          colors: [AppTheme.bgCard, AppTheme.bgBase],
                        ),
                      ),
                      child: const Center(
                        child: Icon(Icons.account_balance, color: AppTheme.gold, size: 45),
                      ),
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Titles
                  const Text(
                    'مجموعة العسافي للاستثمارات',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: AppTheme.gold,
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.5,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'بوابة المستثمر الإلكترونية الرسمية',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: AppTheme.textMuted,
                      fontSize: 14,
                    ),
                  ),

                  const SizedBox(height: 36),

                  // Username
                  TextFormField(
                    controller: _usernameController,
                    decoration: const InputDecoration(
                      labelText: 'اسم المستخدم أو رقم الحساب',
                      prefixIcon: Icon(Icons.person_outline, color: AppTheme.gold),
                    ),
                    validator: (v) => (v == null || v.isEmpty) ? 'يرجى إدخال اسم المستخدم' : null,
                  ),

                  const SizedBox(height: 18),

                  // Password
                  TextFormField(
                    controller: _passwordController,
                    obscureText: _obscurePassword,
                    decoration: InputDecoration(
                      labelText: 'كلمة المرور',
                      prefixIcon: const Icon(Icons.lock_outline, color: AppTheme.gold),
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscurePassword ? Icons.visibility_off : Icons.visibility,
                          color: AppTheme.textMuted,
                        ),
                        onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                      ),
                    ),
                    validator: (v) => (v == null || v.isEmpty) ? 'يرجى إدخال كلمة المرور' : null,
                  ),
                  const SizedBox(height: 30),

                  // Login Button
                  ElevatedButton(
                    onPressed: _isLoading ? null : _handleLogin,
                    child: _isLoading
                        ? const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(color: Colors.black, strokeWidth: 2.5),
                          )
                        : const Text('تسجيل الدخول'),
                  ),

                  if (_hasBiometrics) ...[
                    const SizedBox(height: 20),
                    Center(
                      child: Column(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.fingerprint, size: 52, color: AppTheme.gold),
                            tooltip: 'تسجيل الدخول بالبصمة',
                            onPressed: _isLoading ? null : _handleBiometricLogin,
                          ),
                          const SizedBox(height: 4),
                          const Text('تسجيل الدخول ببصمة الإصبع أو الوجه', style: TextStyle(color: AppTheme.goldLight, fontSize: 12)),
                        ],
                      ),
                    ),
                  ],

                  const SizedBox(height: 35),
                  const Text(
                    'في حال نسيت كلمة المرور، يرجى مراجعة إدارة الفرع أو خدمة العملاء المعتمدة لإعادة التعيين.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppTheme.textMuted, fontSize: 12),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
