import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import 'api_client.dart';

Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
}

class PushNotificationService {
  PushNotificationService();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  Future<void> initialize() async {
    if (Firebase.apps.isEmpty) {
      return;
    }

    await _messaging.requestPermission();

    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings = InitializationSettings(android: androidSettings);
    await _localNotifications.initialize(initSettings);

    const channel = AndroidNotificationChannel(
      'jadwal_default',
      'Notifikasi Jadwal',
      description: 'Notifikasi aplikasi Jadwal Watumalang',
      importance: Importance.high,
    );

    final androidPlugin = _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.createNotificationChannel(channel);

    FirebaseMessaging.onMessage.listen((message) {
      final notification = message.notification;
      if (notification == null) {
        return;
      }

      _localNotifications.show(
        notification.hashCode,
        notification.title,
        notification.body,
        const NotificationDetails(
          android: AndroidNotificationDetails(
            'jadwal_default',
            'Notifikasi Jadwal',
            importance: Importance.high,
            priority: Priority.high,
          ),
        ),
      );
    });
  }

  Future<void> registerDeviceToken(ApiClient apiClient) async {
    if (Firebase.apps.isEmpty) {
      return;
    }

    final token = await _messaging.getToken();
    if (token == null || token.isEmpty) {
      return;
    }

    try {
      await apiClient.post('/device-tokens', data: {
        'token': token,
        'platform': 'android',
      });
    } catch (_) {}

    _messaging.onTokenRefresh.listen((newToken) async {
      if (newToken.isEmpty) {
        return;
      }
      try {
        await apiClient.post('/device-tokens', data: {
          'token': newToken,
          'platform': 'android',
        });
      } catch (_) {}
    });
  }
}
