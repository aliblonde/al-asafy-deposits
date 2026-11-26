import 'package:flutter/material.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import '../constants/theme.dart';
import '../services/api_service.dart';
import 'home_tab.dart';
import 'wallet_tab.dart';
import 'deposits_tab.dart';
import 'transactions_tab.dart';
import 'profile_tab.dart';
import 'notifications_sheet.dart';

class MainNavScreen extends StatefulWidget {
  const MainNavScreen({super.key});

  @override
  State<MainNavScreen> createState() => _MainNavScreenState();
}

class _MainNavScreenState extends State<MainNavScreen> {
  int _currentIndex = 0;

  final List<Widget> _tabs = const [
    HomeTab(),
    WalletTab(),
    DepositsTab(),
    TransactionsTab(),
    ProfileTab(),
  ];

  @override
  void initState() {
    super.initState();
    // Listen for foreground push notifications and show an in-app gold toast banner
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      if (message.notification != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: AppTheme.bgSurface,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
              side: const BorderSide(color: AppTheme.gold, width: 1.5),
            ),
            duration: const Duration(seconds: 5),
            action: SnackBarAction(
              label: 'عرض',
              textColor: AppTheme.gold,
              onPressed: () {
                showModalBottomSheet(
                  context: context,
                  isScrollControlled: true,
                  backgroundColor: Colors.transparent,
                  builder: (_) => const NotificationsSheet(),
                );
              },
            ),
            content: Row(
              children: [
                const Icon(Icons.notifications_active, color: AppTheme.gold),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        message.notification!.title ?? 'إشعار جديد',
                        style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.gold),
                      ),
                      if (message.notification!.body != null)
                        Text(
                          message.notification!.body!,
                          style: const TextStyle(fontSize: 12, color: Colors.white),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _tabs[_currentIndex],
      floatingActionButton: FloatingActionButton(
        backgroundColor: const Color(0xFF25D366),
        foregroundColor: Colors.white,
        tooltip: 'تواصل واتساب مع الدعم (07731111861)',
        onPressed: ApiService.openWhatsAppSupport,
        child: const Icon(Icons.chat, size: 26),
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => setState(() => _currentIndex = index),
        selectedItemColor: AppTheme.gold,
        unselectedItemColor: AppTheme.textMuted,
        backgroundColor: AppTheme.bgSurface,
        type: BottomNavigationBarType.fixed,
        selectedLabelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
        unselectedLabelStyle: const TextStyle(fontSize: 11),
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.dashboard_outlined),
            activeIcon: Icon(Icons.dashboard),
            label: 'الرئيسية',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.account_balance_wallet_outlined),
            activeIcon: Icon(Icons.account_balance_wallet),
            label: 'المحفظة',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.account_balance_outlined),
            activeIcon: Icon(Icons.account_balance),
            label: 'الودائع',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.receipt_long_outlined),
            activeIcon: Icon(Icons.receipt_long),
            label: 'كشف الحساب',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'حسابي',
          ),
        ],
      ),
    );
  }
}
