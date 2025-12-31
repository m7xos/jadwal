import 'package:flutter/foundation.dart';

import '../models/personil.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../services/push_notification_service.dart';
import '../services/storage_service.dart';

class AuthController extends ChangeNotifier {
  AuthController({
    required ApiClient apiClient,
    required StorageService storageService,
    required PushNotificationService pushNotificationService,
  })  : _apiClient = apiClient,
        _storageService = storageService,
        _authService = AuthService(apiClient),
        _pushNotificationService = pushNotificationService;

  final ApiClient _apiClient;
  final StorageService _storageService;
  final AuthService _authService;
  final PushNotificationService _pushNotificationService;

  Personil? _personil;
  bool _loading = false;
  String? _errorMessage;

  Personil? get personil => _personil;
  bool get isLoading => _loading;
  bool get isAuthenticated => _personil != null;
  String? get errorMessage => _errorMessage;

  Future<void> initialize() async {
    _loading = true;
    notifyListeners();

    final token = await _storageService.readToken();
    if (token != null && token.isNotEmpty) {
      _apiClient.setToken(token);
      try {
        _personil = await _authService.me();
        await _pushNotificationService.initialize();
        await _pushNotificationService.registerDeviceToken(_apiClient);
      } catch (_) {
        await _storageService.clearToken();
        _apiClient.setToken(null);
        _personil = null;
      }
    }

    _loading = false;
    notifyListeners();
  }

  Future<bool> login(String nip, String password) async {
    _setLoading(true);
    _errorMessage = null;
    notifyListeners();

    try {
      final result = await _authService.login(nip: nip, password: password);
      _apiClient.setToken(result.token);
      await _storageService.saveToken(result.token);
      _personil = result.personil;

      await _pushNotificationService.initialize();
      await _pushNotificationService.registerDeviceToken(_apiClient);

      _setLoading(false);
      return true;
    } catch (e) {
      _errorMessage = _resolveErrorMessage(e);
      _setLoading(false);
      return false;
    }
  }

  Future<void> logout() async {
    _setLoading(true);
    notifyListeners();

    await _authService.logout();
    await _storageService.clearToken();
    _apiClient.setToken(null);
    _personil = null;

    _setLoading(false);
    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  void _setLoading(bool value) {
    _loading = value;
  }

  String _resolveErrorMessage(Object error) {
    try {
      final dioError = error as dynamic;
      final response = dioError.response;
      if (response?.data is Map<String, dynamic>) {
        final message = response.data['message'] as String?;
        if (message != null && message.trim().isNotEmpty) {
          return message;
        }
      }
    } catch (_) {}

    return 'Username/password salah.';
  }
}
