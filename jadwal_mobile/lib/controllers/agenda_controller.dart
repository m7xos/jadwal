import 'package:flutter/foundation.dart';

import '../models/kegiatan.dart';
import '../services/api_client.dart';

class AgendaController extends ChangeNotifier {
  AgendaController(this._apiClient);

  final ApiClient _apiClient;

  List<Kegiatan> _items = [];
  bool _loading = false;

  List<Kegiatan> get items => _items;
  bool get isLoading => _loading;

  Future<void> fetchAgenda({bool pendingOnly = false}) async {
    _loading = true;
    notifyListeners();

    final response = await _apiClient.get(
      '/kegiatan',
      queryParameters: pendingOnly ? {'belum_disposisi': true} : null,
    );
    final payload = response.data as Map<String, dynamic>;
    final data = (payload['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();

    _items = data.map(Kegiatan.fromJson).toList();
    _loading = false;
    notifyListeners();
  }
}
