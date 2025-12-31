import 'package:dio/dio.dart';

import '../models/personil.dart';
import 'api_client.dart';

class AuthResult {
  AuthResult({
    required this.token,
    required this.personil,
  });

  final String token;
  final Personil personil;
}

class AuthService {
  AuthService(this._apiClient);

  final ApiClient _apiClient;

  Future<AuthResult> login({
    required String noWa,
    required String password,
  }) async {
    final response = await _apiClient.post(
      '/auth/login',
      data: {
        'no_wa': noWa,
        'password': password,
      },
    );

    final data = response.data as Map<String, dynamic>;
    return AuthResult(
      token: data['token'] as String,
      personil: Personil.fromJson(data['personil'] as Map<String, dynamic>),
    );
  }

  Future<Personil> me() async {
    final response = await _apiClient.get('/auth/me');
    return Personil.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> logout() async {
    try {
      await _apiClient.post('/auth/logout');
    } on DioException {
      // Ignore logout failures to allow local sign-out.
    }
  }
}
