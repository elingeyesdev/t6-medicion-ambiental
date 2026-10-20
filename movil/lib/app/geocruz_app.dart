import 'package:flutter/material.dart';

import '../core/network/geocruz_api_client.dart';
import '../core/theme/geocruz_theme.dart';
import '../features/home/home_shell.dart';

class GeoCruzApp extends StatelessWidget {
  const GeoCruzApp({required this.apiClient, super.key});

  final GeoCruzApiClient apiClient;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'GeoCruz',
      debugShowCheckedModeBanner: false,
      theme: GeoCruzTheme.light(),
      home: HomeShell(apiClient: apiClient),
    );
  }
}
