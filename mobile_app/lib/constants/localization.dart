import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AppLocale {
  static const String _prefLangKey = 'app_language_code';
  static final ValueNotifier<Locale> localeNotifier = ValueNotifier<Locale>(const Locale('ar', 'SA'));

  static bool get isArabic => localeNotifier.value.languageCode == 'ar';

  static Future<void> initLocale() async {
    final prefs = await SharedPreferences.getInstance();
    final code = prefs.getString(_prefLangKey) ?? 'ar';
    localeNotifier.value = code == 'en' ? const Locale('en', 'US') : const Locale('ar', 'SA');
  }

  static Future<void> toggleLanguage() async {
    final newCode = isArabic ? 'en' : 'ar';
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefLangKey, newCode);
    localeNotifier.value = newCode == 'en' ? const Locale('en', 'US') : const Locale('ar', 'SA');
  }

  static String text(String key) {
    final lang = localeNotifier.value.languageCode;
    return _translations[key]?[lang] ?? _translations[key]?['ar'] ?? key;
  }

  static final Map<String, Map<String, String>> _translations = {
    'app_title': {'ar': 'العسافي للاستثمارات', 'en': 'AL-ASAFY INVESTMENTS'},
    'home_tab': {'ar': 'الرئيسية', 'en': 'Dashboard'},
    'deposits_tab': {'ar': 'الودائع', 'en': 'Deposits'},
    'tx_tab': {'ar': 'كشف الحساب', 'en': 'Transactions'},
    'profile_tab': {'ar': 'الملف الشخصي', 'en': 'Profile'},
    'notifications': {'ar': 'الإشعارات', 'en': 'Notifications'},
    'whatsapp': {'ar': 'تواصل واتساب مع الدعم', 'en': 'WhatsApp Support'},
    'export_pdf': {'ar': 'تصدير كشف حساب PDF', 'en': 'Export PDF Statement'},
    'pending_review': {'ar': 'طلبات السحب قيد المراجعة', 'en': 'Pending Withdrawal Requests'},
    'active_deposits': {'ar': 'الودائع النشطة', 'en': 'Active Deposits'},
    'recent_tx': {'ar': 'آخر الحركات المالية', 'en': 'Recent Transactions'},
    'withdraw_btn': {'ar': 'طلب سحب أرباح', 'en': 'Withdraw Profit'},
    'profit_due': {'ar': 'أرباح مستحقة للصرف الآن', 'en': 'Profits Due for Payout Now'},
    'next_cycle': {'ar': 'أقرب استحقاق صرف دوري', 'en': 'Next Periodic Payout Date'},
    'total_balance': {'ar': 'إجمالي الرصيد الاستثماري', 'en': 'Total Investment Balance'},
    'total_profit': {'ar': 'الأرباح المتراكمة', 'en': 'Accumulated Profits'},
    'dark_mode': {'ar': 'الوضع الليلي والنهاري', 'en': 'Dark / Light Theme'},
    'biometrics': {'ar': 'تسجيل الدخول بالبصمة', 'en': 'Biometric Authentication'},
    'privacy_mode': {'ar': 'حماية الخصوصية (إخفاء الشاشة)', 'en': 'Privacy Mode (Hide Screen in Apps)'},
    'language': {'ar': 'اللغة (Language)', 'en': 'Language (اللغة)'},
    'current_lang': {'ar': 'العربية', 'en': 'English'},
    'change_password': {'ar': 'تغيير كلمة المرور', 'en': 'Change Password'},
    'logout': {'ar': 'تسجيل الخروج من الحساب', 'en': 'Sign Out'},
    'offline_title': {'ar': 'لا يوجد اتصال بالإنترنت', 'en': 'No Internet Connection'},
    'offline_desc': {'ar': 'أنت غير متصل حالياً، يرجى فحص الشبكة', 'en': 'You are offline, check your network'},
    'monthly_growth': {'ar': 'مؤشر نمو الأرباح الشهرية', 'en': 'Monthly Profit Growth Index'},
  };
}

String tr(String key) => AppLocale.text(key);
