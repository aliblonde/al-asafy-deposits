import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

class ApiService {
  // Live Production Server URL
  static const String baseUrl = 'https://alasafygroup.xyz/public/api';

  static const String _tokenKey = 'auth_jwt_token';
  static const String _investorKey = 'cached_investor_data';

  static const String _bioEnabledKey = 'bio_auth_enabled';
  static const String _bioUsernameKey = 'bio_username';
  static const String _bioPasswordKey = 'bio_password';

  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  static Future<void> saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
  }

  static Future<void> saveInvestorData(Map<String, dynamic> data) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_investorKey, jsonEncode(data));
  }

  static Future<Map<String, dynamic>?> getInvestorData() async {
    final prefs = await SharedPreferences.getInstance();
    final str = prefs.getString(_investorKey);
    if (str == null) return null;
    return jsonDecode(str) as Map<String, dynamic>;
  }

  static Future<void> clearAuth() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_investorKey);
  }

  // Biometrics Helper
  static Future<bool> isBiometricsEnabled() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_bioEnabledKey) ?? false;
  }

  static Future<void> setBiometricsEnabled(bool enabled) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_bioEnabledKey, enabled);
    if (!enabled) {
      await prefs.remove(_bioUsernameKey);
      await prefs.remove(_bioPasswordKey);
    }
  }

  static Future<void> saveBiometricCredentials(String username, String password) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_bioUsernameKey, username);
    await prefs.setString(_bioPasswordKey, password);
  }

  static Future<Map<String, String>?> getBiometricCredentials() async {
    final prefs = await SharedPreferences.getInstance();
    final u = prefs.getString(_bioUsernameKey);
    final p = prefs.getString(_bioPasswordKey);
    if (u != null && p != null && u.isNotEmpty && p.isNotEmpty) {
      return {'username': u, 'password': p};
    }
    return null;
  }

  // WhatsApp Support Helper
  static Future<bool> openWhatsAppSupport() async {
    final uri = Uri.parse('https://wa.me/9647731111861?text=${Uri.encodeComponent("مرحباً مجموعة العسافي، أود الاستفسار بخصوص حسابي الاستثماري.")}');
    if (await canLaunchUrl(uri)) {
      return await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
    return false;
  }

  static Future<Map<String, String>> _getHeaders() async {
    final token = await getToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  // 1. Login
  static Future<Map<String, dynamic>> login(String username, String password) async {
    final url = Uri.parse('$baseUrl/login.php');
    try {
      final res = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'username': username, 'password': password}),
      );

      final data = jsonDecode(res.body) as Map<String, dynamic>;
      if (res.statusCode == 200 && data['success'] == true) {
        final token = data['data']['token'] as String;
        final investor = data['data']['investor'] as Map<String, dynamic>;
        await saveToken(token);
        await saveInvestorData(investor);
        return {'success': true, 'message': data['message'], 'data': data['data']};
      } else {
        return {'success': false, 'message': data['message'] ?? 'فشل تسجيل الدخول.'};
      }
    } catch (e) {
      return {'success': false, 'message': 'تعذر الاتصال بالخادم. يرجى التحقق من اتصال الإنترنت.'};
    }
  }

  // 2. Investor Dashboard Overview
  static Future<Map<String, dynamic>> getDashboard() async {
    final url = Uri.parse('$baseUrl/dashboard.php');
    try {
      final headers = await _getHeaders();
      final res = await http.get(url, headers: headers);
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (e) {
      return {'success': false, 'message': 'تعذر تحميل بيانات لوحة الحساب.'};
    }
  }

  // 3. Deposits List
  static Future<Map<String, dynamic>> getDeposits() async {
    final url = Uri.parse('$baseUrl/deposits.php');
    try {
      final headers = await _getHeaders();
      final res = await http.get(url, headers: headers);
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (e) {
      return {'success': false, 'message': 'تعذر تحميل قائمة الودائع.'};
    }
  }

  // 4. Submit Withdraw Request
  static Future<Map<String, dynamic>> submitWithdrawRequest({
    required int depositId,
    required double amount,
    String note = '',
  }) async {
    final url = Uri.parse('$baseUrl/withdraw_request.php');
    try {
      final headers = await _getHeaders();
      final res = await http.post(
        url,
        headers: headers,
        body: jsonEncode({
          'deposit_id': depositId,
          'amount': amount,
          'note': note,
        }),
      );
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (e) {
      return {'success': false, 'message': 'فشل إرسال طلب السحب.'};
    }
  }

  // 5. Transactions Statement
  static Future<Map<String, dynamic>> getTransactions({String? currency, int page = 1}) async {
    var uriString = '$baseUrl/transactions.php?page=$page';
    if (currency != null && currency.isNotEmpty) {
      uriString += '&currency=$currency';
    }
    final url = Uri.parse(uriString);
    try {
      final headers = await _getHeaders();
      final res = await http.get(url, headers: headers);
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (e) {
      return {'success': false, 'message': 'تعذر تحميل كشف الحساب.'};
    }
  }

  // 6. Change Password
  static Future<Map<String, dynamic>> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    final url = Uri.parse('$baseUrl/change_password.php');
    try {
      final headers = await _getHeaders();
      final res = await http.post(
        url,
        headers: headers,
        body: jsonEncode({
          'current_password': currentPassword,
          'new_password': newPassword,
          'confirm_password': confirmPassword,
        }),
      );
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      if (data['success'] == true && data['data']?['token'] != null) {
        await saveToken(data['data']['token']);
      }
      return data;
    } catch (e) {
      return {'success': false, 'message': 'فشل تغيير كلمة المرور.'};
    }
  }

  // 7. Delete Account Request (Apple 5.1.1 & Google Compliance)
  static Future<Map<String, dynamic>> deleteAccount({
    required String password,
    String reason = '',
  }) async {
    final url = Uri.parse('$baseUrl/delete_account.php');
    try {
      final headers = await _getHeaders();
      final res = await http.post(
        url,
        headers: headers,
        body: jsonEncode({
          'password': password,
          'reason': reason,
        }),
      );
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (e) {
      return {'success': false, 'message': 'فشل إرسال طلب حذف الحساب.'};
    }
  }

  // 8. Notifications Center
  static Future<Map<String, dynamic>> getNotifications({int limit = 30}) async {
    final url = Uri.parse('$baseUrl/notifications.php?limit=$limit');
    try {
      final headers = await _getHeaders();
      final res = await http.get(url, headers: headers);
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (e) {
      return {'success': false, 'message': 'تعذر تحميل الإشعارات.'};
    }
  }

  static Future<bool> markNotificationRead(int notifId) async {
    final url = Uri.parse('$baseUrl/notifications.php');
    try {
      final headers = await _getHeaders();
      await http.post(
        url,
        headers: headers,
        body: jsonEncode({'action': 'mark_read', 'notif_id': notifId}),
      );
      return true;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> markAllNotificationsRead() async {
    final url = Uri.parse('$baseUrl/notifications.php');
    try {
      final headers = await _getHeaders();
      await http.post(
        url,
        headers: headers,
        body: jsonEncode({'action': 'mark_all_read'}),
      );
      return true;
    } catch (_) {
      return false;
    }
  }

  // App Version & Force Update Check
  static const String currentAppVersion = '1.0.0';

  static Future<Map<String, dynamic>> checkAppVersion() async {
    final url = Uri.parse('$baseUrl/app_version.php');
    try {
      final res = await http.get(url).timeout(const Duration(seconds: 6));
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (_) {
      return {'success': false};
    }
  }
}
