import 'dart:async';
import 'dart:io';
import 'package:flutter_map/flutter_map.dart';

/// Fondo transparente únicamente para estas pruebas de resumen y errores.
/// No depende de DNS ni modifica las respuestas API, que usa MockClient.
class TileHttpOverride extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) => _Client();
}

class _Client implements HttpClient {
  @override
  Future<HttpClientRequest> openUrl(String method, Uri url) async => _Request();
  @override
  void close({bool force = false}) {}
  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

class _Headers implements HttpHeaders {
  @override
  void forEach(void Function(String, List<String>) action) {
    action('content-type', ['image/png']);
  }
  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

class _Request implements HttpClientRequest {
  @override
  final HttpHeaders headers = _Headers();
  @override
  Future<void> addStream(Stream<List<int>> stream) async {
    await stream.drain<void>();
  }
  @override
  Future<HttpClientResponse> close() async => _Response();
  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

class _Response extends Stream<List<int>> implements HttpClientResponse {
  @override
  final HttpHeaders headers = _Headers();
  @override
  int get statusCode => 200;
  @override
  int get contentLength => TileProvider.transparentImage.length;
  @override
  bool get isRedirect => false;
  @override
  bool get persistentConnection => false;
  @override
  String get reasonPhrase => 'OK';
  @override
  List<RedirectInfo> get redirects => const [];
  @override
  HttpClientResponseCompressionState get compressionState =>
      HttpClientResponseCompressionState.notCompressed;
  @override
  StreamSubscription<List<int>> listen(void Function(List<int>)? onData,
          {Function? onError, void Function()? onDone, bool? cancelOnError}) =>
      Stream<List<int>>.value(TileProvider.transparentImage).listen(onData,
          onError: onError, onDone: onDone, cancelOnError: cancelOnError);
  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}
