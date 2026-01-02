import 'package:flutter/foundation.dart';

import '../models/layanan.dart';
import '../services/api_client.dart';

class LayananController extends ChangeNotifier {
  LayananController(this._apiClient);

  final ApiClient _apiClient;

  List<Layanan> _items = [];
  bool _loading = false;

  List<Layanan> get items => _items;
  bool get isLoading => _loading;

  Future<void> fetchLayanan() async {
    _loading = true;
    notifyListeners();

    final response = await _apiClient.get('/layanan', queryParameters: {
      'aktif': true,
    });
    final data = (response.data as List<dynamic>).cast<Map<String, dynamic>>();
    _items = data.map(Layanan.fromJson).toList();
    _loading = false;
    notifyListeners();
  }

  Future<Map<String, dynamic>> createRequest({
    required int layananId,
    required String namaPemohon,
    required String noWaPemohon,
  }) async {
    final response = await _apiClient.post('/layanan/register', data: {
      'layanan_publik_id': layananId,
      'nama_pemohon': namaPemohon,
      'no_wa_pemohon': noWaPemohon,
    });
    return response.data as Map<String, dynamic>;
  }
}
