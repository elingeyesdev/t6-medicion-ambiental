import 'package:flutter/material.dart';

import '../core/network/geocruz_api_client.dart';
import '../core/theme/geocruz_theme.dart';
import '../features/auth/model/auth_repository.dart';
import '../features/auth/view/auth_screen.dart';
import '../features/home/view/home_shell.dart';

class GeoCruzApp extends StatefulWidget {
  const GeoCruzApp({required this.apiClient, super.key});

  final GeoCruzApiClient apiClient;

  @override
  State<GeoCruzApp> createState() => _GeoCruzAppState();
}

class _GeoCruzAppState extends State<GeoCruzApp> {
  late final AuthRepository _auth;
  GeoSurUser? _user;
  bool _checkingSession = true;
  String? _sessionError;

  @override
  void initState() {
    super.initState();
    _auth = AuthRepository(api: widget.apiClient);
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    try {
      final user = await _auth.restoreSession();
      if (!mounted) return;
      setState(() {
        _user = user;
        _checkingSession = false;
        _sessionError = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _checkingSession = false;
        _sessionError =
            'No se pudo comprobar la sesión. Inicia sesión de nuevo.';
      });
    }
  }

  void _authenticated(GeoSurUser user) {
    setState(() {
      _user = user;
      _sessionError = null;
    });
  }

  Future<void> _expireSession() async {
    await _auth.clearSession();
    if (mounted) setState(() => _user = null);
  }

  Future<void> _signOut() async {
    try {
      await _auth.signOut();
    } catch (_) {
      await _auth.clearSession();
    }
    if (mounted) setState(() => _user = null);
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'GeoSur',
    debugShowCheckedModeBanner: false,
    theme: GeoCruzTheme.light(),
    home: _checkingSession
        ? const _SessionLoadingScreen()
        : _user == null
        ? AuthScreen(
            auth: _auth,
            initialNotice: _sessionError,
            onAuthenticated: _authenticated,
          )
        : HomeShell(
            apiClient: widget.apiClient,
            auth: _auth,
            user: _user!,
            onSignOut: _signOut,
            onUnauthorized: _expireSession,
            onUserChanged: _authenticated,
          ),
  );
}

class _SessionLoadingScreen extends StatelessWidget {
  const _SessionLoadingScreen();

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: CircularProgressIndicator()));
}
