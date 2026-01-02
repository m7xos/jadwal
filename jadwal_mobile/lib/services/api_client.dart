import 'package:dio/dio.dart';

import '../config.dart';

class ApiClient {
  ApiClient() {
    _dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 20),
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          if (_token != null && _token!.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $_token';
          }
          return handler.next(options);
        },
      ),
    );
  }

  late final Dio _dio;
  String? _token;

  void setToken(String? token) {
    _token = token;
  }

  Future<Response<dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) {
    return _dio.get(path, queryParameters: queryParameters);
  }

  Future<Response<dynamic>> post(
    String path, {
    Map<String, dynamic>? data,
  }) {
    return _dio.post(path, data: data);
  }

  Future<Response<dynamic>> patch(
    String path, {
    Map<String, dynamic>? data,
  }) {
    return _dio.patch(path, data: data);
  }
}
