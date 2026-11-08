import 'package:flutter/material.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../../../core/widgets/geosur_brand.dart';
import '../../../uikits/theme/uikits_theme.dart';
import '../../../uikits/widgets/pill_field.dart';
import '../model/auth_repository.dart';

/// Login al estilo UIKits SignUp 05: tipografía grande, campos píldora, CTA ancho.
class AuthScreen extends StatefulWidget {
  const AuthScreen({
    required this.auth,
    required this.onAuthenticated,
    this.initialNotice,
    super.key,
  });

  final AuthRepository auth;
  final ValueChanged<GeoSurUser> onAuthenticated;
  final String? initialNotice;

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _registering = false;
  bool _busy = false;
  bool _obscurePassword = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _error = widget.initialNotice;
    _prefillEmail();
  }

  Future<void> _prefillEmail() async {
    final remembered = await widget.auth.rememberedEmail();
    if (!mounted || remembered == null || remembered.isEmpty) return;
    _email.text = remembered;
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final user = _registering
          ? await widget.auth.register(
              name: _name.text.trim(),
              email: _email.text.trim(),
              password: _password.text,
              passwordConfirmation: _confirmation.text,
            )
          : await widget.auth.signIn(_email.text.trim(), _password.text);
      if (mounted) widget.onAuthenticated(user);
    } on GeoCruzApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on FormatException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'No se pudo conectar. Revisa el servidor e inténtalo de nuevo.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: GeoSurBrandSeed.canvas,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 430),
              child: Form(
                key: _formKey,
                child: Column(
                  children: [
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.fromLTRB(20, 22, 20, 22),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            scheme.primaryContainer,
                            scheme.secondaryContainer,
                          ],
                        ),
                        borderRadius: BorderRadius.circular(28),
                      ),
                      child: const GeoSurBrand(),
                    ),
                    const SizedBox(height: 28),
                    Text(
                      _registering ? 'Crear cuenta' : 'Iniciar sesión',
                      style: Theme.of(context).textTheme.headlineLarge
                          ?.copyWith(fontWeight: FontWeight.w800),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _registering
                          ? 'Tu cuenta nueva tendrá acceso de consulta.'
                          : 'Ingresa tu correo y contraseña de GeoSur.',
                      style: Theme.of(context).textTheme.bodyMedium,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 28),
                    if (_registering) ...[
                      UiKitPillField(
                        child: TextFormField(
                          controller: _name,
                          textAlign: TextAlign.center,
                          textCapitalization: TextCapitalization.words,
                          decoration: const InputDecoration(
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            hintText: 'Tu nombre',
                          ),
                          validator: (value) => (value?.trim().length ?? 0) < 2
                              ? 'Escribe tu nombre.'
                              : null,
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    UiKitPillField(
                      child: TextFormField(
                        controller: _email,
                        textAlign: TextAlign.center,
                        keyboardType: TextInputType.emailAddress,
                        autocorrect: false,
                        decoration: const InputDecoration(
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          hintText: 'Tu correo',
                        ),
                        validator: (value) =>
                            value == null || !value.contains('@')
                            ? 'Escribe un correo válido.'
                            : null,
                      ),
                    ),
                    const SizedBox(height: 12),
                    UiKitPillField(
                      child: TextFormField(
                        controller: _password,
                        textAlign: TextAlign.center,
                        obscureText: _obscurePassword,
                        decoration: InputDecoration(
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          hintText: 'Tu contraseña',
                          suffixIcon: IconButton(
                            onPressed: () => setState(
                              () => _obscurePassword = !_obscurePassword,
                            ),
                            icon: Icon(
                              _obscurePassword
                                  ? Icons.visibility_outlined
                                  : Icons.visibility_off_outlined,
                            ),
                          ),
                        ),
                        validator: (value) => (value?.length ?? 0) < 12
                            ? 'Usa al menos 12 caracteres.'
                            : null,
                      ),
                    ),
                    if (_registering) ...[
                      const SizedBox(height: 12),
                      UiKitPillField(
                        child: TextFormField(
                          controller: _confirmation,
                          textAlign: TextAlign.center,
                          obscureText: _obscurePassword,
                          decoration: const InputDecoration(
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            hintText: 'Repite la contraseña',
                          ),
                          validator: (value) => value != _password.text
                              ? 'Las contraseñas no coinciden.'
                              : null,
                        ),
                      ),
                    ],
                    if (_error != null) ...[
                      const SizedBox(height: 14),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: scheme.errorContainer,
                          borderRadius: BorderRadius.circular(18),
                        ),
                        child: Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: TextStyle(color: scheme.onErrorContainer),
                        ),
                      ),
                    ],
                    const SizedBox(height: 22),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: _busy ? null : _submit,
                        child: _busy
                            ? const SizedBox.square(
                                dimension: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Text(
                                _registering ? 'Crear cuenta' : 'Ingresar',
                              ),
                      ),
                    ),
                    TextButton(
                      onPressed: _busy
                          ? null
                          : () => setState(() {
                              _registering = !_registering;
                              _error = null;
                            }),
                      child: Text(
                        _registering
                            ? 'Ya tengo una cuenta'
                            : 'Crear una cuenta',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
