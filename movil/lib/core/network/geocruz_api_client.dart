import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
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
    Duration? timeoutOverride,
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
        .get(
          uri,
          headers: const {
            'Accept': 'application/json',
            'Accept-Encoding': 'identity',
            'Connection': 'close',
          },
        )
        .timeout(timeoutOverride ?? timeout);

    Object? payload;
    try {
      final body = utf8.decode(response.bodyBytes);
      payload = jsonDecode(body);
    } on FormatException catch (error) {
      debugPrint(
        'Respuesta JSON inválida: HTTP ${response.statusCode}, '
        'bytes=${response.bodyBytes.length}, '
        'content-type=${response.headers['content-type']}, '
        'offset=${error.offset}, mensaje=${error.message}',
      );
      throw GeoCruzApiException(
        statusCode: response.statusCode,
        message: 'La API devolvió una respuesta que no es JSON válido.',
      );
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final error = payload is Map<String, dynamic> ? payload['error'] : null;
      final message = error is Map<String, dynamic> ? error['mensaje'] : null;
      final code = error is Map<String, dynamic> ? error['codigo'] : null;
      throw GeoCruzApiException(
        statusCode: response.statusCode,
        message: message is String
            ? message
            : 'La consulta no pudo completarse.',
        code: code is String ? code : null,
      );
    }

    return payload;
  }

  void close() => _client.close();
}

class GeoCruzApiException implements Exception {
  const GeoCruzApiException({
    required this.statusCode,
    required this.message,
    this.code,
  });

  final int statusCode;
  final String message;
  final String? code;

  @override
  String toString() => 'GeoCruzApiException($statusCode, $code): $message';
}
