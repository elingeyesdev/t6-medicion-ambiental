import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/features/drought/model/drought_models.dart';
import 'package:geocruz_movil/features/drought/model/drought_repository.dart';
import 'package:geocruz_movil/features/drought/view/drought_screen.dart';
import 'package:geocruz_movil/features/hotspots/model/hotspot_models.dart';

import 'fixtures/drought_response.dart';

Future<void> advance(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
}

class _Repository extends DroughtRepository {
  _Repository(super.apiClient);

  final catalog = Completer<List<TerritoryOption>>();
  final queries = <Completer<DroughtResponse>>[];
  final filters = <DroughtFilters>[];

  @override
  Future<List<TerritoryOption>> loadTerritories() => catalog.future;

  @override
  Future<DroughtResponse> query(DroughtFilters value) {
    filters.add(value);
    final pending = Completer<DroughtResponse>();
    queries.add(pending);
    return pending.future;
  }
}

void main() {
  test('no acepta una respuesta rota o vacía como ausencia de sequía', () {
    for (final malformed in [<String, dynamic>{}, {...droughtResponse(), 'datos': []}, {...droughtResponse(), 'resumen': {}}]) {
      expect(() => DroughtResponse.fromJson(malformed), throwsFormatException);
    }
  });

  test('acepta centro sin municipio y conserva ceros y cobertura parcial', () {
    final response = DroughtResponse.fromJson(droughtResponse());
    expect(response.datos.single.municipioDelCentroCelda, isNull);
    expect(response.datos.single.spi.valor, 0);
    expect(response.datos.single.precipitacionAcumuladaMm, 0);
    expect(response.cobertura.esParcial, isTrue);
    for (final coordinates in [[], [0], [181, 0], [0, double.nan]]) {
      expect(() => DroughtSample.fromJson({...droughtSample(), 'coordenadaCelda': coordinates}), throwsFormatException);
    }
  });

  testWidgets('salir durante el catálogo no inicia una consulta ni actualiza una pantalla cerrada', (tester) async {
    final api = GeoCruzApiClient(baseUrl: Uri.parse('http://localhost/api/v1/'));
    addTearDown(api.close);
    final repository = _Repository(api);
    await tester.pumpWidget(MaterialApp(home: DroughtScreen(apiClient: api, repository: repository)));
    await tester.pumpWidget(const SizedBox.shrink());
    repository.catalog.complete([]);
    await advance(tester);
    expect(repository.queries, isEmpty);
    expect(tester.takeException(), isNull);
  });

  testWidgets('una respuesta tardía no reemplaza la consulta más reciente', (tester) async {
    final api = GeoCruzApiClient(baseUrl: Uri.parse('http://localhost/api/v1/'));
    addTearDown(api.close);
    final repository = _Repository(api)..catalog.complete([]);
    await tester.pumpWidget(MaterialApp(home: DroughtScreen(apiClient: api, repository: repository)));
    await advance(tester);
    await tester.tap(find.byTooltip('Recargar'));
    await advance(tester);
    expect(repository.queries.length, 2);
    repository.queries.last.completeError(const GeoCruzApiException(statusCode: 503, message: 'Consulta reciente'));
    await advance(tester);
    repository.queries.first.completeError(const GeoCruzApiException(statusCode: 503, message: 'Respuesta anterior'));
    await advance(tester);
    expect(find.text('Consulta reciente'), findsOneWidget);
    expect(find.text('Respuesta anterior'), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('cambiar provincia limpia el municipio, conserva el mes y muestra la cobertura', (tester) async {
    tester.view.physicalSize = const Size(900, 1500);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = GeoCruzApiClient(baseUrl: Uri.parse('http://localhost/api/v1/'));
    addTearDown(api.close);
    final repository = _Repository(api)..catalog.complete(const [
      TerritoryOption(identifier: 'a', name: 'Provincia A', type: 'provincia'),
      TerritoryOption(identifier: 'b', name: 'Provincia B', type: 'provincia'),
      TerritoryOption(identifier: 'a1', name: 'Municipio A', type: 'municipio', parentId: 'a'),
      TerritoryOption(identifier: 'b1', name: 'Municipio B', type: 'municipio', parentId: 'b'),
    ]);
    await tester.pumpWidget(MaterialApp(home: DroughtScreen(apiClient: api, repository: repository)));
    await advance(tester);
    repository.queries.single.complete(DroughtResponse.fromJson(droughtResponse()));
    await advance(tester);
    expect(find.textContaining('Cobertura parcial.'), findsOneWidget);
    await tester.tap(find.byTooltip('Filtros'));
    await advance(tester);
    await tester.enterText(find.byType(TextFormField), '2024-08');
    await tester.tap(find.byKey(const Key('drought-province')));
    await advance(tester);
    await tester.tap(find.text('Provincia A').last);
    await advance(tester);
    await tester.tap(find.byKey(const ValueKey('drought-municipality-a')));
    await advance(tester);
    await tester.tap(find.text('Municipio A').last);
    await advance(tester);
    await tester.tap(find.byKey(const Key('drought-province')));
    await advance(tester);
    await tester.tap(find.text('Provincia B').last);
    await advance(tester);
    expect(find.byKey(const ValueKey('drought-municipality-b')), findsOneWidget);
    expect(find.text('2024-08'), findsOneWidget);
    await tester.tap(find.text('Aplicar filtros'));
    await advance(tester);
    expect(repository.filters.last.provincia, 'b');
    expect(repository.filters.last.municipio, isNull);
    expect(repository.filters.last.mesFinal, '2024-08');
    await tester.pumpWidget(const SizedBox.shrink());
    repository.queries.last.completeError(const GeoCruzApiException(statusCode: 503, message: 'Ya cerrada'));
    await advance(tester);
    expect(tester.takeException(), isNull);
  });
}
