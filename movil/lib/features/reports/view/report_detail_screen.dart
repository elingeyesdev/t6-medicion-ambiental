import 'dart:io';

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/formatters/bolivia_time.dart';
import '../../../core/network/geocruz_api_client.dart';
import '../../../uikits/uikits.dart';
import '../model/report_models.dart';
import '../model/report_repository.dart';

class ReportDetailScreen extends StatefulWidget {
  const ReportDetailScreen({
    required this.api,
    required this.reportId,
    required this.canDownload,
    required this.onUnauthorized,
    super.key,
  });

  final GeoCruzApiClient api;
  final String reportId;
  final bool canDownload;
  final VoidCallback onUnauthorized;

  @override
  State<ReportDetailScreen> createState() => _ReportDetailScreenState();
}

class _ReportDetailScreenState extends State<ReportDetailScreen> {
  late final ReportRepository _repository;
  ReportDetail? _detail;
  bool _loading = true;
  bool _downloadingPdf = false;
  bool _downloadingXlsx = false;
  String? _error;
  String? _message;

  @override
  void initState() {
    super.initState();
    _repository = ReportRepository(widget.api);
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final detail = await _repository.get(widget.reportId);
      if (!mounted) return;
      setState(() {
        _detail = detail;
        _loading = false;
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      setState(() {
        _loading = false;
        _error = error.statusCode == 403
            ? 'No tienes permiso para ver este reporte.'
            : error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'No se pudo cargar el detalle del reporte.';
      });
    }
  }

  Future<void> _download(String format) async {
    final detail = _detail;
    if (detail == null || !widget.canDownload) return;
    setState(() {
      if (format == 'pdf') {
        _downloadingPdf = true;
      } else {
        _downloadingXlsx = true;
      }
      _error = null;
      _message = null;
    });
    try {
      final bytes = await _repository.download(detail.id, format);
      final dir = await getApplicationDocumentsDirectory();
      final safeName = detail.title
          .toLowerCase()
          .replaceAll(RegExp(r'[^a-z0-9áéíóúñ]+', caseSensitive: false), '-')
          .replaceAll(RegExp(r'-+'), '-')
          .replaceAll(RegExp(r'^-|-$'), '');
      final fileName =
          'geosur-${safeName.isEmpty ? detail.id : safeName}.$format';
      final file = File('${dir.path}${Platform.pathSeparator}$fileName');
      await file.writeAsBytes(bytes, flush: true);
      final opened = await OpenFilex.open(file.path);
      if (!mounted) return;
      setState(() {
        _downloadingPdf = false;
        _downloadingXlsx = false;
        _message = opened.type == ResultType.done
            ? 'Archivo guardado y abierto: ${file.path}'
            : 'Archivo guardado en ${file.path}'
                '${opened.message.isNotEmpty ? ' (${opened.message})' : ''}';
      });
    } on GeoCruzApiException catch (error) {
      if (!mounted) return;
      if (error.statusCode == 401) {
        widget.onUnauthorized();
        return;
      }
      setState(() {
        _downloadingPdf = false;
        _downloadingXlsx = false;
        _error = error.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _downloadingPdf = false;
        _downloadingXlsx = false;
        _error = 'No se pudo descargar el archivo.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final detail = _detail;
    return Scaffold(
      appBar: AppBar(
        title: Text(detail?.title ?? 'Reporte'),
        actions: [
          IconButton(
            tooltip: 'Actualizar',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: _loading && detail == null
          ? const Center(child: CircularProgressIndicator())
          : detail == null
              ? Padding(
                  padding: const EdgeInsets.all(20),
                  child: UiKitSectionCard(
                    title: 'No disponible',
                    icon: Icons.error_outline,
                    child: Text(_error ?? 'No se encontró el reporte.'),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                    children: [
                      if (_error != null || _message != null) ...[
                        _StatusBanner(
                          message: _error ?? _message!,
                          isError: _error != null,
                        ),
                        const SizedBox(height: 12),
                      ],
                      UiKitSectionCard(
                        title: detail.title,
                        subtitle: detail.createdAt != null
                            ? BoliviaTime.dateTime(detail.createdAt!)
                            : 'Sin fecha',
                        icon: Icons.description_outlined,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(detail.summaryLine),
                            const SizedBox(height: 8),
                            Text(
                              'Identificador: ${detail.id}',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      UiKitSectionCard(
                        title: 'Parámetros',
                        icon: Icons.tune,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            for (final entry in detail.parameters.entries.take(12))
                              Padding(
                                padding: const EdgeInsets.only(bottom: 6),
                                child: Text('${entry.key}: ${entry.value}'),
                              ),
                            if (detail.parameters.isEmpty)
                              const Text('Sin parámetros guardados.'),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      UiKitSectionCard(
                        title: 'Descargas',
                        subtitle: widget.canDownload
                            ? 'PDF y Excel del corte guardado'
                            : 'Sin permiso reportes.ver',
                        icon: Icons.download_outlined,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            FilledButton.icon(
                              onPressed: !widget.canDownload ||
                                      _downloadingPdf ||
                                      _downloadingXlsx
                                  ? null
                                  : () => _download('pdf'),
                              icon: _downloadingPdf
                                  ? const SizedBox.square(
                                      dimension: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.picture_as_pdf_outlined),
                              label: const Text('Descargar PDF'),
                            ),
                            const SizedBox(height: 8),
                            OutlinedButton.icon(
                              onPressed: !widget.canDownload ||
                                      _downloadingPdf ||
                                      _downloadingXlsx
                                  ? null
                                  : () => _download('xlsx'),
                              icon: _downloadingXlsx
                                  ? const SizedBox.square(
                                      dimension: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.table_view_outlined),
                              label: const Text('Descargar Excel'),
                            ),
                          ],
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
