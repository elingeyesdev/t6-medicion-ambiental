import 'dart:io';
import 'package:flutter_test/flutter_test.dart' show setUp, tearDown;
import 'package:integration_test/integration_test.dart';
import '../test/mov09_summary_states_test.dart' as scenarios;
import 'tile_http_override.dart';

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();
  HttpOverrides? previous;
  setUp(() {
    previous = HttpOverrides.current;
    HttpOverrides.global = TileHttpOverride();
  });
  tearDown(() => HttpOverrides.global = previous);
  scenarios.main();
}
