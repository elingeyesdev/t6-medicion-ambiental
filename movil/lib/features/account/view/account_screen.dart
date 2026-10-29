import 'package:flutter/material.dart';

import '../../../core/widgets/geosur_brand.dart';
import '../../auth/model/auth_repository.dart';
import '../../../core/network/geocruz_api_client.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({
    required this.auth,
    required this.user,
    required this.onSignOut,
    required this.onUserChanged,
    super.key,
  });

  final AuthRepository auth;
  final GeoSurUser user;
  final Future<void> Function() onSignOut;
  final ValueChanged<GeoSurUser> onUserChanged;

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  late final TextEditingController _name;
  bool _saving = false;
  String? _message;
  String? _error;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.user.name);
  }

  @override
  void didUpdateWidget(covariant AccountScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.user.name != widget.user.name) _name.text = widget.user.name;
  }

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _saveName() async {
    final name = _name.text.trim();
    if (name.length < 2) {
      setState(() => _error = 'Escribe un nombre de al menos 2 caracteres.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
      _message = null;
    });
    try {
      final user = await widget.auth.updateName(name);
      if (!mounted) return;
      widget.onUserChanged(user);
      setState(() => _message = 'Nombre actualizado.');
    } on GeoCruzApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'No se pudo actualizar el nombre.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const GeoSurBrand(compact: true)),
    body: ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
      children: [
        Text('Mi cuenta', style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: 6),
        Text(
          'Tu perfil de GeoSur',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 16),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _name,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Nombre'),
                ),
                const SizedBox(height: 12),
                InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Correo electrónico',
                  ),
                  child: Text(widget.user.email),
                ),
                const SizedBox(height: 12),
                InputDecorator(
                  decoration: const InputDecoration(labelText: 'Acceso'),
                  child: Text(widget.user.role),
                ),
                if (_message != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _message!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _saving ? null : _saveName,
                  child: _saving
                      ? const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Guardar cambios'),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: ListTile(
            leading: const Icon(Icons.security_outlined),
            title: const Text('Acceso protegido'),
            subtitle: const Text(
              'La sesión se guarda de forma segura en este dispositivo.',
            ),
          ),
        ),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: widget.onSignOut,
          icon: const Icon(Icons.logout),
          label: const Text('Cerrar sesión'),
        ),
      ],
    ),
  );
}
