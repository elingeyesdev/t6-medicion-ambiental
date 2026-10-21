import 'package:flutter/material.dart';

import 'app/geocruz_app.dart';
import 'core/config/api_config.dart';
import 'core/network/geocruz_api_client.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    GeoCruzApp(
      apiClient: GeoCruzApiClient(
        baseUrl: ApiConfig.fromEnvironment(),
        timeout: const Duration(seconds: 75),
      ),
    ),
  );
}
