import 'package:flutter/foundation.dart';

import '../models/app_notification.dart';
import '../services/api_client.dart';

class NotificationsController extends ChangeNotifier {
  NotificationsController(this._apiClient);

  final ApiClient _apiClient;

  List<AppNotification> _items = [];
  bool _loading = false;
  int _unreadCount = 0;

  List<AppNotification> get items => _items;
  bool get isLoading => _loading;
  int get unreadCount => _unreadCount;

  Future<void> fetchNotifications() async {
    _loading = true;
    notifyListeners();

    final response = await _apiClient.get('/notifications');
    final payload = response.data as Map<String, dynamic>;
    final data = (payload['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();

    _items = data.map(AppNotification.fromJson).toList();
    _loading = false;
    notifyListeners();
  }

  Future<void> fetchUnreadCount() async {
    final response = await _apiClient.get('/notifications/unread-count');
    final payload = response.data as Map<String, dynamic>;
    _unreadCount = (payload['count'] as num?)?.toInt() ?? 0;
    notifyListeners();
  }

  Future<void> markAsRead(AppNotification notification) async {
    await _apiClient.post('/notifications/${notification.id}/read');
    await fetchNotifications();
    await fetchUnreadCount();
  }
}
