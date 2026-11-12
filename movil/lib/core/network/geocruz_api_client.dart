import 'dart:convert';
import 'dart:io' show gzip;

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

  /// Decodes large JSON responses on a background isolate and reports timing
  /// only in debug builds. Kept separate so existing API client overrides do
  /// not need a signature change.
  Future<Object?> getJsonWithProfiling(
    String endpoint, {
    Map<String, String> queryParameters = const {},
    Duration? timeoutOverride,
    bool authenticated = false,
    required String profileLabel,
  }) => _requestJson(
    'GET',
    endpoint,
    queryParameters: queryParameters,
    timeoutOverride: timeoutOverride,
    authenticated: authenticated,
    decodeLargeJsonOffMain: true,
    profileLabel: profileLabel,
  );

  /// Descarga bytes (p. ej. PNG de capas GFS) con el mismo token Sanctum.
  Future<Uint8List> getBytes(
    String endpoint, {
    Map<String, String> queryParameters = const {},
    Duration? timeoutOverride,
    bool authenticated = false,
  }) async {
    final bytes = await downloadBytes(
      endpoint,
      queryParameters: queryParameters,
      timeoutOverride: timeoutOverride,
      authenticated: authenticated,
      accept: 'image/png, application/json',
    );
    // Algunos proxies omiten content-type; se valida firma PNG.
    final png =
        bytes.length >= 8 &&
        bytes[0] == 0x89 &&
        bytes[1] == 0x50 &&
        bytes[2] == 0x4E &&
        bytes[3] == 0x47;
    if (!png) {
      throw const GeoCruzApiException(
        statusCode: 502,
        message: 'La respuesta no es una imagen PNG válida.',
      );
    }
    return bytes;
  }

  /// Descarga bytes genéricos (PDF, XLSX, etc.) con el mismo token Sanctum.
  Future<Uint8List> downloadBytes(
    String endpoint, {
    Map<String, String> queryParameters = const {},
    Duration? timeoutOverride,
    bool authenticated = false,
    String accept = '*/*',
  }) async {
    final normalizedEndpoint = endpoint.replaceFirst(RegExp(r'^/+'), '');
    if (normalizedEndpoint.isEmpty ||
        normalizedEndpoint.contains('..') ||
        normalizedEndpoint.contains('://') ||
        normalizedEndpoint.startsWith(r'\\')) {
      throw ArgumentError.value(endpoint, 'endpoint', 'Ruta no válida.');
    }
    final headers = <String, String>{
      'Accept': accept,
      'Accept-Encoding': 'identity',
    };
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
    final uri = _baseUrl
        .resolve(normalizedEndpoint)
        .replace(
          queryParameters: queryParameters.isEmpty ? null : queryParameters,
        );
    final response = await _client
        .get(uri, headers: headers)
        .timeout(timeoutOverride ?? timeout);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      Object? payload;
      try {
        payload = jsonDecode(utf8.decode(response.bodyBytes));
      } catch (_) {
        payload = null;
      }
      final error = payload is Map<String, dynamic> ? payload['error'] : null;
      final message = error is Map<String, dynamic> ? error['mensaje'] : null;
      final code = error is Map<String, dynamic> ? error['codigo'] : null;
      throw GeoCruzApiException(
        statusCode: response.statusCode,
        message: message is String
            ? message
            : 'No se pudo descargar el recurso.',
        code: code is String ? code : null,
      );
    }
    return response.bodyBytes;
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

  Future<Object?> patchJson(
    String endpoint, {
    Object? body,
    bool authenticated = false,
    Duration? timeoutOverride,
  }) => _requestJson(
    'PATCH',
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
    bool decodeLargeJsonOffMain = false,
    String? profileLabel,
  }) async {
    final normalizedEndpoint = endpoint.replaceFirst(RegExp(r'^/+'), '');
    if (normalizedEndpoint.isEmpty ||
        normalizedEndpoint.contains('..') ||
        normalizedEndpoint.contains('://') ||
        normalizedEndpoint.startsWith(r'\\')) {
      throw ArgumentError.value(endpoint, 'endpoint', 'Ruta no válida.');
    }

    final headers = <String, String>{'Accept': 'application/json'};
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
    final encodedBody = body != null
        ? jsonEncode(body)
        : jsonEncode(const <String, Object?>{});

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
        'PATCH' => _client.patch(uri, headers: headers, body: encodedBody),
        _ => throw ArgumentError.value(method, 'method', 'Método no admitido.'),
      };
      return request.timeout(requestTimeout);
    }

    final targetTimeout = timeoutOverride ?? timeout;
    final requestTimer = Stopwatch()..start();
    late final http.Response response;
    try {
      response = await sendTo(_baseUrl, targetTimeout);
    } catch (_) {
      requestTimer.stop();
      _logRequestTiming(
        profileLabel,
        requestTimer.elapsedMilliseconds,
        null,
        false,
      );
      rethrow;
    }
    requestTimer.stop();

    Object? payload;
    final decodeInBackground =
        decodeLargeJsonOffMain &&
        response.bodyBytes.length >= _backgroundDecodeThreshold;
    final decodeTimer = Stopwatch()..start();
    try {
      payload = await _decodeJsonBody(
        response,
        decodeInBackground: decodeInBackground,
      );
    } on FormatException catch (error) {
      decodeTimer.stop();
      _logRequestTiming(
        profileLabel,
        requestTimer.elapsedMilliseconds,
        decodeTimer.elapsedMilliseconds,
        decodeInBackground,
        bytes: response.bodyBytes.length,
      );
      debugPrint(
        'Respuesta JSON inválida: HTTP ${response.statusCode}, '
        'bytes=${response.bodyBytes.length}, '
        'content-type=${response.headers['content-type']}, '
        'offset=${error.offset}, mensaje=${error.message}',
      );
      final head = response.bodyBytes.isEmpty
          ? ''
          : utf8.decode(
              response.bodyBytes.sublist(
                0,
                response.bodyBytes.length < 80 ? response.bodyBytes.length : 80,
              ),
              allowMalformed: true,
            );
      final looksHtml =
          response.headers['content-type']?.contains('text/html') == true ||
          head.contains('<');
      throw GeoCruzApiException(
        statusCode: response.statusCode,
        message: looksHtml
            ? 'El servidor respondió HTML en vez de JSON (¿API caída o URL incorrecta?).'
            : 'La API devolvió una respuesta que no es JSON válido (HTTP ${response.statusCode}).',
      );
    }
    decodeTimer.stop();
    _logRequestTiming(
      profileLabel,
      requestTimer.elapsedMilliseconds,
      decodeTimer.elapsedMilliseconds,
      decodeInBackground,
      bytes: response.bodyBytes.length,
    );

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final error = payload is Map<String, dynamic> ? payload['error'] : null;
      final message = error is Map<String, dynamic> ? error['mensaje'] : null;
      final code = error is Map<String, dynamic> ? error['codigo'] : null;
      final campos = error is Map<String, dynamic> ? error['campos'] : null;
      throw GeoCruzApiException(
        statusCode: response.statusCode,
        message: _mensajeConCampos(
          message is String ? message : 'La consulta no pudo completarse.',
          campos,
        ),
        code: code is String ? code : null,
      );
    }

    return payload;
  }

  void close() => _client.close();
}

const _backgroundDecodeThreshold = 64 * 1024;

Future<Object?> _decodeJsonBody(
  http.Response response, {
  required bool decodeInBackground,
}) async {
  if (decodeInBackground) {
    final outcome = await compute(
      _decodeJsonBytesInBackground,
      response.bodyBytes,
      debugLabel: 'GeoCruz JSON grande',
    );
    if (outcome[0] != true) {
      throw FormatException(
        outcome[1] as String,
        null,
        outcome[2] as int?,
      );
    }
    return outcome[1];
  }
  final body = _prepareJsonBody(response.bodyBytes);
  if (body.isEmpty) throw const FormatException('cuerpo vacío');
  return _decodeJsonContent(body);
}

List<Object?> _decodeJsonBytesInBackground(Uint8List input) {
  try {
    final body = _prepareJsonBody(input);
    if (body.isEmpty) return const [false, 'cuerpo vacío', -1];
    return [true, _decodeJsonContent(body), -1];
  } on FormatException catch (error) {
    return [false, error.message, error.offset ?? -1];
  }
}

String _prepareJsonBody(Uint8List input) {
  var bytes = input;
  // Algunos stacks Android entregan gzip sin Content-Encoding usable.
  if (bytes.length >= 2 && bytes[0] == 0x1f && bytes[1] == 0x8b) {
    try {
      bytes = Uint8List.fromList(gzip.decode(bytes));
    } catch (_) {
      // Sigue con el cuerpo original; el error de JSON será más claro.
    }
  }
  var body = utf8.decode(bytes, allowMalformed: true);
  if (body.isNotEmpty && body.codeUnitAt(0) == 0xFEFF) {
    body = body.substring(1);
  }
  body = body.trimLeft();
  // Proxies/errores PHP a veces anteponen HTML o avisos al JSON.
  if (body.isNotEmpty && body[0] != '{' && body[0] != '[') {
    final objectStart = body.indexOf('{');
    final arrayStart = body.indexOf('[');
    final start = switch ((objectStart, arrayStart)) {
      (-1, -1) => -1,
      (-1, final a) => a,
      (final o, -1) => o,
      (final o, final a) => o < a ? o : a,
    };
    if (start >= 0) body = body.substring(start);
  }
  return body;
}

Object? _decodeJsonContent(String body) {
  try {
    return jsonDecode(body);
  } on FormatException {
    // Cuerpo truncado o basura al final: recorta al último cierre plausible.
    final endObject = body.lastIndexOf('}');
    final endArray = body.lastIndexOf(']');
    final end = endObject > endArray ? endObject : endArray;
    if (end > 0) return jsonDecode(body.substring(0, end + 1));
    rethrow;
  }
}

void _logRequestTiming(
  String? label,
  int requestMs,
  int? decodeMs,
  bool backgroundDecode, {
  int? bytes,
}) {
  if (!kDebugMode || label == null) return;
  debugPrint(
    '[rendimiento][$label] red=${requestMs}ms '
    'json=${decodeMs == null ? 'sin respuesta' : '${decodeMs}ms'} '
    'bytes=${bytes ?? 0} isolate=${backgroundDecode ? 'sí' : 'no'}',
  );
}

String _mensajeConCampos(String mensaje, Object? campos) {
  if (campos is! Map || campos.isEmpty) return mensaje;
  final detalles = <String>[];
  for (final entry in campos.entries) {
    final valor = entry.value;
    if (valor is List) {
      for (final item in valor) {
        if (item is String && item.trim().isNotEmpty) detalles.add(item.trim());
      }
    } else if (valor is String && valor.trim().isNotEmpty) {
      detalles.add(valor.trim());
    }
  }
  if (detalles.isEmpty) return mensaje;
  // Evita repetir el genérico "Revisa los datos…" si ya hay el motivo real.
  if (mensaje == 'Revisa los datos del formulario.' ||
      mensaje == 'La consulta no pudo completarse.') {
    return detalles.first;
  }
  return '$mensaje ${detalles.first}';
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
