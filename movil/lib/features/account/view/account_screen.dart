import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/maps/map_base_styles.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../core/widgets/geosur_brand.dart';
import '../../../core/widgets/units_scope.dart';
import '../../../core/units/units_presentation.dart';
import '../../../uikits/uikits.dart';
import '../../alerts/view/alerts_screen.dart';
import '../../auth/model/auth_repository.dart';
import '../../reports/view/reports_screen.dart';
import '../../simulations/view/simulations_screen.dart';
import '../model/account_preferences.dart';
import '../model/account_repository.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({
    required this.auth,
    required this.api,
    required this.user,
    required this.onSignOut,
    required this.onUnauthorized,
    required this.onUserChanged,
    super.key,
  });

  final AuthRepository auth;
  final GeoCruzApiClient api;
  final GeoSurUser user;
  final Future<void> Function() onSignOut;
  final VoidCallback onUnauthorized;
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
        _message = 'Unidades guardadas.';
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
    final whatsapp = _whatsapp.text.trim();
    final telegram = _telegram.text.trim();
    var next = current.copyWith(
      notifyEmail: current.notifyEmail,
      notifyWhatsapp: current.notifyWhatsapp,
      whatsappDestination: whatsapp.isEmpty ? null : whatsapp,
      clearWhatsappDestination: whatsapp.isEmpty,
      notifyTelegram: current.notifyTelegram,
      telegramDestination: telegram.isEmpty ? null : telegram,
      clearTelegramDestination: telegram.isEmpty,
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
    final scheme = Theme.of(context).colorScheme;
    final scopeUnits = UnitsScope.maybeOf(context)?.presentation;
    final catalog = scopeUnits?.catalog;
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
                padding: EdgeInsets.zero,
                children: [
                  UiKitProfileHeader(
                    name: widget.user.name,
                    subtitle: widget.user.email,
                    roleLabel: widget.user.role,
                  ),
                  // Accesos rápidos estilo ProfileScreen03 (BlockText).
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 8, 8, 0),
                    child: Row(
                      children: [
                        Expanded(
                          child: _QuickStat(
                            title: 'Simulaciones',
                            value: widget.user.canManageSimulations
                                ? 'Abrir'
                                : 'Bloq.',
                            icon: Icons.science_outlined,
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => SimulationsScreen(
                                    api: widget.api,
                                    user: widget.user,
                                    onUnauthorized: widget.onUnauthorized,
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                        Expanded(
                          child: _QuickStat(
                            title: 'Reportes',
                            value: widget.user.canViewReports ? 'Abrir' : 'Bloq.',
                            icon: Icons.description_outlined,
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => ReportsScreen(
                                    api: widget.api,
                                    user: widget.user,
                                    onUnauthorized: widget.onUnauthorized,
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                        Expanded(
                          child: _QuickStat(
                            title: 'Alertas',
                            value: 'Abrir',
                            icon: Icons.notifications_outlined,
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => AlertsScreen(
                                    api: widget.api,
                                    user: widget.user,
                                    onUnauthorized: widget.onUnauthorized,
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (_error != null || _message != null)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      child: UiKitSectionCard(
                        title: _error != null ? 'Atención' : 'Listo',
                        icon: _error != null
                            ? Icons.error_outline
                            : Icons.check_circle_outline,
                        child: Text(_error ?? _message!),
                      ),
                    ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                    child: Card(
                      // Sin clip: el label flotante del campo Nombre no se corta.
                      clipBehavior: Clip.none,
                      child: Column(
                        children: [
                          _AccountPanel(
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
                                    floatingLabelBehavior:
                                        FloatingLabelBehavior.auto,
                                    contentPadding: EdgeInsets.fromLTRB(
                                      18,
                                      18,
                                      18,
                                      16,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 10),
                                InputDecorator(
                                  decoration: const InputDecoration(
                                    labelText: 'Correo',
                                    contentPadding: EdgeInsets.fromLTRB(
                                      18,
                                      18,
                                      18,
                                      16,
                                    ),
                                  ),
                                  child: Text(widget.user.email),
                                ),
                                const SizedBox(height: 12),
                                FilledButton(
                                  onPressed:
                                      _savingProfile ? null : _saveName,
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
                          Divider(
                            height: 1,
                            color: scheme.outlineVariant.withValues(alpha: 0.5),
                          ),
                          _AccountPanel(
                            title: 'Unidades',
                            subtitle: 'Cómo se muestran las lecturas',
                            icon: Icons.straighten_outlined,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                if (catalog == null || catalog.groups.isEmpty)
                                  const Text(
                                    'No se pudo cargar el catálogo. Revisa la API.',
                                  )
                                else
                                  for (final group
                                      in catalog.groups.values) ...[
                                    Text(
                                      group.name,
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleSmall,
                                    ),
                                    const SizedBox(height: 8),
                                    Builder(
                                      builder: (context) {
                                        final codes = group.options.keys
                                            .toList(growable: false);
                                        if (codes.isEmpty) {
                                          return const SizedBox.shrink();
                                        }
                                        final raw = _units[group.key] ??
                                            group.defaultCode;
                                        final selected = codes.contains(raw)
                                            ? raw
                                            : codes.first;
                                        return SegmentedButton<String>(
                                          segments: [
                                            for (final option
                                                in group.options.values)
                                              ButtonSegment(
                                                value: option.code,
                                                label: Text(
                                                  option.symbol.isEmpty
                                                      ? option.name
                                                      : option.symbol,
                                                  overflow:
                                                      TextOverflow.ellipsis,
                                                ),
                                                tooltip: option.name,
                                              ),
                                          ],
                                          selected: {selected},
                                          emptySelectionAllowed: false,
                                          onSelectionChanged: (next) {
                                            if (next.isEmpty) return;
                                            setState(
                                              () => _units[group.key] =
                                                  next.first,
                                            );
                                          },
                                          style: ButtonStyle(
                                            visualDensity:
                                                VisualDensity.compact,
                                            foregroundColor:
                                                WidgetStateProperty
                                                    .resolveWith((states) {
                                              if (states.contains(
                                                WidgetState.selected,
                                              )) {
                                                return scheme.onSurface;
                                              }
                                              return scheme.onSurfaceVariant;
                                            }),
                                            backgroundColor:
                                                WidgetStateProperty
                                                    .resolveWith((states) {
                                              if (states.contains(
                                                WidgetState.selected,
                                              )) {
                                                return scheme
                                                    .surfaceContainerHighest;
                                              }
                                              return scheme.surface;
                                            }),
                                          ),
                                          showSelectedIcon: false,
                                        );
                                      },
                                    ),
                                    if (group.key == 'temperatura' &&
                                        previewUnits != null) ...[
                                      const SizedBox(height: 8),
                                      Text(
                                        'Vista previa: ${previewUnits.format('temperatura', 0, '°C')} · ${previewUnits.format('temperatura', 25, '°C')}',
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall,
                                      ),
                                    ],
                                    const SizedBox(height: 14),
                                  ],
                                FilledButton(
                                  onPressed:
                                      _savingUnits ? null : _saveUnits,
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
                          Divider(
                            height: 1,
                            color: scheme.outlineVariant.withValues(alpha: 0.5),
                          ),
                          _AccountLocationPanel(
                            mapController: _mapController,
                            prefs: _prefs,
                            whatsapp: _whatsapp,
                            telegram: _telegram,
                            savingPrefs: _savingPrefs,
                            onTapMap: _setLocation,
                            onClearLocation: _clearLocation,
                            onPrefsChanged: (next) =>
                                setState(() => _prefs = next),
                            onSavePreferences: _savePreferences,
                          ),
                        ],
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
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

class _QuickStat extends StatelessWidget {
  const _QuickStat({
    required this.title,
    required this.value,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String value;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        child: Column(
          children: [
            Icon(icon, color: scheme.primary),
            const SizedBox(height: 6),
            Text(value, style: Theme.of(context).textTheme.titleMedium),
            Text(title, style: Theme.of(context).textTheme.labelMedium),
          ],
        ),
      ),
    );
  }
}

class _AccountPanel extends StatelessWidget {
  const _AccountPanel({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.child,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        // Siempre cerrado al entrar; sin setState al expandir (evita assert de framework).
        initiallyExpanded: false,
        maintainState: true,
        leading: Icon(icon, color: scheme.primary),
        title: Text(title),
        subtitle: Text(subtitle),
        // Top > 0: el label flotante del OutlineInput no queda cortado.
        childrenPadding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
        children: [child],
      ),
    );
  }
}

/// Mapa de ubicación solo mientras el panel está abierto (evita Placeholder rojo
/// al colapsar y hacer scroll con teselas aún vivas).
class _AccountLocationPanel extends StatefulWidget {
  const _AccountLocationPanel({
    required this.mapController,
    required this.prefs,
    required this.whatsapp,
    required this.telegram,
    required this.savingPrefs,
    required this.onTapMap,
    required this.onClearLocation,
    required this.onPrefsChanged,
    required this.onSavePreferences,
  });

  final MapController mapController;
  final AccountPreferences? prefs;
  final TextEditingController whatsapp;
  final TextEditingController telegram;
  final bool savingPrefs;
  final void Function(LatLng point) onTapMap;
  final VoidCallback onClearLocation;
  final ValueChanged<AccountPreferences> onPrefsChanged;
  final VoidCallback onSavePreferences;

  @override
  State<_AccountLocationPanel> createState() => _AccountLocationPanelState();
}

class _AccountLocationPanelState extends State<_AccountLocationPanel> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final prefs = widget.prefs;
    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        initiallyExpanded: false,
        maintainState: false,
        onExpansionChanged: (open) => setState(() => _expanded = open),
        leading: Icon(Icons.place_outlined, color: scheme.primary),
        title: const Text('Ubicación y avisos'),
        subtitle: const Text('Punto para notificaciones'),
        childrenPadding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: SizedBox(
                  height: 180,
                  child: _expanded
                      ? FlutterMap(
                          mapController: widget.mapController,
                          options: MapOptions(
                            initialCenter: LatLng(
                              prefs?.latitude ?? -17.7833,
                              prefs?.longitude ?? -63.1821,
                            ),
                            initialZoom: 12,
                            interactionOptions: const InteractionOptions(
                              flags: InteractiveFlag.drag |
                                  InteractiveFlag.pinchZoom |
                                  InteractiveFlag.doubleTapZoom,
                            ),
                            onTap: (_, point) => widget.onTapMap(point),
                          ),
                          children: [
                            MapBaseStyle.calles.toLayer(),
                            if (prefs?.hasLocation == true)
                              MarkerLayer(
                                markers: [
                                  Marker(
                                    point: LatLng(
                                      prefs!.latitude!,
                                      prefs.longitude!,
                                    ),
                                    width: 40,
                                    height: 40,
                                    child: Icon(
                                      Icons.location_on,
                                      color: scheme.primary,
                                      size: 36,
                                    ),
                                  ),
                                ],
                              ),
                          ],
                        )
                      : ColoredBox(color: scheme.surfaceContainerHighest),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                prefs?.hasLocation == true
                    ? 'Lat ${prefs!.latitude!.toStringAsFixed(5)} · Lon ${prefs.longitude!.toStringAsFixed(5)}'
                    : 'Sin ubicación guardada',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton(
                  onPressed:
                      prefs?.hasLocation == true ? widget.onClearLocation : null,
                  child: const Text('Quitar ubicación'),
                ),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Avisos por correo'),
                value: prefs?.notifyEmail ?? false,
                onChanged: prefs?.hasLocation == true && prefs != null
                    ? (value) => widget.onPrefsChanged(
                          prefs.copyWith(notifyEmail: value),
                        )
                    : null,
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('WhatsApp'),
                value: prefs?.notifyWhatsapp ?? false,
                onChanged: prefs?.hasLocation == true && prefs != null
                    ? (value) => widget.onPrefsChanged(
                          prefs.copyWith(notifyWhatsapp: value),
                        )
                    : null,
              ),
              if (prefs?.notifyWhatsapp == true)
                TextFormField(
                  controller: widget.whatsapp,
                  decoration: const InputDecoration(
                    labelText: 'Destino WhatsApp',
                  ),
                ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Telegram'),
                value: prefs?.notifyTelegram ?? false,
                onChanged: prefs?.hasLocation == true && prefs != null
                    ? (value) => widget.onPrefsChanged(
                          prefs.copyWith(notifyTelegram: value),
                        )
                    : null,
              ),
              if (prefs?.notifyTelegram == true)
                TextFormField(
                  controller: widget.telegram,
                  decoration: const InputDecoration(
                    labelText: 'Destino Telegram',
                  ),
                ),
              const SizedBox(height: 8),
              FilledButton(
                onPressed:
                    widget.savingPrefs ? null : widget.onSavePreferences,
                child: widget.savingPrefs
                    ? const SizedBox.square(
                        dimension: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Guardar preferencias'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
