import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class GeoCruzApiClient {
  GeoCruzApiClient({
    required Uri baseUrl,
    http.Client? client,
    this.timeout = const Duration(seconds: 75),
  }) : _baseUrl = baseUrl,
       _client = client ?? http.Client();

  final Uri _baseUrl;
  final http.Client _client;
  final Duration timeout;

  String? bearerToken;

  Uri get baseUrl => _baseUrl;

  Future<Object?> getJson(
    String endpoint, {
    Map<String, String> queryParameters = const {},
    Duration? timeoutOverride,
    bool authenticated = false,
  }) async {
    return _requestJson(
      'GET',
      endpoint,
      queryParameters: queryParameters,
      timeoutOverride: timeoutOverride,
      authenticated: authenticated,
    );
  }

  Future<Object?> postJson(
    String endpoint, {
    Object? body,
    bool authenticated = false,
    Duration? timeoutOverride,
  }) => _requestJson(
    'POST',
    endpoint,
    body: body,
    authenticated: authenticated,
    timeoutOverride: timeoutOverride,
  );

  Future<Object?> putJson(
    String endpoint, {
    Object? body,
    bool authenticated = false,
    Duration? timeoutOverride,
  }) => _requestJson(
    'PUT',
    endpoint,
    body: body,
    authenticated: authenticated,
    timeoutOverride: timeoutOverride,
  );

  Future<Object?> _requestJson(
    String method,
    String endpoint, {
    Map<String, String> queryParameters = const {},
    Object? body,
    bool authenticated = false,
    Duration? timeoutOverride,
  }) async {
    final normalizedEndpoint = endpoint.replaceFirst(RegExp(r'^/+'), '');
    if (normalizedEndpoint.isEmpty ||
        normalizedEndpoint.contains('..') ||
        normalizedEndpoint.contains('://') ||
        normalizedEndpoint.startsWith(r'\\')) {
      throw ArgumentError.value(endpoint, 'endpoint', 'Ruta no válida.');
    }

    final headers = <String, String>{
      'Accept': 'application/json',
      'Accept-Encoding': 'identity',
    };
    if (body != null) headers['Content-Type'] = 'application/json';
    if (authenticated) {
      final token = bearerToken;
      if (token == null || token.isEmpty) {
        throw const GeoCruzApiException(
          statusCode: 401,
          message: 'Inicia sesión para consultar esta información.',
          code: 'NO_AUTORIZADO',
        );
      }
      headers['Authorization'] = 'Bearer $token';
    }
    final encodedBody = body != null ? jsonEncode(body) : jsonEncode(const <String, Object?>{});

    Future<http.Response> sendTo(Uri base, Duration requestTimeout) {
      final uri = base
          .resolve(normalizedEndpoint)
          .replace(
            queryParameters: queryParameters.isEmpty ? null : queryParameters,
          );
      final request = switch (method) {
        'GET' => _client.get(uri, headers: headers),
        'POST' => _client.post(uri, headers: headers, body: encodedBody),
        'PUT' => _client.put(uri, headers: headers, body: encodedBody),
        _ => throw ArgumentError.value(method, 'method', 'Método no admitido.'),
      };
      return request.timeout(requestTimeout);
    }

    final targetTimeout = timeoutOverride ?? timeout;
    final response = await sendTo(_baseUrl, targetTimeout);

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
