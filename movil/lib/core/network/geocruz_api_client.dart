import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

class GeoCruzApiClient {
  GeoCruzApiClient({
    required Uri baseUrl,
    http.Client? client,
    this.timeout = const Duration(seconds: 20),
  }) : _baseUrl = baseUrl,
       _client = client ?? http.Client();

  final Uri _baseUrl;
  final http.Client _client;
  final Duration timeout;

  Uri get baseUrl => _baseUrl;

  Future<Object?> getJson(
    String endpoint, {
    Map<String, String> queryParameters = const {},
  }) async {
    final normalizedEndpoint = endpoint.replaceFirst(RegExp(r'^/+'), '');
    if (normalizedEndpoint.isEmpty ||
        normalizedEndpoint.contains('..') ||
        normalizedEndpoint.contains('://') ||
        normalizedEndpoint.startsWith(r'\\')) {
      throw ArgumentError.value(endpoint, 'endpoint', 'Ruta no válida.');
    }

    final uri = _baseUrl
        .resolve(normalizedEndpoint)
        .replace(
          queryParameters: queryParameters.isEmpty ? null : queryParameters,
        );
    final response = await _client
        .get(uri, headers: const {'Accept': 'application/json'})
        .timeout(timeout);

    Object? payload;
    try {
      payload = jsonDecode(utf8.decode(response.bodyBytes));
    } on FormatException {
      throw GeoCruzApiException(
        statusCode: response.statusCode,
        message: 'La API devolvió una respuesta que no es JSON válido.',
      );
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final error = payload is Map<String, dynamic> ? payload['error'] : null;
      final message = error is Map<String, dynamic> ? error['mensaje'] : null;
      throw GeoCruzApiException(
        statusCode: response.statusCode,
        message: message is String
            ? message
            : 'La consulta no pudo completarse.',
      );
    }

    return payload;
  }

  void close() => _client.close();
}

class GeoCruzApiException implements Exception {
  const GeoCruzApiException({required this.statusCode, required this.message});

  final int statusCode;
  final String message;

  @override
  String toString() => 'GeoCruzApiException($statusCode): $message';
}
