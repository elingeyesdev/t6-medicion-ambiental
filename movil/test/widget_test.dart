import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geocruz_movil/core/network/geocruz_api_client.dart';
import 'package:geocruz_movil/core/theme/geocruz_theme.dart';
import 'package:geocruz_movil/features/consultation/consulta_context.dart';
import 'package:geocruz_movil/features/consultation/results_list_screen.dart';
import 'package:geocruz_movil/features/home/home_shell.dart';
import 'package:geocruz_movil/features/modules/ambient_module.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  group('navegación común GeoCruz', () {
    testWidgets('muestra los cinco módulos sin resultados inventados', (
      tester,
    ) async {
      await tester.pumpWidget(_app());
      await tester.tap(find.text('Módulos'));
      await tester.pumpAndSettle();

      for (final module in ambientModules) {
        await tester.scrollUntilVisible(
          find.text(module.name),
          240,
          scrollable: find.byType(Scrollable).first,
        );
        expect(find.text(module.name), findsOneWidget);
        expect(find.text(module.statusLabel), findsAtLeastNWidgets(1));
      }
      expect(find.textContaining('FRP'), findsNothing);
      expect(find.textContaining('2026-10-'), findsNothing);
    });

    testWidgets(
      'focos muestra Próximamente sin simular una consulta',
      (tester) async {
        await tester.pumpWidget(_app());
        await tester.tap(find.text('Módulos'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Focos de calor'));
        await tester.pumpAndSettle();

        expect(
          find.text('Próximamente'),
          findsOneWidget,
        );
        expect(find.textContaining('Móvil en preparación'), findsNothing);
        expect(
          find.textContaining('no presenta resultados de ejemplo'),
          findsOneWidget,
        );
        expect(find.textContaining('NASA FIRMS'), findsNothing);
      },
    );

    testWidgets('conserva territorio y período del listado al volver', (
      tester,
    ) async {
      final context = ConsultaContext(
        moduleId: 'focos-calor',
        territoryId: 'BO07',
        territoryName: 'Santa Cruz',
        from: DateTime.utc(2026, 10, 10),
        untilExclusive: DateTime.utc(2026, 10, 11),
      );
      await tester.pumpWidget(_resultsApp(context, const []));

      expect(find.text('Santa Cruz'), findsOneWidget);
      expect(find.text(expectedPeriod), findsOneWidget);
      expect(find.text('Próximamente'), findsOneWidget);
      expect(find.text('Sin resultados para estos filtros'), findsNothing);
    });

    testWidgets(
      'pasa territorio, período e ID real al detalle y conserva lista',
      (tester) async {
        final context = ConsultaContext(
          moduleId: 'focos-calor',
          territoryId: 'BO070101',
          territoryName: 'Municipio de prueba',
          from: DateTime.utc(2026, 10, 10),
          untilExclusive: DateTime.utc(2026, 10, 11),
        );
        const record = EnvironmentalRecord(
          identifier: 'registro-control-test',
          attributes: {'potenciaRadiativa': null},
          source: 'fuente-control-test',
          nature: 'observacion',
        );
        await tester.pumpWidget(
          _resultsApp(context, const [
            record,
          ], availability: ResultsAvailability.ready),
        );
        expect(find.text('Municipio de prueba'), findsOneWidget);
        await tester.scrollUntilVisible(
          find.text('registro-control-test'),
          250,
          scrollable: find.byType(Scrollable).first,
        );

        await tester.tap(find.text('registro-control-test'));
        await tester.pumpAndSettle();

        expect(find.byKey(const Key('record-identifier')), findsOneWidget);
        expect(find.text('Municipio de prueba'), findsOneWidget);
        expect(find.text(expectedPeriod), findsOneWidget);
        expect(find.text('registro-control-test'), findsAtLeastNWidgets(2));
        expect(find.text('Sin dato'), findsOneWidget);

        await tester.pageBack();
        await tester.pumpAndSettle();
        expect(find.text('Municipio de prueba'), findsOneWidget);
        expect(find.text('registro-control-test'), findsOneWidget);
      },
    );

    testWidgets('distingue resultado vacío de módulo no integrado', (
      tester,
    ) async {
      const context = ConsultaContext(
        moduleId: 'focos-calor',
        territoryId: 'BO07',
        territoryName: 'Santa Cruz',
      );
      await tester.pumpWidget(
        _resultsApp(context, const [], availability: ResultsAvailability.ready),
      );

      expect(find.text('Sin resultados para estos filtros'), findsOneWidget);
      expect(find.text('Próximamente'), findsNothing);
    });
  });
}

Widget _app() {
  return MaterialApp(
    theme: GeoCruzTheme.light(),
    home: HomeShell(
      apiClient: GeoCruzApiClient(
        baseUrl: Uri.parse('http://localhost/api/v1/'),
        client: MockClient((_) async => http.Response('{}', 200)),
      ),
    ),
  );
}

Widget _resultsApp(
  ConsultaContext context,
  List<EnvironmentalRecord> records, {
  ResultsAvailability availability = ResultsAvailability.notIntegrated,
}) {
  return MaterialApp(
    theme: GeoCruzTheme.light(),
    home: ResultsListScreen(
      module: ambientModules.first,
      consulta: context,
      records: records,
      availability: availability,
    ),
  );
}

const expectedPeriod = '09/10/2026 20:00 – 10/10/2026 20:00 (fin excluido)';
