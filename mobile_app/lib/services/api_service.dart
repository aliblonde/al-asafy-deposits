import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  // Live Production Server URL
  static const String baseUrl = 'https://alasafygroup.xyz/public/api';

  static const String _tokenKey = 'auth_jwt_token';
  static const String _investorKey = 'cached_investor_data';

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

  // 2. Dashboard Overview
  static Future<Map<String, dynamic>> getDashboard() async {
    final url = Uri.parse('$baseUrl/dashboard.php');
    try {
      final headers = await _getHeaders();
      final res = await http.get(url, headers: headers);
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (e) {
      return {'success': false, 'message': 'فشل تحميل بيانات لوحة التحكم.'};
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
      return {'success': false, 'message': 'فشل تحميل الودائع.'};
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

  // 5. Transactions / Account Statement
  static Future<Map<String, dynamic>> getTransactions({int page = 1, String currency = ''}) async {
    var uri = '$baseUrl/transactions.php?page=$page';
    if (currency.isNotEmpty) {
      uri += '&currency=$currency';
    }
    final url = Uri.parse(uri);
    try {
      final headers = await _getHeaders();
      final res = await http.get(url, headers: headers);
      final data = jsonDecode(res.body) as Map<String, dynamic>;
      return data;
    } catch (e) {
      return {'success': false, 'message': 'فشل تحميل كشف الحساب.'};
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
}
