import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';

import '../models/kegiatan.dart';
import '../services/api_client.dart';

class AgendaController extends ChangeNotifier {
  AgendaController(this._apiClient);

  final ApiClient _apiClient;

  List<Kegiatan> _items = [];
  bool _loading = false;

  List<Kegiatan> get items => _items;
  bool get isLoading => _loading;

  Future<void> fetchAgenda({
    DateTime? date,
    bool? belumDisposisi,
  }) async {
    _loading = true;
    notifyListeners();

    final queryParameters = <String, dynamic>{};
    if (date != null) {
      final dateString = DateFormat('yyyy-MM-dd').format(date);
      queryParameters['tanggal_mulai'] = dateString;
      queryParameters['tanggal_selesai'] = dateString;
    }
    if (belumDisposisi != null) {
      queryParameters['belum_disposisi'] = belumDisposisi;
    }

    final response = await _apiClient.get(
      '/kegiatan',
      queryParameters: queryParameters.isEmpty ? null : queryParameters,
    );
    final payload = response.data as Map<String, dynamic>;
    final data = (payload['data'] as List<dynamic>? ?? [])
        .cast<Map<String, dynamic>>();

    _items = data.map(Kegiatan.fromJson).toList();
    _loading = false;
    notifyListeners();
  }
}
