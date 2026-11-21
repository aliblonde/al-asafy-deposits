import 'dart:developer' as developer;
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
    developer.log('Handling background message: ${message.messageId}', name: 'FCM');
  } catch (e) {
    developer.log('Background message error: $e', name: 'FCM');
  }
}

class FcmService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  static String? fcmToken;

  static Future<void> initialize() async {
    try {
      await Firebase.initializeApp();

      // Set background messaging handler
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      // Request notification permissions (required for Android 13+ and iOS)
      NotificationSettings settings = await _messaging.requestPermission(
        alert: true,
        announcement: false,
        badge: true,
        carPlay: false,
        criticalAlert: false,
        provisional: false,
        sound: true,
      );

      developer.log('Notification permission: ${settings.authorizationStatus}', name: 'FCM');

      // Get device FCM Token
      fcmToken = await _messaging.getToken();
      developer.log('FCM Device Token: $fcmToken', name: 'FCM');

      // Subscribe to general topics for group notifications
      await _messaging.subscribeToTopic('investors');
      await _messaging.subscribeToTopic('all');

      // Foreground message listener
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        developer.log('Foreground message received: ${message.notification?.title}', name: 'FCM');
      });

      // Token refresh listener
      _messaging.onTokenRefresh.listen((String newToken) {
        fcmToken = newToken;
        developer.log('FCM Token refreshed: $newToken', name: 'FCM');
      });
    } catch (e) {
      developer.log('FCM initialization error (non-fatal): $e', name: 'FCM');
    }
  }

  /// Subscribe to a specific investor's topic when logged in
  static Future<void> subscribeToInvestor(int investorId) async {
    try {
      await _messaging.subscribeToTopic('investor_$investorId');
    } catch (e) {
      developer.log('Error subscribing to investor topic: $e', name: 'FCM');
    }
  }

  /// Unsubscribe when logging out
  static Future<void> unsubscribeFromInvestor(int investorId) async {
    try {
      await _messaging.unsubscribeFromTopic('investor_$investorId');
    } catch (e) {
      developer.log('Error unsubscribing: $e', name: 'FCM');
    }
  }
}
