import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/maps/map_base_styles.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../core/widgets/geosur_brand.dart';
import '../../../core/widgets/units_scope.dart';
import '../../../core/units/units_presentation.dart';
import '../../../uikits/uikits.dart';
import '../../auth/model/auth_repository.dart';
import '../model/account_preferences.dart';
import '../model/account_repository.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({
    required this.auth,
    required this.api,
    required this.user,
    required this.onSignOut,
    required this.onUserChanged,
    super.key,
  });

  final AuthRepository auth;
  final GeoCruzApiClient api;
  final GeoSurUser user;
  final Future<void> Function() onSignOut;
  final ValueChanged<GeoSurUser> onUserChanged;

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  late final AccountRepository _repository;
  late final TextEditingController _name;
  late final TextEditingController _whatsapp;
  late final TextEditingController _telegram;
  final MapController _mapController = MapController();

  AccountPreferences? _prefs;
  Map<String, String> _units = {};
  bool _loading = true;
  bool _savingProfile = false;
  bool _savingUnits = false;
  bool _savingPrefs = false;
  String? _error;
  String? _message;

  @override
  void initState() {
    super.initState();
    _repository = AccountRepository(widget.api);
    _name = TextEditingController(text: widget.user.name);
    _whatsapp = TextEditingController();
    _telegram = TextEditingController();
    _load();
  }

  @override
  void didUpdateWidget(covariant AccountScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.user.name != widget.user.name) {
      _name.text = widget.user.name;
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _whatsapp.dispose();
    _telegram.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final snapshot = await _repository.load();
      if (!mounted) return;
      final unitsController = UnitsScope.of(context);
      final defaults = UnitsPresentation(
        catalog: snapshot.catalog,
      ).defaults();
      final chosen = snapshot.preferences.units.isEmpty
          ? defaults
          : snapshot.preferences.units;
      unitsController.applySnapshot(
        catalog: snapshot.catalog,
        units: chosen,
      );
      setState(() {
        _prefs = snapshot.preferences;
        _units = Map<String, String>.from(chosen);
        _whatsapp.text = snapshot.preferences.whatsappDestination ?? '';
        _telegram.text = snapshot.preferences.telegramDestination ?? '';
        _loading = false;
      });
    } on GeoCruzApiException catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.message;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'No se pudo cargar tu cuenta.';
        });
      }
    }
  }

  Future<void> _saveName() async {
    final name = _name.text.trim();
    if (name.length < 2) {
      setState(() => _error = 'Escribe un nombre de al menos 2 caracteres.');
      return;
    }
    setState(() {
      _savingProfile = true;
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
      if (mounted) setState(() => _savingProfile = false);
    }
  }

  Future<void> _saveUnits() async {
    setState(() {
      _savingUnits = true;
      _error = null;
      _message = null;
    });
    try {
      final saved = await UnitsScope.of(context).saveUnits(_units);
      if (!mounted) return;
      setState(() {
        _units = saved;
        _message = 'Unidades guardadas. Se usan al mostrar lecturas.';
      });
    } on GeoCruzApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'No se pudieron guardar las unidades.');
    } finally {
      if (mounted) setState(() => _savingUnits = false);
    }
  }

  Future<void> _savePreferences() async {
    final current = _prefs;
    if (current == null) return;
    var next = current.copyWith(
      notifyEmail: current.notifyEmail,
      notifyWhatsapp: current.notifyWhatsapp,
      whatsappDestination: _whatsapp.text.trim().isEmpty
          ? null
          : _whatsapp.text.trim(),
      notifyTelegram: current.notifyTelegram,
      telegramDestination: _telegram.text.trim().isEmpty
          ? null
          : _telegram.text.trim(),
    );
    if (!next.hasLocation) {
      next = next.copyWith(
        clearLocation: true,
        notifyEmail: false,
        notifyWhatsapp: false,
        notifyTelegram: false,
      );
    }
    setState(() {
      _savingPrefs = true;
      _error = null;
      _message = null;
    });
    try {
      final saved = await _repository.savePreferences(next);
      if (!mounted) return;
      setState(() {
        _prefs = saved;
        _message = 'Preferencias guardadas.';
      });
    } on GeoCruzApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'No se pudieron guardar las preferencias.');
      }
    } finally {
      if (mounted) setState(() => _savingPrefs = false);
    }
  }

  void _setLocation(LatLng point) {
    final current = _prefs;
    if (current == null) return;
    setState(() {
      _prefs = current.copyWith(
        latitude: point.latitude,
        longitude: point.longitude,
      );
    });
  }

  void _clearLocation() {
    final current = _prefs;
    if (current == null) return;
    setState(() {
      _prefs = current.copyWith(
        clearLocation: true,
        notifyEmail: false,
        notifyWhatsapp: false,
        notifyTelegram: false,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final scopeUnits = UnitsScope.maybeOf(context)?.presentation;
    final catalog = scopeUnits?.catalog;
    // Vista previa con la selección local (antes de guardar).
    final previewUnits = catalog == null
        ? null
        : UnitsPresentation(catalog: catalog, preferences: _units);

    return Scaffold(
      appBar: AppBar(
        title: const GeoSurBrand(compact: true),
        actions: [
          IconButton(
            tooltip: 'Actualizar',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.only(bottom: 32),
                children: [
                  UiKitProfileHeader(
                    name: widget.user.name,
                    subtitle: widget.user.email,
                    roleLabel: widget.user.role,
                  ),
                  if (_error != null || _message != null)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      child: _StatusBanner(
                        message: _error ?? _message!,
                        isError: _error != null,
                      ),
                    ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                    child: UiKitSectionCard(
                      title: 'Perfil',
                      subtitle: 'Nombre visible en GeoSur',
                      icon: Icons.badge_outlined,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          TextFormField(
                            controller: _name,
                            textCapitalization: TextCapitalization.words,
                            decoration: const InputDecoration(
                              labelText: 'Nombre',
                              border: InputBorder.none,
                            ),
                          ),
                          const SizedBox(height: 8),
                          InputDecorator(
                            decoration: const InputDecoration(
                              labelText: 'Correo',
                              border: InputBorder.none,
                            ),
                            child: Text(widget.user.email),
                          ),
                          const SizedBox(height: 12),
                          FilledButton(
                            onPressed: _savingProfile ? null : _saveName,
                            child: _savingProfile
                                ? const SizedBox.square(
                                    dimension: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Text('Guardar nombre'),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                    child: UiKitSectionCard(
                      title: 'Unidades',
                      subtitle: 'Se aplican al mostrar lecturas del mapa.',
                      icon: Icons.straighten_outlined,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          if (catalog == null || catalog.groups.isEmpty)
                            const Text(
                              'No se pudo cargar el catálogo. Revisa la API.',
                            )
                          else
                            for (final group in catalog.groups.values) ...[
                              Text(
                                group.name,
                                style: Theme.of(context).textTheme.titleSmall,
                              ),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  for (final option in group.options.values)
                                    ChoiceChip(
                                      label: Text(option.name),
                                      selected:
                                          (_units[group.key] ??
                                              group.defaultCode) ==
                                          option.code,
                                      onSelected: (_) => setState(
                                        () => _units[group.key] = option.code,
                                      ),
                                    ),
                                ],
                              ),
                              if (group.key == 'temperatura' &&
                                  previewUnits != null) ...[
                                const SizedBox(height: 8),
                                Text(
                                  'Vista previa: ${previewUnits.format('temperatura', 0, '°C')} · ${previewUnits.format('temperatura', 25, '°C')}',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ],
                              const SizedBox(height: 14),
                            ],
                          FilledButton(
                            onPressed: _savingUnits ? null : _saveUnits,
                            child: _savingUnits
                                ? const SizedBox.square(
                                    dimension: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Text('Guardar unidades'),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                    child: UiKitSectionCard(
                      title: 'Ubicación',
                      subtitle: 'Toca el mapa para marcar tu punto.',
                      icon: Icons.place_outlined,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(16),
                            child: SizedBox(
                              height: 180,
                              child: FlutterMap(
                                mapController: _mapController,
                                options: MapOptions(
                                  initialCenter: LatLng(
                                    _prefs?.latitude ?? -17.7833,
                                    _prefs?.longitude ?? -63.1821,
                                  ),
                                  initialZoom: 11,
                                  onTap: (_, point) => _setLocation(point),
                                ),
                                children: [
                                  MapBaseStyle.claro.toLayer(),
                                  if (_prefs?.hasLocation == true)
                                    MarkerLayer(
                                      markers: [
                                        Marker(
                                          point: LatLng(
                                            _prefs!.latitude!,
                                            _prefs!.longitude!,
                                          ),
                                          width: 40,
                                          height: 40,
                                          child: Icon(
                                            Icons.location_on,
                                            color: Theme.of(
                                              context,
                                            ).colorScheme.primary,
                                            size: 36,
                                          ),
                                        ),
                                      ],
                                    ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            _prefs?.hasLocation == true
                                ? 'Lat ${_prefs!.latitude!.toStringAsFixed(5)} · Lon ${_prefs!.longitude!.toStringAsFixed(5)}'
                                : 'Sin ubicación guardada',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                          Align(
                            alignment: Alignment.centerLeft,
                            child: TextButton(
                              onPressed: _prefs?.hasLocation == true
                                  ? _clearLocation
                                  : null,
                              child: const Text('Quitar ubicación'),
                            ),
                          ),
                          SwitchListTile(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Avisos por correo'),
                            value: _prefs?.notifyEmail ?? false,
                            onChanged: _prefs?.hasLocation == true
                                ? (value) => setState(() {
                                    _prefs = _prefs!.copyWith(
                                      notifyEmail: value,
                                    );
                                  })
                                : null,
                          ),
                          SwitchListTile(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('WhatsApp'),
                            value: _prefs?.notifyWhatsapp ?? false,
                            onChanged: _prefs?.hasLocation == true
                                ? (value) => setState(() {
                                    _prefs = _prefs!.copyWith(
                                      notifyWhatsapp: value,
                                    );
                                  })
                                : null,
                          ),
                          if (_prefs?.notifyWhatsapp == true)
                            TextFormField(
                              controller: _whatsapp,
                              decoration: const InputDecoration(
                                labelText: 'Destino WhatsApp',
                                border: InputBorder.none,
                              ),
                            ),
                          SwitchListTile(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Telegram'),
                            value: _prefs?.notifyTelegram ?? false,
                            onChanged: _prefs?.hasLocation == true
                                ? (value) => setState(() {
                                    _prefs = _prefs!.copyWith(
                                      notifyTelegram: value,
                                    );
                                  })
                                : null,
                          ),
                          if (_prefs?.notifyTelegram == true)
                            TextFormField(
                              controller: _telegram,
                              decoration: const InputDecoration(
                                labelText: 'Destino Telegram',
                                border: InputBorder.none,
                              ),
                            ),
                          const SizedBox(height: 8),
                          FilledButton(
                            onPressed: _savingPrefs ? null : _savePreferences,
                            child: _savingPrefs
                                ? const SizedBox.square(
                                    dimension: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Text('Guardar preferencias'),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
                    child: OutlinedButton.icon(
                      onPressed: widget.onSignOut,
                      icon: const Icon(Icons.logout),
                      label: const Text('Cerrar sesión'),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}

class _StatusBanner extends StatelessWidget {
  const _StatusBanner({required this.message, required this.isError});

  final String message;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isError ? scheme.errorContainer : scheme.primaryContainer,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Text(
        message,
        style: TextStyle(
          color: isError ? scheme.onErrorContainer : scheme.onPrimaryContainer,
        ),
      ),
    );
  }
}
