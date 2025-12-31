import 'package:flutter/foundation.dart';

import '../models/wa_inbox_message.dart';
import '../services/api_client.dart';

class WaInboxController extends ChangeNotifier {
  WaInboxController(this._apiClient);

  final ApiClient _apiClient;

  List<WaInboxMessage> _items = [];
  bool _loading = false;

  List<WaInboxMessage> get items => _items;
  bool get isLoading => _loading;

  Future<void> fetchMessages() async {
    _loading = true;
    notifyListeners();

    final response = await _apiClient.get('/wa-inbox');
    final payload = response.data as Map<String, dynamic>;
    final data = (payload['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();

    _items = data.map(WaInboxMessage.fromJson).toList();
    _loading = false;
    notifyListeners();
  }

  Future<void> replyMessage(int id, String reply) async {
    await _apiClient.post('/wa-inbox/$id/reply', data: {
      'reply_message': reply,
    });
    await fetchMessages();
  }
}
