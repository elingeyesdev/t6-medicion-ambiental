import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/network/geocruz_api_client.dart';
import '../model/place_search_repository.dart';

/// Buscador de lugares (API `mapa/buscar`) — mapa completo #62 / #89.
class PlaceSearchSheet extends StatefulWidget {
  const PlaceSearchSheet({
    required this.api,
    this.near,
    super.key,
  });

  final GeoCruzApiClient api;
  final LatLng? near;

  @override
  State<PlaceSearchSheet> createState() => _PlaceSearchSheetState();
}

class _PlaceSearchSheetState extends State<PlaceSearchSheet> {
  late final PlaceSearchRepository _repository;
  final _query = TextEditingController();
  final _focus = FocusNode();
  List<PlaceSearchResult> _results = const [];
  bool _busy = false;
  bool _searched = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _repository = PlaceSearchRepository(widget.api);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _focus.requestFocus();
    });
  }

  @override
  void dispose() {
    _query.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    final text = _query.text.trim();
    if (text.length < 2) {
      setState(() {
        _error = 'Escribe al menos 2 caracteres.';
        _searched = false;
      });
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _results = const [];
      _searched = true;
    });
    try {
      final near = widget.near;
      final results = await _repository.search(
        text,
        latitude: near?.latitude,
        longitude: near?.longitude,
      );
      if (!mounted) return;
      setState(() {
        _results = results;
        if (results.isEmpty) {
          _error = 'Sin resultados. Prueba otro nombre.';
        }
      });
    } on GeoCruzApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'No se pudo buscar ahora. Revisa la conexión.');
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final maxListHeight = MediaQuery.sizeOf(context).height * 0.45;
    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 8,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 16,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Buscar lugar', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(
            'Ciudad o sitio · se centra el mapa al elegir',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _query,
            focusNode: _focus,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => _search(),
            decoration: InputDecoration(
              hintText: 'Ej. Santa Cruz, La Paz…',
              border: const OutlineInputBorder(),
              suffixIcon: IconButton(
                onPressed: _busy ? null : _search,
                icon: _busy
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.search),
              ),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ],
          const SizedBox(height: 8),
          ConstrainedBox(
            constraints: BoxConstraints(maxHeight: maxListHeight),
            child: _busy && _results.isEmpty
                ? const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Center(child: CircularProgressIndicator()),
                  )
                : _results.isEmpty
                ? Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    child: Text(
                      _searched
                          ? 'No hay coincidencias.'
                          : 'Escribe y pulsa buscar.',
                      style: Theme.of(context).textTheme.bodyMedium,
                      textAlign: TextAlign.center,
                    ),
                  )
                : ListView.separated(
                    shrinkWrap: true,
                    itemCount: _results.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final item = _results[index];
                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.place_outlined),
                        title: Text(item.name),
                        subtitle: Text(
                          item.description,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        onTap: () => Navigator.pop(context, item),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
