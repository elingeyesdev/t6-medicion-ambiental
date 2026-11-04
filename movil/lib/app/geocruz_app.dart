import 'package:flutter/material.dart';

import '../core/network/geocruz_api_client.dart';
import '../core/widgets/units_scope.dart';
import '../features/account/model/account_repository.dart';
import '../features/account/model/units_controller.dart';
import '../features/auth/model/auth_repository.dart';
import '../features/auth/view/auth_screen.dart';
import '../features/home/view/home_shell.dart';
import '../uikits/uikits.dart';

class GeoCruzApp extends StatefulWidget {
  const GeoCruzApp({required this.apiClient, super.key});

  final GeoCruzApiClient apiClient;

  @override
  State<GeoCruzApp> createState() => _GeoCruzAppState();
}

class _GeoCruzAppState extends State<GeoCruzApp> {
  late final AuthRepository _auth;
  late final UnitsController _units;
  GeoSurUser? _user;
  bool _checkingSession = true;
  String? _sessionError;

  @override
  void initState() {
    super.initState();
    _auth = AuthRepository(api: widget.apiClient);
    _units = UnitsController(AccountRepository(widget.apiClient));
    _restoreSession();
  }

  @override
  void dispose() {
    _units.dispose();
    super.dispose();
  }

  Future<void> _restoreSession() async {
    try {
      final user = await _auth.restoreSession();
      if (!mounted) return;
      setState(() {
        _user = user;
        _checkingSession = false;
        // Sin aviso al arrancar: si no hay sesión válida, solo muestra el login.
        _sessionError = null;
      });
      if (user != null) await _units.load();
    } catch (_) {
      // Timeout/red: no asustar; deja entrar manualmente con el correo recordado.
      if (!mounted) return;
      setState(() {
        _checkingSession = false;
        _sessionError = null;
      });
    }
  }

  Future<void> _authenticated(GeoSurUser user) async {
    setState(() {
      _user = user;
      _sessionError = null;
    });
    await _units.load();
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
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'GeoSur',
      debugShowCheckedModeBanner: false,
      theme: UiKitsTheme.light(),
      home: _checkingSession
          ? const _SessionLoadingScreen()
          : _user == null
          ? AuthScreen(
              auth: _auth,
              initialNotice: _sessionError,
              onAuthenticated: _authenticated,
            )
          : UnitsScope(
              controller: _units,
              child: HomeShell(
                apiClient: widget.apiClient,
                auth: _auth,
                user: _user!,
                onSignOut: _signOut,
                onUnauthorized: _expireSession,
                onUserChanged: (user) => _authenticated(user),
              ),
            ),
    );
  }
}

class _SessionLoadingScreen extends StatelessWidget {
  const _SessionLoadingScreen();

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: CircularProgressIndicator()));
}
